<?php

namespace Sultana\Admin\Reviews;

use Sultana\Admin\Core\Router;
use WP_Comment;
use WP_Comment_Query;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ReviewService
{
    public const PER_PAGE = 20;

    private const FILTER_STATUS_MAP = [
        ''         => 'all',
        'pending'  => 'hold',
        'approved' => 'approve',
        'spam'     => 'spam',
        'trash'    => 'trash',
    ];

    public function status_options(): array
    {
        return [
            ''         => __( 'Todas', 'sultana-admin' ),
            'pending'  => __( 'Pendientes', 'sultana-admin' ),
            'approved' => __( 'Aprobadas', 'sultana-admin' ),
            'spam'     => __( 'Spam', 'sultana-admin' ),
            'trash'    => __( 'Papelera', 'sultana-admin' ),
        ];
    }

    public function normalize_filter_status( string $status ): string
    {
        $status = sanitize_key( $status );

        return array_key_exists( $status, self::FILTER_STATUS_MAP ) ? $status : '';
    }

    public function list_reviews( array $args ): array
    {
        $search   = isset( $args['search'] ) ? trim( (string) $args['search'] ) : '';
        $status   = $this->normalize_filter_status( (string) ( $args['status'] ?? '' ) );
        $page     = isset( $args['page'] ) ? max( 1, absint( $args['page'] ) ) : 1;
        $per_page = isset( $args['per_page'] ) ? max( 1, min( 50, absint( $args['per_page'] ) ) ) : self::PER_PAGE;
        $base     = [
            'type'       => 'review',
            'post_type'  => 'product',
            'status'     => self::FILTER_STATUS_MAP[ $status ],
            'orderby'    => 'comment_date_gmt',
            'order'      => 'DESC',
            'hierarchical' => false,
        ];

        if ( '' !== $search ) {
            $base['search'] = $search;
        }

        $count_query = new WP_Comment_Query();
        $total       = absint( $count_query->query( array_merge( $base, [ 'count' => true ] ) ) );
        $total_pages = max( 1, (int) ceil( $total / $per_page ) );
        $page        = min( $page, $total_pages );
        $query       = new WP_Comment_Query();
        $comments    = $query->query(
            array_merge(
                $base,
                [
                    'number' => $per_page,
                    'offset' => ( $page - 1 ) * $per_page,
                ]
            )
        );
        $comments    = is_array( $comments ) ? array_filter( $comments, static fn ( $comment ): bool => $comment instanceof WP_Comment ) : [];

        if ( ! empty( $comments ) ) {
            update_meta_cache( 'comment', array_map( static fn ( WP_Comment $comment ): int => absint( $comment->comment_ID ), $comments ) );
        }

        $product_titles = $this->product_titles( $comments );

        return [
            'reviews'     => array_map( fn ( WP_Comment $comment ): array => $this->review_row( $comment, $product_titles ), $comments ),
            'page'        => min( $page, $total_pages ),
            'per_page'    => $per_page,
            'total'       => $total,
            'total_pages' => $total_pages,
        ];
    }

    public function approve_review( int $review_id ): array
    {
        return $this->set_status( $review_id, 'approve', __( 'Reseña aprobada correctamente.', 'sultana-admin' ) );
    }

    public function hold_review( int $review_id ): array
    {
        return $this->set_status( $review_id, 'hold', __( 'Reseña marcada como pendiente.', 'sultana-admin' ) );
    }

    public function spam_review( int $review_id ): array
    {
        return $this->call_comment_action( $review_id, 'wp_spam_comment', __( 'Reseña marcada como spam.', 'sultana-admin' ) );
    }

    public function unspam_review( int $review_id ): array
    {
        return $this->call_comment_action( $review_id, 'wp_unspam_comment', __( 'Reseña restaurada desde spam.', 'sultana-admin' ) );
    }

    public function trash_review( int $review_id ): array
    {
        return $this->call_comment_action( $review_id, 'wp_trash_comment', __( 'Reseña enviada a la papelera.', 'sultana-admin' ) );
    }

    public function restore_review( int $review_id ): array
    {
        return $this->call_comment_action( $review_id, 'wp_untrash_comment', __( 'Reseña restaurada desde la papelera.', 'sultana-admin' ) );
    }

    public function delete_review( int $review_id ): array
    {
        $review = $this->get_product_review( $review_id );

        if ( ! $review ) {
            return $this->error_result( __( 'La reseña no existe.', 'sultana-admin' ) );
        }

        if ( ! $this->can_delete_review( $review ) ) {
            return $this->error_result( __( 'No tienes permisos para eliminar esta reseña.', 'sultana-admin' ) );
        }

        $product_id = absint( $review->comment_post_ID );
        $deleted    = wp_delete_comment( $review->comment_ID, true );

        if ( ! $deleted ) {
            return $this->error_result( __( 'No se pudo eliminar la reseña.', 'sultana-admin' ) );
        }

        $this->clear_product_review_cache( $product_id );

        return $this->success_result( __( 'Reseña eliminada permanentemente.', 'sultana-admin' ) );
    }

    public function update_review( int $review_id, array $data ): array
    {
        $review = $this->get_product_review( $review_id );

        if ( ! $review ) {
            return $this->error_result( __( 'La reseña no existe.', 'sultana-admin' ) );
        }

        if ( ! $this->can_edit_review( $review ) ) {
            return $this->error_result( __( 'No tienes permisos para editar esta reseña.', 'sultana-admin' ) );
        }

        $author  = trim( sanitize_text_field( (string) ( $data['author'] ?? '' ) ) );
        $email   = sanitize_email( (string) ( $data['email'] ?? '' ) );
        $content = trim( sanitize_textarea_field( (string) ( $data['content'] ?? '' ) ) );
        $rating  = absint( $data['rating'] ?? 0 );
        $errors  = [];

        if ( '' === $author ) {
            $errors[] = __( 'El nombre de la reseña es obligatorio.', 'sultana-admin' );
        }

        if ( '' !== $email && ! is_email( $email ) ) {
            $errors[] = __( 'El email de la reseña no es válido.', 'sultana-admin' );
        }

        if ( '' === $content ) {
            $errors[] = __( 'El contenido de la reseña es obligatorio.', 'sultana-admin' );
        }

        if ( $rating < 1 || $rating > 5 ) {
            $errors[] = __( 'La calificación debe estar entre 1 y 5.', 'sultana-admin' );
        }

        if ( ! empty( $errors ) ) {
            return $this->error_result( implode( ' ', $errors ) );
        }

        $updated = wp_update_comment(
            [
                'comment_ID'           => $review->comment_ID,
                'comment_author'       => $author,
                'comment_author_email' => $email,
                'comment_content'      => $content,
            ],
            true
        );

        if ( is_wp_error( $updated ) || false === $updated ) {
            return $this->error_result( __( 'No se pudo actualizar la reseña.', 'sultana-admin' ) );
        }

        update_comment_meta( $review->comment_ID, 'rating', $rating );
        $this->clear_product_review_cache( absint( $review->comment_post_ID ) );

        return $this->success_result( __( 'Reseña actualizada correctamente.', 'sultana-admin' ) );
    }

    public function reply_review( int $review_id, string $content ): array
    {
        $review = $this->get_product_review( $review_id );

        if ( ! $review ) {
            return $this->error_result( __( 'La reseña no existe.', 'sultana-admin' ) );
        }

        if ( ! $this->can_moderate_reviews() ) {
            return $this->error_result( __( 'No tienes permisos para responder reseñas.', 'sultana-admin' ) );
        }

        $content = trim( sanitize_textarea_field( $content ) );

        if ( '' === $content ) {
            return $this->error_result( __( 'La respuesta no puede estar vacía.', 'sultana-admin' ) );
        }

        $user       = wp_get_current_user();
        $comment_id = wp_insert_comment(
            [
                'comment_post_ID'      => absint( $review->comment_post_ID ),
                'comment_parent'       => absint( $review->comment_ID ),
                'comment_author'       => $user->display_name ?: $user->user_login,
                'comment_author_email' => $user->user_email,
                'comment_content'      => $content,
                'comment_type'         => 'comment',
                'comment_approved'     => 1,
                'user_id'              => absint( $user->ID ),
                'comment_date'         => current_time( 'mysql' ),
                'comment_date_gmt'     => current_time( 'mysql', true ),
            ]
        );

        if ( ! $comment_id ) {
            return $this->error_result( __( 'No se pudo guardar la respuesta.', 'sultana-admin' ) );
        }

        $this->clear_product_review_cache( absint( $review->comment_post_ID ) );

        return $this->success_result( __( 'Respuesta publicada correctamente.', 'sultana-admin' ) );
    }

    private function review_row( WP_Comment $comment, array $product_titles ): array
    {
        $review_id     = absint( $comment->comment_ID );
        $product_id    = absint( $comment->comment_post_ID );
        $rating        = max( 0, min( 5, absint( get_comment_meta( $review_id, 'rating', true ) ) ) );
        $status        = $this->comment_status_key( (string) $comment->comment_approved );
        $product_title = $product_titles[ $product_id ] ?? __( 'Producto eliminado', 'sultana-admin' );

        return [
            'id'             => $review_id,
            'product_id'     => $product_id,
            'product_title'  => $product_title,
            'product_url'    => add_query_arg( 'product_id', $product_id, Router::products_url() ),
            'author'         => (string) $comment->comment_author,
            'email'          => (string) $comment->comment_author_email,
            'content'        => (string) $comment->comment_content,
            'excerpt'        => wp_trim_words( wp_strip_all_tags( (string) $comment->comment_content ), 20, '...' ),
            'rating'         => $rating,
            'date'           => $this->format_comment_date( $comment ),
            'status'         => $status,
            'status_label'   => $this->status_label( $status ),
            'can_approve'    => $this->can_moderate_reviews() && 'approved' !== $status,
            'can_hold'       => $this->can_moderate_reviews() && 'pending' !== $status && ! in_array( $status, [ 'spam', 'trash' ], true ),
            'can_spam'       => $this->can_moderate_reviews() && 'spam' !== $status && 'trash' !== $status,
            'can_unspam'     => $this->can_moderate_reviews() && 'spam' === $status,
            'can_trash'      => $this->can_moderate_reviews() && 'trash' !== $status,
            'can_restore'    => $this->can_moderate_reviews() && 'trash' === $status,
            'can_delete'     => $this->can_delete_review( $comment ),
            'can_edit'       => $this->can_edit_review( $comment ),
            'can_reply'      => $this->can_moderate_reviews(),
        ];
    }

    private function get_product_review( int $review_id ): ?WP_Comment
    {
        if ( $review_id <= 0 ) {
            return null;
        }

        $comment = get_comment( $review_id );

        if ( ! $comment instanceof WP_Comment ) {
            return null;
        }

        if ( 'review' !== (string) $comment->comment_type || 'product' !== get_post_type( absint( $comment->comment_post_ID ) ) ) {
            return null;
        }

        return $comment;
    }

    private function set_status( int $review_id, string $status, string $message ): array
    {
        $review = $this->get_product_review( $review_id );

        if ( ! $review ) {
            return $this->error_result( __( 'La reseña no existe.', 'sultana-admin' ) );
        }

        if ( ! $this->can_moderate_reviews() ) {
            return $this->error_result( __( 'No tienes permisos para moderar reseñas.', 'sultana-admin' ) );
        }

        $updated = wp_set_comment_status( $review->comment_ID, $status );

        if ( ! $updated ) {
            return $this->error_result( __( 'No se pudo actualizar el estado de la reseña.', 'sultana-admin' ) );
        }

        $this->clear_product_review_cache( absint( $review->comment_post_ID ) );

        return $this->success_result( $message );
    }

    private function call_comment_action( int $review_id, string $function_name, string $message ): array
    {
        $review = $this->get_product_review( $review_id );

        if ( ! $review ) {
            return $this->error_result( __( 'La reseña no existe.', 'sultana-admin' ) );
        }

        if ( ! $this->can_moderate_reviews() ) {
            return $this->error_result( __( 'No tienes permisos para moderar reseñas.', 'sultana-admin' ) );
        }

        if ( ! function_exists( $function_name ) || ! $function_name( $review->comment_ID ) ) {
            return $this->error_result( __( 'No se pudo actualizar el estado de la reseña.', 'sultana-admin' ) );
        }

        $this->clear_product_review_cache( absint( $review->comment_post_ID ) );

        return $this->success_result( $message );
    }

    private function product_titles( array $comments ): array
    {
        $product_ids = array_values(
            array_unique(
                array_filter(
                    array_map( static fn ( WP_Comment $comment ): int => absint( $comment->comment_post_ID ), $comments )
                )
            )
        );

        if ( empty( $product_ids ) ) {
            return [];
        }

        $posts  = get_posts(
            [
                'post_type'      => 'product',
                'post_status'    => 'any',
                'post__in'       => $product_ids,
                'posts_per_page' => count( $product_ids ),
                'orderby'        => 'post__in',
            ]
        );
        $titles = [];

        foreach ( $posts as $post ) {
            $titles[ absint( $post->ID ) ] = get_the_title( $post );
        }

        return $titles;
    }

    private function format_comment_date( WP_Comment $comment ): string
    {
        $timestamp = strtotime( (string) $comment->comment_date_gmt . ' UTC' );

        if ( ! $timestamp ) {
            $timestamp = strtotime( (string) $comment->comment_date );
        }

        return wp_date( get_option( 'date_format' ) . ' · ' . get_option( 'time_format' ), $timestamp ?: time(), wp_timezone() );
    }

    private function comment_status_key( string $approved ): string
    {
        if ( '1' === $approved ) {
            return 'approved';
        }

        if ( '0' === $approved ) {
            return 'pending';
        }

        if ( 'spam' === $approved ) {
            return 'spam';
        }

        if ( 'trash' === $approved ) {
            return 'trash';
        }

        return 'pending';
    }

    private function status_label( string $status ): string
    {
        $labels = [
            'approved' => __( 'Aprobada', 'sultana-admin' ),
            'pending'  => __( 'Pendiente', 'sultana-admin' ),
            'spam'     => __( 'Spam', 'sultana-admin' ),
            'trash'    => __( 'Papelera', 'sultana-admin' ),
        ];

        return $labels[ $status ] ?? $labels['pending'];
    }

    private function can_moderate_reviews(): bool
    {
        return current_user_can( 'moderate_comments' );
    }

    private function can_edit_review( WP_Comment $review ): bool
    {
        return current_user_can( 'edit_comment', $review->comment_ID ) || $this->can_moderate_reviews();
    }

    private function can_delete_review( WP_Comment $review ): bool
    {
        return current_user_can( 'delete_comment', $review->comment_ID ) || $this->can_moderate_reviews();
    }

    private function clear_product_review_cache( int $product_id ): void
    {
        if ( $product_id <= 0 ) {
            return;
        }

        if ( function_exists( 'wc_delete_product_transients' ) ) {
            wc_delete_product_transients( $product_id );
        }

        if ( class_exists( '\WC_Comments' ) && method_exists( '\WC_Comments', 'clear_transients' ) ) {
            \WC_Comments::clear_transients( $product_id );
        }
    }

    private function success_result( string $message ): array
    {
        return [
            'success' => true,
            'message' => $message,
            'errors'  => [],
        ];
    }

    private function error_result( string $message ): array
    {
        return [
            'success' => false,
            'message' => '',
            'errors'  => [ $message ],
        ];
    }
}

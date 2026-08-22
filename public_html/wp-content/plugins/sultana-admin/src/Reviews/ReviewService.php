<?php

namespace Sultana\Admin\Reviews;

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
        'trash'    => 'trash',
    ];

    public function status_options(): array
    {
        return [
            ''         => __( 'Todas', 'sultana-admin' ),
            'pending'  => __( 'Pendientes', 'sultana-admin' ),
            'approved' => __( 'Aprobadas', 'sultana-admin' ),
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
            'type'         => 'review',
            'post_type'    => 'product',
            'status'       => self::FILTER_STATUS_MAP[ $status ],
            'orderby'      => 'comment_date_gmt',
            'order'        => 'DESC',
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
            'page'        => $page,
            'per_page'    => $per_page,
            'total'       => $total,
            'total_pages' => $total_pages,
        ];
    }

    public function approve_review( int $review_id ): array
    {
        return $this->set_status( $review_id, 'approve', __( 'Reseña aprobada correctamente.', 'sultana-admin' ) );
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

    private function review_row( WP_Comment $comment, array $product_titles ): array
    {
        $review_id     = absint( $comment->comment_ID );
        $product_id    = absint( $comment->comment_post_ID );
        $rating        = max( 0, min( 5, absint( get_comment_meta( $review_id, 'rating', true ) ) ) );
        $status        = $this->comment_status_key( (string) $comment->comment_approved );
        $product_title = $product_titles[ $product_id ] ?? __( 'Producto eliminado', 'sultana-admin' );

        return [
            'id'            => $review_id,
            'product_id'    => $product_id,
            'product_title' => $product_title,
            'author'        => (string) $comment->comment_author,
            'email'         => (string) $comment->comment_author_email,
            'content'       => (string) $comment->comment_content,
            'rating'        => $rating,
            'date'          => $this->format_comment_date( $comment ),
            'status'        => $status,
            'status_label'  => $this->status_label( $status ),
            'can_approve'   => $this->can_moderate_reviews() && 'pending' === $status,
            'can_trash'     => $this->can_moderate_reviews() && 'trash' !== $status,
            'can_restore'   => $this->can_moderate_reviews() && 'trash' === $status,
            'can_delete'    => $this->can_delete_review( $comment ),
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
            'trash'    => __( 'Papelera', 'sultana-admin' ),
        ];

        return $labels[ $status ] ?? $labels['pending'];
    }

    private function can_moderate_reviews(): bool
    {
        return current_user_can( 'moderate_comments' );
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

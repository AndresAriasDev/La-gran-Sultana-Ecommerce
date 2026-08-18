<?php

namespace Sultana\CommerceCore\Modules\Reviews;

use WP_Comment;
use WP_Error;
use WP_User;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ProductReviews
{
    public static function register(): void
    {
        add_action( 'admin_post_scc_save_product_review', [ self::class, 'handle_save' ] );
    }

    public static function handle_save(): void
    {
        if ( ! is_user_logged_in() ) {
            wp_safe_redirect( wp_login_url( wp_get_referer() ?: home_url( '/' ) ) );
            exit;
        }

        check_admin_referer( 'scc_save_product_review', 'scc_review_nonce' );

        $product_id = isset( $_POST['comment_post_ID'] ) ? absint( $_POST['comment_post_ID'] ) : 0;
        $product    = $product_id > 0 && function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;

        if ( ! $product ) {
            wp_safe_redirect( home_url( '/' ) );
            exit;
        }

        $rating  = isset( $_POST['rating'] ) ? absint( $_POST['rating'] ) : 0;
        $content = isset( $_POST['comment'] ) ? trim( wp_unslash( (string) $_POST['comment'] ) ) : '';

        if ( $rating < 1 || $rating > 5 || '' === $content ) {
            wp_safe_redirect( self::product_reviews_url( $product_id ) );
            exit;
        }

        $user      = wp_get_current_user();
        $review_id = isset( $_POST['scc_review_id'] ) ? absint( $_POST['scc_review_id'] ) : 0;
        $review    = $review_id > 0 ? get_comment( $review_id ) : self::find_user_review( $product_id, $user->ID );

        if ( $review instanceof WP_Comment ) {
            self::update_review( $review, $product_id, $user, $rating, $content );
        } else {
            self::create_review( $product_id, $user, $rating, $content );
        }

        wp_safe_redirect( self::product_reviews_url( $product_id ) );
        exit;
    }

    private static function find_user_review( int $product_id, int $user_id ): ?WP_Comment
    {
        $reviews = get_comments(
            [
                'post_id' => $product_id,
                'status'  => [ 'approve', 'hold' ],
                'type'    => 'review',
                'user_id' => $user_id,
                'number'  => 1,
                'orderby' => 'comment_date_gmt',
                'order'   => 'DESC',
            ]
        );

        return isset( $reviews[0] ) && $reviews[0] instanceof WP_Comment ? $reviews[0] : null;
    }

    private static function update_review( WP_Comment $review, int $product_id, WP_User $user, int $rating, string $content ): void
    {
        if (
            (int) $review->comment_post_ID !== $product_id
            || (int) $review->user_id !== $user->ID
            || 'review' !== $review->comment_type
        ) {
            return;
        }

        wp_update_comment(
            [
                'comment_ID'       => (int) $review->comment_ID,
                'comment_content'  => wp_kses_post( $content ),
                'comment_approved' => '0',
            ]
        );

        update_comment_meta( (int) $review->comment_ID, 'rating', $rating );
        self::clear_product_review_cache( $product_id );
    }

    private static function create_review( int $product_id, WP_User $user, int $rating, string $content ): void
    {
        $comment_id = wp_new_comment(
            [
                'comment_post_ID'      => $product_id,
                'comment_author'       => $user->display_name ?: $user->user_login,
                'comment_author_email' => $user->user_email,
                'comment_author_url'   => '',
                'comment_content'      => wp_kses_post( $content ),
                'comment_type'         => 'review',
                'user_id'              => $user->ID,
                'comment_meta'         => [
                    'rating' => $rating,
                ],
            ],
            true
        );

        if ( ! $comment_id instanceof WP_Error ) {
            self::clear_product_review_cache( $product_id );
        }
    }

    private static function clear_product_review_cache( int $product_id ): void
    {
        clean_post_cache( $product_id );

        if ( function_exists( 'wc_delete_product_transients' ) ) {
            wc_delete_product_transients( $product_id );
        }
    }

    private static function product_reviews_url( int $product_id ): string
    {
        return get_permalink( $product_id ) . '#reviews';
    }
}

<?php

namespace Sultana\Admin\Reviews;

use Sultana\Admin\Core\Capabilities;
use Sultana\Admin\Core\Router;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ReviewController
{
    public const ACTION_NONCE_ACTION = 'sultana_admin_review_action';

    public static function prepare_list_screen(): array
    {
        $service = new ReviewService();
        $search  = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
        $search  = substr( trim( $search ), 0, 120 );
        $status  = $service->normalize_filter_status( isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '' );
        $page    = isset( $_GET['review_page'] ) ? absint( wp_unslash( $_GET['review_page'] ) ) : 1;
        $page    = max( 1, min( 500, $page ) );

        self::handle_action_request( $service, $search, $status, $page );

        $listing  = $service->list_reviews(
            [
                'search'   => $search,
                'status'   => $status,
                'page'     => $page,
                'per_page' => ReviewService::PER_PAGE,
            ]
        );
        $feedback = self::pull_feedback();

        return [
            'search'         => $search,
            'status'         => $status,
            'status_options' => $service->status_options(),
            'page'           => $listing['page'],
            'per_page'       => $listing['per_page'],
            'total'          => $listing['total'],
            'total_pages'    => $listing['total_pages'],
            'reviews'        => $listing['reviews'],
            'pagination'     => self::pagination_links( $listing['page'], $listing['total_pages'], $search, $status ),
            'notice'         => $feedback['notice'] ?? '',
            'errors'         => $feedback['errors'] ?? [],
            'has_filters'    => '' !== $search || '' !== $status,
        ];
    }

    private static function handle_action_request( ReviewService $service, string $search, string $status, int $page ): void
    {
        if ( 'POST' !== strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) {
            return;
        }

        $action = isset( $_POST['sultana_admin_action'] ) ? sanitize_key( wp_unslash( $_POST['sultana_admin_action'] ) ) : '';

        if ( '' === $action ) {
            return;
        }

        $result = self::dispatch_action( $service, $action );

        self::store_feedback( $result );
        wp_safe_redirect( self::filtered_url( $search, $status, $page ) );
        exit;
    }

    private static function dispatch_action( ReviewService $service, string $action ): array
    {
        if ( ! is_user_logged_in() || ! current_user_can( Capabilities::ACCESS_CAPABILITY ) ) {
            return [
                'success' => false,
                'errors'  => [ __( 'No tienes permisos para gestionar reseñas.', 'sultana-admin' ) ],
            ];
        }

        if ( ! self::verify_nonce() ) {
            return [
                'success' => false,
                'errors'  => [ __( 'No se pudo validar la solicitud. Intenta nuevamente.', 'sultana-admin' ) ],
            ];
        }

        $review_id = isset( $_POST['review_id'] ) ? absint( wp_unslash( $_POST['review_id'] ) ) : 0;

        switch ( $action ) {
            case 'approve_review':
                return $service->approve_review( $review_id );
            case 'trash_review':
                return $service->trash_review( $review_id );
            case 'restore_review':
                return $service->restore_review( $review_id );
            case 'delete_review':
                return $service->delete_review( $review_id );
        }

        return [
            'success' => false,
            'errors'  => [ __( 'Accion de reseña no valida.', 'sultana-admin' ) ],
        ];
    }

    private static function verify_nonce(): bool
    {
        $nonce = isset( $_POST['sultana_admin_review_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['sultana_admin_review_nonce'] ) ) : '';

        return (bool) wp_verify_nonce( $nonce, self::ACTION_NONCE_ACTION );
    }

    private static function pagination_links( int $page, int $total_pages, string $search, string $status ): array
    {
        $page_url = static function ( int $target_page ) use ( $search, $status ): string {
            return self::filtered_url( $search, $status, $target_page );
        };

        return [
            'previous' => $page > 1 ? $page_url( $page - 1 ) : '',
            'next'     => $page < $total_pages ? $page_url( $page + 1 ) : '',
            'items'    => self::pagination_items( $page, $total_pages, $page_url ),
        ];
    }

    private static function pagination_items( int $page, int $total_pages, callable $page_url ): array
    {
        if ( $total_pages <= 1 ) {
            return [];
        }

        if ( $total_pages <= 7 ) {
            $pages = range( 1, $total_pages );
        } else {
            $start = max( 2, $page - 2 );
            $end   = min( $total_pages - 1, $page + 2 );

            if ( $page <= 3 ) {
                $end = min( $total_pages - 1, 5 );
            }

            if ( $page >= $total_pages - 2 ) {
                $start = max( 2, $total_pages - 4 );
            }

            $pages = [ 1 ];

            if ( $start > 2 ) {
                $pages[] = 'ellipsis';
            }

            foreach ( range( $start, $end ) as $number ) {
                $pages[] = $number;
            }

            if ( $end < $total_pages - 1 ) {
                $pages[] = 'ellipsis';
            }

            $pages[] = $total_pages;
        }

        return array_map(
            static function ( $item ) use ( $page, $page_url ): array {
                if ( 'ellipsis' === $item ) {
                    return [ 'type' => 'ellipsis' ];
                }

                $number = absint( $item );

                return [
                    'type'    => 'page',
                    'page'    => $number,
                    'url'     => $page_url( $number ),
                    'current' => $number === $page,
                ];
            },
            $pages
        );
    }

    private static function filtered_url( string $search, string $status, int $page = 1 ): string
    {
        $args = [];

        if ( '' !== $search ) {
            $args['s'] = $search;
        }

        if ( '' !== $status ) {
            $args['status'] = $status;
        }

        if ( $page > 1 ) {
            $args['review_page'] = $page;
        }

        return empty( $args ) ? Router::reviews_url() : add_query_arg( $args, Router::reviews_url() );
    }

    private static function store_feedback( array $result ): void
    {
        $key = self::feedback_key();

        if ( '' === $key ) {
            return;
        }

        set_transient(
            $key,
            [
                'notice' => ! empty( $result['success'] ) ? (string) ( $result['message'] ?? '' ) : '',
                'errors' => empty( $result['success'] ) ? array_values( array_filter( (array) ( $result['errors'] ?? [] ) ) ) : [],
            ],
            MINUTE_IN_SECONDS
        );
    }

    private static function pull_feedback(): array
    {
        $key = self::feedback_key();

        if ( '' === $key ) {
            return [ 'notice' => '', 'errors' => [] ];
        }

        $feedback = get_transient( $key );
        delete_transient( $key );

        if ( ! is_array( $feedback ) ) {
            return [ 'notice' => '', 'errors' => [] ];
        }

        return [
            'notice' => (string) ( $feedback['notice'] ?? '' ),
            'errors' => array_values( array_filter( (array) ( $feedback['errors'] ?? [] ) ) ),
        ];
    }

    private static function feedback_key(): string
    {
        $user_id = get_current_user_id();

        return $user_id > 0 ? 'sultana_admin_review_feedback_' . $user_id : '';
    }
}

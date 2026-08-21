<?php

namespace Sultana\Admin\Orders;

use Sultana\Admin\Core\Router;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OrderController
{
    public const STATUS_NONCE_FIELD = 'sultana_admin_order_status_nonce';

    public static function prepare_list_screen(): array
    {
        $search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
        $search = trim( $search );
        $search = substr( $search, 0, 120 );
        $page   = isset( $_GET['order_page'] ) ? absint( wp_unslash( $_GET['order_page'] ) ) : 1;
        $page   = max( 1, min( 500, $page ) );

        $service        = new OrderService();
        $status_options = $service->status_options();
        $status         = self::requested_status( $status_options );
        $listing        = $service->list_orders(
            [
                'search'   => $search,
                'status'   => $status,
                'page'     => $page,
                'per_page' => 20,
            ]
        );

        return [
            'search'         => $search,
            'status'         => $status,
            'status_options' => $status_options,
            'page'           => $listing['page'],
            'per_page'       => $listing['per_page'],
            'total'          => $listing['total'],
            'total_pages'    => $listing['total_pages'],
            'orders'         => $listing['orders'],
            'error'          => $listing['error'],
            'search_mode'    => $listing['search_mode'],
            'pagination'     => self::pagination_links( $listing['page'], $listing['total_pages'], $search, $status ),
            'has_filters'    => '' !== $search || '' !== $status,
        ];
    }

    public static function prepare_view_screen( int $order_id ): array
    {
        if ( $order_id <= 0 ) {
            return [
                'not_found' => true,
                'message'   => __( 'Pedido no encontrado.', 'sultana-admin' ),
            ];
        }

        $service = new OrderService();
        $result  = [];

        if ( 'POST' === strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) {
            $result = $service->handle_status_update( $order_id, $_POST );

            if ( ! empty( $result['redirect_url'] ) ) {
                wp_safe_redirect( $result['redirect_url'] );
                exit;
            }
        }

        $screen = $service->order_detail( $order_id );

        if ( ! empty( $result['error'] ) ) {
            $screen['status_error'] = $result['error'];
        }

        if ( ! empty( $result['forbidden'] ) ) {
            $screen['forbidden'] = true;
            $screen['message']   = $result['error'] ?? __( 'No tienes permisos para cambiar este pedido.', 'sultana-admin' );
        }

        return $screen;
    }

    private static function requested_status( array $status_options ): string
    {
        $status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
        $status = preg_replace( '/^wc-/', '', $status );
        $status = is_string( $status ) ? $status : '';

        return isset( $status_options[ $status ] ) ? $status : '';
    }

    private static function pagination_links( int $page, int $total_pages, string $search, string $status ): array
    {
        $base_args = [];

        if ( '' !== $search ) {
            $base_args['s'] = $search;
        }

        if ( '' !== $status ) {
            $base_args['status'] = $status;
        }

        $page_url = static function ( int $target_page ) use ( $base_args ): string {
            return add_query_arg( array_merge( $base_args, [ 'order_page' => $target_page ] ), Router::orders_url() );
        };

        return [
            'previous' => $page > 1 && $total_pages > 1
                ? $page_url( $page - 1 )
                : '',
            'next'     => $page < $total_pages
                ? $page_url( $page + 1 )
                : '',
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
                    return [
                        'type' => 'ellipsis',
                    ];
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
}

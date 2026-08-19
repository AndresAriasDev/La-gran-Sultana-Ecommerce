<?php

namespace Sultana\Admin\Orders;

use Sultana\Admin\Core\Router;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OrderController
{
    public static function prepare_list_screen(): array
    {
        $search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
        $search = trim( $search );
        $page   = isset( $_GET['order_page'] ) ? absint( wp_unslash( $_GET['order_page'] ) ) : 1;
        $page   = max( 1, $page );

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

        return $service->order_detail( $order_id );
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

        return [
            'previous' => $page > 1 && $total_pages > 1
                ? add_query_arg( array_merge( $base_args, [ 'order_page' => $page - 1 ] ), Router::orders_url() )
                : '',
            'next'     => $page < $total_pages
                ? add_query_arg( array_merge( $base_args, [ 'order_page' => $page + 1 ] ), Router::orders_url() )
                : '',
        ];
    }
}

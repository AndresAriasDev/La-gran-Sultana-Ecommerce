<?php

namespace Sultana\Admin\Customers;

use Sultana\Admin\Core\Router;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CustomerController
{
    public static function prepare_list_screen(): array
    {
        $search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
        $search = trim( $search );
        $search = substr( $search, 0, 120 );
        $page   = isset( $_GET['customer_page'] ) ? absint( wp_unslash( $_GET['customer_page'] ) ) : 1;
        $page   = max( 1, min( 500, $page ) );

        $service = new CustomerService();
        $listing = $service->list_customers(
            [
                'search'   => $search,
                'page'     => $page,
                'per_page' => 20,
            ]
        );

        return [
            'search'               => $search,
            'page'                 => $listing['page'],
            'per_page'             => $listing['per_page'],
            'total'                => $listing['total'],
            'total_pages'          => $listing['total_pages'],
            'customers'            => $listing['customers'],
            'error'                => $listing['error'],
            'pagination'           => self::pagination_links( $listing['page'], $listing['total_pages'], $search ),
            'has_filters'          => '' !== $search,
            'valid_order_statuses' => $service->valid_order_statuses(),
        ];
    }

    public static function prepare_view_screen( int $customer_id ): array
    {
        $orders_page = isset( $_GET['orders_page'] ) ? absint( wp_unslash( $_GET['orders_page'] ) ) : 1;
        $orders_page = max( 1, min( 500, $orders_page ) );

        $service = new CustomerService();
        $screen  = $service->customer_detail( $customer_id, $orders_page );
        $screen['valid_order_statuses'] = $service->valid_order_statuses();

        return $screen;
    }

    private static function pagination_links( int $page, int $total_pages, string $search ): array
    {
        $base_args = [];

        if ( '' !== $search ) {
            $base_args['s'] = $search;
        }

        $page_url = static function ( int $target_page ) use ( $base_args ): string {
            return add_query_arg( array_merge( $base_args, [ 'customer_page' => $target_page ] ), Router::customers_url() );
        };

        return [
            'previous' => $page > 1 && $total_pages > 1 ? $page_url( $page - 1 ) : '',
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
}

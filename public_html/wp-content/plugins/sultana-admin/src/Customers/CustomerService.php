<?php

namespace Sultana\Admin\Customers;

use Sultana\Admin\Core\Router;
use Throwable;
use WC_Order;
use WP_User;
use WP_User_Query;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CustomerService
{
    public function list_customers( array $args ): array
    {
        $search   = isset( $args['search'] ) ? trim( (string) $args['search'] ) : '';
        $page     = isset( $args['page'] ) ? max( 1, absint( $args['page'] ) ) : 1;
        $per_page = isset( $args['per_page'] ) ? max( 1, min( 50, absint( $args['per_page'] ) ) ) : 20;

        $query_args = [
            'number'  => $per_page,
            'paged'   => $page,
            'fields'  => 'all',
            'orderby' => 'registered',
            'order'   => 'DESC',
        ];

        if ( '' !== $search ) {
            $query_args = array_merge( $query_args, $this->search_query_args( $search ) );
        }

        try {
            $query = new WP_User_Query( $query_args );
        } catch ( Throwable $exception ) {
            return $this->empty_listing( $page, $per_page, __( 'No pudimos consultar clientes en este momento.', 'sultana-admin' ) );
        }

        $users       = array_values( array_filter( $query->get_results(), static fn ( $user ): bool => $user instanceof WP_User ) );
        $total       = absint( $query->get_total() );
        $total_pages = max( 1, (int) ceil( $total / $per_page ) );

        if ( $total > 0 && $page > $total_pages ) {
            return $this->list_customers(
                [
                    'search'   => $search,
                    'page'     => $total_pages,
                    'per_page' => $per_page,
                ]
            );
        }

        $metrics = $this->metrics_for_customer_ids( wp_list_pluck( $users, 'ID' ) );

        return [
            'customers'   => array_map( fn ( WP_User $user ): array => $this->format_customer_row( $user, $metrics[ $user->ID ] ?? $this->empty_metrics() ), $users ),
            'page'        => min( $page, $total_pages ),
            'per_page'    => $per_page,
            'total'       => $total,
            'total_pages' => $total_pages,
            'error'       => '',
        ];
    }

    public function customer_detail( int $customer_id, int $orders_page = 1 ): array
    {
        $customer_id = absint( $customer_id );

        if ( $customer_id <= 0 ) {
            return [
                'not_found' => true,
                'message'   => __( 'Cliente no encontrado.', 'sultana-admin' ),
            ];
        }

        $user = get_user_by( 'id', $customer_id );

        if ( ! $user instanceof WP_User ) {
            return [
                'not_found' => true,
                'message'   => __( 'Cliente no encontrado.', 'sultana-admin' ),
            ];
        }

        $metrics = $this->metrics_for_customer_ids( [ $customer_id ] );
        $metrics = $metrics[ $customer_id ] ?? $this->empty_metrics();
        $orders  = $this->customer_orders( $customer_id, max( 1, $orders_page ), 8 );

        return [
            'customer'         => $this->format_customer_detail( $user, $metrics ),
            'orders'           => $orders['orders'],
            'orders_page'      => $orders['page'],
            'orders_total'     => $orders['total'],
            'orders_pages'     => $orders['total_pages'],
            'orders_error'     => $orders['error'],
            'orders_pagination' => $this->orders_pagination_links( $customer_id, $orders['page'], $orders['total_pages'] ),
            'back_url'         => Router::customers_url(),
            'not_found'        => false,
        ];
    }

    public function valid_order_statuses(): array
    {
        return CustomerMetrics::VALID_ORDER_STATUSES;
    }

    private function search_query_args( string $search ): array
    {
        if ( preg_match( '/^[0-9+\-\s()]+$/', $search ) ) {
            return [
                'meta_query' => [
                    'relation' => 'OR',
                    [
                        'key'     => 'billing_phone',
                        'value'   => $search,
                        'compare' => 'LIKE',
                    ],
                    [
                        'key'     => 'shipping_phone',
                        'value'   => $search,
                        'compare' => 'LIKE',
                    ],
                ],
            ];
        }

        return [
            'search'         => '*' . esc_attr( $search ) . '*',
            'search_columns' => [ 'user_login', 'user_email', 'display_name' ],
        ];
    }

    private function metrics_for_customer_ids( array $customer_ids ): array
    {
        global $wpdb;

        $customer_ids = array_values( array_unique( array_filter( array_map( 'absint', $customer_ids ) ) ) );

        if ( empty( $customer_ids ) ) {
            return [];
        }

        $metrics = array_fill_keys( $customer_ids, $this->empty_metrics() );
        $statuses = array_map( static fn ( string $status ): string => 'wc-' . $status, CustomerMetrics::VALID_ORDER_STATUSES );

        if ( $this->uses_hpos() ) {
            $table = $wpdb->prefix . 'wc_orders';

            if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) {
                $placeholders = implode( ',', array_fill( 0, count( $customer_ids ), '%d' ) );
                $status_placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
                $sql = $wpdb->prepare(
                    "SELECT customer_id, COUNT(id) AS order_count, COALESCE(SUM(total_amount), 0) AS total_spent, MAX(date_created_gmt) AS last_order_date
                    FROM {$table}
                    WHERE type = 'shop_order'
                    AND customer_id IN ({$placeholders})
                    AND status IN ({$status_placeholders})
                    GROUP BY customer_id",
                    array_merge( $customer_ids, $statuses )
                );

                return $this->merge_metric_rows( $metrics, $wpdb->get_results( $sql, ARRAY_A ) );
            }
        }

        $post_placeholders = implode( ',', array_fill( 0, count( $customer_ids ), '%d' ) );
        $status_placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
        $sql = $wpdb->prepare(
            "SELECT customer_meta.meta_value AS customer_id, COUNT(posts.ID) AS order_count, COALESCE(SUM(total_meta.meta_value + 0), 0) AS total_spent, MAX(posts.post_date_gmt) AS last_order_date
            FROM {$wpdb->posts} posts
            INNER JOIN {$wpdb->postmeta} customer_meta ON customer_meta.post_id = posts.ID AND customer_meta.meta_key = '_customer_user'
            LEFT JOIN {$wpdb->postmeta} total_meta ON total_meta.post_id = posts.ID AND total_meta.meta_key = '_order_total'
            WHERE posts.post_type = 'shop_order'
            AND posts.post_status IN ({$status_placeholders})
            AND customer_meta.meta_value IN ({$post_placeholders})
            GROUP BY customer_meta.meta_value",
            array_merge( $statuses, $customer_ids )
        );

        return $this->merge_metric_rows( $metrics, $wpdb->get_results( $sql, ARRAY_A ) );
    }

    private function merge_metric_rows( array $metrics, $rows ): array
    {
        foreach ( is_array( $rows ) ? $rows : [] as $row ) {
            $customer_id = absint( $row['customer_id'] ?? 0 );

            if ( $customer_id <= 0 || ! isset( $metrics[ $customer_id ] ) ) {
                continue;
            }

            $count = absint( $row['order_count'] ?? 0 );
            $total = (float) ( $row['total_spent'] ?? 0 );

            $metrics[ $customer_id ] = [
                'order_count'     => $count,
                'total_spent_raw' => $total,
                'total_spent'     => $this->format_money( $total ),
                'average_raw'     => $count > 0 ? $total / $count : 0.0,
                'average'         => $this->format_money( $count > 0 ? $total / $count : 0.0 ),
                'last_order'      => $this->format_gmt_date( (string) ( $row['last_order_date'] ?? '' ) ),
            ];
        }

        return $metrics;
    }

    private function customer_orders( int $customer_id, int $page, int $per_page ): array
    {
        if ( ! function_exists( 'wc_get_orders' ) ) {
            return $this->empty_orders( $page, __( 'WooCommerce no esta disponible para consultar pedidos.', 'sultana-admin' ) );
        }

        try {
            $result = wc_get_orders(
                [
                    'customer_id' => $customer_id,
                    'limit'       => $per_page,
                    'page'        => $page,
                    'paginate'    => true,
                    'orderby'     => 'date',
                    'order'       => 'DESC',
                    'return'      => 'objects',
                ]
            );
        } catch ( Throwable $exception ) {
            return $this->empty_orders( $page, __( 'No pudimos consultar los pedidos del cliente.', 'sultana-admin' ) );
        }

        $orders      = is_object( $result ) && isset( $result->orders ) && is_array( $result->orders ) ? $result->orders : [];
        $total       = is_object( $result ) && isset( $result->total ) ? absint( $result->total ) : count( $orders );
        $total_pages = is_object( $result ) && isset( $result->max_num_pages ) ? absint( $result->max_num_pages ) : 1;
        $total_pages = max( 1, $total_pages );

        if ( $total > 0 && $page > $total_pages ) {
            return $this->customer_orders( $customer_id, $total_pages, $per_page );
        }

        return [
            'orders'      => array_map( [ $this, 'format_order_row' ], array_filter( $orders, static fn ( $order ): bool => $order instanceof WC_Order ) ),
            'page'        => min( $page, $total_pages ),
            'total'       => $total,
            'total_pages' => $total_pages,
            'error'       => '',
        ];
    }

    private function format_customer_row( WP_User $user, array $metrics ): array
    {
        return [
            'id'           => $user->ID,
            'name'         => $this->customer_name( $user ),
            'email'        => sanitize_email( $user->user_email ),
            'phone'        => $this->customer_phone( $user ),
            'orders_count' => absint( $metrics['order_count'] ?? 0 ),
            'orders_label' => sprintf(
                /* translators: %d: order count. */
                _n( '%d pedido', '%d pedidos', absint( $metrics['order_count'] ?? 0 ), 'sultana-admin' ),
                absint( $metrics['order_count'] ?? 0 )
            ),
            'total_spent'  => $metrics['total_spent'] ?? $this->format_money( 0 ),
            'last_order'   => $metrics['last_order'] ?? __( 'Sin pedidos', 'sultana-admin' ),
            'view_url'     => Router::customer_url( $user->ID ),
        ];
    }

    private function format_customer_detail( WP_User $user, array $metrics ): array
    {
        $registered = '' !== (string) $user->user_registered
            ? mysql2date( get_option( 'date_format' ), $user->user_registered )
            : __( 'Sin fecha', 'sultana-admin' );

        $order_count = absint( $metrics['order_count'] ?? 0 );

        return [
            'id'           => $user->ID,
            'name'         => $this->customer_name( $user ),
            'email'        => sanitize_email( $user->user_email ),
            'phone'        => $this->customer_phone( $user ),
            'registered'   => $registered,
            'address'      => $this->customer_address( $user ),
            'orders_count' => $order_count,
            'total_spent'  => $metrics['total_spent'] ?? $this->format_money( 0 ),
            'average'      => $metrics['average'] ?? $this->format_money( 0 ),
            'last_order'   => $metrics['last_order'] ?? __( 'Sin pedidos', 'sultana-admin' ),
        ];
    }

    private function format_order_row( WC_Order $order ): array
    {
        $date_created = $order->get_date_created();
        $status       = $order->get_status();

        return [
            'id'           => $order->get_id(),
            'number'       => $order->get_order_number(),
            'date'         => $date_created ? wc_format_datetime( $date_created ) : __( 'Sin fecha', 'sultana-admin' ),
            'status'       => $status,
            'status_label' => function_exists( 'wc_get_order_status_name' ) ? wc_get_order_status_name( $status ) : $status,
            'total'        => wc_price( (float) $order->get_total(), [ 'currency' => $order->get_currency() ] ),
            'view_url'     => Router::order_url( $order->get_id() ),
        ];
    }

    private function customer_name( WP_User $user ): string
    {
        $name = trim( (string) get_user_meta( $user->ID, 'billing_first_name', true ) . ' ' . (string) get_user_meta( $user->ID, 'billing_last_name', true ) );

        if ( '' === $name ) {
            $name = trim( (string) get_user_meta( $user->ID, 'first_name', true ) . ' ' . (string) get_user_meta( $user->ID, 'last_name', true ) );
        }

        if ( '' === $name ) {
            $name = trim( (string) $user->display_name );
        }

        return '' !== $name ? $name : __( 'Cliente', 'sultana-admin' );
    }

    private function customer_phone( WP_User $user ): string
    {
        $phone = trim( (string) get_user_meta( $user->ID, 'billing_phone', true ) );

        if ( '' === $phone ) {
            $phone = trim( (string) get_user_meta( $user->ID, 'shipping_phone', true ) );
        }

        return $phone;
    }

    private function customer_address( WP_User $user ): array
    {
        $state   = trim( (string) get_user_meta( $user->ID, 'shipping_state', true ) );
        $city    = trim( (string) get_user_meta( $user->ID, 'shipping_city', true ) );
        $address = trim( (string) get_user_meta( $user->ID, 'shipping_address_1', true ) );

        if ( '' === $state && '' === $city && '' === $address ) {
            $state   = trim( (string) get_user_meta( $user->ID, 'billing_state', true ) );
            $city    = trim( (string) get_user_meta( $user->ID, 'billing_city', true ) );
            $address = trim( (string) get_user_meta( $user->ID, 'billing_address_1', true ) );
        }

        return array_filter(
            [
                'department'   => $this->department_label( $state ),
                'municipality' => $city,
                'address_1'    => $address,
                'address_2'    => trim( (string) get_user_meta( $user->ID, 'shipping_address_2', true ) ),
            ],
            static fn ( string $value ): bool => '' !== trim( $value )
        );
    }

    private function department_label( string $state ): string
    {
        if ( '' === $state ) {
            return '';
        }

        if ( function_exists( 'WC' ) && WC()->countries ) {
            $states = WC()->countries->get_states( 'NI' );

            if ( is_array( $states ) && isset( $states[ $state ] ) ) {
                return (string) $states[ $state ];
            }
        }

        return $state;
    }

    private function orders_pagination_links( int $customer_id, int $page, int $total_pages ): array
    {
        $page_url = static fn ( int $target_page ): string => add_query_arg( [ 'orders_page' => $target_page ], Router::customer_url( $customer_id ) );

        return [
            'previous' => $page > 1 && $total_pages > 1 ? $page_url( $page - 1 ) : '',
            'next'     => $page < $total_pages ? $page_url( $page + 1 ) : '',
            'items'    => $this->pagination_items( $page, $total_pages, $page_url ),
        ];
    }

    private function pagination_items( int $page, int $total_pages, callable $page_url ): array
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

    private function format_gmt_date( string $date ): string
    {
        if ( '' === $date || '0000-00-00 00:00:00' === $date ) {
            return __( 'Sin pedidos', 'sultana-admin' );
        }

        $timestamp = get_date_from_gmt( $date, 'U' );

        return $timestamp ? date_i18n( get_option( 'date_format' ), absint( $timestamp ) ) : __( 'Sin pedidos', 'sultana-admin' );
    }

    private function format_money( float $amount ): string
    {
        return function_exists( 'wc_price' ) ? wc_price( $amount ) : wp_strip_all_tags( number_format_i18n( $amount, 2 ) );
    }

    private function uses_hpos(): bool
    {
        return class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' )
            && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
    }

    private function empty_metrics(): array
    {
        return [
            'order_count'     => 0,
            'total_spent_raw' => 0.0,
            'total_spent'     => $this->format_money( 0 ),
            'average_raw'     => 0.0,
            'average'         => $this->format_money( 0 ),
            'last_order'      => __( 'Sin pedidos', 'sultana-admin' ),
        ];
    }

    private function empty_listing( int $page, int $per_page, string $error = '' ): array
    {
        return [
            'customers'   => [],
            'page'        => max( 1, $page ),
            'per_page'    => $per_page,
            'total'       => 0,
            'total_pages' => 1,
            'error'       => $error,
        ];
    }

    private function empty_orders( int $page, string $error = '' ): array
    {
        return [
            'orders'      => [],
            'page'        => max( 1, $page ),
            'total'       => 0,
            'total_pages' => 1,
            'error'       => $error,
        ];
    }
}

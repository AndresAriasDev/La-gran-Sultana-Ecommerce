<?php

namespace Sultana\Admin\Orders;

use Throwable;
use WC_Order;
use WC_Order_Item_Shipping;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OrderService
{
    public function list_orders( array $args ): array
    {
        $search   = isset( $args['search'] ) ? trim( (string) $args['search'] ) : '';
        $status   = isset( $args['status'] ) ? sanitize_key( (string) $args['status'] ) : '';
        $page     = isset( $args['page'] ) ? max( 1, absint( $args['page'] ) ) : 1;
        $per_page = isset( $args['per_page'] ) ? max( 1, min( 50, absint( $args['per_page'] ) ) ) : 20;

        if ( ! function_exists( 'wc_get_orders' ) ) {
            return $this->empty_listing( $page, $per_page, __( 'WooCommerce no esta disponible para consultar pedidos.', 'sultana-admin' ) );
        }

        if ( '' !== $search ) {
            return $this->search_orders( $search, $status, $page, $per_page );
        }

        return $this->query_orders( $this->status_query_arg( $status ), $page, $per_page, 'all' );
    }

    public function status_options(): array
    {
        if ( ! function_exists( 'wc_get_order_statuses' ) ) {
            return [];
        }

        $statuses = [];

        foreach ( wc_get_order_statuses() as $key => $label ) {
            $status = preg_replace( '/^wc-/', '', sanitize_key( (string) $key ) );

            if ( '' !== $status ) {
                $statuses[ $status ] = (string) $label;
            }
        }

        return $statuses;
    }

    private function search_orders( string $search, string $status, int $page, int $per_page ): array
    {
        if ( ctype_digit( $search ) ) {
            return $this->search_order_by_id( absint( $search ), $status, $page, $per_page );
        }

        if ( is_email( $search ) ) {
            return $this->query_orders(
                array_merge(
                    $this->status_query_arg( $status ),
                    [
                        'billing_email' => sanitize_email( $search ),
                    ]
                ),
                $page,
                $per_page,
                'email'
            );
        }

        $listing = $this->empty_listing( 1, $per_page );
        $listing['search_mode'] = 'unsupported';

        return $listing;
    }

    private function search_order_by_id( int $order_id, string $status, int $page, int $per_page ): array
    {
        $order = $order_id > 0 && function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;

        if ( ! $order instanceof WC_Order || ( '' !== $status && ! $order->has_status( $status ) ) ) {
            $listing = $this->empty_listing( 1, $per_page );
            $listing['search_mode'] = 'id';

            return $listing;
        }

        return [
            'orders'      => [ $this->format_order( $order ) ],
            'page'        => 1,
            'per_page'    => $per_page,
            'total'       => 1,
            'total_pages' => 1,
            'error'       => '',
            'search_mode' => 'id',
        ];
    }

    private function query_orders( array $query_args, int $page, int $per_page, string $search_mode ): array
    {
        $base_args = array_merge(
            [
                'limit'    => $per_page,
                'page'     => $page,
                'paginate' => true,
                'orderby'  => 'date',
                'order'    => 'DESC',
                'return'   => 'objects',
            ],
            $query_args
        );

        try {
            $result = wc_get_orders( $base_args );
        } catch ( Throwable $exception ) {
            return $this->empty_listing( $page, $per_page, __( 'No pudimos consultar los pedidos en este momento.', 'sultana-admin' ) );
        }

        $orders      = is_object( $result ) && isset( $result->orders ) && is_array( $result->orders ) ? $result->orders : [];
        $total       = is_object( $result ) && isset( $result->total ) ? absint( $result->total ) : count( $orders );
        $total_pages = is_object( $result ) && isset( $result->max_num_pages ) ? absint( $result->max_num_pages ) : 1;
        $total_pages = max( 1, $total_pages );

        if ( $total > 0 && $page > $total_pages ) {
            return $this->query_orders( $query_args, $total_pages, $per_page, $search_mode );
        }

        return [
            'orders'      => array_map( [ $this, 'format_order' ], array_filter( $orders, static fn ( $order ): bool => $order instanceof WC_Order ) ),
            'page'        => min( $page, $total_pages ),
            'per_page'    => $per_page,
            'total'       => $total,
            'total_pages' => $total_pages,
            'error'       => '',
            'search_mode' => $search_mode,
        ];
    }

    private function status_query_arg( string $status ): array
    {
        return '' !== $status ? [ 'status' => [ $status ] ] : [];
    }

    private function empty_listing( int $page, int $per_page, string $error = '' ): array
    {
        return [
            'orders'      => [],
            'page'        => max( 1, $page ),
            'per_page'    => $per_page,
            'total'       => 0,
            'total_pages' => 1,
            'error'       => $error,
            'search_mode' => 'all',
        ];
    }

    private function format_order( WC_Order $order ): array
    {
        $date_created = $order->get_date_created();
        $customer     = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );

        if ( '' === $customer ) {
            $customer = $order->get_billing_email();
        }

        return [
            'id'              => $order->get_id(),
            'number'          => $order->get_order_number(),
            'customer'        => '' !== $customer ? $customer : __( 'Cliente', 'sultana-admin' ),
            'date'            => $date_created ? wc_format_datetime( $date_created ) : __( 'Sin fecha', 'sultana-admin' ),
            'status'          => $order->get_status(),
            'status_label'    => function_exists( 'wc_get_order_status_name' ) ? wc_get_order_status_name( $order->get_status() ) : $order->get_status(),
            'total'           => wc_price( (float) $order->get_total(), [ 'currency' => $order->get_currency() ] ),
            'payment_method'  => $this->payment_method_label( $order ),
            'shipping_method' => $this->shipping_method_label( $order ),
        ];
    }

    private function payment_method_label( WC_Order $order ): string
    {
        $label = trim( (string) $order->get_payment_method_title() );

        if ( '' !== $label ) {
            return $label;
        }

        $method = trim( (string) $order->get_payment_method() );

        return '' !== $method ? $method : __( 'Sin metodo de pago', 'sultana-admin' );
    }

    private function shipping_method_label( WC_Order $order ): string
    {
        $labels = [];

        foreach ( $order->get_shipping_methods() as $shipping_item ) {
            if ( ! $shipping_item instanceof WC_Order_Item_Shipping ) {
                continue;
            }

            $name = trim( (string) $shipping_item->get_name() );

            if ( '' !== $name ) {
                $labels[] = $name;
            }
        }

        return ! empty( $labels ) ? implode( ', ', array_unique( $labels ) ) : __( 'Sin metodo de entrega', 'sultana-admin' );
    }
}

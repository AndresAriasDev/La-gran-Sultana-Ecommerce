<?php

namespace Sultana\Admin\Orders;

use Sultana\Admin\Core\Router;
use Throwable;
use WC_Order;
use WC_Order_Item_Product;
use WC_Order_Item_Shipping;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OrderService
{
    private const COMBO_SNAPSHOT_META = '_scc_combo_components_snapshot';
    private const ALLOWED_STATUS_TARGETS = [ 'pending', 'on-hold', 'processing', 'completed', 'cancelled' ];
    private const ALLOWED_SHIPPING_META_LABELS = [ 'origen', 'ruta', 'entrega' ];

    private ?array $status_options_cache = null;

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
        if ( null !== $this->status_options_cache ) {
            return $this->status_options_cache;
        }

        if ( ! function_exists( 'wc_get_order_statuses' ) ) {
            $this->status_options_cache = [];
            return [];
        }

        $statuses = [];

        foreach ( wc_get_order_statuses() as $key => $label ) {
            $status = preg_replace( '/^wc-/', '', sanitize_key( (string) $key ) );

            if ( '' !== $status ) {
                $statuses[ $status ] = (string) $label;
            }
        }

        $this->status_options_cache = $statuses;

        return $this->status_options_cache;
    }

    public function order_detail( int $order_id ): array
    {
        if ( ! function_exists( 'wc_get_order' ) ) {
            return [
                'error'   => __( 'WooCommerce no esta disponible para consultar pedidos.', 'sultana-admin' ),
                'message' => __( 'No pudimos cargar el pedido.', 'sultana-admin' ),
            ];
        }

        try {
            $order = wc_get_order( $order_id );
        } catch ( Throwable $exception ) {
            return [
                'error'   => __( 'No pudimos consultar el pedido en este momento.', 'sultana-admin' ),
                'message' => __( 'No pudimos cargar el pedido.', 'sultana-admin' ),
            ];
        }

        if ( ! $order instanceof WC_Order ) {
            return [
                'not_found' => true,
                'message'   => __( 'Pedido no encontrado.', 'sultana-admin' ),
            ];
        }

        if ( ! $this->can_view_order( $order ) ) {
            return [
                'forbidden' => true,
                'message'   => __( 'No tienes permisos para ver este pedido.', 'sultana-admin' ),
            ];
        }

        return [
            'order'           => $this->format_order_detail( $order ),
            'back_url'        => Router::orders_url(),
            'not_found'       => false,
            'forbidden'       => false,
            'error'           => '',
            'read_only_notice' => __( 'Vista de solo lectura.', 'sultana-admin' ),
            'notice'          => $this->notice_from_request(),
            'status_action'   => $this->status_action_data( $order ),
        ];
    }

    public function handle_status_update( int $order_id, array $posted_data ): array
    {
        $action = isset( $posted_data['sultana_admin_order_status_action'] )
            ? sanitize_key( wp_unslash( $posted_data['sultana_admin_order_status_action'] ) )
            : '';

        if ( 'update_status' !== $action ) {
            return [];
        }

        if ( ! function_exists( 'wc_get_order' ) ) {
            return [
                'error' => __( 'WooCommerce no esta disponible para cambiar pedidos.', 'sultana-admin' ),
            ];
        }

        $posted_order_id = isset( $posted_data['order_id'] ) ? absint( wp_unslash( $posted_data['order_id'] ) ) : 0;

        if ( $order_id <= 0 || $posted_order_id !== $order_id ) {
            return [
                'error' => __( 'Pedido invalido.', 'sultana-admin' ),
            ];
        }

        try {
            $order = wc_get_order( $order_id );
        } catch ( Throwable $exception ) {
            return [
                'error' => __( 'No pudimos consultar el pedido en este momento.', 'sultana-admin' ),
            ];
        }

        if ( ! $order instanceof WC_Order ) {
            return [
                'error' => __( 'Pedido no encontrado.', 'sultana-admin' ),
            ];
        }

        if ( ! $this->can_update_order_status( $order ) ) {
            return [
                'forbidden' => true,
                'error'     => __( 'No tienes permisos para cambiar este pedido.', 'sultana-admin' ),
            ];
        }

        $nonce = isset( $posted_data[ OrderController::STATUS_NONCE_FIELD ] )
            ? sanitize_text_field( wp_unslash( $posted_data[ OrderController::STATUS_NONCE_FIELD ] ) )
            : '';

        if ( ! wp_verify_nonce( $nonce, $this->status_nonce_action( $order_id ) ) ) {
            return [
                'error' => __( 'La solicitud no es valida. Actualiza la pagina e intentalo nuevamente.', 'sultana-admin' ),
            ];
        }

        $current_status = isset( $posted_data['current_status'] )
            ? $this->normalize_status_key( (string) wp_unslash( $posted_data['current_status'] ) )
            : '';
        $actual_status  = $order->get_status();

        if ( $actual_status !== $current_status ) {
            return [
                'error' => __( 'El estado del pedido cambio mientras lo estabas revisando. Actualiza la pagina e intentalo nuevamente.', 'sultana-admin' ),
            ];
        }

        $new_status = isset( $posted_data['new_status'] )
            ? $this->normalize_status_key( (string) wp_unslash( $posted_data['new_status'] ) )
            : '';

        if ( '' === $new_status || ! $this->status_is_registered( $new_status ) || ! in_array( $new_status, self::ALLOWED_STATUS_TARGETS, true ) ) {
            return [
                'error' => __( 'El estado seleccionado no es valido.', 'sultana-admin' ),
            ];
        }

        if ( $new_status === $actual_status ) {
            return [
                'error' => __( 'Selecciona un estado diferente al actual.', 'sultana-admin' ),
            ];
        }

        if ( ! in_array( $new_status, $this->allowed_next_statuses( $actual_status ), true ) ) {
            return [
                'error' => __( 'Esta transicion de estado no esta permitida en Sultana Admin.', 'sultana-admin' ),
            ];
        }

        try {
            $order->update_status( $new_status );
        } catch ( Throwable $exception ) {
            return [
                'error' => __( 'No pudimos actualizar el estado del pedido.', 'sultana-admin' ),
            ];
        }

        return [
            'redirect_url' => add_query_arg( 'notice', 'order_status_updated', Router::order_url( $order_id ) ),
        ];
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
        $order_id     = $order->get_id();
        $status       = $order->get_status();
        $customer     = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );

        if ( '' === $customer ) {
            $customer = $order->get_billing_email();
        }

        return [
            'id'              => $order_id,
            'number'          => $order->get_order_number(),
            'customer'        => '' !== $customer ? $customer : __( 'Cliente', 'sultana-admin' ),
            'date'            => $date_created ? wc_format_datetime( $date_created ) : __( 'Sin fecha', 'sultana-admin' ),
            'date_time'       => $date_created ? wc_format_datetime( $date_created, wc_date_format() . ' · ' . wc_time_format() ) : __( 'Sin fecha', 'sultana-admin' ),
            'status'          => $status,
            'status_label'    => function_exists( 'wc_get_order_status_name' ) ? wc_get_order_status_name( $status ) : $status,
            'total'           => wc_price( (float) $order->get_total(), [ 'currency' => $order->get_currency() ] ),
            'payment_method'  => $this->payment_method_label( $order ),
            'shipping_method' => $this->shipping_method_label( $order ),
            'view_url'         => Router::order_url( $order_id ),
            'can_view'         => $this->can_view_order( $order ),
        ];
    }

    private function format_order_detail( WC_Order $order ): array
    {
        $date_created  = $order->get_date_created();
        $date_paid     = $order->get_date_paid();
        $date_completed = $order->get_date_completed();
        $items_subtotal = 0.0;
        $items          = $this->line_items( $order, $items_subtotal );
        $shipping       = $this->shipping_data( $order );

        return [
            'id'          => $order->get_id(),
            'number'      => $order->get_order_number(),
            'summary'     => [
                'date'           => $date_created ? wc_format_datetime( $date_created ) : __( 'Sin fecha', 'sultana-admin' ),
                'status'         => $order->get_status(),
                'status_label'   => function_exists( 'wc_get_order_status_name' ) ? wc_get_order_status_name( $order->get_status() ) : $order->get_status(),
                'total'          => $this->format_money( $order->get_total(), $order ),
                'currency'       => $order->get_currency(),
                'date_completed' => $date_completed ? wc_format_datetime( $date_completed ) : '',
            ],
            'customer'    => $this->customer_data( $order ),
            'address'     => $this->delivery_address( $order, $shipping ),
            'items'       => $items,
            'payment'     => [
                'method'         => $this->payment_method_label( $order ),
                'method_id'      => (string) $order->get_payment_method(),
                'paid_label'     => $order->is_paid() ? __( 'Pagado', 'sultana-admin' ) : __( 'Pendiente / no pagado', 'sultana-admin' ),
                'date_paid'      => $date_paid ? wc_format_datetime( $date_paid ) : '',
                'transaction_id' => trim( (string) $order->get_transaction_id() ),
            ],
            'shipping'    => $shipping,
            'totals'      => $this->totals_data( $order, $items_subtotal ),
            'gift'        => $this->gift_data( $order ),
        ];
    }

    private function can_view_order( WC_Order $order ): bool
    {
        $order_id = $order->get_id();

        return current_user_can( 'edit_shop_order', $order_id )
            || current_user_can( 'read_shop_order', $order_id );
    }

    private function can_update_order_status( WC_Order $order ): bool
    {
        $order_id = $order->get_id();

        return current_user_can( 'edit_shop_order', $order_id );
    }

    private function status_action_data( WC_Order $order ): array
    {
        $current_status = $order->get_status();
        $next_statuses  = $this->allowed_next_statuses( $current_status );
        $options        = [];

        foreach ( $this->status_options() as $status => $label ) {
            if ( in_array( $status, $next_statuses, true ) ) {
                $options[ $status ] = $label;
            }
        }

        return [
            'can_update'     => $this->can_update_order_status( $order ),
            'current_status' => $current_status,
            'options'        => $options,
            'nonce_action'   => $this->status_nonce_action( $order->get_id() ),
        ];
    }

    private function allowed_next_statuses( string $current_status ): array
    {
        $transitions = [
            'pending'    => [ 'on-hold', 'processing', 'cancelled' ],
            'on-hold'    => [ 'processing', 'cancelled' ],
            'processing' => [ 'completed', 'cancelled' ],
        ];

        return $transitions[ $current_status ] ?? [];
    }

    private function status_is_registered( string $status ): bool
    {
        return isset( $this->status_options()[ $status ] );
    }

    private function normalize_status_key( string $status ): string
    {
        $status = sanitize_key( $status );
        $status = preg_replace( '/^wc-/', '', $status );

        return is_string( $status ) ? $status : '';
    }

    private function status_nonce_action( int $order_id ): string
    {
        return 'sultana_admin_update_order_status_' . absint( $order_id );
    }

    private function notice_from_request(): string
    {
        $notice = isset( $_GET['notice'] ) ? sanitize_key( wp_unslash( $_GET['notice'] ) ) : '';

        return 'order_status_updated' === $notice
            ? __( 'Estado del pedido actualizado correctamente.', 'sultana-admin' )
            : '';
    }

    private function customer_data( WC_Order $order ): array
    {
        $name = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );

        return [
            'name'  => '' !== $name ? $name : __( 'Cliente', 'sultana-admin' ),
            'email' => sanitize_email( (string) $order->get_billing_email() ),
            'phone' => trim( (string) $order->get_billing_phone() ),
        ];
    }

    private function delivery_address( WC_Order $order, array $shipping ): array
    {
        if ( $this->shipping_data_indicates_store_pickup( $shipping ) ) {
            return [
                'type'         => 'pickup',
                'type_label'   => __( 'Retiro en tienda', 'sultana-admin' ),
                'department'   => '',
                'municipality' => '',
                'address_1'    => '',
            ];
        }

        $use_shipping = $this->has_usable_shipping_address( $order );
        $prefix       = $use_shipping ? 'shipping' : 'billing';
        $state        = $use_shipping ? $order->get_shipping_state() : $order->get_billing_state();
        $city         = $use_shipping ? $order->get_shipping_city() : $order->get_billing_city();
        $address_1    = $use_shipping ? $order->get_shipping_address_1() : $order->get_billing_address_1();

        return [
            'type'         => $prefix,
            'type_label'   => $use_shipping ? __( 'Direccion de entrega', 'sultana-admin' ) : __( 'Direccion de facturacion', 'sultana-admin' ),
            'department'   => $this->department_label( (string) $state ),
            'municipality' => trim( (string) $city ),
            'address_1'    => trim( (string) $address_1 ),
        ];
    }

    private function has_usable_shipping_address( WC_Order $order ): bool
    {
        return '' !== trim( $order->get_shipping_state() . $order->get_shipping_city() . $order->get_shipping_address_1() );
    }

    private function shipping_data_indicates_store_pickup( array $shipping ): bool
    {
        foreach ( $shipping as $shipping_item ) {
            if ( 'scc_store_pickup' === (string) ( $shipping_item['method_id'] ?? '' ) ) {
                return true;
            }
        }

        return false;
    }

    private function department_label( string $state ): string
    {
        $state = trim( $state );

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

    private function line_items( WC_Order $order, float &$items_subtotal = 0.0 ): array
    {
        $items          = [];
        $items_subtotal = 0.0;

        foreach ( $order->get_items( 'line_item' ) as $item ) {
            if ( ! $item instanceof WC_Order_Item_Product ) {
                continue;
            }

            $subtotal = (float) $item->get_subtotal();
            $items_subtotal += $subtotal;

            $items[] = [
                'name'       => $item->get_name(),
                'quantity'   => (float) $item->get_quantity(),
                'subtotal'   => $this->format_money( $subtotal, $order ),
                'total'      => $this->format_money( $item->get_total(), $order ),
                'attributes' => $this->presentable_item_meta( $item ),
                'components' => $this->combo_components_snapshot( $item, max( 1, (float) $item->get_quantity() ) ),
            ];
        }

        return $items;
    }

    private function presentable_item_meta( WC_Order_Item_Product $item ): array
    {
        $metadata = [];

        foreach ( $item->get_formatted_meta_data( '_', false ) as $meta ) {
            $key   = isset( $meta->key ) ? (string) $meta->key : '';
            $label = isset( $meta->display_key ) ? wp_strip_all_tags( (string) $meta->display_key ) : '';
            $value = isset( $meta->display_value ) ? wp_strip_all_tags( (string) $meta->display_value ) : '';

            if ( '' === $label || '' === $value || ! $this->shipping_meta_is_allowed( $key, $label ) ) {
                continue;
            }

            $metadata[] = [
                'label' => $label,
                'value' => $value,
            ];
        }

        return $metadata;
    }

    private function combo_components_snapshot( WC_Order_Item_Product $item, float $order_item_quantity ): array
    {
        $snapshot = $item->get_meta( self::COMBO_SNAPSHOT_META, true );

        if ( ! is_array( $snapshot ) || empty( $snapshot ) ) {
            return [];
        }

        $components = [];

        foreach ( $snapshot as $component ) {
            if ( ! is_array( $component ) ) {
                continue;
            }

            $quantity = max( 1, absint( $component['quantity'] ?? 0 ) );
            $name     = trim( (string) ( $component['name'] ?? '' ) );
            $sku      = trim( (string) ( $component['sku'] ?? '' ) );

            if ( '' === $name ) {
                $name = __( 'Componente del combo', 'sultana-admin' );
            }

            $components[] = [
                'name'        => $name,
                'sku'         => $sku,
                'quantity'    => $quantity,
                'total_units' => $quantity * $order_item_quantity,
                'attributes'  => $this->component_attributes( $component['attributes'] ?? [] ),
            ];
        }

        return $components;
    }

    private function component_attributes( $attributes ): array
    {
        if ( ! is_array( $attributes ) ) {
            return [];
        }

        $formatted = [];

        foreach ( $attributes as $key => $value ) {
            if ( is_array( $value ) ) {
                $label = trim( (string) ( $value['label'] ?? '' ) );
                $value = trim( (string) ( $value['value'] ?? '' ) );

                if ( '' !== $label && '' !== $value ) {
                    $formatted[] = [
                        'label' => $label,
                        'value' => $value,
                    ];
                }

                continue;
            }

            $label = trim( wc_attribute_label( (string) $key ) );
            $value = trim( is_scalar( $value ) ? (string) $value : '' );

            if ( '' !== $label && '' !== $value ) {
                $formatted[] = [
                    'label' => $label,
                    'value' => $value,
                ];
            }
        }

        return $formatted;
    }

    private function gift_data( WC_Order $order ): array
    {
        if ( 'yes' !== (string) $order->get_meta( '_scc_wishlist_gift_order', true ) ) {
            return [
                'is_gift' => false,
            ];
        }

        return [
            'is_gift'           => true,
            'recipient_name'    => trim( (string) $order->get_meta( '_scc_wishlist_recipient_name', true ) ),
            'recipient_address' => trim( (string) $order->get_meta( '_scc_wishlist_recipient_address', true ) ),
        ];
    }

    private function shipping_data( WC_Order $order ): array
    {
        $shipping = [];

        foreach ( $order->get_shipping_methods() as $shipping_item ) {
            if ( ! $shipping_item instanceof WC_Order_Item_Shipping ) {
                continue;
            }

            $shipping[] = [
                'method'    => trim( (string) $shipping_item->get_name() ),
                'method_id' => trim( (string) $shipping_item->get_method_id() ),
                'total'     => $this->format_money( $shipping_item->get_total(), $order ),
                'meta'      => $this->presentable_shipping_meta( $shipping_item ),
            ];
        }

        return $shipping;
    }

    private function presentable_shipping_meta( WC_Order_Item_Shipping $item ): array
    {
        $metadata = [];

        foreach ( $item->get_formatted_meta_data( '_', false ) as $meta ) {
            $key   = isset( $meta->key ) ? (string) $meta->key : '';
            $label = isset( $meta->display_key ) ? wp_strip_all_tags( (string) $meta->display_key ) : '';
            $value = isset( $meta->display_value ) ? wp_strip_all_tags( (string) $meta->display_value ) : '';

            if ( '' === $label || '' === $value || str_starts_with( $key, '_' ) || $this->is_internal_meta_key( $key ) ) {
                continue;
            }

            $metadata[] = [
                'label' => $label,
                'value' => $value,
            ];
        }

        return $metadata;
    }

    private function totals_data( WC_Order $order, float $items_subtotal ): array
    {
        return [
            'subtotal' => $this->format_money( $items_subtotal, $order ),
            'discount' => $this->format_money( $order->get_discount_total(), $order ),
            'shipping' => $this->format_money( $order->get_shipping_total(), $order ),
            'tax'      => $this->format_money( $order->get_total_tax(), $order ),
            'total'    => $this->format_money( $order->get_total(), $order ),
        ];
    }

    private function is_internal_meta_key( string $key ): bool
    {
        $normalized_key = strtolower( trim( $key ) );

        return str_starts_with( $key, '_scc_' )
            || str_starts_with( $key, '_internal' )
            || 'regalo para' === $normalized_key
            || in_array(
                $key,
                [
                    self::COMBO_SNAPSHOT_META,
                    '_scc_combo_components_stock_reduced',
                    '_scc_combo_components_stock_restored',
                ],
                true
            );
    }

    private function shipping_meta_is_allowed( string $key, string $label ): bool
    {
        if ( str_starts_with( $key, '_' ) || $this->is_internal_meta_key( $key ) ) {
            return false;
        }

        $normalized_label = strtolower( remove_accents( trim( $label ) ) );

        return in_array( $normalized_label, self::ALLOWED_SHIPPING_META_LABELS, true );
    }

    private function format_money( $amount, WC_Order $order ): string
    {
        return wc_price( (float) $amount, [ 'currency' => $order->get_currency() ] );
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

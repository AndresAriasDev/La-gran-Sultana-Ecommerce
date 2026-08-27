<?php

namespace Sultana\CommerceCore\Modules\Wishlist;

use Sultana\CommerceCore\Core\StoreBranding;
use Sultana\CommerceCore\Core\TemplateLoader;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Wishlist
{
    private const META_KEY = '_scc_wishlist_items';
    private const SHARE_TOKEN_META_KEY = '_scc_wishlist_share_token';
    private const QUERY_VAR = 'scc_wishlist_token';
    public const ENDPOINT = 'wishlist';

    public static function register(): void
    {
        add_action( 'init', [ self::class, 'register_endpoint' ] );
        add_action( 'init', [ self::class, 'maybe_flush_rewrite_rules' ], 20 );
        add_filter( 'query_vars', [ self::class, 'add_query_vars' ] );
        add_filter( 'template_include', [ self::class, 'load_shared_template' ] );
        add_action( 'template_redirect', [ self::class, 'redirect_invalid_account_wishlist_page' ] );
        add_action( 'wp_head', [ self::class, 'render_shared_meta_tags' ], 1 );
        add_action( 'wp_loaded', [ self::class, 'handle_account_wishlist_post' ], 20 );
        add_action( 'wp_loaded', [ self::class, 'handle_gift_add_to_cart' ], 20 );
        add_filter( 'woocommerce_add_to_cart_validation', [ self::class, 'validate_personal_add_to_cart' ], 10, 5 );
        add_filter( 'woocommerce_get_item_data', [ self::class, 'display_gift_cart_item_data' ], 10, 2 );
        add_action( 'woocommerce_checkout_create_order_line_item', [ self::class, 'add_gift_order_line_meta' ], 10, 4 );
        add_action( 'woocommerce_checkout_create_order', [ self::class, 'add_gift_order_meta' ], 20, 2 );
        add_action( 'woocommerce_checkout_create_order', [ self::class, 'apply_gift_order_shipping_address' ], 40, 2 );
        add_action( 'woocommerce_checkout_order_created', [ self::class, 'add_gift_private_order_note' ] );
        add_action( 'woocommerce_admin_order_data_after_shipping_address', [ self::class, 'render_gift_shipping_email_in_admin' ] );
        add_filter( 'map_meta_cap', [ self::class, 'allow_recipient_to_view_received_gift_order' ], 20, 4 );
        add_filter( 'woocommerce_account_menu_items', [ self::class, 'add_account_menu_item' ], 12 );
        add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', [ self::class, 'render_account_endpoint' ] );
        add_action( 'wp_ajax_scc_add_wishlist_item', [ self::class, 'add_item_ajax' ] );
        add_action( 'wp_ajax_nopriv_scc_add_wishlist_item', [ self::class, 'login_required_ajax' ] );
        add_action( 'wp_ajax_scc_remove_wishlist_item', [ self::class, 'remove_item_ajax' ] );
        add_action( 'wp_ajax_nopriv_scc_remove_wishlist_item', [ self::class, 'login_required_ajax' ] );
        add_action( 'wp_ajax_scc_wishlist_add_to_cart', [ self::class, 'add_to_cart_ajax' ] );
    }

    public static function allow_recipient_to_view_received_gift_order( array $caps, string $cap, int $user_id, array $args ): array
    {
        if ( 'view_order' !== $cap || $user_id <= 0 || empty( $args[0] ) || ! function_exists( 'wc_get_order' ) ) {
            return $caps;
        }

        $order = wc_get_order( absint( $args[0] ) );

        if ( ! $order || ! is_callable( [ $order, 'get_customer_id' ] ) ) {
            return $caps;
        }

        if ( (int) $order->get_customer_id() === $user_id ) {
            return $caps;
        }

        if ( self::is_received_gift_order_for_user( $order, $user_id ) ) {
            return [ 'read' ];
        }

        return $caps;
    }

    private static function is_received_gift_order_for_user( $order, int $user_id ): bool
    {
        if ( $user_id <= 0 || ! $order || ! is_callable( [ $order, 'get_meta' ] ) ) {
            return false;
        }

        if ( 'yes' !== (string) $order->get_meta( '_scc_wishlist_gift_order' ) ) {
            return false;
        }

        if ( absint( $order->get_meta( '_scc_wishlist_recipient_user_id' ) ) !== $user_id ) {
            return false;
        }

        if ( is_callable( [ $order, 'has_status' ] ) && ! $order->has_status( 'completed' ) ) {
            return false;
        }

        return true;
    }

    public static function register_endpoint(): void
    {
        add_rewrite_endpoint( self::ENDPOINT, EP_ROOT | EP_PAGES );
        add_rewrite_rule( '^wishlist/([^/]+)/?$', 'index.php?' . self::QUERY_VAR . '=$matches[1]', 'top' );
    }

    public static function add_query_vars( array $vars ): array
    {
        $vars[] = self::QUERY_VAR;

        return $vars;
    }

    public static function load_shared_template( string $template ): string
    {
        if ( '' === self::get_current_share_token() ) {
            return $template;
        }

        $core_template   = TemplateLoader::locate( 'wishlist/shared.php' );
        $legacy_template = locate_template( 'wishlist/shared.php' );

        if ( self::is_core_template_path( $core_template ) && $legacy_template ) {
            return $legacy_template;
        }

        return $core_template ?: ( $legacy_template ?: $template );
    }

    public static function redirect_invalid_account_wishlist_page(): void
    {
        if ( is_admin() || ! is_user_logged_in() || ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
            return;
        }

        global $wp_query;

        $is_wishlist_endpoint = function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( self::ENDPOINT );

        if ( ! $is_wishlist_endpoint && ! isset( $wp_query->query_vars[ self::ENDPOINT ] ) ) {
            return;
        }

        if ( ! isset( $_GET['wishlist_page'] ) ) {
            return;
        }

        $raw_wishlist_page = $_GET['wishlist_page'];
        $requested_page    = is_scalar( $raw_wishlist_page ) ? absint( wp_unslash( $raw_wishlist_page ) ) : 1;
        $wishlist_url   = function_exists( 'wc_get_account_endpoint_url' ) ? wc_get_account_endpoint_url( self::ENDPOINT ) : '';

        if ( '' === $wishlist_url ) {
            return;
        }

        $items       = self::get_items( get_current_user_id() );
        $total_items = count( $items );
        $total_pages = $total_items > 0 ? (int) ceil( $total_items / 12 ) : 1;

        if ( $requested_page <= 1 ) {
            wp_safe_redirect( remove_query_arg( 'wishlist_page', $wishlist_url ) );
            exit;
        }

        if ( $requested_page > $total_pages ) {
            $redirect_url = $total_pages > 1
                ? add_query_arg( 'wishlist_page', $total_pages, $wishlist_url )
                : remove_query_arg( 'wishlist_page', $wishlist_url );

            wp_safe_redirect( $redirect_url );
            exit;
        }
    }

    public static function maybe_flush_rewrite_rules(): void
    {
        $option_key = 'scc_wishlist_endpoint_version';
        $version    = ( defined( 'SCC_VERSION' ) ? SCC_VERSION : '1' ) . '-wishlist-share-v1';

        if ( get_option( $option_key ) === $version ) {
            return;
        }

        flush_rewrite_rules();
        update_option( $option_key, $version, false );
    }

    public static function add_account_menu_item( array $items ): array
    {
        if ( isset( $items[ self::ENDPOINT ] ) ) {
            return $items;
        }

        $new_items = [];

        foreach ( $items as $endpoint => $label ) {
            if ( 'customer-logout' === $endpoint ) {
                $new_items[ self::ENDPOINT ] = __( 'Lista de deseos', 'sultana-commerce-core' );
            }

            $new_items[ $endpoint ] = $label;
        }

        return $new_items;
    }

    public static function render_account_endpoint(): void
    {
        wc_get_template( 'myaccount/wishlist.php', [], '', self::get_plugin_template_path() );
    }

    public static function get_items( int $user_id ): array
    {
        $items = get_user_meta( $user_id, self::META_KEY, true );

        return is_array( $items ) ? $items : [];
    }

    public static function get_share_token( int $user_id ): string
    {
        if ( $user_id <= 0 ) {
            return '';
        }

        $token = get_user_meta( $user_id, self::SHARE_TOKEN_META_KEY, true );

        if ( is_string( $token ) && self::is_valid_share_token( $token ) ) {
            return $token;
        }

        $token = self::generate_unique_share_token();
        update_user_meta( $user_id, self::SHARE_TOKEN_META_KEY, $token );

        return $token;
    }

    public static function get_share_url( int $user_id ): string
    {
        $token = self::get_share_token( $user_id );

        return $token ? home_url( '/wishlist/' . rawurlencode( $token ) . '/' ) : '';
    }

    public static function get_current_share_token(): string
    {
        $token = get_query_var( self::QUERY_VAR );

        return is_string( $token ) ? sanitize_text_field( wp_unslash( $token ) ) : '';
    }

    public static function get_user_by_share_token( string $token ): ?\WP_User
    {
        if ( ! self::is_valid_share_token( $token ) ) {
            return null;
        }

        $users = get_users(
            [
                'number'     => 1,
                'fields'     => 'all',
                'meta_key'   => self::SHARE_TOKEN_META_KEY,
                'meta_value' => $token,
            ]
        );

        return $users[0] ?? null;
    }

    public static function get_public_owner_display_name( \WP_User $owner ): string
    {
        return self::get_owner_display_name( $owner );
    }

    public static function get_item( int $user_id, string $key ): ?array
    {
        $items = self::get_items( $user_id );

        return $items[ $key ] ?? null;
    }

    public static function is_wishlist_item_in_gift_cart( string $token, string $wishlist_key ): bool
    {
        if ( '' === $token || '' === $wishlist_key || ! function_exists( 'WC' ) || ! WC()->cart ) {
            return false;
        }

        foreach ( WC()->cart->get_cart() as $cart_item ) {
            if ( empty( $cart_item['scc_wishlist_gift'] ) || ! is_array( $cart_item['scc_wishlist_gift'] ) ) {
                continue;
            }

            $gift = $cart_item['scc_wishlist_gift'];

            if (
                hash_equals( $token, (string) ( $gift['wishlist_token'] ?? '' ) )
                && hash_equals( $wishlist_key, (string) ( $gift['wishlist_key'] ?? '' ) )
            ) {
                return true;
            }
        }

        return false;
    }

    public static function get_count( int $user_id ): int
    {
        return count( self::get_items( $user_id ) );
    }

    public static function get_item_variation_options( array $item ): array
    {
        $product_id   = absint( $item['product_id'] ?? 0 );
        $variation_id = absint( $item['variation_id'] ?? 0 );

        if ( ! $variation_id ) {
            return [];
        }

        $variation = wc_get_product( $variation_id );

        if ( ! $variation || ! $variation->is_type( 'variation' ) ) {
            return [];
        }

        $parent_id = $product_id ?: (int) $variation->get_parent_id();
        $parent    = wc_get_product( $parent_id );
        $stored    = is_array( $item['attributes'] ?? null ) ? $item['attributes'] : [];
        $options   = [];

        foreach ( self::get_cart_variation_attributes( $variation, $stored, $parent ) as $name => $value ) {
            if ( '' === (string) $value ) {
                continue;
            }

            $attribute_name = preg_replace( '/^attribute_/', '', (string) $name );
            $options[]      = [
                'label' => wc_attribute_label( $attribute_name, $parent ),
                'value' => self::get_attribute_display_value( $attribute_name, (string) $value ),
            ];
        }

        return $options;
    }

    public static function has_item( int $user_id, int $product_id, int $variation_id = 0, array $attributes = [] ): bool
    {
        $key = self::build_item_key( $product_id, $variation_id, $attributes );

        return isset( self::get_items( $user_id )[ $key ] );
    }

    public static function add_item_ajax(): void
    {
        self::verify_ajax_request();

        $user_id      = get_current_user_id();
        $product_id   = absint( $_POST['product_id'] ?? 0 );
        $variation_id = absint( $_POST['variation_id'] ?? 0 );
        $attributes   = self::sanitize_attributes( $_POST['attributes'] ?? [] );
        $product      = wc_get_product( $product_id );

        if ( ! $product ) {
            self::send_error( __( 'No pudimos encontrar este producto.', 'sultana-commerce-core' ), 404 );
        }

        if ( $product->is_type( 'variable' ) ) {
            if ( ! $variation_id ) {
                self::send_error( __( 'Selecciona las opciones del producto antes de agregarlo a tu lista de deseos.', 'sultana-commerce-core' ) );
            }

            $variation = wc_get_product( $variation_id );

            if ( ! $variation || ! $variation->is_type( 'variation' ) || (int) $variation->get_parent_id() !== $product_id ) {
                self::send_error( __( 'La variación seleccionada no pertenece a este producto.', 'sultana-commerce-core' ) );
            }

            $variation_attributes = self::get_cart_variation_attributes( $variation, $attributes, $product );

            if ( empty( $variation_attributes ) ) {
                self::send_error( __( 'No pudimos validar las opciones seleccionadas.', 'sultana-commerce-core' ) );
            }

            $attributes = $variation_attributes;
        } else {
            $variation_id = 0;
            $attributes   = [];
        }

        $items = self::get_items( $user_id );
        self::get_share_token( $user_id );
        $key   = self::build_item_key( $product_id, $variation_id, $attributes );

        $items[ $key ] = [
            'key'          => $key,
            'product_id'   => $product_id,
            'variation_id' => $variation_id,
            'attributes'   => $attributes,
            'quantity'     => 1,
            'added_at'     => time(),
        ];

        update_user_meta( $user_id, self::META_KEY, $items );

        wp_send_json_success(
            [
                'message' => __( 'Producto agregado a tu lista de deseos.', 'sultana-commerce-core' ),
                'count'   => count( $items ),
                'key'     => $key,
            ]
        );
    }

    public static function remove_item_ajax(): void
    {
        self::verify_ajax_request();

        $user_id           = get_current_user_id();
        $key               = sanitize_text_field( wp_unslash( $_POST['key'] ?? '' ) );
        $raw_wishlist_page = $_POST['wishlist_page'] ?? 1;
        $requested_page    = is_scalar( $raw_wishlist_page ) ? absint( wp_unslash( $raw_wishlist_page ) ) : 1;
        $result            = self::remove_wishlist_item( $user_id, $key );

        if ( is_wp_error( $result ) ) {
            self::send_error( __( 'Este producto ya no está en tu lista de deseos.', 'sultana-commerce-core' ), 404 );
        }

        $payload = [
            'message' => __( 'Producto eliminado de tu lista de deseos.', 'sultana-commerce-core' ),
            'count'   => $result['wishlist_count'],
        ];

        if ( function_exists( 'variedadesexpress_account_wishlist_ajax_payload' ) ) {
            $payload = array_merge( $payload, variedadesexpress_account_wishlist_ajax_payload( $user_id, $requested_page ) );
        }

        wp_send_json_success( $payload );
    }

    public static function add_to_cart_ajax(): void
    {
        self::verify_ajax_request();

        $user_id           = get_current_user_id();
        $raw_wishlist_page = $_POST['wishlist_page'] ?? 1;
        $requested_page    = is_scalar( $raw_wishlist_page ) ? absint( wp_unslash( $raw_wishlist_page ) ) : 1;
        $result = self::add_wishlist_item_to_cart(
            $user_id,
            sanitize_text_field( wp_unslash( $_POST['key'] ?? '' ) )
        );

        if ( is_wp_error( $result ) ) {
            self::send_error( $result->get_error_message() );
        }

        $payload = [
            'message'        => __( 'Producto agregado al carrito.', 'sultana-commerce-core' ),
            'wishlist_count' => $result['wishlist_count'],
            'cart_count'     => $result['cart_count'],
        ];

        if ( function_exists( 'variedadesexpress_account_wishlist_ajax_payload' ) ) {
            $payload = array_merge( $payload, variedadesexpress_account_wishlist_ajax_payload( $user_id, $requested_page ) );
        }

        wp_send_json_success( $payload );

    }

    public static function handle_account_wishlist_post(): void
    {
        if ( empty( $_POST['scc_account_wishlist_action'] ) ) {
            return;
        }

        $action   = sanitize_key( wp_unslash( $_POST['scc_account_wishlist_action'] ) );
        $key      = sanitize_text_field( wp_unslash( $_POST['wishlist_item_key'] ?? '' ) );
        $redirect = self::get_account_wishlist_redirect_url();

        if ( ! is_user_logged_in() ) {
            wc_add_notice( __( 'Inicia sesión para administrar tu lista de deseos.', 'sultana-commerce-core' ), 'error' );
            wp_safe_redirect( $redirect );
            exit;
        }

        if ( ! in_array( $action, [ 'remove', 'add_to_cart' ], true ) ) {
            wc_add_notice( __( 'No pudimos procesar esta solicitud.', 'sultana-commerce-core' ), 'error' );
            wp_safe_redirect( $redirect );
            exit;
        }

        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) ), 'scc_account_wishlist_' . $action . '_' . $key ) ) {
            wc_add_notice( __( 'No pudimos validar esta solicitud. Intenta de nuevo.', 'sultana-commerce-core' ), 'error' );
            wp_safe_redirect( $redirect );
            exit;
        }

        $result = 'remove' === $action
            ? self::remove_wishlist_item( get_current_user_id(), $key )
            : self::add_wishlist_item_to_cart( get_current_user_id(), $key );

        if ( is_wp_error( $result ) ) {
            wc_add_notice( $result->get_error_message(), 'error' );
        } elseif ( 'remove' === $action ) {
            wc_add_notice( __( 'Producto eliminado de tu lista de deseos.', 'sultana-commerce-core' ), 'success' );
        } else {
            wc_add_notice( __( 'Producto agregado al carrito.', 'sultana-commerce-core' ), 'success' );
        }

        wp_safe_redirect( $redirect );
        exit;
    }

    public static function handle_gift_add_to_cart(): void
    {
        if ( empty( $_POST['scc_wishlist_gift_action'] ) || 'add_to_cart' !== $_POST['scc_wishlist_gift_action'] ) {
            return;
        }

        if ( ! function_exists( 'WC' ) ) {
            return;
        }

        if ( ! WC()->cart && function_exists( 'wc_load_cart' ) ) {
            wc_load_cart();
        }

        if ( ! WC()->cart ) {
            return;
        }

        $token        = sanitize_text_field( wp_unslash( $_POST['wishlist_token'] ?? '' ) );
        $wishlist_key = sanitize_text_field( wp_unslash( $_POST['wishlist_item_key'] ?? '' ) );
        $redirect     = esc_url_raw( wp_unslash( $_POST['_wp_http_referer'] ?? home_url( '/' ) ) );

        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) ), 'scc_wishlist_gift_' . $wishlist_key ) ) {
            wc_add_notice( __( 'No pudimos validar esta solicitud. Intenta de nuevo.', 'sultana-commerce-core' ), 'error' );
            wp_safe_redirect( $redirect );
            exit;
        }

        if ( ! is_user_logged_in() ) {
            wc_add_notice( __( 'Creá tu cuenta o iniciá sesión para regalar productos de esta lista.', 'sultana-commerce-core' ), 'error' );
            wp_safe_redirect( $redirect );
            exit;
        }

        $owner = self::get_user_by_share_token( $token );

        if ( ! $owner ) {
            wc_add_notice( __( 'Esta lista de deseos ya no está disponible.', 'sultana-commerce-core' ), 'error' );
            wp_safe_redirect( home_url( '/' ) );
            exit;
        }

        $redirect = self::get_share_url( (int) $owner->ID ) ?: $redirect;

        if ( self::cart_has_personal_items() ) {
            wc_add_notice( __( 'Ya tienes productos personales en tu carrito. Para comprar un regalo desde esta lista, primero finaliza esa compra o elimina esos productos del carrito.', 'sultana-commerce-core' ), 'error' );
            wp_safe_redirect( $redirect );
            exit;
        }

        if ( self::is_wishlist_item_in_gift_cart( $token, $wishlist_key ) ) {
            wc_add_notice( __( 'Este regalo ya esta en tu carrito.', 'sultana-commerce-core' ), 'notice' );
            wp_safe_redirect( $redirect );
            exit;
        }

        foreach ( WC()->cart->get_cart() as $cart_item ) {
            if ( empty( $cart_item['scc_wishlist_gift']['owner_id'] ) ) {
                continue;
            }

            if ( (int) $cart_item['scc_wishlist_gift']['owner_id'] !== (int) $owner->ID ) {
                wc_add_notice( __( 'Ya tenés regalos para otra persona en el carrito. Finalizá esa compra o eliminá esos regalos antes de continuar.', 'sultana-commerce-core' ), 'error' );
                wp_safe_redirect( $redirect );
                exit;
            }
        }

        $item = self::get_item( (int) $owner->ID, $wishlist_key );

        if ( ! $item ) {
            wc_add_notice( __( 'Este producto ya no está disponible en la lista de deseos.', 'sultana-commerce-core' ), 'error' );
            wp_safe_redirect( self::get_share_url( (int) $owner->ID ) );
            exit;
        }

        if ( ! self::owner_has_delivery_address( (int) $owner->ID ) ) {
            wc_add_notice( __( 'Esta lista aún no tiene una dirección de entrega completa.', 'sultana-commerce-core' ), 'error' );
            wp_safe_redirect( self::get_share_url( (int) $owner->ID ) );
            exit;
        }

        $product_id   = absint( $item['product_id'] ?? 0 );
        $variation_id = absint( $item['variation_id'] ?? 0 );
        $stored_attributes = is_array( $item['attributes'] ?? null ) ? $item['attributes'] : [];

        if ( $variation_id ) {
            $variation_product = wc_get_product( $variation_id );

            if ( $variation_product && $variation_product->is_type( 'variation' ) ) {
                $product_id = (int) $variation_product->get_parent_id();
            }
        } else {
            $maybe_variation = wc_get_product( $product_id );

            if ( $maybe_variation && $maybe_variation->is_type( 'variation' ) ) {
                $variation_id = $product_id;
                $product_id   = (int) $maybe_variation->get_parent_id();
            }
        }

        $parent_product = wc_get_product( $product_id );

        if ( ! $variation_id && $parent_product && $parent_product->is_type( 'variable' ) ) {
            $variation_id = self::find_matching_variation_id( $parent_product, $stored_attributes );
        }

        $product = wc_get_product( $variation_id ?: $product_id );

        if ( ! $product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
            wc_add_notice( __( 'Este producto no está disponible para regalar en este momento.', 'sultana-commerce-core' ), 'error' );
            wp_safe_redirect( self::get_share_url( (int) $owner->ID ) );
            exit;
        }

        $attributes = [];

        if ( $variation_id ) {
            $variation = wc_get_product( $variation_id );

            if ( ! $variation || ! $variation->is_type( 'variation' ) || (int) $variation->get_parent_id() !== $product_id ) {
                wc_add_notice( __( 'No pudimos validar las opciones de este regalo.', 'sultana-commerce-core' ), 'error' );
                wp_safe_redirect( self::get_share_url( (int) $owner->ID ) );
                exit;
            }

            $attributes = self::get_cart_variation_attributes( $variation, $stored_attributes, $parent_product );

            if ( empty( $attributes ) ) {
                wc_add_notice( __( 'No pudimos validar las opciones de este regalo.', 'sultana-commerce-core' ), 'error' );
                wp_safe_redirect( self::get_share_url( (int) $owner->ID ) );
                exit;
            }
        }

        $cart_item_data = [
            'scc_wishlist_gift' => [
                'owner_id'      => (int) $owner->ID,
                'owner_name'    => self::get_owner_display_name( $owner ),
                'giver_id'      => get_current_user_id(),
                'wishlist_key'  => $wishlist_key,
                'wishlist_token'=> $token,
            ],
            'scc_wishlist_gift_key' => md5( $token . '|' . $wishlist_key . '|' . time() ),
        ];

        $cart_item_key = WC()->cart->add_to_cart( $product_id, 1, $variation_id, $attributes, $cart_item_data );

        if ( ! $cart_item_key ) {
            if ( function_exists( 'wc_notice_count' ) && 0 === wc_notice_count( 'error' ) ) {
                wc_add_notice( __( 'No pudimos agregar este regalo al carrito.', 'sultana-commerce-core' ), 'error' );
            }

            wp_safe_redirect( self::get_share_url( (int) $owner->ID ) );
            exit;
        }

        wc_add_notice( __( 'Producto agregado al carrito como regalo.', 'sultana-commerce-core' ), 'success' );
        wp_safe_redirect( $redirect );
        exit;
    }

    public static function validate_personal_add_to_cart( bool $passed, int $product_id, int $quantity, int $variation_id = 0, array $variations = [] ): bool
    {
        if ( ! $passed || empty( $product_id ) || ! self::cart_has_gift_items() ) {
            return $passed;
        }

        wc_add_notice( __( 'Tienes una compra de regalos en proceso. Finalizala o vacia el carrito antes de agregar productos para ti.', 'sultana-commerce-core' ), 'error' );

        return false;
    }

    public static function display_gift_cart_item_data( array $item_data, array $cart_item ): array
    {
        if ( empty( $cart_item['scc_wishlist_gift']['owner_name'] ) ) {
            return $item_data;
        }

        $item_data[] = [
            'key'   => __( 'Regalo para', 'sultana-commerce-core' ),
            'value' => esc_html( $cart_item['scc_wishlist_gift']['owner_name'] ),
        ];

        return $item_data;
    }

    public static function add_gift_order_line_meta( $item, string $cart_item_key, array $values, $order ): void
    {
        if ( empty( $values['scc_wishlist_gift'] ) || ! is_array( $values['scc_wishlist_gift'] ) ) {
            return;
        }

        $gift = $values['scc_wishlist_gift'];

        $item->add_meta_data( '_scc_wishlist_gift_owner_id', absint( $gift['owner_id'] ?? 0 ), true );
        $item->add_meta_data( '_scc_wishlist_gift_giver_id', absint( $gift['giver_id'] ?? 0 ), true );
        $item->add_meta_data( '_scc_wishlist_gift_key', sanitize_text_field( $gift['wishlist_key'] ?? '' ), true );
        $item->add_meta_data( __( 'Regalo para', 'sultana-commerce-core' ), sanitize_text_field( $gift['owner_name'] ?? '' ), true );
    }

    public static function add_gift_order_meta( $order, array $data ): void
    {
        if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
            return;
        }

        $gift = self::get_cart_gift_context();

        if ( empty( $gift['owner_id'] ) ) {
            return;
        }

        $owner_id   = absint( $gift['owner_id'] );
        $giver_id   = absint( $gift['giver_id'] ?? get_current_user_id() );
        $owner      = get_user_by( 'id', $owner_id );
        $owner_name = $owner instanceof \WP_User ? self::get_owner_display_name( $owner ) : '';
        $address    = self::get_delivery_address_fields( $owner_id );

        $order->update_meta_data( '_scc_wishlist_gift_order', 'yes' );
        $order->update_meta_data( '_scc_wishlist_recipient_user_id', $owner_id );
        $order->update_meta_data( '_scc_wishlist_giver_user_id', $giver_id );
        $order->update_meta_data( '_scc_wishlist_recipient_name', $owner_name );
        $order->update_meta_data( '_scc_wishlist_recipient_email', $address['email'] );

        $order->update_meta_data( '_scc_wishlist_recipient_address', self::format_delivery_address( $address ) );
    }

    public static function apply_gift_order_shipping_address( $order, array $data ): void
    {
        if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
            return;
        }

        $gift = self::get_cart_gift_context();

        if ( empty( $gift['owner_id'] ) ) {
            return;
        }

        $address = self::get_delivery_address_fields( absint( $gift['owner_id'] ) );

        $order->set_shipping_country( 'NI' );
        $order->set_shipping_postcode( '' );
        $order->set_shipping_company( '' );
        $order->set_shipping_first_name( $address['first_name'] );
        $order->set_shipping_last_name( $address['last_name'] );
        $order->set_shipping_address_1( $address['address_1'] );
        $order->set_shipping_address_2( '' );
        $order->set_shipping_city( $address['city'] );
        $order->set_shipping_state( $address['state'] );

        if ( is_callable( [ $order, 'set_shipping_phone' ] ) ) {
            $order->set_shipping_phone( $address['phone'] );
        }

        if ( is_callable( [ $order, 'set_shipping_email' ] ) ) {
            $order->set_shipping_email( $address['email'] );
        }
    }

    public static function render_gift_shipping_email_in_admin( $order ): void
    {
        if ( ! $order || 'yes' !== $order->get_meta( '_scc_wishlist_gift_order' ) ) {
            return;
        }

        $email = sanitize_email( (string) $order->get_meta( '_scc_wishlist_recipient_email' ) );

        if ( '' === $email ) {
            $owner_id = absint( $order->get_meta( '_scc_wishlist_recipient_user_id' ) );
            $email    = $owner_id > 0 ? sanitize_email( (string) get_user_meta( $owner_id, 'billing_email', true ) ) : '';
        }

        if ( '' === $email ) {
            return;
        }

        ?>
        <p>
            <strong><?php esc_html_e( 'Dirección de correo electrónico:', 'sultana-commerce-core' ); ?></strong>
            <br>
            <a href="<?php echo esc_url( 'mailto:' . $email ); ?>"><?php echo esc_html( $email ); ?></a>
        </p>
        <?php
    }

    public static function add_gift_private_order_note( $order ): void
    {
        if ( ! $order || 'yes' !== $order->get_meta( '_scc_wishlist_gift_order' ) ) {
            return;
        }

        $owner_name = (string) $order->get_meta( '_scc_wishlist_recipient_name' );
        $address    = $order->get_meta( '_scc_wishlist_recipient_address' );
        $address    = is_array( $address ) ? $address : [];

        $order->add_order_note(
            sprintf(
                /* translators: 1: recipient name, 2: recipient address. */
                __( 'Pedido de regalo para %1$s. Enviar a: %2$s', 'sultana-commerce-core' ),
                $owner_name,
                implode( ', ', array_filter( $address ) )
            ),
            false,
            false
        );
    }

    public static function render_gift_cart_notice(): void
    {
        $gift = self::get_cart_gift_context();

        if ( empty( $gift['owner_name'] ) ) {
            return;
        }

        wc_print_notice(
            sprintf(
                /* translators: %s: wishlist owner display name. */
                __( 'Este pedido contiene un regalo para %s. La dirección de entrega se mantiene privada y será gestionada por la tienda.', 'sultana-commerce-core' ),
                esc_html( $gift['owner_name'] )
            ),
            'notice'
        );
    }

    public static function get_cart_gift_notice_context(): array
    {
        return self::get_cart_gift_context();
    }

    public static function get_cart_gift_shipping_destination(): array
    {
        $gift = self::get_cart_gift_context();

        if ( empty( $gift['owner_id'] ) ) {
            return [];
        }

        $owner_id = absint( $gift['owner_id'] );

        return [
            'state' => (string) get_user_meta( $owner_id, 'billing_state', true ),
            'city'  => (string) get_user_meta( $owner_id, 'billing_city', true ),
        ];
    }

    public static function login_required_ajax(): void
    {
        self::send_error( __( 'Inicia sesión para usar tu lista de deseos.', 'sultana-commerce-core' ), 401 );
    }

    public static function render_shared_meta_tags(): void
    {
        $token = self::get_current_share_token();

        if ( '' === $token ) {
            return;
        }

        $owner = self::get_user_by_share_token( $token );

        if ( ! $owner ) {
            return;
        }

        $owner_name  = self::get_owner_display_name( $owner );
        $store_name  = StoreBranding::get_name();
        $title       = sprintf(
            /* translators: 1: wishlist owner display name, 2: store name. */
            __( 'Lista de deseos de %1$s | %2$s', 'sultana-commerce-core' ),
            $owner_name,
            $store_name
        );
        $description = sprintf(
            /* translators: 1: wishlist owner display name, 2: store name. */
            __( 'Descubre los productos que %1$s agregó a su lista de deseos en %2$s.', 'sultana-commerce-core' ),
            $owner_name,
            $store_name
        );
        $image       = StoreBranding::get_logo_url();
        $url         = self::get_share_url( (int) $owner->ID );

        echo "\n" . '<meta property="og:type" content="website">' . "\n";
        echo '<meta property="og:title" content="' . esc_attr( $title ) . '">' . "\n";
        echo '<meta property="og:description" content="' . esc_attr( $description ) . '">' . "\n";
        echo '<meta property="og:url" content="' . esc_url( $url ) . '">' . "\n";
        echo '<meta property="og:site_name" content="' . esc_attr( $store_name ) . '">' . "\n";
        if ( '' !== $image ) {
            echo '<meta property="og:image" content="' . esc_url( $image ) . '">' . "\n";
        }
        echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
        echo '<meta name="twitter:title" content="' . esc_attr( $title ) . '">' . "\n";
        echo '<meta name="twitter:description" content="' . esc_attr( $description ) . '">' . "\n";
        if ( '' !== $image ) {
            echo '<meta name="twitter:image" content="' . esc_url( $image ) . '">' . "\n";
        }
    }

    private static function is_gift_cart_item( array $cart_item ): bool
    {
        return ! empty( $cart_item['scc_wishlist_gift'] ) && is_array( $cart_item['scc_wishlist_gift'] );
    }

    private static function remove_wishlist_item( int $user_id, string $key )
    {
        $items = self::get_items( $user_id );

        if ( $user_id <= 0 || '' === $key || ! isset( $items[ $key ] ) ) {
            return new \WP_Error( 'scc_wishlist_item_missing', __( 'Este producto ya no está en tu lista de deseos.', 'sultana-commerce-core' ) );
        }

        unset( $items[ $key ] );
        update_user_meta( $user_id, self::META_KEY, $items );

        return [
            'wishlist_count' => count( $items ),
        ];
    }

    private static function add_wishlist_item_to_cart( int $user_id, string $key )
    {
        if ( ! function_exists( 'WC' ) || ! function_exists( 'wc_get_product' ) ) {
            return new \WP_Error( 'scc_wishlist_wc_unavailable', __( 'WooCommerce no está disponible.', 'sultana-commerce-core' ) );
        }

        if ( ! WC()->cart && function_exists( 'wc_load_cart' ) ) {
            wc_load_cart();
        }

        if ( ! WC()->cart ) {
            return new \WP_Error( 'scc_wishlist_cart_unavailable', __( 'No pudimos iniciar el carrito. Intenta de nuevo.', 'sultana-commerce-core' ) );
        }

        if ( $user_id <= 0 || '' === $key ) {
            return new \WP_Error( 'scc_wishlist_invalid_item', __( 'No pudimos identificar este producto en tu lista de deseos.', 'sultana-commerce-core' ) );
        }

        $item = self::get_item( $user_id, $key );

        if ( ! $item ) {
            return new \WP_Error( 'scc_wishlist_item_missing', __( 'Este producto ya no está en tu lista de deseos.', 'sultana-commerce-core' ) );
        }

        $product_id        = absint( $item['product_id'] ?? 0 );
        $variation_id      = absint( $item['variation_id'] ?? 0 );
        $stored_attributes = is_array( $item['attributes'] ?? null ) ? $item['attributes'] : [];

        if ( $variation_id ) {
            $variation_product = wc_get_product( $variation_id );

            if ( $variation_product && $variation_product->is_type( 'variation' ) ) {
                $product_id = (int) $variation_product->get_parent_id();
            }
        } else {
            $maybe_variation = wc_get_product( $product_id );

            if ( $maybe_variation && $maybe_variation->is_type( 'variation' ) ) {
                $variation_id = $product_id;
                $product_id   = (int) $maybe_variation->get_parent_id();
            }
        }

        $parent_product = wc_get_product( $product_id );

        if ( ! $parent_product ) {
            return new \WP_Error( 'scc_wishlist_product_missing', __( 'No pudimos encontrar este producto.', 'sultana-commerce-core' ) );
        }

        if ( ! $variation_id && $parent_product->is_type( 'variable' ) ) {
            $variation_id = self::find_matching_variation_id( $parent_product, $stored_attributes );
        }

        $product = wc_get_product( $variation_id ?: $product_id );

        if ( ! $product ) {
            return new \WP_Error( 'scc_wishlist_product_missing', __( 'No pudimos encontrar este producto.', 'sultana-commerce-core' ) );
        }

        if ( ! $product->is_purchasable() ) {
            return new \WP_Error( 'scc_wishlist_not_purchasable', __( 'Este producto no está disponible para comprar en este momento.', 'sultana-commerce-core' ) );
        }

        if ( ! $product->is_in_stock() ) {
            return new \WP_Error( 'scc_wishlist_out_of_stock', __( 'Este producto está agotado.', 'sultana-commerce-core' ) );
        }

        $attributes = [];

        if ( $variation_id ) {
            $variation = wc_get_product( $variation_id );

            if ( ! $variation || ! $variation->is_type( 'variation' ) || (int) $variation->get_parent_id() !== $product_id ) {
                return new \WP_Error( 'scc_wishlist_invalid_variation', __( 'No pudimos validar las opciones seleccionadas.', 'sultana-commerce-core' ) );
            }

            $attributes         = self::get_cart_variation_attributes( $variation, $stored_attributes, $parent_product );
            $missing_attributes = array_filter(
                $attributes,
                static function ( $value ): bool {
                    return '' === (string) $value;
                }
            );

            if ( empty( $attributes ) || ! empty( $missing_attributes ) ) {
                return new \WP_Error( 'scc_wishlist_invalid_variation', __( 'No pudimos validar las opciones seleccionadas.', 'sultana-commerce-core' ) );
            }
        } elseif ( $parent_product->is_type( 'variable' ) ) {
            return new \WP_Error( 'scc_wishlist_invalid_variation', __( 'No pudimos validar las opciones seleccionadas.', 'sultana-commerce-core' ) );
        }

        if ( function_exists( 'wc_clear_notices' ) ) {
            wc_clear_notices();
        }

        $cart_item_key = WC()->cart->add_to_cart( $product_id, 1, $variation_id, $attributes );

        if ( ! $cart_item_key ) {
            return new \WP_Error( 'scc_wishlist_add_to_cart_failed', self::get_cart_error_message( __( 'No pudimos agregar este producto al carrito.', 'sultana-commerce-core' ) ) );
        }

        $items = self::get_items( $user_id );
        unset( $items[ $key ] );
        update_user_meta( $user_id, self::META_KEY, $items );

        return [
            'wishlist_count' => count( $items ),
            'cart_count'     => WC()->cart->get_cart_contents_count(),
        ];
    }

    private static function get_plugin_template_path(): string
    {
        return defined( 'SCC_PLUGIN_PATH' ) ? trailingslashit( SCC_PLUGIN_PATH ) . 'templates/' : '';
    }

    private static function get_account_wishlist_redirect_url(): string
    {
        $referer = wp_get_referer();

        if ( is_string( $referer ) && '' !== $referer ) {
            return $referer;
        }

        return function_exists( 'wc_get_account_endpoint_url' )
            ? wc_get_account_endpoint_url( self::ENDPOINT )
            : home_url( '/' );
    }

    private static function is_core_template_path( string $template ): bool
    {
        if ( '' === $template || ! defined( 'SCC_PLUGIN_PATH' ) ) {
            return false;
        }

        $core_templates_path = wp_normalize_path( trailingslashit( SCC_PLUGIN_PATH ) . 'templates/' );
        $template            = wp_normalize_path( $template );

        return str_starts_with( $template, $core_templates_path );
    }

    private static function cart_has_gift_items(): bool
    {
        if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
            return false;
        }

        foreach ( WC()->cart->get_cart() as $cart_item ) {
            if ( self::is_gift_cart_item( $cart_item ) ) {
                return true;
            }
        }

        return false;
    }

    private static function cart_has_personal_items(): bool
    {
        if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
            return false;
        }

        foreach ( WC()->cart->get_cart() as $cart_item ) {
            if ( ! self::is_gift_cart_item( $cart_item ) ) {
                return true;
            }
        }

        return false;
    }

    private static function verify_ajax_request(): void
    {
        if ( ! function_exists( 'wc_get_product' ) ) {
            self::send_error( __( 'WooCommerce no está disponible.', 'sultana-commerce-core' ), 503 );
        }

        if ( ! is_user_logged_in() ) {
            self::login_required_ajax();
        }

        $nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );

        if ( ! wp_verify_nonce( $nonce, 'scc_wishlist' ) ) {
            self::send_error( __( 'No pudimos validar la solicitud. Actualiza la página e intenta de nuevo.', 'sultana-commerce-core' ), 403 );
        }
    }

    private static function get_owner_display_name( \WP_User $owner ): string
    {
        $display_name = trim( (string) $owner->display_name );

        if ( '' !== $display_name ) {
            return $display_name;
        }

        return trim( (string) $owner->first_name ) ?: __( 'alguien especial', 'sultana-commerce-core' );
    }

    private static function get_cart_gift_context(): array
    {
        if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
            return [];
        }

        foreach ( WC()->cart->get_cart() as $cart_item ) {
            if ( ! empty( $cart_item['scc_wishlist_gift'] ) && is_array( $cart_item['scc_wishlist_gift'] ) ) {
                return $cart_item['scc_wishlist_gift'];
            }
        }

        return [];
    }

    private static function owner_has_delivery_address( int $owner_id ): bool
    {
        foreach ( [ 'billing_first_name', 'billing_last_name', 'billing_address_1', 'billing_city', 'billing_state', 'billing_phone' ] as $field ) {
            if ( '' === trim( (string) get_user_meta( $owner_id, $field, true ) ) ) {
                return false;
            }
        }

        return true;
    }

    private static function get_delivery_address( int $owner_id ): array
    {
        return self::format_delivery_address( self::get_delivery_address_fields( $owner_id ) );
    }

    private static function format_delivery_address( array $address ): array
    {
        return [
            trim( (string) ( $address['first_name'] ?? '' ) . ' ' . (string) ( $address['last_name'] ?? '' ) ),
            (string) ( $address['address_1'] ?? '' ),
            (string) ( $address['city'] ?? '' ),
            (string) ( $address['state'] ?? '' ),
            (string) ( $address['phone'] ?? '' ),
        ];
    }

    private static function get_delivery_address_fields( int $owner_id ): array
    {
        return [
            'first_name' => sanitize_text_field( (string) get_user_meta( $owner_id, 'billing_first_name', true ) ),
            'last_name'  => sanitize_text_field( (string) get_user_meta( $owner_id, 'billing_last_name', true ) ),
            'address_1'  => sanitize_text_field( (string) get_user_meta( $owner_id, 'billing_address_1', true ) ),
            'city'       => sanitize_text_field( (string) get_user_meta( $owner_id, 'billing_city', true ) ),
            'state'      => sanitize_text_field( (string) get_user_meta( $owner_id, 'billing_state', true ) ),
            'phone'      => sanitize_text_field( (string) get_user_meta( $owner_id, 'billing_phone', true ) ),
            'email'      => sanitize_email( (string) get_user_meta( $owner_id, 'billing_email', true ) ),
        ];
    }

    private static function is_valid_share_token( string $token ): bool
    {
        return 1 === preg_match( '/^[a-f0-9]{48}$/', $token );
    }

    private static function generate_unique_share_token(): string
    {
        do {
            $token = bin2hex( random_bytes( 24 ) );
        } while ( self::get_user_by_share_token( $token ) );

        return $token;
    }

    private static function sanitize_attributes( $raw_attributes ): array
    {
        if ( is_string( $raw_attributes ) ) {
            $decoded = json_decode( wp_unslash( $raw_attributes ), true );
            $raw_attributes = is_array( $decoded ) ? $decoded : [];
        }

        if ( ! is_array( $raw_attributes ) ) {
            return [];
        }

        $attributes = [];

        foreach ( $raw_attributes as $name => $value ) {
            $attribute_name  = sanitize_title( wp_unslash( (string) $name ) );
            $attribute_value = sanitize_title( wp_unslash( (string) $value ) );

            if ( '' === $attribute_name || '' === $attribute_value ) {
                continue;
            }

            $attributes[ $attribute_name ] = $attribute_value;
        }

        ksort( $attributes );

        return $attributes;
    }

    private static function normalize_variation_attributes( array $attributes ): array
    {
        $normalized = [];

        foreach ( $attributes as $name => $value ) {
            $attribute_name  = 0 === strpos( $name, 'attribute_' ) ? $name : 'attribute_' . $name;
            $attribute_value = (string) $value;

            if ( '' === $attribute_value ) {
                continue;
            }

            $normalized[ sanitize_title( $attribute_name ) ] = sanitize_title( $attribute_value );
        }

        ksort( $normalized );

        return $normalized;
    }

    private static function get_cart_variation_attributes( \WC_Product_Variation $variation, array $stored_attributes = [], $parent_product = null ): array
    {
        $cart_attributes = [];
        $variation_attributes = $variation->get_variation_attributes();
        $required_attributes  = $parent_product && $parent_product->is_type( 'variable' )
            ? array_keys( $parent_product->get_variation_attributes() )
            : array_keys( $variation_attributes );

        foreach ( $required_attributes as $name ) {
            $attribute_name = self::get_cart_attribute_name( (string) $name );
            $attribute_value = isset( $variation_attributes[ $attribute_name ] )
                ? (string) $variation_attributes[ $attribute_name ]
                : '';

            if ( '' === $attribute_value ) {
                $attribute_value = self::find_stored_attribute_value( $attribute_name, $stored_attributes );
            }

            $cart_attributes[ $attribute_name ] = $attribute_value;
        }

        ksort( $cart_attributes );

        return $cart_attributes;
    }

    private static function find_matching_variation_id( $product, array $stored_attributes ): int
    {
        if ( ! $product || ! $product->is_type( 'variable' ) || empty( $stored_attributes ) ) {
            return 0;
        }

        $attributes = [];

        foreach ( array_keys( $product->get_variation_attributes() ) as $name ) {
            $attribute_name  = self::get_cart_attribute_name( (string) $name );
            $attribute_value = self::find_stored_attribute_value( $attribute_name, $stored_attributes );

            if ( '' !== $attribute_value ) {
                $attributes[ $attribute_name ] = $attribute_value;
            }
        }

        if ( empty( $attributes ) ) {
            return 0;
        }

        $data_store = \WC_Data_Store::load( 'product' );

        if ( ! $data_store || ! method_exists( $data_store, 'find_matching_product_variation' ) ) {
            return 0;
        }

        return absint( $data_store->find_matching_product_variation( $product, $attributes ) );
    }

    private static function get_cart_attribute_name( string $name ): string
    {
        if ( 0 === strpos( $name, 'attribute_' ) ) {
            return $name;
        }

        return function_exists( 'wc_variation_attribute_name' )
            ? wc_variation_attribute_name( $name )
            : 'attribute_' . sanitize_title( $name );
    }

    private static function find_stored_attribute_value( string $attribute_name, array $stored_attributes ): string
    {
        $attribute_base = preg_replace( '/^attribute_/', '', $attribute_name );
        $fallback_names = [
            $attribute_name,
            sanitize_title( $attribute_name ),
            $attribute_base,
            sanitize_title( (string) $attribute_base ),
            str_replace( '_', '-', $attribute_name ),
            str_replace( '_', '-', (string) $attribute_base ),
        ];

        foreach ( $fallback_names as $name ) {
            if ( is_string( $name ) && isset( $stored_attributes[ $name ] ) && '' !== (string) $stored_attributes[ $name ] ) {
                return sanitize_title( (string) $stored_attributes[ $name ] );
            }
        }

        return '';
    }

    private static function get_attribute_display_value( string $attribute_name, string $value ): string
    {
        if ( taxonomy_exists( $attribute_name ) ) {
            $term = get_term_by( 'slug', $value, $attribute_name );

            if ( $term && ! is_wp_error( $term ) ) {
                return $term->name;
            }
        }

        return wc_clean( rawurldecode( $value ) );
    }

    private static function build_item_key( int $product_id, int $variation_id = 0, array $attributes = [] ): string
    {
        ksort( $attributes );

        return md5( wp_json_encode( [ $product_id, $variation_id, $attributes ] ) );
    }

    private static function send_error( string $message, int $status_code = 400 ): void
    {
        wp_send_json_error(
            [
                'message' => $message,
            ],
            $status_code
        );
    }

    private static function get_cart_error_message( string $fallback ): string
    {
        if ( function_exists( 'wc_get_notices' ) ) {
            $notices = wc_get_notices( 'error' );

            foreach ( $notices as $notice ) {
                $message = is_array( $notice ) ? (string) ( $notice['notice'] ?? '' ) : (string) $notice;
                $message = trim( wp_strip_all_tags( $message ) );

                if ( '' !== $message ) {
                    if ( function_exists( 'wc_clear_notices' ) ) {
                        wc_clear_notices();
                    }

                    return $message;
                }
            }
        }

        if ( function_exists( 'wc_clear_notices' ) ) {
            wc_clear_notices();
        }

        return $fallback;
    }
}

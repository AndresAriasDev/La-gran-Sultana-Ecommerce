<?php

namespace Sultana\Admin\Core;

use Sultana\Admin\Customers\CustomerController;
use Sultana\Admin\Orders\OrderController;
use Sultana\Admin\Products\ProductController;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Router
{
    private const QUERY_VAR = 'sultana_admin_route';
    private const ROUTES = [ 'dashboard', 'login', 'logout', 'products', 'product_new', 'product_edit', 'orders', 'order_view', 'customers', 'customer_view' ];

    public static function register_rewrite_rules(): void
    {
        add_rewrite_rule( '^gestion/?$', 'index.php?' . self::QUERY_VAR . '=dashboard', 'top' );
        add_rewrite_rule( '^gestion/login/?$', 'index.php?' . self::QUERY_VAR . '=login', 'top' );
        add_rewrite_rule( '^gestion/logout/?$', 'index.php?' . self::QUERY_VAR . '=logout', 'top' );
        add_rewrite_rule( '^gestion/productos/nuevo/?$', 'index.php?' . self::QUERY_VAR . '=product_new', 'top' );
        add_rewrite_rule( '^gestion/productos/([0-9]+)/?$', 'index.php?' . self::QUERY_VAR . '=product_edit&sultana_admin_product_id=$matches[1]', 'top' );
        add_rewrite_rule( '^gestion/productos/?$', 'index.php?' . self::QUERY_VAR . '=products', 'top' );
        add_rewrite_rule( '^gestion/pedidos/([0-9]+)/?$', 'index.php?' . self::QUERY_VAR . '=order_view&sultana_admin_order_id=$matches[1]', 'top' );
        add_rewrite_rule( '^gestion/pedidos/?$', 'index.php?' . self::QUERY_VAR . '=orders', 'top' );
        add_rewrite_rule( '^gestion/clientes/([0-9]+)/?$', 'index.php?' . self::QUERY_VAR . '=customer_view&sultana_admin_customer_id=$matches[1]', 'top' );
        add_rewrite_rule( '^gestion/clientes/?$', 'index.php?' . self::QUERY_VAR . '=customers', 'top' );
    }

    public static function register_query_vars( array $vars ): array
    {
        $vars[] = self::QUERY_VAR;
        $vars[] = 'sultana_admin_product_id';
        $vars[] = 'sultana_admin_order_id';
        $vars[] = 'sultana_admin_customer_id';

        return $vars;
    }

    public static function handle_request(): void
    {
        $route = self::current_route();

        if ( ! self::is_valid_route( $route ) ) {
            return;
        }

        if ( 'logout' === $route ) {
            Auth::handle_logout();
        }

        if ( ! Bootstrap::dependencies_available() ) {
            self::render_dependencies_unavailable();
            exit;
        }

        if ( 'login' === $route ) {
            self::handle_login_request();
            exit;
        }

        if ( ! is_user_logged_in() ) {
            wp_safe_redirect( self::login_url() );
            exit;
        }

        if ( ! current_user_can( Capabilities::ACCESS_CAPABILITY ) ) {
            self::render_forbidden();
            exit;
        }

        if ( 'product_new' === $route && ! current_user_can( Capabilities::CREATE_PRODUCTS_CAPABILITY ) ) {
            self::render_forbidden();
            exit;
        }

        if ( 'product_edit' === $route && ! current_user_can( Capabilities::CREATE_PRODUCTS_CAPABILITY ) ) {
            self::render_forbidden();
            exit;
        }

        if ( in_array( $route, [ 'orders', 'order_view', 'customers', 'customer_view' ], true ) && ! current_user_can( Capabilities::READ_ORDERS_CAPABILITY ) ) {
            self::render_forbidden();
            exit;
        }

        self::render_admin_screen( $route );
        exit;
    }

    public static function dashboard_url(): string
    {
        return home_url( '/gestion/' );
    }

    public static function login_url(): string
    {
        return home_url( '/gestion/login/' );
    }

    public static function logout_url(): string
    {
        return home_url( '/gestion/logout/' );
    }

    public static function products_url(): string
    {
        return home_url( '/gestion/productos/' );
    }

    public static function new_product_url(): string
    {
        return home_url( '/gestion/productos/nuevo/' );
    }

    public static function edit_product_url( int $product_id ): string
    {
        return home_url( '/gestion/productos/' . absint( $product_id ) . '/' );
    }

    public static function orders_url(): string
    {
        return home_url( '/gestion/pedidos/' );
    }

    public static function order_url( int $order_id ): string
    {
        return home_url( '/gestion/pedidos/' . absint( $order_id ) . '/' );
    }

    public static function customers_url(): string
    {
        return home_url( '/gestion/clientes/' );
    }

    public static function customer_url( int $customer_id ): string
    {
        return home_url( '/gestion/clientes/' . absint( $customer_id ) . '/' );
    }

    public static function is_admin_request(): bool
    {
        return self::is_valid_route( self::current_route() );
    }

    private static function current_route(): string
    {
        $route = get_query_var( self::QUERY_VAR );

        return is_string( $route ) ? sanitize_key( $route ) : '';
    }

    private static function is_valid_route( string $route ): bool
    {
        return in_array( $route, self::ROUTES, true );
    }

    private static function render_admin_screen( string $route ): void
    {
        nocache_headers();

        $current_user = wp_get_current_user();
        $logout_url   = self::logout_url();
        $active_route = in_array( $route, [ 'product_new', 'product_edit' ], true ) ? 'products' : $route;
        $active_route = 'order_view' === $active_route ? 'orders' : $active_route;
        $active_route = 'customer_view' === $active_route ? 'customers' : $active_route;
        $nav_items    = self::admin_nav_items();
        $screen       = self::screen_config( $route );
        $screen_data  = self::screen_data( $route );

        Assets::enqueue( $route, $screen_data );

        if ( ! empty( $screen_data['not_found'] ) ) {
            status_header( 404 );
        } elseif ( ! empty( $screen_data['forbidden'] ) ) {
            status_header( 403 );
        } else {
            status_header( 200 );
        }

        require SULTANA_ADMIN_PATH . 'templates/layout.php';
    }

    private static function admin_nav_items(): array
    {
        return [
            'dashboard' => [
                'label' => __( 'Inicio', 'sultana-admin' ),
                'url'   => self::dashboard_url(),
            ],
            'products'  => [
                'label' => __( 'Productos', 'sultana-admin' ),
                'url'   => self::products_url(),
            ],
            'orders'    => [
                'label' => __( 'Pedidos', 'sultana-admin' ),
                'url'   => self::orders_url(),
            ],
            'customers' => [
                'label' => __( 'Clientes', 'sultana-admin' ),
                'url'   => self::customers_url(),
            ],
        ];
    }

    private static function screen_config( string $route ): array
    {
        $screens = [
            'dashboard' => [
                'title'    => __( 'Inicio', 'sultana-admin' ),
                'subtitle' => __( 'Inicio', 'sultana-admin' ),
                'template' => SULTANA_ADMIN_PATH . 'templates/dashboard.php',
            ],
            'products'  => [
                'title'    => __( 'Productos', 'sultana-admin' ),
                'subtitle' => __( 'Productos', 'sultana-admin' ),
                'template' => SULTANA_ADMIN_PATH . 'templates/products.php',
            ],
            'product_new' => [
                'title'    => __( 'Nuevo producto', 'sultana-admin' ),
                'subtitle' => __( 'Nuevo producto', 'sultana-admin' ),
                'template' => SULTANA_ADMIN_PATH . 'templates/product-new.php',
            ],
            'product_edit' => [
                'title'    => __( 'Editar producto', 'sultana-admin' ),
                'subtitle' => __( 'Editar producto', 'sultana-admin' ),
                'template' => SULTANA_ADMIN_PATH . 'templates/product-new.php',
            ],
            'orders'    => [
                'title'    => __( 'Pedidos', 'sultana-admin' ),
                'subtitle' => __( 'Pedidos', 'sultana-admin' ),
                'template' => SULTANA_ADMIN_PATH . 'templates/orders.php',
            ],
            'order_view' => [
                'title'    => __( 'Ver pedido', 'sultana-admin' ),
                'subtitle' => __( 'Pedido', 'sultana-admin' ),
                'template' => SULTANA_ADMIN_PATH . 'templates/order-view.php',
            ],
            'customers' => [
                'title'    => __( 'Clientes', 'sultana-admin' ),
                'subtitle' => __( 'Clientes', 'sultana-admin' ),
                'template' => SULTANA_ADMIN_PATH . 'templates/customers.php',
            ],
            'customer_view' => [
                'title'    => __( 'Ver cliente', 'sultana-admin' ),
                'subtitle' => __( 'Cliente', 'sultana-admin' ),
                'template' => SULTANA_ADMIN_PATH . 'templates/customer-view.php',
            ],
        ];

        return $screens[ $route ] ?? $screens['dashboard'];
    }

    private static function screen_data( string $route ): array
    {
        if ( 'products' === $route ) {
            return ProductController::prepare_list_screen();
        }

        if ( 'product_new' === $route ) {
            return ProductController::prepare_create_screen();
        }

        if ( 'product_edit' === $route ) {
            return ProductController::prepare_edit_screen( self::current_product_id() );
        }

        if ( 'orders' === $route ) {
            return OrderController::prepare_list_screen();
        }

        if ( 'order_view' === $route ) {
            return OrderController::prepare_view_screen( self::current_order_id() );
        }

        if ( 'customers' === $route ) {
            return CustomerController::prepare_list_screen();
        }

        if ( 'customer_view' === $route ) {
            return CustomerController::prepare_view_screen( self::current_customer_id() );
        }

        return [];
    }

    private static function current_product_id(): int
    {
        return absint( get_query_var( 'sultana_admin_product_id' ) );
    }

    private static function current_order_id(): int
    {
        return absint( get_query_var( 'sultana_admin_order_id' ) );
    }

    private static function current_customer_id(): int
    {
        return absint( get_query_var( 'sultana_admin_customer_id' ) );
    }

    private static function handle_login_request(): void
    {
        if ( is_user_logged_in() && current_user_can( Capabilities::ACCESS_CAPABILITY ) ) {
            wp_safe_redirect( self::dashboard_url() );
            exit;
        }

        $login_error = Auth::handle_login();

        self::render_login( $login_error );
    }

    private static function render_login( string $login_error = '' ): void
    {
        status_header( 200 );
        nocache_headers();
        Assets::enqueue();

        require SULTANA_ADMIN_PATH . 'templates/login.php';
    }

    private static function render_forbidden(): void
    {
        status_header( 403 );
        nocache_headers();
        Assets::enqueue();

        self::render_basic_page(
            __( 'Acceso denegado', 'sultana-admin' ),
            __( 'No tienes permisos para acceder a Sultana Admin.', 'sultana-admin' )
        );
    }

    private static function render_dependencies_unavailable(): void
    {
        status_header( 503 );
        nocache_headers();
        Assets::enqueue();

        self::render_basic_page(
            __( 'Sultana Admin no esta disponible', 'sultana-admin' ),
            __( 'WooCommerce y Sultana Commerce Core deben estar activos para usar este panel.', 'sultana-admin' )
        );
    }

    private static function render_basic_page( string $title, string $message ): void
    {
        ?>
        <!doctype html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo( 'charset' ); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?php echo esc_html( $title ); ?></title>
            <?php wp_head(); ?>
        </head>
        <body>
            <main class="sultana-admin-page sultana-admin-page--message">
                <h1><?php echo esc_html( $title ); ?></h1>
                <p><?php echo esc_html( $message ); ?></p>
            </main>
            <?php wp_footer(); ?>
        </body>
        </html>
        <?php
    }
}

<?php

namespace Sultana\Admin\Core;

use Sultana\Admin\Coupons\CouponController;
use Sultana\Admin\Customers\CustomerController;
use Sultana\Admin\Orders\OrderController;
use Sultana\Admin\Products\ProductController;
use Sultana\Admin\Promotions\PromotionController;
use Sultana\Admin\Reviews\ReviewController;
use Sultana\Admin\Statistics\StatisticsController;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Router
{
    private const QUERY_VAR = 'sultana_admin_route';
    private const ROUTES = [ 'dashboard', 'login', 'logout', 'password_request', 'password_reset', 'products', 'product_new', 'product_edit', 'banners', 'banner_new', 'banner_edit', 'orders', 'order_view', 'customers', 'customer_view', 'coupons', 'coupon_new', 'coupon_edit', 'reviews', 'statistics' ];

    public static function register_rewrite_rules(): void
    {
        add_rewrite_rule( '^gestion/?$', 'index.php?' . self::QUERY_VAR . '=dashboard', 'top' );
        add_rewrite_rule( '^gestion/login/?$', 'index.php?' . self::QUERY_VAR . '=login', 'top' );
        add_rewrite_rule( '^gestion/recuperar-contrasena/?$', 'index.php?' . self::QUERY_VAR . '=password_request', 'top' );
        add_rewrite_rule( '^gestion/restablecer-contrasena/?$', 'index.php?' . self::QUERY_VAR . '=password_reset', 'top' );
        add_rewrite_rule( '^gestion/logout/?$', 'index.php?' . self::QUERY_VAR . '=logout', 'top' );
        add_rewrite_rule( '^gestion/productos/nuevo/?$', 'index.php?' . self::QUERY_VAR . '=product_new', 'top' );
        add_rewrite_rule( '^gestion/productos/([0-9]+)/?$', 'index.php?' . self::QUERY_VAR . '=product_edit&sultana_admin_product_id=$matches[1]', 'top' );
        add_rewrite_rule( '^gestion/productos/?$', 'index.php?' . self::QUERY_VAR . '=products', 'top' );
        add_rewrite_rule( '^gestion/banners/nuevo/?$', 'index.php?' . self::QUERY_VAR . '=banner_new', 'top' );
        add_rewrite_rule( '^gestion/banners/editar/([0-9]+)/?$', 'index.php?' . self::QUERY_VAR . '=banner_edit&sultana_admin_promotion_id=$matches[1]', 'top' );
        add_rewrite_rule( '^gestion/banners/?$', 'index.php?' . self::QUERY_VAR . '=banners', 'top' );
        add_rewrite_rule( '^gestion/pedidos/([0-9]+)/?$', 'index.php?' . self::QUERY_VAR . '=order_view&sultana_admin_order_id=$matches[1]', 'top' );
        add_rewrite_rule( '^gestion/pedidos/?$', 'index.php?' . self::QUERY_VAR . '=orders', 'top' );
        add_rewrite_rule( '^gestion/clientes/([0-9]+)/?$', 'index.php?' . self::QUERY_VAR . '=customer_view&sultana_admin_customer_id=$matches[1]', 'top' );
        add_rewrite_rule( '^gestion/clientes/?$', 'index.php?' . self::QUERY_VAR . '=customers', 'top' );
        add_rewrite_rule( '^gestion/cupones/nuevo/?$', 'index.php?' . self::QUERY_VAR . '=coupon_new', 'top' );
        add_rewrite_rule( '^gestion/cupones/([0-9]+)/?$', 'index.php?' . self::QUERY_VAR . '=coupon_edit&sultana_admin_coupon_id=$matches[1]', 'top' );
        add_rewrite_rule( '^gestion/cupones/?$', 'index.php?' . self::QUERY_VAR . '=coupons', 'top' );
        add_rewrite_rule( '^gestion/resenas/?$', 'index.php?' . self::QUERY_VAR . '=reviews', 'top' );
        add_rewrite_rule( '^gestion/estadisticas/?$', 'index.php?' . self::QUERY_VAR . '=statistics', 'top' );
    }

    public static function register_query_vars( array $vars ): array
    {
        $vars[] = self::QUERY_VAR;
        $vars[] = 'sultana_admin_product_id';
        $vars[] = 'sultana_admin_order_id';
        $vars[] = 'sultana_admin_customer_id';
        $vars[] = 'sultana_admin_coupon_id';
        $vars[] = 'sultana_admin_promotion_id';

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

        if ( 'password_request' === $route ) {
            self::handle_password_request();
            exit;
        }

        if ( 'password_reset' === $route ) {
            self::handle_password_reset();
            exit;
        }

        if ( 'statistics' === $route ) {
            self::redirect_statistics_to_dashboard();
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

        if ( in_array( $route, [ 'dashboard', 'orders', 'order_view', 'customers', 'customer_view' ], true ) && ! current_user_can( Capabilities::READ_ORDERS_CAPABILITY ) ) {
            self::render_forbidden();
            exit;
        }

        if ( in_array( $route, [ 'coupons', 'coupon_new', 'coupon_edit' ], true ) && ! current_user_can( Capabilities::EDIT_COUPONS_CAPABILITY ) ) {
            self::render_forbidden();
            exit;
        }

        if ( 'reviews' === $route && ! current_user_can( Capabilities::MANAGE_REVIEWS_CAPABILITY ) ) {
            self::render_forbidden();
            exit;
        }

        if ( in_array( $route, [ 'banners', 'banner_new', 'banner_edit' ], true ) && ! current_user_can( Capabilities::MANAGE_HOME_PROMOTIONS_CAPABILITY ) ) {
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

    public static function password_request_url(): string
    {
        return home_url( '/gestion/recuperar-contrasena/' );
    }

    public static function password_reset_url( string $key, string $login ): string
    {
        return esc_url_raw(
            add_query_arg(
                [
                    'key'   => $key,
                    'login' => $login,
                ],
                home_url( '/gestion/restablecer-contrasena/' )
            )
        );
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

    public static function coupons_url(): string
    {
        return home_url( '/gestion/cupones/' );
    }

    public static function new_coupon_url(): string
    {
        return home_url( '/gestion/cupones/nuevo/' );
    }

    public static function coupon_url( int $coupon_id ): string
    {
        return home_url( '/gestion/cupones/' . absint( $coupon_id ) . '/' );
    }

    public static function statistics_url(): string
    {
        return self::dashboard_url();
    }

    public static function reviews_url(): string
    {
        return home_url( '/gestion/resenas/' );
    }

    public static function banners_url(): string
    {
        return home_url( '/gestion/banners/' );
    }

    public static function new_banner_url(): string
    {
        return home_url( '/gestion/banners/nuevo/' );
    }

    public static function edit_banner_url( int $promotion_id ): string
    {
        return home_url( '/gestion/banners/editar/' . absint( $promotion_id ) . '/' );
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
        $active_route = in_array( $active_route, [ 'coupon_new', 'coupon_edit' ], true ) ? 'coupons' : $active_route;
        $active_route = in_array( $active_route, [ 'banner_new', 'banner_edit' ], true ) ? 'banners' : $active_route;
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
            'coupons' => [
                'label' => __( 'Cupones', 'sultana-admin' ),
                'url'   => self::coupons_url(),
                'desktop_only' => true,
            ],
            'reviews' => [
                'label' => __( 'Reseñas', 'sultana-admin' ),
                'url'   => self::reviews_url(),
                'desktop_only' => true,
            ],
            'banners' => [
                'label' => __( 'Banners', 'sultana-admin' ),
                'url'   => self::banners_url(),
                'desktop_only' => true,
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
            'banners' => [
                'title'    => __( 'Banners', 'sultana-admin' ),
                'subtitle' => __( 'Banners', 'sultana-admin' ),
                'template' => SULTANA_ADMIN_PATH . 'templates/banners.php',
            ],
            'banner_new' => [
                'title'    => __( 'Nuevo banner', 'sultana-admin' ),
                'subtitle' => __( 'Banners', 'sultana-admin' ),
                'template' => SULTANA_ADMIN_PATH . 'templates/banners.php',
            ],
            'banner_edit' => [
                'title'    => __( 'Editar banner', 'sultana-admin' ),
                'subtitle' => __( 'Banners', 'sultana-admin' ),
                'template' => SULTANA_ADMIN_PATH . 'templates/banners.php',
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
            'coupons' => [
                'title'    => __( 'Cupones', 'sultana-admin' ),
                'subtitle' => __( 'Cupones', 'sultana-admin' ),
                'template' => SULTANA_ADMIN_PATH . 'templates/coupons.php',
            ],
            'coupon_new' => [
                'title'    => __( 'Nuevo cupon', 'sultana-admin' ),
                'subtitle' => __( 'Cupones', 'sultana-admin' ),
                'template' => SULTANA_ADMIN_PATH . 'templates/coupon-form.php',
            ],
            'coupon_edit' => [
                'title'    => __( 'Editar cupon', 'sultana-admin' ),
                'subtitle' => __( 'Cupones', 'sultana-admin' ),
                'template' => SULTANA_ADMIN_PATH . 'templates/coupon-form.php',
            ],
            'reviews' => [
                'title'    => __( 'Reseñas', 'sultana-admin' ),
                'subtitle' => __( 'Reseñas', 'sultana-admin' ),
                'template' => SULTANA_ADMIN_PATH . 'templates/reviews.php',
            ],
            'statistics' => [
                'title'    => __( 'Estadisticas', 'sultana-admin' ),
                'subtitle' => __( 'Estadisticas', 'sultana-admin' ),
                'template' => SULTANA_ADMIN_PATH . 'templates/statistics.php',
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

        if ( 'banners' === $route ) {
            return PromotionController::prepare_list_screen();
        }

        if ( 'banner_new' === $route ) {
            return PromotionController::prepare_create_screen();
        }

        if ( 'banner_edit' === $route ) {
            return PromotionController::prepare_edit_screen( self::current_promotion_id() );
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

        if ( 'coupons' === $route ) {
            return CouponController::prepare_list_screen();
        }

        if ( 'coupon_new' === $route ) {
            return CouponController::prepare_create_screen();
        }

        if ( 'coupon_edit' === $route ) {
            return CouponController::prepare_edit_screen( self::current_coupon_id() );
        }

        if ( 'reviews' === $route ) {
            return ReviewController::prepare_list_screen();
        }

        if ( in_array( $route, [ 'dashboard', 'statistics' ], true ) ) {
            return StatisticsController::prepare_screen();
        }

        return [];
    }

    private static function redirect_statistics_to_dashboard(): void
    {
        $period = isset( $_GET['period'] ) ? sanitize_key( wp_unslash( $_GET['period'] ) ) : '';
        $url    = self::dashboard_url();

        if ( in_array( $period, [ 'today', 'week', 'month' ], true ) ) {
            $url = add_query_arg( 'period', $period, $url );
        }

        wp_safe_redirect( $url, 301 );
        exit;
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

    private static function current_coupon_id(): int
    {
        return absint( get_query_var( 'sultana_admin_coupon_id' ) );
    }

    private static function current_promotion_id(): int
    {
        return absint( get_query_var( 'sultana_admin_promotion_id' ) );
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
        Assets::enqueue( 'login' );

        require SULTANA_ADMIN_PATH . 'templates/login.php';
    }

    private static function handle_password_request(): void
    {
        if ( is_user_logged_in() && current_user_can( Capabilities::ACCESS_CAPABILITY ) ) {
            wp_safe_redirect( self::dashboard_url() );
            exit;
        }

        $reset_state = Auth::handle_password_reset_request();

        self::render_password_request( $reset_state );
    }

    private static function render_password_request( array $reset_state ): void
    {
        status_header( 200 );
        nocache_headers();
        Assets::enqueue( 'password_request' );

        require SULTANA_ADMIN_PATH . 'templates/password-request.php';
    }

    private static function handle_password_reset(): void
    {
        if ( is_user_logged_in() && current_user_can( Capabilities::ACCESS_CAPABILITY ) ) {
            wp_safe_redirect( self::dashboard_url() );
            exit;
        }

        $reset_context = Auth::handle_password_reset_completion();

        self::render_password_reset( $reset_context );
    }

    private static function render_password_reset( array $reset_context ): void
    {
        status_header( 200 );
        nocache_headers();
        Assets::enqueue( 'password_reset' );

        require SULTANA_ADMIN_PATH . 'templates/password-reset.php';
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

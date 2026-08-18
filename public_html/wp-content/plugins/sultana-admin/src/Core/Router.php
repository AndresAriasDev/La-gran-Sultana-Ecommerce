<?php

namespace Sultana\Admin\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Router
{
    private const QUERY_VAR = 'sultana_admin_route';

    public static function register_rewrite_rules(): void
    {
        add_rewrite_rule( '^gestion/?$', 'index.php?' . self::QUERY_VAR . '=dashboard', 'top' );
        add_rewrite_rule( '^gestion/login/?$', 'index.php?' . self::QUERY_VAR . '=login', 'top' );
        add_rewrite_rule( '^gestion/logout/?$', 'index.php?' . self::QUERY_VAR . '=logout', 'top' );
    }

    public static function register_query_vars( array $vars ): array
    {
        $vars[] = self::QUERY_VAR;

        return $vars;
    }

    public static function handle_request(): void
    {
        $route = self::current_route();

        if ( ! in_array( $route, [ 'dashboard', 'login', 'logout' ], true ) ) {
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

        self::render_dashboard();
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

    private static function current_route(): string
    {
        $route = get_query_var( self::QUERY_VAR );

        return is_string( $route ) ? sanitize_key( $route ) : '';
    }

    private static function render_dashboard(): void
    {
        status_header( 200 );
        nocache_headers();
        Assets::enqueue();

        $current_user = wp_get_current_user();
        $logout_url   = self::logout_url();

        require SULTANA_ADMIN_PATH . 'templates/dashboard.php';
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

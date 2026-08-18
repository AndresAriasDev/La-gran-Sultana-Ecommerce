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
    }

    public static function register_query_vars( array $vars ): array
    {
        $vars[] = self::QUERY_VAR;

        return $vars;
    }

    public static function handle_request(): void
    {
        if ( 'dashboard' !== self::current_route() ) {
            return;
        }

        if ( ! is_user_logged_in() ) {
            wp_safe_redirect( wp_login_url( self::dashboard_url() ) );
            exit;
        }

        if ( ! current_user_can( Capabilities::ACCESS_CAPABILITY ) ) {
            self::render_forbidden();
            exit;
        }

        if ( ! Bootstrap::dependencies_available() ) {
            self::render_dependencies_unavailable();
            exit;
        }

        self::render_dashboard();
        exit;
    }

    public static function dashboard_url(): string
    {
        return home_url( '/gestion/' );
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

        $current_user = wp_get_current_user();
        $logout_url   = wp_logout_url( home_url( '/' ) );

        require SULTANA_ADMIN_PATH . 'templates/dashboard.php';
    }

    private static function render_forbidden(): void
    {
        status_header( 403 );
        nocache_headers();

        self::render_basic_page(
            __( 'Acceso denegado', 'sultana-admin' ),
            __( 'No tienes permisos para acceder a Sultana Admin.', 'sultana-admin' )
        );
    }

    private static function render_dependencies_unavailable(): void
    {
        status_header( 503 );
        nocache_headers();

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
            <main style="max-width:720px;margin:48px auto;padding:24px;font-family:system-ui,-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;">
                <h1><?php echo esc_html( $title ); ?></h1>
                <p><?php echo esc_html( $message ); ?></p>
            </main>
            <?php wp_footer(); ?>
        </body>
        </html>
        <?php
    }
}

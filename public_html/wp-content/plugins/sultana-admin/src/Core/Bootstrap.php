<?php

namespace Sultana\Admin\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Bootstrap
{
    public static function init(): void
    {
        add_action( 'init', [ Router::class, 'register_rewrite_rules' ] );
        add_filter( 'query_vars', [ Router::class, 'register_query_vars' ] );
        add_action( 'template_redirect', [ Router::class, 'handle_request' ], 0 );
        add_action( 'admin_init', [ self::class, 'redirect_store_managers_from_wp_admin' ] );
    }

    public static function dependencies_status(): array
    {
        return [
            'woocommerce' => class_exists( 'WooCommerce' ),
            'commerce_core' => defined( 'SCC_VERSION' ) || class_exists( '\Sultana\CommerceCore\Core\Bootstrap' ),
        ];
    }

    public static function dependencies_available(): bool
    {
        $status = self::dependencies_status();

        return ! in_array( false, $status, true );
    }

    public static function redirect_store_managers_from_wp_admin(): void
    {
        if ( ! is_user_logged_in() || ! current_user_can( Capabilities::ACCESS_CAPABILITY ) ) {
            return;
        }

        if ( current_user_can( 'manage_options' ) || wp_doing_ajax() ) {
            return;
        }

        global $pagenow;

        if ( in_array( (string) $pagenow, [ 'admin-ajax.php', 'async-upload.php', 'profile.php' ], true ) ) {
            return;
        }

        wp_safe_redirect( Router::dashboard_url() );
        exit;
    }
}

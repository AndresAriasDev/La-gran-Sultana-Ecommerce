<?php

namespace Sultana\CommerceCore\Modules\Accounts;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AccountAccess
{
    public static function register(): void
    {
        add_action( 'template_redirect', [ self::class, 'redirect_guest_from_account' ], 5 );
        add_filter( 'woocommerce_logout_default_redirect_url', [ self::class, 'logout_redirect_url' ] );
        add_filter( 'logout_redirect', [ self::class, 'logout_redirect_url' ] );
    }

    public static function redirect_guest_from_account(): void
    {
        if ( is_admin() || wp_doing_ajax() || is_user_logged_in() ) {
            return;
        }

        if ( ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
            return;
        }

        wp_safe_redirect( home_url( '/' ) );
        exit;
    }

    public static function logout_redirect_url(): string
    {
        return add_query_arg( 'scc_notice', 'logged_out', home_url( '/' ) );
    }
}

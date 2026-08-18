<?php

namespace Sultana\Admin\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Auth
{
    public const LOGIN_NONCE_ACTION = 'sultana_admin_login';
    public const LOGOUT_NONCE_ACTION = 'sultana_admin_logout';

    public static function handle_login(): string
    {
        if ( 'POST' !== strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) {
            return '';
        }

        $nonce = sanitize_text_field( wp_unslash( $_POST['sultana_admin_login_nonce'] ?? '' ) );

        if ( ! wp_verify_nonce( $nonce, self::LOGIN_NONCE_ACTION ) ) {
            return __( 'Los datos ingresados no son correctos.', 'sultana-admin' );
        }

        $login    = sanitize_text_field( wp_unslash( $_POST['log'] ?? '' ) );
        $password = (string) wp_unslash( $_POST['pwd'] ?? '' );
        $remember = ! empty( $_POST['rememberme'] );

        if ( '' === $login || '' === $password ) {
            return __( 'Los datos ingresados no son correctos.', 'sultana-admin' );
        }

        $user = wp_signon(
            [
                'user_login'    => $login,
                'user_password' => $password,
                'remember'      => $remember,
            ],
            is_ssl()
        );

        if ( is_wp_error( $user ) ) {
            return __( 'Los datos ingresados no son correctos.', 'sultana-admin' );
        }

        if ( ! user_can( $user, Capabilities::ACCESS_CAPABILITY ) ) {
            wp_logout();
            wp_clear_auth_cookie();
            wp_set_current_user( 0 );

            return __( 'No tienes acceso al panel de gestion.', 'sultana-admin' );
        }

        wp_safe_redirect( Router::dashboard_url() );
        exit;
    }

    public static function handle_logout(): void
    {
        if ( 'POST' !== strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) {
            wp_safe_redirect( Router::dashboard_url() );
            exit;
        }

        $nonce = sanitize_text_field( wp_unslash( $_POST['sultana_admin_logout_nonce'] ?? '' ) );

        if ( ! wp_verify_nonce( $nonce, self::LOGOUT_NONCE_ACTION ) ) {
            status_header( 403 );
            nocache_headers();
            exit;
        }

        wp_logout();
        wp_safe_redirect( Router::login_url() );
        exit;
    }
}

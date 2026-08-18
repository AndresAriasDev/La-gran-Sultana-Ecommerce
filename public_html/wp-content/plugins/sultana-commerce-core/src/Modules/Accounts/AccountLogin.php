<?php

namespace Sultana\CommerceCore\Modules\Accounts;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AccountLogin
{
    public static function register(): void
    {
        add_action( 'wp_ajax_nopriv_scc_login_account', [ self::class, 'login_user' ] );
    }

    public static function login_user(): void
    {
        $nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );

        if ( ! wp_verify_nonce( $nonce, 'scc_account_login' ) ) {
            self::send_error( __( 'No pudimos validar la solicitud. Actualizá la página e intentá de nuevo.', 'sultana-commerce-core' ), 403 );
        }

        if ( is_user_logged_in() ) {
            self::send_error( __( 'Ya tenés una sesión activa.', 'sultana-commerce-core' ), 409 );
        }

        $email    = strtolower( sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ) );
        $password = (string) wp_unslash( $_POST['password'] ?? '' );

        if ( '' === $email || '' === $password ) {
            self::send_error( __( 'Ingresá tu correo y contraseña para iniciar sesión.', 'sultana-commerce-core' ) );
        }

        if ( ! is_email( $email ) ) {
            self::send_error( __( 'Ingresá un correo válido.', 'sultana-commerce-core' ) );
        }

        $user = get_user_by( 'email', $email );

        if ( ! $user ) {
            self::send_error( __( 'No encontramos una cuenta con ese correo.', 'sultana-commerce-core' ), 404 );
        }

        self::load_guest_cart_session();

        $auth = wp_signon(
            [
                'user_login'    => $user->user_login,
                'user_password' => $password,
                'remember'      => true,
            ],
            is_ssl()
        );

        if ( is_wp_error( $auth ) ) {
            self::send_error( __( 'La contraseña no coincide con ese correo.', 'sultana-commerce-core' ), 401 );
        }

        self::sync_woocommerce_customer_session();

        wp_send_json_success(
            [
                'message'  => __( 'Sesión iniciada correctamente.', 'sultana-commerce-core' ),
                'redirect' => esc_url_raw( wp_get_referer() ?: home_url( '/' ) ),
            ]
        );
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

    private static function load_guest_cart_session(): void
    {
        if ( ! function_exists( 'WC' ) ) {
            return;
        }

        if ( function_exists( 'wc_load_cart' ) && ( ! WC()->cart || ! WC()->session ) ) {
            wc_load_cart();
        }
    }

    private static function sync_woocommerce_customer_session(): void
    {
        if ( ! function_exists( 'WC' ) || ! WC()->session ) {
            return;
        }

        if ( is_callable( [ WC()->session, 'init_session_cookie' ] ) ) {
            WC()->session->init_session_cookie();
        }
    }
}

<?php

namespace Sultana\CommerceCore\Modules\Accounts;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AccountRegistration
{
    public static function register(): void
    {
        add_action( 'wp_ajax_nopriv_scc_register_account', [ self::class, 'register_user' ] );
    }

    public static function register_user(): void
    {
        $nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );

        if ( ! wp_verify_nonce( $nonce, 'scc_account_register' ) ) {
            self::send_error( __( 'No pudimos validar la solicitud. Actualizá la página e intentá de nuevo.', 'sultana-commerce-core' ), 403 );
        }

        if ( is_user_logged_in() ) {
            self::send_error( __( 'Ya tenés una sesión activa.', 'sultana-commerce-core' ), 409 );
        }

        $name     = trim( sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ) );
        $email    = strtolower( sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ) );
        $password = (string) wp_unslash( $_POST['password'] ?? '' );

        if ( '' === $name || '' === $email || '' === $password ) {
            self::send_error( __( 'Completá todos los campos para crear tu cuenta.', 'sultana-commerce-core' ) );
        }

        if ( strlen( $name ) < 2 ) {
            self::send_error( __( 'Ingresá tu nombre completo para crear la cuenta.', 'sultana-commerce-core' ) );
        }

        if ( ! is_email( $email ) ) {
            self::send_error( __( 'Ingresá un correo válido.', 'sultana-commerce-core' ) );
        }

        if ( email_exists( $email ) ) {
            self::send_error( __( 'Este correo ya está registrado. Inicia sesión para continuar.', 'sultana-commerce-core' ), 409 );
        }

        if ( strlen( $password ) < 8 ) {
            self::send_error( __( 'La contraseña debe tener al menos 8 caracteres.', 'sultana-commerce-core' ) );
        }

        $username = self::unique_username_from_email( $email );
        $user_id  = function_exists( 'wc_create_new_customer' )
            ? wc_create_new_customer( $email, $username, $password )
            : wp_create_user( $username, $password, $email );

        if ( is_wp_error( $user_id ) ) {
            self::send_wp_error( $user_id );
        }

        $name_parts = self::parse_full_name( $name );

        $update_result = wp_update_user(
            [
                'ID'           => $user_id,
                'display_name' => $name_parts['display_name'],
                'first_name'   => $name_parts['first_name'],
                'last_name'    => $name_parts['last_name'],
            ]
        );

        if ( is_wp_error( $update_result ) ) {
            self::send_error( __( 'La cuenta fue creada, pero no pudimos guardar tu nombre. Iniciá sesión para continuar.', 'sultana-commerce-core' ), 500 );
        }

        self::prime_customer_address_names( $user_id, $name_parts, $email );

        self::load_guest_cart_session();

        $auth = wp_signon(
            [
                'user_login'    => $username,
                'user_password' => $password,
                'remember'      => true,
            ],
            is_ssl()
        );

        if ( is_wp_error( $auth ) ) {
            self::send_error( __( 'La cuenta fue creada, pero no pudimos iniciar sesión automáticamente. Iniciá sesión para continuar.', 'sultana-commerce-core' ), 500 );
        }

        self::sync_woocommerce_customer_session();

        wp_send_json_success(
            [
                'message'  => __( 'Cuenta creada correctamente.', 'sultana-commerce-core' ),
                'redirect' => esc_url_raw( wp_get_referer() ?: home_url( '/' ) ),
            ]
        );
    }

    private static function send_wp_error( \WP_Error $error ): void
    {
        $error_codes = $error->get_error_codes();

        if ( array_intersect( $error_codes, [ 'registration-error-email-exists', 'email_exists', 'existing_user_email' ] ) ) {
            self::send_error( __( 'Este correo ya está registrado. Inicia sesión para continuar.', 'sultana-commerce-core' ), 409 );
        }

        self::send_error(
            $error->get_error_message() ?: __( 'No pudimos crear la cuenta. Revisá los datos e intentá de nuevo.', 'sultana-commerce-core' )
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

    private static function unique_username_from_email( string $email ): string
    {
        $base     = sanitize_user( current( explode( '@', $email ) ), true );
        $base     = $base ?: 'cliente';
        $username = $base;
        $suffix   = 1;

        while ( username_exists( $username ) ) {
            $username = $base . $suffix;
            $suffix++;
        }

        return $username;
    }

    private static function parse_full_name( string $name ): array
    {
        $parts = preg_split( '/\s+/', trim( $name ) );
        $parts = array_values( array_filter( $parts ) );

        if ( empty( $parts ) ) {
            return [
                'first_name'   => '',
                'last_name'    => '',
                'display_name' => '',
            ];
        }

        $total_parts = count( $parts );

        if ( 1 === $total_parts ) {
            return [
                'first_name'   => $parts[0],
                'last_name'    => '',
                'display_name' => $parts[0],
            ];
        }

        if ( 2 === $total_parts ) {
            $first_parts = [ $parts[0] ];
            $last_parts  = [ $parts[1] ];
        } else {
            $first_parts = array_slice( $parts, 0, 2 );
            $last_parts  = array_slice( $parts, 2 );
        }

        $first_name   = implode( ' ', $first_parts );
        $last_name    = implode( ' ', $last_parts );
        $display_name = trim( $first_parts[0] . ' ' . ( $last_parts[0] ?? '' ) );

        return [
            'first_name'   => $first_name,
            'last_name'    => $last_name,
            'display_name' => $display_name,
        ];
    }

    private static function prime_customer_address_names( int $user_id, array $name_parts, string $email ): void
    {
        foreach ( [ 'billing', 'shipping' ] as $address_type ) {
            update_user_meta( $user_id, $address_type . '_first_name', $name_parts['first_name'] );
            update_user_meta( $user_id, $address_type . '_last_name', $name_parts['last_name'] );
        }

        update_user_meta( $user_id, 'billing_email', $email );
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

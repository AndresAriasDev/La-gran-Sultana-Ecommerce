<?php

namespace Sultana\CommerceCore\Modules\Checkout;

use Sultana\CommerceCore\Modules\Accounts\AccountPasswordReset;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CheckoutAccounts
{
    private const EMAIL_STATUS_NONCE_ACTION = 'scc_checkout_email_status';
    private const EMAIL_STATUS_RATE_SECONDS = 5;
    private const EXISTING_EMAIL_ERROR_CODE = 'scc_checkout_email_requires_login';
    private const CUSTOMER_CREATION_ERROR_CODE = 'scc_checkout_customer_creation_failed';
    private const ACCOUNT_CREATED_EMAIL_SENT_META = '_scc_account_created_email_sent';

    private static int $checkout_customer_id = 0;
    private static bool $creating_checkout_customer = false;

    public static function register(): void
    {
        add_action( 'woocommerce_after_checkout_validation', [ self::class, 'validate_guest_email' ], 20, 2 );
        add_action( 'woocommerce_after_checkout_validation', [ self::class, 'create_guest_customer' ], 1000, 2 );
        add_filter( 'woocommerce_checkout_customer_id', [ self::class, 'checkout_customer_id' ] );
        add_filter( 'woocommerce_email_enabled_customer_new_account', [ self::class, 'disable_native_new_account_email' ], 10, 3 );
        add_action( 'woocommerce_checkout_order_created', [ self::class, 'send_account_created_email' ] );
        add_action( 'wp_ajax_nopriv_scc_checkout_email_status', [ self::class, 'ajax_email_status' ] );
        add_action( 'wp_ajax_scc_checkout_email_status', [ self::class, 'ajax_email_status' ] );
    }

    public static function validate_guest_email( array $data, \WP_Error $errors ): void
    {
        if ( is_user_logged_in() ) {
            return;
        }

        $email = self::normalize_email( $data['billing_email'] ?? '' );

        if ( '' === $email || ! is_email( $email ) ) {
            return;
        }

        if ( email_exists( $email ) ) {
            $errors->add(
                self::EXISTING_EMAIL_ERROR_CODE,
                __( 'Este correo ya tiene una cuenta. Inicia sesión para continuar con este correo.', 'sultana-commerce-core' )
            );
        }
    }

    public static function create_guest_customer( array $data, \WP_Error $errors ): void
    {
        self::$checkout_customer_id = 0;

        if ( is_user_logged_in() || self::checkout_has_errors( $errors ) ) {
            return;
        }

        $email = self::normalize_email( $data['billing_email'] ?? '' );

        if ( '' === $email || ! is_email( $email ) ) {
            return;
        }

        if ( email_exists( $email ) ) {
            $errors->add(
                self::EXISTING_EMAIL_ERROR_CODE,
                __( 'Este correo ya tiene una cuenta. Inicia sesión para continuar con este correo.', 'sultana-commerce-core' )
            );

            return;
        }

        if ( ! function_exists( 'wc_create_new_customer' ) ) {
            $errors->add(
                self::CUSTOMER_CREATION_ERROR_CODE,
                __( 'No pudimos preparar tu cuenta para completar la compra. Inténtalo nuevamente.', 'sultana-commerce-core' )
            );

            return;
        }

        $password = wp_generate_password( 32, true, true );

        self::$creating_checkout_customer = true;

        try {
            $user_id = wc_create_new_customer( $email, '', $password );
        } finally {
            self::$creating_checkout_customer = false;
        }

        if ( is_wp_error( $user_id ) ) {
            self::handle_customer_creation_error( $user_id, $errors );

            return;
        }

        self::$checkout_customer_id = absint( $user_id );
        self::sync_customer_checkout_data( self::$checkout_customer_id, $data );
        self::authenticate_checkout_customer( self::$checkout_customer_id );
    }

    public static function checkout_customer_id( int $customer_id ): int
    {
        if ( self::$checkout_customer_id <= 0 ) {
            return $customer_id;
        }

        return self::$checkout_customer_id;
    }

    public static function disable_native_new_account_email( bool $enabled, $user = null, $email = null ): bool
    {
        if ( self::$creating_checkout_customer ) {
            return false;
        }

        return $enabled;
    }

    public static function send_account_created_email( $order ): void
    {
        if ( self::$checkout_customer_id <= 0 || ! $order instanceof \WC_Order ) {
            return;
        }

        if ( absint( $order->get_customer_id() ) !== self::$checkout_customer_id ) {
            return;
        }

        if ( 'yes' === $order->get_meta( self::ACCOUNT_CREATED_EMAIL_SENT_META, true ) ) {
            return;
        }

        $user = get_user_by( 'id', self::$checkout_customer_id );

        if ( ! $user instanceof \WP_User ) {
            return;
        }

        $sent = AccountPasswordReset::send_account_created_password_email( $user );

        if ( ! $sent ) {
            error_log(
                sprintf(
                    'SCC checkout account-created password email failed for order #%d and user #%d.',
                    absint( $order->get_id() ),
                    self::$checkout_customer_id
                )
            );

            return;
        }

        $order->update_meta_data( self::ACCOUNT_CREATED_EMAIL_SENT_META, 'yes' );
        $order->save();
    }

    public static function ajax_email_status(): void
    {
        $nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );

        if ( ! wp_verify_nonce( $nonce, self::EMAIL_STATUS_NONCE_ACTION ) ) {
            wp_send_json_error( [ 'message' => __( 'No pudimos validar la solicitud.', 'sultana-commerce-core' ) ], 403 );
        }

        $email = self::normalize_email( $_POST['email'] ?? '' );

        if ( '' === $email || ! is_email( $email ) || is_user_logged_in() ) {
            wp_send_json_success( [ 'requires_login' => false ] );
        }

        $cached_status = self::cached_email_status( $email );

        if ( null !== $cached_status ) {
            wp_send_json_success( [ 'requires_login' => $cached_status ] );
        }

        $requires_login = (bool) email_exists( $email );
        self::cache_email_status( $email, $requires_login );

        wp_send_json_success(
            [
                'requires_login' => $requires_login,
            ]
        );
    }

    public static function email_status_nonce_action(): string
    {
        return self::EMAIL_STATUS_NONCE_ACTION;
    }

    private static function checkout_has_errors( \WP_Error $errors ): bool
    {
        return method_exists( $errors, 'has_errors' ) ? $errors->has_errors() : ! empty( $errors->errors );
    }

    private static function handle_customer_creation_error( \WP_Error $error, \WP_Error $checkout_errors ): void
    {
        $error_codes = $error->get_error_codes();

        if ( array_intersect( $error_codes, [ 'registration-error-email-exists', 'email_exists', 'existing_user_email' ] ) ) {
            $checkout_errors->add(
                self::EXISTING_EMAIL_ERROR_CODE,
                __( 'Este correo ya tiene una cuenta. Inicia sesión para continuar con este correo.', 'sultana-commerce-core' )
            );

            return;
        }

        error_log(
            sprintf(
                'SCC checkout customer creation failed: %s',
                implode( ',', array_map( 'sanitize_key', $error_codes ) )
            )
        );

        $checkout_errors->add(
            self::CUSTOMER_CREATION_ERROR_CODE,
            __( 'No pudimos preparar tu cuenta para completar la compra. Inténtalo nuevamente.', 'sultana-commerce-core' )
        );
    }

    private static function authenticate_checkout_customer( int $user_id ): void
    {
        if ( $user_id <= 0 || is_user_logged_in() ) {
            return;
        }

        $user = get_user_by( 'id', $user_id );

        if ( ! $user instanceof \WP_User ) {
            return;
        }

        wp_set_current_user( $user_id );
        wp_set_auth_cookie( $user_id, false, is_ssl() );

        do_action( 'wp_login', $user->user_login, $user );
    }

    private static function sync_customer_checkout_data( int $user_id, array $data ): void
    {
        if ( $user_id <= 0 || ! class_exists( '\WC_Customer' ) ) {
            return;
        }

        $customer = new \WC_Customer( $user_id );

        foreach ( self::customer_address_fields() as $field ) {
            $billing_key = 'billing_' . $field;
            $shipping_key = 'shipping_' . $field;

            self::set_customer_field( $customer, $billing_key, $data[ $billing_key ] ?? '' );
            self::set_customer_field( $customer, $shipping_key, $data[ $shipping_key ] ?? ( $data[ $billing_key ] ?? '' ) );
        }

        self::set_customer_field( $customer, 'billing_phone', $data['billing_phone'] ?? '' );
        self::set_customer_field( $customer, 'billing_email', $data['billing_email'] ?? '' );

        $customer->save();
    }

    private static function customer_address_fields(): array
    {
        return [
            'first_name',
            'last_name',
            'company',
            'address_1',
            'city',
            'state',
            'postcode',
            'country',
        ];
    }

    private static function set_customer_field( \WC_Customer $customer, string $key, $value ): void
    {
        if ( is_array( $value ) || is_object( $value ) ) {
            $value = '';
        }

        $setter = 'set_' . $key;
        $value  = 'billing_email' === $key ? self::normalize_email( $value ) : sanitize_text_field( wp_unslash( (string) $value ) );

        if ( is_callable( [ $customer, $setter ] ) ) {
            $customer->{$setter}( $value );
        }
    }

    private static function normalize_email( $email ): string
    {
        if ( is_array( $email ) || is_object( $email ) ) {
            return '';
        }

        return strtolower( sanitize_email( wp_unslash( (string) $email ) ) );
    }

    private static function cached_email_status( string $email ): ?bool
    {
        $cached = get_transient( self::email_status_cache_key( $email ) );

        if ( ! is_array( $cached ) || ! array_key_exists( 'requires_login', $cached ) ) {
            return null;
        }

        return (bool) $cached['requires_login'];
    }

    private static function cache_email_status( string $email, bool $requires_login ): void
    {
        set_transient(
            self::email_status_cache_key( $email ),
            [ 'requires_login' => $requires_login ],
            self::EMAIL_STATUS_RATE_SECONDS
        );
    }

    private static function email_status_cache_key( string $email ): string
    {
        return 'scc_checkout_email_status_' . hash( 'sha256', $email . '|' . self::request_ip_address() );
    }

    private static function request_ip_address(): string
    {
        if ( ! isset( $_SERVER['REMOTE_ADDR'] ) || ! is_scalar( $_SERVER['REMOTE_ADDR'] ) ) {
            return '';
        }

        return sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) );
    }
}

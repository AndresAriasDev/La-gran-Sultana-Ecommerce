<?php

namespace Sultana\Admin\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Auth
{
    public const LOGIN_NONCE_ACTION = 'sultana_admin_login';
    public const LOGOUT_NONCE_ACTION = 'sultana_admin_logout';
    public const PASSWORD_RESET_REQUEST_NONCE_ACTION = 'sultana_admin_password_reset_request';
    public const PASSWORD_RESET_COMPLETE_NONCE_ACTION = 'sultana_admin_password_reset_complete';
    private const PASSWORD_RESET_COOLDOWN_SECONDS = 20;

    private static bool $customizing_admin_reset_email = false;

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

    public static function handle_password_reset_request(): array
    {
        $state = [
            'submitted' => false,
            'sent'      => false,
            'error'     => '',
            'login'     => '',
            'cooldown'  => self::PASSWORD_RESET_COOLDOWN_SECONDS,
        ];

        if ( 'POST' !== strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) {
            return $state;
        }

        $state['submitted'] = true;
        $nonce              = sanitize_text_field( wp_unslash( $_POST['sultana_admin_password_reset_nonce'] ?? '' ) );
        $user_login         = trim( sanitize_text_field( wp_unslash( $_POST['user_login'] ?? '' ) ) );
        $state['login']     = $user_login;

        if ( ! wp_verify_nonce( $nonce, self::PASSWORD_RESET_REQUEST_NONCE_ACTION ) ) {
            $state['error'] = __( 'No pudimos validar la solicitud. Actualiza la pagina e intenta de nuevo.', 'sultana-admin' );
            return $state;
        }

        if ( '' === $user_login ) {
            $state['error'] = __( 'Ingresa tu usuario o correo para continuar.', 'sultana-admin' );
            return $state;
        }

        if ( get_transient( self::password_reset_cooldown_key( $user_login ) ) ) {
            $state['sent'] = true;
            return $state;
        }

        set_transient( self::password_reset_cooldown_key( $user_login ), '1', self::PASSWORD_RESET_COOLDOWN_SECONDS );

        $user = self::find_password_reset_user( $user_login );

        if ( ! $user instanceof \WP_User || ! user_can( $user, Capabilities::ACCESS_CAPABILITY ) ) {
            $state['sent'] = true;
            return $state;
        }

        self::$customizing_admin_reset_email = true;
        add_filter( 'retrieve_password_notification_email', [ self::class, 'customize_admin_reset_email' ], 20, 4 );

        $result = retrieve_password( $user->user_login );

        remove_filter( 'retrieve_password_notification_email', [ self::class, 'customize_admin_reset_email' ], 20 );
        self::$customizing_admin_reset_email = false;

        if ( is_wp_error( $result ) && ! self::is_expected_password_reset_lookup_error( $result ) ) {
            error_log(
                sprintf(
                    'Sultana Admin password reset request failed: %s',
                    implode( ',', array_map( 'sanitize_key', $result->get_error_codes() ) )
                )
            );

            $state['error'] = __( 'No pudimos procesar la solicitud en este momento. Intenta nuevamente mas tarde.', 'sultana-admin' );
            return $state;
        }

        $state['sent'] = true;
        return $state;
    }

    public static function reset_password_context(): array
    {
        $key   = self::sanitize_reset_key( wp_unslash( $_GET['key'] ?? '' ) );
        $login = self::sanitize_reset_login( wp_unslash( $_GET['login'] ?? '' ) );

        if ( '' === $key || '' === $login ) {
            return self::invalid_password_reset_context( 'missing' );
        }

        $user = check_password_reset_key( $key, $login );

        if ( is_wp_error( $user ) || ! $user instanceof \WP_User || ! user_can( $user, Capabilities::ACCESS_CAPABILITY ) ) {
            return self::invalid_password_reset_context( is_wp_error( $user ) ? $user->get_error_code() : 'invalid_user' );
        }

        return [
            'valid'   => true,
            'success' => false,
            'error'   => '',
            'key'     => $key,
            'login'   => $login,
            'user'    => $user,
        ];
    }

    public static function handle_password_reset_completion(): array
    {
        $context = self::reset_password_context();

        if ( empty( $context['valid'] ) || 'POST' !== strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) {
            return $context;
        }

        $nonce            = sanitize_text_field( wp_unslash( $_POST['sultana_admin_password_reset_complete_nonce'] ?? '' ) );
        $password         = (string) wp_unslash( $_POST['password'] ?? '' );
        $password_confirm = (string) wp_unslash( $_POST['password_confirm'] ?? '' );

        if ( ! wp_verify_nonce( $nonce, self::PASSWORD_RESET_COMPLETE_NONCE_ACTION ) ) {
            $context['error'] = __( 'No pudimos validar la solicitud. Actualiza la pagina e intenta de nuevo.', 'sultana-admin' );
            return $context;
        }

        if ( '' === $password || '' === $password_confirm ) {
            $context['error'] = __( 'Ingresa y confirma tu nueva contrasena.', 'sultana-admin' );
            return $context;
        }

        if ( $password !== $password_confirm ) {
            $context['error'] = __( 'Las contrasenas no coinciden.', 'sultana-admin' );
            return $context;
        }

        $user = check_password_reset_key( (string) $context['key'], (string) $context['login'] );

        if ( is_wp_error( $user ) || ! $user instanceof \WP_User || ! user_can( $user, Capabilities::ACCESS_CAPABILITY ) ) {
            return self::invalid_password_reset_context( is_wp_error( $user ) ? $user->get_error_code() : 'invalid_user' );
        }

        reset_password( $user, $password );

        return [
            'valid'   => false,
            'success' => true,
            'error'   => '',
            'key'     => '',
            'login'   => '',
            'user'    => null,
        ];
    }

    public static function customize_admin_reset_email( array $defaults, string $key, string $user_login, \WP_User $user_data ): array
    {
        if ( ! self::$customizing_admin_reset_email || ! user_can( $user_data, Capabilities::ACCESS_CAPABILITY ) ) {
            return $defaults;
        }

        $site_name = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
        $reset_url = Router::password_reset_url( $key, $user_login );

        $defaults['subject'] = sprintf(
            /* translators: %s: site name. */
            __( 'Restablece tu acceso - %s', 'sultana-admin' ),
            $site_name
        );
        $defaults['message'] = sprintf(
            /* translators: 1: site name, 2: reset URL. */
            __( "Recibimos una solicitud para restablecer tu acceso a Sultana Admin en %1\$s.\n\nPara crear una nueva contrasena, abre este enlace:\n\n%2\$s\n\nSi no solicitaste este cambio, puedes ignorar este correo.", 'sultana-admin' ),
            $site_name,
            $reset_url
        );
        $defaults['headers'] = [ 'Content-Type: text/plain; charset=UTF-8' ];

        return $defaults;
    }

    public static function neutral_password_reset_message(): string
    {
        return __( 'Si los datos corresponden a una cuenta valida, recibiras un enlace para restablecer la contrasena.', 'sultana-admin' );
    }

    public static function password_reset_cooldown_seconds(): int
    {
        return self::PASSWORD_RESET_COOLDOWN_SECONDS;
    }

    private static function find_password_reset_user( string $user_login ): ?\WP_User
    {
        $user = is_email( $user_login ) ? get_user_by( 'email', $user_login ) : get_user_by( 'login', $user_login );

        return $user instanceof \WP_User ? $user : null;
    }

    private static function password_reset_cooldown_key( string $identifier ): string
    {
        $normalized_identifier = strtolower( trim( $identifier ) );
        $hash                  = hash( 'sha256', $normalized_identifier . '|' . self::request_ip_address() );

        return 'sultana_admin_password_reset_cd_' . $hash;
    }

    private static function request_ip_address(): string
    {
        if ( ! isset( $_SERVER['REMOTE_ADDR'] ) || ! is_scalar( $_SERVER['REMOTE_ADDR'] ) ) {
            return '';
        }

        return sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) );
    }

    private static function is_expected_password_reset_lookup_error( \WP_Error $error ): bool
    {
        $expected_codes = [
            'empty_username',
            'invalid_email',
            'invalidcombo',
            'invalid_username',
        ];

        return (bool) array_intersect( $expected_codes, $error->get_error_codes() );
    }

    private static function invalid_password_reset_context( string $error_code ): array
    {
        return [
            'valid'   => false,
            'success' => false,
            'error'   => $error_code,
            'key'     => '',
            'login'   => '',
            'user'    => null,
        ];
    }

    private static function sanitize_reset_key( $key ): string
    {
        return (string) preg_replace( '/[^a-z0-9]/i', '', (string) $key );
    }

    private static function sanitize_reset_login( $login ): string
    {
        return trim( sanitize_text_field( (string) $login ) );
    }
}

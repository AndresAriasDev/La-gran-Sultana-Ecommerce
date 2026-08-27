<?php

namespace Sultana\CommerceCore\Modules\Accounts;

use Sultana\CommerceCore\Core\CheckoutPerformanceLogger;
use Sultana\CommerceCore\Core\StoreBranding;
use Sultana\CommerceCore\Core\TemplateLoader;
use Sultana\CommerceCore\Modules\Emails\EmailRenderer;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AccountPasswordReset
{
    private const REQUEST_NONCE_ACTION = 'scc_password_reset_request';
    private const COMPLETE_NONCE_ACTION = 'scc_password_reset_complete';
    private const FALLBACK_NONCE_ACTION = 'scc_password_reset_fallback';
    private const QUERY_VAR = 'scc_password_reset';
    private const ROUTE = 'restablecer-contrasena';
    private const REQUEST_COOLDOWN_SECONDS = 30;

    public static function register(): void
    {
        add_action( 'init', [ self::class, 'register_route' ] );
        add_action( 'init', [ self::class, 'maybe_flush_rewrite_rules' ], 20 );
        add_filter( 'query_vars', [ self::class, 'add_query_vars' ] );
        add_filter( 'template_include', [ self::class, 'load_reset_template' ] );
        add_filter( 'retrieve_password_notification_email', [ self::class, 'customize_reset_email' ], 10, 4 );
        add_action( 'wp_ajax_scc_request_password_reset', [ self::class, 'request_password_reset' ] );
        add_action( 'wp_ajax_nopriv_scc_request_password_reset', [ self::class, 'request_password_reset' ] );
        add_action( 'wp_ajax_scc_reset_password', [ self::class, 'complete_password_reset' ] );
        add_action( 'wp_ajax_nopriv_scc_reset_password', [ self::class, 'complete_password_reset' ] );
    }

    public static function register_route(): void
    {
        add_rewrite_rule( '^' . self::ROUTE . '/?$', 'index.php?' . self::QUERY_VAR . '=1', 'top' );
    }

    public static function maybe_flush_rewrite_rules(): void
    {
        $option_key = 'scc_password_reset_route_version';
        $version    = ( defined( 'SCC_VERSION' ) ? SCC_VERSION : '1' ) . '-password-reset-v1';

        if ( get_option( $option_key ) === $version ) {
            return;
        }

        flush_rewrite_rules();
        update_option( $option_key, $version, false );
    }

    public static function add_query_vars( array $vars ): array
    {
        $vars[] = self::QUERY_VAR;

        return $vars;
    }

    public static function load_reset_template( string $template ): string
    {
        if ( ! self::is_reset_page() ) {
            return $template;
        }

        status_header( 200 );

        $core_template   = TemplateLoader::locate( 'account/password-reset.php' );
        $legacy_template = locate_template( 'templates/account/password-reset.php' );

        if ( self::is_core_template_path( $core_template ) && $legacy_template ) {
            return $legacy_template;
        }

        return $core_template ?: ( $legacy_template ?: $template );
    }

    public static function request_password_reset(): void
    {
        $nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );

        if ( ! wp_verify_nonce( $nonce, self::REQUEST_NONCE_ACTION ) ) {
            self::send_error( __( 'No pudimos validar la solicitud. Actualizá la página e intentá de nuevo.', 'sultana-commerce-core' ), 403 );
        }

        $user_login = trim( sanitize_text_field( wp_unslash( $_POST['email'] ?? '' ) ) );

        if ( '' === $user_login ) {
            self::send_error( __( 'Ingresá el correo de tu cuenta para continuar.', 'sultana-commerce-core' ) );
        }

        $cooldown_key = self::request_cooldown_key( $user_login );

        if ( get_transient( $cooldown_key ) ) {
            wp_send_json_success(
                [
                    'message' => self::neutral_message(),
                ]
            );
        }

        set_transient( $cooldown_key, '1', self::REQUEST_COOLDOWN_SECONDS );

        $result = retrieve_password( $user_login );

        if ( is_wp_error( $result ) && ! self::is_expected_lookup_error( $result ) ) {
            error_log(
                sprintf(
                    'SCC password reset request failed: %s',
                    implode( ',', array_map( 'sanitize_key', $result->get_error_codes() ) )
                )
            );

            self::send_error( __( 'No pudimos procesar la solicitud en este momento. Intentá nuevamente más tarde.', 'sultana-commerce-core' ), 500 );
        }

        wp_send_json_success(
            [
                'message' => self::neutral_message(),
            ]
        );
    }

    public static function complete_password_reset(): void
    {
        $nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );

        if ( ! wp_verify_nonce( $nonce, self::COMPLETE_NONCE_ACTION ) ) {
            self::send_error( __( 'No pudimos validar la solicitud. Actualizá la página e intentá de nuevo.', 'sultana-commerce-core' ), 403 );
        }

        $result = self::process_password_reset_submission(
            wp_unslash( $_POST['key'] ?? '' ),
            wp_unslash( $_POST['login'] ?? '' ),
            wp_unslash( $_POST['password'] ?? '' ),
            wp_unslash( $_POST['password_confirm'] ?? '' )
        );

        if ( is_wp_error( $result ) ) {
            self::send_error( $result->get_error_message() );
        }

        wp_send_json_success(
            [
                'message' => __( 'Tu contraseña se cambió correctamente. Ya puedes iniciar sesión con tu nueva contraseña.', 'sultana-commerce-core' ),
            ]
        );
    }

    public static function customize_reset_email( array $defaults, string $key, string $user_login, \WP_User $user_data ): array
    {
        $reset_url = self::frontend_reset_url( $key, $user_login );

        $defaults['subject'] = __( 'Restablece tu contraseña', 'sultana-commerce-core' );
        $defaults['message'] = self::reset_email_body( $reset_url );
        $defaults['headers'] = self::html_email_headers( $defaults['headers'] ?? [] );

        return $defaults;
    }

    public static function send_account_created_password_email( \WP_User $user ): bool
    {
        if ( ! is_email( $user->user_email ) ) {
            return false;
        }

        $key = get_password_reset_key( $user );

        if ( is_wp_error( $key ) ) {
            error_log(
                sprintf(
                    'SCC account-created password key generation failed for user #%d: %s',
                    absint( $user->ID ),
                    implode( ',', array_map( 'sanitize_key', $key->get_error_codes() ) )
                )
            );

            return false;
        }

        $subject = sprintf(
            /* translators: %s: site name. */
            __( 'Tu cuenta fue creada - %s', 'sultana-commerce-core' ),
            StoreBranding::get_name()
        );

        $scc_perf_start = CheckoutPerformanceLogger::start();
        $sent = wp_mail(
            $user->user_email,
            $subject,
            self::account_created_email_body( self::frontend_reset_url( $key, $user->user_login ) ),
            self::html_email_headers( [] )
        );

        CheckoutPerformanceLogger::log_duration(
            'mail:account_created_password',
            $scc_perf_start,
            [
                'sent'    => $sent,
                'user_id' => absint( $user->ID ),
            ]
        );

        return $sent;
    }

    public static function frontend_reset_url( string $key, string $login ): string
    {
        return esc_url_raw(
            add_query_arg(
                [
                    'key'   => $key,
                    'login' => $login,
                ],
                home_url( '/' . self::ROUTE . '/' )
            )
        );
    }

    public static function reset_request_context(): array
    {
        $key   = self::sanitize_reset_key( wp_unslash( $_GET['key'] ?? '' ) );
        $login = self::sanitize_reset_login( wp_unslash( $_GET['login'] ?? '' ) );

        if ( '' === $key || '' === $login ) {
            return self::invalid_context( 'missing' );
        }

        $user = check_password_reset_key( $key, $login );

        if ( is_wp_error( $user ) ) {
            return self::invalid_context( $user->get_error_code() );
        }

        if ( ! $user instanceof \WP_User ) {
            return self::invalid_context( 'invalid_key' );
        }

        return [
            'valid' => true,
            'key'   => $key,
            'login' => $login,
            'user'  => $user,
            'error' => '',
        ];
    }

    public static function request_nonce_action(): string
    {
        return self::REQUEST_NONCE_ACTION;
    }

    public static function complete_nonce_action(): string
    {
        return self::COMPLETE_NONCE_ACTION;
    }

    public static function fallback_nonce_action(): string
    {
        return self::FALLBACK_NONCE_ACTION;
    }

    public static function fallback_submission_context(): array
    {
        if ( empty( $_POST['scc_password_reset_fallback'] ) ) {
            return [
                'submitted' => false,
                'success'   => false,
                'message'   => '',
            ];
        }

        $nonce = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) );

        if ( ! wp_verify_nonce( $nonce, self::FALLBACK_NONCE_ACTION ) ) {
            return [
                'submitted' => true,
                'success'   => false,
                'message'   => __( 'No pudimos validar la solicitud. Actualizá la página e intentá de nuevo.', 'sultana-commerce-core' ),
            ];
        }

        $result = self::process_password_reset_submission(
            wp_unslash( $_POST['key'] ?? '' ),
            wp_unslash( $_POST['login'] ?? '' ),
            wp_unslash( $_POST['password'] ?? '' ),
            wp_unslash( $_POST['password_confirm'] ?? '' )
        );

        if ( is_wp_error( $result ) ) {
            return [
                'submitted' => true,
                'success'   => false,
                'message'   => $result->get_error_message(),
            ];
        }

        return [
            'submitted' => true,
            'success'   => true,
            'message'   => __( 'Tu contraseña se cambió correctamente. Ya puedes iniciar sesión con tu nueva contraseña.', 'sultana-commerce-core' ),
        ];
    }

    public static function is_reset_page(): bool
    {
        return '1' === (string) get_query_var( self::QUERY_VAR );
    }

    private static function reset_email_body( string $reset_url ): string
    {
        return self::account_email_body(
            [
                'title'       => __( 'Restablece tu contraseña', 'sultana-commerce-core' ),
                'eyebrow'     => __( 'Cuenta', 'sultana-commerce-core' ),
                'description' => __( 'Recibimos una solicitud para restablecer la contraseña de tu cuenta.', 'sultana-commerce-core' ),
                'button_text' => __( 'Restablecer contraseña', 'sultana-commerce-core' ),
                'button_url'  => $reset_url,
                'note'        => __( 'Si no solicitaste este cambio, podés ignorar este correo. Tu contraseña seguirá siendo la misma.', 'sultana-commerce-core' ),
            ]
        );
    }

    private static function account_created_email_body( string $reset_url ): string
    {
        return self::account_email_body(
            [
                'title'       => __( 'Tu cuenta fue creada', 'sultana-commerce-core' ),
                'eyebrow'     => __( 'Cuenta', 'sultana-commerce-core' ),
                'description' => __( 'Creamos una cuenta con el correo utilizado en tu compra para que puedas consultar tus pedidos y administrar tus compras.', 'sultana-commerce-core' ),
                'button_text' => __( 'Crear mi contraseña', 'sultana-commerce-core' ),
                'button_url'  => $reset_url,
                'note'        => __( 'Por seguridad, no enviamos contraseñas por correo. Usa el botón para crear la tuya.', 'sultana-commerce-core' ),
            ]
        );
    }

    private static function account_email_body( array $args ): string
    {
        $title       = (string) ( $args['title'] ?? '' );
        $eyebrow     = (string) ( $args['eyebrow'] ?? '' );
        $description = (string) ( $args['description'] ?? '' );
        $button_text = (string) ( $args['button_text'] ?? '' );
        $button_url  = (string) ( $args['button_url'] ?? '' );
        $note        = (string) ( $args['note'] ?? '' );

        ob_start();
        ?>
        <p style="margin:0 0 22px;color:#62566a;font-size:15px;line-height:1.6;">
            <?php echo esc_html( $description ); ?>
        </p>
        <?php

        $content_html = (string) ob_get_clean();

        ob_start();
        ?>
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;border-collapse:collapse;background:#fff8fb;border:1px solid #f5deea;border-radius:5px;margin:0 0 22px;">
            <tr>
                <td style="padding:14px 16px;color:#62566a;font-size:13px;line-height:1.55;">
                    <?php echo esc_html( $note ); ?>
                </td>
            </tr>
        </table>
        <?php

        return EmailRenderer::render(
            [
                'title'             => $title,
                'eyebrow'           => $eyebrow,
                'content_html'      => $content_html,
                'after_button_html' => (string) ob_get_clean(),
                'button'            => [
                    'label' => $button_text,
                    'url'   => $button_url,
                    'align' => 'left',
                ],
            ]
        );
    }

    private static function html_email_headers( $headers ): array
    {
        return EmailRenderer::html_headers( $headers );
    }

    private static function process_password_reset_submission( $key, $login, $password, $password_confirm )
    {
        $key              = self::sanitize_reset_key( $key );
        $login            = self::sanitize_reset_login( $login );
        $password         = (string) $password;
        $password_confirm = (string) $password_confirm;

        if ( '' === $key || '' === $login ) {
            return new \WP_Error( 'scc_password_reset_invalid_link', __( 'El enlace para restablecer tu contraseña no es válido o ya expiró.', 'sultana-commerce-core' ) );
        }

        if ( '' === $password || '' === $password_confirm ) {
            return new \WP_Error( 'scc_password_reset_empty_password', __( 'Ingresá y confirmá tu nueva contraseña.', 'sultana-commerce-core' ) );
        }

        if ( $password !== $password_confirm ) {
            return new \WP_Error( 'scc_password_reset_password_mismatch', __( 'Las contraseñas no coinciden.', 'sultana-commerce-core' ) );
        }

        if ( strlen( $password ) < 8 ) {
            return new \WP_Error( 'scc_password_reset_password_too_short', __( 'La contraseña debe tener al menos 8 caracteres.', 'sultana-commerce-core' ) );
        }

        $user = check_password_reset_key( $key, $login );

        if ( is_wp_error( $user ) || ! $user instanceof \WP_User ) {
            return new \WP_Error( 'scc_password_reset_invalid_link', __( 'El enlace para restablecer tu contraseña no es válido o ya expiró.', 'sultana-commerce-core' ) );
        }

        reset_password( $user, $password );

        return true;
    }

    private static function is_core_template_path( string $template ): bool
    {
        if ( '' === $template || ! defined( 'SCC_PLUGIN_PATH' ) ) {
            return false;
        }

        $core_templates_path = wp_normalize_path( trailingslashit( SCC_PLUGIN_PATH ) . 'templates/' );
        $template            = wp_normalize_path( $template );

        return str_starts_with( $template, $core_templates_path );
    }

    private static function neutral_message(): string
    {
        return __( 'Si existe una cuenta asociada a ese correo, te enviamos un enlace para restablecer tu contraseña.', 'sultana-commerce-core' );
    }

    private static function is_expected_lookup_error( \WP_Error $error ): bool
    {
        $expected_codes = [
            'empty_username',
            'invalid_email',
            'invalidcombo',
            'invalid_username',
        ];

        return (bool) array_intersect( $expected_codes, $error->get_error_codes() );
    }

    private static function invalid_context( string $error_code ): array
    {
        return [
            'valid' => false,
            'key'   => '',
            'login' => '',
            'user'  => null,
            'error' => $error_code,
        ];
    }

    private static function sanitize_reset_key( $key ): string
    {
        return preg_replace( '/[^a-z0-9]/i', '', (string) $key );
    }

    private static function sanitize_reset_login( $login ): string
    {
        return trim( sanitize_text_field( (string) $login ) );
    }

    private static function request_cooldown_key( string $identifier ): string
    {
        $normalized_identifier = strtolower( trim( $identifier ) );
        $hash                  = hash( 'sha256', $normalized_identifier . '|' . self::request_ip_address() );

        return 'scc_password_reset_cd_' . $hash;
    }

    private static function request_ip_address(): string
    {
        if ( ! isset( $_SERVER['REMOTE_ADDR'] ) || ! is_scalar( $_SERVER['REMOTE_ADDR'] ) ) {
            return '';
        }

        return sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) );
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
}

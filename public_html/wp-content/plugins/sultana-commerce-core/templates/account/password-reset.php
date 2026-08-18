<?php
/**
 * Minimal password reset fallback template.
 *
 * Theme override path: sultana-commerce/account/password-reset.php
 *
 * @package SultanaCommerceCore
 */

defined( 'ABSPATH' ) || exit;

$reset_class = '\Sultana\CommerceCore\Modules\Accounts\AccountPasswordReset';
$submission  = class_exists( $reset_class ) && method_exists( $reset_class, 'fallback_submission_context' )
    ? $reset_class::fallback_submission_context()
    : [
        'submitted' => false,
        'success'   => false,
        'message'   => '',
    ];
$context     = class_exists( $reset_class ) && method_exists( $reset_class, 'reset_request_context' )
    ? $reset_class::reset_request_context()
    : [
        'valid' => false,
        'key'   => '',
        'login' => '',
        'user'  => null,
        'error' => 'unavailable',
    ];
$myaccount_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : '';
$myaccount_url = $myaccount_url ? $myaccount_url : home_url( '/' );
$lost_password_url = function_exists( 'wc_lostpassword_url' ) ? wc_lostpassword_url() : wp_lostpassword_url();

get_header();
?>

<main class="scc-password-reset-page" style="max-width:680px;margin:48px auto;padding:0 20px;">
    <style>
        .scc-password-reset-card{background:#fff;border:1px solid #e8e8ef;border-radius:8px;padding:28px;box-shadow:0 12px 32px rgba(15,23,42,.08);font-family:Arial,Helvetica,sans-serif}
        .scc-password-reset-card h1{margin:0 0 12px;font-size:28px;line-height:1.2;color:#101122}
        .scc-password-reset-card p{color:#5d6472;line-height:1.6}
        .scc-password-reset-card label{display:block;margin:0 0 6px;font-weight:700;color:#101122}
        .scc-password-reset-card input[type="password"]{box-sizing:border-box;width:100%;min-height:44px;border:1px solid #cfd4dd;border-radius:6px;padding:10px 12px;font-size:16px}
        .scc-password-reset-card button,.scc-password-reset-card a.scc-password-reset-button{display:inline-block;border:0;border-radius:6px;background:#2f3640;color:#fff;text-decoration:none;padding:12px 18px;font-weight:700;cursor:pointer}
        .scc-password-reset-field{margin:0 0 16px}
        .scc-password-reset-message{border-radius:6px;padding:12px 14px;margin:0 0 18px}
        .scc-password-reset-message--error{background:#fff3f3;color:#9f1239}
        .scc-password-reset-message--success{background:#effaf3;color:#166534}
    </style>

    <section class="scc-password-reset-card" aria-labelledby="scc-password-reset-title">
        <?php if ( ! empty( $submission['success'] ) ) : ?>
            <p class="scc-password-reset-message scc-password-reset-message--success">
                <?php echo esc_html( (string) $submission['message'] ); ?>
            </p>
            <h1 id="scc-password-reset-title"><?php esc_html_e( 'Contraseña actualizada', 'sultana-commerce-core' ); ?></h1>
            <p><?php esc_html_e( 'Ya puedes iniciar sesión con tu nueva contraseña.', 'sultana-commerce-core' ); ?></p>
            <a class="scc-password-reset-button" href="<?php echo esc_url( $myaccount_url ); ?>">
                <?php esc_html_e( 'Ir a Mi Cuenta', 'sultana-commerce-core' ); ?>
            </a>
        <?php elseif ( empty( $context['valid'] ) ) : ?>
            <h1 id="scc-password-reset-title"><?php esc_html_e( 'Este enlace ya no es válido', 'sultana-commerce-core' ); ?></h1>
            <p><?php esc_html_e( 'El enlace para restablecer tu contraseña expiró o ya fue utilizado.', 'sultana-commerce-core' ); ?></p>
            <a class="scc-password-reset-button" href="<?php echo esc_url( $lost_password_url ); ?>">
                <?php esc_html_e( 'Solicitar un nuevo enlace', 'sultana-commerce-core' ); ?>
            </a>
        <?php else : ?>
            <h1 id="scc-password-reset-title"><?php esc_html_e( 'Crear nueva contraseña', 'sultana-commerce-core' ); ?></h1>
            <p><?php esc_html_e( 'Ingresá y confirmá tu nueva contraseña para volver a acceder a tu cuenta.', 'sultana-commerce-core' ); ?></p>

            <?php if ( ! empty( $submission['submitted'] ) && empty( $submission['success'] ) && ! empty( $submission['message'] ) ) : ?>
                <p class="scc-password-reset-message scc-password-reset-message--error">
                    <?php echo esc_html( (string) $submission['message'] ); ?>
                </p>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url( $reset_class::frontend_reset_url( (string) $context['key'], (string) $context['login'] ) ); ?>">
                <input type="hidden" name="scc_password_reset_fallback" value="1">
                <input type="hidden" name="key" value="<?php echo esc_attr( (string) $context['key'] ); ?>">
                <input type="hidden" name="login" value="<?php echo esc_attr( (string) $context['login'] ); ?>">
                <?php wp_nonce_field( $reset_class::fallback_nonce_action() ); ?>

                <div class="scc-password-reset-field">
                    <label for="scc-password-reset-password"><?php esc_html_e( 'Nueva contraseña', 'sultana-commerce-core' ); ?></label>
                    <input id="scc-password-reset-password" type="password" name="password" autocomplete="new-password" minlength="8" required>
                </div>

                <div class="scc-password-reset-field">
                    <label for="scc-password-reset-password-confirm"><?php esc_html_e( 'Confirmar contraseña', 'sultana-commerce-core' ); ?></label>
                    <input id="scc-password-reset-password-confirm" type="password" name="password_confirm" autocomplete="new-password" minlength="8" required>
                </div>

                <button type="submit"><?php esc_html_e( 'Guardar nueva contraseña', 'sultana-commerce-core' ); ?></button>
            </form>
        <?php endif; ?>
    </section>
</main>

<?php
get_footer();

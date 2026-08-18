<?php
/**
 * Frontend password reset form.
 *
 * @package SultanaStorefront
 */

defined( 'ABSPATH' ) || exit;

$reset_class = '\Sultana\CommerceCore\Modules\Accounts\AccountPasswordReset';
$context     = class_exists( $reset_class ) && method_exists( $reset_class, 'reset_request_context' )
    ? $reset_class::reset_request_context()
    : [
        'valid' => false,
        'key'   => '',
        'login' => '',
        'user'  => null,
        'error' => 'unavailable',
    ];

if ( function_exists( 'variedadesexpress_google_tag_manager_head' ) ) {
    remove_action( 'wp_head', 'variedadesexpress_google_tag_manager_head', 1 );
}

if ( function_exists( 'variedadesexpress_google_analytics_head' ) ) {
    remove_action( 'wp_head', 'variedadesexpress_google_analytics_head', 2 );
}

if ( function_exists( 'variedadesexpress_google_tag_manager_body' ) ) {
    remove_action( 'wp_body_open', 'variedadesexpress_google_tag_manager_body', 1 );
}

get_header();
?>

<main class="password-reset-page">
    <section class="password-reset-card" aria-labelledby="password-reset-title">
        <?php if ( ! empty( $context['valid'] ) ) : ?>
            <div class="password-reset-card__content" data-password-reset-active>
                <p class="password-reset-card__eyebrow"><?php esc_html_e( 'Cuenta', 'sultana-storefront' ); ?></p>
                <h1 id="password-reset-title"><?php esc_html_e( 'Crear nueva contraseña', 'sultana-storefront' ); ?></h1>
                <p class="password-reset-card__intro">
                    <?php esc_html_e( 'Crea una nueva contraseña para volver a acceder a tu cuenta.', 'sultana-storefront' ); ?>
                </p>

                <form class="account-form password-reset-form" data-password-reset-form>
                    <p class="account-form__message" data-password-reset-message></p>
                    <input type="hidden" name="key" value="<?php echo esc_attr( (string) $context['key'] ); ?>">
                    <input type="hidden" name="login" value="<?php echo esc_attr( (string) $context['login'] ); ?>">

                    <fieldset class="account-form__field account-form__field--password">
                        <legend><?php esc_html_e( 'Nueva contraseña', 'sultana-storefront' ); ?></legend>
                        <input type="password" name="password" autocomplete="new-password" minlength="8" required>
                        <button class="account-form__password-toggle" type="button" data-password-toggle aria-label="<?php esc_attr_e( 'Mostrar contraseña', 'sultana-storefront' ); ?>">
                            <?php esc_html_e( 'Ver', 'sultana-storefront' ); ?>
                        </button>
                    </fieldset>

                    <fieldset class="account-form__field account-form__field--password">
                        <legend><?php esc_html_e( 'Confirmar contraseña', 'sultana-storefront' ); ?></legend>
                        <input type="password" name="password_confirm" autocomplete="new-password" minlength="8" required>
                        <button class="account-form__password-toggle" type="button" data-password-toggle aria-label="<?php esc_attr_e( 'Mostrar contraseña', 'sultana-storefront' ); ?>">
                            <?php esc_html_e( 'Ver', 'sultana-storefront' ); ?>
                        </button>
                    </fieldset>

                    <button type="submit">
                        <span class="account-form__button-icon" aria-hidden="true"><?php variedadesexpress_icon( 'save', 'account-form__button-svg' ); ?></span>
                        <span class="account-form__button-label" data-button-label><?php esc_html_e( 'Guardar nueva contraseña', 'sultana-storefront' ); ?></span>
                    </button>
                </form>
            </div>

            <div class="password-reset-card__content password-reset-card__success" data-password-reset-success hidden>
                <p class="password-reset-card__eyebrow"><?php esc_html_e( 'Listo', 'sultana-storefront' ); ?></p>
                <h1><?php esc_html_e( 'Contraseña actualizada', 'sultana-storefront' ); ?></h1>
                <p class="password-reset-card__intro">
                    <?php esc_html_e( 'Tu contraseña se cambió correctamente. Ya puedes iniciar sesión con tu nueva contraseña.', 'sultana-storefront' ); ?>
                </p>
                <button class="password-reset-card__button" type="button" data-modal-open="account" data-account-view="login" data-account-login-redirect>
                    <span class="password-reset-card__button-icon" aria-hidden="true"><?php variedadesexpress_icon( 'user', 'password-reset-card__button-svg' ); ?></span>
                    <span><?php esc_html_e( 'Iniciar sesión', 'sultana-storefront' ); ?></span>
                </button>
            </div>
        <?php else : ?>
            <div class="password-reset-card__content password-reset-card__invalid">
                <p class="password-reset-card__eyebrow"><?php esc_html_e( 'Enlace vencido', 'sultana-storefront' ); ?></p>
                <h1 id="password-reset-title"><?php esc_html_e( 'Este enlace ya no es válido', 'sultana-storefront' ); ?></h1>
                <p class="password-reset-card__intro">
                    <?php esc_html_e( 'El enlace para restablecer tu contraseña expiró o ya fue utilizado.', 'sultana-storefront' ); ?>
                </p>
                <button class="password-reset-card__button" type="button" data-modal-open="account" data-account-view="recovery">
                    <span class="password-reset-card__button-icon" aria-hidden="true"><?php variedadesexpress_icon( 'mail-icon', 'password-reset-card__button-svg' ); ?></span>
                    <span><?php esc_html_e( 'Solicitar un nuevo enlace', 'sultana-storefront' ); ?></span>
                </button>
            </div>
        <?php endif; ?>
    </section>
</main>

<?php
get_footer();

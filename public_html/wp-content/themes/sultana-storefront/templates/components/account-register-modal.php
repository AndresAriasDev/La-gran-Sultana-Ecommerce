<?php
/**
 * Account registration modal.
 *
 * @package SultanaStorefront
 */

defined( 'ABSPATH' ) || exit;

if ( is_user_logged_in() ) {
    return;
}
?>

<div class="account-modal" data-account-modal="account" aria-hidden="true">
    <div class="account-modal__overlay" data-modal-close></div>
    <section class="account-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="account-register-title">
        <button class="account-modal__close" type="button" data-modal-close aria-label="<?php esc_attr_e( 'Cerrar', 'sultana-storefront' ); ?>">
            <img src="<?php echo esc_url( get_template_directory_uri() . '/assets/icons/close-icon-blanco.png' ); ?>" alt="" width="18" height="18" aria-hidden="true">
        </button>

        <div class="account-modal__view is-active" data-account-view-panel="register">
            <h3 id="account-register-title"><?php esc_html_e( 'Crea tu cuenta', 'sultana-storefront' ); ?></h3>
            <form class="account-form account-register-form" data-account-register-form novalidate>
                <p class="account-form__message" data-account-register-message></p>
                <fieldset class="account-form__field">
                    <legend><?php esc_html_e( 'Nombres', 'sultana-storefront' ); ?></legend>
                    <input type="text" name="name" autocomplete="name" required>
                </fieldset>
                <fieldset class="account-form__field">
                    <legend><?php esc_html_e( 'Correo', 'sultana-storefront' ); ?></legend>
                    <input type="email" name="email" autocomplete="email" required>
                </fieldset>
                <fieldset class="account-form__field account-form__field--password">
                    <legend><?php esc_html_e( 'Contraseña', 'sultana-storefront' ); ?></legend>
                    <input type="password" name="password" autocomplete="new-password" minlength="8" required>
                    <button class="account-form__password-toggle" type="button" data-password-toggle aria-label="<?php esc_attr_e( 'Mostrar contraseña', 'sultana-storefront' ); ?>">
                        <?php esc_html_e( 'Ver', 'sultana-storefront' ); ?>
                    </button>
                </fieldset>
                <button type="submit"><?php esc_html_e( 'Crear cuenta', 'sultana-storefront' ); ?></button>
                <button class="account-form__switch" type="button" data-account-view="login">
                    <?php esc_html_e( '¿Ya tienes una cuenta?', 'sultana-storefront' ); ?>
                    <span><?php esc_html_e( 'Inicia sesión', 'sultana-storefront' ); ?></span>
                </button>
            </form>
        </div>

        <div class="account-modal__view" data-account-view-panel="login" hidden>
            <h3 id="account-login-title"><?php esc_html_e( 'Iniciar sesión', 'sultana-storefront' ); ?></h3>
            <form class="account-form account-login-form" data-account-login-form>
                <p class="account-form__message" data-account-login-message></p>
                <fieldset class="account-form__field">
                    <legend><?php esc_html_e( 'Correo', 'sultana-storefront' ); ?></legend>
                    <input type="email" name="email" autocomplete="email" required>
                </fieldset>
                <fieldset class="account-form__field account-form__field--password">
                    <legend><?php esc_html_e( 'Contraseña', 'sultana-storefront' ); ?></legend>
                    <input type="password" name="password" autocomplete="off" autocapitalize="off" spellcheck="false" required>
                    <button class="account-form__password-toggle" type="button" data-password-toggle aria-label="<?php esc_attr_e( 'Mostrar contraseña', 'sultana-storefront' ); ?>">
                        <?php esc_html_e( 'Ver', 'sultana-storefront' ); ?>
                    </button>
                </fieldset>
                <button type="submit"><?php esc_html_e( 'Iniciar sesión', 'sultana-storefront' ); ?></button>
                <a class="account-form__forgot-link" href="#" data-account-password-recovery><?php esc_html_e( '¿Has olvidado tu contraseña?', 'sultana-storefront' ); ?></a>
                <button class="account-form__switch" type="button" data-account-view="register">
                    <?php esc_html_e( '¿No tienes una cuenta?', 'sultana-storefront' ); ?>
                    <span><?php esc_html_e( 'Crea una cuenta', 'sultana-storefront' ); ?></span>
                </button>
            </form>
        </div>

        <div class="account-modal__view" data-account-view-panel="recovery" hidden>
            <h3 id="account-recovery-title" data-account-recovery-title><?php esc_html_e( 'Recuperar contraseña', 'sultana-storefront' ); ?></h3>
            <form class="account-form account-recovery-form" data-account-recovery-form>
                <p class="account-form__intro" data-account-recovery-intro>
                    <?php esc_html_e( 'Ingresá el correo de tu cuenta y te enviaremos un enlace para crear una nueva contraseña.', 'sultana-storefront' ); ?>
                </p>
                <p class="account-form__message" data-account-recovery-message></p>
                <div data-account-recovery-fields>
                    <fieldset class="account-form__field">
                        <legend><?php esc_html_e( 'Correo', 'sultana-storefront' ); ?></legend>
                        <input type="email" name="email" autocomplete="email" required>
                    </fieldset>
                </div>
                <div class="account-form__recovery-success" data-account-recovery-success hidden>
                    <p><?php esc_html_e( 'Te enviamos un enlace para crear una nueva contraseña.', 'sultana-storefront' ); ?></p>
                </div>
                <button type="submit">
                    <span class="account-form__button-icon" aria-hidden="true"><?php variedadesexpress_icon( 'mail-icon', 'account-form__button-svg' ); ?></span>
                    <span class="account-form__button-label" data-button-label><?php esc_html_e( 'Enviar enlace', 'sultana-storefront' ); ?></span>
                </button>
                <button class="account-form__switch" type="button" data-account-view="login">
                    <span class="account-form__switch-icon" aria-hidden="true"><?php variedadesexpress_icon( 'chevron-left', 'account-form__switch-svg' ); ?></span>
                    <span><?php esc_html_e( 'Volver a iniciar sesión', 'sultana-storefront' ); ?></span>
                </button>
            </form>
        </div>
    </section>
</div>

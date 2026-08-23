<?php

use Sultana\Admin\Core\Auth;
use Sultana\Admin\Core\Router;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$valid   = ! empty( $reset_context['valid'] );
$success = ! empty( $reset_context['success'] );
$error   = (string) ( $reset_context['error'] ?? '' );
$key     = (string) ( $reset_context['key'] ?? '' );
$login   = (string) ( $reset_context['login'] ?? '' );

?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php esc_html_e( 'Crear nueva contraseña - Admin', 'sultana-admin' ); ?></title>
    <?php wp_head(); ?>
</head>
<body>
    <main class="sultana-admin-login">
        <section class="sultana-admin-login__panel" aria-labelledby="sultana-admin-password-reset-title">
            <?php if ( $success ) : ?>
                <h1 id="sultana-admin-password-reset-title"><?php esc_html_e( 'Contraseña actualizada', 'sultana-admin' ); ?></h1>
                <p class="sultana-admin-login__intro">
                    <?php esc_html_e( 'Tu contraseña se ha cambiado correctamente.', 'sultana-admin' ); ?>
                </p>
                <a class="sultana-admin-login__primary-link" href="<?php echo esc_url( Router::login_url() ); ?>">
                    <?php esc_html_e( 'Iniciar sesión', 'sultana-admin' ); ?>
                </a>
            <?php elseif ( $valid ) : ?>
                <h1 id="sultana-admin-password-reset-title"><?php esc_html_e( 'Crear nueva contraseña', 'sultana-admin' ); ?></h1>

                <?php if ( '' !== $error ) : ?>
                    <div class="sultana-admin-login__error" role="alert">
                        <?php echo esc_html( $error ); ?>
                    </div>
                <?php endif; ?>

                <form method="post" action="<?php echo esc_url( Router::password_reset_url( $key, $login ) ); ?>" data-sultana-admin-login-form>
                    <?php wp_nonce_field( Auth::PASSWORD_RESET_COMPLETE_NONCE_ACTION, 'sultana_admin_password_reset_complete_nonce' ); ?>
                    <input type="hidden" name="key" value="<?php echo esc_attr( $key ); ?>">
                    <input type="hidden" name="login" value="<?php echo esc_attr( $login ); ?>">

                    <div class="sultana-admin-login__field sultana-admin-login__field--password">
                        <label for="sultana-admin-password-reset-new">
                            <?php esc_html_e( 'Nueva contraseña', 'sultana-admin' ); ?>
                        </label>
                        <input id="sultana-admin-password-reset-new" type="password" name="password" autocomplete="new-password" required>
                        <button class="sultana-admin-login__password-toggle" type="button" data-password-toggle aria-label="<?php esc_attr_e( 'Mostrar contraseña', 'sultana-admin' ); ?>">
                            <span class="sultana-admin-login__password-toggle-icon" aria-hidden="true"></span>
                        </button>
                    </div>

                    <div class="sultana-admin-login__field sultana-admin-login__field--password">
                        <label for="sultana-admin-password-reset-confirm">
                            <?php esc_html_e( 'Confirmar contraseña', 'sultana-admin' ); ?>
                        </label>
                        <input id="sultana-admin-password-reset-confirm" type="password" name="password_confirm" autocomplete="new-password" required>
                        <button class="sultana-admin-login__password-toggle" type="button" data-password-toggle aria-label="<?php esc_attr_e( 'Mostrar contraseña', 'sultana-admin' ); ?>">
                            <span class="sultana-admin-login__password-toggle-icon" aria-hidden="true"></span>
                        </button>
                    </div>

                    <button class="sultana-admin-login__submit" type="submit" data-login-submit data-loading-text="<?php esc_attr_e( 'Guardando contraseña...', 'sultana-admin' ); ?>">
                        <span class="sultana-admin-login__spinner" aria-hidden="true"></span>
                        <span class="sultana-admin-login__submit-text"><?php esc_html_e( 'Guardar contraseña', 'sultana-admin' ); ?></span>
                    </button>
                </form>
            <?php else : ?>
                <h1 id="sultana-admin-password-reset-title"><?php esc_html_e( 'Este enlace ya no es válido', 'sultana-admin' ); ?></h1>
                <p class="sultana-admin-login__intro">
                    <?php esc_html_e( 'El enlace para restablecer tu contraseña expiró o ya fue utilizado.', 'sultana-admin' ); ?>
                </p>
                <a class="sultana-admin-login__primary-link" href="<?php echo esc_url( Router::password_request_url() ); ?>">
                    <?php esc_html_e( 'Solicitar un nuevo enlace', 'sultana-admin' ); ?>
                </a>
            <?php endif; ?>
        </section>
    </main>
    <?php wp_footer(); ?>
</body>
</html>

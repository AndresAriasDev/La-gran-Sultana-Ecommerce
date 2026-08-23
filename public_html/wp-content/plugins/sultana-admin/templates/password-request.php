<?php

use Sultana\Admin\Core\Auth;
use Sultana\Admin\Core\Router;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$sent     = ! empty( $reset_state['sent'] );
$error    = (string) ( $reset_state['error'] ?? '' );
$login    = (string) ( $reset_state['login'] ?? '' );
$cooldown = absint( $reset_state['cooldown'] ?? Auth::password_reset_cooldown_seconds() );

?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php esc_html_e( 'Recuperar contraseña - Admin', 'sultana-admin' ); ?></title>
    <?php wp_head(); ?>
</head>
<body>
    <main class="sultana-admin-login">
        <section class="sultana-admin-login__panel" aria-labelledby="sultana-admin-password-request-title">
            <?php if ( $sent ) : ?>
                <h1 id="sultana-admin-password-request-title"><?php esc_html_e( 'Revisa tu correo', 'sultana-admin' ); ?></h1>
                <p class="sultana-admin-login__intro">
                    <?php echo esc_html( Auth::neutral_password_reset_message() ); ?>
                </p>

                <form method="post" action="<?php echo esc_url( Router::password_request_url() ); ?>" data-sultana-admin-login-form>
                    <?php wp_nonce_field( Auth::PASSWORD_RESET_REQUEST_NONCE_ACTION, 'sultana_admin_password_reset_nonce' ); ?>
                    <input type="hidden" name="user_login" value="<?php echo esc_attr( $login ); ?>">

                    <button class="sultana-admin-login__submit" type="submit" data-login-submit data-loading-text="<?php esc_attr_e( 'Enviando enlace...', 'sultana-admin' ); ?>" data-cooldown-seconds="<?php echo esc_attr( (string) $cooldown ); ?>" data-cooldown-label="<?php esc_attr_e( 'Volver a enviar enlace', 'sultana-admin' ); ?>">
                        <span class="sultana-admin-login__spinner" aria-hidden="true"></span>
                        <span class="sultana-admin-login__submit-text"><?php esc_html_e( 'Volver a enviar enlace', 'sultana-admin' ); ?></span>
                    </button>
                </form>

                <a class="sultana-admin-login__secondary-link" href="<?php echo esc_url( Router::login_url() ); ?>">
                    <?php esc_html_e( 'Volver a iniciar sesión', 'sultana-admin' ); ?>
                </a>
            <?php else : ?>
                <h1 id="sultana-admin-password-request-title"><?php esc_html_e( 'Recuperar contraseña', 'sultana-admin' ); ?></h1>
                <p class="sultana-admin-login__intro">
                    <?php esc_html_e( 'Introduce tu usuario o correo y te enviaremos un enlace para crear una nueva contraseña.', 'sultana-admin' ); ?>
                </p>

                <?php if ( '' !== $error ) : ?>
                    <div class="sultana-admin-login__error" role="alert">
                        <?php echo esc_html( $error ); ?>
                    </div>
                <?php endif; ?>

                <form method="post" action="<?php echo esc_url( Router::password_request_url() ); ?>" data-sultana-admin-login-form>
                    <?php wp_nonce_field( Auth::PASSWORD_RESET_REQUEST_NONCE_ACTION, 'sultana_admin_password_reset_nonce' ); ?>

                    <div class="sultana-admin-login__field">
                        <label for="sultana-admin-password-request-user">
                            <?php esc_html_e( 'Usuario o correo', 'sultana-admin' ); ?>
                        </label>
                        <input id="sultana-admin-password-request-user" type="text" name="user_login" value="<?php echo esc_attr( $login ); ?>" autocomplete="username" required>
                    </div>

                    <button class="sultana-admin-login__submit" type="submit" data-login-submit data-loading-text="<?php esc_attr_e( 'Enviando enlace...', 'sultana-admin' ); ?>">
                        <span class="sultana-admin-login__spinner" aria-hidden="true"></span>
                        <span class="sultana-admin-login__submit-text"><?php esc_html_e( 'Enviar enlace', 'sultana-admin' ); ?></span>
                    </button>
                </form>

                <a class="sultana-admin-login__secondary-link" href="<?php echo esc_url( Router::login_url() ); ?>">
                    <?php esc_html_e( 'Volver a iniciar sesión', 'sultana-admin' ); ?>
                </a>
            <?php endif; ?>
        </section>
    </main>
    <?php wp_footer(); ?>
</body>
</html>

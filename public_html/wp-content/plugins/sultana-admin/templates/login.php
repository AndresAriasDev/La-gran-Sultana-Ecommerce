<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php esc_html_e( 'Iniciar sesión - Admin', 'sultana-admin' ); ?></title>
    <?php wp_head(); ?>
</head>
<body>
    <main class="sultana-admin-login">
        <section class="sultana-admin-login__panel" aria-labelledby="sultana-admin-login-title">
            <h1 id="sultana-admin-login-title"><?php esc_html_e( 'Admin', 'sultana-admin' ); ?></h1>

            <?php if ( '' !== $login_error ) : ?>
                <div class="sultana-admin-login__error" role="alert">
                    <?php echo esc_html( $login_error ); ?>
                </div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url( \Sultana\Admin\Core\Router::login_url() ); ?>" data-sultana-admin-login-form>
                <?php wp_nonce_field( \Sultana\Admin\Core\Auth::LOGIN_NONCE_ACTION, 'sultana_admin_login_nonce' ); ?>

                <div class="sultana-admin-login__field">
                    <label for="sultana-admin-login-user">
                        <?php esc_html_e( 'Usuario o correo', 'sultana-admin' ); ?>
                    </label>
                    <input id="sultana-admin-login-user" type="text" name="log" autocomplete="username" required>
                </div>

                <div class="sultana-admin-login__field sultana-admin-login__field--password">
                    <label for="sultana-admin-login-password">
                        <?php esc_html_e( 'Contraseña', 'sultana-admin' ); ?>
                    </label>
                    <input id="sultana-admin-login-password" type="password" name="pwd" autocomplete="current-password" required>
                    <button class="sultana-admin-login__password-toggle" type="button" data-password-toggle aria-label="<?php esc_attr_e( 'Mostrar contraseña', 'sultana-admin' ); ?>">
                        <span class="sultana-admin-login__password-toggle-icon" aria-hidden="true"></span>
                    </button>
                </div>

                <label class="sultana-admin-login__remember">
                    <input type="checkbox" name="rememberme" value="1">
                    <span><?php esc_html_e( 'Recordarme', 'sultana-admin' ); ?></span>
                </label>

                <button class="sultana-admin-login__submit" type="submit" data-login-submit>
                    <span class="sultana-admin-login__submit-text"><?php esc_html_e( 'Iniciar sesión', 'sultana-admin' ); ?></span>
                    <span class="sultana-admin-login__spinner" aria-hidden="true"></span>
                </button>

                <a class="sultana-admin-login__secondary-link" href="<?php echo esc_url( \Sultana\Admin\Core\Router::password_request_url() ); ?>">
                    <?php esc_html_e( '¿Has olvidado tu contraseña?', 'sultana-admin' ); ?>
                </a>
            </form>
        </section>
    </main>
    <?php wp_footer(); ?>
</body>
</html>

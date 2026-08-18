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
    <title><?php esc_html_e( 'Iniciar sesión - Sultana Admin', 'sultana-admin' ); ?></title>
    <?php wp_head(); ?>
</head>
<body>
    <main class="sultana-admin-login">
        <section class="sultana-admin-login__panel" aria-labelledby="sultana-admin-login-title">
            <h1 id="sultana-admin-login-title"><?php esc_html_e( 'Sultana Admin', 'sultana-admin' ); ?></h1>
            <p><?php esc_html_e( 'Acceso para gestores de tienda.', 'sultana-admin' ); ?></p>

            <?php if ( '' !== $login_error ) : ?>
                <div class="sultana-admin-login__error" role="alert">
                    <?php echo esc_html( $login_error ); ?>
                </div>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url( \Sultana\Admin\Core\Router::login_url() ); ?>">
                <?php wp_nonce_field( \Sultana\Admin\Core\Auth::LOGIN_NONCE_ACTION, 'sultana_admin_login_nonce' ); ?>

                <label for="sultana-admin-login-user">
                    <?php esc_html_e( 'Correo electrónico o usuario', 'sultana-admin' ); ?>
                </label>
                <input id="sultana-admin-login-user" type="text" name="log" autocomplete="username" required>

                <label for="sultana-admin-login-password">
                    <?php esc_html_e( 'Contraseña', 'sultana-admin' ); ?>
                </label>
                <input id="sultana-admin-login-password" type="password" name="pwd" autocomplete="current-password" required>

                <label class="sultana-admin-login__remember">
                    <input type="checkbox" name="rememberme" value="1">
                    <span><?php esc_html_e( 'Recordarme', 'sultana-admin' ); ?></span>
                </label>

                <button type="submit"><?php esc_html_e( 'Iniciar sesión', 'sultana-admin' ); ?></button>
            </form>
        </section>
    </main>
    <?php wp_footer(); ?>
</body>
</html>

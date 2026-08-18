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
    <title><?php esc_html_e( 'Sultana Admin', 'sultana-admin' ); ?></title>
    <?php wp_head(); ?>
</head>
<body>
    <main style="max-width:720px;margin:48px auto;padding:24px;font-family:system-ui,-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;">
        <h1><?php esc_html_e( 'Sultana Admin', 'sultana-admin' ); ?></h1>
        <p><?php esc_html_e( 'Panel operativo funcionando correctamente.', 'sultana-admin' ); ?></p>
        <p>
            <?php esc_html_e( 'Usuario actual:', 'sultana-admin' ); ?>
            <strong><?php echo esc_html( $current_user->display_name ?: $current_user->user_login ); ?></strong>
        </p>
        <p>
            <a href="<?php echo esc_url( $logout_url ); ?>">
                <?php esc_html_e( 'Cerrar sesion', 'sultana-admin' ); ?>
            </a>
        </p>
    </main>
    <?php wp_footer(); ?>
</body>
</html>

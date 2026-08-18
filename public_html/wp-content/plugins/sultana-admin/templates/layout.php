<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$display_name = $current_user->display_name ?: $current_user->user_login;

?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo esc_html( $screen['title'] ); ?> - <?php esc_html_e( 'Sultana Admin', 'sultana-admin' ); ?></title>
    <?php wp_head(); ?>
</head>
<body class="sultana-admin-body">
    <div class="sultana-admin-shell">
        <aside class="sultana-admin-sidebar" aria-label="<?php esc_attr_e( 'Navegación principal', 'sultana-admin' ); ?>">
            <div class="sultana-admin-brand">
                <span class="sultana-admin-brand__mark" aria-hidden="true">S</span>
                <span>
                    <strong><?php esc_html_e( 'Sultana Admin', 'sultana-admin' ); ?></strong>
                    <small><?php esc_html_e( 'Gestión de tienda', 'sultana-admin' ); ?></small>
                </span>
            </div>

            <nav class="sultana-admin-nav">
                <?php foreach ( $nav_items as $route_key => $item ) : ?>
                    <a
                        href="<?php echo esc_url( $item['url'] ); ?>"
                        class="<?php echo esc_attr( 'sultana-admin-nav__link' . ( $active_route === $route_key ? ' is-active' : '' ) ); ?>"
                        <?php echo $active_route === $route_key ? 'aria-current="page"' : ''; ?>
                    >
                        <?php echo esc_html( $item['label'] ); ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="sultana-admin-user">
                <span><?php echo esc_html( $display_name ); ?></span>
                <form method="post" action="<?php echo esc_url( $logout_url ); ?>">
                    <?php wp_nonce_field( \Sultana\Admin\Core\Auth::LOGOUT_NONCE_ACTION, 'sultana_admin_logout_nonce' ); ?>
                    <button type="submit"><?php esc_html_e( 'Cerrar sesión', 'sultana-admin' ); ?></button>
                </form>
            </div>
        </aside>

        <div class="sultana-admin-main">
            <header class="sultana-admin-mobile-header">
                <div class="sultana-admin-brand sultana-admin-brand--compact">
                    <span class="sultana-admin-brand__mark" aria-hidden="true">S</span>
                    <strong><?php esc_html_e( 'Sultana Admin', 'sultana-admin' ); ?></strong>
                </div>
                <form method="post" action="<?php echo esc_url( $logout_url ); ?>">
                    <?php wp_nonce_field( \Sultana\Admin\Core\Auth::LOGOUT_NONCE_ACTION, 'sultana_admin_logout_nonce' ); ?>
                    <button type="submit"><?php esc_html_e( 'Salir', 'sultana-admin' ); ?></button>
                </form>
            </header>

            <main class="sultana-admin-content" id="sultana-admin-content">
                <?php require $screen['template']; ?>
            </main>
        </div>

        <nav class="sultana-admin-mobile-nav" aria-label="<?php esc_attr_e( 'Navegación móvil principal', 'sultana-admin' ); ?>">
            <?php foreach ( $nav_items as $route_key => $item ) : ?>
                <a
                    href="<?php echo esc_url( $item['url'] ); ?>"
                    class="<?php echo esc_attr( 'sultana-admin-mobile-nav__link' . ( $active_route === $route_key ? ' is-active' : '' ) ); ?>"
                    <?php echo $active_route === $route_key ? 'aria-current="page"' : ''; ?>
                >
                    <?php echo esc_html( $item['label'] ); ?>
                </a>
            <?php endforeach; ?>
        </nav>
    </div>
    <?php wp_footer(); ?>
</body>
</html>

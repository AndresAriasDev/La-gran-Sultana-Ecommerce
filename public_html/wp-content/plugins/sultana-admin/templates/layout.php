<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$display_name = $current_user->display_name ?: $current_user->user_login;
$shell_subtitle = $screen['subtitle'] ?? $screen['title'] ?? __( 'Inicio', 'sultana-admin' );

$sultana_admin_icon_asset = static function ( string $name ): void {
    $icon_url = \Sultana\Admin\Core\Icons::url( $name );

    if ( '' === $icon_url ) {
        return;
    }

    ?>
    <span class="sultana-admin-icon" style="--sultana-admin-icon-url: url('<?php echo esc_url( $icon_url ); ?>');" aria-hidden="true"></span>
    <?php
};

$sultana_admin_nav_icons = [
    'dashboard' => 'layout-panel-left',
    'products'  => 'box',
    'orders'    => 'shelving-unit',
];

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
    <a class="sultana-admin-skip-link" href="#sultana-admin-content"><?php esc_html_e( 'Saltar al contenido', 'sultana-admin' ); ?></a>
    <div class="sultana-admin-shell">
        <aside class="sultana-admin-sidebar" aria-label="<?php esc_attr_e( 'Navegación principal', 'sultana-admin' ); ?>">
            <a class="sultana-admin-brand" href="<?php echo esc_url( \Sultana\Admin\Core\Router::dashboard_url() ); ?>">
                <span class="sultana-admin-brand__mark" aria-hidden="true">S</span>
                <span>
                    <strong><?php esc_html_e( 'Sultana Admin', 'sultana-admin' ); ?></strong>
                    <small><?php echo esc_html( $shell_subtitle ); ?></small>
                </span>
            </a>

            <nav class="sultana-admin-nav">
                <?php foreach ( $nav_items as $route_key => $item ) : ?>
                    <a
                        href="<?php echo esc_url( $item['url'] ); ?>"
                        class="<?php echo esc_attr( 'sultana-admin-nav__link' . ( $active_route === $route_key ? ' is-active' : '' ) ); ?>"
                        <?php echo $active_route === $route_key ? 'aria-current="page"' : ''; ?>
                    >
                        <span class="sultana-admin-nav__icon">
                            <?php $sultana_admin_icon_asset( $sultana_admin_nav_icons[ $route_key ] ?? '' ); ?>
                        </span>
                        <?php echo esc_html( $item['label'] ); ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="sultana-admin-user">
                <strong><?php echo esc_html( $display_name ); ?></strong>
                <form method="post" action="<?php echo esc_url( $logout_url ); ?>">
                    <?php wp_nonce_field( \Sultana\Admin\Core\Auth::LOGOUT_NONCE_ACTION, 'sultana_admin_logout_nonce' ); ?>
                    <button class="sultana-admin-logout-button" type="submit">
                        <?php $sultana_admin_icon_asset( 'log-out' ); ?>
                        <?php esc_html_e( 'Cerrar sesión', 'sultana-admin' ); ?>
                    </button>
                </form>
            </div>
        </aside>

        <div class="sultana-admin-main">
            <header class="sultana-admin-mobile-header">
                <a class="sultana-admin-brand sultana-admin-brand--compact" href="<?php echo esc_url( \Sultana\Admin\Core\Router::dashboard_url() ); ?>">
                    <span class="sultana-admin-brand__mark" aria-hidden="true">S</span>
                    <span>
                        <strong><?php esc_html_e( 'Sultana Admin', 'sultana-admin' ); ?></strong>
                        <small><?php echo esc_html( $shell_subtitle ); ?></small>
                    </span>
                </a>
                <form method="post" action="<?php echo esc_url( $logout_url ); ?>">
                    <?php wp_nonce_field( \Sultana\Admin\Core\Auth::LOGOUT_NONCE_ACTION, 'sultana_admin_logout_nonce' ); ?>
                    <button class="sultana-admin-icon-button sultana-admin-mobile-logout" type="submit" aria-label="<?php esc_attr_e( 'Cerrar sesión', 'sultana-admin' ); ?>">
                        <?php $sultana_admin_icon_asset( 'log-out' ); ?>
                    </button>
                </form>
            </header>

            <main class="sultana-admin-content" id="sultana-admin-content" tabindex="-1">
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
                    <span class="sultana-admin-nav__icon">
                        <?php $sultana_admin_icon_asset( $sultana_admin_nav_icons[ $route_key ] ?? '' ); ?>
                    </span>
                    <?php echo esc_html( $item['label'] ); ?>
                </a>
            <?php endforeach; ?>
        </nav>
    </div>
    <?php wp_footer(); ?>
</body>
</html>

<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$display_name = $current_user->display_name ?: $current_user->user_login;

?>
<section class="sultana-admin-hero" aria-labelledby="sultana-admin-dashboard-title">
    <p class="sultana-admin-kicker"><?php esc_html_e( 'Panel operativo', 'sultana-admin' ); ?></p>
    <h1 id="sultana-admin-dashboard-title">
        <?php
        printf(
            /* translators: %s: current user display name. */
            esc_html__( 'Hola, %s', 'sultana-admin' ),
            esc_html( $display_name )
        );
        ?>
    </h1>
</section>

<section class="sultana-admin-quicklinks" aria-label="<?php esc_attr_e( 'Accesos principales', 'sultana-admin' ); ?>">
    <a class="sultana-admin-card" href="<?php echo esc_url( \Sultana\Admin\Core\Router::products_url() ); ?>">
        <span class="sultana-admin-card__icon" aria-hidden="true">
            <svg width="24" height="24" viewBox="0 0 24 24" focusable="false" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M4 7.5 12 3l8 4.5v9L12 21l-8-4.5z"></path>
                <path d="M12 12 4.4 7.7"></path>
                <path d="M12 12v8.5"></path>
                <path d="m12 12 7.6-4.3"></path>
            </svg>
        </span>
        <span>
            <strong><?php esc_html_e( 'Productos', 'sultana-admin' ); ?></strong>
            <small><?php esc_html_e( 'Preparado para catálogo y stock.', 'sultana-admin' ); ?></small>
        </span>
    </a>

    <a class="sultana-admin-card" href="<?php echo esc_url( \Sultana\Admin\Core\Router::orders_url() ); ?>">
        <span class="sultana-admin-card__icon" aria-hidden="true">
            <svg width="24" height="24" viewBox="0 0 24 24" focusable="false" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M7 4h10l2 17H5z"></path>
                <path d="M9 8a3 3 0 0 0 6 0"></path>
            </svg>
        </span>
        <span>
            <strong><?php esc_html_e( 'Pedidos', 'sultana-admin' ); ?></strong>
            <small><?php esc_html_e( 'Preparado para gestión operativa.', 'sultana-admin' ); ?></small>
        </span>
    </a>
</section>

<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();
?>

<div class="error-404">
    <div class="error-404__container">
        <p class="error-404__code">404</p>

        <h1 class="error-404__title">
            <?php esc_html_e( 'Pagina no encontrada', 'sultana-storefront' ); ?>
        </h1>

        <p class="error-404__text">
            <?php esc_html_e( 'La pagina que buscas no existe o fue movida.', 'sultana-storefront' ); ?>
        </p>

        <a class="error-404__button" href="<?php echo esc_url( home_url( '/' ) ); ?>">
            <?php esc_html_e( 'Volver al inicio', 'sultana-storefront' ); ?>
        </a>
    </div>
</div>

<?php
get_footer();

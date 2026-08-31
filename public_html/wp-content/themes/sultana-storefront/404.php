<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$shop_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );

get_header();
?>

<main class="error-404-page" id="primary">
    <div class="error-404-page__container">
        <section class="error-404-page__card" aria-labelledby="error-404-title">
            <p class="error-404-page__code" aria-hidden="true">404</p>

            <h1 class="error-404-page__title" id="error-404-title">
                <?php esc_html_e( 'No encontramos esta página', 'sultana-storefront' ); ?>
            </h1>

            <p class="error-404-page__text">
                <?php esc_html_e( 'Es posible que el contenido ya no esté disponible o que la dirección haya cambiado.', 'sultana-storefront' ); ?>
            </p>

            <div class="error-404-page__actions">
                <a class="button" href="<?php echo esc_url( $shop_url ); ?>">
                    <?php esc_html_e( 'Ir a la tienda', 'sultana-storefront' ); ?>
                </a>
                <a class="button button--secondary" href="<?php echo esc_url( home_url( '/' ) ); ?>">
                    <?php esc_html_e( 'Volver al inicio', 'sultana-storefront' ); ?>
                </a>
            </div>
        </section>
    </div>
</main>

<?php
get_footer();

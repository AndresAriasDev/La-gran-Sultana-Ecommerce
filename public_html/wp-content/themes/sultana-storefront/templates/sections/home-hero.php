<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<section class="home-hero" aria-labelledby="home-hero-title">
    <div class="home-hero__content">
        <p class="home-hero__eyebrow">
            <?php echo esc_html( function_exists( 'sultana_storefront_store_name' ) ? sultana_storefront_store_name() : wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ); ?>
        </p>

        <h1 id="home-hero-title" class="home-hero__title">
            <?php esc_html_e( 'Compra facil para tu casa, tu estilo y tus detalles.', 'sultana-storefront' ); ?>
        </h1>

        <p class="home-hero__text">
            <?php esc_html_e( 'Una tienda pensada para descubrir productos utiles, bonitos y listos para pedir sin complicaciones.', 'sultana-storefront' ); ?>
        </p>

        <div class="home-hero__actions">
            <?php if ( class_exists( 'WooCommerce' ) ) : ?>
                <a class="button" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>">
                    <?php esc_html_e( 'Explorar tienda', 'sultana-storefront' ); ?>
                </a>
            <?php endif; ?>

            <a class="button button--secondary" href="#home-categories">
                <?php esc_html_e( 'Ver categorias', 'sultana-storefront' ); ?>
            </a>
        </div>
    </div>

    <div class="home-hero__visual" aria-hidden="true">
        <div class="wire-product wire-product--large"></div>
        <div class="wire-product"></div>
        <div class="wire-product wire-product--accent"></div>
    </div>
</section>

<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<section class="home-cta" aria-labelledby="home-cta-title">
    <div>
        <p class="home-section__eyebrow"><?php esc_html_e( 'Lista para crecer', 'sultana-storefront' ); ?></p>
        <h2 id="home-cta-title"><?php esc_html_e( 'Una base visual modular para construir la tienda completa.', 'sultana-storefront' ); ?></h2>
    </div>

    <?php if ( class_exists( 'WooCommerce' ) ) : ?>
        <a class="button button--light" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>">
            <?php esc_html_e( 'Ir a la tienda', 'sultana-storefront' ); ?>
        </a>
    <?php endif; ?>
</section>

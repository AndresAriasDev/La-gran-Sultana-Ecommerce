<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<section class="home-section home-featured" aria-labelledby="home-featured-title">
    <div class="home-section__header home-section__header--split">
        <div>
            <p class="home-section__eyebrow"><?php esc_html_e( 'Destacados', 'sultana-storefront' ); ?></p>
            <h2 id="home-featured-title"><?php esc_html_e( 'Productos para mostrar primero', 'sultana-storefront' ); ?></h2>
        </div>

        <?php if ( class_exists( 'WooCommerce' ) ) : ?>
            <a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>">
                <?php esc_html_e( 'Ver todos', 'sultana-storefront' ); ?>
            </a>
        <?php endif; ?>
    </div>

    <div class="product-wire-grid">
        <?php for ( $index = 1; $index <= 4; $index++ ) : ?>
            <article class="product-wire-card">
                <span class="product-wire-card__media" aria-hidden="true"></span>
                <span class="product-wire-card__line product-wire-card__line--strong"></span>
                <span class="product-wire-card__line"></span>
                <span class="product-wire-card__price"></span>
            </article>
        <?php endfor; ?>
    </div>
</section>

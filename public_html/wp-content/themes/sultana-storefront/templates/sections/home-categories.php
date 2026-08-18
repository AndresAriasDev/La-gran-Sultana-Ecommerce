<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$product_categories = [];

if ( class_exists( 'WooCommerce' ) ) {
    $product_categories = get_terms(
        [
            'taxonomy'   => 'product_cat',
            'parent'     => 0,
            'hide_empty' => false,
            'number'     => 24,
            'orderby'    => 'menu_order',
            'order'      => 'ASC',
            'exclude'    => get_option( 'default_product_cat' ),
        ]
    );

    if ( is_wp_error( $product_categories ) ) {
        $product_categories = [];
    }
}
?>

<?php if ( $product_categories ) : ?>
    <section id="home-categories" class="home-categories-carousel" aria-labelledby="home-categories-title">
        <div class="home-categories-carousel__header">
            <h1 id="home-categories-title" class="screen-reader-text">
                <?php esc_html_e( 'Categorias principales', 'sultana-storefront' ); ?>
            </h1>
        </div>

        <div class="home-categories-carousel__viewport">
            <button class="home-categories-carousel__arrow home-categories-carousel__arrow--prev" type="button" aria-label="<?php esc_attr_e( 'Ver categorias anteriores', 'sultana-storefront' ); ?>" disabled>
                <img src="<?php echo esc_url( get_template_directory_uri() . '/assets/icons/chevron-left.svg' ); ?>" alt="" width="22" height="22" aria-hidden="true">
            </button>

            <div class="home-categories-carousel__track" tabindex="0">
                <?php foreach ( $product_categories as $category ) : ?>
                    <?php
                    $category_link = get_term_link( $category );

                    if ( is_wp_error( $category_link ) ) {
                        continue;
                    }

                    $thumbnail_id = (int) get_term_meta( $category->term_id, 'thumbnail_id', true );
                    $image_url    = $thumbnail_id ? wp_get_attachment_image_url( $thumbnail_id, 'woocommerce_thumbnail' ) : wc_placeholder_img_src( 'woocommerce_thumbnail' );
                    ?>

                    <a class="home-category-card" href="<?php echo esc_url( $category_link ); ?>">
                        <span class="home-category-card__image-wrap js-image-skeleton">
                            <img
                                class="home-category-card__image"
                                src="<?php echo esc_url( $image_url ); ?>"
                                alt="<?php echo esc_attr( $category->name ); ?>"
                                loading="lazy"
                                decoding="async"
                            >
                        </span>
                        <span class="home-category-card__name">
                            <?php echo esc_html( $category->name ); ?>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>

            <button class="home-categories-carousel__arrow home-categories-carousel__arrow--next" type="button" aria-label="<?php esc_attr_e( 'Ver mas categorias', 'sultana-storefront' ); ?>">
                <img src="<?php echo esc_url( get_template_directory_uri() . '/assets/icons/chevron-right.svg' ); ?>" alt="" width="22" height="22" aria-hidden="true">
            </button>
        </div>
    </section>
<?php endif; ?>

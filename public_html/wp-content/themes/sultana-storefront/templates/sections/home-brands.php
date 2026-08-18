<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'variedadesexpress_home_brands_taxonomy' ) ) {
    function variedadesexpress_home_brands_taxonomy(): string
    {
        foreach ( [ 'product_brand', 'pa_marca', 'pa_brand', 'yith_product_brand' ] as $taxonomy ) {
            if ( taxonomy_exists( $taxonomy ) ) {
                return $taxonomy;
            }
        }

        return '';
    }
}

if ( ! function_exists( 'variedadesexpress_home_brand_logo_url' ) ) {
    function variedadesexpress_home_brand_logo_url( WP_Term $brand ): string
    {
        foreach ( [ 'thumbnail_id', 'brand_thumbnail_id', 'product_brand_thumbnail_id', 'logo_id', 'brand_logo', 'image' ] as $meta_key ) {
            $meta_value = get_term_meta( $brand->term_id, $meta_key, true );

            if ( ! $meta_value ) {
                continue;
            }

            if ( is_numeric( $meta_value ) ) {
                $image_url = wp_get_attachment_image_url( (int) $meta_value, 'full' );

                if ( $image_url ) {
                    return $image_url;
                }
            }

            if ( is_string( $meta_value ) && filter_var( $meta_value, FILTER_VALIDATE_URL ) ) {
                return $meta_value;
            }
        }

        return '';
    }
}

$brand_taxonomy = variedadesexpress_home_brands_taxonomy();

if ( '' === $brand_taxonomy ) {
    return;
}

$brands = get_terms(
    [
        'taxonomy'   => $brand_taxonomy,
        'hide_empty' => false,
        'orderby'    => 'name',
        'order'      => 'ASC',
        'number'     => 18,
    ]
);

if ( is_wp_error( $brands ) || empty( $brands ) ) {
    return;
}
?>

<section class="home-brands" aria-labelledby="home-brands-title">
    <header class="home-brands__header">
        <span aria-hidden="true"></span>
        <h2 id="home-brands-title">
            <?php esc_html_e( 'Marcas que distribuimos', 'sultana-storefront' ); ?>
        </h2>
        <span aria-hidden="true"></span>
    </header>

    <div class="home-brands__viewport">
        <button class="home-brands__arrow home-brands__arrow--prev" type="button" aria-label="<?php esc_attr_e( 'Ver marcas anteriores', 'sultana-storefront' ); ?>" disabled>
            <img src="<?php echo esc_url( get_template_directory_uri() . '/assets/icons/chevron-left.svg' ); ?>" alt="" width="22" height="22" aria-hidden="true">
        </button>

        <div class="home-brands__grid" tabindex="0">
        <?php foreach ( $brands as $brand ) : ?>
            <?php
            $brand_link = get_term_link( $brand );

            if ( is_wp_error( $brand_link ) ) {
                continue;
            }

            $logo_url = variedadesexpress_home_brand_logo_url( $brand );
            ?>

            <a class="home-brand-card" href="<?php echo esc_url( $brand_link ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Ver productos de %s', 'sultana-storefront' ), $brand->name ) ); ?>">
                <span class="home-brand-card__media js-image-skeleton">
                    <?php if ( $logo_url ) : ?>
                        <img
                            src="<?php echo esc_url( $logo_url ); ?>"
                            alt="<?php echo esc_attr( $brand->name ); ?>"
                            loading="lazy"
                            decoding="async"
                        >
                    <?php else : ?>
                        <span class="home-brand-card__fallback">
                            <?php echo esc_html( $brand->name ); ?>
                        </span>
                    <?php endif; ?>
                </span>
            </a>
        <?php endforeach; ?>
        </div>

        <button class="home-brands__arrow home-brands__arrow--next" type="button" aria-label="<?php esc_attr_e( 'Ver mas marcas', 'sultana-storefront' ); ?>">
            <img src="<?php echo esc_url( get_template_directory_uri() . '/assets/icons/chevron-right.svg' ); ?>" alt="" width="22" height="22" aria-hidden="true">
        </button>
    </div>
</section>

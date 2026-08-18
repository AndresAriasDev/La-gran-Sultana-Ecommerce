<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();

$is_product_search = function_exists( 'wc_get_product' ) && 'product' === get_query_var( 'post_type' );
?>

<?php if ( $is_product_search ) : ?>
    <div class="shop-page shop-page--search">
        <header class="shop-page__search-header">
            <h1 class="shop-page__search-title">
                <?php
                printf(
                    esc_html__( 'Resultado de busqueda de: %s', 'sultana-storefront' ),
                    '<span>' . esc_html( get_search_query() ) . '</span>'
                );
                ?>
            </h1>
        </header>

        <?php if ( have_posts() ) : ?>
            <?php ob_start(); ?>
            <?php the_posts_pagination(); ?>
            <?php $search_pagination = trim( ob_get_clean() ); ?>

            <?php if ( $search_pagination ) : ?>
                <nav class="shop-page__pagination shop-page__pagination--top" aria-label="<?php esc_attr_e( 'Paginacion de productos', 'sultana-storefront' ); ?>">
                    <?php echo wp_kses_post( $search_pagination ); ?>
                </nav>
            <?php endif; ?>

            <section class="shop-page__products" aria-label="<?php esc_attr_e( 'Resultados de productos', 'sultana-storefront' ); ?>">
                <?php while ( have_posts() ) : ?>
                    <?php the_post(); ?>

                    <?php
                    $product = wc_get_product( get_the_ID() );

                    if ( $product instanceof WC_Product && $product->is_visible() && function_exists( 'variedadesexpress_home_for_you_card' ) ) {
                        variedadesexpress_home_for_you_card( $product );
                    }
                    ?>
                <?php endwhile; ?>
            </section>

            <?php if ( $search_pagination ) : ?>
                <nav class="shop-page__pagination shop-page__pagination--bottom" aria-label="<?php esc_attr_e( 'Paginacion de productos', 'sultana-storefront' ); ?>">
                    <?php echo wp_kses_post( $search_pagination ); ?>
                </nav>
            <?php endif; ?>
        <?php else : ?>
            <section class="shop-page__empty">
                <h2><?php esc_html_e( 'No encontramos productos', 'sultana-storefront' ); ?></h2>
                <p><?php esc_html_e( 'Intenta buscar con otra palabra o revisa nuestras categorias.', 'sultana-storefront' ); ?></p>
            </section>
        <?php endif; ?>
    </div>
<?php else : ?>
    <div class="search-content">

        <header class="search-header">
            <h1 class="search-header__title">
                <?php
                printf(
                    esc_html__( 'Resultados de busqueda para: %s', 'sultana-storefront' ),
                    '<span>' . esc_html( get_search_query() ) . '</span>'
                );
                ?>
            </h1>
        </header>

        <?php if ( have_posts() ) : ?>

            <div class="search-grid">
                <?php while ( have_posts() ) : ?>
                    <?php the_post(); ?>

                    <article id="post-<?php the_ID(); ?>" <?php post_class( 'search-card' ); ?>>
                        <h2 class="search-card__title">
                            <a href="<?php the_permalink(); ?>">
                                <?php the_title(); ?>
                            </a>
                        </h2>

                        <div class="search-card__excerpt">
                            <?php the_excerpt(); ?>
                        </div>
                    </article>

                <?php endwhile; ?>
            </div>

            <?php the_posts_pagination(); ?>

        <?php else : ?>

            <p><?php esc_html_e( 'No se encontraron resultados.', 'sultana-storefront' ); ?></p>

        <?php endif; ?>

    </div>
<?php endif; ?>

<?php
get_footer();

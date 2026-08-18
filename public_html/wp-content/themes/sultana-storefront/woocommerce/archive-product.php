<?php
/**
 * Product archive template.
 *
 * @package SultanaStorefront
 */

defined( 'ABSPATH' ) || exit;

get_header( 'shop' );

$show_archive_breadcrumb = function_exists( 'is_product_taxonomy' ) && is_product_taxonomy() && ! is_search();

if ( $show_archive_breadcrumb ) {
    remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20 );
}

do_action( 'woocommerce_before_main_content' );

$queried_term  = get_queried_object();
$archive_label = is_product_category()
    ? sprintf( __( 'Productos de la categoria %s', 'sultana-storefront' ), woocommerce_page_title( false ) )
    : __( 'Productos de la tienda', 'sultana-storefront' );

if ( is_product_taxonomy() && $queried_term instanceof WP_Term && ! is_product_category() ) {
    $archive_label = sprintf(
        /* translators: %s: product taxonomy term name. */
        __( 'Productos de %s', 'sultana-storefront' ),
        $queried_term->name
    );
}
$search_query  = function_exists( 'variedadesexpress_current_product_search_query' )
    ? variedadesexpress_current_product_search_query()
    : trim( get_search_query() );
?>

<div class="shop-page">
    <?php if ( woocommerce_product_loop() ) : ?>
        <?php ob_start(); ?>
        <?php woocommerce_pagination(); ?>
        <?php $shop_pagination = trim( ob_get_clean() ); ?>

        <?php if ( is_search() && '' !== $search_query ) : ?>
            <div class="shop-page__topbar">
                <header class="shop-page__search-header">
                    <h1 class="shop-page__search-title">
                        <?php
                        printf(
                            esc_html__( 'Resultados de busqueda de %s', 'sultana-storefront' ),
                            '<span>' . esc_html( $search_query ) . '</span>'
                        );
                        ?>
                    </h1>
                </header>

                <?php if ( $shop_pagination ) : ?>
                    <nav class="shop-page__pagination shop-page__pagination--top" aria-label="<?php esc_attr_e( 'Paginacion de productos', 'sultana-storefront' ); ?>">
                        <?php echo wp_kses_post( $shop_pagination ); ?>
                    </nav>
                <?php endif; ?>
            </div>
        <?php elseif ( $show_archive_breadcrumb ) : ?>
            <div class="shop-page__topbar shop-page__topbar--taxonomy">
                <div class="shop-page__breadcrumb">
                    <?php woocommerce_breadcrumb(); ?>
                </div>

                <?php if ( $shop_pagination ) : ?>
                    <nav class="shop-page__pagination shop-page__pagination--top" aria-label="<?php esc_attr_e( 'Paginacion de productos', 'sultana-storefront' ); ?>">
                        <?php echo wp_kses_post( $shop_pagination ); ?>
                    </nav>
                <?php endif; ?>
            </div>
        <?php elseif ( $shop_pagination ) : ?>
            <nav class="shop-page__pagination shop-page__pagination--top" aria-label="<?php esc_attr_e( 'Paginacion de productos', 'sultana-storefront' ); ?>">
                <?php echo wp_kses_post( $shop_pagination ); ?>
            </nav>
        <?php endif; ?>

        <section class="shop-page__products" aria-label="<?php echo esc_attr( $archive_label ); ?>">
            <?php if ( wc_get_loop_prop( 'total' ) ) : ?>
                <?php while ( have_posts() ) : ?>
                    <?php the_post(); ?>

                    <?php
                    do_action( 'woocommerce_shop_loop' );

                    $product = wc_get_product( get_the_ID() );

                    if ( $product instanceof WC_Product && function_exists( 'variedadesexpress_home_for_you_card' ) ) {
                        variedadesexpress_home_for_you_card( $product );
                    }
                    ?>
                <?php endwhile; ?>
            <?php endif; ?>
        </section>

        <?php if ( $shop_pagination ) : ?>
            <nav class="shop-page__pagination shop-page__pagination--bottom" aria-label="<?php esc_attr_e( 'Paginacion de productos', 'sultana-storefront' ); ?>">
                <?php echo wp_kses_post( $shop_pagination ); ?>
            </nav>
        <?php endif; ?>
    <?php else : ?>
        <?php if ( is_search() && '' !== $search_query ) : ?>
            <?php
            $suggested_products = function_exists( 'variedadesexpress_search_suggestion_products' )
                ? variedadesexpress_search_suggestion_products( $search_query, 15 )
                : [];
            ?>
            <section class="shop-page__empty shop-page__empty--search">
                <span class="shop-page__empty-icon" aria-hidden="true">
                    <?php variedadesexpress_icon( 'search', 'shop-page__empty-svg' ); ?>
                </span>
                <h1><?php esc_html_e( 'Ups, no hemos encontrado el producto que buscas', 'sultana-storefront' ); ?></h1>
            </section>

            <?php if ( $suggested_products && function_exists( 'variedadesexpress_home_for_you_card' ) ) : ?>
                <section class="single-product-related shop-page__suggestions" aria-labelledby="shop-page-suggestions-title">
                    <header class="single-product-related__header">
                        <span aria-hidden="true"></span>
                        <h2 id="shop-page-suggestions-title">
                            <?php esc_html_e( 'Puede que te interese', 'sultana-storefront' ); ?>
                        </h2>
                        <span aria-hidden="true"></span>
                    </header>

                    <div class="single-product-related__viewport">
                        <?php if ( count( $suggested_products ) > 5 ) : ?>
                            <button class="single-product-related__arrow single-product-related__arrow--prev" type="button" aria-label="<?php esc_attr_e( 'Ver productos anteriores', 'sultana-storefront' ); ?>" disabled>
                                <?php variedadesexpress_icon( 'chevron-left', 'single-product-related__arrow-icon' ); ?>
                            </button>
                        <?php endif; ?>

                        <div class="single-product-related__track" data-shop-products-carousel tabindex="0">
                            <?php foreach ( $suggested_products as $suggested_product ) : ?>
                                <?php variedadesexpress_home_for_you_card( $suggested_product ); ?>
                            <?php endforeach; ?>
                        </div>

                        <?php if ( count( $suggested_products ) > 5 ) : ?>
                            <button class="single-product-related__arrow single-product-related__arrow--next" type="button" aria-label="<?php esc_attr_e( 'Ver mas productos sugeridos', 'sultana-storefront' ); ?>">
                                <?php variedadesexpress_icon( 'chevron-right', 'single-product-related__arrow-icon' ); ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>
        <?php else : ?>
            <section class="shop-page__empty">
                <?php do_action( 'woocommerce_no_products_found' ); ?>
            </section>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php
do_action( 'woocommerce_after_main_content' );

get_footer( 'shop' );

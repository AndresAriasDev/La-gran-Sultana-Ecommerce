<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();

$products_per_page = 40;
$paged             = max( 1, absint( get_query_var( 'paged' ) ?: get_query_var( 'page' ) ) );
$tax_query         = [];

if ( function_exists( 'WC' ) && WC()->query ) {
    $tax_query = WC()->query->get_tax_query();
}

$query_args = [
    'post_type'           => 'product',
    'post_status'         => 'publish',
    'posts_per_page'      => $products_per_page,
    'paged'               => $paged,
    'ignore_sticky_posts' => true,
    'no_found_rows'       => false,
    'orderby'             => [
        'menu_order' => 'ASC',
        'date'       => 'DESC',
    ],
];

if ( $tax_query ) {
    $query_args['tax_query'] = $tax_query;
}

$products_query = function_exists( 'wc_get_product' ) ? new WP_Query( $query_args ) : null;
?>

<div class="shop-page">
    <header class="shop-page__header">
        <span class="shop-page__eyebrow"><?php esc_html_e( 'Tienda', 'sultana-storefront' ); ?></span>
        <h1 class="shop-page__title"><?php esc_html_e( 'Todos los productos', 'sultana-storefront' ); ?></h1>
    </header>

    <?php if ( $products_query instanceof WP_Query && $products_query->have_posts() ) : ?>
        <section class="shop-page__products" aria-label="<?php esc_attr_e( 'Productos de la tienda', 'sultana-storefront' ); ?>">
            <?php
            while ( $products_query->have_posts() ) :
                $products_query->the_post();

                $product = wc_get_product( get_the_ID() );

                if ( ! $product instanceof WC_Product || ! $product->is_visible() ) {
                    continue;
                }

                variedadesexpress_home_for_you_card( $product );
            endwhile;
            ?>
        </section>

        <?php
        $pagination = paginate_links(
            [
                'total'     => max( 1, (int) $products_query->max_num_pages ),
                'current'   => $paged,
                'mid_size'  => 1,
                'end_size'  => 1,
                'prev_text' => __( 'Anterior', 'sultana-storefront' ),
                'next_text' => __( 'Siguiente', 'sultana-storefront' ),
                'type'      => 'list',
            ]
        );
        ?>

        <?php if ( $pagination ) : ?>
            <nav class="shop-page__pagination" aria-label="<?php esc_attr_e( 'Paginacion de productos', 'sultana-storefront' ); ?>">
                <?php echo wp_kses_post( $pagination ); ?>
            </nav>
        <?php endif; ?>
    <?php else : ?>
        <section class="shop-page__empty">
            <h2><?php esc_html_e( 'No hay productos disponibles', 'sultana-storefront' ); ?></h2>
            <p><?php esc_html_e( 'Vuelve pronto para descubrir nuevos productos.', 'sultana-storefront' ); ?></p>
        </section>
    <?php endif; ?>
</div>

<?php
wp_reset_postdata();
get_footer();

<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'SULTANA_STOREFRONT_HOME_FOR_YOU_BATCH_SIZE' ) ) {
    define(
        'SULTANA_STOREFRONT_HOME_FOR_YOU_BATCH_SIZE',
        defined( 'VARIEDADESEXPRESS_HOME_FOR_YOU_BATCH_SIZE' ) ? VARIEDADESEXPRESS_HOME_FOR_YOU_BATCH_SIZE : 30
    );
}

if ( ! defined( 'VARIEDADESEXPRESS_HOME_FOR_YOU_BATCH_SIZE' ) ) {
    define( 'VARIEDADESEXPRESS_HOME_FOR_YOU_BATCH_SIZE', SULTANA_STOREFRONT_HOME_FOR_YOU_BATCH_SIZE );
}

function variedadesexpress_home_recently_viewed_product_ids(): array
{
    if ( empty( $_COOKIE['woocommerce_recently_viewed'] ) ) {
        return [];
    }

    return array_values(
        array_filter(
            array_map( 'absint', explode( '|', wp_unslash( $_COOKIE['woocommerce_recently_viewed'] ) ) )
        )
    );
}

function variedadesexpress_home_cart_product_ids(): array
{
    if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
        return [];
    }

    $product_ids = [];

    foreach ( WC()->cart->get_cart() as $cart_item ) {
        if ( ! empty( $cart_item['product_id'] ) ) {
            $product_ids[] = (int) $cart_item['product_id'];
        }
    }

    return array_values( array_unique( $product_ids ) );
}

function variedadesexpress_home_interest_terms( array $product_ids, string $taxonomy ): array
{
    $term_ids = [];

    foreach ( $product_ids as $product_id ) {
        $terms = wp_get_post_terms( $product_id, $taxonomy, [ 'fields' => 'ids' ] );

        if ( ! is_wp_error( $terms ) ) {
            $term_ids = array_merge( $term_ids, $terms );
        }
    }

    return array_values( array_unique( array_map( 'absint', $term_ids ) ) );
}

function variedadesexpress_home_recommendation_query_args( int $limit, int $offset, array $exclude_ids = [] ): array
{
    $signal_product_ids = array_values(
        array_unique(
            array_merge(
                variedadesexpress_home_recently_viewed_product_ids(),
                variedadesexpress_home_cart_product_ids()
            )
        )
    );

    $category_ids = variedadesexpress_home_interest_terms( $signal_product_ids, 'product_cat' );
    $tag_ids      = variedadesexpress_home_interest_terms( $signal_product_ids, 'product_tag' );
    $tax_query    = [];

    if ( $category_ids ) {
        $tax_query[] = [
            'taxonomy' => 'product_cat',
            'field'    => 'term_id',
            'terms'    => $category_ids,
        ];
    }

    if ( $tag_ids ) {
        $tax_query[] = [
            'taxonomy' => 'product_tag',
            'field'    => 'term_id',
            'terms'    => $tag_ids,
        ];
    }

    if ( count( $tax_query ) > 1 ) {
        $tax_query['relation'] = 'OR';
    }

    $args = [
        'post_type'           => 'product',
        'post_status'         => 'publish',
        'posts_per_page'      => $limit,
        'offset'              => $offset,
        'no_found_rows'       => false,
        'ignore_sticky_posts' => true,
        'post__not_in'        => array_values( array_unique( array_merge( $exclude_ids, $signal_product_ids ) ) ),
        'meta_key'            => 'total_sales',
        'orderby'             => [
            'meta_value_num' => 'DESC',
            'date'           => 'DESC',
        ],
    ];

    if ( $tax_query ) {
        $args['tax_query'] = $tax_query;
    }

    return $args;
}

function variedadesexpress_home_for_you_products( int $limit = SULTANA_STOREFRONT_HOME_FOR_YOU_BATCH_SIZE, int $offset = 0 ): array
{
    if ( ! class_exists( 'WooCommerce' ) ) {
        return [
            'products' => [],
            'has_more' => false,
        ];
    }

    $query_args = variedadesexpress_home_recommendation_query_args( $limit + 1, $offset );
    $query      = new WP_Query( $query_args );
    $products   = [];

    foreach ( $query->posts as $post ) {
        $product = wc_get_product( $post->ID );

        if ( $product instanceof WC_Product && $product->is_visible() ) {
            $products[] = $product;
        }
    }

    wp_reset_postdata();

    if ( ! empty( $query_args['tax_query'] ) && count( $products ) < $limit + 1 ) {
        $exclude_ids = array_map(
            static fn ( WC_Product $product ): int => $product->get_id(),
            $products
        );

        $fallback_offset = max( 0, $offset - (int) $query->found_posts );

        $fallback_query = new WP_Query(
            [
                'post_type'           => 'product',
                'post_status'         => 'publish',
                'posts_per_page'      => ( $limit + 1 ) - count( $products ),
                'offset'              => $fallback_offset,
                'no_found_rows'       => false,
                'ignore_sticky_posts' => true,
                'post__not_in'        => $exclude_ids,
                'meta_key'            => 'total_sales',
                'orderby'             => [
                    'meta_value_num' => 'DESC',
                    'date'           => 'DESC',
                ],
            ]
        );

        foreach ( $fallback_query->posts as $post ) {
            $product = wc_get_product( $post->ID );

            if ( $product instanceof WC_Product && $product->is_visible() ) {
                $products[] = $product;
            }
        }

        wp_reset_postdata();
    }

    $has_more = count( $products ) > $limit;
    $products = array_slice( $products, 0, $limit );

    return [
        'products' => $products,
        'has_more' => $has_more,
    ];
}

function variedadesexpress_home_for_you_card( WC_Product $product ): void
{
    $product_id    = $product->get_id();
    $sale_data     = function_exists( 'variedadesexpress_get_product_sale_display_data' )
        ? variedadesexpress_get_product_sale_display_data( $product )
        : [
            'is_on_sale' => false,
            'regular'    => '',
            'sale'       => '',
            'current'    => $product->get_price(),
            'image_id'   => 0,
        ];
    $image_id      = ! empty( $sale_data['image_id'] ) ? absint( $sale_data['image_id'] ) : $product->get_image_id();
    $image_attrs   = [
        'loading'  => 'lazy',
        'decoding' => 'async',
        'sizes'    => '(max-width: 640px) calc((100vw - 24px - 0.75rem) / 2), (max-width: 900px) calc((100vw - 32px - 1.6rem) / 3), min(20vw, 228px)',
    ];
    $image_html    = $image_id ? wp_get_attachment_image( $image_id, 'woocommerce_thumbnail', false, $image_attrs ) : '';
    $image_html    = $image_html ?: wc_placeholder_img( 'woocommerce_thumbnail', $image_attrs );
    $regular_price = (string) ( $sale_data['regular'] ?? '' );
    $sale_price    = (string) ( $sale_data['sale'] ?? '' );
    $current_price = (string) ( $sale_data['current'] ?? '' );
    $has_discount  = ! empty( $sale_data['is_on_sale'] ) && '' !== $regular_price && '' !== $sale_price;
    ?>
    <article class="for-you-product-card">
        <a class="for-you-product-card__link" href="<?php echo esc_url( get_permalink( $product_id ) ); ?>">
            <span class="for-you-product-card__media js-image-skeleton">
                <?php variedadesexpress_product_discount_badge( $product, 'for-you-product-card__discount-badge' ); ?>
                <?php echo $image_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </span>
            <h3 class="for-you-product-card__title">
                <?php echo esc_html( $product->get_name() ); ?>
            </h3>
        </a>

        <div class="for-you-product-card__footer">
            <div class="for-you-product-card__prices <?php echo esc_attr( $has_discount ? 'has-discount' : 'has-single-price' ); ?>">
                <?php if ( $has_discount ) : ?>
                    <span class="for-you-product-card__regular-price">
                        <?php echo wp_kses_post( wc_price( (float) $regular_price ) ); ?>
                    </span>
                    <span class="for-you-product-card__price">
                        <?php echo wp_kses_post( wc_price( (float) $sale_price ) ); ?>
                    </span>
                <?php elseif ( '' !== $current_price ) : ?>
                    <span class="for-you-product-card__price">
                        <?php echo wp_kses_post( wc_price( (float) $current_price ) ); ?>
                    </span>
                <?php endif; ?>
            </div>

            <?php if ( $product->is_purchasable() && $product->is_in_stock() ) : ?>
                <a
                    class="for-you-product-card__cart add_to_cart_button ajax_add_to_cart product_type_<?php echo esc_attr( $product->get_type() ); ?>"
                    href="<?php echo esc_url( $product->add_to_cart_url() ); ?>"
                    data-quantity="1"
                    data-product_id="<?php echo esc_attr( $product_id ); ?>"
                    data-product_sku="<?php echo esc_attr( $product->get_sku() ); ?>"
                    aria-label="<?php echo esc_attr( sprintf( __( 'Agregar %s al carrito', 'sultana-storefront' ), $product->get_name() ) ); ?>"
                    rel="nofollow"
                >
                    <img src="<?php echo esc_url( get_template_directory_uri() . '/assets/icons/shopping-cart.svg' ); ?>" alt="" width="18" height="18" aria-hidden="true">
                </a>
            <?php endif; ?>
        </div>
    </article>
    <?php
}

function variedadesexpress_home_for_you_ajax(): void
{
    check_ajax_referer( 'variedadesexpress_for_you', 'nonce' );

    $offset = isset( $_POST['offset'] ) ? max( 0, absint( $_POST['offset'] ) ) : 0;
    $data   = variedadesexpress_home_for_you_products( SULTANA_STOREFRONT_HOME_FOR_YOU_BATCH_SIZE, $offset );

    ob_start();

    foreach ( $data['products'] as $product ) {
        variedadesexpress_home_for_you_card( $product );
    }

    wp_send_json_success(
        [
            'html'     => ob_get_clean(),
            'count'    => count( $data['products'] ),
            'has_more' => $data['has_more'],
        ]
    );
}

add_action( 'wp_ajax_variedadesexpress_load_for_you', 'variedadesexpress_home_for_you_ajax' );
add_action( 'wp_ajax_nopriv_variedadesexpress_load_for_you', 'variedadesexpress_home_for_you_ajax' );

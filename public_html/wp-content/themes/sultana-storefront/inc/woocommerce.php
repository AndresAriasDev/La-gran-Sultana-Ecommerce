<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function variedadesexpress_woocommerce_setup(): void
{
    add_theme_support( 'woocommerce' );

    add_theme_support(
        'woocommerce',
        [
            'thumbnail_image_width' => 480,
            'single_image_width'    => 900,
            'product_grid'          => [
                'default_rows'    => 3,
                'min_rows'        => 1,
                'max_rows'        => 8,
                'default_columns' => 4,
                'min_columns'     => 2,
                'max_columns'     => 4,
            ],
        ]
    );
}

add_action( 'after_setup_theme', 'variedadesexpress_woocommerce_setup' );

function variedadesexpress_woocommerce_discount_badge(): void
{
    global $product;

    variedadesexpress_product_discount_badge( $product, 'product-card__discount-badge' );
}

add_action( 'woocommerce_before_shop_loop_item_title', 'variedadesexpress_woocommerce_discount_badge', 9 );

function variedadesexpress_shop_products_per_page( int $per_page ): int
{
    if ( ! is_admin() && function_exists( 'is_shop' ) && is_shop() ) {
        return 40;
    }

    return $per_page;
}

add_filter( 'loop_shop_per_page', 'variedadesexpress_shop_products_per_page', 20 );

function variedadesexpress_shop_on_sale_product_ids(): array
{
    if ( ! function_exists( 'wc_get_product_ids_on_sale' ) || ! function_exists( 'wc_get_product' ) || ! class_exists( 'WC_Product' ) ) {
        return [];
    }

    $product_ids = [];

    foreach ( array_map( 'absint', wc_get_product_ids_on_sale() ) as $sale_id ) {
        if ( ! $sale_id ) {
            continue;
        }

        $sale_product = wc_get_product( $sale_id );

        if ( ! $sale_product instanceof WC_Product ) {
            continue;
        }

        $parent_id = method_exists( $sale_product, 'is_type' ) && $sale_product->is_type( 'variation' ) && method_exists( $sale_product, 'get_parent_id' )
            ? absint( $sale_product->get_parent_id() )
            : absint( $sale_product->get_id() );

        if ( $parent_id ) {
            $product_ids[ $parent_id ] = $parent_id;
        }
    }

    return array_values( $product_ids );
}

function variedadesexpress_apply_shop_on_sale_filter( WP_Query $query ): void
{
    if ( is_admin() || ! function_exists( 'is_shop' ) || ! is_shop() ) {
        return;
    }

    $on_sale = isset( $_GET['on_sale'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['on_sale'] ) );

    if ( ! $on_sale ) {
        return;
    }

    $sale_product_ids = variedadesexpress_shop_on_sale_product_ids();
    $sale_product_ids = ! empty( $sale_product_ids ) ? $sale_product_ids : [ 0 ];

    $existing_post__in = $query->get( 'post__in' );

    if ( is_array( $existing_post__in ) && ! empty( $existing_post__in ) ) {
        $sale_product_ids = array_values( array_intersect( array_map( 'absint', $existing_post__in ), $sale_product_ids ) );
        $sale_product_ids = ! empty( $sale_product_ids ) ? $sale_product_ids : [ 0 ];
    }

    $query->set( 'post__in', $sale_product_ids );
}

add_action( 'woocommerce_product_query', 'variedadesexpress_apply_shop_on_sale_filter', 20 );

function variedadesexpress_product_brand_taxonomies(): array
{
    $taxonomies = [];

    foreach ( [ 'product_brand', 'pa_marca', 'pa_brand', 'yith_product_brand' ] as $taxonomy ) {
        if ( taxonomy_exists( $taxonomy ) ) {
            $taxonomies[] = $taxonomy;
        }
    }

    return $taxonomies;
}

function variedadesexpress_normalize_product_search_text( string $text ): string
{
    return sanitize_title( remove_accents( trim( wp_strip_all_tags( $text ) ) ) );
}

function variedadesexpress_current_product_search_query(): string
{
    if ( isset( $_GET['s'] ) ) {
        return trim( sanitize_text_field( wp_unslash( $_GET['s'] ) ) );
    }

    return trim( get_search_query() );
}

function variedadesexpress_brand_terms_matching_search( string $search_query ): array
{
    $normalized_query = variedadesexpress_normalize_product_search_text( $search_query );

    if ( strlen( $normalized_query ) < 2 ) {
        return [];
    }

    $matches = [];

    foreach ( variedadesexpress_product_brand_taxonomies() as $taxonomy ) {
        $terms = get_terms(
            [
                'taxonomy'   => $taxonomy,
                'hide_empty' => true,
            ]
        );

        if ( is_wp_error( $terms ) || empty( $terms ) ) {
            continue;
        }

        foreach ( $terms as $term ) {
            if ( ! $term instanceof WP_Term ) {
                continue;
            }

            $normalized_name = variedadesexpress_normalize_product_search_text( $term->name );

            if (
                $normalized_query === $normalized_name
                || str_contains( $normalized_name, $normalized_query )
                || ( strlen( $normalized_name ) >= 3 && str_contains( $normalized_query, $normalized_name ) )
            ) {
                $matches[ $taxonomy ][] = absint( $term->term_id );
            }
        }
    }

    return array_map( 'array_unique', $matches );
}

function variedadesexpress_product_ids_matching_search_fields( string $search_query, int $limit = 0 ): array
{
    global $wpdb;

    $search_query = trim( wp_strip_all_tags( $search_query ) );

    if ( '' === $search_query ) {
        return [];
    }

    $like        = '%' . $wpdb->esc_like( $search_query ) . '%';
    $limit_sql   = $limit > 0 ? ' LIMIT ' . absint( $limit ) : '';
    $product_ids = $wpdb->get_col(
        $wpdb->prepare(
            "
            SELECT DISTINCT
                CASE
                    WHEN product_posts.post_type = 'product_variation' THEN product_posts.post_parent
                    ELSE product_posts.ID
                END AS product_id
            FROM {$wpdb->posts} AS product_posts
            LEFT JOIN {$wpdb->postmeta} AS product_sku
                ON product_sku.post_id = product_posts.ID
                AND product_sku.meta_key = '_sku'
            WHERE
                (
                    product_posts.post_type = 'product'
                    AND product_posts.post_status = 'publish'
                    AND (
                        product_posts.post_title LIKE %s
                        OR product_posts.post_excerpt LIKE %s
                        OR product_sku.meta_value LIKE %s
                    )
                )
                OR (
                    product_posts.post_type = 'product_variation'
                    AND product_posts.post_status IN ( 'publish', 'private' )
                    AND product_posts.post_parent > 0
                    AND product_sku.meta_value LIKE %s
                )
            ORDER BY product_id DESC
            {$limit_sql}
            ",
            $like,
            $like,
            $like,
            $like
        )
    );

    return array_values( array_unique( array_filter( array_map( 'absint', (array) $product_ids ) ) ) );
}

function variedadesexpress_product_ids_matching_taxonomy_terms( array $term_matches ): array
{
    $product_ids = [];

    foreach ( $term_matches as $taxonomy => $term_ids ) {
        $objects = get_objects_in_term( array_map( 'absint', (array) $term_ids ), (string) $taxonomy );

        if ( is_wp_error( $objects ) || empty( $objects ) ) {
            continue;
        }

        foreach ( $objects as $object_id ) {
            $product_ids[] = absint( $object_id );
        }
    }

    return array_values( array_unique( array_filter( $product_ids ) ) );
}

function variedadesexpress_apply_product_search_to_product_query( WP_Query $query ): void
{
    if ( is_admin() || ! $query->is_main_query() || ! $query->is_search() ) {
        return;
    }

    $post_type = $query->get( 'post_type' );
    $is_product_search = 'product' === $post_type || ( is_array( $post_type ) && in_array( 'product', $post_type, true ) );

    if ( ! $is_product_search ) {
        return;
    }

    $search_query = trim( (string) $query->get( 's' ) );

    if ( '' === $search_query ) {
        return;
    }

    $matching_ids = array_merge(
        variedadesexpress_product_ids_matching_search_fields( $search_query ),
        variedadesexpress_product_ids_matching_taxonomy_terms( variedadesexpress_brand_terms_matching_search( $search_query ) )
    );
    $matching_ids = array_values( array_unique( array_filter( array_map( 'absint', $matching_ids ) ) ) );

    if ( empty( $matching_ids ) ) {
        $matching_ids = [ 0 ];
    }

    $query->set( 's', '' );
    $query->set( 'post_type', 'product' );
    $query->set( 'post__in', $matching_ids );
}

add_action( 'pre_get_posts', 'variedadesexpress_apply_product_search_to_product_query', 20 );

function variedadesexpress_search_suggestion_products( string $search_query, int $limit = 15 ): array
{
    $search_query = trim( wp_strip_all_tags( $search_query ) );

    if ( '' === $search_query || ! function_exists( 'wc_get_product' ) ) {
        return [];
    }

    $products   = [];
    $product_ids = [];
    $tax_query  = [];

    foreach ( array_merge( [ 'product_cat', 'product_tag' ], variedadesexpress_product_brand_taxonomies() ) as $taxonomy ) {
        $term_ids = get_terms(
            [
                'taxonomy'   => $taxonomy,
                'hide_empty' => true,
                'search'     => $search_query,
                'fields'     => 'ids',
            ]
        );

        if ( is_wp_error( $term_ids ) || empty( $term_ids ) ) {
            continue;
        }

        $tax_query[] = [
            'taxonomy' => $taxonomy,
            'field'    => 'term_id',
            'terms'    => array_map( 'absint', $term_ids ),
        ];
    }

    if ( count( $tax_query ) > 1 ) {
        $tax_query['relation'] = 'OR';
    }

    $collect_products = static function ( array $query_args ) use ( &$products, &$product_ids, $limit ): void {
        $query = new WP_Query(
            array_merge(
                [
                    'post_type'           => 'product',
                    'post_status'         => 'publish',
                    'posts_per_page'      => $limit,
                    'no_found_rows'       => true,
                    'ignore_sticky_posts' => true,
                ],
                $query_args
            )
        );

        foreach ( $query->posts as $post ) {
            $product = wc_get_product( $post->ID );

            if ( ! $product instanceof WC_Product || ! $product->is_visible() ) {
                continue;
            }

            $product_id = $product->get_id();

            if ( isset( $product_ids[ $product_id ] ) ) {
                continue;
            }

            $products[]                  = $product;
            $product_ids[ $product_id ] = true;

            if ( count( $products ) >= $limit ) {
                break;
            }
        }

        wp_reset_postdata();
    };

    if ( $tax_query ) {
        $collect_products(
            [
                'tax_query' => $tax_query,
            ]
        );
    }

    if ( count( $products ) < $limit ) {
        $matching_ids = variedadesexpress_product_ids_matching_search_fields( $search_query, $limit * 3 );
        $matching_ids = array_values( array_diff( $matching_ids, array_keys( $product_ids ) ) );

        if ( $matching_ids ) {
            $collect_products(
                [
                    'post__in' => $matching_ids,
                ]
            );
        }
    }

    if ( count( $products ) < $limit ) {
        $collect_products(
            [
                'post__not_in' => array_keys( $product_ids ),
                'meta_key'     => 'total_sales',
                'orderby'      => [
                    'meta_value_num' => 'DESC',
                    'date'           => 'DESC',
                ],
            ]
        );
    }

    if ( count( $products ) < $limit ) {
        $collect_products(
            [
                'post__not_in' => array_keys( $product_ids ),
                'orderby'      => 'date',
                'order'        => 'DESC',
            ]
        );
    }

    return array_slice( $products, 0, $limit );
}

function variedadesexpress_keep_only_reviews_product_tab( array $tabs ): array
{
    unset( $tabs['description'] );
    unset( $tabs['additional_information'] );

    return $tabs;
}

add_filter( 'woocommerce_product_tabs', 'variedadesexpress_keep_only_reviews_product_tab', 20 );

function variedadesexpress_add_to_cart_message_without_button( string $message, array $products, bool $show_qty ): string
{
    $product_names = [];

    foreach ( $products as $product_id => $quantity ) {
        $product = wc_get_product( $product_id );

        if ( ! $product ) {
            continue;
        }

        $product_names[] = sprintf(
            '&ldquo;%s&rdquo;',
            esc_html( $product->get_name() )
        );
    }

    if ( empty( $product_names ) ) {
        return wp_strip_all_tags( $message );
    }

    return sprintf(
        /* translators: %s: product name or product names. */
        esc_html__( '%s se ha añadido a tu carrito.', 'sultana-storefront' ),
        wp_kses_post( implode( ', ', $product_names ) )
    );
}

add_filter( 'wc_add_to_cart_message_html', 'variedadesexpress_add_to_cart_message_without_button', 10, 3 );

function variedadesexpress_single_product_add_to_cart_redirect( $redirect_url, $product )
{
    if ( is_admin() || wp_doing_ajax() || empty( $_POST['add-to-cart'] ) || ! $product instanceof WC_Product ) {
        return $redirect_url;
    }

    if ( ! empty( $_POST['scc_wishlist_gift_action'] ) ) {
        return $redirect_url;
    }

    $redirect_product_id = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();
    $redirect_url        = $redirect_product_id ? get_permalink( $redirect_product_id ) : '';

    return $redirect_url ?: false;
}

add_filter( 'woocommerce_add_to_cart_redirect', 'variedadesexpress_single_product_add_to_cart_redirect', 20, 2 );

function variedadesexpress_render_classic_cart_for_cart_block( string $block_content, array $block ): string
{
    if ( is_admin() || ( $block['blockName'] ?? '' ) !== 'woocommerce/cart' ) {
        return $block_content;
    }

    if ( ! function_exists( 'is_cart' ) || ! is_cart() ) {
        return $block_content;
    }

    return do_shortcode( '[woocommerce_cart]' );
}

add_filter( 'render_block', 'variedadesexpress_render_classic_cart_for_cart_block', 10, 2 );

function variedadesexpress_render_classic_checkout_for_checkout_block( string $block_content, array $block ): string
{
    if ( is_admin() || ( $block['blockName'] ?? '' ) !== 'woocommerce/checkout' ) {
        return $block_content;
    }

    if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_order_received_page() ) {
        return $block_content;
    }

    return do_shortcode( '[woocommerce_checkout]' );
}

add_filter( 'render_block', 'variedadesexpress_render_classic_checkout_for_checkout_block', 10, 2 );

function variedadesexpress_enable_guest_checkout_option(): string
{
    return 'yes';
}

add_filter( 'pre_option_woocommerce_enable_guest_checkout', 'variedadesexpress_enable_guest_checkout_option' );

function variedadesexpress_disable_checkout_account_creation_option(): string
{
    return 'no';
}

add_filter( 'pre_option_woocommerce_enable_signup_and_login_from_checkout', 'variedadesexpress_disable_checkout_account_creation_option' );

function variedadesexpress_remove_checkout_coupon_prompt(): void
{
    if ( function_exists( 'is_checkout' ) && is_checkout() && ! is_order_received_page() ) {
        remove_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10 );
    }
}

add_action( 'wp', 'variedadesexpress_remove_checkout_coupon_prompt' );

function variedadesexpress_get_checkout_gift_context(): array
{
    $wishlist_class = '\Sultana\CommerceCore\Modules\Wishlist\Wishlist';

    if ( ! class_exists( $wishlist_class ) || ! method_exists( $wishlist_class, 'get_cart_gift_notice_context' ) ) {
        return [];
    }

    $gift = $wishlist_class::get_cart_gift_notice_context();

    return is_array( $gift ) ? $gift : [];
}

function variedadesexpress_checkout_body_class( array $classes ): array
{
    if ( function_exists( 'is_checkout' ) && is_checkout() && ! is_order_received_page() ) {
        $gift = variedadesexpress_get_checkout_gift_context();

        if ( ! empty( $gift['owner_id'] ) ) {
            $classes[] = 've-checkout-has-gift';
        }
    }

    return $classes;
}

add_filter( 'body_class', 'variedadesexpress_checkout_body_class' );

function variedadesexpress_render_checkout_gift_notice(): void
{
    $gift = variedadesexpress_get_checkout_gift_context();

    if ( empty( $gift['owner_name'] ) ) {
        return;
    }

    ?>
    <section class="ve-checkout-gift-notice" role="status" data-checkout-gift-notice>
        <span class="ve-checkout-gift-notice__icon" aria-hidden="true"></span>
        <p>
            <?php
            echo esc_html(
                sprintf(
                    /* translators: %s: gift recipient name. */
                    __( 'Este pedido contiene un regalo para %s. La dirección de entrega se mantiene privada y será gestionada por la tienda.', 'sultana-storefront' ),
                    sanitize_text_field( (string) $gift['owner_name'] )
                )
            );
            ?>
        </p>
        <button class="ve-checkout-gift-notice__close" type="button" aria-label="<?php esc_attr_e( 'Cerrar aviso', 'sultana-storefront' ); ?>" data-checkout-gift-notice-close>
            <span aria-hidden="true">×</span>
        </button>
    </section>
    <?php
}

add_action( 'woocommerce_before_checkout_form', 'variedadesexpress_render_checkout_gift_notice', 5 );

function variedadesexpress_render_checkout_billing_gift_hint(): void
{
    $gift = variedadesexpress_get_checkout_gift_context();

    if ( empty( $gift['owner_name'] ) ) {
        return;
    }

    ?>
    <p class="ve-checkout-billing-hint">
        <?php
        esc_html_e( 'Ingresa tus propios datos para facturar esta compra.', 'sultana-storefront' );
        ?>
    </p>
    <?php
}

add_action( 'woocommerce_before_checkout_billing_form', 'variedadesexpress_render_checkout_billing_gift_hint', 5 );

function variedadesexpress_mark_cart_update_for_redirect( bool $cart_updated ): bool
{
    if ( ! $cart_updated || is_admin() || wp_doing_ajax() ) {
        return $cart_updated;
    }

    if ( empty( $_POST['update_cart'] ) || empty( $_POST['woocommerce-cart-nonce'] ) ) {
        return $cart_updated;
    }

    if ( ! function_exists( 'is_cart' ) || ! is_cart() ) {
        return $cart_updated;
    }

    $nonce = sanitize_text_field( wp_unslash( $_POST['woocommerce-cart-nonce'] ) );

    if ( ! wp_verify_nonce( $nonce, 'woocommerce-cart' ) ) {
        return $cart_updated;
    }

    $GLOBALS['variedadesexpress_cart_updated_for_redirect'] = true;

    return $cart_updated;
}

add_filter( 'woocommerce_update_cart_action_cart_updated', 'variedadesexpress_mark_cart_update_for_redirect' );

function variedadesexpress_redirect_after_cart_update(): void
{
    if ( empty( $GLOBALS['variedadesexpress_cart_updated_for_redirect'] ) ) {
        return;
    }

    if ( headers_sent() || ! function_exists( 'wc_get_cart_url' ) ) {
        return;
    }

    wp_safe_redirect( wc_get_cart_url() );
    exit;
}

add_action( 'wp_loaded', 'variedadesexpress_redirect_after_cart_update', 99 );

function variedadesexpress_load_ajax_cart(): bool
{
    if ( ! function_exists( 'WC' ) ) {
        return false;
    }

    if ( ! WC()->cart && function_exists( 'wc_load_cart' ) ) {
        wc_load_cart();
    }

    return (bool) WC()->cart;
}

function variedadesexpress_cart_notice_message( string $type = 'error' ): string
{
    if ( ! function_exists( 'wc_get_notices' ) ) {
        return '';
    }

    $notices = wc_get_notices( $type );

    foreach ( $notices as $notice ) {
        $message = is_array( $notice ) ? (string) ( $notice['notice'] ?? '' ) : (string) $notice;
        $message = trim( wp_strip_all_tags( $message ) );

        if ( '' !== $message ) {
            return $message;
        }
    }

    return '';
}

function variedadesexpress_cart_coupon_error_message( string $message ): string
{
    $formatted = preg_replace( '/«([^»]+)»/u', '($1)', $message );

    return is_string( $formatted ) ? $formatted : $message;
}

function variedadesexpress_render_cart_page_fragment(): string
{
    if ( function_exists( 'wc_clear_notices' ) ) {
        wc_clear_notices();
    }

    ob_start();
    wc_get_template( WC()->cart && WC()->cart->is_empty() ? 'cart/cart-empty.php' : 'cart/cart.php' );

    return (string) ob_get_clean();
}

function variedadesexpress_cart_ajax_payload( string $cart_item_key = '' ): array
{
    $cart_item = ( '' !== $cart_item_key && WC()->cart ) ? WC()->cart->get_cart_item( $cart_item_key ) : null;

    return [
        'fragments'  => [
            'cart_page' => variedadesexpress_render_cart_page_fragment(),
        ],
        'cart_count' => WC()->cart ? WC()->cart->get_cart_contents_count() : 0,
        'is_empty'   => WC()->cart ? WC()->cart->is_empty() : true,
        'quantity'   => is_array( $cart_item ) ? (int) ( $cart_item['quantity'] ?? 0 ) : 0,
    ];
}

function variedadesexpress_cart_update_quantity_ajax(): void
{
    $nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );

    if ( ! wp_verify_nonce( $nonce, 'woocommerce-cart' ) ) {
        wp_send_json_error(
            [
                'message' => __( 'No pudimos validar la solicitud. Actualiza la página e intenta de nuevo.', 'sultana-storefront' ),
            ],
            403
        );
    }

    if ( ! variedadesexpress_load_ajax_cart() ) {
        wp_send_json_error(
            [
                'message' => __( 'No pudimos iniciar el carrito. Intenta de nuevo.', 'sultana-storefront' ),
            ],
            503
        );
    }

    if ( function_exists( 'wc_clear_notices' ) ) {
        wc_clear_notices();
    }

    $cart_item_key = sanitize_text_field( wp_unslash( $_POST['cart_item_key'] ?? '' ) );
    $quantity      = wc_stock_amount( wp_unslash( $_POST['quantity'] ?? 0 ) );

    if ( '' === $cart_item_key || ! WC()->cart->get_cart_item( $cart_item_key ) ) {
        wp_send_json_error(
            array_merge(
                variedadesexpress_cart_ajax_payload(),
                [
                'message' => __( 'Este producto ya no está en tu carrito.', 'sultana-storefront' ),
                ]
            ),
            404
        );
    }

    if ( $quantity < 1 ) {
        wp_send_json_error(
            array_merge(
                variedadesexpress_cart_ajax_payload( $cart_item_key ),
                [
                'message' => __( 'La cantidad no es valida.', 'sultana-storefront' ),
                ]
            ),
            400
        );
    }

    $updated = WC()->cart->set_quantity( $cart_item_key, $quantity, true );

    if ( ! $updated ) {
        $message = variedadesexpress_cart_notice_message( 'error' ) ?: __( 'No pudimos actualizar la cantidad.', 'sultana-storefront' );

        wp_send_json_error(
            array_merge(
                variedadesexpress_cart_ajax_payload( $cart_item_key ),
                [
                'message' => $message,
                ]
            ),
            400
        );
    }

    WC()->cart->calculate_shipping();
    WC()->cart->calculate_totals();

    wp_send_json_success( variedadesexpress_cart_ajax_payload( $cart_item_key ) );
}

add_action( 'wp_ajax_variedadesexpress_cart_update_quantity', 'variedadesexpress_cart_update_quantity_ajax' );
add_action( 'wp_ajax_nopriv_variedadesexpress_cart_update_quantity', 'variedadesexpress_cart_update_quantity_ajax' );

function variedadesexpress_cart_remove_item_ajax(): void
{
    $nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );

    if ( ! wp_verify_nonce( $nonce, 'woocommerce-cart' ) ) {
        wp_send_json_error(
            [
                'message' => __( 'No pudimos validar la solicitud. Actualiza la página e intenta de nuevo.', 'sultana-storefront' ),
            ],
            403
        );
    }

    if ( ! variedadesexpress_load_ajax_cart() ) {
        wp_send_json_error(
            [
                'message' => __( 'No pudimos iniciar el carrito. Intenta de nuevo.', 'sultana-storefront' ),
            ],
            503
        );
    }

    if ( function_exists( 'wc_clear_notices' ) ) {
        wc_clear_notices();
    }

    $cart_item_key = sanitize_text_field( wp_unslash( $_POST['cart_item_key'] ?? '' ) );

    if ( '' === $cart_item_key || ! WC()->cart->get_cart_item( $cart_item_key ) ) {
        wp_send_json_error(
            array_merge(
                variedadesexpress_cart_ajax_payload(),
                [
                    'message' => __( 'Este producto ya no está en tu carrito.', 'sultana-storefront' ),
                ]
            ),
            404
        );
    }

    $removed = WC()->cart->remove_cart_item( $cart_item_key );

    if ( ! $removed ) {
        $message = variedadesexpress_cart_notice_message( 'error' ) ?: __( 'No pudimos eliminar este producto.', 'sultana-storefront' );

        wp_send_json_error(
            array_merge(
                variedadesexpress_cart_ajax_payload( $cart_item_key ),
                [
                    'message' => $message,
                ]
            ),
            400
        );
    }

    WC()->cart->calculate_shipping();
    WC()->cart->calculate_totals();

    wp_send_json_success( variedadesexpress_cart_ajax_payload() );
}

add_action( 'wp_ajax_variedadesexpress_cart_remove_item', 'variedadesexpress_cart_remove_item_ajax' );
add_action( 'wp_ajax_nopriv_variedadesexpress_cart_remove_item', 'variedadesexpress_cart_remove_item_ajax' );

function variedadesexpress_cart_apply_coupon_ajax(): void
{
    $nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );

    if ( ! wp_verify_nonce( $nonce, 'woocommerce-cart' ) ) {
        wp_send_json_error(
            [
                'message' => __( 'No pudimos validar la solicitud. Actualiza la página e intenta de nuevo.', 'sultana-storefront' ),
            ],
            403
        );
    }

    if ( ! variedadesexpress_load_ajax_cart() ) {
        wp_send_json_error(
            [
                'message' => __( 'No pudimos iniciar el carrito. Intenta de nuevo.', 'sultana-storefront' ),
            ],
            503
        );
    }

    if ( function_exists( 'wc_clear_notices' ) ) {
        wc_clear_notices();
    }

    if ( ! wc_coupons_enabled() ) {
        wp_send_json_error(
            array_merge(
                variedadesexpress_cart_ajax_payload(),
                [
                    'message' => __( 'Los cupones no están disponibles en este momento.', 'sultana-storefront' ),
                ]
            ),
            400
        );
    }

    $coupon_code = wc_format_coupon_code( wc_clean( (string) wp_unslash( $_POST['coupon_code'] ?? '' ) ) );

    if ( '' === $coupon_code ) {
        wp_send_json_error(
            array_merge(
                variedadesexpress_cart_ajax_payload(),
                [
                    'message' => __( 'Ingresa un código de cupón.', 'sultana-storefront' ),
                ]
            ),
            400
        );
    }

    $applied = WC()->cart->apply_coupon( $coupon_code );

    if ( ! $applied ) {
        $message = variedadesexpress_cart_notice_message( 'error' ) ?: __( 'No pudimos aplicar este cupón.', 'sultana-storefront' );
        $message = variedadesexpress_cart_coupon_error_message( $message );

        wp_send_json_error(
            array_merge(
                variedadesexpress_cart_ajax_payload(),
                [
                    'message' => $message,
                ]
            ),
            400
        );
    }

    WC()->cart->calculate_shipping();
    WC()->cart->calculate_totals();

    $message = variedadesexpress_cart_notice_message( 'success' ) ?: __( 'Cupón aplicado correctamente.', 'sultana-storefront' );

    wp_send_json_success(
        array_merge(
            variedadesexpress_cart_ajax_payload(),
            [
                'message' => $message,
            ]
        )
    );
}

add_action( 'wp_ajax_variedadesexpress_cart_apply_coupon', 'variedadesexpress_cart_apply_coupon_ajax' );
add_action( 'wp_ajax_nopriv_variedadesexpress_cart_apply_coupon', 'variedadesexpress_cart_apply_coupon_ajax' );

function variedadesexpress_cart_remove_coupon_ajax(): void
{
    $nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );

    if ( ! wp_verify_nonce( $nonce, 'woocommerce-cart' ) ) {
        wp_send_json_error(
            [
                'message' => __( 'No pudimos validar la solicitud. Actualiza la página e intenta de nuevo.', 'sultana-storefront' ),
            ],
            403
        );
    }

    if ( ! variedadesexpress_load_ajax_cart() ) {
        wp_send_json_error(
            [
                'message' => __( 'No pudimos iniciar el carrito. Intenta de nuevo.', 'sultana-storefront' ),
            ],
            503
        );
    }

    if ( function_exists( 'wc_clear_notices' ) ) {
        wc_clear_notices();
    }

    $coupon_code = wc_format_coupon_code( wc_clean( (string) wp_unslash( $_POST['coupon_code'] ?? '' ) ) );

    if ( '' === $coupon_code || ! WC()->cart->has_discount( $coupon_code ) ) {
        wp_send_json_error(
            array_merge(
                variedadesexpress_cart_ajax_payload(),
                [
                    'message' => __( 'Este cupón no está aplicado en tu carrito.', 'sultana-storefront' ),
                ]
            ),
            404
        );
    }

    $removed = WC()->cart->remove_coupon( $coupon_code );

    if ( ! $removed ) {
        $message = variedadesexpress_cart_notice_message( 'error' ) ?: __( 'No pudimos quitar este cupón.', 'sultana-storefront' );

        wp_send_json_error(
            array_merge(
                variedadesexpress_cart_ajax_payload(),
                [
                    'message' => $message,
                ]
            ),
            400
        );
    }

    WC()->cart->calculate_shipping();
    WC()->cart->calculate_totals();

    $message = variedadesexpress_cart_notice_message( 'success' ) ?: __( 'Cupón eliminado correctamente.', 'sultana-storefront' );

    wp_send_json_success(
        array_merge(
            variedadesexpress_cart_ajax_payload(),
            [
                'message' => $message,
            ]
        )
    );
}

add_action( 'wp_ajax_variedadesexpress_cart_remove_coupon', 'variedadesexpress_cart_remove_coupon_ajax' );
add_action( 'wp_ajax_nopriv_variedadesexpress_cart_remove_coupon', 'variedadesexpress_cart_remove_coupon_ajax' );

function variedadesexpress_account_menu_items( array $items ): array
{
    unset( $items['downloads'] );

    if ( isset( $items['dashboard'] ) ) {
        $items['dashboard'] = __( 'Panel', 'sultana-storefront' );
    }

    if ( isset( $items['orders'] ) ) {
        $items['orders'] = __( 'Pedidos', 'sultana-storefront' );
    }

    if ( isset( $items['wishlist'] ) ) {
        $items['wishlist'] = __( 'Lista de deseos', 'sultana-storefront' );
    }

    if ( isset( $items['edit-address'] ) ) {
        $items['edit-address'] = __( 'Dirección', 'sultana-storefront' );
    }

    if ( isset( $items['edit-account'] ) ) {
        $items['edit-account'] = __( 'Detalles de cuenta', 'sultana-storefront' );
    }

    if ( isset( $items['customer-logout'] ) ) {
        $items['customer-logout'] = __( 'Salir', 'sultana-storefront' );
    }

    return $items;
}

add_filter( 'woocommerce_account_menu_items', 'variedadesexpress_account_menu_items', 20 );

function variedadesexpress_shipping_repository()
{
    $repository_class = '\Sultana\CommerceCore\Modules\Shipping\Repositories\ShippingSettingsRepository';

    if ( ! class_exists( $repository_class ) ) {
        return null;
    }

    $repository = new $repository_class();
    $repository->ensure_defaults();

    return $repository;
}

function variedadesexpress_nicaragua_department_options(): array
{
    $repository = variedadesexpress_shipping_repository();
    $options    = [ '' => __( 'Departamento *', 'sultana-storefront' ) ];

    if ( ! $repository ) {
        return $options;
    }

    foreach ( $repository->nicaragua_departments() as $key => $department ) {
        $options[ $key ] = sanitize_text_field( $department['label'] ?? $key );
    }

    return $options;
}

function variedadesexpress_register_nicaragua_states( array $states ): array
{
    $repository = variedadesexpress_shipping_repository();

    if ( ! $repository ) {
        return $states;
    }

    $states['NI'] = [];

    foreach ( $repository->nicaragua_departments() as $key => $department ) {
        $states['NI'][ $key ] = sanitize_text_field( $department['label'] ?? $key );
    }

    return $states;
}

add_filter( 'woocommerce_states', 'variedadesexpress_register_nicaragua_states', 20 );

function variedadesexpress_nicaragua_municipality_options( string $department = '' ): array
{
    $repository = variedadesexpress_shipping_repository();
    $options    = [ '' => __( 'Municipio *', 'sultana-storefront' ) ];

    if ( ! $repository ) {
        return $options;
    }

    $departments = $repository->nicaragua_departments();
    $selected    = $department && isset( $departments[ $department ] )
        ? [ $department => $departments[ $department ] ]
        : $departments;

    foreach ( $selected as $department_data ) {
        foreach ( $department_data['municipalities'] ?? [] as $municipality ) {
            $key             = $repository::normalize_location_key( (string) $municipality );
            $options[ $key ] = sanitize_text_field( (string) $municipality );
        }
    }

    return $options;
}

function variedadesexpress_nicaragua_locations_payload(): array
{
    $repository = variedadesexpress_shipping_repository();
    $payload    = [];

    if ( ! $repository ) {
        return $payload;
    }

    foreach ( $repository->nicaragua_departments() as $department_key => $department ) {
        $payload[ $department_key ] = [];

        foreach ( $department['municipalities'] ?? [] as $municipality ) {
            $key = $repository::normalize_location_key( (string) $municipality );
            $payload[ $department_key ][ $key ] = sanitize_text_field( (string) $municipality );
        }
    }

    return $payload;
}

function variedadesexpress_nicaragua_department_for_municipality( string $municipality ): string
{
    $repository = variedadesexpress_shipping_repository();

    if ( ! $repository || '' === $municipality ) {
        return '';
    }

    $municipality_key = $repository::normalize_location_key( $municipality );

    foreach ( $repository->nicaragua_departments() as $department_key => $department ) {
        foreach ( $department['municipalities'] ?? [] as $department_municipality ) {
            if ( $municipality_key === $repository::normalize_location_key( (string) $department_municipality ) ) {
                return (string) $department_key;
            }
        }
    }

    return '';
}

function variedadesexpress_nicaragua_address_fields( array $fields ): array
{
    unset( $fields['company'] );
    unset( $fields['address_2'] );

    if ( isset( $fields['first_name'] ) ) {
        $fields['first_name']['label']       = __( 'Nombres', 'sultana-storefront' );
        $fields['first_name']['label_class'] = [ 'screen-reader-text' ];
        $fields['first_name']['placeholder'] = __( 'Nombres *', 'sultana-storefront' );
        $fields['first_name']['priority']    = 10;
        $fields['first_name']['class']       = [ 'form-row-first' ];
    }

    if ( isset( $fields['last_name'] ) ) {
        $fields['last_name']['label']       = __( 'Apellidos', 'sultana-storefront' );
        $fields['last_name']['label_class'] = [ 'screen-reader-text' ];
        $fields['last_name']['placeholder'] = __( 'Apellidos *', 'sultana-storefront' );
        $fields['last_name']['priority']    = 20;
        $fields['last_name']['class']       = [ 'form-row-last' ];
    }

    if ( isset( $fields['country'] ) ) {
        $fields['country']['type']     = 'hidden';
        $fields['country']['required'] = false;
        $fields['country']['default']  = 'NI';
        $fields['country']['priority'] = 30;
        $fields['country']['class']    = [ 've-address-field-hidden' ];
    }

    if ( isset( $fields['address_1'] ) ) {
        $fields['address_1']['label']       = __( 'Dirección de su casa u oficina', 'sultana-storefront' );
        $fields['address_1']['label_class'] = [ 'screen-reader-text' ];
        $fields['address_1']['placeholder'] = __( 'DirecciÃ³n de su casa u oficina *', 'sultana-storefront' );
        $fields['address_1']['priority']    = 40;
        $fields['address_1']['class']       = [ 'form-row-wide' ];
    }

    if ( isset( $fields['state'] ) ) {
        $fields['state']['label']       = __( 'Departamento', 'sultana-storefront' );
        $fields['state']['label_class'] = [ 'screen-reader-text' ];
        $fields['state']['type']        = 'select';
        $fields['state']['options']     = variedadesexpress_nicaragua_department_options();
        $fields['state']['required']    = true;
        $fields['state']['priority']    = 60;
        $fields['state']['class']       = [ 'form-row-first' ];
        $fields['state']['input_class'] = [ 've-address-state' ];
    }

    if ( isset( $fields['city'] ) ) {
        $fields['city']['label']       = __( 'Municipio', 'sultana-storefront' );
        $fields['city']['label_class'] = [ 'screen-reader-text' ];
        $fields['city']['type']        = 'select';
        $fields['city']['options']     = variedadesexpress_nicaragua_municipality_options();
        $fields['city']['required']    = true;
        $fields['city']['priority']    = 70;
        $fields['city']['class']       = [ 'form-row-last' ];
        $fields['city']['input_class'] = [ 've-address-city' ];
    }

    if ( isset( $fields['postcode'] ) ) {
        $fields['postcode']['required'] = false;
        $fields['postcode']['priority'] = 80;
        $fields['postcode']['class']    = [ 've-address-field-hidden' ];
    }

    return $fields;
}

add_filter( 'woocommerce_default_address_fields', 'variedadesexpress_nicaragua_address_fields', 20 );

function variedadesexpress_nicaragua_billing_fields( array $fields ): array
{
    unset( $fields['billing_company'] );
    unset( $fields['billing_address_2'] );

    if ( isset( $fields['billing_country'] ) ) {
        $fields['billing_country']['type']     = 'hidden';
        $fields['billing_country']['required'] = false;
        $fields['billing_country']['default']  = 'NI';
        $fields['billing_country']['priority'] = 30;
        $fields['billing_country']['class']    = [ 've-address-field-hidden' ];
    }

    if ( isset( $fields['billing_postcode'] ) ) {
        $fields['billing_postcode']['required'] = false;
        $fields['billing_postcode']['class']    = [ 've-address-field-hidden' ];
    }

    if ( isset( $fields['billing_address_1'] ) ) {
        $fields['billing_address_1']['label']       = __( 'Dirección de su casa u oficina', 'sultana-storefront' );
        $fields['billing_address_1']['label_class'] = [ 'screen-reader-text' ];
        $fields['billing_address_1']['placeholder'] = __( 'DirecciÃ³n de su casa u oficina *', 'sultana-storefront' );
        $fields['billing_address_1']['class']       = [ 'form-row-wide' ];
    }

    if ( isset( $fields['billing_phone'] ) ) {
        $fields['billing_phone']['label']    = __( 'Teléfono', 'sultana-storefront' );
        $fields['billing_phone']['label_class'] = [ 'screen-reader-text' ];
        $fields['billing_phone']['placeholder'] = __( 'TelÃ©fono *', 'sultana-storefront' );
        $fields['billing_phone']['required']    = true;
        $fields['billing_phone']['priority']    = 90;
        $fields['billing_phone']['class']       = [ 'form-row-first' ];
    }

    if ( isset( $fields['billing_state'] ) ) {
        $fields['billing_state']['label']       = __( 'Departamento', 'sultana-storefront' );
        $fields['billing_state']['label_class'] = [ 'screen-reader-text' ];
        $fields['billing_state']['type']        = 'select';
        $fields['billing_state']['options']     = variedadesexpress_nicaragua_department_options();
        $fields['billing_state']['required']    = true;
        $fields['billing_state']['priority']    = 60;
        $fields['billing_state']['class']       = [ 'form-row-first' ];
        $fields['billing_state']['input_class'] = [ 've-address-state' ];
    }

    if ( isset( $fields['billing_city'] ) ) {
        $fields['billing_city']['label']       = __( 'Municipio', 'sultana-storefront' );
        $fields['billing_city']['label_class'] = [ 'screen-reader-text' ];
        $fields['billing_city']['type']        = 'select';
        $fields['billing_city']['options']     = variedadesexpress_nicaragua_municipality_options();
        $fields['billing_city']['required']    = true;
        $fields['billing_city']['priority']    = 70;
        $fields['billing_city']['class']       = [ 'form-row-last' ];
        $fields['billing_city']['input_class'] = [ 've-address-city' ];
    }

    if ( isset( $fields['billing_email'] ) ) {
        $fields['billing_email']['label_class'] = [ 'screen-reader-text' ];
        $fields['billing_email']['placeholder'] = __( 'DirecciÃ³n de correo electrÃ³nico *', 'sultana-storefront' );
        $fields['billing_email']['priority']    = 100;
        $fields['billing_email']['class']       = [ 'form-row-last' ];

        if ( is_user_logged_in() ) {
            $fields['billing_email']['custom_attributes']['readonly'] = 'readonly';
            $fields['billing_email']['input_class'] = $fields['billing_email']['input_class'] ?? [];
            $fields['billing_email']['input_class'][] = 've-field-readonly';
        }
    }

    return $fields;
}

add_filter( 'woocommerce_billing_fields', 'variedadesexpress_nicaragua_billing_fields', 20 );

function variedadesexpress_unified_checkout_fields( array $fields ): array
{
    foreach ( [ 'billing', 'shipping' ] as $group ) {
        unset(
            $fields[ $group ][ $group . '_company' ],
            $fields[ $group ][ $group . '_postcode' ]
        );

        if ( isset( $fields[ $group ][ $group . '_country' ] ) ) {
            $fields[ $group ][ $group . '_country' ]['type']     = 'hidden';
            $fields[ $group ][ $group . '_country' ]['required'] = false;
            $fields[ $group ][ $group . '_country' ]['default']  = 'NI';
            $fields[ $group ][ $group . '_country' ]['class']    = [ 've-address-field-hidden' ];
        }
    }

    $fields['shipping'] = [];
    unset( $fields['order']['order_comments'] );

    return $fields;
}

add_filter( 'woocommerce_checkout_fields', 'variedadesexpress_unified_checkout_fields', 30 );

add_filter( 'woocommerce_ship_to_different_address_checked', '__return_false' );
add_filter( 'woocommerce_cart_needs_shipping_address', '__return_false' );
add_filter( 'woocommerce_enable_order_notes_field', '__return_false' );
add_filter( 'woocommerce_checkout_privacy_policy_text', '__return_empty_string' );

function variedadesexpress_normalize_address_location_value( string $key, string $value ): string
{
    if ( '' === $value || ! preg_match( '/_(state|city)$/', $key ) ) {
        return $value;
    }

    $repository = variedadesexpress_shipping_repository();

    if ( ! $repository ) {
        return $value;
    }

    $normalized = $repository::normalize_location_key( $value );

    if ( '_state' === substr( $key, -6 ) ) {
        return isset( $repository->nicaragua_departments()[ $normalized ] ) ? $normalized : $value;
    }

    return array_key_exists( $normalized, variedadesexpress_nicaragua_municipality_options() ) ? $normalized : $value;
}

function variedadesexpress_normalize_checkout_location_value( $value, string $input )
{
    if ( ! is_string( $value ) ) {
        return $value;
    }

    if ( is_user_logged_in() && in_array( $input, [ 'billing_state', 'billing_city', 'shipping_state', 'shipping_city' ], true ) ) {
        $meta_value = get_user_meta( get_current_user_id(), $input, true );

        if ( '' === (string) $meta_value && 0 === strpos( $input, 'shipping_' ) ) {
            $meta_value = get_user_meta( get_current_user_id(), 'billing_' . substr( $input, 9 ), true );
        }

        if ( '' === (string) $meta_value && 0 === strpos( $input, 'billing_' ) ) {
            $meta_value = get_user_meta( get_current_user_id(), 'shipping_' . substr( $input, 8 ), true );
        }

        if ( '' === (string) $meta_value && function_exists( 'WC' ) && WC()->customer ) {
            $getter = 'get_' . $input;

            if ( is_callable( [ WC()->customer, $getter ] ) ) {
                $meta_value = WC()->customer->{$getter}();
            }
        }

        if ( '' === (string) $meta_value && in_array( $input, [ 'billing_state', 'shipping_state' ], true ) ) {
            $city_prefix = 0 === strpos( $input, 'shipping_' ) ? 'shipping_' : 'billing_';
            $city_value  = get_user_meta( get_current_user_id(), $city_prefix . 'city', true );

            if ( '' === (string) $city_value ) {
                $fallback_prefix = 'billing_' === $city_prefix ? 'shipping_' : 'billing_';
                $city_value      = get_user_meta( get_current_user_id(), $fallback_prefix . 'city', true );
            }

            $meta_value = variedadesexpress_nicaragua_department_for_municipality( (string) $city_value );
        }

        if ( '' !== (string) $meta_value && ( '' === $value || ! wp_doing_ajax() ) ) {
            $value = (string) $meta_value;
        }
    }

    return variedadesexpress_normalize_address_location_value( $input, $value );
}

add_filter( 'woocommerce_checkout_get_value', 'variedadesexpress_normalize_checkout_location_value', 20, 2 );

function variedadesexpress_force_single_checkout_address( array $data ): array
{
    if ( is_user_logged_in() ) {
        $current_user = wp_get_current_user();

        if ( $current_user && $current_user->exists() ) {
            $data['billing_email'] = $current_user->user_email;
        }
    }

    $data['billing_state'] = variedadesexpress_normalize_address_location_value( 'billing_state', (string) ( $data['billing_state'] ?? '' ) );
    $data['billing_city']  = variedadesexpress_normalize_address_location_value( 'billing_city', (string) ( $data['billing_city'] ?? '' ) );

    if ( '' === (string) $data['billing_state'] && '' !== (string) $data['billing_city'] ) {
        $data['billing_state'] = variedadesexpress_nicaragua_department_for_municipality( (string) $data['billing_city'] );
    }

    $data['billing_phone'] = variedadesexpress_normalize_nicaragua_phone( sanitize_text_field( (string) ( $data['billing_phone'] ?? '' ) ) );

    $data['billing_country']   = 'NI';
    $data['billing_postcode']  = '';
    $data['billing_company']   = '';
    $data['shipping_country']  = 'NI';
    $data['shipping_postcode'] = '';
    $data['shipping_company']  = '';
    $data['billing_address_2']  = '';
    $data['shipping_address_2'] = '';

    foreach ( [ 'first_name', 'last_name', 'address_1', 'city', 'state' ] as $field ) {
        $data[ 'shipping_' . $field ] = $data[ 'billing_' . $field ] ?? '';
    }

    $data['shipping_phone'] = $data['billing_phone'];

    return $data;
}

add_filter( 'woocommerce_checkout_posted_data', 'variedadesexpress_force_single_checkout_address', 20 );

function variedadesexpress_validate_checkout_phone( array $data, WP_Error $errors ): void
{
    $phone = variedadesexpress_normalize_nicaragua_phone( sanitize_text_field( (string) ( $data['billing_phone'] ?? '' ) ) );

    if ( '' === $phone ) {
        return;
    }

    if ( ! variedadesexpress_nicaragua_phone_is_valid( $phone ) ) {
        $errors->add(
            'billing_phone_invalid',
            __( 'Telefono debe tener 8 digitos validos. Ej. 86687005.', 'sultana-storefront' )
        );

        return;
    }

    $user_id = get_current_user_id();

    if ( variedadesexpress_phone_belongs_to_other_user( $phone, $user_id ) ) {
        $errors->add(
            'billing_phone_duplicate',
            __( 'Telefono ya esta asociado a otra cuenta.', 'sultana-storefront' )
        );
    }
}

add_action( 'woocommerce_after_checkout_validation', 'variedadesexpress_validate_checkout_phone', 20, 2 );

function variedadesexpress_sync_checkout_customer_for_shipping( string $post_data ): void
{
    if ( ! function_exists( 'WC' ) || ! WC()->customer ) {
        return;
    }

    parse_str( $post_data, $data );

    $data = variedadesexpress_force_single_checkout_address( $data );

    foreach ( [ 'first_name', 'last_name', 'address_1', 'city', 'state', 'country', 'postcode' ] as $field ) {
        $value  = sanitize_text_field( $data[ 'billing_' . $field ] ?? '' );
        $setter = 'set_shipping_' . $field;

        if ( is_callable( [ WC()->customer, $setter ] ) ) {
            WC()->customer->{$setter}( $value );
        }
    }

    WC()->customer->set_billing_country( 'NI' );
    WC()->customer->set_shipping_country( 'NI' );
}

add_action( 'woocommerce_checkout_update_order_review', 'variedadesexpress_sync_checkout_customer_for_shipping', 5 );

function variedadesexpress_enqueue_address_dependency_script(): void
{
    if (
        ! ( function_exists( 'is_checkout' ) && is_checkout() && ! is_order_received_page() )
        && ! ( function_exists( 'is_account_page' ) && is_account_page() )
    ) {
        return;
    }

    $locations = wp_json_encode( variedadesexpress_nicaragua_locations_payload() );

    if ( ! $locations ) {
        return;
    }

    wp_add_inline_script(
        'sultana-storefront-account-modal',
        "window.variedadesExpressLocations={$locations};" .
        "(function(){window.sccCheckoutHasGift=window.sccCheckoutHasGift||document.body.classList.contains('ve-checkout-has-gift');const locations=window.variedadesExpressLocations||{};" .
        "const normalize=function(value){return String(value||'').trim();};" .
        "const findDepartmentByCity=function(cityValue){const selected=normalize(cityValue);if(!selected){return '';}return Object.keys(locations).find(function(departmentKey){return Object.prototype.hasOwnProperty.call(locations[departmentKey]||{},selected);})||'';};" .
        "const fillCity=function(state,city){if(!state||!city){return;}const selected=normalize(city.value);if(!state.value&&selected){const inferredDepartment=findDepartmentByCity(selected);if(inferredDepartment){state.value=inferredDepartment;}}const cities=locations[state.value]||{};city.innerHTML='';const empty=document.createElement('option');empty.value='';empty.textContent='Municipio *';city.appendChild(empty);Object.keys(cities).forEach(function(key){const option=document.createElement('option');option.value=key;option.textContent=cities[key];city.appendChild(option);});if(selected&&cities[selected]){city.value=selected;}};" .
        "const shouldRefreshCheckout=function(){return !window.sccCheckoutHasGift&&window.jQuery;};" .
        "const bind=function(prefix){const state=document.getElementById(prefix+'_state');const city=document.getElementById(prefix+'_city');if(!state||!city){return;}fillCity(state,city);state.addEventListener('change',function(){fillCity(state,city);city.value='';if(shouldRefreshCheckout()){jQuery(document.body).trigger('update_checkout');}});city.addEventListener('change',function(){if(shouldRefreshCheckout()){jQuery(document.body).trigger('update_checkout');}});};" .
        "document.addEventListener('DOMContentLoaded',function(){bind('billing');bind('shipping');});" .
        "}());"
    );
}

add_action( 'wp_enqueue_scripts', 'variedadesexpress_enqueue_address_dependency_script', 20 );

function variedadesexpress_sync_checkout_order_shipping_address( WC_Order $order ): void
{
    $order->set_billing_country( 'NI' );
    $order->set_billing_postcode( '' );
    $order->set_billing_company( '' );
    $order->set_shipping_country( 'NI' );
    $order->set_shipping_postcode( '' );
    $order->set_shipping_company( '' );
    $order->set_billing_address_2( '' );
    $order->set_shipping_first_name( $order->get_billing_first_name() );
    $order->set_shipping_last_name( $order->get_billing_last_name() );
    $order->set_shipping_address_1( $order->get_billing_address_1() );
    $order->set_shipping_address_2( '' );
    $order->set_shipping_city( $order->get_billing_city() );
    $order->set_shipping_state( $order->get_billing_state() );

    if ( is_callable( [ $order, 'set_shipping_phone' ] ) ) {
        $order->set_shipping_phone( $order->get_billing_phone() );
    }

    if ( is_callable( [ $order, 'set_shipping_email' ] ) ) {
        $order->set_shipping_email( $order->get_billing_email() );
    }
}

add_action( 'woocommerce_checkout_create_order', 'variedadesexpress_sync_checkout_order_shipping_address', 30 );

function variedadesexpress_sync_checkout_user_address( int $user_id, array $posted ): void
{
    if ( $user_id <= 0 ) {
        return;
    }

    foreach ( [ 'first_name', 'last_name', 'address_1', 'city', 'state', 'country', 'postcode', 'phone' ] as $field ) {
        $billing_key = 'billing_' . $field;
        $value       = $posted[ $billing_key ] ?? '';

        if ( 'country' === $field ) {
            $value = 'NI';
        }

        if ( 'postcode' === $field ) {
            $value = '';
        }

        if ( in_array( $field, [ 'city', 'state' ], true ) ) {
            $value = variedadesexpress_normalize_address_location_value( $billing_key, (string) $value );
        }

        update_user_meta( $user_id, $billing_key, $value );
        update_user_meta( $user_id, 'shipping_' . $field, $value );
    }

    if ( ! empty( $posted['billing_email'] ) ) {
        update_user_meta( $user_id, 'billing_email', sanitize_email( $posted['billing_email'] ) );
    }
}

add_action( 'woocommerce_checkout_update_user_meta', 'variedadesexpress_sync_checkout_user_address', 20, 2 );

function variedadesexpress_normalize_nicaragua_phone( string $phone ): string
{
    $digits = preg_replace( '/\D+/', '', $phone ) ?: '';

    if ( 11 === strlen( $digits ) && 0 === strpos( $digits, '505' ) ) {
        $digits = substr( $digits, 3 );
    }

    if ( 13 === strlen( $digits ) && 0 === strpos( $digits, '00505' ) ) {
        $digits = substr( $digits, 5 );
    }

    return $digits;
}

function variedadesexpress_nicaragua_phone_is_valid( string $phone ): bool
{
    return 1 === preg_match( '/^[2578][0-9]{7}$/', $phone );
}

function variedadesexpress_phone_belongs_to_other_user( string $phone, int $current_user_id = 0 ): bool
{
    $normalized_phone = variedadesexpress_normalize_nicaragua_phone( $phone );

    if ( '' === $normalized_phone ) {
        return false;
    }

    $users = get_users(
        [
            'fields'     => [ 'ID' ],
            'number'     => -1,
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key'     => 'billing_phone',
                    'compare' => 'EXISTS',
                ],
                [
                    'key'     => 'shipping_phone',
                    'compare' => 'EXISTS',
                ],
            ],
        ]
    );

    foreach ( $users as $user ) {
        $user_id = absint( $user->ID ?? 0 );

        if ( $user_id <= 0 || $user_id === $current_user_id ) {
            continue;
        }

        $billing_phone  = variedadesexpress_normalize_nicaragua_phone( (string) get_user_meta( $user_id, 'billing_phone', true ) );
        $shipping_phone = variedadesexpress_normalize_nicaragua_phone( (string) get_user_meta( $user_id, 'shipping_phone', true ) );

        if ( $normalized_phone === $billing_phone || $normalized_phone === $shipping_phone ) {
            return true;
        }
    }

    return false;
}

function variedadesexpress_prepare_nicaragua_address_save(): void
{
    if ( empty( $_POST['action'] ) || 'edit_address' !== $_POST['action'] ) {
        return;
    }

    $_POST['billing_country']  = 'NI';
    $_POST['shipping_country'] = 'NI';
    $_POST['billing_postcode'] = '';
    $_POST['shipping_postcode'] = '';
    $_POST['billing_company'] = '';
    $_POST['shipping_company'] = '';
    unset( $_POST['billing_address_2'], $_POST['shipping_address_2'] );
    $_POST['billing_state'] = variedadesexpress_normalize_address_location_value( 'billing_state', sanitize_text_field( wp_unslash( $_POST['billing_state'] ?? '' ) ) );
    $_POST['billing_city'] = variedadesexpress_normalize_address_location_value( 'billing_city', sanitize_text_field( wp_unslash( $_POST['billing_city'] ?? '' ) ) );
    $_POST['shipping_state'] = $_POST['billing_state'];
    $_POST['shipping_city'] = $_POST['billing_city'];
    $_POST['billing_phone'] = variedadesexpress_normalize_nicaragua_phone( sanitize_text_field( wp_unslash( $_POST['billing_phone'] ?? '' ) ) );
    $_POST['shipping_phone'] = $_POST['billing_phone'];

    $first_name = sanitize_text_field( wp_unslash( $_POST['billing_first_name'] ?? '' ) );
    $last_name  = sanitize_text_field( wp_unslash( $_POST['billing_last_name'] ?? '' ) );
    $parts      = array_values( array_filter( preg_split( '/\s+/', trim( $first_name . ' ' . $last_name ) ) ) );

    if ( count( $parts ) < 3 ) {
        return;
    }

    $_POST['billing_first_name'] = implode( ' ', array_slice( $parts, 0, 2 ) );
    $_POST['billing_last_name']  = implode( ' ', array_slice( $parts, 2 ) );
}

add_action( 'template_redirect', 'variedadesexpress_prepare_nicaragua_address_save', 1 );

function variedadesexpress_validate_account_address_phone( int $user_id, string $load_address, array $address = [], $customer = null ): void
{
    if ( 'billing' !== $load_address ) {
        return;
    }

    $phone = variedadesexpress_normalize_nicaragua_phone( sanitize_text_field( wp_unslash( $_POST['billing_phone'] ?? '' ) ) );

    if ( '' === $phone ) {
        return;
    }

    if ( ! variedadesexpress_nicaragua_phone_is_valid( $phone ) ) {
        wc_add_notice( __( 'Telefono debe tener 8 digitos validos. Ej. 86687005.', 'sultana-storefront' ), 'error' );
        return;
    }

    if ( variedadesexpress_phone_belongs_to_other_user( $phone, $user_id ) ) {
        wc_add_notice( __( 'Telefono ya esta asociado a otra cuenta.', 'sultana-storefront' ), 'error' );
    }
}

add_action( 'woocommerce_after_save_address_validation', 'variedadesexpress_validate_account_address_phone', 20, 4 );

function variedadesexpress_sync_billing_address_to_shipping( int $user_id, string $load_address ): void
{
    if ( 'billing' !== $load_address ) {
        return;
    }

    $fields = [
        'first_name',
        'last_name',
        'company',
        'address_1',
        'city',
        'state',
        'postcode',
        'country',
        'phone',
    ];

    foreach ( $fields as $field ) {
        update_user_meta( $user_id, 'shipping_' . $field, get_user_meta( $user_id, 'billing_' . $field, true ) );
    }
}

add_action( 'woocommerce_customer_save_address', 'variedadesexpress_sync_billing_address_to_shipping', 20, 2 );

function variedadesexpress_redirect_shipping_address_endpoint(): void
{
    if ( ! function_exists( 'is_wc_endpoint_url' ) || ! is_wc_endpoint_url( 'edit-address' ) ) {
        return;
    }

    if ( 'shipping' !== get_query_var( 'edit-address' ) ) {
        return;
    }

    wp_safe_redirect( wc_get_endpoint_url( 'edit-address', 'billing', wc_get_page_permalink( 'myaccount' ) ) );
    exit;
}

add_action( 'template_redirect', 'variedadesexpress_redirect_shipping_address_endpoint' );

function variedadesexpress_normalize_account_details_before_save(): void
{
    if ( is_user_logged_in() ) {
        $current_user = wp_get_current_user();

        if ( $current_user && $current_user->exists() ) {
            $_POST['account_email'] = $current_user->user_email;
        }
    }

    if ( empty( $_POST['account_first_name'] ) ) {
        return;
    }

    $first_name = sanitize_text_field( wp_unslash( $_POST['account_first_name'] ) );
    $last_name  = sanitize_text_field( wp_unslash( $_POST['account_last_name'] ?? '' ) );
    $parts      = array_values( array_filter( preg_split( '/\s+/', trim( $first_name . ' ' . $last_name ) ) ) );

    if ( count( $parts ) < 3 ) {
        return;
    }

    $normalized_first_name = implode( ' ', array_slice( $parts, 0, 2 ) );
    $normalized_last_name  = implode( ' ', array_slice( $parts, 2 ) );

    $_POST['account_first_name']   = $normalized_first_name;
    $_POST['account_last_name']    = $normalized_last_name;
    $_POST['account_display_name'] = trim( $parts[0] . ' ' . $parts[2] );
}

add_action( 'woocommerce_save_account_details_errors', 'variedadesexpress_normalize_account_details_before_save', 1, 0 );

function variedadesexpress_sync_account_names_to_addresses( int $user_id ): void
{
    $first_name = get_user_meta( $user_id, 'first_name', true );
    $last_name  = get_user_meta( $user_id, 'last_name', true );

    foreach ( [ 'billing', 'shipping' ] as $address_type ) {
        update_user_meta( $user_id, $address_type . '_first_name', $first_name );
        update_user_meta( $user_id, $address_type . '_last_name', $last_name );
    }
}

add_action( 'woocommerce_save_account_details', 'variedadesexpress_sync_account_names_to_addresses' );

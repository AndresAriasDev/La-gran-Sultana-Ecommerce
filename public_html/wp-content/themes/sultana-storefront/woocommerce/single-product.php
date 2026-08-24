<?php
/**
 * Single product template.
 *
 * @package SultanaStorefront
 */

defined( 'ABSPATH' ) || exit;

get_header( 'shop' );

while ( have_posts() ) :
    the_post();

    global $product;

    if ( ! $product instanceof WC_Product ) {
        continue;
    }

    if ( post_password_required() ) {
        echo get_the_password_form(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        continue;
    }

    $product_id        = $product->get_id();
    $main_image_id     = $product->get_image_id();
    $gallery_image_ids = $product->get_gallery_image_ids();
    $image_ids         = array_values( array_filter( array_unique( array_merge( [ $main_image_id ], $gallery_image_ids ) ) ) );
    $main_image_sizes  = '(max-width: 560px) calc(100vw - 20px), (max-width: 900px) calc(100vw - 32px), min(52vw, 615px)';
    $main_image_attrs  = [
        'class'         => 'single-product-gallery__main-image',
        'loading'       => 'eager',
        'fetchpriority' => 'high',
        'decoding'      => 'async',
        'sizes'         => $main_image_sizes,
    ];
    $main_image_html   = $main_image_id ? wp_get_attachment_image( $main_image_id, 'woocommerce_single', false, $main_image_attrs ) : '';
    $main_image_html   = $main_image_html ?: wc_placeholder_img( 'woocommerce_single', $main_image_attrs );
    $short_description = apply_filters( 'woocommerce_short_description', $post->post_excerpt );
    $parent_sku        = $product->get_sku();
    $initial_sku       = $parent_sku;
    $variation_skus    = [];
    $wishlist_state    = [];
    $regular_price     = $product->is_type( 'variable' ) ? $product->get_variation_regular_price( 'min', true ) : $product->get_regular_price();
    $current_price     = $product->is_type( 'variable' ) ? $product->get_variation_price( 'min', true ) : $product->get_price();
    $has_discount      = '' !== $regular_price && '' !== $current_price && (float) $regular_price > (float) $current_price;
    $brand_term        = null;
    $brand_link        = '';

    foreach ( [ 'product_brand', 'pa_marca', 'pa_brand', 'yith_product_brand' ] as $brand_taxonomy ) {
        if ( ! taxonomy_exists( $brand_taxonomy ) ) {
            continue;
        }

        $product_brands = get_the_terms( $product_id, $brand_taxonomy );

        if ( is_wp_error( $product_brands ) || empty( $product_brands ) ) {
            continue;
        }

        $brand_term = array_shift( $product_brands );
        $term_link  = get_term_link( $brand_term );
        $brand_link = is_wp_error( $term_link ) ? '' : $term_link;
        break;
    }

    if ( $product->is_type( 'variable' ) ) {
        foreach ( $product->get_children() as $variation_id ) {
            $variation_sku = (string) get_post_meta( $variation_id, '_sku', true );

            if ( '' === $variation_sku ) {
                continue;
            }

            $variation_skus[ $variation_id ] = $variation_sku;
        }
    }

    $wishlist_class = '\Sultana\CommerceCore\Modules\Wishlist\Wishlist';
    $wishlist_normalize_selection_part = static function ( string $value ): string {
        $value = remove_accents( $value );
        $value = strtolower( trim( $value ) );
        $value = preg_replace( '/[^a-z0-9_-]+/', '-', $value );

        return trim( (string) $value, '-' );
    };
    $wishlist_normalize_attributes = static function ( array $attributes ) use ( $wishlist_normalize_selection_part ): array {
        $normalized = [];

        foreach ( $attributes as $name => $value ) {
            $attribute_name  = (string) $name;
            $attribute_value = (string) $value;

            if ( '' === trim( $attribute_name ) || '' === trim( $attribute_value ) ) {
                continue;
            }

            if ( 0 !== strpos( $attribute_name, 'attribute_' ) ) {
                $attribute_name = 'attribute_' . $attribute_name;
            }

            $normalized[ $wishlist_normalize_selection_part( $attribute_name ) ] = $wishlist_normalize_selection_part( $attribute_value );
        }

        ksort( $normalized );

        return $normalized;
    };
    $wishlist_selection_key = static function ( int $wishlist_product_id, int $wishlist_variation_id, array $attributes ) use ( $wishlist_normalize_attributes ): string {
        return 'selection:' . $wishlist_product_id . ':' . $wishlist_variation_id . ':' . wp_json_encode( $wishlist_normalize_attributes( $attributes ) );
    };

    if ( is_user_logged_in() && class_exists( $wishlist_class ) ) {
        foreach ( $wishlist_class::get_items( get_current_user_id() ) as $wishlist_item ) {
            $wishlist_product_id   = absint( $wishlist_item['product_id'] ?? 0 );
            $wishlist_variation_id = absint( $wishlist_item['variation_id'] ?? 0 );
            $wishlist_key          = sanitize_text_field( $wishlist_item['key'] ?? '' );
            $wishlist_attributes   = is_array( $wishlist_item['attributes'] ?? null ) ? $wishlist_item['attributes'] : [];

            if ( '' === $wishlist_key ) {
                continue;
            }

            if ( $product->is_type( 'variable' ) ) {
                if ( $wishlist_product_id !== $product_id || $wishlist_variation_id <= 0 ) {
                    continue;
                }

                $wishlist_state[ $wishlist_selection_key( $wishlist_product_id, $wishlist_variation_id, $wishlist_attributes ) ] = $wishlist_key;
                continue;
            }

            if ( $wishlist_product_id === $product_id && 0 === $wishlist_variation_id ) {
                $wishlist_state[ 'product:' . $product_id ] = $wishlist_key;
            }
        }
    }

    $product_notices = function_exists( 'wc_get_notices' ) ? wc_get_notices() : [];
    $cart_url        = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/carrito/' );

    if ( function_exists( 'wc_clear_notices' ) && ! empty( $product_notices ) ) {
        wc_clear_notices();
    }

    do_action( 'woocommerce_before_single_product' );

    $review_count      = $product->get_review_count();
    $average_rating    = (float) $product->get_average_rating();
    $related_products  = [];
    $combo_components  = [];
    $combo_stock_class = '\Sultana\CommerceCore\Modules\Combos\ComboStockService';

    if ( 'combo' === $product->get_type() && class_exists( $combo_stock_class ) && method_exists( $combo_stock_class, 'get_display_components' ) ) {
        $combo_components = $combo_stock_class::get_display_components( $product_id );

        foreach ( $combo_components as $combo_component ) {
            $component_image_id = absint( $combo_component['image_id'] ?? 0 );

            if ( $component_image_id ) {
                $image_ids[] = $component_image_id;
            }
        }

        $image_ids = array_values( array_filter( array_unique( array_map( 'absint', $image_ids ) ) ) );
    }

    if ( function_exists( 'wc_get_related_products' ) && function_exists( 'variedadesexpress_home_for_you_card' ) ) {
        foreach ( wc_get_related_products( $product_id, 15 ) as $related_product_id ) {
            $related_product = wc_get_product( $related_product_id );

            if ( $related_product instanceof WC_Product && $related_product->is_visible() ) {
                $related_products[] = $related_product;
            }
        }
    }
    ?>

    <article id="product-<?php the_ID(); ?>" <?php wc_product_class( 'single-product-detail', $product ); ?>>
        <div class="single-product-detail__container">
            <div class="single-product-detail__layout">
                <div class="single-product-detail__mobile-notice-anchor" data-product-mobile-cart-notice-anchor></div>
                <section class="single-product-gallery" aria-label="<?php esc_attr_e( 'Imagenes del producto', 'sultana-storefront' ); ?>" data-product-main-image-id="<?php echo esc_attr( $main_image_id ); ?>">
                    <div class="single-product-gallery__frame">
                        <?php variedadesexpress_product_discount_badge( $product, 'single-product-gallery__discount-badge' ); ?>

                        <?php if ( count( $image_ids ) > 1 ) : ?>
                            <div class="single-product-gallery__thumbs" aria-label="<?php esc_attr_e( 'Miniaturas del producto', 'sultana-storefront' ); ?>">
                                <?php foreach ( $image_ids as $index => $image_id ) : ?>
                                    <?php
                                    $thumb_url     = wp_get_attachment_image_url( $image_id, 'woocommerce_gallery_thumbnail' );
                                    $large_url     = wp_get_attachment_image_url( $image_id, 'large' );
                                    $display_image = wp_get_attachment_image_src( $image_id, 'woocommerce_single' );
                                    $display_url    = is_array( $display_image ) ? (string) ( $display_image[0] ?? '' ) : '';
                                    $display_width  = is_array( $display_image ) ? absint( $display_image[1] ?? 0 ) : 0;
                                    $display_height = is_array( $display_image ) ? absint( $display_image[2] ?? 0 ) : 0;
                                    $display_srcset = wp_get_attachment_image_srcset( $image_id, 'woocommerce_single' );
                                    $display_url    = $display_url ?: $large_url;
                                    $alt            = get_post_meta( $image_id, '_wp_attachment_image_alt', true );
                                    ?>
                                    <button
                                        class="single-product-gallery__thumb <?php echo 0 === $index ? 'is-active' : ''; ?>"
                                        type="button"
                                        data-product-image="<?php echo esc_url( $display_url ); ?>"
                                        data-product-image-alt="<?php echo esc_attr( $alt ); ?>"
                                        data-product-image-srcset="<?php echo esc_attr( $display_srcset ?: '' ); ?>"
                                        data-product-image-sizes="<?php echo esc_attr( $display_srcset ? $main_image_sizes : '' ); ?>"
                                        data-product-image-width="<?php echo esc_attr( $display_width ); ?>"
                                        data-product-image-height="<?php echo esc_attr( $display_height ); ?>"
                                        data-product-zoom-image="<?php echo esc_url( $large_url ); ?>"
                                        aria-label="<?php echo esc_attr( sprintf( __( 'Ver imagen %d de %s', 'sultana-storefront' ), $index + 1, $product->get_name() ) ); ?>"
                                    >
                                        <img src="<?php echo esc_url( $thumb_url ); ?>" alt="" loading="lazy" decoding="async">
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <button class="single-product-gallery__main-link" type="button" aria-label="<?php esc_attr_e( 'Ampliar imagen del producto', 'sultana-storefront' ); ?>">
                            <?php echo $main_image_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </button>
                    </div>
                </section>

                <section class="single-product-summary" aria-labelledby="single-product-title">
                    <div class="single-product-summary__card">
                        <?php if ( ! empty( $product_notices['success'] ) ) : ?>
                            <?php foreach ( $product_notices['success'] as $notice ) : ?>
                                <?php $notice_text = is_array( $notice ) ? (string) ( $notice['notice'] ?? '' ) : (string) $notice; ?>
                                <?php if ( '' !== trim( wp_strip_all_tags( $notice_text ) ) ) : ?>
                                    <div class="single-product-summary__variation-notice single-product-summary__variation-notice--static is-visible is-success is-clickable" role="status" data-product-cart-notice data-product-cart-url="<?php echo esc_url( $cart_url ); ?>">
                                        <span class="single-product-summary__variation-notice-icon" aria-hidden="true">✓</span>
                                        <div class="single-product-summary__variation-notice-content">
                                            <a class="single-product-summary__variation-notice-message" href="<?php echo esc_url( $cart_url ); ?>">
                                                <?php echo wp_kses_post( $notice_text ); ?>
                                            </a>
                                        </div>
                                        <button class="single-product-summary__variation-notice-close" type="button" aria-label="<?php esc_attr_e( 'Cerrar aviso', 'sultana-storefront' ); ?>" data-product-cart-notice-close>
                                            <span aria-hidden="true">&times;</span>
                                        </button>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <h1 id="single-product-title" class="single-product-summary__title">
                            <?php the_title(); ?>
                        </h1>

                        <div class="single-product-summary__meta-row">
                            <div
                                class="single-product-summary__sku <?php echo esc_attr( $initial_sku ? '' : 'is-empty' ); ?>"
                                data-product-sku-wrapper
                                data-initial-sku="<?php echo esc_attr( $initial_sku ); ?>"
                                data-parent-sku="<?php echo esc_attr( $parent_sku ); ?>"
                                data-variation-skus="<?php echo esc_attr( wp_json_encode( $variation_skus ) ); ?>"
                            >
                                <span data-product-sku-text>
                                    <?php echo esc_html( $initial_sku ? sprintf( __( 'SKU: %s', 'sultana-storefront' ), $initial_sku ) : '' ); ?>
                                </span>
                                <button
                                    class="single-product-summary__copy"
                                    type="button"
                                    data-copy-text="<?php echo esc_attr( $initial_sku ); ?>"
                                    aria-label="<?php esc_attr_e( 'Copiar SKU', 'sultana-storefront' ); ?>"
                                    <?php disabled( ! $initial_sku ); ?>
                                >
                                    <img src="<?php echo esc_url( get_template_directory_uri() . '/assets/icons/copy.svg' ); ?>" alt="" width="16" height="16" aria-hidden="true">
                                </button>
                            </div>

                            <?php if ( $review_count > 0 ) : ?>
                                <a class="single-product-summary__rating" href="#reviews" aria-label="<?php echo esc_attr( sprintf( __( 'Valoracion %.1f de 5 basada en %d resenas', 'sultana-storefront' ), $average_rating, $review_count ) ); ?>">
                                    <span class="single-product-summary__stars" aria-hidden="true">
                                        <?php for ( $star = 1; $star <= 5; $star++ ) : ?>
                                            <span class="<?php echo esc_attr( $star <= round( $average_rating ) ? 'is-filled' : '' ); ?>">★</span>
                                        <?php endfor; ?>
                                    </span>
                                    <span><?php echo esc_html( sprintf( _n( '(%d Comentario)', '(%d Comentarios)', $review_count, 'sultana-storefront' ), $review_count ) ); ?></span>
                                </a>
                            <?php endif; ?>
                        </div>

                        <?php if ( $brand_term instanceof WP_Term ) : ?>
                            <?php if ( $brand_link ) : ?>
                                <a class="single-product-summary__brand-pill" href="<?php echo esc_url( $brand_link ); ?>">
                                    <?php echo esc_html( $brand_term->name ); ?>
                                </a>
                            <?php else : ?>
                                <span class="single-product-summary__brand-pill">
                                    <?php echo esc_html( $brand_term->name ); ?>
                                </span>
                            <?php endif; ?>
                        <?php endif; ?>

                        <div class="single-product-summary__price" data-product-price>
                            <?php if ( $has_discount ) : ?>
                                <ins>
                                    <?php echo wp_kses_post( wc_price( (float) $current_price ) ); ?>
                                </ins>
                                <del>
                                    <?php echo wp_kses_post( wc_price( (float) $regular_price ) ); ?>
                                </del>
                            <?php elseif ( $product->is_type( 'variable' ) && '' !== $current_price ) : ?>
                                <?php echo wp_kses_post( wc_price( (float) $current_price ) ); ?>
                            <?php else : ?>
                                <?php echo wp_kses_post( $product->get_price_html() ); ?>
                            <?php endif; ?>
                        </div>

                        <?php if ( $short_description ) : ?>
                            <div class="single-product-summary__excerpt">
                                <?php echo wp_kses_post( $short_description ); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ( ! empty( $combo_components ) ) : ?>
                            <section class="single-product-combo" aria-labelledby="single-product-combo-title">
                                <h2 id="single-product-combo-title"><?php esc_html_e( 'Este combo incluye', 'sultana-storefront' ); ?></h2>

                                <div class="single-product-combo__panel">
                                    <ul class="single-product-combo__list">
                                        <?php foreach ( $combo_components as $combo_component ) : ?>
                                            <?php
                                            $component_name     = sanitize_text_field( (string) ( $combo_component['name'] ?? '' ) );
                                            $component_quantity = max( 1, absint( $combo_component['quantity'] ?? 0 ) );
                                            $component_attrs    = is_array( $combo_component['attributes'] ?? null ) ? $combo_component['attributes'] : [];

                                            if ( '' === $component_name ) {
                                                continue;
                                            }
                                            ?>
                                            <li class="single-product-combo__item">
                                                <div class="single-product-combo__content">
                                                    <span class="single-product-combo__name"><?php echo esc_html( $component_name ); ?></span>

                                                    <?php if ( ! empty( $component_attrs ) ) : ?>
                                                        <ul class="single-product-combo__attributes" aria-label="<?php esc_attr_e( 'Atributos del componente', 'sultana-storefront' ); ?>">
                                                            <?php foreach ( $component_attrs as $component_attr ) : ?>
                                                                <?php
                                                                $attr_label = sanitize_text_field( (string) ( $component_attr['label'] ?? '' ) );
                                                                $attr_value = sanitize_text_field( (string) ( $component_attr['value'] ?? '' ) );

                                                                if ( '' === $attr_value ) {
                                                                    continue;
                                                                }
                                                                ?>
                                                                <li>
                                                                    <?php if ( '' !== $attr_label ) : ?>
                                                                        <span><?php echo esc_html( $attr_label ); ?>:</span>
                                                                    <?php endif; ?>
                                                                    <?php echo esc_html( $attr_value ); ?>
                                                                </li>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                    <?php endif; ?>
                                                </div>

                                                <span class="single-product-combo__quantity" aria-label="<?php echo esc_attr( sprintf( __( 'Cantidad incluida: %d', 'sultana-storefront' ), $component_quantity ) ); ?>">
                                                    &times;<?php echo esc_html( (string) $component_quantity ); ?>
                                                </span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </section>
                        <?php endif; ?>

                        <div
                            class="single-product-summary__actions"
                            data-wishlist-state="<?php echo esc_attr( wp_json_encode( $wishlist_state ) ); ?>"
                        >
                            <?php woocommerce_template_single_add_to_cart(); ?>
                        </div>
                    </div>
                </section>
            </div>

            <section class="single-product-tabs" aria-label="<?php esc_attr_e( 'Informacion del producto', 'sultana-storefront' ); ?>">
                <?php woocommerce_output_product_data_tabs(); ?>
            </section>

            <?php if ( $related_products ) : ?>
                <section class="single-product-related" aria-labelledby="single-product-related-title">
                    <header class="single-product-related__header">
                        <span aria-hidden="true"></span>
                        <h2 id="single-product-related-title">
                            <?php esc_html_e( 'Productos relacionados', 'sultana-storefront' ); ?>
                        </h2>
                        <span aria-hidden="true"></span>
                    </header>

                    <div class="single-product-related__viewport">
                        <?php if ( count( $related_products ) > 5 ) : ?>
                            <button class="single-product-related__arrow single-product-related__arrow--prev" type="button" aria-label="<?php esc_attr_e( 'Ver productos anteriores', 'sultana-storefront' ); ?>" disabled>
                                <?php variedadesexpress_icon( 'chevron-left', 'single-product-related__arrow-icon' ); ?>
                            </button>
                        <?php endif; ?>

                        <div class="single-product-related__track" data-related-products-track tabindex="0">
                            <?php foreach ( $related_products as $related_product ) : ?>
                                <?php variedadesexpress_home_for_you_card( $related_product ); ?>
                            <?php endforeach; ?>
                        </div>

                        <?php if ( count( $related_products ) > 5 ) : ?>
                            <button class="single-product-related__arrow single-product-related__arrow--next" type="button" aria-label="<?php esc_attr_e( 'Ver mas productos relacionados', 'sultana-storefront' ); ?>">
                                <?php variedadesexpress_icon( 'chevron-right', 'single-product-related__arrow-icon' ); ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>
        </div>
    </article>

    <?php do_action( 'woocommerce_after_single_product' ); ?>

<?php endwhile; ?>

<?php
get_footer( 'shop' );

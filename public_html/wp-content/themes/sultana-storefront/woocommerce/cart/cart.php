<?php
/**
 * Cart page.
 *
 * @package SultanaStorefront
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_before_cart' );

$cart_items = WC()->cart ? WC()->cart->get_cart() : [];
$is_gift_cart = false;
$gift_owner_name = '';
$gift_shipping_rate = null;
$gift_shipping_department = '';
$personal_shipping_rates = [];
$personal_shipping_has_destination = false;
$personal_shipping_has_rates = false;
$applied_coupons = WC()->cart ? WC()->cart->get_coupons() : [];

foreach ( $cart_items as $cart_item ) {
    if ( empty( $cart_item['scc_wishlist_gift'] ) || ! is_array( $cart_item['scc_wishlist_gift'] ) ) {
        continue;
    }

    $is_gift_cart    = true;
    $gift_owner_name = sanitize_text_field( $cart_item['scc_wishlist_gift']['owner_name'] ?? '' );
    break;
}

if ( $is_gift_cart && WC()->cart && WC()->cart->needs_shipping() ) {
    $wishlist_class = '\Sultana\CommerceCore\Modules\Wishlist\Wishlist';
    $destination    = ( class_exists( $wishlist_class ) && method_exists( $wishlist_class, 'get_cart_gift_shipping_destination' ) )
        ? $wishlist_class::get_cart_gift_shipping_destination()
        : [];
    $state          = sanitize_text_field( (string) ( $destination['state'] ?? '' ) );

    if ( '' !== $state ) {
        $department_options      = function_exists( 'variedadesexpress_nicaragua_department_options' ) ? variedadesexpress_nicaragua_department_options() : [];
        $gift_shipping_department = sanitize_text_field( (string) ( $department_options[ $state ] ?? $state ) );
    }

    WC()->cart->calculate_shipping();

    $packages       = WC()->shipping() ? WC()->shipping()->get_packages() : [];
    $chosen_methods = WC()->session ? (array) WC()->session->get( 'chosen_shipping_methods', [] ) : [];
    $chosen_updated = false;

    foreach ( $packages as $package_index => $package ) {
        $rates = $package['rates'] ?? [];

        if ( empty( $rates ) || ! is_array( $rates ) ) {
            continue;
        }

        $chosen_rate_id = (string) ( $chosen_methods[ $package_index ] ?? '' );
        $rate_id        = ( '' !== $chosen_rate_id && isset( $rates[ $chosen_rate_id ] ) ) ? $chosen_rate_id : (string) array_key_first( $rates );

        if ( '' === $chosen_rate_id && '' !== $rate_id ) {
            $chosen_methods[ $package_index ] = $rate_id;
            $chosen_updated = true;
        }

        $gift_shipping_rate = $rates[ $rate_id ] ?? null;
        break;
    }

    if ( $chosen_updated && WC()->session ) {
        WC()->session->set( 'chosen_shipping_methods', $chosen_methods );
        WC()->cart->calculate_totals();
    }
}

$cart_destination_value_is_missing = static function ( string $value ): bool {
    $value = trim( $value );

    if ( '' === $value ) {
        return true;
    }

    $normalized = sanitize_title( $value );

    return in_array(
        $normalized,
        [
            'selecciona-un-municipio',
            'selecciona-municipio',
            'seleccione-un-municipio',
            'municipio',
            'select-a-city',
            'selecciona-un-departamento',
            'selecciona-departamento',
            'seleccione-un-departamento',
            'departamento',
            'select-a-state',
        ],
        true
    );
};

$cart_shipping_rate_matches = static function ( $rate, string $method_id ): bool {
    if ( ! is_object( $rate ) ) {
        return false;
    }

    if ( method_exists( $rate, 'get_id' ) && $method_id === (string) $rate->get_id() ) {
        return true;
    }

    if ( method_exists( $rate, 'get_method_id' ) && $method_id === (string) $rate->get_method_id() ) {
        return true;
    }

    return false;
};

$cart_preferred_personal_shipping_rate_id = static function ( array $rates ) use ( $cart_shipping_rate_matches ): string {
    foreach ( [ 'scc_express_granada', 'scc_store_pickup', 'scc_cargotrans' ] as $method_id ) {
        foreach ( $rates as $rate_id => $rate ) {
            if ( $cart_shipping_rate_matches( $rate, $method_id ) ) {
                return (string) $rate_id;
            }
        }
    }

    return (string) array_key_first( $rates );
};

if ( ! $is_gift_cart && WC()->cart && WC()->cart->needs_shipping() && WC()->customer ) {
    $customer = WC()->customer;
    $state    = is_callable( [ $customer, 'get_shipping_state' ] ) ? (string) $customer->get_shipping_state() : '';
    $city     = is_callable( [ $customer, 'get_shipping_city' ] ) ? (string) $customer->get_shipping_city() : '';

    if ( $cart_destination_value_is_missing( $state ) && is_callable( [ $customer, 'get_billing_state' ] ) ) {
        $state = (string) $customer->get_billing_state();
    }

    if ( $cart_destination_value_is_missing( $city ) && is_callable( [ $customer, 'get_billing_city' ] ) ) {
        $city = (string) $customer->get_billing_city();
    }

    if ( function_exists( 'variedadesexpress_normalize_address_location_value' ) ) {
        $state = variedadesexpress_normalize_address_location_value( 'shipping_state', $state );
        $city  = variedadesexpress_normalize_address_location_value( 'shipping_city', $city );
    }

    if (
        $cart_destination_value_is_missing( $state )
        && ! $cart_destination_value_is_missing( $city )
        && function_exists( 'variedadesexpress_nicaragua_department_for_municipality' )
    ) {
        $state = variedadesexpress_nicaragua_department_for_municipality( $city );
    }

    $personal_shipping_has_destination = ! $cart_destination_value_is_missing( $state ) && ! $cart_destination_value_is_missing( $city );
    $personal_shipping_destination     = [
        'country'  => 'NI',
        'state'    => $personal_shipping_has_destination ? $state : '',
        'city'     => $personal_shipping_has_destination ? $city : '',
        'postcode' => '',
    ];
    $personal_shipping_package_filter  = static function ( array $packages ) use ( $personal_shipping_destination ): array {
        foreach ( $packages as $package_index => $package ) {
            $packages[ $package_index ]['destination'] = array_merge(
                (array) ( $package['destination'] ?? [] ),
                $personal_shipping_destination
            );
        }

        return $packages;
    };

    add_filter( 'woocommerce_cart_shipping_packages', $personal_shipping_package_filter, 30 );
    WC()->cart->calculate_shipping();

    $packages                = WC()->shipping() ? WC()->shipping()->get_packages() : [];
    $original_chosen_methods = WC()->session ? (array) WC()->session->get( 'chosen_shipping_methods', [] ) : [];
    $estimate_methods        = $original_chosen_methods;
    $estimate_methods_changed = false;

    foreach ( $packages as $package_index => $package ) {
        $rates = $package['rates'] ?? [];

        if ( empty( $rates ) || ! is_array( $rates ) ) {
            continue;
        }

        $personal_shipping_has_rates = true;
        $chosen_rate_id              = $cart_preferred_personal_shipping_rate_id( $rates );

        if ( isset( $rates[ $chosen_rate_id ] ) ) {
            $personal_shipping_rates[] = $rates[ $chosen_rate_id ];
        }

        if ( '' !== $chosen_rate_id && (string) ( $estimate_methods[ $package_index ] ?? '' ) !== $chosen_rate_id ) {
            $estimate_methods[ $package_index ] = $chosen_rate_id;
            $estimate_methods_changed = true;
        }
    }

    $personal_shipping_has_rates = ! empty( $personal_shipping_rates );

    if ( $estimate_methods_changed && WC()->session ) {
        WC()->session->set( 'chosen_shipping_methods', $estimate_methods );
    }

    WC()->cart->calculate_totals();

    if ( $estimate_methods_changed && WC()->session ) {
        WC()->session->set( 'chosen_shipping_methods', $original_chosen_methods );
    }

    remove_filter( 'woocommerce_cart_shipping_packages', $personal_shipping_package_filter, 30 );
}

$cart_count = WC()->cart ? WC()->cart->get_cart_contents_count() : 0;
$cart_count_label = _n( 'producto', 'productos', $cart_count, 'sultana-storefront' );
$cart_title = $is_gift_cart ? __( 'Carrito de regalo', 'sultana-storefront' ) : __( 'Mi carrito', 'sultana-storefront' );
$cart_eyebrow = $is_gift_cart ? __( 'Compra de regalo', 'sultana-storefront' ) : __( 'Compra personal', 'sultana-storefront' );
$cart_summary_shipping_info = '';

if ( $is_gift_cart && $gift_shipping_rate && '' !== $gift_shipping_department ) {
    $cart_summary_shipping_info = sprintf(
        /* translators: 1: department name, 2: shipping method label. */
        __( 'Este es el costo del envío al departamento %1$s por medio de %2$s.', 'sultana-storefront' ),
        $gift_shipping_department,
        sanitize_text_field( $gift_shipping_rate->get_label() )
    );
} elseif ( ! empty( $personal_shipping_rates ) ) {
    $cart_summary_shipping_info = __( 'Calculado según tu dirección registrada. Podrás cambiar la dirección de envío en finalizar compra.', 'sultana-storefront' );
}
?>

<div class="ve-cart-page">
    <section class="ve-cart-hero">
        <div>
            <span class="ve-cart-hero__eyebrow"><?php echo esc_html( $cart_eyebrow ); ?></span>
            <h1><?php echo esc_html( $cart_title ); ?></h1>
        </div>
        <p
            class="ve-cart-hero__count"
            aria-label="<?php echo esc_attr( sprintf( '%d %s', $cart_count, $cart_count_label ) ); ?>"
        >
            <span class="ve-cart-hero__count-number"><?php echo esc_html( (string) $cart_count ); ?></span>
            <span class="ve-cart-hero__count-label"><?php echo esc_html( $cart_count_label ); ?></span>
        </p>
    </section>

    <?php if ( WC()->cart && WC()->cart->is_empty() ) : ?>
        <?php get_template_part( 'template-parts/cart/empty-state' ); ?>
    <?php else : ?>
        <form class="ve-cart-layout woocommerce-cart-form" action="<?php echo esc_url( wc_get_cart_url() ); ?>" method="post" data-ve-cart-form>
            <section class="ve-cart-items" aria-label="<?php esc_attr_e( 'Productos del carrito', 'sultana-storefront' ); ?>">
                <?php foreach ( $cart_items as $cart_item_key => $cart_item ) : ?>
                    <?php
                    $_product   = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
                    if ( ! $_product || ! $_product->exists() || $cart_item['quantity'] <= 0 ) {
                        continue;
                    }

                    $parent_product    = $_product instanceof WC_Product_Variation ? wc_get_product( $_product->get_parent_id() ) : null;
                    $display_product   = $parent_product ?: $_product;
                    $product_permalink = apply_filters( 'woocommerce_cart_item_permalink', $_product->is_visible() ? $_product->get_permalink( $cart_item ) : '', $cart_item, $cart_item_key );
                    $thumbnail         = apply_filters( 'woocommerce_cart_item_thumbnail', $_product->get_image( 'woocommerce_thumbnail', [ 'class' => 've-cart-item__image' ] ), $cart_item, $cart_item_key );
                    $product_name      = $display_product->get_name();
                    $variation_options = [];
                    $is_gift_item      = ! empty( $cart_item['scc_wishlist_gift']['owner_name'] );
                    $combo_summary     = [];
                    $combo_stock_class = '\Sultana\CommerceCore\Modules\Combos\ComboStockService';

                    if ( 'combo' === $_product->get_type() && class_exists( $combo_stock_class ) && method_exists( $combo_stock_class, 'get_display_components' ) ) {
                        foreach ( $combo_stock_class::get_display_components( $_product->get_id() ) as $combo_component ) {
                            $component_name     = sanitize_text_field( (string) ( $combo_component['name'] ?? '' ) );
                            $component_quantity = max( 1, absint( $combo_component['quantity'] ?? 0 ) );
                            $component_attrs    = is_array( $combo_component['attributes'] ?? null ) ? $combo_component['attributes'] : [];
                            $component_values   = [];

                            foreach ( $component_attrs as $component_attr ) {
                                $attr_value = sanitize_text_field( (string) ( $component_attr['value'] ?? '' ) );

                                if ( '' !== $attr_value ) {
                                    $component_values[] = $attr_value;
                                }
                            }

                            if ( '' === $component_name ) {
                                continue;
                            }

                            $combo_summary[] = [
                                'label'    => $component_values ? sprintf( '%s - %s', $component_name, implode( ' / ', $component_values ) ) : $component_name,
                                'quantity' => $component_quantity,
                            ];
                        }
                    }

                    foreach ( (array) ( $cart_item['variation'] ?? [] ) as $attribute_name => $attribute_value ) {
                        $attribute_value = (string) $attribute_value;

                        if ( '' === $attribute_value ) {
                            continue;
                        }

                        $taxonomy = str_replace( 'attribute_', '', (string) $attribute_name );
                        $label    = rawurldecode( $attribute_value );

                        if ( taxonomy_exists( $taxonomy ) ) {
                            $term = get_term_by( 'slug', $attribute_value, $taxonomy );

                            if ( $term && ! is_wp_error( $term ) ) {
                                $label = $term->name;
                            }
                        }

                        $variation_options[] = sanitize_text_field( $label );
                    }
                    ?>
                    <article class="ve-cart-item" data-ve-cart-item-key="<?php echo esc_attr( $cart_item_key ); ?>">
                        <a class="ve-cart-item__media" href="<?php echo esc_url( $product_permalink ?: '#' ); ?>">
                            <?php echo wp_kses_post( $thumbnail ); ?>
                        </a>

                        <div class="ve-cart-item__body">
                            <?php if ( $is_gift_item ) : ?>
                                <span class="ve-cart-item__gift">
                                    <?php
                                    echo esc_html(
                                        sprintf(
                                            /* translators: %s: gift recipient name. */
                                            __( 'Regalo para %s', 'sultana-storefront' ),
                                            sanitize_text_field( $cart_item['scc_wishlist_gift']['owner_name'] )
                                        )
                                    );
                                    ?>
                                </span>
                            <?php endif; ?>

                            <a class="ve-cart-item__title" href="<?php echo esc_url( $product_permalink ?: '#' ); ?>">
                                <?php echo esc_html( $product_name ); ?>
                            </a>

                            <?php if ( ! empty( $combo_summary ) ) : ?>
                                <p class="ve-cart-item__combo-summary">
                                    <span><?php esc_html_e( 'Incluye:', 'sultana-storefront' ); ?></span>
                                    <?php foreach ( $combo_summary as $combo_summary_index => $combo_summary_item ) : ?>
                                        <?php if ( $combo_summary_index > 0 ) : ?>
                                            <span aria-hidden="true">·</span>
                                        <?php endif; ?>
                                        <span>
                                            <?php
                                            echo esc_html(
                                                sprintf(
                                                    /* translators: 1: combo component name, 2: included quantity. */
                                                    __( '%1$s x%2$d', 'sultana-storefront' ),
                                                    $combo_summary_item['label'],
                                                    $combo_summary_item['quantity']
                                                )
                                            );
                                            ?>
                                        </span>
                                    <?php endforeach; ?>
                                </p>
                            <?php endif; ?>

                            <?php if ( ! empty( $variation_options ) ) : ?>
                                <ul class="ve-cart-item__options" aria-label="<?php esc_attr_e( 'Opciones seleccionadas', 'sultana-storefront' ); ?>">
                                    <?php foreach ( $variation_options as $variation_option ) : ?>
                                        <li><?php echo esc_html( $variation_option ); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>

                            <div class="ve-cart-item__purchase">
                                <span class="ve-cart-item__unit">
                                    <?php echo wp_kses_post( WC()->cart->get_product_price( $_product ) ); ?>
                                </span>
                                <span class="ve-cart-item__multiply" aria-hidden="true">&times;</span>

                                <label class="screen-reader-text" for="cart-<?php echo esc_attr( $cart_item_key ); ?>">
                                    <?php esc_html_e( 'Cantidad', 'sultana-storefront' ); ?>
                                </label>
                                <div class="ve-cart-qty" data-ve-quantity>
                                    <button type="button" data-ve-quantity-step="-1" aria-label="<?php esc_attr_e( 'Reducir cantidad', 'sultana-storefront' ); ?>">-</button>
                                    <?php
                                    echo woocommerce_quantity_input(
                                        [
                                            'input_name'   => "cart[{$cart_item_key}][qty]",
                                            'input_value'  => $cart_item['quantity'],
                                            'max_value'    => $_product->get_max_purchase_quantity(),
                                            'min_value'    => '1',
                                            'product_name' => $product_name,
                                            'input_id'     => 'cart-' . $cart_item_key,
                                        ],
                                        $_product,
                                        false
                                    );
                                    ?>
                                    <button type="button" data-ve-quantity-step="1" aria-label="<?php esc_attr_e( 'Aumentar cantidad', 'sultana-storefront' ); ?>">+</button>
                                </div>
                            </div>
                        </div>

                        <div class="ve-cart-item__actions">
                            <a class="ve-cart-item__remove" href="<?php echo esc_url( wc_get_cart_remove_url( $cart_item_key ) ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Eliminar %s', 'sultana-storefront' ), wp_strip_all_tags( $product_name ) ) ); ?>">
                                <?php variedadesexpress_icon( 'brush-cleaning', 've-cart-item__remove-icon' ); ?>
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>

            <div class="ve-cart-sidebar">
                <div class="woocommerce-notices-wrapper ve-cart-coupon-feedback" data-ve-cart-coupon-feedback></div>

                <?php if ( wc_coupons_enabled() && empty( $applied_coupons ) ) : ?>
                    <div class="ve-cart-coupon">
                        <div class="ve-cart-coupon__header">
                            <label for="coupon_code"><?php esc_html_e( '¿Tenés un cupón?', 'sultana-storefront' ); ?></label>
                        </div>
                        <div class="ve-cart-coupon__form-row">
                            <input type="text" name="coupon_code" id="coupon_code" value="" placeholder="<?php esc_attr_e( 'Código de cupón', 'sultana-storefront' ); ?>">
                            <button type="submit" name="apply_coupon" value="<?php esc_attr_e( 'Aplicar cupón', 'sultana-storefront' ); ?>">
                                <?php esc_html_e( 'Aplicar', 'sultana-storefront' ); ?>
                            </button>
                        </div>
                    </div>
                <?php endif; ?>

                <aside class="ve-cart-summary" aria-label="<?php esc_attr_e( 'Resumen del pedido', 'sultana-storefront' ); ?>">
                <header>
                    <div>
                        <h2><?php esc_html_e( 'RESUMEN', 'sultana-storefront' ); ?></h2>
                    </div>
                    <?php if ( '' !== $cart_summary_shipping_info ) : ?>
                        <span class="ve-cart-summary__shipping-info">
                            <button
                                type="button"
                                class="ve-cart-summary__shipping-info-button"
                                aria-label="<?php esc_attr_e( 'Ver información del envío', 'sultana-storefront' ); ?>"
                                aria-expanded="false"
                                aria-controls="ve-cart-shipping-info"
                                data-cart-shipping-info-toggle
                            >
                                <span aria-hidden="true">!</span>
                            </button>
                            <span id="ve-cart-shipping-info" class="ve-cart-summary__shipping-popover" role="status" hidden data-cart-shipping-info-popover>
                                <?php echo esc_html( $cart_summary_shipping_info ); ?>
                            </span>
                        </span>
                    <?php endif; ?>
                </header>

                <?php if ( $is_gift_cart && $gift_owner_name ) : ?>
                    <p class="ve-cart-summary__note">
                        <?php
                        echo esc_html(
                            sprintf(
                                /* translators: %s: gift recipient name. */
                                __( 'Esta compra contiene regalos para %s. La dirección de entrega se mantiene privada y será gestionada por la tienda.', 'sultana-storefront' ),
                                $gift_owner_name
                            )
                        );
                        ?>
                    </p>
                <?php elseif ( ! $personal_shipping_has_rates ) : ?>
                    <p class="ve-cart-summary__note"><?php esc_html_e( 'El costo de envío se calcula en la página de finalizar compra.', 'sultana-storefront' ); ?></p>
                <?php endif; ?>

                <dl class="ve-cart-summary__totals">
                    <div>
                        <dt><?php esc_html_e( 'SUBTOTAL', 'sultana-storefront' ); ?></dt>
                        <dd><?php echo wp_kses_post( WC()->cart->get_cart_subtotal() ); ?></dd>
                    </div>
                    <?php foreach ( $applied_coupons as $coupon_code => $coupon ) : ?>
                        <?php
                        $coupon_code     = wc_format_coupon_code( $coupon_code );
                        $coupon_discount = WC()->cart->get_coupon_discount_amount( $coupon_code, WC()->cart->display_cart_ex_tax );

                        if ( ! WC()->cart->display_cart_ex_tax ) {
                            $coupon_discount += WC()->cart->get_coupon_discount_tax_amount( $coupon_code );
                        }
                        ?>
                        <div class="ve-cart-summary__coupon">
                            <dt>
                                <?php
                                echo esc_html(
                                    sprintf(
                                        /* translators: %s: coupon code. */
                                        __( 'Cupón: %s', 'sultana-storefront' ),
                                        $coupon_code
                                    )
                                );
                                ?>
                            </dt>
                            <dd>
                                <span><?php echo wp_kses_post( '-' . wc_price( $coupon_discount ) ); ?></span>
                                <button
                                    type="button"
                                    class="ve-cart-summary__coupon-remove"
                                    data-ve-remove-coupon="<?php echo esc_attr( $coupon_code ); ?>"
                                    aria-label="<?php echo esc_attr( sprintf( __( 'Quitar cupón %s', 'sultana-storefront' ), $coupon_code ) ); ?>"
                                >
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </dd>
                        </div>
                    <?php endforeach; ?>
                    <?php if ( $is_gift_cart && $gift_shipping_rate ) : ?>
                        <?php
                        $shipping_label = sanitize_text_field( $gift_shipping_rate->get_label() );
                        $shipping_cost  = (float) $gift_shipping_rate->get_cost() + array_sum( (array) $gift_shipping_rate->get_taxes() );
                        ?>
                        <div class="ve-cart-summary__shipping">
                            <dt><?php esc_html_e( 'ENVÍO', 'sultana-storefront' ); ?></dt>
                            <dd>
                                <span class="ve-cart-summary__shipping-value">
                                    <strong>
                                        <?php
                                        echo wp_kses_post(
                                            sprintf(
                                                /* translators: 1: shipping method label, 2: shipping cost. */
                                                __( '%1$s: %2$s', 'sultana-storefront' ),
                                                esc_html( $shipping_label ),
                                                wc_price( $shipping_cost )
                                            )
                                        );
                                        ?>
                                    </strong>
                                </span>
                            </dd>
                        </div>
                    <?php elseif ( ! empty( $personal_shipping_rates ) ) : ?>
                        <div class="ve-cart-summary__shipping">
                            <dt><?php esc_html_e( 'ENVÍO', 'sultana-storefront' ); ?></dt>
                            <dd>
                                <?php foreach ( $personal_shipping_rates as $personal_shipping_rate ) : ?>
                                    <?php
                                    $personal_shipping_label = sanitize_text_field( $personal_shipping_rate->get_label() );
                                    $personal_shipping_cost  = (float) $personal_shipping_rate->get_cost() + array_sum( (array) $personal_shipping_rate->get_taxes() );
                                    ?>
                                    <span class="ve-cart-summary__shipping-value">
                                        <strong>
                                            <?php
                                            echo wp_kses_post(
                                                sprintf(
                                                    /* translators: 1: shipping method label, 2: shipping cost. */
                                                    __( '%1$s: %2$s', 'sultana-storefront' ),
                                                    esc_html( $personal_shipping_label ),
                                                    wc_price( $personal_shipping_cost )
                                                )
                                            );
                                            ?>
                                        </strong>
                                    </span>
                                <?php endforeach; ?>
                            </dd>
                        </div>
                    <?php endif; ?>
                    <div>
                        <dt><?php esc_html_e( 'TOTAL', 'sultana-storefront' ); ?></dt>
                        <dd><?php echo wp_kses_post( WC()->cart->get_total() ); ?></dd>
                    </div>
                </dl>


                <a class="ve-cart-summary__checkout" href="<?php echo esc_url( wc_get_checkout_url() ); ?>">
                    <?php variedadesexpress_icon( 'shopping-cart', 've-cart-summary__checkout-icon' ); ?>
                    <span><?php esc_html_e( 'Finalizar compra', 'sultana-storefront' ); ?></span>
                </a>

                <button class="ve-cart-summary__update" type="submit" name="update_cart" value="<?php esc_attr_e( 'Actualizar carrito', 'sultana-storefront' ); ?>" hidden>
                    <?php esc_html_e( 'Actualizar cantidades', 'sultana-storefront' ); ?>
                </button>

                <?php wp_nonce_field( 'woocommerce-cart', 'woocommerce-cart-nonce' ); ?>
                <?php do_action( 'woocommerce_cart_actions' ); ?>
            </aside>
            </div>
        </form>
    <?php endif; ?>
</div>

<?php do_action( 'woocommerce_after_cart' ); ?>

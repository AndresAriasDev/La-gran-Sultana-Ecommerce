<?php
/**
 * Cart empty state.
 *
 * @package SultanaStorefront
 */

defined( 'ABSPATH' ) || exit;

$recommended_products = [];
$recommended_ids      = [];
$recommendation_sets  = [
    [
        'featured' => true,
        'orderby'  => 'date',
        'order'    => 'DESC',
    ],
    [
        'orderby' => 'popularity',
        'order'   => 'DESC',
    ],
    [
        'orderby' => 'date',
        'order'   => 'DESC',
    ],
];

if ( function_exists( 'wc_get_products' ) && function_exists( 'variedadesexpress_home_for_you_card' ) ) {
    foreach ( $recommendation_sets as $query_args ) {
        if ( count( $recommended_products ) >= 12 ) {
            break;
        }

        $products = wc_get_products(
            array_merge(
                [
                    'status'       => 'publish',
                    'limit'        => 12,
                    'stock_status' => 'instock',
                ],
                $query_args
            )
        );

        foreach ( $products as $recommended_product ) {
            if (
                ! $recommended_product instanceof WC_Product
                || isset( $recommended_ids[ $recommended_product->get_id() ] )
                || ! $recommended_product->is_visible()
                || ! $recommended_product->is_purchasable()
                || ! $recommended_product->is_in_stock()
            ) {
                continue;
            }

            $recommended_products[] = $recommended_product;
            $recommended_ids[ $recommended_product->get_id() ] = true;

            if ( count( $recommended_products ) >= 12 ) {
                break;
            }
        }
    }
}
?>

<section class="ve-cart-empty">
    <span class="ve-cart-empty__icon" aria-hidden="true">
        <?php variedadesexpress_icon( 'shopping-cart', 've-cart-empty__svg' ); ?>
    </span>
    <div class="ve-cart-empty__content">
        <h2><?php esc_html_e( 'Tu carrito está vacío', 'sultana-storefront' ); ?></h2>
        <p><?php esc_html_e( 'Todavía no agregaste productos. Descubrí algo que te guste y empezá tu compra.', 'sultana-storefront' ); ?></p>
    </div>
    <a class="ve-cart-empty__button" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>">
        <?php variedadesexpress_icon( 'shopping-bag', 've-cart-empty__button-icon' ); ?>
        <span><?php esc_html_e( 'Explorar productos', 'sultana-storefront' ); ?></span>
    </a>
</section>

<?php if ( ! empty( $recommended_products ) ) : ?>
    <section class="single-product-related ve-cart-recommendations" aria-labelledby="ve-cart-recommendations-title">
        <header class="single-product-related__header">
            <span aria-hidden="true"></span>
            <h2 id="ve-cart-recommendations-title">
                <?php esc_html_e( 'Productos que podrían gustarte', 'sultana-storefront' ); ?>
            </h2>
            <span aria-hidden="true"></span>
        </header>

        <div class="single-product-related__viewport">
            <?php if ( count( $recommended_products ) > 5 ) : ?>
                <button class="single-product-related__arrow single-product-related__arrow--prev" type="button" aria-label="<?php esc_attr_e( 'Ver productos anteriores', 'sultana-storefront' ); ?>" disabled>
                    <?php variedadesexpress_icon( 'chevron-left', 'single-product-related__arrow-icon' ); ?>
                </button>
            <?php endif; ?>

            <div class="single-product-related__track" data-cart-recommendations-track tabindex="0">
                <?php foreach ( $recommended_products as $recommended_product ) : ?>
                    <?php variedadesexpress_home_for_you_card( $recommended_product ); ?>
                <?php endforeach; ?>
            </div>

            <?php if ( count( $recommended_products ) > 5 ) : ?>
                <button class="single-product-related__arrow single-product-related__arrow--next" type="button" aria-label="<?php esc_attr_e( 'Ver más productos recomendados', 'sultana-storefront' ); ?>">
                    <?php variedadesexpress_icon( 'chevron-right', 'single-product-related__arrow-icon' ); ?>
                </button>
            <?php endif; ?>
        </div>
    </section>
<?php endif; ?>

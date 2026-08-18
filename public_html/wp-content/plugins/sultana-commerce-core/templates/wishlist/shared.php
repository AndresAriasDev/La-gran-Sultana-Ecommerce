<?php
/**
 * Core fallback template for shared wishlists.
 *
 * @package SultanaCommerceCore
 */

use Sultana\CommerceCore\Core\StoreBranding;
use Sultana\CommerceCore\Modules\Wishlist\Wishlist;

defined( 'ABSPATH' ) || exit;

$token = Wishlist::get_current_share_token();
$owner = Wishlist::get_user_by_share_token( $token );

if ( ! $owner ) {
    status_header( 404 );
}

$owner_id   = $owner ? (int) $owner->ID : 0;
$owner_name = $owner ? Wishlist::get_public_owner_display_name( $owner ) : __( 'alguien especial', 'sultana-commerce-core' );
$items      = $owner_id > 0 ? Wishlist::get_items( $owner_id ) : [];
$share_url  = $owner_id > 0 ? Wishlist::get_share_url( $owner_id ) : home_url( '/' );
$account_url = function_exists( 'wc_get_page_permalink' )
    ? wc_get_page_permalink( 'myaccount' )
    : '';

if ( ! is_string( $account_url ) || '' === $account_url ) {
    $account_url = wp_login_url( $share_url );
}

get_header();
?>

<style>
    .scc-shared-wishlist {
        max-width: 1120px;
        margin: 0 auto;
        padding: clamp(2rem, 5vw, 4rem) 1rem;
        color: #1f2933;
    }

    .scc-shared-wishlist__hero,
    .scc-shared-wishlist__notice,
    .scc-shared-wishlist-card {
        border: 1px solid #edf0f4;
        border-radius: 18px;
        background: #fff;
        box-shadow: 0 18px 45px rgba(31, 41, 51, 0.08);
    }

    .scc-shared-wishlist__hero {
        padding: clamp(1.5rem, 4vw, 2.5rem);
        margin-bottom: 1.5rem;
    }

    .scc-shared-wishlist__eyebrow {
        display: block;
        margin-bottom: 0.5rem;
        font-size: 0.78rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: <?php echo esc_html( StoreBranding::get_primary_color() ); ?>;
    }

    .scc-shared-wishlist h1,
    .scc-shared-wishlist h2,
    .scc-shared-wishlist p {
        margin-top: 0;
    }

    .scc-shared-wishlist h1 {
        margin-bottom: 0.75rem;
        font-size: clamp(2rem, 5vw, 3rem);
        line-height: 1.05;
    }

    .scc-shared-wishlist__intro {
        max-width: 680px;
        margin-bottom: 0;
        color: #5f6c7b;
        font-size: 1rem;
        line-height: 1.65;
    }

    .scc-shared-wishlist__notices {
        margin-bottom: 1rem;
    }

    .scc-shared-wishlist__notice {
        padding: clamp(1.25rem, 4vw, 2rem);
        text-align: center;
    }

    .scc-shared-wishlist__notice p {
        margin-bottom: 1.25rem;
        color: #5f6c7b;
    }

    .scc-shared-wishlist__grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(min(100%, 280px), 1fr));
        gap: 1rem;
    }

    .scc-shared-wishlist-card {
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    .scc-shared-wishlist-card__media {
        display: block;
        aspect-ratio: 1 / 1;
        background: #f7f8fa;
    }

    .scc-shared-wishlist-card__media img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }

    .scc-shared-wishlist-card__body {
        display: flex;
        flex: 1;
        flex-direction: column;
        gap: 0.85rem;
        padding: 1rem;
    }

    .scc-shared-wishlist-card__title {
        color: #111827;
        font-size: 1rem;
        font-weight: 700;
        line-height: 1.35;
        text-decoration: none;
    }

    .scc-shared-wishlist-card__options {
        display: flex;
        flex-wrap: wrap;
        gap: 0.45rem;
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .scc-shared-wishlist-card__options li,
    .scc-shared-wishlist-card__status {
        display: inline-flex;
        gap: 0.3rem;
        align-items: center;
        width: fit-content;
        border: 1px solid #e6e9ef;
        border-radius: 999px;
        padding: 0.35rem 0.6rem;
        color: #516070;
        font-size: 0.83rem;
        line-height: 1.2;
    }

    .scc-shared-wishlist-card__options strong {
        color: #1f2933;
    }

    .scc-shared-wishlist-card__price {
        margin-top: auto;
        color: #111827;
        font-weight: 700;
    }

    .scc-shared-wishlist-card__actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.65rem;
        align-items: center;
        margin-top: 0.25rem;
    }

    .scc-shared-wishlist__button,
    .scc-shared-wishlist-card__button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 44px;
        border: 0;
        border-radius: 999px;
        padding: 0.75rem 1rem;
        background: <?php echo esc_html( StoreBranding::get_primary_color() ); ?>;
        color: #fff;
        font-weight: 700;
        line-height: 1;
        text-decoration: none;
        cursor: pointer;
    }

    .scc-shared-wishlist-card__button--secondary {
        border: 1px solid #d7dde5;
        background: #fff;
        color: #1f2933;
    }

    .scc-shared-wishlist-card__button:disabled {
        opacity: 0.55;
        cursor: not-allowed;
    }

    .scc-shared-wishlist-card__gift-form {
        margin: 0;
    }

    @media (max-width: 640px) {
        .scc-shared-wishlist {
            padding-inline: 0.85rem;
        }

        .scc-shared-wishlist-card__actions {
            align-items: stretch;
            flex-direction: column;
        }

        .scc-shared-wishlist-card__button,
        .scc-shared-wishlist-card__gift-form {
            width: 100%;
        }
    }
</style>

<main id="primary" class="site-main">
    <div class="scc-shared-wishlist">
        <?php if ( function_exists( 'wc_print_notices' ) && function_exists( 'wc_notice_count' ) && wc_notice_count() > 0 ) : ?>
            <div class="scc-shared-wishlist__notices">
                <?php wc_print_notices(); ?>
            </div>
        <?php endif; ?>

        <section class="scc-shared-wishlist__hero">
            <span class="scc-shared-wishlist__eyebrow"><?php esc_html_e( 'Lista compartida', 'sultana-commerce-core' ); ?></span>
            <h1>
                <?php
                echo esc_html(
                    sprintf(
                        /* translators: %s: wishlist owner display name. */
                        __( 'Lista de deseos de %s', 'sultana-commerce-core' ),
                        $owner_name
                    )
                );
                ?>
            </h1>
            <p class="scc-shared-wishlist__intro">
                <?php esc_html_e( 'Elegí un producto de esta lista para enviarlo como regalo. La compra se gestionará con el carrito de WooCommerce.', 'sultana-commerce-core' ); ?>
            </p>
        </section>

        <?php if ( ! $owner ) : ?>
            <section class="scc-shared-wishlist__notice">
                <h2><?php esc_html_e( 'Esta lista no está disponible', 'sultana-commerce-core' ); ?></h2>
                <p><?php esc_html_e( 'El enlace puede haber cambiado o ya no existe.', 'sultana-commerce-core' ); ?></p>
                <a class="scc-shared-wishlist__button" href="<?php echo esc_url( home_url( '/' ) ); ?>">
                    <?php esc_html_e( 'Volver al inicio', 'sultana-commerce-core' ); ?>
                </a>
            </section>
        <?php elseif ( ! is_user_logged_in() ) : ?>
            <section class="scc-shared-wishlist__notice">
                <h2><?php esc_html_e( 'Iniciá sesión para ver esta lista', 'sultana-commerce-core' ); ?></h2>
                <p><?php esc_html_e( 'Así protegemos la información de la persona que compartió su lista de deseos.', 'sultana-commerce-core' ); ?></p>
                <a class="scc-shared-wishlist__button" href="<?php echo esc_url( $account_url ); ?>">
                    <?php esc_html_e( 'Iniciar sesión', 'sultana-commerce-core' ); ?>
                </a>
            </section>
        <?php elseif ( empty( $items ) ) : ?>
            <section class="scc-shared-wishlist__notice">
                <h2><?php esc_html_e( 'Esta lista está vacía', 'sultana-commerce-core' ); ?></h2>
                <p><?php esc_html_e( 'Todavía no hay productos guardados para regalar.', 'sultana-commerce-core' ); ?></p>
            </section>
        <?php else : ?>
            <section class="scc-shared-wishlist__grid" aria-label="<?php esc_attr_e( 'Productos de la lista de deseos', 'sultana-commerce-core' ); ?>">
                <?php foreach ( $items as $item_key => $item ) : ?>
                    <?php
                    $wishlist_key      = sanitize_text_field( (string) ( $item['key'] ?? $item_key ) );
                    $product_id        = absint( $item['product_id'] ?? 0 );
                    $variation_id      = absint( $item['variation_id'] ?? 0 );
                    $product           = function_exists( 'wc_get_product' ) ? wc_get_product( $variation_id ?: $product_id ) : false;
                    $parent            = $variation_id && function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : $product;

                    if ( $variation_id && ! $parent && $product && $product->is_type( 'variation' ) ) {
                        $parent = wc_get_product( (int) $product->get_parent_id() );
                    }
                    $product_is_valid  = $product && $parent && 'publish' === $parent->get_status();
                    $product_is_giftable = $product_is_valid && $product->is_purchasable() && $product->is_in_stock();
                    $permalink         = $product_is_valid ? get_permalink( $product_id ) : '';
                    $options           = $product_is_valid ? Wishlist::get_item_variation_options( $item ) : [];
                    $is_in_cart        = $product_is_valid ? Wishlist::is_wishlist_item_in_gift_cart( $token, $wishlist_key ) : false;
                    $title             = $parent ? $parent->get_name() : __( 'Producto no disponible', 'sultana-commerce-core' );
                    $image             = '';

                    if ( $product_is_valid ) {
                        $image_id = $product->get_image_id() ?: $parent->get_image_id();
                        $image    = $image_id
                            ? wp_get_attachment_image( $image_id, 'woocommerce_thumbnail', false, [ 'class' => 'scc-shared-wishlist-card__image' ] )
                            : ( function_exists( 'wc_placeholder_img' ) ? wc_placeholder_img( 'woocommerce_thumbnail', [ 'class' => 'scc-shared-wishlist-card__image' ] ) : '' );
                    }
                    ?>

                    <article class="scc-shared-wishlist-card">
                        <?php if ( $product_is_valid && '' !== $permalink ) : ?>
                            <a class="scc-shared-wishlist-card__media" href="<?php echo esc_url( $permalink ); ?>">
                                <?php echo wp_kses_post( $image ); ?>
                            </a>
                        <?php else : ?>
                            <div class="scc-shared-wishlist-card__media" aria-hidden="true">
                                <?php echo wp_kses_post( $image ); ?>
                            </div>
                        <?php endif; ?>

                        <div class="scc-shared-wishlist-card__body">
                            <?php if ( $product_is_valid && '' !== $permalink ) : ?>
                                <a class="scc-shared-wishlist-card__title" href="<?php echo esc_url( $permalink ); ?>">
                                    <?php echo esc_html( $title ); ?>
                                </a>
                            <?php else : ?>
                                <strong class="scc-shared-wishlist-card__title"><?php echo esc_html( $title ); ?></strong>
                            <?php endif; ?>

                            <?php if ( ! empty( $options ) ) : ?>
                                <ul class="scc-shared-wishlist-card__options" aria-label="<?php esc_attr_e( 'Opciones seleccionadas', 'sultana-commerce-core' ); ?>">
                                    <?php foreach ( $options as $option ) : ?>
                                        <li>
                                            <span><?php echo esc_html( $option['label'] ); ?>:</span>
                                            <strong><?php echo esc_html( $option['value'] ); ?></strong>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php elseif ( $parent && $parent->is_type( 'variable' ) ) : ?>
                                <span class="scc-shared-wishlist-card__status">
                                    <?php esc_html_e( 'Variación no disponible', 'sultana-commerce-core' ); ?>
                                </span>
                            <?php endif; ?>

                            <?php if ( $product_is_valid ) : ?>
                                <span class="scc-shared-wishlist-card__price">
                                    <?php echo wp_kses_post( $product->get_price_html() ); ?>
                                </span>
                            <?php else : ?>
                                <span class="scc-shared-wishlist-card__status">
                                    <?php esc_html_e( 'Este producto ya no está disponible.', 'sultana-commerce-core' ); ?>
                                </span>
                            <?php endif; ?>

                            <div class="scc-shared-wishlist-card__actions">
                                <?php if ( $product_is_valid && '' !== $permalink ) : ?>
                                    <a class="scc-shared-wishlist-card__button scc-shared-wishlist-card__button--secondary" href="<?php echo esc_url( $permalink ); ?>">
                                        <?php esc_html_e( 'Ver producto', 'sultana-commerce-core' ); ?>
                                    </a>
                                <?php endif; ?>

                                <?php if ( $product_is_valid && $is_in_cart && function_exists( 'wc_get_cart_url' ) ) : ?>
                                    <a class="scc-shared-wishlist-card__button" href="<?php echo esc_url( wc_get_cart_url() ); ?>">
                                        <?php esc_html_e( 'Ir al carrito', 'sultana-commerce-core' ); ?>
                                    </a>
                                <?php elseif ( $product_is_giftable ) : ?>
                                    <form class="scc-shared-wishlist-card__gift-form" method="post" action="<?php echo esc_url( $share_url ); ?>">
                                        <input type="hidden" name="scc_wishlist_gift_action" value="add_to_cart">
                                        <input type="hidden" name="wishlist_token" value="<?php echo esc_attr( $token ); ?>">
                                        <input type="hidden" name="wishlist_item_key" value="<?php echo esc_attr( $wishlist_key ); ?>">
                                        <?php wp_nonce_field( 'scc_wishlist_gift_' . $wishlist_key ); ?>
                                        <button class="scc-shared-wishlist-card__button" type="submit">
                                            <?php esc_html_e( 'Regalar', 'sultana-commerce-core' ); ?>
                                        </button>
                                    </form>
                                <?php elseif ( $product_is_valid ) : ?>
                                    <button class="scc-shared-wishlist-card__button" type="button" disabled>
                                        <?php esc_html_e( 'No disponible', 'sultana-commerce-core' ); ?>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    </div>
</main>

<?php
get_footer();

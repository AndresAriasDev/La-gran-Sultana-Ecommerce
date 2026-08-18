<?php
/**
 * Core fallback template for the account wishlist endpoint.
 *
 * @package SultanaCommerceCore
 */

use Sultana\CommerceCore\Core\StoreBranding;
use Sultana\CommerceCore\Modules\Wishlist\Wishlist;

defined( 'ABSPATH' ) || exit;

$user_id      = get_current_user_id();
$items        = $user_id > 0 ? Wishlist::get_items( $user_id ) : [];
$share_url    = $user_id > 0 ? Wishlist::get_share_url( $user_id ) : '';
$per_page     = 12;
$total_items  = count( $items );
$total_pages  = $total_items > 0 ? (int) ceil( $total_items / $per_page ) : 1;
$raw_page     = $_GET['wishlist_page'] ?? null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$request_page = is_scalar( $raw_page ) ? absint( wp_unslash( $raw_page ) ) : 1;
$current_page = min( max( 1, $request_page ), $total_pages );
$offset       = ( $current_page - 1 ) * $per_page;
$paged_items  = array_slice( $items, $offset, $per_page, true );
$wishlist_url = function_exists( 'wc_get_account_endpoint_url' ) ? wc_get_account_endpoint_url( Wishlist::ENDPOINT ) : '';
$shop_url     = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );

if ( ! is_string( $wishlist_url ) || '' === $wishlist_url ) {
    $wishlist_url = home_url( '/' );
}
?>

<style>
    .scc-account-wishlist {
        color: #1f2933;
    }

    .scc-account-wishlist__header,
    .scc-account-wishlist__empty,
    .scc-account-wishlist-card {
        border: 1px solid #edf0f4;
        border-radius: 16px;
        background: #fff;
        box-shadow: 0 14px 36px rgba(31, 41, 51, 0.07);
    }

    .scc-account-wishlist__header,
    .scc-account-wishlist__empty {
        padding: clamp(1.25rem, 3vw, 2rem);
        margin-bottom: 1rem;
    }

    .scc-account-wishlist__eyebrow {
        display: block;
        margin-bottom: 0.35rem;
        color: <?php echo esc_html( StoreBranding::get_primary_color() ); ?>;
        font-size: 0.78rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .scc-account-wishlist h1,
    .scc-account-wishlist h2,
    .scc-account-wishlist p {
        margin-top: 0;
    }

    .scc-account-wishlist h1 {
        margin-bottom: 0.45rem;
        font-size: clamp(1.8rem, 4vw, 2.45rem);
        line-height: 1.08;
    }

    .scc-account-wishlist__header p,
    .scc-account-wishlist__empty p {
        color: #5f6c7b;
        line-height: 1.6;
    }

    .scc-account-wishlist__actions,
    .scc-account-wishlist-card__actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.65rem;
        align-items: center;
    }

    .scc-account-wishlist__grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(min(100%, 260px), 1fr));
        gap: 1rem;
    }

    .scc-account-wishlist-card {
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    .scc-account-wishlist-card__media {
        display: block;
        aspect-ratio: 1 / 1;
        background: #f7f8fa;
    }

    .scc-account-wishlist-card__media img {
        display: block;
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .scc-account-wishlist-card__body {
        display: flex;
        flex: 1;
        flex-direction: column;
        gap: 0.8rem;
        padding: 1rem;
    }

    .scc-account-wishlist-card__title {
        color: #111827;
        font-weight: 700;
        line-height: 1.35;
        text-decoration: none;
    }

    .scc-account-wishlist-card__options {
        display: flex;
        flex-wrap: wrap;
        gap: 0.45rem;
        margin: 0;
        padding: 0;
        list-style: none;
    }

    .scc-account-wishlist-card__options li,
    .scc-account-wishlist-card__status {
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

    .scc-account-wishlist-card__options strong {
        color: #1f2933;
    }

    .scc-account-wishlist-card__price {
        margin-top: auto;
        font-weight: 700;
    }

    .scc-account-wishlist__button,
    .scc-account-wishlist-card__button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 42px;
        border: 0;
        border-radius: 999px;
        padding: 0.7rem 1rem;
        background: <?php echo esc_html( StoreBranding::get_primary_color() ); ?>;
        color: #fff;
        font-weight: 700;
        text-decoration: none;
        cursor: pointer;
    }

    .scc-account-wishlist-card__button--secondary {
        border: 1px solid #d7dde5;
        background: #fff;
        color: #1f2933;
    }

    .scc-account-wishlist-card__button:disabled {
        opacity: 0.55;
        cursor: not-allowed;
    }

    .scc-account-wishlist-card__form {
        margin: 0;
    }

    .scc-account-wishlist__pagination {
        display: flex;
        justify-content: flex-end;
        margin: 1rem 0;
    }

    .scc-account-wishlist__pagination .nav-links {
        display: inline-flex;
        flex-wrap: wrap;
        gap: 0.35rem;
        align-items: center;
    }

    .scc-account-wishlist__pagination a,
    .scc-account-wishlist__pagination span {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 2.35rem;
        min-height: 2.35rem;
        border: 1px solid #d7dde5;
        border-radius: 999px;
        padding: 0 0.75rem;
        color: #1f2933;
        text-decoration: none;
    }

    .scc-account-wishlist__pagination .current {
        border-color: <?php echo esc_html( StoreBranding::get_primary_color() ); ?>;
        background: <?php echo esc_html( StoreBranding::get_primary_color() ); ?>;
        color: #fff;
    }

    @media (max-width: 640px) {
        .scc-account-wishlist-card__actions {
            align-items: stretch;
            flex-direction: column;
        }

        .scc-account-wishlist-card__button,
        .scc-account-wishlist-card__form {
            width: 100%;
        }
    }
</style>

<div class="scc-account-wishlist">
    <?php if ( function_exists( 'wc_print_notices' ) && function_exists( 'wc_notice_count' ) && wc_notice_count() > 0 ) : ?>
        <div class="scc-account-wishlist__notices">
            <?php wc_print_notices(); ?>
        </div>
    <?php endif; ?>

    <section class="scc-account-wishlist__header">
        <span class="scc-account-wishlist__eyebrow"><?php esc_html_e( 'Lista de deseos', 'sultana-commerce-core' ); ?></span>
        <h1><?php esc_html_e( 'Tus favoritos', 'sultana-commerce-core' ); ?></h1>
        <p><?php esc_html_e( 'Guardá productos para comprarlos más adelante o compartirlos como idea de regalo.', 'sultana-commerce-core' ); ?></p>

        <?php if ( '' !== $share_url ) : ?>
            <p><strong><?php esc_html_e( 'Enlace para compartir:', 'sultana-commerce-core' ); ?></strong> <code><?php echo esc_html( $share_url ); ?></code></p>
        <?php endif; ?>
    </section>

    <?php if ( empty( $items ) ) : ?>
        <section class="scc-account-wishlist__empty">
            <h2><?php esc_html_e( 'Tu lista de deseos está vacía.', 'sultana-commerce-core' ); ?></h2>
            <p><?php esc_html_e( 'Agregá productos desde la tienda para encontrarlos aquí.', 'sultana-commerce-core' ); ?></p>
            <a class="scc-account-wishlist__button" href="<?php echo esc_url( $shop_url ); ?>">
                <?php esc_html_e( 'Explorar productos', 'sultana-commerce-core' ); ?>
            </a>
        </section>
    <?php else : ?>
        <?php
        if ( $total_pages > 1 ) {
            $pagination_base = str_replace(
                '999999999',
                '%#%',
                esc_url( add_query_arg( 'wishlist_page', '999999999', $wishlist_url ) )
            );
            $links = paginate_links(
                [
                    'base'      => $pagination_base,
                    'format'    => '',
                    'current'   => $current_page,
                    'total'     => $total_pages,
                    'type'      => 'array',
                    'prev_text' => '&larr;',
                    'next_text' => '&rarr;',
                ]
            );

            if ( is_array( $links ) ) {
                $page_one_url = esc_url( add_query_arg( 'wishlist_page', 1, $wishlist_url ) );
                $clean_url    = esc_url( remove_query_arg( 'wishlist_page', $wishlist_url ) );
                $links        = array_map(
                    static function ( string $link ) use ( $page_one_url, $clean_url ): string {
                        return str_replace( $page_one_url, $clean_url, $link );
                    },
                    $links
                );

                echo '<nav class="scc-account-wishlist__pagination" aria-label="' . esc_attr__( 'Paginación de lista de deseos', 'sultana-commerce-core' ) . '"><div class="nav-links">' . wp_kses_post( implode( '', $links ) ) . '</div></nav>';
            }
        }
        ?>

        <section class="scc-account-wishlist__grid" aria-label="<?php esc_attr_e( 'Productos guardados', 'sultana-commerce-core' ); ?>">
            <?php foreach ( $paged_items as $item_key => $item ) : ?>
                <?php
                $key              = sanitize_text_field( (string) ( $item['key'] ?? $item_key ) );
                $product_id       = absint( $item['product_id'] ?? 0 );
                $variation_id     = absint( $item['variation_id'] ?? 0 );
                $product          = function_exists( 'wc_get_product' ) ? wc_get_product( $variation_id ?: $product_id ) : false;
                $parent           = $variation_id && function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : $product;

                if ( $variation_id && ! $parent && $product && $product->is_type( 'variation' ) ) {
                    $parent = wc_get_product( (int) $product->get_parent_id() );
                }

                $is_valid       = $product && $parent && 'publish' === $parent->get_status();
                $can_add        = $is_valid && $product->is_purchasable() && $product->is_in_stock() && ( ! $parent->is_type( 'variable' ) || $variation_id );
                $permalink      = $is_valid ? get_permalink( $parent->get_id() ) : '';
                $options        = $is_valid ? Wishlist::get_item_variation_options( $item ) : [];
                $title          = $parent ? $parent->get_name() : __( 'Producto no disponible', 'sultana-commerce-core' );
                $image          = '';

                if ( $is_valid ) {
                    $image_id = $product->get_image_id() ?: $parent->get_image_id();
                    $image    = $image_id
                        ? wp_get_attachment_image( $image_id, 'woocommerce_thumbnail', false, [ 'class' => 'scc-account-wishlist-card__image' ] )
                        : ( function_exists( 'wc_placeholder_img' ) ? wc_placeholder_img( 'woocommerce_thumbnail', [ 'class' => 'scc-account-wishlist-card__image' ] ) : '' );
                }
                ?>
                <article class="scc-account-wishlist-card">
                    <?php if ( $is_valid && '' !== $permalink ) : ?>
                        <a class="scc-account-wishlist-card__media" href="<?php echo esc_url( $permalink ); ?>">
                            <?php echo wp_kses_post( $image ); ?>
                        </a>
                    <?php else : ?>
                        <div class="scc-account-wishlist-card__media" aria-hidden="true">
                            <?php echo wp_kses_post( $image ); ?>
                        </div>
                    <?php endif; ?>

                    <div class="scc-account-wishlist-card__body">
                        <?php if ( $is_valid && '' !== $permalink ) : ?>
                            <a class="scc-account-wishlist-card__title" href="<?php echo esc_url( $permalink ); ?>">
                                <?php echo esc_html( $title ); ?>
                            </a>
                        <?php else : ?>
                            <strong class="scc-account-wishlist-card__title"><?php echo esc_html( $title ); ?></strong>
                        <?php endif; ?>

                        <?php if ( ! empty( $options ) ) : ?>
                            <ul class="scc-account-wishlist-card__options" aria-label="<?php esc_attr_e( 'Opciones seleccionadas', 'sultana-commerce-core' ); ?>">
                                <?php foreach ( $options as $option ) : ?>
                                    <li>
                                        <span><?php echo esc_html( $option['label'] ); ?>:</span>
                                        <strong><?php echo esc_html( $option['value'] ); ?></strong>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php elseif ( $parent && $parent->is_type( 'variable' ) ) : ?>
                            <span class="scc-account-wishlist-card__status">
                                <?php esc_html_e( 'Variación no disponible', 'sultana-commerce-core' ); ?>
                            </span>
                        <?php endif; ?>

                        <?php if ( $is_valid ) : ?>
                            <span class="scc-account-wishlist-card__price">
                                <?php echo wp_kses_post( $product->get_price_html() ); ?>
                            </span>
                        <?php else : ?>
                            <span class="scc-account-wishlist-card__status">
                                <?php esc_html_e( 'Este producto ya no está disponible.', 'sultana-commerce-core' ); ?>
                            </span>
                        <?php endif; ?>

                        <div class="scc-account-wishlist-card__actions">
                            <?php if ( $can_add ) : ?>
                                <form class="scc-account-wishlist-card__form" method="post">
                                    <input type="hidden" name="scc_account_wishlist_action" value="add_to_cart">
                                    <input type="hidden" name="wishlist_item_key" value="<?php echo esc_attr( $key ); ?>">
                                    <?php wp_nonce_field( 'scc_account_wishlist_add_to_cart_' . $key ); ?>
                                    <button class="scc-account-wishlist-card__button" type="submit">
                                        <?php esc_html_e( 'Añadir al carrito', 'sultana-commerce-core' ); ?>
                                    </button>
                                </form>
                            <?php elseif ( $is_valid ) : ?>
                                <button class="scc-account-wishlist-card__button" type="button" disabled>
                                    <?php esc_html_e( 'No disponible', 'sultana-commerce-core' ); ?>
                                </button>
                            <?php endif; ?>

                            <form class="scc-account-wishlist-card__form" method="post">
                                <input type="hidden" name="scc_account_wishlist_action" value="remove">
                                <input type="hidden" name="wishlist_item_key" value="<?php echo esc_attr( $key ); ?>">
                                <?php wp_nonce_field( 'scc_account_wishlist_remove_' . $key ); ?>
                                <button class="scc-account-wishlist-card__button scc-account-wishlist-card__button--secondary" type="submit">
                                    <?php esc_html_e( 'Quitar', 'sultana-commerce-core' ); ?>
                                </button>
                            </form>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
</div>

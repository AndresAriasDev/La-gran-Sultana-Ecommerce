<?php
/**
 * Customer wishlist endpoint.
 *
 * @package SultanaStorefront
 */

defined( 'ABSPATH' ) || exit;

$user_id        = get_current_user_id();
$wishlist       = '\Sultana\CommerceCore\Modules\Wishlist\Wishlist';
$items          = class_exists( $wishlist ) ? $wishlist::get_items( $user_id ) : [];
$share_url      = class_exists( $wishlist ) ? $wishlist::get_share_url( $user_id ) : '';
$per_page       = 12;
$total_items    = count( $items );
$total_pages    = $total_items > 0 ? (int) ceil( $total_items / $per_page ) : 1;
$raw_wishlist_page = $_GET['wishlist_page'] ?? null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$requested_page    = is_scalar( $raw_wishlist_page ) ? absint( wp_unslash( $raw_wishlist_page ) ) : 1;
$current_page   = min( max( 1, $requested_page ), $total_pages );
$wishlist_url   = class_exists( 'WooCommerce' ) ? wc_get_account_endpoint_url( 'wishlist' ) : '';
$offset         = ( $current_page - 1 ) * $per_page;
$paged_items    = array_slice( $items, $offset, $per_page, true );
$wishlist_pagination = '';

if ( $total_pages > 1 ) {
    $pagination_base = str_replace(
        '999999999',
        '%#%',
        esc_url( add_query_arg( 'wishlist_page', '999999999', $wishlist_url ) )
    );

    $pagination_links = paginate_links(
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

    if ( is_array( $pagination_links ) ) {
        $page_one_url = esc_url( add_query_arg( 'wishlist_page', 1, $wishlist_url ) );
        $clean_url    = esc_url( remove_query_arg( 'wishlist_page', $wishlist_url ) );

        $pagination_links = array_map(
            static function ( string $link ) use ( $page_one_url, $clean_url ): string {
                return str_replace( $page_one_url, $clean_url, $link );
            },
            $pagination_links
        );

        $wishlist_pagination = sprintf(
            '<nav class="ve-wishlist-pagination shop-page__pagination" aria-label="%1$s"><div class="navigation pagination"><div class="nav-links">%2$s</div></div></nav>',
            esc_attr__( 'Paginacion de lista de deseos', 'sultana-storefront' ),
            wp_kses_post( implode( '', $pagination_links ) )
        );
    }
}
?>

<div class="ve-wishlist-account">
    <div class="woocommerce-notices-wrapper" data-wishlist-feedback></div>

    <section class="ve-account-section-title ve-wishlist-title">
        <span aria-hidden="true"><?php variedadesexpress_icon( 'heart', 've-account-section-title__icon' ); ?></span>
        <div>
            <span><?php esc_html_e( 'Lista de deseos', 'sultana-storefront' ); ?></span>
            <h1><?php esc_html_e( 'Tus favoritos', 'sultana-storefront' ); ?></h1>
            <p><?php esc_html_e( 'Guardá productos para comprarlos o recibirlos de regalo.', 'sultana-storefront' ); ?></p>
        </div>

        <?php if ( $share_url ) : ?>
            <button class="ve-wishlist-share" type="button" data-copy-text="<?php echo esc_attr( $share_url ); ?>">
                <?php variedadesexpress_icon( 'copy', 've-wishlist-share__icon' ); ?>
                <span><?php esc_html_e( 'Copiar lista', 'sultana-storefront' ); ?></span>
            </button>
        <?php endif; ?>
    </section>

    <?php if ( empty( $items ) ) : ?>
        <section class="ve-account-empty ve-wishlist-empty">
            <span class="ve-account-empty__icon" aria-hidden="true"><?php variedadesexpress_icon( 'heart', 've-account-empty__svg' ); ?></span>
            <div>
                <h2><?php esc_html_e( 'Tu lista está vacía', 'sultana-storefront' ); ?></h2>
                <p><?php esc_html_e( 'Agregá productos desde su página de detalle para encontrarlos rápido aquí.', 'sultana-storefront' ); ?></p>
            </div>
            <a class="ve-account-empty__button" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>">
                <?php esc_html_e( 'Explorar productos', 'sultana-storefront' ); ?>
            </a>
        </section>
    <?php else : ?>
        <?php echo wp_kses_post( $wishlist_pagination ); ?>

        <section class="ve-wishlist-grid" data-wishlist-list>
            <?php foreach ( $paged_items as $item ) : ?>
                <?php
                $product_id   = absint( $item['product_id'] ?? 0 );
                $variation_id = absint( $item['variation_id'] ?? 0 );
                $product      = wc_get_product( $variation_id ?: $product_id );
                $parent       = $variation_id ? wc_get_product( $product_id ) : $product;

                if ( ! $product || ! $parent ) {
                    continue;
                }

                $image_id  = $product->get_image_id() ?: $parent->get_image_id();
                $image     = $image_id
                    ? wp_get_attachment_image( $image_id, 'woocommerce_thumbnail', false, [ 'class' => 've-wishlist-card__image' ] )
                    : wc_placeholder_img( 'woocommerce_thumbnail', [ 'class' => 've-wishlist-card__image' ] );
                $permalink = get_permalink( $product_id );
                $key       = sanitize_text_field( $item['key'] ?? '' );
                $options   = method_exists( $wishlist, 'get_item_variation_options' ) ? $wishlist::get_item_variation_options( $item ) : [];
                $can_add_to_cart = $key
                    && $product->is_purchasable()
                    && $product->is_in_stock()
                    && ( ! $parent->is_type( 'variable' ) || $variation_id );
                ?>
                <article class="ve-wishlist-card" data-wishlist-item="<?php echo esc_attr( $key ); ?>">
                    <a class="ve-wishlist-card__media" href="<?php echo esc_url( $permalink ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Ver %s', 'sultana-storefront' ), $parent->get_name() ) ); ?>">
                        <?php echo wp_kses_post( $image ); ?>
                        <span class="ve-wishlist-card__stock <?php echo $product->is_in_stock() ? 'is-in' : 'is-out'; ?>">
                            <?php echo esc_html( $product->is_in_stock() ? __( 'En existencia', 'sultana-storefront' ) : __( 'Agotado', 'sultana-storefront' ) ); ?>
                        </span>
                    </a>

                    <div class="ve-wishlist-card__body">
                        <a class="ve-wishlist-card__title" href="<?php echo esc_url( $permalink ); ?>">
                            <?php echo esc_html( $parent->get_name() ); ?>
                        </a>

                        <?php if ( ! empty( $options ) ) : ?>
                            <ul class="ve-wishlist-card__options" aria-label="<?php esc_attr_e( 'Opciones seleccionadas', 'sultana-storefront' ); ?>">
                                <?php foreach ( $options as $option ) : ?>
                                    <li>
                                        <?php echo esc_html( $option['value'] ); ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php elseif ( $parent && $parent->is_type( 'variable' ) ) : ?>
                            <p class="ve-wishlist-card__variation-missing">
                                <?php esc_html_e( 'Opciones no seleccionadas', 'sultana-storefront' ); ?>
                            </p>
                        <?php endif; ?>

                        <div class="ve-wishlist-card__meta">
                            <span class="ve-wishlist-card__price">
                                <?php echo wp_kses_post( $product->get_price_html() ); ?>
                            </span>

                            <?php if ( $can_add_to_cart ) : ?>
                                <button
                                    class="ve-wishlist-card__cart"
                                    type="button"
                                    data-wishlist-add-to-cart="<?php echo esc_attr( $key ); ?>"
                                    aria-label="<?php echo esc_attr( sprintf( __( 'Agregar %s al carrito', 'sultana-storefront' ), $parent->get_name() ) ); ?>"
                                >
                                    <?php variedadesexpress_icon( 'shopping-cart', 've-wishlist-card__cart-icon' ); ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <button
                        class="ve-wishlist-card__remove"
                        type="button"
                        data-wishlist-remove="<?php echo esc_attr( $key ); ?>"
                        aria-label="<?php echo esc_attr( sprintf( __( 'Eliminar %s de tu lista de deseos', 'sultana-storefront' ), $parent->get_name() ) ); ?>"
                    >
                        <span aria-hidden="true">&times;</span>
                    </button>
                </article>
            <?php endforeach; ?>
        </section>

        <?php echo wp_kses_post( $wishlist_pagination ); ?>
    <?php endif; ?>
</div>

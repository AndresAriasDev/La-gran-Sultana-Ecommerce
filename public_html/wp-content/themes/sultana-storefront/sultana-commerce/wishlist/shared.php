<?php
/**
 * Shared wishlist view.
 *
 * @package SultanaStorefront
 */

defined( 'ABSPATH' ) || exit;

$wishlist_class = '\Sultana\CommerceCore\Modules\Wishlist\Wishlist';
$token          = class_exists( $wishlist_class ) ? $wishlist_class::get_current_share_token() : '';
$owner          = class_exists( $wishlist_class ) ? $wishlist_class::get_user_by_share_token( $token ) : null;

if ( ! $owner ) {
    status_header( 404 );
}

$owner_id   = $owner ? (int) $owner->ID : 0;
$owner_name = $owner ? trim( (string) $owner->display_name ) : '';
$owner_name = $owner_name ?: __( 'alguien especial', 'sultana-storefront' );
$items      = $owner_id && class_exists( $wishlist_class ) ? $wishlist_class::get_items( $owner_id ) : [];
$is_owner   = is_user_logged_in() && get_current_user_id() === $owner_id;

get_header();
?>

<main id="primary" class="site-main">
    <div class="shared-wishlist page-content">
        <?php if ( function_exists( 'wc_print_notices' ) && function_exists( 'wc_notice_count' ) && wc_notice_count() > 0 ) : ?>
            <div class="shared-wishlist-notices">
                <?php wc_print_notices(); ?>
            </div>
        <?php endif; ?>

        <section class="shared-wishlist__hero">
            <span class="shared-wishlist__icon" aria-hidden="true"><?php variedadesexpress_icon( 'heart', 'shared-wishlist__svg' ); ?></span>
            <div>
                <span><?php esc_html_e( 'Lista compartida', 'sultana-storefront' ); ?></span>
                <h1>
                    <?php
                    echo esc_html(
                        sprintf(
                            /* translators: %s: wishlist owner display name. */
                            __( 'Lista de deseos de %s', 'sultana-storefront' ),
                            $owner_name
                        )
                    );
                    ?>
                </h1>
                <p><?php esc_html_e( 'Elegí productos de esta lista para regalar; se enviarán a la dirección de la persona propietaria de la lista de deseos.', 'sultana-storefront' ); ?></p>
            </div>
        </section>

        <?php if ( ! $owner ) : ?>
            <section class="shared-wishlist__notice">
                <h2><?php esc_html_e( 'Esta lista no está disponible', 'sultana-storefront' ); ?></h2>
                <p><?php esc_html_e( 'El enlace puede haber cambiado o ya no existe.', 'sultana-storefront' ); ?></p>
                <a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Volver al inicio', 'sultana-storefront' ); ?></a>
            </section>
        <?php elseif ( ! is_user_logged_in() ) : ?>
            <section class="shared-wishlist__notice shared-wishlist__notice--guest">
                <span class="shared-wishlist__notice-icon" aria-hidden="true"><?php variedadesexpress_icon( 'badge-question-mark', 'shared-wishlist__notice-svg' ); ?></span>
                <h2><?php esc_html_e( 'Creá tu cuenta para ver esta lista', 'sultana-storefront' ); ?></h2>
                <p><?php esc_html_e( 'Así protegemos la información de la persona que compartió su lista de deseos y mantenemos segura la experiencia de compra.', 'sultana-storefront' ); ?></p>
                <button type="button" data-modal-open="account" data-account-view="register">
                    <?php variedadesexpress_icon( 'user', 'shared-wishlist__button-icon' ); ?>
                    <span><?php esc_html_e( 'Crear cuenta', 'sultana-storefront' ); ?></span>
                </button>
            </section>
        <?php elseif ( empty( $items ) ) : ?>
            <section class="shared-wishlist__notice">
                <h2><?php esc_html_e( 'Esta lista está vacía', 'sultana-storefront' ); ?></h2>
                <p><?php esc_html_e( 'Todavía no hay productos guardados para regalar.', 'sultana-storefront' ); ?></p>
            </section>
        <?php else : ?>
            <?php if ( $is_owner ) : ?>
                <p class="shared-wishlist__owner-note">
                    <?php esc_html_e( 'Estás viendo tu propia lista compartida.', 'sultana-storefront' ); ?>
                </p>
            <?php endif; ?>

            <section class="shared-wishlist__grid" aria-label="<?php esc_attr_e( 'Productos de la lista de deseos', 'sultana-storefront' ); ?>">
                <?php foreach ( $items as $item ) : ?>
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
                        ? wp_get_attachment_image( $image_id, 'woocommerce_thumbnail', false, [ 'class' => 'shared-wishlist-card__image' ] )
                        : wc_placeholder_img( 'woocommerce_thumbnail', [ 'class' => 'shared-wishlist-card__image' ] );
                    $permalink = get_permalink( $product_id );
                    $item_key  = sanitize_text_field( $item['key'] ?? '' );
                    $options   = method_exists( $wishlist_class, 'get_item_variation_options' ) ? $wishlist_class::get_item_variation_options( $item ) : [];
                    $is_in_cart = method_exists( $wishlist_class, 'is_wishlist_item_in_gift_cart' ) ? $wishlist_class::is_wishlist_item_in_gift_cart( $token, $item_key ) : false;
                    ?>
                    <article class="shared-wishlist-card">
                        <a class="shared-wishlist-card__media" href="<?php echo esc_url( $permalink ); ?>">
                            <?php echo wp_kses_post( $image ); ?>
                        </a>
                        <div class="shared-wishlist-card__body">
                            <a class="shared-wishlist-card__title" href="<?php echo esc_url( $permalink ); ?>">
                                <?php echo esc_html( $parent->get_name() ); ?>
                            </a>

                            <?php if ( ! empty( $options ) ) : ?>
                                <ul class="shared-wishlist-card__options" aria-label="<?php esc_attr_e( 'Opciones seleccionadas', 'sultana-storefront' ); ?>">
                                    <?php foreach ( $options as $option ) : ?>
                                        <li>
                                            <span><?php echo esc_html( $option['label'] ); ?></span>
                                            <strong><?php echo esc_html( $option['value'] ); ?></strong>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php elseif ( $parent && $parent->is_type( 'variable' ) ) : ?>
                                <p class="shared-wishlist-card__variation-missing">
                                    <?php esc_html_e( 'Opciones no seleccionadas', 'sultana-storefront' ); ?>
                                </p>
                            <?php endif; ?>

                            <div class="shared-wishlist-card__footer">
                                <span class="shared-wishlist-card__price">
                                    <?php echo wp_kses_post( $product->get_price_html() ); ?>
                                </span>
                                <?php if ( $is_in_cart ) : ?>
                                    <a class="shared-wishlist-card__button" href="<?php echo esc_url( wc_get_cart_url() ); ?>">
                                        <?php variedadesexpress_icon( 'shopping-cart', 'shared-wishlist-card__button-icon' ); ?>
                                        <span><?php esc_html_e( 'Ir al carrito', 'sultana-storefront' ); ?></span>
                                    </a>
                                <?php else : ?>
                                    <form class="shared-wishlist-card__gift-form" method="post">
                                        <input type="hidden" name="scc_wishlist_gift_action" value="add_to_cart">
                                        <input type="hidden" name="wishlist_token" value="<?php echo esc_attr( $token ); ?>">
                                        <input type="hidden" name="wishlist_item_key" value="<?php echo esc_attr( $item_key ); ?>">
                                        <?php wp_nonce_field( 'scc_wishlist_gift_' . $item_key ); ?>
                                        <button class="shared-wishlist-card__button" type="submit">
                                            <?php variedadesexpress_icon( 'shopping-cart', 'shared-wishlist-card__button-icon' ); ?>
                                            <span><?php esc_html_e( 'Regalar', 'sultana-storefront' ); ?></span>
                                        </button>
                                    </form>
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

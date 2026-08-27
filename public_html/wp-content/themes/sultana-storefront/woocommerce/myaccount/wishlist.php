<?php
/**
 * Customer wishlist endpoint.
 *
 * @package SultanaStorefront
 */

defined( 'ABSPATH' ) || exit;

$user_id           = get_current_user_id();
$wishlist          = '\Sultana\CommerceCore\Modules\Wishlist\Wishlist';
$share_url         = class_exists( $wishlist ) ? $wishlist::get_share_url( $user_id ) : '';
$raw_wishlist_page = $_GET['wishlist_page'] ?? null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$requested_page    = is_scalar( $raw_wishlist_page ) ? absint( wp_unslash( $raw_wishlist_page ) ) : 1;
$wishlist_state    = variedadesexpress_account_wishlist_state( $user_id, $requested_page );
?>

<div class="ve-wishlist-account">
    <div class="woocommerce-notices-wrapper" data-wishlist-feedback></div>

    <section class="ve-account-section-title ve-wishlist-title">
        <span aria-hidden="true"><?php variedadesexpress_icon( 'heart', 've-account-section-title__icon' ); ?></span>
        <div>
            <h1><?php esc_html_e( 'Lista de deseos', 'sultana-storefront' ); ?></h1>
            <p><?php esc_html_e( 'Guardá productos para comprarlos o recibirlos de regalo.', 'sultana-storefront' ); ?></p>
        </div>

        <?php if ( $share_url ) : ?>
            <button class="ve-wishlist-share" type="button" data-copy-text="<?php echo esc_attr( $share_url ); ?>">
                <?php variedadesexpress_icon( 'copy', 've-wishlist-share__icon' ); ?>
                <span><?php esc_html_e( 'Copiar lista', 'sultana-storefront' ); ?></span>
            </button>
        <?php endif; ?>
    </section>

    <div class="ve-wishlist-content" data-wishlist-content>
        <?php echo variedadesexpress_account_wishlist_content_html( $wishlist_state ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    </div>
</div>

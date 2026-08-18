<?php
/**
 * Custom My Account layout.
 *
 * @package SultanaStorefront
 */

defined( 'ABSPATH' ) || exit;
?>

<div class="ve-account">
    <div class="ve-account__container">
        <?php do_action( 'woocommerce_account_navigation' ); ?>

        <main class="ve-account__content" aria-label="<?php esc_attr_e( 'Contenido de mi cuenta', 'sultana-storefront' ); ?>">
            <?php do_action( 'woocommerce_account_content' ); ?>
        </main>
    </div>
</div>

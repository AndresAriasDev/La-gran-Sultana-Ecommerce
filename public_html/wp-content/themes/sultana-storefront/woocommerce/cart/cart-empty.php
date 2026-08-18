<?php
/**
 * Empty cart page.
 *
 * @package SultanaStorefront
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_before_cart' );
?>

<div class="ve-cart-page">
    <?php get_template_part( 'template-parts/cart/empty-state' ); ?>
</div>

<?php do_action( 'woocommerce_after_cart' ); ?>

<?php
/**
 * Plugin Name: Sultana Commerce Core
 * Plugin URI: https://lagransultana.com
 * Description: Núcleo de funcionalidades para tiendas WooCommerce desarrolladas por Sultana.
 * Version: 1.0.0
 * Author: AndresAriasDev
 * Author URI: https://lagransultana.com
 * License: GPL v2 or later
 * Text Domain: sultana-commerce-core
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'SCC_VERSION', '1.0.0' );
define( 'SCC_PLUGIN_FILE', __FILE__ );
define( 'SCC_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'SCC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once SCC_PLUGIN_PATH . 'src/Core/Bootstrap.php';

Sultana\CommerceCore\Core\Bootstrap::init();

register_activation_hook(
    __FILE__,
    static function (): void {
        require_once SCC_PLUGIN_PATH . 'src/Modules/Wishlist/Wishlist.php';
        require_once SCC_PLUGIN_PATH . 'src/Modules/Shipping/Repositories/ShippingSettingsRepository.php';
        require_once SCC_PLUGIN_PATH . 'src/Modules/Shipping/Shipping.php';

        Sultana\CommerceCore\Modules\Wishlist\Wishlist::register_endpoint();
        Sultana\CommerceCore\Modules\Shipping\Shipping::activate();
        flush_rewrite_rules();
    }
);

register_deactivation_hook(
    __FILE__,
    static function (): void {
        flush_rewrite_rules();
    }
);

<?php
/**
 * Plugin Name: Sultana Admin
 * Plugin URI: https://lagransultana.com
 * Description: Panel operativo para gestores de La Gran Sultana.
 * Version: 0.1.0
 * Author: AndresAriasDev
 * Author URI: https://lagransultana.com
 * License: GPL v2 or later
 * Text Domain: sultana-admin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'SULTANA_ADMIN_VERSION', '0.1.0' );
define( 'SULTANA_ADMIN_FILE', __FILE__ );
define( 'SULTANA_ADMIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'SULTANA_ADMIN_URL', plugin_dir_url( __FILE__ ) );

require_once SULTANA_ADMIN_PATH . 'src/Core/Capabilities.php';
require_once SULTANA_ADMIN_PATH . 'src/Core/Icons.php';
require_once SULTANA_ADMIN_PATH . 'src/Core/Assets.php';
require_once SULTANA_ADMIN_PATH . 'src/Core/Auth.php';
require_once SULTANA_ADMIN_PATH . 'src/Products/ProductImageService.php';
require_once SULTANA_ADMIN_PATH . 'src/Products/ProductService.php';
require_once SULTANA_ADMIN_PATH . 'src/Products/ProductVariableService.php';
require_once SULTANA_ADMIN_PATH . 'src/Products/ProductComboService.php';
require_once SULTANA_ADMIN_PATH . 'src/Products/ProductController.php';
require_once SULTANA_ADMIN_PATH . 'src/Orders/OrderService.php';
require_once SULTANA_ADMIN_PATH . 'src/Orders/OrderController.php';
require_once SULTANA_ADMIN_PATH . 'src/Core/Router.php';
require_once SULTANA_ADMIN_PATH . 'src/Core/Bootstrap.php';

Sultana\Admin\Core\Bootstrap::init();

register_activation_hook(
    __FILE__,
    static function (): void {
        Sultana\Admin\Core\Capabilities::activate();
        Sultana\Admin\Core\Router::register_rewrite_rules();
        flush_rewrite_rules();
    }
);

register_deactivation_hook(
    __FILE__,
    static function (): void {
        flush_rewrite_rules();
    }
);

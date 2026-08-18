<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once get_template_directory() . '/inc/setup.php';
require_once get_template_directory() . '/inc/store-config.php';
require_once get_template_directory() . '/inc/migrations.php';
require_once get_template_directory() . '/inc/integrations/sultana-commerce-core.php';
require_once get_template_directory() . '/inc/assets.php';
require_once get_template_directory() . '/inc/menus.php';
require_once get_template_directory() . '/inc/sidebars.php';
require_once get_template_directory() . '/inc/woocommerce.php';
require_once get_template_directory() . '/inc/helpers.php';
require_once get_template_directory() . '/inc/home-recommendations.php';

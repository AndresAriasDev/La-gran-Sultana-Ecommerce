<?php

namespace Sultana\Admin\Core;

use Sultana\Admin\Products\ProductController;
use Sultana\Admin\Promotions\PromotionController;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Bootstrap
{
    public static function init(): void
    {
        add_action( 'init', [ Capabilities::class, 'ensure_role_capabilities' ], 5 );
        add_action( 'init', [ Router::class, 'register_rewrite_rules' ] );
        add_action( 'init', [ self::class, 'maybe_flush_rewrite_rules' ], 20 );
        add_filter( 'query_vars', [ Router::class, 'register_query_vars' ] );
        add_action( 'template_redirect', [ Router::class, 'handle_request' ], 0 );
        add_action( 'wp_enqueue_scripts', [ Assets::class, 'dequeue_frontend_assets' ], 1000 );
        add_action( 'admin_init', [ self::class, 'redirect_store_managers_from_wp_admin' ] );
        add_action( 'wp_ajax_' . ProductController::IMAGE_UPLOAD_ACTION, [ ProductController::class, 'ajax_upload_product_image' ] );
        add_action( 'wp_ajax_' . ProductController::IMAGE_DELETE_ACTION, [ ProductController::class, 'ajax_delete_product_image' ] );
        add_action( 'wp_ajax_' . ProductController::COMBO_COMPONENT_SEARCH_ACTION, [ ProductController::class, 'ajax_search_combo_components' ] );
        add_action( 'wp_ajax_' . PromotionController::IMAGE_UPLOAD_ACTION, [ PromotionController::class, 'ajax_upload_promotion_image' ] );
        add_action( 'wp_ajax_' . PromotionController::IMAGE_DELETE_ACTION, [ PromotionController::class, 'ajax_delete_promotion_image' ] );
    }

    public static function dependencies_status(): array
    {
        return [
            'woocommerce' => class_exists( 'WooCommerce' ),
            'commerce_core' => defined( 'SCC_VERSION' ) || class_exists( '\Sultana\CommerceCore\Core\Bootstrap' ),
        ];
    }

    public static function dependencies_available(): bool
    {
        $status = self::dependencies_status();

        return ! in_array( false, $status, true );
    }

    public static function maybe_flush_rewrite_rules(): void
    {
        $option_key = 'sultana_admin_rewrite_rules_version';
        $version    = ( defined( 'SULTANA_ADMIN_VERSION' ) ? SULTANA_ADMIN_VERSION : '1' ) . '-product-inventory-routes-v1';

        if ( get_option( $option_key ) === $version ) {
            return;
        }

        Router::register_rewrite_rules();
        flush_rewrite_rules();
        update_option( $option_key, $version, false );
    }

    public static function redirect_store_managers_from_wp_admin(): void
    {
        if ( ! is_user_logged_in() || ! current_user_can( Capabilities::ACCESS_CAPABILITY ) ) {
            return;
        }

        if ( current_user_can( 'manage_options' ) || wp_doing_ajax() ) {
            return;
        }

        global $pagenow;

        if ( in_array( (string) $pagenow, [ 'admin-ajax.php', 'async-upload.php', 'profile.php' ], true ) ) {
            return;
        }

        wp_safe_redirect( Router::dashboard_url() );
        exit;
    }
}

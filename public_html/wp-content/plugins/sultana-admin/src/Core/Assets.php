<?php

namespace Sultana\Admin\Core;

use Sultana\Admin\Products\ProductController;
use Sultana\Admin\Promotions\PromotionController;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Assets
{
    private const FRONTEND_STYLE_HANDLES = [
        'sultana-storefront-tokens',
        'sultana-storefront-reset',
        'sultana-storefront-global',
        'sultana-storefront-forms',
        'sultana-storefront-buttons',
        'sultana-storefront-header',
        'sultana-storefront-footer',
        'sultana-storefront-cards',
        'sultana-storefront-woocommerce',
        'sultana-storefront-home',
        'sultana-storefront-responsive',
        'sultana-storefront-shop',
        'sultana-storefront-single-product',
        'sultana-storefront-cart',
        'sultana-storefront-checkout',
        'sultana-storefront-account',
        'sultana-storefront-account-dashboard',
        'sultana-storefront-account-orders',
        'sultana-storefront-account-coupons',
        'sultana-storefront-wishlist',
        'woocommerce-layout',
        'woocommerce-smallscreen',
        'woocommerce-general',
        'wc-blocks-style',
    ];

    private const FRONTEND_SCRIPT_HANDLES = [
        'sultana-storefront-header',
        'sultana-storefront-toast',
        'sultana-storefront-account-modal',
        'sultana-storefront-home',
        'sultana-storefront-shop',
        'sultana-storefront-single-product',
        'sultana-storefront-cart',
        'sultana-storefront-checkout',
        'sultana-storefront-account',
        'wc-add-to-cart',
        'woocommerce',
        'jquery-blockui',
        'js-cookie',
        'wc-cart-fragments',
        'sourcebuster-js',
        'wc-order-attribution',
    ];

    public static function enqueue( string $route = '', array $screen_data = [] ): void
    {
        self::enqueue_style( 'admin' );
        self::enqueue_style( 'components', [ 'sultana-admin' ] );

        if ( in_array( $route, [ 'dashboard', 'products', 'product_new', 'product_edit', 'banners', 'banner_new', 'banner_edit', 'orders', 'order_view', 'customers', 'customer_view', 'coupons', 'coupon_new', 'coupon_edit', 'reviews', 'statistics' ], true ) ) {
            self::enqueue_style( 'shell', [ 'sultana-admin-components' ] );
            self::enqueue_shell();
        }

        if ( in_array( $route, [ 'login', 'password_request', 'password_reset' ], true ) ) {
            self::enqueue_login();
        }

        if ( 'products' === $route ) {
            self::enqueue_style( 'products', [ 'sultana-admin-shell' ] );
        }

        if ( in_array( $route, [ 'banners', 'banner_new', 'banner_edit' ], true ) ) {
            self::enqueue_style( 'banners', [ 'sultana-admin-shell' ] );
            self::enqueue_banners();
        }

        if ( in_array( $route, [ 'product_new', 'product_edit' ], true ) ) {
            self::enqueue_style( 'product-editor', [ 'sultana-admin-shell' ] );
        }

        if ( in_array( $route, [ 'orders', 'order_view' ], true ) ) {
            self::enqueue_style( 'orders', [ 'sultana-admin-shell' ] );
        }

        if ( in_array( $route, [ 'customers', 'customer_view' ], true ) ) {
            self::enqueue_style( 'customers', [ 'sultana-admin-shell' ] );
        }

        if ( in_array( $route, [ 'coupons', 'coupon_new', 'coupon_edit' ], true ) ) {
            self::enqueue_style( 'coupons', [ 'sultana-admin-shell' ] );
        }

        if ( 'reviews' === $route ) {
            self::enqueue_style( 'reviews', [ 'sultana-admin-shell' ] );
        }

        if ( in_array( $route, [ 'coupon_new', 'coupon_edit' ], true ) ) {
            self::enqueue_coupon_form();
        }

        if ( in_array( $route, [ 'dashboard', 'statistics' ], true ) ) {
            self::enqueue_style( 'statistics', [ 'sultana-admin-shell' ] );
            self::enqueue_statistics();
        }

        if ( in_array( $route, [ 'orders', 'customers', 'coupons', 'reviews' ], true ) ) {
            self::enqueue_product_list();
        }

        if ( 'reviews' === $route ) {
            self::enqueue_reviews();
        }

        if ( 'products' === $route ) {
            self::enqueue_admin_modal();
            self::enqueue_product_list( true );
            return;
        }

        if ( ! in_array( $route, [ 'product_new', 'product_edit' ], true ) ) {
            return;
        }

        $product_type = self::screen_product_type( $route, $screen_data );

        if ( 'variable' === $product_type ) {
            self::enqueue_style( 'product-variable', [ 'sultana-admin-product-editor' ] );
            self::enqueue_admin_modal();
        }

        if ( 'combo' === $product_type ) {
            self::enqueue_product_editor();
            self::enqueue_combo_editor();
            return;
        }

        if ( ! in_array( $product_type, [ 'simple', 'variable' ], true ) ) {
            return;
        }

        self::enqueue_product_editor();

        $js_path    = SULTANA_ADMIN_PATH . 'assets/js/product-images.js';
        $js_version = file_exists( $js_path ) ? (string) filemtime( $js_path ) : SULTANA_ADMIN_VERSION;

        wp_enqueue_script(
            'sultana-admin-product-images',
            SULTANA_ADMIN_URL . 'assets/js/product-images.js',
            [],
            $js_version,
            true
        );

        wp_localize_script(
            'sultana-admin-product-images',
            'SultanaAdminProductImages',
            [
                'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
                'nonce'        => wp_create_nonce( ProductController::IMAGE_UPLOAD_NONCE_ACTION ),
                'uploadAction' => ProductController::IMAGE_UPLOAD_ACTION,
                'deleteAction' => ProductController::IMAGE_DELETE_ACTION,
                'strings'      => [
                    'uploading'     => __( 'Subiendo imagenes...', 'sultana-admin' ),
                    'uploadBlocked' => __( 'Espera a que terminen de subir las imagenes.', 'sultana-admin' ),
                    'uploadError'   => __( 'No se pudo subir la imagen.', 'sultana-admin' ),
                    'deleteError'   => __( 'La imagen se quito de la seleccion, pero no se pudo eliminar el archivo temporal.', 'sultana-admin' ),
                    'cover'         => __( 'Portada', 'sultana-admin' ),
                    'moveLeft'      => __( 'Mover a la izquierda', 'sultana-admin' ),
                    'moveRight'     => __( 'Mover a la derecha', 'sultana-admin' ),
                    'remove'        => __( 'Eliminar imagen', 'sultana-admin' ),
                ],
                'icons'        => [
                    'chevronLeft'  => Icons::url( 'chevron-left' ),
                    'chevronRight' => Icons::url( 'chevron-right' ),
                    'trash'        => Icons::url( 'trash' ),
                ],
            ]
        );

        if ( 'variable' !== $product_type ) {
            return;
        }

        $variable_js_path    = SULTANA_ADMIN_PATH . 'assets/js/product-variables.js';
        $variable_js_version = file_exists( $variable_js_path ) ? (string) filemtime( $variable_js_path ) : SULTANA_ADMIN_VERSION;

        wp_enqueue_script(
            'sultana-admin-product-variables',
            SULTANA_ADMIN_URL . 'assets/js/product-variables.js',
            [ 'sultana-admin-modal' ],
            $variable_js_version,
            true
        );

        wp_localize_script(
            'sultana-admin-product-variables',
            'SultanaAdminProductVariables',
            [
                'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
                'nonce'        => wp_create_nonce( ProductController::IMAGE_UPLOAD_NONCE_ACTION ),
                'uploadAction' => ProductController::IMAGE_UPLOAD_ACTION,
                'strings'      => [
                    'selectAttribute' => __( 'Selecciona atributo', 'sultana-admin' ),
                    'chooseValues'    => __( 'Selecciona valores', 'sultana-admin' ),
                    'removeAttribute' => __( 'Quitar atributo', 'sultana-admin' ),
                    'uploadImage'     => __( 'Imagen', 'sultana-admin' ),
                    'removeImage'     => __( 'Quitar imagen', 'sultana-admin' ),
                    'uploading'       => __( 'Subiendo imagen...', 'sultana-admin' ),
                    'uploadError'     => __( 'No se pudo subir la imagen.', 'sultana-admin' ),
                    'generateFirst'   => __( 'Genera variaciones para completar sus datos.', 'sultana-admin' ),
                ],
                'icons'        => [
                    'chevron' => Icons::url( 'chevron-right' ),
                    'images'  => Icons::url( 'images' ),
                    'trash'   => Icons::url( 'trash' ),
                ],
            ]
        );

    }

    private static function enqueue_style( string $name, array $dependencies = [] ): void
    {
        $path    = SULTANA_ADMIN_PATH . 'assets/css/' . $name . '.css';
        $version = file_exists( $path ) ? (string) filemtime( $path ) : SULTANA_ADMIN_VERSION;
        $handle  = 'admin' === $name ? 'sultana-admin' : 'sultana-admin-' . $name;

        wp_enqueue_style(
            $handle,
            SULTANA_ADMIN_URL . 'assets/css/' . $name . '.css',
            $dependencies,
            $version
        );
    }

    private static function enqueue_admin_modal(): void
    {
        $js_path    = SULTANA_ADMIN_PATH . 'assets/js/admin-modal.js';
        $js_version = file_exists( $js_path ) ? (string) filemtime( $js_path ) : SULTANA_ADMIN_VERSION;

        wp_enqueue_script(
            'sultana-admin-modal',
            SULTANA_ADMIN_URL . 'assets/js/admin-modal.js',
            [],
            $js_version,
            true
        );
    }

    private static function enqueue_product_list( bool $with_delete_modal = false ): void
    {
        $js_path    = SULTANA_ADMIN_PATH . 'assets/js/product-list.js';
        $js_version = file_exists( $js_path ) ? (string) filemtime( $js_path ) : SULTANA_ADMIN_VERSION;

        wp_enqueue_script(
            'sultana-admin-product-list',
            SULTANA_ADMIN_URL . 'assets/js/product-list.js',
            $with_delete_modal ? [ 'sultana-admin-modal' ] : [],
            $js_version,
            true
        );

        if ( ! $with_delete_modal ) {
            return;
        }

        wp_localize_script(
            'sultana-admin-product-list',
            'SultanaAdminProductList',
            [
                'strings' => [
                    'deleteProductTitle'   => __( 'Eliminar producto', 'sultana-admin' ),
                    'deleteProductMessage' => __( '¿Quieres eliminar este producto?', 'sultana-admin' ),
                    'deleteProductDetail'  => __( 'El producto será enviado a la papelera y dejará de mostrarse en la tienda.', 'sultana-admin' ),
                    'deleteProductConfirm' => __( 'Eliminar producto', 'sultana-admin' ),
                    'cancel'               => __( 'Cancelar', 'sultana-admin' ),
                ],
            ]
        );
    }

    private static function enqueue_login(): void
    {
        $js_path    = SULTANA_ADMIN_PATH . 'assets/js/login.js';
        $js_version = file_exists( $js_path ) ? (string) filemtime( $js_path ) : SULTANA_ADMIN_VERSION;

        wp_enqueue_script(
            'sultana-admin-login',
            SULTANA_ADMIN_URL . 'assets/js/login.js',
            [],
            $js_version,
            true
        );
    }

    private static function enqueue_reviews(): void
    {
        $js_path    = SULTANA_ADMIN_PATH . 'assets/js/reviews.js';
        $js_version = file_exists( $js_path ) ? (string) filemtime( $js_path ) : SULTANA_ADMIN_VERSION;

        wp_enqueue_script(
            'sultana-admin-reviews',
            SULTANA_ADMIN_URL . 'assets/js/reviews.js',
            [ 'sultana-admin-product-list' ],
            $js_version,
            true
        );
    }

    private static function enqueue_shell(): void
    {
        $js_path    = SULTANA_ADMIN_PATH . 'assets/js/shell.js';
        $js_version = file_exists( $js_path ) ? (string) filemtime( $js_path ) : SULTANA_ADMIN_VERSION;

        wp_enqueue_script(
            'sultana-admin-shell',
            SULTANA_ADMIN_URL . 'assets/js/shell.js',
            [],
            $js_version,
            true
        );
    }

    private static function enqueue_statistics(): void
    {
        $js_path    = SULTANA_ADMIN_PATH . 'assets/js/statistics.js';
        $js_version = file_exists( $js_path ) ? (string) filemtime( $js_path ) : SULTANA_ADMIN_VERSION;

        wp_enqueue_script(
            'sultana-admin-statistics',
            SULTANA_ADMIN_URL . 'assets/js/statistics.js',
            [],
            $js_version,
            true
        );

        wp_localize_script(
            'sultana-admin-statistics',
            'SultanaAdminStatistics',
            [
                'money' => [
                    'currencySymbol'    => function_exists( 'get_woocommerce_currency_symbol' ) ? html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, get_bloginfo( 'charset' ) ) : '',
                    'decimalSeparator'  => function_exists( 'wc_get_price_decimal_separator' ) ? wc_get_price_decimal_separator() : '.',
                    'thousandSeparator' => function_exists( 'wc_get_price_thousand_separator' ) ? wc_get_price_thousand_separator() : ',',
                    'decimals'          => function_exists( 'wc_get_price_decimals' ) ? wc_get_price_decimals() : 2,
                    'priceFormat'       => function_exists( 'get_woocommerce_price_format' ) ? html_entity_decode( get_woocommerce_price_format(), ENT_QUOTES, get_bloginfo( 'charset' ) ) : '%1$s%2$s',
                ],
            ]
        );
    }

    private static function enqueue_coupon_form(): void
    {
        $js_path    = SULTANA_ADMIN_PATH . 'assets/js/coupon-form.js';
        $js_version = file_exists( $js_path ) ? (string) filemtime( $js_path ) : SULTANA_ADMIN_VERSION;

        wp_enqueue_script(
            'sultana-admin-coupon-form',
            SULTANA_ADMIN_URL . 'assets/js/coupon-form.js',
            [],
            $js_version,
            true
        );
    }

    private static function enqueue_product_editor(): void
    {
        $js_path    = SULTANA_ADMIN_PATH . 'assets/js/product-editor.js';
        $js_version = file_exists( $js_path ) ? (string) filemtime( $js_path ) : SULTANA_ADMIN_VERSION;

        wp_enqueue_script(
            'sultana-admin-product-editor',
            SULTANA_ADMIN_URL . 'assets/js/product-editor.js',
            [],
            $js_version,
            true
        );

        wp_localize_script(
            'sultana-admin-product-editor',
            'SultanaAdminProductEditor',
            [
                'strings' => [
                    'reviewForm'                => __( 'Revisa el formulario', 'sultana-admin' ),
                    'nameRequired'              => __( 'Ingresa el nombre del producto.', 'sultana-admin' ),
                    'regularPriceInvalid'       => __( 'Ingresa un precio regular valido.', 'sultana-admin' ),
                    'salePriceInvalid'          => __( 'Ingresa un precio de oferta valido.', 'sultana-admin' ),
                    'salePriceLowerThanRegular' => __( 'El precio de oferta debe ser menor al precio regular.', 'sultana-admin' ),
                    'stockInvalid'              => __( 'Ingresa una cantidad de stock valida.', 'sultana-admin' ),
                    'weightInvalid'             => __( 'Ingresa un peso valido para el producto.', 'sultana-admin' ),
                    'attributeRequired'         => __( 'Selecciona al menos un atributo para variaciones.', 'sultana-admin' ),
                    'attributeValuesInvalid'    => __( 'Selecciona valores validos para cada atributo.', 'sultana-admin' ),
                    'variationRequired'         => __( 'Configura al menos una variacion.', 'sultana-admin' ),
                    'variationRegularInvalid'   => __( 'Cada variacion necesita un precio regular valido.', 'sultana-admin' ),
                    'variationSaleInvalid'      => __( 'El precio de oferta de cada variacion debe ser valido y menor al regular.', 'sultana-admin' ),
                    'variationStockInvalid'     => __( 'Cada variacion necesita una cantidad de stock valida.', 'sultana-admin' ),
                    'variationWeightInvalid'    => __( 'Cada variacion necesita un peso valido.', 'sultana-admin' ),
                    'comboComponentRequired'    => __( 'Selecciona al menos un componente.', 'sultana-admin' ),
                    'comboQuantityInvalid'      => __( 'La cantidad debe ser un numero entero.', 'sultana-admin' ),
                    'comboSaleInvalid'          => __( 'Ingresa un precio de oferta valido.', 'sultana-admin' ),
                    'comboSaleLowerThanCurrent' => __( 'El precio de oferta debe ser menor que el precio actual del combo.', 'sultana-admin' ),
                ],
            ]
        );
    }

    private static function enqueue_banners(): void
    {
        $js_path    = SULTANA_ADMIN_PATH . 'assets/js/banners.js';
        $js_version = file_exists( $js_path ) ? (string) filemtime( $js_path ) : SULTANA_ADMIN_VERSION;

        wp_enqueue_script(
            'sultana-admin-banners',
            SULTANA_ADMIN_URL . 'assets/js/banners.js',
            [],
            $js_version,
            true
        );

        wp_localize_script(
            'sultana-admin-banners',
            'SultanaAdminBanners',
            [
                'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
                'nonce'        => wp_create_nonce( PromotionController::IMAGE_UPLOAD_NONCE_ACTION ),
                'uploadAction' => PromotionController::IMAGE_UPLOAD_ACTION,
                'deleteAction' => PromotionController::IMAGE_DELETE_ACTION,
                'strings'      => [
                    'uploading'   => __( 'Subiendo banner...', 'sultana-admin' ),
                    'uploadError' => __( 'No se pudo subir el banner.', 'sultana-admin' ),
                    'deleteError' => __( 'La imagen se quito de la seleccion, pero no se pudo eliminar el archivo temporal.', 'sultana-admin' ),
                ],
            ]
        );
    }

    public static function enqueue_combo_editor(): void
    {
        $combo_js_path    = SULTANA_ADMIN_PATH . 'assets/js/product-combos.js';
        $combo_js_version = file_exists( $combo_js_path ) ? (string) filemtime( $combo_js_path ) : SULTANA_ADMIN_VERSION;

        wp_enqueue_script(
            'sultana-admin-product-combos',
            SULTANA_ADMIN_URL . 'assets/js/product-combos.js',
            [],
            $combo_js_version,
            true
        );

        wp_localize_script(
            'sultana-admin-product-combos',
            'SultanaAdminProductCombos',
            [
                'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
                'nonce'          => wp_create_nonce( ProductController::COMBO_COMPONENT_SEARCH_NONCE_ACTION ),
                'searchAction'   => ProductController::COMBO_COMPONENT_SEARCH_ACTION,
                'currencySymbol' => function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : 'C$',
                'strings'        => [
                    'component'         => __( 'Producto o variacion', 'sultana-admin' ),
                    'searchPlaceholder' => __( 'Buscar producto o variacion', 'sultana-admin' ),
                    'quantity'          => __( 'Cantidad', 'sultana-admin' ),
                    'remove'            => __( 'Quitar producto', 'sultana-admin' ),
                    'searching'         => __( 'Buscando...', 'sultana-admin' ),
                    'searchError'       => __( 'No se pudo buscar componentes.', 'sultana-admin' ),
                ],
                'icons'          => [
                    'trash' => Icons::url( 'trash' ),
                ],
            ]
        );
    }

    public static function dequeue_frontend_assets(): void
    {
        if ( ! Router::is_admin_request() ) {
            return;
        }

        foreach ( self::FRONTEND_STYLE_HANDLES as $handle ) {
            wp_dequeue_style( $handle );
        }

        foreach ( self::FRONTEND_SCRIPT_HANDLES as $handle ) {
            wp_dequeue_script( $handle );
        }
    }

    private static function screen_product_type( string $route, array $screen_data ): string
    {
        $type = isset( $screen_data['product_type'] ) ? sanitize_key( (string) $screen_data['product_type'] ) : '';

        if ( in_array( $type, [ 'simple', 'variable', 'combo' ], true ) ) {
            return $type;
        }

        if ( 'product_new' === $route ) {
            return self::requested_product_type();
        }

        return '';
    }

    private static function requested_product_type(): string
    {
        $type = isset( $_POST['product_type'] )
            ? sanitize_key( wp_unslash( $_POST['product_type'] ) )
            : ( isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : 'simple' );

        return $type;
    }
}

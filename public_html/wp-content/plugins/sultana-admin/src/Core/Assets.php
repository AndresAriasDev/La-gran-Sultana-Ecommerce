<?php

namespace Sultana\Admin\Core;

use Sultana\Admin\Products\ProductController;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Assets
{
    public static function enqueue( string $route = '' ): void
    {
        $css_path = SULTANA_ADMIN_PATH . 'assets/css/admin.css';
        $version  = file_exists( $css_path ) ? (string) filemtime( $css_path ) : SULTANA_ADMIN_VERSION;

        wp_enqueue_style(
            'sultana-admin',
            SULTANA_ADMIN_URL . 'assets/css/admin.css',
            [],
            $version
        );

        if ( ! in_array( $route, [ 'product_new', 'product_edit' ], true ) ) {
            return;
        }

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
            ]
        );

        $variable_js_path    = SULTANA_ADMIN_PATH . 'assets/js/product-variables.js';
        $variable_js_version = file_exists( $variable_js_path ) ? (string) filemtime( $variable_js_path ) : SULTANA_ADMIN_VERSION;

        wp_enqueue_script(
            'sultana-admin-product-variables',
            SULTANA_ADMIN_URL . 'assets/js/product-variables.js',
            [],
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
            ]
        );

        $should_enqueue_combo = 'product_edit' === $route || ( 'product_new' === $route && 'combo' === self::requested_product_type() );

        if ( ! $should_enqueue_combo ) {
            return;
        }

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
                'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
                'nonce'        => wp_create_nonce( ProductController::COMBO_COMPONENT_SEARCH_NONCE_ACTION ),
                'searchAction' => ProductController::COMBO_COMPONENT_SEARCH_ACTION,
                'currencySymbol' => function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : 'C$',
                'strings'      => [
                    'component'         => __( 'Producto o variacion', 'sultana-admin' ),
                    'searchPlaceholder' => __( 'Buscar producto o variacion', 'sultana-admin' ),
                    'quantity'          => __( 'Cantidad', 'sultana-admin' ),
                    'remove'            => __( 'Quitar', 'sultana-admin' ),
                    'searching'         => __( 'Buscando...', 'sultana-admin' ),
                    'searchError'       => __( 'No se pudo buscar componentes.', 'sultana-admin' ),
                ],
            ]
        );
    }

    private static function requested_product_type(): string
    {
        $type = isset( $_POST['product_type'] )
            ? sanitize_key( wp_unslash( $_POST['product_type'] ) )
            : ( isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : 'simple' );

        return $type;
    }
}

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

        if ( 'product_new' !== $route ) {
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
    }
}

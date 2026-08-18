<?php

namespace Sultana\Admin\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Assets
{
    public static function enqueue(): void
    {
        $css_path = SULTANA_ADMIN_PATH . 'assets/css/admin.css';
        $version  = file_exists( $css_path ) ? (string) filemtime( $css_path ) : SULTANA_ADMIN_VERSION;

        wp_enqueue_style(
            'sultana-admin',
            SULTANA_ADMIN_URL . 'assets/css/admin.css',
            [],
            $version
        );
    }
}

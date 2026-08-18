<?php

namespace Sultana\Admin\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Assets
{
    public static function enqueue(): void
    {
        wp_enqueue_style(
            'sultana-admin',
            SULTANA_ADMIN_URL . 'assets/css/admin.css',
            [],
            SULTANA_ADMIN_VERSION
        );
    }
}

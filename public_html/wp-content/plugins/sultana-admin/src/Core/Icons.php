<?php

namespace Sultana\Admin\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Icons
{
    private const ICONS = [
        'brush-cleaning',
        'box',
        'chevron-left',
        'chevron-right',
        'close',
        'eye',
        'funnel',
        'heart',
        'images',
        'layout-panel-left',
        'lock',
        'log-out',
        'package-check',
        'pencil',
        'piggy-bank',
        'save',
        'search',
        'shelving-unit',
        'shopping-cart',
        'tickets',
        'trash',
        'user',
    ];

    public static function url( string $name ): string
    {
        $name = sanitize_key( $name );

        if ( ! in_array( $name, self::ICONS, true ) ) {
            return '';
        }

        return SULTANA_ADMIN_URL . 'assets/icons/' . $name . '.svg';
    }
}

<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function variedadesexpress_register_menus(): void
{
    register_nav_menus(
        [
            'primary' => __( 'Menu principal', 'sultana-storefront' ),
            'footer'  => __( 'Menu del footer', 'sultana-storefront' ),
        ]
    );
}

add_action( 'after_setup_theme', 'variedadesexpress_register_menus' );

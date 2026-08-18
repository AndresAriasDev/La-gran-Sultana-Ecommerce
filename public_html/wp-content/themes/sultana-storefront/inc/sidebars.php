<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function variedadesexpress_register_sidebars(): void
{
    register_sidebar(
        [
            'name'          => __( 'Sidebar de tienda', 'sultana-storefront' ),
            'id'            => 'shop-sidebar',
            'description'   => __( 'Widgets para el catalogo de productos.', 'sultana-storefront' ),
            'before_widget' => '<div id="%1$s" class="widget %2$s">',
            'after_widget'  => '</div>',
            'before_title'  => '<h3 class="widget-title">',
            'after_title'   => '</h3>',
        ]
    );
}

add_action( 'widgets_init', 'variedadesexpress_register_sidebars' );

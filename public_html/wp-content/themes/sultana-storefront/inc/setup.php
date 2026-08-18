<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function variedadesexpress_theme_setup(): void
{
    load_theme_textdomain(
        'sultana-storefront',
        get_template_directory() . '/languages'
    );

    add_theme_support( 'title-tag' );
    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'custom-logo' );

    add_theme_support(
        'html5',
        [
            'search-form',
            'comment-form',
            'comment-list',
            'gallery',
            'caption',
            'style',
            'script',
        ]
    );

    add_theme_support( 'woocommerce' );
    add_theme_support( 'wc-product-gallery-zoom' );
    add_theme_support( 'wc-product-gallery-lightbox' );
    add_theme_support( 'wc-product-gallery-slider' );
}

add_action( 'after_setup_theme', 'variedadesexpress_theme_setup' );
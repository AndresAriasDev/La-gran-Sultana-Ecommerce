<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function variedadesexpress_scc_store_branding_primary_color( string $color ): string
{
    if ( ! function_exists( 'sultana_storefront_store_primary_color' ) ) {
        return $color;
    }

    $storefront_color = variedadesexpress_sanitize_hex_color( sultana_storefront_store_primary_color() );

    return '' !== $storefront_color ? $storefront_color : $color;
}

add_filter( 'scc_store_branding_primary_color', 'variedadesexpress_scc_store_branding_primary_color' );

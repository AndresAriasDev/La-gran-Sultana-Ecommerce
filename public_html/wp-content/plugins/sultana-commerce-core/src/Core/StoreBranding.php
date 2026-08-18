<?php

namespace Sultana\CommerceCore\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class StoreBranding
{
    private const PRIMARY_COLOR_FALLBACK = '#2f3640';

    public static function get_name(): string
    {
        $name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
        $name = self::sanitize_text( $name );

        if ( '' === $name ) {
            $name = 'Store';
        }

        /**
         * Filters the public store name used by Sultana Commerce Core.
         *
         * @param string $name Store name resolved from WordPress.
         */
        $name = apply_filters( 'scc_store_branding_name', $name );

        return self::sanitize_text( $name ) ?: 'Store';
    }

    public static function get_url(): string
    {
        $url = home_url( '/' );

        /**
         * Filters the public store home URL used by Sultana Commerce Core.
         *
         * @param string $url Store home URL resolved from WordPress.
         */
        $url = apply_filters( 'scc_store_branding_url', $url );

        return esc_url_raw( self::sanitize_scalar( $url ) );
    }

    public static function get_logo_url(): string
    {
        $custom_logo_id = (int) get_theme_mod( 'custom_logo' );
        $logo_url       = $custom_logo_id > 0 ? wp_get_attachment_image_url( $custom_logo_id, 'full' ) : '';
        $logo_url       = is_string( $logo_url ) ? $logo_url : '';

        /**
         * Filters the public store logo URL used by Sultana Commerce Core.
         *
         * @param string $logo_url       Logo URL resolved from WordPress Custom Logo.
         * @param int    $custom_logo_id WordPress attachment ID configured as Custom Logo.
         */
        $logo_url = apply_filters( 'scc_store_branding_logo_url', $logo_url, $custom_logo_id );

        return esc_url_raw( self::sanitize_scalar( $logo_url ) );
    }

    public static function get_primary_color(): string
    {
        $color = self::PRIMARY_COLOR_FALLBACK;

        /**
         * Filters the primary brand color used by Sultana Commerce Core.
         *
         * Core intentionally does not read theme files or theme-specific helpers.
         *
         * @param string $color Valid hex color fallback provided by Core.
         */
        $color = apply_filters( 'scc_store_branding_primary_color', $color );
        $color = self::sanitize_hex_color( $color );

        return '' !== $color ? $color : self::PRIMARY_COLOR_FALLBACK;
    }

    private static function sanitize_text( $value ): string
    {
        return sanitize_text_field( self::sanitize_scalar( $value ) );
    }

    private static function sanitize_scalar( $value ): string
    {
        if ( is_array( $value ) || is_object( $value ) ) {
            return '';
        }

        return trim( (string) $value );
    }

    private static function sanitize_hex_color( $color ): string
    {
        if ( is_array( $color ) || is_object( $color ) ) {
            return '';
        }

        $color = trim( (string) $color );

        if ( function_exists( 'sanitize_hex_color' ) ) {
            return (string) sanitize_hex_color( $color );
        }

        return preg_match( '/^#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', $color ) ? $color : '';
    }
}

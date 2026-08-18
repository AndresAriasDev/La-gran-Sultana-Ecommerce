<?php

namespace Sultana\CommerceCore\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TemplateLoader
{
    private const THEME_OVERRIDE_DIRECTORY = 'sultana-commerce/';
    private const PLUGIN_TEMPLATE_DIRECTORY = 'templates/';

    /**
     * Locates a Core template path.
     *
     * Theme override:
     * theme/sultana-commerce/{template}
     *
     * Plugin fallback:
     * sultana-commerce-core/templates/{template}
     *
     * Returns an empty string when the template name is invalid or neither the
     * theme override nor plugin fallback exists.
     */
    public static function locate( string $template ): string
    {
        $template = self::normalize_template_path( $template );

        if ( '' === $template ) {
            return '';
        }

        $theme_template = locate_template( self::THEME_OVERRIDE_DIRECTORY . $template, false, false );

        if ( is_string( $theme_template ) && '' !== $theme_template && is_file( $theme_template ) ) {
            return $theme_template;
        }

        $plugin_template = self::plugin_template_path( $template );

        return '' !== $plugin_template && is_file( $plugin_template ) ? $plugin_template : '';
    }

    private static function normalize_template_path( string $template ): string
    {
        $template = wp_normalize_path( trim( $template ) );

        if (
            '' === $template
            || str_contains( $template, "\0" )
            || str_contains( $template, '://' )
            || str_starts_with( $template, '/' )
            || preg_match( '#^[a-zA-Z]:/#', $template )
            || ! str_ends_with( $template, '.php' )
        ) {
            return '';
        }

        $segments = explode( '/', $template );

        if ( in_array( '..', $segments, true ) || in_array( '.', $segments, true ) || in_array( '', $segments, true ) ) {
            return '';
        }

        return sanitize_text_field( $template );
    }

    private static function plugin_template_path( string $template ): string
    {
        if ( ! defined( 'SCC_PLUGIN_PATH' ) ) {
            return '';
        }

        $base_path = wp_normalize_path( trailingslashit( SCC_PLUGIN_PATH ) . self::PLUGIN_TEMPLATE_DIRECTORY );
        $path      = wp_normalize_path( $base_path . $template );

        return str_starts_with( $path, $base_path ) ? $path : '';
    }
}

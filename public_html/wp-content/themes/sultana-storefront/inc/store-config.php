<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function sultana_storefront_store_name(): string
{
    $store_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
    $store_name = is_string( $store_name ) ? trim( $store_name ) : '';
    $store_name = sanitize_text_field( $store_name );
    $store_name = variedadesexpress_store_scalar_value(
        apply_filters( 'sultana_storefront_store_name', $store_name ),
        $store_name
    );

    return sanitize_text_field( $store_name );
}

/**
 * Legacy alias for sultana_storefront_store_name().
 *
 * @return string
 */
function variedadesexpress_store_name(): string
{
    return sultana_storefront_store_name();
}

function sultana_storefront_store_url(): string
{
    $store_url = home_url( '/' );
    $store_url = variedadesexpress_store_scalar_value(
        apply_filters( 'sultana_storefront_store_url', $store_url ),
        $store_url
    );

    return esc_url_raw( $store_url );
}

/**
 * Legacy alias for sultana_storefront_store_url().
 *
 * @return string
 */
function variedadesexpress_store_url(): string
{
    return sultana_storefront_store_url();
}

function sultana_storefront_store_logo_url(): string
{
    $custom_logo_id = (int) get_theme_mod( 'custom_logo' );
    $logo_url       = $custom_logo_id > 0 ? wp_get_attachment_image_url( $custom_logo_id, 'full' ) : '';
    $logo_url       = is_string( $logo_url ) ? $logo_url : '';
    $logo_url       = variedadesexpress_store_scalar_value(
        apply_filters( 'sultana_storefront_store_logo_url', $logo_url, $custom_logo_id ),
        $logo_url
    );

    return esc_url_raw( $logo_url );
}

/**
 * Legacy alias for sultana_storefront_store_logo_url().
 *
 * @return string
 */
function variedadesexpress_store_logo_url(): string
{
    return sultana_storefront_store_logo_url();
}

function sultana_storefront_store_primary_color(): string
{
    $fallback = '#c24366';
    $color    = get_theme_mod( 'sultana_storefront_primary_color', $fallback );
    $color    = variedadesexpress_sanitize_hex_color( $color ) ?: $fallback;
    $color    = variedadesexpress_store_scalar_value(
        apply_filters( 'sultana_storefront_store_primary_color', $color ),
        $color
    );

    return variedadesexpress_sanitize_hex_color( $color ) ?: $fallback;
}

/**
 * Legacy alias for sultana_storefront_store_primary_color().
 *
 * @return string
 */
function variedadesexpress_store_primary_color(): string
{
    return sultana_storefront_store_primary_color();
}

function sultana_storefront_store_phone(): string
{
    $phone = variedadesexpress_store_theme_mod_text( 'sultana_storefront_phone' );
    $phone = variedadesexpress_store_scalar_value(
        apply_filters( 'sultana_storefront_store_phone', $phone ),
        $phone
    );

    return variedadesexpress_sanitize_phone_text( $phone );
}

/**
 * Legacy alias for sultana_storefront_store_phone().
 *
 * @return string
 */
function variedadesexpress_store_phone(): string
{
    return sultana_storefront_store_phone();
}

function sultana_storefront_store_whatsapp(): string
{
    $whatsapp = variedadesexpress_store_theme_mod_text( 'sultana_storefront_whatsapp' );
    $whatsapp = variedadesexpress_store_scalar_value(
        apply_filters( 'sultana_storefront_store_whatsapp', $whatsapp ),
        $whatsapp
    );

    return variedadesexpress_sanitize_phone_text( $whatsapp );
}

/**
 * Legacy alias for sultana_storefront_store_whatsapp().
 *
 * @return string
 */
function variedadesexpress_store_whatsapp(): string
{
    return sultana_storefront_store_whatsapp();
}

function sultana_storefront_store_whatsapp_url(): string
{
    $whatsapp = sultana_storefront_store_whatsapp();
    $number   = preg_replace( '/\D+/', '', $whatsapp );
    $url      = $number ? 'https://wa.me/' . $number : '';
    $url      = variedadesexpress_store_scalar_value(
        apply_filters( 'sultana_storefront_store_whatsapp_url', $url, $whatsapp ),
        $url
    );

    return esc_url_raw( $url );
}

/**
 * Legacy alias for sultana_storefront_store_whatsapp_url().
 *
 * @return string
 */
function variedadesexpress_store_whatsapp_url(): string
{
    return sultana_storefront_store_whatsapp_url();
}

function sultana_storefront_store_address(): string
{
    $address = variedadesexpress_store_theme_mod_text( 'sultana_storefront_address' );
    $address = variedadesexpress_store_scalar_value(
        apply_filters( 'sultana_storefront_store_address', $address ),
        $address
    );

    return sanitize_text_field( $address );
}

/**
 * Legacy alias for sultana_storefront_store_address().
 *
 * @return string
 */
function variedadesexpress_store_address(): string
{
    return sultana_storefront_store_address();
}

function sultana_storefront_store_contact_email(): string
{
    $email = variedadesexpress_store_theme_mod_text( 'sultana_storefront_contact_email' );
    $email = variedadesexpress_store_scalar_value(
        apply_filters( 'sultana_storefront_store_contact_email', $email ),
        $email
    );

    return sanitize_email( $email );
}

/**
 * Legacy alias for sultana_storefront_store_contact_email().
 *
 * @return string
 */
function variedadesexpress_store_contact_email(): string
{
    return sultana_storefront_store_contact_email();
}

function sultana_storefront_store_social_url( string $network ): string
{
    $network = sanitize_key( $network );

    $theme_mods = [
        'facebook'  => 'sultana_storefront_facebook_url',
        'instagram' => 'sultana_storefront_instagram_url',
        'tiktok'    => 'sultana_storefront_tiktok_url',
    ];

    if ( ! isset( $theme_mods[ $network ] ) ) {
        return '';
    }

    $url = variedadesexpress_store_theme_mod_text( $theme_mods[ $network ] );
    $url = variedadesexpress_store_scalar_value(
        apply_filters( 'sultana_storefront_store_social_url', $url, $network ),
        $url
    );

    return esc_url_raw( $url );
}

/**
 * Legacy alias for sultana_storefront_store_social_url().
 *
 * @param string $network Social network key.
 * @return string
 */
function variedadesexpress_store_social_url( string $network ): string
{
    return sultana_storefront_store_social_url( $network );
}

function sultana_storefront_store_gtm_id(): string
{
    $gtm_id = variedadesexpress_sanitize_gtm_id( variedadesexpress_store_theme_mod_text( 'sultana_storefront_gtm_id' ) );
    $gtm_id = variedadesexpress_store_scalar_value(
        apply_filters( 'sultana_storefront_gtm_id', $gtm_id ),
        $gtm_id
    );

    return variedadesexpress_sanitize_gtm_id( $gtm_id );
}

/**
 * Legacy alias for sultana_storefront_store_gtm_id().
 *
 * @return string
 */
function variedadesexpress_store_gtm_id(): string
{
    return sultana_storefront_store_gtm_id();
}

function sultana_storefront_store_ga4_id(): string
{
    $ga4_id = variedadesexpress_sanitize_ga4_id( variedadesexpress_store_theme_mod_text( 'sultana_storefront_ga4_id' ) );
    $ga4_id = variedadesexpress_store_scalar_value(
        apply_filters( 'sultana_storefront_ga4_id', $ga4_id ),
        $ga4_id
    );

    return variedadesexpress_sanitize_ga4_id( $ga4_id );
}

/**
 * Legacy alias for sultana_storefront_store_ga4_id().
 *
 * @return string
 */
function variedadesexpress_store_ga4_id(): string
{
    return sultana_storefront_store_ga4_id();
}

function variedadesexpress_store_theme_mod_text( string $theme_mod ): string
{
    return variedadesexpress_store_scalar_value( get_theme_mod( $theme_mod, '' ) );
}

function variedadesexpress_store_scalar_value( $value, string $fallback = '' ): string
{
    if ( is_array( $value ) || is_object( $value ) ) {
        return $fallback;
    }

    return (string) $value;
}

function variedadesexpress_sanitize_hex_color( $color ): string
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

function variedadesexpress_sanitize_phone_text( $phone ): string
{
    if ( is_array( $phone ) || is_object( $phone ) ) {
        return '';
    }

    $phone = trim( (string) $phone );
    $phone = preg_replace( '/[^\d\s()+.-]/', '', $phone );

    return sanitize_text_field( (string) $phone );
}

function variedadesexpress_sanitize_gtm_id( $gtm_id ): string
{
    if ( is_array( $gtm_id ) || is_object( $gtm_id ) ) {
        return '';
    }

    $gtm_id = strtoupper( trim( (string) $gtm_id ) );

    return preg_match( '/^GTM-[A-Z0-9]+$/', $gtm_id ) ? $gtm_id : '';
}

function variedadesexpress_sanitize_ga4_id( $ga4_id ): string
{
    if ( is_array( $ga4_id ) || is_object( $ga4_id ) ) {
        return '';
    }

    $ga4_id = strtoupper( trim( (string) $ga4_id ) );

    return preg_match( '/^G-[A-Z0-9]+$/', $ga4_id ) ? $ga4_id : '';
}

function variedadesexpress_store_register_identity_customizer( WP_Customize_Manager $wp_customize ): void
{
    $wp_customize->add_section(
        'sultana_storefront_identity',
        [
            'title'       => __( 'Identidad de la tienda', 'sultana-storefront' ),
            'description' => __( 'Datos comerciales visibles de la tienda.', 'sultana-storefront' ),
            'priority'    => 35,
        ]
    );

    $settings = [
        'sultana_storefront_phone'         => [
            'label'             => __( 'Telefono', 'sultana-storefront' ),
            'sanitize_callback' => 'variedadesexpress_sanitize_phone_text',
            'type'              => 'text',
        ],
        'sultana_storefront_whatsapp'      => [
            'label'             => __( 'WhatsApp', 'sultana-storefront' ),
            'sanitize_callback' => 'variedadesexpress_sanitize_phone_text',
            'type'              => 'text',
        ],
        'sultana_storefront_address'       => [
            'label'             => __( 'Direccion', 'sultana-storefront' ),
            'sanitize_callback' => 'sanitize_text_field',
            'type'              => 'text',
        ],
        'sultana_storefront_contact_email' => [
            'label'             => __( 'Correo de contacto', 'sultana-storefront' ),
            'sanitize_callback' => 'sanitize_email',
            'type'              => 'email',
        ],
        'sultana_storefront_facebook_url'  => [
            'label'             => __( 'Facebook URL', 'sultana-storefront' ),
            'sanitize_callback' => 'esc_url_raw',
            'type'              => 'url',
        ],
        'sultana_storefront_instagram_url' => [
            'label'             => __( 'Instagram URL', 'sultana-storefront' ),
            'sanitize_callback' => 'esc_url_raw',
            'type'              => 'url',
        ],
        'sultana_storefront_tiktok_url'    => [
            'label'             => __( 'TikTok URL', 'sultana-storefront' ),
            'sanitize_callback' => 'esc_url_raw',
            'type'              => 'url',
        ],
    ];

    foreach ( $settings as $setting_id => $setting ) {
        $wp_customize->add_setting(
            $setting_id,
            [
                'default'           => '',
                'sanitize_callback' => $setting['sanitize_callback'],
                'transport'         => 'refresh',
            ]
        );

        $wp_customize->add_control(
            $setting_id,
            [
                'section' => 'sultana_storefront_identity',
                'label'   => $setting['label'],
                'type'    => $setting['type'],
            ]
        );
    }
}

add_action( 'customize_register', 'variedadesexpress_store_register_identity_customizer' );

function variedadesexpress_store_register_analytics_customizer( WP_Customize_Manager $wp_customize ): void
{
    $wp_customize->add_section(
        'sultana_storefront_analytics',
        [
            'title'       => __( 'Analitica', 'sultana-storefront' ),
            'description' => __( 'IDs de Google Tag Manager y Google Analytics para esta tienda.', 'sultana-storefront' ),
            'priority'    => 36,
        ]
    );

    $wp_customize->add_setting(
        'sultana_storefront_gtm_id',
        [
            'default'           => '',
            'sanitize_callback' => 'variedadesexpress_sanitize_gtm_id',
            'transport'         => 'refresh',
        ]
    );

    $wp_customize->add_control(
        'sultana_storefront_gtm_id',
        [
            'section'     => 'sultana_storefront_analytics',
            'label'       => __( 'Google Tag Manager ID', 'sultana-storefront' ),
            'description' => __( 'Formato esperado: GTM-XXXXXXX.', 'sultana-storefront' ),
            'type'        => 'text',
        ]
    );

    $wp_customize->add_setting(
        'sultana_storefront_ga4_id',
        [
            'default'           => '',
            'sanitize_callback' => 'variedadesexpress_sanitize_ga4_id',
            'transport'         => 'refresh',
        ]
    );

    $wp_customize->add_control(
        'sultana_storefront_ga4_id',
        [
            'section'     => 'sultana_storefront_analytics',
            'label'       => __( 'Google Analytics 4 ID', 'sultana-storefront' ),
            'description' => __( 'Formato esperado: G-XXXXXXXXXX.', 'sultana-storefront' ),
            'type'        => 'text',
        ]
    );
}

add_action( 'customize_register', 'variedadesexpress_store_register_analytics_customizer' );

function variedadesexpress_store_migrate_identity_theme_mods(): void
{
    $migration_version = '1';

    if ( get_theme_mod( 'sultana_storefront_identity_migrated' ) === $migration_version ) {
        return;
    }

    $store_name = strtolower( sultana_storefront_store_name() );

    if ( false === strpos( $store_name, 'variedades express' ) ) {
        set_theme_mod( 'sultana_storefront_identity_migrated', $migration_version );
        return;
    }

    $legacy_values = [
        'sultana_storefront_phone'         => '7603 4911',
        'sultana_storefront_whatsapp'      => '50576034911',
        'sultana_storefront_address'       => 'Del Cuerpo de Bomberos 1 cuadra al Lago, Granada, Nicaragua, 43000',
        'sultana_storefront_facebook_url'  => 'https://www.facebook.com/variedadesexpress.nic',
        'sultana_storefront_instagram_url' => 'https://www.instagram.com/variedadesexpress_gr/',
        'sultana_storefront_tiktok_url'    => 'https://www.tiktok.com/@variedadesexpres',
    ];

    foreach ( $legacy_values as $theme_mod => $value ) {
        if ( '' !== variedadesexpress_store_theme_mod_text( $theme_mod ) ) {
            continue;
        }

        set_theme_mod( $theme_mod, $value );
    }

    set_theme_mod( 'sultana_storefront_identity_migrated', $migration_version );
}

add_action( 'after_setup_theme', 'variedadesexpress_store_migrate_identity_theme_mods', 20 );

function variedadesexpress_store_migrate_tracking_theme_mods(): void
{
    $migration_version = '1';

    if ( get_theme_mod( 'sultana_storefront_tracking_migrated' ) === $migration_version ) {
        return;
    }

    $store_name = strtolower( sultana_storefront_store_name() );

    if ( false === strpos( $store_name, 'variedades express' ) ) {
        set_theme_mod( 'sultana_storefront_tracking_migrated', $migration_version );
        return;
    }

    $legacy_values = [
        'sultana_storefront_gtm_id' => 'GTM-KRCCXHNK',
        'sultana_storefront_ga4_id' => 'G-7ELDDD682J',
    ];

    foreach ( $legacy_values as $theme_mod => $value ) {
        if ( '' !== variedadesexpress_store_theme_mod_text( $theme_mod ) ) {
            continue;
        }

        set_theme_mod( $theme_mod, $value );
    }

    set_theme_mod( 'sultana_storefront_tracking_migrated', $migration_version );
}

add_action( 'after_setup_theme', 'variedadesexpress_store_migrate_tracking_theme_mods', 21 );

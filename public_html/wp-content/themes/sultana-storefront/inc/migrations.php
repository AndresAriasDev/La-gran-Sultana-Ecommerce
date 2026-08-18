<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function sultana_storefront_migrate_theme_slug_theme_mods(): void
{
    $migration_version = '1';
    $migration_option  = 'sultana_storefront_theme_slug_migration_version';

    if ( get_option( $migration_option ) === $migration_version ) {
        return;
    }

    $source_option = 'theme_mods_variedadesexpress';
    $target_option = 'theme_mods_sultana-storefront';
    $source_mods   = get_option( $source_option, false );

    if ( ! is_array( $source_mods ) || [] === $source_mods ) {
        return;
    }

    $target_mods = get_option( $target_option, false );

    if ( is_array( $target_mods ) && [] !== $target_mods ) {
        update_option( $migration_option, $migration_version, false );
        return;
    }

    update_option( $target_option, $source_mods );
    update_option( $migration_option, $migration_version, false );
}

add_action( 'after_setup_theme', 'sultana_storefront_migrate_theme_slug_theme_mods', 30 );

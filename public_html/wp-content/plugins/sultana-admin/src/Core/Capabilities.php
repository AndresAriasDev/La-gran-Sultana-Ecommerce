<?php

namespace Sultana\Admin\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Capabilities
{
    public const ROLE = 'gestor_tienda';
    public const ACCESS_CAPABILITY = 'sultana_admin_access';
    public const CREATE_PRODUCTS_CAPABILITY = 'edit_products';
    public const PUBLISH_PRODUCTS_CAPABILITY = 'publish_products';
    public const UPLOAD_FILES_CAPABILITY = 'upload_files';
    public const ASSIGN_PRODUCT_TERMS_CAPABILITY = 'assign_product_terms';

    public static function activate(): void
    {
        self::ensure_role_capabilities();
    }

    public static function ensure_role_capabilities(): void
    {
        $capabilities = [
            'read' => true,
            self::ACCESS_CAPABILITY => true,
            self::CREATE_PRODUCTS_CAPABILITY => true,
            self::PUBLISH_PRODUCTS_CAPABILITY => true,
            self::UPLOAD_FILES_CAPABILITY => true,
            self::ASSIGN_PRODUCT_TERMS_CAPABILITY => true,
        ];

        $role = get_role( self::ROLE );

        if ( ! $role ) {
            add_role( self::ROLE, __( 'Gestor de tienda', 'sultana-admin' ), $capabilities );
            return;
        }

        foreach ( $capabilities as $capability => $grant ) {
            if ( $grant && ! $role->has_cap( $capability ) ) {
                $role->add_cap( $capability );
            }
        }
    }
}

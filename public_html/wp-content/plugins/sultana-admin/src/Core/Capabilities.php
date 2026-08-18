<?php

namespace Sultana\Admin\Core;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Capabilities
{
    public const ROLE = 'gestor_tienda';
    public const ACCESS_CAPABILITY = 'sultana_admin_access';

    public static function activate(): void
    {
        $capabilities = [
            'read' => true,
            self::ACCESS_CAPABILITY => true,
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

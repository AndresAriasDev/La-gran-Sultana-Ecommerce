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
    public const EDIT_OTHERS_PRODUCTS_CAPABILITY = 'edit_others_products';
    public const EDIT_PUBLISHED_PRODUCTS_CAPABILITY = 'edit_published_products';
    public const DELETE_PRODUCTS_CAPABILITY = 'delete_products';
    public const DELETE_OTHERS_PRODUCTS_CAPABILITY = 'delete_others_products';
    public const DELETE_PUBLISHED_PRODUCTS_CAPABILITY = 'delete_published_products';
    public const DELETE_PRIVATE_PRODUCTS_CAPABILITY = 'delete_private_products';
    public const PUBLISH_PRODUCTS_CAPABILITY = 'publish_products';
    public const UPLOAD_FILES_CAPABILITY = 'upload_files';
    public const ASSIGN_PRODUCT_TERMS_CAPABILITY = 'assign_product_terms';
    public const READ_ORDERS_CAPABILITY = 'edit_shop_orders';
    public const EDIT_OTHERS_ORDERS_CAPABILITY = 'edit_others_shop_orders';
    public const EDIT_PRIVATE_ORDERS_CAPABILITY = 'edit_private_shop_orders';
    public const EDIT_PUBLISHED_ORDERS_CAPABILITY = 'edit_published_shop_orders';
    public const READ_PRIVATE_ORDERS_CAPABILITY = 'read_private_shop_orders';
    public const EDIT_COUPONS_CAPABILITY = 'edit_shop_coupons';
    public const EDIT_OTHERS_COUPONS_CAPABILITY = 'edit_others_shop_coupons';
    public const EDIT_PUBLISHED_COUPONS_CAPABILITY = 'edit_published_shop_coupons';
    public const DELETE_COUPONS_CAPABILITY = 'delete_shop_coupons';
    public const DELETE_OTHERS_COUPONS_CAPABILITY = 'delete_others_shop_coupons';
    public const DELETE_PUBLISHED_COUPONS_CAPABILITY = 'delete_published_shop_coupons';
    public const PUBLISH_COUPONS_CAPABILITY = 'publish_shop_coupons';
    public const READ_PRIVATE_COUPONS_CAPABILITY = 'read_private_shop_coupons';
    public const MANAGE_REVIEWS_CAPABILITY = 'moderate_comments';

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
            self::EDIT_OTHERS_PRODUCTS_CAPABILITY => true,
            self::EDIT_PUBLISHED_PRODUCTS_CAPABILITY => true,
            self::DELETE_PRODUCTS_CAPABILITY => true,
            self::DELETE_OTHERS_PRODUCTS_CAPABILITY => true,
            self::DELETE_PUBLISHED_PRODUCTS_CAPABILITY => true,
            self::DELETE_PRIVATE_PRODUCTS_CAPABILITY => true,
            self::PUBLISH_PRODUCTS_CAPABILITY => true,
            self::UPLOAD_FILES_CAPABILITY => true,
            self::ASSIGN_PRODUCT_TERMS_CAPABILITY => true,
            self::READ_ORDERS_CAPABILITY => true,
            self::EDIT_OTHERS_ORDERS_CAPABILITY => true,
            self::EDIT_PRIVATE_ORDERS_CAPABILITY => true,
            self::EDIT_PUBLISHED_ORDERS_CAPABILITY => true,
            self::READ_PRIVATE_ORDERS_CAPABILITY => true,
            self::EDIT_COUPONS_CAPABILITY => true,
            self::EDIT_OTHERS_COUPONS_CAPABILITY => true,
            self::EDIT_PUBLISHED_COUPONS_CAPABILITY => true,
            self::DELETE_COUPONS_CAPABILITY => true,
            self::DELETE_OTHERS_COUPONS_CAPABILITY => true,
            self::DELETE_PUBLISHED_COUPONS_CAPABILITY => true,
            self::PUBLISH_COUPONS_CAPABILITY => true,
            self::READ_PRIVATE_COUPONS_CAPABILITY => true,
            self::MANAGE_REVIEWS_CAPABILITY => true,
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

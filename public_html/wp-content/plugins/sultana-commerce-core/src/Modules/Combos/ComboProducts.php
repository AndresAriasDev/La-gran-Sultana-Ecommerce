<?php

namespace Sultana\CommerceCore\Modules\Combos;

use Sultana\CommerceCore\Modules\Combos\Admin\ComboProductsAdmin;
use WC_Product;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ComboProducts
{
    public static function register(): void
    {
        ComboProductsAdmin::register();

        add_filter( 'woocommerce_product_class', [ self::class, 'map_product_class' ], 10, 4 );
        add_filter( 'woocommerce_locate_template', [ self::class, 'use_simple_add_to_cart_template' ], 10, 3 );
        add_action( 'woocommerce_combo_add_to_cart', [ self::class, 'render_combo_add_to_cart' ], 30 );
        add_filter( 'woocommerce_add_to_cart_validation', [ self::class, 'validate_add_to_cart' ], 20, 5 );
        add_filter( 'woocommerce_update_cart_validation', [ self::class, 'validate_cart_quantity_update' ], 20, 4 );
        add_action( 'woocommerce_check_cart_items', [ self::class, 'validate_cart_items' ], 20 );
        add_action( 'woocommerce_checkout_process', [ self::class, 'validate_cart_items' ], 20 );
        add_action( 'woocommerce_checkout_create_order_line_item', [ ComboOrderService::class, 'add_combo_snapshot_to_order_item' ], 20, 4 );
        add_action( 'woocommerce_reduce_order_stock', [ ComboOrderService::class, 'reduce_combo_component_stock' ], 20 );
        add_action( 'woocommerce_restore_order_stock', [ ComboOrderService::class, 'restore_combo_component_stock' ], 20 );
        add_filter( 'woocommerce_hidden_order_itemmeta', [ ComboOrderService::class, 'hide_combo_order_item_meta' ] );
        add_action( 'woocommerce_product_set_stock', [ self::class, 'sync_combos_for_stock_product' ] );
        add_action( 'woocommerce_variation_set_stock', [ self::class, 'sync_combos_for_stock_product' ] );
        add_action( 'woocommerce_product_set_stock_status', [ self::class, 'sync_combos_for_stock_product' ] );
        add_action( 'woocommerce_variation_set_stock_status', [ self::class, 'sync_combos_for_stock_product' ] );
        add_action( 'woocommerce_product_object_updated_props', [ self::class, 'sync_combos_for_price_product' ], 10, 2 );
    }

    public static function map_product_class( string $classname, string $product_type, string $post_type, int $product_id ): string
    {
        if ( 'combo' === $product_type ) {
            return ProductCombo::class;
        }

        return $classname;
    }

    public static function render_combo_add_to_cart(): void
    {
        if ( function_exists( 'woocommerce_simple_add_to_cart' ) ) {
            woocommerce_simple_add_to_cart();
        }
    }

    public static function use_simple_add_to_cart_template( string $template, string $template_name, string $template_path ): string
    {
        if ( 'single-product/add-to-cart/combo.php' !== $template_name || ! function_exists( 'WC' ) ) {
            return $template;
        }

        $simple_template = WC()->template_path() . 'single-product/add-to-cart/simple.php';
        $located         = locate_template( $simple_template );

        if ( $located ) {
            return $located;
        }

        $woocommerce_template = defined( 'WC_ABSPATH' ) ? WC_ABSPATH . 'templates/single-product/add-to-cart/simple.php' : '';

        return $woocommerce_template && file_exists( $woocommerce_template ) ? $woocommerce_template : $template;
    }

    public static function validate_add_to_cart( bool $passed, int $product_id, int $quantity, int $variation_id = 0, array $variations = [] ): bool
    {
        if ( ! $passed || ! function_exists( 'wc_get_product' ) ) {
            return $passed;
        }

        $product = wc_get_product( $product_id );

        if ( ! ComboStockService::is_combo_product( $product ) ) {
            return $passed;
        }

        $quantity = max( 1, wc_stock_amount( $quantity ) );

        if ( ! ComboStockService::combo_is_available( $product_id, $quantity ) ) {
            wc_add_notice( __( 'No hay stock suficiente para comprar este combo.', 'sultana-commerce-core' ), 'error' );
            return false;
        }

        $demand = ComboStockService::build_cart_component_demand();

        foreach ( ComboStockService::get_components( $product_id ) as $component ) {
            ComboStockService::add_component_demand(
                $demand,
                $component,
                max( 1, absint( $component['quantity'] ?? 0 ) ) * $quantity
            );
        }

        return self::validate_demand_entries( $demand );
    }

    public static function validate_cart_quantity_update( bool $passed, string $cart_item_key, array $values, int $quantity ): bool
    {
        if ( ! $passed || ! self::cart_contains_combo() ) {
            return $passed;
        }

        return ComboStockService::validate_cart_component_demand(
            [
                $cart_item_key => max( 0, wc_stock_amount( $quantity ) ),
            ]
        );
    }

    public static function validate_cart_items(): void
    {
        if ( ! self::cart_contains_combo() ) {
            return;
        }

        ComboStockService::validate_cart_component_demand();
    }

    public static function sync_combos_for_stock_product( $product ): void
    {
        if ( is_numeric( $product ) && function_exists( 'wc_get_product' ) ) {
            $product = wc_get_product( absint( $product ) );
        }

        if ( $product instanceof WC_Product ) {
            ComboStockService::sync_combos_for_component_product( $product );
        }
    }

    public static function sync_combos_for_price_product( $product, array $updated_props ): void
    {
        if ( ! $product instanceof WC_Product ) {
            return;
        }

        if ( ComboStockService::is_combo_product( $product ) ) {
            if ( ! array_intersect( [ 'regular_price', 'sale_price', 'date_on_sale_from', 'date_on_sale_to', 'price' ], $updated_props ) ) {
                return;
            }

            ComboStockService::sync_combo_prices( $product->get_id(), $product );
            return;
        }

        if ( ! in_array( 'regular_price', $updated_props, true ) ) {
            return;
        }

        ComboStockService::sync_combo_prices_for_component_product( $product );
    }

    private static function cart_contains_combo(): bool
    {
        if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
            return false;
        }

        foreach ( WC()->cart->get_cart() as $cart_item ) {
            if ( ComboStockService::is_combo_product( $cart_item['data'] ?? null ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, array{product:WC_Product,demand:int}> $demand
     */
    private static function validate_demand_entries( array $demand ): bool
    {
        foreach ( $demand as $entry ) {
            $product = $entry['product'] ?? null;

            if ( ! $product instanceof WC_Product ) {
                continue;
            }

            $needed    = max( 0, (int) ( $entry['demand'] ?? 0 ) );
            $available = ComboStockService::get_physical_stock_quantity( $product );

            if ( null === $available ) {
                continue;
            }

            if ( $needed > $available ) {
                wc_add_notice(
                    sprintf(
                        /* translators: 1: product name, 2: available stock. */
                        __( 'No hay stock suficiente de %1$s para completar este combo. Disponibles: %2$d.', 'sultana-commerce-core' ),
                        $product->get_name(),
                        $available
                    ),
                    'error'
                );

                return false;
            }
        }

        return true;
    }
}

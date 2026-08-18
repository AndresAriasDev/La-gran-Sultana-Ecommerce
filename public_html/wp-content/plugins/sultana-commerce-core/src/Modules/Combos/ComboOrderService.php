<?php

namespace Sultana\CommerceCore\Modules\Combos;

use WC_Order;
use WC_Order_Item_Product;
use WC_Product;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ComboOrderService
{
    public const SNAPSHOT_META = '_scc_combo_components_snapshot';
    private const STOCK_REDUCED_META = '_scc_combo_components_stock_reduced';
    private const STOCK_RESTORED_META = '_scc_combo_components_stock_restored';

    public static function add_combo_snapshot_to_order_item( $item, string $cart_item_key, array $values, $order ): void
    {
        if ( ! $item instanceof WC_Order_Item_Product ) {
            return;
        }

        $product = $values['data'] ?? null;

        if ( ! ComboStockService::is_combo_product( $product ) ) {
            return;
        }

        $snapshot = ComboStockService::build_snapshot( $product->get_id() );

        if ( empty( $snapshot ) ) {
            return;
        }

        $item->add_meta_data( self::SNAPSHOT_META, $snapshot, true );
    }

    public static function hide_combo_order_item_meta( array $hidden_meta ): array
    {
        $hidden_meta[] = self::SNAPSHOT_META;
        $hidden_meta[] = self::STOCK_REDUCED_META;
        $hidden_meta[] = self::STOCK_RESTORED_META;

        return array_values( array_unique( $hidden_meta ) );
    }

    public static function reduce_combo_component_stock( $order ): void
    {
        if ( ! $order instanceof WC_Order || ! function_exists( 'wc_update_product_stock' ) ) {
            return;
        }

        foreach ( $order->get_items( 'line_item' ) as $item ) {
            if ( ! $item instanceof WC_Order_Item_Product || 'yes' === (string) $item->get_meta( self::STOCK_REDUCED_META, true ) ) {
                continue;
            }

            $product = $item->get_product();

            if ( ! ComboStockService::is_combo_product( $product ) ) {
                continue;
            }

            $components = self::get_item_components_snapshot( $item, $product );

            if ( empty( $components ) ) {
                continue;
            }

            foreach ( $components as $component ) {
                $component_product = ComboStockService::get_component_stock_product( $component );

                if ( ! $component_product instanceof WC_Product ) {
                    continue;
                }

                $quantity = max( 1, absint( $component['quantity'] ?? 0 ) ) * max( 1, absint( $item->get_quantity() ) );
                wc_update_product_stock( $component_product, $quantity, 'decrease' );
                ComboStockService::sync_combos_for_component_product( $component_product );
            }

            $item->update_meta_data( self::STOCK_REDUCED_META, 'yes' );
            $item->update_meta_data( self::STOCK_RESTORED_META, 'no' );
            $item->save();
        }
    }

    public static function restore_combo_component_stock( $order ): void
    {
        if ( ! $order instanceof WC_Order || ! function_exists( 'wc_update_product_stock' ) ) {
            return;
        }

        foreach ( $order->get_items( 'line_item' ) as $item ) {
            if (
                ! $item instanceof WC_Order_Item_Product
                || 'yes' !== (string) $item->get_meta( self::STOCK_REDUCED_META, true )
                || 'yes' === (string) $item->get_meta( self::STOCK_RESTORED_META, true )
            ) {
                continue;
            }

            $product = $item->get_product();

            if ( ! ComboStockService::is_combo_product( $product ) ) {
                continue;
            }

            $components = self::get_item_components_snapshot( $item, $product );

            if ( empty( $components ) ) {
                continue;
            }

            foreach ( $components as $component ) {
                $component_product = ComboStockService::get_component_stock_product( $component );

                if ( ! $component_product instanceof WC_Product ) {
                    continue;
                }

                $quantity = max( 1, absint( $component['quantity'] ?? 0 ) ) * max( 1, absint( $item->get_quantity() ) );
                wc_update_product_stock( $component_product, $quantity, 'increase' );
                ComboStockService::sync_combos_for_component_product( $component_product );
            }

            $item->update_meta_data( self::STOCK_REDUCED_META, 'no' );
            $item->update_meta_data( self::STOCK_RESTORED_META, 'yes' );
            $item->save();
        }
    }

    /**
     * @return array<int, array{product_id:int,variation_id:int,quantity:int}>
     */
    private static function get_item_components_snapshot( WC_Order_Item_Product $item, WC_Product $product ): array
    {
        $snapshot = $item->get_meta( self::SNAPSHOT_META, true );

        if ( is_array( $snapshot ) && ! empty( $snapshot ) ) {
            return ComboStockService::sanitize_components( $snapshot, 0 );
        }

        return ComboStockService::get_components( $product->get_id() );
    }
}

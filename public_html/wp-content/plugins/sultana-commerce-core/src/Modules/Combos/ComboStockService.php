<?php

namespace Sultana\CommerceCore\Modules\Combos;

use WC_Product;
use WC_Product_Variation;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ComboStockService
{
    public const COMPONENTS_META = '_scc_combo_components';
    public const COMPONENT_INDEX_META = '_scc_combo_parent_ids';

    /**
     * @var array<int, array<int, array{product_id:int,variation_id:int,quantity:int}>>
     */
    private static array $components_cache = [];

    /**
     * @var array<int, array<int, array<string,mixed>>>
     */
    private static array $snapshot_cache = [];

    public static function clear_cache( int $combo_id = 0 ): void
    {
        if ( $combo_id > 0 ) {
            unset( self::$components_cache[ $combo_id ], self::$snapshot_cache[ $combo_id ] );
            return;
        }

        self::$components_cache = [];
        self::$snapshot_cache   = [];
    }

    public static function is_combo_product( $product ): bool
    {
        return $product instanceof WC_Product && 'combo' === $product->get_type();
    }

    /**
     * @return array<int, array{product_id:int,variation_id:int,quantity:int}>
     */
    public static function get_components( int $combo_id ): array
    {
        if ( $combo_id <= 0 ) {
            return [];
        }

        if ( isset( self::$components_cache[ $combo_id ] ) ) {
            return self::$components_cache[ $combo_id ];
        }

        $raw = get_post_meta( $combo_id, self::COMPONENTS_META, true );

        if ( ! is_array( $raw ) ) {
            self::$components_cache[ $combo_id ] = [];
            return [];
        }

        $components = [];

        foreach ( $raw as $component ) {
            if ( ! is_array( $component ) ) {
                continue;
            }

            $product_id   = absint( $component['product_id'] ?? 0 );
            $variation_id = absint( $component['variation_id'] ?? 0 );
            $quantity     = max( 1, absint( $component['quantity'] ?? 0 ) );

            if ( ! $product_id || ! $quantity ) {
                continue;
            }

            $components[] = [
                'product_id'   => $product_id,
                'variation_id' => $variation_id,
                'quantity'     => $quantity,
            ];
        }

        self::$components_cache[ $combo_id ] = $components;

        return $components;
    }

    /**
     * @param mixed $raw_components
     * @return array<int, array{product_id:int,variation_id:int,quantity:int}>
     */
    public static function sanitize_components( $raw_components, int $combo_id = 0 ): array
    {
        if ( ! is_array( $raw_components ) || ! function_exists( 'wc_get_product' ) ) {
            return [];
        }

        $components = [];

        foreach ( $raw_components as $raw_component ) {
            if ( ! is_array( $raw_component ) ) {
                continue;
            }

            $quantity  = max( 1, absint( $raw_component['quantity'] ?? 0 ) );
            $component = self::normalize_component( $raw_component, $quantity );

            if ( empty( $component ) ) {
                continue;
            }

            $product_id   = absint( $component['product_id'] ?? 0 );
            $variation_id = absint( $component['variation_id'] ?? 0 );

            if ( ! $product_id || ! $quantity || $product_id === $combo_id ) {
                continue;
            }

            $key = self::stock_unit_key( $variation_id ?: $product_id );

            if ( isset( $components[ $key ] ) ) {
                $components[ $key ]['quantity'] += $quantity;
                continue;
            }

            $components[ $key ] = [
                'product_id'   => $product_id,
                'variation_id' => $variation_id,
                'quantity'     => $quantity,
            ];
        }

        return array_values( $components );
    }

    /**
     * @param array<string,mixed> $raw_component
     * @return array{product_id:int,variation_id:int,quantity:int}|array{}
     */
    private static function normalize_component( array $raw_component, int $quantity ): array
    {
        if ( $quantity <= 0 || ! function_exists( 'wc_get_product' ) ) {
            return [];
        }

        $selected_id = absint( $raw_component['selected_id'] ?? 0 );
        $selected    = $selected_id ? wc_get_product( $selected_id ) : null;

        if ( self::is_variation_product( $selected ) ) {
            $variation_id = $selected->get_id();
            $parent_id    = absint( $selected->get_parent_id() );
            $parent       = $parent_id ? wc_get_product( $parent_id ) : null;

            if ( ! $parent instanceof WC_Product || ! $parent->is_type( 'variable' ) || self::is_combo_product( $parent ) ) {
                return [];
            }

            if ( (int) $selected->get_parent_id() !== $parent_id ) {
                return [];
            }

            return [
                'product_id'   => $parent_id,
                'variation_id' => $variation_id,
                'quantity'     => $quantity,
            ];
        }

        if ( $selected instanceof WC_Product ) {
            if ( self::is_combo_product( $selected ) || $selected->is_type( 'variable' ) ) {
                return [];
            }

            return [
                'product_id'   => $selected->get_id(),
                'variation_id' => 0,
                'quantity'     => $quantity,
            ];
        }

        $product_id   = absint( $raw_component['product_id'] ?? 0 );
        $variation_id = absint( $raw_component['variation_id'] ?? 0 );

        if ( $variation_id ) {
            $variation = wc_get_product( $variation_id );

            if ( ! self::is_variation_product( $variation ) ) {
                return [];
            }

            $parent_id = absint( $variation->get_parent_id() );

            if ( ! $product_id ) {
                $product_id = $parent_id;
            }

            $parent = $product_id ? wc_get_product( $product_id ) : null;

            if ( ! $parent instanceof WC_Product || ! $parent->is_type( 'variable' ) || $parent_id !== $product_id || self::is_combo_product( $parent ) ) {
                return [];
            }

            return [
                'product_id'   => $product_id,
                'variation_id' => $variation_id,
                'quantity'     => $quantity,
            ];
        }

        $product = $product_id ? wc_get_product( $product_id ) : null;

        if ( ! $product instanceof WC_Product || self::is_combo_product( $product ) || $product->is_type( 'variable' ) ) {
            return [];
        }

        return [
            'product_id'   => $product->get_id(),
            'variation_id' => 0,
            'quantity'     => $quantity,
        ];
    }

    private static function is_variation_product( $product ): bool
    {
        return $product instanceof WC_Product_Variation
            || ( $product instanceof WC_Product && 'variation' === $product->get_type() );
    }

    public static function save_components( int $combo_id, array $components ): void
    {
        $old_components = self::get_components( $combo_id );

        update_post_meta( $combo_id, self::COMPONENTS_META, array_values( $components ) );
        self::clear_cache( $combo_id );
        self::update_component_index( $combo_id, $old_components, $components );
        self::sync_combo_stock_status( $combo_id );
        self::sync_combo_prices( $combo_id );
    }

    public static function delete_components( int $combo_id ): void
    {
        $old_components = self::get_components( $combo_id );

        delete_post_meta( $combo_id, self::COMPONENTS_META );
        self::clear_cache( $combo_id );
        self::update_component_index( $combo_id, $old_components, [] );
    }

    public static function get_stock_unit_id( array $component ): int
    {
        return absint( $component['variation_id'] ?? 0 ) ?: absint( $component['product_id'] ?? 0 );
    }

    public static function stock_unit_key( int $stock_unit_id ): string
    {
        return 'stock:' . absint( $stock_unit_id );
    }

    public static function get_component_stock_product( array $component ): ?WC_Product
    {
        if ( ! function_exists( 'wc_get_product' ) ) {
            return null;
        }

        $stock_unit_id = self::get_stock_unit_id( $component );
        $product       = $stock_unit_id ? wc_get_product( $stock_unit_id ) : null;

        return $product instanceof WC_Product ? $product : null;
    }

    public static function get_physical_stock_quantity( WC_Product $product ): ?int
    {
        if ( ! $product->is_in_stock() ) {
            return 0;
        }

        if ( ! $product->managing_stock() && $product instanceof WC_Product_Variation ) {
            $parent_id = absint( $product->get_parent_id() );
            $parent    = $parent_id ? wc_get_product( $parent_id ) : null;

            if ( $parent instanceof WC_Product && $parent->managing_stock() ) {
                $product = $parent;
            }
        }

        if ( ! $product->managing_stock() ) {
            return null;
        }

        $stock_quantity = $product->get_stock_quantity();

        if ( null === $stock_quantity ) {
            return 0;
        }

        return max( 0, (int) floor( (float) $stock_quantity ) );
    }

    public static function get_max_combo_quantity( int $combo_id ): int
    {
        $components = self::get_components( $combo_id );

        if ( empty( $components ) ) {
            return 0;
        }

        $max = null;

        foreach ( $components as $component ) {
            $component_product = self::get_component_stock_product( $component );

            if ( ! $component_product instanceof WC_Product ) {
                return 0;
            }

            $required = max( 1, absint( $component['quantity'] ?? 0 ) );
            $stock    = self::get_physical_stock_quantity( $component_product );

            if ( null === $stock ) {
                continue;
            }

            $allowed  = (int) floor( $stock / $required );
            $max      = null === $max ? $allowed : min( $max, $allowed );
        }

        return null === $max ? -1 : max( 0, (int) $max );
    }

    public static function combo_is_available( int $combo_id, int $quantity = 1 ): bool
    {
        $max_quantity = self::get_max_combo_quantity( $combo_id );

        return $max_quantity < 0 || $max_quantity >= max( 1, $quantity );
    }

    public static function get_combo_weight( int $combo_id ): string
    {
        $weight = 0.0;

        foreach ( self::get_components( $combo_id ) as $component ) {
            $component_product = self::get_component_stock_product( $component );

            if ( ! $component_product instanceof WC_Product ) {
                continue;
            }

            $component_weight = (float) $component_product->get_weight();

            if ( $component_weight <= 0 ) {
                continue;
            }

            $weight += $component_weight * max( 1, absint( $component['quantity'] ?? 0 ) );
        }

        return $weight > 0 ? wc_format_decimal( $weight, false, true ) : '';
    }

    /**
     * @return array<int, array{product_id:int,variation_id:int,quantity:int,name:string,image_id:int,attributes:array<int,array{label:string,value:string}>}>
     */
    public static function get_display_components( int $combo_id ): array
    {
        $display_components = [];

        foreach ( self::get_components( $combo_id ) as $component ) {
            $component_product = self::get_component_stock_product( $component );

            if ( ! $component_product instanceof WC_Product ) {
                continue;
            }

            $parent_product = self::is_variation_product( $component_product )
                ? wc_get_product( $component_product->get_parent_id() )
                : null;
            $name           = $parent_product instanceof WC_Product ? $parent_product->get_name() : $component_product->get_name();
            $image_id       = absint( $component_product->get_image_id() );

            if ( ! $image_id && $parent_product instanceof WC_Product ) {
                $image_id = absint( $parent_product->get_image_id() );
            }

            $display_components[] = [
                'product_id'   => absint( $component['product_id'] ?? 0 ),
                'variation_id' => absint( $component['variation_id'] ?? 0 ),
                'quantity'     => max( 1, absint( $component['quantity'] ?? 0 ) ),
                'name'         => $name,
                'image_id'     => $image_id,
                'attributes'   => self::get_display_component_attributes( $component_product, $parent_product instanceof WC_Product ? $parent_product : null ),
            ];
        }

        return $display_components;
    }

    public static function get_components_regular_total( int $combo_id ): float
    {
        $components_total = 0.0;

        foreach ( self::get_components( $combo_id ) as $component ) {
            $component_product = self::get_component_stock_product( $component );

            if ( ! $component_product instanceof WC_Product ) {
                continue;
            }

            $price = $component_product->get_regular_price();

            if ( '' === $price ) {
                continue;
            }

            $components_total += (float) $price * max( 1, absint( $component['quantity'] ?? 0 ) );
        }

        return $components_total;
    }

    /**
     * @return array{components_total:float,combo_regular_price:?float,combo_price:?float,savings:float,savings_percentage:float}
     */
    public static function get_pricing_summary( WC_Product $combo_product ): array
    {
        $components_total = self::get_components_regular_total( $combo_product->get_id() );
        $combo_price = $combo_product->get_price();
        $combo_price = '' === $combo_price ? null : (float) $combo_price;
        $regular_price = $combo_product->get_regular_price();
        $regular_price = '' === $regular_price ? null : (float) $regular_price;
        $savings     = null !== $combo_price ? max( 0.0, $components_total - $combo_price ) : 0.0;

        return [
            'components_total'   => $components_total,
            'combo_regular_price' => $regular_price,
            'combo_price'        => $combo_price,
            'savings'            => $savings,
            'savings_percentage' => $components_total > 0 && $savings > 0 ? ( $savings / $components_total ) * 100 : 0.0,
        ];
    }

    public static function sync_combo_prices( int $combo_id, ?WC_Product $combo_product = null, bool $save = true ): void
    {
        static $syncing = [];

        if ( $combo_id <= 0 || ! function_exists( 'wc_get_product' ) || isset( $syncing[ $combo_id ] ) ) {
            return;
        }

        $combo_product = $combo_product instanceof WC_Product ? $combo_product : wc_get_product( $combo_id );

        if ( ! self::is_combo_product( $combo_product ) ) {
            return;
        }

        $syncing[ $combo_id ] = true;

        $regular_total = self::get_components_regular_total( $combo_id );
        $regular_price = $regular_total > 0 ? wc_format_decimal( $regular_total ) : '';
        $sale_price    = $combo_product->get_sale_price( 'edit' );
        $price         = '' !== $sale_price && $combo_product->is_on_sale()
            ? $sale_price
            : $regular_price;
        $changed       = $combo_product->get_regular_price( 'edit' ) !== $regular_price
            || $combo_product->get_price( 'edit' ) !== $price;

        $combo_product->set_regular_price( $regular_price );

        if ( '' === $regular_price || ( '' !== $sale_price && (float) $sale_price >= (float) $regular_price ) ) {
            $changed = $changed
                || '' !== $combo_product->get_sale_price( 'edit' )
                || null !== $combo_product->get_date_on_sale_from( 'edit' )
                || null !== $combo_product->get_date_on_sale_to( 'edit' );
            $combo_product->set_sale_price( '' );
            $combo_product->set_date_on_sale_from( null );
            $combo_product->set_date_on_sale_to( null );
            $sale_price = '';
            $price      = $regular_price;
        }

        $combo_product->set_price( $price );

        if ( $save && $changed ) {
            $combo_product->save();
        }

        unset( $syncing[ $combo_id ] );
    }

    /**
     * @return array<int, array{label:string,value:string}>
     */
    private static function get_display_component_attributes( WC_Product $product, ?WC_Product $parent_product ): array
    {
        if ( ! self::is_variation_product( $product ) ) {
            return [];
        }

        $attributes = [];

        foreach ( $product->get_attributes() as $attribute_name => $attribute_value ) {
            $attribute_value = (string) $attribute_value;

            if ( '' === $attribute_value ) {
                continue;
            }

            $taxonomy = preg_replace( '/^attribute_/', '', (string) $attribute_name );
            $label    = function_exists( 'wc_attribute_label' )
                ? wc_attribute_label( $taxonomy, $parent_product )
                : str_replace( [ 'pa_', '-' ], [ '', ' ' ], $taxonomy );
            $value    = $attribute_value;

            if ( taxonomy_exists( $taxonomy ) ) {
                $term = get_term_by( 'slug', $attribute_value, $taxonomy );

                if ( $term && ! is_wp_error( $term ) ) {
                    $value = $term->name;
                }
            }

            $attributes[] = [
                'label' => wp_strip_all_tags( $label ),
                'value' => wp_strip_all_tags( $value ),
            ];
        }

        return $attributes;
    }

    /**
     * @return array<string, array{product:WC_Product,demand:int}>
     */
    public static function build_cart_component_demand( array $quantity_overrides = [] ): array
    {
        if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
            return [];
        }

        $demand = [];

        foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
            $quantity = array_key_exists( $cart_item_key, $quantity_overrides )
                ? wc_stock_amount( $quantity_overrides[ $cart_item_key ] )
                : wc_stock_amount( $cart_item['quantity'] ?? 0 );

            if ( $quantity <= 0 ) {
                continue;
            }

            $product = $cart_item['data'] ?? null;

            if ( self::is_combo_product( $product ) ) {
                foreach ( self::get_components( $product->get_id() ) as $component ) {
                    self::add_component_demand( $demand, $component, max( 1, absint( $component['quantity'] ?? 0 ) ) * $quantity );
                }

                continue;
            }

            if ( $product instanceof WC_Product ) {
                $stock_unit_id = absint( $cart_item['variation_id'] ?? 0 ) ?: absint( $cart_item['product_id'] ?? $product->get_id() );
                $stock_product = $stock_unit_id ? wc_get_product( $stock_unit_id ) : $product;

                if ( $stock_product instanceof WC_Product ) {
                    $key = self::stock_unit_key( $stock_unit_id ?: $stock_product->get_id() );
                    if ( ! isset( $demand[ $key ] ) ) {
                        $demand[ $key ] = [
                            'product' => $stock_product,
                            'demand'  => 0,
                        ];
                    }
                    $demand[ $key ]['demand'] += $quantity;
                }
            }
        }

        return $demand;
    }

    public static function validate_cart_component_demand( array $quantity_overrides = [] ): bool
    {
        $demand = self::build_cart_component_demand( $quantity_overrides );

        foreach ( $demand as $entry ) {
            $product = $entry['product'] ?? null;

            if ( ! $product instanceof WC_Product ) {
                continue;
            }

            $available = self::get_physical_stock_quantity( $product );
            $needed    = max( 0, (int) ( $entry['demand'] ?? 0 ) );

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

    public static function add_component_demand( array &$demand, array $component, int $quantity ): void
    {
        $component_product = self::get_component_stock_product( $component );

        if ( ! $component_product instanceof WC_Product || $quantity <= 0 ) {
            return;
        }

        $key = self::stock_unit_key( self::get_stock_unit_id( $component ) );

        if ( ! isset( $demand[ $key ] ) ) {
            $demand[ $key ] = [
                'product' => $component_product,
                'demand'  => 0,
            ];
        }

        $demand[ $key ]['demand'] += $quantity;
    }

    /**
     * @return array<int, array<string,mixed>>
     */
    public static function build_snapshot( int $combo_id ): array
    {
        if ( isset( self::$snapshot_cache[ $combo_id ] ) ) {
            return self::$snapshot_cache[ $combo_id ];
        }

        $snapshot = [];

        foreach ( self::get_components( $combo_id ) as $component ) {
            $component_product = self::get_component_stock_product( $component );

            if ( ! $component_product instanceof WC_Product ) {
                continue;
            }

            $attributes = [];

            if ( $component_product instanceof WC_Product_Variation ) {
                foreach ( $component_product->get_attributes() as $name => $value ) {
                    if ( '' === (string) $value ) {
                        continue;
                    }

                    $attributes[ (string) $name ] = (string) $value;
                }
            }

            $snapshot[] = [
                'product_id'   => absint( $component['product_id'] ?? 0 ),
                'variation_id' => absint( $component['variation_id'] ?? 0 ),
                'quantity'     => max( 1, absint( $component['quantity'] ?? 0 ) ),
                'name'         => $component_product->get_name(),
                'sku'          => $component_product->get_sku(),
                'attributes'   => $attributes,
            ];
        }

        self::$snapshot_cache[ $combo_id ] = $snapshot;

        return $snapshot;
    }

    public static function sync_combo_stock_status( int $combo_id ): void
    {
        if ( $combo_id <= 0 ) {
            return;
        }

        $status = self::combo_is_available( $combo_id, 1 ) ? 'instock' : 'outofstock';

        if ( function_exists( 'wc_update_product_stock_status' ) ) {
            wc_update_product_stock_status( $combo_id, $status );
            return;
        }

        update_post_meta( $combo_id, '_stock_status', $status );
    }

    public static function sync_combos_for_component_product( WC_Product $product ): void
    {
        $ids = self::get_combo_ids_for_component_id( $product->get_id() );

        if ( $product instanceof WC_Product_Variation ) {
            $ids = array_merge( $ids, self::get_combo_ids_for_component_id( $product->get_parent_id() ) );
        }

        foreach ( array_unique( array_map( 'absint', $ids ) ) as $combo_id ) {
            self::clear_cache( $combo_id );
            self::sync_combo_stock_status( $combo_id );
        }
    }

    public static function sync_combo_prices_for_component_product( WC_Product $product ): void
    {
        $ids = self::get_combo_ids_for_component_id( $product->get_id() );

        if ( $product instanceof WC_Product_Variation ) {
            $ids = array_merge( $ids, self::get_combo_ids_for_component_id( $product->get_parent_id() ) );
        }

        foreach ( array_unique( array_map( 'absint', $ids ) ) as $combo_id ) {
            self::clear_cache( $combo_id );
            self::sync_combo_prices( $combo_id );
        }
    }

    /**
     * @return array<int, int>
     */
    private static function get_combo_ids_for_component_id( int $component_id ): array
    {
        if ( $component_id <= 0 ) {
            return [];
        }

        $ids = get_post_meta( $component_id, self::COMPONENT_INDEX_META, true );

        return is_array( $ids ) ? array_values( array_unique( array_map( 'absint', $ids ) ) ) : [];
    }

    /**
     * @param array<int, array{product_id:int,variation_id:int,quantity:int}> $old_components
     * @param array<int, array{product_id:int,variation_id:int,quantity:int}> $new_components
     */
    private static function update_component_index( int $combo_id, array $old_components, array $new_components ): void
    {
        $old_ids = self::component_index_ids( $old_components );
        $new_ids = self::component_index_ids( $new_components );

        foreach ( array_diff( $old_ids, $new_ids ) as $component_id ) {
            $ids = self::get_combo_ids_for_component_id( $component_id );
            $ids = array_values( array_diff( $ids, [ $combo_id ] ) );
            update_post_meta( $component_id, self::COMPONENT_INDEX_META, $ids );
        }

        foreach ( $new_ids as $component_id ) {
            $ids = self::get_combo_ids_for_component_id( $component_id );
            $ids[] = $combo_id;
            update_post_meta( $component_id, self::COMPONENT_INDEX_META, array_values( array_unique( array_map( 'absint', $ids ) ) ) );
        }
    }

    /**
     * @param array<int, array{product_id:int,variation_id:int,quantity:int}> $components
     * @return array<int, int>
     */
    private static function component_index_ids( array $components ): array
    {
        $ids = [];

        foreach ( $components as $component ) {
            $product_id   = absint( $component['product_id'] ?? 0 );
            $variation_id = absint( $component['variation_id'] ?? 0 );

            if ( $product_id ) {
                $ids[] = $product_id;
            }

            if ( $variation_id ) {
                $ids[] = $variation_id;
            }
        }

        return array_values( array_unique( $ids ) );
    }
}

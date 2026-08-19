<?php

namespace Sultana\CommerceCore\Modules\Combos;

use WC_Product;
use WC_Product_Variation;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ComboComponentService
{
    /**
     * @return array<int, array{product_id:int,variation_id:int,selected_id:int,label:string,regular_price:string}>
     */
    public static function search_components( string $term, int $limit = 30, array $exclude = [] ): array
    {
        if ( '' === trim( $term ) || ! function_exists( 'wc_get_product' ) ) {
            return [];
        }

        $limit   = $limit > 0 ? min( $limit, 50 ) : 30;
        $exclude = array_values( array_unique( array_map( 'absint', $exclude ) ) );
        $ids     = self::search_product_and_variation_ids( $term, $limit * 3, $exclude );
        $results = [];

        foreach ( $ids as $id ) {
            if ( count( $results ) >= $limit ) {
                break;
            }

            $product = wc_get_product( $id );

            if ( ! $product instanceof WC_Product || self::should_exclude_component_option( $product, $exclude ) ) {
                continue;
            }

            $variation_id = self::is_variation_product( $product ) ? $product->get_id() : 0;
            $product_id   = $variation_id ? absint( $product->get_parent_id() ) : $product->get_id();

            $results[] = [
                'product_id'   => $product_id,
                'variation_id' => $variation_id,
                'selected_id'  => $variation_id ?: $product_id,
                'label'        => self::format_component_option_label( $product ),
                'regular_price' => self::get_component_regular_price_for_display( $product ),
            ];
        }

        return $results;
    }

    /**
     * @param mixed $raw_components
     * @return array<int, array{product_id:int,variation_id:int,quantity:int}>|WP_Error
     */
    public static function validate_components( $raw_components, int $combo_id = 0 )
    {
        $errors = new WP_Error();

        if ( ! is_array( $raw_components ) || empty( $raw_components ) ) {
            $errors->add( 'scc_combo_components_empty', __( 'Selecciona al menos un componente.', 'sultana-commerce-core' ) );
            return $errors;
        }

        if ( ! function_exists( 'wc_get_product' ) ) {
            $errors->add( 'scc_combo_components_unavailable', __( 'WooCommerce no esta disponible para validar componentes.', 'sultana-commerce-core' ) );
            return $errors;
        }

        $components = [];

        foreach ( $raw_components as $index => $raw_component ) {
            if ( ! is_array( $raw_component ) ) {
                $errors->add( 'scc_combo_component_invalid', __( 'El componente seleccionado no es valido.', 'sultana-commerce-core' ) );
                continue;
            }

            $quantity = self::validate_quantity( $raw_component['quantity'] ?? null, $errors );
            $component = self::validate_component_product( $raw_component, $combo_id, $errors );

            if ( ! $component || null === $quantity ) {
                continue;
            }

            $key = ComboStockService::stock_unit_key( $component['variation_id'] ?: $component['product_id'] );

            if ( isset( $components[ $key ] ) ) {
                $components[ $key ]['quantity'] += $quantity;
                continue;
            }

            $components[ $key ] = [
                'product_id'   => $component['product_id'],
                'variation_id' => $component['variation_id'],
                'quantity'     => $quantity,
            ];
        }

        if ( $errors->has_errors() ) {
            return $errors;
        }

        if ( empty( $components ) ) {
            $errors->add( 'scc_combo_components_empty', __( 'Selecciona al menos un componente.', 'sultana-commerce-core' ) );
            return $errors;
        }

        return array_values( $components );
    }

    /**
     * @param array<int, array{product_id:int,variation_id:int,quantity:int}> $components
     */
    public static function save_components( int $combo_id, array $components ): void
    {
        ComboStockService::save_components( $combo_id, $components );
    }

    /**
     * @return array<int, array{product_id:int,variation_id:int,quantity:int}>
     */
    public static function get_components( int $combo_id ): array
    {
        return ComboStockService::get_components( $combo_id );
    }

    public static function format_component_option_label( WC_Product $product ): string
    {
        if ( self::is_variation_product( $product ) ) {
            $parent_id = absint( $product->get_parent_id() );
            $parent    = $parent_id ? wc_get_product( $parent_id ) : null;
            $name      = $parent instanceof WC_Product ? $parent->get_name() : $product->get_name();
            $variation = self::format_variation_attributes_label( $product, $parent instanceof WC_Product ? $parent : null );

            return rawurldecode( wp_strip_all_tags( $variation ? sprintf( '%s - %s', $name, $variation ) : $product->get_formatted_name() ) );
        }

        return rawurldecode( wp_strip_all_tags( $product->get_formatted_name() ) );
    }

    /**
     * @return array<int>
     */
    public static function get_derived_image_ids( int $combo_id ): array
    {
        $image_ids = [];

        foreach ( ComboStockService::get_components( $combo_id ) as $component ) {
            $image_id = self::get_component_image_id( $component );

            if ( $image_id && ! in_array( $image_id, $image_ids, true ) ) {
                $image_ids[] = $image_id;
            }
        }

        return $image_ids;
    }

    public static function get_primary_derived_image_id( int $combo_id ): int
    {
        $image_ids = self::get_derived_image_ids( $combo_id );

        return $image_ids[0] ?? 0;
    }

    /**
     * @param array<int, array{product_id:int,variation_id:int,quantity:int}> $components
     */
    public static function calculate_regular_price( array $components ): float
    {
        return ComboStockService::calculate_components_regular_total( $components );
    }

    /**
     * @return array{product_id:int,variation_id:int}|null
     */
    private static function validate_component_product( array $raw_component, int $combo_id, WP_Error $errors ): ?array
    {
        $selected_id  = absint( $raw_component['selected_id'] ?? 0 );
        $product_id   = absint( $raw_component['product_id'] ?? 0 );
        $variation_id = absint( $raw_component['variation_id'] ?? 0 );

        if ( $selected_id ) {
            $selected = wc_get_product( $selected_id );

            if ( ! $selected instanceof WC_Product ) {
                $errors->add( 'scc_combo_component_missing', __( 'El producto seleccionado no existe.', 'sultana-commerce-core' ) );
                return null;
            }

            if ( self::is_variation_product( $selected ) ) {
                return self::validate_variation_component( $selected, $combo_id, $errors );
            }

            return self::validate_simple_component( $selected, $combo_id, $errors );
        }

        if ( $variation_id ) {
            $variation = wc_get_product( $variation_id );

            if ( ! $variation instanceof WC_Product ) {
                $errors->add( 'scc_combo_variation_missing', __( 'La variacion seleccionada no existe.', 'sultana-commerce-core' ) );
                return null;
            }

            return self::validate_variation_component( $variation, $combo_id, $errors, $product_id );
        }

        if ( ! $product_id ) {
            $errors->add( 'scc_combo_component_missing', __( 'Selecciona un producto para cada componente.', 'sultana-commerce-core' ) );
            return null;
        }

        $product = wc_get_product( $product_id );

        if ( ! $product instanceof WC_Product ) {
            $errors->add( 'scc_combo_component_missing', __( 'El producto seleccionado no existe.', 'sultana-commerce-core' ) );
            return null;
        }

        return self::validate_simple_component( $product, $combo_id, $errors );
    }

    private static function get_component_image_id( array $component ): int
    {
        $component_product = ComboStockService::get_component_stock_product( $component );

        if ( ! $component_product instanceof WC_Product ) {
            return 0;
        }

        $image_id = absint( $component_product->get_image_id() );

        if ( $image_id || ! self::is_variation_product( $component_product ) ) {
            return $image_id;
        }

        $parent_id = absint( $component_product->get_parent_id() );
        $parent    = $parent_id ? wc_get_product( $parent_id ) : null;

        return $parent instanceof WC_Product ? absint( $parent->get_image_id() ) : 0;
    }

    private static function get_component_regular_price_for_display( WC_Product $product ): string
    {
        $price = $product->get_regular_price();

        if ( '' === $price ) {
            return '';
        }

        return wc_format_decimal( $price );
    }

    /**
     * @return array{product_id:int,variation_id:int}|null
     */
    private static function validate_simple_component( WC_Product $product, int $combo_id, WP_Error $errors ): ?array
    {
        if ( $combo_id > 0 && $product->get_id() === $combo_id ) {
            $errors->add( 'scc_combo_self_reference', __( 'Un combo no puede incluirse a si mismo.', 'sultana-commerce-core' ) );
            return null;
        }

        if ( ComboStockService::is_combo_product( $product ) ) {
            $errors->add( 'scc_combo_nested_combo', __( 'Los combos no pueden contener otros combos.', 'sultana-commerce-core' ) );
            return null;
        }

        if ( $product->is_type( 'variable' ) ) {
            $errors->add( 'scc_combo_variable_parent', __( 'Un producto variable debe agregarse mediante una variacion concreta.', 'sultana-commerce-core' ) );
            return null;
        }

        if ( 'trash' === $product->get_status() ) {
            $errors->add( 'scc_combo_component_trashed', __( 'No puedes usar productos en la papelera como componentes.', 'sultana-commerce-core' ) );
            return null;
        }

        if ( ! $product->is_type( 'simple' ) ) {
            $errors->add( 'scc_combo_component_type', __( 'Solo puedes agregar productos simples o variaciones concretas.', 'sultana-commerce-core' ) );
            return null;
        }

        return [
            'product_id'   => $product->get_id(),
            'variation_id' => 0,
        ];
    }

    /**
     * @return array{product_id:int,variation_id:int}|null
     */
    private static function validate_variation_component( WC_Product $variation, int $combo_id, WP_Error $errors, int $posted_parent_id = 0 ): ?array
    {
        if ( ! self::is_variation_product( $variation ) ) {
            $errors->add( 'scc_combo_variation_missing', __( 'La variacion seleccionada no existe.', 'sultana-commerce-core' ) );
            return null;
        }

        if ( 'trash' === $variation->get_status() ) {
            $errors->add( 'scc_combo_component_trashed', __( 'No puedes usar variaciones en la papelera como componentes.', 'sultana-commerce-core' ) );
            return null;
        }

        $parent_id = absint( $variation->get_parent_id() );
        $parent    = $parent_id ? wc_get_product( $parent_id ) : null;

        if ( $posted_parent_id && $posted_parent_id !== $parent_id ) {
            $errors->add( 'scc_combo_variation_parent_mismatch', __( 'La variacion seleccionada no pertenece al producto indicado.', 'sultana-commerce-core' ) );
            return null;
        }

        if ( ! $parent instanceof WC_Product || ! $parent->is_type( 'variable' ) || ComboStockService::is_combo_product( $parent ) ) {
            $errors->add( 'scc_combo_variation_parent_invalid', __( 'La variacion seleccionada no pertenece a un producto variable valido.', 'sultana-commerce-core' ) );
            return null;
        }

        if ( 'trash' === $parent->get_status() ) {
            $errors->add( 'scc_combo_component_trashed', __( 'No puedes usar variaciones de productos en la papelera como componentes.', 'sultana-commerce-core' ) );
            return null;
        }

        if ( $combo_id > 0 && $parent_id === $combo_id ) {
            $errors->add( 'scc_combo_self_reference', __( 'Un combo no puede incluirse a si mismo.', 'sultana-commerce-core' ) );
            return null;
        }

        return [
            'product_id'   => $parent_id,
            'variation_id' => $variation->get_id(),
        ];
    }

    private static function validate_quantity( $raw_quantity, WP_Error $errors ): ?int
    {
        if ( null === $raw_quantity || '' === $raw_quantity ) {
            $errors->add( 'scc_combo_quantity_missing', __( 'Indica la cantidad de cada componente.', 'sultana-commerce-core' ) );
            return null;
        }

        $raw_quantity = is_string( $raw_quantity ) ? trim( $raw_quantity ) : $raw_quantity;

        if ( ! is_numeric( $raw_quantity ) ) {
            $errors->add( 'scc_combo_quantity_invalid', __( 'La cantidad debe ser numerica.', 'sultana-commerce-core' ) );
            return null;
        }

        if ( (float) $raw_quantity <= 0 ) {
            $errors->add( 'scc_combo_quantity_positive', __( 'La cantidad debe ser mayor que cero.', 'sultana-commerce-core' ) );
            return null;
        }

        if ( ! preg_match( '/^[0-9]+$/', (string) $raw_quantity ) ) {
            $errors->add( 'scc_combo_quantity_invalid', __( 'La cantidad debe ser un numero entero.', 'sultana-commerce-core' ) );
            return null;
        }

        $quantity = (int) $raw_quantity;

        return $quantity;
    }

    /**
     * @return array<int>
     */
    private static function search_product_and_variation_ids( string $term, int $limit, array $exclude ): array
    {
        if ( class_exists( 'WC_Data_Store' ) ) {
            $data_store = \WC_Data_Store::load( 'product' );

            if ( is_object( $data_store ) && method_exists( $data_store, 'search_products' ) ) {
                $method     = new \ReflectionMethod( $data_store, 'search_products' );
                $parameters = $method->getNumberOfParameters();
                $arguments  = [ $term, '', true, false, $limit, [], $exclude ];
                $ids        = $data_store->search_products( ...array_slice( $arguments, 0, $parameters ) );

                return array_values( array_unique( array_map( 'absint', is_array( $ids ) ? $ids : [] ) ) );
            }
        }

        $query = new \WP_Query(
            [
                'fields'         => 'ids',
                'post_type'      => [ 'product', 'product_variation' ],
                'post_status'    => [ 'publish', 'private' ],
                'posts_per_page' => $limit,
                'post__not_in'   => $exclude,
                's'              => $term,
                'no_found_rows'  => true,
            ]
        );

        return array_values( array_unique( array_map( 'absint', $query->posts ) ) );
    }

    private static function should_exclude_component_option( WC_Product $product, array $exclude ): bool
    {
        if ( in_array( $product->get_id(), $exclude, true ) || ComboStockService::is_combo_product( $product ) ) {
            return true;
        }

        if ( self::is_variation_product( $product ) ) {
            $parent_id = absint( $product->get_parent_id() );
            $parent    = $parent_id ? wc_get_product( $parent_id ) : null;

            return ! $parent instanceof WC_Product || ! $parent->is_type( 'variable' ) || ComboStockService::is_combo_product( $parent );
        }

        return ! $product->is_type( 'simple' );
    }

    private static function is_variation_product( $product ): bool
    {
        return $product instanceof WC_Product_Variation
            || ( $product instanceof WC_Product && 'variation' === $product->get_type() );
    }

    private static function format_variation_attributes_label( WC_Product $variation, ?WC_Product $parent ): string
    {
        $details = [];

        foreach ( $variation->get_attributes() as $attribute_name => $attribute_value ) {
            $attribute_value = (string) $attribute_value;

            if ( '' === $attribute_value ) {
                continue;
            }

            $attribute_label = function_exists( 'wc_attribute_label' )
                ? wc_attribute_label( $attribute_name, $parent )
                : str_replace( [ 'attribute_', 'pa_', '-' ], [ '', '', ' ' ], $attribute_name );
            $attribute_display_value = $attribute_value;

            if ( taxonomy_exists( $attribute_name ) ) {
                $term = get_term_by( 'slug', $attribute_value, $attribute_name );

                if ( $term && ! is_wp_error( $term ) ) {
                    $attribute_display_value = $term->name;
                }
            }

            $details[] = wp_strip_all_tags( $attribute_label ) . ': ' . wp_strip_all_tags( $attribute_display_value );
        }

        return implode( ' - ', $details );
    }
}

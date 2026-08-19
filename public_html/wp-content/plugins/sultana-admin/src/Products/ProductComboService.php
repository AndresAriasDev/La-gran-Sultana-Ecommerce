<?php

namespace Sultana\Admin\Products;

use Sultana\Admin\Core\Capabilities;
use Sultana\CommerceCore\Modules\Combos\ComboComponentService;
use Sultana\CommerceCore\Modules\Combos\ProductCombo;
use Throwable;
use WC_Product;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ProductComboService
{
    public function default_product_data(): array
    {
        return [
            'product_type'      => 'combo',
            'name'              => '',
            'short_description' => '',
            'sku'               => '',
            'current_price'     => '',
            'sale_price'        => '',
            'category_ids'      => [],
            'brand_id'          => 0,
            'product_image_ids' => '',
            'combo_components'  => [],
            'status'            => 'draft',
        ];
    }

    public function create_combo_product( array $data ): array
    {
        $validated = $this->validate_combo_product_data( $data );

        if ( ! empty( $validated['errors'] ) ) {
            return [
                'success' => false,
                'errors'  => $validated['errors'],
            ];
        }

        if ( ! class_exists( ProductCombo::class ) ) {
            return [
                'success' => false,
                'errors'  => [ __( 'Commerce Core no esta listo para crear combos.', 'sultana-admin' ) ],
            ];
        }

        $product_id = 0;

        try {
            $product    = new ProductCombo();
            $product_id = $this->save_combo_product_data( $product, $validated['data'] );

            ComboComponentService::save_components( $product_id, $validated['data']['combo_components'] );
            $this->apply_combo_sale_price( $product_id, $validated['data']['sale_price'] );

            $saved_product = wc_get_product( $product_id );

            if ( ! $saved_product instanceof WC_Product || 'combo' !== $saved_product->get_type() ) {
                throw new \RuntimeException( 'combo_product_type_not_confirmed' );
            }
        } catch ( Throwable $exception ) {
            if ( $product_id ) {
                wp_trash_post( $product_id );
            }

            return [
                'success' => false,
                'errors'  => [ __( 'No se pudo crear el combo. Revisa los datos e intenta nuevamente.', 'sultana-admin' ) ],
            ];
        }

        return [
            'success'    => true,
            'errors'     => [],
            'product_id' => $product_id,
        ];
    }

    public function product_form_data( ProductCombo $product ): array
    {
        $components = class_exists( ComboComponentService::class ) ? ComboComponentService::get_components( $product->get_id() ) : [];

        return [
            'product_type'      => 'combo',
            'name'              => $product->get_name( 'edit' ),
            'short_description' => $product->get_short_description( 'edit' ),
            'sku'               => $product->get_sku( 'edit' ),
            'current_price'     => class_exists( ComboComponentService::class ) ? wc_format_decimal( ComboComponentService::calculate_regular_price( $components ) ) : '',
            'sale_price'        => $product->get_sale_price( 'edit' ),
            'category_ids'      => [],
            'brand_id'          => 0,
            'product_image_ids' => '',
            'combo_components'  => $components,
            'status'            => in_array( $product->get_status(), [ 'draft', 'publish' ], true ) ? $product->get_status() : 'draft',
        ];
    }

    public function update_combo_product( int $product_id, array $data ): array
    {
        $product = wc_get_product( $product_id );

        if ( ! $product instanceof ProductCombo ) {
            return [
                'success' => false,
                'errors'  => [ __( 'Ese combo no existe.', 'sultana-admin' ) ],
            ];
        }

        if ( ! current_user_can( 'edit_product', $product_id ) ) {
            return [
                'success' => false,
                'errors'  => [ __( 'No tienes permisos para editar este producto.', 'sultana-admin' ) ],
            ];
        }

        $validated = $this->validate_combo_product_data( $data, $product_id );

        if ( ! empty( $validated['errors'] ) ) {
            return [
                'success' => false,
                'errors'  => $validated['errors'],
            ];
        }

        try {
            $this->save_combo_product_data( $product, $validated['data'] );
            ComboComponentService::save_components( $product_id, $validated['data']['combo_components'] );
            $this->apply_combo_sale_price( $product_id, $validated['data']['sale_price'] );
        } catch ( Throwable $exception ) {
            return [
                'success' => false,
                'errors'  => [ __( 'No se pudo actualizar el combo. Revisa los datos e intenta nuevamente.', 'sultana-admin' ) ],
            ];
        }

        return [
            'success'    => true,
            'errors'     => [],
            'product_id' => $product_id,
        ];
    }

    public function components_for_form( $raw_components ): array
    {
        if ( ! is_array( $raw_components ) ) {
            return [];
        }

        $components = [];

        foreach ( $raw_components as $component ) {
            if ( ! is_array( $component ) ) {
                continue;
            }

            $product_id   = absint( $component['product_id'] ?? 0 );
            $variation_id = absint( $component['variation_id'] ?? 0 );
            $selected_id  = absint( $component['selected_id'] ?? 0 ) ?: ( $variation_id ?: $product_id );
            $label        = sanitize_text_field( (string) ( $component['label'] ?? '' ) );
            $quantity     = isset( $component['quantity'] ) ? wc_clean( (string) $component['quantity'] ) : '';
            $regular_price = isset( $component['regular_price'] ) ? wc_format_decimal( wc_clean( (string) $component['regular_price'] ) ) : '';

            if ( $selected_id && function_exists( 'wc_get_product' ) ) {
                $product = wc_get_product( $selected_id );
                $label   = '' === $label && $product instanceof WC_Product && class_exists( ComboComponentService::class )
                    ? ComboComponentService::format_component_option_label( $product )
                    : $label;
                $regular_price = '' === $regular_price && $product instanceof WC_Product ? wc_format_decimal( $product->get_regular_price() ) : $regular_price;
            }

            $components[] = [
                'product_id'   => $product_id,
                'variation_id' => $variation_id,
                'selected_id'  => $selected_id,
                'label'        => $label,
                'quantity'     => $quantity,
                'regular_price' => $regular_price,
            ];
        }

        return $components;
    }

    private function validate_combo_product_data( array $data, int $product_id = 0 ): array
    {
        $errors = [];
        $clean  = $this->default_product_data();

        $clean['name'] = trim( sanitize_text_field( (string) ( $data['name'] ?? '' ) ) );

        if ( '' === $clean['name'] ) {
            $errors[] = __( 'Ingresa el nombre del producto.', 'sultana-admin' );
        }

        $clean['short_description'] = wp_kses_post( (string) ( $data['short_description'] ?? '' ) );
        $clean['sku']               = trim( wc_clean( (string) ( $data['sku'] ?? '' ) ) );
        $clean['sale_price']        = $this->validate_optional_decimal( (string) ( $data['sale_price'] ?? '' ), $errors );
        $clean['status']            = sanitize_key( (string) ( $data['status'] ?? 'draft' ) );

        if ( ! in_array( $clean['status'], [ 'draft', 'publish' ], true ) ) {
            $errors[]        = __( 'Selecciona un estado valido para el producto.', 'sultana-admin' );
            $clean['status'] = 'draft';
        }

        if ( 'publish' === $clean['status'] && ! current_user_can( Capabilities::PUBLISH_PRODUCTS_CAPABILITY ) ) {
            $errors[]        = __( 'No tienes permisos para publicar productos.', 'sultana-admin' );
            $clean['status'] = 'draft';
        }

        $sku_product_id = '' !== $clean['sku'] && function_exists( 'wc_get_product_id_by_sku' )
            ? absint( wc_get_product_id_by_sku( $clean['sku'] ) )
            : 0;

        if ( $sku_product_id && $sku_product_id !== $product_id ) {
            $errors[] = __( 'Ese SKU ya esta siendo utilizado.', 'sultana-admin' );
        }

        if ( ! class_exists( ComboComponentService::class ) ) {
            $errors[] = __( 'Commerce Core no esta listo para validar componentes de combo.', 'sultana-admin' );
        } else {
            $components = ComboComponentService::validate_components( $data['combo_components'] ?? [], $product_id );

            if ( is_wp_error( $components ) ) {
                foreach ( $components->get_error_messages() as $message ) {
                    $errors[] = $message;
                }
            } else {
                $clean['combo_components'] = $components;
                $regular_price = ComboComponentService::calculate_regular_price( $components );
                $clean['current_price'] = wc_format_decimal( $regular_price );

                if ( '' !== $clean['sale_price'] && ( $regular_price <= 0 || (float) $clean['sale_price'] >= $regular_price ) ) {
                    $errors[] = __( 'El precio de oferta debe ser menor que el precio actual del combo.', 'sultana-admin' );
                }
            }
        }

        return [
            'data'   => $clean,
            'errors' => $errors,
        ];
    }

    private function save_combo_product_data( ProductCombo $product, array $data ): int
    {
        $product->set_name( $data['name'] );
        $product->set_short_description( $data['short_description'] );
        $product->set_description( '' );
        $product->set_sku( $data['sku'] );
        $product->set_category_ids( [] );
        $product->set_manage_stock( false );
        $product->set_status( $data['status'] );

        $product_id = absint( $product->save() );

        return $product_id;
    }

    private function apply_combo_sale_price( int $product_id, string $sale_price ): void
    {
        $product = wc_get_product( $product_id );

        if ( ! $product instanceof ProductCombo ) {
            throw new \RuntimeException( 'combo_sale_price_product_missing' );
        }

        $product->set_sale_price( $sale_price );
        $product->save();
    }

    private function validate_optional_decimal( string $value, array &$errors ): string
    {
        $value = trim( $value );

        if ( '' === $value ) {
            return '';
        }

        $decimal = wc_format_decimal( $value );

        if ( '' === $decimal || ! is_numeric( $decimal ) || (float) $decimal < 0 ) {
            $errors[] = __( 'Ingresa un precio de oferta valido.', 'sultana-admin' );
            return '';
        }

        return $decimal;
    }
}

<?php

namespace Sultana\Admin\Products;

use Sultana\Admin\Core\Capabilities;
use Throwable;
use WC_Product;
use WC_Product_Attribute;
use WC_Product_Variable;
use WC_Product_Variation;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ProductVariableService
{
    public const MAX_GENERATED_VARIATIONS = 100;
    public const VARIATIONS_PER_PAGE = 25;

    public function default_product_data(): array
    {
        return [
            'product_type'      => 'variable',
            'name'              => '',
            'short_description' => '',
            'sku'               => '',
            'category_ids'      => [],
            'brand_id'          => 0,
            'product_image_ids' => '',
            'status'            => 'draft',
            'variable_attributes' => [],
            'variations'        => [],
            'deleted_variation_ids' => [],
        ];
    }

    public function available_attributes(): array
    {
        if ( ! function_exists( 'wc_get_attribute_taxonomies' ) ) {
            return [];
        }

        $attributes = [];

        foreach ( wc_get_attribute_taxonomies() as $attribute ) {
            $taxonomy = wc_attribute_taxonomy_name( $attribute->attribute_name );

            if ( ! taxonomy_exists( $taxonomy ) ) {
                continue;
            }

            $terms = get_terms(
                [
                    'taxonomy'   => $taxonomy,
                    'hide_empty' => false,
                    'orderby'    => 'name',
                    'order'      => 'ASC',
                ]
            );

            if ( is_wp_error( $terms ) || empty( $terms ) ) {
                continue;
            }

            $attributes[] = [
                'taxonomy' => $taxonomy,
                'label'    => wc_attribute_label( $taxonomy ),
                'terms'    => array_map(
                    static function ( $term ): array {
                        return [
                            'id'   => absint( $term->term_id ),
                            'name' => $term->name,
                            'slug' => $term->slug,
                        ];
                    },
                    $terms
                ),
            ];
        }

        return $attributes;
    }

    public function product_form_data( WC_Product_Variable $product, int $variation_page = 1 ): array
    {
        $base_service  = new ProductService();
        $image_service = new ProductImageService();
        $image_ids     = $image_service->product_image_ids( $product->get_id() );
        $attributes    = [];
        $variation_listing = $this->variation_listing( $product->get_id(), $variation_page, self::VARIATIONS_PER_PAGE );

        foreach ( $product->get_attributes() as $attribute ) {
            if ( ! $attribute->get_variation() || ! $attribute->is_taxonomy() ) {
                continue;
            }

            $taxonomy = $attribute->get_name();

            if ( ! taxonomy_exists( $taxonomy ) ) {
                continue;
            }

            $attributes[] = [
                'taxonomy' => $taxonomy,
                'term_ids' => array_map( 'absint', $attribute->get_options() ),
            ];
        }

        return [
            'product_type'      => 'variable',
            'name'              => $product->get_name( 'edit' ),
            'short_description' => $product->get_short_description( 'edit' ),
            'sku'               => $product->get_sku( 'edit' ),
            'category_ids'      => array_map( 'absint', $product->get_category_ids( 'edit' ) ),
            'brand_id'          => $base_service->product_brand_id_for_form( $product->get_id() ),
            'product_image_ids' => implode( ',', $image_ids ),
            'status'            => in_array( $product->get_status(), [ 'draft', 'publish' ], true ) ? $product->get_status() : 'draft',
            'variable_attributes' => $attributes,
            'variations'        => $variation_listing['variations'],
            'variation_pagination' => $variation_listing['pagination'],
        ];
    }

    public function create_variable_product( array $data ): array
    {
        $validated = $this->validate_variable_product_data( $data, 0 );

        if ( ! empty( $validated['errors'] ) ) {
            return [
                'success' => false,
                'errors'  => $validated['errors'],
            ];
        }

        $product_id = 0;

        try {
            $product    = new WC_Product_Variable();
            $product_id = $this->save_parent_product( $product, $validated['data'] );
            $this->save_variations( $product_id, $validated['data']['variations'], [] );
            $this->sync_variable_product( $product_id, $validated['data']['all_image_ids'] );
        } catch ( Throwable $exception ) {
            if ( $product_id ) {
                $this->trash_variable_tree( $product_id );
            }

            return [
                'success' => false,
                'errors'  => [ __( 'No se pudo crear el producto variable. Revisa los datos e intenta nuevamente.', 'sultana-admin' ) ],
            ];
        }

        return [
            'success'    => true,
            'errors'     => [],
            'product_id' => $product_id,
        ];
    }

    public function update_variable_product( int $product_id, array $data ): array
    {
        $product = wc_get_product( $product_id );

        if ( ! $product instanceof WC_Product_Variable ) {
            return [
                'success' => false,
                'errors'  => [ __( 'Ese producto variable no existe.', 'sultana-admin' ) ],
            ];
        }

        if ( ! current_user_can( 'edit_product', $product_id ) ) {
            return [
                'success' => false,
                'errors'  => [ __( 'No tienes permisos para editar este producto.', 'sultana-admin' ) ],
            ];
        }

        $validated = $this->validate_variable_product_data( $data, $product_id, [], true );

        if ( ! empty( $validated['errors'] ) ) {
            return [
                'success' => false,
                'errors'  => $validated['errors'],
            ];
        }

        try {
            $this->save_parent_product( $product, $validated['data'] );
            $this->delete_variations( $product_id, $validated['data']['deleted_variation_ids'] );
            $this->save_variations( $product_id, $validated['data']['variations'], [] );
            $this->sync_variable_product( $product_id, $validated['data']['all_image_ids'] );
        } catch ( Throwable $exception ) {
            return [
                'success' => false,
                'errors'  => [ __( 'No se pudo actualizar el producto variable. Revisa los datos e intenta nuevamente.', 'sultana-admin' ) ],
            ];
        }

        return [
            'success'    => true,
            'errors'     => [],
            'product_id' => $product_id,
        ];
    }

    public function validate_variable_product_data( array $data, int $product_id = 0, array $existing_variation_ids = [], bool $partial_update = false ): array
    {
        $errors        = [];
        $clean         = $this->default_product_data();
        $base_service  = new ProductService();
        $image_service = new ProductImageService();

        $clean['name'] = trim( sanitize_text_field( (string) ( $data['name'] ?? '' ) ) );

        if ( '' === $clean['name'] ) {
            $errors[] = __( 'Ingresa el nombre del producto.', 'sultana-admin' );
        }

        $clean['short_description'] = wp_kses_post( (string) ( $data['short_description'] ?? '' ) );
        $clean['sku']               = trim( wc_clean( (string) ( $data['sku'] ?? '' ) ) );
        $clean['category_ids']      = $base_service->validate_category_ids_for_form( $data['category_ids'] ?? [], $errors );
        $clean['brand_id']          = absint( $data['brand_id'] ?? 0 );
        $clean['brand_taxonomy']    = $base_service->get_brand_taxonomy();
        $clean['status']            = sanitize_key( (string) ( $data['status'] ?? 'draft' ) );

        if ( ! in_array( $clean['status'], [ 'draft', 'publish' ], true ) ) {
            $errors[]        = __( 'Selecciona un estado valido para el producto.', 'sultana-admin' );
            $clean['status'] = 'draft';
        }

        if ( 'publish' === $clean['status'] && ! current_user_can( Capabilities::PUBLISH_PRODUCTS_CAPABILITY ) ) {
            $errors[]        = __( 'No tienes permisos para publicar productos.', 'sultana-admin' );
            $clean['status'] = 'draft';
        }

        $parent_sku_product_id = '' !== $clean['sku'] ? absint( wc_get_product_id_by_sku( $clean['sku'] ) ) : 0;

        if ( $parent_sku_product_id && $parent_sku_product_id !== $product_id ) {
            $errors[] = __( 'El SKU del producto ya esta siendo utilizado.', 'sultana-admin' );
        }

        if ( $clean['brand_id'] ) {
            $base_service->validate_brand_for_form( $clean, $errors );
        }

        $image_ids = $image_service->validate_product_image_ids( $data['product_image_ids'] ?? '', $product_id, false );

        if ( is_wp_error( $image_ids ) ) {
            $errors[] = $image_ids->get_error_message();
            $image_ids = [];
        }

        $clean['product_image_ids'] = $image_ids;
        $clean['deleted_variation_ids'] = $this->validate_deleted_variation_ids( $data['deleted_variation_ids'] ?? [], $product_id, $errors );
        $clean['variable_attributes'] = $this->validate_attributes( $data['variable_attributes'] ?? [], $errors );
        if ( $product_id > 0 ) {
            $clean['variable_attributes'] = $this->merge_existing_variation_attribute_terms( $clean['variable_attributes'], $data['variations'] ?? [], $product_id );
        }
        $this->validate_generation_size( $clean['variable_attributes'], $data['variations'] ?? [], $product_id, $partial_update, $errors );
        $clean['variations'] = $this->validate_variations( $data['variations'] ?? [], $clean['variable_attributes'], $product_id, $existing_variation_ids, $clean['sku'], $errors );
        if ( ! empty( $clean['deleted_variation_ids'] ) ) {
            $clean['variations'] = array_values(
                array_filter(
                    $clean['variations'],
                    static fn( array $variation ): bool => ! in_array( absint( $variation['id'] ?? 0 ), $clean['deleted_variation_ids'], true )
                )
            );
        }
        $clean['all_image_ids'] = $this->collect_image_ids( $clean['product_image_ids'], $clean['variations'] );

        if ( empty( $clean['variable_attributes'] ) ) {
            $errors[] = __( 'Selecciona al menos un atributo para variaciones.', 'sultana-admin' );
        }

        if ( empty( $clean['variations'] ) ) {
            $errors[] = __( 'Configura al menos una variacion.', 'sultana-admin' );
        }

        return [
            'data'   => $clean,
            'errors' => $errors,
        ];
    }

    private function save_parent_product( WC_Product_Variable $product, array $data ): int
    {
        $image_ids   = $data['product_image_ids'];
        $image_id    = $image_ids[0] ?? 0;
        $gallery_ids = array_slice( $image_ids, 1 );

        $product->set_name( $data['name'] );
        $product->set_short_description( $data['short_description'] );
        $product->set_description( '' );
        $product->set_sku( $data['sku'] );
        $product->set_category_ids( $data['category_ids'] );
        $product->set_status( $data['status'] );
        $product->set_image_id( $image_id );
        $product->set_gallery_image_ids( $gallery_ids );
        $product->set_manage_stock( false );
        $product->set_attributes( $this->wc_attributes( $data['variable_attributes'] ) );

        $product_id = $product->save();

        if ( ! empty( $data['brand_taxonomy'] ) ) {
            $brand_result = wp_set_object_terms(
                $product_id,
                ! empty( $data['brand_id'] ) ? [ $data['brand_id'] ] : [],
                $data['brand_taxonomy'],
                false
            );

            if ( is_wp_error( $brand_result ) ) {
                throw new \RuntimeException( 'brand_assignment_failed' );
            }
        }

        return absint( $product_id );
    }

    private function save_variations( int $product_id, array $variations, array $existing_variation_ids ): array
    {
        $kept_ids = [];

        foreach ( $variations as $variation_data ) {
            $variation_id = absint( $variation_data['id'] ?? 0 );
            $variation    = $variation_id ? wc_get_product( $variation_id ) : new WC_Product_Variation();

            if ( $variation_id && ( ! $variation instanceof WC_Product_Variation || absint( $variation->get_parent_id() ) !== $product_id ) ) {
                continue;
            }

            if ( ! $variation instanceof WC_Product_Variation ) {
                $variation = new WC_Product_Variation();
            }

            $variation->set_parent_id( $product_id );
            $variation->set_attributes( $variation_data['attributes'] );
            $variation->set_sku( $variation_data['sku'] );
            $variation->set_regular_price( $variation_data['regular_price'] );
            $variation->set_sale_price( $variation_data['sale_price'] );
            $variation->set_manage_stock( true );
            $variation->set_stock_quantity( $variation_data['stock_quantity'] );
            $variation->set_stock_status( $variation_data['stock_quantity'] > 0 ? 'instock' : 'outofstock' );
            $variation->set_weight( $variation_data['weight'] );
            $variation->set_status( 'publish' );
            $variation->set_image_id( absint( $variation_data['image_id'] ) );

            $kept_ids[] = absint( $variation->save() );
        }

        return $kept_ids;
    }

    private function delete_variations( int $product_id, array $variation_ids ): void
    {
        foreach ( array_values( array_unique( array_map( 'absint', $variation_ids ) ) ) as $variation_id ) {
            if ( ! $variation_id ) {
                continue;
            }

            $variation = wc_get_product( $variation_id );

            if ( ! $variation instanceof WC_Product_Variation || absint( $variation->get_parent_id() ) !== $product_id ) {
                continue;
            }

            wp_trash_post( $variation_id );
        }
    }

    private function trash_variable_tree( int $product_id ): void
    {
        $product = wc_get_product( $product_id );

        if ( $product && method_exists( $product, 'get_children' ) ) {
            foreach ( $product->get_children() as $variation_id ) {
                wp_trash_post( absint( $variation_id ) );
            }
        }

        wp_trash_post( $product_id );
    }

    private function sync_variable_product( int $product_id, array $image_ids ): void
    {
        WC_Product_Variable::sync( $product_id );
        wc_delete_product_transients( $product_id );

        if ( ! empty( $image_ids ) ) {
            ( new ProductImageService() )->release_temporary_images( $image_ids );
        }
    }

    private function wc_attributes( array $attributes ): array
    {
        $wc_attributes = [];
        $position      = 0;

        foreach ( $attributes as $attribute_data ) {
            $attribute = new WC_Product_Attribute();
            $taxonomy  = $attribute_data['taxonomy'];

            $attribute->set_id( wc_attribute_taxonomy_id_by_name( str_replace( 'pa_', '', $taxonomy ) ) );
            $attribute->set_name( $taxonomy );
            $attribute->set_options( $attribute_data['term_ids'] );
            $attribute->set_position( $position );
            $attribute->set_visible( true );
            $attribute->set_variation( true );

            $wc_attributes[] = $attribute;
            $position++;
        }

        return $wc_attributes;
    }

    private function validate_attributes( $raw_attributes, array &$errors ): array
    {
        if ( ! is_array( $raw_attributes ) ) {
            return [];
        }

        $valid = [];

        foreach ( $raw_attributes as $raw_attribute ) {
            $taxonomy = isset( $raw_attribute['taxonomy'] ) ? sanitize_key( wp_unslash( $raw_attribute['taxonomy'] ) ) : '';

            if ( '' === $taxonomy || isset( $valid[ $taxonomy ] ) || ! taxonomy_exists( $taxonomy ) || 0 !== strpos( $taxonomy, 'pa_' ) ) {
                continue;
            }

            $term_ids = isset( $raw_attribute['term_ids'] ) && is_array( $raw_attribute['term_ids'] )
                ? array_values( array_unique( array_map( 'absint', wp_unslash( $raw_attribute['term_ids'] ) ) ) )
                : [];

            $term_ids = array_values(
                array_filter(
                    $term_ids,
                    static function ( int $term_id ) use ( $taxonomy ): bool {
                        $term = get_term( $term_id, $taxonomy );

                        return $term && ! is_wp_error( $term );
                    }
                )
            );

            if ( empty( $term_ids ) ) {
                $errors[] = __( 'Selecciona valores validos para cada atributo.', 'sultana-admin' );
                continue;
            }

            $valid[ $taxonomy ] = [
                'taxonomy' => $taxonomy,
                'term_ids' => $term_ids,
            ];
        }

        return array_values( $valid );
    }

    private function validate_deleted_variation_ids( $raw_ids, int $product_id, array &$errors ): array
    {
        if ( $product_id <= 0 || ! is_array( $raw_ids ) ) {
            return [];
        }

        $valid = [];

        foreach ( array_values( array_unique( array_map( 'absint', wp_unslash( $raw_ids ) ) ) ) as $variation_id ) {
            if ( ! $variation_id ) {
                continue;
            }

            $variation = wc_get_product( $variation_id );

            if ( ! $variation instanceof WC_Product_Variation || absint( $variation->get_parent_id() ) !== $product_id ) {
                $errors[] = __( 'Una variacion marcada para eliminar no pertenece a este producto.', 'sultana-admin' );
                continue;
            }

            $valid[] = $variation_id;
        }

        return $valid;
    }

    private function merge_existing_variation_attribute_terms( array $attributes, $raw_variations, int $product_id ): array
    {
        if ( ! is_array( $raw_variations ) ) {
            return $attributes;
        }

        $by_taxonomy = [];

        foreach ( $attributes as $attribute ) {
            $taxonomy = sanitize_key( (string) ( $attribute['taxonomy'] ?? '' ) );

            if ( '' === $taxonomy ) {
                continue;
            }

            $by_taxonomy[ $taxonomy ] = [
                'taxonomy' => $taxonomy,
                'term_ids' => isset( $attribute['term_ids'] ) && is_array( $attribute['term_ids'] )
                    ? array_values( array_unique( array_map( 'absint', $attribute['term_ids'] ) ) )
                    : [],
            ];
        }

        foreach ( $raw_variations as $raw_variation ) {
            if ( ! is_array( $raw_variation ) ) {
                continue;
            }

            $variation_id = absint( $raw_variation['id'] ?? 0 );

            if ( ! $variation_id ) {
                continue;
            }

            $variation = wc_get_product( $variation_id );

            if ( ! $variation instanceof WC_Product_Variation || absint( $variation->get_parent_id() ) !== $product_id ) {
                continue;
            }

            $variation_attributes = isset( $raw_variation['attributes'] ) && is_array( $raw_variation['attributes'] )
                ? array_map( 'sanitize_title', wp_unslash( $raw_variation['attributes'] ) )
                : [];

            foreach ( $variation_attributes as $taxonomy => $slug ) {
                $taxonomy = sanitize_key( (string) $taxonomy );
                $slug     = sanitize_title( (string) $slug );

                if ( '' === $taxonomy || '' === $slug || ! taxonomy_exists( $taxonomy ) || 0 !== strpos( $taxonomy, 'pa_' ) ) {
                    continue;
                }

                $term = get_term_by( 'slug', $slug, $taxonomy );

                if ( ! $term || is_wp_error( $term ) ) {
                    continue;
                }

                if ( ! isset( $by_taxonomy[ $taxonomy ] ) ) {
                    $by_taxonomy[ $taxonomy ] = [
                        'taxonomy' => $taxonomy,
                        'term_ids' => [],
                    ];
                }

                $term_id = absint( $term->term_id );

                if ( $term_id && ! in_array( $term_id, $by_taxonomy[ $taxonomy ]['term_ids'], true ) ) {
                    $by_taxonomy[ $taxonomy ]['term_ids'][] = $term_id;
                }
            }
        }

        return array_values(
            array_map(
                static function ( array $attribute ): array {
                    $attribute['term_ids'] = array_values( array_unique( array_map( 'absint', $attribute['term_ids'] ) ) );

                    return $attribute;
                },
                $by_taxonomy
            )
        );
    }

    private function validate_variations( $raw_variations, array $attributes, int $product_id, array $existing_variation_ids, string $parent_sku, array &$errors ): array
    {
        if ( ! is_array( $raw_variations ) ) {
            return [];
        }

        $valid           = [];
        $seen_keys       = [];
        $seen_skus       = [];
        $allowed_terms   = $this->allowed_attribute_terms( $attributes );
        $image_service   = new ProductImageService();

        foreach ( $raw_variations as $raw_variation ) {
            $variation_id = absint( $raw_variation['id'] ?? 0 );
            $attributes_value = isset( $raw_variation['attributes'] ) && is_array( $raw_variation['attributes'] )
                ? array_map( 'sanitize_title', wp_unslash( $raw_variation['attributes'] ) )
                : [];
            $variation_attributes = [];

            foreach ( $allowed_terms as $taxonomy => $term_slugs ) {
                $slug = sanitize_title( (string) ( $attributes_value[ $taxonomy ] ?? '' ) );

                if ( '' !== $slug && ! in_array( $slug, $term_slugs, true ) ) {
                    $errors[] = __( 'Una variacion contiene atributos invalidos.', 'sultana-admin' );
                    continue 2;
                }

                $variation_attributes[ $taxonomy ] = $slug;
            }

            $key = implode( '|', $variation_attributes );

            if ( isset( $seen_keys[ $key ] ) ) {
                continue;
            }

            foreach ( $valid as $existing_variation ) {
                if ( $this->variations_overlap( $existing_variation['attributes'], $variation_attributes ) ) {
                    $errors[] = __( 'Hay variaciones que se solapan por usar Cualquier atributo. Evita combinar una variacion generica con otra especifica que pueda resolver la misma seleccion.', 'sultana-admin' );
                    continue 2;
                }
            }

            $regular_price = $this->validate_decimal( (string) ( $raw_variation['regular_price'] ?? '' ) );
            $sale_price    = trim( (string) ( $raw_variation['sale_price'] ?? '' ) );

            if ( null === $regular_price ) {
                $errors[] = __( 'Cada variacion necesita un precio regular valido.', 'sultana-admin' );
                continue;
            }

            if ( '' !== $sale_price ) {
                $sale_price = $this->validate_decimal( $sale_price );

                if ( null === $sale_price || (float) $sale_price >= (float) $regular_price ) {
                    $errors[] = __( 'El precio de oferta de cada variacion debe ser valido y menor al regular.', 'sultana-admin' );
                    continue;
                }
            }

            $stock_quantity = $this->validate_stock_quantity( (string) ( $raw_variation['stock_quantity'] ?? '' ) );

            if ( null === $stock_quantity ) {
                $errors[] = __( 'Cada variacion necesita una cantidad de stock valida.', 'sultana-admin' );
                continue;
            }

            $weight = $this->validate_positive_decimal( (string) ( $raw_variation['weight'] ?? '' ) );

            if ( null === $weight ) {
                $errors[] = __( 'Cada variacion necesita un peso valido.', 'sultana-admin' );
                continue;
            }

            $sku = trim( wc_clean( (string) ( $raw_variation['sku'] ?? '' ) ) );

            if ( '' !== $sku ) {
                $sku_product_id = absint( wc_get_product_id_by_sku( $sku ) );

                if ( $sku === $parent_sku || isset( $seen_skus[ $sku ] ) || ( $sku_product_id && $sku_product_id !== $variation_id ) ) {
                    $errors[] = __( 'Cada SKU de variacion debe ser unico.', 'sultana-admin' );
                    continue;
                }

                $seen_skus[ $sku ] = true;
            }

            if ( $variation_id && ! in_array( $variation_id, $existing_variation_ids, true ) ) {
                $existing_variation = wc_get_product( $variation_id );

                if ( ! $existing_variation instanceof WC_Product_Variation || absint( $existing_variation->get_parent_id() ) !== $product_id ) {
                    $errors[] = __( 'Una variacion enviada no pertenece a este producto.', 'sultana-admin' );
                    continue;
                }
            }

            $image_id = absint( $raw_variation['image_id'] ?? 0 );

            if ( $image_id ) {
                $image_id = $image_service->validate_variation_image_id( $image_id, $product_id, $variation_id );

                if ( is_wp_error( $image_id ) ) {
                    $errors[] = $image_id->get_error_message();
                    continue;
                }
            }

            $valid[] = [
                'id'            => $variation_id,
                'attributes'    => $variation_attributes,
                'sku'           => $sku,
                'regular_price' => $regular_price,
                'sale_price'    => is_string( $sale_price ) ? $sale_price : '',
                'stock_quantity' => $stock_quantity,
                'weight'        => $weight,
                'image_id'      => absint( $image_id ),
            ];
            $seen_keys[ $key ] = true;
        }

        return $valid;
    }

    private function variation_listing( int $product_id, int $page, int $per_page ): array
    {
        $page     = max( 1, $page );
        $per_page = max( 1, min( 50, $per_page ) );

        $result = wc_get_products(
            [
                'type'     => 'variation',
                'parent'   => $product_id,
                'limit'    => $per_page,
                'page'     => $page,
                'paginate' => true,
                'orderby'  => 'ID',
                'order'    => 'ASC',
                'return'   => 'objects',
                'status'   => [ 'publish', 'private' ],
            ]
        );

        $variations  = is_object( $result ) && isset( $result->products ) && is_array( $result->products ) ? $result->products : [];
        $total       = is_object( $result ) && isset( $result->total ) ? absint( $result->total ) : count( $variations );
        $total_pages = is_object( $result ) && isset( $result->max_num_pages ) ? max( 1, absint( $result->max_num_pages ) ) : 1;

        if ( $total > 0 && $page > $total_pages ) {
            return $this->variation_listing( $product_id, $total_pages, $per_page );
        }

        return [
            'variations' => array_values(
                array_filter(
                    array_map( [ $this, 'format_variation_for_form' ], $variations )
                )
            ),
            'pagination' => [
                'page'        => min( $page, $total_pages ),
                'per_page'    => $per_page,
                'total'       => $total,
                'total_pages' => $total_pages,
            ],
        ];
    }

    private function format_variation_for_form( $variation ): array
    {
        if ( ! $variation instanceof WC_Product_Variation ) {
            return [];
        }

        return [
            'id'             => $variation->get_id(),
            'attributes'     => $variation->get_attributes( 'edit' ),
            'sku'            => $variation->get_sku( 'edit' ),
            'regular_price'  => $variation->get_regular_price( 'edit' ),
            'sale_price'     => $variation->get_sale_price( 'edit' ),
            'stock_quantity' => null !== $variation->get_stock_quantity( 'edit' ) ? (string) $variation->get_stock_quantity( 'edit' ) : '0',
            'weight'         => $variation->get_weight( 'edit' ),
            'image_id'       => absint( $variation->get_image_id() ),
            'image_url'      => $this->image_url( absint( $variation->get_image_id() ) ),
        ];
    }

    private function validate_generation_size( array $attributes, $raw_variations, int $product_id, bool $partial_update, array &$errors ): void
    {
        $raw_variations = is_array( $raw_variations ) ? $raw_variations : [];

        if ( count( $raw_variations ) > self::MAX_GENERATED_VARIATIONS ) {
            $errors[] = sprintf(
                /* translators: %d: maximum variation count. */
                __( 'Sultana Admin puede guardar hasta %d variaciones a la vez.', 'sultana-admin' ),
                self::MAX_GENERATED_VARIATIONS
            );
            return;
        }

        $has_new_variations = 0 === $product_id;

        if ( ! $has_new_variations && $partial_update ) {
            foreach ( $raw_variations as $raw_variation ) {
                if ( is_array( $raw_variation ) && empty( $raw_variation['id'] ) ) {
                    $has_new_variations = true;
                    break;
                }
            }
        }

        if ( $partial_update && ! $has_new_variations ) {
            return;
        }

    }

    private function allowed_attribute_terms( array $attributes ): array
    {
        $allowed = [];

        foreach ( $attributes as $attribute ) {
            $taxonomy = $attribute['taxonomy'];
            $allowed[ $taxonomy ] = [];

            foreach ( $attribute['term_ids'] as $term_id ) {
                $term = get_term( $term_id, $taxonomy );

                if ( $term && ! is_wp_error( $term ) ) {
                    $allowed[ $taxonomy ][] = $term->slug;
                }
            }
        }

        return $allowed;
    }

    private function variations_overlap( array $first, array $second ): bool
    {
        $taxonomies = array_values( array_unique( array_merge( array_keys( $first ), array_keys( $second ) ) ) );

        foreach ( $taxonomies as $taxonomy ) {
            $first_value  = sanitize_title( (string) ( $first[ $taxonomy ] ?? '' ) );
            $second_value = sanitize_title( (string) ( $second[ $taxonomy ] ?? '' ) );

            if ( '' !== $first_value && '' !== $second_value && $first_value !== $second_value ) {
                return false;
            }
        }

        return true;
    }

    private function collect_image_ids( array $product_image_ids, array $variations ): array
    {
        $ids = $product_image_ids;

        foreach ( $variations as $variation ) {
            $image_id = absint( $variation['image_id'] ?? 0 );

            if ( $image_id && ! in_array( $image_id, $ids, true ) ) {
                $ids[] = $image_id;
            }
        }

        return $ids;
    }

    private function validate_decimal( string $value ): ?string
    {
        $value = trim( $value );

        if ( '' === $value ) {
            return null;
        }

        $decimal = wc_format_decimal( $value );

        if ( '' === $decimal || ! is_numeric( $decimal ) || (float) $decimal < 0 ) {
            return null;
        }

        return $decimal;
    }

    private function validate_positive_decimal( string $value ): ?string
    {
        $decimal = $this->validate_decimal( $value );

        if ( null === $decimal || (float) $decimal <= 0 ) {
            return null;
        }

        return $decimal;
    }

    private function validate_stock_quantity( string $value ): ?int
    {
        $value = trim( $value );

        if ( '' === $value || ! preg_match( '/^\d+$/', $value ) ) {
            return null;
        }

        return absint( $value );
    }

    private function image_url( int $attachment_id ): string
    {
        if ( ! $attachment_id ) {
            return '';
        }

        $url = wp_get_attachment_image_url( $attachment_id, 'thumbnail' );

        return is_string( $url ) ? $url : '';
    }
}

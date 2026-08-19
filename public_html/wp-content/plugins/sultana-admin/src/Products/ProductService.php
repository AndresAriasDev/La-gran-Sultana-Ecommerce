<?php

namespace Sultana\Admin\Products;

use Sultana\Admin\Core\Capabilities;
use Throwable;
use WC_Product;
use WC_Product_Simple;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ProductService
{
    private const ALLOWED_TYPES = [ 'simple', 'variable', 'combo' ];
    private const BRAND_TAXONOMY_CANDIDATES = [ 'product_brand', 'pa_marca', 'pa_brand', 'yith_product_brand' ];

    public function default_simple_product_data(): array
    {
        return [
            'name'              => '',
            'regular_price'     => '',
            'sale_price'        => '',
            'sku'               => '',
            'short_description' => '',
            'category_ids'      => [],
            'brand_id'          => 0,
            'stock_quantity'    => '',
            'product_image_ids' => '',
            'status'            => 'draft',
        ];
    }

    public function get_product_categories(): array
    {
        $terms = get_terms(
            [
                'taxonomy'   => 'product_cat',
                'hide_empty' => false,
                'orderby'    => 'name',
                'order'      => 'ASC',
            ]
        );

        if ( is_wp_error( $terms ) ) {
            return [];
        }

        return array_map(
            static function ( $term ): array {
                return [
                    'id'   => absint( $term->term_id ),
                    'name' => $term->name,
                ];
            },
            $terms
        );
    }

    public function get_brand_taxonomy(): string
    {
        foreach ( self::BRAND_TAXONOMY_CANDIDATES as $taxonomy ) {
            if ( ! taxonomy_exists( $taxonomy ) ) {
                continue;
            }

            $taxonomy_object = get_taxonomy( $taxonomy );

            if ( $taxonomy_object && in_array( 'product', (array) $taxonomy_object->object_type, true ) ) {
                return $taxonomy;
            }
        }

        return '';
    }

    public function get_product_brands(): array
    {
        $taxonomy = $this->get_brand_taxonomy();

        if ( '' === $taxonomy ) {
            return [];
        }

        $terms = get_terms(
            [
                'taxonomy'   => $taxonomy,
                'hide_empty' => false,
                'orderby'    => 'name',
                'order'      => 'ASC',
            ]
        );

        if ( is_wp_error( $terms ) ) {
            return [];
        }

        return array_map(
            static function ( $term ): array {
                return [
                    'id'   => absint( $term->term_id ),
                    'name' => $term->name,
                ];
            },
            $terms
        );
    }

    public function create_simple_product( array $data ): array
    {
        $validated = $this->validate_simple_product_data( $data );

        if ( ! empty( $validated['errors'] ) ) {
            return [
                'success' => false,
                'errors'  => $validated['errors'],
            ];
        }

        $image_ids  = $validated['data']['product_image_ids'];
        $image_id   = $image_ids[0] ?? 0;
        $gallery_ids = array_slice( $image_ids, 1 );

        $product_id = 0;

        $product = null;

        try {
            $product = new WC_Product_Simple();
            $product->set_name( $validated['data']['name'] );
            $product->set_regular_price( $validated['data']['regular_price'] );
            $product->set_sale_price( $validated['data']['sale_price'] );
            $product->set_sku( $validated['data']['sku'] );
            $product->set_short_description( $validated['data']['short_description'] );
            $product->set_description( '' );
            $product->set_category_ids( $validated['data']['category_ids'] );
            $product->set_manage_stock( true );
            $product->set_stock_quantity( $validated['data']['stock_quantity'] );
            $product->set_stock_status( $validated['data']['stock_quantity'] > 0 ? 'instock' : 'outofstock' );

            $product->set_status( $validated['data']['status'] );

            if ( $image_id ) {
                $product->set_image_id( $image_id );
            }

            if ( ! empty( $gallery_ids ) ) {
                $product->set_gallery_image_ids( $gallery_ids );
            }

            $product_id = $product->save();

            if ( ! empty( $validated['data']['brand_id'] ) && '' !== $validated['data']['brand_taxonomy'] ) {
                $brand_result = wp_set_object_terms(
                    $product_id,
                    [ $validated['data']['brand_id'] ],
                    $validated['data']['brand_taxonomy'],
                    false
                );

                if ( is_wp_error( $brand_result ) ) {
                    throw new \RuntimeException( 'brand_assignment_failed' );
                }
            }

            if ( ! empty( $image_ids ) ) {
                ( new ProductImageService() )->release_temporary_images( $image_ids );
            }
        } catch ( Throwable $exception ) {
            if ( $product_id && $product instanceof WC_Product_Simple ) {
                $product->delete( true );
            }

            return [
                'success' => false,
                'errors'  => [ $this->friendly_product_error( $exception ) ],
            ];
        }

        if ( ! $product_id ) {
            return [
                'success' => false,
                'errors'  => [ __( 'No se pudo crear el producto.', 'sultana-admin' ) ],
            ];
        }

        return [
            'success'    => true,
            'errors'     => [],
            'product_id' => absint( $product_id ),
        ];
    }

    public function get_product( int $product_id )
    {
        if ( $product_id <= 0 ) {
            return null;
        }

        $product = wc_get_product( $product_id );

        return $product instanceof WC_Product ? $product : null;
    }

    public function product_form_data( WC_Product $product ): array
    {
        $image_service = new ProductImageService();
        $image_ids     = $image_service->product_image_ids( $product->get_id() );
        $status        = $product->get_status();

        return [
            'name'              => $product->get_name( 'edit' ),
            'regular_price'     => $product->get_regular_price( 'edit' ),
            'sale_price'        => $product->get_sale_price( 'edit' ),
            'sku'               => $product->get_sku( 'edit' ),
            'short_description' => $product->get_short_description( 'edit' ),
            'category_ids'      => array_map( 'absint', $product->get_category_ids( 'edit' ) ),
            'brand_id'          => $this->product_brand_id( $product->get_id() ),
            'stock_quantity'    => null !== $product->get_stock_quantity( 'edit' ) ? (string) $product->get_stock_quantity( 'edit' ) : '0',
            'product_image_ids' => implode( ',', $image_ids ),
            'status'            => in_array( $status, [ 'draft', 'publish' ], true ) ? $status : 'draft',
        ];
    }

    public function update_simple_product( int $product_id, array $data ): array
    {
        $product = $this->get_product( $product_id );

        if ( ! $product ) {
            return [
                'success' => false,
                'errors'  => [ __( 'El producto no existe.', 'sultana-admin' ) ],
            ];
        }

        if ( 'simple' !== $product->get_type() || ! ( $product instanceof WC_Product_Simple ) ) {
            return [
                'success' => false,
                'errors'  => [ __( 'Ese tipo de producto todavia no puede editarse desde Sultana Admin.', 'sultana-admin' ) ],
            ];
        }

        if ( ! current_user_can( 'edit_product', $product_id ) ) {
            return [
                'success' => false,
                'errors'  => [ __( 'No tienes permisos para editar este producto.', 'sultana-admin' ) ],
            ];
        }

        $validated = $this->validate_simple_product_data( $data, $product_id );

        if ( ! empty( $validated['errors'] ) ) {
            return [
                'success' => false,
                'errors'  => $validated['errors'],
            ];
        }

        try {
            $this->apply_simple_product_data( $product, $validated['data'] );
            $product->save();

            if ( ! empty( $validated['data']['brand_taxonomy'] ) ) {
                $brand_result = wp_set_object_terms(
                    $product_id,
                    ! empty( $validated['data']['brand_id'] ) ? [ $validated['data']['brand_id'] ] : [],
                    $validated['data']['brand_taxonomy'],
                    false
                );

                if ( is_wp_error( $brand_result ) ) {
                    throw new \RuntimeException( 'brand_assignment_failed' );
                }
            }

            if ( ! empty( $validated['data']['product_image_ids'] ) ) {
                ( new ProductImageService() )->release_temporary_images( $validated['data']['product_image_ids'] );
            }
        } catch ( Throwable $exception ) {
            return [
                'success' => false,
                'errors'  => [ $this->friendly_product_error( $exception ) ],
            ];
        }

        return [
            'success'    => true,
            'errors'     => [],
            'product_id' => $product_id,
        ];
    }

    public function trash_simple_product( int $product_id ): array
    {
        $product = $this->get_product( $product_id );

        if ( ! $product ) {
            return [
                'success' => false,
                'errors'  => [ __( 'El producto no existe o ya fue enviado a la papelera.', 'sultana-admin' ) ],
            ];
        }

        if ( 'trash' === $product->get_status() ) {
            return [
                'success' => false,
                'errors'  => [ __( 'El producto ya fue enviado a la papelera.', 'sultana-admin' ) ],
            ];
        }

        if ( 'simple' !== $product->get_type() ) {
            return [
                'success' => false,
                'errors'  => [ __( 'Solo los productos simples pueden enviarse a la papelera desde Sultana Admin.', 'sultana-admin' ) ],
            ];
        }

        if ( ! current_user_can( 'delete_product', $product_id ) ) {
            return [
                'success' => false,
                'errors'  => [ __( 'No tienes permisos para eliminar este producto.', 'sultana-admin' ) ],
            ];
        }

        $trashed = wp_trash_post( $product_id );

        if ( ! $trashed ) {
            return [
                'success' => false,
                'errors'  => [ __( 'No se pudo enviar el producto a la papelera.', 'sultana-admin' ) ],
            ];
        }

        return [
            'success'    => true,
            'errors'     => [],
            'product_id' => $product_id,
        ];
    }

    public function list_products( array $args ): array
    {
        $search   = isset( $args['search'] ) ? trim( (string) $args['search'] ) : '';
        $page     = isset( $args['page'] ) ? max( 1, absint( $args['page'] ) ) : 1;
        $per_page = isset( $args['per_page'] ) ? max( 1, min( 50, absint( $args['per_page'] ) ) ) : 20;

        $query_args = [
            'status'   => [ 'publish', 'draft', 'pending', 'private' ],
            'type'     => self::ALLOWED_TYPES,
            'limit'    => $per_page,
            'page'     => $page,
            'paginate' => true,
            'orderby'  => 'date',
            'order'    => 'DESC',
            'return'   => 'objects',
        ];

        if ( '' !== $search ) {
            $query_args['search'] = '*' . $search . '*';
        }

        $result      = wc_get_products( $query_args );
        $products    = is_object( $result ) && isset( $result->products ) ? $result->products : [];
        $total       = is_object( $result ) && isset( $result->total ) ? absint( $result->total ) : count( $products );
        $total_pages = is_object( $result ) && isset( $result->max_num_pages ) ? absint( $result->max_num_pages ) : 1;

        if ( '' !== $search && 1 === $page ) {
            [ $products, $total ] = $this->include_exact_sku_match( $search, $products, $total, $per_page );
            $total_pages = max( 1, (int) ceil( $total / $per_page ) );
        }

        return [
            'products'    => array_map( [ $this, 'format_product' ], array_filter( $products, [ $this, 'is_supported_product' ] ) ),
            'page'        => $page,
            'per_page'    => $per_page,
            'total'       => $total,
            'total_pages' => max( 1, $total_pages ),
        ];
    }

    private function include_exact_sku_match( string $search, array $products, int $total, int $per_page ): array
    {
        if ( ! function_exists( 'wc_get_product_id_by_sku' ) ) {
            return [ $products, $total ];
        }

        $sku_product_id = absint( wc_get_product_id_by_sku( $search ) );

        if ( ! $sku_product_id ) {
            return [ $products, $total ];
        }

        foreach ( $products as $product ) {
            if ( $product instanceof WC_Product && $product->get_id() === $sku_product_id ) {
                return [ $products, $total ];
            }
        }

        $sku_product = wc_get_product( $sku_product_id );

        if ( ! $this->is_supported_product( $sku_product ) ) {
            return [ $products, $total ];
        }

        array_unshift( $products, $sku_product );

        if ( count( $products ) > $per_page ) {
            array_pop( $products );
        }

        return [ $products, $total + 1 ];
    }

    private function apply_simple_product_data( WC_Product_Simple $product, array $data ): void
    {
        $image_ids   = $data['product_image_ids'];
        $image_id    = $image_ids[0] ?? 0;
        $gallery_ids = array_slice( $image_ids, 1 );

        $product->set_name( $data['name'] );
        $product->set_regular_price( $data['regular_price'] );
        $product->set_sale_price( $data['sale_price'] );
        $product->set_sku( $data['sku'] );
        $product->set_short_description( $data['short_description'] );
        $product->set_description( '' );
        $product->set_category_ids( $data['category_ids'] );
        $product->set_manage_stock( true );
        $product->set_stock_quantity( $data['stock_quantity'] );
        $product->set_stock_status( $data['stock_quantity'] > 0 ? 'instock' : 'outofstock' );
        $product->set_status( $data['status'] );
        $product->set_image_id( $image_id );
        $product->set_gallery_image_ids( $gallery_ids );
    }

    private function product_brand_id( int $product_id ): int
    {
        $taxonomy = $this->get_brand_taxonomy();

        if ( '' === $taxonomy ) {
            return 0;
        }

        $terms = wp_get_object_terms(
            $product_id,
            $taxonomy,
            [
                'fields' => 'ids',
            ]
        );

        if ( is_wp_error( $terms ) || empty( $terms ) ) {
            return 0;
        }

        return absint( $terms[0] );
    }

    private function validate_simple_product_data( array $data, int $existing_product_id = 0 ): array
    {
        $errors = [];
        $clean  = $this->default_simple_product_data();

        $clean['name'] = trim( sanitize_text_field( (string) ( $data['name'] ?? '' ) ) );

        if ( '' === $clean['name'] ) {
            $errors[] = __( 'Ingresa el nombre del producto.', 'sultana-admin' );
        }

        $regular_price = $this->validate_decimal( (string) ( $data['regular_price'] ?? '' ) );

        if ( null === $regular_price ) {
            $errors[] = __( 'Ingresa un precio regular valido.', 'sultana-admin' );
        } else {
            $clean['regular_price'] = $regular_price;
        }

        $sale_price = trim( (string) ( $data['sale_price'] ?? '' ) );

        if ( '' !== $sale_price ) {
            $sale_price = $this->validate_decimal( $sale_price );

            if ( null === $sale_price ) {
                $errors[] = __( 'Ingresa un precio de oferta valido.', 'sultana-admin' );
            } elseif ( null !== $regular_price && (float) $sale_price >= (float) $regular_price ) {
                $errors[] = __( 'El precio de oferta debe ser menor al precio regular.', 'sultana-admin' );
            } else {
                $clean['sale_price'] = $sale_price;
            }
        }

        $clean['sku'] = trim( wc_clean( (string) ( $data['sku'] ?? '' ) ) );

        $sku_product_id = '' !== $clean['sku'] ? absint( wc_get_product_id_by_sku( $clean['sku'] ) ) : 0;

        if ( $sku_product_id && $sku_product_id !== $existing_product_id ) {
            $errors[] = __( 'Ese SKU ya esta siendo utilizado.', 'sultana-admin' );
        }

        $clean['short_description'] = wp_kses_post( (string) ( $data['short_description'] ?? '' ) );
        $clean['category_ids']      = $this->validate_category_ids( $data['category_ids'] ?? [], $errors );
        $clean['brand_id']          = absint( $data['brand_id'] ?? 0 );
        $clean['brand_taxonomy']    = $this->get_brand_taxonomy();
        $clean['status']            = sanitize_key( (string) ( $data['status'] ?? 'draft' ) );

        if ( ! in_array( $clean['status'], [ 'draft', 'publish' ], true ) ) {
            $errors[]        = __( 'Selecciona un estado valido para el producto.', 'sultana-admin' );
            $clean['status'] = 'draft';
        }

        if ( 'publish' === $clean['status'] && ! current_user_can( Capabilities::PUBLISH_PRODUCTS_CAPABILITY ) ) {
            $errors[]        = __( 'No tienes permisos para publicar productos.', 'sultana-admin' );
            $clean['status'] = 'draft';
        }

        $stock_quantity = $this->validate_stock_quantity( (string) ( $data['stock_quantity'] ?? '' ) );

        if ( null === $stock_quantity ) {
            $errors[] = __( 'Ingresa una cantidad de stock valida.', 'sultana-admin' );
        } else {
            $clean['stock_quantity'] = $stock_quantity;
        }

        $image_ids = ( new ProductImageService() )->validate_product_image_ids( $data['product_image_ids'] ?? '', $existing_product_id );

        if ( is_wp_error( $image_ids ) ) {
            $errors[] = $image_ids->get_error_message();
        } else {
            $clean['product_image_ids'] = $image_ids;
        }

        if ( $clean['brand_id'] ) {
            if ( ! current_user_can( Capabilities::ASSIGN_PRODUCT_TERMS_CAPABILITY ) ) {
                $errors[] = __( 'No tienes permisos para asignar marcas.', 'sultana-admin' );
                return [
                    'data'   => $clean,
                    'errors' => $errors,
                ];
            }

            $brand_taxonomy = $clean['brand_taxonomy'];

            if ( '' === $brand_taxonomy ) {
                $errors[] = __( 'La marca seleccionada no esta disponible.', 'sultana-admin' );
            } else {
                $term = get_term( $clean['brand_id'], $brand_taxonomy );

                if ( ! $term || is_wp_error( $term ) ) {
                    $errors[] = __( 'Selecciona una marca valida.', 'sultana-admin' );
                } else {
                    $clean['brand_taxonomy'] = $brand_taxonomy;
                }
            }
        }

        return [
            'data'   => $clean,
            'errors' => $errors,
        ];
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

    private function validate_stock_quantity( string $value ): ?int
    {
        $value = trim( $value );

        if ( '' === $value || ! preg_match( '/^\d+$/', $value ) ) {
            return null;
        }

        return absint( $value );
    }

    private function validate_category_ids( $category_ids, array &$errors ): array
    {
        if ( ! is_array( $category_ids ) || empty( $category_ids ) ) {
            return [];
        }

        if ( ! current_user_can( Capabilities::ASSIGN_PRODUCT_TERMS_CAPABILITY ) ) {
            $errors[] = __( 'No tienes permisos para asignar categorias.', 'sultana-admin' );
            return [];
        }

        $valid = [];

        foreach ( array_unique( array_map( 'absint', $category_ids ) ) as $category_id ) {
            if ( ! $category_id ) {
                continue;
            }

            $term = get_term( $category_id, 'product_cat' );

            if ( ! $term || is_wp_error( $term ) ) {
                $errors[] = __( 'Selecciona categorias validas.', 'sultana-admin' );
                continue;
            }

            $valid[] = $category_id;
        }

        return $valid;
    }

    private function friendly_product_error( Throwable $exception ): string
    {
        if ( false !== stripos( $exception->getMessage(), 'sku' ) ) {
            return __( 'Ese SKU ya esta siendo utilizado.', 'sultana-admin' );
        }

        return __( 'No se pudo crear el producto. Revisa los datos e intenta nuevamente.', 'sultana-admin' );
    }

    private function is_supported_product( $product ): bool
    {
        return $product instanceof WC_Product && in_array( $product->get_type(), self::ALLOWED_TYPES, true );
    }

    private function format_product( WC_Product $product ): array
    {
        $image_id     = $product->get_image_id();
        $availability = $product->get_availability();
        $stock_text   = ! empty( $availability['availability'] )
            ? wp_strip_all_tags( (string) $availability['availability'] )
            : $this->stock_status_label( $product->get_stock_status() );

        if ( $product->managing_stock() && null !== $product->get_stock_quantity() ) {
            $stock_text = sprintf(
                /* translators: 1: stock availability label, 2: stock quantity. */
                __( '%1$s (%2$d)', 'sultana-admin' ),
                $stock_text,
                (int) $product->get_stock_quantity()
            );
        }

        $image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : '';

        return [
            'id'        => $product->get_id(),
            'image_url' => is_string( $image_url ) ? $image_url : '',
            'name'      => $product->get_name(),
            'sku'       => $product->get_sku(),
            'type_key'  => $product->get_type(),
            'type'      => $this->type_label( $product->get_type() ),
            'price'     => $product->get_price_html(),
            'stock'     => $stock_text,
            'status'    => $this->status_label( $product->get_status() ),
            'can_edit'  => 'simple' === $product->get_type() && current_user_can( 'edit_product', $product->get_id() ),
            'can_delete' => 'simple' === $product->get_type() && current_user_can( 'delete_product', $product->get_id() ),
        ];
    }

    private function type_label( string $type ): string
    {
        $labels = [
            'simple'   => __( 'Simple', 'sultana-admin' ),
            'variable' => __( 'Variable', 'sultana-admin' ),
            'combo'    => __( 'Combo', 'sultana-admin' ),
        ];

        return $labels[ $type ] ?? ucwords( str_replace( [ '-', '_' ], ' ', sanitize_key( $type ) ) );
    }

    private function status_label( string $status ): string
    {
        $labels = [
            'publish' => __( 'Publicado', 'sultana-admin' ),
            'draft'   => __( 'Borrador', 'sultana-admin' ),
            'pending' => __( 'Pendiente', 'sultana-admin' ),
            'private' => __( 'Privado', 'sultana-admin' ),
        ];

        return $labels[ $status ] ?? ucwords( str_replace( [ '-', '_' ], ' ', sanitize_key( $status ) ) );
    }

    private function stock_status_label( string $stock_status ): string
    {
        $labels = [
            'instock'     => __( 'Disponible', 'sultana-admin' ),
            'outofstock'  => __( 'Agotado', 'sultana-admin' ),
            'onbackorder' => __( 'Bajo pedido', 'sultana-admin' ),
        ];

        return $labels[ $stock_status ] ?? ucwords( str_replace( [ '-', '_' ], ' ', sanitize_key( $stock_status ) ) );
    }
}

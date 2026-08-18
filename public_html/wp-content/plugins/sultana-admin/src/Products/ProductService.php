<?php

namespace Sultana\Admin\Products;

use WC_Product;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ProductService
{
    private const ALLOWED_TYPES = [ 'simple', 'variable', 'combo' ];

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
            'type'      => $this->type_label( $product->get_type() ),
            'price'     => $product->get_price_html(),
            'stock'     => $stock_text,
            'status'    => $this->status_label( $product->get_status() ),
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

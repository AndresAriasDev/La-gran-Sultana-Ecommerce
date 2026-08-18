<?php

namespace Sultana\Admin\Products;

use Sultana\Admin\Core\Router;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ProductController
{
    public static function prepare_list_screen(): array
    {
        $search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
        $search = trim( $search );
        $page   = isset( $_GET['product_page'] ) ? absint( wp_unslash( $_GET['product_page'] ) ) : 1;
        $page   = max( 1, $page );

        $service = new ProductService();
        $listing = $service->list_products(
            [
                'search'   => $search,
                'page'     => $page,
                'per_page' => 20,
            ]
        );

        return [
            'search'     => $search,
            'page'       => $listing['page'],
            'per_page'   => $listing['per_page'],
            'total'      => $listing['total'],
            'total_pages' => $listing['total_pages'],
            'products'   => $listing['products'],
            'pagination' => self::pagination_links( $listing['page'], $listing['total_pages'], $search ),
        ];
    }

    private static function pagination_links( int $page, int $total_pages, string $search ): array
    {
        $base_args = [];

        if ( '' !== $search ) {
            $base_args['s'] = $search;
        }

        return [
            'previous' => $page > 1
                ? add_query_arg( array_merge( $base_args, [ 'product_page' => $page - 1 ] ), Router::products_url() )
                : '',
            'next'     => $page < $total_pages
                ? add_query_arg( array_merge( $base_args, [ 'product_page' => $page + 1 ] ), Router::products_url() )
                : '',
        ];
    }
}

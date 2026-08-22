<?php

namespace Sultana\Admin\Coupons;

use Sultana\Admin\Core\Router;
use WC_Coupon;
use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CouponService
{
    public const PER_PAGE = 20;
    private const BRAND_TAXONOMY = 'product_brand';
    private const PRODUCT_BRANDS_META_KEY = 'product_brands';

    public function list_coupons( array $args ): array
    {
        $search   = isset( $args['search'] ) ? trim( (string) $args['search'] ) : '';
        $page     = isset( $args['page'] ) ? max( 1, absint( $args['page'] ) ) : 1;
        $per_page = isset( $args['per_page'] ) ? max( 1, min( 50, absint( $args['per_page'] ) ) ) : self::PER_PAGE;

        $query_args = [
            'post_type'              => 'shop_coupon',
            'post_status'            => [ 'publish', 'draft', 'pending', 'private' ],
            'posts_per_page'         => $per_page,
            'paged'                  => $page,
            'orderby'                => 'date',
            'order'                  => 'DESC',
            'fields'                 => 'ids',
            'no_found_rows'          => false,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ];

        if ( '' !== $search ) {
            $query_args['s'] = $search;
        }

        $query       = new WP_Query( $query_args );
        $coupon_ids  = array_map( 'absint', $query->posts );
        $total       = absint( $query->found_posts );
        $total_pages = max( 1, absint( $query->max_num_pages ) );

        return [
            'coupons'     => array_values( array_filter( array_map( [ $this, 'coupon_row' ], $coupon_ids ) ) ),
            'page'        => $page,
            'per_page'    => $per_page,
            'total'       => $total,
            'total_pages' => $total_pages,
        ];
    }

    public function default_form_data(): array
    {
        return [
            'code'                         => '',
            'description'                  => '',
            'discount_type'                => $this->default_discount_type(),
            'amount'                       => '',
            'date_expires'                 => '',
            'minimum_amount'               => '',
            'maximum_amount'               => '',
            'individual_use'               => '0',
            'exclude_sale_items'           => '0',
            'product_categories'           => [],
            'product_brands'               => [],
            'email_restrictions'           => '',
            'usage_limit'                  => '',
            'usage_limit_per_user'         => '',
        ];
    }

    public function coupon_form_data( WC_Coupon $coupon ): array
    {
        $date_expires = $coupon->get_date_expires();

        return [
            'code'                         => $coupon->get_code(),
            'description'                  => $coupon->get_description(),
            'discount_type'                => $coupon->get_discount_type(),
            'amount'                       => wc_format_decimal( $coupon->get_amount() ),
            'date_expires'                 => $date_expires ? $date_expires->date_i18n( 'Y-m-d' ) : '',
            'minimum_amount'               => wc_format_decimal( $coupon->get_minimum_amount() ),
            'maximum_amount'               => wc_format_decimal( $coupon->get_maximum_amount() ),
            'individual_use'               => $coupon->get_individual_use() ? '1' : '0',
            'exclude_sale_items'           => $coupon->get_exclude_sale_items() ? '1' : '0',
            'product_categories'           => array_map( 'absint', $coupon->get_product_categories() ),
            'product_brands'               => $this->coupon_product_brands( $coupon ),
            'email_restrictions'           => implode( "\n", $coupon->get_email_restrictions() ),
            'usage_limit'                  => $this->empty_zero( $coupon->get_usage_limit() ),
            'usage_limit_per_user'         => $this->empty_zero( $coupon->get_usage_limit_per_user() ),
        ];
    }

    public function get_coupon( int $coupon_id ): ?WC_Coupon
    {
        if ( $coupon_id <= 0 || ! class_exists( WC_Coupon::class ) ) {
            return null;
        }

        try {
            $coupon = new WC_Coupon( $coupon_id );
        } catch ( \Throwable $exception ) {
            return null;
        }

        return $coupon->get_id() > 0 ? $coupon : null;
    }

    public function create_coupon( array $data ): array
    {
        return $this->save_coupon( 0, $data );
    }

    public function update_coupon( int $coupon_id, array $data ): array
    {
        return $this->save_coupon( $coupon_id, $data );
    }

    public function trash_coupon( int $coupon_id ): array
    {
        $coupon = $this->get_coupon( $coupon_id );

        if ( ! $coupon ) {
            return [
                'success' => false,
                'errors'  => [ __( 'El cupon no existe.', 'sultana-admin' ) ],
            ];
        }

        if ( ! current_user_can( 'delete_shop_coupon', $coupon_id ) && ! current_user_can( 'delete_shop_coupons' ) ) {
            return [
                'success' => false,
                'errors'  => [ __( 'No tienes permisos para eliminar este cupon.', 'sultana-admin' ) ],
            ];
        }

        $trashed = wp_trash_post( $coupon_id );

        if ( ! $trashed ) {
            return [
                'success' => false,
                'errors'  => [ __( 'No se pudo enviar el cupon a la papelera.', 'sultana-admin' ) ],
            ];
        }

        return [
            'success'   => true,
            'errors'    => [],
            'coupon_id' => $coupon_id,
        ];
    }

    public function discount_types(): array
    {
        if ( function_exists( 'wc_get_coupon_types' ) ) {
            $types = wc_get_coupon_types();

            if ( is_array( $types ) && ! empty( $types ) ) {
                return $types;
            }
        }

        return [
            'percent'       => __( 'Porcentaje', 'sultana-admin' ),
            'fixed_cart'    => __( 'Descuento fijo del carrito', 'sultana-admin' ),
            'fixed_product' => __( 'Descuento fijo de producto', 'sultana-admin' ),
        ];
    }

    public function product_categories(): array
    {
        $terms = get_terms(
            [
                'taxonomy'   => 'product_cat',
                'hide_empty' => false,
                'orderby'    => 'name',
                'order'      => 'ASC',
            ]
        );

        if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
            return [];
        }

        return array_map(
            static fn ( $term ): array => [
                'id'   => absint( $term->term_id ),
                'name' => (string) $term->name,
            ],
            $terms
        );
    }

    public function brand_taxonomy(): string
    {
        if ( ! taxonomy_exists( self::BRAND_TAXONOMY ) ) {
            return '';
        }

        $taxonomy_object = get_taxonomy( self::BRAND_TAXONOMY );

        if ( $taxonomy_object && in_array( 'product', (array) $taxonomy_object->object_type, true ) ) {
            return self::BRAND_TAXONOMY;
        }

        return '';
    }

    public function product_brands(): array
    {
        $taxonomy = $this->brand_taxonomy();

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

        if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
            return [];
        }

        return array_map(
            static fn ( $term ): array => [
                'id'   => absint( $term->term_id ),
                'name' => (string) $term->name,
            ],
            $terms
        );
    }

    public function category_brand_relationships(): array
    {
        if ( '' === $this->brand_taxonomy() ) {
            return [];
        }

        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DISTINCT cat_tt.term_id AS category_id, brand_tt.term_id AS brand_id
                FROM {$wpdb->posts} products
                INNER JOIN {$wpdb->term_relationships} cat_rel
                    ON cat_rel.object_id = products.ID
                INNER JOIN {$wpdb->term_taxonomy} cat_tt
                    ON cat_tt.term_taxonomy_id = cat_rel.term_taxonomy_id
                    AND cat_tt.taxonomy = %s
                INNER JOIN {$wpdb->term_relationships} brand_rel
                    ON brand_rel.object_id = products.ID
                INNER JOIN {$wpdb->term_taxonomy} brand_tt
                    ON brand_tt.term_taxonomy_id = brand_rel.term_taxonomy_id
                    AND brand_tt.taxonomy = %s
                WHERE products.post_type = %s
                    AND products.post_status IN ( 'publish', 'private' )",
                'product_cat',
                self::BRAND_TAXONOMY,
                'product'
            ),
            ARRAY_A
        );

        if ( ! is_array( $rows ) ) {
            return [];
        }

        return array_values(
            array_map(
                static fn ( array $row ): array => [
                    'category_id' => absint( $row['category_id'] ?? 0 ),
                    'brand_id'    => absint( $row['brand_id'] ?? 0 ),
                ],
                array_filter(
                    $rows,
                    static fn ( array $row ): bool => absint( $row['category_id'] ?? 0 ) > 0 && absint( $row['brand_id'] ?? 0 ) > 0
                )
            )
        );
    }

    private function save_coupon( int $coupon_id, array $data ): array
    {
        $errors = $this->validate_coupon_data( $data, $coupon_id );

        if ( ! empty( $errors ) ) {
            return [
                'success' => false,
                'errors'  => $errors,
            ];
        }

        try {
            $coupon = $coupon_id > 0 ? $this->get_coupon( $coupon_id ) : new WC_Coupon();

            if ( ! $coupon ) {
                return [
                    'success' => false,
                    'errors'  => [ __( 'El cupon no existe.', 'sultana-admin' ) ],
                ];
            }

            $this->apply_coupon_data( $coupon, $data );
            $saved_id = $coupon->save();

            if ( $saved_id > 0 ) {
                $this->save_coupon_product_brands( absint( $saved_id ), $data );
            }
        } catch ( \Throwable $exception ) {
            return [
                'success' => false,
                'errors'  => [ $exception->getMessage() ],
            ];
        }

        return [
            'success'   => $saved_id > 0,
            'errors'    => [],
            'coupon_id' => absint( $saved_id ),
        ];
    }

    private function validate_coupon_data( array $data, int $coupon_id ): array
    {
        $errors         = [];
        $code           = trim( (string) ( $data['code'] ?? '' ) );
        $discount_type  = (string) ( $data['discount_type'] ?? '' );
        $amount         = (string) ( $data['amount'] ?? '' );
        $discount_types = array_keys( $this->discount_types() );

        if ( '' === $code ) {
            $errors[] = __( 'El codigo del cupon es obligatorio.', 'sultana-admin' );
        }

        if ( '' !== $code && function_exists( 'wc_get_coupon_id_by_code' ) ) {
            $existing_id = absint( wc_get_coupon_id_by_code( $code ) );

            if ( $existing_id > 0 && $existing_id !== $coupon_id ) {
                $errors[] = __( 'Ya existe un cupon con ese codigo.', 'sultana-admin' );
            }
        }

        if ( ! in_array( $discount_type, $discount_types, true ) ) {
            $errors[] = __( 'El tipo de descuento no es valido.', 'sultana-admin' );
        }

        if ( '' === $amount || ! is_numeric( str_replace( ',', '.', $amount ) ) ) {
            $errors[] = __( 'El importe del cupon debe ser numerico.', 'sultana-admin' );
        } elseif ( (float) str_replace( ',', '.', $amount ) <= 0 ) {
            $errors[] = __( 'El importe del cupon debe ser mayor que cero.', 'sultana-admin' );
        }

        foreach ( [ 'minimum_amount', 'maximum_amount' ] as $field ) {
            $value = (string) ( $data[ $field ] ?? '' );

            if ( '' !== $value && ! is_numeric( str_replace( ',', '.', $value ) ) ) {
                $errors[] = __( 'Los importes de restricciones deben ser numericos.', 'sultana-admin' );
                break;
            }
        }

        if ( '' !== (string) ( $data['date_expires'] ?? '' ) && false === strtotime( (string) $data['date_expires'] ) ) {
            $errors[] = __( 'La fecha de vencimiento no es valida.', 'sultana-admin' );
        }

        foreach ( $this->absint_list( $data['product_categories'] ?? [] ) as $category_id ) {
            if ( ! term_exists( $category_id, 'product_cat' ) ) {
                $errors[] = __( 'Selecciona categorias validas.', 'sultana-admin' );
                break;
            }
        }

        $brand_taxonomy = $this->brand_taxonomy();

        foreach ( $this->absint_list( $data['product_brands'] ?? [] ) as $brand_id ) {
            if ( '' === $brand_taxonomy || ! term_exists( $brand_id, $brand_taxonomy ) ) {
                $errors[] = __( 'Selecciona marcas validas.', 'sultana-admin' );
                break;
            }
        }

        if ( ! $this->has_compatible_category_brand_selection( $data ) ) {
            $errors[] = __( 'La combinacion de categorias y marcas no corresponde a productos existentes.', 'sultana-admin' );
        }

        return $errors;
    }

    private function apply_coupon_data( WC_Coupon $coupon, array $data ): void
    {
        $coupon->set_code( wc_clean( (string) $data['code'] ) );
        $coupon->set_description( sanitize_textarea_field( (string) ( $data['description'] ?? '' ) ) );
        $coupon->set_discount_type( sanitize_key( (string) $data['discount_type'] ) );
        $coupon->set_amount( wc_format_decimal( (string) $data['amount'] ) );
        $coupon->set_date_expires( $this->date_expires_value( (string) ( $data['date_expires'] ?? '' ) ) );
        $coupon->set_minimum_amount( $this->decimal_or_empty( (string) ( $data['minimum_amount'] ?? '' ) ) );
        $coupon->set_maximum_amount( $this->decimal_or_empty( (string) ( $data['maximum_amount'] ?? '' ) ) );
        $coupon->set_individual_use( ! empty( $data['individual_use'] ) );
        $coupon->set_exclude_sale_items( ! empty( $data['exclude_sale_items'] ) );
        $coupon->set_product_categories( $this->absint_list( $data['product_categories'] ?? [] ) );
        $coupon->set_email_restrictions( $this->email_list( (string) ( $data['email_restrictions'] ?? '' ) ) );
        $coupon->set_usage_limit( $this->positive_int_or_zero( $data['usage_limit'] ?? '' ) );
        $coupon->set_usage_limit_per_user( $this->positive_int_or_zero( $data['usage_limit_per_user'] ?? '' ) );
    }

    private function has_compatible_category_brand_selection( array $data ): bool
    {
        $category_ids = $this->absint_list( $data['product_categories'] ?? [] );
        $brand_ids    = $this->absint_list( $data['product_brands'] ?? [] );

        if ( empty( $category_ids ) || empty( $brand_ids ) ) {
            return true;
        }

        $pairs = $this->category_brand_relationships();

        if ( empty( $pairs ) ) {
            return false;
        }

        $compatible_categories = [];
        $compatible_brands     = [];

        foreach ( $pairs as $pair ) {
            $category_id = absint( $pair['category_id'] ?? 0 );
            $brand_id    = absint( $pair['brand_id'] ?? 0 );

            if ( in_array( $brand_id, $brand_ids, true ) ) {
                $compatible_categories[] = $category_id;
            }

            if ( in_array( $category_id, $category_ids, true ) ) {
                $compatible_brands[] = $brand_id;
            }
        }

        $compatible_categories = array_values( array_unique( $compatible_categories ) );
        $compatible_brands     = array_values( array_unique( $compatible_brands ) );

        return empty( array_diff( $category_ids, $compatible_categories ) )
            && empty( array_diff( $brand_ids, $compatible_brands ) );
    }

    private function coupon_product_brands( WC_Coupon $coupon ): array
    {
        $brand_ids = get_post_meta( $coupon->get_id(), self::PRODUCT_BRANDS_META_KEY, true );

        return $this->absint_list( is_array( $brand_ids ) ? $brand_ids : [] );
    }

    private function save_coupon_product_brands( int $coupon_id, array $data ): void
    {
        update_post_meta( $coupon_id, self::PRODUCT_BRANDS_META_KEY, $this->absint_list( $data['product_brands'] ?? [] ) );
    }

    private function coupon_row( int $coupon_id ): ?array
    {
        $coupon = $this->get_coupon( $coupon_id );

        if ( ! $coupon ) {
            return null;
        }

        $usage_limit  = absint( $coupon->get_usage_limit() );
        $date_expires = $coupon->get_date_expires();
        $type         = $coupon->get_discount_type();
        $types        = $this->discount_types();

        return [
            'id'           => $coupon->get_id(),
            'code'         => $coupon->get_code(),
            'type'         => $types[ $type ] ?? $type,
            'amount'       => $this->format_amount( $coupon ),
            'usage'        => $usage_limit > 0
                ? sprintf( '%1$d / %2$d', absint( $coupon->get_usage_count() ), $usage_limit )
                : sprintf( '%d', absint( $coupon->get_usage_count() ) ),
            'expires'      => $date_expires ? $date_expires->date_i18n( get_option( 'date_format' ) ) : __( 'Sin vencimiento', 'sultana-admin' ),
            'edit_url'     => Router::coupon_url( $coupon->get_id() ),
            'can_edit'     => current_user_can( 'edit_shop_coupon', $coupon->get_id() ) || current_user_can( 'edit_shop_coupons' ),
            'can_delete'   => current_user_can( 'delete_shop_coupon', $coupon->get_id() ) || current_user_can( 'delete_shop_coupons' ),
        ];
    }

    private function format_amount( WC_Coupon $coupon ): string
    {
        if ( 'percent' === $coupon->get_discount_type() ) {
            return wc_format_decimal( $coupon->get_amount() ) . '%';
        }

        return wc_price( $coupon->get_amount() );
    }

    private function default_discount_type(): string
    {
        $types = array_keys( $this->discount_types() );

        return $types[0] ?? 'percent';
    }

    private function decimal_or_empty( string $value ): string
    {
        return '' === trim( $value ) ? '' : wc_format_decimal( $value );
    }

    private function date_expires_value( string $date ): ?int
    {
        $date = trim( $date );

        if ( '' === $date ) {
            return null;
        }

        $datetime = date_create_immutable( $date . ' 23:59:59', wp_timezone() );

        return $datetime ? $datetime->getTimestamp() : null;
    }

    private function absint_list( $value ): array
    {
        if ( ! is_array( $value ) ) {
            $value = '' === (string) $value ? [] : explode( ',', (string) $value );
        }

        return array_values( array_filter( array_unique( array_map( 'absint', $value ) ) ) );
    }

    private function email_list( string $value ): array
    {
        $items  = preg_split( '/[\r\n,]+/', $value ) ?: [];
        $emails = [];

        foreach ( $items as $item ) {
            $email = sanitize_email( trim( $item ) );

            if ( '' !== $email ) {
                $emails[] = $email;
            }
        }

        return array_values( array_unique( $emails ) );
    }

    private function positive_int_or_zero( $value ): int
    {
        return '' === (string) $value ? 0 : max( 0, absint( $value ) );
    }

    private function empty_zero( $value ): string
    {
        $value = absint( $value );

        return $value > 0 ? (string) $value : '';
    }
}

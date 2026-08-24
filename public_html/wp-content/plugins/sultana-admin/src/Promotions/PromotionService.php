<?php

namespace Sultana\Admin\Promotions;

use Sultana\Admin\Core\Router;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PromotionService
{
    private const PROMOTION_CLASS = '\Sultana\CommerceCore\Modules\HomePromotions\HomePromotions';

    public function default_form_data(): array
    {
        return [
            'id'                => 0,
            'name'              => '',
            'desktop_image_id'  => 0,
            'mobile_image_id'   => 0,
            'alt_text'          => '',
            'destination_type'  => 'none',
            'destination_value' => '',
            'custom_url'        => '',
            'active'            => false,
            'menu_order'        => 0,
        ];
    }

    public function list_promotions(): array
    {
        if ( ! $this->core_ready() ) {
            return [];
        }

        $image_service = new PromotionImageService();
        $promotion_class = self::PROMOTION_CLASS;

        return array_map(
            static function ( array $promotion ) use ( $image_service ): array {
                $promotion['edit_url'] = Router::edit_banner_url( absint( $promotion['id'] ?? 0 ) );
                $promotion['desktop_image'] = ! empty( $promotion['desktop_image_id'] )
                    ? $image_service->format_image_item( absint( $promotion['desktop_image_id'] ), false )
                    : null;
                $promotion['mobile_image'] = ! empty( $promotion['mobile_image_id'] )
                    ? $image_service->format_image_item( absint( $promotion['mobile_image_id'] ), false )
                    : null;

                return $promotion;
            },
            $promotion_class::list_admin_promotions()
        );
    }

    public function form_data( int $promotion_id ): array
    {
        if ( ! $promotion_id || ! $this->core_ready() ) {
            return $this->default_form_data();
        }

        $promotion_class = self::PROMOTION_CLASS;
        $promotion = $promotion_class::get_promotion( $promotion_id );

        return is_array( $promotion ) ? array_merge( $this->default_form_data(), $promotion ) : $this->default_form_data();
    }

    public function image_items_for_form( array $form ): array
    {
        $image_service = new PromotionImageService();

        return [
            'desktop' => ! empty( $form['desktop_image_id'] )
                ? $image_service->format_image_item( absint( $form['desktop_image_id'] ), false )
                : null,
            'mobile'  => ! empty( $form['mobile_image_id'] )
                ? $image_service->format_image_item( absint( $form['mobile_image_id'] ), false )
                : null,
        ];
    }

    public function save_promotion( array $data ): array
    {
        if ( ! $this->core_ready() ) {
            return [
                'success' => false,
                'errors'  => [ __( 'Commerce Core no esta listo para gestionar banners.', 'sultana-admin' ) ],
            ];
        }

        $promotion_id  = absint( $data['promotion_id'] ?? 0 );
        $image_service = new PromotionImageService();
        $desktop_id    = $image_service->validate_promotion_image_id( absint( $data['desktop_image_id'] ?? 0 ), $promotion_id );
        $mobile_id     = $image_service->validate_promotion_image_id( absint( $data['mobile_image_id'] ?? 0 ), $promotion_id );
        $errors        = [];

        if ( is_wp_error( $desktop_id ) ) {
            $errors[] = $desktop_id->get_error_message();
            $desktop_id = 0;
        }

        if ( is_wp_error( $mobile_id ) ) {
            $errors[] = $mobile_id->get_error_message();
            $mobile_id = 0;
        }

        if ( ! empty( $errors ) ) {
            return [
                'success' => false,
                'errors'  => $errors,
            ];
        }

        $payload = [
            'name'              => $data['name'] ?? '',
            'desktop_image_id'  => $desktop_id,
            'mobile_image_id'   => $mobile_id,
            'alt_text'          => $data['alt_text'] ?? '',
            'destination_type'  => $data['destination_type'] ?? 'none',
            'destination_value' => $data['destination_value'] ?? '',
            'custom_url'        => $data['custom_url'] ?? '',
            'active'            => ! empty( $data['active'] ),
            'menu_order'        => $data['menu_order'] ?? 0,
        ];

        $promotion_class = self::PROMOTION_CLASS;
        $result = $promotion_id > 0
            ? $promotion_class::update_promotion( $promotion_id, $payload )
            : $promotion_class::create_promotion( $payload );

        if ( is_wp_error( $result ) ) {
            return [
                'success' => false,
                'errors'  => [ $result->get_error_message() ],
            ];
        }

        $saved_id = $promotion_id > 0 ? $promotion_id : absint( $result );
        $image_service->release_temporary_images( [ absint( $desktop_id ), absint( $mobile_id ) ] );

        return [
            'success'      => true,
            'errors'       => [],
            'promotion_id' => $saved_id,
        ];
    }

    public function delete_promotion( int $promotion_id ): array
    {
        if ( ! $promotion_id || ! $this->core_ready() ) {
            return [
                'success' => false,
                'errors'  => [ __( 'La promocion no existe.', 'sultana-admin' ) ],
            ];
        }

        $promotion_class = self::PROMOTION_CLASS;
        $result = $promotion_class::delete_promotion( $promotion_id );

        if ( is_wp_error( $result ) ) {
            return [
                'success' => false,
                'errors'  => [ $result->get_error_message() ],
            ];
        }

        return [
            'success' => true,
            'errors'  => [],
        ];
    }

    public function destination_options(): array
    {
        $promotion_class = self::PROMOTION_CLASS;

        return $this->core_ready() ? $promotion_class::destination_options() : [];
    }

    public function destination_choices(): array
    {
        $promotion_class = self::PROMOTION_CLASS;
        $brand_taxonomy = $this->core_ready() ? $promotion_class::promotion_brand_taxonomy() : '';

        return [
            'pages'      => get_pages(
                [
                    'sort_column' => 'post_title',
                    'sort_order'  => 'ASC',
                ]
            ),
            'categories' => get_terms(
                [
                    'taxonomy'   => 'product_cat',
                    'hide_empty' => false,
                ]
            ),
            'products'   => get_posts(
                [
                    'post_type'      => 'product',
                    'post_status'    => [ 'publish', 'draft', 'private' ],
                    'posts_per_page' => 100,
                    'orderby'        => 'title',
                    'order'          => 'ASC',
                ]
            ),
            'brands'     => '' !== $brand_taxonomy
                ? get_terms(
                    [
                        'taxonomy'   => $brand_taxonomy,
                        'hide_empty' => false,
                    ]
                )
                : [],
        ];
    }

    private function core_ready(): bool
    {
        return class_exists( self::PROMOTION_CLASS )
            && method_exists( self::PROMOTION_CLASS, 'list_admin_promotions' )
            && method_exists( self::PROMOTION_CLASS, 'create_promotion' )
            && method_exists( self::PROMOTION_CLASS, 'update_promotion' );
    }
}

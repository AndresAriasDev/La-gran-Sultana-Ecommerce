<?php

namespace Sultana\Admin\Coupons;

use Sultana\Admin\Core\Capabilities;
use Sultana\Admin\Core\Router;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CouponController
{
    public const CREATE_NONCE_ACTION = 'sultana_admin_create_coupon';
    public const UPDATE_NONCE_ACTION = 'sultana_admin_update_coupon';
    public const TRASH_NONCE_ACTION = 'sultana_admin_trash_coupon';

    public static function prepare_list_screen(): array
    {
        $search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
        $search = substr( trim( $search ), 0, 120 );
        $page   = isset( $_GET['coupon_page'] ) ? absint( wp_unslash( $_GET['coupon_page'] ) ) : 1;
        $page   = max( 1, min( 500, $page ) );

        $service = new CouponService();
        $errors  = self::handle_trash_request( $service );
        $listing = $service->list_coupons(
            [
                'search'   => $search,
                'page'     => $page,
                'per_page' => CouponService::PER_PAGE,
            ]
        );

        return [
            'search'      => $search,
            'page'        => $listing['page'],
            'per_page'    => $listing['per_page'],
            'total'       => $listing['total'],
            'total_pages' => $listing['total_pages'],
            'coupons'     => $listing['coupons'],
            'pagination'  => self::pagination_links( $listing['page'], $listing['total_pages'], $search ),
            'notice'      => self::list_notice(),
            'errors'      => $errors,
        ];
    }

    public static function prepare_create_screen(): array
    {
        $service = new CouponService();
        $form    = $service->default_form_data();
        $errors  = [];

        if ( 'POST' === strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) {
            if ( ! self::can_manage_coupons() ) {
                $errors[] = __( 'No tienes permisos para crear cupones.', 'sultana-admin' );
            } elseif ( ! self::verify_nonce( 'sultana_admin_coupon_nonce', self::CREATE_NONCE_ACTION ) ) {
                $errors[] = __( 'No se pudo validar la solicitud. Intenta nuevamente.', 'sultana-admin' );
            } else {
                $form   = self::posted_coupon_data();
                $result = $service->create_coupon( $form );

                if ( $result['success'] ) {
                    wp_safe_redirect( add_query_arg( 'notice', 'coupon_created', Router::coupons_url() ) );
                    exit;
                }

                $errors = $result['errors'];
            }
        }

        return self::form_screen_data(
            [
                'form'              => $form,
                'errors'            => $errors,
                'form_action'       => Router::new_coupon_url(),
                'form_nonce_action' => self::CREATE_NONCE_ACTION,
                'form_title'        => __( 'Nuevo cupon', 'sultana-admin' ),
                'submit_label'      => __( 'Guardar cupon', 'sultana-admin' ),
            ],
            $service
        );
    }

    public static function prepare_edit_screen( int $coupon_id ): array
    {
        $service = new CouponService();
        $coupon  = $service->get_coupon( $coupon_id );
        $errors  = [];

        if ( ! $coupon ) {
            return [
                'not_found' => true,
                'message'   => __( 'El cupon no existe.', 'sultana-admin' ),
            ];
        }

        if ( ! current_user_can( 'edit_shop_coupon', $coupon_id ) && ! current_user_can( Capabilities::EDIT_COUPONS_CAPABILITY ) ) {
            return [
                'forbidden' => true,
                'message'   => __( 'No tienes permisos para editar este cupon.', 'sultana-admin' ),
            ];
        }

        $form = $service->coupon_form_data( $coupon );

        if ( 'POST' === strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) {
            if ( ! self::verify_nonce( 'sultana_admin_coupon_nonce', self::UPDATE_NONCE_ACTION ) ) {
                $errors[] = __( 'No se pudo validar la solicitud. Intenta nuevamente.', 'sultana-admin' );
            } else {
                $form   = self::posted_coupon_data();
                $result = $service->update_coupon( $coupon_id, $form );

                if ( $result['success'] ) {
                    wp_safe_redirect( add_query_arg( 'notice', 'coupon_updated', Router::coupons_url() ) );
                    exit;
                }

                $errors = $result['errors'];
            }
        }

        return self::form_screen_data(
            [
                'form'              => $form,
                'errors'            => $errors,
                'coupon_id'         => $coupon_id,
                'form_action'       => Router::coupon_url( $coupon_id ),
                'form_nonce_action' => self::UPDATE_NONCE_ACTION,
                'form_title'        => __( 'Editar cupon', 'sultana-admin' ),
                'submit_label'      => __( 'Actualizar', 'sultana-admin' ),
            ],
            $service
        );
    }

    private static function form_screen_data( array $data, CouponService $service ): array
    {
        $form = $data['form'] ?? $service->default_form_data();

        return array_merge(
            $data,
            [
                'discount_types'    => $service->discount_types(),
                'categories'        => $service->product_categories(),
                'brand_taxonomy'    => $service->brand_taxonomy(),
                'brands'            => $service->product_brands(),
                'category_brands'   => $service->category_brand_relationships(),
            ]
        );
    }

    private static function posted_coupon_data(): array
    {
        return [
            'code'                         => isset( $_POST['code'] ) ? wc_clean( wp_unslash( $_POST['code'] ) ) : '',
            'description'                  => isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '',
            'discount_type'                => isset( $_POST['discount_type'] ) ? sanitize_key( wp_unslash( $_POST['discount_type'] ) ) : '',
            'amount'                       => isset( $_POST['amount'] ) ? wc_clean( wp_unslash( $_POST['amount'] ) ) : '',
            'date_expires'                 => isset( $_POST['date_expires'] ) ? sanitize_text_field( wp_unslash( $_POST['date_expires'] ) ) : '',
            'minimum_amount'               => isset( $_POST['minimum_amount'] ) ? wc_clean( wp_unslash( $_POST['minimum_amount'] ) ) : '',
            'maximum_amount'               => isset( $_POST['maximum_amount'] ) ? wc_clean( wp_unslash( $_POST['maximum_amount'] ) ) : '',
            'individual_use'               => isset( $_POST['individual_use'] ) ? '1' : '0',
            'exclude_sale_items'           => isset( $_POST['exclude_sale_items'] ) ? '1' : '0',
            'product_categories'           => self::posted_id_list( 'product_categories' ),
            'product_brands'               => self::posted_id_list( 'product_brands' ),
            'email_restrictions'           => isset( $_POST['email_restrictions'] ) ? sanitize_textarea_field( wp_unslash( $_POST['email_restrictions'] ) ) : '',
            'usage_limit'                  => isset( $_POST['usage_limit'] ) ? wc_clean( wp_unslash( $_POST['usage_limit'] ) ) : '',
            'usage_limit_per_user'         => isset( $_POST['usage_limit_per_user'] ) ? wc_clean( wp_unslash( $_POST['usage_limit_per_user'] ) ) : '',
        ];
    }

    private static function posted_id_list( string $key ): array
    {
        if ( ! isset( $_POST[ $key ] ) || ! is_array( $_POST[ $key ] ) ) {
            return [];
        }

        return array_values( array_filter( array_unique( array_map( 'absint', wp_unslash( $_POST[ $key ] ) ) ) ) );
    }

    private static function pagination_links( int $page, int $total_pages, string $search ): array
    {
        $base_args = [];

        if ( '' !== $search ) {
            $base_args['s'] = $search;
        }

        $page_url = static function ( int $target_page ) use ( $base_args ): string {
            return add_query_arg( array_merge( $base_args, [ 'coupon_page' => $target_page ] ), Router::coupons_url() );
        };

        return [
            'previous' => $page > 1 ? $page_url( $page - 1 ) : '',
            'next'     => $page < $total_pages ? $page_url( $page + 1 ) : '',
            'items'    => self::pagination_items( $page, $total_pages, $page_url ),
        ];
    }

    private static function pagination_items( int $page, int $total_pages, callable $page_url ): array
    {
        if ( $total_pages <= 1 ) {
            return [];
        }

        if ( $total_pages <= 7 ) {
            $pages = range( 1, $total_pages );
        } else {
            $start = max( 2, $page - 2 );
            $end   = min( $total_pages - 1, $page + 2 );

            if ( $page <= 3 ) {
                $end = min( $total_pages - 1, 5 );
            }

            if ( $page >= $total_pages - 2 ) {
                $start = max( 2, $total_pages - 4 );
            }

            $pages = [ 1 ];

            if ( $start > 2 ) {
                $pages[] = 'ellipsis';
            }

            foreach ( range( $start, $end ) as $number ) {
                $pages[] = $number;
            }

            if ( $end < $total_pages - 1 ) {
                $pages[] = 'ellipsis';
            }

            $pages[] = $total_pages;
        }

        return array_map(
            static function ( $item ) use ( $page, $page_url ): array {
                if ( 'ellipsis' === $item ) {
                    return [ 'type' => 'ellipsis' ];
                }

                $number = absint( $item );

                return [
                    'type'    => 'page',
                    'page'    => $number,
                    'url'     => $page_url( $number ),
                    'current' => $number === $page,
                ];
            },
            $pages
        );
    }

    private static function list_notice(): string
    {
        $notice = isset( $_GET['notice'] ) ? sanitize_key( wp_unslash( $_GET['notice'] ) ) : '';

        if ( 'coupon_created' === $notice ) {
            return __( 'Cupon creado correctamente.', 'sultana-admin' );
        }

        if ( 'coupon_updated' === $notice ) {
            return __( 'Cupon actualizado correctamente.', 'sultana-admin' );
        }

        if ( 'coupon_trashed' === $notice ) {
            return __( 'Cupon enviado a la papelera correctamente.', 'sultana-admin' );
        }

        return '';
    }

    private static function handle_trash_request( CouponService $service ): array
    {
        if ( 'POST' !== strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) {
            return [];
        }

        $action = isset( $_POST['sultana_admin_action'] ) ? sanitize_key( wp_unslash( $_POST['sultana_admin_action'] ) ) : '';

        if ( 'trash_coupon' !== $action ) {
            return [];
        }

        if ( ! self::can_manage_coupons() || ! current_user_can( Capabilities::DELETE_COUPONS_CAPABILITY ) ) {
            return [ __( 'No tienes permisos para eliminar cupones.', 'sultana-admin' ) ];
        }

        if ( ! self::verify_nonce( 'sultana_admin_trash_nonce', self::TRASH_NONCE_ACTION ) ) {
            return [ __( 'No se pudo validar la solicitud. Intenta nuevamente.', 'sultana-admin' ) ];
        }

        $coupon_id = isset( $_POST['coupon_id'] ) ? absint( wp_unslash( $_POST['coupon_id'] ) ) : 0;
        $result    = $service->trash_coupon( $coupon_id );

        if ( empty( $result['success'] ) ) {
            return $result['errors'];
        }

        wp_safe_redirect( add_query_arg( 'notice', 'coupon_trashed', Router::coupons_url() ) );
        exit;
    }

    private static function verify_nonce( string $field, string $action ): bool
    {
        $nonce = isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '';

        return wp_verify_nonce( $nonce, $action );
    }

    private static function can_manage_coupons(): bool
    {
        return is_user_logged_in()
            && current_user_can( Capabilities::ACCESS_CAPABILITY )
            && current_user_can( Capabilities::EDIT_COUPONS_CAPABILITY );
    }
}

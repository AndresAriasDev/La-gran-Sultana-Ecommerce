<?php

namespace Sultana\Admin\Products;

use Sultana\Admin\Core\Capabilities;
use Sultana\Admin\Core\Router;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ProductController
{
    public const CREATE_NONCE_ACTION = 'sultana_admin_create_simple_product';
    public const UPDATE_NONCE_ACTION = 'sultana_admin_update_simple_product';
    public const TRASH_NONCE_ACTION = 'sultana_admin_trash_simple_product';
    public const IMAGE_UPLOAD_NONCE_ACTION = 'sultana_admin_product_image_upload';
    public const IMAGE_UPLOAD_ACTION = 'sultana_admin_upload_product_image';
    public const IMAGE_DELETE_ACTION = 'sultana_admin_delete_product_image';
    public const COMBO_COMPONENT_SEARCH_NONCE_ACTION = 'sultana_admin_combo_component_search';
    public const COMBO_COMPONENT_SEARCH_ACTION = 'sultana_admin_search_combo_components';

    public static function prepare_list_screen(): array
    {
        $search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
        $search = trim( $search );
        $page   = isset( $_GET['product_page'] ) ? absint( wp_unslash( $_GET['product_page'] ) ) : 1;
        $page   = max( 1, $page );

        $service = new ProductService();
        $errors  = self::handle_trash_request( $service );
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
            'notice'     => self::list_notice(),
            'errors'     => $errors,
        ];
    }

    public static function prepare_create_screen(): array
    {
        $service          = new ProductService();
        $variable_service = new ProductVariableService();
        $combo_service    = new ProductComboService();
        $product_type     = self::requested_product_type();
        $form             = self::default_form_data( $product_type, $service, $variable_service, $combo_service );
        $errors           = [];

        if ( 'POST' === strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) {
            if ( ! is_user_logged_in() || ! current_user_can( Capabilities::ACCESS_CAPABILITY ) || ! current_user_can( Capabilities::CREATE_PRODUCTS_CAPABILITY ) ) {
                $errors[] = __( 'No tienes permisos para crear productos.', 'sultana-admin' );
            } elseif ( ! self::verify_create_nonce() ) {
                $errors[] = __( 'No se pudo validar la solicitud. Intenta nuevamente.', 'sultana-admin' );
            } else {
                $form         = self::posted_product_data();
                $product_type = self::normalize_product_type( (string) ( $form['product_type'] ?? 'simple' ) );
                $result       = self::create_product_for_type( $product_type, $form, $service, $variable_service, $combo_service );

                if ( $result['success'] ) {
                    wp_safe_redirect( add_query_arg( 'notice', 'product_created', Router::products_url() ) );
                    exit;
                }

                $errors = $result['errors'];
            }
        }

        return [
            'form'            => $form,
            'errors'          => $errors,
            'categories'      => $service->get_product_categories(),
            'brands'          => $service->get_product_brands(),
            'brand_taxonomy' => $service->get_brand_taxonomy(),
            'selected_images' => 'combo' === $product_type ? [] : ( new ProductImageService() )->get_temporary_image_items( $form['product_image_ids'] ?? '' ),
            'product_type'    => $product_type,
            'available_attributes' => $variable_service->available_attributes(),
            'combo_components' => $combo_service->components_for_form( $form['combo_components'] ?? [] ),
            'form_action'     => add_query_arg( 'type', $product_type, Router::new_product_url() ),
            'form_nonce_action' => self::CREATE_NONCE_ACTION,
            'form_title'      => self::form_title_for_type( $product_type, false ),
            'form_kicker'     => self::form_kicker_for_type( $product_type ),
            'submit_label'    => __( 'Guardar producto', 'sultana-admin' ),
            'notice'          => '',
        ];
    }

    public static function prepare_edit_screen( int $product_id ): array
    {
        $service       = new ProductService();
        $image_service = new ProductImageService();
        $product       = $service->get_product( $product_id );
        $errors        = [];

        if ( ! $product ) {
            return [
                'not_found' => true,
                'message'   => __( 'El producto no existe.', 'sultana-admin' ),
            ];
        }

        if ( ! current_user_can( 'edit_product', $product_id ) ) {
            return [
                'forbidden' => true,
                'message'   => __( 'No tienes permisos para editar este producto.', 'sultana-admin' ),
            ];
        }

        if ( ! in_array( $product->get_type(), [ 'simple', 'variable', 'combo' ], true ) ) {
            return [
                'unsupported' => true,
                'message'     => __( 'Ese tipo de producto todavia no puede editarse desde Sultana Admin.', 'sultana-admin' ),
                'product'     => $product,
            ];
        }

        $variable_service = new ProductVariableService();
        $combo_service    = new ProductComboService();
        $product_type     = $product->get_type();
        if ( 'variable' === $product_type && $product instanceof \WC_Product_Variable ) {
            $form = $variable_service->product_form_data( $product );
        } elseif ( 'combo' === $product_type && $product instanceof \Sultana\CommerceCore\Modules\Combos\ProductCombo ) {
            $form = $combo_service->product_form_data( $product );
        } else {
            $form = $service->product_form_data( $product );
        }

        if ( 'POST' === strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) {
            if ( ! self::verify_update_nonce() ) {
                $errors[] = __( 'No se pudo validar la solicitud. Intenta nuevamente.', 'sultana-admin' );
            } else {
                $form   = self::posted_product_data();
                if ( 'variable' === $product_type ) {
                    $result = $variable_service->update_variable_product( $product_id, $form );
                } elseif ( 'combo' === $product_type ) {
                    $result = $combo_service->update_combo_product( $product_id, $form );
                } else {
                    $result = $service->update_simple_product( $product_id, $form );
                }

                if ( $result['success'] ) {
                    wp_safe_redirect( add_query_arg( 'notice', 'product_updated', Router::products_url() ) );
                    exit;
                }

                $errors = $result['errors'];
            }
        }

        return [
            'form'              => $form,
            'errors'            => $errors,
            'categories'        => $service->get_product_categories(),
            'brands'            => $service->get_product_brands(),
            'brand_taxonomy'    => $service->get_brand_taxonomy(),
            'selected_images'   => 'combo' === $product_type ? [] : $image_service->get_product_image_items_for_form( $form['product_image_ids'] ?? '', $product_id ),
            'product_type'      => $product_type,
            'product_id'        => $product_id,
            'available_attributes' => $variable_service->available_attributes(),
            'combo_components'  => $combo_service->components_for_form( $form['combo_components'] ?? [] ),
            'form_action'       => Router::edit_product_url( $product_id ),
            'form_nonce_action' => self::UPDATE_NONCE_ACTION,
            'form_title'        => self::form_title_for_type( $product_type, true ),
            'form_kicker'       => self::form_kicker_for_type( $product_type ),
            'submit_label'      => __( 'Actualizar producto', 'sultana-admin' ),
            'notice'            => self::edit_notice(),
        ];
    }

    public static function ajax_upload_product_image(): void
    {
        if ( ! self::can_handle_image_ajax() ) {
            wp_send_json_error(
                [
                    'message' => __( 'No tienes permisos para subir imagenes.', 'sultana-admin' ),
                ],
                403
            );
        }

        $service = new ProductImageService();
        $result  = $service->upload_temporary_image( 'image' );

        if ( empty( $result['success'] ) ) {
            wp_send_json_error(
                [
                    'message' => $result['error'] ?? __( 'No se pudo subir la imagen.', 'sultana-admin' ),
                ],
                400
            );
        }

        wp_send_json_success(
            [
                'image' => $result['image'],
            ]
        );
    }

    public static function ajax_delete_product_image(): void
    {
        if ( ! self::can_handle_image_ajax() ) {
            wp_send_json_error(
                [
                    'message' => __( 'No tienes permisos para eliminar imagenes.', 'sultana-admin' ),
                ],
                403
            );
        }

        $attachment_id = isset( $_POST['attachment_id'] ) ? absint( wp_unslash( $_POST['attachment_id'] ) ) : 0;
        $service       = new ProductImageService();
        $result        = $service->delete_temporary_image( $attachment_id );

        if ( empty( $result['success'] ) ) {
            wp_send_json_error(
                [
                    'message' => $result['error'] ?? __( 'No se pudo eliminar la imagen.', 'sultana-admin' ),
                ],
                400
            );
        }

        wp_send_json_success();
    }

    public static function ajax_search_combo_components(): void
    {
        if ( ! self::can_search_combo_components() ) {
            wp_send_json_error(
                [
                    'message' => __( 'No tienes permisos para buscar componentes.', 'sultana-admin' ),
                ],
                403
            );
        }

        if ( ! class_exists( '\Sultana\CommerceCore\Modules\Combos\ComboComponentService' ) ) {
            wp_send_json_error(
                [
                    'message' => __( 'Commerce Core no esta listo para buscar componentes.', 'sultana-admin' ),
                ],
                400
            );
        }

        $term  = isset( $_GET['term'] ) ? wc_clean( wp_unslash( $_GET['term'] ) ) : '';
        $limit = isset( $_GET['limit'] ) ? absint( wp_unslash( $_GET['limit'] ) ) : 20;
        $limit = $limit > 0 ? min( $limit, 30 ) : 20;
        $exclude = isset( $_GET['exclude'] ) && is_array( $_GET['exclude'] )
            ? array_slice( array_map( 'absint', wp_unslash( $_GET['exclude'] ) ), 0, 100 )
            : [];
        $exclude = array_values( array_filter( array_unique( $exclude ) ) );

        $components = \Sultana\CommerceCore\Modules\Combos\ComboComponentService::search_components( (string) $term, $limit, $exclude );

        wp_send_json_success(
            [
                'components' => $components,
            ]
        );
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

    private static function list_notice(): string
    {
        $notice = isset( $_GET['notice'] ) ? sanitize_key( wp_unslash( $_GET['notice'] ) ) : '';

        if ( 'product_created' === $notice ) {
            return __( 'Producto creado correctamente.', 'sultana-admin' );
        }

        if ( 'product_updated' === $notice ) {
            return __( 'Producto actualizado correctamente.', 'sultana-admin' );
        }

        if ( 'product_trashed' === $notice ) {
            return __( 'Producto enviado a la papelera correctamente.', 'sultana-admin' );
        }

        return '';
    }

    private static function handle_trash_request( ProductService $service ): array
    {
        if ( 'POST' !== strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) {
            return [];
        }

        $action = isset( $_POST['sultana_admin_action'] ) ? sanitize_key( wp_unslash( $_POST['sultana_admin_action'] ) ) : '';

        if ( 'trash_product' !== $action ) {
            return [];
        }

        if ( ! is_user_logged_in() || ! current_user_can( Capabilities::ACCESS_CAPABILITY ) || ! current_user_can( Capabilities::DELETE_PRODUCTS_CAPABILITY ) ) {
            return [ __( 'No tienes permisos para eliminar productos.', 'sultana-admin' ) ];
        }

        $nonce = isset( $_POST['sultana_admin_trash_nonce'] )
            ? sanitize_text_field( wp_unslash( $_POST['sultana_admin_trash_nonce'] ) )
            : '';

        if ( ! wp_verify_nonce( $nonce, self::TRASH_NONCE_ACTION ) ) {
            return [ __( 'No se pudo validar la solicitud. Intenta nuevamente.', 'sultana-admin' ) ];
        }

        $product_id = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
        $result     = $service->trash_product( $product_id );

        if ( empty( $result['success'] ) ) {
            return $result['errors'];
        }

        wp_safe_redirect( add_query_arg( 'notice', 'product_trashed', Router::products_url() ) );
        exit;
    }

    private static function verify_create_nonce(): bool
    {
        $nonce = isset( $_POST['sultana_admin_product_nonce'] )
            ? sanitize_text_field( wp_unslash( $_POST['sultana_admin_product_nonce'] ) )
            : '';

        return wp_verify_nonce( $nonce, self::CREATE_NONCE_ACTION );
    }

    private static function verify_update_nonce(): bool
    {
        $nonce = isset( $_POST['sultana_admin_product_nonce'] )
            ? sanitize_text_field( wp_unslash( $_POST['sultana_admin_product_nonce'] ) )
            : '';

        return wp_verify_nonce( $nonce, self::UPDATE_NONCE_ACTION );
    }

    private static function edit_notice(): string
    {
        $notice = isset( $_GET['notice'] ) ? sanitize_key( wp_unslash( $_GET['notice'] ) ) : '';

        if ( 'product_updated' === $notice ) {
            return __( 'Producto actualizado correctamente.', 'sultana-admin' );
        }

        return '';
    }

    private static function requested_product_type(): string
    {
        $type = isset( $_POST['product_type'] )
            ? sanitize_key( wp_unslash( $_POST['product_type'] ) )
            : ( isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : 'simple' );

        return self::normalize_product_type( $type );
    }

    private static function posted_product_data(): array
    {
        $category_ids = isset( $_POST['category_ids'] ) && is_array( $_POST['category_ids'] )
            ? array_map( 'absint', wp_unslash( $_POST['category_ids'] ) )
            : [];

        return [
            'product_type'      => isset( $_POST['product_type'] ) ? sanitize_key( wp_unslash( $_POST['product_type'] ) ) : 'simple',
            'name'              => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
            'regular_price'     => isset( $_POST['regular_price'] ) ? wc_clean( wp_unslash( $_POST['regular_price'] ) ) : '',
            'sale_price'        => isset( $_POST['sale_price'] ) ? wc_clean( wp_unslash( $_POST['sale_price'] ) ) : '',
            'sku'               => isset( $_POST['sku'] ) ? wc_clean( wp_unslash( $_POST['sku'] ) ) : '',
            'short_description' => isset( $_POST['short_description'] ) ? wp_kses_post( wp_unslash( $_POST['short_description'] ) ) : '',
            'category_ids'      => array_values( array_filter( array_unique( $category_ids ) ) ),
            'brand_id'          => isset( $_POST['brand_id'] ) ? absint( wp_unslash( $_POST['brand_id'] ) ) : 0,
            'stock_quantity'    => isset( $_POST['stock_quantity'] ) ? wc_clean( wp_unslash( $_POST['stock_quantity'] ) ) : '',
            'product_image_ids' => isset( $_POST['product_image_ids'] ) ? sanitize_text_field( wp_unslash( $_POST['product_image_ids'] ) ) : '',
            'variable_attributes' => isset( $_POST['variable_attributes'] ) && is_array( $_POST['variable_attributes'] ) ? wp_unslash( $_POST['variable_attributes'] ) : [],
            'variations'        => isset( $_POST['variations'] ) && is_array( $_POST['variations'] ) ? wp_unslash( $_POST['variations'] ) : [],
            'combo_components'  => isset( $_POST['combo_components'] ) && is_array( $_POST['combo_components'] ) ? wp_unslash( $_POST['combo_components'] ) : [],
            'status'            => isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : 'draft',
        ];
    }

    private static function default_form_data( string $product_type, ProductService $service, ProductVariableService $variable_service, ProductComboService $combo_service ): array
    {
        if ( 'variable' === $product_type ) {
            return $variable_service->default_product_data();
        }

        if ( 'combo' === $product_type ) {
            return $combo_service->default_product_data();
        }

        return $service->default_simple_product_data();
    }

    private static function create_product_for_type( string $product_type, array $form, ProductService $service, ProductVariableService $variable_service, ProductComboService $combo_service ): array
    {
        if ( 'variable' === $product_type ) {
            return $variable_service->create_variable_product( $form );
        }

        if ( 'combo' === $product_type ) {
            return $combo_service->create_combo_product( $form );
        }

        return $service->create_simple_product( $form );
    }

    private static function normalize_product_type( string $type ): string
    {
        return in_array( $type, [ 'simple', 'variable', 'combo' ], true ) ? $type : 'simple';
    }

    private static function form_title_for_type( string $product_type, bool $editing ): string
    {
        if ( 'variable' === $product_type ) {
            return $editing ? __( 'Editar producto variable', 'sultana-admin' ) : __( 'Nuevo producto variable', 'sultana-admin' );
        }

        if ( 'combo' === $product_type ) {
            return $editing ? __( 'Editar combo', 'sultana-admin' ) : __( 'Nuevo combo', 'sultana-admin' );
        }

        return $editing ? __( 'Editar producto', 'sultana-admin' ) : __( 'Nuevo producto', 'sultana-admin' );
    }

    private static function form_kicker_for_type( string $product_type ): string
    {
        if ( 'variable' === $product_type ) {
            return __( 'Producto variable', 'sultana-admin' );
        }

        if ( 'combo' === $product_type ) {
            return __( 'Combo', 'sultana-admin' );
        }

        return __( 'Producto simple', 'sultana-admin' );
    }

    private static function can_handle_image_ajax(): bool
    {
        if ( ! check_ajax_referer( self::IMAGE_UPLOAD_NONCE_ACTION, 'nonce', false ) ) {
            return false;
        }

        return is_user_logged_in()
            && current_user_can( Capabilities::ACCESS_CAPABILITY )
            && current_user_can( Capabilities::CREATE_PRODUCTS_CAPABILITY )
            && current_user_can( Capabilities::UPLOAD_FILES_CAPABILITY );
    }

    private static function can_search_combo_components(): bool
    {
        if ( ! check_ajax_referer( self::COMBO_COMPONENT_SEARCH_NONCE_ACTION, 'nonce', false ) ) {
            return false;
        }

        return is_user_logged_in()
            && current_user_can( Capabilities::ACCESS_CAPABILITY )
            && current_user_can( Capabilities::CREATE_PRODUCTS_CAPABILITY );
    }
}

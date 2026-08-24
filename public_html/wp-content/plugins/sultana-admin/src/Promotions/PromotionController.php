<?php

namespace Sultana\Admin\Promotions;

use Sultana\Admin\Core\Capabilities;
use Sultana\Admin\Core\Router;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PromotionController
{
    public const SAVE_NONCE_ACTION = 'sultana_admin_save_home_promotion';
    public const DELETE_NONCE_ACTION = 'sultana_admin_delete_home_promotion';
    public const IMAGE_UPLOAD_NONCE_ACTION = 'sultana_admin_promotion_image_upload';
    public const IMAGE_UPLOAD_ACTION = 'sultana_admin_upload_promotion_image';
    public const IMAGE_DELETE_ACTION = 'sultana_admin_delete_promotion_image';

    public static function prepare_screen(): array
    {
        $service      = new PromotionService();
        $errors       = [];
        $notice       = self::notice();
        $promotion_id = isset( $_GET['promotion_id'] ) ? absint( wp_unslash( $_GET['promotion_id'] ) ) : 0;
        $form         = $service->form_data( $promotion_id );

        if ( 'POST' === strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) {
            $action = isset( $_POST['sultana_admin_action'] ) ? sanitize_key( wp_unslash( $_POST['sultana_admin_action'] ) ) : '';

            if ( 'save_promotion' === $action ) {
                $result = self::handle_save_request( $service );

                if ( ! empty( $result['success'] ) ) {
                    wp_safe_redirect( add_query_arg( 'notice', 'promotion_saved', Router::banners_url() ) );
                    exit;
                }

                $errors = $result['errors'];
                $form   = self::posted_form_data();
            } elseif ( 'delete_promotion' === $action ) {
                $result = self::handle_delete_request( $service );

                if ( ! empty( $result['success'] ) ) {
                    wp_safe_redirect( add_query_arg( 'notice', 'promotion_deleted', Router::banners_url() ) );
                    exit;
                }

                $errors = $result['errors'];
            }
        }

        return [
            'promotions'          => $service->list_promotions(),
            'form'                => $form,
            'selected_images'     => $service->image_items_for_form( $form ),
            'destination_options' => $service->destination_options(),
            'destination_choices' => $service->destination_choices(),
            'errors'              => $errors,
            'notice'              => $notice,
            'form_action'         => Router::banners_url(),
            'form_nonce_action'   => self::SAVE_NONCE_ACTION,
            'delete_nonce_action' => self::DELETE_NONCE_ACTION,
        ];
    }

    public static function ajax_upload_promotion_image(): void
    {
        if ( ! self::can_handle_image_ajax() ) {
            wp_send_json_error(
                [
                    'message' => __( 'No tienes permisos para subir banners.', 'sultana-admin' ),
                ],
                403
            );
        }

        $slot    = isset( $_POST['slot'] ) ? sanitize_key( wp_unslash( $_POST['slot'] ) ) : '';
        $service = new PromotionImageService();
        $result  = $service->upload_temporary_image( 'image', $slot, self::image_upload_context() );

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

    public static function ajax_delete_promotion_image(): void
    {
        if ( ! self::can_handle_image_ajax() ) {
            wp_send_json_error(
                [
                    'message' => __( 'No tienes permisos para eliminar banners.', 'sultana-admin' ),
                ],
                403
            );
        }

        $attachment_id = isset( $_POST['attachment_id'] ) ? absint( wp_unslash( $_POST['attachment_id'] ) ) : 0;
        $service       = new PromotionImageService();
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

    private static function handle_save_request( PromotionService $service ): array
    {
        if ( ! self::can_manage_promotions() ) {
            return [
                'success' => false,
                'errors'  => [ __( 'No tienes permisos para gestionar banners.', 'sultana-admin' ) ],
            ];
        }

        if ( ! self::verify_nonce( 'sultana_admin_promotion_nonce', self::SAVE_NONCE_ACTION ) ) {
            return [
                'success' => false,
                'errors'  => [ __( 'No se pudo validar la solicitud. Intenta nuevamente.', 'sultana-admin' ) ],
            ];
        }

        return $service->save_promotion( self::posted_form_data() );
    }

    private static function handle_delete_request( PromotionService $service ): array
    {
        if ( ! self::can_manage_promotions() ) {
            return [
                'success' => false,
                'errors'  => [ __( 'No tienes permisos para gestionar banners.', 'sultana-admin' ) ],
            ];
        }

        if ( ! self::verify_nonce( 'sultana_admin_delete_promotion_nonce', self::DELETE_NONCE_ACTION ) ) {
            return [
                'success' => false,
                'errors'  => [ __( 'No se pudo validar la solicitud. Intenta nuevamente.', 'sultana-admin' ) ],
            ];
        }

        $promotion_id = isset( $_POST['promotion_id'] ) ? absint( wp_unslash( $_POST['promotion_id'] ) ) : 0;

        return $service->delete_promotion( $promotion_id );
    }

    private static function posted_form_data(): array
    {
        return [
            'promotion_id'       => isset( $_POST['promotion_id'] ) ? absint( wp_unslash( $_POST['promotion_id'] ) ) : 0,
            'name'               => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
            'desktop_image_id'   => isset( $_POST['desktop_image_id'] ) ? absint( wp_unslash( $_POST['desktop_image_id'] ) ) : 0,
            'mobile_image_id'    => isset( $_POST['mobile_image_id'] ) ? absint( wp_unslash( $_POST['mobile_image_id'] ) ) : 0,
            'alt_text'           => isset( $_POST['alt_text'] ) ? sanitize_text_field( wp_unslash( $_POST['alt_text'] ) ) : '',
            'destination_type'   => isset( $_POST['destination_type'] ) ? sanitize_key( wp_unslash( $_POST['destination_type'] ) ) : 'none',
            'destination_value'  => isset( $_POST['destination_value'] ) ? absint( wp_unslash( $_POST['destination_value'] ) ) : 0,
            'custom_url'         => isset( $_POST['custom_url'] ) ? esc_url_raw( wp_unslash( $_POST['custom_url'] ) ) : '',
            'active'             => 'yes' === ( isset( $_POST['active'] ) ? sanitize_text_field( wp_unslash( $_POST['active'] ) ) : '' ),
            'menu_order'         => isset( $_POST['menu_order'] ) ? (int) wp_unslash( $_POST['menu_order'] ) : 0,
        ];
    }

    private static function image_upload_context(): array
    {
        return [
            'promotion_title' => isset( $_POST['promotion_title'] )
                ? sanitize_text_field( wp_unslash( $_POST['promotion_title'] ) )
                : '',
            'image_index'     => isset( $_POST['image_index'] )
                ? absint( wp_unslash( $_POST['image_index'] ) )
                : 0,
        ];
    }

    private static function can_handle_image_ajax(): bool
    {
        if ( ! check_ajax_referer( self::IMAGE_UPLOAD_NONCE_ACTION, 'nonce', false ) ) {
            return false;
        }

        return self::can_manage_promotions()
            && current_user_can( Capabilities::UPLOAD_FILES_CAPABILITY );
    }

    private static function can_manage_promotions(): bool
    {
        return is_user_logged_in()
            && current_user_can( Capabilities::ACCESS_CAPABILITY )
            && current_user_can( Capabilities::MANAGE_HOME_PROMOTIONS_CAPABILITY );
    }

    private static function verify_nonce( string $field, string $action ): bool
    {
        $nonce = isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '';

        return wp_verify_nonce( $nonce, $action );
    }

    private static function notice(): string
    {
        $notice = isset( $_GET['notice'] ) ? sanitize_key( wp_unslash( $_GET['notice'] ) ) : '';

        if ( 'promotion_saved' === $notice ) {
            return __( 'Banner guardado correctamente.', 'sultana-admin' );
        }

        if ( 'promotion_deleted' === $notice ) {
            return __( 'Banner enviado a la papelera correctamente.', 'sultana-admin' );
        }

        return '';
    }
}

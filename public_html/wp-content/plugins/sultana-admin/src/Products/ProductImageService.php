<?php

namespace Sultana\Admin\Products;

use Sultana\Admin\Core\Capabilities;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ProductImageService
{
    public const TEMP_META_KEY = '_sultana_admin_temp_upload';
    public const TEMP_USER_META_KEY = '_sultana_admin_temp_upload_user';
    public const TEMP_TIME_META_KEY = '_sultana_admin_temp_upload_time';

    private const ALLOWED_IMAGE_MIMES = [ 'image/jpeg', 'image/png', 'image/gif', 'image/webp' ];

    public function upload_temporary_image( string $field ): array
    {
        if ( ! $this->can_manage_images() ) {
            return [
                'success' => false,
                'error'   => __( 'No tienes permisos para subir imagenes.', 'sultana-admin' ),
            ];
        }

        $upload = $this->upload_file_from_field( $field );

        if ( is_wp_error( $upload ) ) {
            return [
                'success' => false,
                'error'   => $upload->get_error_message(),
            ];
        }

        $attachment_id = absint( $upload );

        update_post_meta( $attachment_id, self::TEMP_META_KEY, '1' );
        update_post_meta( $attachment_id, self::TEMP_USER_META_KEY, get_current_user_id() );
        update_post_meta( $attachment_id, self::TEMP_TIME_META_KEY, time() );

        return [
            'success' => true,
            'image'   => $this->format_image_item( $attachment_id ),
        ];
    }

    public function delete_temporary_image( int $attachment_id ): array
    {
        if ( ! $this->can_manage_images() ) {
            return [
                'success' => false,
                'error'   => __( 'No tienes permisos para eliminar imagenes.', 'sultana-admin' ),
            ];
        }

        if ( ! $this->is_current_user_temporary_image( $attachment_id ) ) {
            return [
                'success' => false,
                'error'   => __( 'No se puede eliminar esa imagen.', 'sultana-admin' ),
            ];
        }

        $deleted = wp_delete_attachment( $attachment_id, true );

        if ( ! $deleted ) {
            return [
                'success' => false,
                'error'   => __( 'No se pudo eliminar la imagen.', 'sultana-admin' ),
            ];
        }

        return [
            'success' => true,
            'error'   => '',
        ];
    }

    public function validate_temporary_image_ids( $raw_ids )
    {
        $ids = $this->parse_image_ids( $raw_ids );

        if ( ! empty( $ids ) && ! $this->can_manage_images() ) {
            return new WP_Error(
                'sultana_admin_product_image_permission_denied',
                __( 'No tienes permisos para usar esas imagenes.', 'sultana-admin' )
            );
        }

        foreach ( $ids as $attachment_id ) {
            if ( ! $this->is_current_user_temporary_image( $attachment_id ) ) {
                return new WP_Error(
                    'sultana_admin_invalid_product_image',
                    __( 'Selecciona imagenes validas para este producto.', 'sultana-admin' )
                );
            }
        }

        return $ids;
    }

    public function get_temporary_image_items( $raw_ids ): array
    {
        $ids   = $this->parse_image_ids( $raw_ids );
        $items = [];

        foreach ( $ids as $attachment_id ) {
            if ( $this->is_current_user_temporary_image( $attachment_id ) ) {
                $items[] = $this->format_image_item( $attachment_id );
            }
        }

        return $items;
    }

    public function release_temporary_images( array $attachment_ids ): void
    {
        foreach ( array_filter( array_map( 'absint', $attachment_ids ) ) as $attachment_id ) {
            if ( ! $this->is_current_user_temporary_image( $attachment_id ) ) {
                continue;
            }

            delete_post_meta( $attachment_id, self::TEMP_META_KEY );
            delete_post_meta( $attachment_id, self::TEMP_USER_META_KEY );
            delete_post_meta( $attachment_id, self::TEMP_TIME_META_KEY );
        }
    }

    private function can_manage_images(): bool
    {
        return is_user_logged_in()
            && current_user_can( Capabilities::ACCESS_CAPABILITY )
            && current_user_can( Capabilities::CREATE_PRODUCTS_CAPABILITY )
            && current_user_can( Capabilities::UPLOAD_FILES_CAPABILITY );
    }

    private function parse_image_ids( $raw_ids ): array
    {
        if ( is_array( $raw_ids ) ) {
            $parts = $raw_ids;
        } else {
            $parts = explode( ',', (string) $raw_ids );
        }

        $ids = [];

        foreach ( $parts as $part ) {
            $attachment_id = absint( $part );

            if ( $attachment_id && ! in_array( $attachment_id, $ids, true ) ) {
                $ids[] = $attachment_id;
            }
        }

        return $ids;
    }

    private function is_current_user_temporary_image( int $attachment_id ): bool
    {
        $post = get_post( $attachment_id );

        if ( ! $post || 'attachment' !== $post->post_type || ! wp_attachment_is_image( $attachment_id ) ) {
            return false;
        }

        if ( '1' !== (string) get_post_meta( $attachment_id, self::TEMP_META_KEY, true ) ) {
            return false;
        }

        return get_current_user_id() === absint( get_post_meta( $attachment_id, self::TEMP_USER_META_KEY, true ) );
    }

    private function upload_file_from_field( string $field )
    {
        $file = $_FILES[ $field ] ?? null;

        if ( ! is_array( $file ) || ! isset( $file['error'] ) ) {
            return new WP_Error( 'sultana_admin_missing_image', __( 'No se pudo subir la imagen.', 'sultana-admin' ) );
        }

        if ( UPLOAD_ERR_OK !== (int) $file['error'] ) {
            return new WP_Error( 'sultana_admin_upload_error', __( 'No se pudo subir la imagen.', 'sultana-admin' ) );
        }

        $check = wp_check_filetype_and_ext( (string) $file['tmp_name'], (string) $file['name'] );
        $type  = isset( $check['type'] ) ? (string) $check['type'] : '';

        if ( '' === $type || ! in_array( $type, self::ALLOWED_IMAGE_MIMES, true ) ) {
            return new WP_Error( 'sultana_admin_invalid_image', __( 'Selecciona un archivo de imagen valido.', 'sultana-admin' ) );
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $attachment_id = media_handle_upload( $field, 0 );

        if ( is_wp_error( $attachment_id ) ) {
            return new WP_Error( 'sultana_admin_media_upload_failed', __( 'No se pudo subir la imagen.', 'sultana-admin' ) );
        }

        return absint( $attachment_id );
    }

    private function format_image_item( int $attachment_id ): array
    {
        $url = wp_get_attachment_image_url( $attachment_id, 'thumbnail' );

        if ( ! is_string( $url ) || '' === $url ) {
            $url = wp_get_attachment_url( $attachment_id );
        }

        return [
            'id'   => $attachment_id,
            'url'  => is_string( $url ) ? $url : '',
            'name' => get_the_title( $attachment_id ),
        ];
    }
}

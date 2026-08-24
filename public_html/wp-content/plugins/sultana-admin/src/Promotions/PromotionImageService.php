<?php

namespace Sultana\Admin\Promotions;

use Sultana\Admin\Core\Capabilities;
use Sultana\Admin\Media\MediaImageProcessor;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class PromotionImageService
{
    public const TEMP_META_KEY = '_sultana_admin_temp_promotion_image';
    public const TEMP_USER_META_KEY = '_sultana_admin_temp_promotion_image_user';
    public const TEMP_TIME_META_KEY = '_sultana_admin_temp_promotion_image_time';
    public const TEMP_SLOT_META_KEY = '_sultana_admin_temp_promotion_image_slot';

    private const ALLOWED_IMAGE_MIMES = [ 'image/jpeg', 'image/png', 'image/webp' ];
    private const VALID_SLOTS = [ 'desktop', 'mobile' ];

    public function upload_temporary_image( string $field, string $slot, array $context = [] ): array
    {
        if ( ! $this->can_manage_images() ) {
            return [
                'success' => false,
                'error'   => __( 'No tienes permisos para subir banners.', 'sultana-admin' ),
            ];
        }

        $slot = $this->sanitize_slot( $slot );

        if ( '' === $slot ) {
            return [
                'success' => false,
                'error'   => __( 'Selecciona un tipo de banner valido.', 'sultana-admin' ),
            ];
        }

        $upload = $this->upload_file_from_field( $field, $slot, $context );

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
        update_post_meta( $attachment_id, self::TEMP_SLOT_META_KEY, $slot );

        return [
            'success' => true,
            'image'   => $this->format_image_item( $attachment_id, true ),
        ];
    }

    public function delete_temporary_image( int $attachment_id ): array
    {
        if ( ! $this->can_manage_images() ) {
            return [
                'success' => false,
                'error'   => __( 'No tienes permisos para eliminar banners.', 'sultana-admin' ),
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

    public function validate_promotion_image_id( int $attachment_id, int $promotion_id = 0 )
    {
        if ( ! $attachment_id ) {
            return 0;
        }

        if ( $promotion_id > 0 && in_array( $attachment_id, $this->official_promotion_image_ids( $promotion_id ), true ) && $this->is_valid_image_attachment( $attachment_id ) ) {
            return $attachment_id;
        }

        if ( $this->is_current_user_temporary_image( $attachment_id ) ) {
            return $attachment_id;
        }

        return new WP_Error(
            'sultana_admin_invalid_promotion_image',
            __( 'Selecciona banners validos para esta promocion.', 'sultana-admin' )
        );
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
            delete_post_meta( $attachment_id, self::TEMP_SLOT_META_KEY );
        }
    }

    public function format_image_item( int $attachment_id, bool $temporary = false ): array
    {
        $src = wp_get_attachment_image_src( $attachment_id, 'full' );
        $url = wp_get_attachment_image_url( $attachment_id, 'medium' );
        $is_temporary = $temporary || $this->is_current_user_temporary_image( $attachment_id );

        if ( ! is_string( $url ) || '' === $url ) {
            $url = wp_get_attachment_url( $attachment_id );
        }

        $file_path = get_attached_file( $attachment_id );
        $filesize  = is_string( $file_path ) && is_readable( $file_path ) ? (int) filesize( $file_path ) : 0;

        return [
            'id'            => $attachment_id,
            'attachment_id' => $attachment_id,
            'url'           => is_string( $url ) ? $url : '',
            'name'          => get_the_title( $attachment_id ),
            'width'         => is_array( $src ) ? absint( $src[1] ?? 0 ) : 0,
            'height'        => is_array( $src ) ? absint( $src[2] ?? 0 ) : 0,
            'filesize'      => $filesize,
            'mime'          => (string) get_post_mime_type( $attachment_id ),
            'temporary'     => $is_temporary,
            'slot'          => (string) get_post_meta( $attachment_id, self::TEMP_SLOT_META_KEY, true ),
        ];
    }

    private function upload_file_from_field( string $field, string $slot, array $context = [] )
    {
        $file = $_FILES[ $field ] ?? null;

        if ( ! is_array( $file ) || ! isset( $file['error'] ) ) {
            return new WP_Error( 'sultana_admin_missing_promotion_image', __( 'No se pudo subir la imagen.', 'sultana-admin' ) );
        }

        if ( UPLOAD_ERR_OK !== (int) $file['error'] ) {
            return new WP_Error( 'sultana_admin_promotion_upload_error', __( 'No se pudo subir la imagen.', 'sultana-admin' ) );
        }

        $check = wp_check_filetype_and_ext( (string) $file['tmp_name'], (string) $file['name'] );
        $type  = isset( $check['type'] ) ? (string) $check['type'] : '';

        if ( '' === $type || ! in_array( $type, self::ALLOWED_IMAGE_MIMES, true ) ) {
            return new WP_Error( 'sultana_admin_invalid_promotion_image', __( 'Selecciona una imagen JPEG, PNG o WebP.', 'sultana-admin' ) );
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $processor = new MediaImageProcessor();
        $profile   = 'mobile' === $slot
            ? MediaImageProcessor::promotion_mobile_profile()
            : MediaImageProcessor::promotion_desktop_profile();

        $prepared_file = $processor->prepare_for_media_handle_upload(
            [
                'name'     => (string) $file['name'],
                'type'     => $type,
                'tmp_name' => (string) $file['tmp_name'],
                'error'    => (int) $file['error'],
                'size'     => isset( $file['size'] ) ? (int) $file['size'] : 0,
            ],
            $context,
            $profile
        );

        if ( is_wp_error( $prepared_file ) ) {
            return $prepared_file;
        }

        $original_file   = $_FILES[ $field ];
        $temporary_paths = $prepared_file['temporary_paths'] ?? [];
        $_FILES[ $field ] = $prepared_file['file'];

        try {
            $attachment_id = media_handle_upload( $field, 0 );
        } finally {
            $_FILES[ $field ] = $original_file;
            $processor->cleanup_temporary_paths( $temporary_paths );
        }

        if ( is_wp_error( $attachment_id ) ) {
            return new WP_Error( 'sultana_admin_promotion_media_upload_failed', __( 'No se pudo subir la imagen.', 'sultana-admin' ) );
        }

        return absint( $attachment_id );
    }

    private function official_promotion_image_ids( int $promotion_id ): array
    {
        $promotion_class = '\Sultana\CommerceCore\Modules\HomePromotions\HomePromotions';

        if ( ! class_exists( $promotion_class ) || ! method_exists( $promotion_class, 'get_promotion' ) ) {
            return [];
        }

        $promotion = $promotion_class::get_promotion( $promotion_id );

        if ( ! is_array( $promotion ) ) {
            return [];
        }

        return array_values(
            array_filter(
                [
                    absint( $promotion['desktop_image_id'] ?? 0 ),
                    absint( $promotion['mobile_image_id'] ?? 0 ),
                ]
            )
        );
    }

    private function can_manage_images(): bool
    {
        return is_user_logged_in()
            && current_user_can( Capabilities::ACCESS_CAPABILITY )
            && current_user_can( Capabilities::MANAGE_HOME_PROMOTIONS_CAPABILITY )
            && current_user_can( Capabilities::UPLOAD_FILES_CAPABILITY );
    }

    private function is_current_user_temporary_image( int $attachment_id ): bool
    {
        if ( ! $this->is_valid_image_attachment( $attachment_id ) ) {
            return false;
        }

        if ( '1' !== (string) get_post_meta( $attachment_id, self::TEMP_META_KEY, true ) ) {
            return false;
        }

        return get_current_user_id() === absint( get_post_meta( $attachment_id, self::TEMP_USER_META_KEY, true ) );
    }

    private function is_valid_image_attachment( int $attachment_id ): bool
    {
        $post = get_post( $attachment_id );

        return $post && 'attachment' === $post->post_type && wp_attachment_is_image( $attachment_id );
    }

    private function sanitize_slot( string $slot ): string
    {
        $slot = sanitize_key( $slot );

        return in_array( $slot, self::VALID_SLOTS, true ) ? $slot : '';
    }
}

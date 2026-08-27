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

    private const ALLOWED_IMAGE_MIMES = [ 'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif' ];

    public function upload_temporary_image( string $field, array $context = [] ): array
    {
        if ( ! $this->can_manage_images() ) {
            return [
                'success' => false,
                'error'   => __( 'No tienes permisos para subir imagenes.', 'sultana-admin' ),
            ];
        }

        $upload = $this->upload_file_from_field( $field, $context );

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
            'image'   => $this->format_image_item( $attachment_id, true ),
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
        return $this->validate_product_image_ids( $raw_ids, 0 );
    }

    public function validate_product_image_ids( $raw_ids, int $product_id = 0, bool $include_variation_images = true )
    {
        $ids = $this->parse_image_ids( $raw_ids );
        $existing_ids = $product_id
            ? ( $include_variation_images ? $this->product_and_variation_image_ids( $product_id ) : $this->product_image_ids( $product_id ) )
            : [];

        if ( ! empty( $ids ) && ! $this->can_manage_images() ) {
            return new WP_Error(
                'sultana_admin_product_image_permission_denied',
                __( 'No tienes permisos para usar esas imagenes.', 'sultana-admin' )
            );
        }

        foreach ( $ids as $attachment_id ) {
            if ( in_array( $attachment_id, $existing_ids, true ) && $this->is_valid_image_attachment( $attachment_id ) ) {
                continue;
            }

            if ( ! $this->is_current_user_temporary_image( $attachment_id ) ) {
                return new WP_Error(
                    'sultana_admin_invalid_product_image',
                    __( 'Selecciona imagenes validas para este producto.', 'sultana-admin' )
                );
            }
        }

        return $ids;
    }

    public function validate_single_product_image_id( int $attachment_id, int $product_id = 0 )
    {
        if ( ! $attachment_id ) {
            return 0;
        }

        $validated = $this->validate_product_image_ids( [ $attachment_id ], $product_id );

        if ( is_wp_error( $validated ) ) {
            return $validated;
        }

        return $validated[0] ?? 0;
    }

    public function get_temporary_image_items( $raw_ids ): array
    {
        return $this->get_product_image_items_for_form( $raw_ids, 0 );
    }

    public function get_product_image_items_for_form( $raw_ids, int $product_id = 0, bool $include_variation_images = true ): array
    {
        $ids   = $this->parse_image_ids( $raw_ids );
        $items = [];
        $existing_ids = $product_id
            ? ( $include_variation_images ? $this->product_and_variation_image_ids( $product_id ) : $this->product_image_ids( $product_id ) )
            : [];

        foreach ( $ids as $attachment_id ) {
            if ( in_array( $attachment_id, $existing_ids, true ) && $this->is_valid_image_attachment( $attachment_id ) ) {
                $items[] = $this->format_image_item( $attachment_id, false );
                continue;
            }

            if ( $this->is_current_user_temporary_image( $attachment_id ) ) {
                $items[] = $this->format_image_item( $attachment_id, true );
            }
        }

        return $items;
    }

    public function product_image_ids( int $product_id ): array
    {
        $product = wc_get_product( $product_id );

        if ( ! $product ) {
            return [];
        }

        $ids      = [];
        $image_id = absint( $product->get_image_id() );

        if ( $image_id ) {
            $ids[] = $image_id;
        }

        foreach ( $product->get_gallery_image_ids() as $gallery_image_id ) {
            $gallery_image_id = absint( $gallery_image_id );

            if ( $gallery_image_id && ! in_array( $gallery_image_id, $ids, true ) ) {
                $ids[] = $gallery_image_id;
            }
        }

        return $ids;
    }

    public function product_and_variation_image_ids( int $product_id ): array
    {
        $ids     = $this->product_image_ids( $product_id );
        $product = wc_get_product( $product_id );

        if ( ! $product || ! method_exists( $product, 'get_children' ) ) {
            return $ids;
        }

        foreach ( $product->get_children() as $variation_id ) {
            $variation = wc_get_product( $variation_id );

            if ( ! $variation ) {
                continue;
            }

            $image_id = absint( $variation->get_image_id() );

            if ( $image_id && ! in_array( $image_id, $ids, true ) ) {
                $ids[] = $image_id;
            }
        }

        return $ids;
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
        if ( ! $this->is_valid_image_attachment( $attachment_id ) ) {
            return false;
        }

        if ( '1' !== (string) get_post_meta( $attachment_id, self::TEMP_META_KEY, true ) ) {
            return false;
        }

        return get_current_user_id() === absint( get_post_meta( $attachment_id, self::TEMP_USER_META_KEY, true ) );
    }

    private function upload_file_from_field( string $field, array $context = [] )
    {
        $file = $_FILES[ $field ] ?? null;

        if ( ! is_array( $file ) || ! isset( $file['error'] ) ) {
            return new WP_Error( 'sultana_admin_missing_image', __( 'No se pudo subir la imagen.', 'sultana-admin' ) );
        }

        if ( UPLOAD_ERR_OK !== (int) $file['error'] ) {
            return new WP_Error( 'sultana_admin_upload_error', __( 'No se pudo subir la imagen.', 'sultana-admin' ) );
        }

        $check = wp_check_filetype_and_ext( (string) $file['tmp_name'], (string) $file['name'], $this->allowed_upload_mimes() );
        $type  = isset( $check['type'] ) ? (string) $check['type'] : '';

        if ( '' === $type || ! in_array( $type, self::ALLOWED_IMAGE_MIMES, true ) ) {
            return new WP_Error( 'sultana_admin_invalid_image', __( 'Selecciona un archivo de imagen valido.', 'sultana-admin' ) );
        }

        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $processor     = new ProductImageProcessor();
        $prepared_file = $processor->prepare_for_media_handle_upload(
            [
                'name'     => (string) $file['name'],
                'type'     => $type,
                'tmp_name' => (string) $file['tmp_name'],
                'error'    => (int) $file['error'],
                'size'     => isset( $file['size'] ) ? (int) $file['size'] : 0,
            ],
            $context
        );

        if ( is_wp_error( $prepared_file ) ) {
            return $prepared_file;
        }

        $original_file  = $_FILES[ $field ];
        $temporary_paths = $prepared_file['temporary_paths'] ?? [];
        $_FILES[ $field ] = $prepared_file['file'];

        try {
            $attachment_id = media_handle_upload( $field, 0 );
        } finally {
            $_FILES[ $field ] = $original_file;
            $processor->cleanup_temporary_paths( $temporary_paths );
        }

        if ( is_wp_error( $attachment_id ) ) {
            return new WP_Error( 'sultana_admin_media_upload_failed', __( 'No se pudo subir la imagen.', 'sultana-admin' ) );
        }

        return absint( $attachment_id );
    }

    /**
     * @return array<string,string>
     */
    private function allowed_upload_mimes(): array
    {
        return [
            'jpg|jpeg|jpe|jfif' => 'image/jpeg',
            'png'               => 'image/png',
            'gif'               => 'image/gif',
            'webp'              => 'image/webp',
            'avif'              => 'image/avif',
        ];
    }

    private function is_valid_image_attachment( int $attachment_id ): bool
    {
        $post = get_post( $attachment_id );

        return $post && 'attachment' === $post->post_type && wp_attachment_is_image( $attachment_id );
    }

    private function format_image_item( int $attachment_id, bool $temporary ): array
    {
        $url = wp_get_attachment_image_url( $attachment_id, 'thumbnail' );

        if ( ! is_string( $url ) || '' === $url ) {
            $url = wp_get_attachment_url( $attachment_id );
        }

        return [
            'id'   => $attachment_id,
            'url'  => is_string( $url ) ? $url : '',
            'name' => get_the_title( $attachment_id ),
            'temporary' => $temporary,
        ];
    }
}

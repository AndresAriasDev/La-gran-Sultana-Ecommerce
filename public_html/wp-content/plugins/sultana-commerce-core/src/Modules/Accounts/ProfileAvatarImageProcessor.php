<?php

namespace Sultana\CommerceCore\Modules\Accounts;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ProfileAvatarImageProcessor
{
    private const AVATAR_SIZE = 512;
    private const MAX_PIXELS = 30000000;
    private const WEBP_QUALITY = 82;

    /**
     * @param array<string,mixed> $file
     * @return array{path:string,mime:string,width:int,height:int}|WP_Error
     */
    public function process( array $file, string $target_dir, string $source_mime )
    {
        $source_path = isset( $file['tmp_name'] ) ? (string) $file['tmp_name'] : '';

        if ( '' === $source_path || ! is_readable( $source_path ) ) {
            return new WP_Error( 'scc_avatar_unreadable_image', __( 'No pudimos leer la imagen subida.', 'sultana-commerce-core' ) );
        }

        if ( 'image/avif' === $source_mime && ! $this->image_mime_is_supported( 'image/avif' ) ) {
            return new WP_Error( 'scc_avatar_avif_unsupported', __( 'El servidor no admite imagenes AVIF.', 'sultana-commerce-core' ) );
        }

        $dimensions = $this->read_dimensions( $source_path );

        if ( is_wp_error( $dimensions ) ) {
            return $dimensions;
        }

        if ( $dimensions['width'] * $dimensions['height'] > self::MAX_PIXELS ) {
            return new WP_Error( 'scc_avatar_dimensions_too_large', __( 'La imagen tiene dimensiones demasiado grandes.', 'sultana-commerce-core' ) );
        }

        if ( ! function_exists( 'wp_get_image_editor' ) ) {
            return new WP_Error( 'scc_avatar_editor_missing', __( 'No pudimos procesar esa imagen. Proba con otra foto.', 'sultana-commerce-core' ) );
        }

        $editor = wp_get_image_editor( $source_path );

        if ( is_wp_error( $editor ) ) {
            return new WP_Error( 'scc_avatar_editor_failed', __( 'No pudimos procesar esa imagen. Proba con otra foto.', 'sultana-commerce-core' ) );
        }

        if ( is_callable( [ $editor, 'set_quality' ] ) ) {
            $editor->set_quality( self::WEBP_QUALITY );
        }

        if ( 'image/jpeg' === $source_mime && is_callable( [ $editor, 'maybe_exif_rotate' ] ) ) {
            $rotated = $editor->maybe_exif_rotate();

            if ( is_wp_error( $rotated ) ) {
                return new WP_Error( 'scc_avatar_orientation_failed', __( 'No pudimos procesar esa imagen. Proba con otra foto.', 'sultana-commerce-core' ) );
            }
        }

        $resized = $editor->resize( self::AVATAR_SIZE, self::AVATAR_SIZE, true );

        if ( is_wp_error( $resized ) ) {
            return new WP_Error( 'scc_avatar_resize_failed', __( 'No pudimos ajustar el tamano de la imagen.', 'sultana-commerce-core' ) );
        }

        $target_mime = $this->get_supported_output_mime( $source_mime );
        $target_ext  = $this->get_extension_for_mime( $target_mime );
        $file_name   = wp_unique_filename( $target_dir, 'avatar-' . strtolower( wp_generate_password( 20, false, false ) ) . '.' . $target_ext );
        $file_path   = trailingslashit( $target_dir ) . $file_name;
        $saved       = $editor->save( $file_path, $target_mime );

        if ( is_wp_error( $saved ) || empty( $saved['path'] ) || ! file_exists( (string) $saved['path'] ) ) {
            if ( is_file( $file_path ) ) {
                wp_delete_file( $file_path );
            }

            return new WP_Error( 'scc_avatar_save_failed', __( 'No pudimos guardar la nueva foto de perfil.', 'sultana-commerce-core' ) );
        }

        return [
            'path'   => (string) $saved['path'],
            'mime'   => sanitize_mime_type( (string) ( $saved['mime-type'] ?? $target_mime ) ),
            'width'  => absint( $saved['width'] ?? self::AVATAR_SIZE ),
            'height' => absint( $saved['height'] ?? self::AVATAR_SIZE ),
        ];
    }

    /**
     * @return array{width:int,height:int}|WP_Error
     */
    private function read_dimensions( string $path )
    {
        $image_info = @getimagesize( $path );

        if ( ! is_array( $image_info ) || empty( $image_info[0] ) || empty( $image_info[1] ) ) {
            return new WP_Error( 'scc_avatar_invalid_dimensions', __( 'Selecciona una imagen JPG, PNG, WebP o AVIF valida.', 'sultana-commerce-core' ) );
        }

        return [
            'width'  => (int) $image_info[0],
            'height' => (int) $image_info[1],
        ];
    }

    private function get_supported_output_mime( string $source_mime ): string
    {
        if ( $this->image_mime_is_supported( 'image/webp' ) ) {
            return 'image/webp';
        }

        if ( 'image/png' === $source_mime ) {
            return 'image/png';
        }

        return 'image/jpeg';
    }

    private function image_mime_is_supported( string $mime ): bool
    {
        return function_exists( 'wp_image_editor_supports' )
            && wp_image_editor_supports(
                [
                    'mime_type' => $mime,
                ]
            );
    }

    private function get_extension_for_mime( string $mime_type ): string
    {
        return match ( $mime_type ) {
            'image/png'  => 'png',
            'image/webp' => 'webp',
            default      => 'jpg',
        };
    }
}

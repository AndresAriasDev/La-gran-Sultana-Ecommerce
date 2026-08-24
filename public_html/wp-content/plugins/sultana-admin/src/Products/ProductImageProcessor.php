<?php

namespace Sultana\Admin\Products;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ProductImageProcessor
{
    private const MAX_LONG_EDGE = 1600;
    private const WEBP_QUALITY = 82;
    private const MAX_UPLOAD_BYTES = 25165824; // 24 MB.
    private const MAX_PIXELS = 50000000; // 50 MP.
    private const MIN_WEBP_SAVINGS_RATIO = 0.08;

    private const RASTER_MIMES = [ 'image/jpeg', 'image/png', 'image/webp' ];

    /**
     * @param array<string,mixed> $file
     * @param array<string,mixed> $context
     * @return array{file:array<string,mixed>,temporary_paths:array<int,string>,processed:bool}
     */
    public function prepare_for_media_handle_upload( array $file, array $context = [] )
    {
        $size = isset( $file['size'] ) ? (int) $file['size'] : 0;

        if ( $size <= 0 ) {
            return new WP_Error( 'sultana_admin_empty_image', __( 'La imagen esta vacia o no se pudo leer.', 'sultana-admin' ) );
        }

        if ( $size > self::MAX_UPLOAD_BYTES ) {
            return new WP_Error(
                'sultana_admin_image_too_large',
                sprintf(
                    /* translators: %s: maximum upload size in MB. */
                    __( 'La imagen es demasiado pesada. El maximo permitido para productos es %s MB.', 'sultana-admin' ),
                    (string) ( self::MAX_UPLOAD_BYTES / 1024 / 1024 )
                )
            );
        }

        $path = isset( $file['tmp_name'] ) ? (string) $file['tmp_name'] : '';

        if ( '' === $path || ! is_readable( $path ) ) {
            return new WP_Error( 'sultana_admin_unreadable_image', __( 'No se pudo leer la imagen subida.', 'sultana-admin' ) );
        }

        $image_info = @getimagesize( $path );

        if ( ! is_array( $image_info ) || empty( $image_info[0] ) || empty( $image_info[1] ) ) {
            return new WP_Error( 'sultana_admin_invalid_image_dimensions', __( 'Selecciona un archivo de imagen valido.', 'sultana-admin' ) );
        }

        $width  = (int) $image_info[0];
        $height = (int) $image_info[1];
        $pixels = $width * $height;

        if ( $pixels > self::MAX_PIXELS ) {
            return new WP_Error(
                'sultana_admin_image_dimensions_too_large',
                __( 'La imagen tiene dimensiones demasiado grandes para procesarse con seguridad.', 'sultana-admin' )
            );
        }

        $mime = isset( $file['type'] ) ? (string) $file['type'] : '';
        $file['name'] = $this->contextual_filename( $context, $mime, (string) ( $file['name'] ?? '' ) );

        if ( 'image/gif' === $mime ) {
            return [
                'file'            => $file,
                'temporary_paths' => [],
                'processed'       => false,
            ];
        }

        if ( ! in_array( $mime, self::RASTER_MIMES, true ) ) {
            return [
                'file'            => $file,
                'temporary_paths' => [],
                'processed'       => false,
            ];
        }

        $needs_resize = max( $width, $height ) > self::MAX_LONG_EDGE;

        if ( 'image/webp' === $mime && ! $needs_resize ) {
            return [
                'file'            => $file,
                'temporary_paths' => [],
                'processed'       => false,
            ];
        }

        $temporary_paths = [];

        if ( 'image/jpeg' === $mime && $this->webp_is_supported() ) {
            $candidate = $this->transform_image( $path, 'image/webp', $needs_resize );

            if ( is_wp_error( $candidate ) ) {
                if ( ! $needs_resize ) {
                    return [
                        'file'            => $file,
                        'temporary_paths' => [],
                        'processed'       => false,
                    ];
                }
            } else {
                $temporary_paths[] = $candidate['path'];

                if ( $this->candidate_has_useful_savings( $size, $candidate['size'] ) || $needs_resize ) {
                    $prepared_file = $this->file_from_candidate( $file, $candidate, $context );

                    if ( is_wp_error( $prepared_file ) ) {
                        foreach ( $temporary_paths as $temporary_path ) {
                            $this->delete_file( $temporary_path );
                        }

                        return $prepared_file;
                    }

                    return [
                        'file'            => $prepared_file,
                        'temporary_paths' => $temporary_paths,
                        'processed'       => true,
                    ];
                }
            }
        }

        if ( ! $needs_resize ) {
            return [
                'file'            => $file,
                'temporary_paths' => $temporary_paths,
                'processed'       => false,
            ];
        }

        $candidate = $this->transform_image( $path, $mime, true );

        if ( is_wp_error( $candidate ) ) {
            foreach ( $temporary_paths as $temporary_path ) {
                $this->delete_file( $temporary_path );
            }

            return new WP_Error( 'sultana_admin_image_processing_failed', __( 'No se pudo optimizar la imagen. Intenta con otra imagen.', 'sultana-admin' ) );
        }

        $temporary_paths[] = $candidate['path'];
        $prepared_file = $this->file_from_candidate( $file, $candidate, $context );

        if ( is_wp_error( $prepared_file ) ) {
            foreach ( $temporary_paths as $temporary_path ) {
                $this->delete_file( $temporary_path );
            }

            return $prepared_file;
        }

        return [
            'file'            => $prepared_file,
            'temporary_paths' => $temporary_paths,
            'processed'       => true,
        ];
    }

    /**
     * @param array<int,string> $paths
     */
    public function cleanup_temporary_paths( array $paths ): void
    {
        foreach ( $paths as $path ) {
            $this->delete_file( (string) $path );
        }
    }

    /**
     * @return array{path:string,mime:string,extension:string,size:int}|WP_Error
     */
    private function transform_image( string $source_path, string $target_mime, bool $resize )
    {
        if ( ! function_exists( 'wp_get_image_editor' ) ) {
            return new WP_Error( 'sultana_admin_image_editor_missing', __( 'El editor de imagenes no esta disponible.', 'sultana-admin' ) );
        }

        $editor = wp_get_image_editor( $source_path );

        if ( is_wp_error( $editor ) ) {
            return $editor;
        }

        if ( method_exists( $editor, 'set_quality' ) ) {
            $editor->set_quality( self::WEBP_QUALITY );
        }

        if ( $resize ) {
            $resized = $editor->resize( self::MAX_LONG_EDGE, self::MAX_LONG_EDGE, false );

            if ( is_wp_error( $resized ) ) {
                return $resized;
            }
        }

        $extension = $this->extension_for_mime( $target_mime );
        $path      = $this->temporary_path( $extension );

        if ( is_wp_error( $path ) ) {
            return $path;
        }

        $saved = $editor->save( $path, $target_mime );

        if ( is_wp_error( $saved ) ) {
            $this->delete_file( $path );
            return $saved;
        }

        $saved_path = isset( $saved['path'] ) ? (string) $saved['path'] : $path;
        $saved_size = is_readable( $saved_path ) ? (int) filesize( $saved_path ) : 0;

        if ( $saved_size <= 0 ) {
            $this->delete_file( $saved_path );
            return new WP_Error( 'sultana_admin_processed_image_empty', __( 'No se pudo optimizar la imagen.', 'sultana-admin' ) );
        }

        return [
            'path'      => $saved_path,
            'mime'      => $target_mime,
            'extension' => $extension,
            'size'      => $saved_size,
        ];
    }

    /**
     * @param array<string,mixed> $file
     * @param array{path:string,mime:string,extension:string,size:int} $candidate
     * @param array<string,mixed> $context
     * @return array<string,mixed>|WP_Error
     */
    private function file_from_candidate( array $file, array $candidate, array $context )
    {
        $original_path = isset( $file['tmp_name'] ) ? (string) $file['tmp_name'] : '';

        if ( '' === $original_path || ! @copy( $candidate['path'], $original_path ) ) {
            return new WP_Error( 'sultana_admin_processed_image_copy_failed', __( 'No se pudo preparar la imagen optimizada.', 'sultana-admin' ) );
        }

        $file['type']     = $candidate['mime'];
        $file['size']     = $candidate['size'];
        $file['name']     = $this->contextual_filename( $context, $candidate['mime'], (string) ( $file['name'] ?? '' ) );

        return $file;
    }

    /**
     * @param array<string,mixed> $context
     */
    private function contextual_filename( array $context, string $mime, string $original_name ): string
    {
        $title = isset( $context['product_title'] ) ? sanitize_text_field( (string) $context['product_title'] ) : '';
        $base  = sanitize_title( $title );

        if ( '' === $base ) {
            $base = 'producto-imagen';
        }

        $index = isset( $context['image_index'] ) ? absint( $context['image_index'] ) : 0;

        if ( $index > 0 ) {
            $base .= '-' . str_pad( (string) min( $index, 99 ), 2, '0', STR_PAD_LEFT );
        }

        $extension = $this->extension_for_mime( $mime );

        if ( '' === $extension ) {
            $extension = pathinfo( $original_name, PATHINFO_EXTENSION );
            $extension = sanitize_file_name( (string) $extension );
        }

        return sanitize_file_name( $base . ( '' !== $extension ? '.' . $extension : '' ) );
    }

    private function candidate_has_useful_savings( int $original_size, int $candidate_size ): bool
    {
        if ( $candidate_size <= 0 || $candidate_size >= $original_size ) {
            return false;
        }

        return ( ( $original_size - $candidate_size ) / $original_size ) >= self::MIN_WEBP_SAVINGS_RATIO;
    }

    private function webp_is_supported(): bool
    {
        return function_exists( 'wp_image_editor_supports' )
            && wp_image_editor_supports(
                [
                    'mime_type' => 'image/webp',
                ]
            );
    }

    private function temporary_path( string $extension )
    {
        $directory = function_exists( 'get_temp_dir' ) ? get_temp_dir() : sys_get_temp_dir();
        $base_path = tempnam( $directory, 'sultana-product-image-' );

        if ( false === $base_path ) {
            return new WP_Error( 'sultana_admin_temp_file_failed', __( 'No se pudo preparar la imagen temporal.', 'sultana-admin' ) );
        }

        $path = $base_path . '.' . $extension;

        if ( ! @rename( $base_path, $path ) ) {
            $this->delete_file( $base_path );
            return new WP_Error( 'sultana_admin_temp_file_failed', __( 'No se pudo preparar la imagen temporal.', 'sultana-admin' ) );
        }

        return $path;
    }

    private function extension_for_mime( string $mime ): string
    {
        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
        ];

        return $extensions[ $mime ] ?? '';
    }

    private function delete_file( string $path ): void
    {
        if ( '' !== $path && is_file( $path ) ) {
            @unlink( $path );
        }
    }
}

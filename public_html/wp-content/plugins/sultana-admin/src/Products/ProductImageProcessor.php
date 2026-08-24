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
    private const EXIF_PROFILE = 'exif';
    private const WEBP_CHUNK_EXIF = 'EXIF';
    private const WEBP_CHUNK_VP8X = 'VP8X';
    private const WEBP_VP8X_EXIF_FLAG = 0x08;

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

        $needs_resize    = max( $width, $height ) > self::MAX_LONG_EDGE;
        $temporary_paths = [];

        if ( 'image/webp' === $mime && ! $needs_resize ) {
            $cleaned_file = $this->prepare_exif_cleaned_original_file( $file, $mime, $context, $temporary_paths );

            if ( is_array( $cleaned_file ) ) {
                return [
                    'file'            => $cleaned_file,
                    'temporary_paths' => $temporary_paths,
                    'processed'       => true,
                ];
            }

            return [
                'file'            => $file,
                'temporary_paths' => $temporary_paths,
                'processed'       => false,
            ];
        }

        if ( 'image/jpeg' === $mime && $this->webp_is_supported() ) {
            $candidate = $this->transform_image( $path, 'image/webp', $needs_resize, $mime );

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
            $cleaned_file = $this->prepare_exif_cleaned_original_file( $file, $mime, $context, $temporary_paths );

            if ( is_array( $cleaned_file ) ) {
                return [
                    'file'            => $cleaned_file,
                    'temporary_paths' => $temporary_paths,
                    'processed'       => true,
                ];
            }

            return [
                'file'            => $file,
                'temporary_paths' => $temporary_paths,
                'processed'       => false,
            ];
        }

        $candidate = $this->transform_image( $path, $mime, true, $mime );

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
    private function transform_image( string $source_path, string $target_mime, bool $resize, string $source_mime )
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

        if ( 'image/jpeg' === $source_mime && method_exists( $editor, 'maybe_exif_rotate' ) ) {
            $rotated = $editor->maybe_exif_rotate();

            if ( is_wp_error( $rotated ) ) {
                return $rotated;
            }
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

        if ( 'image/webp' === $target_mime ) {
            $cleaned_path = $this->prepare_webp_exif_stripped_candidate( $saved_path, 'image/webp' !== $source_mime );

            if ( is_string( $cleaned_path ) ) {
                $this->delete_file( $saved_path );
                $saved_path = $cleaned_path;
            }
        } elseif ( 'image/jpeg' === $target_mime ) {
            $this->remove_exif_profile_in_place( $saved_path );
        }

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
     * @param array<string,mixed> $context
     * @param array<int,string>   $temporary_paths
     * @return array<string,mixed>|false
     */
    private function prepare_exif_cleaned_original_file( array $file, string $mime, array $context, array &$temporary_paths )
    {
        $path = isset( $file['tmp_name'] ) ? (string) $file['tmp_name'] : '';

        if ( '' === $path || ! is_readable( $path ) ) {
            return false;
        }

        if ( 'image/jpeg' === $mime ) {
            if ( ! $this->has_exif_profile( $path ) ) {
                return false;
            }

            $candidate = $this->transform_image( $path, $mime, false, $mime );

            if ( is_wp_error( $candidate ) ) {
                return false;
            }

            $temporary_paths[] = $candidate['path'];
            $prepared_file     = $this->file_from_candidate( $file, $candidate, $context );

            return is_wp_error( $prepared_file ) ? false : $prepared_file;
        }

        if ( 'image/webp' !== $mime ) {
            return false;
        }

        $candidate_path = $this->prepare_webp_exif_stripped_candidate( $path, false );

        if ( ! is_string( $candidate_path ) ) {
            return false;
        }

        $candidate_size = is_readable( $candidate_path ) ? (int) filesize( $candidate_path ) : 0;

        if ( $candidate_size <= 0 ) {
            $this->delete_file( $candidate_path );
            return false;
        }

        $temporary_paths[] = $candidate_path;
        $prepared_file     = $this->file_from_candidate(
            $file,
            [
                'path'      => $candidate_path,
                'mime'      => $mime,
                'extension' => $this->extension_for_mime( $mime ),
                'size'      => $candidate_size,
            ],
            $context
        );

        return is_wp_error( $prepared_file ) ? false : $prepared_file;
    }

    private function prepare_webp_exif_stripped_candidate( string $path, bool $orientation_is_normalized )
    {
        if ( '' === $path || ! is_readable( $path ) ) {
            return false;
        }

        $contents = file_get_contents( $path );

        if ( false === $contents ) {
            return false;
        }

        $stripped = $this->strip_webp_exif_chunk( $contents, $orientation_is_normalized );

        if ( false === $stripped || $stripped === $contents ) {
            return false;
        }

        $candidate_path = $this->temporary_path( 'webp' );

        if ( is_wp_error( $candidate_path ) ) {
            return false;
        }

        $bytes_written = file_put_contents( $candidate_path, $stripped, LOCK_EX );

        if ( strlen( $stripped ) !== $bytes_written ) {
            $this->delete_file( $candidate_path );
            return false;
        }

        return $candidate_path;
    }

    /**
     * Removes only WebP EXIF chunks. Visual chunks and metadata other than EXIF
     * are copied byte for byte; VP8X only has its EXIF feature flag cleared.
     *
     * @return string|false
     */
    private function strip_webp_exif_chunk( string $contents, bool $orientation_is_normalized )
    {
        $length = strlen( $contents );

        if ( $length < 12 || 'RIFF' !== substr( $contents, 0, 4 ) || 'WEBP' !== substr( $contents, 8, 4 ) ) {
            return false;
        }

        $riff_size = $this->read_little_endian_uint32( $contents, 4 );

        if ( false === $riff_size || $riff_size < 4 || $riff_size + 8 !== $length ) {
            return false;
        }

        $offset       = 12;
        $chunks       = [];
        $removed_exif = false;

        while ( $offset < $length ) {
            if ( $length - $offset < 8 ) {
                return false;
            }

            $fourcc       = substr( $contents, $offset, 4 );
            $payload_size = $this->read_little_endian_uint32( $contents, $offset + 4 );

            if ( false === $payload_size ) {
                return false;
            }

            $payload_offset = $offset + 8;
            $payload_end    = $payload_offset + $payload_size;

            if ( $payload_end < $payload_offset || $payload_end > $length ) {
                return false;
            }

            $padded_end = $payload_end + ( $payload_size % 2 );

            if ( $padded_end < $payload_end || $padded_end > $length ) {
                return false;
            }

            if ( 1 === $payload_size % 2 && "\0" !== $contents[ $payload_end ] ) {
                return false;
            }

            if ( self::WEBP_CHUNK_EXIF === $fourcc ) {
                if ( ! $orientation_is_normalized ) {
                    $orientation_requires_transform = $this->webp_exif_orientation_requires_transform(
                        substr( $contents, $payload_offset, $payload_size )
                    );

                    if ( null === $orientation_requires_transform || $orientation_requires_transform ) {
                        return false;
                    }
                }

                $removed_exif = true;
            } else {
                $chunks[] = [
                    'fourcc' => $fourcc,
                    'bytes'  => substr( $contents, $offset, $padded_end - $offset ),
                ];
            }

            $offset = $padded_end;
        }

        if ( ! $removed_exif ) {
            return false;
        }

        $body = 'WEBP';

        foreach ( $chunks as $chunk ) {
            if ( self::WEBP_CHUNK_VP8X === $chunk['fourcc'] ) {
                $chunk['bytes'] = $this->clear_vp8x_exif_flag( $chunk['bytes'] );

                if ( false === $chunk['bytes'] ) {
                    return false;
                }
            }

            $body .= $chunk['bytes'];
        }

        $new_riff_size = strlen( $body );

        if ( $new_riff_size > 0xffffffff ) {
            return false;
        }

        return 'RIFF' . $this->pack_little_endian_uint32( $new_riff_size ) . $body;
    }

    /**
     * @return string|false
     */
    private function clear_vp8x_exif_flag( string $chunk )
    {
        if ( strlen( $chunk ) < 18 || self::WEBP_CHUNK_VP8X !== substr( $chunk, 0, 4 ) ) {
            return false;
        }

        $payload_size = $this->read_little_endian_uint32( $chunk, 4 );

        if ( 10 !== $payload_size || strlen( $chunk ) < 18 + ( $payload_size % 2 ) ) {
            return false;
        }

        $chunk[8] = chr( ord( $chunk[8] ) & ~self::WEBP_VP8X_EXIF_FLAG );

        return $chunk;
    }

    /**
     * @return bool|null Null means the EXIF orientation could not be read safely.
     */
    private function webp_exif_orientation_requires_transform( string $exif ): ?bool
    {
        if ( 0 === strncmp( $exif, "Exif\0\0", 6 ) ) {
            $exif = substr( $exif, 6 );
        }

        if ( strlen( $exif ) < 8 ) {
            return null;
        }

        $byte_order = substr( $exif, 0, 2 );

        if ( 'II' === $byte_order ) {
            $little_endian = true;
        } elseif ( 'MM' === $byte_order ) {
            $little_endian = false;
        } else {
            return null;
        }

        if ( 42 !== $this->read_tiff_uint16( $exif, 2, $little_endian ) ) {
            return null;
        }

        $ifd_offset = $this->read_tiff_uint32( $exif, 4, $little_endian );

        if ( false === $ifd_offset || $ifd_offset < 8 || $ifd_offset + 2 > strlen( $exif ) ) {
            return null;
        }

        $entry_count = $this->read_tiff_uint16( $exif, $ifd_offset, $little_endian );

        if ( false === $entry_count ) {
            return null;
        }

        $entries_offset = $ifd_offset + 2;
        $entries_size   = $entry_count * 12;

        if ( $entries_size < 0 || $entries_offset + $entries_size > strlen( $exif ) ) {
            return null;
        }

        for ( $index = 0; $index < $entry_count; $index++ ) {
            $entry_offset = $entries_offset + ( $index * 12 );
            $tag          = $this->read_tiff_uint16( $exif, $entry_offset, $little_endian );

            if ( 0x0112 !== $tag ) {
                continue;
            }

            $type  = $this->read_tiff_uint16( $exif, $entry_offset + 2, $little_endian );
            $count = $this->read_tiff_uint32( $exif, $entry_offset + 4, $little_endian );

            if ( 3 !== $type || 1 !== $count ) {
                return null;
            }

            $orientation = $this->read_tiff_uint16( $exif, $entry_offset + 8, $little_endian );

            if ( false === $orientation || $orientation < 1 || $orientation > 8 ) {
                return null;
            }

            return 1 !== $orientation;
        }

        return false;
    }

    /**
     * @return int|false
     */
    private function read_little_endian_uint32( string $bytes, int $offset )
    {
        if ( $offset < 0 || $offset + 4 > strlen( $bytes ) ) {
            return false;
        }

        $value = unpack( 'V', substr( $bytes, $offset, 4 ) );

        return is_array( $value ) ? (int) $value[1] : false;
    }

    private function pack_little_endian_uint32( int $value ): string
    {
        return pack( 'V', $value );
    }

    /**
     * @return int|false
     */
    private function read_tiff_uint16( string $bytes, int $offset, bool $little_endian )
    {
        if ( $offset < 0 || $offset + 2 > strlen( $bytes ) ) {
            return false;
        }

        $value = unpack( $little_endian ? 'v' : 'n', substr( $bytes, $offset, 2 ) );

        return is_array( $value ) ? (int) $value[1] : false;
    }

    /**
     * @return int|false
     */
    private function read_tiff_uint32( string $bytes, int $offset, bool $little_endian )
    {
        if ( $offset < 0 || $offset + 4 > strlen( $bytes ) ) {
            return false;
        }

        $value = unpack( $little_endian ? 'V' : 'N', substr( $bytes, $offset, 4 ) );

        return is_array( $value ) ? (int) $value[1] : false;
    }

    private function has_exif_profile( string $path ): bool
    {
        return $this->with_imagick_image(
            $path,
            static function ( \Imagick $image ): bool {
                foreach ( $image->getImageProfiles( '*', true ) as $profile_name => $profile ) {
                    if ( self::EXIF_PROFILE === strtolower( (string) $profile_name ) && '' !== (string) $profile ) {
                        return true;
                    }
                }

                return false;
            }
        );
    }

    private function remove_exif_profile_in_place( string $path ): bool
    {
        return $this->with_imagick_image(
            $path,
            static function ( \Imagick $image ) use ( $path ): bool {
                if ( method_exists( $image, 'getNumberImages' ) && $image->getNumberImages() > 1 ) {
                    return false;
                }

                $removed = false;

                foreach ( array_keys( $image->getImageProfiles( '*', true ) ) as $profile_name ) {
                    if ( self::EXIF_PROFILE !== strtolower( (string) $profile_name ) ) {
                        continue;
                    }

                    $image->removeImageProfile( (string) $profile_name );
                    $removed = true;
                }

                if ( ! $removed ) {
                    return false;
                }

                return $image->writeImage( $path );
            }
        );
    }

    /**
     * @template T
     *
     * @param callable(\Imagick):T $callback
     * @return T|false
     */
    private function with_imagick_image( string $path, callable $callback )
    {
        if (
            '' === $path
            || ! is_readable( $path )
            || ! extension_loaded( 'imagick' )
            || ! class_exists( '\Imagick' )
        ) {
            return false;
        }

        try {
            $image = new \Imagick( $path );

            if ( method_exists( $image, 'setIteratorIndex' ) ) {
                $image->setIteratorIndex( 0 );
            }

            return $callback( $image );
        } catch ( \Throwable $exception ) {
            return false;
        } finally {
            if ( isset( $image ) && $image instanceof \Imagick ) {
                $image->clear();
                $image->destroy();
            }
        }
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

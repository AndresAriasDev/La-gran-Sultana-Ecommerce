<?php

namespace Sultana\CommerceCore\Modules\Accounts;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ProfileAvatar
{
    public const META_KEY = '_scc_profile_avatar';
    private const AVATAR_DIRECTORY = 'sultana-commerce/avatars/';
    private const LEGACY_AVATAR_DIRECTORIES = [
        'variedadesexpress/avatars/',
    ];
    private const NONCE_ACTION = 'scc_profile_avatar';
    private const MAX_FILE_SIZE = 2097152;
    private const AVATAR_SIZE = 512;

    public static function register(): void
    {
        add_action( 'wp_ajax_scc_profile_avatar_upload', [ self::class, 'upload_avatar_ajax' ] );
        add_filter( 'get_avatar_data', [ self::class, 'filter_avatar_data' ], 10, 2 );
    }

    public static function upload_avatar_ajax(): void
    {
        $nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );

        if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
            self::send_error( __( 'No pudimos validar la solicitud. Actualiza la pagina e intenta de nuevo.', 'sultana-commerce-core' ), 403 );
        }

        if ( ! is_user_logged_in() ) {
            self::send_error( __( 'Inicia sesion para cambiar tu foto de perfil.', 'sultana-commerce-core' ), 401 );
        }

        $user_id = get_current_user_id();
        $file    = $_FILES['avatar'] ?? null;

        if ( ! is_array( $file ) ) {
            self::send_error( __( 'Selecciona una imagen para subir.', 'sultana-commerce-core' ) );
        }

        if ( ! empty( $file['error'] ) ) {
            self::send_error( self::get_upload_error_message( (int) $file['error'] ) );
        }

        $tmp_name  = isset( $file['tmp_name'] ) ? (string) $file['tmp_name'] : '';
        $file_name = isset( $file['name'] ) ? sanitize_file_name( (string) $file['name'] ) : '';
        $file_size = isset( $file['size'] ) ? absint( $file['size'] ) : 0;

        if ( '' === $tmp_name || ! is_uploaded_file( $tmp_name ) ) {
            self::send_error( __( 'No pudimos leer la imagen subida.', 'sultana-commerce-core' ) );
        }

        if ( $file_size <= 0 || $file_size > self::MAX_FILE_SIZE ) {
            self::send_error( __( 'La imagen debe pesar como maximo 2 MB.', 'sultana-commerce-core' ) );
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $allowed_mimes = self::get_allowed_mimes();
        $file_type     = wp_check_filetype_and_ext( $tmp_name, $file_name, $allowed_mimes );
        $mime_type     = sanitize_mime_type( (string) ( $file_type['type'] ?? '' ) );

        if ( empty( $file_type['ext'] ) || '' === $mime_type || ! in_array( $mime_type, $allowed_mimes, true ) ) {
            self::send_error( __( 'Sube una imagen JPG, PNG o WebP valida.', 'sultana-commerce-core' ) );
        }

        $editor = wp_get_image_editor( $tmp_name );

        if ( is_wp_error( $editor ) ) {
            self::send_error( __( 'No pudimos procesar esa imagen. Proba con otra foto.', 'sultana-commerce-core' ) );
        }

        if ( is_callable( [ $editor, 'set_quality' ] ) ) {
            $editor->set_quality( 86 );
        }

        $resize_result = $editor->resize( self::AVATAR_SIZE, self::AVATAR_SIZE, true );

        if ( is_wp_error( $resize_result ) ) {
            self::send_error( __( 'No pudimos ajustar el tamano de la imagen.', 'sultana-commerce-core' ) );
        }

        $uploads = wp_upload_dir();

        if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
            self::send_error( __( 'No pudimos acceder a la carpeta de subidas.', 'sultana-commerce-core' ), 500 );
        }

        $target_dir = wp_normalize_path( trailingslashit( $uploads['basedir'] ) . self::AVATAR_DIRECTORY );

        if ( ! wp_mkdir_p( $target_dir ) ) {
            self::send_error( __( 'No pudimos preparar la carpeta de avatares.', 'sultana-commerce-core' ), 500 );
        }

        $target_mime = self::get_supported_output_mime( $mime_type );
        $target_ext  = self::get_extension_for_mime( $target_mime );
        $hash        = strtolower( substr( wp_generate_password( 16, false, false ), 0, 12 ) );
        $file_base   = 'user-' . $user_id . '-' . $hash;
        $file_path   = $target_dir . $file_base . '.' . $target_ext;
        $saved       = $editor->save( $file_path, $target_mime );

        if ( is_wp_error( $saved ) || empty( $saved['path'] ) || ! file_exists( $saved['path'] ) ) {
            self::send_error( __( 'No pudimos guardar la nueva foto de perfil.', 'sultana-commerce-core' ), 500 );
        }

        $relative_path = self::AVATAR_DIRECTORY . basename( (string) $saved['path'] );
        $new_meta      = [
            'relative_path' => $relative_path,
            'mime'          => sanitize_mime_type( (string) ( $saved['mime-type'] ?? $target_mime ) ),
            'width'         => absint( $saved['width'] ?? self::AVATAR_SIZE ),
            'height'        => absint( $saved['height'] ?? self::AVATAR_SIZE ),
            'updated_at'    => time(),
        ];
        $old_meta      = self::get_avatar_meta( $user_id );

        update_user_meta( $user_id, self::META_KEY, $new_meta );

        if ( ! empty( $old_meta['relative_path'] ) && $old_meta['relative_path'] !== $new_meta['relative_path'] ) {
            self::delete_avatar_file( $old_meta );
        }

        $avatar_url = self::get_custom_avatar_url( $user_id );

        wp_send_json_success(
            [
                'message'     => __( 'Foto de perfil actualizada.', 'sultana-commerce-core' ),
                'avatar_url'  => esc_url_raw( $avatar_url ),
                // Deprecated legacy response. Consumers should build their own markup from avatar_url.
                'avatar_html' => get_avatar(
                    $user_id,
                    96,
                    '',
                    '',
                    [
                        'class' => 'scc-profile-avatar',
                    ]
                ),
            ]
        );
    }

    public static function get_avatar_meta( int $user_id ): array
    {
        if ( $user_id <= 0 ) {
            return [];
        }

        $meta = get_user_meta( $user_id, self::META_KEY, true );

        if ( ! is_array( $meta ) ) {
            return [];
        }

        $relative_path = self::sanitize_relative_path( (string) ( $meta['relative_path'] ?? '' ) );

        if ( '' === $relative_path ) {
            return [];
        }

        return [
            'relative_path' => $relative_path,
            'mime'          => sanitize_mime_type( (string) ( $meta['mime'] ?? '' ) ),
            'width'         => absint( $meta['width'] ?? 0 ),
            'height'        => absint( $meta['height'] ?? 0 ),
            'updated_at'    => absint( $meta['updated_at'] ?? 0 ),
        ];
    }

    public static function get_custom_avatar_url( int $user_id ): string
    {
        $meta = self::get_avatar_meta( $user_id );

        if ( empty( $meta['relative_path'] ) ) {
            return '';
        }

        $uploads = wp_upload_dir();

        if ( ! empty( $uploads['error'] ) || empty( $uploads['baseurl'] ) ) {
            return '';
        }

        return trailingslashit( $uploads['baseurl'] ) . $meta['relative_path'];
    }

    public static function has_custom_avatar( int $user_id ): bool
    {
        return '' !== self::get_custom_avatar_url( $user_id );
    }

    public static function filter_avatar_data( array $args, $id_or_email ): array
    {
        $user_id = self::resolve_user_id( $id_or_email );

        if ( $user_id <= 0 ) {
            return $args;
        }

        $avatar_url = self::get_custom_avatar_url( $user_id );

        if ( '' === $avatar_url ) {
            return $args;
        }

        $args['url']          = $avatar_url;
        $args['found_avatar'] = true;

        return $args;
    }

    private static function sanitize_relative_path( string $relative_path ): string
    {
        $relative_path = wp_normalize_path( $relative_path );
        $relative_path = ltrim( $relative_path, '/' );

        if ( '' === $relative_path || self::has_unsafe_path_segments( $relative_path ) || ! self::is_allowed_avatar_relative_path( $relative_path ) || self::is_directory_path( $relative_path ) ) {
            return '';
        }

        return sanitize_text_field( $relative_path );
    }

    private static function get_allowed_mimes(): array
    {
        return [
            'jpg|jpeg' => 'image/jpeg',
            'png'      => 'image/png',
            'webp'     => 'image/webp',
        ];
    }

    private static function get_supported_output_mime( string $source_mime ): string
    {
        if (
            function_exists( 'wp_image_editor_supports' )
            && wp_image_editor_supports(
                [
                    'mime_type' => 'image/webp',
                ]
            )
        ) {
            return 'image/webp';
        }

        if ( 'image/png' === $source_mime ) {
            return 'image/png';
        }

        return 'image/jpeg';
    }

    private static function get_extension_for_mime( string $mime_type ): string
    {
        return match ( $mime_type ) {
            'image/png'  => 'png',
            'image/webp' => 'webp',
            default      => 'jpg',
        };
    }

    private static function delete_avatar_file( array $meta ): void
    {
        $path = self::get_absolute_avatar_path( (string) ( $meta['relative_path'] ?? '' ) );

        if ( '' !== $path && file_exists( $path ) && is_file( $path ) ) {
            wp_delete_file( $path );
        }
    }

    private static function get_absolute_avatar_path( string $relative_path ): string
    {
        $relative_path = self::sanitize_relative_path( $relative_path );

        if ( '' === $relative_path ) {
            return '';
        }

        $uploads = wp_upload_dir();

        if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
            return '';
        }

        $uploads_base = wp_normalize_path( trailingslashit( $uploads['basedir'] ) );
        $path         = wp_normalize_path( $uploads_base . $relative_path );

        foreach ( self::get_allowed_avatar_directories() as $directory ) {
            $base_dir = wp_normalize_path( $uploads_base . $directory );

            if ( str_starts_with( $path, $base_dir ) ) {
                return $path;
            }
        }

        return '';
    }

    private static function get_allowed_avatar_directories(): array
    {
        return array_merge( [ self::AVATAR_DIRECTORY ], self::LEGACY_AVATAR_DIRECTORIES );
    }

    private static function is_allowed_avatar_relative_path( string $relative_path ): bool
    {
        foreach ( self::get_allowed_avatar_directories() as $directory ) {
            if ( str_starts_with( $relative_path, $directory ) ) {
                return true;
            }
        }

        return false;
    }

    private static function has_unsafe_path_segments( string $relative_path ): bool
    {
        if ( str_contains( $relative_path, "\0" ) || preg_match( '#^[a-zA-Z]:/#', $relative_path ) ) {
            return true;
        }

        $segments = explode( '/', $relative_path );

        return in_array( '..', $segments, true ) || in_array( '', array_slice( $segments, 0, -1 ), true );
    }

    private static function is_directory_path( string $relative_path ): bool
    {
        return str_ends_with( $relative_path, '/' ) || '' === basename( $relative_path );
    }

    private static function get_upload_error_message( int $error_code ): string
    {
        return match ( $error_code ) {
            UPLOAD_ERR_INI_SIZE,
            UPLOAD_ERR_FORM_SIZE => __( 'La imagen debe pesar como maximo 2 MB.', 'sultana-commerce-core' ),
            UPLOAD_ERR_NO_FILE   => __( 'Selecciona una imagen para subir.', 'sultana-commerce-core' ),
            default              => __( 'No pudimos subir la imagen. Intenta de nuevo.', 'sultana-commerce-core' ),
        };
    }

    private static function send_error( string $message, int $status_code = 400 ): void
    {
        wp_send_json_error(
            [
                'message' => $message,
            ],
            $status_code
        );
    }

    private static function resolve_user_id( $id_or_email ): int
    {
        if ( is_numeric( $id_or_email ) ) {
            return absint( $id_or_email );
        }

        if ( $id_or_email instanceof \WP_User ) {
            return absint( $id_or_email->ID );
        }

        if ( $id_or_email instanceof \WP_Post ) {
            return absint( $id_or_email->post_author );
        }

        if ( $id_or_email instanceof \WP_Comment ) {
            if ( $id_or_email->user_id > 0 ) {
                return absint( $id_or_email->user_id );
            }

            $id_or_email = $id_or_email->comment_author_email;
        }

        if ( is_string( $id_or_email ) && is_email( $id_or_email ) ) {
            $user = get_user_by( 'email', $id_or_email );

            return $user instanceof \WP_User ? absint( $user->ID ) : 0;
        }

        return 0;
    }
}

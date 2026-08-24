<?php

namespace Sultana\Admin\Products;

use Sultana\Admin\Media\MediaImageProcessor;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ProductImageProcessor
{
    /**
     * @param array<string,mixed> $file
     * @param array<string,mixed> $context
     * @return array{file:array<string,mixed>,temporary_paths:array<int,string>,processed:bool}|\WP_Error
     */
    public function prepare_for_media_handle_upload( array $file, array $context = [] )
    {
        return ( new MediaImageProcessor() )->prepare_for_media_handle_upload(
            $file,
            $context,
            MediaImageProcessor::product_profile()
        );
    }

    /**
     * @param array<int,string> $paths
     */
    public function cleanup_temporary_paths( array $paths ): void
    {
        ( new MediaImageProcessor() )->cleanup_temporary_paths( $paths );
    }
}

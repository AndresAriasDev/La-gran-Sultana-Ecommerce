<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function variedadesexpress_get_theme_version(): string
{
    return wp_get_theme()->get( 'Version' );
}

function variedadesexpress_get_icon_svg( string $icon_name, string $class_name = '' ): string
{
    $icon_name = sanitize_file_name( $icon_name );
    $file_path = get_template_directory() . '/assets/icons/' . $icon_name . '.svg';

    if ( ! file_exists( $file_path ) ) {
        return '';
    }

    $svg = file_get_contents( $file_path );

    if ( false === $svg ) {
        return '';
    }

    if ( '' !== $class_name ) {
        if ( preg_match( '/<svg\b[^>]*\bclass="/', $svg ) ) {
            $svg = preg_replace( '/(<svg\b[^>]*\bclass=")([^"]*)(")/', '$1$2 ' . esc_attr( $class_name ) . '$3', $svg, 1 );
        } else {
            $svg = preg_replace( '/<svg\b/', '<svg class="' . esc_attr( $class_name ) . '"', $svg, 1 );
        }
    }

    return wp_kses(
        $svg,
        [
            'svg'  => [
                'aria-hidden'     => true,
                'class'           => true,
                'fill'            => true,
                'height'          => true,
                'role'            => true,
                'stroke'          => true,
                'stroke-linecap'  => true,
                'stroke-linejoin' => true,
                'stroke-width'    => true,
                'viewbox'         => true,
                'width'           => true,
                'xmlns'           => true,
            ],
            'path' => [
                'd'               => true,
                'fill'            => true,
                'stroke'          => true,
                'stroke-linecap'  => true,
                'stroke-linejoin' => true,
                'stroke-width'    => true,
            ],
            'circle' => [
                'cx'              => true,
                'cy'              => true,
                'fill'            => true,
                'r'               => true,
                'stroke'          => true,
                'stroke-linecap'  => true,
                'stroke-linejoin' => true,
                'stroke-width'    => true,
            ],
            'rect' => [
                'fill'            => true,
                'height'          => true,
                'rx'              => true,
                'stroke'          => true,
                'stroke-linecap'  => true,
                'stroke-linejoin' => true,
                'stroke-width'    => true,
                'width'           => true,
                'x'               => true,
                'y'               => true,
            ],
            'line' => [
                'stroke'          => true,
                'stroke-linecap'  => true,
                'stroke-linejoin' => true,
                'stroke-width'    => true,
                'x1'              => true,
                'x2'              => true,
                'y1'              => true,
                'y2'              => true,
            ],
        ]
    );
}

function variedadesexpress_icon( string $icon_name, string $class_name = '' ): void
{
    echo variedadesexpress_get_icon_svg( $icon_name, $class_name ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

function variedadesexpress_get_product_sale_display_data( $product ): array
{
    $empty = [
        'is_on_sale' => false,
        'regular'    => '',
        'sale'       => '',
        'current'    => '',
        'percentage' => 0,
        'image_id'   => 0,
    ];

    if ( ! $product instanceof WC_Product ) {
        return $empty;
    }

    if ( ! $product->is_on_sale() ) {
        $empty['current'] = $product->is_type( 'variable' ) ? $product->get_variation_price( 'min', true ) : $product->get_price();

        return $empty;
    }

    if ( $product->is_type( 'variable' ) ) {
        $best_sale = $empty;

        foreach ( $product->get_children() as $variation_id ) {
            $variation = wc_get_product( $variation_id );

            if ( ! $variation instanceof WC_Product_Variation || ! $variation->is_on_sale() ) {
                continue;
            }

            $regular_price = $variation->get_regular_price();
            $sale_price    = $variation->get_sale_price();
            $current_price = $variation->get_price();
            $display_sale  = '' !== $sale_price ? $sale_price : $current_price;

            if ( '' === $regular_price || '' === $display_sale ) {
                continue;
            }

            $regular_price = (float) $regular_price;
            $display_sale  = (float) $display_sale;

            if ( $regular_price <= 0 || $display_sale <= 0 || $display_sale >= $regular_price ) {
                continue;
            }

            $percentage = max( 1, (int) round( ( ( $regular_price - $display_sale ) / $regular_price ) * 100 ) );

            if ( $percentage <= $best_sale['percentage'] ) {
                continue;
            }

            $best_sale = [
                'is_on_sale' => true,
                'regular'    => (string) $regular_price,
                'sale'       => (string) $display_sale,
                'current'    => (string) $display_sale,
                'percentage' => $percentage,
                'image_id'   => absint( $variation->get_image_id() ),
            ];
        }

        if ( $best_sale['is_on_sale'] ) {
            return $best_sale;
        }

        $empty['current'] = $product->get_variation_price( 'min', true );

        return $empty;
    }

    $regular_price = $product->get_regular_price();
    $sale_price    = $product->get_sale_price();
    $current_price = $product->get_price();
    $display_sale  = '' !== $sale_price ? $sale_price : $current_price;

    if ( '' === $regular_price || '' === $display_sale ) {
        $empty['current'] = $current_price;

        return $empty;
    }

    $regular_price = (float) $regular_price;
    $display_sale  = (float) $display_sale;

    if ( $regular_price <= 0 || $display_sale <= 0 || $display_sale >= $regular_price ) {
        $empty['current'] = $current_price;

        return $empty;
    }

    return [
        'is_on_sale' => true,
        'regular'    => (string) $regular_price,
        'sale'       => (string) $display_sale,
        'current'    => (string) $display_sale,
        'percentage' => max( 1, (int) round( ( ( $regular_price - $display_sale ) / $regular_price ) * 100 ) ),
        'image_id'   => absint( $product->get_image_id() ),
    ];
}

function variedadesexpress_get_product_discount_percentage( $product ): int
{
    $sale_data = variedadesexpress_get_product_sale_display_data( $product );

    return (int) $sale_data['percentage'];
}

function variedadesexpress_product_discount_badge( $product, string $class_name = 'product-discount-badge' ): void
{
    $discount_percentage = variedadesexpress_get_product_discount_percentage( $product );

    if ( $discount_percentage <= 0 ) {
        return;
    }
    ?>
    <span class="<?php echo esc_attr( $class_name ); ?>">
        <?php echo esc_html( sprintf( __( 'DES. %d%%', 'sultana-storefront' ), $discount_percentage ) ); ?>
    </span>
    <?php
}

<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$promotion_class = '\Sultana\CommerceCore\Modules\HomePromotions\HomePromotions';

if (
    ! class_exists( $promotion_class )
    || ! method_exists( $promotion_class, 'get_active_promotions' )
) {
    return;
}

if ( ! function_exists( 'variedadesexpress_home_promotion_image_data' ) ) {
    /**
     * @return array{src:string,width:int,height:int,srcset:string,sizes:string}|null
     */
    function variedadesexpress_home_promotion_image_data( int $attachment_id, string $sizes ): ?array
    {
        if ( ! $attachment_id ) {
            return null;
        }

        $src = wp_get_attachment_image_src( $attachment_id, 'full' );

        if ( ! is_array( $src ) || empty( $src[0] ) || empty( $src[1] ) || empty( $src[2] ) ) {
            return null;
        }

        return [
            'src'    => (string) $src[0],
            'width'  => absint( $src[1] ),
            'height' => absint( $src[2] ),
            'srcset' => (string) wp_get_attachment_image_srcset( $attachment_id, 'full' ),
            'sizes'  => $sizes,
        ];
    }
}

if ( ! function_exists( 'variedadesexpress_home_promotion_picture' ) ) {
    function variedadesexpress_home_promotion_picture( array $promotion, int $index ): string
    {
        $desktop_image_id = absint( $promotion['desktop_image_id'] ?? 0 );
        $mobile_image_id  = absint( $promotion['mobile_image_id'] ?? 0 );
        $fallback_id      = $desktop_image_id ?: $mobile_image_id;
        $fallback_sizes   = $desktop_image_id ? '(max-width: 900px) calc(100vw - 32px), 1180px' : 'calc(100vw - 32px)';
        $fallback         = variedadesexpress_home_promotion_image_data( $fallback_id, $fallback_sizes );

        if ( null === $fallback ) {
            return '';
        }

        $mobile = null;

        if ( $desktop_image_id && $mobile_image_id ) {
            $mobile = variedadesexpress_home_promotion_image_data( $mobile_image_id, '(max-width: 560px) calc(100vw - 32px), 560px' );
        }

        $image_attrs = [
            'class'    => 'home-promotion-banner__image',
            'src'      => $fallback['src'],
            'width'    => (string) $fallback['width'],
            'height'   => (string) $fallback['height'],
            'alt'      => variedadesexpress_home_promotion_alt_text( $promotion, $fallback_id ),
            'loading'  => 0 === $index ? 'eager' : 'lazy',
            'decoding' => 'async',
            'sizes'    => $fallback['sizes'],
        ];

        if ( '' !== $fallback['srcset'] ) {
            $image_attrs['srcset'] = $fallback['srcset'];
        }

        if ( 0 === $index ) {
            $image_attrs['fetchpriority'] = 'high';
        }

        ob_start();
        ?>
        <picture class="home-promotion-banner__picture">
            <?php if ( null !== $mobile ) : ?>
                <source
                    media="(max-width: 560px)"
                    srcset="<?php echo esc_attr( '' !== $mobile['srcset'] ? $mobile['srcset'] : $mobile['src'] ); ?>"
                    sizes="<?php echo esc_attr( $mobile['sizes'] ); ?>"
                    width="<?php echo esc_attr( (string) $mobile['width'] ); ?>"
                    height="<?php echo esc_attr( (string) $mobile['height'] ); ?>"
                >
            <?php endif; ?>
            <img
                <?php foreach ( $image_attrs as $attr => $value ) : ?>
                    <?php echo esc_attr( $attr ); ?>="<?php echo 'src' === $attr ? esc_url( $value ) : esc_attr( $value ); ?>"
                <?php endforeach; ?>
            >
        </picture>
        <?php

        return (string) ob_get_clean();
    }
}

if ( ! function_exists( 'variedadesexpress_home_promotion_alt_text' ) ) {
    function variedadesexpress_home_promotion_alt_text( array $promotion, int $fallback_id ): string
    {
        $promotion_alt = trim( (string) ( $promotion['alt_text'] ?? '' ) );

        if ( '' !== $promotion_alt ) {
            return $promotion_alt;
        }

        $attachment_alt = trim( (string) get_post_meta( $fallback_id, '_wp_attachment_image_alt', true ) );

        return $attachment_alt;
    }
}

$promotions = $promotion_class::get_active_promotions();

if ( ! is_array( $promotions ) || empty( $promotions ) ) {
    return;
}

$promotions = array_values(
    array_filter(
        $promotions,
        static function ( $promotion ): bool {
            if ( ! is_array( $promotion ) ) {
                return false;
            }

            return absint( $promotion['desktop_image_id'] ?? 0 ) > 0 || absint( $promotion['mobile_image_id'] ?? 0 ) > 0;
        }
    )
);

if ( empty( $promotions ) ) {
    return;
}

$rendered_promotions = [];

foreach ( $promotions as $index => $promotion ) {
    $picture = variedadesexpress_home_promotion_picture( $promotion, $index );

    if ( '' === trim( $picture ) ) {
        continue;
    }

    $promotion['picture'] = $picture;
    $rendered_promotions[] = $promotion;
}

if ( empty( $rendered_promotions ) ) {
    return;
}

$promotions    = $rendered_promotions;
$has_multiple  = count( $promotions ) > 1;
?>

<section class="home-promotion-carousel <?php echo esc_attr( $has_multiple ? 'has-multiple-promotions' : 'has-single-promotion' ); ?>" aria-label="<?php esc_attr_e( 'Promociones destacadas', 'sultana-storefront' ); ?>">
    <div class="home-promotion-carousel__viewport">
        <?php if ( $has_multiple ) : ?>
            <button class="home-promotion-carousel__arrow home-promotion-carousel__arrow--prev" type="button" aria-label="<?php esc_attr_e( 'Ver promocion anterior', 'sultana-storefront' ); ?>" disabled>
                <img src="<?php echo esc_url( get_template_directory_uri() . '/assets/icons/chevron-left.svg' ); ?>" alt="" width="22" height="22" aria-hidden="true">
            </button>
        <?php endif; ?>

        <div class="home-promotion-carousel__track" tabindex="<?php echo esc_attr( $has_multiple ? '0' : '-1' ); ?>" data-home-promotion-track>
            <?php foreach ( $promotions as $index => $promotion ) : ?>
                <?php
                $slide_id = 'home-promotion-slide-' . absint( $promotion['id'] ?? $index );
                $url      = esc_url( (string) ( $promotion['url'] ?? '' ) );
                ?>

                <article
                    id="<?php echo esc_attr( $slide_id ); ?>"
                    class="home-promotion-banner"
                    data-home-promotion-slide
                >
                    <?php if ( '' !== $url ) : ?>
                        <a class="home-promotion-banner__link" href="<?php echo esc_url( $url ); ?>">
                            <?php echo $promotion['picture']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </a>
                    <?php else : ?>
                        <?php echo $promotion['picture']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if ( $has_multiple ) : ?>
            <button class="home-promotion-carousel__arrow home-promotion-carousel__arrow--next" type="button" aria-label="<?php esc_attr_e( 'Ver siguiente promocion', 'sultana-storefront' ); ?>">
                <img src="<?php echo esc_url( get_template_directory_uri() . '/assets/icons/chevron-right.svg' ); ?>" alt="" width="22" height="22" aria-hidden="true">
            </button>
        <?php endif; ?>
    </div>

    <?php if ( $has_multiple ) : ?>
        <div class="home-promotion-carousel__dots" aria-label="<?php esc_attr_e( 'Seleccionar promocion', 'sultana-storefront' ); ?>">
            <?php foreach ( $promotions as $index => $promotion ) : ?>
                <button
                    class="home-promotion-carousel__dot <?php echo esc_attr( 0 === $index ? 'is-active' : '' ); ?>"
                    type="button"
                    aria-label="<?php echo esc_attr( sprintf( __( 'Ver promocion %d', 'sultana-storefront' ), $index + 1 ) ); ?>"
                    aria-current="<?php echo esc_attr( 0 === $index ? 'true' : 'false' ); ?>"
                    data-home-promotion-dot="<?php echo esc_attr( $index ); ?>"
                ></button>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

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

            return '' !== trim( (string) ( $promotion['title'] ?? '' ) )
                || '' !== trim( (string) ( $promotion['subtitle'] ?? '' ) )
                || '' !== trim( (string) ( $promotion['url'] ?? '' ) )
                || absint( $promotion['image_id'] ?? 0 ) > 0;
        }
    )
);

if ( empty( $promotions ) ) {
    return;
}

$has_multiple = count( $promotions ) > 1;
?>

<section class="home-promotion-carousel <?php echo esc_attr( $has_multiple ? 'has-multiple-promotions' : 'has-single-promotion' ); ?>" aria-label="<?php esc_attr_e( 'Promociones destacadas', 'sultana-storefront' ); ?>">
    <div class="home-promotion-carousel__viewport">
        <?php if ( $has_multiple ) : ?>
            <button class="home-promotion-carousel__arrow home-promotion-carousel__arrow--prev" type="button" aria-label="<?php esc_attr_e( 'Ver promoción anterior', 'sultana-storefront' ); ?>" disabled>
                <img src="<?php echo esc_url( get_template_directory_uri() . '/assets/icons/chevron-left.svg' ); ?>" alt="" width="22" height="22" aria-hidden="true">
            </button>
        <?php endif; ?>

        <div class="home-promotion-carousel__track" tabindex="<?php echo esc_attr( $has_multiple ? '0' : '-1' ); ?>" data-home-promotion-track>
            <?php foreach ( $promotions as $index => $promotion ) : ?>
                <?php
                $title       = trim( (string) ( $promotion['title'] ?? '' ) );
                $subtitle    = trim( (string) ( $promotion['subtitle'] ?? '' ) );
                $button_url  = trim( (string) ( $promotion['url'] ?? '' ) );
                $button_text = trim( (string) ( $promotion['button_text'] ?? '' ) );
                $image_id    = absint( $promotion['image_id'] ?? 0 );
                $slide_id    = 'home-promotion-slide-' . absint( $promotion['id'] ?? $index );
                $classes     = [ 'home-promotion-banner' ];
                $requires_account_modal = false;

                if ( '' !== $button_url && ! is_user_logged_in() ) {
                    $button_path  = (string) wp_parse_url( $button_url, PHP_URL_PATH );
                    $coupons_url  = function_exists( 'wc_get_account_endpoint_url' )
                        ? wc_get_account_endpoint_url( 'cupones' )
                        : home_url( '/mi-cuenta/cupones/' );
                    $coupons_path = (string) wp_parse_url( $coupons_url, PHP_URL_PATH );

                    $requires_account_modal = trim( $button_path, '/' ) === trim( $coupons_path, '/' );
                }

                if ( ! $image_id ) {
                    $classes[] = 'home-promotion-banner--no-image';
                }

                if ( '' === $button_url || $requires_account_modal ) {
                    $classes[] = 'home-promotion-banner--no-url';
                } else {
                    $classes[] = 'home-promotion-banner--is-clickable';
                }
                ?>

                <article
                    id="<?php echo esc_attr( $slide_id ); ?>"
                    class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
                    aria-label="<?php echo esc_attr( $title ?: __( 'Promoción destacada', 'sultana-storefront' ) ); ?>"
                    <?php if ( '' !== $button_url && ! $requires_account_modal ) : ?>
                        role="link"
                        tabindex="0"
                        data-promotion-url="<?php echo esc_url( $button_url ); ?>"
                    <?php endif; ?>
                    data-home-promotion-slide
                >
                    <?php if ( $image_id ) : ?>
                        <figure class="home-promotion-banner__art">
                            <?php
                            echo wp_get_attachment_image(
                                $image_id,
                                'large',
                                false,
                                [
                                    'class'    => 'home-promotion-banner__image',
                                    'loading'  => 0 === $index ? 'eager' : 'lazy',
                                    'decoding' => 'async',
                                ]
                            );
                            ?>
                        </figure>
                    <?php endif; ?>

                    <div class="home-promotion-banner__content">
                        <?php if ( '' !== $title ) : ?>
                            <h2 class="home-promotion-banner__title">
                                <?php echo esc_html( $title ); ?>
                            </h2>
                        <?php endif; ?>

                        <?php if ( '' !== $subtitle ) : ?>
                            <p class="home-promotion-banner__subtitle">
                                <?php echo esc_html( $subtitle ); ?>
                            </p>
                        <?php endif; ?>

                        <?php if ( '' !== $button_url ) : ?>
                            <?php if ( $requires_account_modal ) : ?>
                                <button class="home-promotion-banner__button" type="button" data-modal-open="account" data-account-view="register">
                                    <?php
                                    if ( function_exists( 'variedadesexpress_get_icon_svg' ) ) {
                                        echo variedadesexpress_get_icon_svg( 'shopping-bag', 'home-promotion-banner__button-icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                    }
                                    ?>
                                    <span><?php echo esc_html( $button_text ?: __( 'Ver todo', 'sultana-storefront' ) ); ?></span>
                                </button>
                            <?php else : ?>
                                <a class="home-promotion-banner__button" href="<?php echo esc_url( $button_url ); ?>">
                                    <?php
                                    if ( function_exists( 'variedadesexpress_get_icon_svg' ) ) {
                                        echo variedadesexpress_get_icon_svg( 'shopping-bag', 'home-promotion-banner__button-icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                    }
                                    ?>
                                    <span><?php echo esc_html( $button_text ?: __( 'Ver todo', 'sultana-storefront' ) ); ?></span>
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if ( $has_multiple ) : ?>
            <button class="home-promotion-carousel__arrow home-promotion-carousel__arrow--next" type="button" aria-label="<?php esc_attr_e( 'Ver siguiente promoción', 'sultana-storefront' ); ?>">
                <img src="<?php echo esc_url( get_template_directory_uri() . '/assets/icons/chevron-right.svg' ); ?>" alt="" width="22" height="22" aria-hidden="true">
            </button>
        <?php endif; ?>
    </div>

    <?php if ( $has_multiple ) : ?>
        <div class="home-promotion-carousel__dots" aria-label="<?php esc_attr_e( 'Seleccionar promoción', 'sultana-storefront' ); ?>">
            <?php foreach ( $promotions as $index => $promotion ) : ?>
                <button
                    class="home-promotion-carousel__dot <?php echo esc_attr( 0 === $index ? 'is-active' : '' ); ?>"
                    type="button"
                    aria-label="<?php echo esc_attr( sprintf( __( 'Ver promoción %d', 'sultana-storefront' ), $index + 1 ) ); ?>"
                    aria-current="<?php echo esc_attr( 0 === $index ? 'true' : 'false' ); ?>"
                    data-home-promotion-dot="<?php echo esc_attr( $index ); ?>"
                ></button>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

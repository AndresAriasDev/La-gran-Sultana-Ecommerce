<?php
/**
 * Customer coupons endpoint.
 *
 * @package SultanaStorefront
 */

defined( 'ABSPATH' ) || exit;

$coupons = isset( $coupons ) && is_array( $coupons ) ? $coupons : [];
?>

<div class="ve-account-coupons">
    <section class="ve-account-section-title ve-account-coupons__title">
        <span aria-hidden="true"><?php variedadesexpress_icon( 'tickets', 've-account-section-title__icon' ); ?></span>
        <div>
            <span><?php esc_html_e( 'Beneficios', 'sultana-storefront' ); ?></span>
            <h1><?php esc_html_e( 'Mis cupones', 'sultana-storefront' ); ?></h1>
        </div>
    </section>

    <?php if ( empty( $coupons ) ) : ?>
        <section class="ve-account-empty ve-account-coupons__empty">
            <span class="ve-account-empty__icon" aria-hidden="true"><?php variedadesexpress_icon( 'tickets', 've-account-empty__svg' ); ?></span>
            <div>
                <h2><?php esc_html_e( 'No tenés cupones disponibles', 'sultana-storefront' ); ?></h2>
                <p><?php esc_html_e( 'Cuando tengamos descuentos disponibles para tu cuenta, aparecerán aquí.', 'sultana-storefront' ); ?></p>
            </div>
        </section>
    <?php else : ?>
        <section class="ve-account-coupons__grid" aria-label="<?php esc_attr_e( 'Cupones disponibles', 'sultana-storefront' ); ?>" data-account-coupons>
            <?php foreach ( $coupons as $coupon ) : ?>
                <?php
                $code          = (string) ( $coupon['code'] ?? '' );
                $description   = (string) ( $coupon['description'] ?? '' );
                $amount_html   = (string) ( $coupon['amount_html'] ?? '' );
                $expires       = (string) ( $coupon['expires'] ?? '' );
                $discount_text = trim( wp_strip_all_tags( $amount_html ) );

                if ( '' === $code ) {
                    continue;
                }

                $has_details = '' !== $description || '' !== $expires;
                $details_id  = 've-coupon-details-' . sanitize_html_class( md5( $code ) );

                if ( '' !== $discount_text ) {
                    $discount_text = sprintf(
                        /* translators: %s: coupon discount amount. */
                        __( '%s OFF', 'sultana-storefront' ),
                        $discount_text
                    );
                }
                ?>
                <article class="ve-account-card ve-account-coupon-card">
                    <div class="ve-account-coupon-card__content">
                        <div class="ve-account-coupon-card__header">
                            <?php if ( '' !== $discount_text ) : ?>
                                <strong class="ve-account-coupon-card__discount"><?php echo esc_html( $discount_text ); ?></strong>
                            <?php endif; ?>

                            <?php if ( $has_details ) : ?>
                                <div class="ve-account-coupon-card__info">
                                    <button
                                        class="ve-account-coupon-card__info-button"
                                        type="button"
                                        aria-label="<?php esc_attr_e( 'Ver información del cupón', 'sultana-storefront' ); ?>"
                                        aria-expanded="false"
                                        aria-controls="<?php echo esc_attr( $details_id ); ?>"
                                        data-coupon-info-toggle
                                    >
                                        <span aria-hidden="true">!</span>
                                    </button>
                                    <div id="<?php echo esc_attr( $details_id ); ?>" class="ve-account-coupon-card__popover" role="status" hidden data-coupon-info-popover>
                                        <?php if ( '' !== $description ) : ?>
                                            <p><?php echo esc_html( $description ); ?></p>
                                        <?php endif; ?>

                                        <?php if ( '' !== $expires ) : ?>
                                            <p>
                                                <span><?php esc_html_e( 'Válido hasta', 'sultana-storefront' ); ?></span>
                                                <strong><?php echo esc_html( $expires ); ?></strong>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <button
                            class="ve-account-coupon-card__copy"
                            type="button"
                            data-copy-text="<?php echo esc_attr( $code ); ?>"
                            data-copy-message="<?php esc_attr_e( 'Código copiado', 'sultana-storefront' ); ?>"
                            data-copy-prompt="<?php esc_attr_e( 'Copiá este código', 'sultana-storefront' ); ?>"
                            aria-label="<?php echo esc_attr( sprintf( __( 'Copiar código %s', 'sultana-storefront' ), $code ) ); ?>"
                        >
                            <span class="ve-account-coupon-card__code"><?php echo esc_html( $code ); ?></span>
                            <?php variedadesexpress_icon( 'copy', 've-account-coupon-card__copy-icon' ); ?>
                        </button>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
</div>

<?php
/**
 * Core fallback template for the account coupons endpoint.
 *
 * @package SultanaCommerceCore
 */

use Sultana\CommerceCore\Core\StoreBranding;

defined( 'ABSPATH' ) || exit;

$coupons = isset( $coupons ) && is_array( $coupons ) ? $coupons : [];
?>

<style>
    .scc-account-coupons {
        color: #1f2933;
    }

    .scc-account-coupons__header,
    .scc-account-coupons__empty,
    .scc-account-coupon {
        border: 1px solid #edf0f4;
        border-radius: 16px;
        background: #fff;
        box-shadow: 0 14px 36px rgba(31, 41, 51, 0.07);
    }

    .scc-account-coupons__header,
    .scc-account-coupons__empty {
        padding: clamp(1.25rem, 3vw, 2rem);
        margin-bottom: 1rem;
    }

    .scc-account-coupons__eyebrow {
        display: block;
        margin-bottom: 0.35rem;
        color: <?php echo esc_html( StoreBranding::get_primary_color() ); ?>;
        font-size: 0.78rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .scc-account-coupons h1,
    .scc-account-coupons h2,
    .scc-account-coupons p {
        margin-top: 0;
    }

    .scc-account-coupons h1 {
        margin-bottom: 0.45rem;
        font-size: clamp(1.8rem, 4vw, 2.45rem);
        line-height: 1.08;
    }

    .scc-account-coupons__header p,
    .scc-account-coupons__empty p,
    .scc-account-coupon__description,
    .scc-account-coupon__meta {
        color: #5f6c7b;
        line-height: 1.6;
    }

    .scc-account-coupons__grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(min(100%, 260px), 1fr));
        gap: 1rem;
    }

    .scc-account-coupon {
        display: flex;
        flex-direction: column;
        gap: 0.85rem;
        padding: 1rem;
    }

    .scc-account-coupon__amount {
        color: <?php echo esc_html( StoreBranding::get_primary_color() ); ?>;
        font-size: 1.45rem;
        font-weight: 800;
        line-height: 1.1;
    }

    .scc-account-coupon__code {
        display: block;
        width: 100%;
        border: 1px dashed #cfd6df;
        border-radius: 12px;
        padding: 0.8rem;
        background: #f7f8fa;
        color: #1f2933;
        font-size: 1rem;
        font-weight: 800;
        letter-spacing: 0.04em;
        overflow-wrap: anywhere;
        user-select: all;
    }

    .scc-account-coupon__label {
        font-size: 0.78rem;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: #728196;
    }
</style>

<div class="scc-account-coupons">
    <section class="scc-account-coupons__header">
        <span class="scc-account-coupons__eyebrow"><?php esc_html_e( 'Beneficios', 'sultana-commerce-core' ); ?></span>
        <h1><?php esc_html_e( 'Mis cupones', 'sultana-commerce-core' ); ?></h1>
        <p><?php esc_html_e( 'Revisá los descuentos disponibles para tu cuenta.', 'sultana-commerce-core' ); ?></p>
    </section>

    <?php if ( empty( $coupons ) ) : ?>
        <section class="scc-account-coupons__empty">
            <h2><?php esc_html_e( 'No tienes cupones disponibles.', 'sultana-commerce-core' ); ?></h2>
            <p><?php esc_html_e( 'Cuando existan descuentos disponibles para tu cuenta, aparecerán aquí.', 'sultana-commerce-core' ); ?></p>
        </section>
    <?php else : ?>
        <section class="scc-account-coupons__grid" aria-label="<?php esc_attr_e( 'Cupones disponibles', 'sultana-commerce-core' ); ?>">
            <?php foreach ( $coupons as $coupon ) : ?>
                <?php
                $code               = (string) ( $coupon['code'] ?? '' );
                $description        = (string) ( $coupon['description'] ?? '' );
                $amount_html        = (string) ( $coupon['amount_html'] ?? '' );
                $discount_type_name = (string) ( $coupon['discount_type_name'] ?? '' );
                $expires            = (string) ( $coupon['expires'] ?? '' );

                if ( '' === $code ) {
                    continue;
                }
                ?>
                <article class="scc-account-coupon">
                    <?php if ( '' !== $amount_html ) : ?>
                        <strong class="scc-account-coupon__amount">
                            <?php
                            echo wp_kses_post(
                                sprintf(
                                    /* translators: %s: coupon amount. */
                                    __( '%s OFF', 'sultana-commerce-core' ),
                                    wp_strip_all_tags( $amount_html )
                                )
                            );
                            ?>
                        </strong>
                    <?php endif; ?>

                    <div>
                        <span class="scc-account-coupon__label"><?php esc_html_e( 'Código', 'sultana-commerce-core' ); ?></span>
                        <code class="scc-account-coupon__code"><?php echo esc_html( $code ); ?></code>
                    </div>

                    <?php if ( '' !== $description ) : ?>
                        <p class="scc-account-coupon__description"><?php echo esc_html( $description ); ?></p>
                    <?php endif; ?>

                    <?php if ( '' !== $discount_type_name || '' !== $expires ) : ?>
                        <p class="scc-account-coupon__meta">
                            <?php if ( '' !== $discount_type_name ) : ?>
                                <span><?php echo esc_html( $discount_type_name ); ?></span>
                            <?php endif; ?>

                            <?php if ( '' !== $expires ) : ?>
                                <?php if ( '' !== $discount_type_name ) : ?>
                                    <br>
                                <?php endif; ?>
                                <span>
                                    <?php
                                    echo esc_html(
                                        sprintf(
                                            /* translators: %s: coupon expiration date. */
                                            __( 'Válido hasta %s', 'sultana-commerce-core' ),
                                            $expires
                                        )
                                    );
                                    ?>
                                </span>
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
</div>

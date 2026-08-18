<?php

namespace Sultana\CommerceCore\Modules\Emails;

use Sultana\CommerceCore\Core\StoreBranding;
use WC_Order;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OrderStatusEmails
{
    private const CREATED_META_KEY    = '_scc_created_email_sent';
    private const PROCESSING_META_KEY = '_scc_processing_email_sent';
    private const COMPLETED_META_KEY  = '_scc_completed_email_sent';
    private const GIFT_COMPLETED_META_KEY = '_scc_gift_recipient_completed_email_sent';

    public static function register(): void
    {
        add_filter( 'woocommerce_email_enabled_customer_on_hold_order', '__return_false' );
        add_filter( 'woocommerce_email_enabled_customer_processing_order', '__return_false' );
        add_filter( 'woocommerce_email_enabled_customer_completed_order', '__return_false' );

        add_action( 'woocommerce_checkout_order_processed', [ self::class, 'send_created_email' ], 30, 3 );
        add_action( 'woocommerce_order_status_processing', [ self::class, 'send_processing_email' ], 20, 2 );
        add_action( 'woocommerce_order_status_completed', [ self::class, 'send_completed_email' ], 20, 2 );
    }

    public static function send_created_email( int $order_id, array $posted_data, WC_Order $order ): void
    {
        self::send_once(
            $order,
            self::CREATED_META_KEY,
            __( 'Completa tu compra', 'sultana-commerce-core' ),
            __( 'Tu pedido fue creado correctamente. Transfiere el total a la cuenta de banco de tu preferencia.', 'sultana-commerce-core' ),
            __( 'Pendiente de pago', 'sultana-commerce-core' ),
            __( 'Ver datos para transferir', 'sultana-commerce-core' ),
            self::payment_url( $order )
        );
    }

    public static function send_processing_email( int $order_id, WC_Order $order ): void
    {
        self::send_once(
            $order,
            self::PROCESSING_META_KEY,
            __( 'Tu pago fue confirmado', 'sultana-commerce-core' ),
            __( 'Ya confirmamos tu pago y estamos preparando tu pedido.', 'sultana-commerce-core' ),
            __( 'Procesando', 'sultana-commerce-core' ),
            __( 'Ver mi pedido', 'sultana-commerce-core' ),
            $order->get_view_order_url()
        );
    }

    public static function send_completed_email( int $order_id, WC_Order $order ): void
    {
        self::send_once(
            $order,
            self::COMPLETED_META_KEY,
            __( 'Tu pedido fue completado', 'sultana-commerce-core' ),
            sprintf(
                /* translators: %s: store name. */
                __( 'Tu pedido fue completado. Gracias por comprar en %s.', 'sultana-commerce-core' ),
                StoreBranding::get_name()
            ),
            __( 'Completado', 'sultana-commerce-core' ),
            __( 'Ver mi pedido', 'sultana-commerce-core' ),
            $order->get_view_order_url()
        );

        self::send_gift_recipient_completed_email( $order );
    }

    private static function send_gift_recipient_completed_email( WC_Order $order ): void
    {
        if ( 'yes' !== $order->get_meta( '_scc_wishlist_gift_order' ) ) {
            return;
        }

        $recipient = sanitize_email( (string) $order->get_meta( '_scc_wishlist_recipient_email' ) );

        if ( '' === $recipient ) {
            $recipient_id = absint( $order->get_meta( '_scc_wishlist_recipient_user_id' ) );
            $recipient    = $recipient_id > 0 ? sanitize_email( (string) get_user_meta( $recipient_id, 'billing_email', true ) ) : '';
        }

        $giver_name    = self::gift_giver_name( $order );
        $recipient_name = self::gift_recipient_first_name( $order );

        self::send_once(
            $order,
            self::GIFT_COMPLETED_META_KEY,
            sprintf(
                /* translators: %s: gift giver display name. */
                __( 'Regalo de %s', 'sultana-commerce-core' ),
                $giver_name
            ),
            sprintf(
                /* translators: %s: gift giver display name. */
                __( 'recibiste un regalo de %s. Ya fue marcado como completado por la tienda.', 'sultana-commerce-core' ),
                $giver_name
            ),
            __( 'Regalo', 'sultana-commerce-core' ),
            __( 'Ver regalo', 'sultana-commerce-core' ),
            $order->get_view_order_url(),
            $recipient,
            $recipient_name
        );
    }

    private static function send_once( WC_Order $order, string $meta_key, string $subject, string $message, string $status_label, string $button_label, string $button_url, string $recipient = '', string $customer_name = '' ): void
    {
        if ( 'yes' === $order->get_meta( $meta_key, true ) ) {
            return;
        }

        $recipient = '' !== $recipient ? $recipient : $order->get_billing_email();

        if ( ! is_email( $recipient ) ) {
            return;
        }

        $sent = wp_mail(
            $recipient,
            self::email_subject( $subject, $order ),
            self::email_body( $subject, $message, $status_label, $button_label, $button_url, $order, $customer_name ),
            self::email_headers()
        );

        if ( ! $sent ) {
            return;
        }

        $order->update_meta_data( $meta_key, 'yes' );
        $order->save();
    }

    private static function email_subject( string $subject, WC_Order $order ): string
    {
        return sprintf(
            '%s - Pedido #%s',
            $subject,
            $order->get_order_number()
        );
    }

    private static function payment_url( WC_Order $order ): string
    {
        return $order->get_checkout_order_received_url();
    }

    private static function email_body( string $headline, string $message, string $status_label, string $button_label, string $button_url, WC_Order $order, string $customer_name = '' ): string
    {
        $customer_name = trim( $customer_name ) ?: trim( $order->get_billing_first_name() );

        if ( '' === $customer_name ) {
            $customer_name = __( 'cliente', 'sultana-commerce-core' );
        }

        $order_number = $order->get_order_number();
        $order_total  = self::plain_text( $order->get_formatted_order_total() );
        $brand_color  = StoreBranding::get_primary_color();

        ob_start();
        ?>
        <p style="margin:0 0 22px;color:#62566a;font-size:15px;line-height:1.6;">
            <?php echo esc_html( sprintf( __( 'Hola %s, %s', 'sultana-commerce-core' ), $customer_name, lcfirst( $message ) ) ); ?>
        </p>

        <?php echo self::order_items_html( $order ); ?>

        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;border-collapse:collapse;background:#fff8fb;border:1px solid #f5deea;border-radius:5px;margin:0 0 22px;">
            <tr>
                <td style="padding:14px 16px;color:#62566a;font-size:13px;font-weight:700;">
                    <?php esc_html_e( 'Total', 'sultana-commerce-core' ); ?>
                </td>
                <td align="right" style="padding:14px 16px;color:#149361;font-size:17px;font-weight:700;">
                    <span style="display:inline-block;background:#e6f5ee;border-radius:999px;padding:9px 13px;">
                        <?php echo esc_html( $order_total ); ?>
                    </span>
                </td>
            </tr>
        </table>
        <?php

        $content_html = (string) ob_get_clean();
        $eyebrow_html = sprintf(
            '<p style="margin:0 0 8px;color:%1$s;font-size:12px;font-weight:700;letter-spacing:2px;text-transform:uppercase;">%2$s <span style="display:inline-block;margin-left:10px;background:#eaf6fb;border-radius:999px;padding:7px 11px;color:#101122;font-size:12px;font-weight:700;letter-spacing:0;text-transform:none;vertical-align:middle;">%3$s</span></p>',
            esc_attr( $brand_color ),
            esc_html( sprintf( __( 'Pedido #%s', 'sultana-commerce-core' ), $order_number ) ),
            esc_html( $status_label )
        );

        return EmailRenderer::render(
            [
                'title'        => $headline,
                'eyebrow_html' => $eyebrow_html,
                'content_html' => $content_html,
                'button'       => [
                    'label' => $button_label,
                    'url'   => $button_url,
                ],
            ]
        );
    }

    private static function order_items_html( WC_Order $order ): string
    {
        $items = $order->get_items();

        if ( empty( $items ) ) {
            return '';
        }

        ob_start();
        ?>
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;border-collapse:collapse;margin:0 0 16px;">
            <tr>
                <td style="padding:0 0 8px;color:<?php echo esc_attr( StoreBranding::get_primary_color() ); ?>;font-size:12px;font-weight:700;letter-spacing:1.6px;text-transform:uppercase;">
                    <?php esc_html_e( 'Productos', 'sultana-commerce-core' ); ?>
                </td>
            </tr>
            <tr>
                <td>
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="width:100%;border-collapse:collapse;background:#ffffff;border:1px solid #f5deea;border-radius:5px;">
                        <?php foreach ( $items as $item ) : ?>
                            <?php
                            $product_name = $item->get_name();
                            $quantity     = (int) $item->get_quantity();
                            ?>
                            <tr>
                                <td style="padding:14px 16px;border-bottom:1px solid #f5deea;color:#101122;font-size:14px;font-weight:700;line-height:1.4;">
                                    <?php echo esc_html( $product_name ); ?>
                                </td>
                                <td align="right" style="padding:14px 16px;border-bottom:1px solid #f5deea;color:<?php echo esc_attr( StoreBranding::get_primary_color() ); ?>;font-size:14px;font-weight:700;white-space:nowrap;">
                                    x<?php echo esc_html( (string) $quantity ); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </td>
            </tr>
        </table>
        <?php

        return (string) ob_get_clean();
    }

    private static function plain_text( string $html ): string
    {
        return wp_strip_all_tags( html_entity_decode( $html, ENT_QUOTES, get_bloginfo( 'charset' ) ) );
    }

    private static function gift_giver_name( WC_Order $order ): string
    {
        $giver_id = absint( $order->get_meta( '_scc_wishlist_giver_user_id' ) );
        $giver    = $giver_id > 0 ? get_user_by( 'id', $giver_id ) : false;

        if ( $giver instanceof \WP_User && '' !== trim( (string) $giver->display_name ) ) {
            return sanitize_text_field( (string) $giver->display_name );
        }

        $billing_name = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );

        return '' !== $billing_name ? sanitize_text_field( $billing_name ) : __( 'alguien especial', 'sultana-commerce-core' );
    }

    private static function gift_recipient_first_name( WC_Order $order ): string
    {
        $name = trim( (string) $order->get_meta( '_scc_wishlist_recipient_name' ) );

        if ( '' === $name ) {
            return '';
        }

        $parts = preg_split( '/\s+/', $name );

        return sanitize_text_field( (string) ( $parts[0] ?? '' ) );
    }

    private static function email_headers(): array
    {
        return EmailRenderer::html_headers();
    }
}

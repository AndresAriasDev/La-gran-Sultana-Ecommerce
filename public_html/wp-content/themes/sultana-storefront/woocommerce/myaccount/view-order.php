<?php
/**
 * Order detail view.
 *
 * @package SultanaStorefront
 */

defined( 'ABSPATH' ) || exit;

$order = wc_get_order( $order_id );
$current_user_id = get_current_user_id();

$is_received_gift = $order
    && (int) $order->get_customer_id() !== $current_user_id
    && 'yes' === $order->get_meta( '_scc_wishlist_gift_order' )
    && absint( $order->get_meta( '_scc_wishlist_recipient_user_id' ) ) === $current_user_id
    && $order->has_status( 'completed' );

if ( ! $order || ( (int) $order->get_customer_id() !== $current_user_id && ! $is_received_gift ) ) {
    return;
}

$status       = $order->get_status();
$status_label = wc_get_order_status_name( $status );
$status_class = sanitize_html_class( $status );
$date_created = $order->get_date_created();
$actions      = $is_received_gift ? [] : wc_get_account_orders_actions( $order );

unset( $actions['view'] );

$giver_id = absint( $order->get_meta( '_scc_wishlist_giver_user_id' ) );
$giver    = $giver_id > 0 ? get_user_by( 'id', $giver_id ) : false;
$giver_name = $giver instanceof WP_User && '' !== trim( (string) $giver->display_name )
    ? sanitize_text_field( (string) $giver->display_name )
    : '';

if ( ! $is_received_gift && '' === $giver_name ) {
    $giver_name = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
}

$giver_name = '' !== $giver_name ? $giver_name : __( 'alguien especial', 'sultana-storefront' );

if ( $is_received_gift ) {
    $status_label = sprintf(
        /* translators: %s: gift giver display name. */
        __( 'Regalo de %s', 'sultana-storefront' ),
        $giver_name
    );
    $status_class = 'gift';
}

$status_messages = [
    'pending'    => __( 'Completa el pago para que podamos preparar tu pedido.', 'sultana-storefront' ),
    'on-hold'    => __( 'Estamos revisando tu pago para continuar con la preparacion.', 'sultana-storefront' ),
    'processing' => __( 'Tu pedido esta en preparacion.', 'sultana-storefront' ),
    'completed'  => __( 'Tu pedido fue completado.', 'sultana-storefront' ),
    'cancelled'  => __( 'Este pedido fue cancelado.', 'sultana-storefront' ),
    'failed'     => __( 'No se pudo completar este pedido.', 'sultana-storefront' ),
    'refunded'   => __( 'Este pedido fue reembolsado.', 'sultana-storefront' ),
];

$status_message = $is_received_gift
    ? sprintf(
        /* translators: %s: gift giver display name. */
        __( 'Recibiste este regalo de %s. La tienda lo marco como completado.', 'sultana-storefront' ),
        $giver_name
    )
    : ( $status_messages[ $status ] ?? __( 'Revisa el estado actual de tu pedido.', 'sultana-storefront' ) );
$status_message = trim( $status_message );
$status_info_id = 've-view-order-status-info-' . absint( $order->get_id() );
$state_code     = $is_received_gift ? $order->get_shipping_state() : $order->get_billing_state();
$states         = WC()->countries->get_states( 'NI' );
$state_label    = isset( $states[ $state_code ] ) ? $states[ $state_code ] : $state_code;
$address_name   = $is_received_gift
    ? trim( $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name() )
    : trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );

$address_lines = array_filter(
    [
        $address_name,
        $is_received_gift ? $order->get_shipping_address_1() : $order->get_billing_address_1(),
        $is_received_gift ? $order->get_shipping_city() : $order->get_billing_city(),
        $state_label,
    ]
);

$totals = $order->get_order_item_totals();
?>

<section class="ve-view-order">
    <header class="ve-view-order__hero">
        <div class="ve-view-order__intro">
            <span class="ve-view-order__icon" aria-hidden="true">
                <?php variedadesexpress_icon( 'shopping-bag', 've-view-order__icon-svg' ); ?>
            </span>
            <div class="ve-view-order__heading">
                <h1><?php esc_html_e( 'Pedido', 'sultana-storefront' ); ?> #<?php echo esc_html( $order->get_order_number() ); ?></h1>
                <?php if ( $date_created ) : ?>
                    <p><?php echo esc_html( sprintf( __( 'Realizado el %s', 'sultana-storefront' ), wc_format_datetime( $date_created ) ) ); ?></p>
                <?php endif; ?>
            </div>
            <span class="ve-view-order__status-group">
                <?php if ( '' !== $status_message ) : ?>
                    <span class="ve-view-order__info" data-view-order-info>
                        <button
                            type="button"
                            class="ve-view-order__info-button"
                            aria-label="<?php esc_attr_e( 'Ver información del pedido', 'sultana-storefront' ); ?>"
                            aria-expanded="false"
                            aria-controls="<?php echo esc_attr( $status_info_id ); ?>"
                            data-order-info-toggle
                        >
                            <span aria-hidden="true">!</span>
                        </button>
                        <span id="<?php echo esc_attr( $status_info_id ); ?>" class="ve-view-order__info-popover" role="status" hidden data-order-info-popover>
                            <?php echo esc_html( $status_message ); ?>
                        </span>
                    </span>
                <?php endif; ?>
                <span class="ve-view-order__status ve-view-order__status--<?php echo esc_attr( $status_class ); ?>">
                    <?php echo esc_html( $status_label ); ?>
                </span>
            </span>
        </div>
    </header>

    <div class="ve-view-order__grid">
        <div class="ve-view-order__main">
            <section class="ve-view-order__section ve-view-order__products" aria-labelledby="ve-view-order-products">
                <h2 id="ve-view-order-products"><?php esc_html_e( 'Productos', 'sultana-storefront' ); ?></h2>

                <div class="ve-view-order__items">
                    <?php foreach ( $order->get_items() as $item_id => $item ) : ?>
                        <?php
                        $meta_data = $item->get_formatted_meta_data();
                        $hidden_meta_keys = [
                            '_scc_wishlist_gift_owner_id',
                            '_scc_wishlist_gift_giver_id',
                            '_scc_wishlist_gift_key',
                            'regalo para',
                        ];
                        ?>
                        <article class="ve-view-order-item">
                            <div class="ve-view-order-item__body">
                                <h3><?php echo esc_html( $item->get_name() ); ?></h3>
                                <?php if ( ! empty( $meta_data ) ) : ?>
                                <div class="ve-view-order-item__meta">
                                    <?php foreach ( $meta_data as $meta ) : ?>
                                        <?php
                                        $meta_key   = trim( wp_strip_all_tags( $meta->display_key ) );
                                        $meta_value = trim( wp_strip_all_tags( $meta->display_value ) );
                                        $quantity   = (string) $item->get_quantity();
                                        $meta_key   = function_exists( 'mb_strtolower' ) ? mb_strtolower( $meta_key ) : strtolower( $meta_key );

                                        if ( in_array( $meta_key, $hidden_meta_keys, true ) || in_array( $meta_key, [ 'cantidad', 'quantity', 'qty' ], true ) || in_array( $meta_value, [ $quantity, 'x' . $quantity ], true ) ) {
                                            continue;
                                        }
                                        ?>
                                        <span><?php echo esc_html( $meta_value ); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="ve-view-order-item__side">
                                <span class="ve-view-order-item__qty">x<?php echo esc_html( $item->get_quantity() ); ?></span>
                                <strong><?php echo wp_kses_post( $order->get_formatted_line_subtotal( $item ) ); ?></strong>
                        </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="ve-view-order__section ve-view-order__address" aria-labelledby="ve-view-order-address">
                <span class="ve-view-order__eyebrow"><?php echo esc_html( $is_received_gift ? __( 'Entrega', 'sultana-storefront' ) : __( 'Facturacion', 'sultana-storefront' ) ); ?></span>
                <h2 id="ve-view-order-address"><?php echo esc_html( $is_received_gift ? __( 'Direccion de entrega', 'sultana-storefront' ) : __( 'Direccion de facturacion', 'sultana-storefront' ) ); ?></h2>

                <div class="ve-view-order-address">
                    <address>
                        <?php foreach ( $address_lines as $line ) : ?>
                            <span><?php echo esc_html( $line ); ?></span>
                        <?php endforeach; ?>
                        <?php if ( $is_received_gift && is_callable( [ $order, 'get_shipping_phone' ] ) && $order->get_shipping_phone() ) : ?>
                            <span><?php echo esc_html( $order->get_shipping_phone() ); ?></span>
                        <?php elseif ( ! $is_received_gift && $order->get_billing_phone() ) : ?>
                            <span><?php echo esc_html( $order->get_billing_phone() ); ?></span>
                        <?php endif; ?>
                        <?php if ( ! $is_received_gift && $order->get_billing_email() ) : ?>
                            <span><?php echo esc_html( $order->get_billing_email() ); ?></span>
                        <?php endif; ?>
                    </address>
                </div>
            </section>
        </div>

        <aside class="ve-view-order__section ve-view-order__summary" aria-labelledby="ve-view-order-summary">
            <span class="ve-view-order__eyebrow"><?php esc_html_e( 'Resumen', 'sultana-storefront' ); ?></span>
            <h2 id="ve-view-order-summary"><?php esc_html_e( 'Total del pedido', 'sultana-storefront' ); ?></h2>

            <dl class="ve-view-order__totals">
                <?php foreach ( $totals as $total_key => $total ) : ?>
                    <?php
                    if ( 'payment_method' === $total_key ) {
                        continue;
                    }
                    ?>
                    <div>
                        <dt><?php echo wp_kses_post( $total['label'] ); ?></dt>
                        <dd><?php echo wp_kses_post( $total['value'] ); ?></dd>
                    </div>
                <?php endforeach; ?>
            </dl>

            <?php if ( ! empty( $actions ) ) : ?>
                <div class="ve-view-order__actions">
                    <?php foreach ( $actions as $key => $action ) : ?>
                        <?php
                        if ( 'pay' === $key ) {
                            $action['url'] = $order->get_checkout_order_received_url();
                        }
                        ?>
                        <a class="ve-view-order__action ve-view-order__action--<?php echo esc_attr( sanitize_html_class( $key ) ); ?>" href="<?php echo esc_url( $action['url'] ); ?>">
                            <?php echo esc_html( $action['name'] ); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </aside>
    </div>
</section>

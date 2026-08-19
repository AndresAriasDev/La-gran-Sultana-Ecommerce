<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$order    = $screen_data['order'] ?? [];
$back_url = $screen_data['back_url'] ?? \Sultana\Admin\Core\Router::orders_url();
$message  = $screen_data['message'] ?? '';
$error    = $screen_data['error'] ?? '';

if ( ! empty( $screen_data['not_found'] ) || ! empty( $screen_data['forbidden'] ) || '' !== $error ) :
    ?>
    <section class="sultana-admin-order-view" aria-labelledby="sultana-admin-order-view-title">
        <div class="sultana-admin-page-header">
            <div>
                <p class="sultana-admin-kicker"><?php esc_html_e( 'Pedidos', 'sultana-admin' ); ?></p>
                <h1 id="sultana-admin-order-view-title"><?php esc_html_e( 'Ver pedido', 'sultana-admin' ); ?></h1>
            </div>
            <a class="sultana-admin-muted-action" href="<?php echo esc_url( $back_url ); ?>"><?php esc_html_e( 'Volver a pedidos', 'sultana-admin' ); ?></a>
        </div>

        <div class="sultana-admin-empty">
            <h2><?php echo esc_html( '' !== $message ? $message : __( 'No pudimos cargar el pedido.', 'sultana-admin' ) ); ?></h2>
            <?php if ( '' !== $error ) : ?>
                <p><?php echo esc_html( $error ); ?></p>
            <?php endif; ?>
        </div>
    </section>
    <?php
    return;
endif;

$summary  = $order['summary'] ?? [];
$customer = $order['customer'] ?? [];
$address  = $order['address'] ?? [];
$items    = $order['items'] ?? [];
$payment  = $order['payment'] ?? [];
$shipping = $order['shipping'] ?? [];
$totals   = $order['totals'] ?? [];
$gift     = $order['gift'] ?? [ 'is_gift' => false ];
$status_action = $screen_data['status_action'] ?? [];
$status_error  = $screen_data['status_error'] ?? '';
$notice        = $screen_data['notice'] ?? '';

?>
<section class="sultana-admin-order-view" aria-labelledby="sultana-admin-order-view-title">
    <div class="sultana-admin-page-header">
        <div>
            <p class="sultana-admin-kicker"><?php esc_html_e( 'Pedidos', 'sultana-admin' ); ?></p>
            <h1 id="sultana-admin-order-view-title">
                <?php
                printf(
                    /* translators: %s: order number. */
                    esc_html__( 'Pedido #%s', 'sultana-admin' ),
                    esc_html( $order['number'] ?? '' )
                );
                ?>
            </h1>
            <p><?php echo esc_html( $screen_data['read_only_notice'] ?? __( 'Vista de solo lectura.', 'sultana-admin' ) ); ?></p>
        </div>
        <a class="sultana-admin-muted-action" href="<?php echo esc_url( $back_url ); ?>"><?php esc_html_e( 'Volver a pedidos', 'sultana-admin' ); ?></a>
    </div>

    <?php if ( '' !== $notice ) : ?>
        <div class="sultana-admin-notice" role="status"><?php echo esc_html( $notice ); ?></div>
    <?php endif; ?>

    <?php if ( '' !== $status_error ) : ?>
        <div class="sultana-admin-error-list" role="alert">
            <strong><?php esc_html_e( 'No se pudo actualizar el estado', 'sultana-admin' ); ?></strong>
            <ul>
                <li><?php echo esc_html( $status_error ); ?></li>
            </ul>
        </div>
    <?php endif; ?>

    <article class="sultana-admin-detail-panel sultana-admin-status-manager">
        <h2><?php esc_html_e( 'Estado del pedido', 'sultana-admin' ); ?></h2>
        <dl class="sultana-admin-detail-list">
            <div>
                <dt><?php esc_html_e( 'Estado actual', 'sultana-admin' ); ?></dt>
                <dd>
                    <span class="sultana-admin-status-pill sultana-admin-status-pill--<?php echo esc_attr( sanitize_html_class( $summary['status'] ?? '' ) ); ?>">
                        <?php echo esc_html( $summary['status_label'] ?? '' ); ?>
                    </span>
                </dd>
            </div>
        </dl>

        <?php if ( ! empty( $status_action['can_update'] ) && ! empty( $status_action['options'] ) ) : ?>
            <form class="sultana-admin-status-form" method="post" action="<?php echo esc_url( \Sultana\Admin\Core\Router::order_url( absint( $order['id'] ?? 0 ) ) ); ?>">
                <input type="hidden" name="sultana_admin_order_status_action" value="update_status">
                <input type="hidden" name="order_id" value="<?php echo esc_attr( absint( $order['id'] ?? 0 ) ); ?>">
                <input type="hidden" name="current_status" value="<?php echo esc_attr( $status_action['current_status'] ?? '' ); ?>">
                <?php wp_nonce_field( $status_action['nonce_action'] ?? '', \Sultana\Admin\Orders\OrderController::STATUS_NONCE_FIELD ); ?>

                <label for="sultana-admin-new-order-status"><?php esc_html_e( 'Nuevo estado', 'sultana-admin' ); ?></label>
                <div class="sultana-admin-status-form__controls">
                    <select id="sultana-admin-new-order-status" name="new_status" required>
                        <?php foreach ( $status_action['options'] as $status_key => $status_label ) : ?>
                            <option value="<?php echo esc_attr( $status_key ); ?>"><?php echo esc_html( $status_label ); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit"><?php esc_html_e( 'Actualizar estado', 'sultana-admin' ); ?></button>
                </div>
            </form>
        <?php else : ?>
            <p><?php esc_html_e( 'No hay cambios de estado disponibles desde Sultana Admin para este pedido.', 'sultana-admin' ); ?></p>
        <?php endif; ?>
    </article>

    <div class="sultana-admin-order-detail-grid">
        <article class="sultana-admin-detail-panel">
            <h2><?php esc_html_e( 'Resumen', 'sultana-admin' ); ?></h2>
            <dl class="sultana-admin-detail-list">
                <div><dt><?php esc_html_e( 'Fecha', 'sultana-admin' ); ?></dt><dd><?php echo esc_html( $summary['date'] ?? '' ); ?></dd></div>
                <div><dt><?php esc_html_e( 'Estado', 'sultana-admin' ); ?></dt><dd><span class="sultana-admin-status-pill sultana-admin-status-pill--<?php echo esc_attr( sanitize_html_class( $summary['status'] ?? '' ) ); ?>"><?php echo esc_html( $summary['status_label'] ?? '' ); ?></span></dd></div>
                <div><dt><?php esc_html_e( 'Total', 'sultana-admin' ); ?></dt><dd><?php echo wp_kses_post( $summary['total'] ?? '' ); ?></dd></div>
                <div><dt><?php esc_html_e( 'Moneda', 'sultana-admin' ); ?></dt><dd><?php echo esc_html( $summary['currency'] ?? '' ); ?></dd></div>
                <?php if ( ! empty( $summary['date_completed'] ) ) : ?>
                    <div><dt><?php esc_html_e( 'Completado', 'sultana-admin' ); ?></dt><dd><?php echo esc_html( $summary['date_completed'] ); ?></dd></div>
                <?php endif; ?>
            </dl>
        </article>

        <article class="sultana-admin-detail-panel">
            <h2><?php esc_html_e( 'Cliente', 'sultana-admin' ); ?></h2>
            <dl class="sultana-admin-detail-list">
                <div><dt><?php esc_html_e( 'Nombre', 'sultana-admin' ); ?></dt><dd><?php echo esc_html( $customer['name'] ?? '' ); ?></dd></div>
                <?php if ( ! empty( $customer['email'] ) ) : ?>
                    <div><dt><?php esc_html_e( 'Email', 'sultana-admin' ); ?></dt><dd><?php echo esc_html( $customer['email'] ); ?></dd></div>
                <?php endif; ?>
                <?php if ( ! empty( $customer['phone'] ) ) : ?>
                    <div><dt><?php esc_html_e( 'Telefono', 'sultana-admin' ); ?></dt><dd><?php echo esc_html( $customer['phone'] ); ?></dd></div>
                <?php endif; ?>
            </dl>
        </article>

        <?php if ( ! empty( $gift['is_gift'] ) ) : ?>
            <article class="sultana-admin-detail-panel sultana-admin-detail-panel--notice">
                <h2><?php esc_html_e( 'Pedido de regalo', 'sultana-admin' ); ?></h2>
                <dl class="sultana-admin-detail-list">
                    <?php if ( ! empty( $gift['recipient_name'] ) ) : ?>
                        <div><dt><?php esc_html_e( 'Destinatario', 'sultana-admin' ); ?></dt><dd><?php echo esc_html( $gift['recipient_name'] ); ?></dd></div>
                    <?php endif; ?>
                    <?php if ( ! empty( $gift['recipient_address'] ) ) : ?>
                        <div><dt><?php esc_html_e( 'Direccion', 'sultana-admin' ); ?></dt><dd><?php echo esc_html( $gift['recipient_address'] ); ?></dd></div>
                    <?php endif; ?>
                </dl>
            </article>
        <?php endif; ?>

        <article class="sultana-admin-detail-panel">
            <h2><?php esc_html_e( 'Entrega', 'sultana-admin' ); ?></h2>
            <dl class="sultana-admin-detail-list">
                <?php if ( ! empty( $address['type_label'] ) ) : ?>
                    <div><dt><?php esc_html_e( 'Tipo', 'sultana-admin' ); ?></dt><dd><?php echo esc_html( $address['type_label'] ); ?></dd></div>
                <?php endif; ?>
                <?php if ( ! empty( $address['department'] ) ) : ?>
                    <div><dt><?php esc_html_e( 'Departamento', 'sultana-admin' ); ?></dt><dd><?php echo esc_html( $address['department'] ); ?></dd></div>
                <?php endif; ?>
                <?php if ( ! empty( $address['municipality'] ) ) : ?>
                    <div><dt><?php esc_html_e( 'Municipio', 'sultana-admin' ); ?></dt><dd><?php echo esc_html( $address['municipality'] ); ?></dd></div>
                <?php endif; ?>
                <?php if ( ! empty( $address['address_1'] ) ) : ?>
                    <div><dt><?php esc_html_e( 'Direccion', 'sultana-admin' ); ?></dt><dd><?php echo esc_html( $address['address_1'] ); ?></dd></div>
                <?php endif; ?>
            </dl>

            <?php if ( ! empty( $shipping ) ) : ?>
                <div class="sultana-admin-shipping-items">
                    <?php foreach ( $shipping as $shipping_item ) : ?>
                        <div class="sultana-admin-shipping-item">
                            <strong><?php echo esc_html( $shipping_item['method'] ?: __( 'Metodo de entrega', 'sultana-admin' ) ); ?></strong>
                            <span><?php echo wp_kses_post( $shipping_item['total'] ?? '' ); ?></span>
                            <?php if ( ! empty( $shipping_item['meta'] ) ) : ?>
                                <dl class="sultana-admin-mini-list">
                                    <?php foreach ( $shipping_item['meta'] as $meta ) : ?>
                                        <div><dt><?php echo esc_html( $meta['label'] ); ?></dt><dd><?php echo esc_html( $meta['value'] ); ?></dd></div>
                                    <?php endforeach; ?>
                                </dl>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </article>

        <article class="sultana-admin-detail-panel">
            <h2><?php esc_html_e( 'Pago', 'sultana-admin' ); ?></h2>
            <dl class="sultana-admin-detail-list">
                <div><dt><?php esc_html_e( 'Metodo', 'sultana-admin' ); ?></dt><dd><?php echo esc_html( $payment['method'] ?? '' ); ?></dd></div>
                <div><dt><?php esc_html_e( 'Estado', 'sultana-admin' ); ?></dt><dd><?php echo esc_html( $payment['paid_label'] ?? '' ); ?></dd></div>
                <?php if ( ! empty( $payment['date_paid'] ) ) : ?>
                    <div><dt><?php esc_html_e( 'Fecha de pago', 'sultana-admin' ); ?></dt><dd><?php echo esc_html( $payment['date_paid'] ); ?></dd></div>
                <?php endif; ?>
                <?php if ( ! empty( $payment['transaction_id'] ) ) : ?>
                    <div><dt><?php esc_html_e( 'Transaccion', 'sultana-admin' ); ?></dt><dd><?php echo esc_html( $payment['transaction_id'] ); ?></dd></div>
                <?php endif; ?>
            </dl>
        </article>
    </div>

    <article class="sultana-admin-detail-panel">
        <h2><?php esc_html_e( 'Productos', 'sultana-admin' ); ?></h2>
        <div class="sultana-admin-order-items">
            <?php foreach ( $items as $item ) : ?>
                <section class="sultana-admin-order-line">
                    <div class="sultana-admin-order-line__main">
                        <div>
                            <h3><?php echo esc_html( $item['name'] ); ?></h3>
                            <?php if ( ! empty( $item['attributes'] ) ) : ?>
                                <dl class="sultana-admin-mini-list">
                                    <?php foreach ( $item['attributes'] as $attribute ) : ?>
                                        <div><dt><?php echo esc_html( $attribute['label'] ); ?></dt><dd><?php echo esc_html( $attribute['value'] ); ?></dd></div>
                                    <?php endforeach; ?>
                                </dl>
                            <?php endif; ?>
                        </div>
                        <dl class="sultana-admin-line-totals">
                            <div><dt><?php esc_html_e( 'Cantidad', 'sultana-admin' ); ?></dt><dd><?php echo esc_html( $item['quantity'] ); ?></dd></div>
                            <div><dt><?php esc_html_e( 'Subtotal', 'sultana-admin' ); ?></dt><dd><?php echo wp_kses_post( $item['subtotal'] ); ?></dd></div>
                            <div><dt><?php esc_html_e( 'Total', 'sultana-admin' ); ?></dt><dd><?php echo wp_kses_post( $item['total'] ); ?></dd></div>
                        </dl>
                    </div>

                    <?php if ( ! empty( $item['components'] ) ) : ?>
                        <div class="sultana-admin-combo-snapshot">
                            <strong><?php esc_html_e( 'Incluye', 'sultana-admin' ); ?></strong>
                            <ul>
                                <?php foreach ( $item['components'] as $component ) : ?>
                                    <li>
                                        <span><?php echo esc_html( $component['name'] ); ?></span>
                                        <small>
                                            <?php
                                            printf(
                                                /* translators: 1: quantity per combo, 2: total component quantity. */
                                                esc_html__( '%1$s por combo / %2$s total', 'sultana-admin' ),
                                                esc_html( $component['quantity'] ),
                                                esc_html( $component['total_units'] )
                                            );
                                            ?>
                                        </small>
                                        <?php if ( ! empty( $component['sku'] ) ) : ?>
                                            <small><?php echo esc_html( sprintf( __( 'SKU: %s', 'sultana-admin' ), $component['sku'] ) ); ?></small>
                                        <?php endif; ?>
                                        <?php if ( ! empty( $component['attributes'] ) ) : ?>
                                            <dl class="sultana-admin-mini-list">
                                                <?php foreach ( $component['attributes'] as $attribute ) : ?>
                                                    <div><dt><?php echo esc_html( $attribute['label'] ); ?></dt><dd><?php echo esc_html( $attribute['value'] ); ?></dd></div>
                                                <?php endforeach; ?>
                                            </dl>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endforeach; ?>
        </div>
    </article>

    <article class="sultana-admin-detail-panel sultana-admin-detail-panel--totals">
        <h2><?php esc_html_e( 'Totales', 'sultana-admin' ); ?></h2>
        <dl class="sultana-admin-detail-list">
            <div><dt><?php esc_html_e( 'Subtotal productos', 'sultana-admin' ); ?></dt><dd><?php echo wp_kses_post( $totals['subtotal'] ?? '' ); ?></dd></div>
            <div><dt><?php esc_html_e( 'Descuentos', 'sultana-admin' ); ?></dt><dd><?php echo wp_kses_post( $totals['discount'] ?? '' ); ?></dd></div>
            <div><dt><?php esc_html_e( 'Envio', 'sultana-admin' ); ?></dt><dd><?php echo wp_kses_post( $totals['shipping'] ?? '' ); ?></dd></div>
            <div><dt><?php esc_html_e( 'IVA / Impuestos', 'sultana-admin' ); ?></dt><dd><?php echo wp_kses_post( $totals['tax'] ?? '' ); ?></dd></div>
            <div class="sultana-admin-total-row"><dt><?php esc_html_e( 'Total', 'sultana-admin' ); ?></dt><dd><?php echo wp_kses_post( $totals['total'] ?? '' ); ?></dd></div>
        </dl>
    </article>
</section>

<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$orders         = $screen_data['orders'] ?? [];
$search         = $screen_data['search'] ?? '';
$status         = $screen_data['status'] ?? '';
$status_options = $screen_data['status_options'] ?? [];
$page           = absint( $screen_data['page'] ?? 1 );
$total          = absint( $screen_data['total'] ?? 0 );
$total_pages    = absint( $screen_data['total_pages'] ?? 1 );
$pagination     = $screen_data['pagination'] ?? [ 'previous' => '', 'next' => '' ];
$error          = $screen_data['error'] ?? '';
$has_filters    = ! empty( $screen_data['has_filters'] );

?>
<section class="sultana-admin-orders" aria-labelledby="sultana-admin-orders-title">
    <div class="sultana-admin-page-header">
        <div>
            <p class="sultana-admin-kicker"><?php esc_html_e( 'Operacion', 'sultana-admin' ); ?></p>
            <h1 id="sultana-admin-orders-title"><?php esc_html_e( 'Pedidos', 'sultana-admin' ); ?></h1>
        </div>
    </div>

    <form class="sultana-admin-search sultana-admin-order-filters" method="get" action="<?php echo esc_url( \Sultana\Admin\Core\Router::orders_url() ); ?>" role="search">
        <label for="sultana-admin-order-search"><?php esc_html_e( 'Buscar pedidos', 'sultana-admin' ); ?></label>
        <div class="sultana-admin-search__controls sultana-admin-order-filters__controls">
            <input
                id="sultana-admin-order-search"
                type="search"
                name="s"
                value="<?php echo esc_attr( $search ); ?>"
                placeholder="<?php esc_attr_e( 'ID o email exacto', 'sultana-admin' ); ?>"
            >
            <select name="status" aria-label="<?php esc_attr_e( 'Filtrar por estado', 'sultana-admin' ); ?>">
                <option value=""><?php esc_html_e( 'Todos los estados', 'sultana-admin' ); ?></option>
                <?php foreach ( $status_options as $status_key => $status_label ) : ?>
                    <option value="<?php echo esc_attr( $status_key ); ?>" <?php selected( $status, $status_key ); ?>>
                        <?php echo esc_html( $status_label ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit"><?php esc_html_e( 'Filtrar', 'sultana-admin' ); ?></button>
        </div>
    </form>

    <?php if ( '' !== $error ) : ?>
        <div class="sultana-admin-error-list" role="alert">
            <strong><?php esc_html_e( 'No se pudo cargar el listado', 'sultana-admin' ); ?></strong>
            <ul>
                <li><?php echo esc_html( $error ); ?></li>
            </ul>
        </div>
    <?php endif; ?>

    <div class="sultana-admin-list-summary" aria-live="polite">
        <?php
        printf(
            /* translators: 1: current page, 2: total pages, 3: total orders. */
            esc_html__( 'Pagina %1$d de %2$d - %3$d pedidos', 'sultana-admin' ),
            $page,
            max( 1, $total_pages ),
            $total
        );
        ?>
    </div>

    <?php if ( empty( $orders ) ) : ?>
        <div class="sultana-admin-empty">
            <h2><?php echo esc_html( $has_filters ? __( 'Sin resultados', 'sultana-admin' ) : __( 'No hay pedidos todavia', 'sultana-admin' ) ); ?></h2>
            <p><?php echo esc_html( $has_filters ? __( 'No encontramos pedidos con esos filtros.', 'sultana-admin' ) : __( 'No hay pedidos todavia.', 'sultana-admin' ) ); ?></p>
        </div>
    <?php else : ?>
        <div class="sultana-admin-order-table-wrap">
            <table class="sultana-admin-order-table">
                <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e( 'Pedido', 'sultana-admin' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Cliente', 'sultana-admin' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Fecha', 'sultana-admin' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Estado', 'sultana-admin' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Total', 'sultana-admin' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Pago', 'sultana-admin' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Entrega', 'sultana-admin' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Accion', 'sultana-admin' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $orders as $order ) : ?>
                        <tr>
                            <td><strong>#<?php echo esc_html( $order['number'] ); ?></strong></td>
                            <td><?php echo esc_html( $order['customer'] ); ?></td>
                            <td><?php echo esc_html( $order['date'] ); ?></td>
                            <td>
                                <span class="sultana-admin-status-pill sultana-admin-status-pill--<?php echo esc_attr( sanitize_html_class( $order['status'] ) ); ?>">
                                    <?php echo esc_html( $order['status_label'] ); ?>
                                </span>
                            </td>
                            <td><?php echo wp_kses_post( $order['total'] ); ?></td>
                            <td><?php echo esc_html( $order['payment_method'] ); ?></td>
                            <td><?php echo esc_html( $order['shipping_method'] ); ?></td>
                            <td>
                                <button class="sultana-admin-text-action" type="button" disabled aria-disabled="true">
                                    <?php esc_html_e( 'Ver pedido', 'sultana-admin' ); ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="sultana-admin-order-cards">
            <?php foreach ( $orders as $order ) : ?>
                <article class="sultana-admin-order-card">
                    <div class="sultana-admin-order-card__header">
                        <div>
                            <h2>#<?php echo esc_html( $order['number'] ); ?></h2>
                            <p><?php echo esc_html( $order['customer'] ); ?></p>
                        </div>
                        <span class="sultana-admin-status-pill sultana-admin-status-pill--<?php echo esc_attr( sanitize_html_class( $order['status'] ) ); ?>">
                            <?php echo esc_html( $order['status_label'] ); ?>
                        </span>
                    </div>
                    <dl>
                        <div>
                            <dt><?php esc_html_e( 'Fecha', 'sultana-admin' ); ?></dt>
                            <dd><?php echo esc_html( $order['date'] ); ?></dd>
                        </div>
                        <div>
                            <dt><?php esc_html_e( 'Total', 'sultana-admin' ); ?></dt>
                            <dd><?php echo wp_kses_post( $order['total'] ); ?></dd>
                        </div>
                        <div>
                            <dt><?php esc_html_e( 'Pago', 'sultana-admin' ); ?></dt>
                            <dd><?php echo esc_html( $order['payment_method'] ); ?></dd>
                        </div>
                        <div>
                            <dt><?php esc_html_e( 'Entrega', 'sultana-admin' ); ?></dt>
                            <dd><?php echo esc_html( $order['shipping_method'] ); ?></dd>
                        </div>
                    </dl>
                    <button class="sultana-admin-text-action" type="button" disabled aria-disabled="true">
                        <?php esc_html_e( 'Ver pedido', 'sultana-admin' ); ?>
                    </button>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <nav class="sultana-admin-pagination" aria-label="<?php esc_attr_e( 'Paginacion de pedidos', 'sultana-admin' ); ?>">
        <?php if ( ! empty( $pagination['previous'] ) ) : ?>
            <a href="<?php echo esc_url( $pagination['previous'] ); ?>"><?php esc_html_e( 'Anterior', 'sultana-admin' ); ?></a>
        <?php else : ?>
            <span aria-disabled="true"><?php esc_html_e( 'Anterior', 'sultana-admin' ); ?></span>
        <?php endif; ?>

        <strong>
            <?php
            printf(
                /* translators: 1: current page, 2: total pages. */
                esc_html__( '%1$d / %2$d', 'sultana-admin' ),
                $page,
                max( 1, $total_pages )
            );
            ?>
        </strong>

        <?php if ( ! empty( $pagination['next'] ) ) : ?>
            <a href="<?php echo esc_url( $pagination['next'] ); ?>"><?php esc_html_e( 'Siguiente', 'sultana-admin' ); ?></a>
        <?php else : ?>
            <span aria-disabled="true"><?php esc_html_e( 'Siguiente', 'sultana-admin' ); ?></span>
        <?php endif; ?>
    </nav>
</section>

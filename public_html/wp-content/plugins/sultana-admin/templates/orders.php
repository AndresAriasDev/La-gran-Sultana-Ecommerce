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
$pagination     = $screen_data['pagination'] ?? [ 'previous' => '', 'next' => '', 'items' => [] ];
$error          = $screen_data['error'] ?? '';
$has_filters    = ! empty( $screen_data['has_filters'] );
$icon_url       = static fn( string $name ): string => \Sultana\Admin\Core\Icons::url( $name );

?>
<section class="sultana-admin-orders" aria-label="<?php esc_attr_e( 'Pedidos', 'sultana-admin' ); ?>">
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
            <button type="submit">
                <span class="sultana-admin-icon" style="--sultana-admin-icon-url: url('<?php echo esc_url( $icon_url( 'funnel' ) ); ?>');" aria-hidden="true"></span>
                <?php esc_html_e( 'Filtrar', 'sultana-admin' ); ?>
            </button>
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
                        <th scope="col"><?php esc_html_e( 'Entrega', 'sultana-admin' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Accion', 'sultana-admin' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $orders as $order ) : ?>
                        <tr>
                            <td><span class="sultana-admin-order-id-pill">#<?php echo esc_html( $order['number'] ); ?></span></td>
                            <td><?php echo esc_html( $order['customer'] ); ?></td>
                            <td><?php echo esc_html( $order['date'] ); ?></td>
                            <td>
                                <span class="sultana-admin-status-pill sultana-admin-status-pill--<?php echo esc_attr( sanitize_html_class( $order['status'] ) ); ?>">
                                    <?php echo esc_html( $order['status_label'] ); ?>
                                </span>
                            </td>
                            <td><?php echo wp_kses_post( $order['total'] ); ?></td>
                            <td><?php echo esc_html( $order['shipping_method'] ); ?></td>
                            <td>
                                <?php if ( ! empty( $order['can_view'] ) ) : ?>
                                    <a class="sultana-admin-icon-button" href="<?php echo esc_url( $order['view_url'] ); ?>" aria-label="<?php esc_attr_e( 'Ver pedido', 'sultana-admin' ); ?>" title="<?php esc_attr_e( 'Ver pedido', 'sultana-admin' ); ?>">
                                        <span class="sultana-admin-icon" style="--sultana-admin-icon-url: url('<?php echo esc_url( $icon_url( 'package-check' ) ); ?>');" aria-hidden="true"></span>
                                    </a>
                                <?php else : ?>
                                    <button class="sultana-admin-icon-button" type="button" disabled aria-disabled="true" aria-label="<?php esc_attr_e( 'Ver pedido', 'sultana-admin' ); ?>" title="<?php esc_attr_e( 'Ver pedido', 'sultana-admin' ); ?>">
                                        <span class="sultana-admin-icon" style="--sultana-admin-icon-url: url('<?php echo esc_url( $icon_url( 'package-check' ) ); ?>');" aria-hidden="true"></span>
                                    </button>
                                <?php endif; ?>
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
                            <h2><span class="sultana-admin-order-id-pill">#<?php echo esc_html( $order['number'] ); ?></span></h2>
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
                            <dt><?php esc_html_e( 'Entrega', 'sultana-admin' ); ?></dt>
                            <dd><?php echo esc_html( $order['shipping_method'] ); ?></dd>
                        </div>
                    </dl>
                    <?php if ( ! empty( $order['can_view'] ) ) : ?>
                        <a class="sultana-admin-icon-button" href="<?php echo esc_url( $order['view_url'] ); ?>" aria-label="<?php esc_attr_e( 'Ver pedido', 'sultana-admin' ); ?>" title="<?php esc_attr_e( 'Ver pedido', 'sultana-admin' ); ?>">
                            <span class="sultana-admin-icon" style="--sultana-admin-icon-url: url('<?php echo esc_url( $icon_url( 'package-check' ) ); ?>');" aria-hidden="true"></span>
                        </a>
                    <?php else : ?>
                        <button class="sultana-admin-icon-button" type="button" disabled aria-disabled="true" aria-label="<?php esc_attr_e( 'Ver pedido', 'sultana-admin' ); ?>" title="<?php esc_attr_e( 'Ver pedido', 'sultana-admin' ); ?>">
                            <span class="sultana-admin-icon" style="--sultana-admin-icon-url: url('<?php echo esc_url( $icon_url( 'package-check' ) ); ?>');" aria-hidden="true"></span>
                        </button>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ( $total_pages > 1 && ! empty( $pagination['items'] ) ) : ?>
    <nav class="sultana-admin-pagination sultana-admin-pagination--compact" aria-label="<?php esc_attr_e( 'Paginacion de pedidos', 'sultana-admin' ); ?>">
        <?php if ( ! empty( $pagination['previous'] ) ) : ?>
            <a class="sultana-admin-pagination__link sultana-admin-pagination__link--icon" href="<?php echo esc_url( $pagination['previous'] ); ?>" aria-label="<?php esc_attr_e( 'Pagina anterior', 'sultana-admin' ); ?>" title="<?php esc_attr_e( 'Pagina anterior', 'sultana-admin' ); ?>"><span class="sultana-admin-icon" style="--sultana-admin-icon-url: url('<?php echo esc_url( $icon_url( 'chevron-left' ) ); ?>');" aria-hidden="true"></span></a>
        <?php endif; ?>

        <?php foreach ( $pagination['items'] as $item ) : ?>
            <?php if ( 'ellipsis' === ( $item['type'] ?? '' ) ) : ?>
                <span class="sultana-admin-pagination__ellipsis" aria-hidden="true">&hellip;</span>
            <?php elseif ( ! empty( $item['current'] ) ) : ?>
                <span class="sultana-admin-pagination__current" aria-current="page"><?php echo esc_html( (string) absint( $item['page'] ?? 0 ) ); ?></span>
            <?php else : ?>
                <a class="sultana-admin-pagination__link" href="<?php echo esc_url( (string) ( $item['url'] ?? '' ) ); ?>"><?php echo esc_html( (string) absint( $item['page'] ?? 0 ) ); ?></a>
            <?php endif; ?>
        <?php endforeach; ?>

        <?php if ( ! empty( $pagination['next'] ) ) : ?>
            <a class="sultana-admin-pagination__link sultana-admin-pagination__link--icon" href="<?php echo esc_url( $pagination['next'] ); ?>" aria-label="<?php esc_attr_e( 'Pagina siguiente', 'sultana-admin' ); ?>" title="<?php esc_attr_e( 'Pagina siguiente', 'sultana-admin' ); ?>"><span class="sultana-admin-icon" style="--sultana-admin-icon-url: url('<?php echo esc_url( $icon_url( 'chevron-right' ) ); ?>');" aria-hidden="true"></span></a>
        <?php endif; ?>
    </nav>
    <?php endif; ?>
</section>

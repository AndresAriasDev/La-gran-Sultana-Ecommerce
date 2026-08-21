<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$customer     = $screen_data['customer'] ?? [];
$orders       = $screen_data['orders'] ?? [];
$pagination   = $screen_data['orders_pagination'] ?? [ 'previous' => '', 'next' => '', 'items' => [] ];
$orders_error = $screen_data['orders_error'] ?? '';
$icon_url     = static fn( string $name ): string => \Sultana\Admin\Core\Icons::url( $name );

if ( ! empty( $screen_data['not_found'] ) ) :
    ?>
    <section class="sultana-admin-customer-view">
        <div class="sultana-admin-empty">
            <h2><?php echo esc_html( $screen_data['message'] ?? __( 'Cliente no encontrado.', 'sultana-admin' ) ); ?></h2>
            <p><a class="sultana-admin-button sultana-admin-button--secondary" href="<?php echo esc_url( \Sultana\Admin\Core\Router::customers_url() ); ?>"><?php esc_html_e( 'Volver a clientes', 'sultana-admin' ); ?></a></p>
        </div>
    </section>
    <?php
    return;
endif;

?>
<section class="sultana-admin-customer-view" aria-label="<?php esc_attr_e( 'Cliente', 'sultana-admin' ); ?>">
    <div class="sultana-admin-customer-view-grid">
        <div class="sultana-admin-customer-view-grid__main">
        <section class="sultana-admin-detail-panel">
            <h2><?php esc_html_e( 'Datos del cliente', 'sultana-admin' ); ?></h2>
            <dl class="sultana-admin-detail-list">
                <div class="sultana-admin-customer-field sultana-admin-customer-field--wide">
                    <dt><?php esc_html_e( 'Nombre', 'sultana-admin' ); ?></dt>
                    <dd><?php echo esc_html( $customer['name'] ?? '' ); ?></dd>
                </div>
                <div class="sultana-admin-customer-field sultana-admin-customer-field--wide">
                    <dt><?php esc_html_e( 'Email', 'sultana-admin' ); ?></dt>
                    <dd><?php echo esc_html( $customer['email'] ?? '' ); ?></dd>
                </div>
                <?php if ( ! empty( $customer['phone'] ) ) : ?>
                <div class="sultana-admin-customer-field">
                    <dt><?php esc_html_e( 'Telefono', 'sultana-admin' ); ?></dt>
                    <dd><?php echo esc_html( $customer['phone'] ); ?></dd>
                </div>
                <?php endif; ?>
                <div class="sultana-admin-customer-field">
                    <dt><?php esc_html_e( 'Registro', 'sultana-admin' ); ?></dt>
                    <dd><?php echo esc_html( $customer['registered'] ?? '' ); ?></dd>
                </div>
                <?php if ( empty( $customer['address'] ) ) : ?>
                <div class="sultana-admin-customer-field sultana-admin-customer-field--wide">
                    <dt><?php esc_html_e( 'Direccion', 'sultana-admin' ); ?></dt>
                    <dd class="sultana-admin-muted"><?php esc_html_e( 'Sin direccion registrada.', 'sultana-admin' ); ?></dd>
                </div>
                <?php else : ?>
                    <?php foreach ( $customer['address'] as $key => $value ) : ?>
                        <div class="<?php echo esc_attr( 'sultana-admin-customer-field' . ( in_array( $key, [ 'address_1', 'address_2' ], true ) ? ' sultana-admin-customer-field--wide' : '' ) ); ?>">
                            <dt>
                                <?php
                                echo esc_html(
                                    [
                                        'department'   => __( 'Departamento', 'sultana-admin' ),
                                        'municipality' => __( 'Municipio', 'sultana-admin' ),
                                        'address_1'    => __( 'Direccion', 'sultana-admin' ),
                                        'address_2'    => __( 'Referencia', 'sultana-admin' ),
                                    ][ $key ] ?? $key
                                );
                                ?>
                            </dt>
                            <dd><?php echo esc_html( $value ); ?></dd>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </dl>
        </section>

        <section class="sultana-admin-customer-metrics" aria-label="<?php esc_attr_e( 'Resumen comercial', 'sultana-admin' ); ?>">
            <article>
                <span class="sultana-admin-customer-metric__icon sultana-admin-icon" style="--sultana-admin-icon-url: url('<?php echo esc_url( $icon_url( 'package-check' ) ); ?>');" aria-hidden="true"></span>
                <span><?php esc_html_e( 'Completados', 'sultana-admin' ); ?></span>
                <strong><?php echo esc_html( (string) absint( $customer['orders_count'] ?? 0 ) ); ?></strong>
            </article>
            <article>
                <span class="sultana-admin-customer-metric__icon sultana-admin-icon" style="--sultana-admin-icon-url: url('<?php echo esc_url( $icon_url( 'piggy-bank' ) ); ?>');" aria-hidden="true"></span>
                <span><?php esc_html_e( 'Total comprado', 'sultana-admin' ); ?></span>
                <strong><?php echo wp_kses_post( $customer['total_spent'] ?? '' ); ?></strong>
            </article>
        </section>
        </div>

        <div class="sultana-admin-customer-view-grid__aside">
    <section class="sultana-admin-detail-panel">
        <h2><?php esc_html_e( 'Historial de pedidos', 'sultana-admin' ); ?></h2>

        <?php if ( '' !== $orders_error ) : ?>
            <div class="sultana-admin-error-list" role="alert">
                <strong><?php esc_html_e( 'No se pudo cargar el historial', 'sultana-admin' ); ?></strong>
                <ul><li><?php echo esc_html( $orders_error ); ?></li></ul>
            </div>
        <?php endif; ?>

        <?php if ( empty( $orders ) ) : ?>
            <p class="sultana-admin-muted"><?php esc_html_e( 'Este cliente no tiene pedidos registrados.', 'sultana-admin' ); ?></p>
        <?php else : ?>
            <div class="sultana-admin-customer-orders">
                <?php foreach ( $orders as $order ) : ?>
                    <div class="sultana-admin-customer-order-row">
                        <span class="sultana-admin-order-id-pill">#<?php echo esc_html( $order['number'] ); ?></span>
                        <span><?php echo esc_html( $order['date'] ); ?></span>
                        <span class="sultana-admin-status-pill sultana-admin-status-pill--<?php echo esc_attr( sanitize_html_class( $order['status'] ) ); ?>"><?php echo esc_html( $order['status_label'] ); ?></span>
                        <a class="sultana-admin-icon-button sultana-admin-customer-view-action" href="<?php echo esc_url( $order['view_url'] ); ?>" aria-label="<?php esc_attr_e( 'Ver pedido', 'sultana-admin' ); ?>" title="<?php esc_attr_e( 'Ver pedido', 'sultana-admin' ); ?>">
                            <span class="sultana-admin-icon" style="--sultana-admin-icon-url: url('<?php echo esc_url( $icon_url( 'package-check' ) ); ?>');" aria-hidden="true"></span>
                            <span class="sultana-admin-customer-view-action__text"><?php esc_html_e( 'Ver', 'sultana-admin' ); ?></span>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </section>

    <?php if ( ! empty( $pagination['items'] ) ) : ?>
    <nav class="sultana-admin-pagination sultana-admin-pagination--compact sultana-admin-customer-history-pagination" aria-label="<?php esc_attr_e( 'Paginacion del historial', 'sultana-admin' ); ?>">
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
        </div>
    </div>
</section>

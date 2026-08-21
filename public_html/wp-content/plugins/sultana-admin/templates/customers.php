<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$customers   = $screen_data['customers'] ?? [];
$search      = $screen_data['search'] ?? '';
$page        = absint( $screen_data['page'] ?? 1 );
$total       = absint( $screen_data['total'] ?? 0 );
$total_pages = absint( $screen_data['total_pages'] ?? 1 );
$pagination  = $screen_data['pagination'] ?? [ 'previous' => '', 'next' => '', 'items' => [] ];
$error       = $screen_data['error'] ?? '';
$has_filters = ! empty( $screen_data['has_filters'] );
$icon_url    = static fn( string $name ): string => \Sultana\Admin\Core\Icons::url( $name );

?>
<section class="sultana-admin-customers" aria-label="<?php esc_attr_e( 'Clientes', 'sultana-admin' ); ?>">
    <form class="sultana-admin-search sultana-admin-customer-search" method="get" action="<?php echo esc_url( \Sultana\Admin\Core\Router::customers_url() ); ?>" role="search" data-applied-search="<?php echo esc_attr( $search ); ?>" data-clear-url="<?php echo esc_url( \Sultana\Admin\Core\Router::customers_url() ); ?>">
        <label for="sultana-admin-customer-search"><?php esc_html_e( 'Buscar clientes', 'sultana-admin' ); ?></label>
        <div class="sultana-admin-search__controls">
            <input
                id="sultana-admin-customer-search"
                type="search"
                name="s"
                value="<?php echo esc_attr( $search ); ?>"
                placeholder="<?php esc_attr_e( 'Nombre, email o telefono', 'sultana-admin' ); ?>"
            >
            <button class="sultana-admin-icon-button sultana-admin-search__button" type="submit" aria-label="<?php esc_attr_e( 'Buscar clientes', 'sultana-admin' ); ?>" title="<?php esc_attr_e( 'Buscar clientes', 'sultana-admin' ); ?>" data-search-label="<?php esc_attr_e( 'Buscar clientes', 'sultana-admin' ); ?>" data-clear-label="<?php esc_attr_e( 'Limpiar busqueda', 'sultana-admin' ); ?>" data-search-icon="<?php echo esc_url( $icon_url( 'search' ) ); ?>" data-clear-icon="<?php echo esc_url( $icon_url( 'close' ) ); ?>">
                <span class="sultana-admin-icon" style="--sultana-admin-icon-url: url('<?php echo esc_url( $icon_url( 'search' ) ); ?>');" aria-hidden="true"></span>
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
            /* translators: 1: current page, 2: total pages, 3: total customers. */
            esc_html__( 'Pagina %1$d de %2$d - %3$d clientes', 'sultana-admin' ),
            $page,
            max( 1, $total_pages ),
            $total
        );
        ?>
    </div>

    <?php if ( empty( $customers ) ) : ?>
        <div class="sultana-admin-empty">
            <h2><?php echo esc_html( $has_filters ? __( 'Sin resultados', 'sultana-admin' ) : __( 'No hay clientes todavia', 'sultana-admin' ) ); ?></h2>
            <p><?php echo esc_html( $has_filters ? __( 'No encontramos clientes con esa busqueda.', 'sultana-admin' ) : __( 'Los clientes registrados apareceran aqui.', 'sultana-admin' ) ); ?></p>
        </div>
    <?php else : ?>
        <div class="sultana-admin-customer-table-wrap">
            <table class="sultana-admin-customer-table">
                <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e( 'Cliente', 'sultana-admin' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Contacto', 'sultana-admin' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Pedidos', 'sultana-admin' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Total comprado', 'sultana-admin' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Ultimo pedido', 'sultana-admin' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Accion', 'sultana-admin' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $customers as $customer ) : ?>
                        <tr>
                            <td><strong><?php echo esc_html( $customer['name'] ); ?></strong></td>
                            <td>
                                <span><?php echo esc_html( $customer['email'] ); ?></span>
                                <small><?php echo esc_html( '' !== $customer['phone'] ? $customer['phone'] : __( 'Sin telefono', 'sultana-admin' ) ); ?></small>
                            </td>
                            <td><?php echo esc_html( (string) $customer['orders_count'] ); ?></td>
                            <td><?php echo wp_kses_post( $customer['total_spent'] ); ?></td>
                            <td><?php echo esc_html( $customer['last_order'] ); ?></td>
                            <td>
                                <a class="sultana-admin-icon-button sultana-admin-customer-view-action" href="<?php echo esc_url( $customer['view_url'] ); ?>" aria-label="<?php esc_attr_e( 'Ver cliente', 'sultana-admin' ); ?>" title="<?php esc_attr_e( 'Ver cliente', 'sultana-admin' ); ?>">
                                    <span class="sultana-admin-icon" style="--sultana-admin-icon-url: url('<?php echo esc_url( $icon_url( 'user' ) ); ?>');" aria-hidden="true"></span>
                                    <span class="sultana-admin-customer-view-action__text"><?php esc_html_e( 'Ver', 'sultana-admin' ); ?></span>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="sultana-admin-customer-cards">
            <?php foreach ( $customers as $customer ) : ?>
                <?php $panel_id = 'sultana-admin-customer-card-panel-' . absint( $customer['id'] ); ?>
                <article class="sultana-admin-customer-card">
                    <button class="sultana-admin-customer-card__header" type="button" aria-expanded="false" aria-controls="<?php echo esc_attr( $panel_id ); ?>">
                        <span class="sultana-admin-customer-card__identity">
                            <span class="sultana-admin-customer-card__name"><?php echo esc_html( $customer['name'] ); ?></span>
                            <span class="sultana-admin-customer-card__summary"><?php echo esc_html( $customer['orders_label'] ); ?> &middot; <?php echo wp_kses_post( $customer['total_spent'] ); ?></span>
                        </span>
                        <span class="sultana-admin-customer-card__chevron sultana-admin-icon" style="--sultana-admin-icon-url: url('<?php echo esc_url( $icon_url( 'chevron-right' ) ); ?>');" aria-hidden="true"></span>
                    </button>
                    <div id="<?php echo esc_attr( $panel_id ); ?>" class="sultana-admin-customer-card__panel" hidden>
                        <dl>
                            <div>
                                <dt><?php esc_html_e( 'Email', 'sultana-admin' ); ?></dt>
                                <dd><?php echo esc_html( $customer['email'] ); ?></dd>
                            </div>
                            <div>
                                <dt><?php esc_html_e( 'Telefono', 'sultana-admin' ); ?></dt>
                                <dd><?php echo esc_html( '' !== $customer['phone'] ? $customer['phone'] : __( 'Sin telefono', 'sultana-admin' ) ); ?></dd>
                            </div>
                            <div>
                                <dt><?php esc_html_e( 'Ultimo pedido', 'sultana-admin' ); ?></dt>
                                <dd><?php echo esc_html( $customer['last_order'] ); ?></dd>
                            </div>
                        </dl>
                        <a class="sultana-admin-icon-button sultana-admin-customer-view-action" href="<?php echo esc_url( $customer['view_url'] ); ?>" aria-label="<?php esc_attr_e( 'Ver cliente', 'sultana-admin' ); ?>" title="<?php esc_attr_e( 'Ver cliente', 'sultana-admin' ); ?>">
                            <span class="sultana-admin-icon" style="--sultana-admin-icon-url: url('<?php echo esc_url( $icon_url( 'user' ) ); ?>');" aria-hidden="true"></span>
                            <span class="sultana-admin-customer-view-action__text"><?php esc_html_e( 'Ver cliente', 'sultana-admin' ); ?></span>
                        </a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ( $total_pages > 1 && ! empty( $pagination['items'] ) ) : ?>
    <nav class="sultana-admin-pagination sultana-admin-pagination--compact" aria-label="<?php esc_attr_e( 'Paginacion de clientes', 'sultana-admin' ); ?>">
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

<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'sultana_admin_product_image' ) ) {
    function sultana_admin_product_image( array $product ): void
    {
        if ( ! empty( $product['image_url'] ) ) {
            ?>
            <img class="sultana-admin-product-image" src="<?php echo esc_url( $product['image_url'] ); ?>" alt="" loading="lazy">
            <?php
            return;
        }

        ?>
        <span class="sultana-admin-product-image sultana-admin-product-image--empty" aria-hidden="true"></span>
        <?php
    }
}

if ( ! function_exists( 'sultana_admin_inventory_status_badge' ) ) {
    function sultana_admin_inventory_status_badge( array $product ): void
    {
        $flags = is_array( $product['inventory_flags'] ?? null ) ? $product['inventory_flags'] : [];
        $class = ! empty( $flags['outofstock'] ) ? 'sultana-admin-badge--danger' : 'sultana-admin-badge--warning';

        ?>
        <span class="<?php echo esc_attr( 'sultana-admin-badge sultana-admin-inventory-status ' . $class ); ?>">
            <?php echo esc_html( (string) ( $product['inventory_status'] ?? '' ) ); ?>
        </span>
        <?php
    }
}

if ( ! function_exists( 'sultana_admin_inventory_show_detail' ) ) {
    function sultana_admin_inventory_show_detail( array $product ): bool
    {
        return 'variable' === (string) ( $product['type_key'] ?? '' ) && '' !== (string) ( $product['inventory_detail'] ?? '' );
    }
}

$products    = $screen_data['products'] ?? [];
$search      = $screen_data['search'] ?? '';
$filter      = (string) ( $screen_data['filter'] ?? 'attention' );
$filters     = is_array( $screen_data['filters'] ?? null ) ? $screen_data['filters'] : [];
$page        = absint( $screen_data['page'] ?? 1 );
$total       = absint( $screen_data['total'] ?? 0 );
$total_pages = absint( $screen_data['total_pages'] ?? 1 );
$pagination  = $screen_data['pagination'] ?? [ 'previous' => '', 'next' => '', 'items' => [] ];
$icon_url    = static fn( string $name ): string => \Sultana\Admin\Core\Icons::url( $name );

$filter_items = [
    'attention'  => __( 'Requieren atencion', 'sultana-admin' ),
    'outofstock' => __( 'Sin existencias', 'sultana-admin' ),
    'low_stock'  => __( 'Pocas unidades', 'sultana-admin' ),
];

?>
<section class="sultana-admin-products sultana-admin-products--inventory">
    <div class="sultana-admin-section-header">
        <div class="sultana-admin-section-header__content">
            <div class="sultana-admin-inventory-title">
                <h2><?php esc_html_e( 'Inventario', 'sultana-admin' ); ?></h2>
                <span class="sultana-admin-inventory-help" data-inventory-help>
                    <button class="sultana-admin-inventory-help__button" type="button" aria-label="<?php esc_attr_e( 'Ver ayuda de inventario', 'sultana-admin' ); ?>" aria-expanded="false" aria-controls="sultana-admin-inventory-help-popover" data-inventory-help-toggle>
                        <span aria-hidden="true">!</span>
                    </button>
                    <span id="sultana-admin-inventory-help-popover" class="sultana-admin-inventory-help__popover" role="tooltip" hidden data-inventory-help-popover>
                        <?php esc_html_e( 'Productos y variaciones que necesitan revision por stock bajo o agotado.', 'sultana-admin' ); ?>
                    </span>
                </span>
            </div>
        </div>
        <a class="sultana-admin-secondary-action" href="<?php echo esc_url( \Sultana\Admin\Core\Router::products_url() ); ?>">
            <span class="sultana-admin-icon" style="--sultana-admin-icon-url: url('<?php echo esc_url( $icon_url( 'box' ) ); ?>');" aria-hidden="true"></span>
            <?php esc_html_e( 'Todos', 'sultana-admin' ); ?>
        </a>
    </div>

    <nav class="sultana-admin-actions" aria-label="<?php esc_attr_e( 'Filtros de inventario', 'sultana-admin' ); ?>">
        <?php foreach ( $filter_items as $filter_key => $filter_label ) : ?>
            <?php
            $url = \Sultana\Admin\Core\Router::product_inventory_url();

            if ( '' !== $search ) {
                $url = add_query_arg( 's', $search, $url );
            }

            if ( 'attention' !== $filter_key ) {
                $url = add_query_arg( 'inventory_filter', $filter_key, $url );
            }
            ?>
            <a class="<?php echo esc_attr( 'sultana-admin-muted-action' . ( $filter === $filter_key ? ' is-active' : '' ) ); ?>" href="<?php echo esc_url( $url ); ?>" <?php echo $filter === $filter_key ? 'aria-current="page"' : ''; ?>>
                <?php
                printf(
                    /* translators: 1: inventory filter label, 2: filtered product count. */
                    esc_html__( '%1$s (%2$d)', 'sultana-admin' ),
                    esc_html( $filter_label ),
                    absint( $filters[ $filter_key ] ?? 0 )
                );
                ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <form class="sultana-admin-search" method="get" action="<?php echo esc_url( \Sultana\Admin\Core\Router::product_inventory_url() ); ?>" role="search" data-applied-search="<?php echo esc_attr( $search ); ?>" data-clear-url="<?php echo esc_url( \Sultana\Admin\Core\Router::product_inventory_url() ); ?>">
        <div class="sultana-admin-section-header sultana-admin-search__header">
            <label for="sultana-admin-inventory-search"><?php esc_html_e( 'Buscar en inventario', 'sultana-admin' ); ?></label>
        </div>
        <div class="sultana-admin-search__controls">
            <?php if ( 'attention' !== $filter ) : ?>
                <input type="hidden" name="inventory_filter" value="<?php echo esc_attr( $filter ); ?>">
            <?php endif; ?>
            <input
                id="sultana-admin-inventory-search"
                type="search"
                name="s"
                value="<?php echo esc_attr( $search ); ?>"
                placeholder="<?php esc_attr_e( 'Nombre o SKU', 'sultana-admin' ); ?>"
            >
            <button class="sultana-admin-icon-button sultana-admin-search__button" type="submit" aria-label="<?php esc_attr_e( 'Buscar productos', 'sultana-admin' ); ?>" title="<?php esc_attr_e( 'Buscar productos', 'sultana-admin' ); ?>" data-search-label="<?php esc_attr_e( 'Buscar productos', 'sultana-admin' ); ?>" data-clear-label="<?php esc_attr_e( 'Limpiar busqueda', 'sultana-admin' ); ?>" data-search-icon="<?php echo esc_url( $icon_url( 'search' ) ); ?>" data-clear-icon="<?php echo esc_url( $icon_url( 'close' ) ); ?>">
                <span class="sultana-admin-icon" style="--sultana-admin-icon-url: url('<?php echo esc_url( $icon_url( 'search' ) ); ?>');" aria-hidden="true"></span>
            </button>
        </div>
    </form>

    <div class="sultana-admin-list-summary" aria-live="polite">
        <?php
        printf(
            /* translators: 1: current page, 2: total pages, 3: total products. */
            esc_html__( 'Pagina %1$d de %2$d / %3$d productos', 'sultana-admin' ),
            $page,
            max( 1, $total_pages ),
            $total
        );
        ?>
    </div>

    <?php if ( empty( $products ) ) : ?>
        <div class="sultana-admin-empty">
            <h2><?php esc_html_e( 'Sin productos por atender', 'sultana-admin' ); ?></h2>
            <p><?php esc_html_e( 'No se encontraron productos con el filtro actual.', 'sultana-admin' ); ?></p>
        </div>
    <?php else : ?>
        <div class="sultana-admin-product-table-wrap">
            <table class="sultana-admin-product-table sultana-admin-product-table--inventory">
                <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e( 'Imagen', 'sultana-admin' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Producto', 'sultana-admin' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Tipo', 'sultana-admin' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Inventario', 'sultana-admin' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Estado', 'sultana-admin' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Accion', 'sultana-admin' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $products as $product ) : ?>
                        <tr>
                            <td><?php sultana_admin_product_image( $product ); ?></td>
                            <td>
                                <strong><?php echo esc_html( $product['name'] ); ?></strong>
                                <small><?php echo esc_html( '' !== $product['sku'] ? $product['sku'] : __( 'Sin SKU', 'sultana-admin' ) ); ?></small>
                            </td>
                            <td><?php echo esc_html( $product['type'] ); ?></td>
                            <td>
                                <strong><?php echo esc_html( $product['inventory_summary'] ); ?></strong>
                                <?php if ( sultana_admin_inventory_show_detail( $product ) ) : ?>
                                    <small><?php echo esc_html( $product['inventory_detail'] ); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php sultana_admin_inventory_status_badge( $product ); ?></td>
                            <td>
                                <div class="sultana-admin-row-actions">
                                    <?php if ( ! empty( $product['can_edit'] ) ) : ?>
                                        <a class="sultana-admin-icon-button sultana-admin-icon-button--success" href="<?php echo esc_url( \Sultana\Admin\Core\Router::edit_product_url( $product['id'] ) ); ?>" aria-label="<?php esc_attr_e( 'Editar producto', 'sultana-admin' ); ?>" title="<?php esc_attr_e( 'Editar producto', 'sultana-admin' ); ?>">
                                            <span class="sultana-admin-icon" style="--sultana-admin-icon-url: url('<?php echo esc_url( $icon_url( 'pencil' ) ); ?>');" aria-hidden="true"></span>
                                        </a>
                                    <?php else : ?>
                                        <button class="sultana-admin-icon-button sultana-admin-icon-button--success" type="button" disabled aria-disabled="true" aria-label="<?php esc_attr_e( 'Editar producto', 'sultana-admin' ); ?>" title="<?php esc_attr_e( 'Editar producto', 'sultana-admin' ); ?>">
                                            <span class="sultana-admin-icon" style="--sultana-admin-icon-url: url('<?php echo esc_url( $icon_url( 'pencil' ) ); ?>');" aria-hidden="true"></span>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="sultana-admin-product-cards">
            <?php foreach ( $products as $product ) : ?>
                <?php $panel_id = 'sultana-admin-inventory-card-panel-' . absint( $product['id'] ); ?>
                <article class="sultana-admin-product-card">
                    <button class="sultana-admin-product-card__header" type="button" aria-expanded="false" aria-controls="<?php echo esc_attr( $panel_id ); ?>">
                        <?php sultana_admin_product_image( $product ); ?>
                        <span class="sultana-admin-product-card__identity">
                            <span class="sultana-admin-product-card__name"><?php echo esc_html( $product['name'] ); ?></span>
                            <span class="sultana-admin-product-card__meta">
                                <span class="sultana-admin-product-card__sku"><?php echo esc_html( '' !== $product['sku'] ? $product['sku'] : __( 'Sin SKU', 'sultana-admin' ) ); ?></span>
                                <?php sultana_admin_inventory_status_badge( $product ); ?>
                            </span>
                        </span>
                        <span class="sultana-admin-product-card__chevron sultana-admin-icon" style="--sultana-admin-icon-url: url('<?php echo esc_url( $icon_url( 'chevron-right' ) ); ?>');" aria-hidden="true"></span>
                    </button>
                    <div id="<?php echo esc_attr( $panel_id ); ?>" class="sultana-admin-product-card__panel" hidden>
                        <dl>
                            <div>
                                <dt><?php esc_html_e( 'Tipo', 'sultana-admin' ); ?></dt>
                                <dd><?php echo esc_html( $product['type'] ); ?></dd>
                            </div>
                            <div>
                                <dt><?php esc_html_e( 'Inventario', 'sultana-admin' ); ?></dt>
                                <dd>
                                    <?php echo esc_html( $product['inventory_summary'] ); ?>
                                    <?php if ( sultana_admin_inventory_show_detail( $product ) ) : ?>
                                        <small><?php echo esc_html( $product['inventory_detail'] ); ?></small>
                                    <?php endif; ?>
                                </dd>
                            </div>
                        </dl>
                        <div class="sultana-admin-card-actions">
                            <?php if ( ! empty( $product['can_edit'] ) ) : ?>
                                <a class="sultana-admin-icon-button sultana-admin-icon-button--success" href="<?php echo esc_url( \Sultana\Admin\Core\Router::edit_product_url( $product['id'] ) ); ?>" aria-label="<?php esc_attr_e( 'Editar producto', 'sultana-admin' ); ?>" title="<?php esc_attr_e( 'Editar producto', 'sultana-admin' ); ?>">
                                    <span class="sultana-admin-icon" style="--sultana-admin-icon-url: url('<?php echo esc_url( $icon_url( 'pencil' ) ); ?>');" aria-hidden="true"></span>
                                </a>
                            <?php else : ?>
                                <button class="sultana-admin-icon-button sultana-admin-icon-button--success" type="button" disabled aria-disabled="true" aria-label="<?php esc_attr_e( 'Editar producto', 'sultana-admin' ); ?>" title="<?php esc_attr_e( 'Editar producto', 'sultana-admin' ); ?>">
                                    <span class="sultana-admin-icon" style="--sultana-admin-icon-url: url('<?php echo esc_url( $icon_url( 'pencil' ) ); ?>');" aria-hidden="true"></span>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ( $total_pages > 1 && ! empty( $pagination['items'] ) ) : ?>
    <nav class="sultana-admin-pagination sultana-admin-pagination--compact" aria-label="<?php esc_attr_e( 'Paginacion de inventario', 'sultana-admin' ); ?>">
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

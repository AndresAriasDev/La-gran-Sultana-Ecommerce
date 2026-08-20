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

$products    = $screen_data['products'] ?? [];
$search      = $screen_data['search'] ?? '';
$page        = absint( $screen_data['page'] ?? 1 );
$total       = absint( $screen_data['total'] ?? 0 );
$total_pages = absint( $screen_data['total_pages'] ?? 1 );
$pagination  = $screen_data['pagination'] ?? [ 'previous' => '', 'next' => '' ];
$notice      = $screen_data['notice'] ?? '';
$errors      = $screen_data['errors'] ?? [];
$icon_url    = static fn( string $name ): string => \Sultana\Admin\Core\Icons::url( $name );

?>
<section class="sultana-admin-products">
    <?php if ( '' !== $notice ) : ?>
        <div class="sultana-admin-notice" role="status">
            <?php echo esc_html( $notice ); ?>
        </div>
    <?php endif; ?>

    <?php if ( ! empty( $errors ) ) : ?>
        <div class="sultana-admin-error-list" role="alert">
            <strong><?php esc_html_e( 'No se pudo eliminar el producto', 'sultana-admin' ); ?></strong>
            <ul>
                <?php foreach ( $errors as $error ) : ?>
                    <li><?php echo esc_html( $error ); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form class="sultana-admin-search" method="get" action="<?php echo esc_url( \Sultana\Admin\Core\Router::products_url() ); ?>" role="search">
        <div class="sultana-admin-section-header sultana-admin-search__header">
            <label for="sultana-admin-product-search"><?php esc_html_e( 'Buscar productos', 'sultana-admin' ); ?></label>
            <a class="sultana-admin-secondary-action" href="<?php echo esc_url( \Sultana\Admin\Core\Router::new_product_url() ); ?>">
                <span class="sultana-admin-icon" style="--sultana-admin-icon-url: url('<?php echo esc_url( $icon_url( 'box' ) ); ?>');" aria-hidden="true"></span>
                <?php esc_html_e( 'Nuevo', 'sultana-admin' ); ?>
            </a>
        </div>
        <div class="sultana-admin-search__controls">
            <input
                id="sultana-admin-product-search"
                type="search"
                name="s"
                value="<?php echo esc_attr( $search ); ?>"
                placeholder="<?php esc_attr_e( 'Nombre o SKU', 'sultana-admin' ); ?>"
            >
            <button class="sultana-admin-icon-button" type="submit" aria-label="<?php esc_attr_e( 'Buscar productos', 'sultana-admin' ); ?>" title="<?php esc_attr_e( 'Buscar productos', 'sultana-admin' ); ?>">
                <span class="sultana-admin-icon" style="--sultana-admin-icon-url: url('<?php echo esc_url( $icon_url( 'search' ) ); ?>');" aria-hidden="true"></span>
            </button>
        </div>
    </form>

    <div class="sultana-admin-list-summary" aria-live="polite">
        <?php
        printf(
            /* translators: 1: current page, 2: total pages, 3: total products. */
            esc_html__( 'Página %1$d de %2$d · %3$d productos', 'sultana-admin' ),
            $page,
            max( 1, $total_pages ),
            $total
        );
        ?>
    </div>

    <?php if ( empty( $products ) ) : ?>
        <div class="sultana-admin-empty">
            <h2><?php esc_html_e( 'Sin resultados', 'sultana-admin' ); ?></h2>
            <p><?php esc_html_e( 'No se encontraron productos con los filtros actuales.', 'sultana-admin' ); ?></p>
        </div>
    <?php else : ?>
        <div class="sultana-admin-product-table-wrap">
            <table class="sultana-admin-product-table">
                <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e( 'Imagen', 'sultana-admin' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Producto', 'sultana-admin' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Tipo', 'sultana-admin' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Precio', 'sultana-admin' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Stock', 'sultana-admin' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Estado', 'sultana-admin' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Acción', 'sultana-admin' ); ?></th>
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
                            <td><?php echo '' !== $product['price'] ? wp_kses_post( $product['price'] ) : '&mdash;'; ?></td>
                            <td><?php echo esc_html( $product['stock'] ); ?></td>
                            <td><?php echo esc_html( $product['status'] ); ?></td>
                            <td>
                                <div class="sultana-admin-row-actions">
                                <?php if ( ! empty( $product['can_delete'] ) ) : ?>
                                    <form class="sultana-admin-icon-action-form" method="post" action="<?php echo esc_url( \Sultana\Admin\Core\Router::products_url() ); ?>" onsubmit="return confirm('<?php echo esc_js( __( '¿Eliminar este producto? El producto será enviado a la papelera y dejará de mostrarse en la tienda.', 'sultana-admin' ) ); ?>');">
                                        <input type="hidden" name="sultana_admin_action" value="trash_product">
                                        <input type="hidden" name="product_id" value="<?php echo esc_attr( (string) $product['id'] ); ?>">
                                        <?php wp_nonce_field( \Sultana\Admin\Products\ProductController::TRASH_NONCE_ACTION, 'sultana_admin_trash_nonce' ); ?>
                                        <button class="sultana-admin-icon-button sultana-admin-icon-button--danger" type="submit" aria-label="<?php esc_attr_e( 'Eliminar producto', 'sultana-admin' ); ?>" title="<?php esc_attr_e( 'Eliminar producto', 'sultana-admin' ); ?>">
                                            <span class="sultana-admin-icon" style="--sultana-admin-icon-url: url('<?php echo esc_url( $icon_url( 'trash' ) ); ?>');" aria-hidden="true"></span>
                                        </button>
                                    </form>
                                <?php else : ?>
                                    <button class="sultana-admin-icon-button sultana-admin-icon-button--danger" type="button" disabled aria-disabled="true" aria-label="<?php esc_attr_e( 'Eliminar producto', 'sultana-admin' ); ?>" title="<?php esc_attr_e( 'Eliminar producto', 'sultana-admin' ); ?>">
                                        <span class="sultana-admin-icon" style="--sultana-admin-icon-url: url('<?php echo esc_url( $icon_url( 'trash' ) ); ?>');" aria-hidden="true"></span>
                                    </button>
                                <?php endif; ?>

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
                <article class="sultana-admin-product-card">
                    <div class="sultana-admin-product-card__header">
                        <?php sultana_admin_product_image( $product ); ?>
                        <div>
                            <h2><?php echo esc_html( $product['name'] ); ?></h2>
                            <p><?php echo esc_html( '' !== $product['sku'] ? $product['sku'] : __( 'Sin SKU', 'sultana-admin' ) ); ?></p>
                        </div>
                    </div>
                    <dl>
                        <div>
                            <dt><?php esc_html_e( 'Tipo', 'sultana-admin' ); ?></dt>
                            <dd><?php echo esc_html( $product['type'] ); ?></dd>
                        </div>
                        <div>
                            <dt><?php esc_html_e( 'Precio', 'sultana-admin' ); ?></dt>
                            <dd><?php echo '' !== $product['price'] ? wp_kses_post( $product['price'] ) : '&mdash;'; ?></dd>
                        </div>
                        <div>
                            <dt><?php esc_html_e( 'Stock', 'sultana-admin' ); ?></dt>
                            <dd><?php echo esc_html( $product['stock'] ); ?></dd>
                        </div>
                        <div>
                            <dt><?php esc_html_e( 'Estado', 'sultana-admin' ); ?></dt>
                            <dd><?php echo esc_html( $product['status'] ); ?></dd>
                        </div>
                    </dl>
                    <div class="sultana-admin-card-actions">
                        <?php if ( ! empty( $product['can_delete'] ) ) : ?>
                            <form class="sultana-admin-icon-action-form" method="post" action="<?php echo esc_url( \Sultana\Admin\Core\Router::products_url() ); ?>" onsubmit="return confirm('<?php echo esc_js( __( '¿Eliminar este producto? El producto será enviado a la papelera y dejará de mostrarse en la tienda.', 'sultana-admin' ) ); ?>');">
                                <input type="hidden" name="sultana_admin_action" value="trash_product">
                                <input type="hidden" name="product_id" value="<?php echo esc_attr( (string) $product['id'] ); ?>">
                                <?php wp_nonce_field( \Sultana\Admin\Products\ProductController::TRASH_NONCE_ACTION, 'sultana_admin_trash_nonce' ); ?>
                                <button class="sultana-admin-icon-button sultana-admin-icon-button--danger" type="submit" aria-label="<?php esc_attr_e( 'Eliminar producto', 'sultana-admin' ); ?>" title="<?php esc_attr_e( 'Eliminar producto', 'sultana-admin' ); ?>">
                                    <span class="sultana-admin-icon" style="--sultana-admin-icon-url: url('<?php echo esc_url( $icon_url( 'trash' ) ); ?>');" aria-hidden="true"></span>
                                </button>
                            </form>
                        <?php else : ?>
                            <button class="sultana-admin-icon-button sultana-admin-icon-button--danger" type="button" disabled aria-disabled="true" aria-label="<?php esc_attr_e( 'Eliminar producto', 'sultana-admin' ); ?>" title="<?php esc_attr_e( 'Eliminar producto', 'sultana-admin' ); ?>">
                                <span class="sultana-admin-icon" style="--sultana-admin-icon-url: url('<?php echo esc_url( $icon_url( 'trash' ) ); ?>');" aria-hidden="true"></span>
                            </button>
                        <?php endif; ?>

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
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <nav class="sultana-admin-pagination" aria-label="<?php esc_attr_e( 'Paginación de productos', 'sultana-admin' ); ?>">
        <?php if ( ! empty( $pagination['previous'] ) ) : ?>
            <a href="<?php echo esc_url( $pagination['previous'] ); ?>" aria-label="<?php esc_attr_e( 'Página anterior', 'sultana-admin' ); ?>" title="<?php esc_attr_e( 'Página anterior', 'sultana-admin' ); ?>"><span class="sultana-admin-icon" style="--sultana-admin-icon-url: url('<?php echo esc_url( $icon_url( 'chevron-left' ) ); ?>');" aria-hidden="true"></span></a>
        <?php else : ?>
            <span aria-disabled="true" aria-label="<?php esc_attr_e( 'Página anterior', 'sultana-admin' ); ?>"><span class="sultana-admin-icon" style="--sultana-admin-icon-url: url('<?php echo esc_url( $icon_url( 'chevron-left' ) ); ?>');" aria-hidden="true"></span></span>
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
            <a href="<?php echo esc_url( $pagination['next'] ); ?>" aria-label="<?php esc_attr_e( 'Página siguiente', 'sultana-admin' ); ?>" title="<?php esc_attr_e( 'Página siguiente', 'sultana-admin' ); ?>"><span class="sultana-admin-icon" style="--sultana-admin-icon-url: url('<?php echo esc_url( $icon_url( 'chevron-right' ) ); ?>');" aria-hidden="true"></span></a>
        <?php else : ?>
            <span aria-disabled="true" aria-label="<?php esc_attr_e( 'Página siguiente', 'sultana-admin' ); ?>"><span class="sultana-admin-icon" style="--sultana-admin-icon-url: url('<?php echo esc_url( $icon_url( 'chevron-right' ) ); ?>');" aria-hidden="true"></span></span>
        <?php endif; ?>
    </nav>
</section>

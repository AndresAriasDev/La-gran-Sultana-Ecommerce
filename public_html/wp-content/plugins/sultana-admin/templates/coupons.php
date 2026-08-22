<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$coupons     = $screen_data['coupons'] ?? [];
$search      = $screen_data['search'] ?? '';
$page        = absint( $screen_data['page'] ?? 1 );
$total       = absint( $screen_data['total'] ?? 0 );
$total_pages = absint( $screen_data['total_pages'] ?? 1 );
$pagination  = $screen_data['pagination'] ?? [ 'previous' => '', 'next' => '', 'items' => [] ];
$notice      = $screen_data['notice'] ?? '';
$errors      = $screen_data['errors'] ?? [];
$icon_url    = static fn ( string $name ): string => \Sultana\Admin\Core\Icons::url( $name );

?>
<section class="sultana-admin-coupons" aria-label="<?php esc_attr_e( 'Cupones', 'sultana-admin' ); ?>">
    <?php if ( '' !== $notice ) : ?>
        <div class="sultana-admin-notice" role="status"><?php echo esc_html( $notice ); ?></div>
    <?php endif; ?>

    <?php if ( ! empty( $errors ) ) : ?>
        <div class="sultana-admin-error-list" role="alert">
            <strong><?php esc_html_e( 'No se pudo eliminar el cupon', 'sultana-admin' ); ?></strong>
            <ul>
                <?php foreach ( $errors as $error ) : ?>
                    <li><?php echo esc_html( $error ); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form class="sultana-admin-search sultana-admin-coupon-search" method="get" action="<?php echo esc_url( \Sultana\Admin\Core\Router::coupons_url() ); ?>" role="search" data-applied-search="<?php echo esc_attr( $search ); ?>" data-clear-url="<?php echo esc_url( \Sultana\Admin\Core\Router::coupons_url() ); ?>">
        <div class="sultana-admin-section-header sultana-admin-search__header">
            <label for="sultana-admin-coupon-search"><?php esc_html_e( 'Buscar cupones', 'sultana-admin' ); ?></label>
            <a class="sultana-admin-secondary-action" href="<?php echo esc_url( \Sultana\Admin\Core\Router::new_coupon_url() ); ?>">
                <span class="sultana-admin-icon" style="--sultana-admin-icon-url: url('<?php echo esc_url( $icon_url( 'tickets' ) ); ?>');" aria-hidden="true"></span>
                <?php esc_html_e( 'Nuevo', 'sultana-admin' ); ?>
            </a>
        </div>
        <div class="sultana-admin-search__controls">
            <input id="sultana-admin-coupon-search" type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Codigo o descripcion', 'sultana-admin' ); ?>">
            <button class="sultana-admin-icon-button sultana-admin-search__button" type="submit" aria-label="<?php esc_attr_e( 'Buscar cupones', 'sultana-admin' ); ?>" title="<?php esc_attr_e( 'Buscar cupones', 'sultana-admin' ); ?>" data-search-label="<?php esc_attr_e( 'Buscar cupones', 'sultana-admin' ); ?>" data-clear-label="<?php esc_attr_e( 'Limpiar busqueda', 'sultana-admin' ); ?>" data-search-icon="<?php echo esc_url( $icon_url( 'search' ) ); ?>" data-clear-icon="<?php echo esc_url( $icon_url( 'close' ) ); ?>">
                <span class="sultana-admin-icon" style="--sultana-admin-icon-url: url('<?php echo esc_url( $icon_url( 'search' ) ); ?>');" aria-hidden="true"></span>
            </button>
        </div>
    </form>

    <div class="sultana-admin-list-summary" aria-live="polite">
        <?php
        printf(
            esc_html__( 'Pagina %1$d de %2$d - %3$d cupones', 'sultana-admin' ),
            $page,
            max( 1, $total_pages ),
            $total
        );
        ?>
    </div>

    <?php if ( empty( $coupons ) ) : ?>
        <div class="sultana-admin-empty">
            <h2><?php esc_html_e( 'Sin resultados', 'sultana-admin' ); ?></h2>
            <p><?php esc_html_e( 'No se encontraron cupones con los filtros actuales.', 'sultana-admin' ); ?></p>
        </div>
    <?php else : ?>
        <div class="sultana-admin-coupon-table-wrap">
            <table class="sultana-admin-coupon-table">
                <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e( 'Codigo', 'sultana-admin' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Tipo de descuento', 'sultana-admin' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Valor', 'sultana-admin' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Usos', 'sultana-admin' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Vencimiento', 'sultana-admin' ); ?></th>
                        <th scope="col"><?php esc_html_e( 'Accion', 'sultana-admin' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $coupons as $coupon ) : ?>
                        <tr>
                            <td><span class="sultana-admin-coupon-code"><?php echo esc_html( $coupon['code'] ); ?></span></td>
                            <td><?php echo esc_html( $coupon['type'] ); ?></td>
                            <td><?php echo wp_kses_post( $coupon['amount'] ); ?></td>
                            <td><?php echo esc_html( $coupon['usage'] ); ?></td>
                            <td><?php echo esc_html( $coupon['expires'] ); ?></td>
                            <td>
                                <div class="sultana-admin-row-actions">
                                    <?php if ( ! empty( $coupon['can_delete'] ) ) : ?>
                                        <form class="sultana-admin-icon-action-form" method="post" action="<?php echo esc_url( \Sultana\Admin\Core\Router::coupons_url() ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Eliminar este cupon? Sera enviado a la papelera.', 'sultana-admin' ) ); ?>');">
                                            <input type="hidden" name="sultana_admin_action" value="trash_coupon">
                                            <input type="hidden" name="coupon_id" value="<?php echo esc_attr( (string) $coupon['id'] ); ?>">
                                            <?php wp_nonce_field( \Sultana\Admin\Coupons\CouponController::TRASH_NONCE_ACTION, 'sultana_admin_trash_nonce' ); ?>
                                            <button class="sultana-admin-icon-button sultana-admin-icon-button--danger" type="submit" aria-label="<?php esc_attr_e( 'Eliminar cupon', 'sultana-admin' ); ?>" title="<?php esc_attr_e( 'Eliminar cupon', 'sultana-admin' ); ?>">
                                                <span class="sultana-admin-icon" style="--sultana-admin-icon-url: url('<?php echo esc_url( $icon_url( 'trash' ) ); ?>');" aria-hidden="true"></span>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <a class="sultana-admin-icon-button sultana-admin-icon-button--success" href="<?php echo esc_url( $coupon['edit_url'] ); ?>" aria-label="<?php esc_attr_e( 'Editar cupon', 'sultana-admin' ); ?>" title="<?php esc_attr_e( 'Editar cupon', 'sultana-admin' ); ?>">
                                        <span class="sultana-admin-icon" style="--sultana-admin-icon-url: url('<?php echo esc_url( $icon_url( 'pencil' ) ); ?>');" aria-hidden="true"></span>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="sultana-admin-coupon-cards">
            <?php foreach ( $coupons as $coupon ) : ?>
                <article class="sultana-admin-coupon-card">
                    <div class="sultana-admin-coupon-card__header">
                        <span class="sultana-admin-coupon-code"><?php echo esc_html( $coupon['code'] ); ?></span>
                        <strong><?php echo wp_kses_post( $coupon['amount'] ); ?></strong>
                    </div>
                    <dl>
                        <div><dt><?php esc_html_e( 'Tipo', 'sultana-admin' ); ?></dt><dd><?php echo esc_html( $coupon['type'] ); ?></dd></div>
                        <div><dt><?php esc_html_e( 'Usos', 'sultana-admin' ); ?></dt><dd><?php echo esc_html( $coupon['usage'] ); ?></dd></div>
                        <div><dt><?php esc_html_e( 'Vencimiento', 'sultana-admin' ); ?></dt><dd><?php echo esc_html( $coupon['expires'] ); ?></dd></div>
                    </dl>
                    <div class="sultana-admin-card-actions">
                        <?php if ( ! empty( $coupon['can_delete'] ) ) : ?>
                            <form class="sultana-admin-icon-action-form" method="post" action="<?php echo esc_url( \Sultana\Admin\Core\Router::coupons_url() ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Eliminar este cupon? Sera enviado a la papelera.', 'sultana-admin' ) ); ?>');">
                                <input type="hidden" name="sultana_admin_action" value="trash_coupon">
                                <input type="hidden" name="coupon_id" value="<?php echo esc_attr( (string) $coupon['id'] ); ?>">
                                <?php wp_nonce_field( \Sultana\Admin\Coupons\CouponController::TRASH_NONCE_ACTION, 'sultana_admin_trash_nonce' ); ?>
                                <button class="sultana-admin-icon-button sultana-admin-icon-button--danger" type="submit" aria-label="<?php esc_attr_e( 'Eliminar cupon', 'sultana-admin' ); ?>" title="<?php esc_attr_e( 'Eliminar cupon', 'sultana-admin' ); ?>">
                                    <span class="sultana-admin-icon" style="--sultana-admin-icon-url: url('<?php echo esc_url( $icon_url( 'trash' ) ); ?>');" aria-hidden="true"></span>
                                </button>
                            </form>
                        <?php endif; ?>
                        <a class="sultana-admin-icon-button sultana-admin-icon-button--success" href="<?php echo esc_url( $coupon['edit_url'] ); ?>" aria-label="<?php esc_attr_e( 'Editar cupon', 'sultana-admin' ); ?>" title="<?php esc_attr_e( 'Editar cupon', 'sultana-admin' ); ?>">
                            <span class="sultana-admin-icon" style="--sultana-admin-icon-url: url('<?php echo esc_url( $icon_url( 'pencil' ) ); ?>');" aria-hidden="true"></span>
                        </a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ( $total_pages > 1 && ! empty( $pagination['items'] ) ) : ?>
        <nav class="sultana-admin-pagination sultana-admin-pagination--compact" aria-label="<?php esc_attr_e( 'Paginacion de cupones', 'sultana-admin' ); ?>">
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

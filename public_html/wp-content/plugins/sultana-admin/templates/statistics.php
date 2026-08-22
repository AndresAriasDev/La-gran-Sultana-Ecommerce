<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$periods     = $screen_data['periods'] ?? [];
$metrics     = $screen_data['metrics'] ?? [];
$sales_trend = $screen_data['sales_trend'] ?? [ 'points' => [], 'max' => 0, 'empty' => true ];
$top_products = $screen_data['top_products'] ?? [];
$order_statuses = $screen_data['order_statuses'] ?? [ 'items' => [], 'empty' => true ];
$best_customers = $screen_data['best_customers'] ?? [];
$range_label = $screen_data['range_label'] ?? '';
$error       = $screen_data['error'] ?? '';
$sultana_admin_visible_statuses = array_values(
    array_filter(
        $order_statuses['items'] ?? [],
        static fn ( array $status_item ): bool => 'pending' !== ( $status_item['status'] ?? '' )
    )
);
$sultana_admin_visible_status_count = array_sum( array_map( static fn ( array $status_item ): int => absint( $status_item['count'] ?? 0 ), $sultana_admin_visible_statuses ) );

$sultana_admin_statistics_icon = static function ( string $name ): void {
    $icon_url = \Sultana\Admin\Core\Icons::url( $name );

    if ( '' === $icon_url ) {
        return;
    }

    ?>
    <span class="sultana-admin-icon" style="--sultana-admin-icon-url: url('<?php echo esc_url( $icon_url ); ?>');" aria-hidden="true"></span>
    <?php
};

$sultana_admin_chart_points = is_array( $sales_trend['points'] ?? null ) ? $sales_trend['points'] : [];
$sultana_admin_chart_max    = max( 1, (float) ( $sales_trend['max'] ?? 0 ) );
$sultana_admin_chart_count  = count( $sultana_admin_chart_points );
$sultana_admin_chart_width  = 640;
$sultana_admin_chart_height = 220;
$sultana_admin_chart_left   = 24;
$sultana_admin_chart_right  = 16;
$sultana_admin_chart_top    = 18;
$sultana_admin_chart_bottom = 34;
$sultana_admin_chart_plot_w = $sultana_admin_chart_width - $sultana_admin_chart_left - $sultana_admin_chart_right;
$sultana_admin_chart_plot_h = $sultana_admin_chart_height - $sultana_admin_chart_top - $sultana_admin_chart_bottom;
$sultana_admin_polyline     = [];
$sultana_admin_area_points  = [];
$sultana_admin_chart_nodes  = [];

foreach ( $sultana_admin_chart_points as $index => $point ) {
    $x = $sultana_admin_chart_count > 1
        ? $sultana_admin_chart_left + ( $sultana_admin_chart_plot_w * ( $index / ( $sultana_admin_chart_count - 1 ) ) )
        : $sultana_admin_chart_left + ( $sultana_admin_chart_plot_w / 2 );
    $y = $sultana_admin_chart_top + ( $sultana_admin_chart_plot_h * ( 1 - ( (float) ( $point['value'] ?? 0 ) / $sultana_admin_chart_max ) ) );

    $sultana_admin_polyline[]    = round( $x, 2 ) . ',' . round( $y, 2 );
    $sultana_admin_chart_nodes[] = [
        'x'          => $x,
        'y'          => $y,
        'label'      => (string) ( $point['label'] ?? '' ),
        'axis_label' => (string) ( $point['axis_label'] ?? '' ),
        'amount'     => (float) ( $point['value'] ?? 0 ),
    ];
}

if ( ! empty( $sultana_admin_polyline ) ) {
    $sultana_admin_chart_base_y = $sultana_admin_chart_height - $sultana_admin_chart_bottom;
    $sultana_admin_area_points  = array_merge(
        [ round( $sultana_admin_chart_left, 2 ) . ',' . round( $sultana_admin_chart_base_y, 2 ) ],
        $sultana_admin_polyline,
        [ round( $sultana_admin_chart_width - $sultana_admin_chart_right, 2 ) . ',' . round( $sultana_admin_chart_base_y, 2 ) ]
    );
}

?>
<section class="sultana-admin-statistics" aria-label="<?php esc_attr_e( 'Estadisticas', 'sultana-admin' ); ?>">
    <div class="sultana-admin-statistics__toolbar">
        <nav class="sultana-admin-period-switcher" aria-label="<?php esc_attr_e( 'Periodo', 'sultana-admin' ); ?>">
            <?php foreach ( $periods as $period ) : ?>
                <a
                    class="<?php echo esc_attr( 'sultana-admin-period-switcher__item' . ( ! empty( $period['is_active'] ) ? ' is-active' : '' ) ); ?>"
                    href="<?php echo esc_url( $period['url'] ?? '' ); ?>"
                    <?php echo ! empty( $period['is_active'] ) ? 'aria-current="page"' : ''; ?>
                >
                    <?php echo esc_html( $period['label'] ?? '' ); ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <?php if ( '' !== $range_label ) : ?>
            <span><?php echo esc_html( $range_label ); ?></span>
        <?php endif; ?>
    </div>

    <?php if ( '' !== $error ) : ?>
        <div class="sultana-admin-error-list" role="alert">
            <strong><?php esc_html_e( 'No pudimos cargar las estadisticas', 'sultana-admin' ); ?></strong>
            <ul><li><?php echo esc_html( $error ); ?></li></ul>
        </div>
    <?php endif; ?>

    <div class="sultana-admin-statistics-metrics">
        <?php foreach ( $metrics as $metric ) : ?>
            <article class="<?php echo esc_attr( 'sultana-admin-stat-card sultana-admin-stat-card--' . sanitize_html_class( $metric['key'] ?? '' ) ); ?>">
                <span class="sultana-admin-stat-card__icon">
                    <?php $sultana_admin_statistics_icon( $metric['icon'] ?? '' ); ?>
                </span>
                <span class="sultana-admin-stat-card__label"><?php echo esc_html( $metric['label'] ?? '' ); ?></span>
                <strong><?php echo wp_kses_post( $metric['value'] ?? '' ); ?></strong>
                <?php if ( ! empty( $metric['trend'] ) ) : ?>
                    <span class="<?php echo esc_attr( 'sultana-admin-stat-card__trend sultana-admin-stat-card__trend--' . sanitize_html_class( $metric['trend']['direction'] ?? '' ) ); ?>">
                        <?php echo 'up' === ( $metric['trend']['direction'] ?? '' ) ? '&uarr; ' : '&darr; '; ?><?php echo esc_html( $metric['trend']['label'] ?? '' ); ?>
                    </span>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>

    <div class="sultana-admin-statistics-layout">
        <div class="sultana-admin-statistics-column sultana-admin-statistics-column--main">
            <article class="sultana-admin-statistics-panel sultana-admin-statistics-panel--trend">
                <h2><?php esc_html_e( 'Tendencia de ventas', 'sultana-admin' ); ?></h2>

                <?php if ( ! empty( $sales_trend['empty'] ) ) : ?>
                    <p class="sultana-admin-statistics-empty"><?php esc_html_e( 'Sin ventas en este periodo', 'sultana-admin' ); ?></p>
                <?php else : ?>
                    <div class="sultana-admin-sales-chart" data-statistics-chart data-points="<?php echo esc_attr( wp_json_encode( $sultana_admin_chart_nodes ) ); ?>" aria-label="<?php esc_attr_e( 'Tendencia de ventas', 'sultana-admin' ); ?>">
                        <svg viewBox="0 0 <?php echo esc_attr( (string) $sultana_admin_chart_width ); ?> <?php echo esc_attr( (string) $sultana_admin_chart_height ); ?>" role="img" aria-hidden="false">
                            <polygon class="sultana-admin-sales-chart__area" points="<?php echo esc_attr( implode( ' ', $sultana_admin_area_points ) ); ?>" />
                            <polyline class="sultana-admin-sales-chart__line" points="<?php echo esc_attr( implode( ' ', $sultana_admin_polyline ) ); ?>" />
                            <circle class="sultana-admin-sales-chart__active-point" cx="0" cy="0" r="5" aria-hidden="true" />
                            <?php foreach ( $sultana_admin_chart_nodes as $node ) : ?>
                                <?php if ( '' !== $node['axis_label'] ) : ?>
                                    <text class="sultana-admin-sales-chart__label" x="<?php echo esc_attr( (string) round( $node['x'], 2 ) ); ?>" y="<?php echo esc_attr( (string) ( $sultana_admin_chart_height - 8 ) ); ?>"><?php echo esc_html( $node['axis_label'] ); ?></text>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </svg>
                        <div class="sultana-admin-sales-chart__tooltip" role="status" aria-live="polite" hidden></div>
                    </div>
                <?php endif; ?>
            </article>

            <article class="sultana-admin-statistics-panel sultana-admin-statistics-panel--statuses">
                <h2><?php esc_html_e( 'Estado de pedidos', 'sultana-admin' ); ?></h2>

                <?php if ( $sultana_admin_visible_status_count <= 0 ) : ?>
                    <p class="sultana-admin-statistics-empty"><?php esc_html_e( 'Sin pedidos en este periodo', 'sultana-admin' ); ?></p>
                <?php else : ?>
                    <ul class="sultana-admin-status-breakdown">
                        <?php foreach ( $sultana_admin_visible_statuses as $status_item ) : ?>
                            <?php $status_label = (string) ( $status_item['label'] ?? '' ); ?>
                            <li>
                                <span>
                                    <i class="<?php echo esc_attr( 'sultana-admin-status-breakdown__dot sultana-admin-status-breakdown__dot--' . sanitize_html_class( $status_item['status'] ?? '' ) ); ?>" aria-hidden="true"></i>
                                    <?php echo esc_html( $status_label ); ?>
                                </span>
                                <strong><?php echo esc_html( number_format_i18n( absint( $status_item['count'] ?? 0 ) ) ); ?></strong>
                                <a class="sultana-admin-statistics-view-link" href="<?php echo esc_url( add_query_arg( 'status', sanitize_key( (string) ( $status_item['status'] ?? '' ) ), \Sultana\Admin\Core\Router::orders_url() ) ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Ver pedidos %s', 'sultana-admin' ), $status_label ) ); ?>">
                                    <?php $sultana_admin_statistics_icon( 'package-check' ); ?>
                                    <?php esc_html_e( 'Ver', 'sultana-admin' ); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </article>
        </div>

        <div class="sultana-admin-statistics-column sultana-admin-statistics-column--side">
            <article class="sultana-admin-statistics-panel sultana-admin-statistics-panel--products">
                <h2><?php esc_html_e( 'Mas vendidos', 'sultana-admin' ); ?></h2>

                <?php if ( empty( $top_products ) ) : ?>
                    <p class="sultana-admin-statistics-empty"><?php esc_html_e( 'Sin productos vendidos', 'sultana-admin' ); ?></p>
                <?php else : ?>
                    <ol class="sultana-admin-top-products">
                        <?php foreach ( $top_products as $product ) : ?>
                            <?php $product_name = (string) ( $product['name'] ?? '' ); ?>
                            <li>
                                <?php if ( ! empty( $product['image'] ) ) : ?>
                                    <img src="<?php echo esc_url( $product['image'] ); ?>" alt="">
                                <?php endif; ?>
                                <span class="sultana-admin-top-products__body">
                                    <strong><?php echo esc_html( $product_name ); ?></strong>
                                    <small><?php echo esc_html( $product['units_text'] ?? '' ); ?></small>
                                </span>
                                <span class="sultana-admin-top-products__revenue"><?php echo wp_kses_post( $product['revenue'] ?? '' ); ?></span>
                                <a class="sultana-admin-statistics-view-link" href="<?php echo esc_url( $product['url'] ?? '' ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Ver producto %s', 'sultana-admin' ), $product_name ) ); ?>">
                                    <?php $sultana_admin_statistics_icon( 'box' ); ?>
                                    <?php esc_html_e( 'Ver', 'sultana-admin' ); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </article>

            <article class="sultana-admin-statistics-panel sultana-admin-statistics-panel--customers">
                <h2><?php esc_html_e( 'Mejores clientes', 'sultana-admin' ); ?></h2>

                <?php if ( empty( $best_customers ) ) : ?>
                    <p class="sultana-admin-statistics-empty"><?php esc_html_e( 'Sin compras en este periodo', 'sultana-admin' ); ?></p>
                <?php else : ?>
                    <ol class="sultana-admin-best-customers">
                        <?php foreach ( $best_customers as $customer ) : ?>
                            <?php $customer_name = (string) ( $customer['name'] ?? '' ); ?>
                            <li>
                                <span class="sultana-admin-best-customers__body">
                                    <strong><?php echo esc_html( $customer_name ); ?></strong>
                                    <small><?php echo esc_html( $customer['orders_text'] ?? '' ); ?></small>
                                </span>
                                <span class="sultana-admin-best-customers__total"><?php echo wp_kses_post( $customer['total'] ?? '' ); ?></span>
                                <a class="sultana-admin-statistics-view-link" href="<?php echo esc_url( $customer['url'] ?? '' ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Ver cliente %s', 'sultana-admin' ), $customer_name ) ); ?>">
                                    <?php $sultana_admin_statistics_icon( 'user' ); ?>
                                    <?php esc_html_e( 'Ver', 'sultana-admin' ); ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </article>
        </div>
    </div>
</section>

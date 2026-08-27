<?php
/**
 * Custom customer orders.
 *
 * @package SultanaStorefront
 */

defined( 'ABSPATH' ) || exit;

$shop_url       = wc_get_page_permalink( 'shop' );
$current_user_id = get_current_user_id();
$orders_per_page = 6;
$current_page    = isset( $current_page ) ? max( 1, absint( $current_page ) ) : 1;
$filter_statuses = [
    'pending'    => __( 'Pendiente de pago', 'sultana-storefront' ),
    'processing' => __( 'Procesando', 'sultana-storefront' ),
    'on-hold'    => __( 'En espera', 'sultana-storefront' ),
    'completed'  => __( 'Completado', 'sultana-storefront' ),
    'cancelled'  => __( 'Cancelado', 'sultana-storefront' ),
];
$allowed_filters = array_merge( [ 'all', 'gift' ], array_keys( $filter_statuses ) );
$raw_order_filter = $_GET['order_status'] ?? 'all'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$selected_filter  = is_scalar( $raw_order_filter ) ? sanitize_key( wp_unslash( $raw_order_filter ) ) : 'all';
$selected_filter  = in_array( $selected_filter, $allowed_filters, true ) ? $selected_filter : 'all';
$display_orders = [];
$total_items    = 0;
$total_pages    = 1;
$orders_url     = class_exists( 'WooCommerce' ) ? wc_get_account_endpoint_url( 'orders' ) : '';
$store_name     = function_exists( 'sultana_storefront_store_name' ) ? sultana_storefront_store_name() : wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
$store_name     = '' !== trim( (string) $store_name ) ? trim( (string) $store_name ) : __( 'nuestra tienda', 'sultana-storefront' );

$own_orders_args = static function ( int $limit, int $page = 1, array $extra_args = [] ) use ( $current_user_id ): array {
    return array_merge(
        [
            'customer' => $current_user_id,
            'limit'    => $limit,
            'page'     => $page,
            'paginate' => true,
            'orderby'  => 'date',
            'order'    => 'DESC',
            'return'   => 'ids',
        ],
        $extra_args
    );
};

$gift_orders_args = static function ( int $limit, int $page = 1 ) use ( $current_user_id ): array {
    return [
        'limit'      => $limit,
        'page'       => $page,
        'paginate'   => true,
        'status'     => [ 'completed' ],
        'orderby'    => 'date',
        'order'      => 'DESC',
        'return'     => 'ids',
        'meta_query' => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
            [
                'key'   => '_scc_wishlist_gift_order',
                'value' => 'yes',
            ],
            [
                'key'   => '_scc_wishlist_recipient_user_id',
                'value' => $current_user_id,
            ],
        ],
    ];
};

$query_total = static function ( array $args ): int {
    $result = wc_get_orders( array_merge( $args, [ 'limit' => 1 ] ) );

    return is_object( $result ) && isset( $result->total ) ? absint( $result->total ) : 0;
};

$load_orders_from_ids = static function ( array $order_ids ): array {
    $orders = [];

    foreach ( $order_ids as $order_id ) {
        $order = wc_get_order( $order_id );

        if ( $order instanceof WC_Order ) {
            $orders[] = $order;
        }
    }

    return $orders;
};

if ( $current_user_id > 0 ) {
    if ( 'gift' === $selected_filter ) {
        $gift_result = wc_get_orders( $gift_orders_args( $orders_per_page, $current_page ) );
        $total_items = is_object( $gift_result ) && isset( $gift_result->total ) ? absint( $gift_result->total ) : 0;
        $display_orders = is_object( $gift_result ) && ! empty( $gift_result->orders ) ? $load_orders_from_ids( $gift_result->orders ) : [];
    } elseif ( isset( $filter_statuses[ $selected_filter ] ) ) {
        $status_result = wc_get_orders(
            $own_orders_args(
                $orders_per_page,
                $current_page,
                [
                    'status' => [ $selected_filter ],
                ]
            )
        );
        $total_items = is_object( $status_result ) && isset( $status_result->total ) ? absint( $status_result->total ) : 0;
        $display_orders = is_object( $status_result ) && ! empty( $status_result->orders ) ? $load_orders_from_ids( $status_result->orders ) : [];
    } else {
        $own_total  = $query_total( $own_orders_args( 1 ) );
        $gift_total = $query_total( $gift_orders_args( 1 ) );
        $total_items = $own_total + $gift_total;
        $offset       = ( $current_page - 1 ) * $orders_per_page;
        $fetch_limit  = $offset + $orders_per_page;

        $own_result  = wc_get_orders( $own_orders_args( $fetch_limit ) );
        $gift_result = wc_get_orders( $gift_orders_args( $fetch_limit ) );
        $merged_ids   = [];

        foreach ( [ $own_result, $gift_result ] as $result ) {
            if ( ! is_object( $result ) || empty( $result->orders ) ) {
                continue;
            }

            foreach ( $result->orders as $order_id ) {
                $order = wc_get_order( $order_id );

                if ( $order instanceof WC_Order ) {
                    $merged_ids[ (int) $order_id ] = $order;
                }
            }
        }

        uasort(
            $merged_ids,
            static function ( WC_Order $a, WC_Order $b ): int {
                $date_a = $a->get_date_created();
                $date_b = $b->get_date_created();

                return ( $date_b ? $date_b->getTimestamp() : 0 ) <=> ( $date_a ? $date_a->getTimestamp() : 0 );
            }
        );

        $display_orders = array_slice( array_values( $merged_ids ), $offset, $orders_per_page );
    }
}

$total_pages = $total_items > 0 ? (int) ceil( $total_items / $orders_per_page ) : 1;

if ( $total_items > 0 && $current_page > $total_pages && ! headers_sent() ) {
    $redirect_url = 1 === $total_pages ? $orders_url : wc_get_endpoint_url( 'orders', $total_pages );

    if ( 'all' !== $selected_filter ) {
        $redirect_url = add_query_arg( 'order_status', $selected_filter, $redirect_url );
    }

    wp_safe_redirect( $redirect_url );
    exit;
}

$display_has_orders = ! empty( $display_orders );
$available_filter_statuses = [];

foreach ( $filter_statuses as $status => $status_label ) {
    if ( $query_total( $own_orders_args( 1, 1, [ 'status' => [ $status ] ] ) ) > 0 ) {
        $available_filter_statuses[ $status ] = $status_label;
    }
}

if ( $query_total( $gift_orders_args( 1 ) ) > 0 ) {
    $available_filter_statuses['gift'] = __( 'Regalos recibidos', 'sultana-storefront' );
}

$filter_url = static function ( string $status ) use ( $orders_url ): string {
    return 'all' === $status ? $orders_url : add_query_arg( 'order_status', $status, $orders_url );
};

$orders_pagination = '';

if ( $total_pages > 1 ) {
    $pagination_base = str_replace(
        '999999999',
        '%#%',
        esc_url( 'all' !== $selected_filter ? add_query_arg( 'order_status', $selected_filter, wc_get_endpoint_url( 'orders', '999999999' ) ) : wc_get_endpoint_url( 'orders', '999999999' ) )
    );

    $pagination_links = paginate_links(
        [
            'base'      => $pagination_base,
            'format'    => '',
            'current'   => $current_page,
            'total'     => $total_pages,
            'type'      => 'array',
            'prev_text' => '&larr;',
            'next_text' => '&rarr;',
        ]
    );

    if ( is_array( $pagination_links ) ) {
        $page_one_url = esc_url( 'all' !== $selected_filter ? add_query_arg( 'order_status', $selected_filter, wc_get_endpoint_url( 'orders', 1 ) ) : wc_get_endpoint_url( 'orders', 1 ) );
        $clean_url    = esc_url( 'all' !== $selected_filter ? add_query_arg( 'order_status', $selected_filter, $orders_url ) : $orders_url );

        $pagination_links = array_map(
            static function ( string $link ) use ( $page_one_url, $clean_url ): string {
                return str_replace( $page_one_url, $clean_url, $link );
            },
            $pagination_links
        );

        $orders_pagination = sprintf(
            '<nav class="ve-account-orders__pagination shop-page__pagination" aria-label="%1$s"><div class="navigation pagination"><div class="nav-links">%2$s</div></div></nav>',
            esc_attr__( 'Paginacion de pedidos', 'sultana-storefront' ),
            wp_kses_post( implode( '', $pagination_links ) )
        );
    }
}

?>

<section class="ve-account-panel ve-account-orders">
    <header class="ve-account-section-title">
        <div>
            <h1><?php esc_html_e( 'Tus pedidos', 'sultana-storefront' ); ?></h1>
        </div>
        <div class="ve-account-orders__filter" data-order-status-filter>
            <button
                class="ve-account-orders__filter-toggle"
                type="button"
                aria-expanded="false"
                aria-controls="ve-order-status-filter-menu"
                data-order-status-filter-toggle
            >
                <span class="screen-reader-text"><?php esc_html_e( 'Filtrar pedidos por estado', 'sultana-storefront' ); ?></span>
                <?php variedadesexpress_icon( 'funnel', 've-account-orders__filter-icon' ); ?>
            </button>
            <div id="ve-order-status-filter-menu" class="ve-account-orders__filter-menu" hidden data-order-status-filter-menu>
                <button type="button" data-order-status-filter-option="all" data-order-status-filter-url="<?php echo esc_url( $filter_url( 'all' ) ); ?>" <?php echo 'all' === $selected_filter ? 'aria-current="true"' : ''; ?>><?php esc_html_e( 'Ver todo', 'sultana-storefront' ); ?></button>
                <?php foreach ( $available_filter_statuses as $status_value => $status_label ) : ?>
                    <button type="button" data-order-status-filter-option="<?php echo esc_attr( $status_value ); ?>" data-order-status-filter-url="<?php echo esc_url( $filter_url( $status_value ) ); ?>" <?php echo $selected_filter === $status_value ? 'aria-current="true"' : ''; ?>><?php echo esc_html( $status_label ); ?></button>
                <?php endforeach; ?>
            </div>
            <select class="ve-account-orders__filter-native" data-order-status-filter-native aria-label="<?php esc_attr_e( 'Filtrar pedidos por estado', 'sultana-storefront' ); ?>">
                <option value="all" data-order-status-filter-url="<?php echo esc_url( $filter_url( 'all' ) ); ?>" <?php selected( $selected_filter, 'all' ); ?>><?php esc_html_e( 'Ver todo', 'sultana-storefront' ); ?></option>
                <?php foreach ( $available_filter_statuses as $status_value => $status_label ) : ?>
                    <option value="<?php echo esc_attr( $status_value ); ?>" data-order-status-filter-url="<?php echo esc_url( $filter_url( $status_value ) ); ?>" <?php selected( $selected_filter, $status_value ); ?>><?php echo esc_html( $status_label ); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </header>

    <?php if ( $display_has_orders ) : ?>
        <div class="ve-account-orders__list" data-order-status-list>
            <?php foreach ( $display_orders as $order ) : ?>
                <?php
                $item_count = $order->get_item_count() - $order->get_item_count_refunded();
                $is_received_gift = (int) $order->get_customer_id() !== $current_user_id
                    && 'yes' === $order->get_meta( '_scc_wishlist_gift_order' )
                    && absint( $order->get_meta( '_scc_wishlist_recipient_user_id' ) ) === $current_user_id
                    && $order->has_status( 'completed' );
                $giver_id = absint( $order->get_meta( '_scc_wishlist_giver_user_id' ) );
                $giver    = $giver_id > 0 ? get_user_by( 'id', $giver_id ) : false;
                $giver_name = $giver instanceof WP_User && '' !== trim( (string) $giver->display_name )
                    ? sanitize_text_field( (string) $giver->display_name )
                    : trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
                $giver_name = '' !== $giver_name ? $giver_name : __( 'alguien especial', 'sultana-storefront' );
                $display_status = $is_received_gift
                    ? sprintf(
                        /* translators: %s: gift giver display name. */
                        __( 'Regalo de %s', 'sultana-storefront' ),
                        $giver_name
                    )
                    : wc_get_order_status_name( $order->get_status() );
                $display_status_key = $is_received_gift ? 'gift' : $order->get_status();
                ?>

                <article class="ve-account-order-card" data-order-status="<?php echo esc_attr( $display_status_key ); ?>">
                    <div class="ve-account-order-card__summary">
                        <div class="ve-account-order-card__summary-content">
                            <h2>
                                <?php
                                printf(
                                    /* translators: %s: order number. */
                                    esc_html__( 'Pedido #%s', 'sultana-storefront' ),
                                    esc_html( $order->get_order_number() )
                                );
                                ?>
                            </h2>
                            <span class="ve-account-order-card__status ve-account-order-card__status--<?php echo esc_attr( $display_status_key ); ?>">
                                <?php echo esc_html( $display_status ); ?>
                            </span>
                        </div>
                    </div>

                    <dl class="ve-account-order-card__meta">
                        <div>
                            <dt><?php esc_html_e( 'Fecha', 'sultana-storefront' ); ?></dt>
                            <dd>
                                <time datetime="<?php echo esc_attr( $order->get_date_created()->date( 'c' ) ); ?>">
                                    <?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?>
                                </time>
                            </dd>
                        </div>
                        <div>
                            <dt><?php esc_html_e( 'Total', 'sultana-storefront' ); ?></dt>
                            <dd>
                                <?php
                                echo wp_kses_post(
                                    sprintf(
                                        /* translators: 1: order total, 2: item count. */
                                        _n( '%1$s por %2$s articulo', '%1$s por %2$s articulos', $item_count, 'sultana-storefront' ),
                                        $order->get_formatted_order_total(),
                                        esc_html( $item_count )
                                    )
                                );
                                ?>
                            </dd>
                        </div>
                    </dl>

                    <a class="ve-account-order-card__button ve-account-order-card__button--view" href="<?php echo esc_url( $order->get_view_order_url() ); ?>" aria-label="<?php esc_attr_e( 'Ver pedido', 'sultana-storefront' ); ?>">
                        <?php variedadesexpress_icon( 'eye', 've-account-order-card__button-icon' ); ?>
                    </a>
                </article>
            <?php endforeach; ?>
        </div>

        <p class="ve-account-orders__no-results" data-order-status-empty hidden>
            <?php esc_html_e( 'No tienes pedidos con ese estado.', 'sultana-storefront' ); ?>
        </p>

        <?php echo wp_kses_post( $orders_pagination ); ?>
    <?php else : ?>
        <div class="ve-account-empty">
            <div>
                <?php if ( 'all' === $selected_filter ) : ?>
                    <h2><?php esc_html_e( 'Todavia no has hecho ningun pedido', 'sultana-storefront' ); ?></h2>
                    <p><?php echo esc_html( sprintf( __( 'Cuando compres en %s, tus pedidos apareceran aqui con su estado y detalles.', 'sultana-storefront' ), $store_name ) ); ?></p>
                <?php else : ?>
                    <h2><?php esc_html_e( 'No tienes pedidos con ese estado.', 'sultana-storefront' ); ?></h2>
                    <p><?php esc_html_e( 'Puedes volver a ver todos tus pedidos desde el filtro superior.', 'sultana-storefront' ); ?></p>
                <?php endif; ?>
            </div>
            <a class="ve-account-empty__button" href="<?php echo esc_url( $shop_url ); ?>">
                <span><?php esc_html_e( 'Explorar productos', 'sultana-storefront' ); ?></span>
            </a>
        </div>
    <?php endif; ?>
</section>

<?php do_action( 'woocommerce_after_account_orders', $display_has_orders ); ?>

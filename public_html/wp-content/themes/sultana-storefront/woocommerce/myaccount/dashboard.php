<?php
/**
 * Custom account dashboard.
 *
 * @package SultanaStorefront
 */

defined( 'ABSPATH' ) || exit;

$current_user = wp_get_current_user();
$name_parts   = array_values(
    array_filter(
        preg_split( '/\s+/', trim( $current_user->first_name . ' ' . $current_user->last_name ) )
    )
);
$display_name = $current_user->display_name ?: $current_user->user_login;

if ( count( $name_parts ) > 2 ) {
    $display_name = trim( $name_parts[0] . ' ' . $name_parts[2] );
} elseif ( count( $name_parts ) > 1 ) {
    $display_name = trim( $name_parts[0] . ' ' . $name_parts[1] );
}

$orders = wc_get_orders(
    [
        'customer_id' => get_current_user_id(),
        'limit'       => 3,
        'orderby'     => 'date',
        'order'       => 'DESC',
        'return'      => 'objects',
    ]
);

$completed_orders = wc_get_orders(
    [
        'customer_id' => get_current_user_id(),
        'status'      => [ 'completed' ],
        'limit'       => 1,
        'paginate'    => true,
        'return'      => 'ids',
    ]
);
$order_count      = is_object( $completed_orders ) && isset( $completed_orders->total ) ? absint( $completed_orders->total ) : 0;
$wishlist_count = class_exists( '\Sultana\CommerceCore\Modules\Wishlist\Wishlist' )
    ? \Sultana\CommerceCore\Modules\Wishlist\Wishlist::get_count( get_current_user_id() )
    : 0;
$shop_url       = wc_get_page_permalink( 'shop' );
$orders_url     = wc_get_account_endpoint_url( 'orders' );

$recent_product_ids = [];

if ( ! empty( $_COOKIE['woocommerce_recently_viewed'] ) ) {
    $recent_product_ids = array_reverse(
        array_filter(
            array_map(
                'absint',
                explode( '|', sanitize_text_field( wp_unslash( $_COOKIE['woocommerce_recently_viewed'] ) ) )
            )
        )
    );
}

$recommended_terms = [];

foreach ( $recent_product_ids as $product_id ) {
    $terms = get_the_terms( $product_id, 'product_cat' );

    if ( empty( $terms ) || is_wp_error( $terms ) ) {
        continue;
    }

    foreach ( $terms as $term ) {
        if ( isset( $recommended_terms[ $term->term_id ] ) ) {
            continue;
        }

        $recommended_terms[ $term->term_id ] = $term;
    }

    if ( count( $recommended_terms ) >= 2 ) {
        break;
    }
}

if ( count( $recommended_terms ) < 2 ) {
    $fallback_terms = get_terms(
        [
            'taxonomy'   => 'product_cat',
            'hide_empty' => true,
            'number'     => 4,
            'orderby'    => 'count',
            'order'      => 'DESC',
        ]
    );

    if ( ! is_wp_error( $fallback_terms ) ) {
        foreach ( $fallback_terms as $term ) {
            if ( isset( $recommended_terms[ $term->term_id ] ) ) {
                continue;
            }

            $recommended_terms[ $term->term_id ] = $term;

            if ( count( $recommended_terms ) >= 2 ) {
                break;
            }
        }
    }
}

$recommended_terms = array_values( $recommended_terms );
?>

<section class="ve-account-panel ve-dashboard">
    <header class="ve-account-hero">
        <span class="ve-account-hero__icon" aria-hidden="true">
            <?php variedadesexpress_icon( 'layout-panel-left', 've-account-hero__svg' ); ?>
        </span>
        <div>
            <span><?php esc_html_e( 'Panel', 'sultana-storefront' ); ?></span>
            <h1><?php echo esc_html( sprintf( __( 'Hola, %s', 'sultana-storefront' ), $display_name ) ); ?></h1>
            <p><?php esc_html_e( 'Un resumen rápido de tu actividad.', 'sultana-storefront' ); ?></p>
        </div>
    </header>

    <div class="ve-dashboard__stats" aria-label="<?php esc_attr_e( 'Resumen de cuenta', 'sultana-storefront' ); ?>">
        <article class="ve-dashboard-stat">
            <span class="ve-dashboard-stat__icon" aria-hidden="true"><?php variedadesexpress_icon( 'shopping-bag', 've-dashboard-stat__svg' ); ?></span>
            <div>
                <span><?php esc_html_e( 'Realizados', 'sultana-storefront' ); ?></span>
                <strong><?php echo esc_html( number_format_i18n( $order_count ) ); ?></strong>
            </div>
        </article>

        <article class="ve-dashboard-stat">
            <span class="ve-dashboard-stat__icon" aria-hidden="true"><?php variedadesexpress_icon( 'heart', 've-dashboard-stat__svg' ); ?></span>
            <div>
                <span><?php esc_html_e( 'Lista de deseos', 'sultana-storefront' ); ?></span>
                <strong><?php echo esc_html( number_format_i18n( $wishlist_count ) ); ?></strong>
            </div>
        </article>

        <article class="ve-dashboard-stat">
            <span class="ve-dashboard-stat__icon" aria-hidden="true"><?php variedadesexpress_icon( 'lock', 've-dashboard-stat__svg' ); ?></span>
            <div>
                <span><?php esc_html_e( 'Sesión', 'sultana-storefront' ); ?></span>
                <strong><?php esc_html_e( 'Activa', 'sultana-storefront' ); ?></strong>
            </div>
        </article>
    </div>

    <div class="ve-dashboard__grid">
        <section class="ve-dashboard-card">
            <header class="ve-dashboard-card__header">
                <span><?php esc_html_e( 'Para vos', 'sultana-storefront' ); ?></span>
                <h2><?php esc_html_e( 'Seguí explorando', 'sultana-storefront' ); ?></h2>
            </header>

            <div class="ve-dashboard-actions ve-dashboard-actions--compact">
                <?php if ( $recommended_terms ) : ?>
                    <?php foreach ( array_slice( $recommended_terms, 0, 2 ) as $index => $term ) : ?>
                        <?php $term_link = get_term_link( $term ); ?>
                        <?php if ( is_wp_error( $term_link ) ) : ?>
                            <?php continue; ?>
                        <?php endif; ?>
                        <a class="ve-dashboard-action" href="<?php echo esc_url( $term_link ); ?>">
                            <span class="ve-dashboard-action__icon" aria-hidden="true"><?php variedadesexpress_icon( 0 === $index ? 'search' : 'heart', 've-dashboard-action__svg' ); ?></span>
                            <span>
                                <strong>
                                    <?php
                                    echo esc_html(
                                        0 === $index
                                            ? sprintf( __( 'Seguir en %s', 'sultana-storefront' ), $term->name )
                                            : sprintf( __( 'También %s', 'sultana-storefront' ), $term->name )
                                    );
                                    ?>
                                </strong>
                                <small>
                                    <?php
                                    echo esc_html(
                                        $recent_product_ids
                                            ? __( 'Basado en los productos que viste recientemente.', 'sultana-storefront' )
                                            : __( 'Una categoría popular para descubrir productos.', 'sultana-storefront' )
                                    );
                                    ?>
                                </small>
                            </span>
                            <span class="ve-dashboard-action__arrow" aria-hidden="true">›</span>
                        </a>
                    <?php endforeach; ?>
                <?php else : ?>
                    <a class="ve-dashboard-action" href="<?php echo esc_url( $shop_url ); ?>">
                        <span class="ve-dashboard-action__icon" aria-hidden="true"><?php variedadesexpress_icon( 'search', 've-dashboard-action__svg' ); ?></span>
                        <span>
                            <strong><?php esc_html_e( 'Explorar la tienda', 'sultana-storefront' ); ?></strong>
                            <small><?php esc_html_e( 'Encontrá productos seleccionados para tu día a día.', 'sultana-storefront' ); ?></small>
                        </span>
                        <span class="ve-dashboard-action__arrow" aria-hidden="true">›</span>
                    </a>
                <?php endif; ?>
            </div>
        </section>

        <section class="ve-dashboard-card">
            <header class="ve-dashboard-card__header ve-dashboard-card__header--inline">
                <div>
                    <span><?php esc_html_e( 'Compras', 'sultana-storefront' ); ?></span>
                    <h2><?php esc_html_e( 'Pedidos recientes', 'sultana-storefront' ); ?></h2>
                </div>
                <a href="<?php echo esc_url( $orders_url ); ?>"><?php esc_html_e( 'Ver todo', 'sultana-storefront' ); ?></a>
            </header>

            <?php if ( $orders ) : ?>
                <div class="ve-dashboard-orders">
                    <?php foreach ( $orders as $order ) : ?>
                        <a class="ve-dashboard-order" href="<?php echo esc_url( $order->get_view_order_url() ); ?>">
                            <span>
                                <strong><?php echo esc_html( sprintf( '#%s', $order->get_order_number() ) ); ?></strong>
                                <small><?php echo esc_html( wc_format_datetime( $order->get_date_created(), 'F j, Y' ) ); ?></small>
                            </span>
                            <span>
                                <strong><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></strong>
                                <small><?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></small>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <div class="ve-dashboard-empty">
                    <strong><?php esc_html_e( 'Aún no tenés pedidos.', 'sultana-storefront' ); ?></strong>
                    <p><?php esc_html_e( 'Cuando hagás tu primera compra, aparecerá aquí.', 'sultana-storefront' ); ?></p>
                    <a href="<?php echo esc_url( $shop_url ); ?>"><?php esc_html_e( 'Ir a la tienda', 'sultana-storefront' ); ?></a>
                </div>
            <?php endif; ?>
        </section>
    </div>
</section>

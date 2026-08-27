<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function variedadesexpress_account_wishlist_per_page(): int
{
    return 12;
}

function variedadesexpress_account_wishlist_url(): string
{
    return function_exists( 'wc_get_account_endpoint_url' ) ? wc_get_account_endpoint_url( 'wishlist' ) : '';
}

function variedadesexpress_account_wishlist_page_url( int $page ): string
{
    $wishlist_url = variedadesexpress_account_wishlist_url();

    if ( '' === $wishlist_url ) {
        return '';
    }

    return $page > 1
        ? add_query_arg( 'wishlist_page', $page, $wishlist_url )
        : remove_query_arg( 'wishlist_page', $wishlist_url );
}

function variedadesexpress_account_wishlist_state( int $user_id, int $requested_page = 1 ): array
{
    $wishlist_class = '\Sultana\CommerceCore\Modules\Wishlist\Wishlist';
    $items          = class_exists( $wishlist_class ) ? $wishlist_class::get_items( $user_id ) : [];
    $per_page       = variedadesexpress_account_wishlist_per_page();
    $total_items    = count( $items );
    $total_pages    = $total_items > 0 ? (int) ceil( $total_items / $per_page ) : 1;
    $current_page   = min( max( 1, $requested_page ), $total_pages );
    $offset         = ( $current_page - 1 ) * $per_page;
    $paged_items    = array_slice( $items, $offset, $per_page, true );

    return [
        'items'        => $items,
        'paged_items'  => $paged_items,
        'per_page'     => $per_page,
        'total_items'  => $total_items,
        'total_pages'  => $total_pages,
        'current_page' => $current_page,
        'page_url'     => variedadesexpress_account_wishlist_page_url( $current_page ),
    ];
}

function variedadesexpress_account_wishlist_pagination( int $current_page, int $total_pages ): string
{
    $wishlist_url = variedadesexpress_account_wishlist_url();

    if ( $total_pages <= 1 || '' === $wishlist_url ) {
        return '';
    }

    $pagination_base = str_replace(
        '999999999',
        '%#%',
        esc_url( add_query_arg( 'wishlist_page', '999999999', $wishlist_url ) )
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

    if ( ! is_array( $pagination_links ) ) {
        return '';
    }

    $page_one_url = esc_url( add_query_arg( 'wishlist_page', 1, $wishlist_url ) );
    $clean_url    = esc_url( remove_query_arg( 'wishlist_page', $wishlist_url ) );

    $pagination_links = array_map(
        static function ( string $link ) use ( $page_one_url, $clean_url ): string {
            return str_replace( $page_one_url, $clean_url, $link );
        },
        $pagination_links
    );

    return sprintf(
        '<nav class="ve-wishlist-pagination shop-page__pagination" aria-label="%1$s"><div class="navigation pagination"><div class="nav-links">%2$s</div></div></nav>',
        esc_attr__( 'Paginacion de lista de deseos', 'sultana-storefront' ),
        wp_kses_post( implode( '', $pagination_links ) )
    );
}

function variedadesexpress_account_wishlist_empty_html(): string
{
    ob_start();
    ?>
    <section class="ve-account-empty ve-wishlist-empty">
        <span class="ve-account-empty__icon" aria-hidden="true"><?php variedadesexpress_icon( 'heart', 've-account-empty__svg' ); ?></span>
        <div>
            <h2><?php esc_html_e( 'Tu lista está vacía', 'sultana-storefront' ); ?></h2>
            <p><?php esc_html_e( 'Agregá productos desde su página de detalle para encontrarlos rápido aquí.', 'sultana-storefront' ); ?></p>
        </div>
        <a class="ve-account-empty__button" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>">
            <?php esc_html_e( 'Explorar productos', 'sultana-storefront' ); ?>
        </a>
    </section>
    <?php
    return (string) ob_get_clean();
}

function variedadesexpress_account_wishlist_card( array $item, string $wishlist_class ): string
{
    $product_id   = absint( $item['product_id'] ?? 0 );
    $variation_id = absint( $item['variation_id'] ?? 0 );
    $product      = wc_get_product( $variation_id ?: $product_id );
    $parent       = $variation_id ? wc_get_product( $product_id ) : $product;

    if ( ! $product || ! $parent ) {
        return '';
    }

    $image_id  = $product->get_image_id() ?: $parent->get_image_id();
    $image     = $image_id
        ? wp_get_attachment_image( $image_id, 'woocommerce_thumbnail', false, [ 'class' => 've-wishlist-card__image' ] )
        : wc_placeholder_img( 'woocommerce_thumbnail', [ 'class' => 've-wishlist-card__image' ] );
    $permalink = get_permalink( $product_id );
    $key       = sanitize_text_field( $item['key'] ?? '' );
    $options   = method_exists( $wishlist_class, 'get_item_variation_options' ) ? $wishlist_class::get_item_variation_options( $item ) : [];
    $display_options = array_values(
        array_filter(
            $options,
            static function ( $option ): bool {
                return is_array( $option ) && '' !== trim( (string) ( $option['value'] ?? '' ) );
            }
        )
    );
    $can_add_to_cart = $key
        && $product->is_purchasable()
        && $product->is_in_stock()
        && ( ! $parent->is_type( 'variable' ) || $variation_id );

    ob_start();
    ?>
    <article class="ve-wishlist-card" data-wishlist-item="<?php echo esc_attr( $key ); ?>">
        <a class="ve-wishlist-card__media" href="<?php echo esc_url( $permalink ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Ver %s', 'sultana-storefront' ), $parent->get_name() ) ); ?>">
            <?php echo wp_kses_post( $image ); ?>
            <span class="ve-wishlist-card__stock <?php echo $product->is_in_stock() ? 'is-in' : 'is-out'; ?>">
                <?php echo esc_html( $product->is_in_stock() ? __( 'En existencia', 'sultana-storefront' ) : __( 'Agotado', 'sultana-storefront' ) ); ?>
            </span>
        </a>

        <div class="ve-wishlist-card__body">
            <a class="ve-wishlist-card__title" href="<?php echo esc_url( $permalink ); ?>">
                <?php echo esc_html( $parent->get_name() ); ?>
            </a>

            <?php if ( ! empty( $display_options ) ) : ?>
                <ul class="ve-wishlist-card__options" aria-label="<?php esc_attr_e( 'Opciones seleccionadas', 'sultana-storefront' ); ?>">
                    <?php foreach ( $display_options as $option ) : ?>
                        <li><?php echo esc_html( $option['value'] ); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <div class="ve-wishlist-card__meta">
                <span class="ve-wishlist-card__price">
                    <?php echo wp_kses_post( $product->get_price_html() ); ?>
                </span>

                <?php if ( $can_add_to_cart ) : ?>
                    <button
                        class="ve-wishlist-card__cart"
                        type="button"
                        data-wishlist-add-to-cart="<?php echo esc_attr( $key ); ?>"
                        aria-label="<?php echo esc_attr( sprintf( __( 'Agregar %s al carrito', 'sultana-storefront' ), $parent->get_name() ) ); ?>"
                    >
                        <?php variedadesexpress_icon( 'shopping-cart', 've-wishlist-card__cart-icon' ); ?>
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <button
            class="ve-wishlist-card__remove"
            type="button"
            data-wishlist-remove="<?php echo esc_attr( $key ); ?>"
            aria-label="<?php echo esc_attr( sprintf( __( 'Eliminar %s de tu lista de deseos', 'sultana-storefront' ), $parent->get_name() ) ); ?>"
        >
            <span aria-hidden="true">&times;</span>
        </button>
    </article>
    <?php
    return (string) ob_get_clean();
}

function variedadesexpress_account_wishlist_content_html( array $state ): string
{
    $wishlist_class = '\Sultana\CommerceCore\Modules\Wishlist\Wishlist';

    if ( empty( $state['total_items'] ) ) {
        return variedadesexpress_account_wishlist_empty_html();
    }

    ob_start();
    ?>
    <section class="ve-wishlist-grid" data-wishlist-list>
        <?php foreach ( $state['paged_items'] as $item ) : ?>
            <?php echo variedadesexpress_account_wishlist_card( $item, $wishlist_class ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        <?php endforeach; ?>
    </section>

    <?php echo wp_kses_post( variedadesexpress_account_wishlist_pagination( (int) $state['current_page'], (int) $state['total_pages'] ) ); ?>
    <?php
    return (string) ob_get_clean();
}

function variedadesexpress_account_wishlist_ajax_payload( int $user_id, int $requested_page = 1 ): array
{
    $state = variedadesexpress_account_wishlist_state( $user_id, $requested_page );

    return [
        'content_html'  => variedadesexpress_account_wishlist_content_html( $state ),
        'total_items'   => (int) $state['total_items'],
        'current_page'  => (int) $state['current_page'],
        'total_pages'   => (int) $state['total_pages'],
        'per_page'      => (int) $state['per_page'],
        'page_url'      => (string) $state['page_url'],
    ];
}

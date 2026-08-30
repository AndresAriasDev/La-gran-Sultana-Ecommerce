<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$shop_url      = class_exists( 'WooCommerce' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
$account_url   = class_exists( 'WooCommerce' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/' );
$wishlist_url  = class_exists( 'WooCommerce' ) ? wc_get_account_endpoint_url( 'wishlist' ) : $account_url;
$store_name    = function_exists( 'sultana_storefront_store_name' ) ? sultana_storefront_store_name() : wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
$store_url     = function_exists( 'sultana_storefront_store_url' ) ? sultana_storefront_store_url() : home_url( '/' );
$logo_url      = function_exists( 'sultana_storefront_store_logo_url' ) ? sultana_storefront_store_logo_url() : '';
$cart_count    = 0;
$search_value  = function_exists( 'variedadesexpress_current_product_search_query' )
    ? variedadesexpress_current_product_search_query()  
    : get_search_query();
$wishlist_count = 0;
$product_terms = [];
$is_shop_active = class_exists( 'WooCommerce' ) && ( is_shop() || is_product() || ( is_search() && 'product' === get_query_var( 'post_type' ) ) );
$applied_product_search = class_exists( 'WooCommerce' ) && is_search() && 'product' === get_query_var( 'post_type' )
    ? $search_value
    : '';

if ( class_exists( 'WooCommerce' ) ) {
    $cart_count = WC()->cart ? WC()->cart->get_cart_contents_count() : 0;
    $wishlist_class = '\Sultana\CommerceCore\Modules\Wishlist\Wishlist';

    if ( is_user_logged_in() && class_exists( $wishlist_class ) && method_exists( $wishlist_class, 'get_count' ) ) {
        $wishlist_count = $wishlist_class::get_count( get_current_user_id() );
    }

    $product_terms = get_terms(
        [
            'taxonomy'   => 'product_cat',
            'parent'     => 0,
            'hide_empty' => false,
            'number'     => 12,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ]
    );

    if ( is_wp_error( $product_terms ) ) {
        $product_terms = [];
    }
}
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header class="site-header">
    <div class="site-header__top">
        <div class="site-header__top-inner">
            <div class="site-header__marquee" aria-label="<?php esc_attr_e( 'Envíos a toda Nicaragua por medio de Cargotrans', 'sultana-storefront' ); ?>">
                <div class="site-header__marquee-track">
                    <span><?php esc_html_e( 'Envíos a toda Nicaragua por medio de Cargotrans', 'sultana-storefront' ); ?></span>
                    <span aria-hidden="true"><?php esc_html_e( 'Envíos a toda Nicaragua por medio de Cargotrans', 'sultana-storefront' ); ?></span>
                    <span aria-hidden="true"><?php esc_html_e( 'Envíos a toda Nicaragua por medio de Cargotrans', 'sultana-storefront' ); ?></span>
                </div>
            </div>
        </div>
    </div>

    <div class="site-header__main">
        <div class="site-header__container">
            <a class="site-brand" href="<?php echo esc_url( $store_url ); ?>" rel="home">
                <?php if ( $logo_url ) : ?>
                    <img
                        class="site-brand__logo"
                        src="<?php echo esc_url( $logo_url ); ?>"
                        alt="<?php echo esc_attr( $store_name ); ?>"
                        width="220"
                        height="60"
                        decoding="async"
                    >
                <?php else : ?>
                    <span class="site-brand__name"><?php echo esc_html( $store_name ); ?></span>
                <?php endif; ?>
            </a>

            <?php if ( class_exists( 'WooCommerce' ) ) : ?>
                <form class="site-search" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>" data-shop-url="<?php echo esc_url( $shop_url ); ?>" data-clear-url="<?php echo esc_url( $shop_url ); ?>" data-applied-search="<?php echo esc_attr( $applied_product_search ); ?>">
                    <label class="screen-reader-text" for="site-product-search">
                        <?php esc_html_e( 'Buscar productos', 'sultana-storefront' ); ?>
                    </label>
                    <input
                        id="site-product-search"
                        class="site-search__input"
                        type="search"
                        name="s"
                        value="<?php echo esc_attr( $search_value ); ?>"
                        placeholder="<?php esc_attr_e( 'Buscar productos...', 'sultana-storefront' ); ?>"
                    >
                    <input type="hidden" name="post_type" value="product">
                    <button class="site-search__button" type="submit" aria-label="<?php esc_attr_e( 'Buscar', 'sultana-storefront' ); ?>">
                        <img
                            class="site-search__button-icon"
                            src="<?php echo esc_url( get_template_directory_uri() . '/assets/icons/search.svg' ); ?>"
                            alt=""
                            width="20"
                            height="20"
                            aria-hidden="true"
                        >
                        <span class="site-search__clear-icon" aria-hidden="true" hidden>&times;</span>
                    </button>
                </form>
            <?php endif; ?>

            <div class="site-header__actions">
                <a
                    class="site-header__icon-link site-header__icon-link--account"
                    href="<?php echo esc_url( $account_url ); ?>"
                    aria-label="<?php esc_attr_e( 'Mi cuenta', 'sultana-storefront' ); ?>"
                    <?php echo is_user_logged_in() ? '' : 'data-modal-open="account"'; ?>
                >
                    <img src="<?php echo esc_url( get_template_directory_uri() . '/assets/icons/user.svg' ); ?>" alt="" width="24" height="24" aria-hidden="true">
                </a>

                <?php if ( is_user_logged_in() ) : ?>
                    <a class="site-header__icon-link site-header__icon-link--wishlist" href="<?php echo esc_url( $wishlist_url ); ?>" aria-label="<?php esc_attr_e( 'Lista de deseos', 'sultana-storefront' ); ?>">
                        <img src="<?php echo esc_url( get_template_directory_uri() . '/assets/icons/heart.svg' ); ?>" alt="" width="24" height="24" aria-hidden="true">
                        <span class="site-header__wishlist-count" data-wishlist-count <?php echo $wishlist_count > 0 ? '' : 'hidden'; ?>><?php echo esc_html( $wishlist_count ); ?></span>
                    </a>
                <?php else : ?>
                    <button
                        class="site-header__icon-link site-header__icon-link--wishlist"
                        type="button"
                        aria-label="<?php esc_attr_e( 'Lista de deseos', 'sultana-storefront' ); ?>"
                        data-modal-open="account"
                        data-account-view="register"
                    >
                        <img src="<?php echo esc_url( get_template_directory_uri() . '/assets/icons/heart.svg' ); ?>" alt="" width="24" height="24" aria-hidden="true">
                        <span class="site-header__wishlist-count" data-wishlist-count <?php echo $wishlist_count > 0 ? '' : 'hidden'; ?>><?php echo esc_html( $wishlist_count ); ?></span>
                    </button>
                <?php endif; ?>

                <?php if ( class_exists( 'WooCommerce' ) ) : ?>
                    <a class="site-header__cart" href="<?php echo esc_url( wc_get_cart_url() ); ?>" aria-label="<?php esc_attr_e( 'Ver carrito', 'sultana-storefront' ); ?>">
                        <img class="site-header__cart-icon" src="<?php echo esc_url( get_template_directory_uri() . '/assets/icons/shopping-cart.svg' ); ?>" alt="" width="24" height="24" aria-hidden="true">
                        <span class="site-header__cart-count"><?php echo esc_html( $cart_count ); ?></span>
                    </a>
                <?php endif; ?>

                <button class="site-header__menu-toggle" type="button" aria-expanded="false" aria-controls="site-navigation-menu">
                    <span class="site-header__menu-line" aria-hidden="true"></span>
                    <span class="site-header__menu-line" aria-hidden="true"></span>
                    <span class="site-header__menu-line" aria-hidden="true"></span>
                    <span class="screen-reader-text"><?php esc_html_e( 'Abrir menu', 'sultana-storefront' ); ?></span>
                </button>
            </div>
        </div>
    </div>

    <nav id="site-navigation-menu" class="site-navigation" aria-label="<?php esc_attr_e( 'Menu principal', 'sultana-storefront' ); ?>">
        <div class="site-navigation__container">
            <div class="site-navigation__scroll">
                <ul class="site-navigation__menu">
                    <li class="<?php echo is_front_page() ? 'current-menu-item' : ''; ?>"><a href="<?php echo esc_url( $store_url ); ?>"><?php esc_html_e( 'Inicio', 'sultana-storefront' ); ?></a></li>
                    <li class="<?php echo $is_shop_active ? 'current-menu-item' : ''; ?>"><a href="<?php echo esc_url( $shop_url ); ?>"><?php esc_html_e( 'Tienda', 'sultana-storefront' ); ?></a></li>

                    <?php foreach ( $product_terms as $product_term ) : ?>
                        <?php $term_link = get_term_link( $product_term ); ?>
                        <?php if ( ! is_wp_error( $term_link ) ) : ?>
                            <li class="<?php echo is_tax( 'product_cat', $product_term->slug ) ? 'current-menu-item' : ''; ?>"><a href="<?php echo esc_url( $term_link ); ?>"><?php echo esc_html( $product_term->name ); ?></a></li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="site-navigation__arrows">
                <button class="site-navigation__arrow site-navigation__arrow--prev" type="button" aria-label="<?php esc_attr_e( 'Ver categorias anteriores', 'sultana-storefront' ); ?>" disabled>
                    <img src="<?php echo esc_url( get_template_directory_uri() . '/assets/icons/chevron-left.svg' ); ?>" alt="" width="20" height="20" aria-hidden="true">
                </button>

                <button class="site-navigation__arrow site-navigation__arrow--next" type="button" aria-label="<?php esc_attr_e( 'Ver mas categorias', 'sultana-storefront' ); ?>">
                    <img src="<?php echo esc_url( get_template_directory_uri() . '/assets/icons/chevron-right.svg' ); ?>" alt="" width="20" height="20" aria-hidden="true">
                </button>
            </div>
        </div>
    </nav>
</header>

<main id="primary" class="site-main">

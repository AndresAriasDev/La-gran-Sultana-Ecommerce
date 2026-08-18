<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function variedadesexpress_asset_version( string $relative_path ): string
{
    $file_path = get_template_directory() . '/' . ltrim( $relative_path, '/' );

    if ( file_exists( $file_path ) ) {
        return (string) filemtime( $file_path );
    }

    return wp_get_theme()->get( 'Version' );
}

function variedadesexpress_enqueue_style( string $handle, string $relative_path, array $dependencies = [] ): void
{
    wp_enqueue_style(
        $handle,
        get_template_directory_uri() . '/' . ltrim( $relative_path, '/' ),
        $dependencies,
        variedadesexpress_asset_version( $relative_path )
    );
}

function variedadesexpress_enqueue_script( string $handle, string $relative_path, array $dependencies = [] ): void
{
    wp_enqueue_script(
        $handle,
        get_template_directory_uri() . '/' . ltrim( $relative_path, '/' ),
        $dependencies,
        variedadesexpress_asset_version( $relative_path ),
        true
    );
}

function variedadesexpress_add_dynamic_brand_color(): void
{
    if ( ! function_exists( 'sultana_storefront_store_primary_color' ) ) {
        return;
    }

    $primary_color = sultana_storefront_store_primary_color();

    if ( '' === $primary_color ) {
        return;
    }

    wp_add_inline_style(
        'sultana-storefront-tokens',
        sprintf( ':root{--color-brand:%s;}', esc_html( $primary_color ) )
    );
}

function variedadesexpress_is_shared_wishlist_page(): bool
{
    $wishlist_class = '\Sultana\CommerceCore\Modules\Wishlist\Wishlist';

    return class_exists( $wishlist_class ) && '' !== $wishlist_class::get_current_share_token();
}

function variedadesexpress_is_account_wishlist_endpoint(): bool
{
    if ( ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
        return false;
    }

    if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'wishlist' ) ) {
        return true;
    }

    global $wp_query;

    return is_object( $wp_query )
        && isset( $wp_query->query_vars )
        && is_array( $wp_query->query_vars )
        && array_key_exists( 'wishlist', $wp_query->query_vars );
}

function variedadesexpress_is_account_coupons_endpoint(): bool
{
    if ( ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
        return false;
    }

    if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'cupones' ) ) {
        return true;
    }

    global $wp_query;

    return is_object( $wp_query )
        && isset( $wp_query->query_vars )
        && is_array( $wp_query->query_vars )
        && array_key_exists( 'cupones', $wp_query->query_vars );
}

function variedadesexpress_is_cart_experience(): bool
{
    if ( function_exists( 'is_cart' ) && is_cart() ) {
        return true;
    }

    if ( function_exists( 'wc_get_page_id' ) && is_page( wc_get_page_id( 'cart' ) ) ) {
        return true;
    }

    $post = get_post();

    if ( ! $post instanceof WP_Post ) {
        return false;
    }

    return has_block( 'woocommerce/cart', $post ) || has_shortcode( $post->post_content, 'woocommerce_cart' );
}

function variedadesexpress_enqueue_assets(): void
{
    $is_product_search = is_search() && 'product' === get_query_var( 'post_type' );
    $is_product_listing = is_page( 'tienda' )
        || ( function_exists( 'is_shop' ) && is_shop() )
        || ( function_exists( 'is_product_taxonomy' ) && is_product_taxonomy() )
        || $is_product_search;

    variedadesexpress_enqueue_style( 'sultana-storefront-tokens', 'assets/css/base/tokens.css' );
    variedadesexpress_add_dynamic_brand_color();
    variedadesexpress_enqueue_style( 'sultana-storefront-reset', 'assets/css/base/reset.css', [ 'sultana-storefront-tokens' ] );
    variedadesexpress_enqueue_style( 'sultana-storefront-global', 'assets/css/base/global.css', [ 'sultana-storefront-reset' ] );
    variedadesexpress_enqueue_style( 'sultana-storefront-forms', 'assets/css/base/forms.css', [ 'sultana-storefront-global' ] );
    variedadesexpress_enqueue_style( 'sultana-storefront-buttons', 'assets/css/components/buttons.css', [ 'sultana-storefront-forms' ] );
    variedadesexpress_enqueue_style( 'sultana-storefront-header', 'assets/css/components/header.css', [ 'sultana-storefront-buttons' ] );
    variedadesexpress_enqueue_style( 'sultana-storefront-footer', 'assets/css/components/footer.css', [ 'sultana-storefront-header' ] );
    variedadesexpress_enqueue_style( 'sultana-storefront-cards', 'assets/css/components/cards.css', [ 'sultana-storefront-footer' ] );
    variedadesexpress_enqueue_style( 'sultana-storefront-woocommerce', 'assets/css/components/woocommerce.css', [ 'sultana-storefront-cards' ] );

    if ( is_front_page() ) {
        variedadesexpress_enqueue_style( 'sultana-storefront-home', 'assets/css/pages/home.css', [ 'sultana-storefront-woocommerce' ] );
    }

    variedadesexpress_enqueue_style( 'sultana-storefront-responsive', 'assets/css/base/responsive.css', [ 'sultana-storefront-woocommerce' ] );

    variedadesexpress_enqueue_script( 'sultana-storefront-header', 'assets/js/global/header.js' );
    variedadesexpress_enqueue_script( 'sultana-storefront-toast', 'assets/js/global/toast.js', [ 'sultana-storefront-header' ] );
    variedadesexpress_enqueue_script( 'sultana-storefront-account-modal', 'assets/js/global/account-modal.js', [ 'sultana-storefront-toast' ] );

    $storefront_config = [
            'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
            'cartUrl'     => function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/carrito/' ),
            'myAccountUrl' => function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/' ),
            'forYouNonce' => wp_create_nonce( 'variedadesexpress_for_you' ),
            'accountNonce' => wp_create_nonce( 'scc_account_register' ),
            'loginNonce'   => wp_create_nonce( 'scc_account_login' ),
            'passwordResetNonce' => wp_create_nonce( 'scc_password_reset_request' ),
            'passwordResetCompleteNonce' => wp_create_nonce( 'scc_password_reset_complete' ),
            'checkoutEmailStatusNonce' => wp_create_nonce( 'scc_checkout_email_status' ),
            'wishlistNonce' => wp_create_nonce( 'scc_wishlist' ),
            'themeUrl'    => get_template_directory_uri(),
            'icons'       => [
                'check' => variedadesexpress_get_icon_svg( 'check', 'site-toast__icon' ),
                'x'     => variedadesexpress_get_icon_svg( 'x', 'site-toast__icon' ),
            ],
            'notices'     => [
                'loggedOut' => __( 'Sesión cerrada correctamente.', 'sultana-storefront' ),
            ],
    ];
    $storefront_config_json = wp_json_encode( $storefront_config );

    if ( $storefront_config_json ) {
        wp_add_inline_script(
            'sultana-storefront-toast',
            sprintf(
                'window.sultanaStorefront=%s;window.variedadesExpress=window.sultanaStorefront;',
                $storefront_config_json
            ),
            'before'
        );
    }

    if ( is_front_page() ) {
        variedadesexpress_enqueue_script( 'sultana-storefront-home', 'assets/js/pages/home.js', [ 'sultana-storefront-toast' ] );
    }

    if ( $is_product_listing ) {
        variedadesexpress_enqueue_style( 'sultana-storefront-shop', 'assets/css/pages/shop.css', [ 'sultana-storefront-responsive' ] );
        variedadesexpress_enqueue_script( 'sultana-storefront-shop', 'assets/js/pages/shop.js', [ 'sultana-storefront-header' ] );
    }

    if ( function_exists( 'is_product' ) && is_product() ) {
        variedadesexpress_enqueue_style( 'sultana-storefront-single-product', 'assets/css/pages/single-product.css', [ 'sultana-storefront-responsive' ] );
        variedadesexpress_enqueue_script( 'sultana-storefront-single-product', 'assets/js/pages/single-product.js', [ 'jquery', 'sultana-storefront-account-modal' ] );
    }

    if ( variedadesexpress_is_cart_experience() ) {
        variedadesexpress_enqueue_style( 'sultana-storefront-cart', 'assets/css/pages/cart.css', [ 'sultana-storefront-responsive' ] );
        variedadesexpress_enqueue_script( 'sultana-storefront-cart', 'assets/js/pages/cart.js', [ 'sultana-storefront-account-modal' ] );
    }

    if ( function_exists( 'is_checkout' ) && is_checkout() ) {
        variedadesexpress_enqueue_style( 'sultana-storefront-checkout', 'assets/css/pages/checkout.css', [ 'sultana-storefront-responsive' ] );
    }

    if ( function_exists( 'is_checkout' ) && is_checkout() && ! is_order_received_page() ) {
        variedadesexpress_enqueue_script( 'sultana-storefront-checkout', 'assets/js/pages/checkout.js', [ 'jquery', 'sultana-storefront-toast' ] );
    }

    if ( function_exists( 'is_account_page' ) && is_account_page() ) {
        variedadesexpress_enqueue_style( 'sultana-storefront-account', 'assets/css/pages/account.css', [ 'sultana-storefront-responsive' ] );
    }

    if (
        function_exists( 'is_account_page' ) && is_account_page()
        && function_exists( 'is_wc_endpoint_url' )
        && ! is_wc_endpoint_url()
        && ! variedadesexpress_is_account_wishlist_endpoint()
        && ! variedadesexpress_is_account_coupons_endpoint()
    ) {
        variedadesexpress_enqueue_style( 'sultana-storefront-account-dashboard', 'assets/css/pages/account-dashboard.css', [ 'sultana-storefront-account' ] );
    }

    if (
        function_exists( 'is_account_page' ) && is_account_page()
        && function_exists( 'is_wc_endpoint_url' )
        && ( is_wc_endpoint_url( 'orders' ) || is_wc_endpoint_url( 'view-order' ) )
    ) {
        variedadesexpress_enqueue_style( 'sultana-storefront-account-orders', 'assets/css/pages/account-orders.css', [ 'sultana-storefront-account' ] );
    }

    if ( variedadesexpress_is_account_coupons_endpoint() ) {
        variedadesexpress_enqueue_style( 'sultana-storefront-account-coupons', 'assets/css/pages/account-coupons.css', [ 'sultana-storefront-account' ] );
    }

    if ( variedadesexpress_is_account_wishlist_endpoint() ) {
        variedadesexpress_enqueue_style( 'sultana-storefront-wishlist', 'assets/css/pages/wishlist.css', [ 'sultana-storefront-account' ] );
    } elseif ( variedadesexpress_is_shared_wishlist_page() ) {
        variedadesexpress_enqueue_style( 'sultana-storefront-wishlist', 'assets/css/pages/wishlist.css', [ 'sultana-storefront-responsive' ] );
    }

    if ( ( function_exists( 'is_account_page' ) && is_account_page() ) || variedadesexpress_is_shared_wishlist_page() ) {
        variedadesexpress_enqueue_script( 'sultana-storefront-account', 'assets/js/pages/account.js', [ 'sultana-storefront-account-modal' ] );
    }

}

add_action( 'wp_enqueue_scripts', 'variedadesexpress_enqueue_assets' );

function variedadesexpress_dequeue_home_wc_blocks_style(): void
{
    if ( ! is_front_page() ) {
        return;
    }

    wp_dequeue_style( 'wc-blocks-style' );
    wp_deregister_style( 'wc-blocks-style' );
    wp_dequeue_style( 'woocommerce-layout' );
    wp_deregister_style( 'woocommerce-layout' );
    wp_dequeue_style( 'woocommerce-smallscreen' );
    wp_deregister_style( 'woocommerce-smallscreen' );
    wp_dequeue_style( 'woocommerce-general' );
    wp_deregister_style( 'woocommerce-general' );
}

add_action( 'wp_enqueue_scripts', 'variedadesexpress_dequeue_home_wc_blocks_style', 100 );

function variedadesexpress_is_password_reset_route(): bool
{
    if ( '1' === (string) get_query_var( 'scc_password_reset' ) ) {
        return true;
    }

    $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
    $path        = (string) wp_parse_url( $request_uri, PHP_URL_PATH );
    $path        = trim( $path, '/' );

    return 'restablecer-contrasena' === $path;
}

function variedadesexpress_google_tag_manager_head(): void
{
    if ( variedadesexpress_is_password_reset_route() || ! function_exists( 'sultana_storefront_store_gtm_id' ) ) {
        return;
    }

    $gtm_id = sultana_storefront_store_gtm_id();

    if ( '' === $gtm_id ) {
        return;
    }

    ?>
    <!-- Google Tag Manager -->
    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
    new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
    j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
    'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','<?php echo esc_js( $gtm_id ); ?>');</script>
    <!-- End Google Tag Manager -->
    <?php
}

add_action( 'wp_head', 'variedadesexpress_google_tag_manager_head', 1 );

function variedadesexpress_google_analytics_head(): void
{
    if ( variedadesexpress_is_password_reset_route() || ! function_exists( 'sultana_storefront_store_ga4_id' ) ) {
        return;
    }

    $ga4_id = sultana_storefront_store_ga4_id();

    if ( '' === $ga4_id ) {
        return;
    }

    ?>
    <!-- Google tag (gtag.js) -->
    <script async src="<?php echo esc_url( add_query_arg( 'id', $ga4_id, 'https://www.googletagmanager.com/gtag/js' ) ); ?>"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());

      gtag('config', '<?php echo esc_js( $ga4_id ); ?>');
    </script>
    <?php
}

add_action( 'wp_head', 'variedadesexpress_google_analytics_head', 2 );

function variedadesexpress_google_tag_manager_body(): void
{
    if ( variedadesexpress_is_password_reset_route() || ! function_exists( 'sultana_storefront_store_gtm_id' ) ) {
        return;
    }

    $gtm_id = sultana_storefront_store_gtm_id();

    if ( '' === $gtm_id ) {
        return;
    }

    ?>
    <!-- Google Tag Manager (noscript) -->
    <noscript><iframe src="<?php echo esc_url( add_query_arg( 'id', $gtm_id, 'https://www.googletagmanager.com/ns.html' ) ); ?>"
    height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    <!-- End Google Tag Manager (noscript) -->
    <?php
}

add_action( 'wp_body_open', 'variedadesexpress_google_tag_manager_body', 1 );

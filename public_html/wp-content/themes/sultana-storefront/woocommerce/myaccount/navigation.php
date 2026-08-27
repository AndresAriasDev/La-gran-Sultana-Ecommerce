<?php
/**
 * Custom account navigation.
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

$items        = wc_get_account_menu_items();
$icon_map     = [
    'dashboard'       => 'layout-panel-left',
    'orders'          => 'shopping-bag',
    'wishlist'        => 'heart',
    'cupones'         => 'tickets',
    'edit-address'    => 'map-pin',
    'edit-account'    => 'user-pen',
    'customer-logout' => 'log-out',
];
$nav_classes = [ 've-account-nav' ];
$is_section_view = false;

foreach ( $items as $endpoint => $label ) {
    if ( 'dashboard' === $endpoint || 'customer-logout' === $endpoint ) {
        continue;
    }

    if ( str_contains( wc_get_account_menu_item_classes( $endpoint ), 'is-active' ) ) {
        $is_section_view = true;
        break;
    }
}

if ( $is_section_view ) {
    $nav_classes[] = 've-account-nav--endpoint';
}

$avatar_data = get_avatar_data(
    $current_user->ID,
    [
        'size' => 96,
    ]
);
$has_avatar_image = ! empty( $avatar_data['url'] ) && ! empty( $avatar_data['found_avatar'] );
$avatar_initial   = strtoupper( substr( $display_name ?: $current_user->user_email, 0, 1 ) );
?>

<nav class="<?php echo esc_attr( implode( ' ', $nav_classes ) ); ?>" aria-label="<?php esc_attr_e( 'Menu de cuenta', 'sultana-storefront' ); ?>" data-account-mobile-nav>
    <header class="ve-account-nav__toggle">
        <div class="ve-account-nav__avatar-wrap" data-profile-avatar>
            <div class="ve-account-nav__avatar" aria-hidden="true">
                <?php if ( $has_avatar_image ) : ?>
                    <?php
                    echo get_avatar(
                        $current_user->ID,
                        96,
                        '',
                        $display_name,
                        [
                            'class' => 've-account-nav__avatar-image',
                        ]
                    ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                    ?>
                <?php else : ?>
                    <?php echo esc_html( $avatar_initial ); ?>
                <?php endif; ?>
            </div>
            <button class="ve-account-nav__avatar-button" type="button" aria-label="<?php esc_attr_e( 'Cambiar foto de perfil', 'sultana-storefront' ); ?>" data-profile-avatar-button>
                <?php variedadesexpress_icon( 'camera', 've-account-nav__avatar-button-icon' ); ?>
            </button>
            <input class="ve-account-nav__avatar-input" type="file" accept="image/jpeg,image/png,image/webp,image/avif,.jfif" data-profile-avatar-input data-profile-avatar-nonce="<?php echo esc_attr( wp_create_nonce( 'scc_profile_avatar' ) ); ?>">
        </div>
        <div class="ve-account-nav__profile-text">
            <span><?php esc_html_e( 'Cuenta', 'sultana-storefront' ); ?></span>
            <strong><?php echo esc_html( $display_name ); ?></strong>
            <small><?php echo esc_html( $current_user->user_email ); ?></small>
        </div>
        <span class="ve-account-nav__chevron" aria-hidden="true"></span>
    </header>

    <ul id="ve-account-nav-list" class="ve-account-nav__list" data-account-mobile-nav-list>
        <?php foreach ( $items as $endpoint => $label ) : ?>
            <?php
            $classes   = wc_get_account_menu_item_classes( $endpoint );
            $icon_name = $icon_map[ $endpoint ] ?? 'user-pen';
            ?>
            <li class="<?php echo esc_attr( $classes ); ?>">
                <a href="<?php echo esc_url( wc_get_account_endpoint_url( $endpoint ) ); ?>">
                    <?php variedadesexpress_icon( $icon_name, 've-account-nav__icon' ); ?>
                    <span><?php echo esc_html( $label ); ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</nav>

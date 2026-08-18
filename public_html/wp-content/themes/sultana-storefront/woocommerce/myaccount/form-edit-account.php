<?php
/**
 * Custom edit account form.
 *
 * @package SultanaStorefront
 */

defined( 'ABSPATH' ) || exit;

$user       = wp_get_current_user();
$name_parts = array_values(
    array_filter(
        preg_split( '/\s+/', trim( $user->first_name . ' ' . $user->last_name ) )
    )
);

if ( count( $name_parts ) > 2 ) {
    $account_first_name   = implode( ' ', array_slice( $name_parts, 0, 2 ) );
    $account_last_name    = implode( ' ', array_slice( $name_parts, 2 ) );
    $account_display_name = trim( $name_parts[0] . ' ' . $name_parts[2] );
} else {
    $account_first_name   = $user->first_name;
    $account_last_name    = $user->last_name;
    $first_name_parts     = array_values( array_filter( preg_split( '/\s+/', trim( $account_first_name ) ) ) );
    $last_name_parts      = array_values( array_filter( preg_split( '/\s+/', trim( $account_last_name ) ) ) );
    $account_display_name = trim( ( $first_name_parts[0] ?? '' ) . ' ' . ( $last_name_parts[0] ?? '' ) );
}

$account_display_name = $account_display_name ?: $user->display_name;

do_action( 'woocommerce_before_edit_account_form' );
?>

<form class="woocommerce-EditAccountForm edit-account ve-account-form" action="" method="post" autocomplete="off" <?php do_action( 'woocommerce_edit_account_form_tag' ); ?>>
    <?php do_action( 'woocommerce_edit_account_form_start' ); ?>

    <section class="ve-account-panel">
        <header class="ve-account-section-title">
            <span aria-hidden="true"><?php variedadesexpress_icon( 'user-pen', 've-account-section-title__icon' ); ?></span>
            <div>
                <span><?php esc_html_e( 'Perfil', 'sultana-storefront' ); ?></span>
                <h1><?php esc_html_e( 'Detalles de cuenta', 'sultana-storefront' ); ?></h1>
                <p><?php esc_html_e( 'Mantené tus datos actualizados para pedidos, cupones y reseñas.', 'sultana-storefront' ); ?></p>
            </div>
        </header>

        <div class="ve-account-card">
            <h2><?php esc_html_e( 'Información personal', 'sultana-storefront' ); ?></h2>
            <div class="ve-account-form__grid">
                <p class="woocommerce-form-row woocommerce-form-row--first form-row form-row-first">
                    <label for="account_first_name"><?php esc_html_e( 'Nombres', 'sultana-storefront' ); ?>&nbsp;<span class="required">*</span></label>
                    <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="account_first_name" id="account_first_name" autocomplete="given-name" value="<?php echo esc_attr( $account_first_name ); ?>" />
                </p>
                <p class="woocommerce-form-row woocommerce-form-row--last form-row form-row-last">
                    <label for="account_last_name"><?php esc_html_e( 'Apellidos', 'sultana-storefront' ); ?>&nbsp;<span class="required">*</span></label>
                    <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="account_last_name" id="account_last_name" autocomplete="family-name" value="<?php echo esc_attr( $account_last_name ); ?>" />
                </p>
            </div>
            <div class="clear"></div>

            <div class="ve-account-form__grid ve-account-form__grid--spaced">
                <p class="woocommerce-form-row woocommerce-form-row--first form-row form-row-first">
                    <label for="account_display_name"><?php esc_html_e( 'Nombre visible', 'sultana-storefront' ); ?>&nbsp;<span class="required">*</span></label>
                    <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="account_display_name" id="account_display_name" value="<?php echo esc_attr( $account_display_name ); ?>" />
                    <span><em><?php esc_html_e( 'Así se mostrará tu nombre en tu cuenta y reseñas.', 'sultana-storefront' ); ?></em></span>
                </p>

                <p class="woocommerce-form-row woocommerce-form-row--last form-row form-row-last">
                    <label for="account_email"><?php esc_html_e( 'Correo electrónico', 'sultana-storefront' ); ?>&nbsp;<span class="required">*</span></label>
                    <input type="email" class="woocommerce-Input woocommerce-Input--email input-text ve-field-readonly" name="account_email" id="account_email" autocomplete="email" value="<?php echo esc_attr( $user->user_email ); ?>" readonly />
                </p>
            </div>
        </div>

        <div class="ve-account-card">
            <h2><?php esc_html_e( 'Contraseña', 'sultana-storefront' ); ?></h2>
            <p class="ve-account-card__hint"><?php esc_html_e( 'Dejá estos campos vacíos si no querés cambiar tu contraseña.', 'sultana-storefront' ); ?></p>

            <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                <label for="password_current"><?php esc_html_e( 'Contraseña actual', 'sultana-storefront' ); ?></label>
                <input type="password" class="woocommerce-Input woocommerce-Input--password input-text" name="password_current" id="password_current" autocomplete="off" value="" />
            </p>
            <div class="ve-account-form__grid">
                <p class="woocommerce-form-row woocommerce-form-row--first form-row form-row-first">
                    <label for="password_1"><?php esc_html_e( 'Nueva contraseña', 'sultana-storefront' ); ?></label>
                    <input type="password" class="woocommerce-Input woocommerce-Input--password input-text" name="password_1" id="password_1" autocomplete="new-password" value="" />
                </p>
                <p class="woocommerce-form-row woocommerce-form-row--last form-row form-row-last">
                    <label for="password_2"><?php esc_html_e( 'Confirmar contraseña', 'sultana-storefront' ); ?></label>
                    <input type="password" class="woocommerce-Input woocommerce-Input--password input-text" name="password_2" id="password_2" autocomplete="new-password" value="" />
                </p>
            </div>
        </div>

        <?php do_action( 'woocommerce_edit_account_form' ); ?>

        <p>
            <?php wp_nonce_field( 'save_account_details', 'save-account-details-nonce' ); ?>
            <button type="submit" class="woocommerce-Button button ve-account-form__submit" name="save_account_details" value="<?php esc_attr_e( 'Guardar cambios', 'sultana-storefront' ); ?>">
                <span><?php esc_html_e( 'Guardar cambios', 'sultana-storefront' ); ?></span>
            </button>
            <input type="hidden" name="action" value="save_account_details" />
        </p>
    </section>

    <?php do_action( 'woocommerce_edit_account_form_end' ); ?>
</form>

<?php do_action( 'woocommerce_after_edit_account_form' ); ?>

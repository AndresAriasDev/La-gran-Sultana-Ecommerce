<?php
/**
 * Custom edit account address form.
 *
 * @package SultanaStorefront
 */

defined( 'ABSPATH' ) || exit;

$page_title = 'billing' === $load_address
    ? __( 'Dirección principal', 'sultana-storefront' )
    : __( 'Dirección', 'sultana-storefront' );

$current_user = wp_get_current_user();
$name_parts   = array_values(
    array_filter(
        preg_split( '/\s+/', trim( $current_user->first_name . ' ' . $current_user->last_name ) )
    )
);

$normalized_first_name = get_user_meta( get_current_user_id(), $load_address . '_first_name', true );
$normalized_last_name  = get_user_meta( get_current_user_id(), $load_address . '_last_name', true );

if ( count( $name_parts ) > 2 ) {
    $normalized_first_name = implode( ' ', array_slice( $name_parts, 0, 2 ) );
    $normalized_last_name  = implode( ' ', array_slice( $name_parts, 2 ) );
}

do_action( 'woocommerce_before_edit_account_address_form' );
?>

<?php if ( ! $load_address ) : ?>
    <?php wc_get_template( 'myaccount/my-address.php' ); ?>
<?php else : ?>
    <form method="post" class="ve-account-form ve-address-form">
        <section class="ve-account-panel">
            <header class="ve-account-section-title">
                <span aria-hidden="true"><?php variedadesexpress_icon( 'map-pin', 've-account-section-title__icon' ); ?></span>
                <div>
                    <span><?php esc_html_e( 'Dirección principal', 'sultana-storefront' ); ?></span>
                    <h1><?php echo esc_html( apply_filters( 'woocommerce_my_account_edit_address_title', $page_title, $load_address ) ); ?></h1>
                    <p><?php esc_html_e( 'Usaremos esta misma dirección para facturación y envío.', 'sultana-storefront' ); ?></p>
                </div>
            </header>

            <div class="ve-account-card ve-address-form__card">
                <div class="woocommerce-address-fields">
                    <?php do_action( "woocommerce_before_edit_address_form_{$load_address}" ); ?>

                    <div class="woocommerce-address-fields__field-wrapper ve-address-form__grid">
                        <?php
                        foreach ( $address as $key => $field ) {
                            if ( $load_address . '_address_2' === $key ) {
                                continue;
                            }

                            $value = get_user_meta( get_current_user_id(), $key, true );
                            $state_value = get_user_meta( get_current_user_id(), $load_address . '_state', true );

                            if ( $load_address . '_first_name' === $key && $normalized_first_name ) {
                                $value = $normalized_first_name;
                            }

                            if ( $load_address . '_last_name' === $key && $normalized_last_name ) {
                                $value = $normalized_last_name;
                            }

                            if ( $load_address . '_country' === $key ) {
                                $value = 'NI';
                            }

                            if ( $load_address . '_postcode' === $key ) {
                                $value = '';
                            }

                            if ( function_exists( 'variedadesexpress_normalize_address_location_value' ) && in_array( $key, [ $load_address . '_state', $load_address . '_city' ], true ) ) {
                                $value = variedadesexpress_normalize_address_location_value( $key, (string) $value );
                            }

                            if ( $load_address . '_city' === $key && function_exists( 'variedadesexpress_nicaragua_municipality_options' ) ) {
                                $field['options'] = variedadesexpress_nicaragua_municipality_options(
                                    function_exists( 'variedadesexpress_normalize_address_location_value' )
                                        ? variedadesexpress_normalize_address_location_value( $load_address . '_state', (string) $state_value )
                                        : (string) $state_value
                                );
                            }

                            woocommerce_form_field( $key, $field, wc_get_post_data_by_key( $key, $value ) );
                        }
                        ?>
                    </div>

                    <?php do_action( "woocommerce_after_edit_address_form_{$load_address}" ); ?>

                    <p class="ve-address-form__actions">
                        <button type="submit" class="button ve-account-form__submit ve-address-form__submit" name="save_address" value="<?php esc_attr_e( 'Guardar cambios', 'sultana-storefront' ); ?>">
                            <?php variedadesexpress_icon( 'save', 've-address-form__submit-icon' ); ?>
                            <span><?php esc_html_e( 'Guardar cambios', 'sultana-storefront' ); ?></span>
                        </button>
                        <?php wp_nonce_field( 'woocommerce-edit_address', 'woocommerce-edit-address-nonce' ); ?>
                        <input type="hidden" name="action" value="edit_address" />
                    </p>
                </div>
            </div>
        </section>
    </form>
<?php endif; ?>

<?php do_action( 'woocommerce_after_edit_account_address_form' ); ?>

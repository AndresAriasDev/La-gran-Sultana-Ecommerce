<?php
/**
 * Custom account address page with one principal address.
 *
 * @package SultanaStorefront
 */

defined( 'ABSPATH' ) || exit;

$edit_url    = wc_get_endpoint_url( 'edit-address', 'billing', wc_get_page_permalink( 'myaccount' ) );
$customer    = class_exists( 'WC_Customer' ) ? new WC_Customer( get_current_user_id() ) : null;
$address_parts = [
    'first_name' => $customer ? $customer->get_billing_first_name() : get_user_meta( get_current_user_id(), 'billing_first_name', true ),
    'last_name'  => $customer ? $customer->get_billing_last_name() : get_user_meta( get_current_user_id(), 'billing_last_name', true ),
    'address_1'  => $customer ? $customer->get_billing_address_1() : get_user_meta( get_current_user_id(), 'billing_address_1', true ),
    'city'       => $customer ? $customer->get_billing_city() : get_user_meta( get_current_user_id(), 'billing_city', true ),
    'state'      => $customer ? $customer->get_billing_state() : get_user_meta( get_current_user_id(), 'billing_state', true ),
    'country'    => 'NI',
];
$address     = WC()->countries->get_formatted_address( $address_parts );
$has_address = ! empty( $address );
$billing_phone = $customer && is_callable( [ $customer, 'get_billing_phone' ] )
    ? sanitize_text_field( (string) $customer->get_billing_phone() )
    : sanitize_text_field( (string) get_user_meta( get_current_user_id(), 'billing_phone', true ) );
$billing_email = $customer && is_callable( [ $customer, 'get_billing_email' ] )
    ? sanitize_email( (string) $customer->get_billing_email() )
    : sanitize_email( (string) get_user_meta( get_current_user_id(), 'billing_email', true ) );

do_action( 'woocommerce_before_edit_account_address_form' );
?>

<section class="ve-account-panel ve-account-panel--address-overview">
    <header class="ve-account-section-title">
        <span aria-hidden="true"><?php variedadesexpress_icon( 'map-pin', 've-account-section-title__icon' ); ?></span>
        <div>
            <span><?php esc_html_e( 'Dirección principal', 'sultana-storefront' ); ?></span>
            <h1><?php esc_html_e( 'Mi dirección', 'sultana-storefront' ); ?></h1>
            <p><?php esc_html_e( 'Usaremos esta misma dirección para facturación y envío.', 'sultana-storefront' ); ?></p>
        </div>
    </header>

    <div class="ve-account-card ve-account-address-card">
        <div class="ve-account-address-card__content">
            <h2><?php esc_html_e( 'Dirección de entrega', 'sultana-storefront' ); ?></h2>
            <address>
                <?php
                if ( $has_address ) {
                    echo wp_kses_post( $address );

                    if ( '' !== $billing_phone ) {
                        printf(
                            '<span class="ve-account-address-card__contact-line">%s</span>',
                            esc_html(
                                sprintf(
                                    /* translators: %s: billing phone. */
                                    __( 'Teléfono: %s', 'sultana-storefront' ),
                                    $billing_phone
                                )
                            )
                        );
                    }

                    if ( '' !== $billing_email ) {
                        printf(
                            '<span class="ve-account-address-card__contact-line ve-account-address-card__contact-line--email">%s</span>',
                            esc_html(
                                sprintf(
                                    /* translators: %s: billing email. */
                                    __( 'Correo electrónico: %s', 'sultana-storefront' ),
                                    $billing_email
                                )
                            )
                        );
                    }
                } else {
                    esc_html_e( 'Aún no has agregado una dirección.', 'sultana-storefront' );
                }
                ?>
            </address>
        </div>

        <a class="ve-account-address-card__button" href="<?php echo esc_url( $edit_url ); ?>">
            <?php variedadesexpress_icon( 'map-pin', 've-account-address-card__button-icon' ); ?>
            <span><?php echo esc_html( $has_address ? __( 'Editar dirección', 'sultana-storefront' ) : __( 'Agregar dirección', 'sultana-storefront' ) ); ?></span>
        </a>
    </div>
</section>

<?php do_action( 'woocommerce_after_edit_account_address_form' ); ?>

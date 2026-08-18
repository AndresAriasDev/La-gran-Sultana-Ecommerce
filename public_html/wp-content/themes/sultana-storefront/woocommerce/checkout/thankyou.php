<?php
/**
 * Custom order received page.
 *
 * @package SultanaStorefront
 */

defined( 'ABSPATH' ) || exit;

if ( ! $order ) {
    ?>
    <section class="ve-order-received ve-order-received--empty">
        <h1><?php esc_html_e( 'Pedido recibido', 'sultana-storefront' ); ?></h1>
        <p><?php esc_html_e( 'Gracias. Tu pedido ha sido recibido.', 'sultana-storefront' ); ?></p>
    </section>
    <?php
    return;
}

$order_id       = $order->get_id();
$payment_method = $order->get_payment_method();
$state          = $order->get_billing_state();
$city           = $order->get_billing_city();
$state_label    = $state;
$city_label     = $city;
$bacs_accounts  = array_filter( (array) get_option( 'woocommerce_bacs_accounts', [] ) );
$whatsapp_phone = apply_filters( 'variedadesexpress_payment_confirmation_whatsapp', '50586687005' );
$order_total    = wp_strip_all_tags( html_entity_decode( $order->get_formatted_order_total(), ENT_QUOTES, get_bloginfo( 'charset' ) ) );
$first_bank_title = '';
$usd_exchange_rate = (float) get_option( 'variedadesexpress_usd_exchange_rate', 0 );
$orders_url = wc_get_endpoint_url( 'orders', '', wc_get_page_permalink( 'myaccount' ) );

if ( $usd_exchange_rate <= 0 ) {
    $usd_exchange_rate = (float) apply_filters( 'variedadesexpress_usd_exchange_rate', 36.75 );
}

$usd_equivalent = $usd_exchange_rate > 0 ? ceil( ( (float) $order->get_total() / $usd_exchange_rate ) * 100 ) / 100 : 0;

if ( empty( $bacs_accounts ) && function_exists( 'WC' ) && WC()->payment_gateways() ) {
    $payment_gateways = WC()->payment_gateways()->payment_gateways();
    $bacs_gateway     = $payment_gateways['bacs'] ?? null;

    if ( is_object( $bacs_gateway ) && is_callable( [ $bacs_gateway, 'get_option' ] ) ) {
        $bacs_accounts = array_filter( (array) $bacs_gateway->get_option( 'account_details', [] ) );
    }
}

if ( function_exists( 'WC' ) && WC()->countries ) {
    $states = WC()->countries->get_states( 'NI' );

    if ( is_array( $states ) && isset( $states[ $state ] ) ) {
        $state_label = $states[ $state ];
    }
}

if ( function_exists( 'variedadesexpress_nicaragua_municipality_options' ) ) {
    $municipalities = variedadesexpress_nicaragua_municipality_options( $state );

    if ( isset( $municipalities[ $city ] ) ) {
        $city_label = $municipalities[ $city ];
    }
}

if ( function_exists( 'woocommerce_order_details_table' ) ) {
    remove_action( 'woocommerce_thankyou', 'woocommerce_order_details_table', 10 );
}
?>

<section class="ve-order-received">
    <?php if ( $order->has_status( 'failed' ) ) : ?>
        <div class="ve-order-received__panel ve-order-received__panel--failed">
            <h1><?php esc_html_e( 'No pudimos procesar tu pedido', 'sultana-storefront' ); ?></h1>
            <p><?php esc_html_e( 'Intenta nuevamente o contactanos si necesitas ayuda.', 'sultana-storefront' ); ?></p>

            <div class="ve-order-received__actions">
                <a class="button" href="<?php echo esc_url( $order->get_checkout_payment_url() ); ?>">
                    <?php esc_html_e( 'Intentar de nuevo', 'sultana-storefront' ); ?>
                </a>

                <?php if ( is_user_logged_in() ) : ?>
                    <a class="button" href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>">
                        <?php esc_html_e( 'Mi cuenta', 'sultana-storefront' ); ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php else : ?>
        <header class="ve-order-received__hero">
            <span class="ve-order-received__icon" aria-hidden="true"></span>
            <div class="ve-order-received__hero-body">
                <div class="ve-order-received__hero-heading">
                    <span class="ve-order-received__eyebrow"><?php esc_html_e( 'Último paso', 'sultana-storefront' ); ?></span>
                    <h1><?php esc_html_e( 'Completa tu compra', 'sultana-storefront' ); ?></h1>
                </div>
                <p>
                    <?php esc_html_e( 'Transfiere el total a la cuenta de banco de tu preferencia.', 'sultana-storefront' ); ?>
                </p>
            </div>
        </header>

        <div class="ve-order-received__grid">
            <section class="ve-order-received__panel ve-order-received__bank">
                <?php if ( 'bacs' === $payment_method && ! empty( $bacs_accounts ) ) : ?>
                    <div class="ve-order-received__section-title">
                        <h2><?php esc_html_e( 'Nuestras cuentas', 'sultana-storefront' ); ?></h2>
                    </div>

                    <div class="ve-bank-accordion" data-bank-accordion>
                        <?php foreach ( $bacs_accounts as $index => $account ) : ?>
                            <?php
                            $bank_name      = sanitize_text_field( (string) ( $account['bank_name'] ?? '' ) );
                            $account_name   = sanitize_text_field( (string) ( $account['account_name'] ?? '' ) );
                            $account_number = sanitize_text_field( (string) ( $account['account_number'] ?? '' ) );
                            $currency       = sanitize_text_field( (string) ( $account['currency'] ?? '' ) );
                            $button_id      = 've-bank-panel-' . $index;
                            $is_open        = 0 === $index;
                            $title_parts    = array_filter( [ $bank_name, $currency ] );
                            $title          = implode( ' - ', $title_parts );
                            $bank_key       = sanitize_html_class( strtolower( preg_replace( '/[^a-z0-9]+/i', '-', $bank_name ) ) );

                            if ( '' === $title ) {
                                $title = sprintf(
                                    /* translators: %d: account number index. */
                                    __( 'Cuenta bancaria %d', 'sultana-storefront' ),
                                    $index + 1
                                );
                            }

                            if ( 0 === $index ) {
                                $first_bank_title = $title;
                            }
                            ?>
                            <article class="ve-bank-account ve-bank-account--<?php echo esc_attr( $bank_key ?: 'default' ); ?> <?php echo $is_open ? 'is-open' : ''; ?>" data-bank-title="<?php echo esc_attr( $title ); ?>">
                                <button
                                    class="ve-bank-account__toggle"
                                    type="button"
                                    aria-expanded="<?php echo $is_open ? 'true' : 'false'; ?>"
                                    aria-controls="<?php echo esc_attr( $button_id ); ?>"
                                    data-bank-toggle
                                >
                                    <span>
                                        <strong><?php echo esc_html( $title ); ?></strong>
                                    </span>
                                    <span class="ve-bank-account__chevron" aria-hidden="true"></span>
                                </button>

                                <div class="ve-bank-account__panel" id="<?php echo esc_attr( $button_id ); ?>" <?php echo $is_open ? '' : 'hidden'; ?> data-bank-panel>
                                    <div class="ve-bank-account__row ve-bank-account__row--reference">
                                        <div>
                                            <span><?php esc_html_e( 'Usa este numero como referencia de tu transferencia.', 'sultana-storefront' ); ?></span>
                                            <strong>
                                                #<?php echo esc_html( $order->get_order_number() ); ?>
                                                <button class="ve-bank-copy" type="button" data-copy-value="<?php echo esc_attr( $order->get_order_number() ); ?>" aria-label="<?php esc_attr_e( 'Copiar numero de orden', 'sultana-storefront' ); ?>">
                                                    <img src="<?php echo esc_url( get_template_directory_uri() . '/assets/icons/copy.svg' ); ?>" alt="" width="18" height="18" aria-hidden="true">
                                                </button>
                                            </strong>
                                        </div>
                                    </div>

                                    <div class="ve-bank-account__details">
                                        <?php if ( $account_name || $account_number ) : ?>
                                            <div class="ve-bank-account__row">
                                                <div>
                                                    <span><?php esc_html_e( 'Cuenta', 'sultana-storefront' ); ?></span>

                                                    <?php if ( $account_name ) : ?>
                                                        <strong><?php echo esc_html( $account_name ); ?></strong>
                                                    <?php endif; ?>

                                                    <?php if ( $account_number ) : ?>
                                                        <strong class="ve-bank-account__number">
                                                            <?php echo esc_html( $account_number ); ?>
                                                            <button class="ve-bank-copy" type="button" data-copy-value="<?php echo esc_attr( $account_number ); ?>" aria-label="<?php esc_attr_e( 'Copiar numero de cuenta', 'sultana-storefront' ); ?>">
                                                                <img src="<?php echo esc_url( get_template_directory_uri() . '/assets/icons/copy.svg' ); ?>" alt="" width="18" height="18" aria-hidden="true">
                                                            </button>
                                                        </strong>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <?php do_action( 'woocommerce_thankyou_' . $payment_method, $order_id ); ?>
                <?php endif; ?>
            </section>

            <aside class="ve-order-received__panel ve-order-received__summary">
                <div class="ve-order-received__summary-card">
                    <span class="ve-order-received__eyebrow"><?php esc_html_e( 'Resumen', 'sultana-storefront' ); ?></span>
                    <dl>
                        <div>
                            <dt><?php esc_html_e( 'Total a pagar', 'sultana-storefront' ); ?></dt>
                            <dd>
                                <span class="ve-order-received__total-pill"><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></span>
                                <?php if ( $usd_equivalent > 0 ) : ?>
                                    <span class="ve-order-received__total-note">
                                        <?php
                                        echo esc_html(
                                            sprintf(
                                                /* translators: %s: USD equivalent amount. */
                                                __( 'Equivalente para transferencia en dolares: US$%s', 'sultana-storefront' ),
                                                number_format_i18n( $usd_equivalent, 2 )
                                            )
                                        );
                                        ?>
                                    </span>
                                <?php endif; ?>
                            </dd>
                        </div>
                    </dl>
                </div>

                <?php if ( 'bacs' === $payment_method && ! empty( $bacs_accounts ) ) : ?>
                    <div
                        class="ve-payment-confirmation"
                        data-payment-confirmation
                        data-whatsapp-phone="<?php echo esc_attr( preg_replace( '/\D+/', '', (string) $whatsapp_phone ) ); ?>"
                        data-order-number="<?php echo esc_attr( $order->get_order_number() ); ?>"
                        data-order-total="<?php echo esc_attr( $order_total ); ?>"
                        data-orders-url="<?php echo esc_url( $orders_url ); ?>"
                    >
                        <h3><?php esc_html_e( '¿Ya realizaste la transferencia?', 'sultana-storefront' ); ?></h3>
                        <p>
                            <?php esc_html_e( 'Toca este botón para agilizar la confirmación de tu pago.', 'sultana-storefront' ); ?>
                        </p>

                        <dl class="screen-reader-text">
                            <div>
                                <dt><?php esc_html_e( 'Pedido', 'sultana-storefront' ); ?></dt>
                                <dd>#<?php echo esc_html( $order->get_order_number() ); ?></dd>
                            </div>
                            <div>
                                <dt><?php esc_html_e( 'Monto', 'sultana-storefront' ); ?></dt>
                                <dd><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></dd>
                            </div>
                            <div>
                                <dt><?php esc_html_e( 'Banco', 'sultana-storefront' ); ?></dt>
                                <dd data-payment-bank><?php echo esc_html( $first_bank_title ); ?></dd>
                            </div>
                        </dl>

                        <a class="ve-payment-confirmation__button" href="#" target="_blank" rel="noopener" data-payment-whatsapp>
                            <span class="ve-payment-confirmation__icon" aria-hidden="true"></span>
                            <?php esc_html_e( 'Avisar por WhatsApp', 'sultana-storefront' ); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </aside>
        </div>
    <?php endif; ?>
</section>

<script>
document.addEventListener("DOMContentLoaded", function () {
  document.querySelectorAll("[data-bank-accordion]").forEach(function (accordion) {
    const confirmation = document.querySelector("[data-payment-confirmation]");
    const bankLabel = confirmation ? confirmation.querySelector("[data-payment-bank]") : null;
    const whatsappLink = confirmation ? confirmation.querySelector("[data-payment-whatsapp]") : null;
    const ordersUrl = confirmation ? confirmation.getAttribute("data-orders-url") || "" : "";

    const updatePaymentConfirmation = function () {
      if (!confirmation || !bankLabel || !whatsappLink) {
        return;
      }

      const activeAccount = accordion.querySelector(".ve-bank-account.is-open");
      const bankTitle = activeAccount ? activeAccount.getAttribute("data-bank-title") || "" : "";
      const phone = confirmation.getAttribute("data-whatsapp-phone") || "";
      const orderNumber = confirmation.getAttribute("data-order-number") || "";
      const orderTotal = confirmation.getAttribute("data-order-total") || "";
      const message = "Hola, ya realice la transferencia del pedido #" + orderNumber + " por " + orderTotal + " a " + bankTitle + ".";

      bankLabel.textContent = bankTitle;
      whatsappLink.href = "https://wa.me/" + phone + "?text=" + encodeURIComponent(message);
    };

    updatePaymentConfirmation();

    if (whatsappLink && ordersUrl) {
      whatsappLink.addEventListener("click", function () {
        window.setTimeout(function () {
          window.location.href = ordersUrl;
        }, 900);
      });
    }

    accordion.querySelectorAll("[data-bank-toggle]").forEach(function (toggle) {
      toggle.addEventListener("click", function () {
        const account = toggle.closest(".ve-bank-account");
        const panel = account ? account.querySelector("[data-bank-panel]") : null;

        if (!account || !panel || account.classList.contains("is-open")) {
          return;
        }

        accordion.querySelectorAll(".ve-bank-account").forEach(function (item) {
          const itemToggle = item.querySelector("[data-bank-toggle]");
          const itemPanel = item.querySelector("[data-bank-panel]");

          item.classList.remove("is-open");

          if (itemToggle) {
            itemToggle.setAttribute("aria-expanded", "false");
          }

          if (itemPanel) {
            itemPanel.hidden = true;
          }
        });

        account.classList.add("is-open");
        toggle.setAttribute("aria-expanded", "true");
        panel.hidden = false;
        updatePaymentConfirmation();
      });
    });
  });

  document.querySelectorAll("[data-copy-value]").forEach(function (button) {
    button.addEventListener("click", function () {
      const value = button.getAttribute("data-copy-value") || "";

      if (!value || !navigator.clipboard) {
        return;
      }

      navigator.clipboard.writeText(value).then(function () {
        const oldToast = document.querySelector(".ve-bank-copy-toast");
        const toast = document.createElement("span");

        if (oldToast) {
          oldToast.remove();
        }

        toast.className = "ve-bank-copy-toast";
        toast.setAttribute("role", "status");
        toast.textContent = "Texto copiado";
        button.appendChild(toast);

        window.requestAnimationFrame(function () {
          toast.classList.add("is-visible");
        });

        window.setTimeout(function () {
          toast.classList.remove("is-visible");
          window.setTimeout(function () {
            toast.remove();
          }, 180);
        }, 1600);
      });
    });
  });
});
</script>

<?php do_action( 'woocommerce_thankyou', $order_id ); ?>

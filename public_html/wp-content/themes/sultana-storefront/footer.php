<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$shop_url      = class_exists( 'WooCommerce' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
$account_url   = class_exists( 'WooCommerce' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/' );
$terms_page_id = class_exists( 'WooCommerce' ) ? wc_terms_and_conditions_page_id() : 0;
$terms_url     = $terms_page_id ? get_permalink( $terms_page_id ) : home_url( '/terminos-y-condiciones/' );
$privacy_url   = get_privacy_policy_url() ?: home_url( '/politicas-de-privacidad/' );
$store_name    = function_exists( 'sultana_storefront_store_name' ) ? sultana_storefront_store_name() : wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
$store_url     = function_exists( 'sultana_storefront_store_url' ) ? sultana_storefront_store_url() : home_url( '/' );
$logo_url      = function_exists( 'sultana_storefront_store_logo_url' ) ? sultana_storefront_store_logo_url() : '';
$whatsapp_text = '+505 8668 7005';
$whatsapp_url  = 'https://wa.me/50586687005?text=Hola%20Andr%C3%A9s%2C%20me%20interesa%20la%20tienda%20en%20l%C3%ADnea%20y%20me%20gustar%C3%ADa%20recibir%20m%C3%A1s%20informaci%C3%B3n.';
$contact_emails = [
    'andresariasdev02@gmail.com',
    'contacto@lagransultana.com',
];
$social_urls   = function_exists( 'sultana_storefront_store_social_url' )
    ? [
        'facebook'  => sultana_storefront_store_social_url( 'facebook' ),
        'instagram' => sultana_storefront_store_social_url( 'instagram' ),
        'tiktok'    => sultana_storefront_store_social_url( 'tiktok' ),
    ]
    : [];
$has_contact   = '' !== $whatsapp_text || ! empty( $contact_emails );
$social_labels = [
    'facebook'  => sprintf( __( 'Facebook de %s', 'sultana-storefront' ), $store_name ),
    'instagram' => sprintf( __( 'Instagram de %s', 'sultana-storefront' ), $store_name ),
    'tiktok'    => sprintf( __( 'TikTok de %s', 'sultana-storefront' ), $store_name ),
];
?>

</main>

<footer class="site-footer">
    <div class="site-footer__container">
        <div class="site-footer__brand">
            <a class="site-footer__logo-link" href="<?php echo esc_url( $store_url ); ?>" rel="home">
                <?php if ( $logo_url ) : ?>
                    <img
                        class="site-footer__logo"
                        src="<?php echo esc_url( $logo_url ); ?>"
                        alt="<?php echo esc_attr( $store_name ); ?>"
                        width="220"
                        height="60"
                        loading="lazy"
                        decoding="async"
                    >
                <?php else : ?>
                    <span class="site-footer__logo-text"><?php echo esc_html( $store_name ); ?></span>
                <?php endif; ?>
            </a>
            <p><?php esc_html_e( 'Belleza, cuidado personal y accesorios seleccionados para comprar facil en Nicaragua.', 'sultana-storefront' ); ?></p>
        </div>

        <nav class="site-footer__section" aria-labelledby="site-footer-shopping-title">
            <h2 id="site-footer-shopping-title" class="site-footer__heading">
                <?php esc_html_e( 'Enlaces', 'sultana-storefront' ); ?>
            </h2>
            <ul class="site-footer__menu">
                <li><a href="<?php echo esc_url( $shop_url ); ?>"><?php esc_html_e( 'Tienda', 'sultana-storefront' ); ?></a></li>
                <li><a href="<?php echo esc_url( add_query_arg( 'on_sale', '1', $shop_url ) ); ?>"><?php esc_html_e( 'Promociones', 'sultana-storefront' ); ?></a></li>
                <li><a href="<?php echo esc_url( home_url( '/#home-brands-title' ) ); ?>"><?php esc_html_e( 'Marcas', 'sultana-storefront' ); ?></a></li>
            </ul>
        </nav>

        <?php if ( $has_contact ) : ?>
            <address id="site-footer-contact" class="site-footer__section site-footer__contact">
                <h2 class="site-footer__heading">
                    <?php esc_html_e( 'Contacto', 'sultana-storefront' ); ?>
                </h2>
                <a class="site-footer__contact-link" href="<?php echo esc_url( $whatsapp_url ); ?>" target="_blank" rel="noopener">
                    <span class="site-footer__icon site-footer__icon--whatsapp" aria-hidden="true"></span>
                    <span><?php echo esc_html( $whatsapp_text ); ?></span>
                </a>
                <div class="site-footer__contact-item">
                    <span class="site-footer__icon site-footer__icon--mail" aria-hidden="true"></span>
                    <span class="site-footer__contact-emails">
                        <?php foreach ( $contact_emails as $contact_email ) : ?>
                            <a class="site-footer__contact-link" href="<?php echo esc_url( 'mailto:' . $contact_email ); ?>">
                                <?php echo esc_html( $contact_email ); ?>
                            </a>
                        <?php endforeach; ?>
                    </span>
                </div>
            </address>
        <?php endif; ?>

        <nav class="site-footer__section" aria-labelledby="site-footer-support-title">
            <h2 id="site-footer-support-title" class="site-footer__heading">
                <?php esc_html_e( 'Atencion al cliente', 'sultana-storefront' ); ?>
            </h2>
            <ul class="site-footer__menu">
                <?php if ( $has_contact ) : ?>
                    <li><a href="<?php echo esc_url( home_url( '/#site-footer-contact' ) ); ?>"><?php esc_html_e( 'Contacto', 'sultana-storefront' ); ?></a></li>
                <?php endif; ?>
                <li><a href="<?php echo esc_url( $account_url ); ?>"><?php esc_html_e( 'Mi cuenta', 'sultana-storefront' ); ?></a></li>
                <li><a href="<?php echo esc_url( home_url( '/cupones/' ) ); ?>"><?php esc_html_e( 'Cupones', 'sultana-storefront' ); ?></a></li>
                <li><a href="<?php echo esc_url( home_url( '/faq/' ) ); ?>"><?php esc_html_e( 'FAQ', 'sultana-storefront' ); ?></a></li>
            </ul>
        </nav>
    </div>

    <div class="site-footer__utility">
        <nav class="site-footer__legal" aria-label="<?php esc_attr_e( 'Enlaces legales', 'sultana-storefront' ); ?>">
            <a href="<?php echo esc_url( $terms_url ); ?>"><?php esc_html_e( 'Terminos y condiciones', 'sultana-storefront' ); ?></a>
            <a href="<?php echo esc_url( $privacy_url ); ?>"><?php esc_html_e( 'Politicas de privacidad', 'sultana-storefront' ); ?></a>
        </nav>

        <?php if ( array_filter( $social_urls ) ) : ?>
            <div class="site-footer__socials">
                <?php foreach ( $social_urls as $network => $social_url ) : ?>
                    <?php if ( '' === $social_url ) : ?>
                        <?php continue; ?>
                    <?php endif; ?>
                    <a href="<?php echo esc_url( $social_url ); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr( $social_labels[ $network ] ); ?>">
                        <span class="site-footer__icon site-footer__icon--<?php echo esc_attr( $network ); ?>" aria-hidden="true"></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="site-footer__bar">
        <p>
            <?php esc_html_e( 'Tienda Maes®', 'sultana-storefront' ); ?> |
            <?php esc_html_e( 'Desarrollado y diseñado por AndresAriasDev', 'sultana-storefront' ); ?>
        </p>
    </div>
</footer>

<?php locate_template( 'templates/components/account-register-modal.php', true, false ); ?>

<?php wp_footer(); ?>
</body>
</html>

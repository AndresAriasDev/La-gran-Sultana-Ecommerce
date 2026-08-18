<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'variedadesexpress_home_for_you_products' ) ) {
    return;
}

$for_you_limit = defined( 'SULTANA_STOREFRONT_HOME_FOR_YOU_BATCH_SIZE' ) ? SULTANA_STOREFRONT_HOME_FOR_YOU_BATCH_SIZE : 30;
$for_you_data  = variedadesexpress_home_for_you_products( $for_you_limit );
?>

<?php if ( $for_you_data['products'] ) : ?>
    <section class="home-for-you" aria-labelledby="home-for-you-title">
        <header class="home-for-you__header">
            <span aria-hidden="true"></span>
            <h2 id="home-for-you-title">
                <?php esc_html_e( 'Para vos', 'sultana-storefront' ); ?>
            </h2>
            <span aria-hidden="true"></span>
        </header>

        <div class="home-for-you__grid" data-for-you-grid>
            <?php foreach ( $for_you_data['products'] as $product ) : ?>
                <?php variedadesexpress_home_for_you_card( $product ); ?>
            <?php endforeach; ?>
        </div>

        <?php if ( $for_you_data['has_more'] ) : ?>
            <div class="home-for-you__actions">
                <button
                    class="home-for-you__load-more"
                    type="button"
                    data-for-you-load-more
                    data-offset="<?php echo esc_attr( count( $for_you_data['products'] ) ); ?>"
                >
                    <?php esc_html_e( 'Ver mas', 'sultana-storefront' ); ?>
                </button>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php

use Sultana\Admin\Promotions\PromotionController;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$promotions          = $screen_data['promotions'] ?? [];
$form                = $screen_data['form'] ?? [];
$selected_images     = $screen_data['selected_images'] ?? [];
$destination_options = $screen_data['destination_options'] ?? [];
$destination_choices = $screen_data['destination_choices'] ?? [];
$errors              = $screen_data['errors'] ?? [];
$notice              = $screen_data['notice'] ?? '';
$form_action         = $screen_data['form_action'] ?? \Sultana\Admin\Core\Router::banners_url();
$promotion_id        = absint( $form['id'] ?? ( $form['promotion_id'] ?? 0 ) );
$form_title          = $promotion_id ? __( 'Editar banner', 'sultana-admin' ) : __( 'Nuevo banner', 'sultana-admin' );
$new_url             = \Sultana\Admin\Core\Router::banners_url();
$icon_url            = static fn( string $name ): string => \Sultana\Admin\Core\Icons::url( $name );

$render_image_field = static function ( string $slot, string $label, string $recommendation ) use ( $form, $selected_images, $icon_url ): void {
    $field_name = $slot . '_image_id';
    $image      = is_array( $selected_images[ $slot ] ?? null ) ? $selected_images[ $slot ] : null;
    $image_id   = absint( $form[ $field_name ] ?? 0 );
    $image_json = $image ? wp_json_encode( $image ) : '{}';
    ?>
    <section
        class="sultana-admin-banner-image"
        data-sultana-promotion-image="<?php echo esc_attr( $slot ); ?>"
        data-initial-image="<?php echo esc_attr( $image_json ?: '{}' ); ?>"
    >
        <h3><?php echo esc_html( $label ); ?></h3>
        <p><?php echo esc_html( $recommendation ); ?></p>
        <input type="hidden" name="<?php echo esc_attr( $field_name ); ?>" value="<?php echo esc_attr( (string) $image_id ); ?>" data-sultana-promotion-image-id>
        <div class="sultana-admin-banner-image__preview" data-sultana-promotion-image-preview>
            <?php if ( $image && ! empty( $image['url'] ) ) : ?>
                <img src="<?php echo esc_url( (string) $image['url'] ); ?>" alt="">
            <?php endif; ?>
        </div>
        <div class="sultana-admin-banner-image__meta" data-sultana-promotion-image-meta>
            <?php if ( $image ) : ?>
                <?php
                $dimensions = absint( $image['width'] ?? 0 ) . ' x ' . absint( $image['height'] ?? 0 );
                $filesize   = ! empty( $image['filesize'] ) ? size_format( absint( $image['filesize'] ) ) : '';
                echo esc_html( trim( $dimensions . ( '' !== $filesize ? ' - ' . $filesize : '' ) ) );
                ?>
            <?php endif; ?>
        </div>
        <div class="sultana-admin-banner-image__actions">
            <button type="button" class="sultana-admin-muted-action" data-sultana-promotion-image-trigger>
                <span class="sultana-admin-icon" style="--sultana-admin-icon-url: url('<?php echo esc_url( $icon_url( 'images' ) ); ?>');" aria-hidden="true"></span>
                <?php esc_html_e( 'Subir imagen', 'sultana-admin' ); ?>
            </button>
            <button type="button" class="sultana-admin-muted-action" data-sultana-promotion-image-remove <?php disabled( ! $image_id ); ?>>
                <?php esc_html_e( 'Quitar', 'sultana-admin' ); ?>
            </button>
        </div>
        <input class="sultana-admin-visually-hidden" type="file" accept="image/jpeg,image/png,image/webp" data-sultana-promotion-image-input>
        <div class="sultana-admin-image-status" aria-live="polite" data-sultana-promotion-image-status></div>
    </section>
    <?php
};

$render_destination_options = static function ( string $type, string $current_value ) use ( $destination_choices ): void {
    $choices = [];

    if ( 'page' === $type ) {
        $choices = is_array( $destination_choices['pages'] ?? [] ) ? $destination_choices['pages'] : [];
    } elseif ( 'product_category' === $type ) {
        $choices = is_array( $destination_choices['categories'] ?? [] ) ? $destination_choices['categories'] : [];
    } elseif ( 'product' === $type ) {
        $choices = is_array( $destination_choices['products'] ?? [] ) ? $destination_choices['products'] : [];
    } elseif ( 'brand' === $type ) {
        $choices = is_array( $destination_choices['brands'] ?? [] ) ? $destination_choices['brands'] : [];
    }

    foreach ( $choices as $choice ) {
        $id   = isset( $choice->ID ) ? absint( $choice->ID ) : absint( $choice->term_id ?? 0 );
        $name = isset( $choice->post_title ) ? (string) $choice->post_title : (string) ( $choice->name ?? '' );

        if ( ! $id || '' === $name ) {
            continue;
        }
        ?>
        <option value="<?php echo esc_attr( (string) $id ); ?>" <?php selected( (string) $id, $current_value ); ?>>
            <?php echo esc_html( $name ); ?>
        </option>
        <?php
    }
};

?>
<section class="sultana-admin-banners-screen" data-sultana-banners-screen>
    <div class="sultana-admin-page-header">
        <div>
            <h1><?php esc_html_e( 'Banners Home', 'sultana-admin' ); ?></h1>
            <p><?php esc_html_e( 'Gestiona promociones responsive para el Home.', 'sultana-admin' ); ?></p>
        </div>
        <a class="sultana-admin-secondary-action" href="<?php echo esc_url( $new_url ); ?>">
            <?php esc_html_e( 'Nuevo banner', 'sultana-admin' ); ?>
        </a>
    </div>

    <?php if ( '' !== $notice ) : ?>
        <div class="sultana-admin-notice" role="status"><?php echo esc_html( $notice ); ?></div>
    <?php endif; ?>

    <?php if ( ! empty( $errors ) ) : ?>
        <div class="sultana-admin-error-list" role="alert">
            <strong><?php esc_html_e( 'Revisa el formulario', 'sultana-admin' ); ?></strong>
            <ul>
                <?php foreach ( $errors as $error ) : ?>
                    <li><?php echo esc_html( $error ); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="sultana-admin-banners-layout">
        <section class="sultana-admin-banners-list" aria-labelledby="sultana-admin-banners-list-title">
            <h2 id="sultana-admin-banners-list-title"><?php esc_html_e( 'Campanas', 'sultana-admin' ); ?></h2>

            <?php if ( empty( $promotions ) ) : ?>
                <p><?php esc_html_e( 'Todavia no hay banners.', 'sultana-admin' ); ?></p>
            <?php else : ?>
                <div class="sultana-admin-banners-table">
                    <?php foreach ( $promotions as $promotion ) : ?>
                        <article class="sultana-admin-banner-row">
                            <div>
                                <strong><?php echo esc_html( (string) ( $promotion['name'] ?? '' ) ); ?></strong>
                                <span><?php echo ! empty( $promotion['active'] ) ? esc_html__( 'Activo', 'sultana-admin' ) : esc_html__( 'Inactivo', 'sultana-admin' ); ?></span>
                            </div>
                            <div>
                                <span><?php echo esc_html( sprintf( __( 'Orden %d', 'sultana-admin' ), (int) ( $promotion['menu_order'] ?? 0 ) ) ); ?></span>
                                <a class="sultana-admin-muted-action" href="<?php echo esc_url( (string) ( $promotion['edit_url'] ?? '' ) ); ?>">
                                    <?php esc_html_e( 'Editar', 'sultana-admin' ); ?>
                                </a>
                                <form method="post" action="<?php echo esc_url( $form_action ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Enviar este banner a la papelera?', 'sultana-admin' ) ); ?>');">
                                    <input type="hidden" name="sultana_admin_action" value="delete_promotion">
                                    <input type="hidden" name="promotion_id" value="<?php echo esc_attr( (string) absint( $promotion['id'] ?? 0 ) ); ?>">
                                    <?php wp_nonce_field( $screen_data['delete_nonce_action'] ?? PromotionController::DELETE_NONCE_ACTION, 'sultana_admin_delete_promotion_nonce' ); ?>
                                    <button type="submit" class="sultana-admin-muted-action"><?php esc_html_e( 'Eliminar', 'sultana-admin' ); ?></button>
                                </form>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <form id="sultana-admin-banner-form" class="sultana-admin-banner-form" method="post" action="<?php echo esc_url( $form_action ); ?>">
            <?php wp_nonce_field( $screen_data['form_nonce_action'] ?? PromotionController::SAVE_NONCE_ACTION, 'sultana_admin_promotion_nonce' ); ?>
            <input type="hidden" name="sultana_admin_action" value="save_promotion">
            <input type="hidden" name="promotion_id" value="<?php echo esc_attr( (string) $promotion_id ); ?>">

            <section class="sultana-admin-form-section">
                <h2><?php echo esc_html( $form_title ); ?></h2>
                <label for="sultana-admin-banner-name"><?php esc_html_e( 'Nombre interno', 'sultana-admin' ); ?></label>
                <input id="sultana-admin-banner-name" type="text" name="name" value="<?php echo esc_attr( (string) ( $form['name'] ?? '' ) ); ?>" required data-sultana-promotion-title>

                <div class="sultana-admin-form-grid">
                    <div>
                        <label for="sultana-admin-banner-order"><?php esc_html_e( 'Orden', 'sultana-admin' ); ?></label>
                        <input id="sultana-admin-banner-order" type="number" name="menu_order" value="<?php echo esc_attr( (string) (int) ( $form['menu_order'] ?? 0 ) ); ?>" step="1">
                    </div>
                    <label class="sultana-admin-banner-active">
                        <input type="checkbox" name="active" value="yes" <?php checked( ! empty( $form['active'] ) ); ?>>
                        <?php esc_html_e( 'Activa', 'sultana-admin' ); ?>
                    </label>
                </div>
            </section>

            <?php
            $render_image_field( 'desktop', __( 'Banner escritorio', 'sultana-admin' ), __( 'Recomendado: 1600 x 600 px. Se optimiza a max width 1600, sin crop.', 'sultana-admin' ) );
            $render_image_field( 'mobile', __( 'Banner movil', 'sultana-admin' ), __( 'Recomendado: 750 x 375 px. Se optimiza a max width 1200 para DPR, sin crop.', 'sultana-admin' ) );
            ?>

            <section class="sultana-admin-form-section">
                <label for="sultana-admin-banner-alt"><?php esc_html_e( 'Texto alternativo', 'sultana-admin' ); ?></label>
                <input id="sultana-admin-banner-alt" type="text" name="alt_text" value="<?php echo esc_attr( (string) ( $form['alt_text'] ?? '' ) ); ?>">

                <label for="sultana-admin-banner-destination-type"><?php esc_html_e( 'Destino', 'sultana-admin' ); ?></label>
                <select id="sultana-admin-banner-destination-type" name="destination_type" data-sultana-promotion-destination-type>
                    <?php foreach ( $destination_options as $type => $label ) : ?>
                        <option value="<?php echo esc_attr( (string) $type ); ?>" <?php selected( (string) ( $form['destination_type'] ?? 'none' ), (string) $type ); ?>>
                            <?php echo esc_html( (string) $label ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <?php foreach ( [ 'page', 'product_category', 'product', 'brand' ] as $destination_type ) : ?>
                    <div data-sultana-promotion-destination-field="<?php echo esc_attr( $destination_type ); ?>">
                        <label><?php echo esc_html( ucfirst( str_replace( '_', ' ', $destination_type ) ) ); ?></label>
                        <select name="destination_value" data-sultana-promotion-destination-value="<?php echo esc_attr( $destination_type ); ?>">
                            <option value="0"><?php esc_html_e( 'Seleccionar', 'sultana-admin' ); ?></option>
                            <?php $render_destination_options( $destination_type, (string) ( $form['destination_value'] ?? '' ) ); ?>
                        </select>
                    </div>
                <?php endforeach; ?>

                <div data-sultana-promotion-destination-field="custom_url">
                    <label for="sultana-admin-banner-custom-url"><?php esc_html_e( 'URL personalizada', 'sultana-admin' ); ?></label>
                    <input id="sultana-admin-banner-custom-url" type="url" name="custom_url" value="<?php echo esc_attr( (string) ( $form['custom_url'] ?? '' ) ); ?>">
                </div>
            </section>

            <div class="sultana-admin-form-actions">
                <button type="submit">
                    <span class="sultana-admin-icon" style="--sultana-admin-icon-url: url('<?php echo esc_url( $icon_url( 'save' ) ); ?>');" aria-hidden="true"></span>
                    <?php esc_html_e( 'Guardar banner', 'sultana-admin' ); ?>
                </button>
            </div>
        </form>
    </div>
</section>

<?php

use Sultana\Admin\Promotions\PromotionController;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! empty( $screen_data['not_found'] ) || ! empty( $screen_data['forbidden'] ) ) :
    ?>
    <section class="sultana-admin-page--message" aria-live="polite">
        <h1><?php echo esc_html( $screen_data['message'] ?? __( 'Banner no disponible.', 'sultana-admin' ) ); ?></h1>
        <a class="sultana-admin-secondary-action" href="<?php echo esc_url( \Sultana\Admin\Core\Router::banners_url() ); ?>"><?php esc_html_e( 'Volver a banners', 'sultana-admin' ); ?></a>
    </section>
    <?php
    return;
endif;

$mode                = (string) ( $screen_data['mode'] ?? 'list' );
$promotions          = $screen_data['promotions'] ?? [];
$form                = $screen_data['form'] ?? [];
$selected_images     = $screen_data['selected_images'] ?? [];
$destination_options = $screen_data['destination_options'] ?? [];
$destination_choices = $screen_data['destination_choices'] ?? [];
$errors              = $screen_data['errors'] ?? [];
$notice              = $screen_data['notice'] ?? '';
$form_action         = $screen_data['form_action'] ?? \Sultana\Admin\Core\Router::banners_url();
$promotion_id        = absint( $form['id'] ?? ( $form['promotion_id'] ?? 0 ) );
$is_edit             = $promotion_id > 0;
$form_title          = $is_edit ? __( 'Editar banner', 'sultana-admin' ) : __( 'Nuevo banner', 'sultana-admin' );
$submit_label        = $is_edit ? __( 'Guardar cambios', 'sultana-admin' ) : __( 'Crear banner', 'sultana-admin' );
$new_url             = \Sultana\Admin\Core\Router::new_banner_url();
$list_url            = \Sultana\Admin\Core\Router::banners_url();
$icon_url            = static fn( string $name ): string => \Sultana\Admin\Core\Icons::url( $name );
$current_destination = (string) ( $form['destination_type'] ?? 'none' );
$destination_labels  = [
    'none'             => __( 'Sin enlace', 'sultana-admin' ),
    'page'             => __( 'Página', 'sultana-admin' ),
    'product'          => __( 'Producto', 'sultana-admin' ),
    'product_category' => __( 'Categoría de productos', 'sultana-admin' ),
    'brand'            => __( 'Marca', 'sultana-admin' ),
    'sale'             => __( 'Ofertas', 'sultana-admin' ),
    'custom_url'       => __( 'URL personalizada', 'sultana-admin' ),
];

$format_image_meta = static function ( ?array $image ): string {
    if ( ! $image ) {
        return '';
    }

    $dimensions = ! empty( $image['width'] ) && ! empty( $image['height'] )
        ? absint( $image['width'] ) . ' × ' . absint( $image['height'] )
        : '';
    $mime       = (string) ( $image['mime'] ?? '' );
    $format     = str_replace( 'image/', '', $mime );
    $filesize   = ! empty( $image['filesize'] ) ? size_format( absint( $image['filesize'] ) ) : '';

    return implode( ' · ', array_filter( [ $dimensions, $format ? strtoupper( $format ) : '', $filesize ] ) );
};

$render_image_field = static function ( string $slot, string $label, string $recommendation, string $ratio ) use ( $form, $selected_images, $icon_url, $format_image_meta ): void {
    $field_name = $slot . '_image_id';
    $image      = is_array( $selected_images[ $slot ] ?? null ) ? $selected_images[ $slot ] : null;
    $image_id   = absint( $form[ $field_name ] ?? 0 );
    $image_json = $image ? wp_json_encode( $image ) : '{}';
    ?>
    <section
        class="<?php echo esc_attr( 'sultana-admin-banner-uploader sultana-admin-banner-uploader--' . $slot . ( $image_id ? ' has-image' : '' ) ); ?>"
        data-sultana-promotion-image="<?php echo esc_attr( $slot ); ?>"
        data-initial-image="<?php echo esc_attr( $image_json ?: '{}' ); ?>"
    >
        <div class="sultana-admin-banner-uploader__header">
            <div>
                <h3><?php echo esc_html( $label ); ?></h3>
                <p><?php echo esc_html( $recommendation ); ?></p>
            </div>
            <span><?php echo esc_html( $ratio ); ?></span>
        </div>
        <input type="hidden" name="<?php echo esc_attr( $field_name ); ?>" value="<?php echo esc_attr( (string) $image_id ); ?>" data-sultana-promotion-image-id>
        <button class="sultana-admin-banner-uploader__dropzone" type="button" aria-label="<?php echo esc_attr( $image_id ? __( 'Cambiar imagen', 'sultana-admin' ) : __( 'Subir imagen', 'sultana-admin' ) ); ?>" data-sultana-promotion-image-trigger>
            <span class="sultana-admin-banner-uploader__empty">
                <span class="sultana-admin-icon" style="--sultana-admin-icon-url: url('<?php echo esc_url( $icon_url( 'images' ) ); ?>');" aria-hidden="true"></span>
                <span data-sultana-promotion-image-empty-label><?php echo $image_id ? esc_html__( 'Cambiar imagen', 'sultana-admin' ) : esc_html__( 'Subir imagen', 'sultana-admin' ); ?></span>
            </span>
            <span class="sultana-admin-banner-uploader__preview" data-sultana-promotion-image-preview>
                <?php if ( $image && ! empty( $image['url'] ) ) : ?>
                    <img src="<?php echo esc_url( (string) $image['url'] ); ?>" alt="">
                <?php endif; ?>
            </span>
        </button>
        <div class="sultana-admin-banner-uploader__footer">
            <span class="sultana-admin-banner-uploader__meta" data-sultana-promotion-image-meta><?php echo esc_html( $format_image_meta( $image ) ); ?></span>
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
    <?php if ( 'form' !== $mode ) : ?>
        <div class="sultana-admin-section-header sultana-admin-banners-header">
            <div>
                <h1><?php esc_html_e( 'Banners', 'sultana-admin' ); ?></h1>
            </div>
            <a class="sultana-admin-secondary-action" href="<?php echo esc_url( $new_url ); ?>">
                <span class="sultana-admin-icon" style="--sultana-admin-icon-url: url('<?php echo esc_url( $icon_url( 'images' ) ); ?>');" aria-hidden="true"></span>
                <?php esc_html_e( 'Nuevo', 'sultana-admin' ); ?>
            </a>
        </div>

        <?php if ( '' !== $notice ) : ?>
            <div class="sultana-admin-notice" role="status"><?php echo esc_html( $notice ); ?></div>
        <?php endif; ?>

        <?php if ( ! empty( $errors ) ) : ?>
            <div class="sultana-admin-error-list" role="alert">
                <strong><?php esc_html_e( 'No se pudo eliminar el banner', 'sultana-admin' ); ?></strong>
                <ul>
                    <?php foreach ( $errors as $error ) : ?>
                        <li><?php echo esc_html( $error ); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ( empty( $promotions ) ) : ?>
            <div class="sultana-admin-empty sultana-admin-banners-empty">
                <span class="sultana-admin-icon" style="--sultana-admin-icon-url: url('<?php echo esc_url( $icon_url( 'images' ) ); ?>');" aria-hidden="true"></span>
                <h2><?php esc_html_e( 'Aún no hay banners', 'sultana-admin' ); ?></h2>
                <p><?php esc_html_e( 'Crea tu primer banner para mostrar promociones en la página de inicio.', 'sultana-admin' ); ?></p>
                <a class="sultana-admin-secondary-action" href="<?php echo esc_url( $new_url ); ?>"><?php esc_html_e( 'Nuevo', 'sultana-admin' ); ?></a>
            </div>
        <?php else : ?>
            <div class="sultana-admin-banner-table-wrap">
                <table class="sultana-admin-banner-table">
                    <thead>
                        <tr>
                            <th scope="col"><?php esc_html_e( 'Preview', 'sultana-admin' ); ?></th>
                            <th scope="col"><?php esc_html_e( 'Campaña', 'sultana-admin' ); ?></th>
                            <th scope="col"><?php esc_html_e( 'Estado', 'sultana-admin' ); ?></th>
                            <th scope="col"><?php esc_html_e( 'Orden', 'sultana-admin' ); ?></th>
                            <th scope="col"><?php esc_html_e( 'Destino', 'sultana-admin' ); ?></th>
                            <th scope="col"><?php esc_html_e( 'Acción', 'sultana-admin' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $promotions as $promotion ) : ?>
                            <?php
                            $desktop_image = is_array( $promotion['desktop_image'] ?? null ) ? $promotion['desktop_image'] : null;
                            $mobile_image  = is_array( $promotion['mobile_image'] ?? null ) ? $promotion['mobile_image'] : null;
                            $preview_image = $desktop_image ?: $mobile_image;
                            $status_class  = ! empty( $promotion['active'] ) ? 'sultana-admin-badge--success' : '';
                            $status_label  = ! empty( $promotion['active'] ) ? __( 'Activo', 'sultana-admin' ) : __( 'Inactivo', 'sultana-admin' );
                            $destination   = (string) ( $promotion['destination_type'] ?? 'none' );
                            ?>
                            <tr>
                                <td>
                                    <span class="sultana-admin-banner-thumb">
                                        <?php if ( $preview_image && ! empty( $preview_image['url'] ) ) : ?>
                                            <img src="<?php echo esc_url( (string) $preview_image['url'] ); ?>" alt="" loading="lazy">
                                        <?php else : ?>
                                            <span class="sultana-admin-icon" style="--sultana-admin-icon-url: url('<?php echo esc_url( $icon_url( 'images' ) ); ?>');" aria-hidden="true"></span>
                                        <?php endif; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="sultana-admin-banner-name"><?php echo esc_html( (string) ( $promotion['name'] ?? '' ) ); ?></span>
                                    <small><?php echo esc_html( $format_image_meta( $preview_image ) ?: __( 'Sin imagen', 'sultana-admin' ) ); ?></small>
                                </td>
                                <td><span class="<?php echo esc_attr( trim( 'sultana-admin-badge ' . $status_class ) ); ?>"><?php echo esc_html( $status_label ); ?></span></td>
                                <td><?php echo esc_html( (string) (int) ( $promotion['menu_order'] ?? 0 ) ); ?></td>
                                <td><?php echo esc_html( (string) ( $destination_labels[ $destination ] ?? $destination ) ); ?></td>
                                <td>
                                    <div class="sultana-admin-row-actions">
                                        <form class="sultana-admin-icon-action-form" method="post" action="<?php echo esc_url( $form_action ); ?>" onsubmit="return confirm('<?php echo esc_js( __( '¿Enviar este banner a la papelera?', 'sultana-admin' ) ); ?>');">
                                            <input type="hidden" name="sultana_admin_action" value="delete_promotion">
                                            <input type="hidden" name="promotion_id" value="<?php echo esc_attr( (string) absint( $promotion['id'] ?? 0 ) ); ?>">
                                            <?php wp_nonce_field( $screen_data['delete_nonce_action'] ?? PromotionController::DELETE_NONCE_ACTION, 'sultana_admin_delete_promotion_nonce' ); ?>
                                            <button class="sultana-admin-icon-button sultana-admin-icon-button--danger" type="submit" aria-label="<?php esc_attr_e( 'Eliminar banner', 'sultana-admin' ); ?>" title="<?php esc_attr_e( 'Eliminar banner', 'sultana-admin' ); ?>">
                                                <span class="sultana-admin-icon" style="--sultana-admin-icon-url: url('<?php echo esc_url( $icon_url( 'trash' ) ); ?>');" aria-hidden="true"></span>
                                            </button>
                                        </form>
                                        <a class="sultana-admin-icon-button sultana-admin-icon-button--success" href="<?php echo esc_url( (string) ( $promotion['edit_url'] ?? '' ) ); ?>" aria-label="<?php esc_attr_e( 'Editar banner', 'sultana-admin' ); ?>" title="<?php esc_attr_e( 'Editar banner', 'sultana-admin' ); ?>">
                                            <span class="sultana-admin-icon" style="--sultana-admin-icon-url: url('<?php echo esc_url( $icon_url( 'pencil' ) ); ?>');" aria-hidden="true"></span>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="sultana-admin-banner-cards">
                <?php foreach ( $promotions as $promotion ) : ?>
                    <?php
                    $desktop_image = is_array( $promotion['desktop_image'] ?? null ) ? $promotion['desktop_image'] : null;
                    $mobile_image  = is_array( $promotion['mobile_image'] ?? null ) ? $promotion['mobile_image'] : null;
                    $preview_image = $desktop_image ?: $mobile_image;
                    $status_class  = ! empty( $promotion['active'] ) ? 'sultana-admin-badge--success' : '';
                    $status_label  = ! empty( $promotion['active'] ) ? __( 'Activo', 'sultana-admin' ) : __( 'Inactivo', 'sultana-admin' );
                    ?>
                    <article class="sultana-admin-banner-card">
                        <span class="sultana-admin-banner-thumb">
                            <?php if ( $preview_image && ! empty( $preview_image['url'] ) ) : ?>
                                <img src="<?php echo esc_url( (string) $preview_image['url'] ); ?>" alt="" loading="lazy">
                            <?php else : ?>
                                <span class="sultana-admin-icon" style="--sultana-admin-icon-url: url('<?php echo esc_url( $icon_url( 'images' ) ); ?>');" aria-hidden="true"></span>
                            <?php endif; ?>
                        </span>
                        <div>
                            <span class="sultana-admin-banner-name"><?php echo esc_html( (string) ( $promotion['name'] ?? '' ) ); ?></span>
                            <span class="sultana-admin-banner-card__meta">
                                <small><?php echo esc_html( sprintf( __( 'Orden %d', 'sultana-admin' ), (int) ( $promotion['menu_order'] ?? 0 ) ) ); ?></small>
                                <span class="<?php echo esc_attr( trim( 'sultana-admin-badge ' . $status_class ) ); ?>"><?php echo esc_html( $status_label ); ?></span>
                            </span>
                        </div>
                        <div class="sultana-admin-card-actions">
                            <form class="sultana-admin-icon-action-form" method="post" action="<?php echo esc_url( $form_action ); ?>" onsubmit="return confirm('<?php echo esc_js( __( '¿Enviar este banner a la papelera?', 'sultana-admin' ) ); ?>');">
                                <input type="hidden" name="sultana_admin_action" value="delete_promotion">
                                <input type="hidden" name="promotion_id" value="<?php echo esc_attr( (string) absint( $promotion['id'] ?? 0 ) ); ?>">
                                <?php wp_nonce_field( $screen_data['delete_nonce_action'] ?? PromotionController::DELETE_NONCE_ACTION, 'sultana_admin_delete_promotion_nonce' ); ?>
                                <button class="sultana-admin-icon-button sultana-admin-icon-button--danger" type="submit" aria-label="<?php esc_attr_e( 'Eliminar banner', 'sultana-admin' ); ?>" title="<?php esc_attr_e( 'Eliminar banner', 'sultana-admin' ); ?>">
                                    <span class="sultana-admin-icon" style="--sultana-admin-icon-url: url('<?php echo esc_url( $icon_url( 'trash' ) ); ?>');" aria-hidden="true"></span>
                                </button>
                            </form>
                            <a class="sultana-admin-icon-button sultana-admin-icon-button--success" href="<?php echo esc_url( (string) ( $promotion['edit_url'] ?? '' ) ); ?>" aria-label="<?php esc_attr_e( 'Editar banner', 'sultana-admin' ); ?>" title="<?php esc_attr_e( 'Editar banner', 'sultana-admin' ); ?>">
                                <span class="sultana-admin-icon" style="--sultana-admin-icon-url: url('<?php echo esc_url( $icon_url( 'pencil' ) ); ?>');" aria-hidden="true"></span>
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php else : ?>
        <section class="sultana-admin-banner-form-screen" aria-label="<?php esc_attr_e( 'Formulario de banner', 'sultana-admin' ); ?>">
            <div class="sultana-admin-section-header sultana-admin-editor-header">
                <div>
                    <a class="sultana-admin-back-link" href="<?php echo esc_url( $list_url ); ?>">
                        <span class="sultana-admin-icon" style="--sultana-admin-icon-url: url('<?php echo esc_url( $icon_url( 'chevron-left' ) ); ?>');" aria-hidden="true"></span>
                        <?php esc_html_e( 'Banners', 'sultana-admin' ); ?>
                    </a>
                    <h1><?php echo esc_html( $form_title ); ?></h1>
                </div>
            </div>

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

            <form id="sultana-admin-banner-form" class="sultana-admin-banner-form" method="post" action="<?php echo esc_url( $form_action ); ?>">
                <?php wp_nonce_field( $screen_data['form_nonce_action'] ?? PromotionController::SAVE_NONCE_ACTION, 'sultana_admin_promotion_nonce' ); ?>
                <input type="hidden" name="sultana_admin_action" value="save_promotion">
                <input type="hidden" name="promotion_id" value="<?php echo esc_attr( (string) $promotion_id ); ?>">

                <div class="sultana-admin-banner-editor-layout">
                    <div class="sultana-admin-banner-editor-main">
                        <section class="sultana-admin-banner-card-section" aria-labelledby="sultana-admin-banner-info-title">
                            <h2 id="sultana-admin-banner-info-title"><?php esc_html_e( 'Información', 'sultana-admin' ); ?></h2>
                            <div class="sultana-admin-banner-field">
                                <label for="sultana-admin-banner-name"><?php esc_html_e( 'Nombre interno', 'sultana-admin' ); ?></label>
                                <input id="sultana-admin-banner-name" type="text" name="name" value="<?php echo esc_attr( (string) ( $form['name'] ?? '' ) ); ?>" required data-sultana-promotion-title>
                                <p><?php esc_html_e( 'Solo visible en administración.', 'sultana-admin' ); ?></p>
                            </div>
                            <div class="sultana-admin-banner-compact-row">
                                <div class="sultana-admin-banner-field sultana-admin-banner-field--order">
                                    <label for="sultana-admin-banner-order"><?php esc_html_e( 'Orden', 'sultana-admin' ); ?></label>
                                    <input id="sultana-admin-banner-order" type="number" name="menu_order" value="<?php echo esc_attr( (string) (int) ( $form['menu_order'] ?? 0 ) ); ?>" step="1">
                                    <p><?php esc_html_e( 'Los números menores aparecen primero.', 'sultana-admin' ); ?></p>
                                </div>
                                <label class="sultana-admin-banner-toggle">
                                    <input type="checkbox" name="active" value="yes" <?php checked( ! empty( $form['active'] ) ); ?>>
                                    <span>
                                        <strong><?php esc_html_e( 'Activo', 'sultana-admin' ); ?></strong>
                                        <small><?php esc_html_e( 'Visible en la página de inicio.', 'sultana-admin' ); ?></small>
                                    </span>
                                </label>
                            </div>
                        </section>

                        <section class="sultana-admin-banner-card-section" aria-labelledby="sultana-admin-banner-images-title">
                            <h2 id="sultana-admin-banner-images-title"><?php esc_html_e( 'Imágenes del banner', 'sultana-admin' ); ?></h2>
                            <div class="sultana-admin-banner-upload-grid">
                                <?php
                                $render_image_field( 'desktop', __( 'Banner para escritorio', 'sultana-admin' ), __( 'Recomendado: 1600 × 600 px', 'sultana-admin' ), __( '8:3', 'sultana-admin' ) );
                                $render_image_field( 'mobile', __( 'Banner para móvil', 'sultana-admin' ), __( 'Recomendado: 750 × 375 px', 'sultana-admin' ), __( '2:1', 'sultana-admin' ) );
                                ?>
                            </div>
                            <div class="sultana-admin-banner-field">
                                <label for="sultana-admin-banner-alt"><?php esc_html_e( 'Texto alternativo', 'sultana-admin' ); ?></label>
                                <input id="sultana-admin-banner-alt" type="text" name="alt_text" value="<?php echo esc_attr( (string) ( $form['alt_text'] ?? '' ) ); ?>">
                                <p><?php esc_html_e( 'Describe brevemente el banner para lectores de pantalla.', 'sultana-admin' ); ?></p>
                            </div>
                        </section>
                    </div>

                    <aside class="sultana-admin-banner-editor-sidebar">
                        <section class="sultana-admin-banner-card-section" aria-labelledby="sultana-admin-banner-destination-title">
                            <h2 id="sultana-admin-banner-destination-title"><?php esc_html_e( 'Destino', 'sultana-admin' ); ?></h2>
                            <div class="sultana-admin-banner-field">
                                <label for="sultana-admin-banner-destination-type"><?php esc_html_e( 'Tipo de destino', 'sultana-admin' ); ?></label>
                                <select id="sultana-admin-banner-destination-type" name="destination_type" data-sultana-promotion-destination-type>
                                    <?php foreach ( $destination_options as $type => $label ) : ?>
                                        <?php $display_label = $destination_labels[ (string) $type ] ?? (string) $label; ?>
                                        <option value="<?php echo esc_attr( (string) $type ); ?>" <?php selected( $current_destination, (string) $type ); ?>>
                                            <?php echo esc_html( $display_label ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <?php foreach ( [ 'page', 'product_category', 'product', 'brand' ] as $destination_type ) : ?>
                                <?php $is_current_destination = $current_destination === $destination_type; ?>
                                <div class="sultana-admin-banner-field" data-sultana-promotion-destination-field="<?php echo esc_attr( $destination_type ); ?>" <?php hidden( ! $is_current_destination ); ?>>
                                    <label><?php echo esc_html( (string) ( $destination_labels[ $destination_type ] ?? $destination_type ) ); ?></label>
                                    <select name="destination_value" data-sultana-promotion-destination-value="<?php echo esc_attr( $destination_type ); ?>" <?php disabled( ! $is_current_destination ); ?>>
                                        <option value="0"><?php esc_html_e( 'Seleccionar', 'sultana-admin' ); ?></option>
                                        <?php $render_destination_options( $destination_type, (string) ( $form['destination_value'] ?? '' ) ); ?>
                                    </select>
                                </div>
                            <?php endforeach; ?>

                            <?php $is_custom_url_destination = 'custom_url' === $current_destination; ?>
                            <div class="sultana-admin-banner-field" data-sultana-promotion-destination-field="custom_url" <?php hidden( ! $is_custom_url_destination ); ?>>
                                <label for="sultana-admin-banner-custom-url"><?php esc_html_e( 'URL personalizada', 'sultana-admin' ); ?></label>
                                <input id="sultana-admin-banner-custom-url" type="url" name="custom_url" value="<?php echo esc_attr( (string) ( $form['custom_url'] ?? '' ) ); ?>" <?php disabled( ! $is_custom_url_destination ); ?>>
                            </div>
                        </section>
                    </aside>
                </div>

                <div class="sultana-admin-banner-actions">
                    <a class="sultana-admin-muted-action" href="<?php echo esc_url( $list_url ); ?>"><?php esc_html_e( 'Cancelar', 'sultana-admin' ); ?></a>
                    <button type="submit">
                        <span class="sultana-admin-icon" style="--sultana-admin-icon-url: url('<?php echo esc_url( $icon_url( 'save' ) ); ?>');" aria-hidden="true"></span>
                        <?php echo esc_html( $submit_label ); ?>
                    </button>
                </div>
            </form>
        </section>
    <?php endif; ?>
</section>

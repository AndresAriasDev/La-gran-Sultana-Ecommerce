<?php

use Sultana\Admin\Products\ProductController;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$message = $screen_data['message'] ?? '';

if ( ! empty( $screen_data['not_found'] ) || ! empty( $screen_data['unsupported'] ) || ! empty( $screen_data['forbidden'] ) ) :
    ?>
    <section class="sultana-admin-product-form-screen">
        <div class="sultana-admin-empty">
            <h1><?php echo esc_html( $message ); ?></h1>
            <p><?php esc_html_e( 'Vuelve al listado de productos para continuar.', 'sultana-admin' ); ?></p>
            <a class="sultana-admin-secondary-action" href="<?php echo esc_url( \Sultana\Admin\Core\Router::products_url() ); ?>">
                <?php esc_html_e( 'Volver a productos', 'sultana-admin' ); ?>
            </a>
        </div>
    </section>
    <?php
    return;
endif;

$form       = $screen_data['form'] ?? [];
$errors     = $screen_data['errors'] ?? [];
$categories = $screen_data['categories'] ?? [];
$brands     = $screen_data['brands'] ?? [];
$brand_taxonomy = $screen_data['brand_taxonomy'] ?? '';
$selected_images = $screen_data['selected_images'] ?? [];
$form_action = $screen_data['form_action'] ?? \Sultana\Admin\Core\Router::new_product_url();
$form_nonce_action = $screen_data['form_nonce_action'] ?? ProductController::CREATE_NONCE_ACTION;
$form_title = $screen_data['form_title'] ?? __( 'Nuevo producto', 'sultana-admin' );
$form_kicker = $screen_data['form_kicker'] ?? __( 'Producto simple', 'sultana-admin' );
$submit_label = $screen_data['submit_label'] ?? __( 'Guardar producto', 'sultana-admin' );
$notice = $screen_data['notice'] ?? '';
$selected_categories = array_map( 'absint', $form['category_ids'] ?? [] );
$selected_brand = absint( $form['brand_id'] ?? 0 );
$product_image_ids = is_array( $form['product_image_ids'] ?? '' )
    ? implode( ',', array_map( 'absint', $form['product_image_ids'] ) )
    : (string) ( $form['product_image_ids'] ?? '' );

?>
<section class="sultana-admin-product-form-screen" aria-labelledby="sultana-admin-product-new-title">
    <div class="sultana-admin-page-header">
        <div>
            <p class="sultana-admin-kicker"><?php echo esc_html( $form_kicker ); ?></p>
            <h1 id="sultana-admin-product-new-title"><?php echo esc_html( $form_title ); ?></h1>
        </div>
        <a class="sultana-admin-muted-action" href="<?php echo esc_url( \Sultana\Admin\Core\Router::products_url() ); ?>">
            <?php esc_html_e( 'Cancelar', 'sultana-admin' ); ?>
        </a>
    </div>

    <?php if ( '' !== $notice ) : ?>
        <div class="sultana-admin-notice" role="status">
            <?php echo esc_html( $notice ); ?>
        </div>
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

    <form class="sultana-admin-product-form" method="post" action="<?php echo esc_url( $form_action ); ?>" enctype="multipart/form-data">
        <?php wp_nonce_field( $form_nonce_action, 'sultana_admin_product_nonce' ); ?>

        <section class="sultana-admin-form-section" aria-labelledby="sultana-admin-product-main">
            <h2 id="sultana-admin-product-main"><?php esc_html_e( 'Información principal', 'sultana-admin' ); ?></h2>
            <label for="sultana-admin-product-name"><?php esc_html_e( 'Nombre del producto', 'sultana-admin' ); ?></label>
            <input id="sultana-admin-product-name" type="text" name="name" value="<?php echo esc_attr( $form['name'] ?? '' ); ?>" required>

            <label for="sultana-admin-product-short-description"><?php esc_html_e( 'Descripción corta', 'sultana-admin' ); ?></label>
            <textarea id="sultana-admin-product-short-description" name="short_description" rows="4"><?php echo esc_textarea( $form['short_description'] ?? '' ); ?></textarea>

        </section>

        <section class="sultana-admin-form-section" aria-labelledby="sultana-admin-product-pricing">
            <h2 id="sultana-admin-product-pricing"><?php esc_html_e( 'Precio', 'sultana-admin' ); ?></h2>
            <div class="sultana-admin-form-grid">
                <div>
                    <label for="sultana-admin-product-regular-price"><?php esc_html_e( 'Precio regular', 'sultana-admin' ); ?></label>
                    <input id="sultana-admin-product-regular-price" type="number" name="regular_price" value="<?php echo esc_attr( $form['regular_price'] ?? '' ); ?>" min="0" step="0.01" inputmode="decimal" required>
                </div>
                <div>
                    <label for="sultana-admin-product-sale-price"><?php esc_html_e( 'Precio de oferta', 'sultana-admin' ); ?></label>
                    <input id="sultana-admin-product-sale-price" type="number" name="sale_price" value="<?php echo esc_attr( $form['sale_price'] ?? '' ); ?>" min="0" step="0.01" inputmode="decimal">
                </div>
            </div>
        </section>

        <section class="sultana-admin-form-section" aria-labelledby="sultana-admin-product-inventory">
            <h2 id="sultana-admin-product-inventory"><?php esc_html_e( 'Inventario', 'sultana-admin' ); ?></h2>
            <label for="sultana-admin-product-sku"><?php esc_html_e( 'SKU', 'sultana-admin' ); ?></label>
            <input id="sultana-admin-product-sku" type="text" name="sku" value="<?php echo esc_attr( $form['sku'] ?? '' ); ?>">

            <label for="sultana-admin-product-stock"><?php esc_html_e( 'Cantidad en stock', 'sultana-admin' ); ?></label>
            <input id="sultana-admin-product-stock" type="number" name="stock_quantity" value="<?php echo esc_attr( $form['stock_quantity'] ?? '' ); ?>" min="0" step="1" inputmode="numeric" required>
        </section>

        <section class="sultana-admin-form-section" aria-labelledby="sultana-admin-product-organization">
            <h2 id="sultana-admin-product-organization"><?php esc_html_e( 'Organización', 'sultana-admin' ); ?></h2>
            <?php if ( empty( $categories ) ) : ?>
                <p><?php esc_html_e( 'No hay categorías de producto disponibles.', 'sultana-admin' ); ?></p>
            <?php else : ?>
                <fieldset class="sultana-admin-category-list">
                    <legend><?php esc_html_e( 'Categorías', 'sultana-admin' ); ?></legend>
                    <?php foreach ( $categories as $category ) : ?>
                        <label>
                            <input
                                type="checkbox"
                                name="category_ids[]"
                                value="<?php echo esc_attr( (string) $category['id'] ); ?>"
                                <?php checked( in_array( $category['id'], $selected_categories, true ) ); ?>
                            >
                            <span><?php echo esc_html( $category['name'] ); ?></span>
                        </label>
                    <?php endforeach; ?>
                </fieldset>
            <?php endif; ?>

            <label for="sultana-admin-product-brand"><?php esc_html_e( 'Marca', 'sultana-admin' ); ?></label>
            <?php if ( '' === $brand_taxonomy || empty( $brands ) ) : ?>
                <select id="sultana-admin-product-brand" name="brand_id" disabled aria-disabled="true">
                    <option value="0"><?php esc_html_e( 'Sin marca', 'sultana-admin' ); ?></option>
                </select>
            <?php else : ?>
                <select id="sultana-admin-product-brand" name="brand_id">
                    <option value="0"><?php esc_html_e( 'Sin marca', 'sultana-admin' ); ?></option>
                    <?php foreach ( $brands as $brand ) : ?>
                        <option value="<?php echo esc_attr( (string) $brand['id'] ); ?>" <?php selected( $selected_brand, $brand['id'] ); ?>>
                            <?php echo esc_html( $brand['name'] ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
        </section>

        <section class="sultana-admin-form-section" aria-labelledby="sultana-admin-product-images-title">
            <h2 id="sultana-admin-product-images-title"><?php esc_html_e( 'Imagenes del producto', 'sultana-admin' ); ?></h2>
            <p class="sultana-admin-field-help"><?php esc_html_e( 'La primera imagen sera la portada del producto.', 'sultana-admin' ); ?></p>

            <div
                class="sultana-admin-product-images"
                data-sultana-product-images
                data-initial-images="<?php echo esc_attr( wp_json_encode( array_values( $selected_images ) ) ); ?>"
            >
                <input type="hidden" name="product_image_ids" value="<?php echo esc_attr( $product_image_ids ); ?>" data-sultana-product-image-ids>

                <button type="button" class="sultana-admin-image-upload-button" data-sultana-product-image-trigger>
                    <?php esc_html_e( 'Agregar imagenes', 'sultana-admin' ); ?>
                </button>
                <input
                    id="sultana-admin-product-images-input"
                    class="sultana-admin-image-upload-input"
                    type="file"
                    accept="image/jpeg,image/png,image/gif,image/webp"
                    multiple
                    data-sultana-product-image-input
                >

                <div class="sultana-admin-image-status" aria-live="polite" data-sultana-product-image-status></div>
                <div class="sultana-admin-image-grid" data-sultana-product-image-grid></div>
            </div>
        </section>

        <section class="sultana-admin-form-section" aria-labelledby="sultana-admin-product-publication">
            <h2 id="sultana-admin-product-publication"><?php esc_html_e( 'Publicación', 'sultana-admin' ); ?></h2>
            <label for="sultana-admin-product-status"><?php esc_html_e( 'Estado del producto', 'sultana-admin' ); ?></label>
            <select id="sultana-admin-product-status" name="status">
                <option value="draft" <?php selected( $form['status'] ?? 'draft', 'draft' ); ?>><?php esc_html_e( 'Borrador', 'sultana-admin' ); ?></option>
                <option value="publish" <?php selected( $form['status'] ?? 'draft', 'publish' ); ?>><?php esc_html_e( 'Publicado', 'sultana-admin' ); ?></option>
            </select>
        </section>

        <div class="sultana-admin-form-actions">
            <a class="sultana-admin-muted-action" href="<?php echo esc_url( \Sultana\Admin\Core\Router::products_url() ); ?>">
                <?php esc_html_e( 'Cancelar', 'sultana-admin' ); ?>
            </a>
            <button type="submit"><?php echo esc_html( $submit_label ); ?></button>
        </div>
    </form>
</section>

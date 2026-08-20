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
$product_type = $screen_data['product_type'] ?? ( $form['product_type'] ?? 'simple' );
$available_attributes = $screen_data['available_attributes'] ?? [];
$combo_components = $screen_data['combo_components'] ?? [];
$max_generated_variations = absint( $screen_data['max_generated_variations'] ?? 100 );
$variation_pagination = $screen_data['variation_pagination'] ?? [];
$form_action = $screen_data['form_action'] ?? \Sultana\Admin\Core\Router::new_product_url();
$form_nonce_action = $screen_data['form_nonce_action'] ?? ProductController::CREATE_NONCE_ACTION;
$form_title = $screen_data['form_title'] ?? __( 'Nuevo producto', 'sultana-admin' );
$form_kicker = $screen_data['form_kicker'] ?? __( 'Producto simple', 'sultana-admin' );
$submit_label = $screen_data['submit_label'] ?? __( 'Guardar producto', 'sultana-admin' );
$form_id = 'sultana-admin-product-form';
$notice = $screen_data['notice'] ?? '';
$selected_categories = array_map( 'absint', $form['category_ids'] ?? [] );
$icon_url = static fn( string $name ): string => \Sultana\Admin\Core\Icons::url( $name );
$selected_brand = absint( $form['brand_id'] ?? 0 );
$product_image_ids = is_array( $form['product_image_ids'] ?? '' )
    ? implode( ',', array_map( 'absint', $form['product_image_ids'] ) )
    : (string) ( $form['product_image_ids'] ?? '' );
$variable_state = [
    'attributes' => $form['variable_attributes'] ?? [],
    'variations' => $form['variations'] ?? [],
];
$combo_state = [
    'components' => $combo_components,
];
$combo_current_price = (string) ( $form['current_price'] ?? '' );

?>
<section class="sultana-admin-product-form-screen" aria-labelledby="sultana-admin-product-new-title">
    <div class="sultana-admin-page-header sultana-admin-editor-header">
        <div>
            <h1 id="sultana-admin-product-new-title"><?php echo esc_html( $form_title ); ?></h1>
        </div>
        <div class="sultana-admin-editor-header__actions">
            <a class="sultana-admin-muted-action" href="<?php echo esc_url( \Sultana\Admin\Core\Router::products_url() ); ?>">
                <?php esc_html_e( 'Cancelar', 'sultana-admin' ); ?>
            </a>
            <button type="submit" form="<?php echo esc_attr( $form_id ); ?>">
                <span class="sultana-admin-icon" style="--sultana-admin-icon-url: url('<?php echo esc_url( $icon_url( 'save' ) ); ?>');" aria-hidden="true"></span>
                <?php echo esc_html( $submit_label ); ?>
            </button>
        </div>
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

    <form id="<?php echo esc_attr( $form_id ); ?>" class="<?php echo esc_attr( 'sultana-admin-product-form sultana-admin-product-form--' . $product_type ); ?>" method="post" action="<?php echo esc_url( $form_action ); ?>" enctype="multipart/form-data">
        <?php wp_nonce_field( $form_nonce_action, 'sultana_admin_product_nonce' ); ?>
        <input type="hidden" name="product_type" value="<?php echo esc_attr( $product_type ); ?>">

        <?php if ( empty( $screen_data['product_id'] ) ) : ?>
            <section class="sultana-admin-form-section" aria-labelledby="sultana-admin-product-type">
                <h2 id="sultana-admin-product-type"><?php esc_html_e( 'Tipo de producto', 'sultana-admin' ); ?></h2>
                <div class="sultana-admin-type-switch">
                    <a class="<?php echo esc_attr( 'sultana-admin-muted-action' . ( 'simple' === $product_type ? ' is-active' : '' ) ); ?>" href="<?php echo esc_url( add_query_arg( 'type', 'simple', \Sultana\Admin\Core\Router::new_product_url() ) ); ?>">
                        <?php esc_html_e( 'Producto simple', 'sultana-admin' ); ?>
                    </a>
                    <a class="<?php echo esc_attr( 'sultana-admin-muted-action' . ( 'variable' === $product_type ? ' is-active' : '' ) ); ?>" href="<?php echo esc_url( add_query_arg( 'type', 'variable', \Sultana\Admin\Core\Router::new_product_url() ) ); ?>">
                        <?php esc_html_e( 'Producto variable', 'sultana-admin' ); ?>
                    </a>
                    <a class="<?php echo esc_attr( 'sultana-admin-muted-action' . ( 'combo' === $product_type ? ' is-active' : '' ) ); ?>" href="<?php echo esc_url( add_query_arg( 'type', 'combo', \Sultana\Admin\Core\Router::new_product_url() ) ); ?>">
                        <?php esc_html_e( 'Combo', 'sultana-admin' ); ?>
                    </a>
                </div>
            </section>
        <?php endif; ?>

        <div class="sultana-admin-editor-layout">
            <div class="sultana-admin-editor-main">
        <section class="sultana-admin-form-section sultana-admin-form-section--main" aria-label="<?php esc_attr_e( 'Información principal', 'sultana-admin' ); ?>">
            <label class="sultana-admin-visually-hidden" for="sultana-admin-product-name"><?php esc_html_e( 'Nombre del producto', 'sultana-admin' ); ?></label>
            <input id="sultana-admin-product-name" class="sultana-admin-product-name-input" type="text" name="name" value="<?php echo esc_attr( $form['name'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Nombre del producto', 'sultana-admin' ); ?>" required>

            <label class="sultana-admin-visually-hidden" for="sultana-admin-product-short-description"><?php esc_html_e( 'Descripción corta', 'sultana-admin' ); ?></label>
            <textarea id="sultana-admin-product-short-description" name="short_description" rows="4" placeholder="<?php esc_attr_e( 'Descripción corta', 'sultana-admin' ); ?>"><?php echo esc_textarea( $form['short_description'] ?? '' ); ?></textarea>

            <?php if ( 'combo' === $product_type ) : ?>
                <label for="sultana-admin-product-sku"><?php esc_html_e( 'SKU general opcional', 'sultana-admin' ); ?></label>
                <input id="sultana-admin-product-sku" type="text" name="sku" value="<?php echo esc_attr( $form['sku'] ?? '' ); ?>">
            <?php endif; ?>

        </section>

        <?php if ( 'combo' === $product_type ) : ?>
            <section
                class="sultana-admin-form-section sultana-admin-combo-editor"
                aria-labelledby="sultana-admin-combo-components-title"
                data-sultana-combo-editor
                data-initial-state="<?php echo esc_attr( wp_json_encode( $combo_state ) ); ?>"
            >
                <h2 id="sultana-admin-combo-components-title"><?php esc_html_e( 'Componentes del combo', 'sultana-admin' ); ?></h2>
                <div class="sultana-admin-combo-components" data-sultana-combo-components></div>
                <button class="sultana-admin-muted-action" type="button" data-sultana-add-combo-component>
                    <?php esc_html_e( 'Agregar componente', 'sultana-admin' ); ?>
                </button>
                <div class="sultana-admin-image-status" aria-live="polite" data-sultana-combo-status></div>
            </section>

            <section class="sultana-admin-form-section" aria-labelledby="sultana-admin-combo-pricing">
                <h2 id="sultana-admin-combo-pricing"><?php esc_html_e( 'Precio', 'sultana-admin' ); ?></h2>
                <div class="sultana-admin-form-grid">
                    <div>
                        <label for="sultana-admin-combo-current-price"><?php esc_html_e( 'Precio actual', 'sultana-admin' ); ?></label>
                        <input id="sultana-admin-combo-current-price" type="text" value="<?php echo esc_attr( $combo_current_price ); ?>" readonly data-sultana-combo-current-price>
                    </div>
                    <div>
                        <label for="sultana-admin-product-sale-price"><?php esc_html_e( 'Precio de oferta', 'sultana-admin' ); ?></label>
                        <input id="sultana-admin-product-sale-price" type="number" name="sale_price" value="<?php echo esc_attr( $form['sale_price'] ?? '' ); ?>" min="0.01" step="0.01" inputmode="decimal">
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <?php if ( 'simple' === $product_type ) : ?>
        <section class="sultana-admin-form-section sultana-admin-form-section--pricing" aria-label="<?php esc_attr_e( 'Precio', 'sultana-admin' ); ?>">
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

        <section class="sultana-admin-form-section sultana-admin-form-section--inventory" aria-labelledby="sultana-admin-product-inventory">
            <h2 id="sultana-admin-product-inventory"><?php esc_html_e( 'Inventario', 'sultana-admin' ); ?></h2>
            <div class="sultana-admin-inventory-grid">
                <div>
                    <label for="sultana-admin-product-sku"><?php esc_html_e( 'SKU', 'sultana-admin' ); ?></label>
                    <input id="sultana-admin-product-sku" type="text" name="sku" value="<?php echo esc_attr( $form['sku'] ?? '' ); ?>">
                </div>

                <div>
                    <label for="sultana-admin-product-stock"><?php esc_html_e( 'Cantidad en stock', 'sultana-admin' ); ?></label>
                    <input id="sultana-admin-product-stock" type="number" name="stock_quantity" value="<?php echo esc_attr( $form['stock_quantity'] ?? '' ); ?>" min="0" step="1" inputmode="numeric" required>
                </div>

                <div>
                    <label for="sultana-admin-product-weight"><?php esc_html_e( 'Peso (kg)', 'sultana-admin' ); ?></label>
                    <input id="sultana-admin-product-weight" type="number" name="weight" value="<?php echo esc_attr( $form['weight'] ?? '' ); ?>" min="0.01" step="0.01" inputmode="decimal" required>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <?php if ( 'variable' === $product_type ) : ?>
        <section class="sultana-admin-form-section" aria-labelledby="sultana-admin-product-parent-sku">
            <h2 id="sultana-admin-product-parent-sku"><?php esc_html_e( 'Identificación', 'sultana-admin' ); ?></h2>
            <label for="sultana-admin-product-sku"><?php esc_html_e( 'SKU general opcional', 'sultana-admin' ); ?></label>
            <input id="sultana-admin-product-sku" type="text" name="sku" value="<?php echo esc_attr( $form['sku'] ?? '' ); ?>">
        </section>
        <?php endif; ?>


        <?php if ( 'variable' === $product_type ) : ?>
            <section
                class="sultana-admin-form-section sultana-admin-variable-editor"
                aria-labelledby="sultana-admin-variable-title"
                data-sultana-variable-editor
                data-available-attributes="<?php echo esc_attr( wp_json_encode( array_values( $available_attributes ) ) ); ?>"
                data-initial-state="<?php echo esc_attr( wp_json_encode( $variable_state ) ); ?>"
                data-max-generated-variations="<?php echo esc_attr( (string) $max_generated_variations ); ?>"
            >
                <h2 id="sultana-admin-variable-title"><?php esc_html_e( 'Atributos y variaciones', 'sultana-admin' ); ?></h2>
                <p class="sultana-admin-field-help"><?php esc_html_e( 'Selecciona atributos globales existentes y configura cada combinacion.', 'sultana-admin' ); ?></p>
                <div class="sultana-admin-variable-attributes" data-sultana-variable-attributes></div>
                <button class="sultana-admin-muted-action" type="button" data-sultana-add-attribute>
                    <?php esc_html_e( 'Agregar atributo', 'sultana-admin' ); ?>
                </button>
                <button class="sultana-admin-secondary-action" type="button" data-sultana-generate-variations>
                    <?php esc_html_e( 'Generar variaciones', 'sultana-admin' ); ?>
                </button>
                <div class="sultana-admin-field-help" aria-live="polite" data-sultana-variation-count></div>
                <div class="sultana-admin-image-status" aria-live="polite" data-sultana-variable-status></div>
                <div class="sultana-admin-variation-list" data-sultana-variation-list></div>
                <?php if ( ! empty( $variation_pagination ) && absint( $variation_pagination['total'] ?? 0 ) > absint( $variation_pagination['per_page'] ?? 0 ) ) : ?>
                    <?php
                    $variation_links = $variation_pagination['links'] ?? [ 'previous' => '', 'next' => '' ];
                    $variation_page = absint( $variation_pagination['page'] ?? 1 );
                    $variation_total_pages = max( 1, absint( $variation_pagination['total_pages'] ?? 1 ) );
                    $variation_total = absint( $variation_pagination['total'] ?? 0 );
                    ?>
                    <nav class="sultana-admin-pagination" aria-label="<?php esc_attr_e( 'Paginación de variaciones', 'sultana-admin' ); ?>">
                        <?php if ( ! empty( $variation_links['previous'] ) ) : ?>
                            <a href="<?php echo esc_url( $variation_links['previous'] ); ?>" aria-label="<?php esc_attr_e( 'Página anterior', 'sultana-admin' ); ?>" title="<?php esc_attr_e( 'Página anterior', 'sultana-admin' ); ?>"><span class="sultana-admin-icon" style="--sultana-admin-icon-url: url('<?php echo esc_url( $icon_url( 'chevron-left' ) ); ?>');" aria-hidden="true"></span></a>
                        <?php else : ?>
                            <span aria-disabled="true" aria-label="<?php esc_attr_e( 'Página anterior', 'sultana-admin' ); ?>"><span class="sultana-admin-icon" style="--sultana-admin-icon-url: url('<?php echo esc_url( $icon_url( 'chevron-left' ) ); ?>');" aria-hidden="true"></span></span>
                        <?php endif; ?>

                        <strong>
                            <?php
                            printf(
                                /* translators: 1: current page, 2: total pages, 3: total variations. */
                                esc_html__( 'Variaciones %1$d / %2$d - %3$d en total', 'sultana-admin' ),
                                $variation_page,
                                $variation_total_pages,
                                $variation_total
                            );
                            ?>
                        </strong>

                        <?php if ( ! empty( $variation_links['next'] ) ) : ?>
                            <a href="<?php echo esc_url( $variation_links['next'] ); ?>" aria-label="<?php esc_attr_e( 'Página siguiente', 'sultana-admin' ); ?>" title="<?php esc_attr_e( 'Página siguiente', 'sultana-admin' ); ?>"><span class="sultana-admin-icon" style="--sultana-admin-icon-url: url('<?php echo esc_url( $icon_url( 'chevron-right' ) ); ?>');" aria-hidden="true"></span></a>
                        <?php else : ?>
                            <span aria-disabled="true" aria-label="<?php esc_attr_e( 'Página siguiente', 'sultana-admin' ); ?>"><span class="sultana-admin-icon" style="--sultana-admin-icon-url: url('<?php echo esc_url( $icon_url( 'chevron-right' ) ); ?>');" aria-hidden="true"></span></span>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if ( 'combo' !== $product_type ) : ?>
            <section class="sultana-admin-form-section sultana-admin-form-section--images" aria-labelledby="sultana-admin-product-images-title">
                <div
                    class="sultana-admin-product-images"
                    data-sultana-product-images
                    data-initial-images="<?php echo esc_attr( wp_json_encode( array_values( $selected_images ) ) ); ?>"
                >
                    <input type="hidden" name="product_image_ids" value="<?php echo esc_attr( $product_image_ids ); ?>" data-sultana-product-image-ids>

                    <button type="button" class="sultana-admin-image-upload-button sultana-admin-image-upload-zone" data-sultana-product-image-trigger>
                        <span class="sultana-admin-icon" style="--sultana-admin-icon-url: url('<?php echo esc_url( $icon_url( 'images' ) ); ?>');" aria-hidden="true"></span>
                        <span><?php esc_html_e( 'Agregar imágenes', 'sultana-admin' ); ?></span>
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
        <?php endif; ?>

            </div>
            <aside class="sultana-admin-editor-sidebar">
                <?php if ( 'combo' !== $product_type ) : ?>
                    <section class="sultana-admin-form-section sultana-admin-form-section--categories" aria-label="<?php esc_attr_e( 'Categorías', 'sultana-admin' ); ?>">
                        <?php if ( empty( $categories ) ) : ?>
                            <p><?php esc_html_e( 'No hay categorías de producto disponibles.', 'sultana-admin' ); ?></p>
                        <?php else : ?>
                            <fieldset class="sultana-admin-category-picker" data-sultana-category-picker>
                                <legend><?php esc_html_e( 'Categorías', 'sultana-admin' ); ?></legend>
                                <label class="sultana-admin-visually-hidden" for="sultana-admin-category-search"><?php esc_html_e( 'Buscar categorías', 'sultana-admin' ); ?></label>
                                <input
                                    id="sultana-admin-category-search"
                                    class="sultana-admin-category-picker__search"
                                    type="search"
                                    placeholder="<?php esc_attr_e( 'Buscar categorías...', 'sultana-admin' ); ?>"
                                    autocomplete="off"
                                    role="combobox"
                                    aria-autocomplete="list"
                                    aria-expanded="false"
                                    aria-controls="sultana-admin-category-results"
                                    data-sultana-category-search
                                >
                                <div class="sultana-admin-category-picker__selected" aria-live="polite" data-sultana-category-selected></div>
                                <div id="sultana-admin-category-results" class="sultana-admin-category-picker__results" role="listbox" hidden data-sultana-category-results></div>
                                <div class="sultana-admin-category-picker__checkboxes" data-sultana-category-checkboxes>
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
                                </div>
                            </fieldset>
                        <?php endif; ?>
                    </section>

                    <section class="sultana-admin-form-section sultana-admin-form-section--brand" aria-label="<?php esc_attr_e( 'Marca', 'sultana-admin' ); ?>">
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
                <?php endif; ?>
        <section class="sultana-admin-form-section sultana-admin-form-section--publication" aria-label="<?php esc_attr_e( 'Publicación', 'sultana-admin' ); ?>">
            <label for="sultana-admin-product-status"><?php esc_html_e( 'Estado del producto', 'sultana-admin' ); ?></label>
            <select id="sultana-admin-product-status" name="status">
                <option value="draft" <?php selected( $form['status'] ?? 'draft', 'draft' ); ?>><?php esc_html_e( 'Borrador', 'sultana-admin' ); ?></option>
                <option value="publish" <?php selected( $form['status'] ?? 'draft', 'publish' ); ?>><?php esc_html_e( 'Publicado', 'sultana-admin' ); ?></option>
            </select>
        </section>
            </aside>
        </div>

        <div class="sultana-admin-form-actions">
            <a class="sultana-admin-muted-action" href="<?php echo esc_url( \Sultana\Admin\Core\Router::products_url() ); ?>">
                <?php esc_html_e( 'Cancelar', 'sultana-admin' ); ?>
            </a>
            <button type="submit">
                <span class="sultana-admin-icon" style="--sultana-admin-icon-url: url('<?php echo esc_url( $icon_url( 'save' ) ); ?>');" aria-hidden="true"></span>
                <?php echo esc_html( $submit_label ); ?>
            </button>
        </div>
    </form>
</section>

<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! empty( $screen_data['not_found'] ) || ! empty( $screen_data['forbidden'] ) ) :
    ?>
    <section class="sultana-admin-page--message" aria-live="polite">
        <h1><?php echo esc_html( $screen_data['message'] ?? __( 'Cupon no disponible.', 'sultana-admin' ) ); ?></h1>
        <a class="sultana-admin-secondary-action" href="<?php echo esc_url( \Sultana\Admin\Core\Router::coupons_url() ); ?>"><?php esc_html_e( 'Volver a cupones', 'sultana-admin' ); ?></a>
    </section>
    <?php
    return;
endif;

$form              = $screen_data['form'] ?? [];
$errors            = $screen_data['errors'] ?? [];
$discount_types    = $screen_data['discount_types'] ?? [];
$categories        = $screen_data['categories'] ?? [];
$brand_taxonomy    = $screen_data['brand_taxonomy'] ?? '';
$brands            = $screen_data['brands'] ?? [];
$form_action       = $screen_data['form_action'] ?? \Sultana\Admin\Core\Router::new_coupon_url();
$form_nonce_action = $screen_data['form_nonce_action'] ?? \Sultana\Admin\Coupons\CouponController::CREATE_NONCE_ACTION;
$submit_label      = empty( $screen_data['coupon_id'] ) ? __( 'Guardar cupon', 'sultana-admin' ) : __( 'Guardar cambios', 'sultana-admin' );
$icon_url          = static fn ( string $name ): string => \Sultana\Admin\Core\Icons::url( $name );
$selected_category_ids = array_map( 'absint', $form['product_categories'] ?? [] );
$selected_brand_ids    = array_map( 'absint', $form['product_brands'] ?? [] );

?>
<section class="sultana-admin-coupon-form-screen" aria-label="<?php esc_attr_e( 'Formulario de cupon', 'sultana-admin' ); ?>">
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

    <form class="sultana-admin-coupon-form" method="post" action="<?php echo esc_url( $form_action ); ?>">
        <?php wp_nonce_field( $form_nonce_action, 'sultana_admin_coupon_nonce' ); ?>

        <div class="sultana-admin-coupon-editor-layout">
            <div class="sultana-admin-coupon-editor-main">
                <section class="sultana-admin-coupon-card-section" aria-labelledby="sultana-admin-coupon-card-title">
                    <h2 id="sultana-admin-coupon-card-title"><?php esc_html_e( 'Cupon', 'sultana-admin' ); ?></h2>
                    <div class="sultana-admin-coupon-field">
                        <label for="sultana-admin-coupon-code"><?php esc_html_e( 'Codigo del cupon', 'sultana-admin' ); ?></label>
                        <input id="sultana-admin-coupon-code" type="text" name="code" value="<?php echo esc_attr( $form['code'] ?? '' ); ?>" required autocomplete="off">
                    </div>
                    <div class="sultana-admin-coupon-field">
                        <label for="sultana-admin-coupon-description"><?php esc_html_e( 'Descripcion opcional', 'sultana-admin' ); ?></label>
                        <textarea id="sultana-admin-coupon-description" name="description" rows="3"><?php echo esc_textarea( $form['description'] ?? '' ); ?></textarea>
                    </div>
                </section>

                <section class="sultana-admin-coupon-card-section" aria-labelledby="sultana-admin-coupon-discount-title">
                    <h2 id="sultana-admin-coupon-discount-title"><?php esc_html_e( 'Descuento', 'sultana-admin' ); ?></h2>
                    <div class="sultana-admin-coupon-grid sultana-admin-coupon-grid--discount">
                        <div class="sultana-admin-coupon-field">
                            <label for="sultana-admin-coupon-discount-type"><?php esc_html_e( 'Tipo', 'sultana-admin' ); ?></label>
                            <select id="sultana-admin-coupon-discount-type" name="discount_type" required>
                                <?php foreach ( $discount_types as $type_key => $type_label ) : ?>
                                    <option value="<?php echo esc_attr( (string) $type_key ); ?>" <?php selected( $form['discount_type'] ?? '', (string) $type_key ); ?>>
                                        <?php echo esc_html( (string) $type_label ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="sultana-admin-coupon-field">
                            <label for="sultana-admin-coupon-amount"><?php esc_html_e( 'Importe', 'sultana-admin' ); ?></label>
                            <input id="sultana-admin-coupon-amount" type="number" name="amount" value="<?php echo esc_attr( $form['amount'] ?? '' ); ?>" min="0.01" step="0.01" inputmode="decimal" required>
                        </div>
                        <div class="sultana-admin-coupon-field">
                            <label for="sultana-admin-coupon-date-expires"><?php esc_html_e( 'Vencimiento', 'sultana-admin' ); ?></label>
                        <input id="sultana-admin-coupon-date-expires" type="date" name="date_expires" value="<?php echo esc_attr( $form['date_expires'] ?? '' ); ?>">
                        </div>
                    </div>
                </section>

                <section class="sultana-admin-coupon-card-section" aria-labelledby="sultana-admin-coupon-restrictions-title">
                    <h2 id="sultana-admin-coupon-restrictions-title"><?php esc_html_e( 'Restricciones', 'sultana-admin' ); ?></h2>
                    <div class="sultana-admin-coupon-grid">
                        <div class="sultana-admin-coupon-field">
                            <label for="sultana-admin-coupon-minimum-amount"><?php esc_html_e( 'Gasto minimo', 'sultana-admin' ); ?></label>
                            <input id="sultana-admin-coupon-minimum-amount" type="number" name="minimum_amount" value="<?php echo esc_attr( $form['minimum_amount'] ?? '' ); ?>" min="0" step="0.01" inputmode="decimal">
                        </div>
                        <div class="sultana-admin-coupon-field">
                            <label for="sultana-admin-coupon-maximum-amount"><?php esc_html_e( 'Gasto maximo', 'sultana-admin' ); ?></label>
                            <input id="sultana-admin-coupon-maximum-amount" type="number" name="maximum_amount" value="<?php echo esc_attr( $form['maximum_amount'] ?? '' ); ?>" min="0" step="0.01" inputmode="decimal">
                        </div>
                    </div>
                    <div class="sultana-admin-coupon-options">
                        <label class="sultana-admin-coupon-option">
                            <input type="checkbox" name="individual_use" value="1" <?php checked( $form['individual_use'] ?? '0', '1' ); ?>>
                            <span><?php esc_html_e( 'No combinar con otros cupones', 'sultana-admin' ); ?></span>
                        </label>
                        <label class="sultana-admin-coupon-option">
                            <input type="checkbox" name="exclude_sale_items" value="1" <?php checked( $form['exclude_sale_items'] ?? '0', '1' ); ?>>
                            <span><?php esc_html_e( 'Excluir productos en oferta', 'sultana-admin' ); ?></span>
                        </label>
                    </div>
                    <div class="sultana-admin-coupon-field">
                        <label for="sultana-admin-coupon-email-restrictions"><?php esc_html_e( 'Emails permitidos', 'sultana-admin' ); ?></label>
                        <textarea id="sultana-admin-coupon-email-restrictions" name="email_restrictions" rows="2" placeholder="<?php esc_attr_e( 'cliente@correo.com, otro@correo.com', 'sultana-admin' ); ?>"><?php echo esc_textarea( $form['email_restrictions'] ?? '' ); ?></textarea>
                    </div>
                </section>
            </div>

            <aside class="sultana-admin-coupon-editor-sidebar">
                <section class="sultana-admin-coupon-card-section" aria-labelledby="sultana-admin-coupon-apply-title">
                    <h2 id="sultana-admin-coupon-apply-title"><?php esc_html_e( 'Aplicar a', 'sultana-admin' ); ?></h2>
                    <div class="sultana-admin-coupon-category-panels">
                        <fieldset class="sultana-admin-coupon-category-group">
                            <legend><?php esc_html_e( 'Categorias', 'sultana-admin' ); ?></legend>
                            <div class="sultana-admin-coupon-category-list">
                                <?php foreach ( $categories as $category ) : ?>
                                    <label>
                                        <input type="checkbox" name="product_categories[]" value="<?php echo esc_attr( (string) $category['id'] ); ?>" <?php checked( in_array( $category['id'], $selected_category_ids, true ) ); ?>>
                                        <span><?php echo esc_html( $category['name'] ); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </fieldset>
                        <?php if ( '' !== $brand_taxonomy && ! empty( $brands ) ) : ?>
                            <fieldset class="sultana-admin-coupon-category-group">
                                <legend><?php esc_html_e( 'Marcas', 'sultana-admin' ); ?></legend>
                                <div class="sultana-admin-coupon-category-list">
                                    <?php foreach ( $brands as $brand ) : ?>
                                        <label>
                                            <input type="checkbox" name="product_brands[]" value="<?php echo esc_attr( (string) $brand['id'] ); ?>" <?php checked( in_array( $brand['id'], $selected_brand_ids, true ) ); ?>>
                                            <span><?php echo esc_html( $brand['name'] ); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </fieldset>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="sultana-admin-coupon-card-section" aria-labelledby="sultana-admin-coupon-limits-title">
                    <h2 id="sultana-admin-coupon-limits-title"><?php esc_html_e( 'Limites de uso', 'sultana-admin' ); ?></h2>
                    <div class="sultana-admin-coupon-field">
                        <label for="sultana-admin-coupon-usage-limit"><?php esc_html_e( 'Limite total de usos', 'sultana-admin' ); ?></label>
                        <input id="sultana-admin-coupon-usage-limit" type="number" name="usage_limit" value="<?php echo esc_attr( $form['usage_limit'] ?? '' ); ?>" min="0" step="1" inputmode="numeric">
                    </div>
                    <div class="sultana-admin-coupon-field">
                        <label for="sultana-admin-coupon-usage-limit-user"><?php esc_html_e( 'Limite por usuario', 'sultana-admin' ); ?></label>
                        <input id="sultana-admin-coupon-usage-limit-user" type="number" name="usage_limit_per_user" value="<?php echo esc_attr( $form['usage_limit_per_user'] ?? '' ); ?>" min="0" step="1" inputmode="numeric">
                    </div>
                    <div class="sultana-admin-coupon-field">
                        <label for="sultana-admin-coupon-limit-items"><?php esc_html_e( 'Limite de articulos', 'sultana-admin' ); ?></label>
                        <input id="sultana-admin-coupon-limit-items" type="number" name="limit_usage_to_x_items" value="<?php echo esc_attr( $form['limit_usage_to_x_items'] ?? '' ); ?>" min="0" step="1" inputmode="numeric">
                    </div>
                </section>
            </aside>
        </div>

        <div class="sultana-admin-coupon-actions">
            <a class="sultana-admin-muted-action" href="<?php echo esc_url( \Sultana\Admin\Core\Router::coupons_url() ); ?>"><?php esc_html_e( 'Cancelar', 'sultana-admin' ); ?></a>
            <button type="submit">
                <span class="sultana-admin-icon" style="--sultana-admin-icon-url: url('<?php echo esc_url( $icon_url( 'save' ) ); ?>');" aria-hidden="true"></span>
                <?php echo esc_html( $submit_label ); ?>
            </button>
        </div>
    </form>
</section>

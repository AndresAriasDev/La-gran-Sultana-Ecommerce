<?php

namespace Sultana\CommerceCore\Modules\Combos\Admin;

use Sultana\CommerceCore\Modules\Combos\ComboStockService;
use WC_Product;
use WC_Product_Variation;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ComboProductsAdmin
{
    private const NONCE_ACTION = 'scc_save_combo_components';
    private const NONCE_NAME = 'scc_combo_components_nonce';

    public static function register(): void
    {
        add_filter( 'product_type_selector', [ self::class, 'add_product_type' ] );
        add_filter( 'woocommerce_product_data_tabs', [ self::class, 'add_product_data_tab' ] );
        add_action( 'woocommerce_product_data_panels', [ self::class, 'render_product_data_panel' ] );
        add_action( 'woocommerce_admin_process_product_object', [ self::class, 'save_product_components' ] );
        add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_admin_assets' ] );
        add_action( 'wp_ajax_scc_combo_component_search', [ self::class, 'search_combo_components' ] );
    }

    public static function add_product_type( array $types ): array
    {
        $types['combo'] = __( 'Producto combo', 'sultana-commerce-core' );

        return $types;
    }

    public static function add_product_data_tab( array $tabs ): array
    {
        foreach ( [ 'general', 'inventory', 'shipping', 'linked_product', 'advanced' ] as $tab_key ) {
            if ( isset( $tabs[ $tab_key ] ) ) {
                $classes = self::normalize_tab_classes( $tabs[ $tab_key ]['class'] ?? [] );
                $classes[] = 'show_if_combo';
                $tabs[ $tab_key ]['class'] = array_values( array_unique( $classes ) );
            }
        }

        if ( isset( $tabs['attribute'] ) ) {
            $classes = self::normalize_tab_classes( $tabs['attribute']['class'] ?? [] );
            $classes[] = 'hide_if_combo';
            $tabs['attribute']['class'] = array_values( array_unique( $classes ) );
        }

        $tabs['scc_combo_components'] = [
            'label'    => __( 'Componentes', 'sultana-commerce-core' ),
            'target'   => 'scc_combo_components_product_data',
            'class'    => [ 'show_if_combo' ],
            'priority' => 25,
        ];

        return $tabs;
    }

    /**
     * @param mixed $classes
     * @return array<int,string>
     */
    private static function normalize_tab_classes( $classes ): array
    {
        if ( is_array( $classes ) ) {
            return array_values( array_filter( array_map( 'sanitize_html_class', $classes ) ) );
        }

        if ( is_string( $classes ) ) {
            return array_values( array_filter( array_map( 'sanitize_html_class', preg_split( '/\s+/', $classes ) ) ) );
        }

        return [];
    }

    public static function render_product_data_panel(): void
    {
        global $post;

        $product_id = $post ? absint( $post->ID ) : 0;
        $components = ComboStockService::get_components( $product_id );
        $product    = $product_id && function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
        $pricing    = $product instanceof WC_Product ? ComboStockService::get_pricing_summary( $product ) : [
            'components_total'    => 0.0,
            'savings'             => 0.0,
            'savings_percentage'  => 0.0,
        ];
        ?>
        <div id="scc_combo_components_product_data" class="panel woocommerce_options_panel hidden">
            <div class="options_group">
                <?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME ); ?>
                <input type="hidden" name="scc_combo_components_present" value="1">
                <p class="form-field">
                    <label><?php esc_html_e( 'Componentes del combo', 'sultana-commerce-core' ); ?></label>
                    <span class="description">
                        <?php esc_html_e( 'Selecciona productos simples o variaciones concretas. No se permiten combos dentro de combos.', 'sultana-commerce-core' ); ?>
                    </span>
                </p>

                <table class="widefat scc-combo-components" data-scc-combo-components>
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Producto / variación', 'sultana-commerce-core' ); ?></th>
                            <th style="width:120px;"><?php esc_html_e( 'Cantidad', 'sultana-commerce-core' ); ?></th>
                            <th style="width:80px;"><?php esc_html_e( 'Acción', 'sultana-commerce-core' ); ?></th>
                        </tr>
                    </thead>
                    <tbody data-scc-combo-components-list>
                        <?php foreach ( $components as $index => $component ) : ?>
                            <?php self::render_component_row( $component, $index ); ?>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3">
                                <button type="button" class="button" data-scc-combo-add-component>
                                    <?php esc_html_e( 'Agregar componente', 'sultana-commerce-core' ); ?>
                                </button>
                            </td>
                        </tr>
                    </tfoot>
                </table>

                <script type="text/template" data-scc-combo-component-template>
                    <?php
                    self::render_component_row(
                        [
                            'product_id'   => 0,
                            'variation_id' => 0,
                            'quantity'     => 1,
                        ],
                        '__index__'
                    );
                    ?>
                </script>

                <div class="scc-combo-pricing-summary" style="margin:16px 12px 0; max-width:520px; padding:12px 14px; border:1px solid #dcdcde; border-radius:4px; background:#fff;">
                    <p style="display:flex; justify-content:space-between; gap:16px; margin:0 0 8px;">
                        <strong><?php esc_html_e( 'Valor comprando por separado', 'sultana-commerce-core' ); ?></strong>
                        <span><?php echo wp_kses_post( wc_price( (float) $pricing['components_total'] ) ); ?></span>
                    </p>
                    <p class="description" style="margin:10px 0 0;">
                        <?php esc_html_e( 'Este valor se calcula automaticamente a partir del precio regular y la cantidad de los productos incluidos.', 'sultana-commerce-core' ); ?>
                    </p>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * @param int|string $index
     */
    private static function render_component_row( array $component, $index ): void
    {
        $product_id   = absint( $component['product_id'] ?? 0 );
        $variation_id = absint( $component['variation_id'] ?? 0 );
        $quantity     = max( 1, absint( $component['quantity'] ?? 1 ) );
        $selected_id  = $variation_id ?: $product_id;
        $selected     = $selected_id && function_exists( 'wc_get_product' ) ? wc_get_product( $selected_id ) : null;
        $label        = $selected instanceof WC_Product ? self::format_component_option_label( $selected ) : '';
        ?>
        <tr data-scc-combo-component-row>
            <td>
                <select
                    class="wc-product-search"
                    style="width:100%;"
                    name="scc_combo_components[<?php echo esc_attr( (string) $index ); ?>][selected_id]"
                    data-placeholder="<?php esc_attr_e( 'Buscar producto o variación...', 'sultana-commerce-core' ); ?>"
                    data-action="scc_combo_component_search"
                    data-exclude="<?php echo esc_attr( get_the_ID() ); ?>"
                    data-allow_clear="true"
                >
                    <?php if ( $selected_id && $label ) : ?>
                        <option value="<?php echo esc_attr( $selected_id ); ?>" selected="selected"><?php echo esc_html( $label ); ?></option>
                    <?php endif; ?>
                </select>
            </td>
            <td>
                <input
                    type="number"
                    min="1"
                    step="1"
                    name="scc_combo_components[<?php echo esc_attr( (string) $index ); ?>][quantity]"
                    value="<?php echo esc_attr( $quantity ); ?>"
                    style="width:100%;"
                >
            </td>
            <td>
                <button type="button" class="button-link-delete" data-scc-combo-remove-component>
                    <?php esc_html_e( 'Eliminar', 'sultana-commerce-core' ); ?>
                </button>
            </td>
        </tr>
        <?php
    }

    public static function search_combo_components(): void
    {
        if ( ! current_user_can( 'edit_products' ) ) {
            wp_send_json( [] );
        }

        check_ajax_referer( 'search-products', 'security' );

        if ( ! function_exists( 'wc_get_product' ) ) {
            wp_send_json( [] );
        }

        $term    = isset( $_GET['term'] ) ? wc_clean( wp_unslash( $_GET['term'] ) ) : '';
        $exclude = isset( $_GET['exclude'] ) ? array_map( 'absint', wp_parse_id_list( wp_unslash( $_GET['exclude'] ) ) ) : [];
        $limit   = isset( $_GET['limit'] ) ? absint( $_GET['limit'] ) : 30;
        $limit   = $limit > 0 ? min( $limit, 50 ) : 30;

        if ( '' === $term ) {
            wp_send_json( [] );
        }

        $ids = self::search_product_and_variation_ids( $term, $limit * 3, $exclude );

        if ( empty( $ids ) ) {
            wp_send_json( [] );
        }

        $results = [];

        foreach ( $ids as $id ) {
            if ( count( $results ) >= $limit ) {
                break;
            }

            $product = wc_get_product( $id );

            if ( ! $product instanceof WC_Product || self::should_exclude_component_option( $product, $exclude ) ) {
                continue;
            }

            $results[ $product->get_id() ] = self::format_component_option_label( $product );
        }

        wp_send_json( $results );
    }

    /**
     * @return array<int>
     */
    private static function search_product_and_variation_ids( string $term, int $limit, array $exclude ): array
    {
        if ( class_exists( 'WC_Data_Store' ) ) {
            $data_store = \WC_Data_Store::load( 'product' );

            if ( is_object( $data_store ) && method_exists( $data_store, 'search_products' ) ) {
                $method     = new \ReflectionMethod( $data_store, 'search_products' );
                $parameters = $method->getNumberOfParameters();
                $arguments  = [ $term, '', true, false, $limit, [], $exclude ];
                $ids        = $data_store->search_products( ...array_slice( $arguments, 0, $parameters ) );

                return array_values( array_unique( array_map( 'absint', is_array( $ids ) ? $ids : [] ) ) );
            }
        }

        $query = new \WP_Query(
            [
                'fields'         => 'ids',
                'post_type'      => [ 'product', 'product_variation' ],
                'post_status'    => [ 'publish', 'private' ],
                'posts_per_page' => $limit,
                'post__not_in'   => $exclude,
                's'              => $term,
                'no_found_rows'  => true,
            ]
        );

        return array_values( array_unique( array_map( 'absint', $query->posts ) ) );
    }

    private static function should_exclude_component_option( WC_Product $product, array $exclude ): bool
    {
        if ( in_array( $product->get_id(), $exclude, true ) || ComboStockService::is_combo_product( $product ) ) {
            return true;
        }

        if ( $product instanceof WC_Product_Variation || 'variation' === $product->get_type() ) {
            $parent_id = absint( $product->get_parent_id() );
            $parent    = $parent_id ? wc_get_product( $parent_id ) : null;

            return ! $parent instanceof WC_Product || ! $parent->is_type( 'variable' ) || ComboStockService::is_combo_product( $parent );
        }

        return ! $product->is_type( 'simple' );
    }

    private static function format_component_option_label( WC_Product $product ): string
    {
        if ( $product instanceof WC_Product_Variation || 'variation' === $product->get_type() ) {
            $parent_id = absint( $product->get_parent_id() );
            $parent    = $parent_id ? wc_get_product( $parent_id ) : null;
            $name      = $parent instanceof WC_Product ? $parent->get_name() : $product->get_name();
            $variation = self::format_variation_attributes_label( $product, $parent );

            $label = $variation ? $name . ' — ' . $variation : $product->get_formatted_name();

            $label = str_replace( 'â€”', '-', $label );

            $label = $variation ? sprintf( '%s - %s', $name, $variation ) : $product->get_formatted_name();

            return rawurldecode( wp_strip_all_tags( $label ) );
        }

        return rawurldecode( wp_strip_all_tags( $product->get_formatted_name() ) );
    }

    private static function format_variation_attributes_label( WC_Product $variation, ?WC_Product $parent ): string
    {
        $details = [];

        foreach ( $variation->get_attributes() as $attribute_name => $attribute_value ) {
            $attribute_value = (string) $attribute_value;

            if ( '' === $attribute_value ) {
                continue;
            }

            $attribute_label = function_exists( 'wc_attribute_label' )
                ? wc_attribute_label( $attribute_name, $parent )
                : str_replace( [ 'attribute_', 'pa_', '-' ], [ '', '', ' ' ], $attribute_name );
            $attribute_display_value = $attribute_value;

            if ( taxonomy_exists( $attribute_name ) ) {
                $term = get_term_by( 'slug', $attribute_value, $attribute_name );

                if ( $term && ! is_wp_error( $term ) ) {
                    $attribute_display_value = $term->name;
                }
            }

            $details[] = wp_strip_all_tags( $attribute_label ) . ': ' . wp_strip_all_tags( $attribute_display_value );
        }

        return implode( ' · ', $details );
    }

    public static function save_product_components( WC_Product $product ): void
    {
        $product_id = $product->get_id();

        if ( ! current_user_can( 'edit_product', $product_id ) ) {
            return;
        }

        $nonce = sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ?? '' ) );

        if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
            return;
        }

        $posted_product_type = isset( $_POST['product-type'] )
            ? sanitize_text_field( wp_unslash( $_POST['product-type'] ) )
            : '';
        $is_combo = 'combo' === $posted_product_type || ( '' === $posted_product_type && 'combo' === $product->get_type() );

        if ( ! $is_combo ) {
            ComboStockService::delete_components( $product_id );
            return;
        }

        if ( ! isset( $_POST['scc_combo_components_present'] ) ) {
            return;
        }

        $posted_components = isset( $_POST['scc_combo_components'] ) && is_array( $_POST['scc_combo_components'] )
            ? wp_unslash( $_POST['scc_combo_components'] )
            : [];
        $components = ComboStockService::sanitize_components( $posted_components, $product_id );

        ComboStockService::save_components( $product_id, $components );
        ComboStockService::sync_combo_prices( $product_id, $product, false );
    }

    public static function enqueue_admin_assets( string $hook_suffix ): void
    {
        if ( ! in_array( $hook_suffix, [ 'post.php', 'post-new.php' ], true ) ) {
            return;
        }

        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

        if ( ! $screen || 'product' !== $screen->post_type ) {
            return;
        }

        wp_enqueue_script(
            'scc-combo-products-admin',
            SCC_PLUGIN_URL . 'assets/js/admin/combo-products.js',
            [ 'jquery', 'wc-enhanced-select' ],
            defined( 'SCC_VERSION' ) ? SCC_VERSION : '1.0.0',
            true
        );
    }
}

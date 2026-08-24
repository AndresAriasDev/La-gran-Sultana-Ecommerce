<?php

namespace Sultana\CommerceCore\Modules\HomePromotions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HomePromotions
{
    public const POST_TYPE = 'scc_home_promotion';

    private const META_DESKTOP_IMAGE_ID  = '_scc_home_promotion_desktop_image_id';
    private const META_MOBILE_IMAGE_ID   = '_scc_home_promotion_mobile_image_id';
    private const META_ALT_TEXT          = '_scc_home_promotion_alt_text';
    private const META_DESTINATION_TYPE  = '_scc_home_promotion_destination_type';
    private const META_DESTINATION_VALUE = '_scc_home_promotion_destination_value';
    private const META_CUSTOM_URL        = '_scc_home_promotion_custom_url';
    private const META_ACTIVE            = '_scc_home_promotion_active';

    private const DESTINATION_NONE             = 'none';
    private const DESTINATION_PAGE             = 'page';
    private const DESTINATION_PRODUCT_CATEGORY = 'product_category';
    private const DESTINATION_PRODUCT          = 'product';
    private const DESTINATION_BRAND            = 'brand';
    private const DESTINATION_SALE             = 'sale';
    private const DESTINATION_CUSTOM_URL       = 'custom_url';

    public static function register(): void
    {
        add_action( 'init', [ self::class, 'register_post_type' ] );
        add_action( 'add_meta_boxes_' . self::POST_TYPE, [ self::class, 'add_meta_boxes' ] );
        add_action( 'save_post_' . self::POST_TYPE, [ self::class, 'save_meta' ], 10, 2 );
        add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_admin_assets' ] );
    }

    public static function register_post_type(): void
    {
        register_post_type(
            self::POST_TYPE,
            [
                'labels'              => [
                    'name'                  => __( 'Promociones Home', 'sultana-commerce-core' ),
                    'singular_name'         => __( 'Promocion Home', 'sultana-commerce-core' ),
                    'menu_name'             => __( 'Promociones Home', 'sultana-commerce-core' ),
                    'name_admin_bar'        => __( 'Promocion Home', 'sultana-commerce-core' ),
                    'add_new'               => __( 'Agregar nueva', 'sultana-commerce-core' ),
                    'add_new_item'          => __( 'Agregar promocion Home', 'sultana-commerce-core' ),
                    'edit_item'             => __( 'Editar promocion Home', 'sultana-commerce-core' ),
                    'new_item'              => __( 'Nueva promocion Home', 'sultana-commerce-core' ),
                    'view_item'             => __( 'Ver promocion Home', 'sultana-commerce-core' ),
                    'search_items'          => __( 'Buscar promociones Home', 'sultana-commerce-core' ),
                    'not_found'             => __( 'No se encontraron promociones.', 'sultana-commerce-core' ),
                    'not_found_in_trash'    => __( 'No hay promociones en la papelera.', 'sultana-commerce-core' ),
                    'all_items'             => __( 'Promociones Home', 'sultana-commerce-core' ),
                    'archives'              => __( 'Promociones Home', 'sultana-commerce-core' ),
                    'attributes'            => __( 'Atributos de promocion', 'sultana-commerce-core' ),
                    'insert_into_item'      => __( 'Insertar en la promocion', 'sultana-commerce-core' ),
                    'uploaded_to_this_item' => __( 'Subido a esta promocion', 'sultana-commerce-core' ),
                ],
                'public'              => false,
                'show_ui'             => true,
                'show_in_menu'        => true,
                'show_in_admin_bar'   => true,
                'show_in_nav_menus'   => false,
                'show_in_rest'        => false,
                'exclude_from_search' => true,
                'publicly_queryable'  => false,
                'has_archive'         => false,
                'rewrite'             => false,
                'query_var'           => false,
                'menu_icon'           => 'dashicons-megaphone',
                'supports'            => [ 'title', 'page-attributes' ],
                'capability_type'     => 'post',
                'map_meta_cap'        => true,
            ]
        );
    }

    public static function add_meta_boxes(): void
    {
        add_meta_box(
            'scc-home-promotion-content',
            __( 'Banner responsive', 'sultana-commerce-core' ),
            [ self::class, 'render_content_meta_box' ],
            self::POST_TYPE,
            'normal',
            'high'
        );

        add_meta_box(
            'scc-home-promotion-status',
            __( 'Estado en Home', 'sultana-commerce-core' ),
            [ self::class, 'render_status_meta_box' ],
            self::POST_TYPE,
            'side',
            'high'
        );
    }

    public static function render_content_meta_box( \WP_Post $post ): void
    {
        wp_nonce_field( 'scc_home_promotion_meta', 'scc_home_promotion_nonce' );

        $desktop_image_id  = absint( get_post_meta( $post->ID, self::META_DESKTOP_IMAGE_ID, true ) );
        $mobile_image_id   = absint( get_post_meta( $post->ID, self::META_MOBILE_IMAGE_ID, true ) );
        $alt_text          = (string) get_post_meta( $post->ID, self::META_ALT_TEXT, true );
        $destination_type  = self::sanitize_destination_type( get_post_meta( $post->ID, self::META_DESTINATION_TYPE, true ) );
        $destination_value = (string) get_post_meta( $post->ID, self::META_DESTINATION_VALUE, true );
        $custom_url        = (string) get_post_meta( $post->ID, self::META_CUSTOM_URL, true );

        ?>
        <div class="scc-home-promotion-fields">
            <?php
            self::render_image_picker(
                'desktop',
                __( 'Banner escritorio', 'sultana-commerce-core' ),
                'scc_home_promotion_desktop_image_id',
                $desktop_image_id,
                __( 'Medida recomendada: 1600 x 600 px (proporcion 8:3).', 'sultana-commerce-core' )
            );

            self::render_image_picker(
                'mobile',
                __( 'Banner movil', 'sultana-commerce-core' ),
                'scc_home_promotion_mobile_image_id',
                $mobile_image_id,
                __( 'Medida recomendada: 750 x 375 px (proporcion 2:1).', 'sultana-commerce-core' )
            );
            ?>

            <p>
                <label for="scc-home-promotion-alt-text"><strong><?php esc_html_e( 'Texto alternativo', 'sultana-commerce-core' ); ?></strong></label>
                <input id="scc-home-promotion-alt-text" class="widefat" type="text" name="scc_home_promotion_alt_text" value="<?php echo esc_attr( $alt_text ); ?>">
                <span class="description"><?php esc_html_e( 'Describe brevemente el contenido o proposito del banner.', 'sultana-commerce-core' ); ?></span>
            </p>

            <?php self::render_destination_fields( $destination_type, $destination_value, $custom_url ); ?>
        </div>
        <?php
    }

    public static function render_status_meta_box( \WP_Post $post ): void
    {
        $is_active = 'yes' === get_post_meta( $post->ID, self::META_ACTIVE, true );

        ?>
        <p>
            <label>
                <input type="checkbox" name="scc_home_promotion_active" value="yes" <?php checked( $is_active ); ?>>
                <?php esc_html_e( 'Mostrar en Home / Promocion activa', 'sultana-commerce-core' ); ?>
            </label>
        </p>
        <p class="description"><?php esc_html_e( 'Usa el campo Orden para controlar su posicion.', 'sultana-commerce-core' ); ?></p>
        <?php
    }

    public static function save_meta( int $post_id, \WP_Post $post ): void
    {
        if (
            wp_is_post_autosave( $post_id )
            || wp_is_post_revision( $post_id )
            || ! isset( $_POST['scc_home_promotion_nonce'] )
            || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['scc_home_promotion_nonce'] ) ), 'scc_home_promotion_meta' )
            || ! current_user_can( 'edit_post', $post_id )
        ) {
            return;
        }

        $destination_type = self::sanitize_destination_type( $_POST['scc_home_promotion_destination_type'] ?? self::DESTINATION_NONE );

        update_post_meta( $post_id, self::META_DESKTOP_IMAGE_ID, self::sanitize_image_id( $_POST['scc_home_promotion_desktop_image_id'] ?? 0 ) );
        update_post_meta( $post_id, self::META_MOBILE_IMAGE_ID, self::sanitize_image_id( $_POST['scc_home_promotion_mobile_image_id'] ?? 0 ) );
        update_post_meta( $post_id, self::META_ALT_TEXT, sanitize_text_field( wp_unslash( $_POST['scc_home_promotion_alt_text'] ?? '' ) ) );
        update_post_meta( $post_id, self::META_DESTINATION_TYPE, $destination_type );
        update_post_meta( $post_id, self::META_DESTINATION_VALUE, self::sanitize_destination_value( $destination_type, $_POST ) );
        update_post_meta( $post_id, self::META_CUSTOM_URL, self::DESTINATION_CUSTOM_URL === $destination_type ? esc_url_raw( wp_unslash( $_POST['scc_home_promotion_custom_url'] ?? '' ) ) : '' );

        if ( 'yes' === sanitize_text_field( wp_unslash( $_POST['scc_home_promotion_active'] ?? '' ) ) ) {
            update_post_meta( $post_id, self::META_ACTIVE, 'yes' );
            return;
        }

        delete_post_meta( $post_id, self::META_ACTIVE );
    }

    public static function enqueue_admin_assets( string $hook_suffix ): void
    {
        $screen = get_current_screen();

        if ( ! $screen || self::POST_TYPE !== $screen->post_type ) {
            return;
        }

        wp_enqueue_media();

        wp_add_inline_script(
            'jquery',
            self::admin_inline_script(),
            'after'
        );
    }

    public static function get_active_promotion(): ?array
    {
        $promotions = self::get_active_promotions();

        return $promotions[0] ?? null;
    }

    public static function get_active_promotions(): array
    {
        $promotions = get_posts(
            [
                'post_type'      => self::POST_TYPE,
                'post_status'    => 'publish',
                'posts_per_page' => 5,
                'orderby'        => [
                    'menu_order' => 'ASC',
                    'date'       => 'DESC',
                    'ID'         => 'ASC',
                ],
                'meta_key'       => self::META_ACTIVE,
                'meta_value'     => 'yes',
                'fields'         => 'ids',
                'no_found_rows'  => true,
            ]
        );

        return array_values(
            array_filter(
                array_map(
                    static function ( $promotion_id ): array {
                        $promotion_id = absint( $promotion_id );

                        if ( ! $promotion_id ) {
                            return [];
                        }

                        $destination_type  = self::sanitize_destination_type( get_post_meta( $promotion_id, self::META_DESTINATION_TYPE, true ) );
                        $destination_value = (string) get_post_meta( $promotion_id, self::META_DESTINATION_VALUE, true );
                        $custom_url        = esc_url_raw( (string) get_post_meta( $promotion_id, self::META_CUSTOM_URL, true ) );

                        return [
                            'id'                => $promotion_id,
                            'name'              => get_the_title( $promotion_id ),
                            'desktop_image_id'  => absint( get_post_meta( $promotion_id, self::META_DESKTOP_IMAGE_ID, true ) ),
                            'mobile_image_id'   => absint( get_post_meta( $promotion_id, self::META_MOBILE_IMAGE_ID, true ) ),
                            'alt_text'          => (string) get_post_meta( $promotion_id, self::META_ALT_TEXT, true ),
                            'destination_type'  => $destination_type,
                            'destination_value' => $destination_value,
                            'custom_url'        => $custom_url,
                            'url'               => self::resolve_destination_url( $destination_type, $destination_value, $custom_url ),
                        ];
                    },
                    $promotions
                )
            )
        );
    }

    private static function render_image_picker( string $slot, string $label, string $field_name, int $image_id, string $description ): void
    {
        $image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : '';

        ?>
        <p>
            <label><strong><?php echo esc_html( $label ); ?></strong></label>
            <input type="hidden" name="<?php echo esc_attr( $field_name ); ?>" value="<?php echo esc_attr( (string) $image_id ); ?>" data-scc-home-promotion-image-id="<?php echo esc_attr( $slot ); ?>">
            <span class="scc-home-promotion-image-preview" data-scc-home-promotion-image-preview="<?php echo esc_attr( $slot ); ?>">
                <?php if ( $image_url ) : ?>
                    <img src="<?php echo esc_url( $image_url ); ?>" alt="" style="display:block;max-width:240px;height:auto;margin:8px 0;">
                <?php endif; ?>
            </span>
            <button type="button" class="button" data-scc-home-promotion-image-select="<?php echo esc_attr( $slot ); ?>"><?php esc_html_e( 'Seleccionar/Cambiar', 'sultana-commerce-core' ); ?></button>
            <button type="button" class="button" data-scc-home-promotion-image-remove="<?php echo esc_attr( $slot ); ?>" <?php disabled( ! $image_id ); ?>><?php esc_html_e( 'Quitar', 'sultana-commerce-core' ); ?></button>
            <span class="description"><?php echo esc_html( $description ); ?></span>
        </p>
        <?php
    }

    private static function render_destination_fields( string $destination_type, string $destination_value, string $custom_url ): void
    {
        ?>
        <p>
            <label for="scc-home-promotion-destination-type"><strong><?php esc_html_e( 'Destino', 'sultana-commerce-core' ); ?></strong></label>
            <select id="scc-home-promotion-destination-type" class="widefat" name="scc_home_promotion_destination_type" data-scc-home-promotion-destination-type>
                <?php foreach ( self::destination_type_options() as $type => $label ) : ?>
                    <option value="<?php echo esc_attr( $type ); ?>" <?php selected( $destination_type, $type ); ?>>
                        <?php echo esc_html( $label ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>

        <p data-scc-home-promotion-destination-field="page">
            <label for="scc-home-promotion-destination-page"><strong><?php esc_html_e( 'Pagina', 'sultana-commerce-core' ); ?></strong></label>
            <?php
            wp_dropdown_pages(
                [
                    'name'              => 'scc_home_promotion_destination_page',
                    'id'                => 'scc-home-promotion-destination-page',
                    'class'             => 'widefat',
                    'selected'          => self::DESTINATION_PAGE === $destination_type ? absint( $destination_value ) : 0,
                    'show_option_none'  => __( 'Selecciona una pagina', 'sultana-commerce-core' ),
                    'option_none_value' => '0',
                ]
            );
            ?>
        </p>

        <p data-scc-home-promotion-destination-field="product_category">
            <label for="scc-home-promotion-destination-category"><strong><?php esc_html_e( 'Categoria de producto', 'sultana-commerce-core' ); ?></strong></label>
            <?php
            wp_dropdown_categories(
                [
                    'taxonomy'          => 'product_cat',
                    'name'              => 'scc_home_promotion_destination_product_category',
                    'id'                => 'scc-home-promotion-destination-category',
                    'class'             => 'widefat',
                    'selected'          => self::DESTINATION_PRODUCT_CATEGORY === $destination_type ? absint( $destination_value ) : 0,
                    'hide_empty'        => false,
                    'show_option_none'  => __( 'Selecciona una categoria', 'sultana-commerce-core' ),
                    'option_none_value' => '0',
                ]
            );
            ?>
        </p>

        <p data-scc-home-promotion-destination-field="product">
            <label for="scc-home-promotion-destination-product"><strong><?php esc_html_e( 'ID de producto', 'sultana-commerce-core' ); ?></strong></label>
            <input id="scc-home-promotion-destination-product" class="widefat" type="number" min="1" step="1" name="scc_home_promotion_destination_product" value="<?php echo esc_attr( self::DESTINATION_PRODUCT === $destination_type ? (string) absint( $destination_value ) : '' ); ?>">
        </p>

        <p data-scc-home-promotion-destination-field="brand">
            <label for="scc-home-promotion-destination-brand"><strong><?php esc_html_e( 'Marca', 'sultana-commerce-core' ); ?></strong></label>
            <?php self::render_brand_destination_control( self::DESTINATION_BRAND === $destination_type ? absint( $destination_value ) : 0 ); ?>
        </p>

        <p data-scc-home-promotion-destination-field="custom_url">
            <label for="scc-home-promotion-custom-url"><strong><?php esc_html_e( 'URL personalizada', 'sultana-commerce-core' ); ?></strong></label>
            <input id="scc-home-promotion-custom-url" class="widefat" type="url" name="scc_home_promotion_custom_url" value="<?php echo esc_attr( $custom_url ); ?>" placeholder="<?php echo esc_attr( home_url( '/' ) ); ?>">
        </p>
        <?php
    }

    /**
     * @return array<string,string>
     */
    private static function destination_type_options(): array
    {
        return [
            self::DESTINATION_NONE             => __( 'Sin enlace', 'sultana-commerce-core' ),
            self::DESTINATION_PAGE             => __( 'Pagina', 'sultana-commerce-core' ),
            self::DESTINATION_PRODUCT_CATEGORY => __( 'Categoria de producto', 'sultana-commerce-core' ),
            self::DESTINATION_PRODUCT          => __( 'Producto', 'sultana-commerce-core' ),
            self::DESTINATION_BRAND            => __( 'Marca', 'sultana-commerce-core' ),
            self::DESTINATION_SALE             => __( 'Ofertas', 'sultana-commerce-core' ),
            self::DESTINATION_CUSTOM_URL       => __( 'URL personalizada', 'sultana-commerce-core' ),
        ];
    }

    private static function render_brand_destination_control( int $selected_brand_id ): void
    {
        $brand_taxonomy = self::brand_taxonomy();

        if ( '' === $brand_taxonomy ) {
            ?>
            <select id="scc-home-promotion-destination-brand" class="widefat" name="scc_home_promotion_destination_brand" disabled>
                <option value="0"><?php esc_html_e( 'No hay taxonomia de marcas disponible', 'sultana-commerce-core' ); ?></option>
            </select>
            <?php
            return;
        }

        wp_dropdown_categories(
            [
                'taxonomy'          => $brand_taxonomy,
                'name'              => 'scc_home_promotion_destination_brand',
                'id'                => 'scc-home-promotion-destination-brand',
                'class'             => 'widefat',
                'selected'          => $selected_brand_id,
                'hide_empty'        => false,
                'show_option_none'  => __( 'Selecciona una marca', 'sultana-commerce-core' ),
                'option_none_value' => '0',
            ]
        );
    }

    private static function sanitize_image_id( $image_id ): int
    {
        $image_id = absint( $image_id );

        return $image_id && 'attachment' === get_post_type( $image_id ) && wp_attachment_is_image( $image_id ) ? $image_id : 0;
    }

    private static function sanitize_destination_type( $type ): string
    {
        $type = sanitize_key( (string) $type );

        if ( 'offers' === $type ) {
            $type = self::DESTINATION_SALE;
        }

        return array_key_exists( $type, self::destination_type_options() ) ? $type : self::DESTINATION_NONE;
    }

    /**
     * @param array<string,mixed> $source
     */
    private static function sanitize_destination_value( string $destination_type, array $source ): string
    {
        if ( self::DESTINATION_PAGE === $destination_type ) {
            $page_id = absint( $source['scc_home_promotion_destination_page'] ?? 0 );

            return $page_id && 'page' === get_post_type( $page_id ) ? (string) $page_id : '';
        }

        if ( self::DESTINATION_PRODUCT_CATEGORY === $destination_type ) {
            $term_id = absint( $source['scc_home_promotion_destination_product_category'] ?? 0 );

            return $term_id && term_exists( $term_id, 'product_cat' ) ? (string) $term_id : '';
        }

        if ( self::DESTINATION_PRODUCT === $destination_type ) {
            $product_id = absint( $source['scc_home_promotion_destination_product'] ?? 0 );

            return $product_id && 'product' === get_post_type( $product_id ) ? (string) $product_id : '';
        }

        if ( self::DESTINATION_BRAND === $destination_type ) {
            $brand_taxonomy = self::brand_taxonomy();
            $term_id        = absint( $source['scc_home_promotion_destination_brand'] ?? 0 );

            return $term_id && '' !== $brand_taxonomy && term_exists( $term_id, $brand_taxonomy ) ? (string) $term_id : '';
        }

        return '';
    }

    private static function resolve_destination_url( string $destination_type, string $destination_value, string $custom_url ): string
    {
        if ( self::DESTINATION_NONE === $destination_type ) {
            return '';
        }

        if ( self::DESTINATION_CUSTOM_URL === $destination_type ) {
            return esc_url_raw( $custom_url );
        }

        if ( self::DESTINATION_SALE === $destination_type ) {
            $shop_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );

            return esc_url_raw( add_query_arg( 'on_sale', '1', $shop_url ) );
        }

        $object_id = absint( $destination_value );

        if ( ! $object_id ) {
            return '';
        }

        if ( self::DESTINATION_PAGE === $destination_type || self::DESTINATION_PRODUCT === $destination_type ) {
            $expected_post_type = self::DESTINATION_PAGE === $destination_type ? 'page' : 'product';

            if ( $expected_post_type !== get_post_type( $object_id ) ) {
                return '';
            }

            $permalink = get_permalink( $object_id );

            return $permalink ? esc_url_raw( $permalink ) : '';
        }

        if ( self::DESTINATION_PRODUCT_CATEGORY === $destination_type ) {
            return self::resolve_term_url( $object_id, 'product_cat' );
        }

        if ( self::DESTINATION_BRAND === $destination_type ) {
            $brand_taxonomy = self::brand_taxonomy();

            return '' !== $brand_taxonomy ? self::resolve_term_url( $object_id, $brand_taxonomy ) : '';
        }

        return '';
    }

    private static function resolve_term_url( int $term_id, string $taxonomy ): string
    {
        if ( ! $term_id || ! taxonomy_exists( $taxonomy ) || ! term_exists( $term_id, $taxonomy ) ) {
            return '';
        }

        $url = get_term_link( $term_id, $taxonomy );

        return is_wp_error( $url ) ? '' : esc_url_raw( $url );
    }

    private static function brand_taxonomy(): string
    {
        foreach ( [ 'product_brand', 'pa_marca', 'pa_brand', 'yith_product_brand' ] as $taxonomy ) {
            if ( ! taxonomy_exists( $taxonomy ) ) {
                continue;
            }

            $taxonomy_object = get_taxonomy( $taxonomy );

            if ( $taxonomy_object && in_array( 'product', (array) $taxonomy_object->object_type, true ) ) {
                return $taxonomy;
            }
        }

        return '';
    }

    private static function admin_inline_script(): string
    {
        return <<<'JS'
jQuery(function($){
  const frameState = {};

  const imageControls = function(slot) {
    return {
      imageId: $('[data-scc-home-promotion-image-id="' + slot + '"]'),
      preview: $('[data-scc-home-promotion-image-preview="' + slot + '"]'),
      removeButton: $('[data-scc-home-promotion-image-remove="' + slot + '"]')
    };
  };

  $('[data-scc-home-promotion-image-select]').on('click', function(event){
    event.preventDefault();

    const slot = $(this).data('sccHomePromotionImageSelect');
    const controls = imageControls(slot);

    if (!frameState[slot]) {
      frameState[slot] = wp.media({
        title: 'Seleccionar banner',
        button: { text: 'Usar esta imagen' },
        multiple: false,
        library: { type: 'image' }
      });

      frameState[slot].on('select', function(){
        const attachment = frameState[slot].state().get('selection').first().toJSON();
        const url = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;

        controls.imageId.val(attachment.id || '');
        controls.preview.html(url ? '<img src="' + url + '" alt="" style="display:block;max-width:240px;height:auto;margin:8px 0;">' : '');
        controls.removeButton.prop('disabled', false);
      });
    }

    frameState[slot].open();
  });

  $('[data-scc-home-promotion-image-remove]').on('click', function(event){
    event.preventDefault();

    const slot = $(this).data('sccHomePromotionImageRemove');
    const controls = imageControls(slot);

    controls.imageId.val('');
    controls.preview.empty();
    controls.removeButton.prop('disabled', true);
  });

  const destinationType = $('[data-scc-home-promotion-destination-type]');
  const destinationFields = $('[data-scc-home-promotion-destination-field]');

  const updateDestinationFields = function() {
    const activeType = destinationType.val();

    destinationFields.each(function(){
      const field = $(this);
      const isActive = field.data('sccHomePromotionDestinationField') === activeType;

      field.toggle(isActive);
      field.find(':input').prop('disabled', !isActive);
    });
  };

  destinationType.on('change', updateDestinationFields);
  updateDestinationFields();
});
JS;
    }
}

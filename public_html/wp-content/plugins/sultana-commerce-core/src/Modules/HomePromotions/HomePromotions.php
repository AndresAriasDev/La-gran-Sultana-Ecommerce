<?php

namespace Sultana\CommerceCore\Modules\HomePromotions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HomePromotions
{
    public const POST_TYPE = 'scc_home_promotion';

    private const META_PROMO_TITLE = '_scc_home_promotion_title';
    private const META_SUBTITLE    = '_scc_home_promotion_subtitle';
    private const META_IMAGE_ID    = '_scc_home_promotion_image_id';
    private const META_BUTTON_TEXT = '_scc_home_promotion_button_text';
    private const META_URL         = '_scc_home_promotion_url';
    private const META_ACTIVE      = '_scc_home_promotion_active';

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
                    'singular_name'         => __( 'Promoción Home', 'sultana-commerce-core' ),
                    'menu_name'             => __( 'Promociones Home', 'sultana-commerce-core' ),
                    'name_admin_bar'        => __( 'Promoción Home', 'sultana-commerce-core' ),
                    'add_new'               => __( 'Agregar nueva', 'sultana-commerce-core' ),
                    'add_new_item'          => __( 'Agregar promoción Home', 'sultana-commerce-core' ),
                    'edit_item'             => __( 'Editar promoción Home', 'sultana-commerce-core' ),
                    'new_item'              => __( 'Nueva promoción Home', 'sultana-commerce-core' ),
                    'view_item'             => __( 'Ver promoción Home', 'sultana-commerce-core' ),
                    'search_items'          => __( 'Buscar promociones Home', 'sultana-commerce-core' ),
                    'not_found'             => __( 'No se encontraron promociones.', 'sultana-commerce-core' ),
                    'not_found_in_trash'    => __( 'No hay promociones en la papelera.', 'sultana-commerce-core' ),
                    'all_items'             => __( 'Promociones Home', 'sultana-commerce-core' ),
                    'archives'              => __( 'Promociones Home', 'sultana-commerce-core' ),
                    'attributes'            => __( 'Atributos de promoción', 'sultana-commerce-core' ),
                    'insert_into_item'      => __( 'Insertar en la promoción', 'sultana-commerce-core' ),
                    'uploaded_to_this_item' => __( 'Subido a esta promoción', 'sultana-commerce-core' ),
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
            __( 'Contenido de la promoción', 'sultana-commerce-core' ),
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

        $promo_title = (string) get_post_meta( $post->ID, self::META_PROMO_TITLE, true );
        $subtitle    = (string) get_post_meta( $post->ID, self::META_SUBTITLE, true );
        $image_id    = absint( get_post_meta( $post->ID, self::META_IMAGE_ID, true ) );
        $button_text = (string) get_post_meta( $post->ID, self::META_BUTTON_TEXT, true );
        $url         = (string) get_post_meta( $post->ID, self::META_URL, true );
        $image_url   = $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : '';

        ?>
        <div class="scc-home-promotion-fields">
            <p>
                <label for="scc-home-promotion-title"><strong><?php esc_html_e( 'Título promocional', 'sultana-commerce-core' ); ?></strong></label>
                <input id="scc-home-promotion-title" class="widefat" type="text" name="scc_home_promotion_title" value="<?php echo esc_attr( $promo_title ); ?>" placeholder="<?php esc_attr_e( '10% OFF', 'sultana-commerce-core' ); ?>">
            </p>

            <p>
                <label for="scc-home-promotion-subtitle"><strong><?php esc_html_e( 'Texto/subtítulo', 'sultana-commerce-core' ); ?></strong></label>
                <textarea id="scc-home-promotion-subtitle" class="widefat" name="scc_home_promotion_subtitle" rows="3" placeholder="<?php esc_attr_e( 'En productos ELF', 'sultana-commerce-core' ); ?>"><?php echo esc_textarea( $subtitle ); ?></textarea>
            </p>

            <p>
                <label><strong><?php esc_html_e( 'Imagen principal PNG', 'sultana-commerce-core' ); ?></strong></label>
                <input type="hidden" name="scc_home_promotion_image_id" value="<?php echo esc_attr( $image_id ); ?>" data-scc-home-promotion-image-id>
                <span class="scc-home-promotion-image-preview" data-scc-home-promotion-image-preview>
                    <?php if ( $image_url ) : ?>
                        <img src="<?php echo esc_url( $image_url ); ?>" alt="" style="display:block;max-width:180px;height:auto;margin:8px 0;">
                    <?php endif; ?>
                </span>
                <button type="button" class="button" data-scc-home-promotion-image-select><?php esc_html_e( 'Seleccionar imagen', 'sultana-commerce-core' ); ?></button>
                <button type="button" class="button" data-scc-home-promotion-image-remove <?php disabled( ! $image_id ); ?>><?php esc_html_e( 'Quitar imagen', 'sultana-commerce-core' ); ?></button>
            </p>

            <p>
                <label for="scc-home-promotion-button"><strong><?php esc_html_e( 'Texto del botón', 'sultana-commerce-core' ); ?></strong></label>
                <input id="scc-home-promotion-button" class="widefat" type="text" name="scc_home_promotion_button_text" value="<?php echo esc_attr( $button_text ?: __( 'Ver todo', 'sultana-commerce-core' ) ); ?>">
            </p>

            <p>
                <label for="scc-home-promotion-url"><strong><?php esc_html_e( 'URL destino', 'sultana-commerce-core' ); ?></strong></label>
                <input id="scc-home-promotion-url" class="widefat" type="url" name="scc_home_promotion_url" value="<?php echo esc_attr( $url ); ?>" placeholder="<?php echo esc_attr( home_url( '/' ) ); ?>">
                <span class="description"><?php esc_html_e( 'Usa la URL de la marca, categoría o selección que corresponda. No se genera automáticamente desde el cupón.', 'sultana-commerce-core' ); ?></span>
            </p>
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
                <?php esc_html_e( 'Mostrar en Home / Promoción activa', 'sultana-commerce-core' ); ?>
            </label>
        </p>
        <p class="description"><?php esc_html_e( 'Puedes tener varias promociones activas al mismo tiempo. Usa el campo Orden para controlar su posición.', 'sultana-commerce-core' ); ?></p>
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

        update_post_meta( $post_id, self::META_PROMO_TITLE, sanitize_text_field( wp_unslash( $_POST['scc_home_promotion_title'] ?? '' ) ) );
        update_post_meta( $post_id, self::META_SUBTITLE, sanitize_textarea_field( wp_unslash( $_POST['scc_home_promotion_subtitle'] ?? '' ) ) );
        update_post_meta( $post_id, self::META_IMAGE_ID, self::sanitize_image_id( $_POST['scc_home_promotion_image_id'] ?? 0 ) );
        update_post_meta( $post_id, self::META_BUTTON_TEXT, sanitize_text_field( wp_unslash( $_POST['scc_home_promotion_button_text'] ?? '' ) ) );
        update_post_meta( $post_id, self::META_URL, esc_url_raw( wp_unslash( $_POST['scc_home_promotion_url'] ?? '' ) ) );

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
                'posts_per_page' => -1,
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

                        return [
                            'id'          => $promotion_id,
                            'title'       => (string) get_post_meta( $promotion_id, self::META_PROMO_TITLE, true ),
                            'subtitle'    => (string) get_post_meta( $promotion_id, self::META_SUBTITLE, true ),
                            'image_id'    => absint( get_post_meta( $promotion_id, self::META_IMAGE_ID, true ) ),
                            'button_text' => (string) get_post_meta( $promotion_id, self::META_BUTTON_TEXT, true ) ?: __( 'Ver todo', 'sultana-commerce-core' ),
                            'url'         => esc_url_raw( (string) get_post_meta( $promotion_id, self::META_URL, true ) ),
                        ];
                    },
                    $promotions
                )
            )
        );
    }

    private static function sanitize_image_id( $image_id ): int
    {
        $image_id = absint( $image_id );

        return $image_id && 'attachment' === get_post_type( $image_id ) ? $image_id : 0;
    }

    private static function admin_inline_script(): string
    {
        return <<<'JS'
jQuery(function($){
  const frameState = { frame: null };
  const imageId = $('[data-scc-home-promotion-image-id]');
  const preview = $('[data-scc-home-promotion-image-preview]');
  const removeButton = $('[data-scc-home-promotion-image-remove]');

  $('[data-scc-home-promotion-image-select]').on('click', function(event){
    event.preventDefault();

    if (!frameState.frame) {
      frameState.frame = wp.media({
        title: 'Seleccionar imagen principal',
        button: { text: 'Usar esta imagen' },
        multiple: false,
        library: { type: 'image' }
      });

      frameState.frame.on('select', function(){
        const attachment = frameState.frame.state().get('selection').first().toJSON();
        const url = attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;

        imageId.val(attachment.id || '');
        preview.html(url ? '<img src="' + url + '" alt="" style="display:block;max-width:180px;height:auto;margin:8px 0;">' : '');
        removeButton.prop('disabled', false);
      });
    }

    frameState.frame.open();
  });

  removeButton.on('click', function(event){
    event.preventDefault();
    imageId.val('');
    preview.empty();
    removeButton.prop('disabled', true);
  });
});
JS;
    }
}

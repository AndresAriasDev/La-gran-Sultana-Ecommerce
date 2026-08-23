<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$reviews        = $screen_data['reviews'] ?? [];
$search         = $screen_data['search'] ?? '';
$status         = $screen_data['status'] ?? '';
$status_options = $screen_data['status_options'] ?? [];
$page           = absint( $screen_data['page'] ?? 1 );
$total          = absint( $screen_data['total'] ?? 0 );
$total_pages    = absint( $screen_data['total_pages'] ?? 1 );
$pagination     = $screen_data['pagination'] ?? [ 'previous' => '', 'next' => '', 'items' => [] ];
$notice         = $screen_data['notice'] ?? '';
$errors         = $screen_data['errors'] ?? [];
$has_filters    = ! empty( $screen_data['has_filters'] );
$icon_url       = static fn ( string $name ): string => \Sultana\Admin\Core\Icons::url( $name );
$form_args      = [];

if ( '' !== $search ) {
    $form_args['s'] = $search;
}

if ( '' !== $status ) {
    $form_args['status'] = $status;
}

if ( $page > 1 ) {
    $form_args['review_page'] = $page;
}

$form_action  = empty( $form_args ) ? \Sultana\Admin\Core\Router::reviews_url() : add_query_arg( $form_args, \Sultana\Admin\Core\Router::reviews_url() );
$status_class = static fn ( string $review_status ): string => 'sultana-admin-review-status sultana-admin-review-status--' . sanitize_html_class( $review_status );
$rating_label = static fn ( int $rating ): string => sprintf(
    /* translators: %d: review rating. */
    _n( '%d estrella', '%d estrellas', $rating, 'sultana-admin' ),
    $rating
);
$render_icon = static function ( string $name ) use ( $icon_url ): void {
    ?>
    <span class="sultana-admin-icon" style="--sultana-admin-icon-url: url('<?php echo esc_url( $icon_url( $name ) ); ?>');" aria-hidden="true"></span>
    <?php
};
$render_action = static function ( array $review, string $action, string $label, string $variant = '' ) use ( $form_action ): void {
    $classes = trim( 'sultana-admin-review-action ' . $variant );
    ?>
    <form method="post" action="<?php echo esc_url( $form_action ); ?>">
        <input type="hidden" name="sultana_admin_action" value="<?php echo esc_attr( $action ); ?>">
        <input type="hidden" name="review_id" value="<?php echo esc_attr( (string) absint( $review['id'] ?? 0 ) ); ?>">
        <?php wp_nonce_field( \Sultana\Admin\Reviews\ReviewController::ACTION_NONCE_ACTION, 'sultana_admin_review_nonce' ); ?>
        <button class="<?php echo esc_attr( $classes ); ?>" type="submit">
            <?php echo esc_html( $label ); ?>
        </button>
    </form>
    <?php
};

?>
<section class="sultana-admin-reviews" aria-label="<?php esc_attr_e( 'Reseñas', 'sultana-admin' ); ?>">
    <?php if ( '' !== $notice ) : ?>
        <div class="sultana-admin-notice" role="status"><?php echo esc_html( $notice ); ?></div>
    <?php endif; ?>

    <?php if ( ! empty( $errors ) ) : ?>
        <div class="sultana-admin-error-list" role="alert">
            <strong><?php esc_html_e( 'No se pudo gestionar la reseña', 'sultana-admin' ); ?></strong>
            <ul>
                <?php foreach ( $errors as $error ) : ?>
                    <li><?php echo esc_html( $error ); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form class="sultana-admin-search sultana-admin-review-filters" method="get" action="<?php echo esc_url( \Sultana\Admin\Core\Router::reviews_url() ); ?>" role="search" data-applied-search="<?php echo esc_attr( $search ); ?>" data-clear-url="<?php echo esc_url( \Sultana\Admin\Core\Router::reviews_url() ); ?>" data-mobile-clear-only="true">
        <label for="sultana-admin-review-search"><?php esc_html_e( 'Buscar reseñas', 'sultana-admin' ); ?></label>
        <div class="sultana-admin-search__controls sultana-admin-review-filters__controls">
            <input
                id="sultana-admin-review-search"
                type="search"
                name="s"
                value="<?php echo esc_attr( $search ); ?>"
                placeholder="<?php esc_attr_e( 'Cliente, email o texto', 'sultana-admin' ); ?>"
            >
            <select name="status" aria-label="<?php esc_attr_e( 'Filtrar por estado', 'sultana-admin' ); ?>">
                <?php foreach ( $status_options as $status_key => $status_label ) : ?>
                    <option value="<?php echo esc_attr( $status_key ); ?>" <?php selected( $status, $status_key ); ?>>
                        <?php echo esc_html( $status_label ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button class="sultana-admin-search__button" type="submit" aria-label="<?php esc_attr_e( 'Filtrar reseñas', 'sultana-admin' ); ?>" title="<?php esc_attr_e( 'Filtrar reseñas', 'sultana-admin' ); ?>" data-search-label="<?php esc_attr_e( 'Buscar reseñas', 'sultana-admin' ); ?>" data-clear-label="<?php esc_attr_e( 'Limpiar busqueda', 'sultana-admin' ); ?>" data-search-icon="<?php echo esc_url( $icon_url( 'search' ) ); ?>" data-clear-icon="<?php echo esc_url( $icon_url( 'close' ) ); ?>" data-desktop-icon="<?php echo esc_url( $icon_url( 'funnel' ) ); ?>" data-desktop-label="<?php esc_attr_e( 'Filtrar reseñas', 'sultana-admin' ); ?>">
                <span class="sultana-admin-icon" style="--sultana-admin-icon-url: url('<?php echo esc_url( $icon_url( 'funnel' ) ); ?>');" aria-hidden="true"></span>
                <span class="sultana-admin-review-filter-button__text"><?php esc_html_e( 'Filtrar', 'sultana-admin' ); ?></span>
            </button>
        </div>
    </form>

    <div class="sultana-admin-list-summary" aria-live="polite">
        <?php
        printf(
            /* translators: 1: current page, 2: total pages, 3: total reviews. */
            esc_html__( 'Pagina %1$d de %2$d - %3$d reseñas', 'sultana-admin' ),
            $page,
            max( 1, $total_pages ),
            $total
        );
        ?>
    </div>

    <?php if ( empty( $reviews ) ) : ?>
        <div class="sultana-admin-empty">
            <h2><?php echo esc_html( $has_filters ? __( 'Sin resultados', 'sultana-admin' ) : __( 'No hay reseñas todavia', 'sultana-admin' ) ); ?></h2>
            <p><?php echo esc_html( $has_filters ? __( 'No encontramos reseñas con esos filtros.', 'sultana-admin' ) : __( 'Las reseñas de productos apareceran aqui.', 'sultana-admin' ) ); ?></p>
        </div>
    <?php else : ?>
        <div class="sultana-admin-review-cards">
            <?php foreach ( $reviews as $review ) : ?>
                <?php
                $review_id = absint( $review['id'] ?? 0 );
                $panel_id  = 'sultana-admin-review-panel-' . $review_id;
                ?>
                <article class="sultana-admin-review-card">
                    <button class="sultana-admin-review-card__header" type="button" aria-expanded="false" aria-controls="<?php echo esc_attr( $panel_id ); ?>">
                        <span class="sultana-admin-review-card__main">
                            <span class="sultana-admin-review-card__topline">
                                <strong><?php echo esc_html( (string) ( $review['author'] ?? '' ) ); ?></strong>
                                <span class="sultana-admin-review-rating" aria-label="<?php echo esc_attr( $rating_label( absint( $review['rating'] ?? 0 ) ) ); ?>">
                                    <?php echo esc_html( str_repeat( '★', absint( $review['rating'] ?? 0 ) ) . str_repeat( '☆', 5 - absint( $review['rating'] ?? 0 ) ) ); ?>
                                </span>
                            </span>
                        </span>
                        <span class="sultana-admin-review-product"><?php echo esc_html( (string) ( $review['product_title'] ?? '' ) ); ?></span>
                        <span class="<?php echo esc_attr( $status_class( (string) ( $review['status'] ?? '' ) ) ); ?>"><?php echo esc_html( (string) ( $review['status_label'] ?? '' ) ); ?></span>
                        <span class="sultana-admin-review-date"><?php echo esc_html( (string) ( $review['date'] ?? '' ) ); ?></span>
                        <span class="sultana-admin-review-card__chevron sultana-admin-icon" style="--sultana-admin-icon-url: url('<?php echo esc_url( $icon_url( 'chevron-right' ) ); ?>');" aria-hidden="true"></span>
                    </button>

                    <div id="<?php echo esc_attr( $panel_id ); ?>" class="sultana-admin-review-card__panel" hidden>
                        <div class="sultana-admin-review-content">
                            <span><?php esc_html_e( 'Reseña', 'sultana-admin' ); ?></span>
                            <p><?php echo nl2br( esc_html( (string) ( $review['content'] ?? '' ) ) ); ?></p>
                        </div>

                        <div class="sultana-admin-review-actions" aria-label="<?php esc_attr_e( 'Acciones de reseña', 'sultana-admin' ); ?>">
                            <?php if ( ! empty( $review['can_approve'] ) ) : ?>
                                <?php $render_action( $review, 'approve_review', __( 'Aprobar', 'sultana-admin' ), 'sultana-admin-review-action--success' ); ?>
                            <?php endif; ?>
                            <?php if ( ! empty( $review['can_trash'] ) ) : ?>
                                <?php $render_action( $review, 'trash_review', __( 'Eliminar', 'sultana-admin' ), 'sultana-admin-review-action--danger' ); ?>
                            <?php endif; ?>
                            <?php if ( ! empty( $review['can_restore'] ) ) : ?>
                                <?php $render_action( $review, 'restore_review', __( 'Restaurar', 'sultana-admin' ), 'sultana-admin-review-action--success' ); ?>
                            <?php endif; ?>
                            <?php if ( ! empty( $review['can_delete'] ) && 'trash' === ( $review['status'] ?? '' ) ) : ?>
                                <form method="post" action="<?php echo esc_url( $form_action ); ?>" onsubmit="return confirm('<?php echo esc_js( __( 'Eliminar esta reseña permanentemente?', 'sultana-admin' ) ); ?>');">
                                    <input type="hidden" name="sultana_admin_action" value="delete_review">
                                    <input type="hidden" name="review_id" value="<?php echo esc_attr( (string) $review_id ); ?>">
                                    <?php wp_nonce_field( \Sultana\Admin\Reviews\ReviewController::ACTION_NONCE_ACTION, 'sultana_admin_review_nonce' ); ?>
                                    <button class="sultana-admin-review-action sultana-admin-review-action--danger" type="submit">
                                        <?php esc_html_e( 'Eliminar definitivo', 'sultana-admin' ); ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ( $total_pages > 1 && ! empty( $pagination['items'] ) ) : ?>
        <nav class="sultana-admin-pagination sultana-admin-pagination--compact" aria-label="<?php esc_attr_e( 'Paginacion de reseñas', 'sultana-admin' ); ?>">
            <?php if ( ! empty( $pagination['previous'] ) ) : ?>
                <a class="sultana-admin-pagination__link sultana-admin-pagination__link--icon" href="<?php echo esc_url( $pagination['previous'] ); ?>" aria-label="<?php esc_attr_e( 'Pagina anterior', 'sultana-admin' ); ?>" title="<?php esc_attr_e( 'Pagina anterior', 'sultana-admin' ); ?>"><span class="sultana-admin-icon" style="--sultana-admin-icon-url: url('<?php echo esc_url( $icon_url( 'chevron-left' ) ); ?>');" aria-hidden="true"></span></a>
            <?php endif; ?>

            <?php foreach ( $pagination['items'] as $item ) : ?>
                <?php if ( 'ellipsis' === ( $item['type'] ?? '' ) ) : ?>
                    <span class="sultana-admin-pagination__ellipsis" aria-hidden="true">&hellip;</span>
                <?php elseif ( ! empty( $item['current'] ) ) : ?>
                    <span class="sultana-admin-pagination__current" aria-current="page"><?php echo esc_html( (string) absint( $item['page'] ?? 0 ) ); ?></span>
                <?php else : ?>
                    <a class="sultana-admin-pagination__link" href="<?php echo esc_url( (string) ( $item['url'] ?? '' ) ); ?>"><?php echo esc_html( (string) absint( $item['page'] ?? 0 ) ); ?></a>
                <?php endif; ?>
            <?php endforeach; ?>

            <?php if ( ! empty( $pagination['next'] ) ) : ?>
                <a class="sultana-admin-pagination__link sultana-admin-pagination__link--icon" href="<?php echo esc_url( $pagination['next'] ); ?>" aria-label="<?php esc_attr_e( 'Pagina siguiente', 'sultana-admin' ); ?>" title="<?php esc_attr_e( 'Pagina siguiente', 'sultana-admin' ); ?>"><span class="sultana-admin-icon" style="--sultana-admin-icon-url: url('<?php echo esc_url( $icon_url( 'chevron-right' ) ); ?>');" aria-hidden="true"></span></a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
</section>

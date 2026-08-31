<?php
/**
 * Product reviews template.
 *
 * @package SultanaStorefront
 */

defined( 'ABSPATH' ) || exit;

global $product;

if ( ! $product instanceof WC_Product ) {
    return;
}

$product_id       = $product->get_id();
$approved_reviews = get_comments(
    [
        'post_id' => $product_id,
        'status'  => 'approve',
        'type'    => 'review',
        'orderby' => 'comment_date_gmt',
        'order'   => 'DESC',
    ]
);
$visible_reviews  = $approved_reviews;
$current_user_id  = get_current_user_id();

if ( $current_user_id ) {
    $pending_reviews = get_comments(
        [
            'post_id' => $product_id,
            'status'  => 'hold',
            'type'    => 'review',
            'user_id' => $current_user_id,
            'orderby' => 'comment_date_gmt',
            'order'   => 'DESC',
        ]
    );

    $visible_reviews = array_merge( $pending_reviews, $approved_reviews );
}

$review_count     = count( $approved_reviews );
$average_rating   = (float) $product->get_average_rating();
$rating_counts    = array_fill( 1, 5, 0 );
$review_action    = is_user_logged_in() ? 'review' : 'account';
$current_user     = wp_get_current_user();
$icons_url        = trailingslashit( get_template_directory_uri() ) . 'assets/icons/';
$current_user_review = null;
$profile_avatar_class = '\Sultana\CommerceCore\Modules\Accounts\ProfileAvatar';

foreach ( $approved_reviews as $review ) {
    $rating = (int) get_comment_meta( $review->comment_ID, 'rating', true );

    if ( $rating >= 1 && $rating <= 5 ) {
        $rating_counts[ $rating ]++;
    }
}

if ( $current_user_id ) {
    foreach ( $visible_reviews as $review ) {
        if ( (int) $review->user_id === $current_user_id ) {
            $current_user_review = $review;
            break;
        }
    }
}
?>

<div id="reviews" class="product-reviews" data-review-section>
    <div class="product-reviews__summary">
        <div class="product-reviews__score">
            <strong><?php echo esc_html( number_format_i18n( $average_rating, 1 ) ); ?></strong>
            <div class="product-reviews__stars" aria-hidden="true">
                <?php for ( $star = 1; $star <= 5; $star++ ) : ?>
                    <span class="<?php echo esc_attr( $star <= round( $average_rating ) ? 'is-filled' : '' ); ?>">&#9733;</span>
                <?php endfor; ?>
            </div>
            <span>
                <?php echo esc_html( sprintf( _n( 'Basado en %d reseña', 'Basado en %d reseñas', $review_count, 'sultana-storefront' ), $review_count ) ); ?>
            </span>
        </div>

        <div class="product-reviews__bars" aria-label="<?php esc_attr_e( 'Resumen de valoraciones', 'sultana-storefront' ); ?>">
            <?php for ( $rating = 5; $rating >= 1; $rating-- ) : ?>
                <?php
                $count   = $rating_counts[ $rating ];
                $percent = $review_count > 0 ? ( $count / $review_count ) * 100 : 0;
                ?>
                <div class="product-reviews__bar-row">
                    <span><?php echo esc_html( (string) $rating ); ?></span>
                    <span class="product-reviews__bar"><span style="width: <?php echo esc_attr( (string) $percent ); ?>%"></span></span>
                    <strong><?php echo esc_html( (string) $count ); ?></strong>
                </div>
            <?php endfor; ?>
        </div>

        <div class="product-reviews__intro">
            <h3><?php esc_html_e( 'Reseñas del producto', 'sultana-storefront' ); ?></h3>
            <p><?php esc_html_e( 'Contá tu experiencia para ayudar a otras personas a elegir mejor.', 'sultana-storefront' ); ?></p>
            <?php if ( ! $current_user_review ) : ?>
                <button class="product-reviews__write" type="button" data-modal-open="<?php echo esc_attr( $review_action ); ?>">
                    <?php esc_html_e( 'Escribir una reseña', 'sultana-storefront' ); ?>
                </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="product-reviews__list">
        <?php if ( $visible_reviews ) : ?>
            <?php foreach ( $visible_reviews as $review ) : ?>
                <?php
                $rating     = (int) get_comment_meta( $review->comment_ID, 'rating', true );
                $is_pending = '0' === (string) $review->comment_approved;
                $is_owner   = (int) $review->user_id === $current_user_id;
                $has_review_avatar = (int) $review->user_id > 0
                    && class_exists( $profile_avatar_class )
                    && method_exists( $profile_avatar_class, 'has_custom_avatar' )
                    && $profile_avatar_class::has_custom_avatar( (int) $review->user_id );
                ?>
                <article class="product-review-card">
                    <div class="product-review-card__avatar" aria-hidden="true">
                        <?php if ( $has_review_avatar ) : ?>
                            <?php
                            echo get_avatar(
                                (int) $review->user_id,
                                46,
                                '',
                                $review->comment_author,
                                [
                                    'class' => 'product-review-card__avatar-image',
                                ]
                            ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                            ?>
                        <?php else : ?>
                            <?php echo esc_html( strtoupper( substr( $review->comment_author, 0, 1 ) ) ); ?>
                        <?php endif; ?>
                    </div>
                    <div class="product-review-card__body">
                        <header class="product-review-card__header">
                            <div class="product-review-card__identity">
                                <strong><?php echo esc_html( $review->comment_author ); ?></strong>
                                <span class="product-review-card__details">
                                    <?php if ( $rating > 0 ) : ?>
                                        <span class="product-review-card__stars" aria-label="<?php echo esc_attr( sprintf( __( '%d de 5 estrellas', 'sultana-storefront' ), $rating ) ); ?>">
                                            <?php for ( $star = 1; $star <= 5; $star++ ) : ?>
                                                <span class="<?php echo esc_attr( $star <= $rating ? 'is-filled' : '' ); ?>">&#9733;</span>
                                            <?php endfor; ?>
                                        </span>
                                    <?php endif; ?>
                                    <time datetime="<?php echo esc_attr( get_comment_date( DATE_W3C, $review ) ); ?>">
                                        <?php echo esc_html( get_comment_date( 'j F, Y', $review ) ); ?>
                                    </time>
                                </span>
                            </div>
                            <?php if ( $is_owner ) : ?>
                                <span class="product-review-card__meta">
                                    <span class="product-review-card__badge"><?php esc_html_e( 'Tu reseña', 'sultana-storefront' ); ?></span>
                                    <?php if ( $is_pending ) : ?>
                                        <span class="product-review-card__badge product-review-card__badge--pending"><?php esc_html_e( 'Pendiente de aprobación', 'sultana-storefront' ); ?></span>
                                    <?php else : ?>
                                        <span class="product-review-card__badge product-review-card__badge--published"><?php esc_html_e( 'Publicada', 'sultana-storefront' ); ?></span>
                                    <?php endif; ?>
                                    <button
                                        class="product-review-card__edit"
                                        type="button"
                                        data-modal-open="review"
                                        data-review-edit="1"
                                        data-review-id="<?php echo esc_attr( (string) $review->comment_ID ); ?>"
                                        data-review-rating="<?php echo esc_attr( (string) $rating ); ?>"
                                        data-review-content="<?php echo esc_attr( wp_strip_all_tags( $review->comment_content ) ); ?>"
                                        aria-label="<?php esc_attr_e( 'Editar reseña', 'sultana-storefront' ); ?>"
                                    >
                                        <img src="<?php echo esc_url( $icons_url . 'pencil.svg' ); ?>" alt="" width="16" height="16" aria-hidden="true">
                                    </button>
                                </span>
                            <?php endif; ?>
                        </header>
                        <div class="product-review-card__content">
                            <?php comment_text( $review ); ?>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php else : ?>
            <div class="product-reviews__empty">
                <img src="<?php echo esc_url( $icons_url . 'message-circle.svg' ); ?>" alt="" width="34" height="34" aria-hidden="true">
                <strong><?php esc_html_e( 'Todavía no hay reseñas', 'sultana-storefront' ); ?></strong>
                <p><?php esc_html_e( 'Sé la primera persona en compartir tu experiencia.', 'sultana-storefront' ); ?></p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ( is_user_logged_in() ) : ?>
    <div class="review-modal" data-review-modal="review" aria-hidden="true">
        <div class="review-modal__overlay" data-review-close></div>
        <section class="review-modal__dialog review-modal__dialog--review" role="dialog" aria-modal="true" aria-labelledby="review-form-title">
            <button class="review-modal__close" type="button" data-review-close aria-label="<?php esc_attr_e( 'Cerrar', 'sultana-storefront' ); ?>">
                <img src="<?php echo esc_url( get_template_directory_uri() . '/assets/icons/close-icon-blanco.png' ); ?>" alt="" width="18" height="18" aria-hidden="true">
            </button>
            <h3 id="review-form-title"><?php esc_html_e( 'Reseña del producto', 'sultana-storefront' ); ?></h3>
            <form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" class="product-review-form" data-product-review-form novalidate>
                <p class="product-review-form__message" data-product-review-message hidden></p>
                <fieldset class="product-review-form__rating">
                    <legend class="screen-reader-text"><?php esc_html_e( 'Puntuación de la reseña', 'sultana-storefront' ); ?></legend>
                    <?php for ( $rating = 5; $rating >= 1; $rating-- ) : ?>
                        <input id="rating-<?php echo esc_attr( (string) $rating ); ?>" type="radio" name="rating" value="<?php echo esc_attr( (string) $rating ); ?>" required>
                        <label for="rating-<?php echo esc_attr( (string) $rating ); ?>">&#9733;</label>
                    <?php endfor; ?>
                </fieldset>
                <label class="product-review-form__comment">
                    <span class="screen-reader-text"><?php esc_html_e( 'Tu reseña', 'sultana-storefront' ); ?></span>
                    <textarea name="comment" rows="6" placeholder="<?php esc_attr_e( 'Dinos que opinas...', 'sultana-storefront' ); ?>" required></textarea>
                </label>
                <input type="hidden" name="comment_post_ID" value="<?php echo esc_attr( (string) $product_id ); ?>">
                <input type="hidden" name="comment_parent" value="0">
                <input type="hidden" name="comment_type" value="review">
                <input type="hidden" name="action" value="scc_save_product_review">
                <input type="hidden" name="scc_review_id" value="" data-review-id-field>
                <?php wp_nonce_field( 'scc_save_product_review', 'scc_review_nonce' ); ?>
                <input type="hidden" name="redirect_to" value="<?php echo esc_url( get_permalink( $product_id ) . '#reviews' ); ?>">
                <button type="submit"><?php esc_html_e( 'Enviar reseña', 'sultana-storefront' ); ?></button>
            </form>
        </section>
    </div>
<?php endif; ?>

<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();
?>

<div class="archive-content">

    <header class="archive-header">
        <?php the_archive_title( '<h1 class="archive-header__title">', '</h1>' ); ?>
        <?php the_archive_description( '<div class="archive-header__description">', '</div>' ); ?>
    </header>

    <?php if ( have_posts() ) : ?>

        <div class="archive-grid">
            <?php while ( have_posts() ) : ?>
                <?php the_post(); ?>

                <article id="post-<?php the_ID(); ?>" <?php post_class( 'archive-card' ); ?>>
                    <h2 class="archive-card__title">
                        <a href="<?php the_permalink(); ?>">
                            <?php the_title(); ?>
                        </a>
                    </h2>

                    <div class="archive-card__excerpt">
                        <?php the_excerpt(); ?>
                    </div>
                </article>

            <?php endwhile; ?>
        </div>

        <?php the_posts_pagination(); ?>

    <?php else : ?>

        <p><?php esc_html_e( 'No se encontró contenido.', 'sultana-storefront' ); ?></p>

    <?php endif; ?>

</div>

<?php
get_footer();
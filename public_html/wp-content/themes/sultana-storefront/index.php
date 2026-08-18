<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();
?>

<div class="site-content">
    <?php if ( have_posts() ) : ?>

        <?php while ( have_posts() ) : ?>
            <?php the_post(); ?>

            <article id="post-<?php the_ID(); ?>" <?php post_class( 'content-entry' ); ?>>
                <header class="content-entry__header">
                    <h1 class="content-entry__title">
                        <?php the_title(); ?>
                    </h1>
                </header>

                <div class="content-entry__body">
                    <?php the_content(); ?>
                </div>
            </article>

        <?php endwhile; ?>

    <?php else : ?>

        <p><?php esc_html_e( 'No se encontro contenido.', 'sultana-storefront' ); ?></p>

    <?php endif; ?>
</div>

<?php
get_footer();

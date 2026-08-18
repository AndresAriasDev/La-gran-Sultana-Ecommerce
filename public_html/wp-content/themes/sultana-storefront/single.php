<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();
?>

<div class="single-content">
    <?php while ( have_posts() ) : ?>
        <?php the_post(); ?>

        <article id="post-<?php the_ID(); ?>" <?php post_class( 'single-entry' ); ?>>
            <header class="single-entry__header">
                <h1 class="single-entry__title">
                    <?php the_title(); ?>
                </h1>
            </header>

            <div class="single-entry__content">
                <?php the_content(); ?>
            </div>
        </article>

    <?php endwhile; ?>
</div>

<?php
get_footer();
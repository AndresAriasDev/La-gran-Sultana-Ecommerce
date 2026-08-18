<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();
?>

<div class="page-content">
    <?php while ( have_posts() ) : ?>
        <?php the_post(); ?>

        <article id="post-<?php the_ID(); ?>" <?php post_class( 'page-entry' ); ?>>
            <?php if ( ! function_exists( 'is_account_page' ) || ! is_account_page() ) : ?>
                <header class="page-entry__header">
                    <h1 class="page-entry__title">
                        <?php the_title(); ?>
                    </h1>
                </header>
            <?php endif; ?>

            <div class="page-entry__content">
                <?php the_content(); ?>
            </div>
        </article>

    <?php endwhile; ?>
</div>

<?php
get_footer();

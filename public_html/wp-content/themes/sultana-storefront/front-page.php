<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();
?>

<div class="home-page">
    <div class="home-page__container">
        <?php
        locate_template( 'templates/sections/home-promotion-banner.php', true, false );
        locate_template( 'templates/sections/home-categories.php', true, false );
        locate_template( 'templates/sections/home-for-you.php', true, false );
        locate_template( 'templates/sections/home-brands.php', true, false );
        ?>
    </div>
</div>

<?php
get_footer();

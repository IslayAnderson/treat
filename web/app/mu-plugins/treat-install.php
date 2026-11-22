<?php
/**
 * Plugin Name: TREAT install defaults
 * Description: On a fresh WordPress install, sets /%postname%/ permalinks and a static Home page as the front page (the theme's front-page template expects one).
 */

add_action('wp_install', function () {
    update_option('permalink_structure', '/%postname%/');

    $home = wp_insert_post([
        'post_type' => 'page',
        'post_status' => 'publish',
        'post_title' => 'Home',
    ]);

    if ($home && ! is_wp_error($home)) {
        update_option('show_on_front', 'page');
        update_option('page_on_front', $home);
    }

    flush_rewrite_rules(false);
});

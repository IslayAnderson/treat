<?php
/**
 * Plugin Name: TREAT classic editor
 * Description: Turns off the block editor (Gutenberg) for every post type and for widgets, and stops loading block styles on the front end.
 */

// Classic editor everywhere.
add_filter('use_block_editor_for_post_type', '__return_false', 100);
add_filter('use_widgets_block_editor', '__return_false');

// Content isn't built from blocks, so don't ship block CSS or global styles.
add_action('wp_enqueue_scripts', function () {
    wp_dequeue_style('wp-block-library');
    wp_dequeue_style('wp-block-library-theme');
    wp_dequeue_style('classic-theme-styles');
    wp_dequeue_style('global-styles');
}, 100);

// Block-based inline CSS that WordPress adds on demand.
add_filter('should_load_separate_core_block_assets', '__return_true');
remove_action('wp_enqueue_scripts', 'wp_enqueue_global_styles');
remove_action('wp_footer', 'wp_enqueue_global_styles', 1);
remove_action('wp_body_open', 'wp_global_styles_render_svg_filters');

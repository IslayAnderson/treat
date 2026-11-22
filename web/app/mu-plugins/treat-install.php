<?php
/**
 * Plugin Name: TREAT install defaults
 * Description: On a fresh WordPress install, sets /%postname%/ permalinks, a static Home page as the front page (the theme's front-page template expects one) and the Primary menu.
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

    treat_setup_primary_menu();

    flush_rewrite_rules(false);
});

/**
 * Create the "Primary" menu (Issues, About, Contact) and assign it to the
 * theme's primary location. Safe to re-run: it only creates what's missing,
 * only fills an empty menu, and never replaces a menu already assigned.
 * Existing sites: vendor/bin/wp eval-file scripts/setup-menu.php
 *
 * @return string[] what changed, for logging
 */
function treat_setup_primary_menu(): array
{
    $log = [];

    $menu = wp_get_nav_menu_object('Primary');
    $menu_id = $menu ? (int) $menu->term_id : (int) wp_create_nav_menu('Primary');
    if (! $menu) {
        $log[] = "created menu Primary ({$menu_id})";
    }

    if (! wp_get_nav_menu_items($menu_id)) {
        // About page: create as a draft if missing, so nothing empty goes public.
        $about = get_page_by_path('about');
        if (! $about) {
            $about_id = wp_insert_post(['post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'About', 'post_name' => 'about']);
            $about = get_post($about_id);
            $log[] = "created draft About page ({$about_id})";
        }

        $items = [
            ['menu-item-title' => 'Issues', 'menu-item-type' => 'post_type_archive', 'menu-item-object' => 'issue'],
            ['menu-item-title' => 'About', 'menu-item-type' => 'post_type', 'menu-item-object' => 'page', 'menu-item-object-id' => $about->ID],
            ['menu-item-title' => 'Contact', 'menu-item-type' => 'custom', 'menu-item-url' => home_url('/#contact')],
        ];

        foreach ($items as $position => $item) {
            wp_update_nav_menu_item($menu_id, 0, $item + ['menu-item-status' => 'publish', 'menu-item-position' => $position + 1]);
            $log[] = "added {$item['menu-item-title']}";
        }
    }

    $locations = get_nav_menu_locations();
    if (empty($locations['primary'])) {
        $locations['primary'] = $menu_id;
        set_theme_mod('nav_menu_locations', $locations);
        $log[] = 'assigned Primary to the primary location';
    }

    return $log ?: ['nothing to do'];
}

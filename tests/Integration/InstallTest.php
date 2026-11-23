<?php

/** Remove the menu the installer made, so a test starts from an existing site without one. */
function remove_primary_menu(): void
{
    if ($menu = wp_get_nav_menu_object('Primary')) {
        wp_delete_nav_menu($menu->term_id);
    }
    set_theme_mod('nav_menu_locations', []);
}

function primary_menu_items(): array
{
    return array_map(
        fn($item) => [$item->title, $item->type, $item->object],
        wp_get_nav_menu_items('Primary') ?: [],
    );
}

function remove_about_pages(): void
{
    // get_page_by_path() finds drafts too (a "name" query hides them from logged-out users).
    while ($page = get_page_by_path('about')) {
        wp_delete_post($page->ID, true);
    }
}

describe('treat_setup_primary_menu()', function () {
    beforeEach(function () {
        remove_primary_menu();
        remove_about_pages();
        // See menus as stored: the theme hides items for unpublished pages from visitors.
        remove_all_filters('wp_get_nav_menu_items');
    });

    it('creates Issues, About and Contact in order and assigns the menu', function () {
        $log = treat_setup_primary_menu();

        $menu = wp_get_nav_menu_object('Primary');
        expect($menu)->not->toBeFalse()
            ->and(get_nav_menu_locations()['primary'])->toBe($menu->term_id)
            ->and(primary_menu_items())->toBe([
                ['Issues', 'post_type_archive', 'issue'],
                ['About', 'post_type', 'page'],
                ['Contact', 'custom', 'custom'],
            ])
            ->and(wp_get_nav_menu_items('Primary')[2]->url)->toBe(home_url('/#contact'))
            ->and($log)->toContain('assigned Primary to the primary location');
    });

    it('links to an existing About page', function () {
        $about = wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'About', 'post_name' => 'about']);

        $log = treat_setup_primary_menu();

        expect((int) wp_get_nav_menu_items('Primary')[1]->object_id)->toBe($about)
            ->and(implode("\n", $log))->not->toContain('created draft About');
    });

    it('creates a draft About page when there is none, so nothing empty goes public', function () {
        treat_setup_primary_menu();

        $about = get_page_by_path('about');
        expect($about)->not->toBeNull()
            ->and($about->post_status)->toBe('draft')
            ->and((int) wp_get_nav_menu_items('Primary')[1]->object_id)->toBe($about->ID);
    });

    it('does nothing the second time', function () {
        treat_setup_primary_menu();

        expect(treat_setup_primary_menu())->toBe(['nothing to do'])
            ->and(wp_get_nav_menu_items('Primary'))->toHaveCount(3);
    });

    it('leaves an edited Primary menu alone', function () {
        $menu = wp_create_nav_menu('Primary');
        wp_update_nav_menu_item($menu, 0, ['menu-item-title' => 'Shop', 'menu-item-url' => 'https://example.com', 'menu-item-status' => 'publish']);

        treat_setup_primary_menu();

        expect(primary_menu_items())->toBe([['Shop', 'custom', 'custom']]);
    });

    it('does not replace another menu already in the primary location', function () {
        $other = wp_create_nav_menu('Main');
        set_theme_mod('nav_menu_locations', ['primary' => $other]);

        treat_setup_primary_menu();

        expect(get_nav_menu_locations()['primary'])->toBe($other);
    });
});

describe('fresh install', function () {
    it('sets pretty permalinks, a static Home page and the Primary menu', function () {
        remove_primary_menu();
        update_option('permalink_structure', '');
        update_option('show_on_front', 'posts');

        do_action('wp_install', wp_get_current_user());

        $front = get_post((int) get_option('page_on_front'));
        expect(get_option('permalink_structure'))->toBe('/%postname%/')
            ->and(get_option('show_on_front'))->toBe('page')
            ->and($front->post_title)->toBe('Home')
            ->and($front->post_status)->toBe('publish')
            ->and(get_nav_menu_locations())->toHaveKey('primary');
    });
});

<?php

use Timber\Timber;
use Treat\Site;

/** Context as the theme's filters build it (Timber::context() caches, so go straight to the filter). */
function theme_context(): array
{
    return apply_filters('timber/context', []);
}

function page(string $slug, string $status = 'publish'): int
{
    return wp_insert_post(['post_type' => 'page', 'post_status' => $status, 'post_title' => ucfirst($slug), 'post_name' => $slug]);
}

describe('setup', function () {
    it('registers the primary menu location and image sizes', function () {
        expect(get_registered_nav_menus())->toHaveKey('primary')
            ->and(wp_get_registered_image_subsizes())->toHaveKeys(['treat-hero', 'treat-card'])
            ->and(wp_get_registered_image_subsizes()['treat-card'])->toBe(['width' => 640, 'height' => 900, 'crop' => true]);
    });
});

describe('tags', function () {
    it('keeps tags in the order they were added', function () {
        $issue = wp_insert_post(['post_type' => 'issue', 'post_status' => 'publish', 'post_title' => 'Issue']);
        wp_set_post_tags($issue, ['Zebra', 'Apple', 'Mango']);

        $tags = wp_get_object_terms($issue, 'post_tag', ['orderby' => 'term_order', 'fields' => 'names']);

        expect(get_taxonomy('post_tag')->sort)->toBeTrue()
            ->and((new Site())->sortTags([], 'category'))->toBe([])
            ->and($tags)->toBe(['Zebra', 'Apple', 'Mango']);
    });

    it('lists posts and issues together on a tag archive', function () {
        $post = wp_insert_post(['post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Story']);
        $issue = wp_insert_post(['post_type' => 'issue', 'post_status' => 'publish', 'post_title' => 'Issue']);
        $page = page('tagged');
        foreach ([$post, $issue] as $id) {
            wp_set_post_tags($id, ['shared']);
        }

        $found = wp_list_pluck($GLOBALS['wp_the_query']->query(['tag' => 'shared']), 'ID');

        expect($found)->toEqualCanonicalizing([$post, $issue])->not->toContain($page);
    });

    it('leaves other queries alone', function () {
        $query = new WP_Query(['tag' => 'shared', 'fields' => 'ids']);

        expect($query->get('post_type'))->toBe('');
    });
});

describe('menus', function () {
    function menu_with(array $items): int
    {
        $menu = wp_create_nav_menu('Test ' . uniqid());
        foreach ($items as $item) {
            wp_update_nav_menu_item($menu, 0, $item + ['menu-item-status' => 'publish']);
        }

        return $menu;
    }

    beforeEach(function () {
        $this->draft = page('draft-page', 'draft');
        $this->menu = menu_with([
            ['menu-item-title' => 'Live', 'menu-item-type' => 'post_type', 'menu-item-object' => 'page', 'menu-item-object-id' => page('live-page')],
            ['menu-item-title' => 'Draft', 'menu-item-type' => 'post_type', 'menu-item-object' => 'page', 'menu-item-object-id' => $this->draft],
            ['menu-item-title' => 'Contact', 'menu-item-type' => 'custom', 'menu-item-url' => home_url('/#contact')],
        ]);
    });

    it('hides items for unpublished pages from visitors', function () {
        expect(wp_list_pluck(wp_get_nav_menu_items($this->menu), 'title'))->toBe(['Live', 'Contact']);
    });

    it('shows them once published', function () {
        wp_publish_post($this->draft);

        expect(wp_list_pluck(wp_get_nav_menu_items($this->menu), 'title'))->toBe(['Live', 'Draft', 'Contact']);
    });

    it('keeps them in the admin, where menus are edited', function () {
        $this->inAdmin();

        expect(wp_list_pluck(wp_get_nav_menu_items($this->menu), 'title'))->toBe(['Live', 'Draft', 'Contact']);
    });

    it('passes the menu in the primary location to templates', function () {
        set_theme_mod('nav_menu_locations', ['primary' => $this->menu]);

        $menu = theme_context()['menu'];

        expect($menu->id)->toBe($this->menu)
            ->and(wp_list_pluck($menu->items, 'title'))->toBe(['Live', 'Contact']);
    });

    it('offers a fallback menu', function () {
        remove_about_pages();
        $about = page('about');

        expect(theme_context()['fallback_menu'])->toBe([
            ['title' => 'Issues', 'url' => home_url('/issues/')],
            ['title' => 'About', 'url' => get_permalink($about)],
            ['title' => 'Contact', 'url' => home_url('/#contact')],
        ]);
    });

    it('leaves a draft About page out of the fallback menu', function () {
        remove_about_pages();
        page('about', 'draft');

        expect(wp_list_pluck(theme_context()['fallback_menu'], 'title'))->toBe(['Issues', 'Contact']);
    });
});

describe('nav template', function () {
    function render_nav(array $items): string
    {
        return Timber::compile('partials/site-header/nav.twig', ['menu' => ['items' => $items]]);
    }

    it('marks the current page', function () {
        $html = render_nav([['link' => '/about/', 'title' => 'About', 'current' => true, 'classes' => ['menu-item']]]);

        expect($html)->toContain('<a href="/about/" aria-current="page">About</a>');
    });

    it('never marks an anchor link as the current page', function () {
        // On the home page WordPress flags /#contact as current, because it ignores the #fragment.
        $html = render_nav([['link' => '/#contact', 'title' => 'Contact', 'current' => true, 'classes' => []]]);

        expect($html)->toContain('<a href="/#contact">Contact</a>')->not->toContain('aria-current');
    });

    it('falls back when there is no menu', function () {
        $html = Timber::compile('partials/site-header/nav.twig', ['menu' => null, 'fallback_menu' => [['title' => 'Issues', 'url' => '/issues/']]]);

        expect($html)->toContain('<li><a href="/issues/">Issues</a></li>');
    });
});

describe('svg()', function () {
    it('inlines an SVG from assets/img', function () {
        expect(Timber::compile_string("{{ svg('treat-logo') }}"))
            ->toBe(file_get_contents(theme_path('assets/img/treat-logo.svg')));
    });

    it('prints nothing for a missing file', function () {
        expect(Timber::compile_string("[{{ svg('missing') }}]"))->toBe('[]');
    });
});

describe('assets', function () {
    it('loads the compiled CSS, versioned by its modified time', function () {
        do_action('wp_enqueue_scripts');

        $style = wp_styles()->registered['treat'];
        expect($style->src)->toEndWith('assets/dist/main.css')
            ->and($style->ver)->toBe((string) filemtime(theme_path('assets/dist/main.css')))
            ->and($style->deps)->toBe(['treat-fonts']);
    });
});

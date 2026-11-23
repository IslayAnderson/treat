<?php

namespace Treat;

use Timber\Site as TimberSite;
use Timber\Timber;
use Twig\Environment;
use Twig\TwigFunction;

class Site extends TimberSite
{
    public function __construct()
    {
        add_action('after_setup_theme', [$this, 'setup']);
        add_action('wp_enqueue_scripts', [$this, 'assets']);
        add_filter('timber/context', [$this, 'context']);
        add_filter('timber/twig', [$this, 'twig']);
        add_filter('register_taxonomy_args', [$this, 'sortTags'], 10, 2);
        add_action('pre_get_posts', [$this, 'tagArchives']);
        add_filter('wp_get_nav_menu_items', [$this, 'hideUnpublishedMenuItems']);

        parent::__construct();
    }

    /**
     * Remember the order tags are added in, so the hero lists an issue's
     * tags as entered (query with 'orderby' => 'term_order').
     */
    public function sortTags(array $args, string $taxonomy): array
    {
        if ($taxonomy === 'post_tag') {
            $args['sort'] = true;
        }

        return $args;
    }

    /** Tags are shared by posts and issues, so tag archives list both. */
    public function tagArchives(\WP_Query $query): void
    {
        if (!is_admin() && $query->is_main_query() && $query->is_tag()) {
            $query->set('post_type', ['post', 'issue']);
        }
    }

    /**
     * Menu items for drafts, private or scheduled pages would be dead links for
     * visitors, so leave them out of menus on the front end until they're published.
     */
    public function hideUnpublishedMenuItems(array $items): array
    {
        if (is_admin()) {
            return $items;
        }

        return array_values(array_filter($items, fn($item) => $item->type !== 'post_type'
            || get_post_status((int)$item->object_id) === 'publish'));
    }

    public function setup(): void
    {
        add_theme_support('title-tag');
        add_theme_support('post-thumbnails');
        add_theme_support('html5', ['search-form', 'gallery', 'caption', 'style', 'script']);

        register_nav_menus([
            'primary' => __('Primary', 'treat'),
        ]);

        add_image_size('treat-hero', 2400, 2400);
        add_image_size('treat-card', 640, 900, true);
    }

    public function assets(): void
    {
        $version = wp_get_theme()->get('Version');

        wp_enqueue_style(
            'treat-fonts',
            'https://fonts.googleapis.com/css2?family=Inter+Tight:wght@400;500;700&family=Roboto+Mono:wght@300;400&display=swap',
            [],
            null
        );

        $css = get_theme_file_path('assets/dist/main.css');
        wp_enqueue_style(
            'treat',
            get_theme_file_uri('assets/dist/main.css'),
            ['treat-fonts'],
            file_exists($css) ? (string)filemtime($css) : $version
        );
    }

    public function context(array $context): array
    {
        $context['site'] = $this;
        $context['menu'] = Timber::get_menu('primary');

        // Used when no "primary" menu has been assigned yet. About only once it's published.
        $about = get_page_by_path('about');
        $context['fallback_menu'] = array_values(array_filter([
            ['title' => 'Issues', 'url' => get_post_type_archive_link('issue') ?: home_url('/issues/')],
            $about && $about->post_status === 'publish' ? ['title' => 'About', 'url' => get_permalink($about)] : null,
            ['title' => 'Contact', 'url' => home_url('/#contact')],
        ]));

        return $context;
    }

    public function twig(Environment $twig): Environment
    {
        // {{ svg('treat-logo') }} inlines assets/img/treat-logo.svg.
        $twig->addFunction(new TwigFunction('svg', function (string $name) {
            $file = get_theme_file_path("assets/img/{$name}.svg");

            return file_exists($file) ? file_get_contents($file) : '';
        }, ['is_safe' => ['html']]));


        // truncate by word
        $twig->addFunction(new TwigFunction('truncate_by_word', function (string $string, int $value) {
            $split_string = explode(' ', $string);
            return join(' ', array_slice($split_string, 0, $value < count($split_string) ? $value : count($split_string)));
        }));

        return $twig;
    }
}

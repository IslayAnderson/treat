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
        if (! is_admin() && $query->is_main_query() && $query->is_tag()) {
            $query->set('post_type', ['post', 'issue']);
        }
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
            file_exists($css) ? (string) filemtime($css) : $version
        );
    }

    public function context(array $context): array
    {
        $context['site'] = $this;
        $context['menu'] = Timber::get_menu('primary');

        // Used when no "primary" menu has been assigned yet.
        $about = get_page_by_path('about');
        $context['fallback_menu'] = [
            ['title' => 'Issues', 'url' => get_post_type_archive_link('issue') ?: home_url('/issues/')],
            ['title' => 'About', 'url' => $about ? get_permalink($about) : home_url('/about/')],
            ['title' => 'Contact', 'url' => home_url('/#contact')],
        ];

        return $context;
    }

    public function twig(Environment $twig): Environment
    {
        // {{ svg('treat-logo') }} inlines assets/img/treat-logo.svg.
        $twig->addFunction(new TwigFunction('svg', function (string $name) {
            $file = get_theme_file_path("assets/img/{$name}.svg");

            return file_exists($file) ? file_get_contents($file) : '';
        }, ['is_safe' => ['html']]));

        return $twig;
    }
}

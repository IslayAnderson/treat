<?php

use Timber\Timber;

$context = Timber::context();
$home = $context['post'];

// Hero: always the latest published, non-password-protected issue.
$hero_issue = Timber::get_posts([
    'post_type' => 'issue',
    'post_status' => 'publish',
    'has_password' => false,
    'posts_per_page' => 1,
    'orderby' => ['date' => 'DESC', 'ID' => 'DESC'],
    'no_found_rows' => true,
])->to_array()[0] ?? null;

// The issue's hero image, else the homepage's fallback image.
$hero_image_id = ($hero_issue ? (int) $hero_issue->meta('hero_image') : 0)
    ?: (int) $home->meta('hero_fallback_image');

$hero_title = $hero_issue ? trim(wp_strip_all_tags($hero_issue->title())) : '';
$hero_tags = $hero_issue ? $hero_issue->terms(['taxonomy' => 'post_tag', 'orderby' => 'term_order']) : [];

$context['hero'] = [
    'issue' => $hero_issue,
    'image' => $hero_image_id ? Timber::get_image($hero_image_id) : null,
    'tags' => $hero_tags,
    // The caption (title + tags) shows only when both are present.
    'show_caption' => $hero_title !== '' && ! empty($hero_tags),
    'logo_type' => $home->meta('logo_type'),
];

$context['issues'] = Timber::get_posts([
    'post_type' => 'issue',
    'posts_per_page' => 3,
    'orderby' => ['menu_order' => 'ASC', 'date' => 'ASC'],
]);

$context['posts'] = Timber::get_posts([
    'post_type' => 'post',
    'posts_per_page' => 3,
    'orderby' => ['menu_order' => 'ASC', 'date' => 'ASC'],
]);

Timber::render('templates/front-page/front-page.twig', $context);

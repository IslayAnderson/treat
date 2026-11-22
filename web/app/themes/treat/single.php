<?php

use Timber\Timber;

$context = Timber::context();
$entry = $context['post'];

if ($entry->post_type === 'issue') {
    $context['issue_posts'] = Timber::get_posts([
        'post_type' => 'post',
        'posts_per_page' => -1,
        'meta_query' => [['key' => 'related_issue', 'value' => $entry->ID]],
    ]);
}

$type = $entry->post_type;
Timber::render(["templates/single-{$type}/single-{$type}.twig", 'templates/single/single.twig'], $context);

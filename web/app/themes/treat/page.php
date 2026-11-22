<?php

use Timber\Timber;

$context = Timber::context();
$slug = $context['post']->post_name;
Timber::render(["templates/page-{$slug}/page-{$slug}.twig", 'templates/page/page.twig'], $context);

<?php

use Timber\Timber;

$context = Timber::context();

// Templates live in views/templates/{name}/{name}.twig; the first that exists wins.
$names = is_post_type_archive() ? ['archive-' . get_post_type(), 'archive'] : ['archive'];
$templates = array_map(fn ($name) => "templates/{$name}/{$name}.twig", $names);

Timber::render($templates, $context);

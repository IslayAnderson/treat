<?php

use Timber\Timber;

status_header(404);
Timber::render('templates/404/404.twig', Timber::context());

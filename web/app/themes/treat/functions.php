<?php

/**
 * TREAT theme bootstrap.
 *
 * Timber is installed with Composer at the Bedrock root, whose autoloader is
 * already loaded by web/wp-config.php.
 */

if (! class_exists(Timber\Timber::class)) {
    add_action('admin_notices', function () {
        echo '<div class="notice notice-error"><p>TREAT needs Timber. Run <code>composer install</code> in the project root.</p></div>';
    });

    return;
}

require_once __DIR__ . '/src/Site.php';
require_once __DIR__ . '/src/Contact.php';
require_once __DIR__ . '/src/ContactSettings.php';
require_once __DIR__ . '/src/ContactEntries.php';

Timber\Timber::init();
Timber\Timber::$dirname = ['views'];

new Treat\Site();
new Treat\Contact();
new Treat\ContactSettings();

if (is_admin()) {
    new Treat\ContactEntries();
}

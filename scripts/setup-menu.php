<?php
/**
 * Create and assign the Primary menu on an existing install (fresh installs get it
 * from the treat-install mu-plugin). Safe to re-run.
 *
 *   Local / with WP-CLI:  vendor/bin/wp eval-file scripts/setup-menu.php
 *   Server without CLI:   php scripts/setup-menu.php   (run as the site's user, from the project root)
 */

if (! defined('ABSPATH')) {
    define('WP_USE_THEMES', false);
    require __DIR__ . '/../web/wp/wp-load.php';
}

foreach (treat_setup_primary_menu() as $line) {
    echo $line, "\n";
}

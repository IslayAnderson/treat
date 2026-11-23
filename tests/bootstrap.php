<?php

/**
 * Loads WordPress against a separate test database (DB_NAME from phpunit.xml.dist,
 * treat_test by default), installing it on the first run. Each test runs inside a
 * transaction that is rolled back afterwards (see Tests\TestCase).
 */

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);
$db = getenv('DB_NAME');

// Tests write to the database: never let them near a real site's.
if (! $db || ! str_ends_with($db, '_test')) {
    fwrite(STDERR, "Refusing to run: DB_NAME must end in _test (got '{$db}'). Set it in phpunit.xml.dist or the environment.\n");
    exit(1);
}

$wp = escapeshellarg($root . '/vendor/bin/wp') . ' --path=' . escapeshellarg($root . '/web/wp');
exec("{$wp} core is-installed 2>&1", $out, $installed);
if ($installed !== 0) {
    $password = bin2hex(random_bytes(12)); // throwaway; nobody logs in to the test site
    exec("{$wp} core install --url=http://localhost:8080 --title=TREAT --admin_user=admin --admin_password={$password} --admin_email=admin@example.com --skip-email 2>&1", $out, $failed);
    if ($failed) {
        fwrite(STDERR, "Could not install WordPress into {$db}:\n" . implode("\n", $out) . "\n");
        exit(1);
    }
}

$_SERVER['HTTP_HOST'] = parse_url(getenv('WP_HOME') ?: 'http://localhost:8080', PHP_URL_HOST) ?: 'localhost';
$_SERVER['SERVER_NAME'] = $_SERVER['HTTP_HOST'];
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['REQUEST_METHOD'] = 'GET';

define('WP_USE_THEMES', false);
require $root . '/web/wp/wp-load.php';

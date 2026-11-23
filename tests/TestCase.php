<?php

namespace Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Runs each test in a database transaction and puts WordPress's hooks, request
 * globals and caches back the way they were afterwards.
 */
abstract class TestCase extends BaseTestCase
{
    private array $hooks = [];

    private array $server = [];

    protected function setUp(): void
    {
        parent::setUp();

        global $wpdb;
        $wpdb->query('SET autocommit = 0');
        $wpdb->query('START TRANSACTION');

        foreach (['wp_filter', 'wp_actions', 'wp_filters', 'wp_current_filter'] as $key) {
            $this->hooks[$key] = array_map(
                fn($hook) => is_object($hook) ? clone $hook : $hook,
                $GLOBALS[$key] ?? [],
            );
        }

        $this->server = $_SERVER;
        wp_cache_flush();
    }

    protected function tearDown(): void
    {
        global $wpdb;
        $wpdb->query('ROLLBACK');

        foreach ($this->hooks as $key => $value) {
            $GLOBALS[$key] = $value;
        }

        $_SERVER = $this->server;
        $_GET = $_POST = $_REQUEST = [];
        $GLOBALS['current_screen'] = null;
        wp_set_current_user(0);
        wp_cache_flush();
        $this->resetAcf();
        $GLOBALS['wp_rewrite']->init();

        parent::tearDown();
    }

    /** ACF keeps loaded values in memory, apart from the object cache. */
    private function resetAcf(): void
    {
        if (function_exists('acf_get_store')) {
            acf_get_store('values')->reset();
        }
    }

    /**
     * Run code that ends in wp_redirect() + exit, and return where it redirected to
     * (the exit is never reached).
     */
    public function redirectFrom(callable $callback): string
    {
        add_filter('wp_redirect', function (string $location) {
            throw new Redirected($location);
        }, PHP_INT_MAX);

        try {
            $callback();
        } catch (Redirected $redirect) {
            return $redirect->location;
        }

        $this->fail('Expected a redirect.');
    }

    /**
     * Stop wp_mail() sending and record each call. Returns the list, filled in as mail is "sent".
     *
     * @return \ArrayObject<int, array{to: string|string[], subject: string, message: string, headers: string|string[]}>
     */
    public function captureMail(bool $succeed = true): \ArrayObject
    {
        $sent = new \ArrayObject();

        add_filter('pre_wp_mail', function ($short, array $atts) use ($sent, $succeed) {
            $sent[] = $atts;

            return $succeed;
        }, 10, 2);

        return $sent;
    }

    /** Make is_admin() true, as on an admin screen. */
    public function inAdmin(string $screen = 'dashboard'): void
    {
        require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
        require_once ABSPATH . 'wp-admin/includes/screen.php';
        set_current_screen($screen);
    }
}

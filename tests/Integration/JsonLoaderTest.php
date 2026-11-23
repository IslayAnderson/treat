<?php

use function Treat\Json\dirs;
use function Treat\Json\load_definitions;
use function Treat\Json\missing_archive_rules;

/** The loader's init callback that flushes rewrite rules (priority 99). */
function json_flush_callback(): callable
{
    foreach ($GLOBALS['wp_filter']['init']->callbacks[99] ?? [] as $callback) {
        $fn = $callback['function'];
        if ($fn instanceof Closure && str_ends_with((new ReflectionFunction($fn))->getFileName(), 'treat-json.php')) {
            return $fn;
        }
    }

    throw new RuntimeException('treat-json flush callback not found');
}

function json_fixture_dir(array $files): string
{
    $dir = sys_get_temp_dir() . '/treat-json-' . uniqid();
    mkdir($dir);
    foreach ($files as $name => $contents) {
        file_put_contents("{$dir}/{$name}", $contents);
    }

    return $dir;
}

describe('load_definitions()', function () {
    it('keys definitions by their "slug", falling back to the filename', function () {
        $dir = json_fixture_dir([
            'book.json' => '{"label": "Books", "public": true}',
            'anything.json' => '{"slug": "film", "label": "Films"}',
        ]);

        expect(load_definitions($dir))->toBe([
            'film' => ['label' => 'Films'],
            'book' => ['label' => 'Books', 'public' => true],
        ]);
    });

    it('skips a file that is not valid JSON, with a warning', function () {
        $dir = json_fixture_dir(['bad.json' => '{"label": ', 'good.json' => '{"label": "Good"}']);

        $warnings = [];
        set_error_handler(function ($errno, $message) use (&$warnings) {
            $warnings[] = $message;

            return true;
        }, E_USER_WARNING);

        try {
            $definitions = load_definitions($dir);
        } finally {
            restore_error_handler();
        }

        expect($definitions)->toBe(['good' => ['label' => 'Good']])
            ->and($warnings)->toHaveCount(1)
            ->and($warnings[0])->toContain('bad.json');
    });

    it('returns nothing for a missing or empty directory', function () {
        expect(load_definitions('/nonexistent/' . uniqid()))->toBe([])
            ->and(load_definitions(json_fixture_dir([])))->toBe([]);
    });
});

describe('dirs()', function () {
    it('starts with the plugin folder and can be extended with a filter', function () {
        add_filter('treat/json/post-types_dirs', fn($dirs) => [...$dirs, '/extra']);

        $dirs = dirs('post-types');

        expect($dirs[0])->toEndWith('mu-plugins/treat-json/post-types')
            ->and(end($dirs))->toBe('/extra')
            ->and(dirs('taxonomies'))->not->toContain('/extra');
    });
});

describe('registered post types', function () {
    it('registers issues with an archive at /issues/ and shared tags', function () {
        $issue = get_post_type_object('issue');

        expect($issue)->not->toBeNull()
            ->and($issue->public)->toBeTrue()
            ->and($issue->has_archive)->toBe('issues')
            ->and(get_object_taxonomies('issue'))->toContain('post_tag')
            ->and(get_post_type_archive_link('issue'))->toBe(home_url('/issues/'));
    });

    it('keeps contact entries private and out of the REST API', function () {
        $entry = get_post_type_object('contact_entry');

        expect($entry->public)->toBeFalse()
            ->and($entry->show_in_rest)->toBeFalse()
            ->and($entry->publicly_queryable)->toBeFalse()
            ->and(get_post_types(['show_in_rest' => true]))->not->toContain('contact_entry')
            ->and(array_keys(rest_get_server()->get_routes()))->each->not->toContain('contact_entry');
    });

    it('does not let anyone add a contact entry by hand', function () {
        wp_set_current_user(1); // the installer's administrator

        expect(current_user_can('edit_posts'))->toBeTrue()
            ->and(current_user_can(get_post_type_object('contact_entry')->cap->create_posts))->toBeFalse();
    });
});

describe('rewrite rules', function () {
    beforeEach(fn() => flush_rewrite_rules(false));

    it('reports nothing missing once rules are flushed', function () {
        expect(missing_archive_rules())->toBeFalse();
    });

    it('notices when a post type archive has lost its rule', function () {
        $rules = get_option('rewrite_rules');
        unset($rules['issues/?$']);
        update_option('rewrite_rules', $rules);

        expect(missing_archive_rules())->toBeTrue();
    });

    it('ignores plain permalinks, which have no rules', function () {
        update_option('permalink_structure', '');
        update_option('rewrite_rules', ['unrelated' => 'index.php']);

        expect(missing_archive_rules())->toBeFalse();
    });

    it('rebuilds the rules on init when an archive rule is missing', function () {
        $rules = array_filter(get_option('rewrite_rules'), fn($query) => ! str_contains($query, 'post_type=issue'));
        update_option('rewrite_rules', $rules);

        json_flush_callback()();

        expect(get_option('rewrite_rules'))->toHaveKey('issues/?$');
    });

    it('rebuilds the rules on init when the JSON definitions change', function () {
        update_option('treat_json_hash', 'stale');
        update_option('rewrite_rules', ['unrelated' => 'index.php']);

        json_flush_callback()();

        expect(get_option('treat_json_hash'))->not->toBe('stale')
            ->and(get_option('rewrite_rules'))->toHaveKey('issues/?$');
    });
});

describe('ACF local JSON', function () {
    it('loads and saves field groups in the theme acf-json folder', function () {
        $folder = theme_path('acf-json');

        expect(apply_filters('acf/settings/save_json', '/elsewhere'))->toBe($folder)
            ->and(apply_filters('acf/settings/load_json', ['/elsewhere'])[0])->toBe($folder)
            ->and(acf_get_field_group('group_treat_issue'))->toBeArray();
    });
});

<?php
/**
 * Plugin Name: TREAT JSON Loader
 * Description: Registers custom post types and taxonomies from JSON files, and points ACF local JSON at the active theme.
 * Version: 1.0.0
 */

namespace Treat\Json;

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Read every *.json file in a directory and return [slug => args].
 * The slug comes from the "slug" key, falling back to the filename.
 */
function load_definitions(string $dir): array
{
    $definitions = [];

    foreach (glob(trailingslashit($dir) . '*.json') ?: [] as $file) {
        $data = json_decode((string) file_get_contents($file), true);

        if (! is_array($data)) {
            trigger_error(sprintf('TREAT JSON: could not parse %s (%s)', $file, json_last_error_msg()), E_USER_WARNING);
            continue;
        }

        $slug = $data['slug'] ?? basename($file, '.json');
        unset($data['slug']);
        $definitions[$slug] = $data;
    }

    return $definitions;
}

/**
 * Directories are filterable so the theme (or another plugin) can add its own.
 */
function dirs(string $kind): array
{
    $dirs = [__DIR__ . '/' . $kind];

    $theme_dir = get_stylesheet_directory() . '/json/' . $kind;
    if (is_dir($theme_dir)) {
        $dirs[] = $theme_dir;
    }

    return apply_filters("treat/json/{$kind}_dirs", $dirs);
}

add_action('init', function () {
    // Taxonomies first so post types can reference them.
    foreach (dirs('taxonomies') as $dir) {
        foreach (load_definitions($dir) as $slug => $args) {
            $object_types = $args['object_type'] ?? [];
            $terms = $args['terms'] ?? [];
            unset($args['object_type'], $args['terms']);
            register_taxonomy($slug, $object_types, $args);

            // Optional default terms, e.g. "terms": ["Story", "Interview"].
            if ($terms && is_admin()) {
                foreach ($terms as $term) {
                    if (! term_exists($term, $slug)) {
                        wp_insert_term($term, $slug);
                    }
                }
            }
        }
    }

    foreach (dirs('post-types') as $dir) {
        foreach (load_definitions($dir) as $slug => $args) {
            register_post_type($slug, $args);
        }
    }
}, 5);

/**
 * ACF local JSON: field groups live in {theme}/acf-json and are both loaded from
 * and saved to there, so changes made in the admin are written back to git.
 */
add_filter('acf/settings/save_json', fn () => get_stylesheet_directory() . '/acf-json');
add_filter('acf/settings/load_json', function (array $paths) {
    return array_merge([get_stylesheet_directory() . '/acf-json'], $paths);
});

/**
 * Whether a JSON post type's archive has no rewrite rule, e.g. because rules were
 * last flushed (by core or a plugin) before it was registered.
 */
function missing_archive_rules(): bool
{
    $rules = get_option('rewrite_rules');
    if (! get_option('permalink_structure') || ! is_array($rules)) {
        return false; // plain permalinks, or WordPress rebuilds them on the next request anyway
    }

    foreach (get_post_types(['_builtin' => false, 'has_archive' => true], 'objects') as $type) {
        $slug = $type->has_archive === true ? ($type->rewrite['slug'] ?? $type->name) : $type->has_archive;
        if (! isset($rules[trim($slug, '/') . '/?$'])) {
            return true;
        }
    }

    return false;
}

/**
 * Flush rewrite rules when the JSON definitions change (dev convenience), or when a
 * post type archive's rule has gone missing.
 */
add_action('init', function () {
    $hash = '';
    foreach (['taxonomies', 'post-types'] as $kind) {
        foreach (dirs($kind) as $dir) {
            foreach (glob(trailingslashit($dir) . '*.json') ?: [] as $file) {
                $hash .= md5_file($file);
            }
        }
    }
    $hash = md5($hash);

    if (get_option('treat_json_hash') !== $hash || missing_archive_rules()) {
        flush_rewrite_rules(false);
        update_option('treat_json_hash', $hash);
    }
}, 99);

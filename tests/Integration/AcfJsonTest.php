<?php

/*
| ACF quietly skips a field group whose JSON doesn't parse, so a stray missing
| comma makes a whole edit screen lose its fields. Check every file.
*/

$files = glob(dirname(__DIR__, 2) . '/web/app/themes/treat/acf-json/*.json');

it('is valid JSON', function (string $file) {
    json_decode(file_get_contents($file));

    expect(json_last_error_msg())->toBe('No error', basename($file) . ' is not valid JSON');
})->with(array_combine(array_map('basename', $files), $files));

it('is loaded by ACF', function (string $file) {
    $key = json_decode(file_get_contents($file), true)['key'] ?? null;

    expect(wp_list_pluck(acf_get_field_groups(), 'key'))->toContain($key);
})->with(array_combine(array_map('basename', $files), $files));

it('shows the Homepage fields on the front page', function () {
    $groups = acf_get_field_groups(['post_id' => (int) get_option('page_on_front'), 'post_type' => 'page']);

    expect(wp_list_pluck($groups, 'key'))->toContain('group_treat_home')
        ->and(wp_list_pluck(acf_get_fields('group_treat_home'), 'name'))->toContain('issues_count', 'posts_heading', 'posts_count');
});

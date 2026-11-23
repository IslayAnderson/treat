<?php

it('turns the block editor off for every post type', function (string $type) {
    expect(use_block_editor_for_post_type($type))->toBeFalse();
})->with(['post', 'page', 'issue']);

it('turns the block widget editor off', function () {
    expect(wp_use_widgets_block_editor())->toBeFalse();
});

it('does not load block styles on the front end', function () {
    do_action('wp_enqueue_scripts');

    foreach (['wp-block-library', 'wp-block-library-theme', 'classic-theme-styles', 'global-styles'] as $handle) {
        expect(wp_style_is($handle, 'enqueued'))->toBeFalse("{$handle} is enqueued");
    }

    expect(wp_style_is('treat', 'enqueued'))->toBeTrue();
});

<?php

use Treat\ContactSettings;

describe('get()', function () {
    it('falls back to defaults for settings never saved', function () {
        expect(ContactSettings::get('contact_heading'))->toBe('Stories / Interviews / Events')
            ->and(ContactSettings::get('contact_submit_label'))->toBe('Submit')
            ->and(ContactSettings::get('not_a_setting'))->toBeNull();
    });

    it('returns a saved value', function () {
        update_field('contact_heading', 'Write to us', 'option');

        expect(ContactSettings::get('contact_heading'))->toBe('Write to us');
    });

    it('falls back to the default when a saved value is empty', function () {
        update_field('contact_submit_label', '', 'option');

        expect(ContactSettings::get('contact_submit_label'))->toBe('Submit');
    });
});

describe('flag()', function () {
    it('defaults to saving entries and sending email', function () {
        expect(ContactSettings::flag('contact_save_entries'))->toBeTrue()
            ->and(ContactSettings::flag('contact_send_email'))->toBeTrue()
            ->and(ContactSettings::flag('not_a_setting'))->toBeFalse();
    });

    it('can be switched off', function () {
        update_field('contact_send_email', 0, 'option');

        expect(ContactSettings::flag('contact_send_email'))->toBeFalse();
    });
});

describe('recipients()', function () {
    it('sends to the site admin when none are set', function () {
        expect(ContactSettings::recipients())->toBe([get_option('admin_email')]);
    });

    it('accepts addresses separated by commas, semicolons, spaces or new lines', function () {
        update_field('contact_recipients', "a@example.com, b@example.com;c@example.com\nd@example.com e@example.com", 'option');

        expect(ContactSettings::recipients())->toBe(['a@example.com', 'b@example.com', 'c@example.com', 'd@example.com', 'e@example.com']);
    });

    it('drops invalid addresses', function () {
        update_field('contact_recipients', 'nope, editor@example.com, @example.com', 'option');

        expect(ContactSettings::recipients())->toBe(['editor@example.com']);
    });

    it('falls back to the admin when every address is invalid', function () {
        update_field('contact_recipients', 'nope, also nope', 'option');

        expect(ContactSettings::recipients())->toBe([get_option('admin_email')]);
    });
});

it('uses the field group from acf-json', function () {
    $names = wp_list_pluck(acf_get_fields(ContactSettings::GROUP), 'name');

    expect($names)->toContain('contact_save_entries', 'contact_send_email', 'contact_recipients', 'contact_subject', 'contact_heading');
});

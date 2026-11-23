<?php

use Treat\ContactEntries;

function contact_entry(array $meta = [], bool $read = false): WP_Post
{
    $id = wp_insert_post([
        'post_type' => 'contact_entry',
        'post_status' => 'publish',
        'post_title' => 'Ada Lovelace',
        'post_content' => 'A message about the next issue',
        'meta_input' => $meta + ['_treat_name' => 'Ada Lovelace', '_treat_email' => 'ada@example.com', '_treat_unread' => 1],
    ]);

    if ($read) {
        delete_post_meta($id, '_treat_unread');
    }

    return get_post($id);
}

function column_html(string $column, int $id): string
{
    ob_start();
    (new ContactEntries())->column($column, $id);

    return ob_get_clean();
}

beforeEach(function () {
    require_once ABSPATH . 'wp-admin/includes/template.php';
    wp_set_current_user(1);
});

it('lists messages with name, email, message, emailed and received columns', function () {
    expect((new ContactEntries())->columns(['cb' => '<input>', 'title' => 'Title', 'date' => 'Date']))->toBe([
        'cb' => '<input>',
        'treat_name' => 'Name',
        'treat_email' => 'Email',
        'treat_message' => 'Message',
        'treat_mail' => 'Emailed',
        'date' => 'Received',
    ]);
});

it('marks unread messages in the list', function () {
    $unread = contact_entry();
    $read = contact_entry(read: true);

    expect(column_html('treat_name', $unread->ID))->toContain('treat-unread')->toContain('<strong>Ada Lovelace</strong>')
        ->and(column_html('treat_name', $read->ID))->not->toContain('treat-unread');
});

it('shows email, a short message and whether it was emailed', function (string $mail, string $label) {
    $entry = contact_entry(['_treat_mail' => $mail]);

    expect(column_html('treat_email', $entry->ID))->toBe('<a href="mailto:ada@example.com">ada@example.com</a>')
        ->and(column_html('treat_message', $entry->ID))->toBe('A message about the next issue')
        ->and(column_html('treat_mail', $entry->ID))->toBe($label);
})->with([
    ['sent', 'Yes'],
    ['failed', 'Failed'],
    ['off', 'Off'],
    ['', '—'],
]);

it('only offers View and Trash on a message', function () {
    $actions = (new ContactEntries())->rowActions(
        ['edit' => 'Edit', 'inline hide-if-no-js' => 'Quick Edit', 'trash' => 'Trash', 'view' => 'Preview'],
        contact_entry(),
    );

    expect(array_keys($actions))->toBe(['view', 'trash'])
        ->and($actions['view'])->toContain('>View</a>');
});

it('leaves row actions on other post types alone', function () {
    $actions = ['edit' => 'Edit', 'trash' => 'Trash'];
    $post = get_post(wp_insert_post(['post_title' => 'Story', 'post_status' => 'publish']));

    expect((new ContactEntries())->rowActions($actions, $post))->toBe($actions);
});

it('marks a message read when it is opened', function () {
    $this->inAdmin('contact_entry');
    $entry = contact_entry();

    (new ContactEntries())->metaBoxes($entry);

    expect(get_post_meta($entry->ID, '_treat_unread', true))->toBe('')
        ->and($GLOBALS['wp_meta_boxes']['contact_entry']['normal']['high'])->toHaveKey('treat-message');
});

it('shows the message with a reply button', function () {
    $entry = contact_entry(['_treat_page' => home_url('/about/'), '_treat_mail' => 'sent']);
    wp_update_post(['ID' => $entry->ID, 'post_content' => "Line one\n<b>Line two</b>"]);

    ob_start();
    (new ContactEntries())->render(get_post($entry->ID));
    $html = ob_get_clean();

    expect($html)->toContain('mailto:ada@example.com?subject=Re%3A%20your%20message%20to%20TREAT')
        ->toContain('Line one<br />')
        ->toContain('&lt;b&gt;Line two&lt;/b&gt;')
        ->toContain(home_url('/about/'))
        ->toContain('<td>Yes</td>');
});

it('counts unread messages on the menu', function () {
    contact_entry();
    contact_entry();
    contact_entry(read: true);
    $GLOBALS['menu'] = [['Messages', 'edit_posts', 'edit.php?post_type=contact_entry'], ['Posts', 'edit_posts', 'edit.php']];

    (new ContactEntries())->unreadBubble();

    expect($GLOBALS['menu'][0][0])->toContain('<span class="pending-count">2</span>')
        ->and($GLOBALS['menu'][1][0])->toBe('Posts');
});

it('shows no count when everything is read', function () {
    contact_entry(read: true);
    $GLOBALS['menu'] = [['Messages', 'edit_posts', 'edit.php?post_type=contact_entry']];

    (new ContactEntries())->unreadBubble();

    expect($GLOBALS['menu'][0][0])->toBe('Messages');
});

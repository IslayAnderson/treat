<?php

namespace Treat;

use WP_Post;

/**
 * Admin UI for contact_entry posts (registered from treat-json/post-types/contact_entry.json):
 * list columns, a read-only message view, and an unread count on the menu.
 */
class ContactEntries
{
    private const TYPE = Contact::POST_TYPE;

    public function __construct()
    {
        add_filter('manage_' . self::TYPE . '_posts_columns', [$this, 'columns']);
        add_action('manage_' . self::TYPE . '_posts_custom_column', [$this, 'column'], 10, 2);
        add_filter('manage_edit-' . self::TYPE . '_sortable_columns', fn ($c) => $c + ['treat_name' => 'title']);
        add_filter('post_row_actions', [$this, 'rowActions'], 10, 2);
        add_action('add_meta_boxes_' . self::TYPE, [$this, 'metaBoxes']);
        add_action('admin_menu', [$this, 'unreadBubble'], 99);
        add_action('admin_head', [$this, 'styles']);
    }

    public function columns(array $columns): array
    {
        return [
            'cb' => $columns['cb'],
            'treat_name' => 'Name',
            'treat_email' => 'Email',
            'treat_message' => 'Message',
            'treat_mail' => 'Emailed',
            'date' => 'Received',
        ];
    }

    public function column(string $column, int $id): void
    {
        $unread = (bool) get_post_meta($id, '_treat_unread', true);

        switch ($column) {
            case 'treat_name':
                $name = get_post_meta($id, '_treat_name', true) ?: get_the_title($id);
                printf(
                    '<a class="row-title" href="%s">%s%s</a>',
                    esc_url(get_edit_post_link($id)),
                    $unread ? '<span class="treat-unread" title="Unread"></span>' : '',
                    $unread ? '<strong>' . esc_html($name) . '</strong>' : esc_html($name)
                );
                break;
            case 'treat_email':
                $email = get_post_meta($id, '_treat_email', true);
                printf('<a href="mailto:%1$s">%1$s</a>', esc_attr($email));
                break;
            case 'treat_message':
                echo esc_html(wp_trim_words(get_post_field('post_content', $id), 16));
                break;
            case 'treat_mail':
                echo esc_html(['sent' => 'Yes', 'failed' => 'Failed', 'off' => 'Off'][get_post_meta($id, '_treat_mail', true)] ?? '—');
                break;
        }
    }

    public function rowActions(array $actions, WP_Post $post): array
    {
        if ($post->post_type !== self::TYPE) {
            return $actions;
        }

        $view = sprintf('<a href="%s">View</a>', esc_url(get_edit_post_link($post->ID)));

        return ['view' => $view] + array_intersect_key($actions, array_flip(['trash', 'untrash', 'delete']));
    }

    public function metaBoxes(WP_Post $post): void
    {
        add_meta_box('treat-message', 'Message', [$this, 'render'], self::TYPE, 'normal', 'high');

        // Opening a message marks it read.
        delete_post_meta($post->ID, '_treat_unread');
    }

    public function render(WP_Post $post): void
    {
        $name = get_post_meta($post->ID, '_treat_name', true);
        $email = get_post_meta($post->ID, '_treat_email', true);
        $page = get_post_meta($post->ID, '_treat_page', true);
        $mail = get_post_meta($post->ID, '_treat_mail', true);

        $reply = add_query_arg('subject', rawurlencode('Re: your message to ' . get_bloginfo('name')), 'mailto:' . $email);
        ?>
        <table class="form-table treat-message" role="presentation">
            <tr><th>Name</th><td><?= esc_html($name) ?></td></tr>
            <tr><th>Email</th><td><a href="mailto:<?= esc_attr($email) ?>"><?= esc_html($email) ?></a></td></tr>
            <tr><th>Received</th><td><?= esc_html(get_the_date('j F Y, g:i a', $post)) ?></td></tr>
            <?php if ($page) : ?>
                <tr><th>Sent from</th><td><a href="<?= esc_url($page) ?>"><?= esc_html($page) ?></a></td></tr>
            <?php endif; ?>
            <tr><th>Emailed</th><td><?= esc_html(['sent' => 'Yes', 'failed' => 'Failed to send', 'off' => 'Email off'][$mail] ?? '—') ?></td></tr>
            <tr><th>Message</th><td><div class="treat-message__body"><?= nl2br(esc_html($post->post_content)) ?></div></td></tr>
        </table>
        <p><a class="button button-primary" href="<?= esc_url($reply) ?>">Reply by email</a></p>
        <?php
    }

    public function unreadBubble(): void
    {
        global $menu;

        $count = (int) (new \WP_Query([
            'post_type' => self::TYPE,
            'post_status' => 'publish',
            'meta_key' => '_treat_unread',
            'fields' => 'ids',
            'posts_per_page' => 1,
        ]))->found_posts;

        if (! $count) {
            return;
        }

        foreach ($menu as &$item) {
            if (($item[2] ?? '') === 'edit.php?post_type=' . self::TYPE) {
                $item[0] .= sprintf(' <span class="awaiting-mod"><span class="pending-count">%d</span></span>', $count);
                break;
            }
        }
    }

    public function styles(): void
    {
        $screen = get_current_screen();
        if (! $screen || $screen->post_type !== self::TYPE) {
            return;
        }
        ?>
        <style>
            .treat-unread { display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #d63638; margin-right: 6px; vertical-align: middle; }
            .treat-message th { width: 8em; padding-top: 12px; padding-bottom: 12px; }
            .treat-message td { padding-top: 12px; padding-bottom: 12px; }
            .treat-message__body { white-space: normal; max-width: 60em; font-size: 14px; line-height: 1.6; }
            .post-type-contact_entry #minor-publishing,
            .post-type-contact_entry #publishing-action,
            .post-type-contact_entry .page-title-action { display: none; }
            .column-treat_email { width: 18%; } .column-treat_mail { width: 7%; } .column-treat_name { width: 18%; }
        </style>
        <?php
    }
}

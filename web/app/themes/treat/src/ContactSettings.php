<?php

namespace Treat;

/**
 * Messages → Settings: configures the contact form.
 *
 * Fields live in acf-json/group_treat_contact_settings.json and are stored as
 * ACF "option" values. With ACF PRO this is a native options page; on ACF free
 * the same field group is rendered with acf_form() on a plain admin page.
 */
class ContactSettings
{
    public const SLUG = 'treat-contact-settings';
    public const GROUP = 'group_treat_contact_settings';
    public const PARENT = 'edit.php?post_type=contact_entry';
    public const CAPABILITY = 'manage_options';

    /** Fallbacks for options that have never been saved. */
    private const DEFAULTS = [
        'contact_save_entries' => true,
        'contact_send_email' => true,
        'contact_recipients' => '',
        'contact_subject' => '[{site}] Message from {name}',
        'contact_heading' => 'Stories / Interviews / Events',
        'contact_intro' => '',
        'contact_name_label' => 'Name',
        'contact_email_label' => 'Email',
        'contact_message_label' => 'Message',
        'contact_submit_label' => 'Submit',
        'contact_thanks' => "Thanks — we'll be in touch.",
        'contact_invalid' => 'Please add your name, a valid email and a message.',
        'contact_failed' => "Sorry, that didn't send. Please try again.",
    ];

    public function __construct()
    {
        add_action('acf/init', [$this, 'register']);
    }

    public static function get(string $name): mixed
    {
        $value = function_exists('get_field') ? get_field($name, 'option') : null;

        return ($value === null || $value === '') ? (self::DEFAULTS[$name] ?? null) : $value;
    }

    public static function flag(string $name): bool
    {
        // true_false fields come back as bool once saved; null when never saved.
        $value = function_exists('get_field') ? get_field($name, 'option') : null;

        return $value === null ? (bool) (self::DEFAULTS[$name] ?? false) : (bool) $value;
    }

    /** @return string[] */
    public static function recipients(): array
    {
        $lines = preg_split('/[\s,;]+/', (string) self::get('contact_recipients'), -1, PREG_SPLIT_NO_EMPTY);
        $emails = array_values(array_filter(array_map('sanitize_email', $lines), 'is_email'));

        return $emails ?: [get_option('admin_email')];
    }

    public function register(): void
    {
        $args = [
            'page_title' => 'Contact form settings',
            'menu_title' => 'Settings',
            'menu_slug' => self::SLUG,
            'parent_slug' => self::PARENT,
            'capability' => self::CAPABILITY,
            'update_button' => 'Save settings',
            'updated_message' => 'Settings saved.',
        ];

        if (function_exists('acf_add_options_sub_page')) {
            acf_add_options_sub_page($args);

            return;
        }

        // ACF free fallback.
        add_action('admin_menu', function () use ($args) {
            $hook = add_submenu_page(
                $args['parent_slug'],
                $args['page_title'],
                $args['menu_title'],
                $args['capability'],
                $args['menu_slug'],
                fn () => $this->render($args)
            );

            // Saves the form and redirects before any output.
            add_action("load-{$hook}", 'acf_form_head');
        });
    }

    private function render(array $args): void
    {
        echo '<div class="wrap"><h1>' . esc_html($args['page_title']) . '</h1>';

        if (! empty($_GET['updated'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($args['updated_message']) . '</p></div>';
        }

        acf_form([
            'id' => 'treat-contact-settings',
            'post_id' => 'options',
            'field_groups' => [self::GROUP],
            'form' => true,
            'submit_value' => $args['update_button'],
            'html_submit_button' => '<input type="submit" class="button button-primary button-large" value="%s" />',
            'updated_message' => false,
            // Raw URL: menu_page_url() is HTML-escaped (&#038;) and breaks the redirect.
            'return' => add_query_arg(['page' => self::SLUG, 'updated' => 'true'], admin_url(self::PARENT)),
        ]);

        echo '</div>';
    }
}

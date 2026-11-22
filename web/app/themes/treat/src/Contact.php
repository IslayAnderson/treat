<?php

namespace Treat;

/**
 * Contact form: posts to admin-post.php, saves a contact_entry and/or emails it
 * (per Messages → Settings), then redirects back to #contact with
 * ?contact=sent|invalid|failed|expired.
 */
class Contact
{
    public const ACTION = 'treat_contact';
    public const POST_TYPE = 'contact_entry';

    public function __construct()
    {
        add_action('admin_post_nopriv_' . self::ACTION, [$this, 'handle']);
        add_action('admin_post_' . self::ACTION, [$this, 'handle']);
        add_filter('timber/context', [$this, 'context']);
    }

    public function context(array $context): array
    {
        $context['contact'] = [
            'action' => admin_url('admin-post.php'),
            'hidden' => self::ACTION,
            'nonce' => wp_create_nonce(self::ACTION),
            'status' => sanitize_key($_GET['contact'] ?? ''),
            'heading' => ContactSettings::get('contact_heading'),
            'intro' => ContactSettings::get('contact_intro'),
            'labels' => [
                'name' => ContactSettings::get('contact_name_label'),
                'email' => ContactSettings::get('contact_email_label'),
                'message' => ContactSettings::get('contact_message_label'),
                'submit' => ContactSettings::get('contact_submit_label'),
            ],
            'messages' => [
                'sent' => ContactSettings::get('contact_thanks'),
                'invalid' => ContactSettings::get('contact_invalid'),
                'failed' => ContactSettings::get('contact_failed'),
            ],
        ];

        return $context;
    }

    public function handle(): void
    {
        $back = wp_get_referer() ?: home_url('/');
        $back = remove_query_arg('contact', strtok($back, '#'));

        $redirect = function (string $status) use ($back) {
            wp_safe_redirect(add_query_arg('contact', $status, $back) . '#contact');
            exit;
        };

        if (! wp_verify_nonce($_POST['_treat_nonce'] ?? '', self::ACTION)) {
            $redirect('expired');
        }

        // Honeypot: bots fill every field. Pretend it worked.
        if (! empty($_POST['website'])) {
            $redirect('sent');
        }

        $name = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
        $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
        $message = sanitize_textarea_field(wp_unslash($_POST['message'] ?? ''));

        if ($name === '' || ! is_email($email) || $message === '') {
            $redirect('invalid');
        }

        $entry_id = ContactSettings::flag('contact_save_entries')
            ? $this->save($name, $email, $message, $back)
            : 0;

        $mail = 'off';
        if (ContactSettings::flag('contact_send_email')) {
            $mail = $this->send($name, $email, $message) ? 'sent' : 'failed';
        }

        if ($entry_id) {
            update_post_meta($entry_id, '_treat_mail', $mail);
        }

        // A saved entry counts as received even if the email failed.
        $redirect($entry_id || $mail !== 'failed' ? 'sent' : 'failed');
    }

    private function save(string $name, string $email, string $message, string $page): int
    {
        $id = wp_insert_post(wp_slash([
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'post_title' => $name,
            'post_content' => $message,
            'meta_input' => [
                '_treat_name' => $name,
                '_treat_email' => $email,
                '_treat_page' => esc_url_raw($page),
                '_treat_unread' => 1,
            ],
        ]), true);

        return is_wp_error($id) ? 0 : (int) $id;
    }

    private function send(string $name, string $email, string $message): bool
    {
        $subject = strtr((string) ContactSettings::get('contact_subject'), [
            '{site}' => wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES),
            '{name}' => $name,
            '{email}' => $email,
        ]);

        return wp_mail(
            ContactSettings::recipients(),
            $subject,
            "Name: {$name}\nEmail: {$email}\n\n{$message}",
            ['Reply-To: ' . $name . ' <' . $email . '>']
        );
    }
}

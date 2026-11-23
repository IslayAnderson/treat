<?php

use Treat\Contact;

/** Submit the contact form from $page and return where the visitor is sent back to. */
function submit_contact(object $test, array $fields = [], string $page = '/about/'): string
{
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['REQUEST_URI'] = '/wp/wp-admin/admin-post.php';
    $_SERVER['HTTP_REFERER'] = home_url($page);
    $_POST = $fields + [
        '_treat_nonce' => wp_create_nonce(Contact::ACTION),
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'message' => "Hello\nthere",
        'website' => '',
    ];

    return $test->redirectFrom(fn() => (new Contact())->handle());
}

function contact_entries(): array
{
    return get_posts(['post_type' => Contact::POST_TYPE, 'post_status' => 'any', 'numberposts' => -1]);
}

describe('handling a submission', function () {
    it('saves the message, emails it and says thanks', function () {
        $mail = $this->captureMail();

        $location = submit_contact($this);

        expect($location)->toBe(home_url('/about/?contact=sent#contact'))
            ->and(contact_entries())->toHaveCount(1);

        $entry = contact_entries()[0];
        expect($entry->post_title)->toBe('Ada Lovelace')
            ->and($entry->post_content)->toBe("Hello\nthere")
            ->and($entry->post_status)->toBe('publish')
            ->and(get_post_meta($entry->ID, '_treat_email', true))->toBe('ada@example.com')
            ->and(get_post_meta($entry->ID, '_treat_page', true))->toBe(home_url('/about/'))
            ->and(get_post_meta($entry->ID, '_treat_unread', true))->toBe('1')
            ->and(get_post_meta($entry->ID, '_treat_mail', true))->toBe('sent');

        expect($mail)->toHaveCount(1)
            ->and($mail[0]['to'])->toBe([get_option('admin_email')])
            ->and($mail[0]['subject'])->toBe('[TREAT] Message from Ada Lovelace')
            ->and($mail[0]['message'])->toBe("Name: Ada Lovelace\nEmail: ada@example.com\n\nHello\nthere")
            ->and($mail[0]['headers'])->toBe(['Reply-To: Ada Lovelace <ada@example.com>']);
    });

    it('fills in the subject placeholders and sends to every recipient', function () {
        update_field('contact_subject', '{name} <{email}> wrote to {site}', 'option');
        update_field('contact_recipients', 'one@example.com, two@example.com', 'option');
        $mail = $this->captureMail();

        submit_contact($this);

        expect($mail[0]['subject'])->toBe('Ada Lovelace <ada@example.com> wrote to TREAT')
            ->and($mail[0]['to'])->toBe(['one@example.com', 'two@example.com']);
    });

    it('returns to the page it was sent from, dropping an old status', function () {
        $this->captureMail();

        expect(submit_contact($this, [], '/issues/?contact=invalid&page=2#contact'))
            ->toBe(home_url('/issues/?page=2&contact=sent#contact'));
    });

    it('rejects an expired form', function () {
        $mail = $this->captureMail();

        expect(submit_contact($this, ['_treat_nonce' => 'stale']))->toEndWith('contact=expired#contact')
            ->and(contact_entries())->toBeEmpty()
            ->and($mail)->toBeEmpty();
    });

    it('pretends to accept spam that fills in the hidden field', function () {
        $mail = $this->captureMail();

        expect(submit_contact($this, ['website' => 'https://spam.example']))->toEndWith('contact=sent#contact')
            ->and(contact_entries())->toBeEmpty()
            ->and($mail)->toBeEmpty();
    });

    it('asks again when a field is missing or the email is invalid', function (array $fields) {
        $mail = $this->captureMail();

        expect(submit_contact($this, $fields))->toEndWith('contact=invalid#contact')
            ->and(contact_entries())->toBeEmpty()
            ->and($mail)->toBeEmpty();
    })->with([
        'no name' => [['name' => '  ']],
        'bad email' => [['email' => 'not-an-email']],
        'no message' => [['message' => '']],
    ]);

    it('strips tags from what is saved', function () {
        $this->captureMail();

        submit_contact($this, ['name' => '<b>Ada</b>', 'message' => '<script>x()</script>Hi']);

        expect(contact_entries()[0]->post_title)->toBe('Ada')
            ->and(contact_entries()[0]->post_content)->toBe('Hi');
    });
});

describe('settings', function () {
    it('only emails when saving is off', function () {
        update_field('contact_save_entries', 0, 'option');
        $mail = $this->captureMail();

        expect(submit_contact($this))->toEndWith('contact=sent#contact')
            ->and(contact_entries())->toBeEmpty()
            ->and($mail)->toHaveCount(1);
    });

    it('only saves when email is off', function () {
        update_field('contact_send_email', 0, 'option');
        $mail = $this->captureMail();

        expect(submit_contact($this))->toEndWith('contact=sent#contact')
            ->and($mail)->toBeEmpty()
            ->and(get_post_meta(contact_entries()[0]->ID, '_treat_mail', true))->toBe('off');
    });

    it('still counts a saved message as received when the email fails', function () {
        $this->captureMail(succeed: false);

        expect(submit_contact($this))->toEndWith('contact=sent#contact')
            ->and(get_post_meta(contact_entries()[0]->ID, '_treat_mail', true))->toBe('failed');
    });

    it('reports a failure when the email fails and nothing was saved', function () {
        update_field('contact_save_entries', 0, 'option');
        $this->captureMail(succeed: false);

        expect(submit_contact($this))->toEndWith('contact=failed#contact');
    });
});

describe('form context', function () {
    it('gives the form what it needs to post back', function () {
        $_GET['contact'] = 'Sent<script>';

        $contact = (new Contact())->context([])['contact'];

        expect($contact['action'])->toBe(admin_url('admin-post.php'))
            ->and($contact['hidden'])->toBe(Contact::ACTION)
            ->and(wp_verify_nonce($contact['nonce'], Contact::ACTION))->toBeTruthy()
            ->and($contact['status'])->toBe('sentscript')
            ->and($contact['heading'])->toBe('Stories / Interviews / Events')
            ->and($contact['labels'])->toBe(['name' => 'Name', 'email' => 'Email', 'message' => 'Message', 'submit' => 'Submit'])
            ->and($contact['messages'])->toHaveKeys(['sent', 'invalid', 'failed']);
    });

    it('handles the form for visitors and logged-in users', function () {
        new Contact();

        expect(has_action('admin_post_nopriv_' . Contact::ACTION))->toBeTrue()
            ->and(has_action('admin_post_' . Contact::ACTION))->toBeTrue();
    });
});

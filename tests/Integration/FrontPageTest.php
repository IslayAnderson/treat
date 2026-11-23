<?php

/** Render the front page as a visitor would get it. */
function render_front_page(): string
{
    $GLOBALS['wp_the_query']->query(['page_id' => (int) get_option('page_on_front')]);
    $GLOBALS['wp_query'] = $GLOBALS['wp_the_query'];
    $GLOBALS['post'] = $GLOBALS['wp_query']->post;

    ob_start();
    include get_stylesheet_directory() . '/front-page.php';

    return ob_get_clean();
}

function story(string $title, string $date): int
{
    return wp_insert_post(['post_title' => $title, 'post_status' => 'publish', 'post_date' => $date, 'post_content' => 'Body']);
}

beforeEach(function () {
    foreach (get_posts(['post_type' => ['post', 'issue'], 'numberposts' => -1, 'post_status' => 'any']) as $old) {
        wp_delete_post($old->ID, true);
    }
});

it('lists the newest posts with their titles and links', function () {
    story('Oldest', '2026-01-01 10:00:00');
    $newest = story('Newest', '2026-03-01 10:00:00');
    story('Middle', '2026-02-01 10:00:00');

    $html = render_front_page();

    expect($html)->toContain('id="posts"')
        ->toContain('<a href="' . get_permalink($newest) . '">Newest</a>')
        ->and(strpos($html, '>Newest<'))->toBeLessThan(strpos($html, '>Middle<'))
        ->and(strpos($html, '>Middle<'))->toBeLessThan(strpos($html, '>Oldest<'));
});

it('shows as many posts as the Number of posts field says', function () {
    foreach (range(1, 5) as $n) {
        story("Story {$n}", "2026-01-0{$n} 10:00:00");
    }
    update_field('posts_count', 2, (int) get_option('page_on_front'));

    expect(substr_count(render_front_page(), 'class="post-card"'))->toBe(2);
});

it('shows as many issues as the Number of issues field says', function () {
    foreach (range(1, 4) as $n) {
        wp_insert_post(['post_type' => 'issue', 'post_status' => 'publish', 'post_title' => "Issue {$n}"]);
    }
    update_field('issues_count', 2, (int) get_option('page_on_front'));

    $html = render_front_page();
    preg_match('/<section class="section issues" id="issues">.*?<\/section>/s', $html, $section);

    expect(substr_count($section[0] ?? '', '<a class="issue-card'))->toBe(2);
});

it('leaves the posts section out when there are no posts', function () {
    expect(render_front_page())->not->toContain('id="posts"');
});

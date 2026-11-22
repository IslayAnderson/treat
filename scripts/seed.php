<?php
/**
 * Local demo content. Run: vendor/bin/wp eval-file scripts/seed.php [path/to/cover.png]
 * Safe to re-run: skips anything that already exists.
 */

$lorem = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.';

$ensure = function (string $type, string $title, array $extra = []) {
    $existing = get_posts(['post_type' => $type, 'title' => $title, 'post_status' => 'any', 'fields' => 'ids', 'numberposts' => 1]);
    if ($existing) {
        return $existing[0];
    }
    return wp_insert_post(['post_type' => $type, 'post_title' => $title, 'post_status' => 'publish'] + $extra);
};

// Cover image
$cover_id = 0;
$cover = $args[0] ?? '';
if ($cover && file_exists($cover)) {
    $cover_id = (int) (get_posts(['post_type' => 'attachment', 'name' => 'issue-one-cover', 'post_status' => 'inherit', 'fields' => 'ids'])[0] ?? 0);
    if (! $cover_id) {
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        $tmp = wp_tempnam($cover);
        copy($cover, $tmp);
        $cover_id = media_handle_sideload(['name' => 'issue-one-cover.png', 'tmp_name' => $tmp], 0, 'Issue One cover');
        if (is_wp_error($cover_id)) {
            WP_CLI::warning($cover_id->get_error_message());
            $cover_id = 0;
        }
    }
}

// Issues
$issues = [];
foreach (['Issue One', 'Issue Two', 'Issue Three'] as $i => $title) {
    // A month apart, oldest first, so "latest issue" is Issue Three.
    $date = gmdate('Y-m-d H:i:s', strtotime(sprintf('-%d months', 2 - $i)));
    $id = $ensure('issue', $title, ['menu_order' => $i + 1, 'post_content' => $lorem]);
    wp_update_post(['ID' => $id, 'post_date' => get_date_from_gmt($date), 'post_date_gmt' => $date]);
    update_field('issue_number', $i + 1, $id);
    $issues[] = $id;
}
if ($cover_id) {
    update_field('cover_image', $cover_id, $issues[0]);
    update_field('cover_caption', 'Whitehall, London', $issues[0]);
    foreach ($issues as $issue_id) {
        update_field('hero_image', $cover_id, $issue_id); // demo: same photo for every issue
    }
}
wp_set_object_terms($issues[0], ['Civic Responsibility', 'Bob Vylan', 'Go Charlotte', 'Poem Title', 'Lorem Ipsum', 'Dolor Sit', 'Amet Consectetur', 'Adipiscing Elit'], 'post_tag');

// Posts, one per category
foreach (['Story' => 'A first story', 'Interview' => 'In conversation', 'Event' => 'Issue One launch'] as $category => $title) {
    $id = $ensure('post', $title, ['post_content' => $lorem, 'post_excerpt' => substr($lorem, 0, 160)]);
    $term = term_exists($category, 'category') ?: wp_insert_term($category, 'category');
    wp_set_post_categories($id, [(int) $term['term_id']]);
    update_field('related_issue', $issues[0], $id);
}

// Pages
$home = $ensure('page', 'Home');
$ensure('page', 'About', ['post_content' => $lorem]);
update_option('show_on_front', 'page');
update_option('page_on_front', $home);

if ($cover_id && ! get_field('hero_fallback_image', $home)) {
    update_field('hero_fallback_image', $cover_id, $home);
}
update_field('forward_heading', 'Forward', $home);
update_field('forward_text', $lorem, $home);
update_field('issues_heading', 'Issues', $home);
update_field('issues_count', 3, $home);

// Contact form section
if (! get_field('contact_intro', 'option')) {
    update_field('contact_heading', 'Stories / Interviews / Events', 'option');
    update_field('contact_intro', $lorem, 'option');
}

WP_CLI::success('Seeded TREAT demo content.');

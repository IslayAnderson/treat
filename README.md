# TREAT

WordPress site for TREAT magazine, built with Bedrock (Composer), Timber 2 (Twig) and ACF.

## Layout

| Path | What |
| --- | --- |
| `web/app/mu-plugins/treat-json/` | Loads post types (and taxonomies, if you add any) from JSON and points ACF local JSON at the theme |
| `…/treat-json/post-types/*.json` | `register_post_type()` args (`issue`, `contact_entry`) |
| `…/treat-json/taxonomies/*.json` | `register_taxonomy()` args plus `object_type` and optional default `terms` (none at the moment) |
| `web/app/themes/treat/acf-json/` | ACF field groups (Homepage, Issue details, Post details, Contact form). ACF saves edits made in the admin back here |
| `web/app/themes/treat/views/` | Twig, one folder per piece, each holding its own Sass (see below) |
| `web/app/themes/treat/assets/scss/` | Shared Sass: tokens and mixins (`abstracts/`), base, the `.section` layout, the button placeholder, and `main.scss`, which pulls everything together |
| `web/app/themes/treat/assets/dist/` | Compiled CSS that WordPress loads |
| `scripts/seed.php` | Local demo content |

A theme can add more JSON definitions in `{theme}/json/post-types` and `{theme}/json/taxonomies`. Rewrite rules flush automatically when any JSON definition changes.

## Local setup

```bash
composer install
cp .env.example .env   # set DB_* and WP_HOME, generate salts
vendor/bin/wp core install --url=http://localhost:8080 --title=TREAT --admin_user=admin --admin_email=you@example.com
vendor/bin/wp theme activate treat
vendor/bin/wp plugin activate advanced-custom-fields
vendor/bin/wp rewrite structure '/%postname%/'
vendor/bin/wp eval-file scripts/seed.php path/to/cover.png   # optional demo content
vendor/bin/wp server --host=localhost --port=8080
```

### Views

```
views/
  layouts/base/            base.twig + _base.scss
  templates/<name>/        <name>.twig (+ _<name>.scss if it has styles)
  partials/<name>/         <name>.twig + _<name>.scss
```

PHP controllers render `templates/<name>/<name>.twig`: `single-<post type>` falls back to `single`, `archive-<post type>` to `archive`, and `page-<slug>` to `page`. A new component gets a folder with its Twig and `_<name>.scss`, plus one `@use` line in `assets/scss/main.scss`. Its Sass can `@use "abstracts" as *;` because both `assets/scss` and `views` are on the Sass load path.

### Styles

```bash
cd web/app/themes/treat
npm install
npm run dev     # watch + source maps
npm run build   # compressed, for deploy
npm run texture # re-render the paper background (needs cwebp: brew install webp)
```

The page background is `assets/img/paper.webp`, a 2× tile rendered from `assets/img/paper.svg` (noise filters) onto the `paper` colour in `abstracts/_tokens.scss`. Run `npm run texture` after changing either. It's an image rather than the SVG itself because Safari and Firefox don't reliably render SVG noise filters as CSS backgrounds.

The compiled `assets/dist/main.css` is what WordPress loads, so rebuild before deploying (or commit the build).

## Content model

- **Issue** (`/issues/`): issue number, cover image + caption, front page hero image, forward, PDF, publication date, tags. Issues and posts share the built-in Tags, so tag archives list both. Tags remember the order they were added in.
- **Posts** (built-in): stories, interviews and events, as categories. Extra fields: linked issue, byline, event date and location. An issue page lists the posts linked to it.
- **Homepage** (the static front page): fallback hero image, Forward text, number of issues. The hero always uses the latest published issue (newest publish date, skipping drafts and password-protected issues). The image is that issue's front page hero image, else the homepage's fallback hero image. The caption (issue title + tags) shows only when the issue has both a title and at least one tag.

### Contact form

The form posts to `admin-post.php?action=treat_contact`. **Messages → Settings** controls it:

- **Delivery:** whether to save messages and/or email them, the recipients (one address per line, default the admin email), and the subject (`{site}`, `{name}`, `{email}`).
- **Form:** the section heading and intro above the form, placeholder text and button label.
- **Responses:** the sent, invalid and failed messages.

Settings are ACF `option` values from `acf-json/group_treat_contact_settings.json`. With ACF PRO that's a native options page. On ACF free (installed now), `src/ContactSettings.php` renders the same field group with `acf_form()`, so moving to PRO needs no changes.

Saved messages are `contact_entry` posts under **Messages**. They're private: no front end, not in search, `show_in_rest: false`, and they can't be added by hand. Opening one shows it read-only, with a reply button, and marks it read; unread messages get a dot and a count on the menu. A message counts as received if it was saved, even if the email failed. Email needs working mail on the server (an SMTP plugin or similar).

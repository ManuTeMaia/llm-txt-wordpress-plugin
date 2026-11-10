# Security review, LLMs.txt 1.2.2

Review of 1.2.0 as shipped, then the fixes in 1.2.1. The settings option name and the shape of saved sections, titles, and language fields are unchanged, so replacing the plugin in place keeps existing settings.

## High

### Public requests rebuilt the files

A cache miss on `/llms.txt` or `/llms-full.txt` rendered every included Elementor document in the request. Anyone could force that work, including with a cache-busting query string after the transient expired.

**Fix:** Public requests never render. They read a transient that does not expire, then a non-autoloaded option, then the file under `uploads/llms-txt/`. If only a durable copy remains, that copy is served and one rebuild is queued with `wp_schedule_single_event`. A 503 is returned only when no copy has ever been stored. Regeneration runs from that event, the daily cron, admin actions, WP-CLI, and content hooks. A lock allows one rebuild at a time, and a 20 second budget stops a rebuild that runs too long. The previous copy is not deleted before the new one is written.

### Update manifest could fetch arbitrary URLs and install a zip from another host

The manifest URL was requested with `wp_remote_get`, which follows redirects and does not reject private addresses. The manifest could point `download_url` at any host. An update check can run from WP-Cron, which an anonymous visitor can trigger.

**Fix:** The check stays off until a manifest URL is saved. The URL must be `https`, is fetched with `wp_safe_remote_get` and no redirects, the zip must be on the same host, and a `sha256` field is required. The package is downloaded again at install time and rejected if the checksum does not match. The admin still has to choose the URL and confirm the update. A compromised host that serves both the manifest and the zip can still ship a bad package; that is the trust placed in the host they configure. There is no pinned signing key.

## Medium

### Query parameters could select the file

`llms_txt` and `llms_lang` were public query variables, so `/?llms_txt=full&llms_lang=ru` could change which document was served.

**Fix:** Only the URL path is consulted. `/llms.txt?llms_lang=ru&x=1` is the root index. The language directory in that path is the language switch. The home path subtracted from it is the stored site URL, not `home_url()`, because WPML and Polylang prefix `home_url()` with the current language. The request URI is captured when this plugin loads, before that directory can be removed. If the captured path has no language directory and WPML or Polylang's current language is a different known language, that language selects the cached file. A query parameter still cannot.

### REST meta changes were not tied to the post

The exclude flag and “Title in llms.txt” used `edit_posts`, so any user who can edit some posts could change those fields on posts they do not own.

**Fix:** The REST auth callback requires `edit_post` for that post ID. The classic meta box already checked `edit_post` and a nonce. The title is passed through `sanitize_text_field` and tag stripping, and the admin field is escaped.

### Capability filter could be lowered

`llms_txt_capability` could be set to `read`, which would let a subscriber open the settings actions if another callback did that.

**Fix:** The filter may only select `manage_options` or `manage_network_options`. Every admin-post action still checks that capability and a nonce. There is no unauthenticated `admin_post_nopriv` handler and no REST route for settings. Editors, authors, contributors, and subscribers do not have `manage_options`. On a multisite install the settings screen is per site and uses that site’s `manage_options`.

### CLI import and export trusted the path

A path such as `phar://...` or a symlink could be read or written.

**Fix:** Stream wrappers are rejected. Import requires a real, non-symlink file of at most 2 MB. Export writes only a normal file name inside an existing writable directory.

### Web-root and uploads writes could follow a symlink

`llms.txt` in the web root and the cache files under uploads were written by name. A symlink planted at that path would have been followed. The cache directory also received an `index.php`.

**Fix:** Writes are limited to `llms.txt` and `llms-full.txt` in the web root, and to `*.txt` names made only of letters, digits, `_`, and `-` inside `uploads/llms-txt`. Symlinks are refused. The directory guard is an empty `index.html`, not a PHP file. Nothing is written under `/ru/`.

## Low

### Settings import

JSON is decoded with an associative array and a depth limit, then passed through the existing settings sanitizer. Only `llms_txt_settings` is updated. Unknown keys are dropped. There is no `unserialize`. The upload is limited to 2 MB and requires `manage_options` plus a nonce. The same checks apply to `wp llms settings import`.

### Admin output

Notices, the markdown preview, and form values are escaped with `esc_html`, `esc_attr`, `esc_textarea`, or `esc_url`. The public files are `text/plain` with `X-Content-Type-Options: nosniff`. Header values built by the plugin do not include request data.

### Content that must not appear

The query is `post_status=publish` only. Password-protected, private, draft, scheduled, and trashed posts are excluded. Yoast noindex and the per-post exclude flag are honored. A language file does not include posts from another language. Author emails and filesystem paths are not printed in the public files. Generation errors are stored for administrators and are not sent on the public response.

### Uninstall and activation

Uninstall removes options, post meta, caches, and generated files only when “Delete all data when the plugin is deleted” is on. That option stays off. Activation and version upgrade re-sanitize the existing settings array so new fields appear without replacing sections. The option name remains `llms_txt_settings`.

## Checked and not changed

- No AJAX handlers and no custom REST routes for settings.
- Regenerating, saving, exporting, importing, flushing rewrites, and deleting managed root files already required a nonce and `manage_options`.
- Markdown in the public files is not HTML. The admin preview escapes it.
- In-place updates continue to use `llms-txt/llms-txt.php`.

## Static analysis

PHPUnit covers the manifest rules, path checks, query-string behavior, and the uninstall flag. `php -l` is clean.

`phpcs` with `WordPress.Security` (`phpcs-security.xml.dist`) reports the remaining items as intentional:

- The settings `$_POST` array and the import `$_FILES` entry are not passed through `sanitize_text_field` on that line. The settings array is whitelisted by `Settings::sanitize()`. The upload is accepted only after `is_uploaded_file` and a 2 MB cap, then `json_decode` with an array result.
- `REQUEST_URI` is unslashed and only its path is parsed.
- The public file body is not escaped. It is `text/plain`; escaping would put HTML entities back into the document.

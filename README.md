# LLMs.txt

WordPress plugin that publishes `/llms.txt` and `/llms-full.txt` for agents, following the [llmstxt.org](https://llmstxt.org/) specification.

`/llms.txt` is a short curated map: an H1, a blockquote summary, an intro, and H2 sections of links or prose. `/llms-full.txt` is the clean Markdown for the pages that map includes. Both are served as `text/plain; charset=utf-8`. The plugin does not call external services and does not send telemetry.

## Requirements

- PHP 8.1 or newer
- WordPress 6.4 or newer (tested on 7.1)
- Optional: Yoast SEO (meta descriptions and noindex), WPML or Polylang, Elementor

## Install

Build an installable zip from this directory:

```bash
bin/build-zip.sh
```

The zip is written to `dist/llms-txt.zip`. In wp-admin, open Plugins → Add New → Upload Plugin and install that zip. You can also copy this directory to `wp-content/plugins/llms-txt` and activate **LLMs.txt**.

Then open **Settings → LLMs.txt**.

If the site already has a hand-written `llms.txt` in the web root, the server will keep serving that file and WordPress will never see the request. The settings screen warns about this. Either remove the file, or choose **Also write llms.txt and llms-full.txt in the web root** and confirm that an existing file may be replaced. That second mode is the one to use when nginx serves `.txt` files itself.

## Settings

| Setting | What it does |
| --- | --- |
| Title | H1. The site name, or a custom title. |
| Summary | Blockquote under the title. The tagline, custom text, or omitted. |
| Intro | Markdown before the first section. Headings in the intro are turned into bold lines so the file keeps a single H1. |
| File shape | One file; one file with a section per language; or a file per language (`/ru/llms.txt`). |
| Root file | In per-language mode, `/llms.txt` is the default language or every language. |
| Header overrides | Title, summary, and intro for a non-default language file. Blank fields reuse the main header. |
| Sections | Ordered list. **Hand-written Markdown** holds product, workflow, and legal copy, including links off the site. **Automatic list** prints `- [Title](URL): description` for a post type, optional taxonomy, limit, and order. |
| Language on a section | Empty follows the file being rendered. A code such as `en` or `ru` limits that section to one language. In “section per language” mode, an automatic section with no language is split, for example `Articles (English)` and `Articles (Russian)`. |
| Descriptions | Yoast meta description when it exists, otherwise the excerpt, otherwise the page text. A leading heading is left out, and the next block starts a new sentence. HTML entities such as `&#038;` are decoded. |
| Optional | A final section agents may skip. It can link to the Yoast sitemap index (`/sitemap_index.xml`) or the core sitemap. |
| llms-full.txt | Title, URL, last-modified time, language, and Markdown for each included page. Elementor content is the published front-end document, including a theme-builder template that replaces the page. Revisions and autosaves are ignored. Pages with no body text are left out. Scripts, styles, and menus are dropped. A size cap stops the file and says it was truncated. |
| Noindex | Yoast noindex posts are omitted. Password-protected and private posts are always omitted. |
| Exclude checkbox | A box on each public post and page. The same box can replace the title, which is useful when the stored title is `HomeRU`. |
| Delivery | Dynamic rewrite, or also write the two root files for nginx. Language URLs stay dynamic so a real `/ru/` directory is not created. |
| Cache | Transient plus a copy in `uploads/llms-txt/`. Rebuilt when a watched post is published, updated, or trashed, when a menu changes, on the daily cron event, and from **Regenerate now**. |
| Link header | `Link: </llms.txt>; rel="describedby"` on ordinary front-end responses. |
| robots.txt | A copy-paste block for AI crawlers. The plugin never edits `robots.txt`. |

## A file in the shape of terav.vet

The live site is WordPress with WPML (English at `/`, Russian under `/ru/`), Yoast, and Elementor. A useful setup is:

1. Set the file shape to **A file per language** and the root file to **Default language only**. WPML is detected on its own; Polylang is used when WPML is absent. With neither plugin, add a prefix row: code `ru`, prefix `ru`, and leave the default language prefix empty.
2. Custom title and summary for the English header. Under **Header overrides**, fill the Russian title, summary, and intro.
3. Hand-written sections, each with its language set:
   - `Product and access`
   - `Clinical workflows`
   - `Legal and contact`
   - the Russian equivalents
4. One automatic section, heading `Articles and updates`, post type Posts, descriptions on, language left as “Follow the file”. `/llms.txt` then lists English posts and `/ru/llms.txt` lists Russian posts.
5. Optional section: label `Sitemap Index`, note `Additional public pages and articles.`, sitemap URL left blank so Yoast’s `/sitemap_index.xml` is used.
6. Compare **Current llms.txt** with the hand-written file. Then remove the web-root copy, or confirm overwrite and use disk delivery.

Index links point at the normal permalinks, which is the same shape as the file already published on the site.

## WP-CLI

```bash
wp llms regenerate
wp llms show
wp llms show --full
wp llms show --lang=ru
wp llms status
```

## nginx

Dynamic delivery works when a missing `.txt` falls through to `index.php`, which is the usual WordPress `try_files` rule. If `.txt` is served only from disk, use disk delivery or add:

```nginx
location = /llms.txt {
    try_files $uri /index.php?$args;
}
location = /llms-full.txt {
    try_files $uri /index.php?$args;
}
```

`/ru/llms.txt` is not a real file. It is routed through WordPress.

## Development

Runtime code has no Composer dependencies. PHPUnit is a development dependency.

```bash
composer install
composer test
bin/build-zip.sh
```

## Uninstall

Deleting the plugin removes its options, the exclude meta, the uploads cache, the cron event, and web-root copies whose checksum still matches the last file this plugin wrote. A hand-written `llms.txt` is left in place.

## License

GPL-2.0-or-later.

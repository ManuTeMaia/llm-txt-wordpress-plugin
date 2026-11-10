=== LLMs.txt ===
Contributors: manutemaia
Tags: seo, ai, markdown, llms, yoast
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.2.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Publishes /llms.txt and /llms-full.txt for AI agents and keeps them in sync with the site.

== Description ==

LLMs.txt serves two plain-text files that follow the llmstxt.org specification:

* `/llms.txt` is a short curated map: a title, a summary, an introduction, and sections of links or prose.
* `/llms-full.txt` is clean Markdown for the pages included in that map, with the URL and last-modified time of each one.

The files are regenerated when content is published or trashed, when a menu changes, every day via WP-Cron, and from a Regenerate button. Responses are `text/plain; charset=utf-8` and are cached.

Language support uses WPML when it is active, otherwise Polylang, otherwise the first segment of the URL (so Russian content under `/ru/` still works). You can keep one combined file or also publish `/ru/llms.txt`.

Yoast meta descriptions are preferred for link notes. Yoast noindex, password-protected posts, private posts, and a per-post exclude box are left out. Elementor content is read from the builder data so the full file is not full of markup.

If a physical `llms.txt` already sits in the web root, the plugin warns you: the web server will serve that file and the dynamic one will never run. You can instead write the generated files to disk for nginx setups that do not pass `.txt` requests to PHP.

A recommended robots.txt block for AI crawlers is shown in the settings. The plugin does not modify robots.txt and does not send telemetry. It contacts another server only if you save an update-manifest URL.

Maintained by ManuTeMaia. Contact: cath@manutemaia.com

== Installation ==

1. Upload the plugin zip through Plugins → Add New → Upload Plugin, or copy the `llms-txt` directory into `wp-content/plugins/`.
2. Activate LLMs.txt.
3. Open Settings → LLMs.txt and review the generated preview.
4. If a hand-written `llms.txt` exists in the web root, remove it or switch delivery to writing the files on disk and confirm the overwrite.

To update an existing install, use Plugins → Add New → Upload Plugin and choose the new zip. WordPress offers Replace current with uploaded because the zip’s folder and main file stay `llms-txt/llms-txt.php`. Do not delete the plugin first. Deleting it keeps your settings unless “Delete all data when the plugin is deleted” is on; replacing it keeps them either way. Download settings JSON from the settings screen first if you want a backup.

From the command line, `wp llms regenerate` rebuilds the files, `wp llms show` prints the index, and `wp llms settings export` / `wp llms settings import` move the settings.

== Frequently Asked Questions ==

= Does this replace robots.txt or the sitemap? =

No. llms.txt is a curated briefing for an agent that is already allowed to read the site. The Optional section can link to the existing sitemap. The robots.txt snippet is only a recommendation.

= Will it list draft, private, or noindex content? =

No. Only published posts are eligible. Password-protected posts, private posts, Yoast noindex, and the per-post exclude checkbox are omitted.

= How are translations handled? =

WPML is used when it is installed. Polylang is used otherwise. If neither is present, a post's language is taken from the first URL segment and the prefixes you configure. Choose one file with a section per language, or a separate file such as `/ru/llms.txt`.

== Changelog ==

= 1.2.3 =
* /ru/llms.txt and /ru/llms-full.txt serve that language's file again. The language directory in the request was being removed by comparing it with home_url(), which WPML and Polylang prefix with the current language.

= 1.2.2 =
* A public request serves the last generated file if the transient is gone, and queues one background rebuild. 503 is returned only when no copy has ever been stored. Activation and upgrade schedule that rebuild.

= 1.2.1 =
* Security pass: public requests only serve a cached file, the update manifest requires https and a checksum, and file writes refuse symlinks. Settings storage is unchanged.

= 1.2.0 =
* Deleting the plugin keeps settings unless “Delete all data when the plugin is deleted” is turned on.
* Settings can be downloaded and imported as JSON, including with `wp llms settings export` and `wp llms settings import`.
* Activating a newer copy keeps existing settings and fills in any new fields.
* Author and plugin links point to ManuTeMaia. Automatic updates stay off until an update-manifest URL is saved.

= 1.1.3 =
* Blog posts and ordinary pages use their own published body again. A theme-builder template is used only when it replaces the page, such as the homepage, and it is rendered in that post's language with the post set as the current query.

= 1.1.2 =
* Plugin site and author links point to https://terav.vet/. The llmstxt.org address remains only as the specification reference.

= 1.1.1 =
* llms-full.txt uses the Elementor document visitors see, including a theme-builder template that replaces the page, instead of stale builder JSON. Revisions and autosaves are ignored, and the post's WPML language is used while that document is rendered.

= 1.1.0 =
* Decode HTML entities in titles and descriptions, including &#038;.
* Descriptions taken from page content skip a leading heading and keep a separator between blocks.
* llms-full.txt skips pages that have no body text.
* Each post can override the title used in the files.
* The settings screen states whether /ru/llms.txt is a separate file and whether it uses the Russian header.

= 1.0.0 =
* First release. Dynamic `/llms.txt` and `/llms-full.txt`, language files, disk delivery, caching, WP-CLI, and the robots.txt helper.

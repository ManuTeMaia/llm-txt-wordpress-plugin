<?php
/**
 * Settings screen markup.
 *
 * @package LlmsTxt
 *
 * @var array<string, mixed>                         $settings
 * @var \LlmsTxt\Language\Catalog_Result             $catalog
 * @var array<string, array<string, mixed>>          $disk
 * @var array<string, mixed>                         $status
 * @var array<string, \WP_Post_Type>                 $post_types
 * @var array<string, \WP_Taxonomy>                  $taxonomies
 * @var string                                       $preview
 * @var string                                       $preview_error
 * @var string                                       $robots
 */

defined( 'ABSPATH' ) || exit;

$language_choices = $catalog->languages;
$blank_section    = array(
	'id'           => '',
	'type'         => 'static',
	'heading'      => '',
	'language'     => '',
	'markdown'     => '',
	'post_type'    => 'post',
	'taxonomy'     => '',
	'terms'        => '',
	'limit'        => 20,
	'orderby'      => 'date',
	'order'        => 'DESC',
	'descriptions' => true,
);

$generated = isset( $status['generated_at'] ) ? (int) $status['generated_at'] : 0;
$provider_labels = array(
	'wpml'     => __( 'WPML', 'llms-txt' ),
	'polylang' => __( 'Polylang', 'llms-txt' ),
	'prefix'   => __( 'URL prefix fallback', 'llms-txt' ),
);
$provider_label = $provider_labels[ $catalog->provider ] ?? $catalog->provider;
?>
<div class="wrap llms-txt-wrap">
	<h1><?php esc_html_e( 'LLMs.txt', 'llms-txt' ); ?></h1>
	<p><?php esc_html_e( 'Publishes /llms.txt and /llms-full.txt for agents. The index is a curated map. The full file is clean Markdown for the pages that map includes.', 'llms-txt' ); ?></p>

	<?php if ( isset( $_GET['llms-updated'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved and the files were regenerated.', 'llms-txt' ); ?></p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['llms-regenerated'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Regenerated just now.', 'llms-txt' ); ?></p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['llms-flushed'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Rewrite rules flushed.', 'llms-txt' ); ?></p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['llms-deleted'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Removed web-root copies that this plugin had written.', 'llms-txt' ); ?></p></div>
	<?php endif; ?>
	<?php if ( isset( $_GET['llms-error'] ) && ! empty( $status['error'] ) ) : ?>
		<div class="notice notice-error"><p><?php echo esc_html( (string) $status['error'] ); ?></p></div>
	<?php endif; ?>

	<div class="llms-txt-layout">
		<div>
			<form id="llms-txt-settings" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="llms_txt_save">
				<?php wp_nonce_field( 'llms_txt_save', 'llms_txt_nonce' ); ?>

				<h2><?php esc_html_e( 'Document', 'llms-txt' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Serving', 'llms-txt' ); ?></th>
						<td>
							<input type="hidden" name="llms_txt[enabled]" value="0">
							<label>
								<input type="checkbox" name="llms_txt[enabled]" value="1" <?php checked( $settings['enabled'] ); ?>>
								<?php esc_html_e( 'Serve llms.txt and llms-full.txt', 'llms-txt' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="llms-txt-title-mode"><?php esc_html_e( 'Title', 'llms-txt' ); ?></label></th>
						<td>
							<select id="llms-txt-title-mode" name="llms_txt[title_mode]">
								<option value="site" <?php selected( $settings['title_mode'], 'site' ); ?>><?php esc_html_e( 'Site name', 'llms-txt' ); ?></option>
								<option value="custom" <?php selected( $settings['title_mode'], 'custom' ); ?>><?php esc_html_e( 'Custom title', 'llms-txt' ); ?></option>
							</select>
							<input class="regular-text" type="text" name="llms_txt[title]" value="<?php echo esc_attr( $settings['title'] ); ?>" placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
							<p class="description"><?php esc_html_e( 'The H1. The llmstxt.org spec requires this and nothing else.', 'llms-txt' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="llms-txt-summary-mode"><?php esc_html_e( 'Summary', 'llms-txt' ); ?></label></th>
						<td>
							<select id="llms-txt-summary-mode" name="llms_txt[summary_mode]">
								<option value="tagline" <?php selected( $settings['summary_mode'], 'tagline' ); ?>><?php esc_html_e( 'Site tagline', 'llms-txt' ); ?></option>
								<option value="custom" <?php selected( $settings['summary_mode'], 'custom' ); ?>><?php esc_html_e( 'Custom summary', 'llms-txt' ); ?></option>
								<option value="none" <?php selected( $settings['summary_mode'], 'none' ); ?>><?php esc_html_e( 'No summary', 'llms-txt' ); ?></option>
							</select>
							<textarea class="large-text" rows="3" name="llms_txt[summary]"><?php echo esc_textarea( $settings['summary'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Rendered as a blockquote directly under the title.', 'llms-txt' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="llms-txt-intro"><?php esc_html_e( 'Intro', 'llms-txt' ); ?></label></th>
						<td>
							<textarea id="llms-txt-intro" class="large-text" rows="5" name="llms_txt[intro]"><?php echo esc_textarea( $settings['intro'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Free-form Markdown before the first section. Headings here are turned into bold lines so the outline stays valid.', 'llms-txt' ); ?></p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Languages', 'llms-txt' ); ?></h2>
				<p>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: detected multilingual provider. */
							__( 'Detected: %s.', 'llms-txt' ),
							$provider_label
						)
					);
					if ( array() !== $language_choices ) {
						$names = array();
						foreach ( $language_choices as $language ) {
							$names[] = $language->name . ( $language->default ? ' (' . __( 'default', 'llms-txt' ) . ')' : '' );
						}
						echo ' ' . esc_html( implode( ', ', $names ) ) . '.';
					}
					?>
				</p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="llms-txt-language-mode"><?php esc_html_e( 'File shape', 'llms-txt' ); ?></label></th>
						<td>
							<select id="llms-txt-language-mode" name="llms_txt[language_mode]">
								<option value="single" <?php selected( $settings['language_mode'], 'single' ); ?>><?php esc_html_e( 'One file, no language split', 'llms-txt' ); ?></option>
								<option value="sections" <?php selected( $settings['language_mode'], 'sections' ); ?>><?php esc_html_e( 'One file, a section per language', 'llms-txt' ); ?></option>
								<option value="paths" <?php selected( $settings['language_mode'], 'paths' ); ?>><?php esc_html_e( 'A file per language (/ru/llms.txt)', 'llms-txt' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'WPML and Polylang are used when they are active. Otherwise a post’s language is the first URL segment, matched against the prefixes below.', 'llms-txt' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="llms-txt-root-scope"><?php esc_html_e( 'Root file', 'llms-txt' ); ?></label></th>
						<td>
							<select id="llms-txt-root-scope" name="llms_txt[root_scope]">
								<option value="default" <?php selected( $settings['root_scope'], 'default' ); ?>><?php esc_html_e( 'Default language only', 'llms-txt' ); ?></option>
								<option value="all" <?php selected( $settings['root_scope'], 'all' ); ?>><?php esc_html_e( 'Every language', 'llms-txt' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'Used when each language has its own URL. The root /llms.txt can stay in the default language, which matches a site whose English file lives at the root and Russian content lives under /ru/.', 'llms-txt' ); ?></p>
						</td>
					</tr>
				</table>

				<h3><?php esc_html_e( 'Header overrides', 'llms-txt' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Used by language URLs such as /ru/llms.txt. A blank field keeps the main title, summary, or intro.', 'llms-txt' ); ?></p>
				<?php
				$override_languages = array();
				foreach ( $language_choices as $language ) {
					if ( ! $language->default ) {
						$override_languages[ $language->code ] = $language->name;
					}
				}
				foreach ( array_keys( $settings['translations'] ) as $saved_code ) {
					if ( ! isset( $override_languages[ $saved_code ] ) ) {
						$override_languages[ $saved_code ] = $saved_code;
					}
				}
				?>
				<?php if ( array() === $override_languages ) : ?>
					<p class="description"><?php esc_html_e( 'No additional languages detected yet. With WPML or Polylang, each language appears here. Without them, add a URL prefix below.', 'llms-txt' ); ?></p>
				<?php endif; ?>
				<?php foreach ( $override_languages as $code => $name ) : ?>
					<?php $translation = $settings['translations'][ $code ] ?? array( 'title' => '', 'summary' => '', 'intro' => '' ); ?>
					<fieldset class="llms-txt-card">
						<legend><strong><?php echo esc_html( $name . ' (' . $code . ')' ); ?></strong></legend>
						<p>
							<label>
								<?php esc_html_e( 'Title', 'llms-txt' ); ?>
								<input class="widefat" type="text" name="llms_txt[translations][<?php echo esc_attr( $code ); ?>][title]" value="<?php echo esc_attr( (string) ( $translation['title'] ?? '' ) ); ?>">
							</label>
						</p>
						<p>
							<label>
								<?php esc_html_e( 'Summary', 'llms-txt' ); ?>
								<textarea class="widefat" rows="2" name="llms_txt[translations][<?php echo esc_attr( $code ); ?>][summary]"><?php echo esc_textarea( (string) ( $translation['summary'] ?? '' ) ); ?></textarea>
							</label>
						</p>
						<p>
							<label>
								<?php esc_html_e( 'Intro', 'llms-txt' ); ?>
								<textarea class="widefat" rows="3" name="llms_txt[translations][<?php echo esc_attr( $code ); ?>][intro]"><?php echo esc_textarea( (string) ( $translation['intro'] ?? '' ) ); ?></textarea>
							</label>
						</p>
					</fieldset>
				<?php endforeach; ?>

				<h3><?php esc_html_e( 'Prefix fallback', 'llms-txt' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Ignored while WPML or Polylang is active. Leave the prefix empty for the language that lives at the site root.', 'llms-txt' ); ?></p>
				<div id="llms-txt-prefixes">
					<?php foreach ( $settings['prefixes'] as $prefix_index => $prefix ) : ?>
						<div class="llms-txt-prefix">
							<label>
								<?php esc_html_e( 'Code', 'llms-txt' ); ?>
								<input type="text" class="small-text" name="llms_txt[prefixes][<?php echo esc_attr( (string) $prefix_index ); ?>][code]" value="<?php echo esc_attr( (string) $prefix['code'] ); ?>">
							</label>
							<label>
								<?php esc_html_e( 'Name', 'llms-txt' ); ?>
								<input type="text" name="llms_txt[prefixes][<?php echo esc_attr( (string) $prefix_index ); ?>][name]" value="<?php echo esc_attr( (string) $prefix['name'] ); ?>">
							</label>
							<label>
								<?php esc_html_e( 'URL prefix', 'llms-txt' ); ?>
								<input type="text" class="small-text" name="llms_txt[prefixes][<?php echo esc_attr( (string) $prefix_index ); ?>][prefix]" value="<?php echo esc_attr( (string) $prefix['prefix'] ); ?>" placeholder="ru">
							</label>
							<label>
								<input type="radio" name="llms_txt[prefix_default]" value="<?php echo esc_attr( (string) $prefix_index ); ?>" <?php checked( ! empty( $prefix['default'] ) ); ?>>
								<?php esc_html_e( 'Default', 'llms-txt' ); ?>
							</label>
							<button type="button" class="button llms-txt-remove-prefix"><?php esc_html_e( 'Remove', 'llms-txt' ); ?></button>
						</div>
					<?php endforeach; ?>
				</div>
				<p><button type="button" class="button" id="llms-txt-add-prefix"><?php esc_html_e( 'Add language prefix', 'llms-txt' ); ?></button></p>
				<template id="llms-txt-prefix-template">
					<div class="llms-txt-prefix">
						<label>
							<?php esc_html_e( 'Code', 'llms-txt' ); ?>
							<input type="text" class="small-text" name="llms_txt[prefixes][__INDEX__][code]" value="">
						</label>
						<label>
							<?php esc_html_e( 'Name', 'llms-txt' ); ?>
							<input type="text" name="llms_txt[prefixes][__INDEX__][name]" value="">
						</label>
						<label>
							<?php esc_html_e( 'URL prefix', 'llms-txt' ); ?>
							<input type="text" class="small-text" name="llms_txt[prefixes][__INDEX__][prefix]" value="">
						</label>
						<label>
							<input type="radio" name="llms_txt[prefix_default]" value="__INDEX__">
							<?php esc_html_e( 'Default', 'llms-txt' ); ?>
						</label>
						<button type="button" class="button llms-txt-remove-prefix"><?php esc_html_e( 'Remove', 'llms-txt' ); ?></button>
					</div>
				</template>

				<h2><?php esc_html_e( 'Sections', 'llms-txt' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Hand-written sections are for product, workflow, and legal copy, including links that leave this site. Automatic sections list posts as “- [Title](URL): description”. Put the sections in the order they should appear. A section named Optional is always moved to the end.', 'llms-txt' ); ?></p>
				<div id="llms-txt-sections">
					<?php
					foreach ( $settings['sections'] as $section_index => $section ) {
						$index = $section_index;
						include __DIR__ . '/section-card.php';
					}
					?>
				</div>
				<p><button type="button" class="button" id="llms-txt-add"><?php esc_html_e( 'Add section', 'llms-txt' ); ?></button></p>
				<template id="llms-txt-section-template">
					<?php
					$section = $blank_section;
					$index   = '__INDEX__';
					include __DIR__ . '/section-card.php';
					?>
				</template>

				<h2><?php esc_html_e( 'Optional', 'llms-txt' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Agents are expected to skip a section named Optional when they need a shorter context. Keep the heading as Optional unless you have a reason to change it.', 'llms-txt' ); ?></p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Sitemap link', 'llms-txt' ); ?></th>
						<td>
							<input type="hidden" name="llms_txt[sitemap_in_optional]" value="0">
							<label>
								<input type="checkbox" name="llms_txt[sitemap_in_optional]" value="1" <?php checked( $settings['sitemap_in_optional'] ); ?>>
								<?php esc_html_e( 'Include the sitemap in the Optional section', 'llms-txt' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="llms-txt-optional-heading"><?php esc_html_e( 'Heading', 'llms-txt' ); ?></label></th>
						<td><input id="llms-txt-optional-heading" type="text" class="regular-text" name="llms_txt[optional_heading]" value="<?php echo esc_attr( $settings['optional_heading'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="llms-txt-sitemap-label"><?php esc_html_e( 'Link label', 'llms-txt' ); ?></label></th>
						<td><input id="llms-txt-sitemap-label" type="text" class="regular-text" name="llms_txt[sitemap_label]" value="<?php echo esc_attr( $settings['sitemap_label'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="llms-txt-sitemap-note"><?php esc_html_e( 'Link note', 'llms-txt' ); ?></label></th>
						<td><input id="llms-txt-sitemap-note" type="text" class="large-text" name="llms_txt[sitemap_note]" value="<?php echo esc_attr( $settings['sitemap_note'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="llms-txt-sitemap-url"><?php esc_html_e( 'Sitemap URL', 'llms-txt' ); ?></label></th>
						<td>
							<input id="llms-txt-sitemap-url" type="url" class="large-text" name="llms_txt[sitemap_url]" value="<?php echo esc_attr( $settings['sitemap_url'] ); ?>" placeholder="<?php echo esc_attr( home_url( '/sitemap_index.xml' ) ); ?>">
							<p class="description"><?php esc_html_e( 'Leave blank to use the Yoast sitemap index when Yoast is active, otherwise the core sitemap.', 'llms-txt' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="llms-txt-optional-extra"><?php esc_html_e( 'Extra Markdown', 'llms-txt' ); ?></label></th>
						<td><textarea id="llms-txt-optional-extra" class="large-text" rows="4" name="llms_txt[optional_extra]"><?php echo esc_textarea( $settings['optional_extra'] ); ?></textarea></td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Full document', 'llms-txt' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'llms-full.txt', 'llms-txt' ); ?></th>
						<td>
							<input type="hidden" name="llms_txt[full_enabled]" value="0">
							<label>
								<input type="checkbox" name="llms_txt[full_enabled]" value="1" <?php checked( $settings['full_enabled'] ); ?>>
								<?php esc_html_e( 'Publish the expanded Markdown file', 'llms-txt' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Each entry has the title, URL, last-modified time, and the page converted from HTML. Elementor content is read from the builder data. Scripts, styles, and menus are dropped.', 'llms-txt' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="llms-txt-cap"><?php esc_html_e( 'Size cap', 'llms-txt' ); ?></label></th>
						<td>
							<input id="llms-txt-cap" type="number" min="64" max="10240" name="llms_txt[full_cap_kb]" value="<?php echo esc_attr( (string) ( (int) $settings['full_byte_cap'] / 1024 ) ); ?>">
							<?php esc_html_e( 'KB', 'llms-txt' ); ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Linked pages', 'llms-txt' ); ?></th>
						<td>
							<input type="hidden" name="llms_txt[full_include_linked]" value="0">
							<label>
								<input type="checkbox" name="llms_txt[full_include_linked]" value="1" <?php checked( $settings['full_include_linked'] ); ?>>
								<?php esc_html_e( 'Also expand local pages linked from hand-written sections', 'llms-txt' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Descriptions', 'llms-txt' ); ?></th>
						<td>
							<label>
								<?php esc_html_e( 'Length', 'llms-txt' ); ?>
								<input type="number" min="40" max="500" name="llms_txt[description_length]" value="<?php echo esc_attr( (string) $settings['description_length'] ); ?>">
							</label>
							<p>
								<input type="hidden" name="llms_txt[respect_noindex]" value="0">
								<label>
									<input type="checkbox" name="llms_txt[respect_noindex]" value="1" <?php checked( $settings['respect_noindex'] ); ?>>
									<?php esc_html_e( 'Omit posts Yoast marks noindex', 'llms-txt' ); ?>
								</label>
							</p>
							<p>
								<input type="hidden" name="llms_txt[hide_empty_sections]" value="0">
								<label>
									<input type="checkbox" name="llms_txt[hide_empty_sections]" value="1" <?php checked( $settings['hide_empty_sections'] ); ?>>
									<?php esc_html_e( 'Hide automatic sections that have no public posts', 'llms-txt' ); ?>
								</label>
							</p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Delivery', 'llms-txt' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="llms-txt-delivery"><?php esc_html_e( 'How the files are served', 'llms-txt' ); ?></label></th>
						<td>
							<select id="llms-txt-delivery" name="llms_txt[delivery]">
								<option value="dynamic" <?php selected( $settings['delivery'], 'dynamic' ); ?>><?php esc_html_e( 'Dynamic, through WordPress', 'llms-txt' ); ?></option>
								<option value="disk" <?php selected( $settings['delivery'], 'disk' ); ?>><?php esc_html_e( 'Also write llms.txt and llms-full.txt in the web root', 'llms-txt' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'Choose the web root when nginx (or another server) serves .txt files itself and never asks PHP. Language URLs such as /ru/llms.txt stay dynamic, because creating a real /ru/ directory would fight WPML.', 'llms-txt' ); ?></p>
							<p>
								<label>
									<input type="checkbox" name="llms_txt[disk_overwrite_ack]" value="1" <?php checked( $settings['disk_overwrite_ack'] ); ?>>
									<?php esc_html_e( 'Replace an existing web-root file even if this plugin did not write it', 'llms-txt' ); ?>
								</label>
							</p>
							<ul class="llms-txt-disk">
								<li>
									<?php
									echo esc_html(
										sprintf(
											/* translators: 1: file name, 2: present or absent. */
											__( '%1$s: %2$s', 'llms-txt' ),
											'llms.txt',
											$disk['index']['exists']
												? ( $disk['index']['managed'] ? __( 'present, written by this plugin', 'llms-txt' ) : __( 'present, not written by this plugin', 'llms-txt' ) )
												: __( 'not in the web root', 'llms-txt' )
										)
									);
									?>
								</li>
								<li>
									<?php
									echo esc_html(
										sprintf(
											__( '%1$s: %2$s', 'llms-txt' ),
											'llms-full.txt',
											$disk['full']['exists']
												? ( $disk['full']['managed'] ? __( 'present, written by this plugin', 'llms-txt' ) : __( 'present, not written by this plugin', 'llms-txt' ) )
												: __( 'not in the web root', 'llms-txt' )
										)
									);
									?>
								</li>
							</ul>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="llms-txt-cache"><?php esc_html_e( 'Browser cache', 'llms-txt' ); ?></label></th>
						<td>
							<input id="llms-txt-cache" type="number" min="0" max="86400" name="llms_txt[cache_max_age]" value="<?php echo esc_attr( (string) $settings['cache_max_age'] ); ?>">
							<?php esc_html_e( 'seconds', 'llms-txt' ); ?>
							<p class="description"><?php esc_html_e( 'WordPress also keeps a transient and a copy in uploads. Both are rebuilt on publish, trash, menu edits, the daily cron event, and the button below.', 'llms-txt' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Link header', 'llms-txt' ); ?></th>
						<td>
							<input type="hidden" name="llms_txt[link_header]" value="0">
							<label>
								<input type="checkbox" name="llms_txt[link_header]" value="1" <?php checked( $settings['link_header'] ); ?>>
								<?php esc_html_e( 'Send Link: </llms.txt>; rel="describedby" on front-end responses', 'llms-txt' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Save and regenerate', 'llms-txt' ) ); ?>
			</form>

			<div class="llms-txt-tools">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="llms_txt_regenerate">
					<?php wp_nonce_field( 'llms_txt_regenerate', 'llms_txt_nonce' ); ?>
					<?php submit_button( __( 'Regenerate now', 'llms-txt' ), 'secondary', 'submit', false ); ?>
				</form>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="llms_txt_flush">
					<?php wp_nonce_field( 'llms_txt_flush', 'llms_txt_nonce' ); ?>
					<?php submit_button( __( 'Flush rewrites', 'llms-txt' ), 'secondary', 'submit', false ); ?>
				</form>
				<?php if ( $disk['index']['managed'] || $disk['full']['managed'] ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="llms_txt_delete_root">
						<?php wp_nonce_field( 'llms_txt_delete_root', 'llms_txt_nonce' ); ?>
						<?php submit_button( __( 'Delete managed web-root copies', 'llms-txt' ), 'delete', 'submit', false ); ?>
					</form>
				<?php endif; ?>
			</div>
			<p class="description">
				<?php
				if ( $generated > 0 ) {
					echo esc_html(
						sprintf(
							/* translators: %s: localized date and time. */
							__( 'Last rebuild: %s.', 'llms-txt' ),
							wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $generated )
						)
					);
				} else {
					esc_html_e( 'The files have not been built yet.', 'llms-txt' );
				}
				?>
				<a href="<?php echo esc_url( home_url( '/llms.txt' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View llms.txt', 'llms-txt' ); ?></a>
				·
				<a href="<?php echo esc_url( home_url( '/llms-full.txt' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View llms-full.txt', 'llms-txt' ); ?></a>
			</p>

			<h2><?php esc_html_e( 'robots.txt', 'llms-txt' ); ?></h2>
			<p><?php esc_html_e( 'This block is only a recommendation. The plugin does not edit robots.txt. Allow lines belong before Disallow lines for the same crawler.', 'llms-txt' ); ?></p>
			<textarea id="llms-txt-robots" class="large-text code" rows="16" readonly><?php echo esc_textarea( $robots ); ?></textarea>
			<p><button type="button" class="button" data-llms-copy="#llms-txt-robots"><?php esc_html_e( 'Copy', 'llms-txt' ); ?></button></p>
		</div>

		<aside class="llms-txt-preview">
			<h2><?php esc_html_e( 'Current llms.txt', 'llms-txt' ); ?></h2>
			<?php if ( '' !== $preview_error ) : ?>
				<div class="notice notice-error inline"><p><?php echo esc_html( $preview_error ); ?></p></div>
			<?php endif; ?>
			<pre><?php echo esc_html( strlen( $preview ) > 20000 ? substr( $preview, 0, 20000 ) . "\n…" : $preview ); ?></pre>
		</aside>
	</div>
</div>

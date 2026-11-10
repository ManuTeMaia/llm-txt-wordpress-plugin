<?php
/**
 * Builds index and full-text models from settings and posts.
 *
 * @package LlmsTxt
 */

namespace LlmsTxt\Content;

use LlmsTxt\Document\Full_Entry;
use LlmsTxt\Document\Full_Model;
use LlmsTxt\Document\Index_Model;
use LlmsTxt\Document\Markdown_Text;
use LlmsTxt\Document\Section_Model;
use LlmsTxt\Language\Catalog;
use LlmsTxt\Language\Language_Switcher;
use LlmsTxt\Language\Section_Planner;
use LlmsTxt\Settings;

/**
 * Collects the curated sections and the pages they point at.
 */
final class Assembler {
	public function __construct(
		private Settings $settings,
		private Catalog $languages,
		private Section_Planner $planner,
		private Post_Repository $posts,
		private Description_Resolver $descriptions,
		private Content_Extractor $extractor,
		private Language_Switcher $switcher
	) {
	}

	/**
	 * Shared factory used by HTTP, cron, CLI, and wp-admin.
	 */
	public static function make( ?Settings $settings = null ): self {
		$settings  = $settings ?? new Settings();
		$catalog   = new Catalog( $settings );
		$elementor = new Elementor_Extractor();
		return new self(
			$settings,
			$catalog,
			new Section_Planner(),
			new Post_Repository( new Post_Gate( new Exclusion_Policy() ), new Language_Switcher() ),
			new Description_Resolver( $elementor ),
			new Content_Extractor( new Html_To_Markdown(), $elementor ),
			new Language_Switcher()
		);
	}

	/**
	 * @param string|null $route_language Language from the URL, or null for the root file.
	 */
	public function index_model( ?string $route_language ): Index_Model {
		$settings = $this->settings->get();
		$catalog  = $this->languages->detect();
		$sections = array();

		foreach ( $settings['sections'] as $section ) {
			if ( ! is_array( $section ) ) {
				continue;
			}
			if ( 'static' === $section['type'] ) {
				if ( ! $this->planner->static_visible( (string) $section['language'], (string) $settings['language_mode'], $route_language, (string) $settings['root_scope'], $catalog->default_code() ) ) {
					continue;
				}
				$sections[] = new Section_Model( (string) $section['heading'], (string) $section['markdown'], $this->is_optional_heading( (string) $section['heading'], $settings ) );
				continue;
			}

			$plans = $this->planner->auto_plans(
				array(
					'heading'  => (string) $section['heading'],
					'language' => (string) $section['language'],
				),
				(string) $settings['language_mode'],
				$route_language,
				(string) $settings['root_scope'],
				$catalog->languages,
				$catalog->default_code()
			);
			foreach ( $plans as $plan ) {
				$posts = $this->collect_posts( $section, $plan['language'], $catalog->provider, (bool) $settings['respect_noindex'] );
				if ( array() === $posts && $settings['hide_empty_sections'] ) {
					continue;
				}
				$lines = array();
				foreach ( $posts as $post ) {
					$lines[] = $this->link_for(
						$post,
						$plan['language'],
						(bool) $section['descriptions'],
						(int) $settings['description_length']
					);
				}
				$sections[] = new Section_Model( $plan['heading'], implode( "\n", $lines ), false );
			}
		}

		$optional = $this->optional_section( $settings );
		if ( null !== $optional ) {
			$sections[] = $optional;
		}

		$header = $this->settings->header_for( $settings, $route_language );
		$model  = new Index_Model( $header['title'], $header['summary'], $header['intro'], $sections );

		$filtered = apply_filters( 'llms_txt_index_model', $model, $route_language );
		return $filtered instanceof Index_Model ? $filtered : $model;
	}

	/**
	 * @param string|null $route_language Language from the URL, or null for the root file.
	 */
	public function full_model( ?string $route_language ): Full_Model {
		$settings = $this->settings->get();
		$catalog  = $this->languages->detect();
		$ids      = array();

		foreach ( $settings['sections'] as $section ) {
			if ( ! is_array( $section ) ) {
				continue;
			}
			if ( 'static' === $section['type'] ) {
				if ( $settings['full_include_linked'] && $this->planner->static_visible( (string) $section['language'], (string) $settings['language_mode'], $route_language, (string) $settings['root_scope'], $catalog->default_code() ) ) {
					foreach ( $this->local_ids_from_markdown( (string) $section['markdown'] ) as $id ) {
						$ids[] = $id;
					}
				}
				continue;
			}

			$plans = $this->planner->auto_plans(
				array(
					'heading'  => (string) $section['heading'],
					'language' => (string) $section['language'],
				),
				(string) $settings['language_mode'],
				$route_language,
				(string) $settings['root_scope'],
				$catalog->languages,
				$catalog->default_code()
			);
			foreach ( $plans as $plan ) {
				foreach ( $this->collect_posts( $section, $plan['language'], $catalog->provider, (bool) $settings['respect_noindex'] ) as $post ) {
					$ids[] = (int) $post->ID;
				}
			}
		}

		$seen          = array();
		$entries       = array();
		$gate          = new Post_Gate( new Exclusion_Policy() );
		$file_language = null;
		if ( 'paths' === $settings['language_mode'] ) {
			$file_language = $this->planner->file_language( $route_language, (string) $settings['root_scope'], $catalog->default_code() );
		}
		foreach ( $ids as $id ) {
			if ( isset( $seen[ $id ] ) || ! $gate->allows( $id, (bool) $settings['respect_noindex'] ) ) {
				continue;
			}
			if ( null !== $file_language ) {
				$post_language = $this->languages->post_language( $id );
				if ( '' !== $post_language && $post_language !== $file_language ) {
					continue;
				}
			}
			$seen[ $id ] = true;
			$entry       = $this->entry_for( $id );
			if ( null !== $entry ) {
				$entries[] = $entry;
			}
		}

		$header = $this->settings->header_for( $settings, $route_language );
		$model  = new Full_Model( $header['title'], $header['summary'], $entries );
		$filtered = apply_filters( 'llms_txt_full_model', $model, $route_language );
		return $filtered instanceof Full_Model ? $filtered : $model;
	}

	/**
	 * Public sitemap URL, with Yoast and core as the defaults.
	 *
	 * @param array<string, mixed> $settings Clean settings.
	 */
	public function sitemap_url( array $settings ): string {
		$override = trim( (string) ( $settings['sitemap_url'] ?? '' ) );
		if ( '' !== $override ) {
			$url = $override;
		} elseif ( defined( 'WPSEO_VERSION' ) || class_exists( 'RankMath' ) ) {
			$url = home_url( '/sitemap_index.xml' );
		} else {
			$url = home_url( '/wp-sitemap.xml' );
		}

		$filtered = apply_filters( 'llms_txt_sitemap_url', $url, $settings );
		return is_string( $filtered ) && '' !== $filtered ? $filtered : $url;
	}

	/**
	 * @param array<string, mixed> $section          Section settings.
	 * @param string|null          $language         Language to query.
	 * @param string               $provider         Active provider.
	 * @param bool                 $respect_noindex  Noindex policy.
	 * @return list<\WP_Post>
	 */
	private function collect_posts( array $section, ?string $language, string $provider, bool $respect_noindex ): array {
		$posts = $this->posts->query( $section, $language, $provider, $respect_noindex );
		if ( 'prefix' !== $provider || null === $language ) {
			return $posts;
		}

		$filtered = array();
		foreach ( $posts as $post ) {
			if ( $this->languages->post_language( (int) $post->ID ) === $language ) {
				$filtered[] = $post;
			}
		}
		$limit = (int) ( $section['limit'] ?? count( $filtered ) );
		return array_slice( $filtered, 0, $limit );
	}

	/**
	 * @param \WP_Post    $post             Post to link.
	 * @param string|null $language         Language whose permalink should be used.
	 * @param bool        $with_description Whether to append a short description.
	 * @param int         $length           Description length.
	 */
	private function link_for( \WP_Post $post, ?string $language, bool $with_description, int $length ): string {
		$url         = $this->permalink( $post, $language );
		$description = $with_description ? $this->descriptions->for_post( (int) $post->ID, $length ) : '';
		return Markdown_Text::link_item( $this->display_title( $post ), $url, $description );
	}

	/**
	 * @param \WP_Post    $post     Post.
	 * @param string|null $language Active language.
	 */
	private function permalink( \WP_Post $post, ?string $language ): string {
		$url = $this->switcher->run(
			$language,
			static function () use ( $post ): string {
				$permalink = get_permalink( $post );
				return is_string( $permalink ) ? $permalink : '';
			}
		);
		return is_string( $url ) ? $url : '';
	}

	/**
	 * @param int $post_id Post ID.
	 */
	private function entry_for( int $post_id ): ?Full_Entry {
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return null;
		}
		$language = $this->languages->post_language( $post_id );
		$url      = $this->permalink( $post, '' !== $language ? $language : null );
		$modified = get_post_modified_time( 'c', true, $post );
		$body     = $this->switcher->run(
			'' !== $language ? $language : null,
			function () use ( $post ): string {
				return $this->extractor->markdown_for_post( $post );
			}
		);
		$body  = is_string( $body ) ? trim( $body ) : '';
		$title = $this->display_title( $post );
		if ( ! Markdown_Text::has_prose( $body, $title ) ) {
			return null;
		}

		return new Full_Entry(
			$title,
			$url,
			is_string( $modified ) ? $modified : '',
			$language,
			$body
		);
	}

	/**
	 * Title shown in both files. A per-post override wins over the WordPress title.
	 *
	 * @param \WP_Post $post Post.
	 */
	private function display_title( \WP_Post $post ): string {
		$custom = get_post_meta( $post->ID, '_llms_txt_title', true );
		$raw    = is_string( $custom ) && '' !== trim( $custom ) ? $custom : (string) get_the_title( $post );
		$title  = Markdown_Text::plain( $raw );
		$filtered = apply_filters( 'llms_txt_post_title', $title, $post );
		return is_string( $filtered ) && '' !== trim( $filtered ) ? Markdown_Text::plain( $filtered ) : $title;
	}

	/**
	 * @param array<string, mixed> $settings Clean settings.
	 */
	private function optional_section( array $settings ): ?Section_Model {
		$parts = array();
		if ( $settings['sitemap_in_optional'] ) {
			$parts[] = Markdown_Text::link_item(
				(string) $settings['sitemap_label'],
				$this->sitemap_url( $settings ),
				(string) $settings['sitemap_note']
			);
		}
		$body = implode( "\n", $parts );
		$extra = trim( (string) $settings['optional_extra'] );
		if ( '' !== $extra ) {
			$body = trim( $body . "\n\n" . $extra );
		}
		if ( '' === trim( $body ) ) {
			return null;
		}

		return new Section_Model( (string) $settings['optional_heading'], $body, true );
	}

	/**
	 * @param string               $heading  Section heading.
	 * @param array<string, mixed> $settings Clean settings.
	 */
	private function is_optional_heading( string $heading, array $settings ): bool {
		return 0 === strcasecmp( trim( $heading ), trim( (string) $settings['optional_heading'] ) );
	}

	/**
	 * Post IDs for Markdown links that point at this site.
	 *
	 * @param string $markdown Section Markdown.
	 * @return list<int>
	 */
	private function local_ids_from_markdown( string $markdown ): array {
		preg_match_all( '/\[[^\]]*\]\(([^)\s]+)\)/', $markdown, $matches );
		if ( empty( $matches[1] ) ) {
			return array();
		}
		$ids = array();
		foreach ( $matches[1] as $url ) {
			$url = html_entity_decode( (string) $url, ENT_QUOTES );
			$url = str_replace( array( '%20', '%29' ), array( ' ', ')' ), $url );
			if ( ! $this->is_local( $url ) ) {
				continue;
			}
			$id = $this->url_to_id( $url );
			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}
		return $ids;
	}

	/**
	 * @param string $url Candidate URL.
	 */
	private function is_local( string $url ): bool {
		if ( str_starts_with( $url, '/' ) ) {
			return true;
		}
		$host = wp_parse_url( $url, PHP_URL_HOST );
		$home = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		if ( ! is_string( $host ) || ! is_string( $home ) ) {
			return false;
		}
		return strtolower( $host ) === strtolower( $home );
	}

	/**
	 * @param string $url Local URL.
	 */
	private function url_to_id( string $url ): int {
		$home = untrailingslashit( home_url( '/' ) );
		$bare = untrailingslashit( $url );
		if ( $bare === $home ) {
			$front = (int) get_option( 'page_on_front' );
			if ( $front > 0 ) {
				return $front;
			}
		}
		return (int) url_to_postid( $url );
	}
}

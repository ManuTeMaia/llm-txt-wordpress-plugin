<?php
/**
 * Detects WPML, Polylang, or a URL-prefix fallback.
 *
 * @package LlmsTxt
 */

namespace LlmsTxt\Language;

use LlmsTxt\Http\Site_Home;
use LlmsTxt\Settings;

/**
 * The active multilingual provider for this request.
 */
final class Catalog {
	private ?Catalog_Result $cached = null;

	public function __construct( private Settings $settings ) {
	}

	/**
	 * Detect languages. WPML wins, then Polylang, then configured prefixes.
	 */
	public function detect(): Catalog_Result {
		if ( null !== $this->cached ) {
			return $this->cached;
		}

		$languages = $this->from_wpml();
		if ( array() !== $languages ) {
			$this->cached = new Catalog_Result( 'wpml', $languages );
			return $this->cached;
		}

		$languages = $this->from_polylang();
		if ( array() !== $languages ) {
			$this->cached = new Catalog_Result( 'polylang', $languages );
			return $this->cached;
		}

		$this->cached = new Catalog_Result( 'prefix', $this->from_prefixes() );
		return $this->cached;
	}

	/**
	 * Language code stored for a post, or the prefix implied by its permalink.
	 *
	 * @param int $post_id Post ID.
	 */
	public function post_language( int $post_id ): string {
		$result = $this->detect();
		if ( 'wpml' === $result->provider ) {
			$code = $this->wpml_post_language( $post_id );
			if ( '' !== $code ) {
				return $code;
			}
		}
		if ( 'polylang' === $result->provider && function_exists( 'pll_get_post_language' ) ) {
			$code = pll_get_post_language( $post_id, 'slug' );
			return is_string( $code ) ? $code : '';
		}

		$permalink = function_exists( 'get_permalink' ) ? get_permalink( $post_id ) : '';
		$path      = is_string( $permalink ) ? (string) parse_url( $permalink, PHP_URL_PATH ) : '';
		return ( new Path_Language() )->match( $path, $this->settings->get()['prefixes'], $result->default_code() );
	}

	/**
	 * @return list<Language>
	 */
	private function from_wpml(): array {
		if ( ! defined( 'ICL_SITEPRESS_VERSION' ) && ! ( function_exists( 'has_filter' ) && has_filter( 'wpml_active_languages' ) ) ) {
			return array();
		}

		try {
			$raw = apply_filters( 'wpml_active_languages', null, array( 'skip_missing' => 0 ) );
			if ( ! is_array( $raw ) || array() === $raw ) {
				if ( function_exists( 'icl_get_languages' ) ) {
					$raw = icl_get_languages( 'skip_missing=0' );
				}
			}
			if ( ! is_array( $raw ) || array() === $raw ) {
				return array();
			}
			$default = apply_filters( 'wpml_default_language', null );
			$default = is_string( $default ) ? $default : '';
			return $this->map_rows( $raw, $default );
		} catch ( \Throwable $exception ) {
			return array();
		}
	}

	/**
	 * @return list<Language>
	 */
	private function from_polylang(): array {
		if ( ! function_exists( 'pll_languages_list' ) && ! function_exists( 'pll_the_languages' ) ) {
			return array();
		}

		try {
			$default = function_exists( 'pll_default_language' ) ? (string) pll_default_language( 'slug' ) : '';
			if ( function_exists( 'pll_the_languages' ) ) {
				$raw = pll_the_languages(
					array(
						'raw'           => 1,
						'hide_if_empty' => 0,
						'echo'          => 0,
					)
				);
				if ( is_array( $raw ) && array() !== $raw ) {
					return $this->map_rows( $raw, $default );
				}
			}
			return array();
		} catch ( \Throwable $exception ) {
			return array();
		}
	}

	/**
	 * @param array<mixed> $raw     Provider rows.
	 * @param string       $default Default language code.
	 * @return list<Language>
	 */
	private function map_rows( array $raw, string $default ): array {
		$languages = array();
		foreach ( $raw as $key => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$code = (string) ( $row['language_code'] ?? $row['code'] ?? $row['slug'] ?? $key );
			if ( 1 !== preg_match( '/^[a-z]{2,3}(?:-[a-z0-9]{2,12})?$/i', $code ) ) {
				continue;
			}
			$code = strtolower( $code );
			$name = (string) ( $row['native_name'] ?? $row['name'] ?? $row['translated_name'] ?? $code );
			$url  = (string) ( $row['url'] ?? '' );
			$languages[] = new Language( $code, '' !== $name ? $name : $code, $this->prefix_from_url( $url ), $code === $default );
		}
		return $this->ensure_default( $languages, $default );
	}

	/**
	 * @return list<Language>
	 */
	private function from_prefixes(): array {
		$rows       = $this->settings->get()['prefixes'];
		$languages  = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$languages[] = new Language(
				(string) $row['code'],
				(string) $row['name'],
				(string) $row['prefix'],
				(bool) $row['default']
			);
		}
		return $languages;
	}

	/**
	 * First path segment of a language home URL, relative to this site.
	 *
	 * @param string $url Language home URL.
	 */
	private function prefix_from_url( string $url ): string {
		$path = trim( (string) parse_url( $url, PHP_URL_PATH ), '/' );
		$home = trim( ( new Site_Home() )->path(), '/' );
		if ( '' !== $home && str_starts_with( $path, $home ) ) {
			$path = trim( substr( $path, strlen( $home ) ), '/' );
		}
		if ( '' === $path || str_contains( $path, '/' ) ) {
			return '' === $path ? '' : (string) strtok( $path, '/' );
		}
		return $path;
	}

	/**
	 * @param list<Language> $languages Languages so far.
	 * @param string         $default   Preferred default code.
	 * @return list<Language>
	 */
	private function ensure_default( array $languages, string $default ): array {
		if ( array() === $languages ) {
			return array();
		}
		$found = false;
		foreach ( $languages as $language ) {
			if ( $language->default ) {
				$found = true;
				break;
			}
		}
		if ( $found ) {
			return $languages;
		}

		$updated = array();
		$marked  = false;
		foreach ( $languages as $language ) {
			$is_default = false;
			if ( ! $marked && ( '' === $default || $language->code === $default ) ) {
				$is_default = true;
				$marked     = true;
			}
			$updated[] = new Language( $language->code, $language->name, $language->prefix, $is_default );
		}
		if ( ! $marked && isset( $updated[0] ) ) {
			$first     = $updated[0];
			$updated[0] = new Language( $first->code, $first->name, $first->prefix, true );
		}
		return $updated;
	}

	/**
	 * @param int $post_id Post ID.
	 */
	private function wpml_post_language( int $post_id ): string {
		$type = function_exists( 'get_post_type' ) ? (string) get_post_type( $post_id ) : 'post';
		$code = apply_filters(
			'wpml_element_language_code',
			null,
			array(
				'element_id'   => $post_id,
				'element_type' => 'post_' . $type,
			)
		);
		if ( ! is_string( $code ) || '' === $code ) {
			$code = apply_filters(
				'wpml_element_language_code',
				null,
				array(
					'element_id'   => $post_id,
					'element_type' => 'post',
				)
			);
		}
		return is_string( $code ) ? strtolower( $code ) : '';
	}
}

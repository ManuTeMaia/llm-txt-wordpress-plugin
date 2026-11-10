<?php
/**
 * Chooses which cached llms file a public request may read.
 *
 * @package LlmsTxt
 */

namespace LlmsTxt\Http;

use LlmsTxt\Language\Catalog_Result;

/**
 * Path language wins. A multilingual plugin's current language is only a fallback
 * for a directory that plugin already removed from the path. Query parameters are ignored.
 */
final class Document_Request {
	public function __construct( private Request_Matcher $matcher ) {
	}

	/**
	 * @param string|null $active_language Language WPML or Polylang already resolved, or null.
	 * @return array{kind:string,language:?string}|null
	 */
	public function resolve( string $request_uri, string $home_path, ?string $active_language, Catalog_Result $catalog, bool $per_language_files ): ?array {
		$matched = $this->matcher->match( $request_uri, $home_path );
		if ( null === $matched ) {
			return null;
		}

		return $this->apply( $matched, $active_language, $catalog, $per_language_files );
	}

	/**
	 * @param array{kind:string,language:?string} $matched            Path match.
	 * @param string|null                         $active_language    Language WPML or Polylang already resolved.
	 * @return array{kind:string,language:?string}
	 */
	public function apply( array $matched, ?string $active_language, Catalog_Result $catalog, bool $per_language_files ): array {
		if ( ! $per_language_files ) {
			$matched['language'] = null;
			return $matched;
		}
		if ( null !== $matched['language'] ) {
			return $matched;
		}

		$active = $this->usable_active_language( $active_language, $catalog );
		if ( null !== $active ) {
			$matched['language'] = $active;
		}

		return $matched;
	}

	/**
	 * A non-default catalog language recovered after the directory was stripped.
	 *
	 * The default language stays null so /llms.txt keeps the root file, including
	 * when that root file contains every language.
	 *
	 * @param string|null $active Language reported by the multilingual plugin.
	 */
	private function usable_active_language( ?string $active, Catalog_Result $catalog ): ?string {
		if ( ! is_string( $active ) ) {
			return null;
		}
		$active = strtolower( $active );
		if ( 1 !== preg_match( '/^[a-z]{2,3}(?:-[a-z0-9]{2,12})?$/', $active ) ) {
			return null;
		}
		if ( $active === strtolower( $catalog->default_code() ) || ! $catalog->has( $active ) ) {
			return null;
		}

		return $active;
	}
}

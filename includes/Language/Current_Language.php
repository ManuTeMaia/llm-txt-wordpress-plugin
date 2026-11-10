<?php
/**
 * Language WPML or Polylang has already chosen for this request.
 *
 * @package LlmsTxt
 */

namespace LlmsTxt\Language;

/**
 * Reads the plugin API only. It does not look at query parameters.
 */
final class Current_Language {
	/**
	 * Active language code, or null when no multilingual plugin has one.
	 */
	public function code(): ?string {
		$code = $this->from_wpml();
		if ( null === $code ) {
			$code = $this->from_polylang();
		}
		if ( ! is_string( $code ) ) {
			return null;
		}

		$code = strtolower( trim( $code ) );
		if ( '' === $code || 'all' === $code ) {
			return null;
		}

		return $code;
	}

	/**
	 * WPML's current language, when its public filter is present.
	 */
	private function from_wpml(): ?string {
		if ( ! defined( 'ICL_SITEPRESS_VERSION' ) && ! ( function_exists( 'has_filter' ) && has_filter( 'wpml_current_language' ) ) ) {
			return null;
		}

		$code = apply_filters( 'wpml_current_language', null );
		return is_string( $code ) ? $code : null;
	}

	/**
	 * Polylang's current language slug.
	 */
	private function from_polylang(): ?string {
		if ( ! function_exists( 'pll_current_language' ) ) {
			return null;
		}

		$code = pll_current_language( 'slug' );
		return is_string( $code ) ? $code : null;
	}
}

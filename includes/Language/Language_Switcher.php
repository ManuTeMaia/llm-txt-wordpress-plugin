<?php
/**
 * Switches the active WPML language around a callback and always restores it.
 *
 * @package LlmsTxt
 */

namespace LlmsTxt\Language;

/**
 * WPML filters queries by the current language. Polylang is handled with the lang query arg.
 */
final class Language_Switcher {
	/**
	 * Run a callback in a language. Null asks WPML for every language.
	 *
	 * @param string|null $code     Language code, or null for all languages.
	 * @param callable    $callback Work to run while the language is active.
	 */
	public function run( ?string $code, callable $callback ): mixed {
		$switched = false;
		$previous = null;
		if ( $this->wpml_loaded() ) {
			$previous = apply_filters( 'wpml_current_language', null );
			do_action( 'wpml_switch_language', $code ?? 'all' );
			$switched = true;
		}

		try {
			return $callback();
		} finally {
			if ( $switched ) {
				do_action( 'wpml_switch_language', $previous );
			}
		}
	}

	/**
	 * Whether WPML's public language API is present.
	 */
	private function wpml_loaded(): bool {
		return defined( 'ICL_SITEPRESS_VERSION' ) || ( function_exists( 'has_filter' ) && has_filter( 'wpml_switch_language' ) );
	}
}

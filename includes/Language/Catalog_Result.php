<?php
/**
 * Languages detected for the current request.
 *
 * @package LlmsTxt
 */

namespace LlmsTxt\Language;

/**
 * Provider name plus the language list.
 */
final class Catalog_Result {
	/**
	 * @param string         $provider  wpml, polylang, or prefix.
	 * @param list<Language> $languages Detected languages.
	 */
	public function __construct(
		public readonly string $provider,
		public readonly array $languages
	) {
	}

	/**
	 * Default language code, or an empty string when nothing was detected.
	 */
	public function default_code(): string {
		foreach ( $this->languages as $language ) {
			if ( $language->default ) {
				return $language->code;
			}
		}

		return isset( $this->languages[0] ) ? $this->languages[0]->code : '';
	}

	/**
	 * @param string $code Language code to look up.
	 */
	public function has( string $code ): bool {
		$code = strtolower( $code );
		foreach ( $this->languages as $language ) {
			if ( strtolower( $language->code ) === $code ) {
				return true;
			}
		}

		return false;
	}
}

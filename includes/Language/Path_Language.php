<?php
/**
 * Guess a language from the first URL segment.
 *
 * @package LlmsTxt
 */

namespace LlmsTxt\Language;

/**
 * Fallback used when neither WPML nor Polylang is active.
 */
final class Path_Language {
	/**
	 * @param string                                                                 $path     URL path.
	 * @param list<array{code:string,prefix:string,name?:string,default?:bool}>    $prefixes Configured prefixes.
	 * @param string                                                                 $default  Code to use when no prefix matches.
	 */
	public function match( string $path, array $prefixes, string $default ): string {
		$path  = trim( $path, '/' );
		$first = '';
		if ( '' !== $path ) {
			$segment = strtok( $path, '/' );
			$first   = strtolower( is_string( $segment ) ? $segment : '' );
		}

		foreach ( $prefixes as $prefix ) {
			$value = strtolower( (string) ( $prefix['prefix'] ?? '' ) );
			if ( '' === $value ) {
				continue;
			}
			if ( $first === $value ) {
				return (string) $prefix['code'];
			}
		}

		return $default;
	}
}

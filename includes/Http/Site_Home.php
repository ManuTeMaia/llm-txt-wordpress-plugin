<?php
/**
 * Home path without a multilingual plugin's language directory.
 *
 * @package LlmsTxt
 */

namespace LlmsTxt\Http;

/**
 * WPML and Polylang filter home_url() so a Russian request reports /ru/ as the site root.
 * The stored home option stays at the real install path.
 */
final class Site_Home {
	/**
	 * Path component of the site home, such as / or /blog.
	 */
	public function path(): string {
		$home = '';
		if ( function_exists( 'get_option' ) ) {
			$stored = get_option( 'home' );
			if ( is_string( $stored ) ) {
				$home = $stored;
			}
		}
		if ( '' === $home && function_exists( 'home_url' ) ) {
			$home = (string) home_url( '/' );
		}

		$path = function_exists( 'wp_parse_url' ) ? wp_parse_url( $home, PHP_URL_PATH ) : parse_url( $home, PHP_URL_PATH );
		if ( ! is_string( $path ) || '' === $path ) {
			return '/';
		}

		return $path;
	}
}

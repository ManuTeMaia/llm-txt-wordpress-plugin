<?php
/**
 * Matches /llms.txt URLs, including language prefixes and subdirectory installs.
 *
 * @package LlmsTxt
 */

namespace LlmsTxt\Http;

/**
 * Pure request matching so rewrite misses still serve the files.
 */
final class Request_Matcher {
	/**
	 * @param string $request_uri Request URI, including the query string if present.
	 * @param string $home_path   Path component of the site's home URL.
	 * @return array{kind:string,language:?string}|null
	 */
	public function match( string $request_uri, string $home_path ): ?array {
		$path = parse_url( $request_uri, PHP_URL_PATH );
		if ( ! is_string( $path ) || '' === $path ) {
			return null;
		}

		$path = rawurldecode( $path );
		$home = '/' . trim( $home_path, '/' );
		if ( '/' === $home ) {
			$relative = $path;
		} elseif ( str_starts_with( $path, $home . '/' ) || $path === $home ) {
			$relative = substr( $path, strlen( $home ) );
			if ( '' === $relative ) {
				$relative = '/';
			}
		} else {
			return null;
		}

		if ( ! str_starts_with( $relative, '/' ) ) {
			$relative = '/' . $relative;
		}

		if ( 1 !== preg_match( '#^/(?:([a-z]{2,3}(?:-[a-z0-9]{2,12})?)/)?llms(-full)?\.txt/?$#i', $relative, $matches ) ) {
			return null;
		}

		$language = isset( $matches[1] ) && '' !== $matches[1] ? strtolower( $matches[1] ) : null;
		$kind     = isset( $matches[2] ) && '' !== $matches[2] ? 'full' : 'index';

		return array(
			'kind'     => $kind,
			'language' => $language,
		);
	}
}

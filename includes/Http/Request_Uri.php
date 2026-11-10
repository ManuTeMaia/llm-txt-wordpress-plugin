<?php
/**
 * Remembers the request path from the moment this plugin loads.
 *
 * @package LlmsTxt
 */

namespace LlmsTxt\Http;

/**
 * WPML can remove a language directory from REQUEST_URI before template_redirect.
 * The first value is the path the browser asked for.
 */
final class Request_Uri {
	private static ?string $captured = null;

	/**
	 * Store REQUEST_URI once. Later changes are ignored.
	 */
	public static function capture(): void {
		if ( null !== self::$captured ) {
			return;
		}

		$uri            = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- only the URL path is parsed, and the snapshot is taken before other plugins rewrite it.
		self::$captured = $uri;
	}

	/**
	 * Original request URI, including a query string when one was present.
	 */
	public static function original(): string {
		self::capture();
		return self::$captured ?? '';
	}
}

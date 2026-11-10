<?php
/**
 * Rules for an optional update manifest. No network calls.
 *
 * @package LlmsTxt
 */

namespace LlmsTxt\Update;

/**
 * HTTPS only, package on the same host, and a sha256 checksum is required.
 */
final class Manifest {
	/**
	 * @param string $manifest_url Saved manifest URL.
	 */
	public static function is_acceptable_url( string $manifest_url ): bool {
		$parts = parse_url( $manifest_url );
		if ( ! is_array( $parts ) ) {
			return false;
		}
		$scheme = strtolower( (string) ( $parts['scheme'] ?? '' ) );
		$host   = strtolower( (string) ( $parts['host'] ?? '' ) );
		return 'https' === $scheme && '' !== $host && ! isset( $parts['user'] ) && ! isset( $parts['pass'] );
	}

	/**
	 * @param string $manifest_url Manifest URL.
	 * @param string $package_url  Zip URL from the manifest.
	 */
	public static function same_host( string $manifest_url, string $package_url ): bool {
		$manifest = self::host( $manifest_url );
		$package  = self::host( $package_url );
		return '' !== $manifest && $manifest === $package;
	}

	/**
	 * @param string $value Claimed checksum.
	 */
	public static function is_sha256( string $value ): bool {
		return 1 === preg_match( '/^[a-f0-9]{64}$/', strtolower( $value ) );
	}

	/**
	 * @param string $url Absolute URL.
	 */
	private static function host( string $url ): string {
		$parts = parse_url( $url );
		if ( ! is_array( $parts ) ) {
			return '';
		}
		return strtolower( (string) ( $parts['host'] ?? '' ) );
	}
}

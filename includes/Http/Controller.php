<?php
/**
 * Serves the text files on the front end.
 *
 * @package LlmsTxt
 */

namespace LlmsTxt\Http;

use LlmsTxt\Cache\Store;
use LlmsTxt\Language\Catalog;
use LlmsTxt\Settings;

/**
 * Dynamic text/plain responses, with a URI fallback when rewrites are stale.
 */
final class Controller {
	public function register_hooks(): void {
		add_action( 'template_redirect', array( $this, 'serve' ), 0 );
	}

	/**
	 * Send the document or return so WordPress can continue.
	 */
	public function serve(): void {
		$match = $this->match_request();
		if ( null === $match ) {
			return;
		}

		$settings = ( new Settings() )->get();
		if ( ! $settings['enabled'] ) {
			$this->not_found();
		}
		if ( 'full' === $match['kind'] && ! $settings['full_enabled'] ) {
			$this->not_found();
		}
		// One combined file ignores a /ru/ prefix. WPML removes that prefix before
		// rewrite matching, so the original URL is the only place the language remains.
		if ( 'paths' !== $settings['language_mode'] ) {
			$match['language'] = null;
		} elseif ( null !== $match['language'] && ! ( new Catalog( new Settings() ) )->detect()->has( $match['language'] ) ) {
			$this->not_found();
		}

		$key  = $match['kind'] . '_' . ( $match['language'] ?? 'root' );
		$body = ( new Store() )->get( $key );
		if ( null === $body ) {
			$this->unavailable();
		}

		$this->send( $body, (int) $settings['cache_max_age'] );
	}

	/**
	 * @return array{kind:string,language:?string}|null
	 */
	private function match_request(): ?array {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- only the URL path is parsed.
		$home = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		// The path is the only input. Query parameters cannot select a language or a file.
		return ( new Request_Matcher() )->match( $uri, $home );
	}

	/**
	 * Plain 404 that does not fall through to the theme.
	 */
	private function not_found(): void {
		status_header( 404 );
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' );
		header( 'X-Content-Type-Options: nosniff' );
		echo "Not found.\n";
		exit;
	}

	/**
	 * A cold cache is filled by cron, admin, or a content hook, never by this request.
	 */
	private function unavailable(): void {
		status_header( 503 );
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Robots-Tag: noindex' );
		header( 'Retry-After: 60' );
		header( 'Cache-Control: no-store' );
		echo "llms.txt is being prepared. Try again shortly.\n";
		exit;
	}

	/**
	 * @param string $body    Document body.
	 * @param int    $max_age Cache-Control max-age.
	 */
	private function send( string $body, int $max_age ): void {
		$etag = '"' . md5( $body ) . '"';
		if ( $this->etag_matches( $etag ) ) {
			status_header( 304 );
			header( 'Content-Type: text/plain; charset=utf-8' );
			header( 'ETag: ' . $etag );
			header( 'X-Robots-Tag: noindex' );
			exit;
		}

		status_header( 200 );
		header_remove( 'Expires' );
		header_remove( 'Pragma' );
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Robots-Tag: noindex' );
		if ( $max_age <= 0 ) {
			header( 'Cache-Control: no-cache, must-revalidate' );
		} else {
			header( 'Cache-Control: public, max-age=' . $max_age . ', must-revalidate' );
		}
		header( 'ETag: ' . $etag );
		// text/plain document. Escaping would re-encode characters the file is required to contain.
		echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * @param string $etag Quoted MD5.
	 */
	private function etag_matches( string $etag ): bool {
		$offered = isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) ? (string) wp_unslash( $_SERVER['HTTP_IF_NONE_MATCH'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- stripped to ETag characters on the next line.
		$offered = preg_replace( '/[^A-Za-z0-9",=\/\s*-]/', '', $offered ) ?? '';
		if ( '' === trim( $offered ) ) {
			return false;
		}
		foreach ( explode( ',', $offered ) as $part ) {
			$part = trim( $part );
			if ( '*' === $part || $part === $etag || 'W/' . $etag === $part ) {
				return true;
			}
		}
		return false;
	}
}

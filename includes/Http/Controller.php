<?php
/**
 * Serves the text files on the front end.
 *
 * @package LlmsTxt
 */

namespace LlmsTxt\Http;

use LlmsTxt\Cache\Store;
use LlmsTxt\Cron\Scheduler;
use LlmsTxt\Language\Catalog;
use LlmsTxt\Language\Current_Language;
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
		$matcher = new Request_Matcher();
		$matched = $matcher->match( Request_Uri::original(), ( new Site_Home() )->path() );
		if ( null === $matched ) {
			return;
		}

		$settings = ( new Settings() )->get();
		if ( ! $settings['enabled'] ) {
			$this->not_found();
		}
		if ( 'full' === $matched['kind'] && ! $settings['full_enabled'] ) {
			$this->not_found();
		}

		$match     = $this->select_language( $matcher, $matched, $settings );
		$key       = $match['kind'] . '_' . ( $match['language'] ?? 'root' );
		$store     = new Store();
		$snapshot  = $store->snapshot( $key );
		$queued    = (bool) wp_next_scheduled( Scheduler::ONCE );
		$decision  = ( new Serve_Decision() )->decide( $snapshot['hot'], $snapshot['durable'], $queued );
		if ( $decision['rebuild'] ) {
			( new Scheduler() )->schedule_once();
		}
		if ( 503 === $decision['status'] || ! is_string( $decision['body'] ) ) {
			$this->unavailable();
		}
		if ( null === $snapshot['hot'] ) {
			$store->reheat( $key, $decision['body'] );
		}

		$this->send( $decision['body'], (int) $settings['cache_max_age'] );
	}

	/**
	 * Language of the cached file. Query parameters cannot select it.
	 *
	 * The home path compared above is the stored site URL. home_url() is filtered by
	 * WPML and Polylang, so /ru/llms.txt was reduced to /llms.txt and the root file was served.
	 * The request URI was captured at plugin load, before that directory can be removed.
	 * When the captured path has no directory, the current WPML or Polylang language is used
	 * if it is a different known language.
	 *
	 * @param array{kind:string,language:?string} $matched  Path match against the stored home.
	 * @param array<string, mixed>                $settings Clean settings.
	 * @return array{kind:string,language:?string}
	 */
	private function select_language( Request_Matcher $matcher, array $matched, array $settings ): array {
		if ( 'paths' !== $settings['language_mode'] ) {
			$matched['language'] = null;
			return $matched;
		}

		$catalog = ( new Catalog( new Settings() ) )->detect();
		$active  = null === $matched['language'] ? ( new Current_Language() )->code() : null;
		$match   = ( new Document_Request( $matcher ) )->apply( $matched, $active, $catalog, true );
		if ( null !== $match['language'] && ! $catalog->has( $match['language'] ) ) {
			$this->not_found();
		}

		return $match;
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

<?php
/**
 * Serves the text files on the front end.
 *
 * @package LlmsTxt
 */

namespace LlmsTxt\Http;

use LlmsTxt\Cache\Store;
use LlmsTxt\Generator;
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
		$store = new Store();
		$body = $store->get( $key );
		if ( null === $body ) {
			$generator = Generator::make();
			$body      = 'full' === $match['kind']
				? $generator->render_full( $match['language'] )
				: $generator->render_index( $match['language'] );
			$store->set( $key, $body );
		}

		$this->send( $body, (int) $settings['cache_max_age'] );
	}

	/**
	 * @return array{kind:string,language:?string}|null
	 */
	private function match_request(): ?array {
		$uri      = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$home     = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		$from_uri = ( new Request_Matcher() )->match( $uri, $home );

		$which = get_query_var( 'llms_txt' );
		if ( is_string( $which ) && in_array( $which, array( 'index', 'full' ), true ) ) {
			$lang     = get_query_var( 'llms_lang' );
			$language = is_string( $lang ) && '' !== $lang ? strtolower( $lang ) : null;
			if ( null === $language && is_array( $from_uri ) && null !== $from_uri['language'] ) {
				$language = $from_uri['language'];
			}
			$kind = $which;
			if ( is_array( $from_uri ) && 'full' === $from_uri['kind'] ) {
				$kind = 'full';
			}
			return array(
				'kind'     => $kind,
				'language' => $language,
			);
		}

		return $from_uri;
	}

	/**
	 * Plain 404 that does not fall through to the theme.
	 */
	private function not_found(): void {
		status_header( 404 );
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'X-Robots-Tag: noindex' );
		echo "Not found.\n";
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
		echo $body;
		exit;
	}

	/**
	 * @param string $etag Quoted MD5.
	 */
	private function etag_matches( string $etag ): bool {
		$offered = isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) ? (string) wp_unslash( $_SERVER['HTTP_IF_NONE_MATCH'] ) : '';
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

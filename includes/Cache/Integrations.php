<?php
/**
 * Asks popular page caches to forget the generated URLs.
 *
 * @package LlmsTxt
 */

namespace LlmsTxt\Cache;

/**
 * Best-effort purges. Each integration is skipped when its plugin is absent.
 */
final class Integrations {
	/**
	 * Purge the public llms.txt URLs from caches that expose a function or action.
	 */
	public function purge(): void {
		$urls = array(
			home_url( '/llms.txt' ),
			home_url( '/llms-full.txt' ),
		);
		$urls = apply_filters( 'llms_txt_purge_urls', $urls );
		if ( ! is_array( $urls ) ) {
			return;
		}

		foreach ( $urls as $url ) {
			if ( ! is_string( $url ) || '' === $url ) {
				continue;
			}
			if ( function_exists( 'rocket_clean_files' ) ) {
				rocket_clean_files( $url );
			}
			if ( function_exists( 'w3tc_flush_url' ) ) {
				w3tc_flush_url( $url );
			}
			do_action( 'litespeed_purge_url', $url );
		}
	}
}

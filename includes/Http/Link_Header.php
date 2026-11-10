<?php
/**
 * Advertises llms.txt with a describedby link relation.
 *
 * @package LlmsTxt
 */

namespace LlmsTxt\Http;

use LlmsTxt\Settings;

/**
 * Adds the HTTP Link header recommended by llmstxt.org.
 */
final class Link_Header {
	public function register_hooks(): void {
		add_action( 'send_headers', array( $this, 'send' ) );
	}

	/**
	 * Attach the header to ordinary front-end responses.
	 */
	public function send(): void {
		$settings = ( new Settings() )->get();
		if ( ! $settings['enabled'] || ! $settings['link_header'] ) {
			return;
		}
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || headers_sent() ) {
			return;
		}

		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- only the URL path is parsed.
		$home = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		if ( null !== ( new Request_Matcher() )->match( $uri, $home ) ) {
			return;
		}

		$url = esc_url_raw( home_url( '/llms.txt' ) );
		if ( '' === $url || 1 === preg_match( '/[\r\n]/', $url ) ) {
			return;
		}
		header( 'Link: <' . $url . '>; rel="describedby"', false );
	}
}

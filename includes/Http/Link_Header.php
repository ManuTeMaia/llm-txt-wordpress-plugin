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

		if ( null !== ( new Request_Matcher() )->match( Request_Uri::original(), ( new Site_Home() )->path() ) ) {
			return;
		}

		$url = esc_url_raw( home_url( '/llms.txt' ) );
		if ( '' === $url || 1 === preg_match( '/[\r\n]/', $url ) ) {
			return;
		}
		header( 'Link: <' . $url . '>; rel="describedby"', false );
	}
}

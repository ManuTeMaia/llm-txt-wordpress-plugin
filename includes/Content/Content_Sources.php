<?php
/**
 * Chooses which HTML is the published page.
 *
 * @package LlmsTxt
 */

namespace LlmsTxt\Content;

/**
 * The rendered document wins over raw builder JSON, which can stay on an old draft.
 */
final class Content_Sources {
	/**
	 * @param string $rendered HTML Elementor would print for this request.
	 * @param string $legacy   HTML parsed from the post's stored _elementor_data.
	 */
	public static function html( string $rendered, string $legacy ): string {
		if ( self::has_text( $rendered ) ) {
			return $rendered;
		}
		return $legacy;
	}

	/**
	 * @param string $html Candidate HTML.
	 */
	public static function has_text( string $html ): bool {
		return '' !== trim( strip_tags( $html ) );
	}
}

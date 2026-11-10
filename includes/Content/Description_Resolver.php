<?php
/**
 * Short description for an llms.txt link.
 *
 * @package LlmsTxt
 */

namespace LlmsTxt\Content;

use LlmsTxt\Document\Markdown_Text;

/**
 * Prefers the Yoast meta description, then the excerpt, then the content.
 */
final class Description_Resolver {
	public function __construct( private Elementor_Extractor $elementor ) {
	}

	/**
	 * @param int $post_id Post ID.
	 * @param int $length  Maximum characters.
	 */
	public function for_post( int $post_id, int $length ): string {
		$text = $this->yoast_description( $post_id );
		if ( '' === $text ) {
			$post = get_post( $post_id );
			if ( $post instanceof \WP_Post ) {
				$raw  = '' !== $post->post_excerpt ? $post->post_excerpt : $post->post_content;
				$text = trim( (string) $raw );
			}
		}
		if ( '' === trim( strip_tags( $text ) ) ) {
			$text = $this->elementor_text( $post_id );
		}

		$text = (string) apply_filters( 'llms_txt_post_description', $text, $post_id );
		return Markdown_Text::single_line( $text, $length );
	}

	/**
	 * @param int $post_id Post ID.
	 */
	private function yoast_description( int $post_id ): string {
		$description = get_post_meta( $post_id, '_yoast_wpseo_metadesc', true );
		if ( ! is_string( $description ) ) {
			return '';
		}
		$description = trim( $description );
		if ( '' === $description ) {
			return '';
		}
		if ( function_exists( 'wpseo_replace_vars' ) ) {
			$post = get_post( $post_id );
			if ( $post instanceof \WP_Post ) {
				$replaced = wpseo_replace_vars( $description, $post );
				if ( is_string( $replaced ) ) {
					$description = $replaced;
				}
			}
		}
		return $description;
	}

	/**
	 * @param int $post_id Post ID.
	 */
	private function elementor_text( int $post_id ): string {
		$mode = get_post_meta( $post_id, '_elementor_edit_mode', true );
		if ( 'builder' !== $mode ) {
			return '';
		}
		$raw = get_post_meta( $post_id, '_elementor_data', true );
		if ( ! is_string( $raw ) && ! is_array( $raw ) ) {
			return '';
		}
		return $this->elementor->to_html( $raw );
	}
}

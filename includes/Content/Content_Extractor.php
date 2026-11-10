<?php
/**
 * Clean Markdown for one post, preferring Elementor's stored document.
 *
 * @package LlmsTxt
 */

namespace LlmsTxt\Content;

/**
 * Strips shortcode and builder chrome before the HTML conversion.
 */
final class Content_Extractor {
	public function __construct(
		private Html_To_Markdown $markdown,
		private Elementor_Extractor $elementor
	) {
	}

	/**
	 * @param \WP_Post $post Post to read.
	 */
	public function markdown_for_post( \WP_Post $post ): string {
		$built = $this->from_elementor( $post );
		if ( '' === $built ) {
			$html  = $this->render_content( $post );
			$built = $this->markdown->convert( $html, (string) get_permalink( $post ) );
		}

		$built = trim( $built );
		return (string) apply_filters( 'llms_txt_post_markdown', $built, $post );
	}

	/**
	 * @param \WP_Post $post Post that might be built with Elementor.
	 */
	private function from_elementor( \WP_Post $post ): string {
		$mode = get_post_meta( $post->ID, '_elementor_edit_mode', true );
		if ( 'builder' !== $mode ) {
			return '';
		}
		$raw = get_post_meta( $post->ID, '_elementor_data', true );
		if ( ! is_string( $raw ) && ! is_array( $raw ) ) {
			return '';
		}
		$html = $this->elementor->to_html( $raw );
		if ( '' === trim( strip_tags( $html ) ) ) {
			return '';
		}
		return $this->markdown->convert( $html, (string) get_permalink( $post ) );
	}

	/**
	 * @param \WP_Post $post Classic or shortcode content.
	 */
	private function render_content( \WP_Post $post ): string {
		$previous = $GLOBALS['post'] ?? null;
		$GLOBALS['post'] = $post;
		setup_postdata( $post );

		$content = (string) $post->post_content;
		$content = (string) preg_replace( '/\[\/?elementor-template[^\]]*\]/', '', $content );
		$html    = apply_filters( 'the_content', $content );

		wp_reset_postdata();
		if ( $previous instanceof \WP_Post ) {
			$GLOBALS['post'] = $previous;
			setup_postdata( $previous );
		}

		return is_string( $html ) ? $html : '';
	}
}

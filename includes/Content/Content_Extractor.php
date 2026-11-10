<?php
/**
 * Clean Markdown for the content Elementor actually publishes.
 *
 * @package LlmsTxt
 */

namespace LlmsTxt\Content;

/**
 * Prefers the front-end document over raw builder JSON, which can be an old draft.
 */
final class Content_Extractor {
	private Elementor_Renderer $renderer;

	public function __construct(
		private Html_To_Markdown $markdown,
		private Elementor_Extractor $elementor,
		?Elementor_Renderer $renderer = null
	) {
		$this->renderer = $renderer ?? Elementor_Renderer::make();
	}

	/**
	 * @param \WP_Post $post Post to read.
	 */
	public function markdown_for_post( \WP_Post $post ): string {
		$rendered = $this->renderer->render( $post );
		$legacy   = Content_Sources::has_text( $rendered ) ? '' : $this->from_elementor_json( $post );
		$html     = Content_Sources::html( $rendered, $legacy );
		if ( ! Content_Sources::has_text( $html ) ) {
			$html = $this->render_content( $post );
		}

		$built = trim( $this->markdown->convert( $html, (string) get_permalink( $post ) ) );
		return (string) apply_filters( 'llms_txt_post_markdown', $built, $post );
	}

	/**
	 * @param \WP_Post $post Post that might be built with Elementor.
	 */
	private function from_elementor_json( \WP_Post $post ): string {
		$mode = get_post_meta( $post->ID, '_elementor_edit_mode', true );
		if ( 'builder' !== $mode ) {
			return '';
		}
		$raw = get_post_meta( $post->ID, '_elementor_data', true );
		if ( ! is_string( $raw ) && ! is_array( $raw ) ) {
			return '';
		}
		return $this->elementor->to_html( $raw );
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

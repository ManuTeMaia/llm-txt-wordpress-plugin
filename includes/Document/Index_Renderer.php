<?php
/**
 * Renders an llms.txt document in the llmstxt.org order.
 *
 * @package LlmsTxt
 */

namespace LlmsTxt\Document;

/**
 * Produces the index file: H1, blockquote, intro, H2 sections, Optional last.
 */
final class Index_Renderer {
	private Markdown_Text $text;

	public function __construct( ?Markdown_Text $text = null ) {
		$this->text = $text ?? new Markdown_Text();
	}

	/**
	 * Render the document, including a trailing newline.
	 *
	 * @param Index_Model $model Document model.
	 */
	public function render( Index_Model $model ): string {
		$chunks   = array();
		$title    = Markdown_Text::plain( str_replace( array( "\r", "\n" ), ' ', $model->title ) );
		$chunks[] = '# ' . ( '' !== $title ? $title : 'Untitled' );

		$summary = trim( Markdown_Text::decode_entities( $model->summary ) );
		if ( '' !== $summary ) {
			$quoted = array();
			foreach ( preg_split( "/\r\n|\n|\r/", $summary ) ?: array() as $line ) {
				$quoted[] = '> ' . $line;
			}
			$chunks[] = implode( "\n", $quoted );
		}

		$intro = $this->text->strip_intro_headings( $model->intro );
		if ( '' !== $intro ) {
			$chunks[] = $intro;
		}

		$normal   = array();
		$optional = array();
		foreach ( $model->sections as $section ) {
			if ( ! $section instanceof Section_Model ) {
				continue;
			}
			$block = $this->render_section( $section );
			if ( '' === $block ) {
				continue;
			}
			if ( $section->optional ) {
				$optional[] = $block;
			} else {
				$normal[] = $block;
			}
		}

		foreach ( array_merge( $normal, $optional ) as $block ) {
			$chunks[] = $block;
		}

		return implode( "\n\n", $chunks ) . "\n";
	}

	/**
	 * @param Section_Model $section Section to render.
	 */
	private function render_section( Section_Model $section ): string {
		$heading = Markdown_Text::plain( $section->heading );
		$body    = $this->text->demote_headings( $section->body, 2 );
		if ( '' === $heading || '' === $body ) {
			return '';
		}

		return '## ' . $heading . "\n\n" . $body;
	}
}

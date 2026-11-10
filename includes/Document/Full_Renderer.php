<?php
/**
 * Renders llms-full.txt and enforces the size cap.
 *
 * @package LlmsTxt
 */

namespace LlmsTxt\Document;

/**
 * Expanded Markdown with a hard byte ceiling.
 */
final class Full_Renderer {
	/**
	 * @param Full_Model $model    Entries to render.
	 * @param int        $byte_cap Maximum file size in bytes.
	 */
	public function render( Full_Model $model, int $byte_cap ): string {
		$cap  = max( 2048, $byte_cap );
		$out  = ( new Index_Renderer() )->render( new Index_Model( $model->title, $model->summary, '', array() ) );
		$note = "\n---\n\n> Truncated: output reached the configured size cap. Raise the cap or include fewer pages in Settings → LLMs.txt.\n";

		$added     = 0;
		$truncated = false;
		foreach ( $model->entries as $entry ) {
			if ( ! $entry instanceof Full_Entry ) {
				continue;
			}
			$piece = "\n" . $this->render_entry( $entry );
			if ( strlen( $out ) + strlen( $piece ) + strlen( $note ) > $cap ) {
				if ( 0 === $added ) {
					$room = $cap - strlen( $out ) - strlen( $note ) - 1;
					if ( $room > 200 ) {
						$slice = substr( $piece, 0, $room );
						$slice = (string) preg_replace( "/\n[^\n]*$/", "\n", $slice );
						$out  .= $slice;
						++$added;
					}
				}
				$truncated = true;
				break;
			}
			$out .= $piece;
			++$added;
		}

		if ( $truncated ) {
			$out = rtrim( $out ) . $note;
		}
		if ( ! str_ends_with( $out, "\n" ) ) {
			$out .= "\n";
		}

		return $out;
	}

	/**
	 * @param Full_Entry $entry One page.
	 */
	private function render_entry( Full_Entry $entry ): string {
		$title = Markdown_Text::plain( str_replace( array( "\r", "\n" ), ' ', $entry->title ) );
		if ( '' === $title ) {
			$title = 'Untitled';
		}

		$lines   = array();
		$lines[] = '# ' . $title;
		$lines[] = '';
		$lines[] = 'URL: ' . trim( $entry->url );
		$lines[] = 'Last modified: ' . trim( $entry->modified );
		if ( '' !== trim( $entry->language ) ) {
			$lines[] = 'Language: ' . trim( $entry->language );
		}
		$lines[] = '';
		$body    = trim( $entry->body );
		if ( '' !== $body ) {
			$lines[] = $body;
			$lines[] = '';
		}
		$lines[] = '---';

		return implode( "\n", $lines );
	}
}

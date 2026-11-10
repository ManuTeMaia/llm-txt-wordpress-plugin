<?php
/**
 * Picks the Elementor document visitors actually see.
 *
 * @package LlmsTxt
 */

namespace LlmsTxt\Content;

/**
 * A theme-builder single template replaces the page. Revisions and autosaves never do.
 */
final class Published_Document_Selector {
	/**
	 * @param int        $post_id      Published post being described.
	 * @param list<int>  $template_ids Single-location templates that match this post, most specific first.
	 */
	public function document_id( int $post_id, array $template_ids ): int {
		foreach ( $template_ids as $template_id ) {
			$template_id = (int) $template_id;
			if ( $template_id > 0 && $template_id !== $post_id ) {
				return $template_id;
			}
		}
		return $post_id;
	}

	/**
	 * Published posts only. Autosave revisions keep a copy of older builder data.
	 *
	 * @param string $status Post status.
	 * @param string $name   Post name, which contains "-autosave" for Elementor autosaves.
	 */
	public function is_published_source( string $status, string $name ): bool {
		if ( 'publish' !== $status ) {
			return false;
		}
		return ! str_contains( $name, '-autosave' );
	}
}

<?php
/**
 * Decides whether a post may appear in llms.txt.
 *
 * @package LlmsTxt
 */

namespace LlmsTxt\Content;

/**
 * Drops private, protected, opted-out, and noindex posts.
 */
final class Exclusion_Policy {
	/**
	 * @param Post_Snapshot $post            Post facts.
	 * @param bool          $respect_noindex Whether a noindex flag excludes the post.
	 */
	public function is_excluded( Post_Snapshot $post, bool $respect_noindex ): bool {
		if ( 'publish' !== $post->status ) {
			return true;
		}
		if ( '' !== $post->password ) {
			return true;
		}
		if ( $post->excluded ) {
			return true;
		}
		if ( $respect_noindex && $post->noindex ) {
			return true;
		}

		return false;
	}
}

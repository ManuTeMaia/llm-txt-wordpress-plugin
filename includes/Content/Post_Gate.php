<?php
/**
 * Applies the exclusion policy to a real WordPress post.
 *
 * @package LlmsTxt
 */

namespace LlmsTxt\Content;

/**
 * Password, privacy, the per-post checkbox, and Yoast noindex.
 */
final class Post_Gate {
	public function __construct( private Exclusion_Policy $policy ) {
	}

	/**
	 * @param int  $post_id          Post ID.
	 * @param bool $respect_noindex  Whether Yoast noindex excludes the post.
	 */
	public function allows( int $post_id, bool $respect_noindex ): bool {
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return false;
		}

		$snapshot = new Post_Snapshot(
			(string) $post->post_status,
			(string) $post->post_password,
			'1' === (string) get_post_meta( $post_id, '_llms_txt_exclude', true ),
			$respect_noindex && $this->is_noindex( $post )
		);
		$excluded = $this->policy->is_excluded( $snapshot, $respect_noindex );
		$excluded = (bool) apply_filters( 'llms_txt_exclude_post', $excluded, $post_id, $post );

		return ! $excluded;
	}

	/**
	 * Yoast's per-post robots flag, with the post-type default as a fallback.
	 *
	 * @param \WP_Post $post Post object.
	 */
	private function is_noindex( \WP_Post $post ): bool {
		if ( function_exists( 'YoastSEO' ) ) {
			try {
				$meta = YoastSEO()->meta->for_post( $post->ID );
				if ( is_object( $meta ) && isset( $meta->robots ) && is_array( $meta->robots ) ) {
					return 'noindex' === ( $meta->robots['index'] ?? '' );
				}
			} catch ( \Throwable $exception ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
				unset( $exception );
			}
		}

		$flag = (string) get_post_meta( $post->ID, '_yoast_wpseo_meta-robots-noindex', true );
		if ( '1' === $flag ) {
			return true;
		}
		if ( '2' === $flag ) {
			return false;
		}

		if ( class_exists( \WPSEO_Options::class ) ) {
			$key = 'noindex-' . $post->post_type;
			try {
				return (bool) \WPSEO_Options::get( $key, false );
			} catch ( \Throwable $exception ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
				unset( $exception );
			}
		}

		return false;
	}
}

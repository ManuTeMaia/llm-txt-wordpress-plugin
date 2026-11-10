<?php
/**
 * Loads the posts that belong in an automatic section.
 *
 * @package LlmsTxt
 */

namespace LlmsTxt\Content;

use LlmsTxt\Language\Language_Switcher;

/**
 * WP_Query wrapper that honors language, taxonomy, and the exclusion policy.
 */
final class Post_Repository {
	public function __construct(
		private Post_Gate $gate,
		private Language_Switcher $switcher
	) {
	}

	/**
	 * @param array<string, mixed> $section  Automatic section settings.
	 * @param string|null          $language Language code, or null for every language.
	 * @param string               $provider wpml, polylang, or prefix.
	 * @param bool                 $respect_noindex Whether noindex posts are removed.
	 * @return list<\WP_Post>
	 */
	public function query( array $section, ?string $language, string $provider, bool $respect_noindex ): array {
		$limit = max( 1, (int) ( $section['limit'] ?? 20 ) );
		$args  = array(
			'post_type'              => (string) ( $section['post_type'] ?? 'post' ),
			'post_status'            => 'publish',
			'posts_per_page'         => min( 200, $limit * 3 ),
			'orderby'                => $this->orderby( (string) ( $section['orderby'] ?? 'date' ), (string) ( $section['order'] ?? 'DESC' ) ),
			'order'                  => (string) ( $section['order'] ?? 'DESC' ),
			'no_found_rows'          => true,
			'ignore_sticky_posts'    => true,
			'has_password'           => false,
			'update_post_term_cache' => false,
			'suppress_filters'       => false,
		);

		$tax_query = $this->tax_query( $section );
		if ( array() !== $tax_query ) {
			$args['tax_query'] = $tax_query;
		}
		if ( in_array( $provider, array( 'wpml', 'polylang' ), true ) ) {
			$args['lang'] = $language ?? 'all';
		}

		$posts = $this->switcher->run(
			$language,
			static function () use ( $args ): array {
				$query = new \WP_Query( $args );
				return is_array( $query->posts ) ? $query->posts : array();
			}
		);

		$allowed = array();
		foreach ( $posts as $post ) {
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}
			if ( ! $this->gate->allows( (int) $post->ID, $respect_noindex ) ) {
				continue;
			}
			$allowed[] = $post;
			if ( count( $allowed ) >= $limit ) {
				break;
			}
		}

		return $allowed;
	}

	/**
	 * @param string $orderby date, modified, title, or menu_order.
	 * @param string $order   ASC or DESC.
	 * @return string|array<string, string>
	 */
	private function orderby( string $orderby, string $order ): string|array {
		if ( 'menu_order' === $orderby ) {
			return array(
				'menu_order' => 'ASC' === $order ? 'ASC' : 'DESC',
				'title'      => 'ASC',
			);
		}
		return in_array( $orderby, array( 'date', 'modified', 'title' ), true ) ? $orderby : 'date';
	}

	/**
	 * @param array<string, mixed> $section Section settings.
	 * @return list<array<string, mixed>>
	 */
	private function tax_query( array $section ): array {
		$taxonomy = (string) ( $section['taxonomy'] ?? '' );
		$terms    = (string) ( $section['terms'] ?? '' );
		if ( '' === $taxonomy || '' === trim( $terms ) ) {
			return array();
		}
		if ( function_exists( 'taxonomy_exists' ) && ! taxonomy_exists( $taxonomy ) ) {
			return array();
		}
		$slugs = array_values(
			array_filter(
				array_map( 'trim', explode( ',', $terms ) ),
				static fn( string $slug ): bool => '' !== $slug
			)
		);
		if ( array() === $slugs ) {
			return array();
		}

		return array(
			array(
				'taxonomy' => $taxonomy,
				'field'    => 'slug',
				'terms'    => $slugs,
			),
		);
	}
}

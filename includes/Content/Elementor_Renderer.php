<?php
/**
 * Renders the Elementor document that the front end prints for a published post.
 *
 * @package LlmsTxt
 */

namespace LlmsTxt\Content;

use LlmsTxt\Language\Language_Switcher;

/**
 * A full theme template can replace a page. A single-post template that only wraps the post cannot.
 */
final class Elementor_Renderer {
	private static bool $rendering = false;

	public function __construct( private Published_Document_Selector $selector ) {
	}

	public static function make(): self {
		return new self( new Published_Document_Selector() );
	}

	/**
	 * HTML for the published view of this post, or an empty string when Elementor is absent.
	 *
	 * @param \WP_Post $post Published post.
	 */
	public function render( \WP_Post $post ): string {
		if ( self::$rendering || \LlmsTxt\Budget::exceeded() || ! $this->elementor_loaded() || ! $this->selector->is_published_source( (string) $post->post_status, (string) $post->post_name ) ) {
			return '';
		}

		self::$rendering = true;
		try {
			return $this->in_post_language(
				$post,
				function () use ( $post ): string {
					return $this->render_in_language( $post );
				}
			);
		} catch ( \Throwable $exception ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'LLMs.txt Elementor render failed for post ' . $post->ID . ': ' . $exception->getMessage() );
			}
			return '';
		} finally {
			self::$rendering = false;
		}
	}

	/**
	 * @param \WP_Post $post Published post.
	 */
	private function render_in_language( \WP_Post $post ): string {
		$language  = $this->language_code( $post );
		$policy    = new Template_Policy();
		$templates = $this->with_singular_query(
			$post,
			function (): array {
				return $this->matching_template_ids();
			}
		);
		$template_id = $this->selector->document_id( (int) $post->ID, is_array( $templates ) ? $templates : array() );
		$payload     = null;
		if ( $template_id !== (int) $post->ID ) {
			$template_id = $this->translate_document( $template_id, $language );
			$embeds      = $this->template_embeds_post_content( $template_id );
			$rendered    = '';
			if ( ! $embeds ) {
				$rendered = $this->with_singular_query(
					$post,
					function () use ( $template_id ): string {
						return $this->document_html( $template_id );
					}
				);
			}
			$payload = array(
				'embeds_post_content' => $embeds,
				'html_by_language'    => array(
					( '' !== $language ? $language : 'default' ) => is_string( $rendered ) ? $rendered : '',
				),
			);
		}

		return $policy->html_for_post(
			array(
				'id'       => (int) $post->ID,
				'language' => $language,
				'own_html' => '',
			),
			$payload
		);
	}

	/**
	 * Map a theme template to the translation that belongs to this language.
	 *
	 * @param int    $document_id Template id in the default language.
	 * @param string $language    Post language code, or an empty string.
	 */
	private function translate_document( int $document_id, string $language ): int {
		if ( $document_id <= 0 || '' === $language ) {
			return $document_id;
		}
		if ( function_exists( 'pll_get_post' ) ) {
			$translated = pll_get_post( $document_id, $language );
			if ( is_numeric( $translated ) && (int) $translated > 0 ) {
				return (int) $translated;
			}
		}
		$type       = get_post_type( $document_id );
		$type       = is_string( $type ) && '' !== $type ? $type : 'elementor_library';
		$translated = apply_filters( 'wpml_object_id', $document_id, $type, true, $language );
		return is_numeric( $translated ) && (int) $translated > 0 ? (int) $translated : $document_id;
	}

	/**
	 * @param int $template_id Published theme template.
	 */
	private function template_embeds_post_content( int $template_id ): bool {
		$raw = get_post_meta( $template_id, '_elementor_data', true );
		if ( is_string( $raw ) ) {
			$decoded = json_decode( $raw, true );
			$raw     = is_array( $decoded ) ? $decoded : array();
		}
		if ( ! is_array( $raw ) ) {
			return false;
		}
		return ( new Template_Policy() )->embeds_post_content( $raw );
	}

	/**
	 * Front-end markup for one published document, then that document's saved elements.
	 *
	 * @param int $post_id Document id. A theme template id is not the page id.
	 */
	private function document_html( int $post_id ): string {
		try {
			$html = $this->builder_html( $post_id );
		} catch ( \Throwable $exception ) {
			$html = '';
		}
		if ( Content_Sources::has_text( $html ) ) {
			return $html;
		}
		$raw = get_post_meta( $post_id, '_elementor_data', true );
		if ( ! is_string( $raw ) && ! is_array( $raw ) ) {
			return '';
		}
		return ( new Elementor_Extractor() )->to_html( $raw );
	}

	/**
	 * @param int $post_id Elementor document id.
	 */
	private function builder_html( int $post_id ): string {
		if ( $post_id <= 0 || ! $this->elementor_loaded() ) {
			return '';
		}
		$status = get_post_status( $post_id );
		$name   = (string) get_post_field( 'post_name', $post_id );
		if ( ! is_string( $status ) || ! $this->selector->is_published_source( $status, $name ) ) {
			return '';
		}

		$frontend = \Elementor\Plugin::$instance->frontend;
		if ( ! is_object( $frontend ) || ! method_exists( $frontend, 'get_builder_content' ) ) {
			return '';
		}

		ob_start();
		$html = $frontend->get_builder_content( $post_id, false );
		$echoed = ob_get_clean();
		if ( is_string( $html ) && Content_Sources::has_text( $html ) ) {
			return $html;
		}
		return is_string( $echoed ) ? $echoed : '';
	}

	/**
	 * @return list<int>
	 */
	private function matching_template_ids(): array {
		if ( ! class_exists( \ElementorPro\Modules\ThemeBuilder\Module::class ) ) {
			return array();
		}
		$module = \ElementorPro\Modules\ThemeBuilder\Module::instance();
		if ( ! is_object( $module ) || ! method_exists( $module, 'get_conditions_manager' ) ) {
			return array();
		}
		$manager = $module->get_conditions_manager();
		if ( ! is_object( $manager ) || ! method_exists( $manager, 'get_documents_for_location' ) ) {
			return array();
		}
		if ( method_exists( $manager, 'clear_location_cache' ) ) {
			$manager->clear_location_cache();
		}

		$documents = $manager->get_documents_for_location( 'single' );
		if ( method_exists( $manager, 'clear_location_cache' ) ) {
			$manager->clear_location_cache();
		}
		if ( ! is_array( $documents ) ) {
			return array();
		}

		$ids = array();
		foreach ( $documents as $id => $document ) {
			$document_id = (int) $id;
			if ( is_object( $document ) && method_exists( $document, 'get_post' ) ) {
				$document_post = $document->get_post();
				if ( $document_post instanceof \WP_Post ) {
					$document_id = (int) $document_post->ID;
				}
			}
			$status = get_post_status( $document_id );
			$name   = (string) get_post_field( 'post_name', $document_id );
			if ( is_string( $status ) && $this->selector->is_published_source( $status, $name ) ) {
				$ids[] = $document_id;
			}
		}
		return $ids;
	}

	/**
	 * @param \WP_Post $subject  Post to pretend is the main query.
	 * @param callable $callback Work that reads Elementor display conditions.
	 */
	private function with_singular_query( \WP_Post $subject, callable $callback ): mixed {
		global $wp_query, $post;
		$previous_query = $wp_query;
		$previous_post  = $post instanceof \WP_Post ? $post : null;

		$query                    = new \WP_Query();
		$query->queried_object    = $subject;
		$query->queried_object_id = (int) $subject->ID;
		$query->is_singular       = true;
		$query->is_single         = 'page' !== $subject->post_type;
		$query->is_page           = 'page' === $subject->post_type;
		$query->is_attachment     = false;
		$query->is_archive        = false;
		$query->is_home           = false;
		$query->is_posts_page     = (int) get_option( 'page_for_posts' ) === (int) $subject->ID;
		$query->post              = $subject;
		$query->posts             = array( $subject );
		$query->post_count        = 1;
		$query->found_posts       = 1;
		$wp_query                 = $query;
		$post                     = $subject;
		$query->in_the_loop       = true;
		setup_postdata( $subject );

		try {
			return $callback();
		} finally {
			$wp_query = $previous_query;
			$post     = $previous_post;
		}
	}

	/**
	 * @param \WP_Post $post     Post whose language should be active.
	 * @param callable $callback Render callback.
	 */
	private function in_post_language( \WP_Post $post, callable $callback ): mixed {
		$code = $this->language_code( $post );
		if ( '' === $code ) {
			return $callback();
		}
		return ( new Language_Switcher() )->run( $code, $callback );
	}

	/**
	 * @param \WP_Post $post Post.
	 */
	private function language_code( \WP_Post $post ): string {
		if ( defined( 'ICL_SITEPRESS_VERSION' ) || ( function_exists( 'has_filter' ) && has_filter( 'wpml_element_language_code' ) ) ) {
			$code = apply_filters(
				'wpml_element_language_code',
				null,
				array(
					'element_id'   => (int) $post->ID,
					'element_type' => 'post_' . $post->post_type,
				)
			);
			if ( is_string( $code ) && '' !== $code ) {
				return strtolower( $code );
			}
		}
		if ( function_exists( 'pll_get_post_language' ) ) {
			$code = pll_get_post_language( (int) $post->ID, 'slug' );
			return is_string( $code ) ? strtolower( $code ) : '';
		}
		return '';
	}

	private function elementor_loaded(): bool {
		return class_exists( \Elementor\Plugin::class ) && isset( \Elementor\Plugin::$instance->frontend );
	}
}

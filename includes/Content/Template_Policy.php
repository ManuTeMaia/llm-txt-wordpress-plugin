<?php
/**
 * Decides when a theme-builder template is the page, and when it is only a layout.
 *
 * @package LlmsTxt
 */

namespace LlmsTxt\Content;

/**
 * Single-post templates that print the current post must not replace that post's body.
 */
final class Template_Policy {
	/**
	 * Widget types that output the current post instead of fixed page content.
	 */
	private const POST_CONTENT_WIDGETS = array(
		'theme-post-content',
		'theme-post-excerpt',
		'post-content',
	);

	/**
	 * @param array<mixed> $elementor_data Decoded _elementor_data for a theme template.
	 */
	public function embeds_post_content( array $elementor_data ): bool {
		$found = false;
		$walk  = static function ( mixed $node ) use ( &$walk, &$found ): void {
			if ( $found || ! is_array( $node ) ) {
				return;
			}
			if ( isset( $node['widgetType'] ) && is_string( $node['widgetType'] ) && in_array( $node['widgetType'], self::POST_CONTENT_WIDGETS, true ) ) {
				$found = true;
				return;
			}
			foreach ( $node as $child ) {
				if ( is_array( $child ) ) {
					$walk( $child );
				}
			}
		};
		$walk( $elementor_data );
		return $found;
	}

	/**
	 * HTML that should be stored for one post.
	 *
	 * A template with a post-content widget is the blog layout. Each post keeps its own
	 * body, in its own language. A template without that widget replaces the page, which
	 * is how the homepage is published, including a translated copy for a second language.
	 *
	 * @param array{id:int,language:string,own_html:string}                                      $post     Post being rendered.
	 * @param array{embeds_post_content:bool,html_by_language:array<string,string>}|null         $template Matched single template, or null.
	 */
	public function html_for_post( array $post, ?array $template ): string {
		if ( null !== $template && ! $template['embeds_post_content'] ) {
			$language = '' !== $post['language'] ? $post['language'] : 'default';
			$rendered = $template['html_by_language'][ $language ] ?? '';
			if ( Content_Sources::has_text( $rendered ) ) {
				return $rendered;
			}
		}
		return $post['own_html'];
	}
}

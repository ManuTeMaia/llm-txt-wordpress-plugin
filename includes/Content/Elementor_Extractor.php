<?php
/**
 * Pulls readable HTML out of Elementor's JSON document.
 *
 * @package LlmsTxt
 */

namespace LlmsTxt\Content;

/**
 * Walks _elementor_data and ignores menus, icons, and style settings.
 */
final class Elementor_Extractor {
	private const SKIP_WIDGETS = array(
		'nav-menu',
		'social-icons',
		'share-buttons',
		'search',
		'search-form',
		'shortcode',
		'menu-anchor',
		'icon',
		'spacer',
		'star-rating',
		'progress',
		'counter',
		'form',
		'login',
		'lottie',
		'video',
		'slides',
		'wp-widget',
		'sidebar',
		'theme-site-logo',
		'theme-site-title',
		'theme-post-title',
		'theme-post-excerpt',
		'theme-post-content',
		'theme-post-featured-image',
		'facebook-button',
		'facebook-comments',
		'facebook-embed',
		'facebook-page',
	);

	private const TEXT_KEYS = array(
		'editor',
		'title',
		'text',
		'description',
		'caption',
		'tab_content',
		'tab_title',
		'testimonial_content',
		'testimonial_name',
		'alert_title',
		'alert_description',
		'title_text',
		'description_text',
		'blockquote_content',
		'author_name',
		'html',
		'item_description',
	);

	private const SKIP_SETTING_KEYS = array(
		'image',
		'background_image',
		'background_overlay',
		'icon',
		'selected_icon',
		'__globals__',
		'link',
		'wp',
	);

	/**
	 * @param string|array<mixed> $data Elementor document JSON or a decoded array.
	 */
	public function to_html( string|array $data ): string {
		if ( is_string( $data ) ) {
			$decoded = json_decode( $data, true );
			if ( ! is_array( $decoded ) ) {
				return '';
			}
			$data = $decoded;
		}

		$chunks = array();
		$this->walk( $data, $chunks, 0 );
		$chunks = array_values( array_filter( $chunks, static fn( string $chunk ): bool => '' !== trim( $chunk ) ) );
		return implode( "\n\n", $chunks );
	}

	/**
	 * @param mixed        $node   Document node.
	 * @param list<string> $chunks Collected HTML chunks.
	 * @param int          $depth  Recursion guard.
	 */
	private function walk( mixed $node, array &$chunks, int $depth ): void {
		if ( $depth > 40 || ! is_array( $node ) ) {
			return;
		}
		if ( array_is_list( $node ) ) {
			foreach ( $node as $child ) {
				$this->walk( $child, $chunks, $depth + 1 );
			}
			return;
		}

		$widget = isset( $node['widgetType'] ) && is_string( $node['widgetType'] ) ? $node['widgetType'] : '';
		if ( '' !== $widget && in_array( $widget, self::SKIP_WIDGETS, true ) ) {
			return;
		}

		$is_widget = isset( $node['elType'] ) && 'widget' === $node['elType'];
		if ( $is_widget && isset( $node['settings'] ) && is_array( $node['settings'] ) ) {
			$this->extract_widget( $widget, $node['settings'], $chunks );
		}

		if ( isset( $node['elements'] ) ) {
			$this->walk( $node['elements'], $chunks, $depth + 1 );
		}
	}

	/**
	 * @param string               $widget   Widget type.
	 * @param array<string, mixed> $settings Widget settings.
	 * @param list<string>         $chunks   Output chunks.
	 */
	private function extract_widget( string $widget, array $settings, array &$chunks ): void {
		switch ( $widget ) {
			case 'heading':
				$title = isset( $settings['title'] ) ? (string) $settings['title'] : '';
				$size  = isset( $settings['header_size'] ) ? (string) $settings['header_size'] : 'h2';
				if ( ! in_array( $size, array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ), true ) ) {
					$size = 'h2';
				}
				if ( '' !== trim( strip_tags( $title ) ) ) {
					$chunks[] = '<' . $size . '>' . $title . '</' . $size . '>';
				}
				return;
			case 'text-editor':
				if ( isset( $settings['editor'] ) && is_string( $settings['editor'] ) && '' !== trim( strip_tags( $settings['editor'] ) ) ) {
					$chunks[] = $settings['editor'];
				}
				return;
			case 'html':
				if ( isset( $settings['html'] ) && is_string( $settings['html'] ) && '' !== trim( $settings['html'] ) ) {
					$chunks[] = $settings['html'];
				}
				return;
			case 'icon-list':
				$items = isset( $settings['icon_list'] ) && is_array( $settings['icon_list'] ) ? $settings['icon_list'] : array();
				$list  = '';
				foreach ( $items as $item ) {
					if ( ! is_array( $item ) ) {
						continue;
					}
					$text = isset( $item['text'] ) ? (string) $item['text'] : '';
					if ( '' === trim( strip_tags( $text ) ) ) {
						continue;
					}
					$list .= '<li>' . $text . '</li>';
				}
				if ( '' !== $list ) {
					$chunks[] = '<ul>' . $list . '</ul>';
				}
				return;
			case 'button':
				$text = trim( strip_tags( isset( $settings['text'] ) ? (string) $settings['text'] : '' ) );
				$url  = '';
				if ( isset( $settings['link'] ) && is_array( $settings['link'] ) && isset( $settings['link']['url'] ) ) {
					$url = (string) $settings['link']['url'];
				}
				if ( '' === $text ) {
					return;
				}
				$chunks[] = '' !== $url
					? '<p><a href="' . $this->esc( $url ) . '">' . $this->esc( $text ) . '</a></p>'
					: '<p>' . $this->esc( $text ) . '</p>';
				return;
			case 'divider':
				$chunks[] = '<hr>';
				return;
			case 'image':
				$url     = '';
				$alt     = '';
				$caption = isset( $settings['caption'] ) ? (string) $settings['caption'] : '';
				if ( isset( $settings['image'] ) && is_array( $settings['image'] ) ) {
					$url = isset( $settings['image']['url'] ) ? (string) $settings['image']['url'] : '';
					$alt = isset( $settings['image']['alt'] ) ? (string) $settings['image']['alt'] : '';
				}
				if ( '' !== $url ) {
					$chunks[] = '<img src="' . $this->esc( $url ) . '" alt="' . $this->esc( $alt ) . '">';
				}
				if ( '' !== trim( strip_tags( $caption ) ) ) {
					$chunks[] = '<p>' . $caption . '</p>';
				}
				return;
			case 'accordion':
			case 'toggle':
			case 'tabs':
			case 'nested-accordion':
				$tabs = isset( $settings['tabs'] ) && is_array( $settings['tabs'] ) ? $settings['tabs'] : array();
				foreach ( $tabs as $tab ) {
					if ( ! is_array( $tab ) ) {
						continue;
					}
					$title   = trim( strip_tags( (string) ( $tab['tab_title'] ?? $tab['title'] ?? '' ) ) );
					$content = (string) ( $tab['tab_content'] ?? $tab['content'] ?? '' );
					if ( '' !== $title ) {
						$chunks[] = '<h3>' . $this->esc( $title ) . '</h3>';
					}
					if ( '' !== trim( strip_tags( $content ) ) ) {
						$chunks[] = $content;
					}
				}
				return;
			default:
				$this->generic_text( $settings, $chunks );
		}
	}

	/**
	 * @param mixed        $node   Settings tree.
	 * @param list<string> $chunks Output chunks.
	 */
	private function generic_text( mixed $node, array &$chunks ): void {
		if ( ! is_array( $node ) ) {
			return;
		}
		if ( array_is_list( $node ) ) {
			foreach ( $node as $child ) {
				$this->generic_text( $child, $chunks );
			}
			return;
		}

		foreach ( $node as $key => $value ) {
			if ( ! is_string( $key ) || in_array( $key, self::SKIP_SETTING_KEYS, true ) ) {
				continue;
			}
			if ( is_string( $value ) && in_array( $key, self::TEXT_KEYS, true ) ) {
				$trimmed = trim( $value );
				if ( '' === $trimmed || '' === trim( strip_tags( $trimmed ) ) || $this->looks_like_url( $trimmed ) ) {
					continue;
				}
				$chunks[] = $trimmed === strip_tags( $trimmed ) ? '<p>' . $this->esc( $trimmed ) . '</p>' : $trimmed;
				continue;
			}
			if ( is_array( $value ) ) {
				$this->generic_text( $value, $chunks );
			}
		}
	}

	/**
	 * @param string $value Candidate text.
	 */
	private function looks_like_url( string $value ): bool {
		return 1 === preg_match( '#^https?://\S+$#i', $value );
	}

	/**
	 * @param string $value Untrusted attribute or text.
	 */
	private function esc( string $value ): string {
		return htmlspecialchars( $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	}
}

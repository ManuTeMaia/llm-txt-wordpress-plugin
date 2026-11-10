<?php
/**
 * Converts rendered HTML into readable Markdown.
 *
 * @package LlmsTxt
 */

namespace LlmsTxt\Content;

/**
 * DOM-based converter. Scripts, styles, and layout chrome are dropped.
 */
final class Html_To_Markdown {
	private string $base_url = '';

	/**
	 * @param string $html     Fragment or document HTML.
	 * @param string $base_url Page URL used to resolve relative links.
	 */
	public function convert( string $html, string $base_url = '' ): string {
		$this->base_url = $base_url;
		$html           = $this->preclean( $html );
		if ( '' === trim( $html ) ) {
			return '';
		}
		if ( ! class_exists( \DOMDocument::class ) ) {
			return $this->normalize( strip_tags( $html ) );
		}

		$document = new \DOMDocument();
		$previous = libxml_use_internal_errors( true );
		$wrapped  = '<?xml encoding="UTF-8"><body>' . $html . '</body>';
		$document->loadHTML( $wrapped, LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		$body = $document->getElementsByTagName( 'body' )->item( 0 );
		if ( ! $body instanceof \DOMElement ) {
			return '';
		}

		return $this->normalize( $this->render_children( $body ) );
	}

	/**
	 * Remove comments and elements that never belong in agent-facing text.
	 *
	 * @param string $html Raw HTML.
	 */
	private function preclean( string $html ): string {
		$html = (string) preg_replace( '/<!--.*?-->/s', '', $html );
		$html = (string) preg_replace( '#<(script|style|noscript|svg|iframe|canvas|template|form)\b[^>]*>.*?</\1>#is', '', $html );
		$html = (string) preg_replace( '#<(script|style|noscript|svg|iframe|canvas|template|form)\b[^>]*/?>#is', '', $html );
		return $html;
	}

	/**
	 * @param \DOMNode $node Parent node.
	 */
	private function render_children( \DOMNode $node ): string {
		$output = '';
		foreach ( $node->childNodes as $child ) {
			$output .= $this->render( $child );
		}
		return $output;
	}

	/**
	 * @param \DOMNode $node Node to render.
	 */
	private function render( \DOMNode $node ): string {
		if ( $node instanceof \DOMText ) {
			return (string) preg_replace( '/\s+/u', ' ', $node->textContent ?? '' );
		}
		if ( ! $node instanceof \DOMElement ) {
			return '';
		}

		$tag = strtolower( $node->tagName );
		if ( in_array( $tag, array( 'script', 'style', 'noscript', 'svg', 'iframe', 'canvas', 'form', 'button', 'input', 'select', 'textarea', 'link', 'meta', 'template', 'nav' ), true ) ) {
			return '';
		}
		if ( $this->is_noise( $node ) ) {
			return '';
		}

		switch ( $tag ) {
			case 'h1':
			case 'h2':
			case 'h3':
			case 'h4':
			case 'h5':
			case 'h6':
				$text = trim( $this->render_children( $node ) );
				return '' === $text ? '' : "\n\n" . str_repeat( '#', (int) substr( $tag, 1 ) ) . ' ' . $text . "\n\n";
			case 'p':
			case 'figcaption':
				$text = trim( $this->render_children( $node ) );
				return '' === $text ? '' : "\n\n" . $text . "\n\n";
			case 'br':
				return "\n";
			case 'hr':
				return "\n\n---\n\n";
			case 'strong':
			case 'b':
				$text = trim( $this->render_children( $node ) );
				return '' === $text ? '' : '**' . $text . '**';
			case 'em':
			case 'i':
				$text = trim( $this->render_children( $node ) );
				return '' === $text ? '' : '*' . $text . '*';
			case 'code':
				if ( $this->inside_pre( $node ) ) {
					return $node->textContent ?? '';
				}
				$text = trim( $node->textContent ?? '' );
				return '' === $text ? '' : '`' . str_replace( '`', '\'', $text ) . '`';
			case 'pre':
				$code = trim( (string) ( $node->textContent ?? '' ), "\n" );
				return '' === $code ? '' : "\n\n```\n" . $code . "\n```\n\n";
			case 'a':
				return $this->render_link( $node );
			case 'img':
				return $this->render_image( $node );
			case 'blockquote':
				$text = trim( $this->render_children( $node ) );
				if ( '' === $text ) {
					return '';
				}
				$lines  = preg_split( "/\r\n|\n|\r/", $text ) ?: array();
				$quoted = array_map(
					static function ( string $line ): string {
						return '> ' . $line;
					},
					$lines
				);
				return "\n\n" . implode( "\n", $quoted ) . "\n\n";
			case 'ul':
				return $this->render_list( $node, false );
			case 'ol':
				return $this->render_list( $node, true );
			case 'table':
				return $this->render_table( $node );
			case 'header':
			case 'footer':
			case 'aside':
				return '';
			default:
				return $this->render_children( $node );
		}
	}

	/**
	 * @param \DOMElement $node Element that may be layout chrome.
	 */
	private function is_noise( \DOMElement $node ): bool {
		$class = $node->getAttribute( 'class' );
		if ( '' === $class ) {
			return false;
		}

		return 1 === preg_match( '/(^|\s)(elementor-location-(header|footer)|elementor-widget-nav-menu|elementor-widget-social-icons|elementor-share-buttons|screen-reader-text)(\s|$)/', $class );
	}

	/**
	 * @param \DOMElement $node Code element.
	 */
	private function inside_pre( \DOMElement $node ): bool {
		$parent = $node->parentNode;
		while ( $parent instanceof \DOMElement ) {
			if ( 'pre' === strtolower( $parent->tagName ) ) {
				return true;
			}
			$parent = $parent->parentNode;
		}
		return false;
	}

	/**
	 * @param \DOMElement $node Anchor.
	 */
	private function render_link( \DOMElement $node ): string {
		$text = trim( $this->render_children( $node ) );
		$href = $this->resolve_url( $node->getAttribute( 'href' ) );
		if ( '' === $text ) {
			return '';
		}
		if ( '' === $href ) {
			return $text;
		}

		$label = str_replace( array( '[', ']' ), array( '\\[', '\\]' ), $text );
		return '[' . $label . '](' . $this->escape_url( $href ) . ')';
	}

	/**
	 * @param \DOMElement $node Image.
	 */
	private function render_image( \DOMElement $node ): string {
		$alt = trim( $node->getAttribute( 'alt' ) );
		$src = $this->resolve_url( $node->getAttribute( 'src' ) );
		if ( '' === $src || str_starts_with( $src, 'data:' ) ) {
			return '';
		}
		$alt = str_replace( array( '[', ']', "\n" ), '', $alt );
		return "\n\n![" . $alt . '](' . $this->escape_url( $src ) . ")\n\n";
	}

	/**
	 * @param \DOMElement $list    List element.
	 * @param bool        $ordered Whether the list is numbered.
	 */
	private function render_list( \DOMElement $list, bool $ordered ): string {
		$items = array();
		$index = 1;
		foreach ( $list->childNodes as $child ) {
			if ( ! $child instanceof \DOMElement || 'li' !== strtolower( $child->tagName ) ) {
				continue;
			}
			$body = trim( $this->render_children( $child ) );
			$body = (string) preg_replace( "/\n{2,}/", "\n", $body );
			if ( '' === $body ) {
				continue;
			}
			$marker = $ordered ? ( $index . '. ' ) : '- ';
			$lines  = explode( "\n", $body );
			$first  = (string) array_shift( $lines );
			$chunk  = $marker . $first;
			foreach ( $lines as $line ) {
				if ( '' === trim( $line ) ) {
					continue;
				}
				$chunk .= "\n  " . $line;
			}
			$items[] = $chunk;
			++$index;
		}

		if ( array() === $items ) {
			return '';
		}

		return "\n\n" . implode( "\n", $items ) . "\n\n";
	}

	/**
	 * @param \DOMElement $table Table element.
	 */
	private function render_table( \DOMElement $table ): string {
		$rows = $this->table_rows( $table );
		if ( array() === $rows ) {
			return '';
		}

		$width = 0;
		foreach ( $rows as $row ) {
			$width = max( $width, count( $row ) );
		}
		if ( 0 === $width ) {
			return '';
		}

		$lines  = array();
		$header = array_pad( $rows[0], $width, '' );
		$lines[] = '| ' . implode( ' | ', array_map( array( $this, 'cell' ), $header ) ) . ' |';
		$lines[] = '| ' . implode( ' | ', array_fill( 0, $width, '---' ) ) . ' |';
		$count   = count( $rows );
		for ( $i = 1; $i < $count; $i++ ) {
			$cells   = array_pad( $rows[ $i ], $width, '' );
			$lines[] = '| ' . implode( ' | ', array_map( array( $this, 'cell' ), $cells ) ) . ' |';
		}

		return "\n\n" . implode( "\n", $lines ) . "\n\n";
	}

	/**
	 * @param \DOMElement $table Table element.
	 * @return list<list<string>>
	 */
	private function table_rows( \DOMElement $table ): array {
		$rows = array();
		foreach ( $table->childNodes as $child ) {
			if ( ! $child instanceof \DOMElement ) {
				continue;
			}
			$tag = strtolower( $child->tagName );
			if ( 'tr' === $tag ) {
				$rows[] = $this->table_cells( $child );
				continue;
			}
			if ( in_array( $tag, array( 'thead', 'tbody', 'tfoot' ), true ) ) {
				foreach ( $child->childNodes as $row ) {
					if ( $row instanceof \DOMElement && 'tr' === strtolower( $row->tagName ) ) {
						$rows[] = $this->table_cells( $row );
					}
				}
			}
		}
		return $rows;
	}

	/**
	 * @param \DOMElement $row Table row.
	 * @return list<string>
	 */
	private function table_cells( \DOMElement $row ): array {
		$cells = array();
		foreach ( $row->childNodes as $cell ) {
			if ( ! $cell instanceof \DOMElement ) {
				continue;
			}
			if ( ! in_array( strtolower( $cell->tagName ), array( 'td', 'th' ), true ) ) {
				continue;
			}
			$cells[] = trim( $this->render_children( $cell ) );
		}
		return $cells;
	}

	/**
	 * @param string $value Cell text.
	 */
	private function cell( string $value ): string {
		$value = trim( (string) preg_replace( '/\s+/u', ' ', $value ) );
		return str_replace( '|', '\\|', $value );
	}

	/**
	 * @param string $url Attribute value.
	 */
	private function resolve_url( string $url ): string {
		$url = trim( html_entity_decode( $url, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		if ( '' === $url || str_starts_with( $url, '#' ) || str_starts_with( strtolower( $url ), 'javascript:' ) || str_starts_with( $url, 'data:' ) ) {
			return '';
		}
		if ( str_starts_with( strtolower( $url ), 'mailto:' ) ) {
			return $url;
		}
		if ( 1 === preg_match( '#^https?://#i', $url ) || str_starts_with( $url, '//' ) ) {
			return $url;
		}
		if ( '' === $this->base_url ) {
			return $url;
		}

		$parts  = parse_url( $this->base_url );
		$scheme = is_array( $parts ) ? (string) ( $parts['scheme'] ?? 'https' ) : 'https';
		$host   = is_array( $parts ) ? (string) ( $parts['host'] ?? '' ) : '';
		if ( '' === $host ) {
			return $url;
		}
		$origin = $scheme . '://' . $host;
		if ( is_array( $parts ) && isset( $parts['port'] ) ) {
			$origin .= ':' . $parts['port'];
		}
		if ( str_starts_with( $url, '/' ) ) {
			return $origin . $url;
		}

		$path = is_array( $parts ) ? (string) ( $parts['path'] ?? '/' ) : '/';
		$dir  = substr( $path, 0, (int) strrpos( $path, '/' ) );
		return $origin . $dir . '/' . $url;
	}

	/**
	 * @param string $url Absolute or relative URL.
	 */
	private function escape_url( string $url ): string {
		return str_replace( array( ' ', ')' ), array( '%20', '%29' ), $url );
	}

	/**
	 * @param string $markdown Converted text.
	 */
	private function normalize( string $markdown ): string {
		$markdown = str_replace( array( "\r\n", "\r" ), "\n", $markdown );
		$markdown = (string) preg_replace( "/[ \t]+\n/", "\n", $markdown );
		$markdown = (string) preg_replace( "/\n{3,}/", "\n\n", $markdown );
		return trim( $markdown );
	}
}

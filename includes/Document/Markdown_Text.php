<?php
/**
 * Small Markdown shaping helpers with no WordPress dependency.
 *
 * @package LlmsTxt
 */

namespace LlmsTxt\Document;

/**
 * Escapes link text and keeps the llms.txt outline intact.
 */
final class Markdown_Text {
	/**
	 * Collapse a string into one line and cap it on a word boundary.
	 *
	 * @param string $text Source text, possibly HTML.
	 * @param int    $max  Maximum characters. Zero returns an empty string.
	 */
	/**
	 * Decode entities repeatedly. WordPress titles often contain &#038; or a double-encoded form of it.
	 *
	 * @param string $text Raw text.
	 */
	public static function decode_entities( string $text ): string {
		$previous = null;
		$current  = $text;
		for ( $i = 0; $i < 3 && $current !== $previous; $i++ ) {
			$previous = $current;
			$current  = html_entity_decode( $current, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		}
		return str_replace( "\u{00A0}", ' ', $current );
	}

	/**
	 * One plain line: tags removed, entities decoded.
	 *
	 * @param string $text Raw text or HTML.
	 */
	public static function plain( string $text ): string {
		$text = self::decode_entities( $text );
		$text = trim( (string) preg_replace( '/\s+/u', ' ', strip_tags( $text ) ) );
		return $text;
	}

	/**
	 * Short description from HTML. A leading heading is dropped, and later blocks stay separate.
	 *
	 * @param string $html HTML or plain text.
	 * @param int    $max  Maximum characters.
	 */
	public static function summarize( string $html, int $max ): string {
		$blocks = self::text_blocks( $html );
		if ( array() === $blocks ) {
			return self::single_line( $html, $max );
		}
		if ( count( $blocks ) > 1 && $blocks[0]['heading'] ) {
			array_shift( $blocks );
		}

		$joined = '';
		foreach ( $blocks as $block ) {
			$part = trim( $block['text'] );
			if ( '' === $part ) {
				continue;
			}
			if ( '' === $joined ) {
				$joined = $part;
				continue;
			}
			$joined = preg_match( '/[.!?:…]$/u', $joined ) ? $joined . ' ' . $part : $joined . '. ' . $part;
		}

		return self::single_line( $joined, $max );
	}

	/**
	 * Whether Markdown has text other than a repeated title.
	 *
	 * @param string $markdown Markdown body.
	 * @param string $title    Entry title, ignored when it is the only text.
	 */
	public static function has_prose( string $markdown, string $title = '' ): bool {
		$text = (string) preg_replace( '/!\[[^\]]*\]\([^)]*\)/', ' ', $markdown );
		$text = (string) preg_replace( '/\[([^\]]*)\]\([^)]*\)/', '$1', $text );
		$text = (string) preg_replace( '/^#{1,6}\s+/m', '', $text );
		$text = str_replace( array( '---', '***', '___' ), ' ', $text );
		$text = self::plain( $text );
		if ( '' === $text ) {
			return false;
		}
		$title = self::plain( $title );
		return '' === $title || 0 !== strcasecmp( $text, $title );
	}

	public static function single_line( string $text, int $max ): string {
		$text = (string) preg_replace( '#<(script|style|noscript|iframe|svg|template)\b[^>]*>.*?</\1>#is', ' ', $text );
		$text = (string) preg_replace( '#<(br|hr)\b[^>]*/?>#i', ' ', $text );
		$text = (string) preg_replace( '#</(p|div|li|h[1-6]|tr|blockquote|section|article|ul|ol|table|figcaption|header|footer)>#i', ' ', $text );
		$text = self::decode_entities( strip_tags( $text ) );
		$text = str_replace( array( "\r", "\n", "\t" ), ' ', $text );
		$text = trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
		if ( '' === $text || $max <= 0 ) {
			return '';
		}
		if ( mb_strlen( $text ) <= $max ) {
			return $text;
		}

		$slice = mb_substr( $text, 0, $max );
		$cut   = mb_strrpos( $slice, ' ' );
		if ( false !== $cut && $cut > (int) floor( $max * 0.6 ) ) {
			$slice = mb_substr( $slice, 0, $cut );
		}

		return rtrim( $slice, " \t.,;:—-" ) . '…';
	}

	/**
	 * Escape a Markdown link label.
	 *
	 * @param string $text Raw label.
	 */
	public static function link_text( string $text ): string {
		$text = self::single_line( $text, 300 );
		return str_replace( array( '[', ']' ), array( '\\[', '\\]' ), $text );
	}

	/**
	 * Escape characters that would break a Markdown link destination.
	 *
	 * @param string $url Raw URL.
	 */
	public static function link_url( string $url ): string {
		$url = trim( $url );
		return str_replace( array( ' ', ')' ), array( '%20', '%29' ), $url );
	}

	/**
	 * Format one llms.txt list item.
	 *
	 * @param string $title       Link label.
	 * @param string $url         Absolute URL.
	 * @param string $description Optional note after the colon.
	 */
	public static function link_item( string $title, string $url, string $description = '' ): string {
		$line = '- [' . self::link_text( $title ) . '](' . self::link_url( $url ) . ')';
		$description = self::single_line( $description, 500 );
		if ( '' !== $description ) {
			$line .= ': ' . $description;
		}
		return $line;
	}

	/**
	 * The spec forbids headings in the free-form intro. Turn them into bold lines.
	 *
	 * @param string $markdown Intro Markdown.
	 */
	public function strip_intro_headings( string $markdown ): string {
		$lines = $this->lines( $markdown );
		$fence = false;
		foreach ( $lines as &$line ) {
			if ( 1 === preg_match( '/^```/', $line ) ) {
				$fence = ! $fence;
				continue;
			}
			if ( $fence ) {
				continue;
			}
			if ( 1 === preg_match( '/^#{1,6}\s+(.*)$/', $line, $matches ) ) {
				$line = '**' . trim( $matches[1] ) . '**';
			}
		}
		unset( $line );

		return trim( implode( "\n", $lines ) );
	}

	/**
	 * Push headings down so a section body cannot invent a second H1 or a competing H2.
	 *
	 * @param string $markdown Section body.
	 * @param int    $shift    Levels to add.
	 */
	public function demote_headings( string $markdown, int $shift ): string {
		if ( $shift <= 0 ) {
			return $markdown;
		}

		$lines = $this->lines( $markdown );
		$fence = false;
		foreach ( $lines as &$line ) {
			if ( 1 === preg_match( '/^```/', $line ) ) {
				$fence = ! $fence;
				continue;
			}
			if ( $fence ) {
				continue;
			}
			if ( 1 === preg_match( '/^(#{1,6})\s+(.*)$/', $line, $matches ) ) {
				$level = min( 6, strlen( $matches[1] ) + $shift );
				$line  = str_repeat( '#', $level ) . ' ' . $matches[2];
			}
		}
		unset( $line );

		return trim( implode( "\n", $lines ) );
	}

	/**
	 * @param string $html HTML fragment.
	 * @return list<array{heading:bool,text:string}>
	 */
	private static function text_blocks( string $html ): array {
		$html = (string) preg_replace( '#<(script|style|noscript|svg|iframe|template)\b[^>]*>.*?</\1>#is', '', $html );
		if ( '' === trim( $html ) || ! class_exists( \DOMDocument::class ) || ! str_contains( $html, '<' ) ) {
			return array();
		}

		$document = new \DOMDocument();
		$previous = libxml_use_internal_errors( true );
		$document->loadHTML( '<?xml encoding="UTF-8"><body>' . $html . '</body>', LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		$body = $document->getElementsByTagName( 'body' )->item( 0 );
		if ( ! $body instanceof \DOMElement ) {
			return array();
		}

		$blocks = array();
		self::collect_blocks( $body, $blocks );
		return $blocks;
	}

	/**
	 * @param \DOMNode                               $node   Node to walk.
	 * @param list<array{heading:bool,text:string}> $blocks Collected blocks.
	 */
	private static function collect_blocks( \DOMNode $node, array &$blocks ): void {
		if ( ! $node instanceof \DOMElement ) {
			foreach ( $node->childNodes as $child ) {
				self::collect_blocks( $child, $blocks );
			}
			return;
		}

		$tag = strtolower( $node->tagName );
		if ( in_array( $tag, array( 'script', 'style', 'noscript', 'svg', 'nav', 'form', 'iframe' ), true ) ) {
			return;
		}

		$heading = 1 === preg_match( '/^h[1-6]$/', $tag );
		if ( $heading || in_array( $tag, array( 'p', 'li', 'blockquote', 'figcaption', 'td', 'th' ), true ) ) {
			$text = self::plain( $node->textContent ?? '' );
			if ( '' !== $text ) {
				$blocks[] = array(
					'heading' => $heading,
					'text'    => $text,
				);
			}
			return;
		}

		$has_block_child = false;
		foreach ( $node->childNodes as $child ) {
			if ( $child instanceof \DOMElement && self::is_block_container( $child ) ) {
				$has_block_child = true;
				break;
			}
		}
		if ( ! $has_block_child && ! in_array( $tag, array( 'body', 'html', 'ul', 'ol', 'table', 'thead', 'tbody' ), true ) ) {
			$text = self::plain( $node->textContent ?? '' );
			if ( '' !== $text ) {
				$blocks[] = array(
					'heading' => false,
					'text'    => $text,
				);
			}
			return;
		}

		foreach ( $node->childNodes as $child ) {
			self::collect_blocks( $child, $blocks );
		}
	}

	/**
	 * @param \DOMElement $node Element that may wrap further blocks.
	 */
	private static function is_block_container( \DOMElement $node ): bool {
		$tag = strtolower( $node->tagName );
		return 1 === preg_match( '/^h[1-6]$/', $tag )
			|| in_array( $tag, array( 'p', 'div', 'section', 'article', 'ul', 'ol', 'li', 'blockquote', 'table', 'header', 'footer', 'main', 'figure' ), true );
	}

	/**
	 * @param string $markdown Source text.
	 * @return list<string>
	 */
	private function lines( string $markdown ): array {
		$markdown = str_replace( array( "\r\n", "\r" ), "\n", $markdown );
		$parts    = explode( "\n", $markdown );
		return $parts;
	}
}

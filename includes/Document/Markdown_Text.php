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
	public static function single_line( string $text, int $max ): string {
		$text = (string) preg_replace( '#<(script|style|noscript|iframe|svg|template)\b[^>]*>.*?</\1>#is', ' ', $text );
		$text = (string) preg_replace( '#<(br|hr)\b[^>]*/?>#i', ' ', $text );
		$text = (string) preg_replace( '#</(p|div|li|h[1-6]|tr|blockquote|section|article|ul|ol|table|figcaption|header|footer)>#i', ' ', $text );
		$text = html_entity_decode( strip_tags( $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
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
	 * @param string $markdown Source text.
	 * @return list<string>
	 */
	private function lines( string $markdown ): array {
		$markdown = str_replace( array( "\r\n", "\r" ), "\n", $markdown );
		$parts    = explode( "\n", $markdown );
		return $parts;
	}
}

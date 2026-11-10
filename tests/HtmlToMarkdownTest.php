<?php
/**
 * HTML and Elementor extraction.
 *
 * @package LlmsTxt
 */

declare( strict_types=1 );

namespace LlmsTxt\Tests;

use LlmsTxt\Content\Elementor_Extractor;
use LlmsTxt\Content\Html_To_Markdown;
use PHPUnit\Framework\TestCase;

final class HtmlToMarkdownTest extends TestCase {
	public function test_converts_content_and_drops_scripts(): void {
		$html = '<h1>Title</h1><p>Hello <strong>world</strong> <a href="https://terav.vet/how/">How</a></p><ul><li>One</li><li>Two</li></ul><style>.x{color:red}</style><script>evil()</script><nav>Menu</nav>';
		$markdown = ( new Html_To_Markdown() )->convert( $html, 'https://terav.vet/how/' );

		self::assertStringContainsString( '# Title', $markdown );
		self::assertStringContainsString( 'Hello **world** [How](https://terav.vet/how/)', $markdown );
		self::assertStringContainsString( '- One', $markdown );
		self::assertStringContainsString( '- Two', $markdown );
		self::assertStringNotContainsString( 'evil()', $markdown );
		self::assertStringNotContainsString( 'color:red', $markdown );
		self::assertStringNotContainsString( 'Menu', $markdown );
	}

	public function test_resolves_a_root_relative_link(): void {
		$markdown = ( new Html_To_Markdown() )->convert( '<p><a href="/privacy-policy/">Privacy</a></p>', 'https://terav.vet/articles/post/' );
		self::assertStringContainsString( '](https://terav.vet/privacy-policy/)', $markdown );
	}

	public function test_elementor_json_skips_menus_and_scripts(): void {
		$json = wp_json_encode_substitute(
			array(
				array(
					'elType'   => 'container',
					'elements' => array(
						array(
							'elType'     => 'widget',
							'widgetType' => 'heading',
							'settings'   => array(
								'title'       => 'Clinical workflows',
								'header_size' => 'h2',
							),
						),
						array(
							'elType'     => 'widget',
							'widgetType' => 'text-editor',
							'settings'   => array(
								'editor' => '<p>Maintain patient context.</p><script>alert(1)</script>',
							),
						),
						array(
							'elType'     => 'widget',
							'widgetType' => 'nav-menu',
							'settings'   => array(
								'title' => 'Should not appear',
							),
						),
					),
				),
			)
		);

		$html     = ( new Elementor_Extractor() )->to_html( $json );
		$markdown = ( new Html_To_Markdown() )->convert( $html );

		self::assertStringContainsString( '## Clinical workflows', $markdown );
		self::assertStringContainsString( 'Maintain patient context.', $markdown );
		self::assertStringNotContainsString( 'alert(1)', $markdown );
		self::assertStringNotContainsString( 'Should not appear', $markdown );
	}
}

/**
 * The tests avoid a WordPress dependency, including wp_json_encode.
 *
 * @param mixed $value Value to encode.
 */
function wp_json_encode_substitute( mixed $value ): string {
	$encoded = json_encode( $value );
	return is_string( $encoded ) ? $encoded : '';
}

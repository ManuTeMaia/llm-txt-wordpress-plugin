<?php
/**
 * Settings sanitization.
 *
 * @package LlmsTxt
 */

declare( strict_types=1 );

namespace LlmsTxt\Tests;

use LlmsTxt\Settings;
use PHPUnit\Framework\TestCase;

final class SettingsTest extends TestCase {
	public function test_strips_active_content_and_clamps_values(): void {
		$clean = ( new Settings() )->sanitize(
			array(
				'intro'              => "Hello\n<script>alert(1)</script>\nThere",
				'language_mode'      => 'sideways',
				'description_length' => '5',
				'full_cap_kb'        => '10',
				'sections'           => array(
					array(
						'heading' => '',
						'type'    => 'static',
					),
					array(
						'heading'  => 'Product',
						'type'     => 'static',
						'markdown' => "- [A](https://example.com)\n\n<iframe src=\"https://evil.test\"></iframe>",
					),
				),
			)
		);

		self::assertSame( 'single', $clean['language_mode'] );
		self::assertSame( 40, $clean['description_length'] );
		self::assertSame( 64 * 1024, $clean['full_byte_cap'] );
		self::assertStringNotContainsString( 'alert(1)', $clean['intro'] );
		self::assertStringContainsString( 'Hello', $clean['intro'] );
		self::assertCount( 1, $clean['sections'] );
		self::assertSame( 'Product', $clean['sections'][0]['heading'] );
		self::assertStringNotContainsString( 'iframe', strtolower( $clean['sections'][0]['markdown'] ) );
	}

	public function test_language_header_overrides_and_clean_descriptions(): void {
		$settings = ( new Settings() )->sanitize(
			array(
				'title_mode'   => 'custom',
				'title'        => 'Terav',
				'summary_mode' => 'custom',
				'summary'      => 'English summary',
				'intro'        => 'English intro',
				'translations' => array(
					'ru' => array(
						'title'   => 'Терав',
						'summary' => 'Русское описание',
						'intro'   => '',
					),
					'!!' => array( 'title' => 'nope' ),
				),
			)
		);
		$service = new Settings();
		$english = $service->header_for( $settings, null );
		$russian = $service->header_for( $settings, 'ru' );

		self::assertSame( 'Terav', $english['title'] );
		self::assertSame( 'Терав', $russian['title'] );
		self::assertSame( 'Русское описание', $russian['summary'] );
		self::assertSame( 'English intro', $russian['intro'] );
		self::assertArrayNotHasKey( '!!', $settings['translations'] );

		$description = \LlmsTxt\Document\Markdown_Text::single_line(
			'<h2>Three steps</h2><p>Start a case.</p><script>nope()</script>',
			240
		);
		self::assertSame( 'Three steps Start a case.', $description );
	}

	public function test_round_trips_a_stored_byte_cap(): void {
		$once  = ( new Settings() )->sanitize( array( 'full_cap_kb' => 512 ) );
		$twice = ( new Settings() )->sanitize( $once );
		self::assertSame( 512 * 1024, $twice['full_byte_cap'] );
	}
}

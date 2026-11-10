<?php
/**
 * The published Elementor view wins over leftover builder JSON.
 *
 * @package LlmsTxt
 */

declare( strict_types=1 );

namespace LlmsTxt\Tests;

use LlmsTxt\Content\Content_Sources;
use LlmsTxt\Content\Published_Document_Selector;
use PHPUnit\Framework\TestCase;

final class PublishedContentTest extends TestCase {
	public function test_theme_template_replaces_stale_elementor_json(): void {
		$selector = new Published_Document_Selector();

		self::assertSame( 1012, $selector->document_id( 15, array( 1012 ) ) );
		self::assertSame( 15, $selector->document_id( 15, array() ) );
		self::assertSame( 15, $selector->document_id( 15, array( 15 ) ) );

		$html = Content_Sources::html(
			'<h2>Grounded in real research</h2><p>The current homepage.</p>',
			'<p>рекомендации по лечению животных</p>'
		);

		self::assertStringContainsString( 'Grounded in real research', $html );
		self::assertStringContainsString( 'The current homepage.', $html );
		self::assertStringNotContainsString( 'рекомендации по лечению животных', $html );
	}

	public function test_empty_render_can_fall_back_to_builder_json(): void {
		$html = Content_Sources::html( '', '<p>Published builder text.</p>' );
		self::assertStringContainsString( 'Published builder text.', $html );
	}

	public function test_revisions_and_autosaves_are_not_published_sources(): void {
		$selector = new Published_Document_Selector();

		self::assertFalse( $selector->is_published_source( 'inherit', '15-autosave-v1' ) );
		self::assertFalse( $selector->is_published_source( 'draft', 'home' ) );
		self::assertTrue( $selector->is_published_source( 'publish', 'home' ) );
		self::assertFalse( $selector->is_published_source( 'publish', '1012-autosave' ) );
	}
}

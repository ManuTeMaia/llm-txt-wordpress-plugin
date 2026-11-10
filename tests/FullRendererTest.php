<?php
/**
 * llms-full.txt size cap.
 *
 * @package LlmsTxt
 */

declare( strict_types=1 );

namespace LlmsTxt\Tests;

use LlmsTxt\Document\Full_Entry;
use LlmsTxt\Document\Full_Model;
use LlmsTxt\Document\Full_Renderer;
use PHPUnit\Framework\TestCase;

final class FullRendererTest extends TestCase {
	public function test_stops_at_the_byte_cap_and_says_so(): void {
		$entries = array();
		foreach ( array( 'Alpha', 'Beta', 'Gamma' ) as $title ) {
			$entries[] = new Full_Entry( $title, 'https://example.com/' . strtolower( $title ), '2026-11-09T14:47:34+00:00', 'en', str_repeat( 'word ', 180 ) );
		}

		$markdown = ( new Full_Renderer() )->render( new Full_Model( 'Clinic', 'Summary', $entries ), 2048 );

		self::assertStringContainsString( '# Alpha', $markdown );
		self::assertStringContainsString( 'URL: https://example.com/alpha', $markdown );
		self::assertStringContainsString( 'Last modified: 2026-11-09T14:47:34+00:00', $markdown );
		self::assertStringContainsString( 'Language: en', $markdown );
		self::assertStringContainsString( 'Truncated:', $markdown );
		self::assertStringNotContainsString( '# Gamma', $markdown );
	}
}

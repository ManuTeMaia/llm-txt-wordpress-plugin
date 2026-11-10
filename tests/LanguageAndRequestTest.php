<?php
/**
 * Language planning, URL prefixes, and request matching.
 *
 * @package LlmsTxt
 */

declare( strict_types=1 );

namespace LlmsTxt\Tests;

use LlmsTxt\Content\Exclusion_Policy;
use LlmsTxt\Content\Post_Snapshot;
use LlmsTxt\Http\Request_Matcher;
use LlmsTxt\Language\Language;
use LlmsTxt\Language\Path_Language;
use LlmsTxt\Language\Section_Planner;
use PHPUnit\Framework\TestCase;

final class LanguageAndRequestTest extends TestCase {
	public function test_path_language_matches_the_first_segment(): void {
		$matcher   = new Path_Language();
		$prefixes  = array(
			array( 'code' => 'en', 'prefix' => '' ),
			array( 'code' => 'ru', 'prefix' => 'ru' ),
		);

		self::assertSame( 'ru', $matcher->match( '/ru/kak-rabotaet-terav/', $prefixes, 'en' ) );
		self::assertSame( 'en', $matcher->match( '/how-terav-works/', $prefixes, 'en' ) );
		self::assertSame( 'en', $matcher->match( '/', $prefixes, 'en' ) );
	}

	public function test_paths_mode_hides_a_section_for_the_other_language(): void {
		$planner = new Section_Planner();
		$section = array(
			'heading'  => 'Articles',
			'language' => 'en',
		);

		self::assertSame( array(), $planner->auto_plans( $section, 'paths', 'ru', 'default', array(), 'en' ) );
		self::assertFalse( $planner->static_visible( 'en', 'paths', 'ru', 'default', 'en' ) );
		self::assertTrue( $planner->static_visible( '', 'paths', 'ru', 'default', 'en' ) );
	}

	public function test_sections_mode_splits_an_unassigned_list(): void {
		$plans = ( new Section_Planner() )->auto_plans(
			array(
				'heading'  => 'Articles',
				'language' => '',
			),
			'sections',
			null,
			'default',
			array(
				new Language( 'en', 'English', '', true ),
				new Language( 'ru', 'Russian', 'ru', false ),
			),
			'en'
		);

		self::assertSame(
			array(
				array(
					'heading'  => 'Articles (English)',
					'language' => 'en',
				),
				array(
					'heading'  => 'Articles (Russian)',
					'language' => 'ru',
				),
			),
			$plans
		);
	}

	public function test_root_file_uses_the_default_language_unless_asked_for_all(): void {
		$planner = new Section_Planner();
		self::assertSame( 'en', $planner->file_language( null, 'default', 'en' ) );
		self::assertNull( $planner->file_language( null, 'all', 'en' ) );
		self::assertSame( 'ru', $planner->file_language( 'ru', 'default', 'en' ) );
	}

	public function test_request_matcher_accepts_language_files_and_subdirectory_installs(): void {
		$matcher = new Request_Matcher();

		self::assertSame(
			array( 'kind' => 'index', 'language' => null ),
			$matcher->match( '/llms.txt', '/' )
		);
		self::assertSame(
			array( 'kind' => 'full', 'language' => 'ru' ),
			$matcher->match( '/ru/llms-full.txt?cache=1', '/' )
		);
		self::assertSame(
			array( 'kind' => 'index', 'language' => null ),
			$matcher->match( '/blog/llms.txt', '/blog' )
		);
		self::assertSame(
			array( 'kind' => 'index', 'language' => 'ru' ),
			$matcher->match( '/blog/ru/llms.txt/', '/blog/' )
		);
		self::assertNull( $matcher->match( '/articles/llms.txt', '/' ) );
		self::assertNull( $matcher->match( '/llms.txt.bak', '/' ) );
	}

	public function test_exclusion_policy(): void {
		$policy = new Exclusion_Policy();
		self::assertFalse( $policy->is_excluded( new Post_Snapshot( 'publish', '', false, false ), true ) );
		self::assertTrue( $policy->is_excluded( new Post_Snapshot( 'private', '', false, false ), true ) );
		self::assertTrue( $policy->is_excluded( new Post_Snapshot( 'publish', 'secret', false, false ), true ) );
		self::assertTrue( $policy->is_excluded( new Post_Snapshot( 'publish', '', true, false ), true ) );
		self::assertTrue( $policy->is_excluded( new Post_Snapshot( 'publish', '', false, true ), true ) );
		self::assertFalse( $policy->is_excluded( new Post_Snapshot( 'publish', '', false, true ), false ) );
	}
}

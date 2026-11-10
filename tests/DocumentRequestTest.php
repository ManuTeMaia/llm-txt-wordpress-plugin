<?php
/**
 * Public file selection when a language directory is rewritten the way WPML does.
 *
 * @package LlmsTxt
 */

declare( strict_types=1 );

namespace LlmsTxt\Tests;

use LlmsTxt\Http\Document_Request;
use LlmsTxt\Http\Request_Matcher;
use LlmsTxt\Language\Catalog_Result;
use LlmsTxt\Language\Language;
use PHPUnit\Framework\TestCase;

final class DocumentRequestTest extends TestCase {
	public function test_wpml_home_directory_must_not_hide_the_language_of_the_original_path(): void {
		$matcher = new Request_Matcher();
		$catalog = $this->terav_catalog();

		// home_url() on a Russian request is /ru/, so the old subtraction saw /llms.txt.
		$hidden = $matcher->match( '/ru/llms.txt', '/ru' );
		self::assertNotNull( $hidden );
		self::assertNull( $hidden['language'] );

		// The stored home stays /, and the browser path still contains /ru/.
		$resolved = $this->documents()->resolve( '/ru/llms.txt', '/', 'ru', $catalog, true );
		self::assertSame(
			array(
				'kind'     => 'index',
				'language' => 'ru',
			),
			$resolved
		);

		$full = $this->documents()->resolve( '/ru/llms-full.txt', '/', 'ru', $catalog, true );
		self::assertSame(
			array(
				'kind'     => 'full',
				'language' => 'ru',
			),
			$full
		);
	}

	public function test_a_stripped_request_uri_uses_wpml_current_language_when_it_is_not_the_default(): void {
		$resolved = $this->documents()->resolve( '/llms.txt', '/', 'ru', $this->terav_catalog(), true );

		self::assertSame( 'ru', $resolved['language'] );
		self::assertSame( 'index', $resolved['kind'] );
	}

	public function test_root_file_stays_the_root_document_for_the_default_language(): void {
		$resolved = $this->documents()->resolve( '/llms.txt', '/', 'en', $this->terav_catalog(), true );

		self::assertNull( $resolved['language'] );
	}

	public function test_query_parameters_cannot_select_a_language_or_a_file(): void {
		$resolved = $this->documents()->resolve( '/llms.txt?llms_lang=ru&lang=ru', '/', null, $this->terav_catalog(), true );

		self::assertSame(
			array(
				'kind'     => 'index',
				'language' => null,
			),
			$resolved
		);
		self::assertNull( $this->documents()->resolve( '/llms-full.txt?llms_txt=index&llms_lang=ru', '/', null, $this->terav_catalog(), true )['language'] );
		self::assertSame( 'full', $this->documents()->resolve( '/llms-full.txt?llms_txt=index', '/', null, $this->terav_catalog(), true )['kind'] );
	}

	public function test_the_path_wins_when_it_disagrees_with_the_active_language(): void {
		$resolved = $this->documents()->resolve( '/ru/llms.txt', '/', 'en', $this->terav_catalog(), true );

		self::assertSame( 'ru', $resolved['language'] );
	}

	public function test_combined_mode_ignores_the_directory_and_the_active_language(): void {
		$resolved = $this->documents()->resolve( '/ru/llms.txt', '/', 'ru', $this->terav_catalog(), false );

		self::assertNull( $resolved['language'] );
	}

	public function test_active_language_is_ignored_unless_it_is_a_known_non_default_code(): void {
		$catalog = $this->terav_catalog();

		self::assertNull( $this->documents()->resolve( '/llms.txt', '/', 'all', $catalog, true )['language'] );
		self::assertNull( $this->documents()->resolve( '/llms.txt', '/', '../ru', $catalog, true )['language'] );
		self::assertNull( $this->documents()->resolve( '/llms.txt', '/', 'de', $catalog, true )['language'] );
	}

	public function test_an_unknown_directory_is_left_for_the_controller_to_reject(): void {
		$resolved = $this->documents()->resolve( '/de/llms.txt', '/', 'ru', $this->terav_catalog(), true );

		self::assertSame( 'de', $resolved['language'] );
	}

	public function test_subdirectory_install_keeps_the_language_directory(): void {
		$resolved = $this->documents()->resolve( '/blog/ru/llms.txt/', '/blog/', null, $this->terav_catalog(), true );

		self::assertSame( 'ru', $resolved['language'] );
	}

	private function documents(): Document_Request {
		return new Document_Request( new Request_Matcher() );
	}

	private function terav_catalog(): Catalog_Result {
		return new Catalog_Result(
			'wpml',
			array(
				new Language( 'en', 'English', '', true ),
				new Language( 'ru', 'Russian', 'ru', false ),
			)
		);
	}
}

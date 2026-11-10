<?php
/**
 * Security regressions that do not need WordPress.
 *
 * @package LlmsTxt
 */

declare( strict_types=1 );

namespace LlmsTxt\Tests;

use LlmsTxt\Cli\Local_Path;
use LlmsTxt\Http\Serve_Decision;
use LlmsTxt\Update\Manifest;
use PHPUnit\Framework\TestCase;

final class SecurityTest extends TestCase {
	public function test_manifest_rejects_http_other_hosts_and_missing_checksums(): void {
		self::assertFalse( Manifest::is_acceptable_url( 'http://manutemaia.com/llms-txt.json' ) );
		self::assertFalse( Manifest::is_acceptable_url( 'https://user:pass@manutemaia.com/llms-txt.json' ) );
		self::assertTrue( Manifest::is_acceptable_url( 'https://manutemaia.com/llms-txt.json' ) );
		self::assertFalse( Manifest::same_host( 'https://manutemaia.com/llms-txt.json', 'https://evil.example/llms-txt.zip' ) );
		self::assertTrue( Manifest::same_host( 'https://manutemaia.com/a.json', 'https://manutemaia.com/a.zip' ) );
		self::assertFalse( Manifest::is_sha256( 'abc' ) );
		self::assertTrue( Manifest::is_sha256( str_repeat( 'ab', 32 ) ) );
	}

	public function test_a_missing_transient_serves_the_durable_copy_and_queues_one_rebuild(): void {
		$decision = new Serve_Decision();

		$warm = $decision->decide( "# Current\n", "# Older\n", false );
		self::assertSame( 200, $warm['status'] );
		self::assertSame( "# Current\n", $warm['body'] );
		self::assertFalse( $warm['rebuild'] );

		$evicted = $decision->decide( null, "# Last good\n", false );
		self::assertSame( 200, $evicted['status'] );
		self::assertSame( "# Last good\n", $evicted['body'] );
		self::assertTrue( $evicted['rebuild'] );

		$already = $decision->decide( '', "# Last good\n", true );
		self::assertSame( 200, $already['status'] );
		self::assertFalse( $already['rebuild'] );

		$never = $decision->decide( null, null, false );
		self::assertSame( 503, $never['status'] );
		self::assertNull( $never['body'] );
		self::assertTrue( $never['rebuild'] );

		$never_queued = $decision->decide( null, '', true );
		self::assertSame( 503, $never_queued['status'] );
		self::assertFalse( $never_queued['rebuild'] );
	}

	public function test_cli_paths_reject_wrappers_and_symlinks(): void {
		$this->expectException( \InvalidArgumentException::class );
		Local_Path::resolve( 'phar://llms-txt-settings.json', true );
	}

	public function test_cli_export_stays_inside_a_real_directory(): void {
		$directory = sys_get_temp_dir() . '/llms-export-' . uniqid( '', true );
		mkdir( $directory );
		$path = Local_Path::resolve( $directory . '/settings.json', false );
		self::assertStringEndsWith( 'settings.json', $path );
		self::assertStringStartsWith( realpath( $directory ), $path );
		rmdir( $directory );
	}
}

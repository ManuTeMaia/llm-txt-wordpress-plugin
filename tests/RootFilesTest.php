<?php
/**
 * Web-root writes refuse to clobber a hand-written file.
 *
 * @package LlmsTxt
 */

declare( strict_types=1 );

namespace LlmsTxt\Tests;

use LlmsTxt\Disk\Root_Files;
use LlmsTxt\Disk\Signature_Store;
use PHPUnit\Framework\TestCase;

final class Memory_Signature_Store implements Signature_Store {
	/** @var array<string, string> */
	public array $data = array();

	public function all(): array {
		return $this->data;
	}

	public function save( array $signatures ): void {
		$this->data = $signatures;
	}
}

final class RootFilesTest extends TestCase {
	private string $root;

	private Memory_Signature_Store $signatures;

	protected function setUp(): void {
		$this->root       = sys_get_temp_dir() . '/llms-root-' . uniqid( '', true );
		$this->signatures = new Memory_Signature_Store();
		mkdir( $this->root );
	}

	protected function tearDown(): void {
		foreach ( array( 'llms.txt', 'llms-full.txt', 'llms.txt.llms-tmp' ) as $name ) {
			$path = $this->root . '/' . $name;
			if ( is_file( $path ) ) {
				unlink( $path );
			}
		}
		if ( is_dir( $this->root ) ) {
			rmdir( $this->root );
		}
	}

	public function test_refuses_a_foreign_file_until_overwrite_is_confirmed(): void {
		file_put_contents( $this->root . '/llms.txt', "# Hand written\n" );
		$files  = new Root_Files( $this->signatures, $this->root );
		$denied = $files->write( "# Generated\n", "# Full\n", false );

		self::assertFalse( $denied->ok );
		self::assertSame( "# Hand written\n", (string) file_get_contents( $this->root . '/llms.txt' ) );

		$allowed = $files->write( "# Generated\n", "# Full\n", true );
		self::assertTrue( $allowed->ok );
		self::assertSame( "# Generated\n", (string) file_get_contents( $this->root . '/llms.txt' ) );
		self::assertTrue( $files->inspect()['index']['managed'] );

		$files->delete_managed();
		self::assertFileDoesNotExist( $this->root . '/llms.txt' );
		self::assertFileDoesNotExist( $this->root . '/llms-full.txt' );
	}
}

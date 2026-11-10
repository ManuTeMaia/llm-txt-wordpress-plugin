<?php
/**
 * Reads and writes llms.txt in the WordPress web root.
 *
 * @package LlmsTxt
 */

namespace LlmsTxt\Disk;

/**
 * Disk delivery for nginx setups that never hand .txt requests to PHP.
 */
final class Root_Files {
	private const INDEX = 'llms.txt';
	private const FULL  = 'llms-full.txt';

	public function __construct(
		private Signature_Store $signatures,
		private ?string $root = null
	) {
	}

	/**
	 * Absolute web root with a trailing slash.
	 */
	public function root(): string {
		if ( null !== $this->root ) {
			return rtrim( $this->root, '/\\' ) . '/';
		}
		$base = defined( 'ABSPATH' ) ? ABSPATH : '';
		return rtrim( $base, '/\\' ) . '/';
	}

	/**
	 * Describe the two root files and whether this plugin owns them.
	 *
	 * @return array<string, array{path:string,exists:bool,bytes:int,managed:bool,writable_dir:bool}>
	 */
	public function inspect(): array {
		$signatures = $this->signatures->all();
		$writable   = is_dir( $this->root() ) && is_writable( $this->root() );
		$files      = array();
		foreach ( array( 'index' => self::INDEX, 'full' => self::FULL ) as $key => $name ) {
			$path    = $this->root() . $name;
			$exists  = is_file( $path );
			$hash    = $exists ? (string) md5_file( $path ) : '';
			$stored  = (string) ( $signatures[ $name ] ?? '' );
			$managed = $exists && '' !== $stored && hash_equals( $stored, $hash );
			$files[ $key ] = array(
				'path'         => $path,
				'exists'       => $exists,
				'bytes'        => $exists ? (int) filesize( $path ) : 0,
				'managed'      => $managed,
				'writable_dir' => $writable,
			);
		}
		return $files;
	}

	/**
	 * Write the root copies. A null full document deletes a managed llms-full.txt.
	 *
	 * @param string      $index Index document.
	 * @param string|null $full  Full document, or null to remove it.
	 * @param bool        $ack   Whether the administrator allowed replacing foreign files.
	 */
	public function write( string $index, ?string $full, bool $ack ): Write_Result {
		$root = $this->root();
		if ( ! is_dir( $root ) || ! is_writable( $root ) ) {
			return new Write_Result( false, 'The web root is not writable, so the files could not be written to disk.' );
		}

		$plan = array( self::INDEX => $index );
		if ( null !== $full ) {
			$plan[ self::FULL ] = $full;
		}

		$signatures = $this->signatures->all();
		foreach ( $plan as $name => $body ) {
			$blocked = $this->blocked( $root . $name, $name, $signatures, $ack );
			if ( null !== $blocked ) {
				return $blocked;
			}
		}

		foreach ( $plan as $name => $body ) {
			$result = $this->put( $root . $name, $body );
			if ( ! $result->ok ) {
				return $result;
			}
			$signatures[ $name ] = md5( $body );
		}

		if ( null === $full ) {
			$this->delete_if_managed( self::FULL, $signatures );
		}

		$this->signatures->save( $signatures );
		return new Write_Result( true, '' );
	}

	/**
	 * Delete root files whose contents still match the last copy this plugin wrote.
	 */
	public function delete_managed(): void {
		$signatures = $this->signatures->all();
		$this->delete_if_managed( self::INDEX, $signatures );
		$this->delete_if_managed( self::FULL, $signatures );
		$this->signatures->save( $signatures );
	}

	/**
	 * @param string               $path        Absolute path.
	 * @param string               $name        File name.
	 * @param array<string,string> $signatures  Known signatures.
	 * @param bool                 $ack         Overwrite acknowledgement.
	 */
	private function blocked( string $path, string $name, array $signatures, bool $ack ): ?Write_Result {
		if ( ! is_file( $path ) ) {
			return null;
		}
		$hash    = (string) md5_file( $path );
		$stored  = (string) ( $signatures[ $name ] ?? '' );
		$managed = '' !== $stored && hash_equals( $stored, $hash );
		if ( $managed || $ack ) {
			return null;
		}

		return new Write_Result(
			false,
			sprintf(
				'Refusing to replace the existing %s because this plugin did not write it. Confirm overwrite in the settings, or remove the file yourself.',
				$name
			)
		);
	}

	/**
	 * @param string $path Destination.
	 * @param string $body File contents.
	 */
	private function put( string $path, string $body ): Write_Result {
		$temporary = $path . '.llms-tmp';
		$written   = file_put_contents( $temporary, $body, LOCK_EX );
		if ( false === $written ) {
			return new Write_Result( false, 'Could not write a temporary copy of ' . basename( $path ) . '.' );
		}
		if ( ! rename( $temporary, $path ) ) {
			if ( is_file( $temporary ) ) {
				unlink( $temporary );
			}
			return new Write_Result( false, 'Could not move ' . basename( $path ) . ' into the web root.' );
		}
		return new Write_Result( true, '' );
	}

	/**
	 * @param string               $name        File name.
	 * @param array<string,string> $signatures  Signatures, edited by reference.
	 */
	private function delete_if_managed( string $name, array &$signatures ): void {
		$path = $this->root() . $name;
		if ( ! is_file( $path ) ) {
			unset( $signatures[ $name ] );
			return;
		}
		$stored = (string) ( $signatures[ $name ] ?? '' );
		$hash   = (string) md5_file( $path );
		if ( '' !== $stored && hash_equals( $stored, $hash ) ) {
			unlink( $path );
			unset( $signatures[ $name ] );
		}
	}
}

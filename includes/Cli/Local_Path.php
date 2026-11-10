<?php
/**
 * Local filesystem paths for WP-CLI import and export.
 *
 * @package LlmsTxt
 */

namespace LlmsTxt\Cli;

/**
 * Rejects stream wrappers and paths that are not a normal file in a real directory.
 */
final class Local_Path {
	/**
	 * @param string $path       User-supplied path.
	 * @param bool   $must_exist Whether the file itself must already exist.
	 */
	public static function resolve( string $path, bool $must_exist ): string {
		if ( '' === $path || str_contains( $path, "\0" ) || 1 === preg_match( '#^[a-z][a-z0-9+.-]*://#i', $path ) ) {
			throw new \InvalidArgumentException( 'Path must be a local file, not a URL or stream wrapper.' );
		}
		if ( $must_exist ) {
			if ( is_link( $path ) ) {
				throw new \InvalidArgumentException( 'Refusing to read a symlink.' );
			}
			$real = realpath( $path );
			if ( false === $real || ! is_file( $real ) ) {
				throw new \InvalidArgumentException( 'Import file was not found.' );
			}
			return $real;
		}

		$directory = realpath( dirname( $path ) );
		$base      = basename( $path );
		if ( false === $directory || ! is_dir( $directory ) || ! is_writable( $directory ) ) {
			throw new \InvalidArgumentException( 'Export directory is not writable.' );
		}
		if ( '' === $base || '.' === $base || '..' === $base || str_contains( $base, '/' ) || str_contains( $base, '\\' ) ) {
			throw new \InvalidArgumentException( 'Export file name is not valid.' );
		}
		return $directory . DIRECTORY_SEPARATOR . $base;
	}
}

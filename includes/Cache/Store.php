<?php
/**
 * Transient cache plus a copy under uploads.
 *
 * @package LlmsTxt
 */

namespace LlmsTxt\Cache;

/**
 * Keeps generated documents warm between publishes.
 */
final class Store {
	private const KEYS = 'llms_txt_cache_keys';

	/**
	 * @param string $key Cache key such as index_root.
	 */
	public function get( string $key ): ?string {
		$cached = get_transient( $this->transient_name( $key ) );
		if ( is_string( $cached ) && '' !== $cached ) {
			return $cached;
		}
		$path = $this->path( $key );
		if ( null !== $path && is_readable( $path ) ) {
			$body = file_get_contents( $path );
			return is_string( $body ) && '' !== $body ? $body : null;
		}
		return null;
	}

	/**
	 * @param string $key  Cache key.
	 * @param string $body Generated document.
	 */
	public function set( string $key, string $body ): void {
		set_transient( $this->transient_name( $key ), $body, DAY_IN_SECONDS );
		$this->remember( $key );
		$path = $this->path( $key, true );
		if ( null !== $path ) {
			file_put_contents( $path, $body, LOCK_EX );
		}
	}

	/**
	 * @param string $key Cache key.
	 */
	public function delete( string $key ): void {
		delete_transient( $this->transient_name( $key ) );
		$path = $this->path( $key );
		if ( null !== $path && is_file( $path ) ) {
			unlink( $path );
		}
		$keys = get_option( self::KEYS, array() );
		if ( is_array( $keys ) && isset( $keys[ $key ] ) ) {
			unset( $keys[ $key ] );
			update_option( self::KEYS, $keys, false );
		}
	}

	/**
	 * Drop every document this plugin has cached.
	 */
	public function flush(): void {
		$keys = get_option( self::KEYS, array() );
		if ( is_array( $keys ) ) {
			foreach ( array_keys( $keys ) as $key ) {
				if ( is_string( $key ) ) {
					delete_transient( $this->transient_name( $key ) );
					$path = $this->path( $key );
					if ( null !== $path && is_file( $path ) ) {
						unlink( $path );
					}
				}
			}
		}
		update_option( self::KEYS, array(), false );
	}

	/**
	 * @param string $key Cache key.
	 */
	private function remember( string $key ): void {
		$keys = get_option( self::KEYS, array() );
		if ( ! is_array( $keys ) ) {
			$keys = array();
		}
		$keys[ $key ] = time();
		update_option( self::KEYS, $keys, false );
	}

	/**
	 * @param string $key Cache key.
	 */
	private function transient_name( string $key ): string {
		$safe = (string) preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $key ) );
		return 'llms_txt_' . $safe;
	}

	/**
	 * @param string $key  Cache key.
	 * @param bool   $create Whether to create the directory.
	 */
	private function path( string $key, bool $create = false ): ?string {
		$directory = $this->directory( $create );
		if ( null === $directory ) {
			return null;
		}
		$safe = (string) preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $key ) );
		if ( '' === $safe ) {
			return null;
		}
		$path = $directory . '/' . $safe . '.txt';
		if ( is_link( $path ) ) {
			return null;
		}
		return $path;
	}

	/**
	 * @param bool $create Whether to create the directory.
	 */
	private function directory( bool $create ): ?string {
		$uploads = wp_upload_dir( null, false );
		if ( ! empty( $uploads['error'] ) || empty( $uploads['basedir'] ) ) {
			return null;
		}
		$base = realpath( (string) $uploads['basedir'] );
		if ( false === $base ) {
			return null;
		}
		$directory = $base . '/llms-txt';
		if ( is_link( $directory ) ) {
			return null;
		}
		if ( ! is_dir( $directory ) ) {
			if ( ! $create || ! wp_mkdir_p( $directory ) ) {
				return null;
			}
			if ( ! is_file( $directory . '/index.html' ) ) {
				file_put_contents( $directory . '/index.html', '' );
			}
		}
		$resolved = realpath( $directory );
		if ( false === $resolved || ! str_starts_with( $resolved, $base ) ) {
			return null;
		}
		return $resolved;
	}
}

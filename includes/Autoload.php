<?php
/**
 * Class autoloader for the plugin.
 *
 * @package LlmsTxt
 */

namespace LlmsTxt;

/**
 * Maps the LlmsTxt namespace onto the includes directory.
 */
final class Autoload {
	/**
	 * Register the autoloader.
	 */
	public static function register(): void {
		spl_autoload_register( array( self::class, 'load' ) );
	}

	/**
	 * Load a class file when it belongs to this plugin.
	 *
	 * @param string $class Fully qualified class name.
	 */
	public static function load( string $class ): void {
		$prefix = __NAMESPACE__ . '\\';
		if ( ! str_starts_with( $class, $prefix ) ) {
			return;
		}

		$relative = substr( $class, strlen( $prefix ) );
		$path     = __DIR__ . '/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
}

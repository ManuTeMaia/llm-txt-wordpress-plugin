<?php
/**
 * wp llms settings export and import.
 *
 * @package LlmsTxt
 */

namespace LlmsTxt\Cli;

use LlmsTxt\Generator;
use LlmsTxt\Http\Rewrite;
use LlmsTxt\Settings;
use LlmsTxt\Settings_Port;

/**
 * Moves the filled-in settings to and from a JSON file.
 */
final class Settings_Command {
	/**
	 * Write the current settings as JSON.
	 *
	 * ## OPTIONS
	 *
	 * [--file=<path>]
	 * : Write to this path. Omit it to print the JSON.
	 *
	 * ## EXAMPLES
	 *
	 *     wp llms settings export --file=llms-txt-settings.json
	 *
	 * @param list<string>         $args  Positional arguments.
	 * @param array<string, mixed> $assoc Associative arguments.
	 */
	public function export( array $args, array $assoc ): void {
		unset( $args );
		$json = ( new Settings_Port( new Settings() ) )->to_json( ( new Settings() )->get(), LLMS_TXT_VERSION );
		if ( isset( $assoc['file'] ) && is_string( $assoc['file'] ) && '' !== $assoc['file'] ) {
			try {
				$path = Local_Path::resolve( $assoc['file'], false );
			} catch ( \InvalidArgumentException $exception ) {
				\WP_CLI::error( $exception->getMessage() );
			}
			$written = file_put_contents( $path, $json );
			if ( false === $written ) {
				\WP_CLI::error( 'Could not write ' . $assoc['file'] );
			}
			\WP_CLI::success( 'Wrote ' . $path );
			return;
		}
		fwrite( STDOUT, $json );
	}

	/**
	 * Replace the saved settings from a JSON export.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : Path to a file produced by `wp llms settings export`.
	 *
	 * ## EXAMPLES
	 *
	 *     wp llms settings import llms-txt-settings.json
	 *
	 * @param list<string>         $args  Positional arguments.
	 * @param array<string, mixed> $assoc Associative arguments.
	 */
	public function import( array $args, array $assoc ): void {
		unset( $assoc );
		$path = $args[0] ?? '';
		try {
			$path = Local_Path::resolve( $path, true );
		} catch ( \InvalidArgumentException $exception ) {
			\WP_CLI::error( $exception->getMessage() );
		}
		$size = filesize( $path );
		if ( false === $size || $size > 2_000_000 ) {
			\WP_CLI::error( 'The export file must be under 2 MB.' );
		}
		$json = file_get_contents( $path );
		if ( ! is_string( $json ) ) {
			\WP_CLI::error( 'Could not read ' . $path );
		}
		try {
			$clean = ( new Settings_Port( new Settings() ) )->parse( $json );
		} catch ( \InvalidArgumentException $exception ) {
			\WP_CLI::error( $exception->getMessage() );
		}
		update_option( Settings::OPTION, $clean, true );
		( new Rewrite() )->add_rules();
		flush_rewrite_rules( false );
		Generator::make()->regenerate_all();
		\WP_CLI::success( 'Imported settings from ' . $path );
	}
}

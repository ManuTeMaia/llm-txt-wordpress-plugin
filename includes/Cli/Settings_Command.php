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
			$written = file_put_contents( $assoc['file'], $json );
			if ( false === $written ) {
				\WP_CLI::error( 'Could not write ' . $assoc['file'] );
			}
			\WP_CLI::success( 'Wrote ' . $assoc['file'] );
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
		if ( '' === $path || ! is_readable( $path ) ) {
			\WP_CLI::error( 'Give a readable JSON export: wp llms settings import <file>' );
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

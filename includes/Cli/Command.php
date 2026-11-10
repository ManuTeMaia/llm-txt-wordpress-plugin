<?php
/**
 * WP-CLI commands.
 *
 * @package LlmsTxt
 */

namespace LlmsTxt\Cli;

use LlmsTxt\Disk\Option_Signature_Store;
use LlmsTxt\Disk\Root_Files;
use LlmsTxt\Generator;
use LlmsTxt\Language\Catalog;
use LlmsTxt\Settings;

/**
 * wp llms regenerate, wp llms show, and wp llms status.
 */
final class Command {
	/**
	 * Regenerate llms.txt and llms-full.txt.
	 *
	 * Refreshes the cache, and writes the web root when disk delivery is enabled.
	 *
	 * ## EXAMPLES
	 *
	 *     wp llms regenerate
	 *
	 * @param list<string>          $args  Positional arguments.
	 * @param array<string, mixed>  $assoc Associative arguments.
	 */
	public function regenerate( array $args, array $assoc ): void {
		unset( $args, $assoc );
		$status = Generator::make()->regenerate_all();
		if ( ! empty( $status['error'] ) ) {
			\WP_CLI::warning( (string) $status['error'] );
		}
		$index = 0;
		$full  = 0;
		if ( isset( $status['variants'] ) && is_array( $status['variants'] ) ) {
			foreach ( $status['variants'] as $variant ) {
				if ( is_array( $variant ) && 'root' === ( $variant['language'] ?? '' ) ) {
					$index = (int) ( $variant['index_bytes'] ?? 0 );
					$full  = (int) ( $variant['full_bytes'] ?? 0 );
				}
			}
		}
		\WP_CLI::success( sprintf( 'Regenerated llms.txt (%d bytes) and llms-full.txt (%d bytes).', $index, $full ) );
	}

	/**
	 * Print a generated document.
	 *
	 * ## OPTIONS
	 *
	 * [--full]
	 * : Print llms-full.txt instead of llms.txt.
	 *
	 * [--lang=<code>]
	 * : Language code. Omit it to print the root file.
	 *
	 * ## EXAMPLES
	 *
	 *     wp llms show
	 *     wp llms show --full
	 *     wp llms show --lang=ru
	 *
	 * @param list<string>         $args  Positional arguments.
	 * @param array<string, mixed> $assoc Associative arguments.
	 */
	public function show( array $args, array $assoc ): void {
		unset( $args );
		$language = isset( $assoc['lang'] ) ? strtolower( (string) $assoc['lang'] ) : null;
		if ( '' === $language ) {
			$language = null;
		}
		$generator = Generator::make();
		$body      = array_key_exists( 'full', $assoc )
			? $generator->render_full( $language )
			: $generator->render_index( $language );
		\WP_CLI::log( rtrim( $body, "\n" ) );
	}

	/**
	 * Show generator status.
	 *
	 * ## EXAMPLES
	 *
	 *     wp llms status
	 *
	 * @param list<string>         $args  Positional arguments.
	 * @param array<string, mixed> $assoc Associative arguments.
	 */
	public function status( array $args, array $assoc ): void {
		unset( $args, $assoc );
		$settings = new Settings();
		$values   = $settings->get();
		$catalog  = ( new Catalog( $settings ) )->detect();
		$files    = ( new Root_Files( new Option_Signature_Store() ) )->inspect();
		$last     = get_option( 'llms_txt_status', array() );
		$codes    = array();
		foreach ( $catalog->languages as $language ) {
			$codes[] = array(
				'code'    => $language->code,
				'name'    => $language->name,
				'default' => $language->default,
				'prefix'  => $language->prefix,
			);
		}

		\WP_CLI::log(
			(string) wp_json_encode(
				array(
					'provider' => $catalog->provider,
					'languages' => $codes,
					'mode'     => $values['language_mode'],
					'delivery' => $values['delivery'],
					'enabled'  => $values['enabled'],
					'disk'     => $files,
					'last'     => $last,
				),
				JSON_PRETTY_PRINT
			)
		);
	}
}

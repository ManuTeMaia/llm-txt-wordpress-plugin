<?php
/**
 * Plugin activation.
 *
 * @package LlmsTxt
 */

namespace LlmsTxt;

use LlmsTxt\Cron\Scheduler;
use LlmsTxt\Http\Rewrite;

/**
 * Seeds settings, flushes rewrites, and builds the first copy of the files.
 */
final class Activator {
	public static function activate(): void {
		$settings = new Settings();
		$stored   = get_option( Settings::OPTION, null );
		if ( ! is_array( $stored ) ) {
			add_option( Settings::OPTION, $settings->sanitize( Settings::defaults() ), '', true );
		} else {
			update_option( Settings::OPTION, $settings->sanitize( $stored ), true );
		}

		( new Rewrite() )->add_rules();
		flush_rewrite_rules( false );
		( new Scheduler() )->schedule();
		update_option( 'llms_txt_version', LLMS_TXT_VERSION, true );

		try {
			Generator::make()->regenerate_all();
		} catch ( \Throwable $exception ) {
			update_option(
				'llms_txt_status',
				array(
					'generated_at' => time(),
					'error'        => $exception->getMessage(),
					'variants'     => array(),
					'disk'         => array(
						'ok'      => false,
						'message' => $exception->getMessage(),
					),
					'provider'     => '',
				),
				false
			);
		}
	}
}

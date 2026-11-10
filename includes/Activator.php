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
 * Seeds settings, flushes rewrites, and queues the first rebuild on cron.
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
		( new Scheduler() )->schedule_once();
		update_option( 'llms_txt_version', LLMS_TXT_VERSION, true );
	}
}

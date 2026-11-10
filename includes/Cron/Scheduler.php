<?php
/**
 * Daily rebuild, in case a hook was missed.
 *
 * @package LlmsTxt
 */

namespace LlmsTxt\Cron;

use LlmsTxt\Generator;

/**
 * WP-Cron event for llms.txt.
 */
final class Scheduler {
	public const HOOK = 'llms_txt_daily_regenerate';

	public function register_hooks(): void {
		add_action( 'init', array( $this, 'ensure_scheduled' ) );
		add_action( self::HOOK, array( $this, 'run' ) );
	}

	/**
	 * Schedule the daily event once.
	 */
	public function ensure_scheduled(): void {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::HOOK );
		}
	}

	/**
	 * Force the next run onto the cron array. Used on activation.
	 */
	public function schedule(): void {
		wp_clear_scheduled_hook( self::HOOK );
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::HOOK );
	}

	/**
	 * Remove the event. Used on deactivation.
	 */
	public function unschedule(): void {
		wp_clear_scheduled_hook( self::HOOK );
	}

	/**
	 * Cron callback.
	 */
	public function run(): void {
		Generator::make()->regenerate_all();
	}
}

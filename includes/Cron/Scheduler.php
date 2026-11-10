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

	public const ONCE = 'llms_txt_rebuild_once';

	public function register_hooks(): void {
		add_action( 'init', array( $this, 'ensure_scheduled' ) );
		add_action( self::HOOK, array( $this, 'run' ) );
		add_action( self::ONCE, array( $this, 'run' ) );
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
	 * Queue one rebuild. A second call does nothing while the first is still queued.
	 */
	public function schedule_once(): bool {
		if ( wp_next_scheduled( self::ONCE ) ) {
			return false;
		}
		$lock = get_option( 'llms_txt_regen_lock', null );
		if ( is_numeric( $lock ) && ( time() - (int) $lock ) < 120 ) {
			return false;
		}
		wp_schedule_single_event( time(), self::ONCE );
		if ( function_exists( 'spawn_cron' ) ) {
			spawn_cron();
		}
		return true;
	}

	/**
	 * Remove the event. Used on deactivation.
	 */
	public function unschedule(): void {
		wp_clear_scheduled_hook( self::HOOK );
		wp_clear_scheduled_hook( self::ONCE );
	}

	/**
	 * Cron callback.
	 */
	public function run(): void {
		Generator::make()->regenerate_all();
	}
}

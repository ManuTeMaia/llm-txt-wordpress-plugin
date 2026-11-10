<?php
/**
 * Plugin deactivation.
 *
 * @package LlmsTxt
 */

namespace LlmsTxt;

use LlmsTxt\Cron\Scheduler;

/**
 * Clears the cron event and lets WordPress rebuild rewrites without this plugin.
 */
final class Deactivator {
	public static function deactivate(): void {
		( new Scheduler() )->unschedule();
		delete_option( 'rewrite_rules' );
	}
}

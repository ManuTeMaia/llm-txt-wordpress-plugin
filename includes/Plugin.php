<?php
/**
 * Wires the plugin into WordPress.
 *
 * @package LlmsTxt
 */

namespace LlmsTxt;

use LlmsTxt\Admin\Meta_Box;
use LlmsTxt\Admin\Notices;
use LlmsTxt\Admin\Settings_Page;
use LlmsTxt\Cache\Invalidator;
use LlmsTxt\Cli\Command;
use LlmsTxt\Cron\Scheduler;
use LlmsTxt\Http\Controller;
use LlmsTxt\Http\Link_Header;
use LlmsTxt\Http\Rewrite;

/**
 * Bootstrap. Hooks are registered here and the work lives in the other classes.
 */
final class Plugin {
	public static function boot(): void {
		add_action( 'init', array( self::class, 'load_textdomain' ), 0 );
		add_action( 'init', array( self::class, 'maybe_upgrade' ), 20 );

		( new Rewrite() )->register_hooks();
		( new Controller() )->register_hooks();
		( new Link_Header() )->register_hooks();
		( new Invalidator() )->register_hooks();
		( new Scheduler() )->register_hooks();
		( new Meta_Box() )->register_hooks();

		if ( is_admin() ) {
			( new Settings_Page() )->register_hooks();
			( new Notices() )->register_hooks();
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'llms', Command::class );
		}
	}

	/**
	 * Load the text domain for self-hosted translation files.
	 */
	public static function load_textdomain(): void {
		load_plugin_textdomain( 'llms-txt', false, dirname( plugin_basename( LLMS_TXT_FILE ) ) . '/languages' );
	}

	/**
	 * Flush rewrites once after an update so new rules are not stuck in the option.
	 */
	public static function maybe_upgrade(): void {
		$stored = get_option( 'llms_txt_version' );
		if ( LLMS_TXT_VERSION === $stored ) {
			return;
		}
		flush_rewrite_rules( false );
		update_option( 'llms_txt_version', LLMS_TXT_VERSION, true );
	}
}

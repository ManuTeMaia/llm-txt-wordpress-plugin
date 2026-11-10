<?php
/**
 * Warns when a physical llms.txt would be served instead of WordPress.
 *
 * @package LlmsTxt
 */

namespace LlmsTxt\Admin;

use LlmsTxt\Capabilities;
use LlmsTxt\Disk\Option_Signature_Store;
use LlmsTxt\Disk\Root_Files;
use LlmsTxt\Settings;

/**
 * Admin notice for the web-root shadow file.
 */
final class Notices {
	public function register_hooks(): void {
		add_action( 'admin_notices', array( $this, 'render' ) );
	}

	/**
	 * Show the warning on the dashboard, the plugins screen, and our settings page.
	 */
	public function render(): void {
		if ( ! Capabilities::can_manage() || ! function_exists( 'get_current_screen' ) ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->id, array( 'dashboard', 'plugins', 'settings_page_llms-txt' ), true ) ) {
			return;
		}

		$settings = ( new Settings() )->get();
		$files    = ( new Root_Files( new Option_Signature_Store() ) )->inspect();
		$exists   = $files['index']['exists'] || $files['full']['exists'];
		if ( ! $exists ) {
			return;
		}

		$url = admin_url( 'options-general.php?page=llms-txt' );
		if ( 'disk' === $settings['delivery'] ) {
			if ( ( $files['index']['exists'] && ! $files['index']['managed'] ) || ( $files['full']['exists'] && ! $files['full']['managed'] ) ) {
				echo '<div class="notice notice-warning"><p>';
				echo esc_html__( 'LLMs.txt is set to write the web root, but an existing file was not overwritten. Confirm overwrite on the settings screen or remove the file.', 'llms-txt' );
				echo ' <a href="' . esc_url( $url ) . '">' . esc_html__( 'Review delivery', 'llms-txt' ) . '</a>';
				echo '</p></div>';
			}
			return;
		}

		echo '<div class="notice notice-error"><p>';
		echo esc_html__( 'A physical llms.txt or llms-full.txt in the web root will be served by the web server before WordPress, so the dynamic file never runs. Remove it, or switch delivery to writing the files on disk.', 'llms-txt' );
		echo ' <a href="' . esc_url( $url ) . '">' . esc_html__( 'Open LLMs.txt settings', 'llms-txt' ) . '</a>';
		echo '</p></div>';
	}
}

<?php
/**
 * Settings → LLMs.txt.
 *
 * @package LlmsTxt
 */

namespace LlmsTxt\Admin;

use LlmsTxt\Capabilities;
use LlmsTxt\Disk\Option_Signature_Store;
use LlmsTxt\Disk\Root_Files;
use LlmsTxt\Generator;
use LlmsTxt\Http\Rewrite;
use LlmsTxt\Language\Catalog;
use LlmsTxt\Settings;
use LlmsTxt\Settings_Port;

/**
 * Settings screen, sanitization, and the manual rebuild action.
 */
final class Settings_Page {
	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_post_llms_txt_save', array( $this, 'save' ) );
		add_action( 'admin_post_llms_txt_regenerate', array( $this, 'regenerate' ) );
		add_action( 'admin_post_llms_txt_flush', array( $this, 'flush_rules' ) );
		add_action( 'admin_post_llms_txt_delete_root', array( $this, 'delete_root' ) );
		add_action( 'admin_post_llms_txt_export', array( $this, 'export_settings' ) );
		add_action( 'admin_post_llms_txt_import', array( $this, 'import_settings' ) );
	}

	/**
	 * Register the submenu.
	 */
	public function menu(): void {
		add_options_page(
			__( 'LLMs.txt', 'llms-txt' ),
			__( 'LLMs.txt', 'llms-txt' ),
			Capabilities::required(),
			'llms-txt',
			array( $this, 'render' )
		);
	}

	/**
	 * @param string $hook Current admin page hook.
	 */
	public function assets( string $hook ): void {
		if ( 'settings_page_llms-txt' !== $hook ) {
			return;
		}
		wp_enqueue_style(
			'llms-txt-admin',
			plugins_url( 'assets/admin.css', LLMS_TXT_FILE ),
			array(),
			LLMS_TXT_VERSION
		);
		wp_enqueue_script(
			'llms-txt-admin',
			plugins_url( 'assets/admin.js', LLMS_TXT_FILE ),
			array(),
			LLMS_TXT_VERSION,
			true
		);
		wp_localize_script(
			'llms-txt-admin',
			'llmsTxtAdmin',
			array(
				'copied' => __( 'Copied', 'llms-txt' ),
				'copy'   => __( 'Copy', 'llms-txt' ),
			)
		);
	}

	/**
	 * Render the screen.
	 */
	public function render(): void {
		if ( ! Capabilities::can_manage() ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to manage LLMs.txt.', 'llms-txt' ) );
		}

		$settings_service = new Settings();
		$settings         = $settings_service->get();
		$catalog          = ( new Catalog( $settings_service ) )->detect();
		$disk             = ( new Root_Files( new Option_Signature_Store() ) )->inspect();
		$status           = get_option( 'llms_txt_status', array() );
		if ( ! is_array( $status ) ) {
			$status = array();
		}

		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		unset( $post_types['attachment'] );
		$taxonomies = get_taxonomies( array( 'public' => true ), 'objects' );

		$preview       = '';
		$preview_error = '';
		try {
			$preview = Generator::make()->render_index( null );
		} catch ( \Throwable $exception ) {
			$preview_error = $exception->getMessage();
		}

		$robots        = Robots_Guide::snippet();
		$import_notice = get_transient( 'llms_txt_import_notice' );
		if ( is_array( $import_notice ) ) {
			delete_transient( 'llms_txt_import_notice' );
		} else {
			$import_notice = array();
		}

		include LLMS_TXT_DIR . 'includes/Admin/views/settings-page.php';
	}

	/**
	 * Persist settings and rebuild.
	 */
	public function save(): void {
		$this->assert_manage();
		check_admin_referer( 'llms_txt_save', 'llms_txt_nonce' );

		// The nested settings array is whitelisted in Settings::sanitize().
		$input = isset( $_POST['llms_txt'] ) ? wp_unslash( $_POST['llms_txt'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( ! is_array( $input ) ) {
			$input = array();
		}
		$input = $this->apply_prefix_default( $input );

		$clean = ( new Settings() )->sanitize( $input );
		update_option( Settings::OPTION, $clean, true );
		( new Rewrite() )->add_rules();
		flush_rewrite_rules( false );
		$status = Generator::make()->regenerate_all();

		$args = array(
			'page'         => 'llms-txt',
			'llms-updated' => '1',
		);
		if ( is_array( $status ) && ! empty( $status['error'] ) ) {
			$args['llms-error'] = '1';
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'options-general.php' ) ) );
		exit;
	}

	/**
	 * Rebuild without changing settings.
	 */
	public function regenerate(): void {
		$this->assert_manage();
		check_admin_referer( 'llms_txt_regenerate', 'llms_txt_nonce' );
		$status = Generator::make()->regenerate_all();
		$args   = array(
			'page'             => 'llms-txt',
			'llms-regenerated' => '1',
		);
		if ( is_array( $status ) && ! empty( $status['error'] ) ) {
			$args['llms-error'] = '1';
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'options-general.php' ) ) );
		exit;
	}

	/**
	 * Flush permalinks so /llms.txt is routed into WordPress.
	 */
	public function flush_rules(): void {
		$this->assert_manage();
		check_admin_referer( 'llms_txt_flush', 'llms_txt_nonce' );
		( new Rewrite() )->add_rules();
		flush_rewrite_rules( false );
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'        => 'llms-txt',
					'llms-flushed' => '1',
				),
				admin_url( 'options-general.php' )
			)
		);
		exit;
	}

	/**
	 * Remove web-root copies that still match the last generated checksum.
	 */
	public function delete_root(): void {
		$this->assert_manage();
		check_admin_referer( 'llms_txt_delete_root', 'llms_txt_nonce' );
		( new Root_Files( new Option_Signature_Store() ) )->delete_managed();
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'         => 'llms-txt',
					'llms-deleted' => '1',
				),
				admin_url( 'options-general.php' )
			)
		);
		exit;
	}

	/**
	 * Download the current settings as JSON.
	 */
	public function export_settings(): void {
		$this->assert_manage();
		check_admin_referer( 'llms_txt_export', 'llms_txt_nonce' );
		$json = ( new Settings_Port( new Settings() ) )->to_json( ( new Settings() )->get(), LLMS_TXT_VERSION );
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="llms-txt-settings.json"' );
		echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Replace settings from an uploaded JSON export.
	 */
	public function import_settings(): void {
		$this->assert_manage();
		check_admin_referer( 'llms_txt_import', 'llms_txt_nonce' );
		$file = isset( $_FILES['llms_txt_import'] ) ? $_FILES['llms_txt_import'] : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- file upload is checked with is_uploaded_file and a size cap, then JSON-decoded.
		$redirect = admin_url( 'options-general.php?page=llms-txt' );
		if ( ! is_array( $file ) || empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			$this->import_notice( 'error', __( 'Choose a JSON export to import.', 'llms-txt' ) );
			wp_safe_redirect( $redirect );
			exit;
		}
		$size = isset( $file['size'] ) ? (int) $file['size'] : 0;
		if ( $size < 1 || $size > 2_000_000 ) {
			$this->import_notice( 'error', __( 'The export file must be under 2 MB.', 'llms-txt' ) );
			wp_safe_redirect( $redirect );
			exit;
		}
		$json = file_get_contents( $file['tmp_name'] );
		if ( ! is_string( $json ) ) {
			$this->import_notice( 'error', __( 'The export file could not be read.', 'llms-txt' ) );
			wp_safe_redirect( $redirect );
			exit;
		}
		try {
			$clean = ( new Settings_Port( new Settings() ) )->parse( $json );
		} catch ( \InvalidArgumentException $exception ) {
			$this->import_notice( 'error', $exception->getMessage() );
			wp_safe_redirect( $redirect );
			exit;
		}
		update_option( Settings::OPTION, $clean, true );
		( new Rewrite() )->add_rules();
		flush_rewrite_rules( false );
		Generator::make()->regenerate_all();
		$this->import_notice( 'success', __( 'Settings imported and the files were regenerated.', 'llms-txt' ) );
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * @param string $type    success or error.
	 * @param string $message Notice text.
	 */
	private function import_notice( string $type, string $message ): void {
		set_transient(
			'llms_txt_import_notice',
			array(
				'type'    => 'success' === $type ? 'success' : 'error',
				'message' => $message,
			),
			MINUTE_IN_SECONDS
		);
	}

	/**
	 * Map the default-language radio onto the prefix row.
	 *
	 * @param array<string, mixed> $input Posted settings.
	 * @return array<string, mixed>
	 */
	private function apply_prefix_default( array $input ): array {
		if ( ! isset( $input['prefixes'] ) || ! is_array( $input['prefixes'] ) || ! isset( $input['prefix_default'] ) ) {
			return $input;
		}
		$chosen = (string) $input['prefix_default'];
		foreach ( $input['prefixes'] as $index => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$row['default']            = (string) $index === $chosen ? '1' : '0';
			$input['prefixes'][ $index ] = $row;
		}
		return $input;
	}

	/**
	 * Stop the request when the user cannot manage the plugin.
	 */
	private function assert_manage(): void {
		if ( ! Capabilities::can_manage() ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to manage LLMs.txt.', 'llms-txt' ), '', array( 'response' => 403 ) );
		}
	}
}

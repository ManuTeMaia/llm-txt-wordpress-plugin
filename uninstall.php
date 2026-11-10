<?php
/**
 * Runs when the plugin is deleted from wp-admin.
 *
 * Settings, post meta, caches, and generated files stay unless
 * "Delete all data when the plugin is deleted" was turned on.
 *
 * @package LlmsTxt
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/includes/Lifecycle/Uninstall_Gate.php';

$settings = get_option( 'llms_txt_settings', array() );

wp_clear_scheduled_hook( 'llms_txt_daily_regenerate' );

if ( ! \LlmsTxt\Lifecycle\Uninstall_Gate::should_delete( $settings ) ) {
	return;
}

$signatures = get_option( 'llms_txt_disk_signatures', array() );

delete_option( 'llms_txt_settings' );
delete_option( 'llms_txt_status' );
delete_option( 'llms_txt_version' );
delete_option( 'llms_txt_disk_signatures' );
delete_option( 'llms_txt_cache_keys' );

global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_llms\_txt\_%' OR option_name LIKE '\_transient\_timeout\_llms\_txt\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

delete_post_meta_by_key( '_llms_txt_exclude' );
delete_post_meta_by_key( '_llms_txt_title' );

$uploads = wp_upload_dir( null, false );
if ( empty( $uploads['error'] ) && ! empty( $uploads['basedir'] ) ) {
	$directory = trailingslashit( $uploads['basedir'] ) . 'llms-txt';
	if ( is_dir( $directory ) ) {
		$entries = scandir( $directory );
		if ( is_array( $entries ) ) {
			foreach ( $entries as $entry ) {
				if ( '.' === $entry || '..' === $entry ) {
					continue;
				}
				$path = $directory . '/' . $entry;
				if ( is_file( $path ) ) {
					unlink( $path );
				}
			}
		}
		@rmdir( $directory ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}
}

$root = trailingslashit( ABSPATH );
foreach ( array( 'llms.txt', 'llms-full.txt' ) as $name ) {
	$path = $root . $name;
	if ( ! is_file( $path ) || ! is_array( $signatures ) || empty( $signatures[ $name ] ) || ! is_string( $signatures[ $name ] ) ) {
		continue;
	}
	$hash = md5_file( $path );
	if ( is_string( $hash ) && hash_equals( $signatures[ $name ], $hash ) ) {
		unlink( $path );
	}
}

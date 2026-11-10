<?php
/**
 * Optional WordPress update check against a JSON manifest.
 *
 * @package LlmsTxt
 */

namespace LlmsTxt\Update;

use LlmsTxt\Settings;

/**
 * Stays silent until an update manifest URL is saved. The header only enables the hook.
 */
final class Checker {
	public function register_hooks(): void {
		add_filter( 'update_plugins_manutemaia.com', array( $this, 'check' ), 10, 4 );
	}

	/**
	 * @param mixed                $update      Existing update result.
	 * @param array<string, mixed> $plugin_data Header data.
	 * @param string               $plugin_file Plugin basename.
	 * @param mixed                $locales     Requested locales.
	 * @return mixed
	 */
	public function check( mixed $update, array $plugin_data, string $plugin_file, mixed $locales ): mixed {
		unset( $plugin_data, $locales );
		$url = ( new Settings() )->get()['update_manifest_url'] ?? '';
		if ( ! is_string( $url ) || '' === $url ) {
			return $update;
		}
		$body = $this->fetch( $url );
		$offer = self::offer( LLMS_TXT_VERSION, $body );
		if ( null === $offer ) {
			return $update;
		}
		$offer['plugin'] = $plugin_file;
		return $offer;
	}

	/**
	 * Build an update offer from a manifest body. Null means "no update".
	 *
	 * @param string      $current Installed version.
	 * @param string|null $body    Manifest JSON, or null when it could not be read.
	 * @return array<string, string>|null
	 */
	public static function offer( string $current, ?string $body ): ?array {
		if ( null === $body || '' === trim( $body ) ) {
			return null;
		}
		$manifest = json_decode( $body, true );
		if ( ! is_array( $manifest ) ) {
			return null;
		}
		$version = isset( $manifest['version'] ) && is_string( $manifest['version'] ) ? $manifest['version'] : '';
		$package = '';
		foreach ( array( 'download_url', 'package' ) as $key ) {
			if ( isset( $manifest[ $key ] ) && is_string( $manifest[ $key ] ) && '' !== $manifest[ $key ] ) {
				$package = $manifest[ $key ];
				break;
			}
		}
		if ( '' === $version || '' === $package || ! version_compare( $version, $current, '>' ) ) {
			return null;
		}
		return array(
			'slug'         => 'llms-txt',
			'new_version'  => $version,
			'package'      => $package,
			'url'          => isset( $manifest['homepage'] ) && is_string( $manifest['homepage'] ) ? $manifest['homepage'] : 'https://manutemaia.com/',
			'requires'     => isset( $manifest['requires'] ) && is_string( $manifest['requires'] ) ? $manifest['requires'] : '6.4',
			'requires_php' => isset( $manifest['requires_php'] ) && is_string( $manifest['requires_php'] ) ? $manifest['requires_php'] : '8.1',
			'tested'       => isset( $manifest['tested'] ) && is_string( $manifest['tested'] ) ? $manifest['tested'] : '',
		);
	}

	/**
	 * @param string $url Manifest URL.
	 */
	private function fetch( string $url ): ?string {
		$cached = get_transient( 'llms_txt_update_manifest' );
		if ( is_array( $cached ) && ( $cached['url'] ?? '' ) === $url && is_string( $cached['body'] ?? null ) ) {
			return $cached['body'];
		}
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 5,
				'headers' => array( 'Accept' => 'application/json' ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return null;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		if ( 200 !== $code || ! is_string( $body ) ) {
			return null;
		}
		set_transient(
			'llms_txt_update_manifest',
			array(
				'url'  => $url,
				'body' => $body,
			),
			12 * HOUR_IN_SECONDS
		);
		return $body;
	}
}

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
		add_filter( 'upgrader_pre_download', array( $this, 'verify_download' ), 10, 3 );
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
		if ( ! is_string( $url ) || '' === $url || ! Manifest::is_acceptable_url( $url ) ) {
			return $update;
		}
		$body  = $this->fetch( $url );
		$offer = self::offer( LLMS_TXT_VERSION, $url, $body );
		if ( null === $offer ) {
			return $update;
		}
		update_option(
			'llms_txt_expected_update',
			array(
				'package' => $offer['package'],
				'sha256'  => $offer['sha256'],
			),
			false
		);
		unset( $offer['sha256'] );
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
	public static function offer( string $current, string $manifest_url, ?string $body ): ?array {
		if ( ! Manifest::is_acceptable_url( $manifest_url ) || null === $body || '' === trim( $body ) ) {
			return null;
		}
		$manifest = json_decode( $body, true, 16 );
		if ( ! is_array( $manifest ) ) {
			return null;
		}
		$version = isset( $manifest['version'] ) && is_string( $manifest['version'] ) ? $manifest['version'] : '';
		$package = '';
		foreach ( array( 'download_url', 'package' ) as $key ) {
			if ( isset( $manifest[ $key ] ) && is_string( $manifest[ $key ] ) && Manifest::is_acceptable_url( $manifest[ $key ] ) ) {
				$package = $manifest[ $key ];
				break;
			}
		}
		$checksum = isset( $manifest['sha256'] ) && is_string( $manifest['sha256'] ) ? strtolower( $manifest['sha256'] ) : '';
		if ( '' === $version || '' === $package || ! Manifest::is_sha256( $checksum ) || ! Manifest::same_host( $manifest_url, $package ) ) {
			return null;
		}
		if ( ! version_compare( $version, $current, '>' ) ) {
			return null;
		}
		return array(
			'slug'         => 'llms-txt',
			'new_version'  => $version,
			'package'      => $package,
			'sha256'       => $checksum,
			'url'          => 'https://manutemaia.com/',
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
		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'     => 5,
				'redirection' => 0,
				'headers'     => array( 'Accept' => 'application/json' ),
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

	/**
	 * Download the package ourselves and refuse it when the checksum does not match.
	 *
	 * @param mixed                               $reply    Existing short-circuit.
	 * @param string                              $package  Package URL.
	 * @param \WP_Upgrader                        $upgrader Upgrader instance.
	 * @return mixed
	 */
	public function verify_download( mixed $reply, string $package, \WP_Upgrader $upgrader ): mixed {
		unset( $upgrader );
		if ( false !== $reply || ! str_contains( $package, '://' ) ) {
			return $reply;
		}
		$expected = get_option( 'llms_txt_expected_update', array() );
		if ( ! is_array( $expected ) || ( $expected['package'] ?? '' ) !== $package || ! Manifest::is_sha256( (string) ( $expected['sha256'] ?? '' ) ) ) {
			return $reply;
		}
		if ( ! Manifest::is_acceptable_url( $package ) ) {
			return new \WP_Error( 'llms_txt_package', 'The update package URL is not https.' );
		}
		$response = wp_safe_remote_get(
			$package,
			array(
				'timeout'     => 20,
				'redirection' => 0,
			)
		);
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return new \WP_Error( 'llms_txt_package', 'The update package could not be downloaded.' );
		}
		$body = wp_remote_retrieve_body( $response );
		if ( ! is_string( $body ) || ! hash_equals( (string) $expected['sha256'], hash( 'sha256', $body ) ) ) {
			return new \WP_Error( 'llms_txt_package', 'The update package checksum did not match the manifest.' );
		}
		$temp = wp_tempnam( 'llms-txt.zip' );
		if ( ! is_string( $temp ) || false === file_put_contents( $temp, $body, LOCK_EX ) ) {
			return new \WP_Error( 'llms_txt_package', 'The update package could not be stored.' );
		}
		return $temp;
	}
}

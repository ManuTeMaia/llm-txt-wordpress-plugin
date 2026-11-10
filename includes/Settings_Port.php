<?php
/**
 * JSON export and import of the settings option.
 *
 * @package LlmsTxt
 */

namespace LlmsTxt;

/**
 * A portable copy of the filled-in settings, not cache or generated files.
 */
final class Settings_Port {
	public function __construct( private Settings $settings ) {
	}

	/**
	 * @param array<string, mixed> $settings Sanitized settings.
	 * @param string               $version  Plugin version stamp.
	 */
	public function document( array $settings, string $version ): array {
		return array(
			'plugin'      => 'llms-txt',
			'version'     => $version,
			'exported_at' => gmdate( 'c' ),
			'settings'    => $settings,
		);
	}

	/**
	 * @param array<string, mixed> $settings Sanitized settings.
	 * @param string               $version  Plugin version stamp.
	 */
	public function to_json( array $settings, string $version ): string {
		$flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
		$document = $this->document( $settings, $version );
		$json     = function_exists( 'wp_json_encode' ) ? wp_json_encode( $document, $flags ) : json_encode( $document, $flags );
		if ( ! is_string( $json ) ) {
			$json = '{}';
		}
		return $json . "\n";
	}

	/**
	 * Read an export file and return sanitized settings.
	 *
	 * @param string $json Export document.
	 * @return array<string, mixed>
	 */
	public function parse( string $json ): array {
		$decoded = json_decode( $json, true );
		if ( ! is_array( $decoded ) ) {
			throw new \InvalidArgumentException( 'The file is not a JSON settings export.' );
		}
		if ( ( $decoded['plugin'] ?? '' ) !== 'llms-txt' ) {
			throw new \InvalidArgumentException( 'This JSON file is not an LLMs.txt settings export.' );
		}
		if ( ! isset( $decoded['settings'] ) || ! is_array( $decoded['settings'] ) ) {
			throw new \InvalidArgumentException( 'The export does not contain a settings object.' );
		}
		return $this->settings->sanitize( $decoded['settings'] );
	}
}

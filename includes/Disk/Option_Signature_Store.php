<?php
/**
 * Stores web-root signatures in a WordPress option.
 *
 * @package LlmsTxt
 */

namespace LlmsTxt\Disk;

/**
 * Option-backed signature store.
 */
final class Option_Signature_Store implements Signature_Store {
	public const OPTION = 'llms_txt_disk_signatures';

	/**
	 * @return array<string, string>
	 */
	public function all(): array {
		if ( ! function_exists( 'get_option' ) ) {
			return array();
		}
		$value = get_option( self::OPTION, array() );
		if ( ! is_array( $value ) ) {
			return array();
		}

		$clean = array();
		foreach ( $value as $name => $hash ) {
			if ( is_string( $name ) && is_string( $hash ) ) {
				$clean[ $name ] = $hash;
			}
		}
		return $clean;
	}

	/**
	 * @param array<string, string> $signatures Signatures to persist.
	 */
	public function save( array $signatures ): void {
		if ( function_exists( 'update_option' ) ) {
			update_option( self::OPTION, $signatures, false );
		}
	}
}

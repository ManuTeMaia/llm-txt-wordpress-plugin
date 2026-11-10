<?php
/**
 * Capability used by the settings screen and CLI-equivalent actions.
 *
 * @package LlmsTxt
 */

namespace LlmsTxt;

/**
 * Defaults to manage_options and can be replaced with the llms_txt_capability filter.
 */
final class Capabilities {
	public const MANAGE = 'manage_options';

	/**
	 * Capability required to change settings or regenerate.
	 */
	public static function required(): string {
		$capability = apply_filters( 'llms_txt_capability', self::MANAGE );
		if ( is_string( $capability ) && in_array( $capability, array( self::MANAGE, 'manage_network_options' ), true ) ) {
			return $capability;
		}
		return self::MANAGE;
	}

	/**
	 * Whether the current user may manage the files.
	 */
	public static function can_manage(): bool {
		return current_user_can( self::required() );
	}
}

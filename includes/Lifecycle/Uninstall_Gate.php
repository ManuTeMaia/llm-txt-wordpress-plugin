<?php
/**
 * Whether deleting the plugin should also delete its data.
 *
 * @package LlmsTxt
 */

namespace LlmsTxt\Lifecycle;

/**
 * Off unless the owner explicitly opts in. Reinstalling otherwise keeps settings.
 */
final class Uninstall_Gate {
	/**
	 * @param mixed $settings Stored llms_txt_settings value.
	 */
	public static function should_delete( mixed $settings ): bool {
		if ( ! is_array( $settings ) ) {
			return false;
		}
		$value = $settings['delete_data_on_uninstall'] ?? false;
		return true === $value || 1 === $value || '1' === $value || 'true' === $value || 'on' === $value || 'yes' === $value;
	}
}

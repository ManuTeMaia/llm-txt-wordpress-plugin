<?php
/**
 * Outcome of a web-root write.
 *
 * @package LlmsTxt
 */

namespace LlmsTxt\Disk;

/**
 * Success flag and a message safe to show in wp-admin.
 */
final class Write_Result {
	public function __construct(
		public readonly bool $ok,
		public readonly string $message
	) {
	}
}

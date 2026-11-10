<?php
/**
 * Wall-clock limit for one regeneration.
 *
 * @package LlmsTxt
 */

namespace LlmsTxt;

/**
 * Stops a rebuild that has already run long enough, without affecting public reads.
 */
final class Budget {
	private static ?float $deadline = null;

	/**
	 * @param int $seconds Maximum seconds for the current rebuild.
	 */
	public static function start( int $seconds ): void {
		self::$deadline = microtime( true ) + max( 1, $seconds );
	}

	public static function reset(): void {
		self::$deadline = null;
	}

	public static function exceeded(): bool {
		return null !== self::$deadline && microtime( true ) >= self::$deadline;
	}
}

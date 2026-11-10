<?php
/**
 * What a public request should do with the copies it found.
 *
 * @package LlmsTxt
 */

namespace LlmsTxt\Http;

/**
 * Serves the last good document and queues at most one rebuild. 503 only if none exists.
 */
final class Serve_Decision {
	/**
	 * @param string|null $hot     Transient copy. Empty counts as missing.
	 * @param string|null $durable Uploads or option copy. Empty counts as missing.
	 * @param bool        $queued  Whether a one-off rebuild is already scheduled or running.
	 * @return array{body:?string,rebuild:bool,status:int}
	 */
	public function decide( ?string $hot, ?string $durable, bool $queued ): array {
		$hot     = is_string( $hot ) && '' !== $hot ? $hot : null;
		$durable = is_string( $durable ) && '' !== $durable ? $durable : null;
		if ( null !== $hot ) {
			return array(
				'body'    => $hot,
				'rebuild' => false,
				'status'  => 200,
			);
		}
		if ( null !== $durable ) {
			return array(
				'body'    => $durable,
				'rebuild' => ! $queued,
				'status'  => 200,
			);
		}
		return array(
			'body'    => null,
			'rebuild' => ! $queued,
			'status'  => 503,
		);
	}
}

<?php
/**
 * The few post facts the exclusion policy needs.
 *
 * @package LlmsTxt
 */

namespace LlmsTxt\Content;

/**
 * Value object so the policy can be tested without WordPress.
 */
final class Post_Snapshot {
	public function __construct(
		public readonly string $status,
		public readonly string $password,
		public readonly bool $excluded,
		public readonly bool $noindex
	) {
	}
}

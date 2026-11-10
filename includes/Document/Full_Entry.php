<?php
/**
 * One page inside llms-full.txt.
 *
 * @package LlmsTxt
 */

namespace LlmsTxt\Document;

/**
 * Expanded Markdown for a single public post.
 */
final class Full_Entry {
	/**
	 * @param string $title    Entry title.
	 * @param string $url      Canonical URL.
	 * @param string $modified ISO-8601 last modified time.
	 * @param string $language Language code, or an empty string.
	 * @param string $body     Clean Markdown body.
	 */
	public function __construct(
		public readonly string $title,
		public readonly string $url,
		public readonly string $modified,
		public readonly string $language,
		public readonly string $body
	) {
	}
}

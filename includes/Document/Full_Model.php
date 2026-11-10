<?php
/**
 * Structured llms-full.txt document.
 *
 * @package LlmsTxt
 */

namespace LlmsTxt\Document;

/**
 * Site header plus the expanded entries.
 */
final class Full_Model {
	/**
	 * @param string           $title    Project or site name.
	 * @param string           $summary  Blockquote summary.
	 * @param list<Full_Entry> $entries  Entries in index order.
	 */
	public function __construct(
		public readonly string $title,
		public readonly string $summary,
		public readonly array $entries
	) {
	}
}

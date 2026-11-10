<?php
/**
 * One H2 section of an llms.txt document.
 *
 * @package LlmsTxt
 */

namespace LlmsTxt\Document;

/**
 * Immutable section. The Optional section is rendered last.
 */
final class Section_Model {
	/**
	 * @param string $heading  Section heading without the leading hashes.
	 * @param string $body     Markdown body. Lists and prose are both allowed.
	 * @param bool   $optional Whether agents may skip this section.
	 */
	public function __construct(
		public readonly string $heading,
		public readonly string $body,
		public readonly bool $optional = false
	) {
	}
}

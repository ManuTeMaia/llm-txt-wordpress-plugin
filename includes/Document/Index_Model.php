<?php
/**
 * Structured llms.txt document before it is rendered.
 *
 * @package LlmsTxt
 */

namespace LlmsTxt\Document;

/**
 * H1, summary, intro, and H2 sections.
 */
final class Index_Model {
	/**
	 * @param string              $title    Project or site name.
	 * @param string              $summary  Blockquote summary. Empty omits the blockquote.
	 * @param string              $intro    Free-form Markdown that must not introduce headings.
	 * @param list<Section_Model> $sections Sections in editor order. Optional sections are sorted last.
	 */
	public function __construct(
		public readonly string $title,
		public readonly string $summary,
		public readonly string $intro,
		public readonly array $sections
	) {
	}
}

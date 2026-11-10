<?php
/**
 * llms.txt rendering.
 *
 * @package LlmsTxt
 */

declare( strict_types=1 );

namespace LlmsTxt\Tests;

use LlmsTxt\Document\Index_Model;
use LlmsTxt\Document\Index_Renderer;
use LlmsTxt\Document\Markdown_Text;
use LlmsTxt\Document\Section_Model;
use PHPUnit\Framework\TestCase;

final class IndexRendererTest extends TestCase {
	public function test_renders_the_spec_order_and_keeps_optional_last(): void {
		$model = new Index_Model(
			'Terav',
			'Veterinary AI assistant',
			"Intro paragraph.\n## Not a heading",
			array(
				new Section_Model( 'Optional', "- [Early](https://example.com/early)", true ),
				new Section_Model(
					'Product and access',
					"- [Overview](https://terav.vet/): Features and pricing\n- [Open Terav](https://terav.app/): Web application\n\nTrial details stay in the product section."
				),
				new Section_Model(
					'Articles',
					Markdown_Text::link_item( 'A [draft] title', 'https://terav.vet/lab workflow/', 'How to read a panel' )
				),
			)
		);

		$expected = <<<'MD'
# Terav

> Veterinary AI assistant

Intro paragraph.
**Not a heading**

## Product and access

- [Overview](https://terav.vet/): Features and pricing
- [Open Terav](https://terav.app/): Web application

Trial details stay in the product section.

## Articles

- [A \[draft\] title](https://terav.vet/lab%20workflow/): How to read a panel

## Optional

- [Early](https://example.com/early)

MD;

		self::assertSame( $expected, ( new Index_Renderer() )->render( $model ) );
	}

	public function test_omits_an_empty_summary_and_empty_sections(): void {
		$markdown = ( new Index_Renderer() )->render(
			new Index_Model( 'Only', '', '', array( new Section_Model( 'Empty', '   ' ) ) )
		);

		self::assertSame( "# Only\n", $markdown );
	}
}

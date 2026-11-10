<?php
/**
 * The published Elementor view wins over leftover builder JSON.
 *
 * @package LlmsTxt
 */

declare( strict_types=1 );

namespace LlmsTxt\Tests;

use LlmsTxt\Content\Content_Sources;
use LlmsTxt\Content\Published_Document_Selector;
use LlmsTxt\Content\Template_Policy;
use PHPUnit\Framework\TestCase;

final class PublishedContentTest extends TestCase {
	public function test_theme_template_replaces_stale_elementor_json(): void {
		$selector = new Published_Document_Selector();

		self::assertSame( 1012, $selector->document_id( 15, array( 1012 ) ) );
		self::assertSame( 15, $selector->document_id( 15, array() ) );
		self::assertSame( 15, $selector->document_id( 15, array( 15 ) ) );

		$html = Content_Sources::html(
			'<h2>Grounded in real research</h2><p>The current homepage.</p>',
			'<p>рекомендации по лечению животных</p>'
		);

		self::assertStringContainsString( 'Grounded in real research', $html );
		self::assertStringContainsString( 'The current homepage.', $html );
		self::assertStringNotContainsString( 'рекомендации по лечению животных', $html );
	}

	public function test_empty_render_can_fall_back_to_builder_json(): void {
		$html = Content_Sources::html( '', '<p>Published builder text.</p>' );
		self::assertStringContainsString( 'Published builder text.', $html );
	}

	public function test_single_post_template_keeps_each_posts_body_in_each_language(): void {
		$policy   = new Template_Policy();
		$template = array(
			array(
				'elType'     => 'widget',
				'widgetType' => 'theme-post-content',
				'settings'   => array(),
			),
		);
		self::assertTrue( $policy->embeds_post_content( $template ) );

		$wrapper = array(
			'embeds_post_content' => true,
			'html_by_language'    => array(
				'en' => '<h1>What’s new in Terav: shaped by our first 90 users</h1><p>November 9, 2026</p>',
				'ru' => '<h1>What’s new in Terav: shaped by our first 90 users</h1>',
			),
		);
		$posts   = array(
			array(
				'id'       => 20,
				'language' => 'en',
				'own_html' => '<p>A near-miss can show where the handoff failed.</p>',
			),
			array(
				'id'       => 21,
				'language' => 'en',
				'own_html' => '<p>Spoken case notes become a reviewable question.</p>',
			),
			array(
				'id'       => 22,
				'language' => 'ru',
				'own_html' => '<p>Почти случившаяся ошибка показывает, где передача смены теряет деталь.</p>',
			),
		);

		$bodies = array();
		foreach ( $posts as $post ) {
			$bodies[ $post['id'] ] = $policy->html_for_post( $post, $wrapper );
		}

		self::assertStringContainsString( 'near-miss', $bodies[20] );
		self::assertStringContainsString( 'Spoken case notes', $bodies[21] );
		self::assertStringContainsString( 'Почти случившаяся ошибка', $bodies[22] );
		foreach ( $bodies as $html ) {
			self::assertStringNotContainsString( 'What’s new in Terav', $html );
		}
	}

	public function test_homepage_template_is_used_per_language_and_not_the_stale_draft(): void {
		$policy = new Template_Policy();
		self::assertFalse(
			$policy->embeds_post_content(
				array(
					array(
						'elType'     => 'widget',
						'widgetType' => 'heading',
						'settings'   => array( 'title' => 'The AI Assistant' ),
					),
				)
			)
		);

		$landing = array(
			'embeds_post_content' => false,
			'html_by_language'    => array(
				'en' => '<h1>The AI Assistant</h1><p>Grounded in real research.</p>',
				'ru' => '<h1>AI-ассистент для ветеринаров</h1><p>На основе актуальных исследований.</p>',
			),
		);
		$english = $policy->html_for_post(
			array(
				'id'       => 15,
				'language' => 'en',
				'own_html' => '<p>рекомендации по лечению животных</p>',
			),
			$landing
		);
		$russian = $policy->html_for_post(
			array(
				'id'       => 16,
				'language' => 'ru',
				'own_html' => '<p>рекомендации по лечению животных</p>',
			),
			$landing
		);

		self::assertStringContainsString( 'Grounded in real research', $english );
		self::assertStringContainsString( 'На основе актуальных исследований', $russian );
		self::assertStringNotContainsString( 'рекомендации по лечению животных', $english );
		self::assertStringNotContainsString( 'рекомендации по лечению животных', $russian );
	}

	public function test_revisions_and_autosaves_are_not_published_sources(): void {
		$selector = new Published_Document_Selector();

		self::assertFalse( $selector->is_published_source( 'inherit', '15-autosave-v1' ) );
		self::assertFalse( $selector->is_published_source( 'draft', 'home' ) );
		self::assertTrue( $selector->is_published_source( 'publish', 'home' ) );
		self::assertFalse( $selector->is_published_source( 'publish', '1012-autosave' ) );
	}
}

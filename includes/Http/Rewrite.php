<?php
/**
 * Rewrite rules for the public text files.
 *
 * @package LlmsTxt
 */

namespace LlmsTxt\Http;

/**
 * Registers /llms.txt, /llms-full.txt, and the per-language copies.
 */
final class Rewrite {
	public function register_hooks(): void {
		add_action( 'init', array( $this, 'add_rules' ) );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
	}

	/**
	 * Add rules at the top so a Page cannot shadow them.
	 */
	public function add_rules(): void {
		add_rewrite_rule( '^llms\.txt$', 'index.php?llms_txt=index', 'top' );
		add_rewrite_rule( '^llms-full\.txt$', 'index.php?llms_txt=full', 'top' );
		// Language copies are always routed. The controller answers 404 unless
		// path mode is on, so changing that setting does not require a new rule.
		add_rewrite_rule(
			'^([a-z]{2,3}(?:-[a-z0-9]{2,12})?)/llms\.txt$',
			'index.php?llms_txt=index&llms_lang=$matches[1]',
			'top'
		);
		add_rewrite_rule(
			'^([a-z]{2,3}(?:-[a-z0-9]{2,12})?)/llms-full\.txt$',
			'index.php?llms_txt=full&llms_lang=$matches[1]',
			'top'
		);
	}

	/**
	 * @param list<string> $vars Public query vars.
	 * @return list<string>
	 */
	public function query_vars( array $vars ): array {
		$vars[] = 'llms_txt';
		$vars[] = 'llms_lang';
		return $vars;
	}
}

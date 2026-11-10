<?php
/**
 * Recommended robots.txt snippet. The plugin never writes robots.txt.
 *
 * @package LlmsTxt
 */

namespace LlmsTxt\Admin;

/**
 * Copy-paste guidance for AI crawlers.
 */
final class Robots_Guide {
	/**
	 * Snippet that allows known AI crawlers to read the generated files.
	 */
	public static function snippet(): string {
		return <<<'TXT'
# AI crawlers — review before pasting. LLMs.txt does not modify robots.txt.
# Allow rules should appear before any Disallow for the same user-agent.

User-agent: GPTBot
User-agent: ChatGPT-User
User-agent: OAI-SearchBot
User-agent: ClaudeBot
User-agent: Claude-User
User-agent: Claude-SearchBot
User-agent: Google-Extended
User-agent: PerplexityBot
User-agent: Perplexity-User
User-agent: Amazonbot
User-agent: Applebot-Extended
User-agent: Bytespider
User-agent: CCBot
User-agent: meta-externalagent
Allow: /llms.txt
Allow: /llms-full.txt

# Optional training opt-out. Not applied automatically.
# To keep the llms files readable while closing the rest of the site to one
# crawler, give that crawler its own group and put Allow before Disallow:
# User-agent: GPTBot
# Allow: /llms.txt
# Allow: /llms-full.txt
# Disallow: /
TXT;
	}
}

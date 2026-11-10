<?php
/**
 * One language the catalog can address.
 *
 * @package LlmsTxt
 */

namespace LlmsTxt\Language;

/**
 * Language code, display name, and the URL prefix used on the front end.
 */
final class Language {
	public function __construct(
		public readonly string $code,
		public readonly string $name,
		public readonly string $prefix,
		public readonly bool $default
	) {
	}
}

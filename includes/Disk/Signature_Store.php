<?php
/**
 * Remembers the checksum of files this plugin wrote.
 *
 * @package LlmsTxt
 */

namespace LlmsTxt\Disk;

/**
 * Storage for web-root file signatures.
 */
interface Signature_Store {
	/**
	 * @return array<string, string> Map of file name to md5.
	 */
	public function all(): array;

	/**
	 * @param array<string, string> $signatures Signatures to persist.
	 */
	public function save( array $signatures ): void;
}

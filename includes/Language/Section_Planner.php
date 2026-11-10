<?php
/**
 * Decides which language variants of a section belong in a given file.
 *
 * @package LlmsTxt
 */

namespace LlmsTxt\Language;

/**
 * Pure planning for single-file, split-section, and per-language URL modes.
 */
final class Section_Planner {
	/**
	 * Plans for an automatic section.
	 *
	 * A null language in a plan means "every language".
	 *
	 * @param array{heading:string,language:string} $section        Section config.
	 * @param string                                 $mode           single, sections, or paths.
	 * @param string|null                            $route_language Language from the URL, or null for the root file.
	 * @param string                                 $root_scope     default or all.
	 * @param list<Language>                         $languages      Known languages.
	 * @param string                                 $default_code   Default language code.
	 * @return list<array{heading:string,language:?string}>
	 */
	public function auto_plans(
		array $section,
		string $mode,
		?string $route_language,
		string $root_scope,
		array $languages,
		string $default_code
	): array {
		$heading = $section['heading'];
		$chosen  = $section['language'];

		if ( 'paths' === $mode ) {
			$file_language = $this->file_language( $route_language, $root_scope, $default_code );
			if ( '' !== $chosen && null !== $file_language && $chosen !== $file_language ) {
				return array();
			}
			$language = '' !== $chosen ? $chosen : $file_language;
			return array(
				array(
					'heading'  => $heading,
					'language' => $language,
				),
			);
		}

		if ( 'sections' === $mode && '' === $chosen && count( $languages ) > 1 ) {
			$plans = array();
			foreach ( $languages as $language ) {
				$plans[] = array(
					'heading'  => $heading . ' (' . $language->name . ')',
					'language' => $language->code,
				);
			}
			return $plans;
		}

		if ( '' !== $chosen ) {
			return array(
				array(
					'heading'  => $heading,
					'language' => $chosen,
				),
			);
		}

		return array(
			array(
				'heading'  => $heading,
				'language' => null,
			),
		);
	}

	/**
	 * Whether a hand-written section belongs in this file.
	 *
	 * @param string      $section_language Language assigned to the section, or empty for all files.
	 * @param string      $mode             Language mode.
	 * @param string|null $route_language   Language from the URL.
	 * @param string      $root_scope       default or all.
	 * @param string      $default_code     Default language code.
	 */
	public function static_visible(
		string $section_language,
		string $mode,
		?string $route_language,
		string $root_scope,
		string $default_code
	): bool {
		if ( 'paths' !== $mode || '' === $section_language ) {
			return true;
		}

		$file_language = $this->file_language( $route_language, $root_scope, $default_code );
		if ( null === $file_language ) {
			return true;
		}

		return $section_language === $file_language;
	}

	/**
	 * Language of the file being rendered. Null means every language.
	 *
	 * @param string|null $route        URL language.
	 * @param string      $root_scope   default or all.
	 * @param string      $default_code Default language code.
	 */
	public function file_language( ?string $route, string $root_scope, string $default_code ): ?string {
		if ( null !== $route && '' !== $route ) {
			return $route;
		}
		if ( 'all' === $root_scope ) {
			return null;
		}

		return '' !== $default_code ? $default_code : null;
	}
}

<?php
/**
 * Plugin settings: defaults, reading, and sanitizing.
 *
 * @package LlmsTxt
 */

namespace LlmsTxt;

/**
 * One option array, sanitized on the way in and on the way out.
 */
final class Settings {
	public const OPTION = 'llms_txt_settings';

	/**
	 * Stored settings merged through the sanitizer.
	 *
	 * @return array<string, mixed>
	 */
	public function get(): array {
		$stored = function_exists( 'get_option' ) ? get_option( self::OPTION, null ) : null;
		if ( ! is_array( $stored ) ) {
			return $this->sanitize( self::defaults() );
		}
		return $this->sanitize( $stored );
	}

	/**
	 * Defaults for a site that has not saved the screen yet.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'enabled'              => true,
			'title_mode'           => 'site',
			'title'                => '',
			'summary_mode'         => 'tagline',
			'summary'              => '',
			'intro'                => '',
			'language_mode'        => 'single',
			'root_scope'           => 'default',
			'respect_noindex'      => true,
			'description_length'   => 240,
			'hide_empty_sections'  => true,
			'link_header'          => true,
			'cache_max_age'        => 600,
			'full_enabled'         => true,
			'full_byte_cap'        => 1048576,
			'full_include_linked'  => true,
			'delivery'             => 'dynamic',
			'disk_overwrite_ack'   => false,
			'delete_data_on_uninstall' => false,
			'update_manifest_url'  => '',
			'sitemap_in_optional'  => true,
			'optional_heading'     => 'Optional',
			'sitemap_url'          => '',
			'sitemap_label'        => 'Sitemap',
			'sitemap_note'         => 'Additional public pages and articles.',
			'optional_extra'       => '',
			'translations'         => array(),
			'prefixes'             => array(
				array(
					'code'    => self::default_language_code(),
					'prefix'  => '',
					'name'    => self::default_language_name(),
					'default' => true,
				),
			),
			'sections'             => array(
				array(
					'id'           => 'pages',
					'type'         => 'auto',
					'heading'      => 'Pages',
					'language'     => '',
					'markdown'     => '',
					'post_type'    => 'page',
					'taxonomy'     => '',
					'terms'        => '',
					'limit'        => 20,
					'orderby'      => 'menu_order',
					'order'        => 'ASC',
					'descriptions' => true,
				),
				array(
					'id'           => 'articles',
					'type'         => 'auto',
					'heading'      => 'Articles',
					'language'     => '',
					'markdown'     => '',
					'post_type'    => 'post',
					'taxonomy'     => '',
					'terms'        => '',
					'limit'        => 20,
					'orderby'      => 'date',
					'order'        => 'DESC',
					'descriptions' => true,
				),
			),
		);
	}

	/**
	 * Title that should appear as the H1.
	 *
	 * @param array<string, mixed> $settings Clean settings.
	 */
	public function resolved_title( array $settings ): string {
		if ( 'custom' === ( $settings['title_mode'] ?? '' ) && is_string( $settings['title'] ) && '' !== $settings['title'] ) {
			return $settings['title'];
		}
		if ( function_exists( 'get_bloginfo' ) ) {
			$name = (string) get_bloginfo( 'name' );
			if ( '' !== $name ) {
				return $name;
			}
		}
		return is_string( $settings['title'] ?? null ) && '' !== $settings['title'] ? $settings['title'] : 'Untitled';
	}

	/**
	 * Blockquote summary. An empty string omits the blockquote.
	 *
	 * @param array<string, mixed> $settings Clean settings.
	 */
	public function resolved_summary( array $settings ): string {
		$mode = (string) ( $settings['summary_mode'] ?? 'tagline' );
		if ( 'none' === $mode ) {
			return '';
		}
		if ( 'custom' === $mode ) {
			return is_string( $settings['summary'] ?? null ) ? (string) $settings['summary'] : '';
		}
		if ( function_exists( 'get_bloginfo' ) ) {
			return (string) get_bloginfo( 'description' );
		}
		return is_string( $settings['summary'] ?? null ) ? (string) $settings['summary'] : '';
	}

	/**
	 * Header fields for one file. A language file can override any of them.
	 *
	 * @param array<string, mixed> $settings Clean settings.
	 * @param string|null          $language Route language, or null for the root file.
	 * @return array{title:string,summary:string,intro:string}
	 */
	public function header_for( array $settings, ?string $language ): array {
		$title   = $this->resolved_title( $settings );
		$summary = $this->resolved_summary( $settings );
		$intro   = is_string( $settings['intro'] ?? null ) ? (string) $settings['intro'] : '';

		if ( null !== $language && isset( $settings['translations'][ $language ] ) && is_array( $settings['translations'][ $language ] ) ) {
			$row = $settings['translations'][ $language ];
			if ( isset( $row['title'] ) && is_string( $row['title'] ) && '' !== $row['title'] ) {
				$title = $row['title'];
			}
			if ( isset( $row['summary'] ) && is_string( $row['summary'] ) && '' !== $row['summary'] ) {
				$summary = $row['summary'];
			}
			if ( isset( $row['intro'] ) && is_string( $row['intro'] ) && '' !== $row['intro'] ) {
				$intro = $row['intro'];
			}
		}

		return array(
			'title'   => $title,
			'summary' => $summary,
			'intro'   => $intro,
		);
	}

	/**
	 * Post types referenced by automatic sections.
	 *
	 * @return list<string>
	 */
	public function watched_post_types(): array {
		$settings = $this->get();
		$types    = array();
		foreach ( $settings['sections'] as $section ) {
			if ( is_array( $section ) && 'auto' === ( $section['type'] ?? '' ) && is_string( $section['post_type'] ?? null ) ) {
				$types[] = $section['post_type'];
			}
		}
		if ( array() === $types ) {
			$types = array( 'post', 'page' );
		}
		return array_values( array_unique( $types ) );
	}

	/**
	 * Sanitize a raw settings payload.
	 *
	 * @param mixed              $input   Posted or stored settings.
	 * @param list<string>|null  $allowed Post types the caller considers public.
	 * @return array<string, mixed>
	 */
	public function sanitize( mixed $input, ?array $allowed = null ): array {
		$defaults = self::defaults();
		$input    = is_array( $input ) ? $input : array();
		$clean    = $defaults;

		$clean['enabled']             = $this->flag( $input['enabled'] ?? false );
		$clean['title_mode']          = $this->enum( $input['title_mode'] ?? '', array( 'site', 'custom' ), 'site' );
		$clean['title']               = $this->line( $input['title'] ?? '', 180 );
		$clean['summary_mode']        = $this->enum( $input['summary_mode'] ?? '', array( 'tagline', 'custom', 'none' ), 'tagline' );
		$clean['summary']             = $this->line( $input['summary'] ?? '', 2000 );
		$clean['intro']               = $this->markdown( $input['intro'] ?? '', 20000 );
		$clean['language_mode']       = $this->enum( $input['language_mode'] ?? '', array( 'single', 'sections', 'paths' ), 'single' );
		$clean['root_scope']          = $this->enum( $input['root_scope'] ?? '', array( 'default', 'all' ), 'default' );
		$clean['respect_noindex']     = $this->flag( $input['respect_noindex'] ?? false );
		$clean['description_length']  = $this->int_range( $input['description_length'] ?? null, 40, 500, 240 );
		$clean['hide_empty_sections'] = $this->flag( $input['hide_empty_sections'] ?? false );
		$clean['link_header']         = $this->flag( $input['link_header'] ?? false );
		$clean['cache_max_age']       = $this->int_range( $input['cache_max_age'] ?? null, 0, 86400, 600 );
		$clean['full_enabled']        = $this->flag( $input['full_enabled'] ?? false );
		$kilobytes                    = $input['full_cap_kb'] ?? null;
		if ( null === $kilobytes && isset( $input['full_byte_cap'] ) ) {
			$kilobytes = ( (int) $input['full_byte_cap'] ) / 1024;
		}
		$clean['full_byte_cap']       = $this->int_range( $kilobytes, 64, 10240, 1024 ) * 1024;
		$clean['full_include_linked'] = $this->flag( $input['full_include_linked'] ?? false );
		$clean['delivery']            = $this->enum( $input['delivery'] ?? '', array( 'dynamic', 'disk' ), 'dynamic' );
		$clean['disk_overwrite_ack']  = $this->flag( $input['disk_overwrite_ack'] ?? false );
		$clean['delete_data_on_uninstall'] = $this->flag( $input['delete_data_on_uninstall'] ?? false );
		$clean['update_manifest_url'] = $this->url( $input['update_manifest_url'] ?? '' );
		$clean['sitemap_in_optional'] = $this->flag( $input['sitemap_in_optional'] ?? false );
		$clean['optional_heading']    = $this->line( $input['optional_heading'] ?? 'Optional', 80 );
		if ( '' === $clean['optional_heading'] ) {
			$clean['optional_heading'] = 'Optional';
		}
		$clean['sitemap_url']         = $this->url( $input['sitemap_url'] ?? '' );
		$clean['sitemap_label']       = $this->line( $input['sitemap_label'] ?? 'Sitemap', 120 );
		if ( '' === $clean['sitemap_label'] ) {
			$clean['sitemap_label'] = 'Sitemap';
		}
		$clean['sitemap_note']        = $this->line( $input['sitemap_note'] ?? '', 300 );
		$clean['optional_extra']      = $this->markdown( $input['optional_extra'] ?? '', 20000 );
		$clean['prefixes']            = $this->prefixes( $input['prefixes'] ?? $defaults['prefixes'] );
		$clean['translations']        = $this->translations( $input['translations'] ?? array() );
		$clean['sections']            = $this->sections( $input['sections'] ?? array(), $allowed );

		return $clean;
	}

	/**
	 * @param mixed $value Raw flag.
	 */
	private function flag( mixed $value ): bool {
		return true === $value || 1 === $value || '1' === $value || 'true' === $value || 'on' === $value || 'yes' === $value;
	}

	/**
	 * @param mixed        $value   Raw value.
	 * @param list<string> $allowed Allowed values.
	 * @param string       $default Fallback.
	 */
	private function enum( mixed $value, array $allowed, string $default ): string {
		$value = is_string( $value ) ? $value : '';
		return in_array( $value, $allowed, true ) ? $value : $default;
	}

	/**
	 * @param mixed $value Raw number.
	 * @param int   $min   Inclusive minimum.
	 * @param int   $max   Inclusive maximum.
	 * @param int   $default Fallback when the value is not numeric.
	 */
	private function int_range( mixed $value, int $min, int $max, int $default ): int {
		if ( is_int( $value ) || ( is_string( $value ) && is_numeric( trim( $value ) ) ) || is_float( $value ) ) {
			return max( $min, min( $max, (int) $value ) );
		}
		return $default;
	}

	/**
	 * @param mixed $value Raw text.
	 * @param int   $max   Maximum characters.
	 */
	private function line( mixed $value, int $max ): string {
		$text = $this->markdown( $value, $max );
		$text = trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
		$text = trim( strip_tags( $text ) );
		if ( mb_strlen( $text ) > $max ) {
			$text = mb_substr( $text, 0, $max );
		}
		return $text;
	}

	/**
	 * @param mixed $value Raw Markdown.
	 * @param int   $max   Maximum characters.
	 */
	private function markdown( mixed $value, int $max ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}
		$value = str_replace( "\0", '', $value );
		if ( function_exists( 'wp_check_invalid_utf8' ) ) {
			$value = (string) wp_check_invalid_utf8( $value, true );
		}
		$value = str_replace( array( "\r\n", "\r" ), "\n", $value );
		$value = (string) preg_replace( '#<\s*(script|style|iframe|object|embed)\b[^>]*>.*?<\s*/\s*\1\s*>#is', '', $value );
		$value = (string) preg_replace( '#<\s*(script|style|iframe|object|embed)\b[^>]*\/?>#is', '', $value );
		$value = (string) preg_replace( '/\sjavascript\s*:/i', '', $value );
		if ( mb_strlen( $value ) > $max ) {
			$value = mb_substr( $value, 0, $max );
		}
		return trim( $value );
	}

	/**
	 * @param mixed $value Raw URL.
	 */
	private function url( mixed $value ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}
		$value = trim( $value );
		if ( '' === $value ) {
			return '';
		}
		if ( function_exists( 'esc_url_raw' ) ) {
			return (string) esc_url_raw( $value );
		}
		if ( 1 !== preg_match( '#^https?://#i', $value ) ) {
			return '';
		}
		return $this->line( $value, 500 );
	}

	/**
	 * @param mixed $input Raw per-language header overrides.
	 * @return array<string, array{title:string,summary:string,intro:string}>
	 */
	private function translations( mixed $input ): array {
		if ( ! is_array( $input ) ) {
			return array();
		}
		$clean = array();
		foreach ( $input as $code => $row ) {
			if ( count( $clean ) >= 20 || ! is_array( $row ) ) {
				continue;
			}
			$code = strtolower( $this->line( is_string( $code ) ? $code : '', 20 ) );
			if ( 1 !== preg_match( '/^[a-z]{2,3}(?:-[a-z0-9]{2,12})?$/', $code ) ) {
				continue;
			}
			$title   = $this->line( $row['title'] ?? '', 180 );
			$summary = $this->line( $row['summary'] ?? '', 2000 );
			$intro   = $this->markdown( $row['intro'] ?? '', 20000 );
			if ( '' === $title && '' === $summary && '' === $intro ) {
				continue;
			}
			$clean[ $code ] = array(
				'title'   => $title,
				'summary' => $summary,
				'intro'   => $intro,
			);
		}
		return $clean;
	}

	/**
	 * @param mixed $input Raw prefix rows.
	 * @return list<array{code:string,prefix:string,name:string,default:bool}>
	 */
	private function prefixes( mixed $input ): array {
		if ( ! is_array( $input ) ) {
			$input = array();
		}
		$rows    = array();
		$seen    = array();
		$blocked = array( 'wp-admin', 'wp-content', 'wp-includes', 'wp-json' );
		foreach ( array_values( $input ) as $row ) {
			if ( ! is_array( $row ) || count( $rows ) >= 20 ) {
				continue;
			}
			$code = strtolower( $this->line( $row['code'] ?? '', 20 ) );
			if ( 1 !== preg_match( '/^[a-z]{2,3}(?:-[a-z0-9]{2,12})?$/', $code ) || isset( $seen[ $code ] ) ) {
				continue;
			}
			$prefix = strtolower( $this->line( $row['prefix'] ?? '', 20 ) );
			if ( 1 !== preg_match( '/^[a-z0-9-]{0,20}$/', $prefix ) || in_array( $prefix, $blocked, true ) ) {
				continue;
			}
			$name = $this->line( $row['name'] ?? '', 80 );
			if ( '' === $name ) {
				$name = strtoupper( $code );
			}
			$seen[ $code ] = true;
			$rows[]        = array(
				'code'    => $code,
				'prefix'  => $prefix,
				'name'    => $name,
				'default' => $this->flag( $row['default'] ?? false ),
			);
		}

		if ( array() === $rows ) {
			$rows = self::defaults()['prefixes'];
		}

		$has_default = false;
		foreach ( $rows as $row ) {
			if ( $row['default'] ) {
				$has_default = true;
				break;
			}
		}
		if ( ! $has_default ) {
			$rows[0]['default'] = true;
		} else {
			$marked = false;
			foreach ( $rows as $index => $row ) {
				if ( $row['default'] && ! $marked ) {
					$marked = true;
					continue;
				}
				$rows[ $index ]['default'] = false;
			}
		}

		return $rows;
	}

	/**
	 * @param mixed             $input   Raw sections.
	 * @param list<string>|null $allowed Allowed post types.
	 * @return list<array<string, mixed>>
	 */
	private function sections( mixed $input, ?array $allowed ): array {
		if ( ! is_array( $input ) ) {
			return array();
		}
		$post_types = $this->allowed_post_types( $allowed );
		$clean      = array();
		$seen       = array();
		foreach ( array_values( $input ) as $index => $row ) {
			if ( ! is_array( $row ) || count( $clean ) >= 40 ) {
				continue;
			}
			$heading = $this->line( $row['heading'] ?? '', 180 );
			if ( '' === $heading ) {
				continue;
			}
			$type = $this->enum( $row['type'] ?? '', array( 'static', 'auto' ), 'static' );
			$id   = $this->line( $row['id'] ?? '', 40 );
			if ( 1 !== preg_match( '/^[a-zA-Z0-9_-]{1,40}$/', $id ) || isset( $seen[ $id ] ) ) {
				$id = 'sec_' . substr( md5( $heading . '|' . $index ), 0, 8 );
			}
			$seen[ $id ] = true;
			$language    = strtolower( $this->line( $row['language'] ?? '', 20 ) );
			if ( '' !== $language && 1 !== preg_match( '/^[a-z]{2,3}(?:-[a-z0-9]{2,12})?$/', $language ) ) {
				$language = '';
			}
			$post_type = $this->key( $row['post_type'] ?? 'post' );
			if ( ! in_array( $post_type, $post_types, true ) ) {
				$post_type = in_array( 'post', $post_types, true ) ? 'post' : $post_types[0];
			}
			$orderby = $this->enum( $row['orderby'] ?? '', array( 'date', 'modified', 'title', 'menu_order' ), 'date' );
			$clean[] = array(
				'id'           => $id,
				'type'         => $type,
				'heading'      => $heading,
				'language'     => $language,
				'markdown'     => $this->markdown( $row['markdown'] ?? '', 100000 ),
				'post_type'    => $post_type,
				'taxonomy'     => $this->key( $row['taxonomy'] ?? '' ),
				'terms'        => $this->terms( $row['terms'] ?? '' ),
				'limit'        => $this->int_range( $row['limit'] ?? null, 1, 200, 20 ),
				'orderby'      => $orderby,
				'order'        => $this->enum( strtoupper( (string) ( $row['order'] ?? 'DESC' ) ), array( 'ASC', 'DESC' ), 'DESC' ),
				'descriptions' => $this->flag( $row['descriptions'] ?? false ),
			);
		}
		return $clean;
	}

	/**
	 * @param list<string>|null $allowed Explicit allow-list.
	 * @return list<string>
	 */
	private function allowed_post_types( ?array $allowed ): array {
		if ( is_array( $allowed ) && array() !== $allowed ) {
			return array_values( $allowed );
		}
		if ( function_exists( 'get_post_types' ) ) {
			$types = get_post_types( array( 'public' => true ), 'names' );
			unset( $types['attachment'] );
			$types = array_values( $types );
			if ( array() !== $types ) {
				return $types;
			}
		}
		return array( 'post', 'page' );
	}

	/**
	 * @param mixed $value Raw key.
	 */
	private function key( mixed $value ): string {
		$value = is_string( $value ) ? strtolower( $value ) : '';
		if ( function_exists( 'sanitize_key' ) ) {
			return sanitize_key( $value );
		}
		return (string) preg_replace( '/[^a-z0-9_-]/', '', $value );
	}

	/**
	 * @param mixed $value Comma-separated term slugs, including Unicode slugs.
	 */
	private function terms( mixed $value ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}
		$value = trim( (string) preg_replace( '/[^\p{L}\p{N}\-_,\s]/u', '', $value ) );
		if ( mb_strlen( $value ) > 500 ) {
			$value = mb_substr( $value, 0, 500 );
		}
		return $value;
	}

	/**
	 * Two-letter code derived from the site locale.
	 */
	private static function default_language_code(): string {
		$locale = function_exists( 'get_locale' ) ? (string) get_locale() : 'en_US';
		$code   = strtolower( substr( $locale, 0, 2 ) );
		return 1 === preg_match( '/^[a-z]{2}$/', $code ) ? $code : 'en';
	}

	/**
	 * English name of the site locale, when the intl extension can provide one.
	 */
	private static function default_language_name(): string {
		$locale = function_exists( 'get_locale' ) ? (string) get_locale() : 'en_US';
		if ( function_exists( 'locale_get_display_language' ) ) {
			$name = locale_get_display_language( $locale, 'en' );
			if ( is_string( $name ) && '' !== $name ) {
				return $name;
			}
		}
		return strtoupper( self::default_language_code() );
	}
}

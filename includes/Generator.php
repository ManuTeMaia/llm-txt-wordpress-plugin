<?php
/**
 * Renders documents, fills the cache, and writes the web root when asked.
 *
 * @package LlmsTxt
 */

namespace LlmsTxt;

use LlmsTxt\Cache\Integrations;
use LlmsTxt\Cache\Store;
use LlmsTxt\Content\Assembler;
use LlmsTxt\Disk\Option_Signature_Store;
use LlmsTxt\Disk\Root_Files;
use LlmsTxt\Document\Full_Renderer;
use LlmsTxt\Document\Index_Renderer;
use LlmsTxt\Language\Catalog;

/**
 * The single place that turns settings into the two public files.
 */
final class Generator {
	private static ?self $instance = null;

	private bool $running = false;

	public function __construct(
		private Settings $settings,
		private Assembler $assembler,
		private Index_Renderer $index_renderer,
		private Full_Renderer $full_renderer,
		private Store $store,
		private Root_Files $files,
		private Catalog $languages
	) {
	}

	/**
	 * Process-wide generator so publish hooks cannot recurse through a second instance.
	 */
	public static function make(): self {
		if ( null === self::$instance ) {
			$settings        = new Settings();
			self::$instance = new self(
				$settings,
				Assembler::make( $settings ),
				new Index_Renderer(),
				new Full_Renderer(),
				new Store(),
				new Root_Files( new Option_Signature_Store() ),
				new Catalog( $settings )
			);
		}
		return self::$instance;
	}

	/**
	 * @param string|null $language Route language, or null for the root file.
	 */
	public function render_index( ?string $language ): string {
		$markdown = $this->index_renderer->render( $this->assembler->index_model( $language ) );
		$filtered = apply_filters( 'llms_txt_index_markdown', $markdown, $language );
		return is_string( $filtered ) ? $filtered : $markdown;
	}

	/**
	 * @param string|null $language Route language, or null for the root file.
	 */
	public function render_full( ?string $language ): string {
		$settings = $this->settings->get();
		$markdown = $this->full_renderer->render(
			$this->assembler->full_model( $language ),
			(int) $settings['full_byte_cap']
		);
		$filtered = apply_filters( 'llms_txt_full_markdown', $markdown, $language );
		return is_string( $filtered ) ? $filtered : $markdown;
	}

	/**
	 * Rebuild every public variant, refresh the cache, and honor the delivery mode.
	 *
	 * @return array<string, mixed>
	 */
	public function regenerate_all(): array {
		if ( $this->running || ! $this->acquire_lock() ) {
			return array(
				'skipped' => true,
				'error'   => '',
			);
		}
		$this->running = true;
		Budget::start( 20 );

		try {
			$settings = $this->settings->get();
			$catalog  = $this->languages->detect();
			$variants = array( null );
			if ( 'paths' === $settings['language_mode'] ) {
				foreach ( $catalog->languages as $language ) {
					$variants[] = $language->code;
				}
			}

			$this->store->flush();
			$stored     = array();
			$root_index = '';
			$root_full  = '';
			foreach ( $variants as $language ) {
				$index = $this->render_index( $language );
				$full  = $settings['full_enabled'] ? $this->render_full( $language ) : '';
				if ( Budget::exceeded() && '' !== $full && ! str_contains( $full, 'generation reached the time limit' ) ) {
					$full = rtrim( $full ) . "\n\n---\n\n> Truncated: generation reached the time limit.\n";
				}
				$suffix = $language ?? 'root';
				$this->store->set( 'index_' . $suffix, $index );
				if ( $settings['full_enabled'] ) {
					$this->store->set( 'full_' . $suffix, $full );
				}
				$stored[] = array(
					'language'    => $suffix,
					'index_bytes' => strlen( $index ),
					'full_bytes'  => strlen( $full ),
				);
				if ( null === $language ) {
					$root_index = $index;
					$root_full  = $full;
				}
			}

			if ( 'disk' === $settings['delivery'] ) {
				$result = $this->files->write(
					$root_index,
					$settings['full_enabled'] ? $root_full : null,
					(bool) $settings['disk_overwrite_ack']
				);
				$disk = array(
					'ok'      => $result->ok,
					'message' => $result->message,
				);
			} else {
				$this->files->delete_managed();
				$disk = array(
					'ok'      => true,
					'message' => '',
				);
			}

			$status = array(
				'generated_at' => time(),
				'provider'     => $catalog->provider,
				'variants'     => $stored,
				'disk'         => $disk,
				'error'        => $disk['ok'] ? '' : $disk['message'],
			);
			update_option( 'llms_txt_status', $status, false );
			do_action( 'llms_txt_regenerated', $status );
			( new Integrations() )->purge();
			return $status;
		} catch ( \Throwable $exception ) {
			$status = array(
				'generated_at' => time(),
				'provider'     => '',
				'variants'     => array(),
				'disk'         => array(
					'ok'      => false,
					'message' => $exception->getMessage(),
				),
				'error'        => $exception->getMessage(),
			);
			update_option( 'llms_txt_status', $status, false );
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'LLMs.txt regeneration failed: ' . $exception->getMessage() );
			}
			return $status;
		} finally {
			$this->running = false;
			Budget::reset();
			delete_option( 'llms_txt_regen_lock' );
		}
	}

	/**
	 * One rebuild at a time across PHP processes. A stale lock is stolen after two minutes.
	 */
	private function acquire_lock(): bool {
		$existing = get_option( 'llms_txt_regen_lock', null );
		if ( is_numeric( $existing ) && ( time() - (int) $existing ) < 120 ) {
			return false;
		}
		if ( null !== $existing && false !== $existing ) {
			delete_option( 'llms_txt_regen_lock' );
		}
		return add_option( 'llms_txt_regen_lock', time(), '', false );
	}
}

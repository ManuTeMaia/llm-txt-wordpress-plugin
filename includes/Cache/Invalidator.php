<?php
/**
 * Rebuilds the files when content that can affect them changes.
 *
 * @package LlmsTxt
 */

namespace LlmsTxt\Cache;

use LlmsTxt\Generator;
use LlmsTxt\Settings;

/**
 * Publish, trash, menu, and selected option hooks.
 */
final class Invalidator {
	public function register_hooks(): void {
		add_action( 'save_post', array( $this, 'on_post' ), 30, 2 );
		add_action( 'wp_trash_post', array( $this, 'on_id' ), 30 );
		add_action( 'untrash_post', array( $this, 'on_id' ), 30 );
		add_action( 'before_delete_post', array( $this, 'on_id' ), 30 );
		add_action( 'wp_update_nav_menu', array( $this, 'regenerate' ), 30 );
		add_action( 'update_option_blogname', array( $this, 'regenerate' ), 30 );
		add_action( 'update_option_blogdescription', array( $this, 'regenerate' ), 30 );
		add_action( 'update_option_permalink_structure', array( $this, 'regenerate' ), 30 );
		add_action( 'update_option_wpseo_titles', array( $this, 'regenerate' ), 30 );
		add_action( 'edited_term', array( $this, 'regenerate' ), 30 );
		add_action( 'created_term', array( $this, 'regenerate' ), 30 );
		add_action( 'delete_term', array( $this, 'regenerate' ), 30 );
		add_action( 'switch_theme', array( $this, 'regenerate' ), 30 );
	}

	/**
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 */
	public function on_post( int $post_id, \WP_Post $post ): void {
		if ( $this->skipped_request() || wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! $this->watched( $post->post_type ) ) {
			return;
		}
		$this->regenerate();
	}

	/**
	 * @param int $post_id Post ID.
	 */
	public function on_id( int $post_id ): void {
		if ( $this->skipped_request() ) {
			return;
		}
		$post = get_post( $post_id );
		if ( $post instanceof \WP_Post && ! $this->watched( $post->post_type ) ) {
			return;
		}
		$this->regenerate();
	}

	/**
	 * Rebuild now. Called from hooks that do not carry a post.
	 */
	public function regenerate(): void {
		if ( $this->skipped_request() ) {
			return;
		}
		Generator::make()->regenerate_all();
	}

	/**
	 * Imports and autosaves are not a useful time to rebuild.
	 */
	private function skipped_request(): bool {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return true;
		}
		if ( defined( 'WP_IMPORTING' ) && WP_IMPORTING ) {
			return true;
		}
		return false;
	}

	/**
	 * @param string $post_type Post type being saved.
	 */
	private function watched( string $post_type ): bool {
		if ( 'attachment' === $post_type || 'revision' === $post_type || 'nav_menu_item' === $post_type ) {
			return false;
		}
		return in_array( $post_type, ( new Settings() )->watched_post_types(), true );
	}
}

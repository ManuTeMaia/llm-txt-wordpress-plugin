<?php
/**
 * Per-post exclude checkbox.
 *
 * @package LlmsTxt
 */

namespace LlmsTxt\Admin;

/**
 * Stores _llms_txt_exclude and exposes it to the REST API.
 */
final class Meta_Box {
	public const META_KEY = '_llms_txt_exclude';

	public function register_hooks(): void {
		add_action( 'init', array( $this, 'register_meta' ) );
		add_action( 'add_meta_boxes', array( $this, 'add' ) );
		add_action( 'save_post', array( $this, 'save' ) );
	}

	/**
	 * Register the meta key for every public post type.
	 */
	public function register_meta(): void {
		$types = get_post_types( array( 'public' => true ), 'names' );
		foreach ( $types as $type ) {
			if ( 'attachment' === $type ) {
				continue;
			}
			register_post_meta(
				$type,
				self::META_KEY,
				array(
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => true,
					'auth_callback'     => static function (): bool {
						return current_user_can( 'edit_posts' );
					},
					'sanitize_callback' => static function ( $value ): string {
						return '1' === (string) $value ? '1' : '';
					},
				)
			);
		}
	}

	/**
	 * Add the side box to public post types.
	 */
	public function add(): void {
		$types = get_post_types( array( 'public' => true ), 'names' );
		foreach ( $types as $type ) {
			if ( 'attachment' === $type ) {
				continue;
			}
			add_meta_box(
				'llms_txt_exclude',
				__( 'LLMs.txt', 'llms-txt' ),
				array( $this, 'render' ),
				$type,
				'side',
				'default'
			);
		}
	}

	/**
	 * @param \WP_Post $post Current post.
	 */
	public function render( \WP_Post $post ): void {
		wp_nonce_field( 'llms_txt_exclude_' . $post->ID, 'llms_txt_exclude_nonce' );
		$checked = '1' === (string) get_post_meta( $post->ID, self::META_KEY, true );
		?>
		<label>
			<input type="checkbox" name="llms_txt_exclude" value="1" <?php checked( $checked ); ?>>
			<?php esc_html_e( 'Exclude from llms.txt and llms-full.txt', 'llms-txt' ); ?>
		</label>
		<p class="description"><?php esc_html_e( 'Password-protected, private, and Yoast noindex content is already omitted.', 'llms-txt' ); ?></p>
		<?php
	}

	/**
	 * @param int $post_id Post ID.
	 */
	public function save( int $post_id ): void {
		if ( ! isset( $_POST['llms_txt_exclude_nonce'] ) ) {
			return;
		}
		$nonce = sanitize_text_field( wp_unslash( $_POST['llms_txt_exclude_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, 'llms_txt_exclude_' . $post_id ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( isset( $_POST['llms_txt_exclude'] ) ) {
			update_post_meta( $post_id, self::META_KEY, '1' );
			return;
		}
		delete_post_meta( $post_id, self::META_KEY );
	}
}

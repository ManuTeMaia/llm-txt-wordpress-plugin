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

	public const TITLE_KEY = '_llms_txt_title';

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
					'auth_callback'     => array( self::class, 'can_edit_meta' ),
					'sanitize_callback' => static function ( $value ): string {
						return '1' === (string) $value ? '1' : '';
					},
				)
			);
			register_post_meta(
				$type,
				self::TITLE_KEY,
				array(
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => true,
					'auth_callback'     => array( self::class, 'can_edit_meta' ),
					'sanitize_callback' => static function ( $value ): string {
						$value = is_string( $value ) ? sanitize_text_field( $value ) : '';
						return \LlmsTxt\Document\Markdown_Text::plain( $value );
					},
				)
			);
		}
	}

	/**
	 * REST meta changes require edit permission on that specific post.
	 *
	 * @param mixed $allowed   Unused default.
	 * @param mixed $meta_key  Meta key.
	 * @param mixed $object_id Post ID.
	 */
	public static function can_edit_meta( mixed $allowed, mixed $meta_key, mixed $object_id ): bool {
		unset( $allowed, $meta_key );
		return is_numeric( $object_id ) && current_user_can( 'edit_post', (int) $object_id );
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
		$custom  = (string) get_post_meta( $post->ID, self::TITLE_KEY, true );
		?>
		<p>
			<label for="llms-txt-title-<?php echo esc_attr( (string) $post->ID ); ?>"><?php esc_html_e( 'Title in llms.txt', 'llms-txt' ); ?></label>
			<input id="llms-txt-title-<?php echo esc_attr( (string) $post->ID ); ?>" class="widefat" type="text" name="llms_txt_title" value="<?php echo esc_attr( $custom ); ?>" placeholder="<?php echo esc_attr( get_the_title( $post ) ); ?>">
		</p>
		<p class="description"><?php esc_html_e( 'Leave blank to use the WordPress title. Use this when the stored title is a slug such as HomeRU.', 'llms-txt' ); ?></p>
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
		} else {
			delete_post_meta( $post_id, self::META_KEY );
		}

		$title = isset( $_POST['llms_txt_title'] ) ? sanitize_text_field( wp_unslash( $_POST['llms_txt_title'] ) ) : '';
		$title = \LlmsTxt\Document\Markdown_Text::plain( $title );
		if ( '' === $title ) {
			delete_post_meta( $post_id, self::TITLE_KEY );
			return;
		}
		update_post_meta( $post_id, self::TITLE_KEY, $title );
	}
}

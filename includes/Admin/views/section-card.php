<?php
/**
 * One repeatable section card.
 *
 * @package LlmsTxt
 *
 * @var array<string, mixed> $section
 * @var int|string           $index
 * @var array<string, \WP_Post_Type> $post_types
 * @var array<string, \WP_Taxonomy>  $taxonomies
 * @var array<int, \LlmsTxt\Language\Language> $language_choices
 */

defined( 'ABSPATH' ) || exit;

$section_type = isset( $section['type'] ) ? (string) $section['type'] : 'static';
?>
<div class="llms-txt-card" data-section-type="<?php echo esc_attr( $section_type ); ?>">
	<div class="llms-txt-card-head">
		<strong><?php esc_html_e( 'Section', 'llms-txt' ); ?></strong>
		<span class="llms-txt-actions">
			<button type="button" class="button llms-txt-up"><?php esc_html_e( 'Up', 'llms-txt' ); ?></button>
			<button type="button" class="button llms-txt-down"><?php esc_html_e( 'Down', 'llms-txt' ); ?></button>
			<button type="button" class="button llms-txt-remove"><?php esc_html_e( 'Remove', 'llms-txt' ); ?></button>
		</span>
	</div>
	<input type="hidden" name="llms_txt[sections][<?php echo esc_attr( (string) $index ); ?>][id]" value="<?php echo esc_attr( (string) ( $section['id'] ?? '' ) ); ?>">
	<p>
		<label>
			<?php esc_html_e( 'Heading', 'llms-txt' ); ?>
			<input class="widefat" type="text" name="llms_txt[sections][<?php echo esc_attr( (string) $index ); ?>][heading]" value="<?php echo esc_attr( (string) ( $section['heading'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'Articles and updates', 'llms-txt' ); ?>">
		</label>
	</p>
	<div class="llms-txt-grid">
		<p>
			<label>
				<?php esc_html_e( 'Type', 'llms-txt' ); ?>
				<select class="llms-txt-type" name="llms_txt[sections][<?php echo esc_attr( (string) $index ); ?>][type]">
					<option value="static" <?php selected( $section_type, 'static' ); ?>><?php esc_html_e( 'Hand-written Markdown', 'llms-txt' ); ?></option>
					<option value="auto" <?php selected( $section_type, 'auto' ); ?>><?php esc_html_e( 'Automatic list', 'llms-txt' ); ?></option>
				</select>
			</label>
		</p>
		<p>
			<label>
				<?php esc_html_e( 'Language', 'llms-txt' ); ?>
				<select name="llms_txt[sections][<?php echo esc_attr( (string) $index ); ?>][language]">
					<option value=""><?php esc_html_e( 'Follow the file', 'llms-txt' ); ?></option>
					<?php foreach ( $language_choices as $language ) : ?>
						<option value="<?php echo esc_attr( $language->code ); ?>" <?php selected( (string) ( $section['language'] ?? '' ), $language->code ); ?>>
							<?php echo esc_html( $language->name . ' (' . $language->code . ')' ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</label>
		</p>
	</div>
	<div class="llms-txt-static">
		<p>
			<label>
				<?php esc_html_e( 'Markdown', 'llms-txt' ); ?>
				<textarea class="widefat" rows="8" name="llms_txt[sections][<?php echo esc_attr( (string) $index ); ?>][markdown]"><?php echo esc_textarea( (string) ( $section['markdown'] ?? '' ) ); ?></textarea>
			</label>
		</p>
		<p class="description"><?php esc_html_e( 'Lists and paragraphs. Do not add a heading — the section title above is the H2. Example item: - [Overview](https://example.com/): Short description', 'llms-txt' ); ?></p>
	</div>
	<div class="llms-txt-auto">
		<div class="llms-txt-grid">
			<p>
				<label>
					<?php esc_html_e( 'Post type', 'llms-txt' ); ?>
					<select name="llms_txt[sections][<?php echo esc_attr( (string) $index ); ?>][post_type]">
						<?php foreach ( $post_types as $post_type ) : ?>
							<option value="<?php echo esc_attr( $post_type->name ); ?>" <?php selected( (string) ( $section['post_type'] ?? 'post' ), $post_type->name ); ?>>
								<?php echo esc_html( $post_type->labels->singular_name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</label>
			</p>
			<p>
				<label>
					<?php esc_html_e( 'Taxonomy', 'llms-txt' ); ?>
					<select name="llms_txt[sections][<?php echo esc_attr( (string) $index ); ?>][taxonomy]">
						<option value=""><?php esc_html_e( 'Any', 'llms-txt' ); ?></option>
						<?php foreach ( $taxonomies as $taxonomy ) : ?>
							<option value="<?php echo esc_attr( $taxonomy->name ); ?>" <?php selected( (string) ( $section['taxonomy'] ?? '' ), $taxonomy->name ); ?>>
								<?php echo esc_html( $taxonomy->labels->singular_name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</label>
			</p>
			<p>
				<label>
					<?php esc_html_e( 'Term slugs', 'llms-txt' ); ?>
					<input class="widefat" type="text" name="llms_txt[sections][<?php echo esc_attr( (string) $index ); ?>][terms]" value="<?php echo esc_attr( (string) ( $section['terms'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'articles, news', 'llms-txt' ); ?>">
				</label>
			</p>
			<p>
				<label>
					<?php esc_html_e( 'Limit', 'llms-txt' ); ?>
					<input type="number" min="1" max="200" name="llms_txt[sections][<?php echo esc_attr( (string) $index ); ?>][limit]" value="<?php echo esc_attr( (string) ( $section['limit'] ?? 20 ) ); ?>">
				</label>
			</p>
			<p>
				<label>
					<?php esc_html_e( 'Order by', 'llms-txt' ); ?>
					<select name="llms_txt[sections][<?php echo esc_attr( (string) $index ); ?>][orderby]">
						<?php
						$orderby_choices = array(
							'date'       => __( 'Published date', 'llms-txt' ),
							'modified'   => __( 'Modified date', 'llms-txt' ),
							'title'      => __( 'Title', 'llms-txt' ),
							'menu_order' => __( 'Menu order', 'llms-txt' ),
						);
						foreach ( $orderby_choices as $value => $label ) :
							?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( (string) ( $section['orderby'] ?? 'date' ), $value ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
			</p>
			<p>
				<label>
					<?php esc_html_e( 'Direction', 'llms-txt' ); ?>
					<select name="llms_txt[sections][<?php echo esc_attr( (string) $index ); ?>][order]">
						<option value="DESC" <?php selected( (string) ( $section['order'] ?? 'DESC' ), 'DESC' ); ?>><?php esc_html_e( 'Descending', 'llms-txt' ); ?></option>
						<option value="ASC" <?php selected( (string) ( $section['order'] ?? '' ), 'ASC' ); ?>><?php esc_html_e( 'Ascending', 'llms-txt' ); ?></option>
					</select>
				</label>
			</p>
		</div>
		<p>
			<input type="hidden" name="llms_txt[sections][<?php echo esc_attr( (string) $index ); ?>][descriptions]" value="0">
			<label>
				<input type="checkbox" name="llms_txt[sections][<?php echo esc_attr( (string) $index ); ?>][descriptions]" value="1" <?php checked( ! empty( $section['descriptions'] ) ); ?>>
				<?php esc_html_e( 'Append a short description (Yoast meta description, otherwise the excerpt).', 'llms-txt' ); ?>
			</label>
		</p>
	</div>
</div>

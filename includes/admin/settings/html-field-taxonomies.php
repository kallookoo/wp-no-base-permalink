<?php
/**
 * Field Taxonomies Template.
 *
 * @package kallookoo\NBP
 */

defined( 'ABSPATH' ) || exit;

?>
<fieldset>
	<legend class="screen-reader-text">
		<span><?php echo esc_html( $args['title'] ); ?></span>
	</legend>
<?php if ( ! empty( $args['desc'] ) ) : ?>
	<p><?php echo wp_kses_post( $args['desc'] ); ?></p>
<?php endif; ?>
	<ul role="list">
<?php foreach ( $args['choices'] as $option => $tax_obj ) : ?>
	<li>
		<label>
	<?php
		$value = ( in_array( $option, $args['value'], true ) ? 'yes' : 'no' );
		printf(
			'<input type="checkbox" name="%1$s" value="yes"%2$s>%3$s, ',
			esc_attr( "{$args['name']}[{$option}]" ),
			checked( $value, 'yes', false ),
			esc_html( $tax_obj->labels->name )
		);
	?>
	<?php
		echo wp_kses_post(
			sprintf(
				/* translators: %1$s Post Type or Post Types, %2$s The post types used by taxonomy. */
				__( 'used by %1$s: <code>%2$s</code>.', 'wp-no-base-permalink' ),
				_n( 'Post Type', 'Post Types', count( (array) $tax_obj->object_type ), 'wp-no-base-permalink' ),
				implode( '</code>, <code>', (array) $tax_obj->object_type )
			)
		);
	?>
		</label>
	</li>
<?php endforeach; ?>
	</ul>
</fieldset>

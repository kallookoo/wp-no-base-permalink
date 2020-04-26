<?php
/**
 * Field Checkbox Template.
 *
 * @package kallookoo\NBP
 */

defined( 'ABSPATH' ) || exit;

?>
<fieldset>
	<legend class="screen-reader-text">
		<span><?php echo esc_html( $args['title'] ); ?></span>
	</legend>
	<span>
<?php
	printf(
		'<input type="checkbox" id="%1$s" name="%2$s" value="yes"%3$s>',
		esc_attr( $args['id'] ),
		esc_attr( $args['name'] ),
		checked( esc_attr( $args['value'] ), 'yes', false )
	);
	?>
<?php if ( ! empty( $args['desc'] ) ) : ?>
	<span><?php echo wp_kses_post( $args['desc'] ); ?></span>
<?php endif; ?>
	</span>
</fieldset>

<?php
/**
 * Field Text Template.
 *
 * @package kallookoo\NBP
 */

defined( 'ABSPATH' ) || exit;

$value = ( empty( $args['value'] ) ? $args['default'] : $args['value'] );
?>
<fieldset>
	<legend class="screen-reader-text">
		<span><?php echo esc_html( $args['title'] ); ?></span>
	</legend>
	<span>
<?php
	printf(
		'<input type="text" id="%1$s" class="regular-text code" name="%2$s" value="%3$s">',
		esc_attr( $args['id'] ),
		esc_attr( $args['name'] ),
		esc_attr( $value )
	);
	?>
	</span>
<?php if ( ! empty( $args['desc'] ) ) : ?>
	<?php echo wp_kses_post( $args['desc'] ); ?>
<?php endif; ?>
</fieldset>

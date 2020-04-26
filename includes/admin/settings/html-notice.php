<?php
/**
 * Notice Template.
 *
 * @package kallookoo\NBP
 */

defined( 'ABSPATH' ) || exit;

?>

<div class="notice notice-warning inline">
	<p><?php echo wp_kses_post( $args['notice'] ); ?></p>
</div>

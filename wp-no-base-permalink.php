<?php
/**
 * Plugin Name: WP No Base Permalink
 * Plugin URI: https://wordpress.org/plugins/wp-no-base-permalink/
 * Description: Remove taxonomy slug and remove terms parents in hierarchical taxonomies from your permalinks.
 * Version: 2.0
 * Author: Sergio ( kallookoo )
 * Author URI: http://dsergio.com/
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-no-base-permalink
 *
 * @package kallookoo\NBP
 */

namespace kallookoo\NBP;

defined( 'ABSPATH' ) || exit;

define( 'WP_NO_BASE_PERMALINK_FILE', __FILE__ );
define( 'WP_NO_BASE_PERMALINK_VERSION', '2.0.0' );
define( 'WP_NO_BASE_PERMALINK_ABSPATH', dirname( __FILE__ ) . '/' );

spl_autoload_register(
	function ( $class ) {
		if ( 0 === strncmp( __NAMESPACE__, $class, 13 ) ) {
			$filename = WP_NO_BASE_PERMALINK_ABSPATH . 'includes' . preg_replace(
				'|([^/]+)$|',
				'class-$1.php',
				str_replace(
					[ '\\', '_' ],
					[ '/', '-' ],
					strtolower( substr( $class, 13 ) )
				)
			);

			if ( file_exists( $filename ) ) {
				require_once $filename;
			}
		}
	}
);

require_once WP_NO_BASE_PERMALINK_ABSPATH . 'includes/trait-singleton.php';

if ( is_admin() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
	define( 'WP_NO_BASE_PERMALINK_BASENAME', plugin_basename( __FILE__ ) );

	add_action(
		'activate_' . WP_NO_BASE_PERMALINK_BASENAME,
		__NAMESPACE__ . '\Admin\Plugin::on_activation'
	);
	add_action(
		'deactivate_' . WP_NO_BASE_PERMALINK_BASENAME,
		__NAMESPACE__ . '\Admin\Plugin::on_deactivation'
	);
}

/**
 * Use this action to make sure everything is registered.
 */
add_action( 'wp_loaded', __NAMESPACE__ . ( is_admin() ? '\Admin' : '' ) . '\Plugin::instance', PHP_INT_MAX );

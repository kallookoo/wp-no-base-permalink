<?php
/**
 * Plugin Options class
 *
 * @package kallookoo\NBP
 */

namespace kallookoo\NBP;

defined( 'ABSPATH' ) || exit;

/**
 * Options class
 */
class Options {

	/**
	 * Plugin options
	 *
	 * @var array
	 */
	private static $values;

	/**
	 * Find the single option recursively
	 *
	 * @param string $name    Single option name.
	 * @param mixed  $default Default value.
	 * @param mixed  $options Array to find the single option.
	 *
	 * @return mixed
	 */
	private static function find( $name, $default, $options ) {
		if ( is_array( $options ) ) {
			if ( array_key_exists( $name, $options ) ) {
				return $options[ $name ];
			}
			foreach ( $options as $option ) {
				$value = self::find( $name, null, $option );
				if ( ! is_null( $value ) ) {
					return $value;
				}
			}
		}
		return $default;
	}

	/**
	 * Gel all options
	 *
	 * @return array
	 */
	public static function get_all() {
		if ( ! is_array( self::$values ) ) {
			self::$values = get_option( 'wp_no_base_permalink' );
		}
		return ( is_array( self::$values ) ? self::$values : [] );
	}

	/**
	 * Get single option value
	 *
	 * @param string $name    Single option name.
	 * @param mixed  $default Default option value.
	 *
	 * @return mixed
	 */
	public static function get( $name, $default = false ) {
		return self::find( $name, $default, self::get_all() );
	}

	/**
	 * Update and save the options
	 *
	 * @param array $values The option values to update.
	 */
	public static function update( $values ) {
		if ( current_user_can( 'manage_options' ) ) {
			if ( update_option( 'wp_no_base_permalink', $values, 'no' ) ) {
				delete_transient( '_wp_no_base_permalink_rewrite_rules' );
			}
			self::$values = $values;
		}
	}
}

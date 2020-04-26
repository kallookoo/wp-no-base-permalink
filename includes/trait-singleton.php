<?php
/**
 * Singleton Pattern
 *
 * @package kallookoo\NBP
 */

namespace kallookoo\NBP;

defined( 'ABSPATH' ) || exit;

/**
 * Singleton
 */
trait Singleton {

	/**
	 * Current class instance.
	 *
	 * @var object Class instance.
	 */
	private static $class_instance;

	/**
	 * Get current class instance.
	 *
	 * @return object The class instance.
	 */
	public static function instance() {
		if ( ! ( static::$class_instance instanceof static ) ) {
			static::$class_instance = new static();
		}
		return static::$class_instance;
	}

	/**
	 * Cloning is forbidden.
	 */
	public function __clone() {}

	/**
	 * Unserializing is forbidden.
	 */
	public function __wakeup() {}
}

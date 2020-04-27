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
	private static $options;

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
		if ( ! is_array( self::$options ) ) {
			self::$options = get_option( 'wp_no_base_permalink' );
		}
		return ( is_array( self::$options ) ? self::$options : [] );
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
	 * Sanitized and save settings
	 */
	public static function save() {
		if ( isset( $_POST['wp-no-base-permalink'] ) && current_user_can( 'manage_options' ) ) {
			check_admin_referer( 'update-permalink' );

			$options = map_deep( wp_unslash( $_POST['wp-no-base-permalink'] ), 'sanitize_text_field' );
			if ( $options && is_array( $options ) ) {
				$sanitized = [
					'taxonomies' => [],
					'selected'   => [],
				];

				$taxonomies = [];
				if ( array_key_exists( 'taxonomies', $options ) ) {
					$taxonomies = $options['taxonomies'];
				}

				if ( array_key_exists( 'selected', $options ) ) {
					foreach ( array_keys( $options['selected'] ) as $taxonomy ) {
						if ( taxonomy_exists( $taxonomy ) ) {
							$sanitized['taxonomies'][ $taxonomy ] = [
								'remove'    => 'yes',
								'redirects' => [],
							];

							if ( is_taxonomy_hierarchical( $taxonomy ) ) {
								$sanitized['taxonomies'][ $taxonomy ]['parents'] = 'yes';
							}

							if ( ! array_key_exists( $taxonomy, $taxonomies ) || ! is_array( $taxonomies[ $taxonomy ] ) ) {
								continue;
							}

							$tax_options = $taxonomies[ $taxonomy ];
							if ( ! array_key_exists( 'remove', $tax_options ) ) {
								$sanitized['taxonomies'][ $taxonomy ]['remove'] = 'no';
							}

							if (
								array_key_exists( 'parents', $sanitized['taxonomies'][ $taxonomy ] ) &&
								! array_key_exists( 'parents', $tax_options )
							) {
								$sanitized['taxonomies'][ $taxonomy ]['parents'] = 'no';
							}

							if (
								array_key_exists( 'redirects', $tax_options ) &&
								( ! empty( $tax_options['redirects'] ) && is_string( $tax_options['redirects'] ) )
							) {
								$redirects = array_map(
									function ( $a ) {
										return trim( trim( $a ), '/' );
									},
									preg_split( '@(,| )@', wp_strip_all_tags( $tax_options['redirects'], true ) )
								);
								$redirects = array_filter( array_unique( $redirects ) );

								if ( ! empty( $redirects ) ) {
									$tax_obj = get_taxonomy( $taxonomy );
									if ( $tax_obj instanceof \WP_Taxonomy ) {
										if ( 'category' === $taxonomy ) {
											$tax_slug = 'category';
										} elseif ( 'post_tag' === $taxonomy ) {
											$tax_slug = 'tag';
										} elseif ( function_exists( '\wc_get_permalink_structure' ) ) {
											if ( 'product_cat' === $taxonomy ) {
												$tax_slug = _x( 'product-category', 'slug', 'woocommerce' );
											} elseif ( 'product_tag' === $taxonomy ) {
												$tax_slug = _x( 'product-tag', 'slug', 'woocommerce' );
											}
										}
										$tax_slug  = ( empty( $tax_slug ) ? $tax_obj->rewrite['slug'] : $tax_slug );
										$key_found = array_search( trim( $tax_slug, '/' ), $redirects, true );
										if ( is_numeric( $key_found ) ) {
											unset( $redirects[ $key_found ] );
										}
									}
								}
							}

							if ( ! empty( $redirects ) ) {
								$sanitized['taxonomies'][ $taxonomy ]['redirects'] = $redirects;
							}
						}
					}
				}

				self::update( $sanitized );
			}
		}
	}
	/**
	 * Update and save the options
	 *
	 * @param array $options The option values to update.
	 */
	public static function update( $options ) {
		if ( ( is_admin() || ( defined( 'WP_CLI' ) && WP_CLI ) ) && current_user_can( 'manage_options' ) ) {
			if ( update_option( 'wp_no_base_permalink', $options, 'no' ) ) {
				delete_transient( 'wp_no_base_permalink_rewrite_rules' );
			}
			self::$options = $options;
		}
	}
}

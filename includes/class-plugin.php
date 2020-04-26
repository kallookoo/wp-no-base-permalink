<?php
/**
 * Frontend Plugin class
 *
 * @package kallookoo\NBP
 */

namespace kallookoo\NBP;

use \kallookoo\NBP\Rewrite;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin class
 */
class Plugin {

	use \kallookoo\NBP\Singleton;

	/**
	 * Taxonomies options
	 *
	 * @var array
	 */
	private $options;

	/**
	 * Construct
	 */
	private function __construct() {
		$this->options = Rewrite::instance()->get_options();
		if ( $this->options ) {
			add_filter( 'query_vars', [ $this, 'query_vars' ], PHP_INT_MAX );
			add_filter( 'request', [ $this, 'request' ], PHP_INT_MAX );
			add_filter( 'term_link', [ $this, 'term_link' ], PHP_INT_MAX, 2 );
		}
	}

	/**
	 * Filter query_vars
	 *
	 * @param array $query_vars The array of whitelisted query variable names.
	 *
	 * @return array
	 */
	public function query_vars( $query_vars ) {
		return array_merge( $query_vars, [ 'wp-no-base-permalink-redirect', 'wp-no-base-permalink-taxonomy' ] );
	}

	/**
	 * Filter request
	 *
	 * @param array $query_vars The array of requested query variables.
	 *
	 * @return array
	 */
	public function request( $query_vars ) {
		if ( isset( $query_vars['wp-no-base-permalink-redirect'], $query_vars['wp-no-base-permalink-taxonomy'] ) ) {
			$redirect = sanitize_text_field( $query_vars['wp-no-base-permalink-redirect'] );
			$taxonomy = sanitize_text_field( $query_vars['wp-no-base-permalink-taxonomy'] );
			if ( $redirect && $taxonomy && taxonomy_exists( $taxonomy ) ) {
				/**
				 * {@internal Extract the term slug.}}
				 */
				$term_slug = preg_replace( '@(?:.+/)?([^/]+)/?$@', '$1', $redirect );
				/**
				 * {@internal Get the term link, use this function for ensure the correct format of the term link.}}
				 */
				$term_link = get_term_link( $term_slug, $taxonomy );
				if ( $term_link && ! is_wp_error( $term_link ) ) {
					/**
					 * Filters the wp_safe_redirect status code.
					 *
					 * @var int
					 */
					$code = apply_filters( 'wp_no_base_permalink_redirect_code', 301 );
					/**
					 * Filters the wp_safe_redirect status code by taxonomy.
					 *
					 * @var int
					 */
					$code = apply_filters( "wp_no_base_permalink_{$taxonomy}_redirect_code", $code );

					$code = ( is_numeric( $code ) ? absint( $code ) : 301 );
					$code = ( ( 301 <= $code || 308 >= $code ) ? $code : 301 );

					wp_safe_redirect( $term_link, $code );
					exit;
				}
			}
		}
		return $query_vars;
	}

	/**
	 * Filter term_link
	 *
	 * @param string   $termlink Term link URL.
	 * @param \WP_Term $term     Term object.
	 * @param string   $taxonomy Taxonomy slug.
	 *
	 * @return string Term link URL.
	 */
	public function term_link( $termlink, $term, $taxonomy = '' ) {
		if ( ! isset( $this->options[ $term->taxonomy ] ) ) {
			return $termlink;
		}

		$taxonomy = get_taxonomy( $term->taxonomy );
		if ( ! ( $taxonomy instanceof \WP_Taxonomy ) ) {
			return $termlink;
		}

		$tax_slug = trim( $taxonomy->rewrite['slug'], '/' );
		if ( 'no' === $this->options[ $taxonomy->name ]['parents'] ) {
			$termlink = preg_replace( "@(/{$tax_slug}.*?/)({$term->slug}/?)$@", "/{$tax_slug}/$2", $termlink );
		}
		if ( 'yes' === $this->options[ $taxonomy->name ]['remove'] ) {
			$termlink = str_replace( "/{$tax_slug}/", '/', $termlink );
		}
		return $termlink;
	}
}

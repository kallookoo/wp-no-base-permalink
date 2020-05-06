<?php
/**
 * Rewrite class.
 *
 * @package kallookoo\NBP
 */

namespace kallookoo\NBP;

use \kallookoo\NBP\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Rewrite
 */
class Rewrite {

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
		if ( ! empty( get_option( 'permalink_structure' ) ) ) {
			$this->options = Options::get( 'taxonomies', [] );
			if ( $this->options ) {
				$taxonomies = [];
				foreach ( $this->options as $taxonomy => $tax_options ) {
					if ( ! taxonomy_exists( $taxonomy ) || ! is_array( $tax_options ) ) {
						continue;
					}

					$tax_options = wp_parse_args(
						$tax_options,
						[
							'remove'    => 'no',
							'parents'   => 'yes',
							'redirects' => [],
						]
					);

					if ( 'yes' === $tax_options['remove'] || 'no' === $tax_options['parents'] ) {
						$taxonomies[ $taxonomy ] = $tax_options;

						add_action( "created_{$taxonomy}", [ $this, 'flush_rewrite_rules' ], PHP_INT_MAX, 2 );
						add_action( "edited_{$taxonomy}", [ $this, 'flush_rewrite_rules' ], PHP_INT_MAX, 2 );
						add_action( "delete_{$taxonomy}", [ $this, 'flush_rewrite_rules' ], PHP_INT_MAX, 4 );

						add_filter( "{$taxonomy}_rewrite_rules", [ $this, "generate_{$taxonomy}_rules" ], PHP_INT_MAX );
					} elseif ( ! empty( $tax_options['redirects'] ) ) {
						$taxonomies[ $taxonomy ] = $tax_options;

						add_filter( "{$taxonomy}_rewrite_rules", [ $this, "generate_{$taxonomy}_rules" ], PHP_INT_MAX );
					}
				}

				$this->options = $taxonomies;
			}
		}
	}

	/**
	 * Get taxonomies options
	 *
	 * @return array
	 */
	public function get_options() {
		return ( is_array( $this->options ) ? $this->options : [] );
	}

	/**
	 * Flush rewrite rules.
	 *
	 * @param mixed ...$unused Unused.
	 */
	public function flush_rewrite_rules( ...$unused ) {
		delete_transient( 'wp_no_base_permalink_rewrite_rules' );
		add_action( 'shutdown', '\flush_rewrite_rules', PHP_INT_MAX );
	}

	/**
	 * Magic to generate taxonmy rules.
	 *
	 * @param  string $name Method name.
	 * @param  array  $args Method arguments.
	 *
	 * @return mixed
	 */
	public function __call( $name, $args ) {
		$rules = reset( $args );
		if ( preg_match( '/^generate_(.*)_rules$/', $name, $matches ) ) {
			return call_user_func_array( [ $this, 'generate_rules' ], [ $rules, end( $matches ) ] );
		}
		return $rules;
	}

	/**
	 * Generate rewrite rules.
	 *
	 * @param  array  $rules    The default rewrite rules.
	 * @param  string $taxonomy The taxonomy name.
	 *
	 * @return array            The default rewrite rules or plugin generated rules.
	 */
	private function generate_rules( $rules, $taxonomy ) {
		if ( ! array_key_exists( $taxonomy, $this->get_options() ) ) {
			return $rules;
		}

		$taxonomy = get_taxonomy( $taxonomy );
		if ( ! ( $taxonomy instanceof \WP_Taxonomy ) ) {
			return $rules;
		}

		$transient = maybe_unserialize( get_transient( 'wp_no_base_permalink_rewrite_rules' ) );
		if ( is_array( $transient ) && array_key_exists( $taxonomy, $transient ) ) {
			return $transient[ $taxonomy ];
		}

		$tax_options = $this->options[ $taxonomy->name ];

		if ( 'yes' === $tax_options['remove'] || 'no' === $tax_options['parents'] ) {
			$terms_list = $this->get_terms_list( $taxonomy->name );
			if ( is_wp_error( $terms_list ) ) {
				return $rules;
			}
		}

		if ( isset( $terms_list ) && $taxonomy->hierarchical ) {
			if ( 'yes' === $tax_options['parents'] ) {
				$terms_list = array_map(
					function ( $a ) {
						if ( ! empty( $a['parents'] ) ) {
							$a['slug'] = "{$a['parents']}/{$a['slug']}";
						}
						return $a;
					},
					$terms_list
				);
			} else {
				$terms_list_redirect = array_unique( array_filter( wp_list_pluck( $terms_list, 'parents' ) ) );
			}
		}

		$tax_index = 'index.php?';
		if ( $taxonomy->query_var ) {
			$tax_index .= $taxonomy->query_var;
		} else {
			$tax_index .= "taxonomy={$taxonomy->name}&term";
		}

		$tax_slug = trim( $taxonomy->rewrite['slug'], '/' );

		/**
		 * {@internal Find and set the front (blog/, archive/, etc..) of the taxonomy.}}
		 */
		$tax_regex = '';
		foreach ( $rules as $k => $v ) {
			$p = strpos( $k, $tax_slug );
			if ( is_int( $p ) && 0 < $p ) {
				$tax_regex = '(?:' . trim( substr( $k, 0, $p ), '/' ) . '/)?';
				break;
			}
		}

		if ( 'no' === $tax_options['remove'] ) {
			$tax_regex .= "{$tax_slug}/";
		}

		if ( isset( $terms_list ) ) {
			/**
			 * Filters the value to use on array_chunk function for create multiple groups for keys or single groups.
			 *
			 * Multiple groups: The group keys are longer and make fewer rules.
			 * Single groups:   The group keys only define one term and make more rules.
			 *
			 * @param int     The value to use in array_chunk function or for disable. Default: 100
			 * @param string  The current taxonomy name.
			 */
			$terms_chunk = apply_filters( 'wp_no_base_permalink_rewrite_groups', 100, $taxonomy->name );
			$terms_chunk = ( is_numeric( $terms_chunk ) ? absint( $terms_chunk ) : 0 );
			$terms_list  = wp_list_pluck( $terms_list, 'slug' );

			if ( 1 < $terms_chunk ) {
				$terms_list = array_map(
					function( $a ) {
						return implode( '|', $a );
					},
					array_chunk( $terms_list, $terms_chunk )
				);
			}

			$terms_rules = [];
			foreach ( $terms_list as $group ) {
				$group = "{$tax_regex}({$group})";

				$terms_rules[ $group . '/?$' ]                                    = $tax_index . '=$matches[1]';
				$terms_rules[ $group . '/embed/?$' ]                              = $tax_index . '=$matches[1]&embed=true';
				$terms_rules[ $group . '/page/?([0-9]{1,})/?$' ]                  = $tax_index . '=$matches[1]&paged=$matches[2]';
				$terms_rules[ $group . '/(?:feed/)?(feed|rdf|rss|rss2|atom)/?$' ] = $tax_index . '=$matches[1]&feed=$matches[2]';
			}

			if ( isset( $terms_list_redirect ) && ! empty( $terms_rules ) ) {
				if ( 1 < $terms_chunk ) {
					$terms_list_redirect = array_map(
						function( $a ) {
							return implode( '|', $a );
						},
						array_chunk( $terms_list_redirect, $terms_chunk )
					);
				}

				foreach ( $terms_list_redirect as $group ) {
					$terms_rules[ "{$tax_regex}({$group})/(.*)/?$" ]  = 'index.php?wp-no-base-permalink-redirect=$matches[2]';
					$terms_rules[ "{$tax_regex}({$group})/(.*)/?$" ] .= '&wp-no-base-permalink-taxonomy=' . $taxonomy->name;
				}
			}

			if ( ( 'yes' === $tax_options['remove'] ) && ! empty( $terms_rules ) ) {
				$terms_rules[ $tax_regex . $tax_slug . '/(.*)/?$' ]  = 'index.php?wp-no-base-permalink-redirect=$matches[1]';
				$terms_rules[ $tax_regex . $tax_slug . '/(.*)/?$' ] .= '&wp-no-base-permalink-taxonomy=' . $taxonomy->name;
			}
		}

		if ( ! empty( $tax_options['redirects'] ) && is_array( $tax_options['redirects'] ) ) {
			$tax_regex     = ( isset( $terms_rules ) ? $tax_regex : '' );
			$tax_redirects = "{$tax_regex}(" . implode( '|', $tax_options['redirects'] ) . ')/(.*)/?$';
			$terms_rules   = ( isset( $terms_rules ) ? $terms_rules : $rules );

			if ( ! empty( $terms_rules ) ) {
				$terms_rules[ $tax_redirects ]  = 'index.php?wp-no-base-permalink-redirect=$matches[2]';
				$terms_rules[ $tax_redirects ] .= '&wp-no-base-permalink-taxonomy=' . $taxonomy->name;
			}
		}

		if ( ! empty( $terms_rules ) ) {
			/**
			 * Filters to save generated rewrite rules
			 *
			 * Note: To ensure the rewrtie rules is updated, the transient only is stored for one month.
			 *
			 * @param bool Save or not the generated rewrite rules. Default: True.
			 */
			$save_rewrite_rules = apply_filters( 'wp_no_base_permalink_save_rewrite_rules', true );
			if ( wp_validate_boolean( $save_rewrite_rules ) ) {
				if ( ! is_array( $transient ) ) {
					$transient = [];
				}

				$transient[ $taxonomy->name ] = $terms_rules;

				set_transient( 'wp_no_base_permalink_rewrite_rules', maybe_serialize( $transient ), MONTH_IN_SECONDS );
			}
			return $terms_rules;
		}
		return $rules;
	}

	/**
	 * Get terms list.
	 *
	 * @param  string $taxonomy Taxonomy name.
	 *
	 * @return array            The terms list.
	 */
	private function get_terms_list( $taxonomy ) {
		$priority_terms_clauses = false;
		$priority_get_term      = false;

		global $sitepress;
		if ( $sitepress && ( $sitepress instanceof \SitePress ) ) {
			$priority_terms_clauses = has_filter( 'terms_clauses', [ $sitepress, 'terms_clauses' ] );
			if ( $priority_terms_clauses || is_numeric( $priority_terms_clauses ) ) {
				remove_filter( 'terms_clauses', [ $sitepress, 'terms_clauses' ], $priority_terms_clauses );
			}
			$priority_get_term = has_filter( 'get_term', [ $sitepress, 'get_term_adjust_id' ] );
			if ( $priority_get_term || is_numeric( $priority_get_term ) ) {
				remove_filter( 'get_term', [ $sitepress, 'get_term_adjust_id' ], $priority_get_term );
			}
		}

		$terms = get_terms(
			[
				'hide_empty'      => false,
				'suppress_filter' => true,
				'taxonomy'        => $taxonomy,
			]
		);

		if ( $terms && ! is_wp_error( $terms ) ) {
			$terms_list = [];
			foreach ( $terms as $term ) {
				$terms_list[ $term->term_id ] = [
					'slug'    => $term->slug,
					'parents' => '',
				];
				if ( 0 < $term->parent ) {
					$parents = get_term_parents_list(
						$term->term_id,
						$taxonomy,
						[
							'link'      => false,
							'format'    => 'slug',
							'inclusive' => false,
						]
					);

					if ( is_wp_error( $parents ) ) {
						$terms_list = $parents;
						break;
					}

					$terms_list[ $term->term_id ]['parents'] = untrailingslashit( $parents );
				}
			}
		}

		if ( $priority_terms_clauses || is_numeric( $priority_terms_clauses ) ) {
			add_filter( 'terms_clauses', [ $sitepress, 'terms_clauses' ], $priority_terms_clauses, 3 );
		}

		if ( $priority_get_term || is_numeric( $priority_get_term ) ) {
			add_filter( 'get_term', [ $sitepress, 'get_term_adjust_id' ], $priority_get_term, 2 );
		}

		if ( empty( $terms_list ) ) {
			$terms_list = new \WP_Error( 'wp_no_base_permalink_terms_list', __( 'Empty Terms List', 'wp-no-base-permalink' ) );
		}
		return $terms_list;
	}
}

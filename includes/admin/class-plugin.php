<?php
/**
 * Backend Plugin class
 *
 * @package kallookoo\NBP
 */

namespace kallookoo\NBP\Admin;

use \kallookoo\NBP\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin class
 */
class Plugin {

	use \kallookoo\NBP\Singleton;

	/**
	 * Action activate_
	 *
	 * @param bool $network_wide Whether to enable the plugin for all sites
	 *                           in the network or just the current site.
	 *                           Multisite only. Default: false.
	 */
	public static function on_activation( $network_wide = false ) {
		if ( ! $network_wide ) {
			$options = Options::get_all();
			if ( $options && ! isset( $options['taxonomies'] ) ) {
				$taxonomies = [
					'category' => [
						'remove'  => ( isset( $options['disable-category-base'] ) ? 'yes' : 'no' ),
						'parents' => ( isset( $options['remove-parents-categories'] ) ? 'no' : 'yes' ),
					],
					'post_tag' => [
						'remove' => ( isset( $options['disable-tag-base'] ) ? 'yes' : 'no' ),
					],
				];

				if ( ! empty( $options['old-category-redirect'] ) && is_array( $options['old-category-redirect'] ) ) {
					$taxonomies['category']['redirects'] = $options['old-category-redirect'];
				}

				if ( ! empty( $options['old-tag-redirect'] ) && is_array( $options['old-tag-redirect'] ) ) {
					$taxonomies['post_tag']['redirects'] = $options['old-tag-redirect'];
				}

				Options::update(
					[
						'selected'   => array_keys( $taxonomies ),
						'taxonomies' => $taxonomies,
					]
				);
			}
		}
	}

	/**
	 * Action deactivate_
	 *
	 * @param bool $network_wide Whether to enable the plugin for all sites
	 *                           in the network or just the current site.
	 *                           Multisite only. Default: false.
	 */
	public static function on_deactivation( $network_wide = false ) {
		if ( ! $network_wide ) {
			delete_transient( 'wp_no_base_permalink_rewrite_rules' );
			add_action( 'shutdown', '\flush_rewrite_rules', PHP_INT_MAX );
		}
	}

	/**
	 * Construct
	 */
	private function __construct() {
		if ( ! is_network_admin() ) {
			add_action( 'admin_init', [ $this, 'admin_init' ], 20 );
			add_action( 'admin_init', '\kallookoo\NBP\Rewrite::instance', 30 );
		}
	}

	/**
	 * Action admin_init
	 */
	public function admin_init() {
		/**
		 * Save settings.
		 *
		 * Settings API & Permalink Settings Page
		 * http://core.trac.wordpress.org/ticket/9296
		 */
		add_action( 'load-options-permalink.php', '\kallookoo\NBP\Options::save', 10 );
		add_action( 'load-options-permalink.php', [ $this, 'load_options_permalink' ], 20 );
		add_action( 'load-plugins.php', [ $this, 'load_plugins' ], 10 );
	}

	/**
	 * Action load-options-permalink.php
	 */
	public function load_options_permalink() {
		/** Register settings. */
		add_settings_section(
			'wp-no-base-permalink-section',
			esc_html__( 'WP No Base Permalink Plugin Settings', 'wp-no-base-permalink' ),
			'',
			'permalink'
		);

		$taxonomies   = [];
		$get_tax_args = [
			'show_ui' => true,
			'public'  => true,
		];
		foreach ( get_taxonomies( $get_tax_args, 'objects' ) as $taxonomy => $tax_obj ) {
			$taxonomies[ $taxonomy ] = $tax_obj;
		}

		add_settings_field(
			'wp-no-base-permalink-selected',
			esc_html__( 'Select Taxonomies', 'wp-no-base-permalink' ),
			[ $this, 'render_field' ],
			'permalink',
			'wp-no-base-permalink-section',
			[
				'field_args' => [
					'title'   => __( 'Select Taxonomies', 'wp-no-base-permalink' ),
					'type'    => 'taxonomies',
					'id'      => 'wp-no-base-permalink-selected',
					'name'    => 'wp-no-base-permalink[selected]',
					'value'   => Options::get( 'selected', [] ),
					'choices' => $taxonomies,
					'notice'  => '',
					'desc'    => __(
						'Select the taxonomies to be displayed with their respective options.',
						'wp-no-base-permalink'
					),
				],
			]
		);

		$taxonomies = Options::get( 'taxonomies', Options::get( 'selected', [] ) );
		$taxonomies = ( is_array( $taxonomies ) ? $taxonomies : [] );
		foreach ( $taxonomies as $taxonomy => $tax_options ) {
			$taxonomy = get_taxonomy( $taxonomy );
			if ( ! ( $taxonomy instanceof \WP_Taxonomy ) ) {
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

			$tax_slug = trim( $taxonomy->rewrite['slug'], '/' );
			/**
			 * Filters the taxonomy slug aka base to redirects option.
			 *
			 * @param string|array $tax_base Taxonomy slugs that the plugin does not detect.
			 */
			$tax_base = apply_filters( "wp_no_base_permalink_{$taxonomy->name}_base", '', $taxonomy );
			if ( ! empty( $tax_base ) ) {
				if ( is_string( $tax_base ) ) {
					$tax_base = [ $tax_base ];
				}
				if ( is_array( $tax_base ) ) {
					$tax_base = array_map(
						function ( $a ) {
							return trim( sanitize_text_field( $a ), '/' );
						},
						array_filter( $tax_base, 'is_string' )
					);
				}
			}
			$tax_base = ( is_array( $tax_base ) ? array_filter( $tax_base ) : [] );

			if ( 'category' === $taxonomy->name ) {
				$tax_base[] = 'category';
			} elseif ( 'post_tag' === $taxonomy->name ) {
				$tax_base[] = 'tag';
			} elseif ( function_exists( '\wc_get_permalink_structure' ) ) {
				if ( 'product_cat' === $taxonomy->name ) {
					$tax_base[] = _x( 'product-category', 'slug', 'woocommerce' );
				} elseif ( 'product_tag' === $taxonomy->name ) {
					$tax_base[] = _x( 'product-tag', 'slug', 'woocommerce' );
				}
			}

			if ( $tax_base && ! in_array( $tax_slug, $tax_base, true ) ) {
				$tax_options['redirects'] = array_unique(
					array_merge( $tax_base, $tax_options['redirects'] )
				);
			}

			$settings = [
				'remove'    => [
					'title'   => sprintf(
						/* translators: %1$s taxonomy singular name, %2$s taxonomy name */
						__( 'Remove the %1$s base (%2$s)', 'wp-no-base-permalink' ),
						$taxonomy->labels->singular_name,
						$taxonomy->labels->name
					),
					'desc'    => sprintf(
						/* translators: %1$s taxonomy singular name, %2$s home url, %3$s taxonomy slug */
						__(
							'Remove the %1$s base in the permalinks. For example, the <code>%2$s/%3$s/term</code> link would look like this <code>%2$s/term</code>.',
							'wp-no-base-permalink'
						),
						$taxonomy->labels->singular_name,
						home_url(),
						$tax_slug
					),
					'type'    => 'checkbox',
					'default' => 'yes',
				],
				'parents'   => [
					'title'   => sprintf(
						/* translators: %s taxonomy name */
						__( '%s terms parents', 'wp-no-base-permalink' ),
						$taxonomy->labels->name
					),
					'desc'    => __(
						'By default all parent terms are included in the permalinks, if you <strong>uncheck</strong> it they will be removed and internal redirects will be created.',
						'wp-no-base-permalink'
					),
					'type'    => 'checkbox',
					'default' => 'yes',
				],
				'redirects' => [
					'title' => sprintf(
						/* translators: %1$s taxonomy singular name, %2$s taxonomy name */
						__( '%1$s base to redirect (%2$s)', 'wp-no-base-permalink' ),
						$taxonomy->labels->singular_name,
						$taxonomy->labels->name
					),
					'type'  => 'text',
					'value' => implode( ', ', $tax_options['redirects'] ),
					'desc'  => sprintf(
						'<p>%s</p><p class="description">%s</p>',
						sprintf(
							/* translators: %1$s taxonomy singular name, %2$s taxonomy slug */
							__(
								'You can specify other %1$s base than <code>%2$s</code>. To define more than one, use a comma or a space to separate them.',
								'wp-no-base-permalink'
							),
							$taxonomy->labels->singular_name,
							$tax_slug
						),
						sprintf(
							/* translators: %1$s taxonomy name */
							__(
								'This option always works independently of the others, to create redirects in case of changing the %1$s base, to disable it, uncheck the %1$s in the Select taxonomies option, if you do not use any other option for this taxonomy.',
								'wp-no-base-permalink'
							),
							$taxonomy->labels->name
						)
					),
				],
			];

			foreach ( $settings as $section => $options ) {
				if ( 'parents' === $section && ! $taxonomy->hierarchical ) {
					continue;
				}

				$options = wp_parse_args(
					$options,
					[
						'id'      => "wp-no-base-permalink-{$taxonomy->name}-{$section}",
						'name'    => sprintf( 'wp-no-base-permalink[taxonomies][%s][%s]', $taxonomy->name, $section ),
						'value'   => $tax_options[ $section ],
						'default' => '',
						'notice'  => '',
					]
				);

				if (
					'redirects' === $section &&
					( $tax_base && ! in_array( $tax_slug, $tax_base, true ) )
				) {
					$options['notice'] = sprintf(
						/* translators: %s taxonomy name */
						__( 'Detected that it does not use the default %s base and it is included automatically.', 'wp-no-base-permalink' ),
						$taxonomy->labels->name
					);
				}

				add_settings_field(
					$options['id'],
					esc_html( $options['title'] ),
					[ $this, 'render_field' ],
					'permalink',
					'wp-no-base-permalink-section',
					[
						'label_for'  => esc_attr( $options['id'] ),
						'field_args' => $options,
					]
				);
			}
		}
	}

	/**
	 * Render settings field
	 *
	 * @param array $args Settings field args.
	 */
	public function render_field( $args ) {
		$args = $args['field_args'];
		include __DIR__ . "/settings/html-field-{$args['type']}.php";
		if ( ! empty( $args['notice'] ) ) {
			include __DIR__ . '/settings/html-notice.php';
		}
	}

	/**
	 * Action load-plugins.php
	 */
	public function load_plugins() {
		add_filter( 'plugin_action_links_' . WP_NO_BASE_PERMALINK_BASENAME, [ $this, 'plugin_action_links' ], 10, 4 );
	}

	/**
	 * Filter plugins_action_links_
	 *
	 * @param array $actions   Plugin actions links.
	 * @param mixed ...$unused Unused.
	 *
	 * @return array
	 */
	public function plugin_action_links( $actions, ...$unused ) {
		array_unshift(
			$actions,
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'options-permalink.php' ) ),
				esc_html__( 'Settings', 'wp-no-base-permalink' )
			)
		);
		return $actions;
	}
}

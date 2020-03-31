<?php
/**
 * Plugin Name: WP No Base Permalink
 * Plugin URI: http://wordpress.org/plugins/wp-no-base-permalink/
 * Description: Removes base from your category and tag in permalinks and remove parents categories in permalinks (optional). WPML and Multisite Compatible.
 * Version: 0.2
 * Author: Sergio P.A. (23r9i0)
 * Author URI: http://dsergio.com/
 *
 * 
 * Copyright 2013  Sergio P.A. (23r9i0) ( email : 23r9i0@gmail.com )
 *
 * Inpiraded in:
 * WP No Category Base - WPML compatible	http://wordpress.org/plugins/no-category-base-wpml/
 * WP-No-Tag-Base							http://wordpress.org/plugins/wp-no-tag-base/
 * No category parents						http://wordpress.org/plugins/no-category-parents/
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License,
 * or( at your option ) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

add_action( 'plugins_loaded', array( 'WP_No_Base_Permalink', 'get_instance' ) );
register_activation_hook( __FILE__, array( 'WP_No_Base_Permalink', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WP_No_Base_Permalink', 'deactivate' ) );

class WP_No_Base_Permalink {
	
	private static $instance = null;
	
	private static $options = array();
	
	private static $doptions = array(
		'old-category-redirect' => 'category', 'disabled-tag-base' => 1,
		'old-tag-redirect' => 'tag', 'remove-parents-categories' => 1
	);
	
	private static $version = '0.2';
	
	public static function get_instance() {
		if ( ! isset( self::$instance ) )
			self::$instance = new self;

		return self::$instance;
	}
	
	public static function activate() {
		add_option( 'wpnbp-first-init', 1 );
		add_option( 'wp_no_base_permalink', self::$doptions );
		add_option( 'wp_no_base_permalink_version', self::$version );
	}
	
	public static function deactivate() {
		remove_filter( 'category_rewrite_rules', array( __CLASS__, 'category_rewrite_rules' ) );
		
		if ( isset( self::$options['disabled-tag-base'] )  )
			remove_filter( 'tag_rewrite_rules', array( __CLASS__, 'tag_rewrite_rules' ) );
			
		delete_option( 'wp_no_base_permalink' );
		delete_option( 'wp_no_base_permalink_version' );
		flush_rewrite_rules();
	}
		
	private function __construct() {
		// Global	
		add_action( 'init', array( $this, 'base_permastruct' ) );
		add_filter( 'plugin_action_links' , array( $this, 'plugin_action_links' ), 10, 2 );
		
		if ( get_option( 'wpnbp-first-init' ) ) {
			add_action( 'init', array( $this, 'flush_rewrite_rules' ), 999 );
			delete_option('wpnbp-first-init');
		}
		
		// Add Version to changes futures options of this plugin
		if ( ! get_option( 'wp_no_base_permalink_version' ) )
			add_option( 'wp_no_base_permalink_version', self::$version );
		
		// Get Current Options 
		self::$options = get_option( 'wp_no_base_permalink' );
		
		// Categories
		add_action( 'created_category', array( $this, 'flush_rewrite_rules' ) );
		add_action( 'edited_category', array( $this, 'flush_rewrite_rules' ) );
		add_action( 'delete_category', array( $this, 'flush_rewrite_rules' ) );
		
		add_filter( 'category_rewrite_rules', array( __CLASS__, 'category_rewrite_rules' ), 11 );
		
		// Remove Parents Categories 	
		if ( isset( self::$options['remove-parents-categories'] )  )
			add_filter( 'category_link', array( $this, 'remove_parents_category_link' ), 10, 2 );
		else
			remove_filter( 'category_link', array( $this, 'remove_parents_category_link' ), 10, 2 );
				
		// Tags
		if ( isset( self::$options['disabled-tag-base'] )  ) {
			add_action( 'created_post_tag', array( $this, 'flush_rewrite_rules' ) );
			add_action( 'edited_post_tag', array( $this, 'flush_rewrite_rules' ) );
			add_action( 'delete_post_tag', array( $this, 'flush_rewrite_rules' ) );
		
			add_filter( 'tag_rewrite_rules', array( __CLASS__, 'tag_rewrite_rules' ) );
		} else {
			remove_action( 'created_post_tag', array( $this, 'flush_rewrite_rules' ) );
			remove_action( 'edited_post_tag', array( $this, 'flush_rewrite_rules' ) );
			remove_action( 'delete_post_tag', array( $this, 'flush_rewrite_rules' ) );
			
			remove_filter( 'tag_rewrite_rules', array( __CLASS__, 'tag_rewrite_rules' ) );
		}

		// Redirects
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_filter( 'request', array( $this, 'request' ) );
		
		if ( current_user_can( 'manage_options' ) ) {
			// Custom Options
			add_action( 'admin_init', array( $this, 'add_settings' ) );
			add_action( 'admin_init', array( $this, 'save_settings' ) );
			
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		}
		
		// Lang
		load_plugin_textdomain( 'wpnbplang', false, dirname( plugin_basename( __FILE__ ) ) . '/include/languages/' );
		
	}

	public function plugin_action_links( $links, $file ) {
		if ( $file == plugin_basename( __FILE__ ) ) {
	   		$link_setting = '<a href="'. get_admin_url( null, 'options-permalink.php' ) .'">' . __( 'Settings', 'wpnbplang' ) . '</a>';
			array_unshift( $links, $link_setting );
		}
		
		return $links;
	}
	
	public function flush_rewrite_rules() {
		flush_rewrite_rules();
	}
	
	public function base_permastruct() {
		global $wp_rewrite;
		
		if ( get_option( 'category_base' ) == '' )
			$wp_rewrite->extra_permastructs['category']['struct'] = '%category%';
		
		if ( isset( self::$options['disabled-tag-base'] ) && get_option( 'tag_base' ) == '' )	
			$wp_rewrite->extra_permastructs['post_tag']['struct'] = '%post_tag%';
	}
	
	public static function category_rewrite_rules( $rewrite ) {
		$category_rewrite = array();
		$blog_prefix = '';
		
		if ( function_exists( 'is_multisite' ) && is_multisite() && ! is_subdomain_install() && is_main_site() )
			$blog_prefix = 'blog/';
			
		if ( '' == get_option( 'category_base' ) ) {
			// WPML is present: temporary disable terms_clauses filter to get all categories for rewrite
  			if ( class_exists( 'Sitepress' ) ) {
		    	global $sitepress;
		    	remove_filter( 'terms_clauses', array( $sitepress, 'terms_clauses' ) );
		    	$categories = get_categories( array( 'hide_empty' => false ) );
			    add_filter( 'terms_clauses', array( $sitepress, 'terms_clauses' ) );
			} else {
			    $categories = get_categories( array( 'hide_empty' => false ) );
			}
			foreach ( (array) $categories as $category ) {
				$category_nicename = $category->slug;
				if ( ! isset( self::$options['remove-parents-categories'] ) ) {
					if ( $category->parent == $category->cat_ID ) { // recursive recursion
						$category->parent = 0;
					} elseif ( $category->parent != 0 ) {
						$category_nicename = get_category_parents( $category->parent, false, '/', true ) . $category_nicename;
					}
				}
				$category_rewrite[$blog_prefix . '(' . $category_nicename . ')/(?:feed/)?(feed|rdf|rss|rss2|atom)/?$'] = 'index.php?category_name=$matches[1]&feed=$matches[2]';
				$category_rewrite[$blog_prefix . '(' . $category_nicename . ')/page/?([0-9]{1,})/?$'] = 'index.php?category_name=$matches[1]&paged=$matches[2]';
				$category_rewrite[$blog_prefix . '(' . $category_nicename . ')/?$'] = 'index.php?category_name=$matches[1]';
			}
		}
		
		// Redirect support from Old Custom Category Base
		$old_category_base = explode( ',', self::$options['old-category-redirect'] );
		
		foreach ( $old_category_base as $old )
				$category_rewrite[$blog_prefix . trim( $old ) . '/(.+)$'] = 'index.php?category_redirect=$matches[1]';
		
		return $category_rewrite;
	}
	
	public function remove_parents_category_link( $termlink, $term ) {
		$parents = get_ancestors( $term, 'category' );
		$category_base = get_option( 'category_base' ) ? get_option( 'category_base' ) . '/' : '';
		
		if ( ! empty( $parents ) ) {
			$link = get_term( $term, 'category' );
			$termlink = home_url( user_trailingslashit( $category_base . $link->slug, 'category' ) );
		}
		
		return $termlink;
	}

	public static function tag_rewrite_rules( $rewrite ) {
		$tag_rewrite = array();
		$blog_prefix = '';
		
		if ( function_exists( 'is_multisite' ) && is_multisite() && ! is_subdomain_install() && is_main_site() )
			$blog_prefix = 'blog/';
			
		if ( '' == get_option( 'tag_base' ) ) {
			// WPML is present: temporary disable terms_clauses filter to get all tags for rewrite
  			if ( class_exists( 'Sitepress' ) ) {
		    	global $sitepress;
		    	remove_filter( 'terms_clauses', array( $sitepress, 'terms_clauses' ) );
		    	$tags = get_tags( array( 'hide_empty' => false ) );
			    add_filter( 'terms_clauses', array( $sitepress, 'terms_clauses' ) );
			} else {
			    $tags = get_tags( array( 'hide_empty' => false ) );
			}
			foreach ( (array) $tags as $tag ) {
	        	$tag_rewrite[$blog_prefix . '(' . $tag->slug . ')/(?:feed/)?(feed|rdf|rss|rss2|atom)/?$'] = 'index.php?tag=$matches[1]&feed=$matches[2]';
				$tag_rewrite[$blog_prefix . '(' . $tag->slug . ')/page/?([0-9]{1,})/?$'] = 'index.php?tag=$matches[1]&paged=$matches[2]';
				$tag_rewrite[$blog_prefix . '(' . $tag->slug . ')/?$'] = 'index.php?tag=$matches[1]';
			}
		}
		
		// Redirect support from Old Tag Base
		$old_tag_base = explode( ',', self::$options['old-tag-redirect'] );
		
		foreach ( $old_tag_base as $old )
			$tag_rewrite[$blog_prefix . trim( $old ) . '/(.+)$'] = 'index.php?tag_redirect=$matches[1]';
		
		return $tag_rewrite;
	}
	
	public function query_vars( $query_vars ) {
		$query_vars[] = 'category_redirect';
		
		if ( isset( self::$options['disabled-tag-base'] ) )
			$query_vars[] = 'tag_redirect';
			
		return $query_vars;
	}
	
	public function request( $query_vars ) {
		if ( isset( $query_vars['category_redirect'] ) ) {
			$catlink = home_url( user_trailingslashit( $query_vars['category_redirect'], 'category' ) );
			wp_redirect( $catlink, 301 );
			exit;
		}

		if ( isset( $query_vars['tag_redirect'] ) ) {
			$taglink = home_url( user_trailingslashit( $query_vars['tag_redirect'] ) );
			wp_redirect( $taglink, 301 );
			exit;
		}
			
		return $query_vars;
	}
	
	public function add_settings() {
		add_settings_section( 
			'no_base_setting_section',
			__( 'Options for plugin WP No Base Permalink','wpnbplang' ),
			array( $this, 'intro_section' ),
			'permalink'
		);
		add_settings_field(
			'old-category-redirect',
			__( 'Oldest Categories Base','wpnbplang' ),
			array( $this, 'old_category_redirect' ),
			'permalink',
			'no_base_setting_section',
			array( 'label_for' => 'old-category-redirect' )
		);
		add_settings_section( 
			'no_base_setting_section_optional',
			__( 'Optional' ),
			'__return_false',
			'permalink'
		);
		add_settings_field(
			'remove-parents-categories',
			__( 'Remove Parents Categories', 'wpnbplang' ),
			array( $this, 'remove_parents_categories' ),
			'permalink',
			'no_base_setting_section_optional',
			array( 'label_for' => 'remove-parents-categories' )
		);
		add_settings_field(
			'disabled-tag-base',
			__( 'Disabled Tag Base','wpnbplang' ),
			array( $this, 'disabled_tag_base' ),
			'permalink',
			'no_base_setting_section_optional',
			array( 'label_for' => 'disabled-tag-base' )
		);
		add_settings_field(
			'old-tag-redirect',
			__( 'Oldest Tags Base','wpnbplang' ),
			array( $this, 'old_tags_redirect' ),
			'permalink',
			'no_base_setting_section_optional',
			array( 'label_for' => 'old-tag-redirect' )
		);
		
		/**
		 * Bug 9296
		 * Settings API & Permalink Settings Page
		 * http://core.trac.wordpress.org/ticket/9296
		 *
		 * @see function save_settings()
		 */
		//register_setting( 'permalink', 'wp_no_base_permalink' );
	}
	
	public function intro_section() {
		echo '<p>' . __( 'The Oldest Categories Base and Oldest Tags Base option are to customize the redirect old permalinks either with the default <code>\'category\'</code>, <code>\'tag\'</code> or some customized. The Remove Parents Categories option is to remove parents categories of the permalinks leaving a cleanest permalink, in my modest opinion.', 'wpnbplang' ) . '</p>';
	}
	
	public function old_category_redirect() {
		echo '<input name="wp-no-base-permalink[old-category-redirect]" id="old-category-redirect" type="text" value="' . esc_attr( self::$options['old-category-redirect'] ) . '" class="regular-text code" />';
		echo '<p class="description">' . __( 'Redirect in oldest categories base, by default <code>\'category\'</code>. For more oldest categories base separated by <code>, </code>.', 'wpnbplang' ) . '</p>';
	}
	
	public function remove_parents_categories() {
		echo '<input name="wp-no-base-permalink[remove-parents-categories]" id="remove-parents-categories" type="checkbox" value="1"' . checked( '1', self::$options['remove-parents-categories'], false ) . ' />';
		echo '<span> ' . __( 'Activated by default.', 'wpnbplang' ) . '</span>';
	}

	public function disabled_tag_base() {
		echo '<input name="wp-no-base-permalink[disabled-tag-base]" id="disabled-tag-base" type="checkbox" value="1"' . checked( '1', self::$options['disabled-tag-base'], false ) . ' />';
		echo '<span> ' . __( 'Activated by default.', 'wpnbplang' ) . '</span>';
	}

	public function old_tags_redirect() {
		echo '<input name="wp-no-base-permalink[old-tag-redirect]" id="old-tag-redirect" type="text" value="' . esc_attr( self::$options['old-tag-redirect'] ) . '" class="regular-text code" />';
		echo '<p class="description">' . __( 'Redirect in old tag base, by default <code>\'tag\'</code>. For more oldest tag base separated by <code>, </code>.', 'wpnbplang' ) . '</p>';
	}
	
	public function save_settings() {
		if ( ! isset( $_POST['wp-no-base-permalink'] ) )
			return;
		
		check_admin_referer( 'update-permalink' );
		
		if ( empty( $_POST['wp-no-base-permalink'] ) || ! is_array( $_POST['wp-no-base-permalink'] ) )
			return;
		
		$input = $_POST['wp-no-base-permalink'];
		$output = array();
		
		foreach ( self::$doptions as $doption => $dvalue ) {
			if ( 'old' == substr( $doption, 0, 3 ) ) {
				if ( isset( $input[$doption] ) && ! empty( $input[$doption] ) )
					$output[$doption] = $input[$doption];
				else 
					$output[$doption] = $dvalue;
			} else {
				if ( isset( $input[$doption] ) && ! empty( $input[$doption] ) )
					$output[$doption] = $input[$doption];
				else 
					$output[$doption] = 0;	
			}
		}
		
		update_option( 'wp_no_base_permalink', $output );
	}
	
	public function admin_enqueue_scripts( $hook ) {
		if ( 'options-permalink.php' != $hook )
			return;
		
		$debug = ( ( defined( 'WP_DEBUG' ) && WP_DEBUG ) || ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ) ? 'dev/jquery.wpnbp.js' : 'jquery.wpnbp.min.js';
		wp_register_script( 'wpnbp-scripts', plugins_url( 'include/javascript/' . $debug, __FILE__ ), array( 'jquery' ), self::$version, true );
		wp_enqueue_script( 'wpnbp-scripts' );	
	}
}
?>
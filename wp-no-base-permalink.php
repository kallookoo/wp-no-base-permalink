<?php
/**
 * Plugin Name: WP No Base Permalink
 * Plugin URI: http://wordpress.org/plugins/wp-no-base-permalink/
 * Description: Removes base from your category and tag in permalinks and remove parents categories in permalinks (optional). WPML and Multisite Compatible.
 * Version: 0.1
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

register_activation_hook( __FILE__, array( 'WP_No_Base_Permalink', 'wpnbp_activate' ) );
register_deactivation_hook( __FILE__, array( 'WP_No_Base_Permalink', 'wpnbp_deactivate' ) );

add_action( 'plugins_loaded', array( 'WP_No_Base_Permalink', 'get_instance' ) );

final class WP_No_Base_Permalink {
	
	private static $instance = null;
	
	private static $options = array();
	
	private static $doptions = array( 
		'old-category-redirect' 	=> 'category',
		'disabled-tag-base'			=> 1,
		'old-tag-redirect' 			=> 'tag',
		'remove-parents-categories' => 1
	);
	
	public static function get_instance() {
		if ( ! isset( self::$instance ) )
			self::$instance = new self;

		return self::$instance;
	}
	
	public function __clone() {}
	
	public function __wakeup() {}
	
	private function __construct() {
		if ( ! current_user_can( 'manage_options' ) )
			return;

		// Global	
		add_action( 'init', array( $this, 'wpnbp_base_permastruct' ) );
		add_filter( 'plugin_action_links' , array( $this, 'wpnbp_plugin_action_links' ), 10, 2 );
		
		if ( get_option( 'wpnbp-first-init' ) ) {
			add_action( 'init', array( $this, 'wpnbp_flush_rewrite_rules' ), 999 );
			delete_option('wpnbp-first-init');
		}
		
		// Get Options 
		self::$options = get_option( 'wpnbp-options' );
		
		// Categories
		add_action( 'created_category', array( $this, 'wpnbp_flush_rewrite_rules' ) );
		add_action( 'edited_category', array( $this, 'wpnbp_flush_rewrite_rules' ) );
		add_action( 'delete_category', array( $this, 'wpnbp_flush_rewrite_rules' ) );
		
		add_filter( 'category_rewrite_rules', array( __CLASS__, 'wpnbp_category_rewrite_rules' ) );
		
		// Remove Parents Categoris 	
		if ( isset( self::$options['remove-parents-categories'] )  )
			add_filter( 'category_link', array( $this, 'wpnbp_remove_parents_category_link' ), 10, 2 );
		else
			remove_filter( 'category_link', array( $this, 'wpnbp_remove_parents_category_link' ), 10, 2 );
				
		// Tags
		if ( isset( self::$options['disabled-tag-base'] )  ) {
			add_action( 'created_post_tag', array( $this, 'wpnbp_flush_rewrite_rules' ) );
			add_action( 'edited_post_tag', array( $this, 'wpnbp_flush_rewrite_rules' ) );
			add_action( 'delete_post_tag', array( $this, 'wpnbp_flush_rewrite_rules' ) );
		
			add_filter( 'tag_rewrite_rules', array( __CLASS__, 'wpnbp_tag_rewrite_rules' ) );
		} else {
			remove_action( 'created_post_tag', array( $this, 'wpnbp_flush_rewrite_rules' ) );
			remove_action( 'edited_post_tag', array( $this, 'wpnbp_flush_rewrite_rules' ) );
			remove_action( 'delete_post_tag', array( $this, 'wpnbp_flush_rewrite_rules' ) );
			
			remove_filter( 'tag_rewrite_rules', array( __CLASS__, 'wpnbp_tag_rewrite_rules' ) );
		}

		// Redirects
		add_filter( 'query_vars', array( $this, 'wpnbp_base_query_vars' ) );
		add_filter( 'request', array( $this, 'wpnbp_base_request' ) );
		
		// Custom Options
		add_action( 'admin_init', array( $this, 'wpnbp_add_settings_permalink' ) );
		add_action( 'admin_init', array( $this, 'wpnbp_save_settings_permalink' ) );
		
		add_action( 'admin_enqueue_scripts', array( $this, 'wpnbp_add_js_settings' ) );
		
		// Lang
		load_plugin_textdomain( 'wpnbplang', false, dirname( plugin_basename( __FILE__ ) ) . '/include/languages/' );
		
	}
	
	public function wpnbp_activate() {
		add_option('wpnbp-first-init', 1);
		add_option( 'wpnbp-options', self::$doptions );
	}
	
	public function wpnbp_deactivate() {
		remove_filter( 'category_rewrite_rules', array( 'WP_No_Base_Permalink', 'wpnbp_category_rewrite_rules' ) );
		if ( isset( self::$options['disabled-tag-base'] )  )
			remove_filter( 'tag_rewrite_rules', array( 'WP_No_Base_Permalink', 'wpnbp_tag_rewrite_rules' ) );
		delete_option( 'wpnbp-options' );
		flush_rewrite_rules();
	}

	public function wpnbp_plugin_action_links( $links, $file ) {
		if ( $file == plugin_basename(__FILE__) ) {
	   		$link_setting = '<a href="'. get_admin_url(null, 'options-permalink.php') .'">' . __( 'Settings', 'wpnbplang' ) . '</a>';
			array_unshift( $links, $link_setting );
		}
		
		return $links;
	}
	
	public function wpnbp_flush_rewrite_rules() {
		flush_rewrite_rules();
	}
	
	public function wpnbp_base_permastruct() {
		global $wp_rewrite;
		if ( get_option( 'category_base' ) == '' )
			$wp_rewrite->extra_permastructs['category']['struct'] = '%category%';
		
		if ( isset( self::$options['disabled-tag-base'] ) && get_option( 'tag_base' ) == '' )	
			$wp_rewrite->extra_permastructs['post_tag']['struct'] = '%post_tag%';
	}
	
	public static function wpnbp_category_rewrite_rules( $category_rewrite ) {
		$blog_prefix = '';
		if ( function_exists( 'is_multisite' ) && is_multisite() && !is_subdomain_install() && is_main_site() )
			$blog_prefix = 'blog/';
			
		if ( get_option( 'category_base' ) == '' ) {
			$category_rewrite = array();
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
					if ( $category->parent == $category->cat_ID) // recursive recursion
						$category->parent = 0;
					elseif ( $category->parent != 0 )
						$category_nicename = get_category_parents($category->parent, false, '/', true) . $category_nicename;
				}
				$category_rewrite[$blog_prefix . '(' . $category_nicename . ')/(?:feed/)?(feed|rdf|rss|rss2|atom)/?$'] = 'index.php?category_name=$matches[1]&feed=$matches[2]';
				$category_rewrite[$blog_prefix . '(' . $category_nicename . ')/page/?([0-9]{1,})/?$'] = 'index.php?category_name=$matches[1]&paged=$matches[2]';
				$category_rewrite[$blog_prefix . '(' . $category_nicename . ')/?$'] = 'index.php?category_name=$matches[1]';
			}
		}
		
		// Redirect support from Old Custom Category Base
		$old_category_base = explode( ',', self::$options['old-category-redirect'] );
		foreach ( $old_category_base as $old ) {
			if ( $old != 'category' )
				$category_rewrite[$blog_prefix . $old . '/(.*)$'] = 'index.php?category_redirect=$matches[1]';
		}
		// Redirect support from Old Category Base
		$category_rewrite[$blog_prefix . 'category/(.*)$'] = 'index.php?category_redirect=$matches[1]';
		
		return $category_rewrite;
	}
	
	public function wpnbp_remove_parents_category_link( $termlink, $term ) {
		$parents = get_ancestors( $term, 'category' );
		$category_base = get_option( 'category_base' ) ? get_option( 'category_base' ) . '/' : '';
		if ( !empty( $parents ) ) :
			$link = get_term( $term, 'category' );
			$termlink = home_url( user_trailingslashit( $category_base . $link->slug, 'category' ) );
		endif;
		
		return $termlink;
	}

	public static function wpnbp_tag_rewrite_rules( $tag_rewrite ) {
		$blog_prefix = '';
		if ( function_exists( 'is_multisite' ) && is_multisite() && !is_subdomain_install() && is_main_site() )
			$blog_prefix = 'blog/';
			
		if ( get_option( 'tag_base' ) == '' ) {
			$tag_rewrite = array();
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
				$tag_nicename = $tag->slug;	
	        	$tag_rewrite[$blog_prefix . '(' . $tag_nicename . ')/(?:feed/)?(feed|rdf|rss|rss2|atom)/?$'] = 'index.php?tag=$matches[1]&feed=$matches[2]';
				$tag_rewrite[$blog_prefix . '(' . $tag_nicename . ')/page/?([0-9]{1,})/?$'] = 'index.php?tag=$matches[1]&paged=$matches[2]';
				$tag_rewrite[$blog_prefix . '(' . $tag_nicename . ')/?$'] = 'index.php?tag=$matches[1]';
			}
		}
		
		// Redirect support from Old Custom Tag Base
		$old_tag_base = explode( ',', self::$options['old-tag-redirect'] );
		$i = 0;
		foreach ( $old_tag_base as $old ) {
			if ( $old != 'tag' )
				$tag_rewrite[$blog_prefix . $old . '/(.*)$'] = 'index.php?tag_redirect=$matches[1]';
		}
		// Redirect support from Old Tag Base
		$tag_rewrite[$blog_prefix . 'tag/(.*)$'] = 'index.php?tag_redirect=$matches[1]';
		
		return $tag_rewrite;
	}
	
	public function wpnbp_base_query_vars( $public_query_vars ) {
		$public_query_vars[] = 'category_redirect';
		if ( isset( self::$options['disabled-tag-base'] ) )
			$public_query_vars[] = 'tag_redirect';
			
		return $public_query_vars;
	}
	
	public function wpnbp_base_request( $query_vars ) {
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
	
	public function wpnbp_add_settings_permalink() {
		add_settings_section( 
			'no_base_setting_section',
			__( 'Options for plugin WP No Base Permalink','wpnbplang' ),
			array( $this, 'wpnbp_intro_section' ),
			'permalink'
		);
		add_settings_field(
			'old-category-redirect',
			__( 'Old Category Base','wpnbplang' ),
			array( $this, 'wpnbp_old_category_redirect' ),
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
			'disabled-tag-base',
			__( 'Disabled Tag Base','wpnbplang' ),
			array( $this, 'wpnbp_disabled_tag_base' ),
			'permalink',
			'no_base_setting_section_optional',
			array( 'label_for' => 'disabled-tag-base' )
		);

		add_settings_field(
			'old-tag-redirect',
			__( 'Old Tag Base','wpnbplang' ),
			array( $this, 'wpnbp_old_tags_redirect' ),
			'permalink',
			'no_base_setting_section_optional',
			array( 'label_for' => 'old-tag-redirect' )
		);
		
		add_settings_field(
			'remove-parents-categories',
			__( 'Remove Parents Categories', 'wpnbplang' ),
			array( $this, 'wpnbp_remove_parents_categories' ),
			'permalink',
			'no_base_setting_section_optional',
			array( 'label_for' => 'remove-parents-categories' )
		);
		
		/**
		 * Bug 9296
		 * Settings API & Permalink Settings Page
		 * http://core.trac.wordpress.org/ticket/9296
		 *
		 * @see function wpnbp_save_settings_permalink()
		 */
		//register_setting( 'permalink', 'wpnbp-options' );
	}
	
	public function wpnbp_intro_section() {
		echo '<p>' . __( 'The Old Category Base and Old Tag Base option are to customize the redirect old permalinks either with the default <code>\'category\', \'tag\'</code> or some customized. The Remove Parents Categories option is to remove parents categories of the permalinks leaving a cleanest permalink, in my modest opinion.', 'wpnbplang' ) . '</p>';
	}
	
	public function wpnbp_old_category_redirect() {
		echo '<input name="wpnbp-options[old-category-redirect]" id="old-category-redirect" type="text" value="' . esc_attr( self::$options['old-category-redirect'] ) . '" class="regular-text code" />';
		echo '<p class="description">' . __( 'Redirect in old categories base, by default category. For more categories base separated by <code>, </code>.', 'wpnbplang' ) . '</p>';
	}

	public function wpnbp_disabled_tag_base() {
		$checked = ( isset( self::$options['disabled-tag-base'] ) ) ? self::$options['disabled-tag-base'] : 0;
		echo '<input name="wpnbp-options[disabled-tag-base]" id="disabled-tag-base" type="checkbox" value="1"' . checked( '1', $checked, false ) . ' />';
		echo '<span> ' . __( 'Activated by default.', 'wpnbplang' ) . '</span>';
	}

	public function wpnbp_old_tags_redirect() {
		echo '<input name="wpnbp-options[old-tag-redirect]" id="old-tag-redirect" type="text" value="' . esc_attr( self::$options['old-tag-redirect'] ) . '" class="regular-text code" />';
		echo '<p class="description">' . __( 'Redirect in old tag base, by default tag. For more tag base separated by <code>, </code>.', 'wpnbplang' ) . '</p>';
	}

	public function wpnbp_remove_parents_categories() {
		$checked = ( isset( self::$options['remove-parents-categories'] ) ) ? self::$options['remove-parents-categories'] : 0;
		echo '<input name="wpnbp-options[remove-parents-categories]" id="remove-parents-categories" type="checkbox" value="1"' . checked( '1', $checked, false ) . ' />';
		echo '<span> ' . __( 'Activated by default.', 'wpnbplang' ) . '</span>';
	}
	
	public function wpnbp_save_settings_permalink() {
		if ( isset( $_POST['wpnbp-options'] ) ) {
			check_admin_referer('update-permalink');
			update_option( 'wpnbp-options', $_POST['wpnbp-options'] );
		}
	}
	
	public function wpnbp_add_js_settings( $hook ) {
		if ( 'options-permalink.php' != $hook )
			return;
			
		//wp_register_script( 'wpnbp-scripts', plugins_url( 'include/javascript/dev/jquery.wpnbp.js', __FILE__ ), array( 'jquery' ), '0.1', true );
		wp_register_script( 'wpnbp-scripts', plugins_url( 'include/javascript/jquery.wpnbp.min.js', __FILE__ ), array( 'jquery' ), '0.1', true );
		wp_enqueue_script( 'wpnbp-scripts' );	
	}
}
?>
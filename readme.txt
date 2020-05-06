=== WP No Base Permalink ===
Contributors: kallookoo
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=X7SFE48Y4FEQL
Tags: permalink, taxonomy, taxonomy base, taxonomy slug, slug, terms, terms parents, terms slug, category, category base, tag, tag base
Requires at least: 5.0
Tested up to: 5.4.1
Requires PHP: 5.6
Stable tag: 2.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Remove taxonomy slug and remove terms parents in hierarchical taxonomies from your permalinks.

== Description ==

Remove the taxonomy slug from your permalinks, by default after enabled the taxonomy is enabled.
Remove on hierarchical taxonomies the terms parents from your permalinks.

The options above are optional and generate their own rewrite rules.

Compatible with WPML Plugin and WordPress Multisite partially.

Read the [FAQ](https://wordpress.org/plugins/wp-no-base-permalink/faq/) before use.

== Installation ==

1. Upload the 'wp-no-base-permalink' folder to the '/wp-content/plugins/' directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to Permalinks settings and select any taxonomy to configure.
4. Configure any options from the taxonomy settings section.

== Frequently Asked Questions ==

= Won't this conflict with posts/pages? =

Simply don't have a post/page and taxonomy ( if you use this option ) with the same slug. Warning!!!, if they have the same slug will prioritize the taxonomy slug.

= The plugin has been uninstalled, but the slug for taxonomy did not reappear why? =

A particular installation does not allow the rewrite feature in disabling the plugin. Try after disabling the plugin, save permanent links again.

= Why don't you have full support for multisite? =

There is currently no secure method for regenerating rules at all sites when activated or deactivated on the network.
Activating it for the entire network will have no effect until you visit the corresponding page of each site.

= Are you a developer? =

- Filter `wp_no_base_permalink_rewrite_groups`:
Filters the value to use on array_chunk function for create multiple groups for keys or single groups.
 Multiple groups: The group keys are longer and make fewer rules.
 Single groups:   The group keys only define one term and make more rules.
Default: 100. Only numeric is allowed.

- Filter `wp_no_base_permalink_save_rewrite_rules`:
Filters to save generated rewrite rules, to ensure the rewrtie rules is updated, and if enabled, the transient only is stored for one month.
After created, edited, deleted any term for taxonomies always recreate the transient for save the rewrite rules.
Default: true. Only boolean are allowed.

- Filter `wp_no_base_permalink_redirect_status_code` and `wp_no_base_permalink_{$taxonomy}_redirect_status_code`:
Filters the wp_safe_redirect status code. Default: 301. Only 300 headers are allowed.

- Filter `wp_no_base_permalink_{$taxonomy->name}_base`:
Filters the taxonomy slug aka base to redirects option. Use if the plugin not detect the original slug.
Default: Empty String. Only support string or array.


== Changelog ==
= 2.0.0 =
* The plugin support multiple taxonomies
= 1.0 =
* Rewrite plugin for resolve bug
= 0.3.2 =
* Tested version
= 0.3.1 =
* Unknown bug
= 0.3 =
* Updated certain parts to fix issues
* Change Remove Category Base to optional. By default is enabled.
* Removes texts and scripts
* Restore support for PHP 5.3, change plugin class to static
= 0.2.3 =
* Update Tested Version
* Add Disabled plugin update on PHP 5.3, last updated Require 5.4 or later
= 0.2.2 =
* fix constant developer
= 0.2.1 =
 * Update code for personal options (developer)
= 0.2 =
 * Fix 404 in not latin letters ( tested locally)
 * Fix 404 for not admin users
= 0.1 =
 * init version

== Upgrade Notice ==
= 0.3.1 =
Bug: on activation, please force saved settings, investigating the motive.
= 0.3 =
Resolve issues
= 0.2.3 =
Add Disabled plugin update on PHP 5.3, last updated Require 5.4 or later
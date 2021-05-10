<?php
/*
Plugin Name: UCF People Directory
Description: Provides a directory for the UCF people custom post type

### Note - update version number here and in includes/shortcode.php
Version: 3.2.1-beta
###

Author: Stephen Schrauger
Plugin URI: https://github.com/schrauger/UCF-People-Directory
Github Plugin URI: schrauger/UCF-People-Directory
*/

if ( ! defined( 'WPINC' ) ) {
	die;
}

// add a rewrite rule for the directory page, to make urls more pretty

// https://www.domain.tld/directory/tag/ - shows all items for the tag (not paginated)
// https://www.domain.tld/directory/page/number - shows limited subset of all posts (doesn't filter by category)
// https://www.domain.tld/directory/tag/page/number - might not work in wordpress. would show paginated, filtered results

//include plugin_dir_path( __FILE__ ) . 'includes/common/tinymce.php';
//include plugin_dir_path( __FILE__ ) . 'includes/common/shortcode-taxonomy.php';
include_once plugin_dir_path( __FILE__ ) . 'includes/acf-pro-fields.php';
include_once plugin_dir_path( __FILE__ ) . 'includes/acf-pro-location-rules.php';
include_once plugin_dir_path( __FILE__ ) . 'includes/shortcode.php';
include_once plugin_dir_path( __FILE__ ) . 'includes/single-person-search.php';
//include plugin_dir_path( __FILE__ ) . 'includes/taxonomy.php';


class ucf_people_directory {
	//const directory_path = 'directory';
	const taxonomy_name = 'people_group'; // defined by ucf people plugin

	function __construct() {
		// plugin css/js
		add_action( 'enqueue_block_assets', array( $this, 'add_css' ) );
		add_action( 'enqueue_block_assets', array( $this, 'add_js' ) );

		// plugin activation hooks
		register_activation_hook( __FILE__, array( $this, 'activation' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivation' ) );
		register_uninstall_hook( __FILE__, array( $this, 'deactivation' ) );

		add_filter( 'admin_init', array($this, 'add_admin_hook'));

	}

	function add_css() {
		if ( file_exists( plugin_dir_path( __FILE__ ) . '/includes/plugin.css' ) ) {
			wp_enqueue_style(
				'ucf-people-directory-theme-style',
				plugin_dir_url( __FILE__ ) . '/includes/plugin.css',
				false,
				filemtime( plugin_dir_path( __FILE__ ) . '/includes/plugin.css' ),
				false
			);
		}
	}

	function add_js() {
		if ( file_exists( plugin_dir_path( __FILE__ ) . '/includes/plugin.js' ) ) {
			wp_enqueue_script(
				'ucf-people-directory-theme-script',
				plugin_dir_url( __FILE__ ) . 'includes/plugin.js',
				false,
				filemtime( plugin_dir_path( __FILE__ ) . '/includes/plugin.js' ),
				false
			);
		}
	}

	function add_admin_hook(){

		// add restriction to taxonomy terms that are marked as 'external' by hiding them from editors.
		// admins can still see them, and editors could add the term fairly easily if they tried with javascript,
		// but this is mainly a UI change so that they don't accidentally
		// or incorrectly use a taxonomy term that should not be used on anything.
		//
		// Only add it to admin pages - that is, backend pages like the page editor and taxonomy editor.
		// Frontend pages, like the directory, will not have those special terms removed
		// (they will however be modified to show an external link by a different function)
		add_filter( 'get_terms_defaults', array($this, 'hide_categories_terms'), 10, 2);
	}

	// add restriction to taxonomy terms that are marked as 'external' by hiding them from editors.
	// admins can still see them, and editors could add the term fairly easily if they tried with javascript,
	// but this is mainly a UI change so that they don't accidentally
	// or incorrectly use a taxonomy term that should not be used on anything.
	function hide_categories_terms($args, $taxonomies){

		if ( ! current_user_can( 'manage_options' ) ) {
			// make sure to match this capability with the one defined in acf-pro-fields.php

			if ( count( $taxonomies ) != 1 || ! in_array( self::taxonomy_name, $taxonomies ) ) {
				return ( $args );
			}

			$args[ 'meta_query' ] = array(
				'relation' => 'OR',
				array(
					'key'     => 'external-link',
					'value'   => '1',
					'compare' => '!=',
				),
				array(
					'key'     => 'external-link',
					'compare' => 'NOT EXISTS',
				),
			);
		}
		return ($args);
	}

	// run on plugin activation
	function activation() {
		// insert the shortcode for this plugin as a term in the taxonomy
		//ucf_people_directory_shortcode::insert_shortcode_term();
	}

	// run on plugin deactivation
	function deactivation() {
		//ucf_people_directory_shortcode::delete_shortcode_term();
	}

	// run on plugin complete uninstall
	function uninstall() {
		//ucf_people_directory_shortcode::delete_shortcode_term();
	}


}

new ucf_people_directory();

register_activation_hook( __FILE__, array( 'ucf_people_directory', 'activation' ) );
register_deactivation_hook( __FILE__, array( 'ucf_people_directory', 'deactivation' ) );
register_uninstall_hook( __FILE__, array( 'ucf_people_directory', 'deactivation' ) );


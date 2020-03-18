<?php
/*
Plugin Name: UCF People Directory
Description: Provides a directory for the UCF people custom post type
Version: 1.5.3
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

include plugin_dir_path( __FILE__ ) . 'includes/common/tinymce.php';
include plugin_dir_path( __FILE__ ) . 'includes/common/shortcode-taxonomy.php';
include plugin_dir_path( __FILE__ ) . 'includes/acf-pro-fields.php';
include plugin_dir_path( __FILE__ ) . 'includes/shortcode.php';


class ucf_people_directory {
    static $directory_path = 'directory';

    function __construct() {
        // plugin css/js
        add_action('wp_enqueue_scripts', array($this, 'add_css'));
        add_action('wp_enqueue_scripts', array($this, 'add_js'));

        // plugin activation hooks
        register_activation_hook( __FILE__, array($this,'activation'));
        register_deactivation_hook( __FILE__, array($this,'deactivation'));
        register_uninstall_hook( __FILE__, array($this,'deactivation'));
    }

    function add_css(){
	    if (file_exists(plugin_dir_path(__FILE__).'/includes/plugin.css')) {
		    wp_enqueue_style(
			    'ucf-people-directory-theme-style',
			    plugin_dir_url( __FILE__ ) . '/includes/plugin.css',
			    false,
			    filemtime( plugin_dir_path( __FILE__ ) . '/includes/plugin.css' ),
			    false
		    );
	    }
    }

    function add_js(){
	    if (file_exists(plugin_dir_path(__FILE__).'/includes/plugin.js')) {
		    wp_enqueue_script(
			    'ucf-people-directory-theme-script',
			    plugin_dir_url( __FILE__ ) . 'includes/plugin.js',
			    false,
			    filemtime( plugin_dir_path( __FILE__ ) . '/includes/plugin.js' ),
			    false
		    );
	    }
    }
/*
    // add rewrite rules
    static function custom_rewrite( $wp_rewrite){

        add_rewrite_tag(
            '%mycustomtag%',
            '([^/]+)'
        );

        /*add_rewrite_rule(
            '^' . self::$directory_path . '/mycustomtag/([^/]+)/page/([^/]+)',
            self::$directory_path . '?tag=$matches[1]&paged=$matches[2]',
            'bottom'
        );
        add_rewrite_rule(
            '^' . self::$directory_path . '/mycustomtag/([^/]+)',
            'index.php?pagename=' . self::$directory_path . '&mycustomtag=$matches[1]',
            'bottom'
        );
        /*add_rewrite_rule(
            '^' . self::$directory_path . '/page/([^/]+)',
            self::$directory_path . '?tag=$matches[1]',
            'bottom'
        );*//*


    }*/



    // run on plugin activation
    function activation(){
        // insert the shortcode for this plugin as a term in the taxonomy
        ucf_people_directory_shortcode::insert_shortcode_term();
    }

    // run on plugin deactivation
    function deactivation(){
        ucf_people_directory_shortcode::delete_shortcode_term();
    }

    // run on plugin complete uninstall
    function uninstall(){
        ucf_people_directory_shortcode::delete_shortcode_term();
    }


}

new ucf_people_directory();

register_activation_hook( __FILE__, array('ucf_people_directory','activation'));
register_deactivation_hook( __FILE__, array('ucf_people_directory','deactivation'));
register_uninstall_hook( __FILE__, array('ucf_people_directory','deactivation'));


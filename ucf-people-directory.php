<?php
/*
Plugin Name: UCF People Directory
Description: Provides a directory for the UCF people custom post type
Version: 0.3.1
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

include plugin_dir_path( __FILE__ ) . 'ucf-people-directory-shortcode.php';

class ucf_people_directory {
    static $directory_path = 'directory';

    function __construct() {
        //add_action('init', array('ucf_people_directory', 'custom_rewrite'));
        add_action('wp_enqueue_scripts', array('ucf_people_directory', 'add_css'));
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
    static function activation(){
        global $wp_rewrite;
        flush_rewrite_rules();
    }

    // run on plugin deactivation
    static function deactivation(){
        flush_rewrite_rules();
    }

    // run on plugin complete uninstall
    static function uninstall(){

    }

    static function add_css(){
        wp_enqueue_style(
            'ucf-people-directory-theme-style',
            plugin_dir_url(__FILE__) . 'style.css',
            false,
            filemtime( plugin_dir_path(__FILE__).'/style.css'),
            false
        );
    }


}

new ucf_people_directory();

register_activation_hook( __FILE__, array('ucf_people_directory','activation'));
register_deactivation_hook( __FILE__, array('ucf_people_directory','deactivation'));
register_uninstall_hook( __FILE__, array('ucf_people_directory','deactivation'));

// @TODO upon plugin activation, the permalinks must be flushed due to the custom rewrite rules
// @TODO if we let the user define the directory url, have them flush rules when they change it (or do it automatically as well)
// @TODO setting to allow custom path for directory


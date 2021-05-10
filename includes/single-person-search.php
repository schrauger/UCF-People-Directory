<?php
/**
 * Created by IntelliJ IDEA.
 * User: stephen
 * Date: 2021-05-07
 * Time: 11:34 AM
 */

// Adds a search bar above every person's profile to search the main directory.
// The main directory page must be specified within the settings page for the plugin, because
// the directory itself can be on any and multiple pages. Without this setting, there would
// be no way to know which directory page to search (you can have the same directory with different
// default filters for use on subpages)
class ucf_people_directory_single_person_search {

	var $search_bar_enabled = false;

	const acf_option_settings_page = 'options-general.php';
	const acf_option_search_enabled = 'ucf_people_directory_options_enable_search';
	const acf_option_multisite_subsite_toggle = 'ucf_people_directory_options_main_sub_switch';
	const acf_option_url = 'ucf_people_directory_options_target_page';

	const wp_action_to_target = 'single_person_before_article'; // the action that a theme has in their template file that this plugin hooks into

	/**
	 * A reference to an instance of this class.
	 */
	private static $instance;

	/**
	 * Returns an instance of this class.
	 */
	public static function get_instance() {

		if ( null == self::$instance ) {
			self::$instance = new ucf_people_directory_single_person_search();
		}

		return self::$instance;

	}

	public function __construct() {

		$this->search_bar_enabled = get_field(self::acf_option_search_enabled, 'option');
		if ($this->search_bar_enabled == true){
			add_action(self::wp_action_to_target, array($this, 'inject_search_bar'));
		}
		$this->add_admin_settings_page();
	}

	/*
	 * Adds the webpage ui to set the settings for the single person search field
	 */
	public function add_admin_settings_page(){
		if( function_exists('acf_add_options_page') ) {

			if (get_current_blog_id() === 1) {
				acf_add_options_sub_page( array(
					'page_title' 	=> 'UCF People Directory Settings',
					'menu_title'	=> 'UCF People Directory Settings',
					'menu_slug' 	=> 'ucf-people-directory-general-settings',
					'parent_slug'   => self::acf_option_settings_page,
					'capability'	=> 'edit_posts',
					'redirect'		=> false
				));
			} else {
				acf_add_options_sub_page( array(
					'page_title' 	=> 'UCF People Directory Settings',
					'menu_title'	=> 'UCF People Directory Settings',
					'menu_slug' 	=> 'ucf-people-directory-subsite-general-settings',
					'parent_slug'   => self::acf_option_settings_page,
					'capability'	=> 'edit_posts',
					'redirect'		=> false
				));
			}
		}
	}

	/**
	 * Injects the search bar html into the person pages
	 */
	public function inject_search_bar(){
		// Add directory search bar on person pages
		$obj_shortcode_attributes = new ucf_people_directory_shortcode_attributes();
		if (is_multisite()){
			if (get_current_site() === 1){
				$search_page = get_field(self::acf_option_url, 'option');
			} else {
				$main_or_subsite_toggle = get_field(self::acf_option_multisite_subsite_toggle, 'option');
				if ($main_or_subsite_toggle){
					// user wants to use a subsite directory. just pull the option as usual
					$search_page = get_field(self::acf_option_url, 'option');
				} else {
					// user wants to use the main site's directory (the default). switch and then grab the option.
					switch_to_blog(1);
					$search_page = get_field(self::acf_option_url, 'option');
					restore_current_blog();
				}
			}
		}
		$obj_shortcode_attributes->canonical_url = $search_page; //@TODO check that the page has a directory block before showing (or check before allowing that option to be saved beforehand)
		echo ucf_people_directory_shortcode::search_bar_html($obj_shortcode_attributes);
	}
}

add_action( 'plugins_loaded', array( 'ucf_people_directory_single_person_search', 'get_instance' ) );
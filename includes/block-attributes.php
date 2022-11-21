<?php
/**
 * Created by IntelliJ IDEA.
 * User: stephen
 * Date: 2021-07-26
 * Time: 3:08 PM
 */

namespace ucf_people_directory\block_attributes;

use const ucf_people_directory\block\acf_filter_term_name;
use const ucf_people_directory\block\acf_filter_term_name_main_site;
use const ucf_people_directory\block\GET_param_group;
use const ucf_people_directory\block\GET_param_keyword;
use const ucf_people_directory\block\posts_per_page;
use const ucf_people_directory\block\taxonomy_name;
use const ucf_people_directory\block\transient_cache_buster_name;

class ucf_people_directory_block_attributes {

	/** @var bool whether to show the search bar or not */
	public $show_search_bar = true;

	const SEARCH_STANDARD    = "standard";
	const SEARCH_SPECIALIZED = "specialized";

	/** @var string type of search bar to show (currently, standard and specialized). defines what fields to search on */
	public $search_bar_type;

	/** @var string user specified search string */
	public $search_content = '';

	/** @var bool whether to show the actual contact cards or not */
	public $show_contacts = false;

	/** @var bool whether the editor wants unfiltered/initial views to show the contact cards */
	public $show_contacts_on_unfiltered = false;

	/** @var bool whether to show the sidebar with group filter links or not */
	public $show_group_filter_sidebar = false;

	/** @var string the actual html that gets printed out */
	public $replacement_data = '';

	/** @var array editor specified array of people groups slugs to show in directory. if empty, show everyone (full directory) */
	public $editor_people_groups = [];

	/** @var array editor specified array of people groups ids to show in directory. if empty, show everyone (full directory) */
	public $editor_people_groups_ids = [];

	/** @var string user specified people groups slug to filter. user overrides editor. if empty, show editor people groups */
	public $people_group_slug;

	/** @var string Calculated. If user entered a category, use that. Else if editor has a single category, use that. Else, null. */
	public $weighted_category_slug;

	/** @var string Calculated. If user entered a category, use that. Else if editor has a single category, use that. Else, null. */
	public $weighted_category_id;

	/** @var integer current page number */
	public $paged = 1;

	/** @var integer number of people to show per page */
	public $posts_per_page = posts_per_page;

	/**
	 * The subsite canonical url. Useful when switching blogs for the main profiles to still print out
	 * the subsite url in the link, for pagination and group filters.
	 * @var string
	 */
	public $canonical_url;

	/** @var bool Whether to pull from the current subsite or from the main blog */
	public $switch_to_main_site = false;

	/** @var string|void collision prevention - generate random bytes to prevent multiple directory blocks on the same page from having the same #id */
	public $directory_id;

	/**
	 * @var string transient name for the card view of the current directory, based on name search, page, category, and
	 *      a cache buster that changes whenever a profile changes
	 */
	public $transient_name_cards;

	/**
	 * @var string transient name for the wp_query->max_num_pages, used for pagination
	 */
	public $transient_name_wp_query_max_pages;

	/**
	 * ucf_people_directory_shortcode_attributes constructor.
	 * Initializes all values with safe and logical values, based on user input, editor preferences, and logical
	 * deductions.
	 */
	public function __construct() {

		// before switching to blog, save the subsite canonical url in order to print out the correct urls for filtering.
		$this->canonical_url       = wp_get_canonical_url();
		$this->switch_to_main_site = get_field( 'switch_to_main_site' );
		if ( $this->switch_to_main_site ) {
			switch_to_blog( 1 );
		}

		$this->initialize_search_bar();
		$this->initialize_editor_specified_groups();
		$this->initialize_user_specified_people_groups();
		$this->initialize_weighted_category();
		$this->search_content = ( get_query_var( GET_param_keyword ) ) ? get_query_var( GET_param_keyword ) : '';

		if (
			( $this->people_group_slug != '' )
			|| ( $this->search_content != '' )
		) { // user has searched, or the user or page owner has specified a group. show the contacts.
			$this->show_contacts = true;
		}

		// this defaults to false, as that's the default value for show_contacts.
		if ( get_field( 'initially_shown' ) ) {
			$this->show_contacts               = true;
			$this->show_contacts_on_unfiltered = true;
		}

		$this->paged                     = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1; //default to page 1;
		$this->posts_per_page            = ( get_field( 'profiles_per_page' ) ? get_field( 'profiles_per_page' ) : posts_per_page );
		$this->show_group_filter_sidebar = ( get_field( 'show_group_filter_sidebar' ) || get_field( 'show_group_filter_sidebar' ) === null );

		$this->directory_id = "menu-directory-departments-" . bin2hex( random_bytes( 8 ) ); // prevent #id collisions by generating a different id for each directory block. changes on each page load, but it isn't referenced in css.
		$this->set_transient_name();
		if ( $this->switch_to_main_site ) {
			restore_current_blog();
		}
	}

	/**
	 * Defines whether to show the search bar or not, and if so, what type.
	 * Sets the options for the search bar. If advanced search options isn't enabled, default to standard.
	 */
	public function initialize_search_bar() {
		// if enabled (or if undefined due to previous versions), show the search bar
		$this->show_search_bar = ( get_field( 'show_search_bar' ) || get_field( 'show_search_bar' ) === null );

		if ( $this->show_search_bar ) {
			$advanced_options_enabled = get_field( 'advanced_search_bar_options' );
			if ( $advanced_options_enabled ) {
				if ( have_rows( 'search_bar_options' ) ) {
					while ( have_rows( 'search_bar_options' ) ) {
						the_row();
						$search_type = get_sub_field( 'search_bar_type' );

						// don't trust user input. match against known types, and set the variable to a match, or to the default for no match
						switch ( $search_type ) {
							case self::SEARCH_SPECIALIZED:
								$this->search_bar_type = self::SEARCH_SPECIALIZED;
								break;
							case self::SEARCH_STANDARD:
								$this->search_bar_type = self::SEARCH_STANDARD;
								break;
							default:
								$this->search_bar_type = self::SEARCH_STANDARD;
						}
					}

				}
			} else {
				$this->search_bar_type = self::SEARCH_STANDARD;
			}
		}
	}

	public function get_search_bar_placeholder_text() {
		$return_text = "";
		switch ( $this->search_bar_type ) {
			case self::SEARCH_SPECIALIZED:
				$return_text = "Name or specialty";
				break;
			case self::SEARCH_STANDARD:
			default:
				$return_text = "Name or keyword";
				break;
		}

		return $return_text;
	}

	/**
	 * Gets the editor specified groups from the database.
	 */
	protected function initialize_editor_specified_groups() {
		if ( $this->switch_to_main_site ) {
			$acf_filter_term_name     = acf_filter_term_name_main_site;
			$acf_filter_subfield_name = 'group_main_site';
		} else {
			$acf_filter_term_name     = acf_filter_term_name;
			$acf_filter_subfield_name = 'group';
		}
		if ( get_field( 'filtered' ) && have_rows( $acf_filter_term_name ) ) {
			while ( have_rows( $acf_filter_term_name ) ) {
				the_row();
				$group                            = get_sub_field( $acf_filter_subfield_name );
				$this->editor_people_groups[]     = $group->slug;
				$this->editor_people_groups_ids[] = $group->term_id;
			}
			reset_rows();
		}
	}

	/**
	 * Note: run *after* the editor specified groups has been initialized
	 * Sets the people_groups filter variable. It checks the user input against allowed categories, and resets it to
	 * defauls if user input is invalid.
	 */
	protected function initialize_user_specified_people_groups() {
		if ( get_query_var( GET_param_group ) ) {
			// user specified a group to filter to
			$u_people_group            = get_query_var( GET_param_group ); // possibly unsafe value. check against allowed values
			$matching_people_group_obj = get_term_by( 'slug', $u_people_group, taxonomy_name );
			if ( $matching_people_group_obj ) {
				if ( $this->editor_people_groups ) {
					// we have a user group, and the editor also defined one or more groups. we must now check that the user
					// specified group is one of the editor specified groups, or that it is a descendent of one of the editor groups.

					foreach ( $this->editor_people_groups as $editor_group_slug ) {
						if ( $editor_group_slug === $u_people_group ) {
							// user filter equals one of the root editor groups
							$this->people_group_slug = $u_people_group;
						} elseif ( term_is_ancestor_of( get_term_by( 'slug', $editor_group_slug, taxonomy_name ), $matching_people_group_obj, taxonomy_name ) ) {
							// user filter equals a descendent of one of the root editor groups
							$this->people_group_slug = $u_people_group;
						} else {
							// no match yet. do nothing.
						}
					}
					if ( ! $this->people_group_slug ) {
						// no match was found. user tried to filter to a group outside the allowed groups. default to editor groups.
						$this->people_group_slug = null;
					}

				} else {
					// editor did not specify any group. this is a main directory. therefore, allow all user-specified groups (that exist).
					$this->people_group_slug = $u_people_group;
				}
			} else {
				// term slug is not found. default to editor groups. user may have tried typing a group manually
				$this->people_group_slug = null;
			}
		} else {
			// user didn't specify a group, so show all the groups that the editor defined for this page
			$this->people_group_slug = null;
		}
	}

	/**
	 * Determine which category, if any, will be checked against for weighted profiles.
	 * If the user specified a category, use that choice.
	 * If no filter is active, but the page editor defined exactly one category to be shown,
	 * then use that category.
	 * Otherwise, weights are not taken into account.
	 */
	protected function initialize_weighted_category() {
		if ( $this->people_group_slug ) {
			$this->weighted_category_slug = $this->people_group_slug;
			$this->weighted_category_id   = get_term_by( 'slug', $this->weighted_category_slug, taxonomy_name )->term_id;

		} elseif ( sizeof( $this->editor_people_groups ) === 1 ) {
			$this->weighted_category_slug = $this->editor_people_groups[ 0 ];
			$this->weighted_category_id   = get_term_by( 'slug', $this->weighted_category_slug, taxonomy_name )->term_id;
		} else {
			$this->weighted_category_slug = null;
			$this->weighted_category_id   = null;
		}
	}

	/**
	 * Computes the transient name for this particular directory view, based on the categories, search, page, post per
	 * view, and a cache-buster unique value
	 * @return bool|string|void
	 */
	protected function set_transient_name() {
        $plugin_version = get_plugin_data(plugin_dir_path(__FILE__) . '../ucf-people-directory.php')['Version']; // current block version - manually update along with version in main php file whenever pushing a new version. used for cache busting, to prevent version incompatibilities.

        if ( ! $this->show_contacts ) {
			$this->transient_name_cards = '';

			return; // transient is only for contacts. if this current view doesn't show contacts, there's no transient.
		}

		// first, get the current cache-busting transient value. this value changes whenever a person is added or updated,
		// so that the directory is always up to date with the latest information, but is only recomputed when people change.

		$meta_transient_cache_buster_value = get_transient( transient_cache_buster_name );

		// transient name is comprised of:
		/*
		 * 1. plugin name
		 * HASH of the following:
		 * 2. EITHER
		 *       2a weight_category_slug, if set.
		 *     OR
		 *       2b editor_people_groups, array to string
		 * 3. search string
		 * 4. page
		 * 5. posts_per_page
		 * 6. Cache-busting value - changes whenever a person is edited or added
		 *
		 * "ucf-pd-"(md5){"enterprise-german-page1-20people-unique24425"}
		 */
		// because transient names are limited, everything besides the plugin name is hashed.
		$transient_name_prefix = 'ucf-pd-'; // prefix with semi-readable name, so we can at least see in the database that these transients belong to this plugin
		if ( $this->weighted_category_slug ) {
			$category = $this->weighted_category_slug;
		} else {
			$category = implode( "+", $this->editor_people_groups );
		}
		$transient_name = md5( $category . $this->search_content . $this->paged . $this->posts_per_page . $meta_transient_cache_buster_value . $plugin_version );

		$this->transient_name_cards              = substr( $transient_name_prefix . $transient_name, 0, 40 ); // transient names are limited to 45 characters, if they have an expiration. use the first 40 characters of our ucf-pd-MD5HASH1234123412341234
		$this->transient_name_wp_query_max_pages = substr( $transient_name_prefix . 'pages-' . $transient_name, 0, 40 ); // transient names are limited to 45 characters, if they have an expiration. use the first 40 characters of our ucf-pd-MD5HASH1234123412341234
	}
}

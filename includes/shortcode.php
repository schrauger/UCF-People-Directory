<?php

class ucf_people_directory_shortcode {
    const shortcode_slug        = 'ucf_people_directory'; // the shortcode text entered by the user (inside square brackets)
    const shortcode_name        = 'People Directory (deprecated - use blocks)';
    const shortcode_description = 'Searchable directory of all people';
    const posts_per_page        = '10'; // number of profiles to list per page when paginating
    const taxonomy_categories   = ''; // slug for the 'categories' taxonomy

	const taxonomy_name = 'people_group';
	const acf_filter_term_name = 'specific_terms';
    const GET_param_group = 'group_search'; // group or category person is in
    const GET_param_name  = 'name_search'; // restrict to profiles matching the user text

//    public function __construct() {
//        add_action( 'init', array( $this, 'add_shortcode' ) );
//        add_filter( 'query_vars', array( $this, 'add_query_vars_filter' ) ); // tell wordpress about new url parameters
//        add_filter( 'ucf_college_shortcode_menu_item', array( $this, 'add_ckeditor_shortcode' ) );
//
//    }

    /**
     * Adds the shortcode to wordpress' index of shortcodes
     */
    public static function add_shortcode() {
        if ( ! ( shortcode_exists( self::shortcode_slug ) ) ) {
            add_shortcode( self::shortcode_slug, array( 'ucf_people_directory_shortcode', 'replacement' ) );
        }
    }

    /**
     * Adds the shortcode to the ckeditor dropdown menu
     *
     * @var $shortcode_array array
     *
     * @return array
     */
	static function add_ckeditor_shortcode( $shortcode_array ) {
        $shortcode_array[] = array(
            'slug'        => self::shortcode_slug,
            'name'        => self::shortcode_name,
            'description' => self::shortcode_description
        );

        return $shortcode_array;
    }


    /**
     * Tells wordpress to listen for the 'people_group' parameter in the url. Used to filter down to specific profiles.
     *
     * @param $vars
     *
     * @return array
     */
	static public function add_query_vars_filter( $vars ) {
        $vars[] = self::GET_param_group;
        $vars[] = self::GET_param_name; // person name, from user submitted search text
        return $vars;
    }

    /**
     * Returns the replacement html that WordPress uses in place of the shortcode
     *
     * @param null $attrs
     *
     * @return mixed
     */
	static public function replacement( $attrs = null ) {

		$obj_shortcode_attributes = new ucf_people_directory_shortcode_attributes();

        //$replacement_data = ''; //string of html to return
        // print out search bar
		if ($obj_shortcode_attributes->show_search_bar) { // check for null for backwards compatibility
			$obj_shortcode_attributes->replacement_data .= self::search_bar_html($obj_shortcode_attributes);
		}

		$wp_query = null;
		if ($obj_shortcode_attributes->show_contacts) { // user has searched, or the user or page owner has specified a group. show the contacts.
	        $wp_query = self::query_profiles( $obj_shortcode_attributes );
	        // print out profiles
			$obj_shortcode_attributes->replacement_data .= "<div class='profiles-list'>";
			$obj_shortcode_attributes->replacement_data .= self::profiles_html( $wp_query );
			$obj_shortcode_attributes->replacement_data .= "</div>";

	        wp_reset_postdata();
        }
        // print out subcategories unless shortcode defines a specific category
		if ($obj_shortcode_attributes->show_group_filter_sidebar) {
			$obj_shortcode_attributes->replacement_data .= self::people_groups_html($obj_shortcode_attributes);
		}

        // print out pagination, if we're showing contacts
		if ($obj_shortcode_attributes->show_contacts) {
			$obj_shortcode_attributes->replacement_data .= self::pagination_html( $wp_query, $obj_shortcode_attributes );
		}


        return $obj_shortcode_attributes->replacement_data;
    }

	static function replacement_print() {
		echo self::replacement();
	}

    // ############ Search Bar Start

    /**
     * Return a string of HTML for the search input form
     *
     * @param $shortcode_attributes ucf_people_directory_shortcode_attributes
     *
     * @return string
     */
	static public function search_bar_html($shortcode_attributes) {
        $html_search_bar  = '';
        $name_search      = self::GET_param_name;
        $current_page_url = wp_get_canonical_url();
        $html_search_bar .= "
        <div class='searchbar'>
            <form id='searchform' action='{$current_page_url}' method='get'>
                <input 
                    class='searchbar' 
                    type='text' 
                    name='{$name_search}' 
                    placeholder='Search by Name' 
                    onfocus='this.placeholder = \"\" '
                    onblur='this.placeholder = \"Search by Name\"'
                    value='{$shortcode_attributes->search_by_name}'
                />
                <input 
                    class='searchsubmit'
                    type='submit'
                    alt='Search'
                    value='Search'
                    id='searchsubmit'
                />
            </form>
        </div>
        ";

        return $html_search_bar;
    }

    // ############ Search Bar End

    // ############ Profile Output Start

    /**
     * Return a string of HTML with all matching profiles. If a single category is specified, weighted profiles appear first.
     *
	 * @param $shortcode_attributes ucf_people_directory_shortcode_attributes
	 *
	 * @return WP_Query
	 */
	static public function query_profiles(  $shortcode_attributes ) {
		global $wpdb;

		$single_category = '';
		if ($shortcode_attributes->people_group_slug || sizeof($shortcode_attributes->editor_people_groups === 1)){
			// user asked for a specific category. we now need to look for weighted people.
			$single_category = $shortcode_attributes->people_group_slug;
			$weighted_people = self::profiles_weighted_id_list($single_category, $shortcode_attributes->search_by_name);
		} elseif (sizeof($shortcode_attributes->editor_people_groups === 1)) {
			// the editor is showing one single department. we now need to look for weighted people.
			$single_category = $shortcode_attributes->editor_people_groups[0];
			$weighted_people = self::profiles_weighted_id_list($single_category, $shortcode_attributes->search_by_name);
		} else {
			// user has not specified a category. weights don't come into effect, since we don't weight multi category views.
			$weighted_people = [];
		}

		$query_args     = array(
			'paged'          => $shortcode_attributes->paged,
			'posts_per_page' => $shortcode_attributes->posts_per_page,
			'post_type'      => 'person', // 'person' is a post type defined in ucf-people-cpt
			's'              => $shortcode_attributes->search_by_name,
			'orderby'        => 'meta_value',
			'meta_key'       => 'person_orderby_name',
			'order'          => 'ASC',
		);

		if (sizeof($weighted_people > 0)){
			// weighted people found. run another query to get EVERY person to create an array of ids.
			$all_people = self::profiles_id_list($single_category, $shortcode_attributes->search_by_name);
			$correctly_sorted_people = array_merge($weighted_people, $all_people);
			$correctly_sorted_people = array_unique($correctly_sorted_people);
			// now only select those profiles, and in the order specified
			$query_args['post__in'] = $correctly_sorted_people;
			$query_args['orderby'] = 'post__in';
		}

		// if any group specified, filter to those groups. otherwise, show all.
		$people_groups = ($shortcode_attributes->people_group_slug ? $shortcode_attributes->people_group_slug : $shortcode_attributes->editor_people_groups);
        if ($people_groups){
        	$query_args['tax_query'] = array(
		        array(
			        'taxonomy'         => self::taxonomy_name,
			        'field'            => 'slug',
			        'terms'            => $people_groups,
			        'include_children' => true,
			        'operator'         => 'IN'
		        )
	        );
        }

		// Now we have all profiles, with the correct weighted ones at the beginning.
		// Finally, do a WP_QUERY, passing in our exact list of profiles, which will
		// honor the sort we specify.

        return new WP_Query( $query_args );
    }

	/**
	 * Gets an ordered list of profile ids, sorted by specified weights. Used when querying a category
	 * and you want a specific set of people to be shown first. You can specify weights so that one
	 * or two people are at the top, then another group next, and finally everyone else in the
	 * category.
	 *
	 * @param string $single_category_slug
	 *
	 * @param string $name_search
	 *
	 * @return array
	 */
    static public function profiles_weighted_id_list($single_category_slug, $name_search = null) {
		// first, find all profiles that have a 'head of department' or similar tag for the currently filtered department.
	    // sort by their weight. smaller numbers first.
	    $query_args     = array(
	    	// Don't use paged. We want ALL profiles that have a weight.
		    // Then this list of ids will be given to another wp_query, which will paginate and filter as needed.
		    //'paged'          => $shortcode_attributes->paged,
		    'posts_per_page' => -1,
		    'post_type'      => 'person', // 'person' is a post type defined in ucf-people-cpt
		    's'              => $name_search,
		    'orderby'        => 'meta_value',
		    'meta_key'       => 'person_orderby_name', // we still order by person name. if weights are equal, names should be sorted.
		    'order'          => 'ASC',

		    // only query the user-specified people group.
		    // if the user hasn't specified one, this function shouldn't be called.
		    // we don't weight any profiles on the default view.
		    'tax_query' => array(
			    array(
				    'taxonomy'         => self::taxonomy_name,
				    'field'            => 'slug',
				    'terms'            => $single_category_slug,
				    'include_children' => true,
				    'operator'         => '='
			    )
		    ),

		    // only get profiles with a weight for the user-specified category.
		    // also, check that the custom_sort_order boolean is true. if the editor
		    // marks it as false, the old data is still in the database, but we shouldn't sort by it.
		    'meta_query'	=> array(
			    'relation'		=> 'AND',
			    array(
				    'key'		=> 'departments_$_department',
				    'compare'	=> '=',
				    'value'		=> get_term_by('slug', $single_category_slug, ucf_people_directory_shortcode::taxonomy_name)->term_id,
				    // acf stores taxonomy by id within the database backend, so convert the user slug to id
			    ),
			    array(
				    'key'		=> 'custom_sort_order',
				    'compare'	=> '=',
				    'value'		=> '1',
			    )
		    ),
		    'suppress_filters' => false
	    );




	    add_filter('posts_where', array('ucf_people_directory_shortcode','acf_meta_subfield_filter'));

	    $wp_query = new WP_Query($query_args);

	    remove_filter('posts_where', array('ucf_people_directory_shortcode','acf_meta_subfield_filter'));


	    // next, query for all profiles in the category, and use the previous array as the initial sortby field, but also
	    // sort by the orderby_name field after.

	    $weighted_array = [];
	    $single_category_id = get_term_by('slug', $single_category_slug, ucf_people_directory_shortcode::taxonomy_name)->term_id;
	    if ( $wp_query->have_posts() ) {
		    while ( $wp_query->have_posts() ) {
			    $wp_query->the_post();
			    // get the matching weight for our category
			    while (have_rows('departments', get_the_ID())){ // the_post apparently doesn't set the right global variables, so we explicitly tell acf the post id
			    	the_row();
			    	$department = get_sub_field('department');
			    	if ($department === $single_category_id) {
			    		// found the matching weight
					    $weighted_array[get_the_ID()] = get_sub_field('weight');
					    break;
				    }
			    }
		    }
	    }

	    // sort the unweighted array by weights
	    asort($weighted_array);
	    return array_keys($weighted_array); // keys are the post id. they should now be sorted

    }

	/**
	 * Gets an ordered list of profile ids, unsorted.
	 *
	 * @param string $single_category_slug
	 *
	 * @param string $name_search
	 *
	 * @return array
	 */
    static public function profiles_id_list($single_category_slug, $name_search = null){
	    global $wpdb;

	    $query_args     = array(
		    'posts_per_page' => -1,
		    'post_type'      => 'person', // 'person' is a post type defined in ucf-people-cpt
		    's'              => $name_search,
		    'orderby'        => 'meta_value',
		    'meta_key'       => 'person_orderby_name',
		    'order'          => 'ASC',
		    'fields'         => 'ids', // only get a list of ids

		    // only query the user-specified people group.
		    // if the user hasn't specified one, this function shouldn't be called.
		    // we don't weight any profiles on the default view.
		    'tax_query' => array(
			    array(
				    'taxonomy'         => self::taxonomy_name,
				    'field'            => 'slug',
				    'terms'            => $single_category_slug,
				    'include_children' => true,
				    'operator'         => '='
			    )
		    ),
	    );
	    $wp_query = new WP_Query($query_args);
	    return $wp_query->posts;
    }

	/**
	 * Alters the WP SQL query to allow filtering posts based on a repeater subfield.
	 * Turns 'key = parent_$_child' to 'key LIKE parent_%_child', replacing the dollar sign with percent,
	 * and the equals with LIKE.
	 * @param $where
	 *
	 * @return mixed
	 */
	static public function acf_meta_subfield_filter( $where ) {

		$where = str_replace('meta_key = \'departments_$_department', "meta_key LIKE 'departments_%_department", $where);
		//$where = str_replace('meta_key = \'departments_$_weight', "meta_key LIKE 'departments_%_weight", $where);
		return $where;
	}

    /**
     * @param $wp_query WP_Query
     *
     * @return string
     */
	static public function profiles_html( $wp_query ) {
        $html_list_profiles = "";

        if ( $wp_query->have_posts() ) {
            while ( $wp_query->have_posts() ) {
                $wp_query->the_post();
                $html_list_profiles .= self::profile();

            }
        } else {
            $html_list_profiles .= "<div class='no-results'>No results found.</div>";
        }

        return $html_list_profiles;
    }

    /**
     * Call this function after the_post is set to a profile (called within a loop)
     */
	static public function profile() {
        $html_single_profile = ''; //return data

        // #### set variables used in html output
        $person_title_prefix = get_field( 'person_title_prefix' );
        $full_name           = $person_title_prefix . ' ' . get_the_title();
        $profile_url         = get_permalink();
        $image_url           = get_the_post_thumbnail_url(null, 'medium');
        $cv_link             = get_field( 'person_cv' );
        if ( ! $image_url ) {
            $image_url = plugin_dir_url( __FILE__ ) . "default.png"; // default image location
        }
        $job_title = get_field( 'person_jobtitle' );
        if ( $cv_link ) {
            $cv_link = "<a href='{$cv_link}' class='button yellow'>Download CV</a>";
        }
        $title_suffix = get_field( 'person_title_suffix' );
        $department   = null; // get_field('person_') // @TODO this field may be unused on this site
        $location     = get_field( 'person_room' );
        $location_url = get_field( 'person_room_url' ); // link to a map
        $email        = get_field( 'person_email' );
        $phone_array  = get_field( 'person_phone_numbers' );
        $phone        = $phone_array[ 0 ][ 'number' ];

        $div_location = self::contact_info( $location, 'location', $location_url );
        $div_email    = self::contact_info( $email, 'email', "mailto:{$email}" );
        $div_phone    = self::contact_info( $phone, 'phone', "tel:{$phone}" );

        // ####

        $html_single_profile .= "
        <div class='person'>
            <div class='photo'>
                <a href='{$profile_url}' title='{$full_name}' style='background: url({$image_url}) no-repeat center center; background-size: cover;'>
                    {$full_name}
                </a>
            </div>
            <div class='details'>
                <a href='{$profile_url}' class='full_name'>{$full_name}</a>
                <small>{$title_suffix}</small>
                <span class='job_title'>{$job_title}</span>
                <span class='department'>{$department}</span>
                <div class='contact'>
                    {$div_location}
                    {$div_email}
                    {$div_phone}
                    {$cv_link}
                </div>
            </div>
        </div>
        ";

        return $html_single_profile;
    }

    /**
     * Output individual contact information for person, if defined
     *
     * @param string      $data
     * @param string      $class
     * @param string|null $url
     * @param string|null $title
     *
     * @return string
     */
	static public function contact_info( $data, $class, $url = null, $title = null ) {

        if ( $data ) {
            if ( ! $title ) {
                $title = strtoupper( $class );
            }

            $return = "<div class='{$class}'>";
            $return .= "<span class='label'>{$title}:</span>";
            if ( $url ) {
                $return .= "<span class='data'><a href={$url}>{$data}</a></span>";
            } else {
                $return .= "<span class='data'>{$data}</span>";
            }
            $return .= "</div>";

            return $return;
        } else {
            return ''; // no data
        }
    }

    // ############ Profile Output End

    // ############ Pagination Start

	/**
	 * Page links to go to other pages for the current search.
	 * You should only run this when actually showing contact cards. No use in page buttons to view more of nothing.
	 * @param $wp_query WP_Query
	 * @param $shortcode_attributes ucf_people_directory_shortcode_attributes
	 *
	 * @return string
	 */
	static public function pagination_html( $wp_query, $shortcode_attributes ) {
        $html_pagination = "<div class='pagination'>";

		$html_pagination .= paginate_links(
			array(
				'base'      => str_replace( 999999999, '%#%', esc_url( get_pagenum_link( 999999999 ) ) ),
				'total'     => $wp_query->max_num_pages,
				'current'   => max( 1, $shortcode_attributes->paged ),
				'end_size'  => 2,
				'mid_size'  => 2,
				'prev_next' => true

			)
		);
        $html_pagination .= "</div>";

        return $html_pagination;
    }
    // ############ Pagination End

    // ############ Subcategories Start

	/**
	 * Html wrapper for people groups list html
	 *
	 * @param $shortcode_attributes ucf_people_directory_shortcode_attributes
	 *
	 * @return string
	 */
	static public function people_groups_html($shortcode_attributes) {
        $html_people_groups = '';


        $people_group_list_html = self::people_group_list_html($shortcode_attributes);

        $html_people_groups .= "
            <div class='people_groups'>
                <h3 class='title yellow_underline'>Filter by</h3>
                <div class='list'>
                    <ul id='menu-directory-departments' class='menu'>
                        {$people_group_list_html}
                    </ul>
                </div>
            </div>
        ";

        return $html_people_groups;
    }

    /**
     * Sidebar with a list of people groups. Users can select a group to filter down to profiles only in that group.
     *
     * @param $shortcode_attributes ucf_people_directory_shortcode_attributes
     *
     * @return string
     */
	static public function people_group_list_html( $shortcode_attributes) {
        $html_people_group_list = '';
        $current_page_url       = wp_get_canonical_url();

        $current_term = $shortcode_attributes->people_group_slug;


		$get_terms_arguments = array(
			'taxonomy'   => self::taxonomy_name,
			'hide_empty' => true, // hide empty groups, even if specified by editor
		);
        if ( sizeof($shortcode_attributes->editor_people_groups) > 0 ) {
	        $get_terms_arguments['include'] = $shortcode_attributes->editor_people_groups_ids; // only include terms specified by the editor
        } else {
        	$get_terms_arguments['parent'] = 0; // only show top level groups - we'll get the children later for formatting
        }

        $people_groups_terms_top_level = new WP_Term_Query($get_terms_arguments);
        if (!$current_term){
            $html_people_group_list .= self::term_list_entry("All groups", $current_page_url, null, 'reset active');
        } else {
            $html_people_group_list .= self::term_list_entry("All groups", $current_page_url, null, 'reset');
        }
        foreach ( $people_groups_terms_top_level->terms as $top_level_term ) {
            /* @var $top_level_term  WP_Term */

            // list the parent
            if ( $current_term == $top_level_term->slug ){
                $html_people_group_list .= self::term_list_entry($top_level_term->name, $current_page_url, $top_level_term->slug, 'parent active');
            } else {
                $html_people_group_list .= self::term_list_entry($top_level_term->name, $current_page_url, $top_level_term->slug, 'parent');
            }

            $people_groups_terms_children = get_terms(
                array(
                    'taxonomy'   => self::taxonomy_name,
                    'hide_empty' => true, // hide empty groups, even if specified by editor
                    'parent' => $top_level_term->term_id // only show top level children for this group
                )
            );

            foreach ($people_groups_terms_children as $child_term) {
                // list the children. set class to child so it can be formatted differently
                if ( $current_term == $child_term->slug ){
                    $html_people_group_list .= self::term_list_entry($child_term->name, $current_page_url, $child_term->slug, 'child active');
                } else {
                    $html_people_group_list .= self::term_list_entry($child_term->name, $current_page_url, $child_term->slug, 'child');
                }

            }
        }

        return $html_people_group_list;
    }

    /**
     * Print out a single list item of the people group term
     * @param        $title
     * @param        $current_page_url
     * @param        $slug
     * @param string $class
     *
     * @return string
     */
	static public function term_list_entry($title, $current_page_url, $slug, $class = 'parent') {
        if ($slug) {
            $url_filter = "?" . self::GET_param_group . "={$slug}";
            $title_text = "Display only {$title} profiles";
        } else {
            $url_filter = ""; //if no slug is defined, this is the 'All groups' reset filter
            $title_text = "Display all profiles";
        }
        return "
                <li class='menu-item {$class}'>
                    <a 
                        title='{$title_text}' 
                        href='{$current_page_url}{$url_filter}'>
                        {$title}
                    </a>
                </li>
            ";
    }

    // ############ Subcategories End

    /**
     * Only run this on plugin activation, as it's stored in the database
     */
    static function insert_shortcode_term() {
        $taxonomy = new ucf_college_shortcode_taxonomy;
        $taxonomy->create_taxonomy();
        wp_insert_term(
            self::shortcode_name,
            ucf_college_shortcode_taxonomy::taxonomy_slug,
            array(
                'description' => self::shortcode_description,
                'slug'        => self::shortcode_slug
            )
        );
    }

    /**
     * Run when plugin is disabled and/or uninstalled. This removes the shortcode from the contentof shortcodes in the
     * taxonomy.
     */
    static function delete_shortcode_term() {
        $taxonomy = new ucf_college_shortcode_taxonomy;
        $taxonomy->create_taxonomy();
        wp_delete_term( get_term_by( 'slug', self::shortcode_slug )->term_id, ucf_college_shortcode_taxonomy::taxonomy_slug );
    }

}

class ucf_people_directory_shortcode_attributes {

	/** @var bool whether to show the search bar or not */
	public $show_search_bar = true;

	/** @var bool whether to show the actual contact cards or not */
	public $show_contacts = false;

	/** @var bool whether to show the sidebar with group filter links or not */
	public $show_group_filter_sidebar = false;

	/** @var string the actual html that gets printed out */
	public $replacement_data = '';

	/** @var array editor specified array of people groups slugs to show in directory. if empty, show everyone (full directory) */
	public $editor_people_groups = [];

	/** @var array editor specified array of people groups ids to show in directory. if empty, show everyone (full directory) */
	public $editor_people_groups_ids = [];

	/** @var string user specified people groups slug to filter. user overrides editor. if empty, show editor people groups */
	public $people_group_slug = '';

	/** @var string user specified search string */
	public $search_by_name = '';

	/** @var integer current page number */
	public $paged = 1;

	/** @var integer number of people to show per page */
	public $posts_per_page = ucf_people_directory_shortcode::posts_per_page;

	public function __construct() {
		$this->show_search_bar = (get_field('show_search_bar') || get_field('show_search_bar') === null);
		$this->initialize_editor_specified_groups();
		$this->initialize_user_specified_people_groups();
		$this->search_by_name = ( get_query_var( ucf_people_directory_shortcode::GET_param_name ) ) ? get_query_var( ucf_people_directory_shortcode::GET_param_name ) : '';

		if ( ( $this->people_group_slug != '') || ( $this->search_by_name != '')) { // user has searched, or the user or page owner has specified a group. show the contacts.
			$this->show_contacts = true;
		}

		// @TODO add another flag in ACF for the editor to allow contact cards to show on main view, even when user has not filtered anything by name or category.
		// this defaults to false, as that's the default value for show_contacts.
		/*if (some acf field == true){
			$this->show_contacts = true;
		}*/

		$this->paged = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1; //default to page 1;
		$this->posts_per_page = ( get_field('profiles_per_page') ? get_field('profiles_per_page') : ucf_people_directory_shortcode::posts_per_page);
		$this->show_group_filter_sidebar = (get_field('show_group_filter_sidebar')|| get_field('show_group_filter_sidebar') === null);

	}

	/**
	 * Gets the editor specified groups from the database.
	 */
	protected function initialize_editor_specified_groups(){
		if  (get_field('filtered') && have_rows( ucf_people_directory_shortcode::acf_filter_term_name ) ) {
			while ( have_rows( ucf_people_directory_shortcode::acf_filter_term_name ) ) {
				the_row();
				$group           = get_sub_field( 'group' );
				$this->editor_people_groups[] = $group->slug;
				$this->editor_people_groups_ids[] = $group->term_id;
			}
			reset_rows();
		}
	}

	/**
	 * Note: run *after* the editor specified groups has been initialized
	 * Sets the people_groups filter variable. It checks the user input against allowed categories, and resets it to defauls if user input is invalid.
	 */
	protected function initialize_user_specified_people_groups(){
		if ( get_query_var( ucf_people_directory_shortcode::GET_param_group ) ) {
			// user specified a group to filter to
			$u_people_group = get_query_var( ucf_people_directory_shortcode::GET_param_group ); // possibly unsafe value. check against allowed values

			$matching_people_group_obj = get_term_by('slug', $u_people_group, ucf_people_directory_shortcode::taxonomy_name);
			if ($matching_people_group_obj) {
				if ($this->editor_people_groups){
					// we have a user group, and the editor also defined one or more groups. we must now check that the user
					// specified group is one of the editor specified groups, or that it is a descendent of one of the editor groups.

					foreach ($this->editor_people_groups as $editor_group_slug){
						if ($editor_group_slug === $u_people_group) {
							// user filter equals one of the root editor groups
							$this->people_group_slug = $u_people_group;
						} elseif (term_is_ancestor_of(get_term_by('slug',$editor_group_slug), $matching_people_group_obj, ucf_people_directory_shortcode::taxonomy_name)) {
							// user filter equals a descendent of one of the root editor groups
							$this->people_group_slug = $u_people_group;
						} else {
							// no match yet. do nothing.
						}
					}
					if (!$this->people_group_slug) {
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
}

//new ucf_people_directory_shortcode();

add_action( 'init', array( 'ucf_people_directory_shortcode', 'add_shortcode' ) );
add_filter( 'query_vars', array( 'ucf_people_directory_shortcode', 'add_query_vars_filter' ) ); // tell wordpress about new url parameters
add_filter( 'ucf_college_shortcode_menu_item', array( 'ucf_people_directory_shortcode', 'add_ckeditor_shortcode' ) );
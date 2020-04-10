<?php

class ucf_people_directory_shortcode {
	const shortcode_slug        = 'ucf_people_directory'; // the shortcode text entered by the user (inside square brackets)
	const shortcode_name        = 'People Directory (deprecated - use blocks)';
	const shortcode_description = 'Searchable directory of all people';
	const posts_per_page        = '10'; // number of profiles to list per page when paginating
	const taxonomy_categories   = ''; // slug for the 'categories' taxonomy

	const taxonomy_name        = 'people_group';
	const acf_filter_term_name = 'specific_terms';
	const GET_param_group      = 'group_search'; // group or category person is in
	const GET_param_name       = 'name_search'; // restrict to profiles matching the user text

	const acf_sort_key = 'person_orderby_name';

	const transient_cache_buster_name = 'ucf-pd-cache-buster'; // the transient stored in the database with this name is simply a nonsenical value that is altered anytime a person is edited or added.

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
	 * @return array
	 * @var $shortcode_array array
	 *
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

		// wrapper div. special class if not showing cards.
		if ( $obj_shortcode_attributes->show_contacts ) {
			$obj_shortcode_attributes->replacement_data .= "<div class='ucf-people-directory'>";
		} else {
			$obj_shortcode_attributes->replacement_data .= "<div class='ucf-people-directory no-card-view'>";
		}

		// print out search bar
		if ( $obj_shortcode_attributes->show_search_bar ) {
			$obj_shortcode_attributes->replacement_data .= self::search_bar_html( $obj_shortcode_attributes );
		}

		$wp_query = null;
		if ( $obj_shortcode_attributes->show_contacts ) { // user has searched, or the user or page owner has specified a group. show the contacts.
			$transient_data = get_transient( $obj_shortcode_attributes->transient_name );
			if ( $transient_data ) {
				$obj_shortcode_attributes->replacement_data .= $transient_data;
			} else {
				$wp_query = self::query_profiles( $obj_shortcode_attributes );
				// print out profiles
				$fresh_data                                 = self::profiles_html( $wp_query, $obj_shortcode_attributes );
				$obj_shortcode_attributes->replacement_data .= $fresh_data;
				set_transient( $obj_shortcode_attributes->transient_name, $fresh_data, 60 * 60 * 24 * 30 ); // one month expiration. will also expire when any person is added/updated
			}

			wp_reset_postdata();
		}
		// print out subcategories unless shortcode defines a specific category
		if ( $obj_shortcode_attributes->show_group_filter_sidebar ) {
			$obj_shortcode_attributes->replacement_data .= self::people_groups_html( $obj_shortcode_attributes );
		}

		// print out pagination, if we're showing contacts
		if ( $obj_shortcode_attributes->show_contacts ) {
			$obj_shortcode_attributes->replacement_data .= self::pagination_html( $wp_query, $obj_shortcode_attributes );
		}

		$obj_shortcode_attributes->replacement_data .= "</div>";

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
	static public function search_bar_html( $shortcode_attributes ) {
		$html_search_bar  = '';
		$name_search      = self::GET_param_name;
		$current_page_url = wp_get_canonical_url();
		$html_search_bar  .= "
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
	 * Return a string of HTML with all matching profiles. If a single category is specified, weighted profiles appear
	 * first.
	 *
	 * @param $shortcode_attributes ucf_people_directory_shortcode_attributes
	 *
	 * @return WP_Query
	 */
	static public function query_profiles( $shortcode_attributes ) {

		// ## Query 1 - Run if viewing a single category
		if ( $shortcode_attributes->weighted_category_id ) {
			// user asked for a specific category, or the editor is showing one single department. we now need to look for weighted people.
			$weighted_people = self::profiles_weighted_id_list( $shortcode_attributes ); // Query 1
		} else {
			// user has not specified a category. weights don't come into effect, since we don't weight multi category views.
			$weighted_people = [];
		}

		$query_args = array(
			'paged'          => $shortcode_attributes->paged,
			'posts_per_page' => $shortcode_attributes->posts_per_page,
			'post_type'      => 'person', // 'person' is a post type defined in ucf-people-cpt
			's'              => $shortcode_attributes->search_by_name,
			'meta_query'     => array(
				'relation' => 'OR',
				// need to have this meta query in order to allow people that lack this meta key to still be included in results
				array(
					'key'     => self::acf_sort_key,
					'compare' => 'EXISTS'
				),
				array(
					'key'     => self::acf_sort_key,
					'compare' => 'NOT EXISTS'
				)
				// Gibson, Jane S.
			),
			'orderby'        => array(
				'meta_value' => 'ASC',
				'title'      => 'ASC',
				// fallback to title sort (first name, but oh well) if the sort field is missing. note: this will
			)
			//			'meta_key'       => self::acf_sort_key,
			//			'order'          => 'ASC',
		);

		// ## Query 2 - Run if single category, and we found weighted people for that category.
		if ( sizeof( $weighted_people ) > 0 ) {
			// weighted people found. run another query to get EVERY person to create an array of ids.
			$all_people              = self::profiles_id_list( $shortcode_attributes ); // Query 2
			$correctly_sorted_people = array_merge( $weighted_people, $all_people );
			$correctly_sorted_people = array_unique( $correctly_sorted_people );
			// now only select those profiles, and in the order specified
			$query_args[ 'post__in' ] = $correctly_sorted_people;
			$query_args[ 'orderby' ]  = 'post__in';
		}

		// if any group specified, filter to those groups. otherwise, show all.
		$people_groups = ( $shortcode_attributes->people_group_slug ? $shortcode_attributes->people_group_slug : $shortcode_attributes->editor_people_groups );
		if ( $people_groups ) {
			$query_args[ 'tax_query' ] = array(
				array(
					'taxonomy'         => self::taxonomy_name,
					'field'            => 'slug',
					'terms'            => $people_groups,
					'include_children' => true,
					'operator'         => 'IN'
				)
			);
		}

		// ## Query 3 - Always run. Optionally add results from previous two queries if they both ran.

		// Now we have all profiles, with the correct weighted ones at the beginning.
		// Finally, do a WP_QUERY, passing in our exact list of profiles, which will
		// honor the sort we specify.
		add_filter( 'posts_orderby', array( 'ucf_people_directory_shortcode', 'override_sql_order' ) );
		$return_query = new WP_Query( $query_args ); // Query 3
		remove_filter( 'posts_orderby', array( 'ucf_people_directory_shortcode', 'override_sql_order' ) );

		return $return_query;
	}

	/**
	 * Alters the sort order. Allows people without a sort key set to be sorted alphabetically by title.
	 * Note: this intermixes first name sorting with last name sorting. Not ideal.
	 * For now, it forces those without a sort key (or an empty one) to be sorted AFTER those with one defined.
	 *
	 * @param $orderby
	 *
	 * @return string
	 */
	static public function override_sql_order( $orderby ) {
		global $wpdb;

		$sort_key       = self::acf_sort_key;
		$sql_order_case = "
			CASE
				WHEN {$wpdb->postmeta}.meta_key <> '{$sort_key}' THEN CONCAT('2',{$wpdb->posts}.post_title)
				WHEN {$wpdb->postmeta}.meta_key = '{$sort_key}' AND {$wpdb->postmeta}.meta_value = '' THEN CONCAT('2',{$wpdb->posts}.post_title)
				WHEN {$wpdb->postmeta}.meta_key = '{$sort_key}' THEN CONCAT('1',{$wpdb->postmeta}.meta_value)
			END ASC
		";

		// sort order ends up being 2PostTitle and 1SortKey (via concat), so that sorting-wise those with a defined sort key are sorted before everyone who lacks that key.
		// otherwise, we'd have issues of AALastnameDDFirstname, BBFirstNameZZLastName, CCLastNameAAFirstName - IE intermixed last and first name sorting, which is useless.

		// We can't just extrapolate the first and last name from the post_title, either. Since the title may be something like Dr Firstname Middle1 Middle2 Lastname PHD MD SUFFIX.
		// There's no way to know from that text what their actual last and first name are for sorting purposes.

		$orderby = $sql_order_case;

		return $orderby;

	}

	/**
	 * Gets an ordered list of profile ids, sorted by specified weights. Used when querying a category
	 * and you want a specific set of people to be shown first. You can specify weights so that one
	 * or two people are at the top, then another group next, and finally everyone else in the
	 * category.
	 *
	 * @param ucf_people_directory_shortcode_attributes $shortcode_attributes
	 *
	 * @return array
	 */
	static public function profiles_weighted_id_list( $shortcode_attributes ) {
		// first, find all profiles that have a 'head of department' or similar tag for the currently filtered department.
		// sort by their weight. smaller numbers first.
		$query_args = array(
			// Don't use paged. We want ALL profiles that have a weight.
			// Then this list of ids will be given to another wp_query, which will paginate and filter as needed.
			//'paged'          => $shortcode_attributes->paged,
			'posts_per_page'   => - 1,
			'post_type'        => 'person',
			// 'person' is a post type defined in ucf-people-cpt
			's'                => $shortcode_attributes->search_by_name,
			'orderby'          => 'meta_value',
			'meta_key'         => self::acf_sort_key,
			// we still order by person name. if weights are equal, names should be sorted.
			'order'            => 'ASC',

			// only query the user-specified people group.
			// if the user hasn't specified one, this function shouldn't be called.
			// we don't weight any profiles on the default view.
			'tax_query'        => array(
				array(
					'taxonomy'         => self::taxonomy_name,
					'field'            => 'slug',
					'terms'            => $shortcode_attributes->weighted_category_slug,
					'include_children' => true,
					'operator'         => '='
				)
			),

			// only get profiles with a weight for the user-specified category.
			// also, check that the custom_sort_order boolean is true. if the editor
			// marks it as false, the old data is still in the database, but we shouldn't sort by it.
			'meta_query'       => array(
				'relation' => 'AND',
				array(
					'key'     => 'departments_$_department',
					'compare' => '=',
					'value'   => $shortcode_attributes->weighted_category_id,
					// acf stores taxonomy by id within the database backend, so convert the user slug to id
				),
				array(
					'key'     => 'custom_sort_order',
					'compare' => '=',
					'value'   => '1',
				)
			),
			'suppress_filters' => false
		);


		add_filter( 'posts_where', array( 'ucf_people_directory_shortcode', 'acf_meta_subfield_filter' ) );

		$wp_query = new WP_Query( $query_args );

		remove_filter( 'posts_where', array( 'ucf_people_directory_shortcode', 'acf_meta_subfield_filter' ) );


		// next, query for all profiles in the category, and use the previous array as the initial sortby field, but also
		// sort by the orderby_name field after.

		$weighted_array     = [];
		$single_category_id = $shortcode_attributes->weighted_category_id;
		if ( $wp_query->have_posts() ) {
			while ( $wp_query->have_posts() ) {
				$wp_query->the_post();
				$person_id = get_the_ID();
				// get the matching weight for our category
				$weighted_array[ $person_id ] = self::acf_weight_for_category( $single_category_id, $person_id );
			}
		}

		// sort the unweighted array by weights
		asort( $weighted_array );

		return array_keys( $weighted_array ); // keys are the post id. they should now be sorted

	}

	/**
	 * Gets an ordered list of profile ids, unsorted.
	 *
	 * @param ucf_people_directory_shortcode_attributes $shortcode_attributes
	 *
	 * @return array
	 */
	static public function profiles_id_list( $shortcode_attributes ) {
		$query_args = array(
			'posts_per_page' => - 1,
			'post_type'      => 'person', // 'person' is a post type defined in ucf-people-cpt
			's'              => $shortcode_attributes->search_by_name,
			'orderby'        => 'meta_value',
			'meta_key'       => self::acf_sort_key,
			'order'          => 'ASC',
			'fields'         => 'ids', // only get a list of ids

			// only query the user-specified people group.
			// if the user hasn't specified one, this function shouldn't be called.
			// we don't weight any profiles on the default view.
			'tax_query'      => array(
				array(
					'taxonomy'         => self::taxonomy_name,
					'field'            => 'slug',
					'terms'            => $shortcode_attributes->weighted_category_slug,
					'include_children' => true,
					'operator'         => '='
				)
			),
		);
		$wp_query   = new WP_Query( $query_args );

		return $wp_query->posts;
	}

	/**
	 * Alters the WP SQL query to allow filtering posts based on a repeater subfield.
	 * Turns 'key = parent_$_child' to 'key LIKE parent_%_child', replacing the dollar sign with percent,
	 * and the equals with LIKE.
	 *
	 * @param $where
	 *
	 * @return mixed
	 */
	static public function acf_meta_subfield_filter( $where ) {

		$where = str_replace( 'meta_key = \'departments_$_department', "meta_key LIKE 'departments_%_department", $where );

		//$where = str_replace('meta_key = \'departments_$_weight', "meta_key LIKE 'departments_%_weight", $where);
		return $where;
	}

	/**
	 * Returns the weight, if any, for the specified category and the specified or current person.
	 *
	 * @param int      $category_id ID (not slug) of the category-specific weight to look for
	 * @param int|null $person_id   Person profile to look for. If unspecified, uses current post.
	 *
	 * @return integer
	 */
	static public function acf_weight_for_category( $category_id, $person_id = null ) {
		$return_weight = null;
		if ( ! $person_id ) {
			$person_id = get_the_ID();
		}
		while ( have_rows( 'departments', $person_id ) ) { // the_post apparently doesn't set the right global variables, so we explicitly tell acf the post id
			the_row();
			$department = get_sub_field( 'department' );
			if ( $department === $category_id ) {
				// found the matching weight
				$return_weight = get_sub_field( 'weight' );
			}
		}

		return $return_weight;
	}

	/**
	 * @param $wp_query WP_Query
	 *
	 * @return string
	 */
	static public function profiles_html( $wp_query, $shortcode_attributes ) {
		$html_list_profiles = "<div class='profiles-list'>";

		if ( $wp_query->have_posts() ) {
			while ( $wp_query->have_posts() ) {
				$wp_query->the_post();
				$html_list_profiles .= self::profile( $shortcode_attributes );

			}
		} else {
			$html_list_profiles .= "<div class='no-results'>No results found.</div>";
		}

		$html_list_profiles .= "</div>";

		return $html_list_profiles;
	}

	/**
	 * Call this function after the_post is set to a profile (called within a loop)
	 */
	/**
	 * @param ucf_people_directory_shortcode_attributes $shortcode_attributes
	 *
	 * @return string
	 */
	static public function profile( $shortcode_attributes ) {
		$html_single_profile = ''; //return data

		// #### set variables used in html output
		$person_title_prefix = get_field( 'person_title_prefix' );
		$full_name           = $person_title_prefix . ' ' . get_the_title();
		$profile_url         = get_permalink();
		$image_url           = get_the_post_thumbnail_url( null, 'medium' );
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

		$weight = self::acf_weight_for_category( $shortcode_attributes->weighted_category_id, get_the_ID() );

		if ( $weight ) {
			$weight_class = "weighted weight-{$weight}";
		} else {
			$weight_class = "";
		}

		// ####

		$html_single_profile .= "
        <div class='person {$weight_class}'>
            <div class='photo'>
                <a href='{$profile_url}' title='{$full_name}' style='background-image: url({$image_url})'>
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
	 *
	 * @param $wp_query             WP_Query
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
	static public function people_groups_html( $shortcode_attributes ) {
		$html_people_groups = '';


		$people_group_list_html = self::people_group_list_html( $shortcode_attributes );
		$html_people_groups     .= "
            <div class='people_groups'>
                <h3 class='title yellow_underline'>Filter by</h3>
                <div class='list'>
                    <ul id='{$shortcode_attributes->directory_id}' class='menu'>
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
	static public function people_group_list_html( $shortcode_attributes ) {
		$html_people_group_list = '';

		$current_page_url = wp_get_canonical_url();

		$current_term = $shortcode_attributes->people_group_slug;


		$get_terms_arguments = array(
			'taxonomy'   => self::taxonomy_name,
			'hide_empty' => true, // hide empty groups, even if specified by editor
		);
		if ( sizeof( $shortcode_attributes->editor_people_groups ) > 0 ) {
			$get_terms_arguments[ 'include' ] = $shortcode_attributes->editor_people_groups_ids; // only include terms specified by the editor
		} else {
			// editor wants a global directory (no categories specified)
			$get_terms_arguments[ 'parent' ] = 0; // only show top level groups - we'll get the children later for formatting
		}

		$people_groups_terms_top_level = new WP_Term_Query( $get_terms_arguments );
		if ( ! $current_term ) {
			if ( $shortcode_attributes->show_contacts_on_unfiltered ) {
				// only show the 'all groups' link on an unfiltered view if the editor is also showing contacts.
				// otherwise, 'all groups' shouldn't be shown, as it's confusing to have a link to 'all groups' but then not see any contacts when clicked.
				$html_people_group_list .= self::term_list_entry( "All Groups", $current_page_url, null, 'reset active' );
			} else {
				if ( $shortcode_attributes->search_by_name ) {
					// show the reset filter link when a user searched by name.
					$html_people_group_list .= self::term_list_entry( "Reset Filters", $current_page_url, null, 'reset' );
				} else {
					// don't need an else. if no current term, we're on unfiltered. and editor doesn't want cards shown. so don't print out either 'all groups' or 'reset filters'
				}
			}
		} else {
			if ( $shortcode_attributes->show_contacts_on_unfiltered ) {
				$html_people_group_list .= self::term_list_entry( "All Groups", $current_page_url, null, 'reset' );
			} else {
				$html_people_group_list .= self::term_list_entry( "Reset Filters", $current_page_url, null, 'reset' );
			}
		}
		foreach ( $people_groups_terms_top_level->terms as $top_level_term ) {
			/* @var $top_level_term  WP_Term */

			$people_groups_terms_children = get_terms(
				array(
					'taxonomy'   => self::taxonomy_name,
					'hide_empty' => true, // hide empty groups, even if specified by editor
					'parent'     => $top_level_term->term_id // only show top level children for this group
				)
			);

			if ( sizeof( $people_groups_terms_children ) > 0 ) {
				// term has children. make the top term an accordion, with the first inner element pointing to the parent filter. rest of elements are children filters
				$html_people_group_list .= self::term_list_entry_with_children( $top_level_term, $current_page_url, $current_term, $people_groups_terms_children, $shortcode_attributes );


			} else {
				// term is a loner. just make it a filter link, no accordion.
				// list the parent
				if ( $current_term == $top_level_term->slug ) {
					$html_people_group_list .= self::term_list_entry( $top_level_term->name, $current_page_url, $top_level_term->slug, 'parent active' );
				} else {
					$html_people_group_list .= self::term_list_entry( $top_level_term->name, $current_page_url, $top_level_term->slug, 'parent' );
				}
			}


		}

		return $html_people_group_list;
	}

	/**
	 * Print out a single list item of the people group term
	 *
	 * @param        $title
	 * @param        $current_page_url
	 * @param        $slug
	 * @param string $class
	 *
	 * @return string
	 */
	static public function term_list_entry( $title, $current_page_url, $slug, $class = 'parent' ) {
		if ( $slug ) {
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

	/**
	 * @param                                           $top_level_term
	 * @param                                           $current_page_url
	 * @param                                           $current_term
	 * @param                                           $people_groups_terms_children
	 * @param ucf_people_directory_shortcode_attributes $shortcode_attributes
	 *
	 * @return string
	 */
	static public function term_list_entry_with_children( $top_level_term, $current_page_url, $current_term, $people_groups_terms_children, $shortcode_attributes ) {
		$return_accordion_html              = "";
		$accordion_collapsible_content_html = "";
		$collapsed                          = true;

		// list the parent
		if ( $current_term == $top_level_term->slug ) {
			$accordion_collapsible_content_html .= self::term_list_entry( "All " . $top_level_term->name, $current_page_url, $top_level_term->slug, 'parent active' );
			$collapsed                          = false;
		} else {
			$accordion_collapsible_content_html .= self::term_list_entry( "All " . $top_level_term->name, $current_page_url, $top_level_term->slug, 'parent' );
		}

		foreach ( $people_groups_terms_children as $child_term ) {
			// list the children. set class to child so it can be formatted differently
			if ( $current_term == $child_term->slug ) {
				$accordion_collapsible_content_html .= self::term_list_entry( $child_term->name, $current_page_url, $child_term->slug, 'child active' );
				$collapsed                          = false;
			} else {
				$accordion_collapsible_content_html .= self::term_list_entry( $child_term->name, $current_page_url, $child_term->slug, 'child' );
			}

		}
		if ( $collapsed ) {
			$collapse_class = "collapse";
			$expanded       = "false";
		} else {
			$collapse_class = "collapse show";
			$expanded       = "true";
		}

		if ( $shortcode_attributes->show_contacts ) {
			$accordion_mode = "collapse";
			$parent_href    = "href='#collapse-{$top_level_term->slug}'";
		} else {
			$accordion_mode = "";
			$parent_href    = "";
			$collapse_class = "collapse show";
			$expanded       = "true";
		}

		$return_accordion_html .= "
<li class='menu-item-collapse' id='heading-{$top_level_term->slug}'>
    <a data-toggle='{$accordion_mode}' $parent_href aria-expanded='{$expanded}' aria-controls='collapse-{$top_level_term->slug}'>
        <i class='fa fa-angle-down'></i>{$top_level_term->name}
    </a>
    
	<div id='collapse-{$top_level_term->slug}' class='{$collapse_class}' role='atabpanel' aria-labelledby='heading-{$top_level_term->slug}' data-parent='#{$shortcode_attributes->directory_id}'>
		<ul>
		{$accordion_collapsible_content_html}
		</ul>
	</div>
</li>
    ";

		return $return_accordion_html;
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

	/**
	 * This function alters a unique value whenever a person is added or edited.
	 * The result is that all previous transients are invalidated or inaccessible.
	 * They'll expire eventually, but they won't be utilized anymore, since their
	 * name is directly tied to this value.
	 * WordPress cannot delete transients with a wildcard, and since we need a lot
	 * of different transients for each unique set of categories, pagination, posts_per_page,
	 * and other variables, we can't simply invalidate all transients.
	 * Instead, all transients with those variables also use this common cache value,
	 * so when it changes, WordPress will try to access a brand new transient name which
	 * doesn't exist yet, and all the old names stop being used.
	 *
	 * @param integer $post_id
	 * @param WP_Post $post
	 *
	 * @throws Exception
	 */
	static function cache_bust_on_person_edit() {
		set_transient( self::transient_cache_buster_name, bin2hex( random_bytes( 8 ) ) );
	}

}

class ucf_people_directory_shortcode_attributes {

	/** @var bool whether to show the search bar or not */
	public $show_search_bar = true;

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

	/** @var string user specified search string */
	public $search_by_name = '';

	/** @var integer current page number */
	public $paged = 1;

	/** @var integer number of people to show per page */
	public $posts_per_page = ucf_people_directory_shortcode::posts_per_page;

	/** @var string|void collision prevention - generate random bytes to prevent multiple directory blocks on the same page from having the same #id */
	public $directory_id;

	/**
	 * @var string transient name for the card view of the current directory, based on name search, page, category, and
	 *      a cache buster that changes whenever a profile changes
	 */
	public $transient_name;

	/**
	 * ucf_people_directory_shortcode_attributes constructor.
	 * Initializes all values with safe and logical values, based on user input, editor preferences, and logical
	 * deductions.
	 */
	public function __construct() {
		$this->show_search_bar = ( get_field( 'show_search_bar' ) || get_field( 'show_search_bar' ) === null );
		$this->initialize_editor_specified_groups();
		$this->initialize_user_specified_people_groups();
		$this->initialize_weighted_category();
		$this->search_by_name = ( get_query_var( ucf_people_directory_shortcode::GET_param_name ) ) ? get_query_var( ucf_people_directory_shortcode::GET_param_name ) : '';

		if ( ( $this->people_group_slug != '' ) || ( $this->search_by_name != '' ) ) { // user has searched, or the user or page owner has specified a group. show the contacts.
			$this->show_contacts = true;
		}

		// this defaults to false, as that's the default value for show_contacts.
		if ( get_field( 'initially_shown' ) ) {
			$this->show_contacts               = true;
			$this->show_contacts_on_unfiltered = true;
		}

		$this->paged                     = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1; //default to page 1;
		$this->posts_per_page            = ( get_field( 'profiles_per_page' ) ? get_field( 'profiles_per_page' ) : ucf_people_directory_shortcode::posts_per_page );
		$this->show_group_filter_sidebar = ( get_field( 'show_group_filter_sidebar' ) || get_field( 'show_group_filter_sidebar' ) === null );

		$this->directory_id = "menu-directory-departments-" . bin2hex( random_bytes( 8 ) ); // prevent #id collisions by generating a different id for each directory block. changes on each page load, but it isn't referenced in css.
		$this->set_transient_name();
	}

	/**
	 * Gets the editor specified groups from the database.
	 */
	protected function initialize_editor_specified_groups() {
		if ( get_field( 'filtered' ) && have_rows( ucf_people_directory_shortcode::acf_filter_term_name ) ) {
			while ( have_rows( ucf_people_directory_shortcode::acf_filter_term_name ) ) {
				the_row();
				$group                            = get_sub_field( 'group' );
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
		if ( get_query_var( ucf_people_directory_shortcode::GET_param_group ) ) {
			// user specified a group to filter to
			$u_people_group = get_query_var( ucf_people_directory_shortcode::GET_param_group ); // possibly unsafe value. check against allowed values

			$matching_people_group_obj = get_term_by( 'slug', $u_people_group, ucf_people_directory_shortcode::taxonomy_name );
			if ( $matching_people_group_obj ) {
				if ( $this->editor_people_groups ) {
					// we have a user group, and the editor also defined one or more groups. we must now check that the user
					// specified group is one of the editor specified groups, or that it is a descendent of one of the editor groups.

					foreach ( $this->editor_people_groups as $editor_group_slug ) {
						if ( $editor_group_slug === $u_people_group ) {
							// user filter equals one of the root editor groups
							$this->people_group_slug = $u_people_group;
						} elseif ( term_is_ancestor_of( get_term_by( 'slug', $editor_group_slug ), $matching_people_group_obj, ucf_people_directory_shortcode::taxonomy_name ) ) {
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
			$this->weighted_category_id   = get_term_by( 'slug', $this->weighted_category_slug, ucf_people_directory_shortcode::taxonomy_name )->term_id;

		} elseif ( sizeof( $this->editor_people_groups ) === 1 ) {
			$this->weighted_category_slug = $this->editor_people_groups[ 0 ];
			$this->weighted_category_id   = get_term_by( 'slug', $this->weighted_category_slug, ucf_people_directory_shortcode::taxonomy_name )->term_id;
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
		if ( ! $this->show_contacts ) {
			$this->transient_name = '';

			return; // transient is only for contacts. if this current view doesn't show contacts, there's no transient.
		}

		// first, get the current cache-busting transient value. this value changes whenever a person is added or updated,
		// so that the directory is always up to date with the latest information, but is only recomputed when people change.

		$meta_transient_cache_buster_value = get_transient( ucf_people_directory_shortcode::transient_cache_buster_name );

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
		$transient_name = 'ucf-pd-'; // prefix with semi-readable name, so we can at least see in the database that these transients belong to this plugin
		if ( $this->weighted_category_slug ) {
			$category = $this->weighted_category_slug;
		} else {
			$category = implode( "+", $this->editor_people_groups );
		}
		$transient_name .= md5( $category . $this->search_by_name . $this->paged . $this->posts_per_page . $meta_transient_cache_buster_value );

		$this->transient_name = substr( $transient_name, 0, 40 ); // transient names are limited to 45 characters, if they have an expiration. use the first 40 characters of our ucf-pd-MD5HASH1234123412341234
		echo $this->transient_name;
	}
}

//new ucf_people_directory_shortcode();

add_action( 'init', array( 'ucf_people_directory_shortcode', 'add_shortcode' ) );
add_filter( 'query_vars', array(
	'ucf_people_directory_shortcode',
	'add_query_vars_filter'
) ); // tell wordpress about new url parameters

// when a person is added or updated, change the cache-buster value to force directories to recompute
// note: publish_person will run on both draft->publish and on publish->publish (ie saved updated data)
// https://developer.wordpress.org/reference/functions/wp_transition_post_status/
add_action( 'publish_person', array( 'ucf_people_directory_shortcode', 'cache_bust_on_person_edit' ) );
add_action( 'trash_person', array( 'ucf_people_directory_shortcode', 'cache_bust_on_person_edit' ) );


add_filter( 'ucf_college_shortcode_menu_item', array( 'ucf_people_directory_shortcode', 'add_ckeditor_shortcode' ) );
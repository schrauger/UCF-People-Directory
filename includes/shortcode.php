<?php

class ucf_people_directory_shortcode {
    const shortcode_slug        = 'ucf_people_directory'; // the shortcode text entered by the user (inside square brackets)
    const shortcode_name        = 'People Directory';
    const shortcode_description = 'Searchable directory of all people';
    const posts_per_page        = '10'; // number of profiles to list per page when paginating
    const taxonomy_categories   = ''; // slug for the 'categories' taxonomy

    const get_param_group = 'people_group'; // group or category person is in
    const get_param_name  = 'name_search'; // restrict to profiles matching the user text

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
        $vars[] = self::get_param_group;
        $vars[] = self::get_param_name; // person name, from user submitted search text
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
        $replacement_data = ''; //string of html to return
        // print out search bar
        $replacement_data .= self::search_bar_html();

        // get people groups. if user is filtering down to a group, only get those records. otherwise, show groups the editor defined.
        // allow user to specify a people_group (filter down from available groups)
        if ( get_query_var( self::get_param_group ) ) {
            $people_groups = get_query_var( self::get_param_group );
        } else {
            // user didn't specify a group, so show all the groups that the editor defined for this page
            $people_groups = array();
            $filter_active = get_query_var('filtered');
            if ( ($filter_active === 'true' || $filter_active === true) && have_rows( self::get_param_group ) ) {
                while ( have_rows( self::get_param_group ) ) {
                    the_row();
                    $group           = get_sub_field( 'group' );
                    $people_groups[] = $group->slug;
                }
                reset_rows();
            }
        }

        $wp_query = self::query_profiles( $people_groups );
        // print out profiles
        $replacement_data .= "<div class='profiles-list'>";
        $replacement_data .= self::profiles_html( $wp_query );
        $replacement_data .= "</div>";

        wp_reset_postdata();

        // print out subcategories unless shortcode defines a specific category
        $replacement_data .= self::people_groups_html();


        // print out pagination
        $replacement_data .= self::pagination_html( $wp_query );

        return $replacement_data;
    }

	static function replacement_print() {
		echo self::replacement();
	}

    // ############ Search Bar Start

    /**
     * Return a string of HTML for the search input form
     * @return string
     */
	static public function search_bar_html() {
        $html_search_bar  = '';
        $name_search      = self::get_param_name;
        $name_search_value = get_query_var(self::get_param_name);
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
                    value='{$name_search_value}'
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
     * Return a string of HTML with all matching profiles
     *
     * @param $people_groups
     *
     * @return wp_query
     */
	static public function query_profiles( $people_groups ) {

        $paged          = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1; //default to page 1
        $search_by_name = ( get_query_var( self::get_param_name ) ) ? get_query_var( self::get_param_name ) : ''; //don't restrict by default
        $query_args     = array(
            'paged'          => $paged,
            'posts_per_page' => self::posts_per_page,
            'post_type'      => 'person', // 'person' is a post type defined in ucf-people-cpt
            's'              => $search_by_name,
            'orderby'        => 'meta_value',
            'meta_key'       => 'person_orderby_name',
            'order'          => 'ASC'
        );

        // if any group specified, filter to those groups. otherwise, show all.
        if ($people_groups){
        	$query_args['tax_query'] = array(
		        array(
			        'taxonomy'         => 'people_group',
			        'field'            => 'slug',
			        'terms'            => $people_groups,
			        'include_children' => true,
			        'operator'         => 'IN'
		        )
	        );
        }



        return new WP_Query( $query_args );

    }

    /**
     * @param $wp_query
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

	static public function pagination_html( $wp_query ) {
        $html_pagination = "<div class='pagination'>";

        $html_pagination .= paginate_links( array(
                                                'base'      => str_replace( 999999999, '%#%', esc_url( get_pagenum_link( 999999999 ) ) ),
                                                'total'     => $wp_query->max_num_pages,
                                                'current'   => max( 1, get_query_var( 'paged' ) ),
                                                'end_size'  => 2,
                                                'mid_size'  => 2,
                                                'prev_next' => true

                                            ) );
        $html_pagination .= "</div>";

        return $html_pagination;
    }
    // ############ Pagination End

    // ############ Subcategories Start

	static public function people_groups_html() {
        $html_people_groups = '';


        $people_group_list_html = self::people_group_list_html();

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
     * @return string
     */
	static public function people_group_list_html() {
        $html_people_group_list = '';
        $current_page_url       = wp_get_canonical_url();
        if ( get_query_var( self::get_param_group ) ) {
            $current_term = get_query_var( self::get_param_group );
        } else {
            $current_term = null;
        }



        $people_groups_term_ids = array();
        if ( have_rows( 'people_groups' ) ) {
            while ( have_rows( 'people_groups' ) ) {
                the_row();
                $group                 = get_sub_field( 'group' );
                $people_groups_term_ids[] = $group->term_id;
            }
            reset_rows();
        }

        //
        $people_groups_terms_top_level = get_terms(
            array(
                'taxonomy'   => 'people_group',
                'hide_empty' => true, // hide empty groups, even if specified by editor
                'include' => $people_groups_term_ids, // only include terms specified by the editor
                //'parent' => 0 // only show top level groups - we'll get the children later for formatting
            )
        );

        if (!$current_term){
            $html_people_group_list .= self::term_list_entry("All groups", $current_page_url, null, 'reset active');
        } else {
            $html_people_group_list .= self::term_list_entry("All groups", $current_page_url, null, 'reset');
        }

        foreach ( $people_groups_terms_top_level as $top_level_term ) {
            /* @var $term  WP_Term */


            // list the parent
            if ( $current_term == $top_level_term->slug ){
                $html_people_group_list .= self::term_list_entry($top_level_term->name, $current_page_url, $top_level_term->slug, 'parent active');
            } else {
                $html_people_group_list .= self::term_list_entry($top_level_term->name, $current_page_url, $top_level_term->slug, 'parent');
            }

            $people_groups_terms_children = get_terms(
                array(
                    'taxonomy'   => 'people_group',
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
            $url_filter = "?people_group={$slug}";
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

//new ucf_people_directory_shortcode();

add_action( 'init', array( 'ucf_people_directory_shortcode', 'add_shortcode' ) );
add_filter( 'query_vars', array( 'ucf_people_directory_shortcode', 'add_query_vars_filter' ) ); // tell wordpress about new url parameters
add_filter( 'ucf_college_shortcode_menu_item', array( 'ucf_people_directory_shortcode', 'add_ckeditor_shortcode' ) );
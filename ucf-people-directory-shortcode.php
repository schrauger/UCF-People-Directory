<?php

class ucf_people_directory_shortcode {
    const shortcode         = 'ucf_people_directory'; // the shortcode text entered by the user (inside square brackets)
    const posts_per_page     = '10'; // number of profiles to list per page when paginating
    const taxonomy_categories = ''; // slug for the 'categories' taxonomy

    const get_param_group = 'people_group'; // group or category person is in
    const get_param_name = 'name_search'; // restrict to profiles matching the user text

    public function __construct() {
        add_action( 'init', array( $this, 'add_shortcode' ) );
        add_filter( 'query_vars', array($this, 'add_query_vars_filter' )); // tell wordpress about new url parameters
    }

    /**
     * Adds the shortcode to wordpress' index of shortcodes
     */
    public function add_shortcode() {
        if ( ! ( shortcode_exists( self::shortcode ) ) ) {
            add_shortcode( self::shortcode, array($this, 'replacement' ));
        }
    }

    /**
     * Tells wordpress to listen for the 'people_group' parameter in the url. Used to filter down to specific profiles.
     * @param $vars
     *
     * @return array
     */
    public function add_query_vars_filter($vars){
        $vars[] = self::get_param_group;
        $vars[] = self::get_param_name; // person name, from user submitted search text
        return $vars;
    }

    /**
     * Returns the replacement html that WordPress uses in place of the shortcode
     * @param null $attrs
     *
     * @return mixed
     */
    public function replacement( $attrs = null ){
        $replacement_data = ''; //string of html to return

        // define defaults for shortcode attributes if user doesn't specify
        $attributes = shortcode_atts(
            array(
                self::get_param_group => '',
                ''
            ), $attrs, self::shortcode );

        // print out search bar
        $replacement_data .= $this->search_bar_html();

        $wp_query = $this->query_profiles($attributes);
        // print out profiles
        $replacement_data .= "<div class='profiles-list'>";
            $replacement_data .= $this->profiles_html($wp_query);
        $replacement_data .= "</div>";

        wp_reset_postdata();

        // print out subcategories
        $replacement_data .= $this->people_groups_html();
        // $replacement_data .=

        // print out pagination
        $replacement_data .= $this->pagination_html($wp_query);

        return $replacement_data;
    }

    // ############ Search Bar Start

    /**
     * Return a string of HTML for the search input form
     * @return string
     */
    public function search_bar_html(){
        $html_search_bar = '';
        $name_search = self::get_param_name;
        $current_page_url = wp_get_canonical_url();
        $html_search_bar .= "
        <div class='searchbar'>
            <form id='searchform' action='$current_page_url' method='get'>
                <input 
                    class='searchbar' 
                    type='text' 
                    name='{$name_search}' 
                    placeholder='Search by Name' 
                    onfocus='this.placeholder = \"\" '
                    onblur='this.placeholder = \"Search by Name\"'
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
     * @param $attributes
     *
     * @return wp_query
     */
    public function query_profiles($attributes){

        // only allow user to specify people_group if the editor has not explicitely defined a people_group in the shortcode
        if ($attributes['people_group']){
            $people_group = $attributes[self::get_param_group];
        } else {
            $people_group = get_query_var(self::get_param_group);
        }

        $paged = ( get_query_var ( 'paged' ) ) ? get_query_var ( 'paged' ) : 1; //default to page 1
        $search_by_name = ( get_query_var ( self::get_param_name ) ) ? get_query_var ( self::get_param_name ) : ''; //don't restrict by default
        $query_args = array(
            'people_group' => $people_group,
            'paged' => $paged,
            'posts_per_page' => self::posts_per_page,
            'post_type' => 'person', // 'person' is a post type defined in ucf-people-cpt
            's' => $search_by_name
        );
        return new WP_Query( $query_args );

    }

    /**
     * @param $wp_query
     *
     * @return string
     */
    public function profiles_html($wp_query){
        $html_list_profiles = "";

        if ($wp_query->have_posts()) {
            while ($wp_query->have_posts()) {
                $wp_query->the_post();
                $html_list_profiles .= $this->profile();

            }
        }

        return $html_list_profiles;
    }

    /**
     * Call this function after the_post is set to a profile (called within a loop)
     */
    public function profile(){
        $html_single_profile = ''; //return data

        // #### set variables used in html output
        $person_title_prefix = get_field('person_title_prefix');
        $full_name = $person_title_prefix . ' ' . get_the_title();
        $profile_url = get_permalink();
        $image_url = get_the_post_thumbnail_url();
        $cv_link = get_field('person_cv');
        if (!$image_url){
            $image_url = plugin_dir_url(__FILE__) . "default.png"; // default image location
        }
        $job_title = get_field('person_jobtitle');
        if ($cv_link){
            $cv_link = "<a href='{$cv_link}' class='button yellow'>Download CV</a>";
        }
        $title_suffix = get_field('person_title_suffix');
        $department = null; // get_field('person_')
        $location = get_field('person_room');
        $location_url = get_field('person_room_url'); // link to a map
        $email = get_field('person_email');
        $phone_array = get_field('person_phone_numbers');
        $phone = $phone_array[0]['number'];

        $div_location = $this->contact_info($location, 'location', $location_url);
        $div_email = $this->contact_info($email, 'email', "mailto:{$email}");
        $div_phone = $this->contact_info($phone, 'phone');

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
     * @param string $data
     * @param string $class
     * @param string|null $url
     * @param string|null $title
     *
     * @return string
     */
    public function contact_info($data, $class, $url = null, $title = null){

        if ($data){
            if (!$title){
                $title = strtoupper($class);
            }

            $return = "<div class='{$class}'>";
            $return .= "<span class='label'>{$title}:</span>";
            if ($url){
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

    public function pagination_html($wp_query){
        $html_pagination = "<div class='pagination'>";

        $html_pagination .= paginate_links( array(
            'base' => str_replace( 999999999, '%#%', esc_url( get_pagenum_link( 999999999 ) ) ),
            'total' => $wp_query->max_num_pages,
            'current' => max( 1, get_query_var('paged')),
            'end_size' => 2,
            'mid_size' => 2,
            'prev_next' => true

        ));
        $html_pagination .= "</div>";
        return $html_pagination;
    }
    // ############ Pagination End

    // ############ Subcategories Start

    public function people_groups_html(){
        $html_people_groups = '';



        $people_group_list_html = $this->people_group_list_html();

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

    public function people_group_list_html(){
        $html_people_group_list = '';
        $current_page_url = wp_get_canonical_url();

        $people_groups_terms = get_terms( array(
            'taxonomy' => 'people_group',
            'hide_empty' => true
        ));

        foreach ($people_groups_terms as $term) {
            /* @var $term  WP_Term */
            $title = $term->name;
            $slug = $term->slug;

            $html_people_group_list .= "
                <li class='menu-item'>
                    <a 
                        title='Display only {$title} profiles' 
                        href='{$current_page_url}?people_group={$slug}'>
                        {$title}
                    </a>
                </li>
            ";
        }

        return $html_people_group_list;
    }
    // ############ Subcategories End

}
new ucf_people_directory_shortcode();

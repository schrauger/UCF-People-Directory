<?php

class ucf_people_directory_shortcode {
    const shortcode         = 'ucf_people_directory'; // the shortcode text entered by the user (inside square brackets)
    const posts_per_page     = '10'; // number of profiles to list per page when paginating
    const taxonomy_categories = ''; // slug for the 'categories' taxonomy

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
        $vars[] = 'people_group';
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

        // print out search bar
        // @TODO search bar
        // $replacement_data .=

        // print out profiles
        $replacement_data .= $this->list_profiles($attrs);

        // print out pagination
        // @TODO pagination
        // $replacement_data .=

        wp_reset_postdata();
        return $replacement_data;
    }

    /**
     * Return a string of HTML with all matching profiles
     * @param $shortcode_attributes
     *
     * @return string
     */
    public function list_profiles($shortcode_attributes){
        $html_list_profiles = '';
        $attributes = shortcode_atts(
            array(
                'people_group' => '',
            ), $shortcode_attributes, self::shortcode );

        // only allow user to specify people_group if the editor has not explicitely defined a people_group in the shortcode
        if ($attributes['people_group']){
            $people_group = $attributes['people_group'];
        } else {
            $people_group = get_query_var('people_group');
        }

        $paged = ( get_query_var ( 'paged' ) ) ? get_query_var ( 'paged' ) : 1; //default to page 1
        $query_args = array(
            'people_group' => $people_group,
            'paged' => $paged,
            'posts_per_page' => self::posts_per_page,
            'post_type' => 'person', // 'person' is a post type defined in ucf-people-cpt
        );
        $query = new WP_Query( $query_args );

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
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
        if (!$image_url){
            $image_url = "http://local.nursing.ucf.edu/wp-content/uploads/2018/10/PEO_BR_22.jpg"; // default image location
        }
        $job_title = get_field('person_jobtitle');
        $department = null; // get_field('person_')
        $location = get_field('person_room');
        $location_url = get_field('person_room_url'); // link to a map
        $email = get_field('person_email');
        $phone_array = get_field('person_phone_numbers');
        $phone = $phone_array[0]['number'];

        $div_location = $this->contact_info($location, 'location');
        $div_email = $this->contact_info($email, 'email');
        $div_phone = $this->contact_info($phone, 'phone');

        // ####

        $html_single_profile .= "
        <div class='person'>
            <div class='photo'>
                <a href='{$profile_url}' title='{$full_name}'>
                    <img src='{$image_url}' alt='photo of {$full_name}' />
                </a>
            </div>
            <div class='details'>
                <a href='{$profile_url}' class='full_name'>{$full_name}</a>
                <span class='job_title'>{$job_title}</span>
                <span class='department'>{$department}</span>
                <div class='contact'>
                    {$div_location}
                    {$div_email}
                    {$div_phone}
                </div>
            </div>
        </div>
        ";

        return $html_single_profile;
    }

    public function contact_info($data, $class, $title = null){

        if ($data){
            if (!$title){
                $title = strtoupper($class);
            }

            return "
            <div class='{$class}'>
                <span class='label'>{$title}:</span>
                <span class='data'>{$data}</span>
            </div>
            ";
        } else {
            return ''; // no data
        }
    }


}
new ucf_people_directory_shortcode();

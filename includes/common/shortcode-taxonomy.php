<?php

//------------------------------------------------------------Custom taxonomies
if (!class_exists('ucf_college_shortcode_taxonomy')) {
    class ucf_college_shortcode_taxonomy {
        const taxonomy_slug        = 'ucf_college_shortcode_category';
        const taxonomy_name        = 'UCF College Shortcodes';
        const taxonomy_name_single = 'UCF College Shortcode';


        function __construct() {
            add_action( 'init', array( $this, 'create_taxonomy' ) );

        }

        function create_taxonomy() {
            /**
             * The UCF Shortcodes taxonomy is solely used to dynamically show/hide ACF fields on the page.
             * This allows you to add a shortcode that requires extra fields without having to manually
             * add that page to the ACF conditional statement. Fields will show up on the page as soon
             * as the checkbox is checked for the various shortcode the user wants.
             */
            $labels = array(
                'name'          => _x( self::taxonomy_name, 'taxonomy general name' ),
                'singular_name' => _x( self::taxonomy_name_single, 'taxonomy singular name' ),
                'all_items'     => __( 'All ' . self::taxonomy_name ),
                'edit_item'     => __( 'Edit ' . self::taxonomy_name_single ),
                'update_item'   => __( 'Update ' . self::taxonomy_name_single ),
                'add_new_item'  => __( 'Add New ' . self::taxonomy_name_single ),
                'new_item_name' => __( 'New ' . self::taxonomy_name_single ),
                'menu_name'     => __( self::taxonomy_name )
            );

            if ( ! ( taxonomy_exists( self::taxonomy_slug ) ) ) {
                register_taxonomy(
                    self::taxonomy_slug,
                    array( // @TODO add to all existing post types, and have site setting to (en/dis)able for specific
                           'page',
                           'person',
                           'post'
                    ),
                    array(
                        'hierarchical' => true,
                        'labels'       => $labels,
                        'query_var'    => false, // don't allow url queries for this shortcode
                        'manage_terms' => false,
                        'show_in_rest' => true,
                    )
                );
            }
        }
    }

    new ucf_college_shortcode_taxonomy();
}
<?php

/**
 * Created by PhpStorm.
 * User: stephen
 * Date: 2019-02-01
 * Time: 1:47 PM
 */
class ucf_people_directory_acf_pro_fields {

    const shortcode = 'ucf_people_directory';

    function __construct() {
        add_action( 'acf/init', array( 'ucf_people_directory_acf_pro_fields', 'create_fields' ) );
    }

    static function create_fields() {
	    if( function_exists('acf_register_block') ) {
		    // register a testimonial block
		    acf_register_block(array(
			                       'name'				=> 'ucf_college_people_directory',
			                       'title'				=> __('UCF People Directory'),
			                       'description'		=> __('People directory.'),
			                       'render_callback'	=> array('ucf_people_directory_shortcode','replacement_print'),
			                       'category'			=> 'layout',
			                       'icon'				=> 'screenoptions',
			                       'keywords'			=> array( 'ucf', 'college','people','directory','profile','person' ),
		                       ));
	    }

	    if ( function_exists( 'acf_add_local_field_group' ) ) {
            acf_add_local_field_group(
                array(
                    'key'                   => 'group_5c81351daa8b2',
                    'title'                 => 'People Directory Options',
                    'fields'                => array(
	                    array(
		                    'key' => 'field_5e722eaa23422',
		                    'label' => 'Filtered directory',
		                    'name' => 'filtered',
		                    'type' => 'true_false',
		                    'instructions' => '',
		                    'required' => 0,
		                    'conditional_logic' => 0,
		                    'wrapper' => array(
			                    'width' => '',
			                    'class' => '',
			                    'id' => '',
		                    ),
		                    'message' => 'Show specific categories',
		                    'default_value' => 0,
		                    'ui' => 0,
		                    'ui_on_text' => '',
		                    'ui_off_text' => '',
	                    ),
                        array(
                            'key'               => 'field_5c8136ee0c0f6',
                            'label'             => 'People Directory Groups',
                            'name'              => 'people_groups',
                            'type'              => 'repeater',
                            'instructions'      => '',
                            'required'          => 0,
                            'conditional_logic' => array(
	                            array(
		                            array(
			                            'field' => 'field_5e722eaa23422',
			                            'operator' => '==',
			                            'value' => '1',
		                            ),
	                            ),
                            ),
                            'wrapper'           => array(
                                'width' => '',
                                'class' => '',
                                'id'    => '',
                            ),
                            'collapsed'         => '',
                            'min'               => 1,
                            'max'               => 0,
                            'layout'            => 'table',
                            'button_label'      => 'Add group',
                            'sub_fields'        => array(
                                array(
                                    'key'               => 'field_5c81372a0c0f7',
                                    'label'             => 'Group',
                                    'name'              => 'group',
                                    'type'              => 'taxonomy',
                                    'instructions'      => '',
                                    'required'          => 1,
                                    'conditional_logic' => 0,
                                    'wrapper'           => array(
                                        'width' => '',
                                        'class' => '',
                                        'id'    => '',
                                    ),
                                    'taxonomy'          => 'people_group',
                                    'field_type'        => 'select',
                                    'allow_null'        => 0,
                                    'add_term'          => 0,
                                    'save_terms'        => 0,
                                    'load_terms'        => 0,
                                    'return_format'     => 'object',
                                    'multiple'          => 0,
                                ),
                            ),
                        ),
                    ),
                    'location'              => array(
	                    array(
		                    array(
			                    'param'    => 'block',
			                    'operator' => '==',
			                    'value'    => 'acf/ucf-college-people-directory',
		                    ),
	                    ),
                        array(
                            array(
                                'param'    => 'post_taxonomy',
                                'operator' => '==',
                                'value'    => 'ucf_college_shortcode_category:' . self::shortcode,
                            ),
                        ),
                    ),
                    'menu_order'            => 0,
                    'position'              => 'normal',
                    'style'                 => 'default',
                    'label_placement'       => 'top',
                    'instruction_placement' => 'label',
                    'hide_on_screen'        => '',
                    'active'                => true,
                    'description'           => '',
                ) );

        }
    }
}

new ucf_people_directory_acf_pro_fields();
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

		// sort order override for people posttypes, used in directory sorting
		add_action( 'acf/init', array( 'ucf_people_directory_acf_pro_fields', 'extend_person_fields' ) );

		// add 'limited' checkbox to people group taxonomy
		add_action( 'acf/init', array( 'ucf_people_directory_acf_pro_fields', 'people_group_meta_fields' ) );
	}

	static function create_fields() {
		if ( function_exists( 'acf_register_block' ) ) {
			// register a testimonial block
			acf_register_block(
				array(
					'name'            => 'ucf_college_people_directory',
					'title'           => __( 'UCF People Directory' ),
					'description'     => __( 'People directory.' ),
					'render_callback' => array( 'ucf_people_directory_shortcode', 'replacement_print' ),
					'category'        => 'embed',
					'icon'            => 'id',
					'keywords'        => array(
						'ucf',
						'college',
						'people',
						'directory',
						'profile',
						'person'
					),
				)
			);
		}

		if ( function_exists( 'acf_add_local_field_group' ) ) {
			acf_add_local_field_group(
				array(
					'key'                   => 'group_5c81351daa8b2',
					'title'                 => 'People Directory Options',
					'fields'                => array(
						array(
							'key'               => 'field_5e727e5c02cb6',
							'label'             => 'Search bar',
							'name'              => 'show_search_bar',
							'type'              => 'true_false',
							'instructions'      => '',
							'required'          => 0,
							'conditional_logic' => 0,
							'wrapper'           => array(
								'width' => '',
								'class' => '',
								'id'    => '',
							),
							'message'           => '',
							'default_value'     => 1,
							'ui'                => 1,
							'ui_on_text'        => 'Visible',
							'ui_off_text'       => 'Hidden',
						),
						array(
							'key'               => 'field_5e722eaa23422',
							'label'             => 'Filtered directory',
							'name'              => 'filtered',
							'type'              => 'true_false',
							'instructions'      => '',
							'required'          => 0,
							'conditional_logic' => 0,
							'wrapper'           => array(
								'width' => '',
								'class' => '',
								'id'    => '',
							),
							'message'           => 'Show specific categories',
							'default_value'     => 0,
							'ui'                => 1,
							'ui_on_text'        => 'Filtered',
							'ui_off_text'       => 'Everyone',
						),
						array(
							'key'               => 'field_5c8136ee0c0f6',
							'label'             => 'People Directory Groups',
							'name'              => 'specific_terms',
							'type'              => 'repeater',
							'instructions'      => '',
							'required'          => 0,
							'conditional_logic' => array(
								array(
									array(
										'field'    => 'field_5e722eaa23422',
										'operator' => '==',
										'value'    => '1',
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
						array(
							'key'               => 'field_5e722eaa2347a',
							'label'             => 'Initial view',
							'name'              => 'initially_shown',
							'type'              => 'true_false',
							'instructions'      => '',
							'required'          => 0,
							'conditional_logic' => 0,
							'wrapper'           => array(
								'width' => '',
								'class' => '',
								'id'    => '',
							),
							'message'           => 'Show contact cards on initial view',
							'default_value'     => 0,
							'ui'                => 1,
							'ui_on_text'        => 'Visible',
							'ui_off_text'       => 'Hidden',
						),
						array(
							'key'               => 'field_5e727e7f02cb7',
							'label'             => 'Group filter sidebar',
							'name'              => 'show_group_filter_sidebar',
							'type'              => 'true_false',
							'instructions'      => '',
							'required'          => 0,
							'conditional_logic' => 0,
							'wrapper'           => array(
								'width' => '',
								'class' => '',
								'id'    => '',
							),
							'message'           => '',
							'default_value'     => 1,
							'ui'                => 1,
							'ui_on_text'        => 'Visible',
							'ui_off_text'       => 'Hidden',
						),
						array(
							'key'               => 'field_5e72817c085cc',
							'label'             => 'Profiles per page',
							'name'              => 'profiles_per_page',
							'type'              => 'number',
							'instructions'      => '',
							'required'          => 1,
							'conditional_logic' => 0,
							'wrapper'           => array(
								'width' => '',
								'class' => '',
								'id'    => '',
							),
							'default_value'     => 10,
							'placeholder'       => '',
							'prepend'           => '',
							'append'            => '',
							'min'               => 1,
							'max'               => 100,
							'step'              => 1,
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
				)
			);
		}
	}

	// Adds a group of fields that lets the user override sort order for directories. This lets them place
	// specific people at the top of the list when specific departments are specified.
	static function extend_person_fields() {
		$colleges_theme_acf_id_for_people = 'group_5953a81f683a8'; // defined in Colleges Theme (parent theme) in dev/acf-export.json.
		// unused. I tried altering the existing group to add our own fields, but it ended up overwriting them completely.
		// possibly because those fields are defined in the database (via json import), whereas these are defined in php.
		// https://support.advancedcustomfields.com/forums/topic/updating-field-settings-in-php/

		if ( function_exists( 'acf_add_local_field_group' ) ) {
			acf_add_local_field_group(
				array(
					'key'                   => 'group_5e87861cc6263',
					'title'                 => 'Directory sort order',
					'fields'                => array(
						array(
							'key'               => 'field_5e8779adb6da0',
							'label'             => 'Custom sort order',
							'name'              => 'custom_sort_order',
							'type'              => 'true_false',
							'instructions'      => 'Enable to specify departments where this person should be sorted earlier than others.',
							'required'          => 0,
							'conditional_logic' => 0,
							'wrapper'           => array(
								'width' => '',
								'class' => '',
								'id'    => '',
							),
							'message'           => '',
							'default_value'     => 0,
							'ui'                => 1,
							'ui_on_text'        => '',
							'ui_off_text'       => '',
						),
						array(
							'key'               => 'field_5e87798ab6d9f',
							'label'             => '',
							'name'              => 'departments',
							'type'              => 'repeater',
							'instructions'      => '',
							'required'          => 0,
							'conditional_logic' => array(
								array(
									array(
										'field'    => 'field_5e8779adb6da0',
										'operator' => '==',
										'value'    => '1',
									),
								),
							),
							'wrapper'           => array(
								'width' => '',
								'class' => '',
								'id'    => '',
							),
							'collapsed'         => 'field_5e8779eeb6da1',
							'min'               => 1,
							'max'               => 0,
							'layout'            => 'table',
							'button_label'      => '',
							'sub_fields'        => array(
								array(
									'key'               => 'field_5e8779eeb6da1',
									'label'             => 'People Group',
									'name'              => 'department',
									'type'              => 'taxonomy',
									'instructions'      => 'When a user filters the directory to one of people groups listed here, this person will be appear earlier in the list.',
									'required'          => 0,
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
									'save_terms'        => 1,
									'load_terms'        => 0,
									'return_format'     => 'id',
									'multiple'          => 0,
								),
								array(
									'key'               => 'field_5e877a1bb6da2',
									'label'             => 'Weight',
									'name'              => 'weight',
									'type'              => 'number',
									'instructions'      => 'People with a smaller weight will appear first, followed by people with a larger weight. People without a custom sort order get sorted last (alphabetically).',
									'required'          => 0,
									'conditional_logic' => 0,
									'wrapper'           => array(
										'width' => '',
										'class' => '',
										'id'    => '',
									),
									'default_value'     => 1,
									'placeholder'       => '',
									'prepend'           => '',
									'append'            => '',
									'min'               => 1,
									'max'               => 10,
									'step'              => 1,
								),
							),
						),
					),
					'location'              => array(
						array(
							array(
								'param'    => 'post_type',
								'operator' => '==',
								'value'    => 'person',
							),
						),
					),
					'menu_order'            => 10,
					'position'              => 'normal',
					'style'                 => 'default',
					'label_placement'       => 'top',
					'instruction_placement' => 'label',
					'hide_on_screen'        => '',
					'active'                => true,
					'description'           => 'Custom sort order',
				)
			);
		}
	}


	// Adds a checkbox to the backend taxonomy terms. If checked, that People Group will limit the information shown for any Person who is assigned that group.
	static function people_group_meta_fields() {
		if ( function_exists( 'acf_add_local_field_group' ) ) {
			acf_add_local_field_group(
				array(
					'key'                   => 'group_5f19f92f44f8b',
					'title'                 => 'People Group Taxonomy Meta Fields',
					'fields'                => array(
						array(
							'key'               => 'field_5f19f978b21ac',
							'label'             => 'Limited Info',
							'name'              => 'limited-info',
							'type'              => 'true_false',
							'instructions'      => 'If set, any Person who is assigned to this People Group will have limited fields shown in the editor, and in Directory views they will have limited information shown and have no link to their full profile.',
							'required'          => 0,
							'conditional_logic' => 0,
							'wrapper'           => array(
								'width' => '',
								'class' => '',
								'id'    => '',
							),
							'message'           => '',
							'default_value'     => 0,
							'ui'                => 1,
							'ui_on_text'        => '',
							'ui_off_text'       => '',
						),
						array(
							'key'               => 'field_5f19f978b21ad',
							'label'             => 'External Link',
							'name'              => 'external-link',
							'type'              => 'true_false',
							'instructions'      => 'If checked, this People Group will link to an external directory, and it will prevent a Person from being added to this group.',
							'required'          => 0,
							'conditional_logic' => 0,
							'wrapper'           => array(
								'width' => '',
								'class' => '',
								'id'    => '',
							),
							'message'           => '',
							'default_value'     => 0,
							'ui'                => 1,
							'ui_on_text'        => '',
							'ui_off_text'       => '',
						),
						array(
							'key'               => 'field_5f19f978b21ae',
							'label'             => 'External Link URL',
							'name'              => 'external-link-url',
							'type'              => 'url',
							'instructions'      => 'The url to the external directory.',
							'required'          => 1,
							'conditional_logic' => array(
								array(
									array(
										'field'    => 'field_5f19f978b21ad',
										'operator' => '==',
										'value'    => '1',
									),
								),
							),
							'wrapper'           => array(
								'width' => '',
								'class' => '',
								'id'    => '',
							),
							'message'           => '',
							'default_value'     => '',
						),
					),
					'location'              => array(
						array(
							array(
								'param'    => 'taxonomy',
								'operator' => '==',
								'value'    => 'people_group',
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
				)
			);
		}
	}
}

new ucf_people_directory_acf_pro_fields();
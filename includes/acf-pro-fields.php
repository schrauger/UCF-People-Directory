<?php

/**
 * Created by PhpStorm.
 * User: stephen
 * Date: 2019-02-01
 * Time: 1:47 PM
 */

namespace ucf_people_directory\acf_pro_fields;

include_once 'block-attributes.php';

const shortcode = 'ucf_people_directory';
const acf_key_filtered =        'field_5e722eaa23422';
const acf_key_show_search_bar = 'field_5e727e5c02cb6';
const acf_key_switch_to_main_site = 'field_5e72817c085cd';
add_action( 'acf/init', __NAMESPACE__ . '\\create_fields' );

// sort order override for people posttypes, used in directory sorting.
// also adds specialty search options
add_action( 'acf/init', __NAMESPACE__ . '\\extend_person_fields' );

// single person search options
add_action( 'acf/init', __NAMESPACE__ . '\\single_person_search_fields' );
add_action( 'acf/init', __NAMESPACE__ . '\\single_person_subsite_search_fields' );

// add 'limited' checkbox to people group taxonomy
add_action( 'acf/init', __NAMESPACE__ . '\\people_group_meta_fields' );

// pull in taxonomy from main blog, if the block is requesting that
add_filter( 'acf/fields/taxonomy/query', __NAMESPACE__ . '\\mark_term_query_origination', 10, 3 );
add_filter( 'get_terms', __NAMESPACE__ . '\\people_group_switch_to_blog', 20, 3 );


function create_fields() {
	if ( function_exists( 'acf_register_block' ) ) {
		// register a testimonial block
		acf_register_block(
			array(
				'name'            => 'ucf_college_people_directory',
				'title'           => __( 'UCF People Directory' ),
				'description'     => __( 'People directory.' ),
				'render_callback' => 'ucf_people_directory\\block\\replacement_print',
				'enqueue_assets'  => 'ucf_people_directory\\enqueue_js_css',
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
						'key'               => acf_key_show_search_bar,
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
						'key'               => 'field_60638e2a83d02',
						'label'             => 'Advanced search bar options',
						'name'              => 'advanced_search_bar_options',
						'type'              => 'true_false',
						'instructions'      => '',
						'required'          => 0,
						'conditional_logic' => array(
							array(
								array(
									'field'    => acf_key_show_search_bar,
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
						'default_value'     => 0,
						'ui'                => 0,
						'ui_on_text'        => '',
						'ui_off_text'       => '',
					),
					array(
						'key'               => 'field_60638e0483d00',
						'label'             => 'Search bar options',
						'name'              => 'search_bar_options',
						'type'              => 'group',
						'instructions'      => '',
						'required'          => 0,
						'conditional_logic' => array(
							array(
								array(
									'field'    => acf_key_show_search_bar,
									'operator' => '==',
									'value'    => '1',
								),
								array(
									'field'    => 'field_60638e2a83d02',
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
						'layout'            => 'block',
						'sub_fields'        => array(
							array(
								'key'               => 'field_60638e4f83d03',
								'label'             => 'Search bar type',
								'name'              => 'search_bar_type',
								'type'              => 'button_group',
								'instructions'      => 'Which fields to search',
								'required'          => 0,
								'conditional_logic' => array(
									array(
										array(
											'field'    => acf_key_show_search_bar,
											'operator' => '==',
											'value'    => '1',
										),
										array(
											'field'    => 'field_60638e2a83d02',
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
								'choices'           => array(
									\ucf_people_directory\block_attributes\ucf_people_directory_block_attributes::SEARCH_STANDARD    => 'Name and Content',
									\ucf_people_directory\block_attributes\ucf_people_directory_block_attributes::SEARCH_SPECIALIZED => 'Name and Specialized Keyword',
								),
								'allow_null'        => 0,
								'default_value'     => 'default',
								'layout'            => 'vertical',
								'return_format'     => 'value',
							),
						),
					),
					( ( get_current_blog_id() !== 1 ) ? array(
						'key'               => acf_key_switch_to_main_site,
						'label'             => 'Profile source',
						'name'              => 'switch_to_main_site',
						'type'              => 'true_false',
						'instructions'      => '',
						'required'          => 0,
						'conditional_logic' => 0,
						'wrapper'           => array(
							'width' => '',
							'class' => '',
							'id'    => '',
						),
						'message'           => 'Toggle between profiles from the current subsite or from the primary COM site.',
						'default_value'     => 0,
						'ui'                => 1,
						'ui_on_text'        => 'COM',
						'ui_off_text'       => 'Subsite',
					) : null ),
                    array(
                        'key' => acf_key_filtered,
                        'label' => 'Filtered directory',
                        'name' => 'filtered',
                        'aria-label' => '',
                        'type' => 'button_group',
                        'instructions' => 'Show specific categories',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => array(
                            'width' => '',
                            'class' => '',
                            'id' => '',
                        ),
                        'choices' => array(
                            \ucf_people_directory\block_attributes\ucf_people_directory_block_attributes::FILTERED_NONE => 'Everyone',
                            \ucf_people_directory\block_attributes\ucf_people_directory_block_attributes::FILTERED_WHITELIST => 'Whitelist',
                            \ucf_people_directory\block_attributes\ucf_people_directory_block_attributes::FILTERED_BLACKLIST => 'Blacklist',
                        ),
                        'default_value' => 0,
                        'return_format' => 'value',
                        'allow_null' => 0,
                        'layout' => 'horizontal',
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
									'field'    => acf_key_filtered,
									'operator' => '==',
									'value'    => \ucf_people_directory\block_attributes\ucf_people_directory_block_attributes::FILTERED_WHITELIST,
								),
								array(
									'field'    => acf_key_switch_to_main_site,
									'operator' => '!=',
									'value'    => '1',
								),
							),
                            array(
                                array(
                                    'field'    => acf_key_filtered,
                                    'operator' => '==',
                                    'value'    => \ucf_people_directory\block_attributes\ucf_people_directory_block_attributes::FILTERED_BLACKLIST,
                                ),
                                array(
                                    'field'    => acf_key_switch_to_main_site,
                                    'operator' => '!=',
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
					( ( get_current_blog_id() !== 1 ) ? array(
						'key'               => 'field_5c8136ee0c0f7',
						'label'             => 'People Directory Groups',
						'name'              => 'specific_terms_main_site',
						'type'              => 'repeater',
						'instructions'      => '',
						'required'          => 0,
						'conditional_logic' => array(
							array(
								array(
									'field'    => acf_key_filtered,
									'operator' => '==',
									'value'    => \ucf_people_directory\block_attributes\ucf_people_directory_block_attributes::FILTERED_WHITELIST,
								),
								array(
									'field'    => acf_key_switch_to_main_site,
									'operator' => '==',
									'value'    => '1',
								),
							),
                            array(
                                array(
                                    'field'    => acf_key_filtered,
                                    'operator' => '==',
                                    'value'    => \ucf_people_directory\block_attributes\ucf_people_directory_block_attributes::FILTERED_BLACKLIST,
                                ),
                                array(
                                    'field'    => acf_key_switch_to_main_site,
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
							// replicate the previous field, and show it when the user wants COM profiles instead of current site profiles.
							// the key is the only thing that changes, since that's what is checked during the get_term filter
							// to see if we should switch to blog. we can't access other acf fields during the filter, so
							// we use the fact that the user is viewing this slightly different field to determine when to switch to blog 1.
							array(
								'key'               => 'field_5c81372a0c0f8',
								// same field as previous, but different key and only shown when
								'label'             => 'Group',
								'name'              => 'group_main_site',
								// I guess we'll use a different field name as well
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
					) : null ),
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
							'value'    => 'ucf_college_shortcode_category:' . shortcode,
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
// Also adds a group of fields for defining specialty keywords for use with advanced directory searching.
function extend_person_fields() {
	//$colleges_theme_acf_id_for_people = 'group_5953a81f683a8'; // defined in Colleges Theme (parent theme) in dev/acf-export.json.
	// unused. I tried altering the existing group to add our own fields, but it ended up overwriting them completely.
	// possibly because those fields are defined in the database (via json import), whereas these are defined in php.
	// https://support.advancedcustomfields.com/forums/topic/updating-field-settings-in-php/
	// Instead, this just adds a separate grouping of fields on the page.

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
								'save_terms'        => 0,
								// don't set this to true. otherwise, the person will have all categories removed except ones where they have manual sort orders applied. instead, leave it to the editor to add them to a people group even if it's defined here.
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
		acf_add_local_field_group(
			array(
				'key'                   => 'group_605b9d94742b4',
				'title'                 => 'Person fields - Specialty Search',
				'fields'                => array(
					array(
						'key'               => 'field_6064e6a85ffc0',
						'label'             => 'Enable Specialty Search Keywords',
						'name'              => 'enable_specialty_search_keywords',
						'type'              => 'true_false',
						'instructions'      => 'Enabling this option will let you enter text for this profile for use in advanced directory searching. It will be used within directory listings that have enabled advanced search options to specifically search on this field.',
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
						'ui_on_text'        => 'Enabled',
						'ui_off_text'       => 'Disabled',
					),
					array(
						'key'               => 'field_605b9d9bfc498',
						'label'             => 'Specialties',
						'name'              => 'specialties',
						'type'              => 'textarea',
						'instructions'      => '',
						'required'          => 0,
						'conditional_logic' => array(
							array(
								array(
									'field'    => 'field_6064e6a85ffc0',
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
						'default_value'     => '',
						'placeholder'       => '',
						'maxlength'         => '',
						'rows'              => '',
						'new_lines'         => '',
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

function single_person_search_fields() {
	if ( function_exists( 'acf_add_local_field_group' ) ) {

		acf_add_local_field_group(
			array(
				'key'                   => 'group_60956e240d6b0',
				'title'                 => 'Single Person Search',
				'fields'                => array(
					array(
						'key'               => 'field_609572124542e',
						'label'             => 'Enable search bar',
						'name'              => 'ucf_people_directory_options_enable_search',
						'type'              => 'true_false',
						'instructions'      => 'Enables the search bar on top of every profile page.',
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
						'ui_on_text'        => 'Enabled',
						'ui_off_text'       => 'Disabled',
					),
					array(
						'key'               => 'field_60956e3b77080',
						'label'             => 'Target Page',
						'name'              => 'ucf_people_directory_options_target_page',
						'type'              => 'page_link',
						'instructions'      => 'This page should have a directory block, and all searches coming from a single person\'s profile will redirect to this page and search within the directory defined on this page.',
						'required'          => 1,
						'conditional_logic' => array(
							array(
								array(
									'field'    => 'field_609572124542e',
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
						'post_type'         => '',
						'taxonomy'          => '',
						'allow_null'        => 0,
						'allow_archives'    => 1,
						'multiple'          => 0,
					),
				),
				'location'              => array(
					array(
						array(
							'param'    => 'options_page',
							'operator' => '==',
							'value'    => 'ucf-people-directory-general-settings',
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

function single_person_subsite_search_fields() {
	if ( function_exists( 'acf_add_local_field_group' ) ) {

		acf_add_local_field_group(
			array(
				'key'                   => 'group_60956e240d6b1',
				'title'                 => 'Single Person Search',
				'fields'                => array(
					array(
						'key'               => 'field_609572124542f',
						'label'             => 'Enable search bar',
						'name'              => 'ucf_people_directory_options_enable_search',
						'type'              => 'true_false',
						'instructions'      => 'Enables the search bar on top of every profile page.',
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
						'ui_on_text'        => 'Enabled',
						'ui_off_text'       => 'Disabled',
					),
					array(
						'key'               => 'field_60956e3b77082',
						'label'             => 'Main or Subsite Page',
						'name'              => 'ucf_people_directory_options_main_sub_switch',
						'type'              => 'true_false',
						'instructions'      => 'This page should have a directory block, and all searches coming from a single person\'s profile will redirect to this page and search within the directory defined on this page.',
						'required'          => 0,
						'conditional_logic' => array(
							array(
								array(
									'field'    => 'field_609572124542f',
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
						'default_value'     => 0,
						'ui'                => 1,
						'ui_on_text'        => 'This Subsite',
						'ui_off_text'       => 'COM Site',
					),
					array(
						'key'               => 'field_60956e3b77081',
						'label'             => 'Target Page',
						'name'              => 'ucf_people_directory_options_target_page',
						'type'              => 'page_link',
						'instructions'      => 'This page should have a directory block, and all searches coming from a single person\'s profile will redirect to this page and search within the directory defined on this page.',
						'required'          => 1,
						'conditional_logic' => array(
							array(
								array(
									'field'    => 'field_609572124542f',
									'operator' => '==',
									'value'    => '1',
								),
								array(
									'field'    => 'field_60956e3b77082',
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
						'post_type'         => '',
						'taxonomy'          => '',
						'allow_null'        => 0,
						'allow_archives'    => 1,
						'multiple'          => 0,
					),
				),
				'location'              => array(
					array(
						array(
							'param'    => 'options_page',
							'operator' => '==',
							'value'    => 'ucf-people-directory-subsite-general-settings',
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
		acf_add_local_field_group(
			array(
				'key'                   => 'group_6123e1b3a9bf7',
				'title'                 => 'People Custom Post Type Options',
				'fields'                => array(
					array(
						'key'               => 'field_6123e21fd1958',
						'label'             => 'Person Rewrite Slug',
						'name'              => 'person_rewrite_slug',
						'type'              => 'text',
						'instructions'      => 'Override the url path for the \'People\' custom post type. Default is "site.com/person". Setting a value here will change it to "site.com/yourvalue"',
						'required'          => 0,
						'conditional_logic' => 0,
						'wrapper'           => array(
							'width' => '',
							'class' => '',
							'id'    => '',
						),
						'default_value'     => '',
						'placeholder'       => '',
						'prepend'           => '',
						'append'            => '',
						'maxlength'         => '',
					),
				),
				'location'              => array(
					array(
						array(
							'param'    => 'options_page',
							'operator' => '==',
							'value'    => 'ucf-people-directory-general-settings',
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

// Adds a checkbox to the backend taxonomy terms. If checked, that People Group will limit the information shown for any Person who is assigned that group.
function people_group_meta_fields() {
	if ( function_exists( 'acf_add_local_field_group' ) ) {
		$fields_array = array();


		$fields_array[] = array(
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
		);

		// Only show the toggle for external links to admins or others with the manage_options capability.
		// If set, this toggle will hide a term from non admins on the editor side, as well as overwrite
		// its listing in the directory with an external link (ie to point to an offsite directory).
		if ( current_user_can( 'manage_options' ) ) {
			// make sure to match this capability with the one defined in ucf-people-directory.php

			$fields_array[] = array(
				'key'               => 'field_5f19f978b21ad',
				'label'             => 'External Link',
				'name'              => 'external-link',
				'type'              => 'true_false',
				'instructions'      => 'If checked, this People Group will link to an external directory, and it will prevent a Person from being added to this group.
							                        <em>Warning - any term marked with this will become invisible to non-admin users, except during frontend directory viewing (for printing out the link).</em>',
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
			);
			$fields_array[] = array(
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
			);
		}

		acf_add_local_field_group(
			array(
				'key'                   => 'group_5f19f92f44f8b',
				'title'                 => 'People Group Taxonomy Meta Fields',
				'fields'                => $fields_array,
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

/**
 * Adds a flag to the $args so that the subsequent get_terms call by wordpress can be detected and modified.
 * This function checks to see if the user is trying to view people groups for the main site while on a subsite.
 * If so, it adds a flag to the arguments, and another function detects that and modifies the term request.
 * This is primarily for the editor view when modifying options, since the actual directory output
 * is able to check the other acf fields values and switch to blogs as needed.
 *
 * @param $args
 * @param $field
 * @param $post_id
 *
 * @return mixed
 */
function mark_term_query_origination( $args, $field, $post_id ) {
	if ( $field[ 'key' ] == 'field_5c81372a0c0f8' || $field[ 'name' ] == 'group_main_site' ) {
		$args[ 'switch_to_blog' ] = true;
	}

	return $args;
}

/**
 * Checks to see if the get_term request should be switched to the primary blog, based on a previous filter
 * that marks a special flag. If so, overwrite the terms passed in with a new query after switching to blog 1.
 *
 * @param $terms
 * @param $taxonomies
 * @param $args
 *
 * @return int|WP_Error|WP_Term[]
 */
function people_group_switch_to_blog( $terms, $taxonomies, $args ) {
	if ( isset( $args[ 'switch_to_blog' ] ) ) {

		// remove this filter, or we'd end up with an infinite recursion when this get_terms filter runs get_terms.
		remove_action( 'get_terms', __NAMESPACE__ . '\\people_group_switch_to_blog', 20 );

		switch_to_blog( 1 );
		$terms = get_terms( $args );
		restore_current_blog();

		add_filter( 'get_terms', __NAMESPACE__ . '\\people_group_switch_to_blog', 20, 3 );
	}

	return $terms;
}

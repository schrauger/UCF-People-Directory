<?php

namespace ucf_people_directory\block;

include_once 'block-attributes.php';

use WP_Query;
use WP_Term_Query;
use ucf_people_directory\block_attributes\ucf_people_directory_block_attributes;

const posts_per_page        = '10'; // number of profiles to list per page when paginating
const taxonomy_categories   = ''; // slug for the 'categories' taxonomy

const taxonomy_name                  = 'people_group';
const acf_filtered_choice            = 'filtered';
const acf_filter_term_name           = 'specific_terms';
const acf_filter_term_name_main_site = 'specific_terms_main_site';
const GET_param_group                = 'group_search'; // group or category person is in
const GET_param_keyword              = 'search'; // restrict to profiles matching the user text
const GET_param_search_type          = 'search_type'; // type of search being run (name/bio OR name/specialty)

const acf_sort_key = 'person_orderby_name';

const transient_cache_buster_name = 'ucf-pd-cache-buster'; // the transient stored in the database with this name is simply a nonsenical value that is altered anytime a person is edited or added.

/**
 * Tells wordpress to listen for the 'people_group' parameter in the url. Used to filter down to specific profiles.
 *
 * @param $vars
 *
 * @return array
 */
function add_query_vars_filter( $vars ) {
	$vars[] = GET_param_group;
	$vars[] = GET_param_keyword; // person name, from user submitted search text
	$vars[] = GET_param_search_type; // person name, from user submitted search text

	return $vars;
}

/**
 * Adds a filter with a priority higher/later than the one added in ucf-people-directory.php (which hides certain terms
 * for editors). This filter overrides the previous one and unhides categories, so that they show up in the directory.
 * NOTE: Not actually used, since I figured out how to apply the initial hiding filter to just backend pages. But this
 * function can be used elsewhere or in the future if the hiding filter needs to be bypassed for some reason.
 *
 * @param $args
 * @param $taxonomies
 *
 * @return mixed
 */
function unhide_categories_terms( $args, $taxonomies ) {
	if ( count( $taxonomies ) != 1 || ! in_array( taxonomy_name, $taxonomies ) ) {
		return ( $args );
	}

	$args[ 'meta_query' ] = array(
		'relation' => 'OR',
		array(
			'key'     => 'external-link',
			'compare' => 'EXISTS',
		),
		array(
			'key'     => 'external-link',
			'compare' => 'NOT EXISTS',
		),
	);

	return ( $args );
}

/**
 * Returns the replacement html that WordPress uses in place of the block
 *
 * @param null $attrs
 *
 * @return mixed
 */
function replacement( $attrs = null ) {

	$obj_block_attributes = new \ucf_people_directory\block_attributes\ucf_people_directory_block_attributes();

	if ( $obj_block_attributes->switch_to_main_site ) {
		ucf_switch_site(1);
	}

	$directory_div_classes_array = [
	    'ucf-people-directory'
	];

	if ( ! $obj_block_attributes->show_contacts ) {
        $directory_div_classes_array[] = 'no-card-view';
	}

	// print out search bar
	$search_bar_html = "";
	if ( $obj_block_attributes->show_search_bar ) {
		$search_bar_html = search_bar_html( $obj_block_attributes );
	} else {
        $directory_div_classes_array[] = 'no-search-bar';
    }

	// print out subcategories unless block defines a specific category
    $people_groups_html = "";
	if ( $obj_block_attributes->show_group_filter_sidebar ) {
        $people_groups_html = people_groups_html( $obj_block_attributes );
	} else {
        $directory_div_classes_array[] = 'no-filter-sidebar';
    }


    $profiles_html = "";
	$wp_query           = null;
	$wp_query_max_pages = null;

	if ( $obj_block_attributes->show_contacts ) { // user has searched or selected a group, or the editor is showing contacts on initial/unfiltered view. show the contacts.

		$transient_data_compressed = get_transient( $obj_block_attributes->transient_name_cards );
		if ( $transient_data_compressed ) {
			$transient_data        = gzuncompress( $transient_data_compressed );
            $profiles_html         = $transient_data;
			$wp_query_max_pages    = get_transient( $obj_block_attributes->transient_name_wp_query_max_pages );
		} else {
			$wp_query = query_profiles( $obj_block_attributes );
			// print out profiles
			$fresh_data            = profiles_html( $wp_query, $obj_block_attributes );
            $profiles_html         = $fresh_data;
			$wp_query_max_pages    = $wp_query->max_num_pages;

			$seconds_per_week = 60 * 60 * 24 * 7;
			set_transient( $obj_block_attributes->transient_name_cards, gzcompress( $fresh_data ), $seconds_per_week * 5 ); // 5 WEEK expiration. will also expire when any person is added/updated
			set_transient( $obj_block_attributes->transient_name_wp_query_max_pages, $wp_query_max_pages, $seconds_per_week * 5 );
			wp_reset_postdata();
		}

		wp_reset_postdata();
	}

	// print out pagination, if we're showing contacts
    $pagination_html = "";
	if ( $obj_block_attributes->show_contacts ) {
        $pagination_html = pagination_html( $wp_query_max_pages, $obj_block_attributes );
	}

	if ( $obj_block_attributes->switch_to_main_site ) {
		ucf_switch_site();
	}

    // Put it all together
    $directory_div_classes = implode(" ", $directory_div_classes_array);
    $obj_block_attributes->replacement_data = "
	    <div class='${directory_div_classes}'>
	        ${search_bar_html}
	        ${people_groups_html}
	        ${profiles_html}
	        ${pagination_html}
	    </div>
	";

	return $obj_block_attributes->replacement_data;
}

function replacement_print() {
	echo replacement();
}

// ############ Search Bar Start

/**
 * Return a string of HTML for the search input form
 *
 * @param $block_attributes ucf_people_directory_block_attributes
 *
 * @return string
 */
function search_bar_html(ucf_people_directory_block_attributes $block_attributes ) {
	$html_search_bar         = '';
	$keyword_search          = GET_param_keyword;
	$search_type             = GET_param_search_type;
	$current_category_slug   = ucfirst( get_query_var( GET_param_group ) );
	$current_category_wp_obj = "";
	if ( $current_category_slug ) {
		$current_category_wp_obj = get_term_by( 'slug', $current_category_slug, taxonomy_name );
	}
	$current_page_url = $block_attributes->canonical_url;
	$html_search_bar  .= "
        <div class='searchbar'>
            <form id='searchform' action='{$current_page_url}' method='get'>
                <input 
                    class='searchbar' 
                    type='text' 
                    name='{$keyword_search}' 
                    placeholder='{$block_attributes->get_search_bar_placeholder_text()}'
                    onfocus='this.placeholder = \"\" '
                    onblur='this.placeholder = \"{$block_attributes->get_search_bar_placeholder_text()}\"'
                    value='{$block_attributes->search_content}'
                />
                <input
                	class='hidden'
                	type='hidden'
                	name='{$search_type}'
                	value='{$block_attributes->search_bar_type}'
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
	if ( $current_category_wp_obj ) {
		$html_search_bar .= "
        <div class='current-section-notification'><small><i class=\"fa fa-user-circle-o icongrey\"></i> Currently Viewing: <strong>{$current_category_wp_obj->name}</strong></small></div>
        ";
	}

	return $html_search_bar;
}

// ############ Search Bar End

// ############ Profile Output Start

/**
 * Return a string of HTML with all matching profiles. If a single category is specified, weighted profiles appear
 * first.
 *
 * @param $block_attributes  ucf_people_directory_block_attributes
 *
 * @return WP_Query
 */
function query_profiles(ucf_people_directory_block_attributes $block_attributes ) {

	// ## Query 1 - Run if viewing a single category
	if ( $block_attributes->weighted_category_id ) {
		// user asked for a specific category, or the editor is showing one single department. we now need to look for weighted people.
		$weighted_people = profiles_weighted_id_list( $block_attributes ); // Query 1
	} else {
		// user has not specified a category. weights don't come into effect, since we don't weight multi category views.
		$weighted_people = [];
	}

	$query_args = array(
		'paged'          => $block_attributes->paged,
		'posts_per_page' => $block_attributes->posts_per_page,
		'post_type'      => 'person', // 'person' is a post type defined in ucf-people-cpt
		'post_status'    => 'publish',
		/*'meta_query'     => array( // slow, but works for people that lack the sort key. profile migrator should have fixed that bug, though.
			'relation' => 'OR',
			// need to have this meta query in order to allow people that lack this meta key to still be included in results
			array(
				'key'     => acf_sort_key,
				'compare' => 'EXISTS'
			),
			array(
				'key'     => acf_sort_key,
				'compare' => 'NOT EXISTS'
			)
		),*/
		'orderby'        => array(
			'meta_value' => 'ASC',
			'title'      => 'ASC',
			// fallback to title sort (first name, but oh well) if the sort field is missing.
		),
		'meta_key'       => acf_sort_key,
		'switch_to_blog' => get_field( 'switch_to_main_site', get_the_ID() )
		//'order'          => 'ASC',
	);
	if ( $block_attributes->search_content ) {
		switch ( $block_attributes->search_bar_type ) {
			case $block_attributes::SEARCH_STANDARD:
				$query_args[ 's' ] = $block_attributes->search_content;
				break;
			case $block_attributes::SEARCH_SPECIALIZED:
				$query_args[ 'meta_query' ] = array(
					'key'     => 'specialties',
					'value'   => $block_attributes->search_content,
					'compare' => 'LIKE'
				);

				$query_args[ 'search_prod_title' ] = $block_attributes->search_content;
				add_filter( 'posts_where', 'ucf_people_directory\\block\\title_filter_OR', 10, 2 );
				break;
			default:
				$query_args[ 's' ] = $block_attributes->search_content;
				break;
		}
	}

	// ## Query 2 - Run if single category, and we found weighted people for that category.
	if ( sizeof( $weighted_people ) > 0 ) {
		// weighted people found. run another query to get EVERY person to create an array of ids.
		$all_people              = profiles_id_list( $block_attributes ); // Query 2
		$correctly_sorted_people = array_merge( $weighted_people, $all_people );
		$correctly_sorted_people = array_unique( $correctly_sorted_people );
		// now only select those profiles, and in the order specified
		$query_args[ 'post__in' ] = $correctly_sorted_people;
		$query_args[ 'orderby' ]  = 'post__in';
	}

	// if any group specified, filter to those groups. otherwise, show all.
    if ($block_attributes->people_group_slug) {
        // user has filtered to a specific allowed people group slug
        $people_groups = $block_attributes->people_group_slug;
        $people_groups_is_user_selected = true;
    } else {
        // user has not filtered to a people group. use editor-defined default groups, if editor has set that.
        $people_groups = $block_attributes->editor_people_groups;
        $people_groups_is_user_selected = false;
    }
//	$people_groups = ( $block_attributes->people_group_slug ? $block_attributes->people_group_slug : $block_attributes->editor_people_groups );
//    var_dump($block_attributes);
//    var_dump($block_attributes->people_group_slug); // @TODO this is null for some reason, when using group search parameter. this seems to be broken with the new blacklist. check logic.
//    var_dump($block_attributes->editor_people_groups);
//    var_dump($block_attributes->filtered);

//    { // this actually gets the correct category the user searched for. @TODO use this somehow.
//        $current_category_slug = ucfirst(get_query_var(GET_param_group));
//        $current_category_wp_obj = "";
//        if ($current_category_slug) {
//            $current_category_wp_obj = get_term_by('slug', $current_category_slug, taxonomy_name);
//        }
////        var_dump($current_category_wp_obj);
//    }

    if ( $people_groups ) {
        if ($people_groups_is_user_selected) {
            $query_args['tax_query'] = array(
                array(
                    'taxonomy' => taxonomy_name,
                    'field' => 'slug',
                    'terms' => $people_groups,
                    'include_children' => true,
                    'operator' => 'IN'
                )
            );
            if ($block_attributes->editor_people_groups && $block_attributes->filtered == \ucf_people_directory\block_attributes\ucf_people_directory_block_attributes::FILTERED_BLACKLIST){
                // if the blacklist is active, and the user has specified a category, we still need to filter out any profiles from that category that are also
                // in the blacklist. ex if Enterprise is selected by the user, and Dean is blacklisted, we need to still use the blacklist to remove
                // the Dean profiles from the results.
                $query_args['tax_query']['relation'] = "AND";
                $query_args['tax_query'][] = array(
                    'taxonomy' => taxonomy_name,
                    'field' => 'slug',
                    'terms' => $block_attributes->editor_people_groups,
                    'include_children' => false,
                    'operator' => 'NOT IN'
                );
            }
        } else {
            if ($block_attributes->filtered == \ucf_people_directory\block_attributes\ucf_people_directory_block_attributes::FILTERED_WHITELIST) {
                $query_args['tax_query'] = array(
                    array(
                        'taxonomy' => taxonomy_name,
                        'field' => 'slug',
                        'terms' => $people_groups,
                        'include_children' => true,
                        'operator' => 'IN'
                    )
                );
            } elseif ($block_attributes->filtered == \ucf_people_directory\block_attributes\ucf_people_directory_block_attributes::FILTERED_BLACKLIST) {
                $query_args['tax_query'] = array(
                    array(
                        'taxonomy' => taxonomy_name,
                        'field' => 'slug',
                        'terms' => $people_groups,
                        'include_children' => false,
                        'operator' => 'NOT IN'
                    )
                );
            }

        }
	}

	// ## Query 3 - Always run. Optionally add results from previous two queries if they both ran.

	// Now we have all profiles, with the correct weighted ones at the beginning.
	// Finally, do a WP_QUERY, passing in our exact list of profiles, which will
	// honor the sort we specify.

	// removing this for now. causes query to run more slowly, and initial bug with missing field was fixed in profile migrator
	//add_filter( 'posts_orderby', 'ucf_people_directory\\block\\override_sql_order' );

	$return_query = new WP_Query( $query_args ); // Query 3

	remove_filter( 'posts_where', __NAMESPACE__ . '\\title_filter_OR', 10, 2 ); // remove custom filter (if not set, it doesn't matter)


	// removing this for now. causes query to run more slowly, and initial bug with missing field was fixed in profile migrator
	//remove_filter( 'posts_orderby', 'ucf_people_directory\\block\\override_sql_order' );

	return $return_query;
}

/**
 * For specialties searches, it uses an ACF repeater field. Built-in WordPress meta queries
 * can't search all these fields, so we modify the WHERE clause of sql queries, look for our
 * dollar sign string (just a random character) and replace it with a percent sign.
 * This lets wordpress search for any meta key that starts with our repeater field name,
 * which is how acf stores the subfields of repeater fields.
 *
 * @param $where
 *
 * @return mixed
 */
function override_sql_where_for_specialty_repeater_field( $where ) {
	$where = str_replace( "meta_key = 'specialties_array_$", "meta_key LIKE 'specialties_array_%", $where );

	return $where;
}

/**
 * WordPress doesn't let you search by JUST the title, so this function lets you search just that field and not the
 * content.
 *
 * @param $where
 * @param $wp_query
 *
 * @return string
 */
function title_filter_OR( $where, &$wp_query ) {
	global $wpdb;
	if ( $search_term = $wp_query->get( 'search_prod_title' ) ) {
		$safe_search = esc_sql( like_escape( $search_term ) );
		$where       .= "
			OR (
				{$wpdb->posts}.post_title LIKE '%{$safe_search}%'
				AND
				{$wpdb->posts}.post_type='person'
				AND
				{$wpdb->posts}.post_status='publish'
			)";
		// make sure to restrict to person and published, or else it will return any post with that title
	}

	return $where;
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
function override_sql_order( $orderby ) {
	global $wpdb;

	$sort_key       = acf_sort_key;
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
 * @param \ucf_people_directory\block_attributes\ucf_people_directory_block_attributes $block_attributes
 *
 * @return array
 */
function profiles_weighted_id_list( $block_attributes ) {
	// first, find all profiles that have a 'head of department' or similar tag for the currently filtered department.
	// sort by their weight. smaller numbers first.

    $tax_query_array = array(
        array(
            'taxonomy'         => taxonomy_name,
            'field'            => 'slug',
            'terms'            => $block_attributes->weighted_category_slug,
            'include_children' => true,
            'operator'         => '='
        )
    );
    if ($block_attributes->filtered == \ucf_people_directory\block_attributes\ucf_people_directory_block_attributes::FILTERED_BLACKLIST) {
        // we need to explicitly filter out any people (whether weighted or not) from the list of blacklisted categories.
        if ($block_attributes->editor_people_groups) {
            $tax_query_array['relation'] = "AND";
            $tax_query_array[] = array(
                $query_args['tax_query'] = array(
                    array(
                        'taxonomy' => taxonomy_name,
                        'field' => 'slug',
                        'terms' => $block_attributes->editor_people_groups,
                        'include_children' => true,
                        'operator' => 'NOT IN'
                    )
                )
            );
        }
    }

	$query_args = array(
		// Don't use paged. We want ALL profiles that have a weight.
		// Then this list of ids will be given to another wp_query, which will paginate and filter as needed.
		//'paged'          => $block_attributes->paged,
		'posts_per_page'   => - 1,
		'post_type'        => 'person',
		// 'person' is a post type defined in ucf-people-cpt
		'post_status'      => 'publish',
		's'                => $block_attributes->search_content,
		'orderby'          => 'meta_value',
		'meta_key'         => acf_sort_key,
		// we still order by person name. if weights are equal, names should be sorted.
		'order'            => 'ASC',

		// only query the user-specified people group.
		// if the user hasn't specified one, this function shouldn't be called.
		// we don't weight any profiles on the default view.
		'tax_query'        => $tax_query_array,

		// only get profiles with a weight for the user-specified category.
		// also, check that the custom_sort_order boolean is true. if the editor
		// marks it as false, the old data is still in the database, but we shouldn't sort by it.
		'meta_query'       => array(
			'relation' => 'AND',
			array(
				'key'     => 'departments_$_department',
				'compare' => '=',
				'value'   => $block_attributes->weighted_category_id,
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


	add_filter( 'posts_where', __NAMESPACE__ . '\\acf_meta_subfield_filter' );

	$wp_query = new WP_Query( $query_args );

	remove_filter( 'posts_where', __NAMESPACE__ . '\\acf_meta_subfield_filter' );


	// next, query for all profiles in the category, and use the previous array as the initial sortby field, but also
	// sort by the orderby_name field after.

	$weighted_array     = [];
	$single_category_id = $block_attributes->weighted_category_id;
	if ( $wp_query->have_posts() ) {
		while ( $wp_query->have_posts() ) {
			$wp_query->the_post();
			$person_id = get_the_ID();
			// get the matching weight for our category
			$weighted_array[ $person_id ] = acf_weight_for_category( $single_category_id, $person_id );
		}
	}

	wp_reset_postdata();

	// sort the unweighted array by weights
	asort( $weighted_array );

	return array_keys( $weighted_array ); // keys are the post id. they should now be sorted

}

/**
 * Gets an ordered list of profile ids, unsorted.
 *
 * @param \ucf_people_directory\block_attributes\ucf_people_directory_block_attributes $block_attributes
 *
 * @return array
 */
function profiles_id_list( $block_attributes ) {
	$query_args   = array(
		'posts_per_page' => - 1,
		'post_type'      => 'person', // 'person' is a post type defined in ucf-people-cpt
		'post_status'    => 'publish',
		's'              => $block_attributes->search_content,
		'orderby'        => 'meta_value',
		'meta_key'       => acf_sort_key,
		'order'          => 'ASC',
		'fields'         => 'ids', // only get a list of ids

		// only query the user-specified people group.
		// if the user hasn't specified one, this function shouldn't be called.
		// we don't weight any profiles on the default view.
		'tax_query'      => array(
			array(
				'taxonomy'         => taxonomy_name,
				'field'            => 'slug',
				'terms'            => $block_attributes->weighted_category_slug,
				'include_children' => true,
				'operator'         => '='
			)
		),
	);
	$wp_query     = new WP_Query( $query_args );
	$return_posts = $wp_query->posts;
	wp_reset_postdata();

	return $return_posts;
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
function acf_meta_subfield_filter( $where ) {

    $where = str_replace( "meta_key = 'departments_\$_department", "meta_key LIKE 'departments_%_department", $where );

    // 2025-04-01 ACF Pro changed at some point to storing this value as a serialized string in the database.
    // So we need to check for just the integer value in the string (for entries whose value hasn't been changed since the ACF change)
    // and secondly for the integer surrounded by quotes and other characters, for newer entries that have been serialized.
    $where = preg_replace(
        "/AND mt1\.meta_value = '(\d+)'/",
        "AND (mt1.meta_value = '\$1' OR mt1.meta_value LIKE '%\"\$1\"%')",
        $where
    );
//	$where = str_replace( "mt1.meta_value = '", "meta_key LIKE 'departments_%_department", $where );
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
function acf_weight_for_category( $category_id, $person_id = null ) {
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
function profiles_html( $wp_query, $block_attributes ) {
	$html_list_profiles = "<div class='profiles-list'>";

	if ( $wp_query->have_posts() ) {
		while ( $wp_query->have_posts() ) {
			$wp_query->the_post();
			$html_list_profiles .= profile( $block_attributes );

		}
	} else {
		$html_list_profiles .= "<div class='no-results'>No results found.</div>";
	}

	$html_list_profiles .= "</div>";

	return $html_list_profiles;
}

/**
 * Call this function after the_post is set to a profile (called within a loop).
 * If the profile is in a People Group that has the Limited flag set,
 * then a limited set of info is printed.
 */
/**
 * @param \ucf_people_directory\block_attributes\ucf_people_directory_block_attributes $block_attributes
 *
 * @return string
 */
function profile( $block_attributes ) {
	$id      = get_the_ID();
	$terms   = get_the_terms( $id, taxonomy_name );
	$limited = false;
	if ( is_iterable( $terms ) ) {
		foreach ( $terms as $term ) {
			if ( get_term_meta( $term->term_id, 'limited-info', true ) == 1 ) {
				$limited = true;
			}
		}
	}
	if ( $limited ) {
		return profile_limited( $block_attributes );
	} else {
		return profile_full( $block_attributes );
	}
}

/**
 * Returns html that prints out a single profile, used within the directory listing
 *
 * @param $block_attributes
 *
 * @return string
 */
function profile_full( $block_attributes ) {
	$html_single_profile = ''; //return data
	$current_post_id     = get_the_ID();

	// #### set variables used in html output
	$person_title_prefix = get_field( 'person_title_prefix', $current_post_id );
	$person_title_suffix = get_field( 'person_title_suffix', $current_post_id );
	$full_name           = $person_title_prefix . get_the_title() . $person_title_suffix;
	$profile_url         = get_permalink();
	// Something is broken on Pantheon wordpress in a strange way. The very first person
	// in a directory listing on TEST and LIVE, when viewed in a private window, does not
	// return the thumbnail url. But if you call the thumbnail url a second time, wordpress
	// returns it without issue.
	// So we MUST call get_the_post_thumbnail_url twice for the first person in the directory,
	// or else it may intermittenly have a blank image.
	$image_url           = get_the_post_thumbnail_url( $current_post_id, 'medium' ); // this is 'false' *sometimes* for the very first person listed
	$image_url2           = get_the_post_thumbnail_url( $current_post_id, 'medium' ); // calling a second time gets the url without any issue.
	if ( ! $image_url2 ) {
		$image_url2 = plugin_dir_url( __FILE__ ) . "default.png"; // default image location
	}
	$job_title = get_field( 'person_jobtitle', $current_post_id );

	$department   = null; // get_field('person_') // @TODO this field may be unused on this site
	$location     = get_field( 'person_room', $current_post_id );
	$location_url = get_field( 'person_room_url', $current_post_id );// link to a map
	$email        = get_field( 'person_email', $current_post_id );
	$phone_array  = get_field( 'person_phone_numbers', $current_post_id );

    // if phone_array exists, and is an array, then if array[0] exists, get the [number] subarray item or return '' if that doesn't exist
    $phone        = ($phone_array && is_array($phone_array) ? ($phone_array[ 0 ] ? ($phone_array[ 0 ][ 'number' ] ?? '') : '' ) : '');

	$div_location = contact_info( $location, 'location', $location_url );
	$div_email    = contact_info( $email, 'email', "mailto:{$email}" );
	$div_phone    = contact_info( $phone, 'phone', "tel:{$phone}" );

	$weight = acf_weight_for_category( $block_attributes->weighted_category_id, get_the_ID() );

	if ( $weight ) {
		$weight_class = "weighted weight-{$weight}";
	} else {
		$weight_class = "";
	}

	// ####

	$html_single_profile .= "
        <div class='person person-full {$weight_class}'>
            <div class='photo'>
                <a href='{$profile_url}' title='{$full_name}' style='background-image: url({$image_url2})'>
                    {$full_name}
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

/**
 * Returns html that prints out a single profile, used within the directory listing
 * Limited info (for affiliates and others that have the limited-info flag set)
 *
 * @param $block_attributes
 *
 * @return string
 */
function profile_limited( $block_attributes ) {
	$html_single_profile = ''; //return data
	$current_post_id     = get_the_ID();

	// #### set variables used in html output
	$person_title_prefix = get_field( 'person_title_prefix', $current_post_id );
	$person_title_suffix = get_field( 'person_title_suffix', $current_post_id );
	$full_name           = $person_title_prefix . get_the_title() . $person_title_suffix;

	$weight = acf_weight_for_category( $block_attributes->weighted_category_id, get_the_ID() );

	if ( $weight ) {
		$weight_class = "weighted weight-{$weight}";
	} else {
		$weight_class = "";
	}

	$content = get_the_content();

	// ####

	$html_single_profile .= "
        <div class='person person-limited {$weight_class}'>
            <div class='details'>
                <span class='full_name'>{$full_name}</span>
                <div class='content'>{$content}</div>
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
function contact_info( $data, $class, $url = null, $title = null ) {

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
 * @param $block_attributes \ucf_people_directory\block_attributes\ucf_people_directory_block_attributes
 *
 * @return string
 */
function pagination_html( $wp_query_total_pages, $block_attributes ) {
	$html_pagination = "<div class='pagination'>";

	$html_pagination .= paginate_links(
		array(
			'base'      => str_replace( 999999999, '%#%', esc_url( get_pagenum_link( 999999999 ) ) ),
			'total'     => $wp_query_total_pages,
			'current'   => max( 1, $block_attributes->paged ),
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
 * @param $block_attributes \ucf_people_directory\block_attributes\ucf_people_directory_block_attributes
 *
 * @return string
 */
function people_groups_html( $block_attributes ) {
	$html_people_groups = '';

	// bypass the normal filter that hides people_group terms marked as external.
	// normally, those are invisible to non-admins, but we want them to show up
	// on the directory output.

	// don't need to add a filter to unhide terms; the previous filter to hide the terms only gets added
	// during admin_init pages (ie editor and other backend pages), so on frontend actual pages,
	// that filter hasn't been included.
	// this code has been left in so that it is easy to override the filter if need be in the future.
	//add_filter( 'get_terms_defaults', __NAMESPACE__ .  '\\unhide_categories_terms', 20, 2);
	$people_group_list_html = people_group_list_html( $block_attributes );
	//remove_filter( 'get_terms_defaults', __NAMESPACE__ . '\\unhide_categories_terms', 20);

	$html_people_groups .= "
	<nav class='navbar navbar-side navbar-directory navbar-toggleable-lg navbar-light bg-faded'>
          <h3 class='navbar-brand' id='filter_top' >Filter By</h3>


  <button class='navbar-toggler collapsed' type='button' data-toggle='collapse' data-target='#navbarNav' aria-controls='navbarNav' aria-expanded='false' aria-label='Toggle navigation'>
    <span class='navbar-toggler-text' >Filter By</span>
    <span class='navbar-toggler-icon'></span>
  </button>
  <div class='collapse navbar-collapse' id='navbarNav'>

    <ul class='autonav'>



            <div class='people_groups'>
                <div class='list'>
                    <ul id='{$block_attributes->directory_id}' class='menu'>
                        {$people_group_list_html}
                    </ul>
                </div>
                <a href='/directory/' class='badge badge-complementary'><i class='fa fa-user-circle-o icongrey'></i> View Full Directory</a>
                <a href='#filter_top' class='badge badge-secondary'><i class='fa fa-chevron-circle-up icongrey'></i> Top of Listing</a>

            </div>



     </ul>

  </div>

</nav>
        ";

	return $html_people_groups;
}

/**
 * Sidebar with a list of people groups. Users can select a group to filter down to profiles only in that group.
 *
 * @param $block_attributes \ucf_people_directory\block_attributes\ucf_people_directory_block_attributes
 *
 * @return string
 */
function people_group_list_html( $block_attributes ) {
	$html_people_group_list = '';

	$current_page_url = $block_attributes->canonical_url;

	$current_term = $block_attributes->people_group_slug;

	$get_terms_arguments = array(
		'taxonomy'   => taxonomy_name,
		//'hide_empty' => true, // hide empty groups, even if specified by editor
		'hide_empty' => false,
		// we have to include empty groups due to the possibility of an external link category which will have a 0 count usage. we have to filter out other empty ones later.
	);
	if ( sizeof( $block_attributes->editor_people_groups ) > 0 ) {
        if ( $block_attributes->filtered == \ucf_people_directory\block_attributes\ucf_people_directory_block_attributes::FILTERED_WHITELIST ) {
		    $get_terms_arguments[ 'include' ] = $block_attributes->editor_people_groups_ids; // only include terms specified by the editor
        } elseif ( $block_attributes->filtered == \ucf_people_directory\block_attributes\ucf_people_directory_block_attributes::FILTERED_BLACKLIST ) {
            $get_terms_arguments[ 'exclude' ] = $block_attributes->editor_people_groups_ids; // only include terms specified by the editor
            $get_terms_arguments[ 'parent' ] = 0; // also only show top level groups - we'll get the children later for formatting
        }
	} else {
		// editor wants a global directory (no categories specified)
		$get_terms_arguments[ 'parent' ] = 0; // only show top level groups - we'll get the children later for formatting
	}

	//		if ($block_attributes->switch_to_main_site){
	//			$get_terms_arguments[ 'switch_to_blog'] = true;
	//		}

	$people_groups_terms_top_level = new WP_Term_Query( $get_terms_arguments );

	if ( ! $current_term ) {
		if ( $block_attributes->show_contacts_on_unfiltered ) {
			// only show the 'all groups' link on an unfiltered view if the editor is also showing contacts.
			// otherwise, 'all groups' shouldn't be shown, as it's confusing to have a link to 'all groups' but then not see any contacts when clicked.
			$html_people_group_list .= term_list_entry( "All Groups", $current_page_url, null, 'reset active' );
		} else {
			if ( $block_attributes->search_content ) {
				// show the reset filter link when a user searched by name.
				$html_people_group_list .= term_list_entry( "Reset Filters", $current_page_url, null, 'reset' );
			} else {
				// don't need an else. if no current term, we're on unfiltered. and editor doesn't want cards shown. so don't print out either 'all groups' or 'reset filters'
			}
		}
	} else {
		if ( $block_attributes->show_contacts_on_unfiltered ) {
			$html_people_group_list .= term_list_entry( "All Groups", $current_page_url, null, 'reset' );
		} else {
			$html_people_group_list .= term_list_entry( "Reset Filters", $current_page_url, null, 'reset' );
		}
	}
	foreach ( $people_groups_terms_top_level->terms as $top_level_term ) {
		/* @var $top_level_term  \WP_Term */
        $get_terms_arguments = array(
            'taxonomy'   => taxonomy_name,
            //'hide_empty' => true, // hide empty groups, even if specified by editor
            'hide_empty' => false,
            // we have to include empty groups due to the possibility of an external link category which will have a 0 count usage. we have to filter out other empty ones later.
            'parent'     => $top_level_term->term_id
            // only show top level children for this group
        );
        if ( $block_attributes->filtered == \ucf_people_directory\block_attributes\ucf_people_directory_block_attributes::FILTERED_BLACKLIST ) {
            $get_terms_arguments['exclude'] = $block_attributes->editor_people_groups_ids; // only include terms specified by the editor
        }

		$people_groups_terms_children = get_terms($get_terms_arguments);

		// Option 1 - Have all top level groups show with an accordion
		$html_people_group_list .= term_list_entry_with_children( $top_level_term, $current_page_url, $current_term, $people_groups_terms_children, $block_attributes );

		// Option 2 - Have childless groups be on their own without an accordion, but show accordion for categories with children.
		/*if ( sizeof( $people_groups_terms_children ) > 0 ) {
			// term has children. make the top term an accordion, with the first inner element pointing to the parent filter. rest of elements are children filters
			$html_people_group_list .= term_list_entry_with_children( $top_level_term, $current_page_url, $current_term, $people_groups_terms_children, $block_attributes );


		} else {
			// term is a loner. just make it a filter link, no accordion.
			// list the parent
			if ( $current_term == $top_level_term->slug ) {
				$html_people_group_list .= term_list_entry( $top_level_term->name, $current_page_url, $top_level_term->slug, 'parent active' );
			} else {
				$html_people_group_list .= term_list_entry( $top_level_term->name, $current_page_url, $top_level_term->slug, 'parent' );
			}
		}*/

	}
	wp_reset_postdata();

	return $html_people_group_list;
}

/**
 * Print out a single list item of the people group term.
 * If term has no count (ie unused), it doesn't print out, unless the external flag is set and a url is defined.
 *
 * @param        $title
 * @param        $current_page_url
 * @param        $slug
 * @param string $class
 *
 * @return string
 */
function term_list_entry( $title, $current_page_url, $slug, $class = 'parent' ) {
	$term_url = "";
    $title_text = "";
	if ( $slug ) {
		$term = get_term_by( 'slug', $slug, taxonomy_name );

		$external_set = get_term_meta( $term->term_id, 'external-link', true );
		if ( $external_set ) {
			$term_url = get_term_meta( $term->term_id, 'external-link-url', true );
		}

		$count = $term->count;

		if ( ! $term_url && $count == 0 ) {
			return ""; // don't show any groups that are unused, except for those marked as external with a link defined.
		}
	}

	if ( ! $term_url ) {
		if ( $slug ) {
			$url_filter = "?" . GET_param_group . "={$slug}";
			$title_text = "Display only {$title} profiles";
		} else {
			$url_filter = ""; //if no slug is defined, this is the 'All groups' reset filter
			$title_text = "Display all profiles";
		}
		$term_url = $current_page_url . $url_filter;
	}

	return "
                <li class='menu-item {$class}'>
                    <a 
                        title='{$title_text}' 
                        href='{$term_url}'>
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
 * @param \ucf_people_directory\block_attributes\ucf_people_directory_block_attributes $block_attributes
 *
 * @return string
 */
function term_list_entry_with_children( $top_level_term, $current_page_url, $current_term, $people_groups_terms_children, $block_attributes ) {
	$return_accordion_html              = "";
	$accordion_collapsible_content_html = "";
	$collapsed                          = true;

	// list the parent
	if ( $current_term == $top_level_term->slug ) {
		$accordion_collapsible_content_html .= term_list_entry( "All " . $top_level_term->name, $current_page_url, $top_level_term->slug, 'parent active' );
		$collapsed                          = false;
	} else {
		$accordion_collapsible_content_html .= term_list_entry( "All " . $top_level_term->name, $current_page_url, $top_level_term->slug, 'parent' );
	}

	foreach ( $people_groups_terms_children as $child_term ) {
		// list the children. set class to child so it can be formatted differently
		if ( $current_term == $child_term->slug ) {
			$accordion_collapsible_content_html .= term_list_entry( $child_term->name, $current_page_url, $child_term->slug, 'child active' );
			$collapsed                          = false;
		} else {
			$accordion_collapsible_content_html .= term_list_entry( $child_term->name, $current_page_url, $child_term->slug, 'child' );
		}

	}

	if ( ! $accordion_collapsible_content_html ) {
		return ""; // no inner content. probably due to top level term having 0 count.
	}

	if ( $collapsed ) {
		$collapse_class = "collapse";
		$expanded       = "false";
	} else {
		$collapse_class = "collapse show";
		$expanded       = "true";
	}

	if ( $block_attributes->show_contacts ) {
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
    
	<div id='collapse-{$top_level_term->slug}' class='{$collapse_class}' role='atabpanel' aria-labelledby='heading-{$top_level_term->slug}' data-parent='#{$block_attributes->directory_id}'>
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
 * Switches to a specific blog, or restores current blog, depending on input.
 * Whether switching to or restoring back, it also re-registers the people post type, since
 * other subsites might have different people definitions (such as the url slug), and we need
 * to have the proper definition for permalinks calculations to be done correctly.
 * @param int $id If set to a positive number, switches to that blog. If set to anything else (zero, null, false), it restores the current blog.
 */
function ucf_switch_site($id = 0){
	if (is_int($id) && ($id >= 1)){
		switch_to_blog($id);
	} else {
		restore_current_blog();
	}

	if ( class_exists( 'UCF_People_PostType' ) ) {
		\UCF_People_PostType::register();
	}
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
 * @throws Exception
 */
function cache_bust_on_person_edit() {
	set_transient( transient_cache_buster_name, bin2hex( random_bytes( 8 ) ) );
}



//new ucf_people_directory_block();

//add_action( 'init', 'ucf_people_directory\\block\\add_block' );
add_filter( 'query_vars', 'ucf_people_directory\\block\\add_query_vars_filter' );

// tell wordpress about new url parameters

// when a person is added or updated, change the cache-buster value to force directories to recompute
// note: publish_person will run on both draft->publish and on publish->publish (ie saved updated data)
// https://developer.wordpress.org/reference/functions/wp_transition_post_status/
add_action( 'publish_person', 'ucf_people_directory\\block\\cache_bust_on_person_edit' );
add_action( 'trash_person', 'ucf_people_directory\\block\\cache_bust_on_person_edit' );


//add_filter( 'ucf_college_block_menu_item', 'ucf_people_directory\\block\\add_ckeditor_block' ) );

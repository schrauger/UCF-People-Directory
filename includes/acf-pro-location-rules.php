<?php
/**
 * Created by IntelliJ IDEA.
 * User: stephen
 * Date: 2020-07-23
 * Time: 3:42 PM
 */

class ucf_people_directory_acf_pro_location_rules {
	const taxonomy_name        = 'people_group';

	function __construct() {
		add_action( 'acf/location/rule_types', array( 'ucf_people_directory_acf_pro_location_rules', 'people_group_limited' ) );
		add_action( 'acf/location/rule_values/post_taxonomy_limited', array( 'ucf_people_directory_acf_pro_location_rules', 'people_group_limited_values' ) );
		add_action( 'acf/location/rule_match/post_taxonomy_limited', array( 'ucf_people_directory_acf_pro_location_rules', 'people_group_limited_match' ), 10, 4 );

	}

	/**
	 * Adds a new rule choice for ACF to match if the current post has a People Group that is set to Limited.
	 * This is used to hide the people fields for limited profiles, who only get to set a name and a Content.
	 * @param $choices
	 *
	 * @return mixed
	 */
	function people_group_limited($choices){
		if (!isset($choices['Post']['post_taxonomy_limited'])) {
			$choices['Post']['post_taxonomy_limited'] = 'Post Taxonomy Limited';
		}
		return $choices;
	}

	/**
	 * Only one value, Limited. In the future, if more parameters are added to the taxonomy backend, you could add it here.
	 * @param $choices
	 *
	 * @return mixed
	 */
	function people_group_limited_values($choices) {
		// copied from acf rules values for post_category
		$choices["limited-info"] = "Limited Info"; // array key MUST match the term_meta key
		return $choices;
	}

	/**
	 * On page load (in the editor), it checks the current taxonomy terms for the post. If any of those
	 * terms is marked as Limited in the backend, then this post is a limited person, and the rule returns true.
	 * It also checks on AJAX calls when the editor modifies the People Groups, so the fields should show/hide
	 * in real time, rather than just updating when the post is saved.
	 * @param $match
	 * @param $rule
	 * @param $options
	 * @param $field_group
	 *
	 * @return bool
	 */
	function people_group_limited_match($match, $rule, $options, $field_group) {
		$id = get_the_ID();
		$term_ids = $options["post_terms"][self::taxonomy_name];

		$rule_parameter_match = false;

		// AJAX call
		if (is_iterable($term_ids)) {
			foreach ($term_ids as $term_id){
				if (get_term_meta($term_id, $rule['value'], true) == 1){
					$rule_parameter_match = true;
					break;
				}
			}
		} else {
			// INITIAL page load
			// no people group array returned, which means this is the initial page load. get terms from database
			$terms = get_the_terms($id, self::taxonomy_name);
			if (is_iterable($terms)) {
				foreach ($terms as $term){
					if (get_term_meta($term->term_id, $rule['value'], true) == 1){
						$rule_parameter_match = true;
						break;
					}
				}
			}
		}


		if ($rule['operator'] == "=="){
			$match = $rule_parameter_match;
		} elseif ($rule['operator'] == "!=") {
			$match = !$rule_parameter_match;
		}
		return $match;
	}
}

new ucf_people_directory_acf_pro_location_rules();
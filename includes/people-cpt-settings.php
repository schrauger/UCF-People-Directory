<?php
/**
 * Created by IntelliJ IDEA.
 * User: stephen
 * Date: 2021-08-23
 * Time: 2:04 PM
 */

namespace ucf_people_directory\people_cpt_settings;


// Lets the user override certain parts of the People CPT.
// Specifically, this lets the user change the url slug that is prepended to a single-person page.
add_filter( 'ucf_people_post_type_args', __NAMESPACE__ . '\\set_url_slug', 30, 1 );

// When the site admin modifies the slug ACF preference, force WordPress to recalculate the slugs (same as navigating to the permalinks page)
add_action( 'acf/save_post', __NAMESPACE__ . '\\purge_rewrite_rules_on_acf_save', 20, 1);

function set_url_slug($args){
	if ( function_exists( 'get_field' ) ) {
		$rewrite = get_field( 'person_rewrite_slug', 'option' );
	}
	if ($rewrite) {
		$args[ 'rewrite' ] = [ 'slug' => $rewrite ];
	}
	return $args;
}

/**
 * Tells WordPress to flush the permalink url structure if the ACF options page is changed with a new Person slug
 * @param $post_id
 */
function purge_rewrite_rules_on_acf_save($post_id){
	$screen = get_current_screen();

	// only run this expensive operation when our custom options page is saved
	if (strpos($screen->id, "ucf-people-directory-general-settings") == true) {



		// need to re-register the class definition, so that the rewrite flush function knows what the new slug is.
		// otherwise, it would use the already-defined class with the old slug, since changing the setting doesn't
		// change the CPT definition until another page is loaded. IE it would require pressing save a second time.
		if ( class_exists( 'UCF_People_PostType' ) ) {
			\UCF_People_PostType::register();
		}
		// Now that the in-memory version has the new arguments, we can flush the rules
		flush_rewrite_rules(false);
	}
}
<?php
/**
 * Created by IntelliJ IDEA.
 * User: stephen
 * Date: 2020-07-06
 * Time: 2:59 PM
 */

// ###
// Unused code. Using ACF fields for the extra taxonomy fields. Edit includes/acf-pro-fields.php instead.
// ###

// Adds a checkbox to the backend taxonomy terms. If checked, that People Group will limit the information shown for any Person who is assigned that group.

class ucf_people_directory_taxonomy_tweaks {
	const taxonomy = 'people_group'; // existing taxonomy that we are modifying to add another field

	public static function admin_init_hooks(){
		add_action( self::taxonomy . '_add_form_fields', array('ucf_people_directory_taxonomy_tweaks', 'add_field'), 10, 2); // add admin field for the 'add' screen
		add_action( self::taxonomy . '_edit_form_fields', array('ucf_people_directory_taxonomy_tweaks', 'edit_field'), 10, 2); // add admin field for the 'edit' screen
		add_action( 'created_' . self::taxonomy, array('ucf_people_directory_taxonomy_tweaks', 'save_field'), 10, 2); // save the meta field when adding a new taxonomy
		add_action( 'edited_' . self::taxonomy, array('ucf_people_directory_taxonomy_tweaks', 'update_field'), 10, 2); // save the meta field when updating a taxonomy


	}

	public static function add_field($term){
		?>
        <div class="form-field term-group">
            <label for="limited-info">Limited</label>
            <input type="checkbox" class="postform" name="limited-info" />

        </div>
        <div class="form-field term-group">
            <label for="external-link">External Link</label>
            <input type="checkbox" class="postform" name="external-link" />

        </div>
        <div class="form-field term-group">
            <label for="external-link-url">External Link URL</label>
            <input type="url" class="postform" name="external-link-url" />

        </div>
		<?php

	}
	public static function edit_field($term){
		$t_id = $term->term_id;

		$term_meta_limited = get_term_meta($t_id, 'limited-info', true);
		$term_meta_external = get_term_meta($t_id, 'external-link', true);
		$term_meta_external_url = get_term_meta($t_id, 'external-link-url', true);
		?>
        <tr class="form-field">

            <th scope="row" valign="top"><label for="limited-info">Limited</label></th>
            <td>
                <input type="checkbox" class="postform" name="limited-info" value="1" <?= ($term_meta_limited)? "checked='checked'" : ""; ?> />

                <p class="description">If checked, any Person with this People Group will only show a limited set of information and will not have a link to view their entire profile.</p>
            </td>
        </tr>
        <tr class="form-field">

            <th scope="row" valign="top"><label for="external-link">External Link</label></th>
            <td>
                <input type="checkbox" class="postform" name="external-link" value="1" <?= ($term_meta_external)? "checked='checked'" : ""; ?> />

                <p class="description">If checked, this People Group will link to an external directory, and it will prevent a Person from being added to this group.</p>
            </td>
        </tr>
        <tr class="form-field">

            <th scope="row" valign="top"><label for="external-link-url">External Link URL</label></th>
            <td>
                <input type="url" class="postform" name="external-link-url" value="<?= $term_meta_external_url ?>" />

                <p class="description">The url to the external directory.</p>
            </td>
        </tr>
		<?php

	}
	public static function save_field($term_id){
		if ( isset( $_POST['limited-info']) && '1' == $_POST['limited-info']){
			add_term_meta($term_id, 'limited-info', 1);
		} else {
			add_term_meta($term_id, 'limited-info', 0);
		}
		if ( isset( $_POST['external-link']) && '1' == $_POST['external-link']){
			add_term_meta($term_id, 'external-link', 1);
		} else {
			add_term_meta($term_id, 'external-link', 0);
		}
		if ( isset( $_POST['external-link-url']) ){
			add_term_meta($term_id, 'external-link-url', $_POST['external-link-url']);
		} else {
			add_term_meta($term_id, 'external-link-url', "");
		}

	}
	public static function update_field($term_id){
		if ( isset( $_POST['limited-info']) && '1' == $_POST['limited-info']){
			update_term_meta($term_id, 'limited-info', 1);
		} else {
			update_term_meta($term_id, 'limited-info', 0);
		}
		if ( isset( $_POST['external-link']) && '1' == $_POST['external-link']){
			update_term_meta($term_id, 'external-link', 1);
		} else {
			update_term_meta($term_id, 'external-link', 0);
		}
		if ( isset( $_POST['external-link-url']) ){
			update_term_meta($term_id, 'external-link-url', $_POST['external-link-url']);
		} else {
			update_term_meta($term_id, 'external-link-url', "");
		}
	}
}

add_action('admin_init', array('ucf_people_directory_taxonomy_tweaks', 'admin_init_hooks'));
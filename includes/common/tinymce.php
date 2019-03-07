<?php
/**
 * Created by PhpStorm.
 * User: stephen
 * Date: 2019-02-01
 * Time: 4:01 PM
 */
if (!class_exists('ucf_college_tinymce')) {
    class  ucf_college_tinymce {
        function __construct() {
            add_filter( 'mce_external_plugins', array( $this, 'plugin' ) );
            add_filter( 'mce_buttons', array( $this, 'button' ) );
            add_action( 'admin_head', array( $this, 'write_array_to_header'));
        }

        /**
         * Include plugin.js in the tinymce way (which doesn't use wp_register_script but rather uses its own function)
         */
        function plugin( $plugin_array ) {

            $plugin_array[ 'ucf_college_shortcodes_key' ] = plugin_dir_url( __FILE__ ) . 'tinymce.js'; // include the javascript for the button, located inside the current plugin folder
            return $plugin_array;
        }

        /**
         * Add a new button to tinymce, which will be used to create a dropdown list.
         */
        function button( $buttons ) {
            array_push( $buttons, 'separator', 'ucf_college_shortcodes_key' );

            return $buttons;
        }

        /**
         * Runs a custom hook that writes all shortcode menu items out as a JavaScript array.
         * Note: we can't use wp_localize_script, since the tinymce javascript isn't enqueued with normal
         * WordPress hooks, but rather as a mce_external_plugins hook.
         * Therefore, we simply write the values to the header manually.
         */
        function write_array_to_header(){
            $shortcode_array = array();
            $shortcode_array = apply_filters('ucf_college_shortcode_menu_item', $shortcode_array);
            $shortcode_array = $this->sort_shortcode_array($shortcode_array);
            ?>
            <script type="text/javascript">
                var ucf_college_shortcodes_array = <?php echo json_encode($shortcode_array); ?>;
            </script>
            <?php

        }

        function sort_shortcode_array($shortcode_array){
            usort($shortcode_array, function($a, $b){ return strcmp($a['slug'], $b['slug']); });
            return $shortcode_array;
        }


    }

    new ucf_college_tinymce();
}
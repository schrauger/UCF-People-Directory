/**
 * Created by stephen on 2/1/19.
 */
(function () {
    tinymce.PluginManager.add('ucf_college_shortcodes_key', function (editor, url) {

        // read through all the shortcodes that have been enabled from UCF-College-* plugins
        var shortcode_menu = [];
        ucf_college_shortcodes_array.forEach(function(element){
            shortcode_menu.push(
                {
                    title: element.slug,
                    text: element.name,
                    onclick: function () {
                        editor.insertContent('[' + element.slug + ']')
                    }
                }
            )
        });

        // add a button with a dropdown menu of all our shortcodes
        editor.addButton('ucf_college_shortcodes_key', {
            title: 'UCF College Shortcodes',
            text: 'UCF College Shortcodes',
            icon: false,
            type: 'menubutton',
            menu: shortcode_menu
        });
    });
})();
<?php
/**
 * Plugin Name: Surecart Dashboard Tabs Manager
 * Description: Allows shop owners to add, edit, and remove custom dashboard tabs via a GUI with dynamic icon styling, media uploader support, and a dynamic post type selector. The "Post Type" column remains in place even when hidden.
 * Version: 1.3.4
 * Author: Your Name
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/* ==========================================================================
   1. Enqueue Admin Scripts
   ========================================================================== */
add_action( 'admin_enqueue_scripts', 'rup_sc_sdtm_enqueue_admin_scripts' );
function rup_sc_sdtm_enqueue_admin_scripts( $hook ) {
    // This was the old check:
    // if ( $hook === 'toplevel_page_sdtm-dashboard-tabs' ) {
    //     wp_enqueue_media();
    //     wp_enqueue_script( 'jquery' );
    // }

    // Now that it's a submenu under "Settings", do:
    if ( $hook === 'settings_page_sdtm-dashboard-tabs' ) {
        wp_enqueue_media(); // Enqueue the media uploader scripts.
        wp_enqueue_script( 'jquery' );
    }
}


/* ==========================================================================
   2. Admin Settings Page for Dashboard Tabs
   ========================================================================== */

// Add a new menu page for managing dashboard tabs.
add_action( 'admin_menu', 'rup_sc_sdtm_add_admin_menu' );
function rup_sc_sdtm_add_admin_menu() {
// add_menu_page(
//     'Dashboard Tabs',
//     'Dashboard Tabs',
//     'manage_options',
//     'sdtm-dashboard-tabs',
//     'rup_sc_sdtm_render_settings_page',
//     'dashicons-admin-generic'
// );

    add_submenu_page(
    'options-general.php',      // Parent slug for Settings
    'SC Dashboard Tabs',           // Page title
    'SC Dashboard Tabs',           // Menu title
    'manage_options',           // Capability
    'sdtm-dashboard-tabs',      // Menu slug
    'rup_sc_sdtm_render_settings_page' // Callback function to render the page
);
}
// Register the setting to store the dashboard tabs.
add_action( 'admin_init', 'rup_sc_sdtm_register_settings' );
function rup_sc_sdtm_register_settings() {
    register_setting( 'rup_sc_sdtm_options_group', 'custom_dashboard_tabs' );
}

// Render the settings page.
function rup_sc_sdtm_render_settings_page() {
    // Get all public post types for the post type dropdown.
    $post_types = get_post_types( array( 'public' => true ), 'objects' );
    $tabs = get_option( 'custom_dashboard_tabs', array() );
    ?>
    <div class="wrap">
        <h1>SureCart Dashboard Tabs Manager</h1>
        <form method="post" action="options.php">
            <?php 
                settings_fields( 'rup_sc_sdtm_options_group' );
                do_settings_sections( 'rup_sc_sdtm_options_group' );
            ?>
            <table id="tabs-table" class="widefat">
                <thead>
                    <tr>
                        <th>Tab Name</th>
                        <th>Slug</th>
                        <th>Icon URL / Class</th>
                        <th>Content Type</th>
                        <th class="sdtm-post-type-th">Post Type</th>
                        <th>Content Source</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php 
                if ( is_array( $tabs ) && ! empty( $tabs ) ) :
                    foreach( $tabs as $index => $tab ) :
                        // If content_type !== 'page', we want to hide the Post Type cell visually but keep its space.
                        $visibility = ( isset( $tab['content_type'] ) && $tab['content_type'] === 'page' ) ? 'visible' : 'hidden';
                        ?>
                    <tr>
                        <td>
                            <input type="text" name="custom_dashboard_tabs[<?php echo esc_attr( $index ); ?>][name]" 
                                   value="<?php echo esc_attr( $tab['name'] ); ?>" />
                        </td>
                        <td>
                            <input type="text" name="custom_dashboard_tabs[<?php echo esc_attr( $index ); ?>][slug]" 
                                   value="<?php echo esc_attr( $tab['slug'] ); ?>" />
                        </td>
                        <td>
                            <input type="text" class="sdtm-icon-field" name="custom_dashboard_tabs[<?php echo esc_attr( $index ); ?>][icon]" 
                                   value="<?php echo esc_attr( $tab['icon'] ); ?>" />
                            <button class="button sdtm-select-icon">Select Image</button>
                        </td>
                        <td>
                            <select name="custom_dashboard_tabs[<?php echo esc_attr( $index ); ?>][content_type]" class="sdtm-content-type">
                                <option value="page" <?php selected( $tab['content_type'], 'page' ); ?>>Page/Post/Custom Post</option>
                                <option value="shortcode" <?php selected( $tab['content_type'], 'shortcode' ); ?>>Shortcode</option>
                                <!--<option value="code" <?php selected( $tab['content_type'], 'code' ); ?>>Code (HTML/PHP)</option>-->
                            </select>
                        </td>
                        <td class="sdtm-post-type-cell" style="visibility: <?php echo esc_attr($visibility); ?>;">
                            <select name="custom_dashboard_tabs[<?php echo esc_attr( $index ); ?>][post_type]">
                                <?php foreach ( $post_types as $pt ) : ?>
                                    <option value="<?php echo esc_attr( $pt->name ); ?>" <?php selected( isset($tab['post_type']) ? $tab['post_type'] : 'page', $pt->name ); ?>>
                                        <?php echo esc_html( $pt->labels->singular_name ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td class="sdtm-content-source-cell">
                            <?php if ( isset( $tab['content_type'] ) && $tab['content_type'] === 'code' ) { ?>
                                <textarea name="custom_dashboard_tabs[<?php echo esc_attr( $index ); ?>][content_source]" style="width:100%;" rows="8"><?php echo esc_textarea( $tab['content_source'] ); ?></textarea>
                            <?php } else { ?>
                                <input type="text" name="custom_dashboard_tabs[<?php echo esc_attr( $index ); ?>][content_source]" 
                                       value="<?php echo esc_attr( $tab['content_source'] ); ?>" />
                            <?php } ?>
                        </td>
                        <td>
                            <button class="button sdtm-remove-row">Remove</button>
                        </td>
                    </tr>
                <?php 
                    endforeach;
                endif; 
                ?>
                </tbody>
            </table>
            <p>
                <button id="sdtm-add-row" class="button">Add Tab</button>
            </p>
            <?php submit_button(); ?>
        </form>
    </div>
    <script>
    (function($){
        // Helper functions to switch between input and textarea.
        function convertToTextarea($input) {
            var value = $input.val();
            var name = $input.attr('name');
            var $textarea = $('<textarea>').attr('name', name).css('width','100%').attr('rows', 8).val(value);
            $input.replaceWith($textarea);
        }
        function convertToInput($textarea) {
            var value = $textarea.val();
            var name = $textarea.attr('name');
            var $input = $('<input type="text">').attr('name', name).val(value);
            $textarea.replaceWith($input);
        }
        $(document).ready(function(){
            var counter = <?php echo is_array($tabs) ? count($tabs) : 0; ?>;
            
            // When adding a new row, default content type is "page".
            $('#sdtm-add-row').on('click', function(e){
                e.preventDefault();
                var postTypesOptions = '';
                <?php
                // Pre-build the <option> list for all public post types
                $post_types_js = '';
                foreach ( $post_types as $pt ) {
                    $post_types_js .= '<option value=\"' . esc_attr( $pt->name ) . '\">' . esc_html( $pt->labels->singular_name ) . '</option>';
                }
                ?>
                var newRow = '<tr>' +
                    '<td><input type="text" name="custom_dashboard_tabs['+counter+'][name]" value="" /></td>' +
                    '<td><input type="text" name="custom_dashboard_tabs['+counter+'][slug]" value="" /></td>' +
                    '<td><input type="text" class="sdtm-icon-field" name="custom_dashboard_tabs['+counter+'][icon]" value="" /> ' +
                        '<button class="button sdtm-select-icon">Select Image</button></td>' +
                    '<td><select name="custom_dashboard_tabs['+counter+'][content_type]" class="sdtm-content-type">' +
                        '<option value="page">Page/Post/Custom Post</option>' +
                        '<option value="shortcode">Shortcode</option>' +
                    '</select></td>' +
                    // Default to visible for new row, because "page" is default
                    '<td class="sdtm-post-type-cell" style="visibility: visible;">' +
                        '<select name="custom_dashboard_tabs['+counter+'][post_type]">' +
                            "<?php echo $post_types_js; ?>" +
                        '</select>' +
                    '</td>' +
                    '<td class="sdtm-content-source-cell"><input type="text" name="custom_dashboard_tabs['+counter+'][content_source]" value="" /></td>' +
                    '<td><button class="button sdtm-remove-row">Remove</button></td>' +
                    '</tr>';
                $('#tabs-table tbody').append(newRow);
                counter++;
            });
            
            // Remove row.
            $('#tabs-table').on('click', '.sdtm-remove-row', function(e){
                e.preventDefault();
                $(this).closest('tr').remove();
            });
            
            // When content type changes, switch the content source field and hide/show the post type cell (via visibility).
            $('#tabs-table').on('change', '.sdtm-content-type', function(){
                var $select = $(this);
                var type = $select.val();
                var $row = $select.closest('tr');
                var $sourceCell = $row.find('.sdtm-content-source-cell');
                var $postTypeCell = $row.find('.sdtm-post-type-cell');
                
                if ( type === 'code' ) {
                    // If we had that option re-enabled, we'd do a text area.
                    var $input = $sourceCell.find('input');
                    if ( $input.length ) {
                        convertToTextarea($input);
                    }
                } else if ( type !== 'code' ) {
                    var $textarea = $sourceCell.find('textarea');
                    if ( $textarea.length ) {
                        convertToInput($textarea);
                    }
                }
                
                // Show post type cell only when "page" is selected, but keep the column's space:
                if ( type === 'page' ) {
                    $postTypeCell.css('visibility','visible');
                } else {
                    $postTypeCell.css('visibility','hidden');
                }
            });
            
            // Media uploader for icon field.
            var mediaUploader;
            $('#tabs-table').on('click', '.sdtm-select-icon', function(e){
                e.preventDefault();
                var $button = $(this);
                var $iconField = $button.siblings('.sdtm-icon-field');
                if ( mediaUploader ) {
                    mediaUploader.open();
                    return;
                }
                mediaUploader = wp.media.frames.file_frame = wp.media({
                    title: 'Select Icon',
                    button: {
                        text: 'Select Icon'
                    },
                    multiple: false
                });
                mediaUploader.on('select', function(){
                    var attachment = mediaUploader.state().get('selection').first().toJSON();
                    $iconField.val(attachment.url);
                });
                mediaUploader.open();
            });
            
            // On page load, trigger change to hide/show post type cell as needed.
            $('.sdtm-content-type').trigger('change');
        });
    })(jQuery);
    </script>
    <?php
}

/* ==========================================================================
   3. Dynamically Register Dashboard Tabs
   ========================================================================== */

add_filter( 'rup_sc_dashextender_surecart_navigation', 'rup_sc_sdtm_register_dashboard_tabs' );
function rup_sc_sdtm_register_dashboard_tabs( $navigation ) {
    $tabs = get_option( 'custom_dashboard_tabs', array() );
    if ( is_array( $tabs ) ) {
        foreach ( $tabs as $tab ) {
            // Generate a unique slug if not set.
            $slug = ! empty( $tab['slug'] ) ? sanitize_title( $tab['slug'] ) : sanitize_title( $tab['name'] );
            // Process the icon field.
            $icon_value = isset( $tab['icon'] ) ? trim( $tab['icon'] ) : '';
            $icon_data = array();
            $icon_name = '';
            if ( ! empty( $icon_value ) && filter_var( $icon_value, FILTER_VALIDATE_URL ) ) {
                $icon_data = array( 'icon_url' => $icon_value );
                $icon_name = $slug;
            } else {
                $icon_value = 'default-icon';
                $icon_data = array( 'icon_name' => $icon_value );
                $icon_name = $icon_value;
            }
            
            // Merge navigation array.
            $navigation[ $slug ] = array_merge( array(
                'name'      => $tab['name'],
                'href'      => add_query_arg( 'sc-page', $slug, get_permalink() ),
                'icon_name' => $icon_name,
            ), $icon_data );
            
            // Register content callback.
            add_action( 'rup_sc_dashextender_surecart_dashboard_right_' . $slug, function() use ( $tab ) {
                echo '<div class="custom-tab-content">';
                switch ( $tab['content_type'] ) {
                    case 'page':
                        // Get the post type (default to "page" if not set).
                        $post_type = isset( $tab['post_type'] ) && ! empty( $tab['post_type'] ) ? sanitize_key( $tab['post_type'] ) : 'page';
                        // Look for a page/post/custom post by its path (slug) in the given post type.
                        $page = get_page_by_path( $tab['content_source'], OBJECT, $post_type );
                        if ( $page ) {
                            echo apply_filters( 'the_content', $page->post_content );
                        } else {
                            echo '<p>Content not found. Please check your content source.</p>';
                        }
                        break;
                    case 'shortcode':
                        echo do_shortcode( $tab['content_source'] );
                        break;
                    case 'code':
                        // WARNING: Using eval() can be dangerous. Only allow trusted code.
                        eval( '?>' . $tab['content_source'] );
                        break;
                    default:
                        echo '<p>No valid content type selected.</p>';
                        break;
                }
                echo '</div>';
            });
        }
    }
    return $navigation;
}

/* ==========================================================================
   4. Output Dynamic Icon Stylesheet Using <sc-icon> Selector
   ========================================================================== */

// Output the dynamic icon stylesheet on both admin and front-end.
if ( is_admin() ) {
    add_action( 'admin_head', 'rup_sc_sdtm_output_dynamic_icon_styles' );
} else {
    add_action( 'wp_head', 'rup_sc_sdtm_output_dynamic_icon_styles' );
}

function rup_sc_sdtm_output_dynamic_icon_styles() {
    $tabs = get_option( 'custom_dashboard_tabs', array() );
    if ( empty( $tabs ) ) {
        return;
    }
    echo '<style type="text/css">' . "\n";
    foreach ( $tabs as $tab ) {
        $slug = ! empty( $tab['slug'] ) ? sanitize_title( $tab['slug'] ) : sanitize_title( $tab['name'] );
        if ( ! empty( $tab['icon'] ) && filter_var( $tab['icon'], FILTER_VALIDATE_URL ) ) {
            $icon_attr = $slug;
            echo "sc-icon[name=\"" . esc_attr( $icon_attr ) . "\"] {\n";
            echo "    display: inline-block;\n";
            echo "    width: 20px;\n";
            echo "    height: 20px;\n";
            echo "    background: url('" . esc_url( $tab['icon'] ) . "') no-repeat center center;\n";
            echo "    background-size: contain;\n";
            echo "    text-indent: -9999px;\n";
            echo "}\n";
        } else {
            // Fallback rule for default-icon.
            echo "sc-icon[name=\"default-icon\"] {\n";
            echo "    display: inline-block;\n";
            echo "    width: 20px;\n";
            echo "    height: 20px;\n";
            echo "    background: url('/path/to/fallback-icon.svg') no-repeat center center;\n";
            echo "    background-size: contain;\n";
            echo "    text-indent: -9999px;\n";
            echo "}\n";
        }
    }
    echo '</style>' . "\n";
}

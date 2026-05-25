<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/* ==========================================================================
   1. Enqueue Admin Scripts
   ========================================================================== */
add_action( 'admin_enqueue_scripts', 'rup_sc_sdtm_enqueue_admin_scripts' );
function rup_sc_sdtm_enqueue_admin_scripts( $hook ) {
    $screen_page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

    if ( 'surecart-dashy' === $screen_page || false !== strpos( (string) $hook, 'surecart-dashy' ) ) {
        wp_enqueue_media(); // Enqueue the media uploader scripts.
        wp_enqueue_script( 'jquery' );
    }
}


/**
 * AJAX: return published items for the selected content post type.
 *
 * The saved value remains custom_dashboard_tabs[*][content_source], so existing
 * manual slugs/IDs remain backward compatible. This endpoint only powers the
 * admin dropdown helper.
 */
add_action( 'wp_ajax_rup_sc_sdtm_get_content_sources', 'rup_sc_sdtm_get_content_sources' );
function rup_sc_sdtm_get_content_sources() {
    check_ajax_referer( 'rup_sc_sdtm_content_sources', 'nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => __( 'Permission denied.', 'dashy-for-surecart' ) ), 403 );
    }

    $post_type = isset( $_POST['post_type'] ) ? sanitize_key( wp_unslash( $_POST['post_type'] ) ) : 'page';
    $post_type_object = get_post_type_object( $post_type );

    if ( ! $post_type_object || empty( $post_type_object->public ) ) {
        wp_send_json_error( array( 'message' => __( 'Invalid post type.', 'dashy-for-surecart' ) ), 400 );
    }

    $items = get_posts(
        array(
            'post_type'      => $post_type,
            'post_status'    => array( 'publish', 'private' ),
            'posts_per_page' => 300,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'fields'         => 'ids',
        )
    );

    $results = array();
    foreach ( $items as $post_id ) {
        $post = get_post( $post_id );
        if ( ! $post ) {
            continue;
        }

        $results[] = array(
            'id'    => (string) $post_id,
            'slug'  => (string) $post->post_name,
            'title' => html_entity_decode( get_the_title( $post_id ), ENT_QUOTES, get_bloginfo( 'charset' ) ),
        );
    }

    wp_send_json_success( $results );
}


/* ==========================================================================
   2. Admin Settings Page for Dashboard Tabs
   ========================================================================== */

// Add Dashy under the SureCart admin menu.
add_action( 'admin_menu', 'rup_sc_sdtm_add_admin_menu', 99 );
function rup_sc_sdtm_add_admin_menu() {
    add_submenu_page(
        'sc-dashboard',
        'Dashy',
        'Dashy',
        'manage_options',
        'surecart-dashy',
        'rup_sc_sdtm_render_settings_page'
    );
}

// Register the setting to store the dashboard tabs.
add_action( 'admin_init', 'rup_sc_sdtm_register_settings' );
function rup_sc_sdtm_register_settings() {
    register_setting(
        'rup_sc_sdtm_options_group',
        'custom_dashboard_tabs',
        array(
            'sanitize_callback' => 'rup_sc_sdtm_sanitize_tabs',
        )
    );
}


/**
 * Sanitize uploaded icon URLs and native SureCart/Lucide icon names.
 *
 * @param string $icon Raw icon value.
 * @return string
 */
function rup_sc_sdtm_sanitize_icon_value( $icon ) {
    $icon = trim( (string) $icon );

    if ( '' === $icon ) {
        return '';
    }

    return filter_var( $icon, FILTER_VALIDATE_URL ) ? esc_url_raw( $icon ) : sanitize_title( $icon );
}

/**
 * Normalize old/manual page slugs to post IDs when possible while preserving
 * unknown legacy values for backward compatibility.
 *
 * @param mixed $tabs Raw submitted tabs.
 * @return array
 */
function rup_sc_sdtm_sanitize_tabs( $tabs ) {
    if ( empty( $tabs ) || ! is_array( $tabs ) ) {
        return array();
    }

    $clean = array();

    foreach ( $tabs as $tab ) {
        if ( ! is_array( $tab ) ) {
            continue;
        }

        $content_type = isset( $tab['content_type'] ) ? sanitize_key( $tab['content_type'] ) : 'page';
        $post_type    = isset( $tab['post_type'] ) ? sanitize_key( $tab['post_type'] ) : 'page';

        $clean_tab = array(
            'name'           => isset( $tab['name'] ) ? sanitize_text_field( wp_unslash( $tab['name'] ) ) : '',
            'slug'           => isset( $tab['slug'] ) ? sanitize_title( wp_unslash( $tab['slug'] ) ) : '',
            'icon'           => isset( $tab['icon'] ) ? rup_sc_sdtm_sanitize_icon_value( wp_unslash( $tab['icon'] ) ) : '',
            'content_type'   => in_array( $content_type, array( 'page', 'shortcode', 'code' ), true ) ? $content_type : 'page',
            'post_type'      => $post_type,
            'content_source' => isset( $tab['content_source'] ) ? wp_kses_post( wp_unslash( $tab['content_source'] ) ) : '',
        );

        if ( 'page' === $clean_tab['content_type'] ) {
            $clean_tab['content_source'] = rup_sc_sdtm_normalize_content_source( $clean_tab['content_source'], $post_type );
        }

        if ( '' !== $clean_tab['name'] || '' !== $clean_tab['slug'] ) {
            $clean[] = $clean_tab;
        }
    }

    return $clean;
}

/**
 * Convert a page/post/CPT source slug to an ID when it can be found.
 * Unknown values are preserved so old manual entries do not break.
 *
 * @param string $source Saved source value.
 * @param string $post_type Post type to search.
 * @return string
 */
function rup_sc_sdtm_normalize_content_source( $source, $post_type = 'page' ) {
    $source = trim( (string) $source );

    if ( '' === $source ) {
        return '';
    }

    if ( is_numeric( $source ) ) {
        return (string) absint( $source );
    }

    $post_type = sanitize_key( $post_type ?: 'page' );
    $post      = get_page_by_path( sanitize_title( $source ), OBJECT, $post_type );

    if ( ! $post ) {
        $post = get_page_by_path( $source, OBJECT, $post_type );
    }

    return $post instanceof WP_Post ? (string) $post->ID : $source;
}

/**
 * Quietly migrate existing option values on admin load.
 *
 * This removes the need to show a duplicate manual field in the UI. Old slugs
 * are converted to IDs when possible, and anything unresolved is preserved.
 */
add_action( 'admin_init', 'rup_sc_sdtm_maybe_migrate_saved_tabs' );
function rup_sc_sdtm_maybe_migrate_saved_tabs() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $tabs = get_option( 'custom_dashboard_tabs', array() );
    if ( empty( $tabs ) || ! is_array( $tabs ) ) {
        return;
    }

    $migrated = rup_sc_sdtm_sanitize_tabs( $tabs );

    if ( $migrated !== $tabs ) {
        update_option( 'custom_dashboard_tabs', $migrated );
    }
}


/**
 * Render the content source field.
 *
 * For page/post/CPT content, the hidden input remains the canonical saved value.
 * Existing manual slugs are migrated to IDs when possible. If a legacy value
 * cannot be resolved, it is preserved in the hidden input and shown as the
 * selected legacy option so the admin page stays uncluttered.
 */
function rup_sc_sdtm_render_content_source_field( $index, $tab ) {
    $content_type   = isset( $tab['content_type'] ) ? $tab['content_type'] : 'page';
    $post_type      = isset( $tab['post_type'] ) ? sanitize_key( $tab['post_type'] ) : 'page';
    $content_source = isset( $tab['content_source'] ) ? (string) $tab['content_source'] : '';

    if ( 'code' === $content_type ) {
        ?>
        <textarea name="custom_dashboard_tabs[<?php echo esc_attr( $index ); ?>][content_source]" style="width:100%;" rows="8"><?php echo esc_textarea( $content_source ); ?></textarea>
        <?php
        return;
    }

    if ( 'page' !== $content_type ) {
        ?>
        <input type="text" name="custom_dashboard_tabs[<?php echo esc_attr( $index ); ?>][content_source]" value="<?php echo esc_attr( $content_source ); ?>" />
        <?php
        return;
    }

    $normalized_source = rup_sc_sdtm_normalize_content_source( $content_source, $post_type );
    ?>
    <input type="hidden" class="sdtm-content-source-value" name="custom_dashboard_tabs[<?php echo esc_attr( $index ); ?>][content_source]" value="<?php echo esc_attr( $normalized_source ); ?>" />
    <select class="sdtm-content-source-select" data-selected="<?php echo esc_attr( $normalized_source ); ?>" data-legacy="<?php echo esc_attr( $content_source ); ?>" style="min-width:260px;max-width:100%;">
        <option value=""><?php esc_html_e( 'Loading…', 'dashy-for-surecart' ); ?></option>
    </select>
    <?php
}

// Render the settings page.
function rup_sc_sdtm_render_settings_page() {
    // Get all public post types for the post type dropdown.
    $post_types = get_post_types( array( 'public' => true ), 'objects' );
    $tabs = get_option( 'custom_dashboard_tabs', array() );
    ?>
    <div class="wrap">
        <h1>Dashy</h1>
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
                        <th>Content</th>
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
                            <select class="sdtm-post-type" name="custom_dashboard_tabs[<?php echo esc_attr( $index ); ?>][post_type]">
                                <?php foreach ( $post_types as $pt ) : ?>
                                    <option value="<?php echo esc_attr( $pt->name ); ?>" <?php selected( isset($tab['post_type']) ? $tab['post_type'] : 'page', $pt->name ); ?>>
                                        <?php echo esc_html( $pt->labels->singular_name ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td class="sdtm-content-source-cell">
                            <?php rup_sc_sdtm_render_content_source_field( $index, $tab ); ?>
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
        var sourceNonce = <?php echo wp_json_encode( wp_create_nonce( 'rup_sc_sdtm_content_sources' ) ); ?>;
        var postTypesOptions = <?php
            $post_types_js = '';
            foreach ( $post_types as $pt ) {
                $post_types_js .= '<option value="' . esc_attr( $pt->name ) . '">' . esc_html( $pt->labels->singular_name ) . '</option>';
            }
            echo wp_json_encode( $post_types_js );
        ?>;

        function sourceName(index) {
            return 'custom_dashboard_tabs[' + index + '][content_source]';
        }

        function pageSourceField(index, currentValue) {
            currentValue = currentValue || '';
            return '<input type="hidden" class="sdtm-content-source-value" name="' + sourceName(index) + '" value="' + $('<div>').text(currentValue).html() + '" />' +
                '<select class="sdtm-content-source-select" data-selected="' + $('<div>').text(currentValue).html() + '" data-legacy="' + $('<div>').text(currentValue).html() + '" style="min-width:260px;max-width:100%;"><option value="">Loading…</option></select>';
        }

        function textSourceField(index, currentValue) {
            currentValue = currentValue || '';
            return '<input type="text" name="' + sourceName(index) + '" value="' + $('<div>').text(currentValue).html() + '" />';
        }

        function textareaSourceField(index, currentValue) {
            currentValue = currentValue || '';
            return '<textarea name="' + sourceName(index) + '" style="width:100%;" rows="8">' + $('<div>').text(currentValue).html() + '</textarea>';
        }

        function getRowIndex($row) {
            var name = $row.find('[name*="[name]"]').attr('name') || '';
            var match = name.match(/custom_dashboard_tabs\[(\d+)\]/);
            return match ? match[1] : '';
        }

        function loadContentSources($row) {
            var $postType = $row.find('.sdtm-post-type');
            var $select = $row.find('.sdtm-content-source-select');
            var $hidden = $row.find('.sdtm-content-source-value');

            if (!$select.length || !$postType.length) {
                return;
            }

            var selected = ($hidden.val() || $select.data('selected') || $select.data('legacy') || '').toString();
            var legacy = ($select.data('legacy') || selected || '').toString();
            $select.html('<option value="">Loading…</option>').prop('disabled', true);

            $.post(ajaxurl, {
                action: 'rup_sc_sdtm_get_content_sources',
                nonce: sourceNonce,
                post_type: $postType.val()
            }).done(function(response){
                var options = '<option value="">Select content…</option>';
                var matched = false;
                var matchedValue = '';

                if (response && response.success && $.isArray(response.data)) {
                    $.each(response.data, function(_, item){
                        var value = item.id.toString();
                        var label = item.title + ' (' + item.slug + ', ID ' + item.id + ')';
                        var isMatch = selected && (selected === value || selected === item.slug || legacy === value || legacy === item.slug);
                        if (isMatch) {
                            matched = true;
                            matchedValue = value;
                        }
                        options += '<option value="' + $('<div>').text(value).html() + '" data-slug="' + $('<div>').text(item.slug).html() + '"' + (isMatch ? ' selected' : '') + '>' + $('<div>').text(label).html() + '</option>';
                    });
                }

                if (selected && !matched) {
                    options += '<option value="' + $('<div>').text(selected).html() + '" selected>Saved legacy value: ' + $('<div>').text(selected).html() + '</option>';
                }

                $select.html(options).prop('disabled', false);

                if (matched && matchedValue) {
                    // Auto-migrate old slug values to post IDs without exposing a duplicate manual field.
                    $hidden.val(matchedValue);
                } else if (selected) {
                    $hidden.val(selected);
                }
            }).fail(function(){
                var selected = ($hidden.val() || '').toString();
                $select.html(selected ? '<option value="' + $('<div>').text(selected).html() + '" selected>Saved legacy value: ' + $('<div>').text(selected).html() + '</option>' : '<option value="">Unable to load content</option>').prop('disabled', false);
            });
        }

        $(document).ready(function(){
            var counter = <?php echo is_array($tabs) ? count($tabs) : 0; ?>;

            $('#sdtm-add-row').on('click', function(e){
                e.preventDefault();
                var newRow = '<tr>' +
                    '<td><input type="text" name="custom_dashboard_tabs['+counter+'][name]" value="" /></td>' +
                    '<td><input type="text" name="custom_dashboard_tabs['+counter+'][slug]" value="" /></td>' +
                    '<td><input type="text" class="sdtm-icon-field" name="custom_dashboard_tabs['+counter+'][icon]" value="" /> ' +
                        '<button class="button sdtm-select-icon">Select Image</button></td>' +
                    '<td><select name="custom_dashboard_tabs['+counter+'][content_type]" class="sdtm-content-type">' +
                        '<option value="page">Page/Post/Custom Post</option>' +
                        '<option value="shortcode">Shortcode</option>' +
                    '</select></td>' +
                    '<td class="sdtm-post-type-cell" style="visibility: visible;">' +
                        '<select class="sdtm-post-type" name="custom_dashboard_tabs['+counter+'][post_type]">' + postTypesOptions + '</select>' +
                    '</td>' +
                    '<td class="sdtm-content-source-cell">' + pageSourceField(counter, '') + '</td>' +
                    '<td><button class="button sdtm-remove-row">Remove</button></td>' +
                    '</tr>';
                var $row = $(newRow).appendTo('#tabs-table tbody');
                loadContentSources($row);
                counter++;
            });

            $('#tabs-table').on('click', '.sdtm-remove-row', function(e){
                e.preventDefault();
                $(this).closest('tr').remove();
            });

            $('#tabs-table').on('change', '.sdtm-content-type', function(){
                var $select = $(this);
                var type = $select.val();
                var $row = $select.closest('tr');
                var index = getRowIndex($row);
                var $sourceCell = $row.find('.sdtm-content-source-cell');
                var $postTypeCell = $row.find('.sdtm-post-type-cell');
                var currentValue = $sourceCell.find('.sdtm-content-source-value, input[type="text"], textarea').first().val() || '';

                if ( type === 'page' ) {
                    $postTypeCell.css('visibility','visible');
                    $sourceCell.html(pageSourceField(index, currentValue));
                    loadContentSources($row);
                } else if ( type === 'code' ) {
                    $postTypeCell.css('visibility','hidden');
                    $sourceCell.html(textareaSourceField(index, currentValue));
                } else {
                    $postTypeCell.css('visibility','hidden');
                    $sourceCell.html(textSourceField(index, currentValue));
                }
            });

            $('#tabs-table').on('change', '.sdtm-post-type', function(){
                var $row = $(this).closest('tr');
                if ($row.find('.sdtm-content-type').val() === 'page') {
                    loadContentSources($row);
                }
            });

            $('#tabs-table').on('change', '.sdtm-content-source-select', function(){
                var $row = $(this).closest('tr');
                $row.find('.sdtm-content-source-value').val($(this).val());
            });

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
                    button: { text: 'Select Icon' },
                    multiple: false
                });
                mediaUploader.on('select', function(){
                    var attachment = mediaUploader.state().get('selection').first().toJSON();
                    $iconField.val(attachment.url);
                });
                mediaUploader.open();
            });

            $('#tabs-table tbody tr').each(function(){
                var $row = $(this);
                if ($row.find('.sdtm-content-type').val() === 'page') {
                    loadContentSources($row);
                }
            });
        });
    })(jQuery);
    </script>
    <?php
}


/**
 * Get the customer dashboard URL independently of the current loop context.
 *
 * In FSE/block themes get_permalink() may point at the wrong post when the
 * navigation is rendered inside a Query Loop or template part. This keeps tab
 * links anchored to the configured SureCart dashboard page.
 *
 * @return string
 */
function rup_sc_sdtm_get_dashboard_url() {
    $slug = 'customer-dashboard';

    if ( defined( 'RUPDASHEXTENDERSC_TEMPLATE_URL' ) && ! empty( RUPDASHEXTENDERSC_TEMPLATE_URL ) ) {
        $slug = RUPDASHEXTENDERSC_TEMPLATE_URL;
    }

    $slug = trim( (string) apply_filters( 'rup_sc_d4sc_dashboard_slug', $slug ), '/' );
    $page = get_page_by_path( $slug );

    if ( $page instanceof WP_Post ) {
        return get_permalink( $page );
    }

    return home_url( '/' . $slug . '/' );
}


/**
 * Return configured custom tab slugs.
 *
 * @return array
 */
function rup_sc_sdtm_get_custom_tab_slugs() {
    $tabs  = get_option( 'custom_dashboard_tabs', array() );
    $slugs = array();

    if ( empty( $tabs ) || ! is_array( $tabs ) ) {
        return $slugs;
    }

    foreach ( $tabs as $tab ) {
        $slug = ! empty( $tab['slug'] ) ? sanitize_title( $tab['slug'] ) : sanitize_title( isset( $tab['name'] ) ? $tab['name'] : '' );
        if ( $slug ) {
            $slugs[] = $slug;
        }
    }

    return array_values( array_unique( $slugs ) );
}

/**
 * Get the requested custom dashboard tab from either the native-looking model
 * route or the older sc-page route.
 *
 * @return string|null
 */
function rup_sc_sdtm_get_requested_custom_tab_slug() {
    $custom_slugs = rup_sc_sdtm_get_custom_tab_slugs();

    if ( empty( $custom_slugs ) ) {
        return null;
    }

    $requested_model = isset( $_GET['model'] ) ? sanitize_title( wp_unslash( $_GET['model'] ) ) : '';
    if ( $requested_model && in_array( $requested_model, $custom_slugs, true ) ) {
        return $requested_model;
    }

    $legacy_page = isset( $_GET['sc-page'] ) ? sanitize_title( wp_unslash( $_GET['sc-page'] ) ) : '';
    if ( $legacy_page && in_array( $legacy_page, $custom_slugs, true ) ) {
        return $legacy_page;
    }

    return null;
}

/**
 * FSE-only safety net: when a custom dashboard tab is requested, SureCart's
 * web component loader may not be enqueued because the route is not one of its
 * built-in models. The left navigation/account menu then renders as raw custom
 * elements. Classic themes tend to get the loader through the page template;
 * block themes can miss it.
 *
 * This does not replace native markup or styling. It only ensures the same
 * Stencil web-component loader is present on FSE custom-tab requests.
 */
function rup_sc_sdtm_force_surecart_component_loader_for_fse() {
    if ( ! rup_sc_sdtm_get_requested_custom_tab_slug() ) {
        return;
    }

    $surecart_plugin_url = trailingslashit( WP_PLUGIN_URL ) . 'surecart/';
    $component_base      = $surecart_plugin_url . 'dist/components/surecart/';
    ?>
    <!-- Dashy For SureCart: forcing SureCart component loader for custom dashboard tab. -->
    <script type="module" src="<?php echo esc_url( $component_base . 'surecart.esm.js' ); ?>"></script>
    <script nomodule src="<?php echo esc_url( $component_base . 'surecart.js' ); ?>"></script>
    <script>
    window.dashySureCartLoader = window.dashySureCartLoader || {};
    window.dashySureCartLoader.customTab = <?php echo wp_json_encode( rup_sc_sdtm_get_requested_custom_tab_slug() ); ?>;
    window.addEventListener('load', function() {
        window.dashySureCartLoader.scButtonDefined = !!(window.customElements && window.customElements.get && window.customElements.get('sc-button'));
        if (!window.dashySureCartLoader.scButtonDefined && window.console && console.warn) {
            console.warn('Dashy For SureCart: SureCart web components were still not defined after forcing the custom-tab loader. Check that /wp-content/plugins/surecart/dist/components/surecart/surecart.esm.js exists.');
        }
    });
    </script>
    <?php
}
add_action( 'wp_head', 'rup_sc_sdtm_force_surecart_component_loader_for_fse', 1 );

/**
 * FSE custom-tab style safety net.
 *
 * On block themes, SureCart's route-specific dashboard CSS can be missed for a
 * non-native model. The component loader fix hydrates the elements, but without
 * the dashboard display rules the desktop and mobile navigation can both appear,
 * making the native icons look detached from their labels. Keep this scoped to
 * FSE custom-tab requests and only restore the native dashboard shell layout.
 */
function rup_sc_sdtm_output_fse_dashboard_shell_styles() {
    if ( ! rup_sc_sdtm_get_requested_custom_tab_slug() ) {
        return;
    }

    if ( function_exists( 'wp_is_block_theme' ) && ! wp_is_block_theme() ) {
        return;
    }
    ?>
    <style type="text/css" id="dashy-surecart-fse-dashboard-shell-styles">
        .sc-dashboard .sc-dashboard__header-mobile {
            display: none !important;
        }

        .sc-dashboard .sc-dashboard__header sc-tab {
            display: block;
        }

        .sc-dashboard .sc-dashboard__header sc-tab sc-icon[slot="prefix"],
        .sc-dashboard .sc-dashboard__back sc-icon[slot="prefix"],
        .sc-dashboard .sc-dashboard__user-menu sc-icon {
            width: 18px;
            height: 18px;
            min-width: 18px;
            vertical-align: middle;
        }

        @media (max-width: 782px) {
            .sc-dashboard .sc-dashboard__header-mobile {
                display: flex !important;
            }
        }
    </style>
    <?php
}
add_action( 'wp_head', 'rup_sc_sdtm_output_fse_dashboard_shell_styles', 20 );


/* ==========================================================================
   3. Dynamically Register Dashboard Tabs
   ========================================================================== */

add_filter( 'rup_sc_dashextender_surecart_navigation', 'rup_sc_sdtm_register_dashboard_tabs' );
function rup_sc_sdtm_register_dashboard_tabs( $navigation ) {
    $tabs = get_option( 'custom_dashboard_tabs', array() );
    if ( empty( $tabs ) || ! is_array( $tabs ) ) {
        return $navigation;
    }

    $current_slug = rup_sc_sdtm_get_requested_custom_tab_slug();

    // 🔧 If a custom tab is active, unset 'active' on the default Dashboard
    if ( $current_slug && isset( $navigation['dashboard'] ) && is_array( $navigation['dashboard'] ) ) {
        $navigation['dashboard']['active'] = false;
    }

    foreach ( $tabs as $tab ) {
        $slug = ! empty( $tab['slug'] ) ? sanitize_title( $tab['slug'] ) : sanitize_title( $tab['name'] );
        $is_active = ( $current_slug && $slug === $current_slug );

        $icon_value = isset( $tab['icon'] ) ? trim( (string) $tab['icon'] ) : '';
        $icon_name  = 'folder';
        $icon_data  = array( 'icon_name' => $icon_name );

        if ( ! empty( $icon_value ) && filter_var( $icon_value, FILTER_VALIDATE_URL ) ) {
            // Uploaded/custom image icons need their own synthetic icon name so
            // the generated CSS below can target only this Dashy icon. Do not
            // use a native SureCart/Lucide name here or we will accidentally
            // override SureCart's built-in SVG icons on FSE dashboard routes.
            $icon_name = 'dashy-' . $slug;
            $icon_data = array(
                'icon_name' => $icon_name,
                'icon_url'  => $icon_value,
            );
        } elseif ( ! empty( $icon_value ) ) {
            // Backwards compatible: allow admins to type a native SureCart/Lucide
            // icon name such as "folder", "server", "inbox", etc. These should
            // be rendered by SureCart itself, so we output no custom CSS for them.
            $icon_name = sanitize_title( $icon_value );
            $icon_data = array( 'icon_name' => $icon_name );
        }

        $navigation[ $slug ] = array_merge( array(
            'name'      => $tab['name'],
            'href'      => add_query_arg( array( 'action' => 'index', 'model' => $slug ), rup_sc_sdtm_get_dashboard_url() ),
            'icon_name' => $icon_name,
            'active'    => $is_active,
        ), $icon_data );

        add_action( 'rup_sc_dashextender_surecart_dashboard_right_' . $slug, function() use ( $tab ) {
            echo '<div class="custom-tab-content">';
            switch ( $tab['content_type'] ) {
                case 'page':
                    $post_type      = ! empty( $tab['post_type'] ) ? sanitize_key( $tab['post_type'] ) : 'page';
                    $content_source = isset( $tab['content_source'] ) ? trim( (string) $tab['content_source'] ) : '';
                    $page           = null;

                    if ( is_numeric( $content_source ) ) {
                        $maybe_post = get_post( absint( $content_source ) );
                        if ( $maybe_post && $maybe_post->post_type === $post_type ) {
                            $page = $maybe_post;
                        }
                    }

                    if ( ! $page && ! empty( $content_source ) ) {
                        $page = get_page_by_path( sanitize_title( $content_source ), OBJECT, $post_type );
                    }

                    if ( ! $page && ! empty( $content_source ) ) {
                        $page = get_page_by_path( $content_source, OBJECT, $post_type );
                    }

                    echo $page ? apply_filters( 'the_content', $page->post_content ) : '<p>Content not found. Please check your content source.</p>';
                    break;
                case 'shortcode':
                    echo do_shortcode( $tab['content_source'] );
                    break;
                case 'code':
                    if ( current_user_can( 'manage_options' ) ) {
                        eval( '?>' . $tab['content_source'] );
                    } else {
                        echo '<p><strong>Access denied.</strong></p>';
                    }
                    break;
                default:
                    echo '<p>No valid content type selected.</p>';
                    break;
            }
            echo '</div>';
        });
    }

    return $navigation;
}






/**
 * Custom-tab icon safety net.
 *
 * On some custom model routes, the SureCart web components hydrate
 * but the icon library is not populated, leaving native <sc-icon> shadow roots
 * with an empty [part="base"] element. This only runs on Dashy custom tabs and only fills known native SureCart/Lucide icons when the SVG is
 * missing. Uploaded Dashy image icons are still handled by CSS above.
 */
function rup_sc_sdtm_output_fse_native_icon_repair() {
    if ( ! rup_sc_sdtm_get_requested_custom_tab_slug() ) {
        return;
    }

    ?>
    <script id="dashy-surecart-native-icon-repair">
    (function(){
        var icons = {
            'server': '<rect x="2" y="2" width="20" height="8" rx="2" ry="2"></rect><rect x="2" y="14" width="20" height="8" rx="2" ry="2"></rect><line x1="6" y1="6" x2="6.01" y2="6"></line><line x1="6" y1="18" x2="6.01" y2="18"></line>',
            'shopping-bag': '<path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path><line x1="3" y1="6" x2="21" y2="6"></line><path d="M16 10a4 4 0 0 1-8 0"></path>',
            'inbox': '<polyline points="22 12 16 12 14 15 10 15 8 12 2 12"></polyline><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"></path>',
            'repeat': '<polyline points="17 1 21 5 17 9"></polyline><path d="M3 11V9a4 4 0 0 1 4-4h14"></path><polyline points="7 23 3 19 7 15"></polyline><path d="M21 13v2a4 4 0 0 1-4 4H3"></path>',
            'download-cloud': '<polyline points="8 17 12 21 16 17"></polyline><line x1="12" y1="12" x2="12" y2="21"></line><path d="M20.88 18.09A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.29"></path>',
            'folder': '<path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"></path>',
            'menu': '<line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line>',
            'arrow-left': '<line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline>',
            'chevron-up': '<polyline points="18 15 12 9 6 15"></polyline>',
            'user': '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle>',
            'log-out': '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line>'
        };

        var labels = {
            'shopping-bag': 'shopping bag',
            'download-cloud': 'download cloud',
            'arrow-left': 'Previous',
            'chevron-up': 'Close',
            'log-out': 'log out'
        };

        function svgFor(name) {
            if (!icons[name]) return '';
            var label = labels[name] || name;
            return '<svg part="svg" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' + icons[name] + '</svg>';
        }

        function repairIcons() {
            var repaired = 0;
            document.querySelectorAll('sc-icon[name]').forEach(function(icon){
                var name = icon.getAttribute('name');
                if (!icons[name] || !icon.shadowRoot) return;
                var base = icon.shadowRoot.querySelector('[part="base"]');
                if (!base || base.querySelector('svg')) return;
                base.setAttribute('role', 'img');
                base.setAttribute('aria-label', labels[name] || name.replace(/-/g, ' '));
                base.innerHTML = svgFor(name);
                repaired++;
            });
            return repaired;
        }

        var attempts = 0;
        var timer = window.setInterval(function(){
            attempts++;
            repairIcons();
            if (attempts > 30) {
                window.clearInterval(timer);
            }
        }, 100);

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', repairIcons);
        } else {
            repairIcons();
        }

        if (window.MutationObserver) {
            new MutationObserver(function(){ repairIcons(); }).observe(document.documentElement, { childList: true, subtree: true });
        }
    })();
    </script>
    <?php
}
add_action( 'wp_footer', 'rup_sc_sdtm_output_fse_native_icon_repair', 100 );


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
    if ( empty( $tabs ) || ! is_array( $tabs ) ) {
        return;
    }

    $rules = '';

    foreach ( $tabs as $tab ) {
        $slug       = ! empty( $tab['slug'] ) ? sanitize_title( $tab['slug'] ) : sanitize_title( $tab['name'] );
        $icon_value = isset( $tab['icon'] ) ? trim( (string) $tab['icon'] ) : '';

        // Only uploaded/custom image icons need generated CSS. Native icon names
        // must be left alone so SureCart's own <sc-icon> SVG renderer can hydrate
        // Dashboard, Orders, Invoices, Plans, Downloads, Back Home, and account icons.
        if ( empty( $icon_value ) || ! filter_var( $icon_value, FILTER_VALIDATE_URL ) ) {
            continue;
        }

        $icon_attr = 'dashy-' . $slug;
        $icon_url  = esc_url( $icon_value );

        $rules .= "sc-icon[name=\"" . esc_attr( $icon_attr ) . "\"] {\n";
        $rules .= "    display: inline-block;\n";
        $rules .= "    width: 20px;\n";
        $rules .= "    height: 20px;\n";
        $rules .= "    min-width: 20px;\n";
        $rules .= "    background: url('" . $icon_url . "') no-repeat center center;\n";
        $rules .= "    background-size: contain;\n";
        $rules .= "    color: transparent;\n";
        $rules .= "}\n";
        $rules .= "sc-icon[name=\"" . esc_attr( $icon_attr ) . "\"]::part(svg),\n";
        $rules .= "sc-icon[name=\"" . esc_attr( $icon_attr ) . "\"] svg {\n";
        $rules .= "    display: none !important;\n";
        $rules .= "}\n";
    }

    if ( empty( $rules ) ) {
        return;
    }

    echo '<style type="text/css" id="dashy-surecart-custom-icon-styles">' . "\n";
    echo $rules; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Rules are escaped while generated above.
    echo '</style>' . "\n";
}

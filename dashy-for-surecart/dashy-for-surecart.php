<?php
/**
 * Plugin Name:         Dashy For SureCart
 * Description:         Easily add Dashboard Tabs to SureCart with Dashy, Custom Icons, Page/Post or Custom Post Type or just load a shortcode the easy way
 * Tested up to:        6.7.2
 * Requires at least:   6.5
 * Requires PHP:        8.0
 * Version:             1.21
 * Author:              ReallyUsefulPlugins.com
 * Author URI:          https://Reallyusefulplugins.com
 * License:             GPL-2.0-or-later
 * License URI:         https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:         dashy-for-surecart
 * Website:             https://reallyusefulplugins.com
 */
namespace rupdashextendersc\SureCartDashboard {
    if ( ! defined( 'ABSPATH' ) ) {
        exit; // Exit if accessed directly.
    }

    if ( ! defined( 'RUPDASHEXTENDERSC_TEMPLATE_LOADED' ) ) {
        define( 'RUPDASHEXTENDERSC_TEMPLATE_LOADED', true );

        /**
         * Override the SureCart dashboard template if the current page slug matches the designated dashboard slug.
         *
         * By default, this function checks if the current page's slug is 'customer-dashboard'.
         * However, if the constant RUPDASHEXTENDERSC_TEMPLATE_URL is defined and not empty,
         * its value is used as the dashboard slug instead.
         *
         * @param string $template The original template file.
         * @return string The custom dashboard template if the condition is met, or the original template.
         */
        function override_surecart_dashboard_template( $template ) {
            // Set default dashboard slug.
            $dashboard_slug = 'customer-dashboard';

            // Override the default slug if a custom slug is defined via constant.
            if ( defined( 'RUPDASHEXTENDERSC_TEMPLATE_URL' ) && ! empty( RUPDASHEXTENDERSC_TEMPLATE_URL ) ) {
                $dashboard_slug = RUPDASHEXTENDERSC_TEMPLATE_URL;
            }
            
            // Check if the current page matches the dashboard slug.
            if ( is_page( $dashboard_slug ) ) {
                // Build the path to the custom dashboard template.
                $custom_template = plugin_dir_path( __FILE__ ) . 'templates/my-surecart-dashboard.php';
                // If the custom template exists, use it.
                if ( file_exists( $custom_template ) ) {
                    return $custom_template;
                }
            }
            // Otherwise, return the original template.
            return $template;
        }
        add_filter( 'template_include', __NAMESPACE__ . '\\override_surecart_dashboard_template', 9999 );
    }
}

namespace {
    function initialize_plugin_update_checker() {
        // Ensure the required function is available.
        if ( ! function_exists( 'get_plugin_data' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        // Get the plugin data from the header.
        $plugin_data = get_plugin_data( __FILE__ );
        
        // Build the constant name prefix using the Text Domain.
        $prefix = 'rup_' . $plugin_data['TextDomain'];

        // Define the constants and their corresponding values.
        $constants = array(
            '_version'         => $plugin_data['Version'],
            '_slug'            => $plugin_data['TextDomain'],
            '_main_file'       => __FILE__,
            '_dir'             => plugin_dir_path( __FILE__ ),
            '_url'             => plugin_dir_url( __FILE__ ),
            '_access_key'      => 'V29Ay8bKd753AdHdeemVZA77UjbmzZMNk',     // Replace with your access key.
            '_server_location' => 'https://updater.reallyusefulplugins.com/gateway.php'
        );

        // Loop through the array and define each constant dynamically.
        foreach ( $constants as $suffix => $value ) {
            if ( ! defined( $prefix . $suffix ) ) {
                define( $prefix . $suffix, $value );
            }
        }

        // Retrieve the dynamic constants for easier reference.
        $version         = constant($prefix . '_version');
        $slug            = constant($prefix . '_slug');
        $main_file       = constant($prefix . '_main_file');
        $dir             = constant($prefix . '_dir');
        $url             = constant($prefix . '_url');
        $access_key      = constant($prefix . '_access_key');
        $server_location = constant($prefix . '_server_location');

        // Build the update server URL dynamically.
        $updateserver = $server_location . '?key=' . $access_key . '&action=get_metadata&slug=' . $slug;

        // Include the update checker.
        require_once $dir . 'plugin-update-checker/plugin-update-checker.php';

        // Use the fully qualified class name to build the update checker.
        $my_plugin_update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
            $updateserver,
            $main_file,
            $slug
        );
    }

    add_action( 'init', 'initialize_plugin_update_checker' );
}


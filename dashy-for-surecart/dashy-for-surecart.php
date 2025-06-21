<?php
/**
 * Plugin Name:       Dashy For SureCart
 * Description:       Easily add Dashboard Tabs to SureCart with Dashy, Custom Icons, Page/Post or Custom Post Type or just load a shortcode the easy way
 * Tested up to:      6.8.1
 * Requires at least: 6.5
 * Requires PHP:      8.0
 * Version:           1.25
 * Author:            ReallyUsefulPlugins.com
 * Author URI:        https://Reallyusefulplugins.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       dashy-for-surecart
 * Website:           https://reallyusefulplugins.com
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

    define('RUP_SC_D4SC_VERSION', '1.25');
    
    function register_plugin_updater() {
    // 1) Load the universal drop-in.
    require_once __DIR__ . '/inc/updater.php';

    // 2) Build the updater config array.
    $updater_config = [
        'plugin_file' => plugin_basename( __FILE__ ),
        'slug'        => 'dashy-for-surecart',
        'name'        => 'Dashy For SureCart',
        'version'     => RUP_SC_D4SC_VERSION,
        'key'         => 'CeW5jUv66xCMVZd83QTema',
        'server'      => 'https://raw.githubusercontent.com/stingray82/dashy-for-surecart/main/uupd/index.json',
    ];

    // 3) Register with the updater.
    \UUPD\V1\UUPD_Updater_V1::register( $updater_config );
}

// Hook into plugins_loaded with priority 1
add_action( 'plugins_loaded', 'register_plugin_updater', 1 );


   
}


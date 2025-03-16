<?php
/**
 * Plugin Name:       dashy for surecart
 * Description:       Easily add Dashboard Tabs to SureCart with Dashy, Custom Icons, Page/Post or Custom Post Type or just load a shortcode the easy way
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Version:           1.12
 * Author:            ReallyUsefulPlugins.com
 * Author URI: https://Reallyusefulplugins.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       dashy_for_surecart
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
    // Running in Global Namespace
    $plugin_prefix = 'DASHYFORSURECART';

    // Extract the version number
    $plugin_data = get_file_data(__FILE__, ['Version' => 'Version']);

    // Plugin Constants
    define($plugin_prefix . '_DIR', plugin_basename(__DIR__));
    define($plugin_prefix . '_BASE', plugin_basename(__FILE__));
    define($plugin_prefix . '_PATH', plugin_dir_path(__FILE__));
    define($plugin_prefix . '_VER', $plugin_data['Version']);
    define($plugin_prefix . '_CACHE_KEY', 'dashy_for_surecart-cache-key-for-plugin');
    define($plugin_prefix . '_REMOTE_URL', 'https://reallyusefulplugins.com/wp-content/plugins/hoster/inc/secure-download.php?file=json&download=691&token=d72a80a5744d65f665d041574fea997b6297b6cfbafdd66a4476d1c772053850');

    require constant($plugin_prefix . '_PATH') . 'inc/update.php';
    require constant($plugin_prefix . '_PATH') . 'inc/tab-extender.php';

    new DASHYFORSURECART_DPUpdateChecker(
        constant($plugin_prefix . '_BASE'),
        constant($plugin_prefix . '_VER'),
        constant($plugin_prefix . '_CACHE_KEY'),
        constant($plugin_prefix . '_REMOTE_URL')
    );

    // Optionally, you can also keep the ABSPATH check here.
    if ( ! defined( 'ABSPATH' ) ) {
        exit;
    }

    
}

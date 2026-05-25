<?php
/**
 * Plugin Name:       Dashy For SureCart
 * Description:       Easily add Dashboard Tabs to SureCart with Dashy, Custom Icons, Page/Post or Custom Post Type or just load a shortcode the easy way
 * Tested up to:      7.0
 * Requires at least: 6.5
 * Requires PHP:      8.0
 * Version:           1.30.0
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
        /**
         * Return the configured SureCart customer dashboard slug/path.
         *
         * Block/FSE themes can resolve pages differently from classic themes, so we
         * normalise the slug and use it in several detection fallbacks below.
         *
         * @return string
         */
        function get_dashboard_slug() {
            $dashboard_slug = 'customer-dashboard';

            if ( defined( 'RUPDASHEXTENDERSC_TEMPLATE_URL' ) && ! empty( RUPDASHEXTENDERSC_TEMPLATE_URL ) ) {
                $dashboard_slug = RUPDASHEXTENDERSC_TEMPLATE_URL;
            }

            return trim( (string) apply_filters( 'rup_sc_d4sc_dashboard_slug', $dashboard_slug ), '/' );
        }

        /**
         * Detect the SureCart dashboard request in both classic and FSE/block themes.
         *
         * is_page() is not always reliable once a block theme's template resolution and
         * query loop are involved, so this also checks queried object/page path, request
         * path and common query vars.
         *
         * @return bool
         */
        function is_dashboard_request() {
            if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
                return false;
            }

            $dashboard_slug = get_dashboard_slug();

            if ( empty( $dashboard_slug ) ) {
                return false;
            }

            if ( is_page( $dashboard_slug ) ) {
                return true;
            }

            $queried = get_queried_object();
            if ( $queried instanceof \WP_Post ) {
                $page_uri = trim( (string) get_page_uri( $queried ), '/' );
                if ( $page_uri === $dashboard_slug || basename( $page_uri ) === basename( $dashboard_slug ) ) {
                    return true;
                }
            }

            $pagename = get_query_var( 'pagename' );
            if ( ! empty( $pagename ) && trim( (string) $pagename, '/' ) === $dashboard_slug ) {
                return true;
            }

            $request_path = isset( $_SERVER['REQUEST_URI'] ) ? strtok( wp_unslash( $_SERVER['REQUEST_URI'] ), '?' ) : '';
            $request_path = trim( rawurldecode( (string) $request_path ), '/' );
            $home_path    = trim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );

            if ( $home_path && str_starts_with( $request_path, $home_path ) ) {
                $request_path = trim( substr( $request_path, strlen( $home_path ) ), '/' );
            }

            return $request_path === $dashboard_slug || basename( $request_path ) === basename( $dashboard_slug );
        }

        function override_surecart_dashboard_template( $template ) {
            if ( is_dashboard_request() ) {
                $custom_template = plugin_dir_path( __FILE__ ) . 'templates/my-surecart-dashboard.php';
                if ( file_exists( $custom_template ) ) {
                    return $custom_template;
                }
            }

            return $template;
        }
        add_filter( 'template_include', __NAMESPACE__ . '\\override_surecart_dashboard_template', PHP_INT_MAX );
    }
}

namespace {

    define('RUP_SC_D4SC_VERSION', '1.30');
    
    function register_plugin_updater() {
    // 1) Load the universal drop-in.
    require_once __DIR__ . '/inc/updater.php';

    // 2) Build the updater config array.
    $updater_config = [
    	'vendor'      => 'RUP',
        'plugin_file' => plugin_basename( __FILE__ ),
        'slug'        => 'dashy-for-surecart',
        'name'        => 'Dashy For SureCart',
        'version'     => RUP_SC_D4SC_VERSION,
        'key'         => 'CeW5jUv66xCMVZd83QTema',
        'server'      => 'https://raw.githubusercontent.com/stingray82/dashy-for-surecart/main/uupd/index.json',
    ];

    // 3) Register with the updater.
    \RUP\Updater\Updater_V2::register( $updater_config );
}

// Hook into plugins_loaded with priority 1
add_action( 'plugins_loaded', 'register_plugin_updater', 20 );

// MainWP Icon Filter
add_filter('mainwp_child_stats_get_plugin_info', function($info, $slug) {

    if ('dashy-for-surecart/dashy-for-surecart.php' === $slug) {
        $info['icon'] = 'https://raw.githubusercontent.com/stingray82/dashy-for-surecart/main/uupd/icon-128.png'; // Supported types: jpeg, jpg, gif, ico, png
    }

    return $info;

}, 10, 2);




require_once __DIR__ . '/inc/tab-extender.php';


   
}



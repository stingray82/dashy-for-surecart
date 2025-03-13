<?php
/*
Template Name: Custom Dashboard with Combined Filters
*/

// Global block: define the constant if not already defined.
namespace {
    if ( ! defined( 'RUPDASHEXTENDERSC_TEMPLATE_LOADED' ) ) {
        define( 'RUPDASHEXTENDERSC_TEMPLATE_LOADED', true );
    }
}

// Now, enter your custom namespace using a bracketed declaration.
namespace rupdashextendersc\SureCartDashboard {

    use SureCart\Controllers\Web\DashboardController;

    if ( ! defined( 'ABSPATH' ) ) {
        exit; // Exit if accessed directly.
    }

    $controller = new DashboardController();
    $data       = $controller->getData();

    // If a query parameter "sc-page" is present, override the active_tab.
    if ( isset( $_GET['sc-page'] ) ) {
        $data['active_tab'] = sanitize_text_field( $_GET['sc-page'] );
    }

    // Capture original navigation keys (from SureCart before any custom modifications)
    $surecart_navigation_tabs = array_keys( $data['navigation'] );

    // -- Apply the Surelywp filters first --
    $controller         = apply_filters( 'surelywp_surecart_customer_dashboard_controller', $controller, $data );
    $data               = apply_filters( 'surelywp_surecart_customer_dashboard_data', $data, $controller );
    $data['navigation'] = apply_filters( 'surelywp_surecart_navigation', $data['navigation'], $data, $controller );

    // Determine custom tabs added by Surelywp:
    $custom_tabs = array_diff( array_keys( $data['navigation'] ), $surecart_navigation_tabs );

    // -- Then apply our own custom filters --
    $controller         = apply_filters( 'rup_sc_dashextender_surecart_dashboard_controller', $controller, $data );
    $data               = apply_filters( 'rup_sc_dashextender_surecart_dashboard_data', $data, $controller );
    $data['navigation'] = apply_filters( 'rup_sc_dashextender_surecart_navigation', $data['navigation'], $data, $controller );

    // Now, the active tab should be set in $data['active_tab']
    $active_tab = $data['active_tab'];
    ?>
    <!DOCTYPE html>
    <html <?php language_attributes(); ?>>
    <head>
        <meta charset="<?php bloginfo( 'charset' ); ?>" />
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <?php wp_head(); ?>
    </head>
    <body <?php body_class( is_user_logged_in() ? 'has-columns' : '' ); ?>>
        <?php wp_body_open(); ?>

        <?php do_action( 'surecart_template_dashboard_body_open' ); ?>

        <?php if ( ! is_user_logged_in() ) : ?>
            <div class="sc-dashboard__logged-out">
                <div class="sc-dashboard__logged-out-content">
                    <header class="sc-dashboard__logged-out-header">
                        <a href="<?php echo esc_url( apply_filters( 'sc_customer_dashboard_store_logo_url', get_home_url() ) ); ?>">
                            <?php \SureCart::render( 'layouts/partials/store-logo', $data ); ?>
                        </a>
                    </header>
                    <div class="sc-dashboard__logged-out-content">
                        <?php while ( have_posts() ) : the_post(); the_content(); endwhile; ?>
                    </div>
                </div>
            </div>
        <?php else : ?>
            <div class="sc-dashboard">
                <div class="sc-dashboard__column dashboard-left is-sticky">
                    <header class="sc-dashboard__header">
                        <div class="sc-dashboard__logo">
                            <a href="<?php echo esc_url( apply_filters( 'sc_customer_dashboard_store_logo_url', get_home_url() ) ); ?>"
                               aria-label="<?php esc_attr_e( 'Site logo', 'surecart' ); ?>">
                                <?php \SureCart::render( 'layouts/partials/store-logo', $data ); ?>
                            </a>
                        </div>
                        <?php \SureCart::render( 'layouts/partials/customer-dashboard/dashboard-navigation', $data ); ?>
                    </header>

                    <header class="sc-dashboard__header-mobile">
                        <div class="sc-dashboard__logo">
                            <?php \SureCart::render( 'layouts/partials/store-logo', $data ); ?>
                        </div>
                        <sc-dropdown class="sc-dashboard__mobile-menu" placement="bottom-right" distance="20">
                            <sc-button circle slot="trigger" role="button" tabindex="0">
                                <sc-icon name="menu"></sc-icon>
                            </sc-button>
                            <?php
                            \SureCart::render(
                                'layouts/partials/customer-dashboard/dashboard-navigation',
                                array_merge(
                                    $data,
                                    array(
                                        'is_mobile'    => true,
                                        'show_account' => true,
                                    )
                                )
                            );
                            ?>
                        </sc-dropdown>
                    </header>

                    <div class="sc-dashboard__back sc-pin-bottom">
                        <sc-button href="<?php echo esc_url( apply_filters( 'sc_customer_dashboard_back_home_url', get_home_url() ) ); ?>"
                                   type="text" class="sc-link-home"
                                   aria-label="<?php esc_attr_e( 'Back Home', 'surecart' ); ?>"
                                   role="button">
                            <sc-icon name="arrow-left" slot="prefix"></sc-icon>
                            <?php echo esc_html( apply_filters( 'sc_customer_dashboard_back_home_text', __( 'Back Home', 'surecart' ) ) ); ?>
                        </sc-button>
                    </div>

                    <div class="sc-dashboard__user-menu">
                        <sc-dropdown style="width: 100%;" placement="top-right" distance="20"
                                     role="select" aria-multiselectable="false">
                            <sc-flex class="sc-dashboard__menu-trigger" align-items="center" justify-content="space-between"
                                      slot="trigger" tabindex="0"
                                      aria-label="<?php esc_attr_e( 'Open or close account menu', 'surecart' ); ?>"
                                      role="button">
                                <sc-flex class="sc-dashboard__menu-user" align-items="center" justify-content="space-between">
                                    <sc-avatar image="<?php echo esc_url( get_avatar_url( $data['user']->user_email, array( 'default' => '404' ) ) ); ?>"
                                               style="--sc-avatar-size: 34px"
                                               initials="<?php echo esc_attr( substr( $data['user']->display_name, 0, 1 ) ); ?>"></sc-avatar>
                                    <span class="sc-dashboard__user-menu-name"><?php echo esc_html( $data['user']->display_name ); ?></span>
                                </sc-flex>
                                <sc-icon name="chevron-up"></sc-icon>
                            </sc-flex>

                            <sc-menu tabindex="0">
                                <?php foreach ( $data['account_navigation'] as $navigation_item ) : ?>
                                    <?php
                                    \SureCart::render(
                                        'layouts/partials/customer-dashboard/dashboard-menu-item',
                                        array(
                                            'icon_name' => $navigation_item['icon_name'],
                                            'name'      => $navigation_item['name'],
                                            'active'    => $navigation_item['active'],
                                            'href'      => $navigation_item['href'],
                                            'is_mobile' => true,
                                        )
                                    );
                                    ?>
                                <?php endforeach; ?>

                                <?php if ( ! empty( $data['account_navigation'] ) && ! empty( $data['logout_link'] ) ) : ?>
                                    <sc-menu-divider></sc-menu-divider>
                                <?php endif; ?>

                                <?php if ( ! empty( $data['logout_link'] ) ) : ?>
                                    <sc-menu-item href="<?php echo esc_url( $data['logout_link'] ); ?>" role="option">
                                        <sc-icon name="log-out" slot="prefix" style="opacity: 0.65;"></sc-icon>
                                        <?php echo esc_html_e( 'Log Out', 'surecart' ); ?>
                                    </sc-menu-item>
                                <?php endif; ?>
                            </sc-menu>
                        </sc-dropdown>
                    </div>
                </div>

                <div class="sc-dashboard__column dashboard-right">
                    <?php
                    // Build a dynamic hook for our custom content.
                    $custom_hook = 'rup_sc_dashextender_surecart_dashboard_right_' . sanitize_key( $active_tab );

                    // Capture output from the Surelywp hook.
                    ob_start();
                    do_action( 'surelywp_surecart_dashboard_right', $controller, $data );
                    $surelywp_output = ob_get_clean();

                    // Capture output from our custom hook.
                    ob_start();
                    do_action( $custom_hook, $controller, $data );
                    $custom_output = ob_get_clean();

                    // If either hook produced output, echo it. Otherwise, display the default SureCart content.
                    if ( ! empty( $surelywp_output ) ) {
                        echo $surelywp_output;
                    } elseif ( ! empty( $custom_output ) ) {
                        echo $custom_output;
                    } else {
                        // In case the main loop was partially consumed, rewind it.
                        rewind_posts();
                        while ( have_posts() ) {
                            the_post();
                            the_content();
                        }
                    }
                    ?>
                </div>
            </div>
        <?php endif; ?>

        <?php wp_footer(); ?>
    </body>
    </html>
<?php
} // End of namespace block for rupdashextendersc\SureCartDashboard.

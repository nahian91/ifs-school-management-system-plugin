<?php
/**
 * Institutional Settings Main Controller & Modular Loader
 * File: inc/settings.php
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// --------------------------------------------------------------------------
// 1. Load All Settings Sub-Modules Securely
// --------------------------------------------------------------------------
$educore_settings_submodules = array(
    'general',
    'prefixes',
    'academics',
    'fees',
    'permissions',
);

foreach ( $educore_settings_submodules as $submodule ) {
    $submodule_file = EDUCORE_PATH . 'inc/settings/' . $submodule . '.php';
    if ( file_exists( $submodule_file ) ) {
        require_once $submodule_file;
    }
}

// --------------------------------------------------------------------------
// 2. Main Tab Router Interface
// --------------------------------------------------------------------------
function educore_settings_tab() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to access institutional settings.', 'ifsedu-school-management' ) );
    }

    // Media uploader dependencies
    wp_enqueue_media();

    $allowed_sub_tabs = array( 'general', 'prefixes', 'academics', 'fees', 'permissions' );

    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $raw_subtab    = isset( $_GET['subtab'] ) ? sanitize_key( wp_unslash( $_GET['subtab'] ) ) : 'general';
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    $active_subtab = in_array( $raw_subtab, $allowed_sub_tabs, true ) ? $raw_subtab : 'general';

    $base_admin_url = admin_url( 'admin.php' );
    $base_url       = add_query_arg( array( 'page' => 'school_management_system', 'tab' => 'settings' ), $base_admin_url );
    ?>
    <div class="ifs-educore-settings-root">

        <!-- Top Settings Sub-Navigation -->
        <div class="ifs-educore-subnav">
            <a href="<?php echo esc_url( add_query_arg( 'subtab', 'general', $base_url ) ); ?>" class="ifs-educore-subnav-link <?php echo 'general' === $active_subtab ? 'active' : ''; ?>">
                <span class="dashicons dashicons-admin-generic"></span>
                <?php esc_html_e( 'General Settings', 'ifsedu-school-management' ); ?>
            </a>
            <a href="<?php echo esc_url( add_query_arg( 'subtab', 'prefixes', $base_url ) ); ?>" class="ifs-educore-subnav-link <?php echo 'prefixes' === $active_subtab ? 'active' : ''; ?>">
                <span class="dashicons dashicons-id-alt"></span>
                <?php esc_html_e( 'ID Prefix Settings', 'ifsedu-school-management' ); ?>
            </a>
            <a href="<?php echo esc_url( add_query_arg( 'subtab', 'academics', $base_url ) ); ?>" class="ifs-educore-subnav-link <?php echo 'academics' === $active_subtab ? 'active' : ''; ?>">
                <span class="dashicons dashicons-welcome-learn-more"></span>
                <?php esc_html_e( 'Academic Setup', 'ifsedu-school-management' ); ?>
            </a>
            <a href="<?php echo esc_url( add_query_arg( 'subtab', 'fees', $base_url ) ); ?>" class="ifs-educore-subnav-link <?php echo 'fees' === $active_subtab ? 'active' : ''; ?>">
                <span class="dashicons dashicons-money-alt"></span>
                <?php esc_html_e( 'Fees Settings', 'ifsedu-school-management' ); ?>
            </a>
            <a href="<?php echo esc_url( add_query_arg( 'subtab', 'permissions', $base_url ) ); ?>" class="ifs-educore-subnav-link <?php echo 'permissions' === $active_subtab ? 'active' : ''; ?>">
                <span class="dashicons dashicons-lock"></span>
                <?php esc_html_e( 'User Roles & Permissions', 'ifsedu-school-management' ); ?>
            </a>
        </div>

        <!-- Render Target Sub-Tab Component -->
        <?php
        if ( 'prefixes' === $active_subtab && function_exists( 'educore_render_settings_prefixes_view' ) ) {
            educore_render_settings_prefixes_view( $base_url );
        } elseif ( 'academics' === $active_subtab && function_exists( 'educore_render_settings_academics_view' ) ) {
            educore_render_settings_academics_view( $base_url );
        } elseif ( 'fees' === $active_subtab && function_exists( 'educore_render_settings_fees_view' ) ) {
            educore_render_settings_fees_view( $base_url );
        } elseif ( 'permissions' === $active_subtab && function_exists( 'educore_render_settings_permissions_view' ) ) {
            educore_render_settings_permissions_view( $base_url );
        } elseif ( function_exists( 'educore_render_settings_general_view' ) ) {
            educore_render_settings_general_view( $base_url );
        }
        ?>

    </div>
    <?php
}
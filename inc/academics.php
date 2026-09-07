<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Main Academic Configuration Module
 * File: academic.php
 * Theme Aesthetic: Elite Neo-Bento UI Router
 * Custom Prefixes Applied: dpt-, afdp-
 */
function educore_academics_tab() {
    // 1. Security & Capability Check
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to access this module.', 'ifsedu-school-management' ) );
    }

    $allowed_sub_tabs = array( 'units', 'subjects', 'teacher_subjects', 'routine', 'available_teachers' );

    // 2. Set Base Router Variables
    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $raw_subtab = isset( $_GET['subtab'] ) ? sanitize_key( wp_unslash( $_GET['subtab'] ) ) : 'units';
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    $current_subtab = in_array( $raw_subtab, $allowed_sub_tabs, true ) ? $raw_subtab : 'units';
    $base_url       = admin_url( 'admin.php?page=school_management_system&tab=academics' );

    // 3. Include Shared Academic Header, Styles & Subtab Navigation
    require_once plugin_dir_path( __FILE__ ) . 'academics/academic-header.php';

    // 4. Clean Sub-Router Controller Switch
    switch ( $current_subtab ) {
        
        case 'units':
            require_once plugin_dir_path( __FILE__ ) . 'academics/academic-units.php';
            break;

        case 'subjects':
            require_once plugin_dir_path( __FILE__ ) . 'academics/academic-subjects.php';
            break;

        case 'teacher_subjects':
            require_once plugin_dir_path( __FILE__ ) . 'academics/academic-teacher-subjects.php';
            break;

        case 'routine':
            require_once plugin_dir_path( __FILE__ ) . 'academics/academic-routine.php';
            break;

        case 'available_teachers':
            require_once plugin_dir_path( __FILE__ ) . 'academics/academic-available-teachers.php';
            break;

        default:
            ?>
            
            <div class="dpt-bento-card afdp-bento-error-card">
                <span class="dashicons dashicons-warning afdp-error-icon"></span>
                <h4 class="afdp-error-title"><?php esc_html_e( 'Academic Module Not Found', 'ifsedu-school-management' ); ?></h4>
                <p class="afdp-error-desc"><?php esc_html_e( 'The requested subtab view does not exist or has been relocated.', 'ifsedu-school-management' ); ?></p>
            </div>
            <?php
            break;
    }

    // 5. Close Root Wrapper Opened in academic-header.php
    echo '</div>'; 
}
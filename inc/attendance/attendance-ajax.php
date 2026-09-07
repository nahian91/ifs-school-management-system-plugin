<?php
/**
 * Attendance Dynamic Dropdowns AJAX Handlers
 * File: inc/attendance/attendance-ajax.php
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Direct access safety buffer
}

// Dynamically load Sections based on Class selection
add_action( 'wp_ajax_ifs_educore_get_sections_by_class_attendance', 'ifs_educore_get_sections_by_class_attendance_handler' );
function ifs_educore_get_sections_by_class_attendance_handler() {
    check_ajax_referer( 'ifs_educore_attendance_nonce', 'security' );

    // Allow Administrators, Teachers, and Staff who can edit posts or manage options
    if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( array( 'message' => esc_html__( 'Permission denied.', 'ifsedu-school-management' ) ) );
    }

    global $wpdb;
    $table_units = $wpdb->prefix . 'sms_academic_units';
    $class_name  = isset( $_POST['class_name'] ) ? sanitize_text_field( wp_unslash( $_POST['class_name'] ) ) : '';

    if ( empty( $class_name ) ) {
        wp_send_json_success( array() );
    }

    $clean_class = trim( str_ireplace( 'Class ', '', $class_name ) );

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $sections = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT DISTINCT section_name FROM `{$table_units}` WHERE (class_name = %s OR class_name = %s) AND section_name != '' ORDER BY section_name ASC",
            $class_name,
            $clean_class
        )
    );
    // phpcs:enable

    if ( ! empty( $sections ) && is_array( $sections ) ) {
        usort( $sections, 'strnatcasecmp' );
    }

    wp_send_json_success( is_array( $sections ) ? $sections : array() );
}
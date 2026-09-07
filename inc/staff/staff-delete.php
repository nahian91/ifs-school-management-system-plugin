<?php
/**
 * Staff Member Deletion Handler
 * File: inc/staff/staff-delete.php
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function educore_staff_delete_action() {
    global $wpdb;

    // 1. Strict Security & Capability Verification
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have permission to delete staff profiles.', 'ifsedu-school-management' ) );
    }

    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $staff_id = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0;
    $nonce    = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
    // phpcs:enable WordPress.Security.NonceVerification.Recommended
    
    // Security check
    if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'delete_staff_' . $staff_id ) ) {
        wp_die( esc_html__( 'Security check failed. You do not have permission to delete this record.', 'ifsedu-school-management' ) );
    }

    if ( $staff_id > 0 ) {
        $table_name = $wpdb->prefix . 'sms_staff';
        
        // Log activity before deletion
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $staff = $wpdb->get_row( $wpdb->prepare( "SELECT full_name FROM `{$table_name}` WHERE id = %d LIMIT 1", $staff_id ) );
        // phpcs:enable

        if ( $staff && function_exists( 'educore_log_activity' ) ) {
            /* translators: %s: Staff full name */
            educore_log_activity( sprintf( __( 'Deleted staff record: %s', 'ifsedu-school-management' ), $staff->full_name ) );
        }

        // Execute Delete
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $wpdb->delete( $table_name, array( 'id' => $staff_id ), array( '%d' ) );
        // phpcs:enable
    }

    // Redirect safely back to the list with a status notification
    $redirect_url = add_query_arg(
        array(
            'page' => 'school_management_system',
            'tab'  => 'staff',
            'sub'  => 'list',
            'msg'  => 'deleted',
        ),
        admin_url( 'admin.php' )
    );

    if ( ! headers_sent() ) {
        wp_safe_redirect( $redirect_url );
        exit;
    } else {
        echo '<script type="text/javascript">window.location.href=' . wp_json_encode( esc_url_raw( $redirect_url ) ) . ';</script>';
        exit;
    }
}
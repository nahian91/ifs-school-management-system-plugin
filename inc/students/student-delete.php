<?php
/**
 * Student Deletion Action Handler
 * File: student-delete-action.php
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

function educore_student_delete_action() {
    global $wpdb;

    // 1. Capability Check
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'ifsedu-school-management' ) );
    }

    // 2. Get ID properly
    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $raw_id = isset( $_GET['id'] ) ? sanitize_text_field( wp_unslash( $_GET['id'] ) ) : '';
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    $redirect_url = add_query_arg(
        array(
            'page' => 'school_management_system',
            'tab'  => 'students',
            'sub'  => 'list',
        ),
        admin_url( 'admin.php' )
    );

    if ( empty( $raw_id ) ) {
        ifs_educore_safe_redirect_helper( $redirect_url );
        exit;
    }

    // 3. Security Nonce Check
    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'delete_student_' . $raw_id ) ) {
        wp_die( esc_html__( 'Security check failed. You do not have permission to delete this record.', 'ifsedu-school-management' ) );
    }

    $table_students   = $wpdb->prefix . 'sms_students';
    $table_attendance = $wpdb->prefix . 'sms_attendance';
    $table_fees       = $wpdb->prefix . 'sms_fees';
    $table_results    = $wpdb->prefix . 'sms_results';

    // 4. Fetch Student Record
    if ( is_numeric( $raw_id ) ) {
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $student = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, student_id, full_name FROM `{$table_students}` WHERE id = %d LIMIT 1",
                absint( $raw_id )
            )
        );
        // phpcs:enable
    } else {
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $student = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, student_id, full_name FROM `{$table_students}` WHERE student_id = %s LIMIT 1",
                $raw_id
            )
        );
        // phpcs:enable
    }

    if ( $student ) {
        $student_db_id = absint( $student->id );

        // 5. Transactional Cascade Deletion
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query( 'START TRANSACTION' );

        try {
            // Delete dependent records across modules
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->delete( $table_attendance, array( 'student_id' => $student_db_id ), array( '%d' ) );
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->delete( $table_fees, array( 'student_id' => $student_db_id ), array( '%d' ) );
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->delete( $table_results, array( 'student_id' => $student_db_id ), array( '%d' ) );

            // Delete primary student record
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $deleted = $wpdb->delete( $table_students, array( 'id' => $student_db_id ), array( '%d' ) );

            if ( false === $deleted ) {
                throw new Exception( __( 'Failed to remove student record from database.', 'ifsedu-school-management' ) );
            }

            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->query( 'COMMIT' );

            if ( function_exists( 'educore_log_activity' ) ) {
                /* translators: 1: Student Full Name, 2: Student ID */
                educore_log_activity( sprintf( __( 'Deleted student record: %1$s (%2$s) and associated logs', 'ifsedu-school-management' ), $student->full_name, $student->student_id ) );
            }

            $redirect_url = add_query_arg( 'msg', 'deleted', $redirect_url );
        } catch ( Exception $e ) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->query( 'ROLLBACK' );
            wp_die( esc_html( $e->getMessage() ) );
        }
    }

    // 6. Safe Hybrid Redirection
    ifs_educore_safe_redirect_helper( $redirect_url );
    exit;
}

/**
 * Safe redirect invoker to prevent redeclaration & fatal errors
 */
if ( ! function_exists( 'ifs_educore_safe_redirect_helper' ) ) {
    function ifs_educore_safe_redirect_helper( $url ) {
        if ( function_exists( 'educore_safe_redirect' ) ) {
            educore_safe_redirect( $url );
        } elseif ( ! headers_sent() ) {
            wp_safe_redirect( $url );
            exit;
        } else {
            echo '<script type="text/javascript">window.location.href=' . wp_json_encode( esc_url_raw( $url ) ) . ';</script>';
            echo '<noscript><meta http-equiv="refresh" content="0;url=' . esc_url( $url ) . '" /></noscript>';
            exit;
        }
    }
}
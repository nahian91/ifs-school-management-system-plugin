<?php
/**
 * Accounting Entry Deletion Handler
 * File: accounting-delete.php
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Direct access safety buffer
}

function educore_accounting_delete_handler() {
    global $wpdb;

    $current_user = wp_get_current_user();
    $roles        = (array) $current_user->roles;

    $is_admin      = current_user_can( 'manage_options' );
    $is_accountant = in_array( 'accountant', $roles, true ) || current_user_can( 'edit_posts' );

    if ( ! $is_admin && ! $is_accountant ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to delete accounting records.', 'ifsedu-school-management' ) );
    }

    $table_accounting = $wpdb->prefix . 'sms_accounting';

    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $delete_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
    $nonce     = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    if ( $delete_id > 0 && ! empty( $nonce ) && wp_verify_nonce( $nonce, 'delete_acct_' . $delete_id ) ) {
        
        // Fetch record title & voucher for audit trail
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $entry = $wpdb->get_row( $wpdb->prepare( "SELECT voucher_no, title, amount FROM `{$table_accounting}` WHERE id = %d LIMIT 1", $delete_id ) );

        if ( $entry ) {
            if ( function_exists( 'educore_log_activity' ) ) {
                /* translators: 1: Voucher number, 2: Voucher title, 3: Amount */
                educore_log_activity( sprintf( __( 'Deleted Accounting Ledger Entry: (%1$s) %2$s - Amount: %3$.2f', 'ifsedu-school-management' ), $entry->voucher_no, $entry->title, $entry->amount ) );
            }

            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $wpdb->delete( $table_accounting, array( 'id' => $delete_id ), array( '%d' ) );
            // phpcs:enable
        }

        $redirect_url = add_query_arg(
            array(
                'page' => 'school_management_system',
                'tab'  => 'accounting',
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
    } else {
        wp_die( esc_html__( 'Security check failed or invalid transaction entry ID.', 'ifsedu-school-management' ) );
    }
}
<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Notice & Event Deletion Handler
 */
function educore_notice_events_delete_action( $type = 'notice' ) {
    global $wpdb;
    $table_notices = $wpdb->prefix . 'sms_notices';

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Permission denied.', 'ifsedu-school-management' ) );
    }

    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $id     = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
    $_nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    if ( $id > 0 && wp_verify_nonce( $_nonce, 'delete_item_' . $id ) ) {
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->delete( $table_notices, array( 'id' => $id ), array( '%d' ) );
        // phpcs:enable
    }

    $target_url = admin_url( 'admin.php?page=school_management_system&tab=notices&type=' . ( ( $type === 'events' || $type === 'event' ) ? 'events' : 'notice' ) . '&sub=list' );
    educore_safe_redirect( $target_url );
}
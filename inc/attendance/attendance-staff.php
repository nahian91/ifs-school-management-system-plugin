<?php
/**
 * Faculty & Staff Attendance Roster Entry Workspace
 * File: inc/attendance/attendance-staff.php
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Direct access safety buffer.
}

/**
 * Render Faculty & Staff Attendance Roster Entry Workspace View & Handle Submission
 */
function educore_staff_attendance_view() {
    global $wpdb;

    if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'edit_posts' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'ifsedu-school-management' ) );
    }

    $table_staff      = $wpdb->prefix . 'sms_staff';
    $table_attendance = $wpdb->prefix . 'sms_staff_attendance';

    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $filter_date       = isset( $_REQUEST['attendance_date'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['attendance_date'] ) ) : current_time( 'Y-m-d' );
    $filter_staff_type = isset( $_REQUEST['staff_type'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['staff_type'] ) ) : '';
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    $notice_banner_html = '';

    // Save Staff Attendance Form Action.
    $req_method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
    if ( 'POST' === $req_method && isset( $_POST['educore_save_staff_attendance'] ) && isset( $_POST['ifs_educore_staff_att_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ifs_educore_staff_att_nonce'] ) ), 'save_staff_attendance_action' ) ) {
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
        $raw_attendance = ( isset( $_POST['staff_attendance'] ) && is_array( $_POST['staff_attendance'] ) ) ? wp_unslash( $_POST['staff_attendance'] ) : array();
        $raw_late_mins  = ( isset( $_POST['late_minutes'] ) && is_array( $_POST['late_minutes'] ) ) ? wp_unslash( $_POST['late_minutes'] ) : array();
        
        // Allowed statuses including 'Leave' for Paid Leave.
        $allowed_statuses = array( 'Present', 'Absent', 'Late', 'Leave' );
        $saved_count      = 0;

        if ( ! empty( $raw_attendance ) ) {
            foreach ( $raw_attendance as $staff_id => $status_val ) {
                $staff_id = absint( $staff_id );
                $status   = sanitize_text_field( (string) $status_val );
                if ( ! in_array( $status, $allowed_statuses, true ) ) {
                    $status = 'Present';
                }

                $late_mins = isset( $raw_late_mins[ $staff_id ] ) ? absint( $raw_late_mins[ $staff_id ] ) : 0;
                $remarks   = ( 'Late' === $status && 0 < $late_mins ) ? sprintf( __( 'Late by %d minutes', 'ifsedu-school-management' ), $late_mins ) : '';

                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                $existing_id = (int) $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT id FROM `{$table_attendance}` WHERE staff_id = %d AND attendance_date = %s",
                        $staff_id,
                        $filter_date
                    )
                );

                $data = array(
                    'staff_id'        => $staff_id,
                    'attendance_date' => $filter_date,
                    'status'          => $status,
                    'remarks'         => $remarks,
                    'recorded_by'     => get_current_user_id(),
                );

                $formats = array( '%d', '%s', '%s', '%s', '%d' );

                if ( 0 < $existing_id ) {
                    $wpdb->update( 
                        $table_attendance, 
                        array( 'status' => $status, 'remarks' => $remarks, 'recorded_by' => get_current_user_id() ), 
                        array( 'id' => $existing_id ), 
                        array( '%s', '%s', '%d' ), 
                        array( '%d' ) 
                    );
                } else {
                    $wpdb->insert( $table_attendance, $data, $formats );
                }
                // phpcs:enable
                
                $saved_count++;
            }
        }

        $notice_banner_html = '<div class="ifs-educore-success-banner"><span class="dashicons dashicons-yes-alt"></span> ' . sprintf(
            /* translators: %d: Number of staff members whose attendance was updated */
            esc_html__( 'Staff attendance successfully updated for %d employees.', 'ifsedu-school-management' ),
            intval( $saved_count )
        ) . '</div>';
    }

    // Fetch Unique Employment Types for dropdown filter.
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $all_staff_types = $wpdb->get_col( "SELECT DISTINCT staff_type FROM `{$table_staff}` WHERE status = 'Active' AND staff_type != '' ORDER BY staff_type ASC" );
    // phpcs:enable

    // Build Query for Active Staff Members with optional Employment Type filter.
    $query      = "SELECT id, staff_id, full_name, name_bn, designation, phone, status, order_number FROM `{$table_staff}` WHERE status = 'Active'";
    $query_args = array();

    if ( ! empty( $filter_staff_type ) ) {
        $query      .= ' AND staff_type = %s';
        $query_args[] = $filter_staff_type;
    }

    $query .= ' ORDER BY order_number ASC, full_name ASC';
    
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    if ( ! empty( $query_args ) ) {
        $staff_members = $wpdb->get_results( $wpdb->prepare( $query, ...$query_args ) );
    } else {
        $staff_members = $wpdb->get_results( $query );
    }
    // phpcs:enable

    // Fetch Existing Attendance Records & Remarks for Date.
    $attendance_states  = array();
    $attendance_remarks = array();
    if ( ! empty( $staff_members ) ) {
        $staff_ids   = array_map( 'absint', wp_list_pluck( $staff_members, 'id' ) );
        $placeholders = implode( ',', $staff_ids );
        
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $raw_states = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT staff_id, status, remarks FROM `{$table_attendance}` WHERE attendance_date = %s AND staff_id IN ({$placeholders})",
                $filter_date
            ),
            OBJECT_K
        );
        // phpcs:enable

        if ( ! empty( $raw_states ) ) {
            foreach ( $raw_states as $sid => $obj ) {
                $attendance_states[ (int) $sid ] = $obj->status;
                $attendance_remarks[ (int) $sid ] = $obj->remarks;
            }
        }
    }
    ?>

    <style id="ifs-educore-staff-attendance-styles">
        .ifs-educore-attendance-staff-root {
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #0f172a;
        }
        .ifs-educore-success-banner {
            background: #ecfdf5;
            border-left: 4px solid #10b981;
            color: #065f46;
            padding: 12px 18px;
            border-radius: 8px;
            font-weight: 700;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .ifs-educore-alert-warning {
            background: #fffbeb;
            border: 1px solid #fde68a;
            color: #92400e;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            font-weight: 600;
            margin-top: 20px;
        }
        .ifs-educore-bento-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 4px 15px -3px rgba(0, 0, 0, 0.03);
            margin-bottom: 24px;
        }
        .ifs-educore-filter-grid {
            display: flex;
            align-items: flex-end;
            gap: 16px;
            flex-wrap: wrap;
        }
        .ifs-educore-filter-node {
            display: flex;
            flex-direction: column;
            gap: 6px;
            flex: 1;
            min-width: 220px;
        }
        .ifs-educore-filter-label {
            font-size: 12px;
            font-weight: 700;
            color: #475569;
        }
        .ifs-educore-input, .ifs-educore-select {
            height: 38px;
            border: 1.5px solid #cbd5e1;
            border-radius: 8px;
            padding: 0 12px;
            font-size: 13px;
            color: #0f172a;
            background: #ffffff;
            outline: none;
        }
        .ifs-educore-btn-load {
            height: 38px;
            padding: 0 20px;
            background: #00523c;
            color: #ffffff;
            font-size: 13px;
            font-weight: 700;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .ifs-educore-btn-load:hover {
            background: #003e2d;
        }
        .ifs-educore-roster-meta-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 24px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            flex-wrap: wrap;
            gap: 12px;
        }
        .ifs-educore-roster-title-main {
            margin: 0;
            font-size: 16px;
            font-weight: 800;
            color: #0f172a;
        }
        .ifs-educore-roster-date-sub {
            color: #64748b;
            font-size: 12px;
            font-weight: 600;
        }
        .ifs-educore-counter-cluster {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
            font-size: 12.5px;
            font-weight: 700;
        }
        .ifs-educore-counter-badge-total { background: #f1f5f9; color: #334155; padding: 4px 10px; border-radius: 6px; }
        .ifs-educore-counter-badge-present { background: #ecfdf5; color: #065f46; padding: 4px 10px; border-radius: 6px; }
        .ifs-educore-counter-badge-absent { background: #fef2f2; color: #991b1b; padding: 4px 10px; border-radius: 6px; }
        .ifs-educore-counter-badge-late { background: #fffbeb; color: #92400e; padding: 4px 10px; border-radius: 6px; }
        
        .ifs-educore-bulk-automation-row {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 24px;
            background: #ffffff;
            border-bottom: 1px solid #f1f5f9;
            flex-wrap: wrap;
        }
        .ifs-educore-bulk-label {
            font-size: 12.5px;
            font-weight: 700;
            color: #475569;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .ifs-educore-bulk-buttons-group {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .ifs-educore-bulk-btn {
            height: 30px;
            padding: 0 12px;
            font-size: 11.5px;
            font-weight: 700;
            border-radius: 6px;
            cursor: pointer;
            border: 1px solid transparent;
            transition: all 0.2s ease;
        }
        .ifs-educore-bulk-btn-present { background: #ecfdf5; color: #065f46; border-color: #a7f3d0; }
        .ifs-educore-bulk-btn-present:hover { background: #10b981; color: #ffffff; }
        .ifs-educore-bulk-btn-absent { background: #fef2f2; color: #991b1b; border-color: #fecaca; }
        .ifs-educore-bulk-btn-absent:hover { background: #ef4444; color: #ffffff; }
        .ifs-educore-bulk-btn-late { background: #fffbeb; color: #92400e; border-color: #fde68a; }
        .ifs-educore-bulk-btn-late:hover { background: #f59e0b; color: #ffffff; }
        .ifs-educore-bulk-btn-leave { background: #fffbeb; color: #92400e; border-color: #fde68a; }
        .ifs-educore-bulk-btn-leave:hover { background: #f59e0b; color: #ffffff; }

        .ifs-educore-attendance-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 13.5px;
        }
        .ifs-educore-attendance-table th {
            padding: 12px 20px;
            background: #f8fafc;
            color: #475569;
            font-size: 11.5px;
            font-weight: 800;
            border-bottom: 1px solid #e2e8f0;
            text-transform: uppercase;
        }
        .ifs-educore-attendance-table td {
            padding: 12px 20px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }
        .ifs-educore-staff-id-code {
            background: #f1f5f9;
            padding: 2px 6px;
            border-radius: 4px;
            border: 1px solid #cbd5e1;
            font-weight: 700;
            color: #0f172a;
            font-size: 12px;
        }
        .ifs-educore-staff-name-text {
            font-weight: 700;
            color: #0f172a;
        }
        
        /* Segmented Status Radios */
        .att-segmented-group {
            display: inline-flex;
            background: #f1f5f9;
            padding: 3px;
            border-radius: 8px;
            gap: 2px;
        }
        .att-radio-input {
            display: none;
        }
        .att-status-pill {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 6px 12px;
            font-size: 12px;
            font-weight: 700;
            color: #64748b;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
            margin: 0;
        }
        .att-status-pill .dashicons {
            font-size: 14px;
            width: 14px;
            height: 14px;
        }
        .att-status-pill:hover {
            color: #0f172a;
        }
        
        /* Radio Checked States */
        .status-radio-node[value="Present"]:checked + .att-status-pill {
            background-color: #10b981 !important;
            color: #ffffff !important;
            box-shadow: 0 2px 5px rgba(16, 185, 129, 0.3);
        }
        .status-radio-node[value="Absent"]:checked + .att-status-pill {
            background-color: #ef4444 !important;
            color: #ffffff !important;
            box-shadow: 0 2px 5px rgba(239, 68, 68, 0.3);
        }
        .status-radio-node[value="Late"]:checked + .att-status-pill {
            background-color: #f59e0b !important;
            color: #ffffff !important;
            box-shadow: 0 2px 5px rgba(245, 158, 11, 0.3);
        }
        /* Active State Styling for Paid Leave */
        .status-radio-node[value="Leave"]:checked + .att-status-pill {
            background-color: #fffbeb !important;
            color: #92400e !important;
            border-color: #f59e0b !important;
            box-shadow: 0 0 0 2px rgba(245, 158, 11, 0.2);
        }

        .ifs-educore-submit-bar {
            padding: 16px 24px;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            text-align: right;
        }
        .ifs-educore-btn-save-att {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            height: 40px;
            padding: 0 24px;
            background: #00523c;
            color: #ffffff;
            font-size: 13.5px;
            font-weight: 800;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0, 82, 60, 0.25);
            transition: all 0.2s ease;
        }
        .ifs-educore-btn-save-att:hover {
            background: #003e2d;
        }

        @media print {
            .no-print { display: none !important; }
        }
    </style>

    <div class="ifs-educore-attendance-staff-root">
        <?php echo $notice_banner_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

        <!-- Staff Filter Controls Bento Card -->
        <div class="ifs-educore-bento-card no-print">
            <form method="GET" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="ifs-educore-filter-grid">
                <input type="hidden" name="page" value="school_management_system">
                <input type="hidden" name="tab" value="attendance">
                <input type="hidden" name="sub" value="staff">

                <div class="ifs-educore-filter-node">
                    <label class="ifs-educore-filter-label"><?php esc_html_e( 'Target Date', 'ifsedu-school-management' ); ?> *</label>
                    <input type="date" name="attendance_date" class="ifs-educore-input" value="<?php echo esc_attr( $filter_date ); ?>" max="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" required>
                </div>

                <div class="ifs-educore-filter-node">
                    <label class="ifs-educore-filter-label"><?php esc_html_e( 'Filter by Employment Type', 'ifsedu-school-management' ); ?></label>
                    <select name="staff_type" class="ifs-educore-select">
                        <option value=""><?php esc_html_e( '-- All Employment Types --', 'ifsedu-school-management' ); ?></option>
                        <?php 
                        $default_staff_types = array( 'Teacher (School)', 'Teacher (College)', 'Officer', 'Staff' );
                        $merged_staff_types  = array_unique( array_merge( $default_staff_types, is_array( $all_staff_types ) ? $all_staff_types : array() ) );

                        foreach ( $merged_staff_types as $st_type ) : ?>
                            <option value="<?php echo esc_attr( $st_type ); ?>" <?php selected( $filter_staff_type, $st_type ); ?>><?php echo esc_html( $st_type ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <button type="submit" class="ifs-educore-btn-load"><?php esc_html_e( 'Load Staff Roster', 'ifsedu-school-management' ); ?></button>
                </div>
            </form>
        </div>

        <?php if ( ! empty( $staff_members ) ) : ?>
            <div class="ifs-educore-bento-card" style="padding:0; overflow:hidden;">
                
                <!-- Meta Bar with Live Counters -->
                <div class="ifs-educore-roster-meta-bar">
                    <div class="ifs-educore-roster-title">
                        <h4 class="ifs-educore-roster-title-main"><?php esc_html_e( 'Staff Attendance Roster', 'ifsedu-school-management' ); ?></h4>
                        <small class="ifs-educore-roster-date-sub"><?php esc_html_e( 'Target Date:', 'ifsedu-school-management' ); ?> 
                            <?php 
                            $staff_timestamp = strtotime( $filter_date );
                            echo esc_html( $staff_timestamp ? date_i18n( 'd F, Y', $staff_timestamp ) : '—' ); 
                            ?>
                        </small>
                    </div>
                    
                    <div class="ifs-educore-counter-cluster">
                        <span class="ifs-educore-counter-badge-total"><?php esc_html_e( 'Total:', 'ifsedu-school-management' ); ?> <span id="cnt-total"><?php echo count( $staff_members ); ?></span></span>
                        <span class="ifs-educore-counter-badge-present"><?php esc_html_e( 'Present:', 'ifsedu-school-management' ); ?> <span id="cnt-present">0</span></span>
                        <span class="ifs-educore-counter-badge-absent"><?php esc_html_e( 'Absent:', 'ifsedu-school-management' ); ?> <span id="cnt-absent">0</span></span>
                        <span class="ifs-educore-counter-badge-late"><?php esc_html_e( 'Late:', 'ifsedu-school-management' ); ?> <span id="cnt-late">0</span></span>
                        <span class="ifs-educore-counter-badge-leave" style="background:#fffbeb; color:#92400e; padding:3px 8px; border-radius:6px; font-weight:700;"><?php esc_html_e( 'Leave:', 'ifsedu-school-management' ); ?> <span id="cnt-leave">0</span></span>
                    </div>
                </div>

                <!-- Bulk Operations Bar -->
                <div class="ifs-educore-bulk-automation-row no-print">
                    <div class="ifs-educore-bulk-label">
                        <span class="dashicons dashicons-admin-tools"></span> <?php esc_html_e( 'Bulk Operations:', 'ifsedu-school-management' ); ?>
                    </div>
                    <div class="ifs-educore-bulk-buttons-group">
                        <button type="button" class="ifs-educore-bulk-btn ifs-educore-bulk-btn-present" data-target-status="Present"><?php esc_html_e( 'Set All Present', 'ifsedu-school-management' ); ?></button>
                        <button type="button" class="ifs-educore-bulk-btn ifs-educore-bulk-btn-absent" data-target-status="Absent"><?php esc_html_e( 'Set All Absent', 'ifsedu-school-management' ); ?></button>
                        <button type="button" class="ifs-educore-bulk-btn ifs-educore-bulk-btn-late" data-target-status="Late"><?php esc_html_e( 'Set All Late', 'ifsedu-school-management' ); ?></button>
                        <button type="button" class="ifs-educore-bulk-btn ifs-educore-bulk-btn-leave" data-target-status="Leave" style="background:#fffbeb; color:#92400e; border:1px solid #fde68a;"><?php esc_html_e( 'Set All Paid Leave', 'ifsedu-school-management' ); ?></button>
                    </div>
                </div>

                <form method="POST" action="">
                    <?php wp_nonce_field( 'save_staff_attendance_action', 'ifs_educore_staff_att_nonce' ); ?>
                    <input type="hidden" name="attendance_date" value="<?php echo esc_attr( $filter_date ); ?>">

                    <div style="overflow-x:auto;">
                        <table class="ifs-educore-attendance-table">
                            <thead>
                                <tr>
                                    <th style="width: 15%;"><?php esc_html_e( 'Staff ID', 'ifsedu-school-management' ); ?></th>
                                    <th style="width: 25%;"><?php esc_html_e( 'Full Name', 'ifsedu-school-management' ); ?></th>
                                    <th style="width: 15%;"><?php esc_html_e( 'Designation', 'ifsedu-school-management' ); ?></th>
                                    <th style="width: 45%; text-align:center;"><?php esc_html_e( 'Attendance Status & Late Delay (Mins)', 'ifsedu-school-management' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $staff_members as $st ) : 
                                    $st_id     = (int) $st->id;
                                    $status    = isset( $attendance_states[ $st_id ] ) ? $attendance_states[ $st_id ] : 'Present';
                                    $remarks   = isset( $attendance_remarks[ $st_id ] ) ? $attendance_remarks[ $st_id ] : '';
                                    
                                    $extracted_mins = 0;
                                    if ( 'Late' === $status && ! empty( $remarks ) && preg_match( '/(\d+)/', $remarks, $m ) ) {
                                        $extracted_mins = (int) $m[1];
                                    }

                                    $full_name        = ! empty( $st->name_bn ) ? $st->name_bn : $st->full_name;
                                    $display_staff_id = ( property_exists( $st, 'staff_id' ) && ! empty( $st->staff_id ) ) ? $st->staff_id : '#' . $st_id;
                                ?>
                                    <tr class="staff-attendance-row">
                                        <td><code class="ifs-educore-staff-id-code"><?php echo esc_html( strtoupper( (string) $display_staff_id ) ); ?></code></td>
                                        <td><span class="ifs-educore-staff-name-text"><?php echo esc_html( $full_name ); ?></span></td>
                                        <td style="color:#475569;"><?php echo esc_html( ! empty( $st->designation ) ? $st->designation : esc_html__( 'Faculty', 'ifsedu-school-management' ) ); ?></td>
                                        <td style="text-align:center;">
                                            <div style="display: flex; flex-direction: column; gap: 8px; align-items: center;">
                                                <div class="att-segmented-group">
                                                    <input type="radio" class="att-radio-input status-radio-node" name="staff_attendance[<?php echo esc_attr( $st_id ); ?>]" id="st_pres_<?php echo esc_attr( $st_id ); ?>" value="Present" <?php checked( $status, 'Present' ); ?>>
                                                    <label class="att-status-pill" for="st_pres_<?php echo esc_attr( $st_id ); ?>">
                                                        <span class="dashicons dashicons-yes-alt"></span>
                                                        <?php esc_html_e( 'Present', 'ifsedu-school-management' ); ?>
                                                    </label>

                                                    <input type="radio" class="att-radio-input status-radio-node" name="staff_attendance[<?php echo esc_attr( $st_id ); ?>]" id="st_abs_<?php echo esc_attr( $st_id ); ?>" value="Absent" <?php checked( $status, 'Absent' ); ?>>
                                                    <label class="att-status-pill" for="st_abs_<?php echo esc_attr( $st_id ); ?>">
                                                        <span class="dashicons dashicons-dismiss"></span>
                                                        <?php esc_html_e( 'Absent', 'ifsedu-school-management' ); ?>
                                                    </label>

                                                    <input type="radio" class="att-radio-input status-radio-node" name="staff_attendance[<?php echo esc_attr( $st_id ); ?>]" id="st_late_<?php echo esc_attr( $st_id ); ?>" value="Late" <?php checked( $status, 'Late' ); ?>>
                                                    <label class="att-status-pill" for="st_late_<?php echo esc_attr( $st_id ); ?>">
                                                        <span class="dashicons dashicons-clock"></span>
                                                        <?php esc_html_e( 'Late', 'ifsedu-school-management' ); ?>
                                                    </label>

                                                    <input type="radio" class="att-radio-input status-radio-node" name="staff_attendance[<?php echo esc_attr( $st_id ); ?>]" id="st_leave_<?php echo esc_attr( $st_id ); ?>" value="Leave" <?php checked( $status, 'Leave' ); ?>>
                                                    <label class="att-status-pill" for="st_leave_<?php echo esc_attr( $st_id ); ?>">
                                                        <span class="dashicons dashicons-calendar"></span>
                                                        <?php esc_html_e( 'Paid Leave', 'ifsedu-school-management' ); ?>
                                                    </label>
                                                </div>

                                                <!-- Late Delay Minutes Input -->
                                                <div class="late-mins-wrapper" id="late_box_<?php echo esc_attr( $st_id ); ?>" style="display: <?php echo ( 'Late' === $status ) ? 'inline-block' : 'none'; ?>;">
                                                    <label style="font-size: 11px; font-weight: 700; color: #d97706; margin-right: 4px;"><?php esc_html_e( 'Delay:', 'ifsedu-school-management' ); ?></label>
                                                    <input type="number" min="1" max="360" name="late_minutes[<?php echo esc_attr( $st_id ); ?>]" value="<?php echo esc_attr( $extracted_mins > 0 ? $extracted_mins : 15 ); ?>" placeholder="Mins" style="width: 75px; height: 28px; font-size: 12px; border-radius: 4px; border: 1.5px solid #fcd34d; text-align: center;">
                                                    <span style="font-size: 11px; color: #78350f; margin-left: 2px;"><?php esc_html_e( 'mins', 'ifsedu-school-management' ); ?></span>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="ifs-educore-submit-bar">
                        <button type="submit" name="educore_save_staff_attendance" class="ifs-educore-btn-save-att">
                            <span class="dashicons dashicons-saved" style="margin-top:2px;"></span> <?php esc_html_e( 'Save Staff Attendance', 'ifsedu-school-management' ); ?>
                        </button>
                    </div>
                </form>
            </div>
        <?php else : ?>
            <div class="ifs-educore-alert-warning"><span class="dashicons dashicons-warning" style="font-size:24px; width:24px; height:24px; margin-bottom:10px; display:block; margin:0 auto;"></span><p style="margin:0;"><?php esc_html_e( 'No active staff records found matching the filter criteria.', 'ifsedu-school-management' ); ?></p></div>
        <?php endif; ?>
        
        <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function() {
            function updateLiveCounters() {
                var total   = document.querySelectorAll('.staff-attendance-row').length;
                var present = document.querySelectorAll('.status-radio-node[value="Present"]:checked').length;
                var absent  = document.querySelectorAll('.status-radio-node[value="Absent"]:checked').length;
                var late    = document.querySelectorAll('.status-radio-node[value="Late"]:checked').length;
                var leave   = document.querySelectorAll('.status-radio-node[value="Leave"]:checked').length;
                
                var elTotal   = document.getElementById('cnt-total');
                var elPresent = document.getElementById('cnt-present');
                var elAbsent  = document.getElementById('cnt-absent');
                var elLate    = document.getElementById('cnt-late');
                var elLeave   = document.getElementById('cnt-leave');
                
                if (elTotal)   elTotal.textContent   = total;
                if (elPresent) elPresent.textContent = present;
                if (elAbsent)  elAbsent.textContent  = absent;
                if (elLate)    elLate.textContent    = late;
                if (elLeave)   elLeave.textContent   = leave;

                // Toggle visibility of late delay input boxes.
                document.querySelectorAll('.staff-attendance-row').forEach(function(row) {
                    var lateRadio = row.querySelector('.status-radio-node[value="Late"]');
                    var staffIdMatch = lateRadio.id.replace('st_late_', '');
                    var lateBox = document.getElementById('late_box_' + staffIdMatch);
                    if (lateBox) {
                        lateBox.style.display = lateRadio.checked ? 'inline-block' : 'none';
                    }
                });
            }

            var allRadios = document.querySelectorAll('.status-radio-node');
            allRadios.forEach(function(radio) {
                radio.addEventListener('change', updateLiveCounters);
            });
            
            var bulkBtns = document.querySelectorAll('.ifs-educore-bulk-btn');
            bulkBtns.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var targetStatus = this.getAttribute('data-target-status');
                    var matchingRadios = document.querySelectorAll('.status-radio-node[value="' + targetStatus + '"]');
                    
                    matchingRadios.forEach(function(radio) {
                        radio.checked = true;
                    });
                    
                    updateLiveCounters();
                });
            });

            updateLiveCounters();
        });
        </script>
    </div>
    <?php
}
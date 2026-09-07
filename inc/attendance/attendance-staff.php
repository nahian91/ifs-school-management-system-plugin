<?php
/**
 * Faculty & Staff Attendance Roster Entry Workspace
 * File: inc/attendance/attendance-staff.php
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Direct access safety buffer
}

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

    // Save Staff Attendance Form Action
    if ( isset( $_POST['educore_save_staff_attendance'] ) && isset( $_POST['ifs_educore_staff_att_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ifs_educore_staff_att_nonce'] ) ), 'save_staff_attendance_action' ) ) {
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
        $raw_attendance = ( isset( $_POST['staff_attendance'] ) && is_array( $_POST['staff_attendance'] ) ) ? wp_unslash( $_POST['staff_attendance'] ) : array();
        
        $allowed_statuses = array( 'Present', 'Absent', 'Late' );
        $saved_count      = 0;

        if ( ! empty( $raw_attendance ) ) {
            foreach ( $raw_attendance as $staff_id => $status_val ) {
                $staff_id = absint( $staff_id );
                $status   = sanitize_text_field( (string) $status_val );
                if ( ! in_array( $status, $allowed_statuses, true ) ) {
                    $status = 'Present';
                }

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
                    'recorded_by'     => get_current_user_id(),
                );

                $formats = array( '%d', '%s', '%s', '%d' );

                if ( $existing_id > 0 ) {
                    $wpdb->update( $table_attendance, array( 'status' => $status, 'recorded_by' => get_current_user_id() ), array( 'id' => $existing_id ), array( '%s', '%d' ), array( '%d' ) );
                } else {
                    $wpdb->insert( $table_attendance, $data, $formats );
                }
                // phpcs:enable
                
                $saved_count++;
            }
        }

        echo '<div class="ifs-educore-success-banner" style="background:#ecfdf5; border:1px solid #a7f3d0; color:#047857; padding:12px 18px; border-radius:10px; margin-bottom:20px; font-weight:700; display:flex; align-items:center; gap:8px;"><span class="dashicons dashicons-yes-alt"></span> ' . sprintf(
            /* translators: %d: Number of staff members whose attendance was updated */
            esc_html__( 'Staff attendance successfully updated for %d employees.', 'ifsedu-school-management' ),
            intval( $saved_count )
        ) . '</div>';
    }

    // Fetch Unique Employment Types for dropdown filter
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $all_staff_types = $wpdb->get_col( "SELECT DISTINCT staff_type FROM `{$table_staff}` WHERE status = 'Active' AND staff_type != '' ORDER BY staff_type ASC" );
    // phpcs:enable

    // Build Query for Active Staff Members with optional Employment Type filter
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

    // Fetch Existing Attendance Records for Date
    $attendance_states = array();
    if ( ! empty( $staff_members ) ) {
        $staff_ids    = array_map( 'absint', wp_list_pluck( $staff_members, 'id' ) );
        $placeholders = implode( ',', $staff_ids );
        
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $raw_states = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT staff_id, status FROM `{$table_attendance}` WHERE attendance_date = %s AND staff_id IN ({$placeholders})",
                $filter_date
            ),
            OBJECT_K
        );
        // phpcs:enable

        if ( ! empty( $raw_states ) ) {
            foreach ( $raw_states as $sid => $obj ) {
                $attendance_states[ (int) $sid ] = $obj->status;
            }
        }
    }
    ?>

    <!-- Staff Filter Controls Bento Card -->
    <div class="ifs-educore-bento-card no-print" style="background:#fff; border:1px solid #e2e8f0; padding:20px; border-radius:12px; margin-bottom:24px;">
        <form method="GET" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="display:flex; gap:16px; align-items:flex-end; flex-wrap:wrap;">
            <input type="hidden" name="page" value="school_management_system">
            <input type="hidden" name="tab" value="attendance">
            <input type="hidden" name="sub" value="staff">

            <div class="ifs-educore-form-group" style="flex:1; min-width:200px;">
                <label style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:6px;"><?php esc_html_e( 'Target Date', 'ifsedu-school-management' ); ?> *</label>
                <input type="date" name="attendance_date" style="width:100%; height:40px; border:1px solid #cbd5e1; border-radius:8px; padding:0 12px;" value="<?php echo esc_attr( $filter_date ); ?>" max="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" required>
            </div>

            <div class="ifs-educore-form-group" style="flex:1; min-width:220px;">
                <label style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:6px;"><?php esc_html_e( 'Filter by Employment Type', 'ifsedu-school-management' ); ?></label>
                <select name="staff_type" style="width:100%; height:40px; border:1px solid #cbd5e1; border-radius:8px; padding:0 12px; background:#fff;">
                    <option value=""><?php esc_html_e( '-- All Employment Types --', 'ifsedu-school-management' ); ?></option>
                    <?php 
                    $default_staff_types = array( 'Teacher (School)', 'Teacher (College)', 'Officer', 'Staff' );
                    $merged_staff_types  = array_unique( array_merge( $default_staff_types, is_array( $all_staff_types ) ? $all_staff_types : array() ) );

                    foreach ( $merged_staff_types as $st_type ) : ?>
                        <option value="<?php echo esc_attr( $st_type ); ?>" <?php selected( $filter_staff_type, $st_type ); ?>><?php echo esc_html( $st_type ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="ifs-educore-form-group">
                <button type="submit" style="height:40px; padding:0 24px; background:#00523c; color:#fff; border:none; border-radius:8px; font-weight:700; cursor:pointer;"><?php esc_html_e( 'Load Staff Roster', 'ifsedu-school-management' ); ?></button>
            </div>
        </form>
    </div>

    <?php if ( ! empty( $staff_members ) ) : ?>
        <div class="ifs-educore-bento-card" style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden;">
            
            <!-- Meta Bar with Live Counters -->
            <div class="ifs-educore-roster-meta-bar" style="padding:20px; background:#f8fafc; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
                <div class="ifs-educore-roster-title">
                    <h4 style="margin:0; font-weight:800; font-size:18px; color:#0f172a;"><?php esc_html_e( 'Staff Attendance Roster', 'ifsedu-school-management' ); ?></h4>
                    <small style="color:#64748b; font-weight:600; font-size:13px;"><?php esc_html_e( 'Target Date:', 'ifsedu-school-management' ); ?> 
                        <?php 
                        $staff_timestamp = strtotime( $filter_date );
                        echo esc_html( $staff_timestamp ? date_i18n( 'd F, Y', $staff_timestamp ) : '—' ); 
                        ?>
                    </small>
                </div>
                
                <div class="ifs-educore-counter-cluster" style="display:flex; gap:10px;">
                    <span style="background:#e2e8f0; color:#475569; padding:6px 12px; border-radius:20px; font-size:12px; font-weight:700;"><?php esc_html_e( 'Total:', 'ifsedu-school-management' ); ?> <span id="cnt-total"><?php echo count( $staff_members ); ?></span></span>
                    <span style="background:#ecfdf5; color:#059669; padding:6px 12px; border-radius:20px; font-size:12px; font-weight:700;"><?php esc_html_e( 'Present:', 'ifsedu-school-management' ); ?> <span id="cnt-present">0</span></span>
                    <span style="background:#fef2f2; color:#dc2626; padding:6px 12px; border-radius:20px; font-size:12px; font-weight:700;"><?php esc_html_e( 'Absent:', 'ifsedu-school-management' ); ?> <span id="cnt-absent">0</span></span>
                    <span style="background:#fff7ed; color:#ea580c; padding:6px 12px; border-radius:20px; font-size:12px; font-weight:700;"><?php esc_html_e( 'Late:', 'ifsedu-school-management' ); ?> <span id="cnt-late">0</span></span>
                </div>
            </div>

            <!-- Bulk Operations Bar -->
            <div class="ifs-educore-bulk-automation-row no-print" style="padding:16px 20px; border-bottom:1px solid #e2e8f0; display:flex; gap:16px; align-items:center;">
                <div style="font-size:13px; font-weight:700; color:#475569; display:flex; align-items:center; gap:6px;">
                    <span class="dashicons dashicons-admin-tools"></span> <?php esc_html_e( 'Bulk Operations:', 'ifsedu-school-management' ); ?>
                </div>
                <div style="display:flex; gap:8px;">
                    <button type="button" class="ifs-educore-bulk-btn" data-target-status="Present" style="cursor:pointer; background:#fff; border:1px solid #a7f3d0; color:#059669; font-weight:600; padding:6px 12px; border-radius:6px; font-size:12px; transition:0.2s;"><?php esc_html_e( 'Set All Present', 'ifsedu-school-management' ); ?></button>
                    <button type="button" class="ifs-educore-bulk-btn" data-target-status="Absent" style="cursor:pointer; background:#fff; border:1px solid #fecaca; color:#dc2626; font-weight:600; padding:6px 12px; border-radius:6px; font-size:12px; transition:0.2s;"><?php esc_html_e( 'Set All Absent', 'ifsedu-school-management' ); ?></button>
                    <button type="button" class="ifs-educore-bulk-btn" data-target-status="Late" style="cursor:pointer; background:#fff; border:1px solid #fed7aa; color:#ea580c; font-weight:600; padding:6px 12px; border-radius:6px; font-size:12px; transition:0.2s;"><?php esc_html_e( 'Set All Late', 'ifsedu-school-management' ); ?></button>
                </div>
            </div>

            <form method="POST" action="">
                <?php wp_nonce_field( 'save_staff_attendance_action', 'ifs_educore_staff_att_nonce' ); ?>
                <input type="hidden" name="attendance_date" value="<?php echo esc_attr( $filter_date ); ?>">

                <div style="overflow-x:auto;">
                    <table style="width:100%; border-collapse:collapse; text-align:left; font-size:13.5px;">
                        <thead>
                            <tr style="background:#f8fafc;">
                                <th style="padding:12px 20px; color:#475569; border-bottom:1px solid #e2e8f0; width:15%;"><?php esc_html_e( 'Staff ID', 'ifsedu-school-management' ); ?></th>
                                <th style="padding:12px 20px; color:#475569; border-bottom:1px solid #e2e8f0; width:30%;"><?php esc_html_e( 'Full Name', 'ifsedu-school-management' ); ?></th>
                                <th style="padding:12px 20px; color:#475569; border-bottom:1px solid #e2e8f0; width:20%;"><?php esc_html_e( 'Designation', 'ifsedu-school-management' ); ?></th>
                                <th style="padding:12px 20px; color:#475569; border-bottom:1px solid #e2e8f0; width:35%; text-align:center;"><?php esc_html_e( 'Attendance Status', 'ifsedu-school-management' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $staff_members as $st ) : 
                                $st_id  = (int) $st->id;
                                $status = isset( $attendance_states[ $st_id ] ) ? $attendance_states[ $st_id ] : 'Present';
                                $full_name = ! empty( $st->name_bn ) ? $st->name_bn : $st->full_name;
                                $display_staff_id = ( property_exists( $st, 'staff_id' ) && ! empty( $st->staff_id ) ) ? $st->staff_id : '#' . $st_id;
                            ?>
                                <tr class="staff-attendance-row" style="border-bottom:1px solid #f1f5f9;">
                                    <td style="padding:12px 20px;"><code style="color:#0f172a; font-weight:700; background:#f1f5f9; padding:4px 8px; border-radius:6px; border:1px solid #e2e8f0;"><?php echo esc_html( strtoupper( (string) $display_staff_id ) ); ?></code></td>
                                    <td style="padding:12px 20px;"><span style="font-weight:700; color:#0f172a;"><?php echo esc_html( $full_name ); ?></span></td>
                                    <td style="padding:12px 20px; color:#475569;"><?php echo esc_html( ! empty( $st->designation ) ? $st->designation : esc_html__( 'Faculty', 'ifsedu-school-management' ) ); ?></td>
                                    <td style="padding:12px 20px; text-align:center;">
                                        
                                        <div class="ifs-educore-att-segmented-group">
                                            <input type="radio" class="ifs-educore-att-radio-input status-radio-node" name="staff_attendance[<?php echo esc_attr( $st_id ); ?>]" id="st_pres_<?php echo esc_attr( $st_id ); ?>" value="Present" <?php checked( $status, 'Present' ); ?>>
                                            <label class="ifs-educore-att-status-pill" for="st_pres_<?php echo esc_attr( $st_id ); ?>">
                                                <span class="dashicons dashicons-yes-alt"></span>
                                                <?php esc_html_e( 'Present', 'ifsedu-school-management' ); ?>
                                            </label>

                                            <input type="radio" class="ifs-educore-att-radio-input status-radio-node" name="staff_attendance[<?php echo esc_attr( $st_id ); ?>]" id="st_abs_<?php echo esc_attr( $st_id ); ?>" value="Absent" <?php checked( $status, 'Absent' ); ?>>
                                            <label class="ifs-educore-att-status-pill" for="st_abs_<?php echo esc_attr( $st_id ); ?>">
                                                <span class="dashicons dashicons-dismiss"></span>
                                                <?php esc_html_e( 'Absent', 'ifsedu-school-management' ); ?>
                                            </label>

                                            <input type="radio" class="ifs-educore-att-radio-input status-radio-node" name="staff_attendance[<?php echo esc_attr( $st_id ); ?>]" id="st_late_<?php echo esc_attr( $st_id ); ?>" value="Late" <?php checked( $status, 'Late' ); ?>>
                                            <label class="ifs-educore-att-status-pill" for="st_late_<?php echo esc_attr( $st_id ); ?>">
                                                <span class="dashicons dashicons-clock"></span>
                                                <?php esc_html_e( 'Late', 'ifsedu-school-management' ); ?>
                                            </label>
                                        </div>

                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div style="padding:20px; background:#f8fafc; text-align:right; border-top:1px solid #e2e8f0;">
                    <button type="submit" name="educore_save_staff_attendance" style="padding:0 32px; height:44px; font-size:14px; font-weight:700; background:#00523c; color:#fff; border:none; border-radius:8px; cursor:pointer; box-shadow:0 4px 12px rgba(0, 82, 60, 0.2);">
                        <span class="dashicons dashicons-saved" style="margin-top:5px;"></span> <?php esc_html_e( 'Save Staff Attendance', 'ifsedu-school-management' ); ?>
                    </button>
                </div>
            </form>
        </div>
    <?php else : ?>
        <div style="background:#fffbeb; border:1px solid #fed7aa; color:#9a3412; padding:20px; border-radius:12px; text-align:center; font-weight:600;"><span class="dashicons dashicons-warning" style="font-size:24px; width:24px; height:24px; margin-bottom:10px; display:block; margin:0 auto;"></span><p style="margin:0;"><?php esc_html_e( 'No active staff records found matching the filter criteria.', 'ifsedu-school-management' ); ?></p></div>
    <?php endif; ?>
    
    <script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function() {
        function updateLiveCounters() {
            var total   = document.querySelectorAll('.staff-attendance-row').length;
            var present = document.querySelectorAll('.status-radio-node[value="Present"]:checked').length;
            var absent  = document.querySelectorAll('.status-radio-node[value="Absent"]:checked').length;
            var late    = document.querySelectorAll('.status-radio-node[value="Late"]:checked').length;
            
            var elTotal   = document.getElementById('cnt-total');
            var elPresent = document.getElementById('cnt-present');
            var elAbsent  = document.getElementById('cnt-absent');
            var elLate    = document.getElementById('cnt-late');
            
            if (elTotal)   elTotal.textContent   = total;
            if (elPresent) elPresent.textContent = present;
            if (elAbsent)  elAbsent.textContent  = absent;
            if (elLate)    elLate.textContent    = late;
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
    <?php
}
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

    $notice_banner_html = '';

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

        $notice_banner_html = '<div class="ifs-educore-success-banner"><span class="dashicons dashicons-yes-alt"></span> ' . sprintf(
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
                                    <th style="width: 30%;"><?php esc_html_e( 'Full Name', 'ifsedu-school-management' ); ?></th>
                                    <th style="width: 20%;"><?php esc_html_e( 'Designation', 'ifsedu-school-management' ); ?></th>
                                    <th style="width: 35%; text-align:center;"><?php esc_html_e( 'Attendance Status', 'ifsedu-school-management' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $staff_members as $st ) : 
                                    $st_id  = (int) $st->id;
                                    $status = isset( $attendance_states[ $st_id ] ) ? $attendance_states[ $st_id ] : 'Present';
                                    $full_name = ! empty( $st->name_bn ) ? $st->name_bn : $st->full_name;
                                    $display_staff_id = ( property_exists( $st, 'staff_id' ) && ! empty( $st->staff_id ) ) ? $st->staff_id : '#' . $st_id;
                                ?>
                                    <tr class="staff-attendance-row">
                                        <td><code class="ifs-educore-staff-id-code"><?php echo esc_html( strtoupper( (string) $display_staff_id ) ); ?></code></td>
                                        <td><span class="ifs-educore-staff-name-text"><?php echo esc_html( $full_name ); ?></span></td>
                                        <td style="color:#475569;"><?php echo esc_html( ! empty( $st->designation ) ? $st->designation : esc_html__( 'Faculty', 'ifsedu-school-management' ) ); ?></td>
                                        <td style="text-align:center;">
                                            
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
    </div>
    <?php
}
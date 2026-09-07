<?php
/**
 * Daily Student Attendance Entry Workspace
 * File: inc/attendance/attendance-daily.php
 * Strictly Filtered by Assigned Teacher Subjects & Units from Academic Setup
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Direct access safety buffer
}

function educore_daily_attendance_view( $classes, $sections, $filter_class, $filter_section, $filter_date ) {
    global $wpdb;

    $current_user = wp_get_current_user();
    $is_admin     = current_user_can( 'manage_options' );

    // 1. Resolve Exact Assigned Classes & Sections for Non-Admin Teachers
    $teacher_assigned_classes  = array();
    $teacher_assigned_sections = array();
    $assigned_unit_ids         = array();

    if ( ! $is_admin ) {
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $teacher_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM `{$wpdb->prefix}sms_staff` WHERE email = %s OR full_name = %s LIMIT 1",
                $current_user->user_email,
                $current_user->display_name
            )
        );
        // phpcs:enable

        if ( $teacher_id ) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $allocations = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT DISTINCT u.id AS unit_id, u.class_name, u.section_name, u.sort_order 
                     FROM `{$wpdb->prefix}sms_teacher_subjects` ts
                     INNER JOIN `{$wpdb->prefix}sms_academic_units` u ON ts.class_id = u.id
                     WHERE ts.teacher_id = %d AND u.class_name != ''
                     ORDER BY u.sort_order ASC, CAST(u.class_name AS UNSIGNED) ASC, u.class_name ASC, u.section_name ASC",
                    absint( $teacher_id )
                )
            );
            // phpcs:enable

            if ( ! empty( $allocations ) ) {
                foreach ( $allocations as $al ) {
                    $assigned_unit_ids[] = absint( $al->unit_id );
                    $c_val = trim( (string) $al->class_name );
                    $s_val = trim( (string) $al->section_name );
                    if ( ! empty( $c_val ) && ! in_array( $c_val, $teacher_assigned_classes, true ) ) {
                        $teacher_assigned_classes[] = $c_val;
                    }
                    if ( ! empty( $s_val ) && ! in_array( $s_val, $teacher_assigned_sections, true ) ) {
                        $teacher_assigned_sections[] = $s_val;
                    }
                }
            }
        }
        $classes = $teacher_assigned_classes;
    }

    // Build sort_order dictionary for classes
    $class_order_rows = $wpdb->get_results( "SELECT class_name, MIN(sort_order) as min_sort FROM `{$wpdb->prefix}sms_academic_units` GROUP BY class_name" );
    $class_order_map  = array();
    if ( ! empty( $class_order_rows ) ) {
        foreach ( $class_order_rows as $cor ) {
            $class_order_map[ $cor->class_name ] = (int) $cor->min_sort;
        }
    }

    // Apply sort_order then natural numeric sorting to classes
    if ( ! empty( $classes ) ) {
        usort( $classes, function( $a, $b ) use ( $class_order_map ) {
            $order_a = isset( $class_order_map[ $a ] ) ? $class_order_map[ $a ] : 0;
            $order_b = isset( $class_order_map[ $b ] ) ? $class_order_map[ $b ] : 0;
            if ( $order_a !== $order_b ) {
                return $order_a - $order_b;
            }
            return strnatcasecmp( $a, $b );
        } );
    }

    // 2. Fetch Academic Units scoped to teacher's assignments or global (Ordered by sort_order)
    if ( ! $is_admin && ! empty( $assigned_unit_ids ) ) {
        $assigned_unit_ids = array_map( 'absint', $assigned_unit_ids );
        $unit_placeholders = implode( ',', array_fill( 0, count( $assigned_unit_ids ), '%d' ) );
        
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $all_units = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, class_name, section_name, sort_order FROM `{$wpdb->prefix}sms_academic_units` WHERE id IN ($unit_placeholders) AND section_name != '' ORDER BY sort_order ASC, section_name ASC",
                ...$assigned_unit_ids
            )
        );
        // phpcs:enable
    } else {
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $all_units = $wpdb->get_results( "SELECT id, class_name, section_name, sort_order FROM `{$wpdb->prefix}sms_academic_units` WHERE section_name != '' ORDER BY sort_order ASC, CAST(class_name AS UNSIGNED) ASC, class_name ASC, section_name ASC" );
        // phpcs:enable
    }

    // Auto-select class & section for teachers if not explicitly chosen
    if ( ! $is_admin && empty( $filter_class ) && ! empty( $classes[0] ) ) {
        $filter_class = $classes[0];
    }
    if ( ! $is_admin && empty( $filter_section ) && ! empty( $all_units ) ) {
        foreach ( $all_units as $unit_row ) {
            if ( $unit_row->class_name === $filter_class && ! empty( $unit_row->section_name ) ) {
                $filter_section = $unit_row->section_name;
                break;
            }
        }
    }

    // 3. Fetch Active Students scoped to Assigned Classes & Sections
    if ( ! $is_admin && ! empty( $classes ) ) {
        $class_placeholders = implode( ',', array_fill( 0, count( $classes ), '%s' ) );
        $st_args            = $classes;

        $st_query_sql = "SELECT id, class_name, section_name, full_name, roll_no, student_id FROM `{$wpdb->prefix}sms_students` WHERE status = 'Active' AND class_name IN ($class_placeholders)";

        if ( ! empty( $teacher_assigned_sections ) ) {
            $sec_placeholders = implode( ',', array_fill( 0, count( $teacher_assigned_sections ), '%s' ) );
            $st_query_sql    .= " AND section_name IN ($sec_placeholders)";
            $st_args          = array_merge( $st_args, $teacher_assigned_sections );
        }

        $st_query_sql .= ' ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC';

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $all_active_students = $wpdb->get_results( $wpdb->prepare( $st_query_sql, ...$st_args ) );
        // phpcs:enable
    } else {
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $all_active_students = $wpdb->get_results( "SELECT id, class_name, section_name, full_name, roll_no, student_id FROM `{$wpdb->prefix}sms_students` WHERE status = 'Active' ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC" );
        // phpcs:enable
    }

    // Additional Filter for specific student
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $filter_student = isset( $_GET['filter_student'] ) ? absint( wp_unslash( $_GET['filter_student'] ) ) : 0;

    // Handle Attendance Form Commit
    if ( isset( $_POST['educore_save_attendance'], $_POST['educore_attendance_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['educore_attendance_nonce'] ) ), 'save_attendance_action' ) ) {
        $attendance_date = isset( $_POST['attendance_date'] ) ? sanitize_text_field( wp_unslash( $_POST['attendance_date'] ) ) : current_time( 'Y-m-d' );
        
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $raw_attendance  = isset( $_POST['attendance'] ) && is_array( $_POST['attendance'] ) ? wp_unslash( $_POST['attendance'] ) : array();
        $current_user_id = get_current_user_id();

        $allowed_statuses = array( 'Present', 'Absent', 'Late' );
        $saved_count      = 0;

        if ( ! empty( $raw_attendance ) ) {
            $target_student_ids = array_map( 'absint', array_keys( $raw_attendance ) );
            $ids_placeholder    = implode( ',', array_fill( 0, count( $target_student_ids ), '%d' ) );

            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $existing_records = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT student_id, id FROM `{$wpdb->prefix}sms_attendance` WHERE attendance_date = %s AND student_id IN ($ids_placeholder)",
                    $attendance_date,
                    ...$target_student_ids
                ),
                OBJECT_K
            );
            // phpcs:enable

            foreach ( $raw_attendance as $student_id => $status_val ) {
                $student_id = absint( $student_id );
                $status     = sanitize_text_field( (string) $status_val );

                if ( ! in_array( $status, $allowed_statuses, true ) ) {
                    $status = 'Present';
                }

                if ( isset( $existing_records[ $student_id ] ) ) {
                    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
                    $wpdb->update(
                        $wpdb->prefix . 'sms_attendance',
                        array(
                            'status'      => $status,
                            'recorded_by' => $current_user_id,
                        ),
                        array( 'id' => absint( $existing_records[ $student_id ]->id ) ),
                        array( '%s', '%d' ),
                        array( '%d' )
                    );
                    // phpcs:enable
                } else {
                    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
                    $wpdb->insert(
                        $wpdb->prefix . 'sms_attendance',
                        array(
                            'student_id'      => $student_id,
                            'attendance_date' => $attendance_date,
                            'status'          => $status,
                            'remarks'         => '',
                            'recorded_by'     => $current_user_id,
                        ),
                        array( '%d', '%s', '%s', '%s', '%d' )
                    );
                    // phpcs:enable
                }
                $saved_count++;
            }
        }

        echo '<div class="afdp-success-banner" style="background:#ecfdf5; border:1px solid #a7f3d0; color:#047857; padding:12px 18px; border-radius:10px; margin-bottom:20px; font-weight:700; display:flex; align-items:center; gap:8px;"><span class="dashicons dashicons-yes-alt"></span> ' . sprintf(
            /* translators: %d: Number of students whose attendance records were updated */
            esc_html__( 'Attendance records successfully updated for %d students.', 'ifsedu-school-management' ),
            absint( $saved_count )
        ) . '</div>';
    }
    ?>

    <!-- Daily Filter Controls Bento Card -->
    <div class="dpt-bento-card no-print" style="background:#fff; border:1px solid #e2e8f0; padding:20px; border-radius:12px; margin-bottom:24px;">
        <form method="GET" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="display:flex; gap:16px; align-items:flex-end; flex-wrap:wrap;">
            <input type="hidden" name="page" value="school_management_system">
            <input type="hidden" name="tab" value="attendance">
            <input type="hidden" name="sub" value="daily">
            
            <div class="dpt-form-group" style="flex:1; min-width:180px;">
                <label style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:6px;"><?php esc_html_e( 'Select Target Date', 'ifsedu-school-management' ); ?> *</label>
                <input type="date" name="attendance_date" style="width:100%; height:40px; border:1px solid #cbd5e1; border-radius:8px; padding:0 12px;" value="<?php echo esc_attr( $filter_date ); ?>" max="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" required>
            </div>
            
            <div class="dpt-form-group" style="flex:1; min-width:180px;">
                <label style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:6px;">
                    <?php esc_html_e( 'Academic Class', 'ifsedu-school-management' ); ?> *
                    <?php if ( ! $is_admin ) : ?>
                        <span style="color:#059669; font-size:11px; font-weight:700;">(<?php esc_html_e( 'Assigned Only', 'ifsedu-school-management' ); ?>)</span>
                    <?php endif; ?>
                </label>
                <select name="class_name" id="educore_attendance_class_select" style="width:100%; height:40px; border:1px solid #cbd5e1; border-radius:8px; padding:0 12px;" required>
                    <option value=""><?php esc_html_e( '-- Select Class --', 'ifsedu-school-management' ); ?></option>
                    <?php foreach ( $classes as $cls ) : ?>
                        <option value="<?php echo esc_attr( $cls ); ?>" <?php selected( $filter_class, $cls ); ?>><?php echo esc_html( $cls ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="dpt-form-group" style="flex:1; min-width:180px;">
                <label style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:6px;">
                    <?php esc_html_e( 'Section', 'ifsedu-school-management' ); ?>
                    <?php if ( ! $is_admin ) : ?>
                        <span style="color:#059669; font-size:11px; font-weight:700;">(<?php esc_html_e( 'Assigned', 'ifsedu-school-management' ); ?>)</span>
                    <?php endif; ?>
                </label>
                <select name="section_name" id="educore_attendance_section_select" style="width:100%; height:40px; border:1px solid #cbd5e1; border-radius:8px; padding:0 12px;">
                    <option value=""><?php esc_html_e( '-- All Sections --', 'ifsedu-school-management' ); ?></option>
                </select>
            </div>

            <div class="dpt-form-group" style="flex:1; min-width:220px;">
                <label style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:6px;"><?php esc_html_e( 'Student (Optional)', 'ifsedu-school-management' ); ?></label>
                <select name="filter_student" id="educore_attendance_student_select" style="width:100%; height:40px; border:1px solid #cbd5e1; border-radius:8px; padding:0 12px;">
                    <option value=""><?php esc_html_e( '-- All Students --', 'ifsedu-school-management' ); ?></option>
                </select>
            </div>
            
            <div class="dpt-form-group">
                <button type="submit" style="height:40px; padding:0 24px; background:#00523c; color:#fff; border:none; border-radius:8px; font-weight:700; cursor:pointer;"><?php esc_html_e( 'Load Students', 'ifsedu-school-management' ); ?></button>
            </div>
        </form>
    </div>

    <?php
    if ( ! empty( $filter_class ) ) {
        // Enforce boundary check for non-admin teachers
        if ( ! $is_admin && ! in_array( $filter_class, $classes, true ) ) {
            echo '<div style="background:#fef2f2; border:1px solid #fecaca; color:#dc2626; padding:20px; border-radius:12px; text-align:center; font-weight:600;"><p style="margin:0;">' . esc_html__( 'You are not authorized to mark attendance for this class.', 'ifsedu-school-management' ) . '</p></div>';
            return;
        }

        $query_sql = "SELECT id, student_id, full_name, roll_no FROM `{$wpdb->prefix}sms_students` WHERE status = 'Active' AND class_name = %s";
        $sql_args  = array( $filter_class );

        if ( ! empty( $filter_section ) ) {
            $query_sql  .= ' AND section_name = %s';
            $sql_args[]  = $filter_section;
        } elseif ( ! $is_admin && ! empty( $teacher_assigned_sections ) ) {
            $sec_placeholders = implode( ',', array_fill( 0, count( $teacher_assigned_sections ), '%s' ) );
            $query_sql       .= " AND section_name IN ($sec_placeholders)";
            $sql_args         = array_merge( $sql_args, $teacher_assigned_sections );
        }

        if ( $filter_student > 0 ) {
            $query_sql  .= ' AND id = %d';
            $sql_args[]  = $filter_student;
        }

        $query_sql .= ' ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC';
        
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $students = $wpdb->get_results( $wpdb->prepare( $query_sql, ...$sql_args ) );
        // phpcs:enable

        if ( $students ) {
            $student_ids  = array_map( 'absint', wp_list_pluck( $students, 'id' ) );
            $placeholders = implode( ',', array_fill( 0, count( $student_ids ), '%d' ) );
            
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $loaded_attendance_states = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT student_id, status FROM `{$wpdb->prefix}sms_attendance` WHERE attendance_date = %s AND student_id IN ($placeholders)",
                    $filter_date,
                    ...$student_ids
                ),
                OBJECT_K
            );
            // phpcs:enable
            
            $att_timestamp      = strtotime( $filter_date );
            $att_date_formatted = $att_timestamp ? date_i18n( 'd F, Y', $att_timestamp ) : '—';
            ?>
            <div class="dpt-bento-card" style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden;">
                
                <div class="afdp-roster-meta-bar" style="padding:20px; background:#f8fafc; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
                    <div class="afdp-roster-title">
                        <h4 style="margin:0; font-weight:800; font-size:18px;"><?php esc_html_e( 'Mark Attendance:', 'ifsedu-school-management' ); ?> <span style="color: #00523c;"><?php echo esc_html( $filter_class . ( $filter_section ? ' (' . $filter_section . ')' : '' ) ); ?></span></h4>
                        <small style="color:#64748b; font-weight:600; font-size:13px;"><?php esc_html_e( 'Target Date:', 'ifsedu-school-management' ); ?> <?php echo esc_html( $att_date_formatted ); ?></small>
                    </div>
                    
                    <div class="dpt-counter-cluster" style="display:flex; gap:10px;">
                        <span style="background:#e2e8f0; color:#475569; padding:6px 12px; border-radius:20px; font-size:12px; font-weight:700;"><?php esc_html_e( 'Total:', 'ifsedu-school-management' ); ?> <span id="cnt-total"><?php echo absint( count( $students ) ); ?></span></span>
                        <span style="background:#ecfdf5; color:#059669; padding:6px 12px; border-radius:20px; font-size:12px; font-weight:700;"><?php esc_html_e( 'Present:', 'ifsedu-school-management' ); ?> <span id="cnt-present">0</span></span>
                        <span style="background:#fef2f2; color:#dc2626; padding:6px 12px; border-radius:20px; font-size:12px; font-weight:700;"><?php esc_html_e( 'Absent:', 'ifsedu-school-management' ); ?> <span id="cnt-absent">0</span></span>
                        <span style="background:#fff7ed; color:#ea580c; padding:6px 12px; border-radius:20px; font-size:12px; font-weight:700;"><?php esc_html_e( 'Late:', 'ifsedu-school-management' ); ?> <span id="cnt-late">0</span></span>
                    </div>
                </div>

                <div class="afdp-bulk-automation-row no-print" style="padding:16px 20px; border-bottom:1px solid #e2e8f0; display:flex; gap:16px; align-items:center;">
                    <div style="font-size:13px; font-weight:700; color:#475569; display:flex; align-items:center; gap:6px;">
                        <span class="dashicons dashicons-admin-tools"></span> <?php esc_html_e( 'Bulk Operations:', 'ifsedu-school-management' ); ?>
                    </div>
                    <div style="display:flex; gap:8px;">
                        <button type="button" class="dpt-bulk-btn" data-target-status="Present" style="cursor:pointer; background:#fff; border:1px solid #a7f3d0; color:#059669; font-weight:600; padding:6px 12px; border-radius:6px; font-size:12px; transition:0.2s;"><?php esc_html_e( 'Set All Present', 'ifsedu-school-management' ); ?></button>
                        <button type="button" class="dpt-bulk-btn" data-target-status="Absent" style="cursor:pointer; background:#fff; border:1px solid #fecaca; color:#dc2626; font-weight:600; padding:6px 12px; border-radius:6px; font-size:12px; transition:0.2s;"><?php esc_html_e( 'Set All Absent', 'ifsedu-school-management' ); ?></button>
                        <button type="button" class="dpt-bulk-btn" data-target-status="Late" style="cursor:pointer; background:#fff; border:1px solid #fed7aa; color:#ea580c; font-weight:600; padding:6px 12px; border-radius:6px; font-size:12px; transition:0.2s;"><?php esc_html_e( 'Set All Late', 'ifsedu-school-management' ); ?></button>
                    </div>
                </div>
                
                <form method="POST" action="" id="educoreAttendanceSubmitEngine">
                    <?php wp_nonce_field( 'save_attendance_action', 'educore_attendance_nonce' ); ?>
                    <input type="hidden" name="attendance_date" value="<?php echo esc_attr( $filter_date ); ?>">

                    <div style="overflow-x:auto;">
                        <table style="width:100%; border-collapse:collapse; text-align:left; font-size:13.5px;">
                            <thead>
                                <tr style="background:#f8fafc;">
                                    <th style="padding:12px 20px; color:#475569; border-bottom:1px solid #e2e8f0; width:12%;"><?php esc_html_e( 'Roll No', 'ifsedu-school-management' ); ?></th>
                                    <th style="padding:12px 20px; color:#475569; border-bottom:1px solid #e2e8f0; width:18%;"><?php esc_html_e( 'Student ID', 'ifsedu-school-management' ); ?></th>
                                    <th style="padding:12px 20px; color:#475569; border-bottom:1px solid #e2e8f0; width:35%;"><?php esc_html_e( 'Student Name', 'ifsedu-school-management' ); ?></th>
                                    <th style="padding:12px 20px; color:#475569; border-bottom:1px solid #e2e8f0; width:35%; text-align:center;"><?php esc_html_e( 'Attendance Status', 'ifsedu-school-management' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                foreach ( $students as $student ) : 
                                    $student_internal_id = absint( $student->id );
                                    $current_status      = isset( $loaded_attendance_states[ $student_internal_id ] ) ? $loaded_attendance_states[ $student_internal_id ]->status : 'Present';
                                ?>
                                <tr class="student-attendance-row" style="border-bottom:1px solid #f1f5f9;">
                                    <td style="padding:12px 20px;"><strong># <?php echo esc_html( $student->roll_no ); ?></strong></td>
                                    <td style="padding:12px 20px;"><code style="color:#0f172a; font-weight:700; background:#f1f5f9; padding:4px 8px; border-radius:6px; border:1px solid #cbd5e1;"><?php echo esc_html( strtoupper( (string) $student->student_id ) ); ?></code></td>
                                    <td style="padding:12px 20px;"><span style="font-weight:700; color:#0f172a;"><?php echo esc_html( $student->full_name ); ?></span></td>
                                    <td style="padding:12px 20px; text-align:center;">
                                        
                                        <!-- Segmented Pill Control -->
                                        <div class="att-segmented-group">
                                            <input type="radio" class="att-radio-input status-radio-node" name="attendance[<?php echo esc_attr( $student_internal_id ); ?>]" id="stu_pres_<?php echo esc_attr( $student_internal_id ); ?>" value="Present" <?php checked( $current_status, 'Present' ); ?>>
                                            <label class="att-status-pill" for="stu_pres_<?php echo esc_attr( $student_internal_id ); ?>">
                                                <span class="dashicons dashicons-yes-alt"></span>
                                                <?php esc_html_e( 'Present', 'ifsedu-school-management' ); ?>
                                            </label>

                                            <input type="radio" class="att-radio-input status-radio-node" name="attendance[<?php echo esc_attr( $student_internal_id ); ?>]" id="stu_abs_<?php echo esc_attr( $student_internal_id ); ?>" value="Absent" <?php checked( $current_status, 'Absent' ); ?>>
                                            <label class="att-status-pill" for="stu_abs_<?php echo esc_attr( $student_internal_id ); ?>">
                                                <span class="dashicons dashicons-dismiss"></span>
                                                <?php esc_html_e( 'Absent', 'ifsedu-school-management' ); ?>
                                            </label>

                                            <input type="radio" class="att-radio-input status-radio-node" name="attendance[<?php echo esc_attr( $student_internal_id ); ?>]" id="stu_late_<?php echo esc_attr( $student_internal_id ); ?>" value="Late" <?php checked( $current_status, 'Late' ); ?>>
                                            <label class="att-status-pill" for="stu_late_<?php echo esc_attr( $student_internal_id ); ?>">
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
                        <button type="submit" name="educore_save_attendance" style="padding:0 32px; height:44px; font-size:14px; font-weight:700; background:#00523c; color:#fff; border:none; border-radius:8px; cursor:pointer; box-shadow:0 4px 12px rgba(0, 106, 78, 0.2);">
                            <span class="dashicons dashicons-saved" style="margin-top:5px;"></span> <?php esc_html_e( 'Save Attendance Data', 'ifsedu-school-management' ); ?>
                        </button>
                    </div>
                </form>
            </div>
            <?php
        } else {
            echo '<div style="background:#fffbeb; border:1px solid #fed7aa; color:#9a3412; padding:20px; border-radius:12px; text-align:center; font-weight:600;"><span class="dashicons dashicons-warning" style="font-size:24px; width:24px; height:24px; margin-bottom:10px; display:block; margin:0 auto;"></span><p style="margin:0;">' . esc_html__( 'No active students found matching current filters.', 'ifsedu-school-management' ) . '</p></div>';
        }
    } else {
        echo '<div style="background:#f0f9ff; border:1px solid #bae6fd; color:#0369a1; padding:20px; border-radius:12px; text-align:center; font-weight:600;"><span class="dashicons dashicons-info" style="font-size:24px; width:24px; height:24px; margin-bottom:10px; display:block; margin:0 auto;"></span><p style="margin:0;">' . esc_html__( 'Select a Target Date and Academic Class above to load the attendance workspace.', 'ifsedu-school-management' ) . '</p></div>';
    }
    ?>
    
    <!-- Dynamic JS Engine: Safe Class->Section->Student Chaining & Bulk Logic -->
    <script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function() {
        
        var rawUnits = <?php echo wp_json_encode( ! empty( $all_units ) ? $all_units : array() ); ?>;
        var rawStudents = <?php echo wp_json_encode( ! empty( $all_active_students ) ? $all_active_students : array() ); ?>;
        
        var unitsMap = Array.isArray(rawUnits) ? rawUnits : [];
        var studentsMap = Array.isArray(rawStudents) ? rawStudents : [];
        
        var currentFilterSection = "<?php echo esc_js( $filter_section ); ?>";
        var currentFilterStudent = "<?php echo esc_js( $filter_student ); ?>";
        
        var classSelect = document.getElementById('educore_attendance_class_select');
        var sectionSelect = document.getElementById('educore_attendance_section_select');
        var studentSelect = document.getElementById('educore_attendance_student_select');

        function populateSections(selectedClass, selectedSecName) {
            selectedSecName = selectedSecName || '';
            if (!sectionSelect) return;
            sectionSelect.innerHTML = '<option value=""><?php echo esc_js( __( '-- All Sections --', 'ifsedu-school-management' ) ); ?></option>';
            if (!selectedClass) return;

            var filtered = unitsMap.filter(function(item) { return item.class_name == selectedClass; });
            var uniqueSections = [];
            filtered.forEach(function(item) {
                if (item.section_name && uniqueSections.indexOf(item.section_name) === -1) {
                    uniqueSections.push(item.section_name);
                }
            });

            // Apply natural numeric sorting to sections
            uniqueSections.sort(function(a, b) {
                return a.localeCompare(b, undefined, { numeric: true, sensitivity: 'base' });
            });

            uniqueSections.forEach(function(secName) {
                var opt = document.createElement('option');
                opt.value = secName;
                opt.textContent = secName;
                if (secName == selectedSecName) {
                    opt.selected = true;
                }
                sectionSelect.appendChild(opt);
            });
        }

        function populateStudents(selectedClass, selectedSecName, selectedStudentId) {
            selectedStudentId = selectedStudentId || '';
            if (!studentSelect) return;
            studentSelect.innerHTML = '<option value=""><?php echo esc_js( __( '-- All Students --', 'ifsedu-school-management' ) ); ?></option>';
            if (!selectedClass) return;

            var filteredStudents = studentsMap.filter(function(item) { return item.class_name == selectedClass; });
            
            if (selectedSecName) {
                filteredStudents = filteredStudents.filter(function(item) { return item.section_name == selectedSecName; });
            }

            filteredStudents.forEach(function(stu) {
                var opt = document.createElement('option');
                opt.value = stu.id;
                opt.textContent = stu.roll_no ? '[Roll: ' + stu.roll_no + '] ' + stu.full_name : stu.full_name;
                
                if (String(stu.id) === String(selectedStudentId)) {
                    opt.selected = true;
                }
                studentSelect.appendChild(opt);
            });
        }

        if (classSelect && sectionSelect && studentSelect) {
            populateSections(classSelect.value, currentFilterSection);
            populateStudents(classSelect.value, currentFilterSection, currentFilterStudent);

            classSelect.addEventListener('change', function() {
                populateSections(classSelect.value);
                populateStudents(classSelect.value, sectionSelect.value);
            });

            sectionSelect.addEventListener('change', function() {
                populateStudents(classSelect.value, sectionSelect.value);
            });
        }
        
        function updateLiveCounters() {
            var total = document.querySelectorAll('.student-attendance-row').length;
            var present = document.querySelectorAll('.status-radio-node[value="Present"]:checked').length;
            var absent = document.querySelectorAll('.status-radio-node[value="Absent"]:checked').length;
            var late = document.querySelectorAll('.status-radio-node[value="Late"]:checked').length;
            
            var elTotal = document.getElementById('cnt-total');
            var elPresent = document.getElementById('cnt-present');
            var elAbsent = document.getElementById('cnt-absent');
            var elLate = document.getElementById('cnt-late');
            
            if (elTotal) elTotal.textContent = total;
            if (elPresent) elPresent.textContent = present;
            if (elAbsent) elAbsent.textContent = absent;
            if (elLate) elLate.textContent = late;
        }

        var allRadios = document.querySelectorAll('.status-radio-node');
        allRadios.forEach(function(radio) {
            radio.addEventListener('change', updateLiveCounters);
        });
        
        var bulkBtns = document.querySelectorAll('.dpt-bulk-btn');
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
<?php
/**
 * Premium Student & Employee Attendance Analytics & Audit Module
 * File: inc/reports/reports-attendance-view.php
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Direct access safety buffer
}

function educore_reports_attendance_view() {
    global $wpdb;
    $table_students   = $wpdb->prefix . 'sms_students';
    $table_staff      = $wpdb->prefix . 'sms_staff';
    $table_stu_att    = $wpdb->prefix . 'sms_attendance';
    $table_staff_att  = $wpdb->prefix . 'sms_staff_attendance';
    $table_units      = $wpdb->prefix . 'sms_academic_units';

    // Strict Capability Check
    if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'edit_posts' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to view attendance audit reports.', 'ifsedu-school-management' ) );
    }

    // Audit Target Mode Switcher (Student vs Employee)
    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $report_target = isset( $_GET['report_target'] ) ? sanitize_key( wp_unslash( $_GET['report_target'] ) ) : 'student';

    // Student Filters
    $filter_class   = isset( $_GET['class_name'] ) ? sanitize_text_field( wp_unslash( $_GET['class_name'] ) ) : '';
    $filter_section = isset( $_GET['section_name'] ) ? sanitize_text_field( wp_unslash( $_GET['section_name'] ) ) : '';

    // Employee Filters
    $filter_staff_type = isset( $_GET['staff_type'] ) ? sanitize_text_field( wp_unslash( $_GET['staff_type'] ) ) : '';

    // Date Filters
    $filter_selected_month = isset( $_GET['report_month'] ) ? sanitize_key( wp_unslash( $_GET['report_month'] ) ) : current_time( 'm' );
    $filter_year           = isset( $_GET['report_year'] ) ? absint( wp_unslash( $_GET['report_year'] ) ) : intval( current_time( 'Y' ) );
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    $filter_month = $filter_year . '-' . sprintf( '%02d', absint( $filter_selected_month ) );

    // Fetch classes and units
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $class_rows = $wpdb->get_results( "SELECT DISTINCT class_name FROM `{$table_units}` WHERE class_name != '' AND class_name IS NOT NULL ORDER BY CAST(class_name AS UNSIGNED) ASC, class_name ASC" );
    // phpcs:enable
    
    $classes    = ! empty( $class_rows ) ? wp_list_pluck( $class_rows, 'class_name' ) : array();

    if ( ! empty( $classes ) && is_array( $classes ) ) {
        usort( $classes, function( $a, $b ) {
            return strnatcasecmp( (string) $a, (string) $b );
        } );
    }

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $all_units = $wpdb->get_results( "SELECT id, class_name, section_name FROM `{$table_units}` WHERE section_name != '' AND section_name IS NOT NULL ORDER BY section_name ASC" );

    // Fetch unique Staff Types for the Employee mode filter
    $db_staff_types   = $wpdb->get_col( "SELECT DISTINCT staff_type FROM `{$table_staff}` WHERE status = 'Active' AND staff_type != '' ORDER BY staff_type ASC" );
    // phpcs:enable
    
    $default_types    = array( 'Teacher (School)', 'Teacher (College)', 'Officer', 'Staff' );
    $all_staff_types  = array_unique( array_merge( $default_types, is_array( $db_staff_types ) ? $db_staff_types : array() ) );

    $months = array(
        '01' => __( 'January', 'ifsedu-school-management' ),
        '02' => __( 'February', 'ifsedu-school-management' ),
        '03' => __( 'March', 'ifsedu-school-management' ),
        '04' => __( 'April', 'ifsedu-school-management' ),
        '05' => __( 'May', 'ifsedu-school-management' ),
        '06' => __( 'June', 'ifsedu-school-management' ),
        '07' => __( 'July', 'ifsedu-school-management' ),
        '08' => __( 'August', 'ifsedu-school-management' ),
        '09' => __( 'September', 'ifsedu-school-management' ),
        '10' => __( 'October', 'ifsedu-school-management' ),
        '11' => __( 'November', 'ifsedu-school-management' ),
        '12' => __( 'December', 'ifsedu-school-management' ),
    );

     $current_yr_int = intval( current_time( 'Y' ) );
     $years          = array( strval( $current_yr_int - 1 ), strval( $current_yr_int ), strval( $current_yr_int + 1 ) );
    ?>
    <div class="ifs-educore-attendance-root">
        
        <!-- Header Banner -->
        <div class="ifs-educore-header-frame no-print">
            <div class="ifs-educore-header-content">
                <h2>
                    <span class="dashicons dashicons-calendar-alt"></span> <?php esc_html_e( 'Monthly Attendance Audit Statement', 'ifsedu-school-management' ); ?>
                </h2>
                <p><?php esc_html_e( 'Select target scope, month, and year to generate comprehensive monthly attendance audit reports.', 'ifsedu-school-management' ); ?></p>
            </div>
        </div>

        <!-- Filter Control Matrix Card -->
        <div class="ifs-educore-filter-card no-print">
            <form method="GET" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
                <input type="hidden" name="page" value="school_management_system">
                <input type="hidden" name="tab" value="reports">
                <input type="hidden" name="sub" value="attendance">
                
                <!-- Target Scope Switcher -->
                <div style="margin-bottom:20px; display:flex; gap:16px; align-items:center; flex-wrap:wrap;">
                    <span style="font-size:13px; font-weight:700; color:#475569;"><?php esc_html_e( 'Audit Target Scope:', 'ifsedu-school-management' ); ?></span>
                    
                    <div class="report-mode-segmented">
                        <input type="radio" class="report-mode-input" id="target_student" name="report_target" value="student" <?php checked( $report_target, 'student' ); ?>>
                        <label class="report-mode-pill" for="target_student">
                            <span class="dashicons dashicons-welcome-learn-more"></span>
                            <?php esc_html_e( 'Students Audit', 'ifsedu-school-management' ); ?>
                        </label>

                        <input type="radio" class="report-mode-input" id="target_staff" name="report_target" value="staff" <?php checked( $report_target, 'staff' ); ?>>
                        <label class="report-mode-pill" for="target_staff">
                            <span class="dashicons dashicons-businessman"></span>
                            <?php esc_html_e( 'Employees (Staff / Faculty) Audit', 'ifsedu-school-management' ); ?>
                        </label>
                    </div>
                </div>

                <div style="display:flex; gap:14px; align-items:flex-end; flex-wrap:wrap;">
                    
                    <!-- STUDENT FILTERS -->
                    <div id="wrapper_student_filters" style="display: <?php echo ( 'student' === $report_target ) ? 'flex' : 'none'; ?>; gap:14px; flex:2; flex-wrap:wrap;">
                        <div class="ifs-educore-field-group" style="flex:1; min-width:160px;">
                            <label class="ifs-educore-label"><?php esc_html_e( 'Select Class', 'ifsedu-school-management' ); ?> *</label>
                            <select name="class_name" id="afdp_class_select" class="ifs-educore-select">
                                <option value=""><?php esc_html_e( '-- Choose Class --', 'ifsedu-school-management' ); ?></option>
                                <?php foreach ( $classes as $cls ) : ?>
                                    <option value="<?php echo esc_attr( $cls ); ?>" <?php selected( $filter_class, $cls ); ?>><?php echo esc_html( $cls ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="ifs-educore-field-group" style="flex:1; min-width:160px;">
                            <label class="ifs-educore-label"><?php esc_html_e( 'Select Section', 'ifsedu-school-management' ); ?></label>
                            <select name="section_name" id="afdp_section_select" class="ifs-educore-select">
                                <option value=""><?php esc_html_e( '-- All Sections --', 'ifsedu-school-management' ); ?></option>
                            </select>
                        </div>
                    </div>

                    <!-- EMPLOYEE FILTERS -->
                    <div id="wrapper_staff_filters" style="display: <?php echo ( 'staff' === $report_target ) ? 'flex' : 'none'; ?>; gap:14px; flex:2; flex-wrap:wrap;">
                        <div class="ifs-educore-field-group" style="flex:1; min-width:220px;">
                            <label class="ifs-educore-label"><?php esc_html_e( 'Filter by Employment Type', 'ifsedu-school-management' ); ?></label>
                            <select name="staff_type" id="afdp_staff_type_select" class="ifs-educore-select">
                                <option value=""><?php esc_html_e( '-- All Employment Types --', 'ifsedu-school-management' ); ?></option>
                                <?php foreach ( $all_staff_types as $st_type ) : ?>
                                    <option value="<?php echo esc_attr( $st_type ); ?>" <?php selected( $filter_staff_type, $st_type ); ?>><?php echo esc_html( $st_type ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- MONTH & YEAR FILTERS -->
                    <div class="ifs-educore-field-group" style="flex:1; min-width:140px;">
                        <label class="ifs-educore-label"><?php esc_html_e( 'Select Month', 'ifsedu-school-management' ); ?> *</label>
                        <select name="report_month" class="ifs-educore-select" required>
                            <?php foreach ( $months as $m_num => $m_name ) : ?>
                                <option value="<?php echo esc_attr( $m_num ); ?>" <?php selected( $filter_selected_month, $m_num ); ?>>
                                    <?php echo esc_html( $m_name ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="ifs-educore-field-group" style="flex:1; min-width:120px;">
                        <label class="ifs-educore-label"><?php esc_html_e( 'Select Year', 'ifsedu-school-management' ); ?> *</label>
                        <select name="report_year" class="ifs-educore-select" required>
                            <?php foreach ( $years as $yr ) : ?>
                                <option value="<?php echo esc_attr( $yr ); ?>" <?php selected( $filter_year, intval( $yr ) ); ?>>
                                    <?php echo esc_html( $yr ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="ifs-educore-field-group" style="min-width:140px;">
                        <button type="submit" class="ifs-educore-btn-generate" style="width:100%;">
                            <span class="dashicons dashicons-filter"></span> <?php esc_html_e( 'View Report', 'ifsedu-school-management' ); ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- REPORT STATEMENT ENGINE -->
        <?php
        $like_pattern = $wpdb->esc_like( $filter_month ) . '%';
        $month_name   = isset( $months[ $filter_selected_month ] ) ? $months[ $filter_selected_month ] : '';

        if ( 'student' === $report_target && ! empty( $filter_class ) ) {
            
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            if ( ! empty( $filter_section ) ) {
                $total_working_days = (int) $wpdb->get_var( $wpdb->prepare( "
                    SELECT COUNT(DISTINCT a.attendance_date) 
                    FROM `{$table_stu_att}` a
                    INNER JOIN `{$table_students}` s ON a.student_id = s.id
                    WHERE s.class_name = %s AND s.section_name = %s AND a.attendance_date LIKE %s
                ", $filter_class, $filter_section, $like_pattern ) );

                $students = $wpdb->get_results( $wpdb->prepare( "
                    SELECT 
                        s.id, s.student_id, s.full_name, s.roll_no, s.section_name,
                        SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) as present_count,
                        SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) as absent_count,
                        SUM(CASE WHEN a.status = 'Late' THEN 1 ELSE 0 END) as late_count
                    FROM `{$table_students}` s
                    LEFT JOIN `{$table_stu_att}` a 
                        ON s.id = a.student_id AND a.attendance_date LIKE %s
                    WHERE s.status = 'Active' AND s.class_name = %s AND s.section_name = %s
                    GROUP BY s.id
                ", $like_pattern, $filter_class, $filter_section ) );
            } else {
                $total_working_days = (int) $wpdb->get_var( $wpdb->prepare( "
                    SELECT COUNT(DISTINCT a.attendance_date) 
                    FROM `{$table_stu_att}` a
                    INNER JOIN `{$table_students}` s ON a.student_id = s.id
                    WHERE s.class_name = %s AND a.attendance_date LIKE %s
                ", $filter_class, $like_pattern ) );

                $students = $wpdb->get_results( $wpdb->prepare( "
                    SELECT 
                        s.id, s.student_id, s.full_name, s.roll_no, s.section_name,
                        SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) as present_count,
                        SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) as absent_count,
                        SUM(CASE WHEN a.status = 'Late' THEN 1 ELSE 0 END) as late_count
                    FROM `{$table_students}` s
                    LEFT JOIN `{$table_stu_att}` a 
                        ON s.id = a.student_id AND a.attendance_date LIKE %s
                    WHERE s.status = 'Active' AND s.class_name = %s
                    GROUP BY s.id
                ", $like_pattern, $filter_class ) );
            }
            // phpcs:enable

            if ( ! empty( $students ) && is_array( $students ) ) {
                usort( $students, function( $a, $b ) {
                    return strnatcasecmp( (string) $a->roll_no, (string) $b->roll_no );
                } );
            }

            $section_label = ! empty( $filter_section ) ? esc_html__( 'Section: ', 'ifsedu-school-management' ) . esc_html( $filter_section ) : esc_html__( 'All Sections', 'ifsedu-school-management' );
            ?>

            <!-- Print Header -->
            <div class="ifs-educore-print-header-area">
                <h1><?php echo esc_html( get_bloginfo( 'name' ) ); ?></h1>
                <h3><?php esc_html_e( 'Student Monthly Attendance Audit Report', 'ifsedu-school-management' ); ?></h3>
                <div class="ifs-educore-print-meta-grid">
                    <div>
                        <strong><?php esc_html_e( 'Class:', 'ifsedu-school-management' ); ?></strong> <?php echo esc_html( $filter_class ); ?> (<?php echo esc_html( $section_label ); ?>)<br>
                        <strong><?php esc_html_e( 'Academic Session:', 'ifsedu-school-management' ); ?></strong> <?php echo absint( $filter_year ); ?>
                    </div>
                    <div style="text-align: right;">
                        <strong><?php esc_html_e( 'Report Month:', 'ifsedu-school-management' ); ?></strong> <?php echo esc_html( $month_name . ' ' . $filter_year ); ?><br>
                        <strong><?php esc_html_e( 'Generated:', 'ifsedu-school-management' ); ?></strong> <?php echo esc_html( current_time( 'M j, Y, g:i a' ) ); ?>
                    </div>
                </div>
            </div>

            <!-- Screen Summary Bento -->
            <div class="ifs-educore-summary-bento no-print">
                <h3 class="ifs-educore-summary-title">
                    <span class="dashicons dashicons-groups" style="color:#00523c;"></span> 
                    <?php esc_html_e( 'Class:', 'ifsedu-school-management' ); ?> <?php echo esc_html( $filter_class . ' - ' . $section_label ); ?> | <?php esc_html_e( 'Month:', 'ifsedu-school-management' ); ?> <?php echo esc_html( $month_name . ' ' . $filter_year ); ?>
                </h3>
                <div style="display:flex; gap:12px; align-items:center;">
                    <span class="ifs-educore-badge-days">
                        <span class="dashicons dashicons-clock"></span> <?php esc_html_e( 'Total Working Days:', 'ifsedu-school-management' ); ?> <?php echo absint( $total_working_days ); ?>
                    </span>
                    <button onclick="window.print()" class="ifs-educore-btn-generate no-print" style="height:34px; padding:0 14px; font-size:12.5px; background:#f1f5f9; color:#334155; border:1px solid #cbd5e1; box-shadow:none;">
                        <span class="dashicons dashicons-printer"></span> <?php esc_html_e( 'Print Report', 'ifsedu-school-management' ); ?>
                    </button>
                </div>
            </div>

            <!-- Table Card -->
            <div class="ifs-educore-table-card">
                <div class="ifs-educore-table-wrapper">
                    <table class="ifs-educore-data-table">
                        <thead>
                            <tr>
                                <th style="width: 10%;"><?php esc_html_e( 'Roll No', 'ifsedu-school-management' ); ?></th>
                                <th style="text-align: left;"><?php esc_html_e( 'Student Name', 'ifsedu-school-management' ); ?></th>
                                <th style="width: 12%;"><?php esc_html_e( 'Student ID', 'ifsedu-school-management' ); ?></th>
                                <th style="width: 10%;"><?php esc_html_e( 'Section', 'ifsedu-school-management' ); ?></th>
                                <th style="width: 12%;"><?php esc_html_e( 'Present', 'ifsedu-school-management' ); ?></th>
                                <th style="width: 12%;"><?php esc_html_e( 'Absent', 'ifsedu-school-management' ); ?></th>
                                <th style="width: 12%;"><?php esc_html_e( 'Late', 'ifsedu-school-management' ); ?></th>
                                <th style="width: 14%;"><?php esc_html_e( 'Percentage', 'ifsedu-school-management' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ( ! empty( $students ) ) : foreach ( $students as $student ) : 
                                $present_count = absint( $student->present_count );
                                $absent_count  = absint( $student->absent_count );
                                $late_count    = absint( $student->late_count );

                                $total_attended = $present_count + $late_count;
                                $percentage     = ( $total_working_days > 0 ) ? round( ( $total_attended / $total_working_days ) * 100, 1 ) : 0;
                                
                                $fill_class = 'ifs-educore-fill-danger';
                                $text_class = 'ifs-educore-text-danger';
                                if ( $percentage >= 80 ) {
                                    $fill_class = 'ifs-educore-fill-success';
                                    $text_class = 'ifs-educore-text-success';
                                } elseif ( $percentage >= 50 ) {
                                    $fill_class = 'ifs-educore-fill-warning';
                                    $text_class = 'ifs-educore-text-warning';
                                }
                            ?>
                            <tr>
                                <td><strong>#<?php echo esc_html( $student->roll_no ); ?></strong></td>
                                <td style="text-align: left;"><div style="font-weight: 700;"><?php echo esc_html( $student->full_name ); ?></div></td>
                                <td><code><?php echo esc_html( (string) $student->student_id ); ?></code></td>
                                <td><span><?php echo esc_html( ! empty( $student->section_name ) ? $student->section_name : 'N/A' ); ?></span></td>
                                <td style="color:#047857; font-weight:800;"><?php echo absint( $present_count ); ?> <?php esc_html_e( 'Days', 'ifsedu-school-management' ); ?></td>
                                <td style="color:#b91c1c; font-weight:800;"><?php echo absint( $absent_count ); ?> <?php esc_html_e( 'Days', 'ifsedu-school-management' ); ?></td>
                                <td style="color:#b45309; font-weight:800;"><?php echo absint( $late_count ); ?> <?php esc_html_e( 'Days', 'ifsedu-school-management' ); ?></td>
                                <td>
                                    <div class="ifs-educore-progress-container">
                                        <div class="ifs-educore-progress-bar-bg no-print">
                                            <div class="ifs-educore-progress-bar-fill <?php echo esc_attr( $fill_class ); ?>" style="width: <?php echo esc_attr( min( 100, $percentage ) ); ?>%;"></div>
                                        </div>
                                        <span class="<?php echo esc_attr( $text_class ); ?>"><?php echo esc_html( $percentage ); ?>%</span>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; else : ?>
                            <tr><td colspan="8" style="padding: 30px; color: #94a3b8;"><?php esc_html_e( 'No active students found assigned to this class/section filter.', 'ifsedu-school-management' ); ?></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php } elseif ( 'staff' === $report_target ) {
            
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            if ( ! empty( $filter_staff_type ) ) {
                $total_working_days = (int) $wpdb->get_var( $wpdb->prepare( "
                    SELECT COUNT(DISTINCT a.attendance_date) 
                    FROM `{$table_staff_att}` a
                    INNER JOIN `{$table_staff}` st ON a.staff_id = st.id
                    WHERE st.status = 'Active' AND st.staff_type = %s AND a.attendance_date LIKE %s
                ", $filter_staff_type, $like_pattern ) );

                $staff_members = $wpdb->get_results( $wpdb->prepare( "
                    SELECT 
                        st.id, st.staff_id, st.full_name, st.name_bn, st.designation, st.staff_type,
                        SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) as present_count,
                        SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) as absent_count,
                        SUM(CASE WHEN a.status = 'Late' THEN 1 ELSE 0 END) as late_count
                    FROM `{$table_staff}` st
                    LEFT JOIN `{$table_staff_att}` a 
                        ON st.id = a.staff_id AND a.attendance_date LIKE %s
                    WHERE st.status = 'Active' AND st.staff_type = %s
                    GROUP BY st.id
                    ORDER BY st.full_name ASC
                ", $like_pattern, $filter_staff_type ) );
            } else {
                $total_working_days = (int) $wpdb->get_var( $wpdb->prepare( "
                    SELECT COUNT(DISTINCT a.attendance_date) 
                    FROM `{$table_staff_att}` a
                    INNER JOIN `{$table_staff}` st ON a.staff_id = st.id
                    WHERE st.status = 'Active' AND a.attendance_date LIKE %s
                ", $like_pattern ) );

                $staff_members = $wpdb->get_results( $wpdb->prepare( "
                    SELECT 
                        st.id, st.staff_id, st.full_name, st.name_bn, st.designation, st.staff_type,
                        SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) as present_count,
                        SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) as absent_count,
                        SUM(CASE WHEN a.status = 'Late' THEN 1 ELSE 0 END) as late_count
                    FROM `{$table_staff}` st
                    LEFT JOIN `{$table_staff_att}` a 
                        ON st.id = a.staff_id AND a.attendance_date LIKE %s
                    WHERE st.status = 'Active'
                    GROUP BY st.id
                    ORDER BY st.full_name ASC
                ", $like_pattern ) );
            }
            // phpcs:enable

            $type_label = ! empty( $filter_staff_type ) ? $filter_staff_type : __( 'All Employment Types', 'ifsedu-school-management' );
            ?>

            <!-- Print Header -->
            <div class="ifs-educore-print-header-area">
                <h1><?php echo esc_html( get_bloginfo( 'name' ) ); ?></h1>
                <h3><?php esc_html_e( 'Employee Monthly Attendance Audit Statement', 'ifsedu-school-management' ); ?></h3>
                <div class="ifs-educore-print-meta-grid">
                    <div>
                        <strong><?php esc_html_e( 'Employment Scope:', 'ifsedu-school-management' ); ?></strong> <?php echo esc_html( $type_label ); ?><br>
                        <strong><?php esc_html_e( 'Academic Session:', 'ifsedu-school-management' ); ?></strong> <?php echo absint( $filter_year ); ?>
                    </div>
                    <div style="text-align: right;">
                        <strong><?php esc_html_e( 'Report Month:', 'ifsedu-school-management' ); ?></strong> <?php echo esc_html( $month_name . ' ' . $filter_year ); ?><br>
                        <strong><?php esc_html_e( 'Generated:', 'ifsedu-school-management' ); ?></strong> <?php echo esc_html( current_time( 'M j, Y, g:i a' ) ); ?>
                    </div>
                </div>
            </div>

            <!-- Screen Summary Bento -->
            <div class="ifs-educore-summary-bento no-print">
                <h3 class="ifs-educore-summary-title">
                    <span class="dashicons dashicons-businessman" style="color:#00523c;"></span> 
                    <?php esc_html_e( 'Scope:', 'ifsedu-school-management' ); ?> <?php echo esc_html( $type_label ); ?> | <?php esc_html_e( 'Month:', 'ifsedu-school-management' ); ?> <?php echo esc_html( $month_name . ' ' . $filter_year ); ?>
                </h3>
                <div style="display:flex; gap:12px; align-items:center;">
                    <span class="ifs-educore-badge-days">
                        <span class="dashicons dashicons-clock"></span> <?php esc_html_e( 'Total Working Days:', 'ifsedu-school-management' ); ?> <?php echo absint( $total_working_days ); ?>
                    </span>
                    <button onclick="window.print()" class="ifs-educore-btn-generate no-print" style="height:34px; padding:0 14px; font-size:12.5px; background:#f1f5f9; color:#334155; border:1px solid #cbd5e1; box-shadow:none;">
                        <span class="dashicons dashicons-printer"></span> <?php esc_html_e( 'Print Report', 'ifsedu-school-management' ); ?>
                    </button>
                </div>
            </div>

            <!-- Table Card -->
            <div class="ifs-educore-table-card">
                <div class="ifs-educore-table-wrapper">
                    <table class="ifs-educore-data-table">
                        <thead>
                            <tr>
                                <th style="width: 12%;"><?php esc_html_e( 'Staff ID', 'ifsedu-school-management' ); ?></th>
                                <th style="text-align: left;"><?php esc_html_e( 'Employee Name', 'ifsedu-school-management' ); ?></th>
                                <th style="width: 18%;"><?php esc_html_e( 'Designation', 'ifsedu-school-management' ); ?></th>
                                <th style="width: 16%;"><?php esc_html_e( 'Employment Type', 'ifsedu-school-management' ); ?></th>
                                <th style="width: 10%;"><?php esc_html_e( 'Present', 'ifsedu-school-management' ); ?></th>
                                <th style="width: 10%;"><?php esc_html_e( 'Absent', 'ifsedu-school-management' ); ?></th>
                                <th style="width: 10%;"><?php esc_html_e( 'Late', 'ifsedu-school-management' ); ?></th>
                                <th style="width: 14%;"><?php esc_html_e( 'Percentage', 'ifsedu-school-management' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ( ! empty( $staff_members ) ) : foreach ( $staff_members as $st ) : 
                                $staff_internal_id = absint( $st->id );
                                $present_count     = absint( $st->present_count );
                                $absent_count      = absint( $st->absent_count );
                                $late_count        = absint( $st->late_count );

                                $total_attended = $present_count + $late_count;
                                $percentage     = ( $total_working_days > 0 ) ? round( ( $total_attended / $total_working_days ) * 100, 1 ) : 0;
                                
                                $fill_class = 'ifs-educore-fill-danger';
                                $text_class = 'ifs-educore-text-danger';
                                if ( $percentage >= 80 ) {
                                    $fill_class = 'ifs-educore-fill-success';
                                    $text_class = 'ifs-educore-text-success';
                                } elseif ( $percentage >= 50 ) {
                                    $fill_class = 'ifs-educore-fill-warning';
                                    $text_class = 'ifs-educore-text-warning';
                                }

                                $name_display = ! empty( $st->name_bn ) ? $st->name_bn : $st->full_name;
                            ?>
                            <tr>
                                <td><code><?php echo esc_html( ! empty( $st->staff_id ) ? (string) $st->staff_id : '#' . $staff_internal_id ); ?></code></td>
                                <td style="text-align: left;"><div style="font-weight: 700;"><?php echo esc_html( $name_display ); ?></div></td>
                                <td><span><?php echo esc_html( ! empty( $st->designation ) ? $st->designation : 'Faculty' ); ?></span></td>
                                <td><span style="background:#f1f5f9; padding:2px 8px; border-radius:4px; font-weight:600; font-size:12px;"><?php echo esc_html( $st->staff_type ); ?></span></td>
                                <td style="color:#047857; font-weight:800;"><?php echo absint( $present_count ); ?> <?php esc_html_e( 'Days', 'ifsedu-school-management' ); ?></td>
                                <td style="color:#b91c1c; font-weight:800;"><?php echo absint( $absent_count ); ?> <?php esc_html_e( 'Days', 'ifsedu-school-management' ); ?></td>
                                <td style="color:#b45309; font-weight:800;"><?php echo absint( $late_count ); ?> <?php esc_html_e( 'Days', 'ifsedu-school-management' ); ?></td>
                                <td>
                                    <div class="ifs-educore-progress-container">
                                        <div class="ifs-educore-progress-bar-bg no-print">
                                            <div class="ifs-educore-progress-bar-fill <?php echo esc_attr( $fill_class ); ?>" style="width: <?php echo esc_attr( min( 100, $percentage ) ); ?>%;"></div>
                                        </div>
                                        <span class="<?php echo esc_attr( $text_class ); ?>"><?php echo esc_html( $percentage ); ?>%</span>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; else : ?>
                            <tr><td colspan="8" style="padding: 30px; color: #94a3b8;"><?php esc_html_e( 'No active employees found for the selected scope.', 'ifsedu-school-management' ); ?></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php } else {
            echo '<div class="ifs-educore-fallback-card no-print"><span class="dashicons dashicons-info"></span><p>' . esc_html__( 'Please select your Target Scope, Class/Type, Month, and Year above to generate the monthly attendance report.', 'ifsedu-school-management' ) . '</p></div>';
        }
        ?>

    </div>

    <!-- Client-Side Target Switcher & Section Chaining Script -->
    <script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function() {
        var rawUnits       = <?php echo wp_json_encode( ! empty( $all_units ) ? $all_units : array() ); ?>;
        var currentSection = "<?php echo esc_js( $filter_section ); ?>";
        var classSelect    = document.getElementById('afdp_class_select');
        var sectionSelect  = document.getElementById('afdp_section_select');

        var targetRadios   = document.querySelectorAll('input[name="report_target"]');
        var wrapperStudents = document.getElementById('wrapper_student_filters');
        var wrapperStaff   = document.getElementById('wrapper_staff_filters');

        // Target Switcher Listener
        targetRadios.forEach(function(radio) {
            radio.addEventListener('change', function() {
                if (this.value === 'student') {
                    wrapperStudents.style.display = 'flex';
                    wrapperStaff.style.display    = 'none';
                } else {
                    wrapperStudents.style.display = 'none';
                    wrapperStaff.style.display    = 'flex';
                }
            });
        });

        // Section Chaining
        function populateSections(selectedClass, selectedSecName) {
            selectedSecName = selectedSecName || '';
            if (!sectionSelect) return;
            sectionSelect.innerHTML = '<option value=""><?php echo esc_js( __( '-- All Sections --', 'ifsedu-school-management' ) ); ?></option>';
            if (!selectedClass) return;

            var filtered = rawUnits.filter(function(item) { return item.class_name == selectedClass; });
            var uniqueSections = [];
            filtered.forEach(function(item) {
                if (item.section_name && uniqueSections.indexOf(item.section_name) === -1) {
                    uniqueSections.push(item.section_name);
                }
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

        if (classSelect && sectionSelect) {
            populateSections(classSelect.value, currentSection);

            classSelect.addEventListener('change', function() {
                populateSections(this.value);
            });
        }
    });
    </script>
    <?php
}
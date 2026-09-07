<?php
/**
 * Attendance Reports, Today's Live Attendance Matrix, Institutional Daily Attendance Sheet & Audit Log Workspace
 * File: inc/attendance/attendance-reports.php
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Direct access safety buffer
}

// 1. AJAX Handler for Dynamic Subject Loading in Reports
add_action( 'wp_ajax_ifs_educore_get_subjects_by_class_report', 'ifs_educore_get_subjects_by_class_report_handler' );
function ifs_educore_get_subjects_by_class_report_handler() {
    check_ajax_referer( 'ifs_educore_report_nonce', 'security' );

    if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( array( 'message' => esc_html__( 'Permission denied.', 'ifsedu-school-management' ) ) );
    }

    global $wpdb;
    $class_name   = isset( $_POST['class_name'] ) ? sanitize_text_field( wp_unslash( $_POST['class_name'] ) ) : '';
    $section_name = isset( $_POST['section_name'] ) ? sanitize_text_field( wp_unslash( $_POST['section_name'] ) ) : '';

    if ( empty( $class_name ) ) {
        wp_send_json_success( array() );
    }

    $clean_class = trim( str_ireplace( 'Class ', '', $class_name ) );

    if ( ! empty( $section_name ) ) {
        $unit_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT id FROM `{$wpdb->prefix}sms_academic_units` WHERE (class_name = %s OR class_name = %s) AND (section_name = %s OR dept_name = %s)",
                $class_name,
                $clean_class,
                $section_name,
                $section_name
            )
        );
    } else {
        $unit_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT id FROM `{$wpdb->prefix}sms_academic_units` WHERE (class_name = %s OR class_name = %s)",
                $class_name,
                $clean_class
            )
        );
    }

    $subjects = array();
    if ( ! empty( $unit_ids ) ) {
        $unit_ids_int   = array_map( 'absint', $unit_ids );
        $clean_unit_ids = implode( ',', $unit_ids_int );
        $subjects       = $wpdb->get_results( "SELECT DISTINCT id, subject_name, subject_code FROM `{$wpdb->prefix}sms_subjects` WHERE class_id IN ($clean_unit_ids) ORDER BY subject_name ASC" );
    }

    if ( empty( $subjects ) ) {
        $subjects = $wpdb->get_results( "SELECT DISTINCT id, subject_name, subject_code FROM `{$wpdb->prefix}sms_subjects` ORDER BY subject_name ASC" );
    }

    wp_send_json_success( ! empty( $subjects ) ? $subjects : array() );
}

function educore_student_attendance_log_view( $classes ) {
    global $wpdb;

    // Database Tables
    $table_students   = $wpdb->prefix . 'sms_students';
    $table_units      = $wpdb->prefix . 'sms_academic_units';
    $table_subjects   = $wpdb->prefix . 'sms_subjects';
    $table_attendance = $wpdb->prefix . 'sms_attendance';
    $table_staff      = $wpdb->prefix . 'sms_staff';
    $table_staff_att  = $wpdb->prefix . 'sms_staff_attendance';
    $table_exams      = $wpdb->prefix . 'sms_exams';
    $table_exam_att   = $wpdb->prefix . 'sms_exam_attendance';

    // --------------------------------------------------------------------------
    // 1. DYNAMIC GLOBAL DATASETS FETCHING
    // --------------------------------------------------------------------------
    // Academic Units
    $all_units = $wpdb->get_results(
        "SELECT id, class_name, section_name, dept_name, sort_order 
         FROM `{$table_units}` 
         WHERE class_name != '' 
         ORDER BY sort_order ASC, CAST(class_name AS UNSIGNED) ASC, class_name ASC, section_name ASC"
    );

    // Dynamic Classes List fallback if empty
    if ( empty( $classes ) && ! empty( $all_units ) ) {
        $classes = array();
        foreach ( $all_units as $u ) {
            $cn = trim( (string) $u->class_name );
            if ( ! in_array( $cn, $classes, true ) ) {
                $classes[] = $cn;
            }
        }
    }
    if ( ! empty( $classes ) && is_array( $classes ) ) {
        usort( $classes, 'strnatcasecmp' );
    }

    // Active Students
    $all_students = $wpdb->get_results(
        "SELECT id, student_id, full_name, class_name, section_name, roll_no 
         FROM `{$table_students}` 
         WHERE status = 'Active' 
         ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC"
    );

    // Active Staff & Faculty
    $all_staff = $wpdb->get_results(
        "SELECT id, staff_id, full_name, name_bn, designation, staff_type, phone 
         FROM `{$table_staff}` 
         WHERE status = 'Active' 
         ORDER BY full_name ASC"
    );

    // Distinct Staff Types
    $all_staff_types = $wpdb->get_col(
        "SELECT DISTINCT staff_type 
         FROM `{$table_staff}` 
         WHERE staff_type IS NOT NULL AND staff_type != '' 
         ORDER BY staff_type ASC"
    );
    if ( empty( $all_staff_types ) ) {
        $all_staff_types = array( 'Faculty / Teacher', 'Office Executive', 'Administrative Staff', 'Support Staff' );
    }

    // Available Exams
    $all_exams = $wpdb->get_results(
        "SELECT id, exam_name, class_name, start_date, end_date, status 
         FROM `{$table_exams}` 
         ORDER BY id DESC"
    );

    // Available Subjects
    $available_subjects = $wpdb->get_results(
        "SELECT DISTINCT id, subject_name, subject_code 
         FROM `{$table_subjects}` 
         ORDER BY subject_name ASC"
    );

    $today_date = current_time( 'Y-m-d' );

    // Request Parameters
    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $active_tab        = isset( $_GET['view_tab'] ) ? sanitize_key( wp_unslash( $_GET['view_tab'] ) ) : 'today';
    $report_mode       = isset( $_GET['report_mode'] ) ? sanitize_key( wp_unslash( $_GET['report_mode'] ) ) : 'student';
    $filter_class      = isset( $_GET['filter_class'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_class'] ) ) : '';
    $filter_section    = isset( $_GET['filter_section'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_section'] ) ) : '';
    $filter_student_id = isset( $_GET['student_id'] ) ? absint( wp_unslash( $_GET['student_id'] ) ) : 0;
    $filter_subject    = isset( $_GET['subject_name'] ) ? sanitize_text_field( wp_unslash( $_GET['subject_name'] ) ) : '';

    $filter_exam_id    = isset( $_GET['exam_id'] ) ? absint( wp_unslash( $_GET['exam_id'] ) ) : 0;
    $exam_class        = isset( $_GET['exam_class'] ) ? sanitize_text_field( wp_unslash( $_GET['exam_class'] ) ) : '';
    $exam_subject      = isset( $_GET['exam_subject'] ) ? sanitize_text_field( wp_unslash( $_GET['exam_subject'] ) ) : '';

    $filter_staff_type = isset( $_GET['staff_type'] ) ? sanitize_text_field( wp_unslash( $_GET['staff_type'] ) ) : '';
    $filter_staff_id   = isset( $_GET['staff_id'] ) ? absint( wp_unslash( $_GET['staff_id'] ) ) : 0;

    $start_date        = isset( $_GET['start_date'] ) ? sanitize_text_field( wp_unslash( $_GET['start_date'] ) ) : gmdate( 'Y-m-01' );
    $end_date          = isset( $_GET['end_date'] ) ? sanitize_text_field( wp_unslash( $_GET['end_date'] ) ) : $today_date;
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    $base_page_url = add_query_arg(
        array(
            'page' => 'school_management_system',
            'tab'  => 'attendance',
            'sub'  => 'reports',
        ),
        admin_url( 'admin.php' )
    );

    // --------------------------------------------------------------------------
    // 2. QUERY TODAY'S STRUCTURED DATASETS (FOR LIVE SUMMARY SHEET)
    // --------------------------------------------------------------------------
    $today_att_logs = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT student_id, status FROM `{$table_attendance}` WHERE attendance_date = %s",
            $today_date
        )
    );

    $att_status_map = array();
    foreach ( $today_att_logs as $log_row ) {
        $att_status_map[ $log_row->student_id ] = $log_row->status;
    }

    $morning_units_matrix   = array();
    $day_units_matrix       = array();
    $morning_total_students = 0;
    $morning_present_total  = 0;
    $morning_absent_total   = 0;
    $day_total_students     = 0;
    $day_present_total      = 0;
    $day_absent_total       = 0;

    if ( ! empty( $all_units ) ) {
        foreach ( $all_units as $unit ) {
            $c_name = trim( (string) $unit->class_name );
            $s_name = trim( (string) $unit->section_name );

            preg_match( '/\d+/', $c_name, $matches );
            $c_num = ! empty( $matches ) ? intval( $matches[0] ) : 0;
            $lower_cname = strtolower( $c_name );

            // Morning shift detection
            $is_morning = in_array( $c_num, array( 1, 2, 3, 4 ), true ) || strpos( $lower_cname, 'play' ) !== false || strpos( $lower_cname, 'kg' ) !== false || strpos( $lower_cname, 'nursery' ) !== false;

            $unit_students = array_filter( $all_students, function( $stu ) use ( $c_name, $s_name ) {
                $match_cls = strcasecmp( trim( (string) $stu->class_name ), $c_name ) === 0;
                $match_sec = empty( $s_name ) || ( strcasecmp( trim( (string) $stu->section_name ), $s_name ) === 0 );
                return $match_cls && $match_sec;
            } );

            $tot_count    = count( $unit_students );
            $pr_count     = 0;
            $ab_count     = 0;
            $absent_rolls = array();

            foreach ( $unit_students as $stu_item ) {
                $st = isset( $att_status_map[ $stu_item->id ] ) ? $att_status_map[ $stu_item->id ] : 'Unmarked';
                if ( 'Present' === $st ) {
                    $pr_count++;
                } elseif ( 'Absent' === $st ) {
                    $ab_count++;
                    if ( ! empty( $stu_item->roll_no ) ) {
                        $absent_rolls[] = $stu_item->roll_no;
                    }
                }
            }

            $label = $c_name . ( ! empty( $s_name ) ? ' - ' . $s_name : '' );

            $row_data = array(
                'class_name'   => $c_name,
                'section_name' => $s_name,
                'label'        => $label,
                'total'        => $tot_count,
                'present'      => $pr_count,
                'absent'       => $ab_count,
                'absent_rolls' => implode( ', ', $absent_rolls ),
            );

            if ( $is_morning ) {
                $morning_units_matrix[] = $row_data;
                $morning_total_students += $tot_count;
                $morning_present_total  += $pr_count;
                $morning_absent_total   += $ab_count;
            } else {
                $day_units_matrix[]     = $row_data;
                $day_total_students     += $tot_count;
                $day_present_total      += $pr_count;
                $day_absent_total       += $ab_count;
            }
        }
    }

    $grand_total_students = $morning_total_students + $day_total_students;
    $grand_total_present  = $morning_present_total + $day_present_total;
    $grand_total_absent   = $morning_absent_total + $day_absent_total;

    // --------------------------------------------------------------------------
    // 3. EXECUTE TAB 2 QUERIES (HISTORICAL AUDIT & LOGS)
    // --------------------------------------------------------------------------
    $logs           = array();
    $subject_person = null;
    $exam_meta      = null;

    if ( 'historical' === $active_tab ) {
        if ( 'student' === $report_mode && $filter_student_id > 0 ) {
            $subject_person = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT id, student_id, full_name, class_name, section_name, roll_no 
                     FROM `{$table_students}` 
                     WHERE id = %d LIMIT 1",
                    $filter_student_id
                )
            );

            if ( $subject_person ) {
                $logs = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT attendance_date, status, remarks 
                         FROM `{$table_attendance}` 
                         WHERE student_id = %d AND attendance_date BETWEEN %s AND %s 
                         ORDER BY attendance_date DESC",
                        $filter_student_id,
                        $start_date,
                        $end_date
                    )
                );
            }
        } elseif ( 'exam' === $report_mode && $filter_exam_id > 0 ) {
            $exam_meta = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT id, exam_name, class_name, start_date, end_date 
                     FROM `{$table_exams}` 
                     WHERE id = %d LIMIT 1",
                    $filter_exam_id
                )
            );

            if ( $exam_meta ) {
                $exam_att_table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table_exam_att}'" );
                if ( $exam_att_table_exists ) {
                    $logs = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT ea.*, s.roll_no, s.full_name, s.student_id as reg_id 
                             FROM `{$table_exam_att}` ea 
                             INNER JOIN `{$table_students}` s ON ea.student_id = s.id 
                             WHERE ea.exam_id = %d 
                             ORDER BY CAST(s.roll_no AS UNSIGNED) ASC",
                            $filter_exam_id
                        )
                    );
                }
            }
        } elseif ( 'staff' === $report_mode && $filter_staff_id > 0 ) {
            $subject_person = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT id, staff_id, full_name, name_bn, designation, staff_type 
                     FROM `{$table_staff}` 
                     WHERE id = %d LIMIT 1",
                    $filter_staff_id
                )
            );

            if ( $subject_person ) {
                $staff_att_table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table_staff_att}'" );
                if ( $staff_att_table_exists ) {
                    $logs = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT attendance_date, status, remarks 
                             FROM `{$table_staff_att}` 
                             WHERE staff_id = %d AND attendance_date BETWEEN %s AND %s 
                             ORDER BY attendance_date DESC",
                            $filter_staff_id,
                            $start_date,
                            $end_date
                        )
                    );
                }
            }
        }
    }

    // Dynamic School Settings
    $school_name    = get_option( 'educore_school_name', get_bloginfo( 'name' ) );
    $school_tagline = get_option( 'educore_school_tagline', get_bloginfo( 'description' ) );
    $school_logo    = get_option( 'educore_school_logo', '' );
    ?>

    <style>
        .ifs-educore-subnav-pill {
            padding: 8px 18px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            color: #475569;
            text-decoration: none;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .ifs-educore-subnav-pill.is-active {
            background: #00523c;
            color: #ffffff;
            box-shadow: 0 2px 6px rgba(0, 82, 60, 0.2);
        }

        /* Printable Official Attendance Sheet Styling */
        .ifs-official-sheet-container {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 24px;
            max-width: 1000px;
            margin: 0 auto 24px auto;
            color: #000;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.04);
        }
        .ifs-official-header {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 12px;
            margin-bottom: 14px;
        }
        .ifs-official-logo {
            max-height: 55px;
            margin-bottom: 4px;
        }
        .ifs-official-school-title {
            font-size: 20px;
            font-weight: 900;
            text-transform: uppercase;
            margin: 0;
            color: #000;
            letter-spacing: 0.5px;
        }
        .ifs-official-school-tagline {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1.5px;
            margin: 2px 0;
            text-transform: uppercase;
        }
        .ifs-official-school-contact {
            font-size: 11.5px;
            color: #222;
            margin: 0;
        }
        .ifs-official-meta-strip {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 12px;
            padding: 4px 8px;
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
        }
        .ifs-official-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            margin-bottom: 16px;
        }
        .ifs-official-table th, 
        .ifs-official-table td {
            border: 1px solid #000;
            padding: 6px 10px;
            vertical-align: middle;
        }
        .ifs-official-table th {
            background: #e2e8f0;
            font-weight: 800;
            text-align: center;
            text-transform: uppercase;
        }
        .ifs-official-summary-box {
            display: flex;
            justify-content: space-between;
            border: 1.5px solid #000;
            padding: 8px 12px;
            font-size: 13px;
            font-weight: 800;
            background: #f8fafc;
            margin-bottom: 30px;
        }
        .ifs-official-sig-row {
            display: flex;
            justify-content: space-between;
            margin-top: 50px;
            padding: 0 20px;
        }
        .ifs-official-sig-block {
            text-align: center;
            width: 200px;
            border-top: 1.5px dashed #000;
            padding-top: 5px;
            font-size: 12px;
            font-weight: 700;
        }

        /* Segmented Mode Selector */
        .ifs-educore-report-mode-segmented {
            display: inline-flex;
            background: #f1f5f9;
            padding: 4px;
            border-radius: 8px;
            gap: 4px;
        }
        .ifs-educore-report-mode-input {
            display: none;
        }
        .ifs-educore-report-mode-pill {
            padding: 6px 14px;
            border-radius: 6px;
            font-size: 12.5px;
            font-weight: 700;
            color: #475569;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .ifs-educore-report-mode-input:checked + .ifs-educore-report-mode-pill {
            background: #00523c;
            color: #ffffff;
            box-shadow: 0 2px 5px rgba(0,82,60,0.2);
        }

        @media print {
            body * {
                visibility: hidden;
            }
            .ifs-official-sheet-container, .ifs-official-sheet-container * {
                visibility: visible;
            }
            .ifs-official-sheet-container {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                border: none;
                box-shadow: none;
                padding: 0;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>

    <!-- Top Mode Switcher Nav -->
    <div class="no-print" style="margin-bottom: 20px; display: flex; gap: 8px; background: #e2e8f0; padding: 4px; border-radius: 10px; width: fit-content;">
        <a href="<?php echo esc_url( add_query_arg( 'view_tab', 'today', $base_page_url ) ); ?>" class="ifs-educore-subnav-pill <?php echo 'today' === $active_tab ? 'is-active' : ''; ?>">
            <span class="dashicons dashicons-calendar-alt"></span>
            <?php esc_html_e( "Today's Attendance Sheet", 'ifsedu-school-management' ); ?>
        </a>
        <a href="<?php echo esc_url( add_query_arg( 'view_tab', 'historical', $base_page_url ) ); ?>" class="ifs-educore-subnav-pill <?php echo 'historical' === $active_tab ? 'is-active' : ''; ?>">
            <span class="dashicons dashicons-analytics"></span>
            <?php esc_html_e( 'Individual Student & Staff Reports', 'ifsedu-school-management' ); ?>
        </a>
    </div>

    <?php if ( 'today' === $active_tab ) : ?>
        <!-- ========================================================================= -->
        <!-- TAB 1: OFFICIAL DAILY ATTENDANCE FORMAT SHEET                             -->
        <!-- ========================================================================= -->

        <div class="no-print" style="display:flex; justify-content:space-between; align-items:center; max-width:1000px; margin:0 auto 16px auto; flex-wrap:wrap; gap:10px;">
            <!-- Filter for Class & Section -->
            <div style="display:flex; align-items:center; gap:8px;">
                <label style="font-size:13px; font-weight:700; color:#334155;"><?php esc_html_e( 'Filter Sheet:', 'ifsedu-school-management' ); ?></label>
                <select id="ifs_sheet_class_filter" style="height:36px; border:1.5px solid #cbd5e1; border-radius:6px; padding:0 10px; font-size:13px; background:#fff;">
                    <option value="all"><?php esc_html_e( '-- All Classes --', 'ifsedu-school-management' ); ?></option>
                    <?php foreach ( $classes as $c ) : ?>
                        <option value="<?php echo esc_attr( strtolower( $c ) ); ?>"><?php echo esc_html( $c ); ?></option>
                    <?php endforeach; ?>
                </select>

                <input type="text" id="ifs_sheet_section_search" placeholder="<?php esc_attr_e( 'Search Section / Stream...', 'ifsedu-school-management' ); ?>" style="height:36px; border:1.5px solid #cbd5e1; border-radius:6px; padding:0 10px; font-size:13px; background:#fff; max-width:200px;">
            </div>

            <button type="button" onclick="window.print();" style="height:38px; padding:0 18px; background:#00523c; color:#fff; border:none; border-radius:6px; font-weight:700; cursor:pointer; display:inline-flex; align-items:center; gap:6px;">
                <span class="dashicons dashicons-printer"></span> <?php esc_html_e( 'Print Official Attendance Sheet', 'ifsedu-school-management' ); ?>
            </button>
        </div>

        <div class="ifs-official-sheet-container">
            
            <!-- Institutional Header -->
            <div class="ifs-official-header">
                <?php if ( ! empty( $school_logo ) ) : ?>
                    <img src="<?php echo esc_url( $school_logo ); ?>" alt="Logo" class="ifs-official-logo">
                <?php endif; ?>
                <div class="ifs-official-school-tagline"><?php echo esc_html( $school_tagline ); ?></div>
                <h1 class="ifs-official-school-title"><?php echo esc_html( $school_name ); ?></h1>
                <p class="ifs-official-school-contact"><?php esc_html_e( 'Bangabir Road, South Surma, Sylhet | Mob: 01755 592295 | E-mail: ggisc.syl@gmail.com', 'ifsedu-school-management' ); ?></p>
            </div>

            <!-- Title & Meta Banner -->
            <div style="text-align:center; font-size:16px; font-weight:900; text-transform:uppercase; margin-bottom:8px;">
                <?php printf( esc_html__( 'Daily Attendance - %s', 'ifsedu-school-management' ), esc_html( gmdate( 'Y' ) ) ); ?>
            </div>

            <div class="ifs-official-meta-strip">
                <div><strong><?php esc_html_e( 'Date:', 'ifsedu-school-management' ); ?></strong> <?php echo esc_html( date_i18n( 'n/j/Y', strtotime( $today_date ) ) ); ?></div>
                <div><strong><?php esc_html_e( 'Days:', 'ifsedu-school-management' ); ?></strong> <?php echo esc_html( date_i18n( 'l', strtotime( $today_date ) ) ); ?></div>
            </div>

            <!-- 1. MORNING SHIFT TABLE -->
            <table class="ifs-official-table" id="ifs_morning_shift_table">
                <thead>
                    <tr>
                        <th colspan="4" style="background:#f8fafc; font-size:13px; text-align:left; padding:6px 10px;">
                            <strong><?php esc_html_e( 'Morning Shift', 'ifsedu-school-management' ); ?></strong>
                        </th>
                        <th style="background:#f8fafc; font-size:12px; text-align:center; width:38%;">
                            <?php esc_html_e( "Absent Student's Roll", 'ifsedu-school-management' ); ?>
                        </th>
                    </tr>
                    <tr>
                        <th style="width:32%; text-align:left;"><?php esc_html_e( 'CLASS', 'ifsedu-school-management' ); ?></th>
                        <th style="width:10%;"><?php esc_html_e( 'TOTAL', 'ifsedu-school-management' ); ?></th>
                        <th style="width:10%;"><?php esc_html_e( 'PR.', 'ifsedu-school-management' ); ?></th>
                        <th style="width:10%;"><?php esc_html_e( 'AB.', 'ifsedu-school-management' ); ?></th>
                        <th style="width:38%;"><?php esc_html_e( 'Roll Numbers', 'ifsedu-school-management' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( ! empty( $morning_units_matrix ) ) : foreach ( $morning_units_matrix as $m_row ) : ?>
                        <tr class="sheet-unit-row" data-class="<?php echo esc_attr( strtolower( $m_row['class_name'] ) ); ?>" data-section="<?php echo esc_attr( strtolower( $m_row['section_name'] ) ); ?>" data-label="<?php echo esc_attr( strtolower( $m_row['label'] ) ); ?>">
                            <td><strong><?php echo esc_html( $m_row['label'] ); ?></strong></td>
                            <td style="text-align:center; font-weight:700;"><?php echo intval( $m_row['total'] ); ?></td>
                            <td style="text-align:center;"><?php echo intval( $m_row['present'] ); ?></td>
                            <td style="text-align:center; color:#dc2626; font-weight:700;"><?php echo intval( $m_row['absent'] ); ?></td>
                            <td style="font-family:monospace; font-size:11.5px;"><?php echo esc_html( ! empty( $m_row['absent_rolls'] ) ? $m_row['absent_rolls'] : '—' ); ?></td>
                        </tr>
                    <?php endforeach; else : ?>
                        <tr><td colspan="5" style="text-align:center; color:#64748b;"><?php esc_html_e( 'No classes configured for Morning Shift.', 'ifsedu-school-management' ); ?></td></tr>
                    <?php endif; ?>
                    <tr style="background:#f1f5f9; font-weight:900;" class="sheet-summary-row">
                        <td><?php esc_html_e( 'Total (Morning)', 'ifsedu-school-management' ); ?></td>
                        <td style="text-align:center;"><?php echo intval( $morning_total_students ); ?></td>
                        <td style="text-align:center;"><?php echo intval( $morning_present_total ); ?></td>
                        <td style="text-align:center; color:#dc2626;"><?php echo intval( $morning_absent_total ); ?></td>
                        <td></td>
                    </tr>
                </tbody>
            </table>

            <!-- 2. DAY SHIFT TABLE -->
            <table class="ifs-official-table" id="ifs_day_shift_table">
                <thead>
                    <tr>
                        <th colspan="4" style="background:#f8fafc; font-size:13px; text-align:left; padding:6px 10px;">
                            <strong><?php esc_html_e( 'Day Shift', 'ifsedu-school-management' ); ?></strong>
                        </th>
                        <th style="background:#f8fafc; font-size:12px; text-align:center; width:38%;">
                            <?php esc_html_e( "Absent Student's Roll", 'ifsedu-school-management' ); ?>
                        </th>
                    </tr>
                    <tr>
                        <th style="width:32%; text-align:left;"><?php esc_html_e( 'CLASS', 'ifsedu-school-management' ); ?></th>
                        <th style="width:10%;"><?php esc_html_e( 'TOTAL', 'ifsedu-school-management' ); ?></th>
                        <th style="width:10%;"><?php esc_html_e( 'PR.', 'ifsedu-school-management' ); ?></th>
                        <th style="width:10%;"><?php esc_html_e( 'AB.', 'ifsedu-school-management' ); ?></th>
                        <th style="width:38%;"><?php esc_html_e( 'Roll Numbers', 'ifsedu-school-management' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( ! empty( $day_units_matrix ) ) : foreach ( $day_units_matrix as $d_row ) : ?>
                        <tr class="sheet-unit-row" data-class="<?php echo esc_attr( strtolower( $d_row['class_name'] ) ); ?>" data-section="<?php echo esc_attr( strtolower( $d_row['section_name'] ) ); ?>" data-label="<?php echo esc_attr( strtolower( $d_row['label'] ) ); ?>">
                            <td><strong><?php echo esc_html( $d_row['label'] ); ?></strong></td>
                            <td style="text-align:center; font-weight:700;"><?php echo intval( $d_row['total'] ); ?></td>
                            <td style="text-align:center;"><?php echo intval( $d_row['present'] ); ?></td>
                            <td style="text-align:center; color:#dc2626; font-weight:700;"><?php echo intval( $d_row['absent'] ); ?></td>
                            <td style="font-family:monospace; font-size:11.5px;"><?php echo esc_html( ! empty( $d_row['absent_rolls'] ) ? $d_row['absent_rolls'] : '—' ); ?></td>
                        </tr>
                    <?php endforeach; else : ?>
                        <tr><td colspan="5" style="text-align:center; color:#64748b;"><?php esc_html_e( 'No classes configured for Day Shift.', 'ifsedu-school-management' ); ?></td></tr>
                    <?php endif; ?>
                    <tr style="background:#f1f5f9; font-weight:900;" class="sheet-summary-row">
                        <td><?php esc_html_e( 'Total (Day)', 'ifsedu-school-management' ); ?></td>
                        <td style="text-align:center;"><?php echo intval( $day_total_students ); ?></td>
                        <td style="text-align:center;"><?php echo intval( $day_present_total ); ?></td>
                        <td style="text-align:center; color:#dc2626;"><?php echo intval( $day_absent_total ); ?></td>
                        <td></td>
                    </tr>
                </tbody>
            </table>

            <!-- Summary Box -->
            <div class="ifs-official-summary-box">
                <div><?php esc_html_e( 'In Total Present:', 'ifsedu-school-management' ); ?> <span style="color:#047857;"><?php echo intval( $grand_total_present ); ?></span></div>
                <div><?php esc_html_e( 'In Total Absent:', 'ifsedu-school-management' ); ?> <span style="color:#dc2626;"><?php echo intval( $grand_total_absent ); ?></span></div>
                <div><?php esc_html_e( 'In Total Students:', 'ifsedu-school-management' ); ?> <?php echo intval( $grand_total_students ); ?></div>
            </div>

            <!-- Signatures Strip -->
            <div class="ifs-official-sig-row">
                <div class="ifs-official-sig-block">
                    <?php esc_html_e( 'Md Shaha Alam', 'ifsedu-school-management' ); ?><br>
                    <small style="font-weight:600; color:#555;"><?php esc_html_e( 'Office Executive', 'ifsedu-school-management' ); ?></small>
                </div>
                <div class="ifs-official-sig-block" style="border-top:none;">
                    <span style="font-size:11px; color:#666; font-style:italic;"><?php esc_html_e( 'Remarks / Notice for Class Teacher', 'ifsedu-school-management' ); ?></span>
                </div>
                <div class="ifs-official-sig-block">
                    <?php esc_html_e( 'Md Siddiqur Rahman', 'ifsedu-school-management' ); ?><br>
                    <small style="font-weight:600; color:#555;"><?php esc_html_e( 'Principal', 'ifsedu-school-management' ); ?></small>
                </div>
            </div>

            <div style="font-size:10px; color:#888; text-align:right; margin-top:20px;">
                <?php printf( esc_html__( 'Last Update: %s', 'ifsedu-school-management' ), esc_html( date_i18n( 'n/j/Y', strtotime( $today_date ) ) ) ); ?>
            </div>
        </div>

        <!-- Real-time Filter Script for Attendance Sheet -->
        <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function() {
            var classFilter = document.getElementById('ifs_sheet_class_filter');
            var sectionSearch = document.getElementById('ifs_sheet_section_search');

            function applySheetFilters() {
                var selectedClass = classFilter ? classFilter.value.toLowerCase().trim() : 'all';
                var sectionQuery = sectionSearch ? sectionSearch.value.toLowerCase().trim() : '';

                var rows = document.querySelectorAll('.sheet-unit-row');
                rows.forEach(function(row) {
                    var rClass = (row.getAttribute('data-class') || '').toLowerCase().trim();
                    var rSec = (row.getAttribute('data-section') || '').toLowerCase().trim();
                    var rLabel = (row.getAttribute('data-label') || '').toLowerCase().trim();

                    var classMatches = (selectedClass === 'all' || rClass === selectedClass || rLabel.indexOf(selectedClass) !== -1);
                    var sectionMatches = (!sectionQuery || rSec.indexOf(sectionQuery) !== -1 || rLabel.indexOf(sectionQuery) !== -1);

                    row.style.display = (classMatches && sectionMatches) ? '' : 'none';
                });
            }

            if (classFilter) classFilter.addEventListener('change', applySheetFilters);
            if (sectionSearch) sectionSearch.addEventListener('input', applySheetFilters);
        });
        </script>

    <?php else : ?>
        <!-- ========================================================================= -->
        <!-- TAB 2: HISTORICAL ATTENDANCE REPORTS & AUDIT LOGS                         -->
        <!-- ========================================================================= -->
        
        <div class="ifs-educore-bento-card no-print" style="background:#fff; border:1px solid #e2e8f0; padding:24px; border-radius:12px; margin-bottom:24px;">
            <form method="GET" action="" id="ifs_educore_reports_form">
                <input type="hidden" name="page" value="school_management_system">
                <input type="hidden" name="tab" value="attendance">
                <input type="hidden" name="sub" value="reports">
                <input type="hidden" name="view_tab" value="historical">

                <!-- Report Mode Switcher -->
                <div style="margin-bottom:20px; display:flex; gap:16px; align-items:center; flex-wrap:wrap;">
                    <span style="font-size:13px; font-weight:700; color:#475569;"><?php esc_html_e( 'Audit Scope Target:', 'ifsedu-school-management' ); ?></span>
                    
                    <div class="ifs-educore-report-mode-segmented">
                        <input type="radio" class="ifs-educore-report-mode-input" id="ifs_educore_mode_student" name="report_mode" value="student" <?php checked( $report_mode, 'student' ); ?>>
                        <label class="ifs-educore-report-mode-pill" for="ifs_educore_mode_student">
                            <span class="dashicons dashicons-welcome-learn-more"></span>
                            <?php esc_html_e( 'Individual Student Log', 'ifsedu-school-management' ); ?>
                        </label>

                        <input type="radio" class="ifs-educore-report-mode-input" id="ifs_educore_mode_exam" name="report_mode" value="exam" <?php checked( $report_mode, 'exam' ); ?>>
                        <label class="ifs-educore-report-mode-pill" for="ifs_educore_mode_exam">
                            <span class="dashicons dashicons-awards"></span>
                            <?php esc_html_e( 'Exam Attendance Audit', 'ifsedu-school-management' ); ?>
                        </label>

                        <input type="radio" class="ifs-educore-report-mode-input" id="ifs_educore_mode_staff" name="report_mode" value="staff" <?php checked( $report_mode, 'staff' ); ?>>
                        <label class="ifs-educore-report-mode-pill" for="ifs_educore_mode_staff">
                            <span class="dashicons dashicons-businessman"></span>
                            <?php esc_html_e( 'Employment Type (Faculty / Staff)', 'ifsedu-school-management' ); ?>
                        </label>
                    </div>
                </div>

                <!-- 1. STUDENT MODE CONTROLS -->
                <div id="ifs_educore_wrapper_student_controls" style="display: <?php echo ( 'student' === $report_mode ) ? 'grid' : 'none'; ?>; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap:16px; align-items:flex-end;">
                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-form-label" style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:6px;"><?php esc_html_e( 'Academic Class', 'ifsedu-school-management' ); ?></label>
                        <select name="filter_class" id="ifs_educore_rpt_class_select" style="width:100%; height:40px; border:1px solid #cbd5e1; border-radius:8px; padding:0 12px; background:#fff;">
                            <option value=""><?php esc_html_e( '-- Select Class --', 'ifsedu-school-management' ); ?></option>
                            <?php foreach ( $classes as $cls ) : ?>
                                <option value="<?php echo esc_attr( $cls ); ?>" <?php selected( $filter_class, $cls ); ?>><?php echo esc_html( $cls ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-form-label" style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:6px;"><?php esc_html_e( 'Section', 'ifsedu-school-management' ); ?></label>
                        <select name="filter_section" id="ifs_educore_rpt_section_select" style="width:100%; height:40px; border:1px solid #cbd5e1; border-radius:8px; padding:0 12px; background:#fff;">
                            <option value=""><?php esc_html_e( '-- All Sections --', 'ifsedu-school-management' ); ?></option>
                        </select>
                    </div>

                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-form-label" style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:6px;"><?php esc_html_e( 'Select Subject (Optional)', 'ifsedu-school-management' ); ?></label>
                        <select name="subject_name" id="ifs_educore_rpt_subject_select" style="width:100%; height:40px; border:1px solid #cbd5e1; border-radius:8px; padding:0 12px; background:#fff;">
                            <option value=""><?php esc_html_e( '-- All Subjects --', 'ifsedu-school-management' ); ?></option>
                            <?php foreach ( $available_subjects as $sub ) : ?>
                                <option value="<?php echo esc_attr( $sub->subject_name ); ?>" <?php selected( $filter_subject, $sub->subject_name ); ?>><?php echo esc_html( $sub->subject_name ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="ifs-educore-form-group" style="grid-column: span 2;">
                        <label class="ifs-educore-form-label" style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:6px;"><?php esc_html_e( 'Select Student', 'ifsedu-school-management' ); ?> *</label>
                        <select name="student_id" id="ifs_educore_report_student_id" style="width:100%; height:40px; border:1px solid #cbd5e1; border-radius:8px; padding:0 12px; background:#fff;">
                            <option value=""><?php esc_html_e( '-- Choose Student --', 'ifsedu-school-management' ); ?></option>
                        </select>
                    </div>
                </div>

                <!-- 2. EXAM ATTENDANCE MODE CONTROLS -->
                <div id="ifs_educore_wrapper_exam_controls" style="display: <?php echo ( 'exam' === $report_mode ) ? 'grid' : 'none'; ?>; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:16px; align-items:flex-end;">
                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-form-label" style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:6px;"><?php esc_html_e( 'Select Exam', 'ifsedu-school-management' ); ?> *</label>
                        <select name="exam_id" id="ifs_educore_rpt_exam_select" style="width:100%; height:40px; border:1px solid #cbd5e1; border-radius:8px; padding:0 12px; background:#fff;">
                            <option value=""><?php esc_html_e( '-- Choose Exam --', 'ifsedu-school-management' ); ?></option>
                            <?php foreach ( $all_exams as $ex ) : ?>
                                <option value="<?php echo absint( $ex->id ); ?>" <?php selected( $filter_exam_id, $ex->id ); ?>><?php echo esc_html( $ex->exam_name ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-form-label" style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:6px;"><?php esc_html_e( 'Class Name', 'ifsedu-school-management' ); ?> *</label>
                        <select name="exam_class" id="ifs_educore_rpt_exam_class" style="width:100%; height:40px; border:1px solid #cbd5e1; border-radius:8px; padding:0 12px; background:#fff;">
                            <option value=""><?php esc_html_e( '-- Choose Class --', 'ifsedu-school-management' ); ?></option>
                            <?php foreach ( $classes as $cls ) : ?>
                                <option value="<?php echo esc_attr( $cls ); ?>" <?php selected( $exam_class, $cls ); ?>><?php echo esc_html( $cls ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-form-label" style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:6px;"><?php esc_html_e( 'Exam Subject', 'ifsedu-school-management' ); ?> *</label>
                        <select name="exam_subject" id="ifs_educore_rpt_exam_subject" style="width:100%; height:40px; border:1px solid #cbd5e1; border-radius:8px; padding:0 12px; background:#fff;">
                            <option value=""><?php esc_html_e( '-- Choose Subject --', 'ifsedu-school-management' ); ?></option>
                        </select>
                    </div>
                </div>

                <!-- 3. STAFF MODE CONTROLS -->
                <div id="ifs_educore_wrapper_staff_controls" style="display: <?php echo ( 'staff' === $report_mode ) ? 'flex' : 'none'; ?>; gap:16px; flex-wrap:wrap; align-items:flex-end;">
                    <div class="ifs-educore-form-group" style="flex:1; min-width:200px;">
                        <label class="ifs-educore-form-label" style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:6px;"><?php esc_html_e( 'Employment Type', 'ifsedu-school-management' ); ?> *</label>
                        <select name="staff_type" id="ifs_educore_report_staff_type" style="width:100%; height:40px; border:1px solid #cbd5e1; border-radius:8px; padding:0 12px; background:#fff;">
                            <option value=""><?php esc_html_e( '-- All Employment Types --', 'ifsedu-school-management' ); ?></option>
                            <?php foreach ( $all_staff_types as $st_type ) : ?>
                                <option value="<?php echo esc_attr( $st_type ); ?>" <?php selected( $filter_staff_type, $st_type ); ?>><?php echo esc_html( $st_type ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="ifs-educore-form-group" style="flex:2; min-width:220px;">
                        <label class="ifs-educore-form-label" style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:6px;"><?php esc_html_e( 'Select Employee', 'ifsedu-school-management' ); ?> *</label>
                        <select name="staff_id" id="ifs_educore_report_staff_id" style="width:100%; height:40px; border:1px solid #cbd5e1; border-radius:8px; padding:0 12px; background:#fff;">
                            <option value=""><?php esc_html_e( '-- Choose Employee --', 'ifsedu-school-management' ); ?></option>
                        </select>
                    </div>
                </div>

                <!-- DATE RANGE & SUBMIT ROW -->
                <div id="ifs_educore_wrapper_date_range" style="display: <?php echo ( 'exam' === $report_mode ) ? 'none' : 'flex'; ?>; gap:16px; margin-top:16px; align-items:flex-end; flex-wrap:wrap;">
                    <div class="ifs-educore-form-group" style="flex:1; min-width:160px;">
                        <label class="ifs-educore-form-label" style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:6px;"><?php esc_html_e( 'From Date', 'ifsedu-school-management' ); ?></label>
                        <input type="date" name="start_date" style="width:100%; height:40px; border:1px solid #cbd5e1; border-radius:8px; padding:0 12px;" value="<?php echo esc_attr( $start_date ); ?>" max="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>">
                    </div>

                    <div class="ifs-educore-form-group" style="flex:1; min-width:160px;">
                        <label class="ifs-educore-form-label" style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:6px;"><?php esc_html_e( 'To Date', 'ifsedu-school-management' ); ?></label>
                        <input type="date" name="end_date" style="width:100%; height:40px; border:1px solid #cbd5e1; border-radius:8px; padding:0 12px;" value="<?php echo esc_attr( $end_date ); ?>" max="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>">
                    </div>

                    <div class="ifs-educore-form-group" style="flex:1; min-width:160px;">
                        <button type="submit" style="width:100%; height:40px; background:#00523c; color:#fff; border:none; border-radius:8px; font-weight:700; cursor:pointer;"><?php esc_html_e( 'Fetch Attendance Log', 'ifsedu-school-management' ); ?></button>
                    </div>
                </div>

                <div id="ifs_educore_wrapper_exam_submit" style="display: <?php echo ( 'exam' === $report_mode ) ? 'block' : 'none'; ?>; margin-top:16px;">
                    <button type="submit" style="width:100%; max-width:240px; height:40px; background:#00523c; color:#fff; border:none; border-radius:8px; font-weight:700; cursor:pointer;"><?php esc_html_e( 'Load Exam Roster Audit', 'ifsedu-school-management' ); ?></button>
                </div>
            </form>
        </div>

        <!-- Statement Result Output -->
        <?php if ( $subject_person || $exam_meta ) : 
            if ( 'exam' === $report_mode && $exam_meta ) {
                $display_title = $exam_meta->exam_name . ' — Class ' . $exam_class;
                $display_code  = 'Subject: ' . $exam_subject;
                $meta_line     = sprintf(
                    esc_html__( 'Total Candidates Audited: %d Candidates', 'ifsedu-school-management' ),
                    count( $logs )
                );
            } else {
                $display_title = ( 'student' === $report_mode ) ? $subject_person->full_name : ( ! empty( $subject_person->name_bn ) ? $subject_person->name_bn : $subject_person->full_name );
                $display_code  = ( 'student' === $report_mode ) ? $subject_person->student_id : ( ! empty( $subject_person->staff_id ) ? $subject_person->staff_id : '#' . $subject_person->id );
                $meta_line     = ( 'student' === $report_mode ) 
                    ? sprintf(
                        esc_html__( 'Class: %1$s %2$s %3$s | Log Period: %4$s to %5$s', 'ifsedu-school-management' ),
                        esc_html( $subject_person->class_name ),
                        esc_html( $subject_person->section_name ? '(' . $subject_person->section_name . ')' : '' ),
                        esc_html( $filter_subject ? ' | Subject: ' . $filter_subject : '' ),
                        esc_html( $start_date ),
                        esc_html( $end_date )
                    )
                    : sprintf(
                        esc_html__( 'Designation: %1$s (%2$s) | Log Period: %3$s to %4$s', 'ifsedu-school-management' ),
                        esc_html( $subject_person->designation ? $subject_person->designation : 'Faculty' ),
                        esc_html( $subject_person->staff_type ),
                        esc_html( $start_date ),
                        esc_html( $end_date )
                    );
            }
        ?>
            <div class="ifs-educore-bento-card" style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:24px;">
                <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #f1f5f9; padding-bottom:16px; margin-bottom:20px;">
                    <div>
                        <h3 style="margin:0; font-weight:800; font-size:18px; color:#0f172a;"><?php echo esc_html( $display_title ); ?> <small style="color:#64748b; font-size:14px;">(<?php echo esc_html( $display_code ); ?>)</small></h3>
                        <span style="color:#64748b; font-size:13px; font-weight:600;"><?php echo esc_html( $meta_line ); ?></span>
                    </div>
                    <button type="button" onclick="window.print();" class="no-print" style="height:36px; padding:0 16px; background:#0f172a; color:#fff; border:none; border-radius:6px; font-weight:600; cursor:pointer;">
                        <span class="dashicons dashicons-printer" style="vertical-align:middle; font-size:16px; width:16px; height:16px;"></span>
                        <?php esc_html_e( 'Print Log Statement', 'ifsedu-school-management' ); ?>
                    </button>
                </div>

                <div style="overflow-x:auto;">
                    <table style="width:100%; border-collapse:collapse; text-align:left; font-size:13.5px;">
                        <thead>
                            <tr style="background:#f8fafc;">
                                <?php if ( 'exam' === $report_mode ) : ?>
                                    <th style="padding:12px 16px; color:#475569; border-bottom:1px solid #e2e8f0; width:15%;"><?php esc_html_e( 'Roll', 'ifsedu-school-management' ); ?></th>
                                    <th style="padding:12px 16px; color:#475569; border-bottom:1px solid #e2e8f0; width:25%;"><?php esc_html_e( 'Candidate Name', 'ifsedu-school-management' ); ?></th>
                                    <th style="padding:12px 16px; color:#475569; border-bottom:1px solid #e2e8f0; width:30%;"><?php esc_html_e( 'Invigilator Notes', 'ifsedu-school-management' ); ?></th>
                                    <th style="padding:12px 16px; color:#475569; border-bottom:1px solid #e2e8f0; width:30%; text-align:right;"><?php esc_html_e( 'Exam Hall Status', 'ifsedu-school-management' ); ?></th>
                                <?php else : ?>
                                    <th style="padding:12px 16px; color:#475569; border-bottom:1px solid #e2e8f0; width:30%;"><?php esc_html_e( 'Date', 'ifsedu-school-management' ); ?></th>
                                    <th style="padding:12px 16px; color:#475569; border-bottom:1px solid #e2e8f0; width:35%;"><?php esc_html_e( 'Day', 'ifsedu-school-management' ); ?></th>
                                    <th style="padding:12px 16px; color:#475569; border-bottom:1px solid #e2e8f0; width:35%; text-align:right;"><?php esc_html_e( 'Attendance Status', 'ifsedu-school-management' ); ?></th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ( ! empty( $logs ) ) : foreach ( $logs as $l ) : 
                                $status   = isset( $l->status ) ? $l->status : 'Present';
                                $log_time = ! empty( $l->attendance_date ) ? strtotime( $l->attendance_date ) : false;
                            ?>
                                <tr style="border-bottom:1px solid #f1f5f9;">
                                    <?php if ( 'exam' === $report_mode ) : ?>
                                        <td style="padding:12px 16px;"><strong>#<?php echo esc_html( $l->roll_no ); ?></strong></td>
                                        <td style="padding:12px 16px; font-weight:700; color:#0f172a;"><?php echo esc_html( $l->full_name ); ?> <small style="color:#64748b;">(<?php echo esc_html( strtoupper( (string) $l->reg_id ) ); ?>)</small></td>
                                        <td style="padding:12px 16px; color:#475569;"><?php echo esc_html( ! empty( $l->invigilator_remarks ) ? $l->invigilator_remarks : '—' ); ?></td>
                                        <td style="padding:12px 16px; text-align:right;">
                                            <?php if ( 'Present' === $status ) : ?>
                                                <span class="ifs-educore-att-status-badge ifs-educore-att-badge-present" style="background:#ecfdf5; color:#047857; padding:4px 10px; border-radius:6px; font-weight:700;"><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Present', 'ifsedu-school-management' ); ?></span>
                                            <?php elseif ( 'Absent' === $status ) : ?>
                                                <span class="ifs-educore-att-status-badge ifs-educore-att-badge-absent" style="background:#fef2f2; color:#dc2626; padding:4px 10px; border-radius:6px; font-weight:700;"><span class="dashicons dashicons-dismiss"></span> <?php esc_html_e( 'Absent', 'ifsedu-school-management' ); ?></span>
                                            <?php else : ?>
                                                <span class="ifs-educore-att-status-badge ifs-educore-att-badge-late" style="background:#fffbeb; color:#d97706; padding:4px 10px; border-radius:6px; font-weight:700;"><span class="dashicons dashicons-warning"></span> <?php esc_html_e( 'Late / Expelled', 'ifsedu-school-management' ); ?></span>
                                            <?php endif; ?>
                                        </td>
                                    <?php else : ?>
                                        <td style="padding:12px 16px;"><strong><?php echo esc_html( $log_time ? date_i18n( 'd F, Y', $log_time ) : '—' ); ?></strong></td>
                                        <td style="padding:12px 16px; color:#475569; font-weight:600;"><?php echo esc_html( $log_time ? date_i18n( 'l', $log_time ) : '—' ); ?></td>
                                        <td style="padding:12px 16px; text-align:right;">
                                            <?php if ( 'Present' === $status ) : ?>
                                                <span class="ifs-educore-att-status-badge ifs-educore-att-badge-present" style="background:#ecfdf5; color:#047857; padding:4px 10px; border-radius:6px; font-weight:700;"><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Present', 'ifsedu-school-management' ); ?></span>
                                            <?php elseif ( 'Absent' === $status ) : ?>
                                                <span class="ifs-educore-att-status-badge ifs-educore-att-badge-absent" style="background:#fef2f2; color:#dc2626; padding:4px 10px; border-radius:6px; font-weight:700;"><span class="dashicons dashicons-dismiss"></span> <?php esc_html_e( 'Absent', 'ifsedu-school-management' ); ?></span>
                                            <?php else : ?>
                                                <span class="ifs-educore-att-status-badge ifs-educore-att-badge-late" style="background:#fffbeb; color:#d97706; padding:4px 10px; border-radius:6px; font-weight:700;"><span class="dashicons dashicons-clock"></span> <?php esc_html_e( 'Late', 'ifsedu-school-management' ); ?></span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; else : ?>
                                <tr><td colspan="4" style="text-align:center; color:#94a3b8; padding:40px; font-weight:600;"><?php esc_html_e( 'No attendance logs recorded matching the selected criteria.', 'ifsedu-school-management' ); ?></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- Dynamic JS Scripts -->
        <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function() {
            var ajaxUrl     = '<?php echo esc_url( admin_url( "admin-ajax.php" ) ); ?>';
            var reportNonce = '<?php echo esc_js( wp_create_nonce( "ifs_educore_report_nonce" ) ); ?>';

            var unitsMap    = <?php echo wp_json_encode( ! empty( $all_units ) ? $all_units : array() ); ?>;
            var studentsMap = <?php echo wp_json_encode( ! empty( $all_students ) ? $all_students : array() ); ?>;
            var staffMap    = <?php echo wp_json_encode( ! empty( $all_staff ) ? $all_staff : array() ); ?>;

            var currentSection   = "<?php echo esc_js( $filter_section ); ?>";
            var currentSubject   = "<?php echo esc_js( $filter_subject ); ?>";
            var currentStudentId = "<?php echo esc_js( $filter_student_id ); ?>";
            var currentExamSub   = "<?php echo esc_js( $exam_subject ); ?>";
            var currentStaffId   = "<?php echo esc_js( $filter_staff_id ); ?>";

            var modeRadios     = document.querySelectorAll('input[name="report_mode"]');
            var wrapperStudent = document.getElementById('ifs_educore_wrapper_student_controls');
            var wrapperExam    = document.getElementById('ifs_educore_wrapper_exam_controls');
            var wrapperStaff   = document.getElementById('ifs_educore_wrapper_staff_controls');
            var wrapperDate    = document.getElementById('ifs_educore_wrapper_date_range');
            var wrapperExSub   = document.getElementById('ifs_educore_wrapper_exam_submit');

            var classSelect   = document.getElementById('ifs_educore_rpt_class_select');
            var sectionSelect = document.getElementById('ifs_educore_rpt_section_select');
            var subjectSelect = document.getElementById('ifs_educore_rpt_subject_select');
            var studentSelect = document.getElementById('ifs_educore_report_student_id');

            var examClassSelect = document.getElementById('ifs_educore_rpt_exam_class');
            var examSubSelect   = document.getElementById('ifs_educore_rpt_exam_subject');

            var staffTypeSelect = document.getElementById('ifs_educore_report_staff_type');
            var staffSelect     = document.getElementById('ifs_educore_report_staff_id');

            modeRadios.forEach(function(radio) {
                radio.addEventListener('change', function() {
                    if (this.value === 'student') {
                        wrapperStudent.style.display = 'grid';
                        wrapperExam.style.display    = 'none';
                        wrapperStaff.style.display   = 'none';
                        wrapperDate.style.display    = 'flex';
                        wrapperExSub.style.display   = 'none';
                    } else if (this.value === 'exam') {
                        wrapperStudent.style.display = 'none';
                        wrapperExam.style.display    = 'grid';
                        wrapperStaff.style.display   = 'none';
                        wrapperDate.style.display    = 'none';
                        wrapperExSub.style.display   = 'block';
                        if (examClassSelect && examClassSelect.value) {
                            loadExamSubjects(examClassSelect.value, currentExamSub);
                        }
                    } else {
                        wrapperStudent.style.display = 'none';
                        wrapperExam.style.display    = 'none';
                        wrapperStaff.style.display   = 'flex';
                        wrapperDate.style.display    = 'flex';
                        wrapperExSub.style.display   = 'none';
                        if (staffTypeSelect) {
                            populateStaffByType(staffTypeSelect.value, currentStaffId);
                        }
                    }
                });
            });

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

            function loadSubjects(selectedClass, selectedSection, targetSubject) {
                targetSubject = targetSubject || '';
                if (!subjectSelect) return;
                subjectSelect.innerHTML = '<option value=""><?php echo esc_js( __( '-- Loading Subjects... --', 'ifsedu-school-management' ) ); ?></option>';

                if (!selectedClass) {
                    subjectSelect.innerHTML = '<option value=""><?php echo esc_js( __( '-- All Subjects --', 'ifsedu-school-management' ) ); ?></option>';
                    return;
                }

                jQuery.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'ifs_educore_get_subjects_by_class_report',
                        security: reportNonce,
                        class_name: selectedClass,
                        section_name: selectedSection
                    },
                    success: function(response) {
                        subjectSelect.innerHTML = '';
                        var defaultOpt = document.createElement('option');
                        defaultOpt.value = '';
                        defaultOpt.textContent = '<?php echo esc_js( __( '-- All Subjects --', 'ifsedu-school-management' ) ); ?>';
                        subjectSelect.appendChild(defaultOpt);

                        if (response.success && response.data && response.data.length > 0) {
                            response.data.forEach(function(sub) {
                                var codeStr = sub.subject_code ? ' (' + sub.subject_code + ')' : '';
                                var opt = document.createElement('option');
                                opt.value = sub.subject_name;
                                opt.textContent = sub.subject_name + codeStr;
                                if (sub.subject_name === targetSubject) {
                                    opt.selected = true;
                                }
                                subjectSelect.appendChild(opt);
                            });
                        } else {
                            var noOpt = document.createElement('option');
                            noOpt.value = '';
                            noOpt.textContent = '<?php echo esc_js( __( 'No Subjects Configured', 'ifsedu-school-management' ) ); ?>';
                            subjectSelect.appendChild(noOpt);
                        }
                    },
                    error: function() {
                        subjectSelect.innerHTML = '<option value=""><?php echo esc_js( __( '-- Error Loading Subjects --', 'ifsedu-school-management' ) ); ?></option>';
                    }
                });
            }

            function loadExamSubjects(selectedClass, targetSubject) {
                targetSubject = targetSubject || '';
                if (!examSubSelect) return;
                examSubSelect.innerHTML = '<option value=""><?php echo esc_js( __( '-- Loading Subjects... --', 'ifsedu-school-management' ) ); ?></option>';

                if (!selectedClass) {
                    examSubSelect.innerHTML = '<option value=""><?php echo esc_js( __( '-- Choose Subject --', 'ifsedu-school-management' ) ); ?></option>';
                    return;
                }

                jQuery.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'ifs_educore_get_subjects_by_class_report',
                        security: reportNonce,
                        class_name: selectedClass,
                        section_name: ''
                    },
                    success: function(response) {
                        examSubSelect.innerHTML = '';
                        var defaultOpt = document.createElement('option');
                        defaultOpt.value = '';
                        defaultOpt.textContent = '<?php echo esc_js( __( '-- Choose Subject --', 'ifsedu-school-management' ) ); ?>';
                        examSubSelect.appendChild(defaultOpt);

                        if (response.success && response.data && response.data.length > 0) {
                            response.data.forEach(function(sub) {
                                var codeStr = sub.subject_code ? ' (' + sub.subject_code + ')' : '';
                                var opt = document.createElement('option');
                                opt.value = sub.subject_name;
                                opt.textContent = sub.subject_name + codeStr;
                                if (sub.subject_name === targetSubject) {
                                    opt.selected = true;
                                }
                                examSubSelect.appendChild(opt);
                            });
                        } else {
                            var noOpt = document.createElement('option');
                            noOpt.value = '';
                            noOpt.textContent = '<?php echo esc_js( __( 'No Subjects Found', 'ifsedu-school-management' ) ); ?>';
                            examSubSelect.appendChild(noOpt);
                        }
                    },
                    error: function() {
                        examSubSelect.innerHTML = '<option value=""><?php echo esc_js( __( '-- Error --', 'ifsedu-school-management' ) ); ?></option>';
                    }
                });
            }

            function populateStudents(selectedClass, selectedSecName, selectedStudentId) {
                selectedStudentId = selectedStudentId || '';
                if (!studentSelect) return;
                studentSelect.innerHTML = '<option value=""><?php echo esc_js( __( '-- Choose Student --', 'ifsedu-school-management' ) ); ?></option>';

                var filteredStudents = studentsMap;

                if (selectedClass) {
                    filteredStudents = filteredStudents.filter(function(item) { return item.class_name == selectedClass; });
                }

                if (selectedSecName) {
                    filteredStudents = filteredStudents.filter(function(item) { return item.section_name == selectedSecName; });
                }

                filteredStudents.forEach(function(stu) {
                    var opt = document.createElement('option');
                    opt.value = stu.id;
                    opt.textContent = stu.roll_no ? '[Roll: ' + stu.roll_no + '] ' + stu.full_name + ' (' + stu.class_name + ')' : stu.full_name + ' (' + stu.class_name + ')';
                    
                    if (String(stu.id) === String(selectedStudentId)) {
                        opt.selected = true;
                    }
                    studentSelect.appendChild(opt);
                });
            }

            if (classSelect && sectionSelect && subjectSelect && studentSelect) {
                populateSections(classSelect.value, currentSection);
                populateStudents(classSelect.value, currentSection, currentStudentId);
                if (classSelect.value && currentSubject) {
                    loadSubjects(classSelect.value, currentSection, currentSubject);
                }

                classSelect.addEventListener('change', function() {
                    populateSections(this.value, '');
                    loadSubjects(this.value, '', '');
                    populateStudents(this.value, '', '');
                });

                sectionSelect.addEventListener('change', function() {
                    loadSubjects(classSelect.value, this.value, '');
                    populateStudents(classSelect.value, this.value, '');
                });
            }

            if (examClassSelect) {
                if (examClassSelect.value) {
                    loadExamSubjects(examClassSelect.value, currentExamSub);
                }
                examClassSelect.addEventListener('change', function() {
                    loadExamSubjects(this.value, '');
                });
            }

            function populateStaffByType(selectedType, selectedStaffId) {
                selectedStaffId = selectedStaffId || '';
                if (!staffSelect) return;
                staffSelect.innerHTML = '<option value=""><?php echo esc_js( __( '-- Choose Employee --', 'ifsedu-school-management' ) ); ?></option>';

                var filteredStaff = staffMap;

                if (selectedType && selectedType.trim() !== '') {
                    var targetLower = selectedType.trim().toLowerCase();
                    filteredStaff = staffMap.filter(function(item) {
                        var itemTypeLower = (item.staff_type || '').trim().toLowerCase();
                        return itemTypeLower === targetLower || itemTypeLower.indexOf(targetLower) !== -1 || targetLower.indexOf(itemTypeLower) !== -1;
                    });

                    if (filteredStaff.length === 0) {
                        filteredStaff = staffMap;
                    }
                }

                filteredStaff.forEach(function(st) {
                    var opt = document.createElement('option');
                    opt.value = st.id;
                    var name = st.name_bn ? st.name_bn : st.full_name;
                    opt.textContent = st.designation ? name + ' (' + st.designation + ')' : name;
                    
                    if (String(st.id) === String(selectedStaffId)) {
                        opt.selected = true;
                    }
                    staffSelect.appendChild(opt);
                });
            }

            if (staffTypeSelect && staffSelect) {
                populateStaffByType(staffTypeSelect.value, currentStaffId);

                staffTypeSelect.addEventListener('change', function() {
                    populateStaffByType(this.value);
                });
            }
        });
        </script>
    <?php endif; ?>
<?php
}
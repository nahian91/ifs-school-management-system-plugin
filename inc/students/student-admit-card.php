<?php
/**
 * Enterprise Academic Admit Card Engine & Precision Print Compiler with Financial Clearance & Attendance Calculation
 * File: student-admit-card-view.php
 * Text Domain: ifsedu-school-management
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'educore_admit_get_table' ) ) {
    /**
     * Helper: Defensive table name resolver for admit card module
     */
    function educore_admit_get_table( string $key ): string {
        global $wpdb;
        if ( function_exists( 'educore_get_table_name' ) ) {
            $tbl = educore_get_table_name( $key );
            if ( ! empty( $tbl ) ) {
                return $tbl;
            }
        }
        return $wpdb->prefix . 'sms_' . $key;
    }
}

// --------------------------------------------------------------------------
// 0. AJAX HANDLERS FOR DYNAMIC SELECTORS
// --------------------------------------------------------------------------
add_action( 'wp_ajax_ifs_educore_get_sections_by_class_admit', 'ifs_educore_get_sections_by_class_admit_handler' );
/**
 * AJAX Handler: Get sections by class for admit card generator.
 */
function ifs_educore_get_sections_by_class_admit_handler(): void {
    check_ajax_referer( 'ifs_educore_admit_nonce', 'security' );

    $can_access = ( function_exists( 'educore_has_access' ) && ( educore_has_access( 'educore_manage_exams' ) || educore_has_access( 'educore_manage_students' ) ) ) || current_user_can( 'manage_options' );
    if ( ! $can_access ) {
        wp_send_json_error( array( 'message' => esc_html__( 'Permission denied.', 'ifsedu-school-management' ) ) );
    }

    global $wpdb;
    $table_units = educore_admit_get_table( 'academic_units' );
    $class_name  = isset( $_POST['class_name'] ) ? sanitize_text_field( wp_unslash( $_POST['class_name'] ) ) : '';

    if ( empty( $class_name ) ) {
        wp_send_json_success( array() );
    }

    $sections = $wpdb->get_col(
        $wpdb->prepare(
            'SELECT DISTINCT section_name FROM %i WHERE class_name = %s AND section_name != %s ORDER BY sort_order ASC, section_name ASC',
            $table_units,
            $class_name,
            ''
        )
    );

    wp_send_json_success( is_array( $sections ) ? $sections : array() );
}

add_action( 'wp_ajax_ifs_educore_get_students_by_class_admit', 'ifs_educore_get_students_by_class_admit_handler' );
/**
 * AJAX Handler: Get students by class and section for admit card generator.
 */
function ifs_educore_get_students_by_class_admit_handler(): void {
    check_ajax_referer( 'ifs_educore_admit_nonce', 'security' );

    $can_access = ( function_exists( 'educore_has_access' ) && ( educore_has_access( 'educore_manage_exams' ) || educore_has_access( 'educore_manage_students' ) ) ) || current_user_can( 'manage_options' );
    if ( ! $can_access ) {
        wp_send_json_error( array( 'message' => esc_html__( 'Permission denied.', 'ifsedu-school-management' ) ) );
    }

    global $wpdb;
    $table_students = educore_admit_get_table( 'students' );
    $class_name     = isset( $_POST['class_name'] ) ? sanitize_text_field( wp_unslash( $_POST['class_name'] ) ) : '';
    $section_name   = isset( $_POST['section_name'] ) ? sanitize_text_field( wp_unslash( $_POST['section_name'] ) ) : '';

    if ( empty( $class_name ) ) {
        wp_send_json_success( array() );
    }

    if ( ! empty( $section_name ) ) {
        $students = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT id, full_name, student_id, roll_no FROM %i WHERE status = %s AND class_name = %s AND section_name = %s ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC',
                $table_students,
                'Active',
                $class_name,
                $section_name
            )
        );
    } else {
        $students = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT id, full_name, student_id, roll_no FROM %i WHERE status = %s AND class_name = %s ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC',
                $table_students,
                'Active',
                $class_name
            )
        );
    }

    wp_send_json_success( is_array( $students ) ? $students : array() );
}

// --------------------------------------------------------------------------
// 1. MAIN ADMIT CARD COMPILER VIEW
// --------------------------------------------------------------------------
/**
 * Render Academic Admit Card Compiler View
 */
function educore_student_admit_card_view(): void {
    $can_access = ( function_exists( 'educore_has_access' ) && ( educore_has_access( 'educore_manage_exams' ) || educore_has_access( 'educore_manage_students' ) ) ) || current_user_can( 'manage_options' );
    if ( ! $can_access ) {
        wp_die(
            esc_html__( 'You do not have sufficient administrative permissions to access this page.', 'ifsedu-school-management' ),
            403
        );
    }

    global $wpdb;
    $table_students   = educore_admit_get_table( 'students' );
    $table_units      = educore_admit_get_table( 'academic_units' );
    $table_exams      = educore_admit_get_table( 'exams' );
    $table_attendance = educore_admit_get_table( 'attendance' );
    $table_fees       = educore_admit_get_table( 'fees' );

    // Fetch Exams & Unique Classes
    $exams = $wpdb->get_results(
        $wpdb->prepare(
            'SELECT id, exam_name, start_date, end_date, att_start_date, att_end_date, fee_clear_month FROM %i ORDER BY id DESC',
            $table_exams
        )
    );

    $raw_classes_data = $wpdb->get_results(
        $wpdb->prepare(
            'SELECT class_name, MIN(sort_order) as min_sort 
             FROM %i 
             WHERE class_name IS NOT NULL AND class_name != %s 
             GROUP BY class_name 
             ORDER BY min_sort ASC, CAST(class_name AS UNSIGNED) ASC, class_name ASC',
            $table_units,
            ''
        )
    );

    $classes = array();
    if ( ! empty( $raw_classes_data ) && is_array( $raw_classes_data ) ) {
        foreach ( $raw_classes_data as $c_row ) {
            $c_name = trim( (string) $c_row->class_name );
            if ( ! empty( $c_name ) && ! in_array( $c_name, $classes, true ) ) {
                $classes[] = $c_name;
            }
        }
    }

    // Capture Filter Requests
    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $selected_exam_id = isset( $_GET['exam_id'] ) ? absint( wp_unslash( $_GET['exam_id'] ) ) : 0;
    $selected_class   = isset( $_GET['class_name'] ) ? sanitize_text_field( wp_unslash( $_GET['class_name'] ) ) : '';
    $selected_section = isset( $_GET['section_name'] ) ? sanitize_text_field( wp_unslash( $_GET['section_name'] ) ) : '';
    $selected_student = isset( $_GET['student_id'] ) ? absint( wp_unslash( $_GET['student_id'] ) ) : 0;
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    $available_sections = array();
    $available_students = array();
    if ( ! empty( $selected_class ) ) {
        $available_sections = $wpdb->get_col(
            $wpdb->prepare(
                'SELECT DISTINCT section_name FROM %i WHERE class_name = %s AND section_name != %s ORDER BY sort_order ASC, section_name ASC',
                $table_units,
                $selected_class,
                ''
            )
        );

        if ( ! empty( $selected_section ) ) {
            $available_students = $wpdb->get_results(
                $wpdb->prepare(
                    'SELECT id, full_name, student_id, roll_no FROM %i WHERE status = %s AND class_name = %s AND section_name = %s ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC',
                    $table_students,
                    'Active',
                    $selected_class,
                    $selected_section
                )
            );
        } else {
            $available_students = $wpdb->get_results(
                $wpdb->prepare(
                    'SELECT id, full_name, student_id, roll_no FROM %i WHERE status = %s AND class_name = %s ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC',
                    $table_students,
                    'Active',
                    $selected_class
                )
            );
        }
    }

    $students        = array();
    $exam_title      = '';
    $exam_year       = current_time( 'Y' );
    $exam_att_start  = '';
    $exam_att_end    = '';
    $fee_clear_month = '';

    if ( $selected_exam_id > 0 ) {
        $exam_row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM %i WHERE id = %d LIMIT 1',
                $table_exams,
                $selected_exam_id
            )
        );
        if ( $exam_row ) {
            $exam_title      = (string) $exam_row->exam_name;
            $exam_att_start  = ! empty( $exam_row->att_start_date ) ? (string) $exam_row->att_start_date : ( ! empty( $exam_row->start_date ) ? (string) $exam_row->start_date : gmdate( 'Y-01-01' ) );
            $exam_att_end    = ! empty( $exam_row->att_end_date ) ? (string) $exam_row->att_end_date : ( ! empty( $exam_row->end_date ) ? (string) $exam_row->end_date : current_time( 'Y-m-d' ) );
            $fee_clear_month = ! empty( $exam_row->fee_clear_month ) ? (string) $exam_row->fee_clear_month : '';
            
            if ( ! empty( $exam_att_start ) ) {
                $exam_year = substr( $exam_att_start, 0, 4 );
            } elseif ( ! empty( $exam_row->start_date ) ) {
                $exam_year = substr( (string) $exam_row->start_date, 0, 4 );
            }
        }
    }

    // Calculate Working Days & Attendance Ratio
    $cal_year_num = (int) $exam_year;
    $saved_off_days_map   = get_option( 'educore_academic_off_dates_' . $cal_year_num, array() );
    $attendance_threshold = absint( get_option( 'educore_attendance_threshold', 75 ) );

    $total_working_days = 0;
    if ( ! empty( $exam_att_start ) && ! empty( $exam_att_end ) ) {
        $curr_ts = strtotime( $exam_att_start );
        $end_ts  = strtotime( $exam_att_end );

        while ( $curr_ts && $end_ts && $curr_ts <= $end_ts ) {
            $d_str = gmdate( 'Y-m-d', $curr_ts );
            if ( ! isset( $saved_off_days_map[ $d_str ] ) ) {
                $total_working_days++;
            }
            $curr_ts = strtotime( '+1 day', $curr_ts );
        }
    }
    if ( $total_working_days <= 0 ) {
        $total_working_days = 1;
    }

    $target_fields = 'id, student_id, full_name, class_name, section_name, roll_no, photo_url, guardian_phone, father_phone, student_phone';

    if ( ! empty( $selected_class ) && $selected_exam_id > 0 ) {
        if ( ! empty( $selected_section ) && $selected_student > 0 ) {
            $students = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT {$target_fields} FROM %i WHERE status = %s AND class_name = %s AND section_name = %s AND id = %d ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC",
                    $table_students,
                    'Active',
                    $selected_class,
                    $selected_section,
                    $selected_student
                )
            );
        } elseif ( ! empty( $selected_section ) ) {
            $students = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT {$target_fields} FROM %i WHERE status = %s AND class_name = %s AND section_name = %s ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC",
                    $table_students,
                    'Active',
                    $selected_class,
                    $selected_section
                )
            );
        } elseif ( $selected_student > 0 ) {
            $students = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT {$target_fields} FROM %i WHERE status = %s AND class_name = %s AND id = %d ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC",
                    $table_students,
                    'Active',
                    $selected_class,
                    $selected_student
                )
            );
        } else {
            $students = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT {$target_fields} FROM %i WHERE status = %s AND class_name = %s ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC",
                    $table_students,
                    'Active',
                    $selected_class
                )
            );
        }
    }

    $attendance_present_counts = array();
    $financial_dues_map        = array();

    if ( ! empty( $students ) ) {
        $student_ids_list = array_map( 'absint', wp_list_pluck( $students, 'id' ) );
        $ids_placeholder  = implode( ',', array_fill( 0, count( $student_ids_list ), '%d' ) );

        // Attendance Resolution
        if ( ! empty( $exam_att_start ) && ! empty( $exam_att_end ) ) {
            $att_results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT student_id, COUNT(id) as present_days 
                     FROM %i 
                     WHERE student_id IN ($ids_placeholder) 
                       AND (status = 'Present' OR status = 'Late') 
                       AND attendance_date BETWEEN %s AND %s 
                     GROUP BY student_id",
                    array_merge( array( $table_attendance ), $student_ids_list, array( $exam_att_start, $exam_att_end ) )
                )
            );

            if ( ! empty( $att_results ) ) {
                foreach ( $att_results as $ar ) {
                    $attendance_present_counts[ (int) $ar->student_id ] = absint( $ar->present_days );
                }
            }
        }

        // Financial Clearance Rule Resolution
        if ( ! empty( $fee_clear_month ) ) {
            $months_order = array( 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December' );
            $target_idx   = array_search( $fee_clear_month, $months_order, true );

            if ( false !== $target_idx ) {
                $valid_months = array_slice( $months_order, 0, $target_idx + 1 );
                $m_placeholders = implode( ',', array_fill( 0, count( $valid_months ), '%s' ) );

                $fee_query_sql = "SELECT student_id, SUM(due_amount) as total_due 
                                  FROM %i 
                                  WHERE student_id IN ($ids_placeholder) 
                                    AND fee_year = %s 
                                    AND fee_month IN ($m_placeholders) 
                                  GROUP BY student_id 
                                  HAVING total_due > 0";

                $fee_query_args = array_merge( array( $table_fees ), $student_ids_list, array( (string) $exam_year ), $valid_months );
                $due_results    = $wpdb->get_results( $wpdb->prepare( $fee_query_sql, $fee_query_args ) );

                if ( ! empty( $due_results ) ) {
                    foreach ( $due_results as $dr ) {
                        $financial_dues_map[ (int) $dr->student_id ] = (float) $dr->total_due;
                    }
                }
            }
        }
    }

    $school_name   = get_option( 'educore_school_name', get_bloginfo( 'name' ) );
    $school_tagline = get_option( 'educore_school_tagline', '' );
    $school_logo   = get_option( 'educore_school_logo', '' );
    $principal_sig = get_option( 'educore_principal_sig', '' );

    if ( empty( $school_name ) || 'WordPress' === $school_name ) {
        $school_name = get_bloginfo( 'name' );
    }
    $curr = function_exists( 'educore_get_currency_symbol' ) ? educore_get_currency_symbol() : '৳';
    ?>

    <style id="ifs-educore-admit-card-styles">
        .ifs-educore-admit-engine-root {
            padding: 10px 0;
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #0f172a;
        }
        .ifs-educore-bento-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 15px -3px rgba(0, 0, 0, 0.03);
            margin-bottom: 24px;
        }
        .ifs-educore-admit-cards-container {
            display: flex;
            flex-wrap: wrap;
            gap: 24px;
            margin-top: 25px;
        }
        .ifs-educore-admit-card-wrapper {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            page-break-inside: avoid;
            margin-bottom: 20px;
        }
        .ifs-educore-admit-card-top-tools {
            width: 100%;
            max-width: 180mm;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 6px 14px;
            border-radius: 8px;
            box-sizing: border-box;
        }
        .ifs-educore-single-print-btn {
            background: #00523c;
            color: #ffffff;
            border: none;
            border-radius: 6px;
            padding: 5px 12px;
            font-size: 11.5px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: background 0.2s ease, transform 0.1s ease;
        }
        .ifs-educore-single-print-btn:hover {
            background: #065f46;
            transform: translateY(-1px);
        }
        .ifs-educore-admit-card-box {
            width: 180mm;
            background: #ffffff;
            border: 2px solid #00523c;
            border-radius: 8px;
            padding: 14px 18px;
            box-sizing: border-box;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.06);
            position: relative;
        }
        .ifs-educore-admit-header {
            text-align: center;
            border-bottom: 2px solid #00523c;
            padding-bottom: 10px;
            margin-bottom: 12px;
        }
        .ifs-educore-admit-school-brand-row {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .ifs-educore-admit-logo-img {
            width: 38px;
            height: 38px;
            object-fit: contain;
        }
        .ifs-educore-admit-school-title {
            margin: 0;
            font-size: 20px;
            font-weight: 800;
            color: #00523c;
            text-transform: capitalize;
            letter-spacing: -0.2px;
        }
        .ifs-educore-admit-school-sub {
            font-size: 11.5px;
            color: #64748b;
            margin-top: 2px;
        }
        .ifs-educore-admit-title-badge {
            display: inline-block;
            background: #00523c;
            color: #ffffff;
            font-weight: 800;
            font-size: 12px;
            padding: 4px 18px;
            border-radius: 20px;
            margin-top: 8px;
            text-transform: capitalize;
            letter-spacing: 0.5px;
        }
        .ifs-educore-admit-body-layout {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 12px;
        }
        .ifs-educore-admit-details-column {
            flex: 1;
        }
        .ifs-educore-admit-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12.5px;
        }
        .ifs-educore-admit-table td {
            padding: 4px 0;
        }
        .ifs-educore-admit-table .label-col {
            font-weight: 700;
            color: #64748b;
            width: 34%;
        }
        .ifs-educore-admit-table .value-col {
            font-weight: 800;
            color: #0f172a;
        }
        .ifs-educore-admit-photo-column {
            width: 28mm;
            flex-shrink: 0;
        }
        .ifs-educore-student-photo-frame {
            width: 28mm;
            height: 34mm;
            border: 1px dashed #00523c;
            border-radius: 4px;
            background: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            overflow: hidden;
        }
        .ifs-educore-student-photo-frame img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .ifs-educore-student-photo-frame span {
            font-size: 9px;
            color: #94a3b8;
            font-weight: 700;
            line-height: 1.2;
        }
        .ifs-educore-admit-instructions {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 8px 12px;
            font-size: 11px;
            color: #475569;
            line-height: 1.4;
            margin-bottom: 14px;
        }
        .ifs-educore-signature-container {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            padding-top: 18px;
        }
        .ifs-educore-signature-item {
            text-align: center;
            width: 40%;
        }
        .ifs-educore-signature-line {
            border-top: 1px dashed #0f172a;
            padding-top: 4px;
            font-size: 11px;
            font-weight: 700;
            color: #334155;
        }
        .ifs-educore-admit-sig-img {
            max-height: 24px;
            object-fit: contain;
            display: block;
            margin: 0 auto 3px auto;
        }
        .ifs-educore-form-grid-wrapper {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)) auto;
            gap: 16px;
            align-items: flex-end;
        }
        .ifs-educore-input-block {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .ifs-educore-input-block label {
            font-size: 12.5px;
            font-weight: 700;
            color: #1e293b;
            text-transform: capitalize;
            letter-spacing: 0.3px;
        }
        .ifs-educore-input-block select,
        .ifs-educore-input-block input {
            width: 100%;
            height: 40px;
            border: 1.5px solid #cbd5e1;
            border-radius: 8px;
            padding: 0 12px;
            font-size: 13.5px;
            color: #0f172a;
            background: #ffffff;
            box-sizing: border-box;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .ifs-educore-input-block select:focus,
        .ifs-educore-input-block input:focus {
            border-color: #00523c;
            box-shadow: 0 0 0 3px rgba(0, 82, 60, 0.12);
        }
        .ifs-educore-action-block {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .ifs-educore-btn {
            height: 40px;
            padding: 0 20px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 13.5px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: none;
            text-decoration: none;
            transition: background 0.2s;
        }
        .ifs-educore-btn-primary {
            background: #00523c;
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(0, 82, 60, 0.18);
        }
        .ifs-educore-btn-primary:hover {
            background: #047857;
            color: #ffffff;
        }
        .ifs-educore-btn-secondary {
            background: #f1f5f9;
            color: #0f172a;
            border: 1.5px solid #cbd5e1;
        }
        .ifs-educore-btn-secondary:hover {
            background: #e2e8f0;
        }
        @media print {
            #adminmenuwrap, #adminmenuback, #wpadminbar, #wpfooter, .no-print, .ifs-educore-admit-card-top-tools {
                display: none !important;
            }
            body, .ifs-educore-admit-engine-root, #ifs-educore-printable-admit-area {
                background: transparent !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            body.printing-single-card .ifs-educore-admit-card-wrapper:not(.target-single-print) {
                display: none !important;
            }
            body.printing-single-card .ifs-educore-admit-card-wrapper.target-single-print {
                display: block !important;
                margin: 0 auto !important;
            }
            .ifs-educore-admit-card-box {
                box-shadow: none !important;
                page-break-inside: avoid !important;
                margin: 0 auto 20px auto !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }
    </style>

    <div class="ifs-educore-admit-engine-root">
        <!-- Filter Form -->
        <div class="ifs-educore-bento-card no-print">
            <h3 style="margin: 0 0 16px 0; font-size: 15px; font-weight: 800; color: #0f172a; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px;">
                <span class="dashicons dashicons-tickets-alt" style="color:#00523c; vertical-align:middle;"></span>
                <?php esc_html_e( 'Academic Admit Card Compiler', 'ifsedu-school-management' ); ?>
            </h3>

            <form method="GET" action="" class="ifs-educore-form-grid-wrapper">
                <input type="hidden" name="page" value="school_management_system">
                <input type="hidden" name="tab" value="students">
                <input type="hidden" name="sub" value="admit_card">

                <div class="ifs-educore-input-block">
                    <label for="exam_id"><?php esc_html_e( 'Select Examination', 'ifsedu-school-management' ); ?> <span style="color:#ef4444;">*</span></label>
                    <select name="exam_id" id="exam_id" required>
                        <option value=""><?php esc_html_e( '-- Choose Exam --', 'ifsedu-school-management' ); ?></option>
                        <?php foreach ( $exams as $ex ) : 
                            $ex_y = ! empty( $ex->start_date ) ? substr( (string) $ex->start_date, 0, 4 ) : ( ! empty( $ex->att_start_date ) ? substr( (string) $ex->att_start_date, 0, 4 ) : current_time( 'Y' ) );
                            $ex_label = trim( (string) $ex->exam_name ) . ' (' . $ex_y . ')';
                        ?>
                            <option value="<?php echo (int) $ex->id; ?>" <?php selected( $selected_exam_id, (int) $ex->id ); ?>>
                                <?php echo esc_html( $ex_label ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="ifs-educore-input-block">
                    <label for="ifs_educore_admit_class_select"><?php esc_html_e( 'Select Class', 'ifsedu-school-management' ); ?> <span style="color:#ef4444;">*</span></label>
                    <select name="class_name" id="ifs_educore_admit_class_select" required>
                        <option value=""><?php esc_html_e( '-- Select Class --', 'ifsedu-school-management' ); ?></option>
                        <?php foreach ( $classes as $cls_name ) : ?>
                            <option value="<?php echo esc_attr( $cls_name ); ?>" <?php selected( $selected_class, $cls_name ); ?>>
                                <?php echo esc_html( $cls_name ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="ifs-educore-input-block">
                    <label for="ifs_educore_admit_section_select"><?php esc_html_e( 'Select Section', 'ifsedu-school-management' ); ?></label>
                    <select name="section_name" id="ifs_educore_admit_section_select">
                        <option value=""><?php esc_html_e( '-- All Sections --', 'ifsedu-school-management' ); ?></option>
                        <?php foreach ( $available_sections as $sec_name ) : ?>
                            <option value="<?php echo esc_attr( $sec_name ); ?>" <?php selected( $selected_section, $sec_name ); ?>>
                                <?php echo esc_html( $sec_name ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="ifs-educore-input-block">
                    <label for="ifs_educore_admit_student_select"><?php esc_html_e( 'Single Student (Optional)', 'ifsedu-school-management' ); ?></label>
                    <select name="student_id" id="ifs_educore_admit_student_select">
                        <option value="0"><?php esc_html_e( '-- All Students in Section --', 'ifsedu-school-management' ); ?></option>
                        <?php foreach ( $available_students as $st_item ) : ?>
                            <option value="<?php echo (int) $st_item->id; ?>" <?php selected( $selected_student, (int) $st_item->id ); ?>>
                                <?php echo esc_html( sprintf( '[Roll %1$s] %2$s (%3$s)', $st_item->roll_no, $st_item->full_name, strtoupper( (string) $st_item->student_id ) ) ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="ifs-educore-action-block">
                    <button type="submit" class="ifs-educore-btn ifs-educore-btn-primary">
                        <span class="dashicons dashicons-filter"></span>
                        <?php esc_html_e( 'Compile Cards', 'ifsedu-school-management' ); ?>
                    </button>
                    <?php if ( ! empty( $students ) ) : ?>
                        <button type="button" onclick="window.print();" class="ifs-educore-btn ifs-educore-btn-secondary">
                            <span class="dashicons dashicons-printer"></span>
                            <?php esc_html_e( 'Print All Cards', 'ifsedu-school-management' ); ?>
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Compiled Grid Output Area -->
        <?php if ( ! empty( $selected_class ) && 0 < $selected_exam_id ) : ?>
            <div id="ifs-educore-printable-admit-area">
                <?php if ( ! empty( $students ) ) : ?>
                    <div class="ifs-educore-admit-cards-container">
                        <?php foreach ( $students as $student ) : 
                            $card_id = 'admit_card_' . (int) $student->id;

                            // Attendance calculation
                            $student_present_days = $attendance_present_counts[ (int) $student->id ] ?? 0;
                            $att_percentage       = round( ( $student_present_days / $total_working_days ) * 100, 1 );
                            $is_att_eligible      = $att_percentage >= $attendance_threshold;

                            // Financial Clearance evaluation
                            $has_fee_due   = isset( $financial_dues_map[ (int) $student->id ] );
                            $student_due   = $has_fee_due ? (float) $financial_dues_map[ (int) $student->id ] : 0.00;
                            $is_cleared    = ! $has_fee_due;
                        ?>
                            <div class="ifs-educore-admit-card-wrapper" id="<?php echo esc_attr( $card_id ); ?>">
                                
                                <!-- Top Tools Bar -->
                                <div class="ifs-educore-admit-card-top-tools no-print">
                                    <span style="font-size:11.5px; font-weight:800; color:#0f172a;">
                                        <?php esc_html_e( 'Roll:', 'ifsedu-school-management' ); ?> #<?php echo esc_html( (string) $student->roll_no ); ?> &mdash; <?php echo esc_html( (string) $student->full_name ); ?>
                                    </span>
                                    <div style="display:flex; align-items:center; gap:8px;">
                                        <?php if ( ! empty( $fee_clear_month ) ) : ?>
                                            <?php if ( $is_cleared ) : ?>
                                                <span style="background:#ecfdf5; color:#047857; border:1px solid #a7f3d0; padding:2px 8px; border-radius:4px; font-size:11px; font-weight:800;">
                                                    <?php esc_html_e( 'Fee Cleared', 'ifsedu-school-management' ); ?>
                                                </span>
                                            <?php else : ?>
                                                <span style="background:#fef2f2; color:#dc2626; border:1px solid #fca5a5; padding:2px 8px; border-radius:4px; font-size:11px; font-weight:800;" title="<?php echo esc_attr( sprintf( __( 'Unpaid Dues: %s', 'ifsedu-school-management' ), $curr . number_format( $student_due, 2 ) ) ); ?>">
                                                    <?php esc_html_e( 'HOLD (Unpaid Dues)', 'ifsedu-school-management' ); ?>
                                                </span>
                                            <?php endif; ?>
                                        <?php endif; ?>

                                        <button type="button" onclick="educorePrintSingleCard('<?php echo esc_js( $card_id ); ?>');" class="ifs-educore-single-print-btn">
                                            <span class="dashicons dashicons-printer" style="font-size:13px; width:13px; height:13px;"></span>
                                            <?php esc_html_e( 'Print This Card', 'ifsedu-school-management' ); ?>
                                        </button>
                                    </div>
                                </div>

                                <div class="ifs-educore-admit-card-box">
                                    <!-- Header -->
                                    <div class="ifs-educore-admit-header">
                                        <div class="ifs-educore-admit-school-brand-row">
                                            <?php if ( ! empty( $school_logo ) ) : ?>
                                                <img src="<?php echo esc_url( (string) $school_logo ); ?>" alt="<?php esc_attr_e( 'Logo', 'ifsedu-school-management' ); ?>" class="ifs-educore-admit-logo-img">
                                            <?php endif; ?>
                                            <h3 class="ifs-educore-admit-school-title"><?php echo esc_html( (string) $school_name ); ?></h3>
                                        </div>
                                        <div class="ifs-educore-admit-school-sub">
                                            <?php echo esc_html( ! empty( $school_tagline ) ? (string) $school_tagline : get_bloginfo( 'description' ) ); ?>
                                        </div>
                                        <div class="ifs-educore-admit-title-badge">
                                            <?php
                                            printf(
                                                /* translators: 1: Exam title, 2: Exam year */
                                                esc_html__( 'ADMIT CARD : %1$s — %2$s', 'ifsedu-school-management' ),
                                                esc_html( $exam_title ),
                                                esc_html( $exam_year )
                                            );
                                            ?>
                                        </div>
                                    </div>

                                    <!-- Body Layout -->
                                    <div class="ifs-educore-admit-body-layout">
                                        <div class="ifs-educore-admit-details-column">
                                            <table class="ifs-educore-admit-table">
                                                <tr>
                                                    <td class="label-col"><?php esc_html_e( 'Student ID:', 'ifsedu-school-management' ); ?></td>
                                                    <td class="value-col" style="color: #00523c;"><?php echo esc_html( strtoupper( (string) $student->student_id ) ); ?></td>
                                                </tr>
                                                <tr>
                                                    <td class="label-col"><?php esc_html_e( 'Candidate Name:', 'ifsedu-school-management' ); ?></td>
                                                    <td class="value-col" style="text-transform: capitalize;"><?php echo esc_html( (string) $student->full_name ); ?></td>
                                                </tr>
                                                <tr>
                                                    <td class="label-col"><?php esc_html_e( 'Class & Section:', 'ifsedu-school-management' ); ?></td>
                                                    <td class="value-col">
                                                        <?php echo esc_html( (string) $student->class_name ); ?>
                                                        <?php echo ! empty( $student->section_name ) ? esc_html( ' (Sec: ' . (string) $student->section_name . ')' ) : ''; ?>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td class="label-col"><?php esc_html_e( 'Roll Number:', 'ifsedu-school-management' ); ?></td>
                                                    <td class="value-col">
                                                        <span style="background: #0f172a; color:#ffffff; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 800;">
                                                            #<?php echo esc_html( (string) $student->roll_no ); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td class="label-col"><?php esc_html_e( 'Term Attendance:', 'ifsedu-school-management' ); ?></td>
                                                    <td class="value-col">
                                                        <span style="font-weight:800; color:<?php echo $is_att_eligible ? '#047857' : '#dc2626'; ?>;">
                                                            <?php echo esc_html( (string) $att_percentage ); ?>%
                                                        </span>
                                                        <small style="color:#64748b; font-size:11px; font-weight:600;">
                                                            (<?php echo (int) $student_present_days; ?>/<?php echo (int) $total_working_days; ?> <?php esc_html_e( 'Days', 'ifsedu-school-management' ); ?>)
                                                        </small>
                                                    </td>
                                                </tr>
                                                <?php if ( ! empty( $fee_clear_month ) ) : ?>
                                                    <tr>
                                                        <td class="label-col"><?php esc_html_e( 'Financial Clearance:', 'ifsedu-school-management' ); ?></td>
                                                        <td class="value-col">
                                                            <?php if ( $is_cleared ) : ?>
                                                                <span style="color:#047857; font-weight:800;"><?php esc_html_e( 'Cleared (No Dues)', 'ifsedu-school-management' ); ?></span>
                                                            <?php else : ?>
                                                                <span style="color:#dc2626; font-weight:800;">
                                                                    <?php printf( esc_html__( 'Due: %s', 'ifsedu-school-management' ), esc_html( $curr . number_format( $student_due, 2 ) ) ); ?>
                                                                </span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endif; ?>
                                                <tr>
                                                    <td class="label-col"><?php esc_html_e( 'Guardian Phone:', 'ifsedu-school-management' ); ?></td>
                                                    <td class="value-col" style="color: #334155;">
                                                        <?php echo esc_html( ! empty( $student->guardian_phone ) ? (string) $student->guardian_phone : ( ! empty( $student->father_phone ) ? (string) $student->father_phone : __( 'N/A', 'ifsedu-school-management' ) ) ); ?>
                                                    </td>
                                                </tr>
                                            </table>
                                        </div>

                                        <div class="ifs-educore-admit-photo-column">
                                            <div class="ifs-educore-student-photo-frame">
                                                <?php if ( ! empty( $student->photo_url ) ) : ?>
                                                    <img src="<?php echo esc_url( (string) $student->photo_url ); ?>" alt="<?php echo esc_attr( (string) $student->full_name ); ?>">
                                                <?php else : ?>
                                                    <span><?php echo esc_html( "AFFIX\nPHOTO\nHERE" ); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Candidate Instructions -->
                                    <div class="ifs-educore-admit-instructions">
                                        <strong><?php esc_html_e( 'Important Instructions:', 'ifsedu-school-management' ); ?></strong>
                                        <ol style="margin: 2px 0 0 14px; padding: 0;">
                                            <li><?php esc_html_e( 'Candidates must carry this admit card to the examination hall daily.', 'ifsedu-school-management' ); ?></li>
                                            <li><?php esc_html_e( 'Any unauthorized materials or mobile phones are strictly prohibited.', 'ifsedu-school-management' ); ?></li>
                                        </ol>
                                    </div>

                                    <!-- Signatures -->
                                    <div class="ifs-educore-signature-container">
                                        <div class="ifs-educore-signature-item">
                                            <div class="ifs-educore-signature-line"><?php esc_html_e( 'Controller of Exams', 'ifsedu-school-management' ); ?></div>
                                        </div>
                                        <div class="ifs-educore-signature-item">
                                            <?php if ( ! empty( $principal_sig ) ) : ?>
                                                <img src="<?php echo esc_url( (string) $principal_sig ); ?>" alt="<?php esc_attr_e( 'Signature', 'ifsedu-school-management' ); ?>" class="ifs-educore-admit-sig-img">
                                            <?php endif; ?>
                                            <div class="ifs-educore-signature-line"><?php esc_html_e( 'Principal / Headmaster', 'ifsedu-school-management' ); ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <div style="text-align:center; padding:50px; background:#fff; border:1px dashed #cbd5e1; border-radius:12px;" class="no-print">
                        <span class="dashicons dashicons-warning" style="font-size:36px; color:#94a3b8;"></span>
                        <p style="margin:8px 0 0 0; font-weight:700; color:#64748b;"><?php esc_html_e( 'No active student records matched this query.', 'ifsedu-school-management' ); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Client-Side Script -->
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        var nonce = '<?php echo esc_js( wp_create_nonce( "ifs_educore_admit_nonce" ) ); ?>';

        $('#ifs_educore_admit_class_select').on('change', function() {
            var selectedClass   = $(this).val();
            var $sectionSelect  = $('#ifs_educore_admit_section_select');
            var $studentSelect  = $('#ifs_educore_admit_student_select');

            $sectionSelect.html('<option value=""><?php echo esc_js( __( '-- Loading Sections... --', 'ifsedu-school-management' ) ); ?></option>');
            $studentSelect.html('<option value="0"><?php echo esc_js( __( '-- All Students in Section --', 'ifsedu-school-management' ) ); ?></option>');

            if (!selectedClass) {
                $sectionSelect.html('<option value=""><?php echo esc_js( __( '-- All Sections --', 'ifsedu-school-management' ) ); ?></option>');
                return;
            }

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ifs_educore_get_sections_by_class_admit',
                    security: nonce,
                    class_name: selectedClass
                },
                success: function(response) {
                    $sectionSelect.empty().append($('<option>', {
                        value: '',
                        text: '<?php echo esc_js( __( '-- All Sections --', 'ifsedu-school-management' ) ); ?>'
                    }));

                    if (response.success && response.data.length > 0) {
                        $.each(response.data, function(i, sec) {
                            $sectionSelect.append($('<option>', {
                                value: sec,
                                text: sec
                            }));
                        });
                    }
                    reloadStudentsDropdown();
                }
            });
        });

        $('#ifs_educore_admit_section_select').on('change', function() {
            reloadStudentsDropdown();
        });

        function reloadStudentsDropdown() {
            var selectedClass   = $('#ifs_educore_admit_class_select').val();
            var selectedSection = $('#ifs_educore_admit_section_select').val();
            var $studentSelect  = $('#ifs_educore_admit_student_select');

            if (!selectedClass) return;

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ifs_educore_get_students_by_class_admit',
                    security: nonce,
                    class_name: selectedClass,
                    section_name: selectedSection
                },
                success: function(response) {
                    $studentSelect.empty().append($('<option>', {
                        value: '0',
                        text: '<?php echo esc_js( __( '-- All Students in Section --', 'ifsedu-school-management' ) ); ?>'
                    }));

                    if (response.success && response.data.length > 0) {
                        $.each(response.data, function(i, st) {
                            var uid = (st.student_id || '').toUpperCase();
                            var labelText = '[Roll ' + st.roll_no + '] ' + st.full_name + ' (' + uid + ')';
                            $studentSelect.append($('<option>', {
                                value: st.id,
                                text: labelText
                            }));
                        });
                    }
                }
            });
        }
    });

    // Individual Single Card Print Isolation Trigger.
    function educorePrintSingleCard(cardId) {
        var targetCard = document.getElementById(cardId);
        if (!targetCard) return;

        document.body.classList.add('printing-single-card');
        targetCard.classList.add('target-single-print');

        window.print();

        document.body.classList.remove('printing-single-card');
        targetCard.classList.remove('target-single-print');
    }
    </script>
    <?php
}
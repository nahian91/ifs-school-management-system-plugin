<?php
/**
 * High-End Academic Progress Marksheet & Tabulation Sheet Engine
 * File: inc/results/exams-report.php
 * Text Domain: ifsedu-school-management
 * Layout: Standard NCTB Multi-Component Grid with Isolated Print, PDF & Excel Engines
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// --------------------------------------------------------------------------
// 1. AJAX HANDLERS
// --------------------------------------------------------------------------

add_action( 'wp_ajax_ifs_educore_get_sections_by_class', 'ifs_educore_get_sections_by_class_report_handler' );
/**
 * AJAX Handler: Dynamic Section Loading based on Class
 */
function ifs_educore_get_sections_by_class_report_handler() {
    check_ajax_referer( 'ifs_educore_report_nonce', 'security' );

    $current_user = wp_get_current_user();
    $is_admin     = current_user_can( 'manage_options' ) || in_array( 'administrator', (array) $current_user->roles, true );
    $is_staff     = false;

    if ( function_exists( 'educore_has_access' ) ) {
        $is_staff = educore_has_access( array( 'teacher', 'staff', 'operator', 'instructor', 'editor', 'author' ) );
    }

    global $wpdb;
    $table_staff = $wpdb->prefix . 'sms_staff';

    if ( ! $is_admin && ! $is_staff ) {
        $staff_exists = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM `{$table_staff}` WHERE wp_user_id = %d OR email = %s LIMIT 1",
                $current_user->ID,
                $current_user->user_email
            )
        );
        if ( $staff_exists > 0 ) {
            $is_staff = true;
        }
    }

    if ( ! $is_admin && ! $is_staff ) {
        wp_send_json_error( array( 'message' => esc_html__( 'Permission denied.', 'ifsedu-school-management' ) ) );
    }

    $table_units = $wpdb->prefix . 'sms_academic_units';
    $class_name  = isset( $_POST['class_name'] ) ? sanitize_text_field( wp_unslash( $_POST['class_name'] ) ) : '';

    if ( empty( $class_name ) ) {
        wp_send_json_success( array() );
    }

    $clean_class = trim( str_ireplace( 'Class ', '', $class_name ) );

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $sections = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT DISTINCT section_name FROM `{$table_units}` WHERE (class_name = %s OR class_name = %s) AND section_name != '' ORDER BY sort_order ASC, section_name ASC",
            $class_name,
            $clean_class
        )
    );
    // phpcs:enable

    wp_send_json_success( is_array( $sections ) ? $sections : array() );
}

add_action( 'wp_ajax_ifs_educore_get_students_by_class', 'ifs_educore_get_students_by_class_handler' );
/**
 * AJAX Handler: Dynamic Student Loading based on Class and Section
 */
function ifs_educore_get_students_by_class_handler() {
    check_ajax_referer( 'ifs_educore_report_nonce', 'security' );

    $current_user = wp_get_current_user();
    $is_admin     = current_user_can( 'manage_options' ) || in_array( 'administrator', (array) $current_user->roles, true );
    $is_staff     = false;

    if ( function_exists( 'educore_has_access' ) ) {
        $is_staff = educore_has_access( array( 'teacher', 'staff', 'operator', 'instructor', 'editor', 'author' ) );
    }

    global $wpdb;
    $table_staff = $wpdb->prefix . 'sms_staff';

    if ( ! $is_admin && ! $is_staff ) {
        $staff_exists = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM `{$table_staff}` WHERE wp_user_id = %d OR email = %s LIMIT 1",
                $current_user->ID,
                $current_user->user_email
            )
        );
        if ( $staff_exists > 0 ) {
            $is_staff = true;
        }
    }

    if ( ! $is_admin && ! $is_staff ) {
        wp_send_json_error( array( 'message' => esc_html__( 'Permission denied.', 'ifsedu-school-management' ) ) );
    }

    $table_students = $wpdb->prefix . 'sms_students';
    $class_name     = isset( $_POST['class_name'] ) ? sanitize_text_field( wp_unslash( $_POST['class_name'] ) ) : '';
    $section_name   = isset( $_POST['section_name'] ) ? sanitize_text_field( wp_unslash( $_POST['section_name'] ) ) : '';

    if ( empty( $class_name ) ) {
        wp_send_json_success( array() );
    }

    $clean_class = trim( str_ireplace( 'Class ', '', $class_name ) );

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    if ( ! empty( $section_name ) ) {
        $students = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, full_name, student_id, roll_no FROM `{$table_students}` WHERE status = 'Active' AND (class_name = %s OR class_name = %s) AND section_name = %s ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC",
                $class_name,
                $clean_class,
                $section_name
            )
        );
    } else {
        $students = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, full_name, student_id, roll_no FROM `{$table_students}` WHERE status = 'Active' AND (class_name = %s OR class_name = %s) ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC",
                $class_name,
                $clean_class
            )
        );
    }
    // phpcs:enable

    $data = array();
    if ( ! empty( $students ) ) {
        foreach ( $students as $s ) {
            $data[] = array(
                'id'         => absint( $s->id ),
                'full_name'  => esc_html( $s->full_name ),
                'student_id' => esc_html( (string) $s->student_id ),
                'roll_no'    => esc_html( $s->roll_no ),
            );
        }
    }

    wp_send_json_success( $data );
}

// --------------------------------------------------------------------------
// 2. MAIN REPORT ENGINE VIEW
// --------------------------------------------------------------------------

/**
 * Render Academic Progress Marksheet & Tabulation Sheet Engine View
 */
function educore_exams_report_view() {
    global $wpdb;
    $current_user = wp_get_current_user();

    $table_students = $wpdb->prefix . 'sms_students';
    $table_exams    = $wpdb->prefix . 'sms_exams';
    $table_results  = $wpdb->prefix . 'sms_results';
    $table_units    = $wpdb->prefix . 'sms_academic_units';
    $table_subjects = $wpdb->prefix . 'sms_subjects';
    $table_staff    = $wpdb->prefix . 'sms_staff';

    $is_admin = current_user_can( 'manage_options' ) || in_array( 'administrator', (array) $current_user->roles, true );
    $is_staff = false;

    if ( function_exists( 'educore_has_access' ) ) {
        $is_staff = educore_has_access( array( 'teacher', 'staff', 'operator', 'instructor', 'editor', 'author' ) );
    }

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    if ( ! $is_staff && ! $is_admin ) {
        $staff_exists = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM `{$table_staff}` WHERE wp_user_id = %d OR email = %s LIMIT 1",
                $current_user->ID,
                $current_user->user_email
            )
        );
        if ( $staff_exists > 0 ) {
            $is_staff = true;
        }
    }
    // phpcs:enable

    if ( ! $is_admin && ! $is_staff ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to generate academic reports.', 'ifsedu-school-management' ) );
    }

    $active_tab_slug = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'results';
    $active_sub_slug = isset( $_GET['sub'] ) ? sanitize_key( wp_unslash( $_GET['sub'] ) ) : 'reports';

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $exams = $wpdb->get_results( "SELECT id, exam_name, class_name, subject_ids FROM `{$table_exams}` ORDER BY id DESC" );
    // phpcs:enable

    $exam_class_map = array();
    foreach ( $exams as $ex_item ) {
        $exam_class_map[ $ex_item->id ] = array();
        if ( ! empty( $ex_item->class_name ) ) {
            $classes_array = array_map( 'trim', explode( ',', (string) $ex_item->class_name ) );
            $exam_class_map[ $ex_item->id ] = array_filter( $classes_array );
        }
    }

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $all_classes_raw = $wpdb->get_col( "SELECT DISTINCT class_name FROM `{$table_units}` WHERE class_name != '' ORDER BY sort_order ASC, CAST(class_name AS UNSIGNED) ASC, class_name ASC" );
    // phpcs:enable

    if ( ! empty( $all_classes_raw ) && is_array( $all_classes_raw ) ) {
        $all_classes_raw = array_values( array_unique( $all_classes_raw ) );
        usort( $all_classes_raw, 'strnatcasecmp' );
    }

    // Request Parameters.
    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $filter_exam    = isset( $_GET['exam_id'] ) ? absint( wp_unslash( $_GET['exam_id'] ) ) : 0;
    $report_type    = isset( $_GET['report_type'] ) ? sanitize_key( wp_unslash( $_GET['report_type'] ) ) : 'tabulation';
    $filter_class   = isset( $_GET['class_name'] ) ? sanitize_text_field( wp_unslash( $_GET['class_name'] ) ) : '';
    $filter_section = isset( $_GET['section_name'] ) ? sanitize_text_field( wp_unslash( $_GET['section_name'] ) ) : '';
    $filter_student = isset( $_GET['student_id'] ) ? absint( wp_unslash( $_GET['student_id'] ) ) : 0;
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    $available_sections = array();
    if ( ! empty( $filter_class ) ) {
        $clean_class = trim( str_ireplace( 'Class ', '', $filter_class ) );
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $available_sections = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT section_name FROM `{$table_units}` WHERE (class_name = %s OR class_name = %s) AND section_name != '' ORDER BY sort_order ASC, section_name ASC",
                $filter_class,
                $clean_class
            )
        );
        // phpcs:enable
    }

    $school_name    = get_option( 'educore_school_name', get_bloginfo( 'name' ) );
    $school_tagline = get_option( 'educore_school_tagline', '' );
    $school_logo    = get_option( 'educore_school_logo', '' );
    $principal_sig  = get_option( 'educore_principal_sig', '' );

    if ( empty( $school_name ) || 'WordPress' === $school_name ) {
        $school_name = get_bloginfo( 'name' );
    }

    $back_url = add_query_arg(
        array(
            'page' => 'school_management_system',
            'tab'  => $active_tab_slug,
            'sub'  => 'marks',
        ),
        admin_url( 'admin.php' )
    );
    ?>

    <style>
        .ifs-educore-report-root {
            max-width: 100%;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: inherit;
        }
        .ifs-educore-header-block {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 20px;
        }
        .ifs-educore-header-block h2 {
            margin: 0;
            font-size: 20px;
            font-weight: 800;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .ifs-educore-btn-secondary {
            background: #ffffff;
            border: 1.5px solid #cbd5e1;
            color: #334155;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
        }
        .ifs-educore-btn-secondary:hover {
            background: #f8fafc;
            color: #00523c;
            border-color: #00523c;
        }

        /* Filter Bento Card */
        .ifs-educore-bento-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 20px 24px;
            margin-bottom: 24px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.02);
            box-sizing: border-box;
        }
        .ifs-educore-filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)) 130px;
            gap: 14px;
            align-items: end;
        }
        @media (max-width: 900px) {
            .ifs-educore-filter-grid {
                grid-template-columns: 1fr;
            }
        }
        .ifs-educore-form-group {
            display: flex;
            flex-direction: column;
        }
        .ifs-educore-form-label {
            display: block;
            font-size: 11.5px;
            font-weight: 700;
            color: #475569;
            text-transform: capitalize;
            letter-spacing: 0.4px;
            margin-bottom: 8px;
        }
        .ifs-educore-select-field {
            width: 100% !important;
            height: 42px !important;
            padding: 0 34px 0 14px !important;
            border: 1.5px solid #cbd5e1 !important;
            border-radius: 9px !important;
            font-size: 13.5px !important;
            font-weight: 600 !important;
            color: #0f172a !important;
            background-color: #ffffff !important;
            background-image: url('data:image/svg+xml;utf8,<svg fill="%2364748b" height="20" viewBox="0 0 24 24" width="20" xmlns="http://www.w3.org/2000/svg"><path d="M7 10l5 5 5-5z"/></svg>') !important;
            background-repeat: no-repeat !important;
            background-position: right 10px center !important;
            box-sizing: border-box !important;
            outline: none !important;
            appearance: none !important;
            -webkit-appearance: none !important;
            transition: all 0.2s ease !important;
            cursor: pointer;
        }
        .ifs-educore-select-field:focus {
            border-color: #00523c !important;
            box-shadow: 0 0 0 3px rgba(0, 82, 60, 0.12) !important;
        }
        .ifs-educore-btn-submit-trigger {
            width: 100%;
            height: 42px;
            background: #00523c;
            color: #ffffff;
            border: none;
            border-radius: 9px;
            font-weight: 700;
            font-size: 13.5px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            box-shadow: 0 4px 12px rgba(0, 82, 60, 0.18);
            transition: background 0.2s ease;
        }
        .ifs-educore-btn-submit-trigger:hover {
            background: #047857;
        }

        /* Action Toolbar */
        .ifs-report-actions-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .ifs-report-action-btn {
            background: #ffffff;
            border: 1.5px solid #cbd5e1;
            color: #334155;
            text-decoration: none;
            padding: 8px 18px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .ifs-report-action-btn:hover {
            background: #f8fafc;
            color: #00523c;
            border-color: #00523c;
        }
        .ifs-report-action-btn.excel {
            background: #f0fdf4;
            color: #15803d;
            border-color: #bbf7d0;
        }
        .ifs-report-action-btn.excel:hover {
            background: #dcfce7;
            color: #166534;
        }
        .ifs-report-action-btn.pdf {
            background: #00523c;
            color: #ffffff;
            border-color: #00523c;
            box-shadow: 0 4px 12px rgba(0, 82, 60, 0.2);
        }
        .ifs-report-action-btn.pdf:hover {
            background: #047857;
        }

        /* Column Toggles Toolbar */
        .ifs-col-toggles-bar {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 8px 14px;
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
        }
        .ifs-col-toggles-bar span {
            font-size: 11.5px;
            font-weight: 800;
            color: #64748b;
            text-transform: capitalize;
        }
        .ifs-col-toggles-bar label {
            font-size: 12px;
            font-weight: 700;
            color: #1e293b;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
            user-select: none;
        }

        /* Layout Document Containers */
        .ifs-educore-tabulation-container,
        .ifs-educore-report-card-container {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
            margin-bottom: 30px;
            color: #000000;
        }
        .ifs-educore-report-header {
            text-align: center;
            border-bottom: 2px solid #000000;
            padding-bottom: 14px;
            margin-bottom: 18px;
        }
        .ifs-educore-header-brand-row {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .ifs-educore-header-logo {
            max-height: 48px;
            object-fit: contain;
        }
        .ifs-educore-header-title {
            margin: 0;
            font-size: 22px;
            font-weight: 900;
            text-transform: capitalize;
            color: #000;
            letter-spacing: -0.2px;
        }
        .ifs-educore-header-sub {
            font-size: 11.5px;
            color: #475569;
            font-weight: 700;
            text-transform: capitalize;
            letter-spacing: 1px;
            margin-top: 2px;
        }

        /* Statistics Dashboard */
        .ifs-educore-summary-dashboard {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 12px;
            margin-bottom: 16px;
        }
        .ifs-educore-summary-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px 14px;
            text-align: center;
        }
        .ifs-educore-summary-label {
            font-size: 11px;
            font-weight: 700;
            color: #64748b;
            text-transform: capitalize;
        }
        .ifs-educore-summary-val {
            font-size: 18px;
            font-weight: 800;
            margin-top: 2px;
        }

        /* Grade Counts Pill Bar */
        .ifs-educore-grade-counts-bar {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            flex-wrap: wrap;
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            padding: 8px 14px;
            border-radius: 8px;
            margin-bottom: 18px;
        }
        .ifs-educore-grade-pill {
            font-size: 12px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 5px;
            background: #ffffff;
            border: 1px solid #cbd5e1;
        }

        /* Tabulation Sheet Table Layout */
        .ifs-educore-tabulation-scroll-wrapper {
            overflow-x: auto;
            border: 1px solid #000000;
            margin-bottom: 24px;
        }
        .ifs-educore-tabulation-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
            text-align: center;
        }
        .ifs-educore-tabulation-table th, 
        .ifs-educore-tabulation-table td {
            border: 1px solid #000000;
            padding: 4px 5px;
            vertical-align: middle;
        }
        .ifs-educore-tabulation-table thead th {
            background: #f1f5f9;
            font-weight: 800;
            font-size: 10.5px;
            text-transform: capitalize;
            color: #000000;
        }
        .ifs-educore-tabulation-table thead th.subject-parent-col {
            background: #e2e8f0;
            font-size: 11.5px;
            padding: 5px 6px;
        }
        .ifs-sub-component-hdr {
            font-size: 9px;
            font-weight: 800;
            color: #334155;
            background: #f8fafc;
            padding: 2px 1px;
        }

        /* Student Marksheet Table */
        .ifs-educore-marks-table,
        .ifs-educore-grading-legend-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            margin-bottom: 20px;
            text-align: center;
        }
        .ifs-educore-marks-table th, 
        .ifs-educore-marks-table td,
        .ifs-educore-grading-legend-table th,
        .ifs-educore-grading-legend-table td {
            border: 1px solid #000000;
            padding: 8px 10px;
            vertical-align: middle;
        }
        .ifs-educore-marks-table thead th,
        .ifs-educore-grading-legend-table thead th {
            background: #f1f5f9;
            font-weight: 800;
            font-size: 11.5px;
            text-transform: capitalize;
        }
        .ifs-educore-gpa-box {
            background: #f8fafc;
            border: 1.5px solid #000000;
            padding: 12px 18px;
            border-radius: 6px;
            margin-bottom: 24px;
            text-align: center;
        }

        /* Signatures Grid */
        .ifs-educore-sign-row {
            display: flex;
            justify-content: space-between;
            margin-top: 55px;
            padding: 0 20px;
        }
        .ifs-educore-signature-col {
            text-align: center;
            width: 190px;
        }
        .ifs-educore-sign-line {
            border-top: 1.5px dashed #000000;
            padding-top: 5px;
            font-size: 11px;
            font-weight: 700;
            color: #000000;
        }
        .ifs-educore-sig-img {
            max-height: 38px;
            margin-bottom: 2px;
            display: block;
            margin-left: auto;
            margin-right: auto;
        }

        /* Dynamic Visibility Classes */
        .col-roll-cell, .col-roll-hdr { display: table-cell; }
        .col-id-cell, .col-id-hdr { display: table-cell; }
        .col-name-cell, .col-name-hdr { display: table-cell; }

        body.hide-roll .col-roll-cell, body.hide-roll .col-roll-hdr { display: none !important; }
        body.hide-id .col-id-cell, body.hide-id .col-id-hdr { display: none !important; }
        body.hide-name .col-name-cell, body.hide-name .col-name-hdr { display: none !important; }

        /* --------------------------------------------------------------------------
         * ABSOLUTE FULL RESET FOR PRINTING (Fixes WordPress Blank Print Window)
         * -------------------------------------------------------------------------- */
        @media print {
            #adminmenumain, #adminmenuwrap, #adminmenuback, #wpadminbar, #wpfooter, 
            #screen-meta, #screen-meta-links, .notice, .no-print, 
            .ifs-report-actions-bar, .ifs-col-toggles-bar, .ifs-educore-bento-card, 
            .ifs-educore-header-block, .update-nag {
                display: none !important;
                height: 0 !important;
                visibility: hidden !important;
            }

            html, body {
                height: auto !important;
                min-height: 100% !important;
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                background: #ffffff !important;
                color: #000000 !important;
                overflow: visible !important;
                float: none !important;
            }

            #wpwrap, #wpcontent, #wpbody, #wpbody-content {
                margin: 0 !important;
                padding: 0 !important;
                overflow: visible !important;
                position: static !important;
                float: none !important;
                height: auto !important;
                min-height: auto !important;
                width: 100% !important;
            }

            .ifs-educore-report-root {
                position: static !important;
                overflow: visible !important;
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            .ifs-educore-tabulation-container,
            .ifs-educore-report-card-container {
                display: block !important;
                visibility: visible !important;
                position: static !important;
                border: none !important;
                box-shadow: none !important;
                padding: 0 !important;
                margin: 0 !important;
                width: 100% !important;
            }

            .ifs-educore-tabulation-scroll-wrapper {
                overflow: visible !important;
                border: none !important;
                width: 100% !important;
            }

            table {
                width: 100% !important;
                border-collapse: collapse !important;
                page-break-inside: auto;
            }

            tr {
                page-break-inside: avoid;
                page-break-after: auto;
            }

            th, td {
                border: 1px solid #000000 !important;
                color: #000000 !important;
            }

            .ifs-educore-tabulation-table thead th,
            .ifs-educore-marks-table thead th {
                background: #f1f5f9 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .ifs-educore-sign-row {
                page-break-inside: avoid;
                margin-top: 40px !important;
            }
        }
    </style>

    <div class="ifs-educore-report-root">
        
        <!-- Header Block -->
        <div class="ifs-educore-header-block no-print">
            <h2>
                <span class="dashicons dashicons-clipboard" style="color:#00523c;"></span>
                <?php esc_html_e( 'Academic Progress Marksheet & Tabulation Sheet Engine', 'ifsedu-school-management' ); ?>
            </h2>
            <a href="<?php echo esc_url( $back_url ); ?>" class="ifs-educore-btn-secondary">
                <span class="dashicons dashicons-arrow-left-alt" style="font-size:14px; width:14px; height:14px;"></span>
                <?php esc_html_e( 'Back to Marks Entry', 'ifsedu-school-management' ); ?>
            </a>
        </div>

        <!-- Generator Control Filter Card -->
        <div class="ifs-educore-bento-card no-print">
            <form method="GET" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" id="educoreReportFilterForm">
                <input type="hidden" name="page" value="school_management_system">
                <input type="hidden" name="tab" value="<?php echo esc_attr( $active_tab_slug ); ?>">
                <input type="hidden" name="sub" value="<?php echo esc_attr( $active_sub_slug ); ?>">
                
                <div class="ifs-educore-filter-grid">
                    <!-- 1. Exam Selection -->
                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-form-label"><?php esc_html_e( '1. Select Exam', 'ifsedu-school-management' ); ?> <span style="color:#ef4444;">*</span></label>
                        <select name="exam_id" id="ifs_educore_report_exam_select" class="ifs-educore-select-field" required>
                            <option value=""><?php esc_html_e( '-- Choose Exam --', 'ifsedu-school-management' ); ?></option>
                            <?php foreach ( $exams as $ex ) : ?>
                                <option value="<?php echo absint( $ex->id ); ?>" <?php selected( $filter_exam, $ex->id ); ?>>
                                    <?php echo esc_html( $ex->exam_name ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- 2. Report Type -->
                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-form-label"><?php esc_html_e( '2. Report Type', 'ifsedu-school-management' ); ?> <span style="color:#ef4444;">*</span></label>
                        <select name="report_type" id="ifs_educore_report_type" class="ifs-educore-select-field" required>
                            <option value="tabulation" <?php selected( $report_type, 'tabulation' ); ?>><?php esc_html_e( 'Class Tabulation Sheet (Subject as Column)', 'ifsedu-school-management' ); ?></option>
                            <option value="individual" <?php selected( $report_type, 'individual' ); ?>><?php esc_html_e( 'Student Marksheet', 'ifsedu-school-management' ); ?></option>
                        </select>
                    </div>

                    <!-- 3. Class Selection -->
                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-form-label"><?php esc_html_e( '3. Exam Class', 'ifsedu-school-management' ); ?> <span style="color:#ef4444;">*</span></label>
                        <select name="class_name" id="ifs_educore_class_filter" class="ifs-educore-select-field" required>
                            <option value=""><?php esc_html_e( '-- Select Class --', 'ifsedu-school-management' ); ?></option>
                            <?php foreach ( $all_classes_raw as $cls_item ) : ?>
                                <option value="<?php echo esc_attr( $cls_item ); ?>" <?php selected( $filter_class, $cls_item ); ?>>
                                    <?php echo esc_html( preg_match( '/^class\s+/i', (string) $cls_item ) ? $cls_item : 'Class ' . $cls_item ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- 4. Section Selection -->
                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-form-label"><?php esc_html_e( '4. Section', 'ifsedu-school-management' ); ?></label>
                        <select name="section_name" id="ifs_educore_section_filter" class="ifs-educore-select-field">
                            <option value=""><?php esc_html_e( '-- All Sections --', 'ifsedu-school-management' ); ?></option>
                            <?php foreach ( $available_sections as $sec_val ) : ?>
                                <option value="<?php echo esc_attr( $sec_val ); ?>" <?php selected( $filter_section, $sec_val ); ?>>
                                    <?php echo esc_html( $sec_val ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- 5. Student Selection -->
                    <div class="ifs-educore-form-group" id="student_select_box" style="<?php echo ( 'tabulation' === $report_type ) ? 'display:none;' : ''; ?>">
                        <label class="ifs-educore-form-label"><?php esc_html_e( '5. Target Student', 'ifsedu-school-management' ); ?></label>
                        <select name="student_id" id="ifs_educore_student_filter" class="ifs-educore-select-field">
                            <option value=""><?php esc_html_e( '-- Choose Student --', 'ifsedu-school-management' ); ?></option>
                        </select>
                    </div>

                    <!-- 6. Submit Button -->
                    <div>
                        <button type="submit" class="ifs-educore-btn-submit-trigger">
                            <span class="dashicons dashicons-analytics"></span>
                            <?php esc_html_e( 'Generate', 'ifsedu-school-management' ); ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Dynamic Dropdown AJAX Controller Script -->
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            var nonce         = '<?php echo esc_js( wp_create_nonce( "ifs_educore_report_nonce" ) ); ?>';
            var examClassMap  = <?php echo wp_json_encode( ! empty( $exam_class_map ) ? $exam_class_map : array() ); ?>;
            var allClasses    = <?php echo wp_json_encode( ! empty( $all_classes_raw ) ? $all_classes_raw : array() ); ?>;
            var currentClass  = "<?php echo esc_js( $filter_class ); ?>";
            var currentSection = "<?php echo esc_js( $filter_section ); ?>";
            var currentStudent = "<?php echo esc_js( $filter_student ); ?>";

            function toggleStudentBox() {
                if ($('#ifs_educore_report_type').val() === 'tabulation') {
                    $('#student_select_box').hide();
                } else {
                    $('#student_select_box').show();
                }
            }

            $('#ifs_educore_report_type').on('change', function() {
                toggleStudentBox();
            });

            // Column Visibility Handlers.
            $('#toggle_col_roll').on('change', function() {
                $('body').toggleClass('hide-roll', !$(this).is(':checked'));
            });
            $('#toggle_col_id').on('change', function() {
                $('body').toggleClass('hide-id', !$(this).is(':checked'));
            });
            $('#toggle_col_name').on('change', function() {
                $('body').toggleClass('hide-name', !$(this).is(':checked'));
            });

            function populateExamClasses(examId, selectedClass) {
                var $classSelect = $('#ifs_educore_class_filter');
                $classSelect.html('<option value=""><?php echo esc_js( __( '-- Select Class --', 'ifsedu-school-management' ) ); ?></option>');

                if (!examId) {
                    $classSelect.html('<option value=""><?php echo esc_js( __( '-- Select Exam First --', 'ifsedu-school-management' ) ); ?></option>');
                    $('#ifs_educore_section_filter').html('<option value=""><?php echo esc_js( __( '-- All Sections --', 'ifsedu-school-management' ) ); ?></option>');
                    $('#ifs_educore_student_filter').html('<option value=""><?php echo esc_js( __( '-- Choose Student --', 'ifsedu-school-management' ) ); ?></option>');
                    return;
                }

                var classesToLoad = (examClassMap[examId] && examClassMap[examId].length > 0) ? examClassMap[examId] : allClasses;

                $.each(classesToLoad, function(i, cls) {
                    var sel = (cls === selectedClass) ? 'selected' : '';
                    var displayCls = (/^class\s+/i.test(cls)) ? cls : 'Class ' + cls;
                    $classSelect.append('<option value="' + cls + '" ' + sel + '>' + displayCls + '</option>');
                });
            }

            $('#ifs_educore_report_exam_select').on('change', function() {
                populateExamClasses($(this).val(), '');
                $('#ifs_educore_class_filter').trigger('change');
            });

            $('#ifs_educore_class_filter').on('change', function() {
                var selectedClass  = $(this).val();
                var $sectionSelect = $('#ifs_educore_section_filter');

                $sectionSelect.html('<option value=""><?php echo esc_js( __( '-- All Sections --', 'ifsedu-school-management' ) ); ?></option>');

                if (!selectedClass) {
                    reloadStudents();
                    return;
                }

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'ifs_educore_get_sections_by_class',
                        security: nonce,
                        class_name: selectedClass
                    },
                    success: function(response) {
                        if (response.success && response.data.length > 0) {
                            var secOptions = '<option value=""><?php echo esc_js( __( '-- All Sections --', 'ifsedu-school-management' ) ); ?></option>';
                            $.each(response.data, function(i, sec) {
                                var sel = (sec === currentSection) ? 'selected' : '';
                                secOptions += '<option value="' + sec + '" ' + sel + '>' + sec + '</option>';
                            });
                            $sectionSelect.html(secOptions);
                        }
                        reloadStudents();
                    }
                });
            });

            $('#ifs_educore_section_filter').on('change', function() {
                reloadStudents();
            });

            function reloadStudents() {
                var selectedClass   = $('#ifs_educore_class_filter').val();
                var selectedSection = $('#ifs_educore_section_filter').val();
                var $studentSelect  = $('#ifs_educore_student_filter');

                $studentSelect.html('<option value=""><?php echo esc_js( __( '-- Loading Students... --', 'ifsedu-school-management' ) ); ?></option>');

                if (!selectedClass) {
                    $studentSelect.html('<option value=""><?php echo esc_js( __( '-- Choose Student --', 'ifsedu-school-management' ) ); ?></option>');
                    return;
                }

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'ifs_educore_get_students_by_class',
                        security: nonce,
                        class_name: selectedClass,
                        section_name: selectedSection
                    },
                    success: function(response) {
                        if (response.success && response.data.length > 0) {
                            var options = '<option value=""><?php echo esc_js( __( '-- Choose Student --', 'ifsedu-school-management' ) ); ?></option>';
                            $.each(response.data, function(index, student) {
                                var sel = (String(student.id) === String(currentStudent)) ? 'selected' : '';
                                options += '<option value="' + student.id + '" ' + sel + '>Roll ' + student.roll_no + ': ' + student.full_name + ' (' + student.student_id + ')</option>';
                            });
                            $studentSelect.html(options);
                        } else {
                            $studentSelect.html('<option value=""><?php echo esc_js( __( 'No Active Students Found', 'ifsedu-school-management' ) ); ?></option>');
                        }
                    }
                });
            }

            if ($('#ifs_educore_report_exam_select').val()) {
                populateExamClasses($('#ifs_educore_report_exam_select').val(), currentClass);
                reloadStudents();
            }
        });
        </script>

        <!-- Isolated Print Engine Script -->
        <script type="text/javascript">
        function printIsolatedElement(elementId, orientation) {
            var printableEl = document.getElementById(elementId);
            if (!printableEl) return;

            var printFrame = document.createElement('iframe');
            printFrame.style.position = 'fixed';
            printFrame.style.right = '0';
            printFrame.style.bottom = '0';
            printFrame.style.width = '0';
            printFrame.style.height = '0';
            printFrame.style.border = '0';
            document.body.appendChild(printFrame);

            var frameDoc = printFrame.contentWindow.document;
            frameDoc.open();
            frameDoc.write('<!DOCTYPE html><html><head><title>' + document.title + '</title>');
            frameDoc.write('<style>');
            frameDoc.write('@page { size: ' + (orientation || 'portrait') + '; margin: 8mm; }');
            frameDoc.write('body { font-family: Arial, sans-serif; margin: 0; padding: 0; color: #000; background: #fff; }');
            frameDoc.write('table { width: 100%; border-collapse: collapse; margin-bottom: 15px; font-size: 11px; text-align: center; }');
            frameDoc.write('th, td { border: 1px solid #000; padding: 4px 5px; vertical-align: middle; }');
            frameDoc.write('thead th { background: #f1f5f9 !important; font-weight: bold; }');
            frameDoc.write('.ifs-educore-report-header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 15px; }');
            frameDoc.write('.ifs-educore-header-title { font-size: 20px; font-weight: bold; margin: 0; text-transform: capitalize; }');
            frameDoc.write('.ifs-educore-header-sub { font-size: 11px; color: #475569; font-weight: bold; text-transform: capitalize; margin-top: 2px; }');
            frameDoc.write('.ifs-educore-sign-row { display: flex; justify-content: space-between; margin-top: 40px; padding: 0 20px; }');
            frameDoc.write('.ifs-educore-signature-col { text-align: center; width: 180px; }');
            frameDoc.write('.ifs-educore-sign-line { border-top: 1.5px dashed #000; padding-top: 4px; font-size: 11px; font-weight: bold; }');
            if (document.body.classList.contains('hide-roll')) frameDoc.write('.col-roll-cell, .col-roll-hdr { display: none !important; }');
            if (document.body.classList.contains('hide-id')) frameDoc.write('.col-id-cell, .col-id-hdr { display: none !important; }');
            if (document.body.classList.contains('hide-name')) frameDoc.write('.col-name-cell, .col-name-hdr { display: none !important; }');
            frameDoc.write('</style></head><body>');
            frameDoc.write(printableEl.innerHTML);
            frameDoc.write('</body></html>');
            frameDoc.close();

            setTimeout(function() {
                printFrame.contentWindow.focus();
                printFrame.contentWindow.print();
                setTimeout(function() {
                    document.body.removeChild(printFrame);
                }, 1000);
            }, 300);
        }
        </script>

        <?php
        $clean_filter_class = trim( str_ireplace( 'Class ', '', $filter_class ) );

        // ==========================================================================
        // CASE A: INDIVIDUAL STUDENT MARKSHEET REPORT
        // ==========================================================================
        if ( $filter_exam > 0 && 'individual' === $report_type ) {
            ?>
            <style>
                @media print {
                    @page { size: portrait; margin: 10mm; }
                }
            </style>
            <?php
            if ( empty( $filter_student ) ) {
                echo '<div class="ifs-educore-bento-card no-print" style="text-align:center; color:#64748b; padding:24px;"><strong>' . esc_html__( 'Please select a specific student from the Target Student dropdown to generate the marksheet.', 'ifsedu-school-management' ) . '</strong></div>';
            } else {
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                $student = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table_students}` WHERE id = %d LIMIT 1", $filter_student ) );
                $exam    = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table_exams}` WHERE id = %d LIMIT 1", $filter_exam ) );
                
                $results = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT r.*, COALESCE(s.subject_order, 999) AS subject_order 
                         FROM `{$table_results}` r
                         LEFT JOIN `{$table_units}` u ON (u.class_name = r.class_name OR u.class_name = TRIM(REPLACE(r.class_name, 'Class ', '')))
                         LEFT JOIN `{$table_subjects}` s ON (s.class_id = u.id AND s.subject_name = r.subject_name)
                         WHERE r.exam_id = %d AND r.student_id = %d 
                         GROUP BY r.id
                         ORDER BY subject_order ASC, r.subject_name ASC",
                        $filter_exam,
                        $filter_student
                    )
                );
                // phpcs:enable

                if ( ! $results ) {
                    echo '<div class="ifs-educore-bento-card no-print" style="text-align:center; color:#64748b; padding:30px;">' . esc_html__( 'No published marks found for this student in the selected examination.', 'ifsedu-school-management' ) . '</div>';
                } else {
                    $total_sub          = count( $results );
                    $sum_gpa            = 0;
                    $total_marks_all    = 0;
                    $obtained_marks_all = 0;
                    $has_failed         = false;

                    foreach ( $results as $r ) {
                        $sum_gpa            += floatval( $r->gpa );
                        $total_marks_all    += floatval( $r->total_marks );
                        $obtained_marks_all += floatval( $r->obtained_marks );
                        if ( 'F' === strtoupper( trim( (string) $r->grade ) ) || floatval( $r->gpa ) <= 0 ) {
                            $has_failed = true;
                        }
                    }

                    $avg_gpa   = ( $total_sub > 0 ) ? ( $sum_gpa / $total_sub ) : 0;
                    $final_gpa = $has_failed ? '0.00' : number_format( $avg_gpa, 2 );
                    
                    $final_grade = 'F';
                    if ( ! $has_failed ) {
                        if ( $avg_gpa >= 5.0 ) {
                            $final_grade = 'A+';
                        } elseif ( $avg_gpa >= 4.0 ) {
                            $final_grade = 'A';
                        } elseif ( $avg_gpa >= 3.5 ) {
                            $final_grade = 'A-';
                        } elseif ( $avg_gpa >= 3.0 ) {
                            $final_grade = 'B';
                        } elseif ( $avg_gpa >= 2.0 ) {
                            $final_grade = 'C';
                        } elseif ( $avg_gpa >= 1.0 ) {
                            $final_grade = 'D';
                        }
                    }
                    ?>

                    <!-- Action Export Toolbar -->
                    <div class="ifs-report-actions-bar no-print">
                        <div class="ifs-col-toggles-bar">
                            <span><span class="dashicons dashicons-visibility" style="vertical-align:middle;"></span> <?php esc_html_e( 'Display Options:', 'ifsedu-school-management' ); ?></span>
                            <label><input type="checkbox" id="toggle_col_roll" checked> <?php esc_html_e( 'Roll', 'ifsedu-school-management' ); ?></label>
                            <label><input type="checkbox" id="toggle_col_id" checked> <?php esc_html_e( 'Student ID', 'ifsedu-school-management' ); ?></label>
                            <label><input type="checkbox" id="toggle_col_name" checked> <?php esc_html_e( 'Name', 'ifsedu-school-management' ); ?></label>
                        </div>

                        <div style="display:flex; gap:8px;">
                            <button type="button" onclick="printIsolatedElement('printableMarksheetCard', 'portrait');" class="ifs-report-action-btn pdf">
                                <span class="dashicons dashicons-printer"></span>
                                <?php esc_html_e( 'Download / Print PDF', 'ifsedu-school-management' ); ?>
                            </button>
                            <button type="button" id="btnExportMarksheetExcel" class="ifs-report-action-btn excel">
                                <span class="dashicons dashicons-media-spreadsheet"></span>
                                <?php esc_html_e( 'Export Excel (.xls)', 'ifsedu-school-management' ); ?>
                            </button>
                        </div>
                    </div>

                    <div class="ifs-educore-report-card-container" id="printableMarksheetCard">
                        <div class="ifs-educore-report-header">
                            <div class="ifs-educore-header-brand-row">
                                <?php if ( ! empty( $school_logo ) ) : ?>
                                    <img src="<?php echo esc_url( $school_logo ); ?>" alt="<?php esc_attr_e( 'Logo', 'ifsedu-school-management' ); ?>" class="ifs-educore-header-logo">
                                <?php endif; ?>
                                <h2 class="ifs-educore-header-title"><?php echo esc_html( $school_name ); ?></h2>
                            </div>
                            <?php if ( ! empty( $school_tagline ) ) : ?>
                                <div class="ifs-educore-header-sub"><?php echo esc_html( $school_tagline ); ?></div>
                            <?php endif; ?>
                            <h4 style="margin: 6px 0 4px 0; font-weight: 800; color: #1e293b; font-size: 15px;"><?php echo esc_html( $exam ? $exam->exam_name : '' ); ?> &mdash; <?php esc_html_e( 'Academic Progress Marksheet', 'ifsedu-school-management' ); ?></h4>
                        </div>

                        <!-- Grading Scale Reference -->
                        <table class="ifs-educore-grading-legend-table">
                            <thead>
                                <tr>
                                    <th>Marks</th>
                                    <th>80-100%</th>
                                    <th>70-79%</th>
                                    <th>60-69%</th>
                                    <th>50-59%</th>
                                    <th>40-49%</th>
                                    <th>33-39%</th>
                                    <th>0-32%</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>Grade / GP</strong></td>
                                    <td>A+ (5.00)</td>
                                    <td>A (4.00)</td>
                                    <td>A- (3.50)</td>
                                    <td>B (3.00)</td>
                                    <td>C (2.00)</td>
                                    <td>D (1.00)</td>
                                    <td>F (0.00)</td>
                                </tr>
                            </tbody>
                        </table>

                        <div style="display: flex; justify-content: space-between; background: #f8fafc; border: 1px solid #cbd5e1; padding: 14px 18px; border-radius: 8px; margin-bottom: 20px; font-size: 13px; line-height: 1.6;">
                            <div>
                                <p style="margin: 2px 0;" class="col-name-cell"><strong><?php esc_html_e( 'Student Name:', 'ifsedu-school-management' ); ?></strong> <span style="text-transform: capitalize; font-weight: 800; color:#0f172a;"><?php echo esc_html( $student ? $student->full_name : '—' ); ?></span></p>
                                <p style="margin: 2px 0;" class="col-id-cell"><strong><?php esc_html_e( 'Student ID:', 'ifsedu-school-management' ); ?></strong> <code><?php echo esc_html( $student ? (string) $student->student_id : '—' ); ?></code></p>
                                <p style="margin: 2px 0;"><strong><?php esc_html_e( 'Guardian:', 'ifsedu-school-management' ); ?></strong> <?php echo esc_html( ! empty( $student->guardian_name ) ? $student->guardian_name : ( ! empty( $student->father_name ) ? $student->father_name : '—' ) ); ?></p>
                            </div>
                            <div style="text-align: right;">
                                <p style="margin: 2px 0;"><strong><?php esc_html_e( 'Class:', 'ifsedu-school-management' ); ?></strong> <?php echo esc_html( $student ? $student->class_name : $filter_class ); ?></p>
                                <p style="margin: 2px 0;"><strong><?php esc_html_e( 'Section:', 'ifsedu-school-management' ); ?></strong> <?php echo esc_html( ! empty( $student->section_name ) ? $student->section_name : __( 'N/A', 'ifsedu-school-management' ) ); ?></p>
                                <p style="margin: 2px 0;" class="col-roll-cell"><strong><?php esc_html_e( 'Roll Number:', 'ifsedu-school-management' ); ?></strong> <span style="background: #ffffff; border: 1px solid #cbd5e1; padding: 2px 8px; border-radius: 4px; font-weight: 800;">#<?php echo esc_html( $student ? $student->roll_no : '—' ); ?></span></p>
                            </div>
                        </div>

                        <table class="ifs-educore-marks-table">
                            <thead>
                                <tr>
                                    <th style="text-align: left; width: 32%;"><?php esc_html_e( 'Subject Name', 'ifsedu-school-management' ); ?></th>
                                    <th><?php esc_html_e( 'Full Marks', 'ifsedu-school-management' ); ?></th>
                                    <th><?php esc_html_e( 'MCQ', 'ifsedu-school-management' ); ?></th>
                                    <th><?php esc_html_e( 'CQ', 'ifsedu-school-management' ); ?></th>
                                    <th><?php esc_html_e( 'PR', 'ifsedu-school-management' ); ?></th>
                                    <th><?php esc_html_e( 'Obtained', 'ifsedu-school-management' ); ?></th>
                                    <th><?php esc_html_e( 'Grade', 'ifsedu-school-management' ); ?></th>
                                    <th><?php esc_html_e( 'GP', 'ifsedu-school-management' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $results as $r ) : 
                                    $row_failed = ( 'F' === strtoupper( trim( (string) $r->grade ) ) || floatval( $r->gpa ) <= 0 );
                                ?>
                                <tr>
                                    <td style="text-align: left; font-weight: 700; color: #0f172a;"><?php echo esc_html( $r->subject_name ); ?></td>
                                    <td><?php echo floatval( $r->total_marks ); ?></td>
                                    <td><?php echo isset( $r->mcq_marks ) && floatval( $r->mcq_marks ) > 0 ? floatval( $r->mcq_marks ) : '—'; ?></td>
                                    <td><?php echo isset( $r->cq_marks ) && floatval( $r->cq_marks ) > 0 ? floatval( $r->cq_marks ) : '—'; ?></td>
                                    <td><?php echo isset( $r->practical_marks ) && floatval( $r->practical_marks ) > 0 ? floatval( $r->practical_marks ) : '—'; ?></td>
                                    <td><strong><?php echo floatval( $r->obtained_marks ); ?></strong></td>
                                    <td style="font-weight: 800; color: <?php echo $row_failed ? '#dc2626' : '#059669'; ?>;"><?php echo esc_html( $r->grade ); ?></td>
                                    <td><strong style="color: <?php echo $row_failed ? '#dc2626' : '#00523c'; ?>;"><?php echo number_format( floatval( $r->gpa ), 2 ); ?></strong></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <div class="ifs-educore-gpa-box">
                            <h4 style="margin: 0; font-weight: 800; color: #00523c; text-transform: capitalize; font-size: 13.5px;"><?php esc_html_e( 'Final Result Summary', 'ifsedu-school-management' ); ?></h4>
                            <p style="font-size: 14px; margin: 6px 0 0 0; color: #1e293b;">
                                <?php esc_html_e( 'Status:', 'ifsedu-school-management' ); ?> 
                                <strong style="color: <?php echo $has_failed ? '#dc2626' : '#059669'; ?>;">
                                    <?php echo $has_failed ? esc_html__( 'FAILED (F)', 'ifsedu-school-management' ) : sprintf( esc_html__( 'PASSED (%s)', 'ifsedu-school-management' ), esc_html( $final_grade ) ); ?>
                                </strong> &nbsp;|&nbsp; 
                                <?php esc_html_e( 'Total Score:', 'ifsedu-school-management' ); ?> <strong><?php echo floatval( $obtained_marks_all ); ?> / <?php echo floatval( $total_marks_all ); ?></strong> &nbsp;|&nbsp;
                                <?php esc_html_e( 'GPA:', 'ifsedu-school-management' ); ?> <strong style="font-size: 16px; color: #00523c;"><?php echo esc_html( $final_gpa ); ?></strong>
                            </p>
                        </div>

                        <div class="ifs-educore-sign-row">
                            <div class="ifs-educore-signature-col">
                                <div class="ifs-educore-sign-line"><?php esc_html_e( 'Class Teacher Signature', 'ifsedu-school-management' ); ?></div>
                            </div>
                            <div class="ifs-educore-signature-col">
                                <div class="ifs-educore-sign-line"><?php esc_html_e( 'Exam Controller', 'ifsedu-school-management' ); ?></div>
                            </div>
                            <div class="ifs-educore-signature-col">
                                <?php if ( ! empty( $principal_sig ) ) : ?>
                                    <img src="<?php echo esc_url( $principal_sig ); ?>" alt="<?php esc_attr_e( 'Signature', 'ifsedu-school-management' ); ?>" class="ifs-educore-sig-img">
                                <?php endif; ?>
                                <div class="ifs-educore-sign-line"><?php esc_html_e( 'Principal / Headmaster', 'ifsedu-school-management' ); ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Client-Side Excel Serialization -->
                    <script type="text/javascript">
                    document.getElementById('btnExportMarksheetExcel').addEventListener('click', function() {
                        var cardContent = document.getElementById('printableMarksheetCard').innerHTML;
                        var excelTemplate = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">' +
                            '<head><meta charset="utf-8"><!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet>' +
                            '<x:Name>Marksheet</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->' +
                            '<style>table{border-collapse:collapse;} th,td{border:1px solid #000; text-align:center; padding:5px; font-family:Arial;}</style></head>' +
                            '<body>' + cardContent + '</body></html>';

                        var blob = new Blob([excelTemplate], { type: 'application/vnd.ms-excel;charset=utf-8' });
                        var url  = URL.createObjectURL(blob);
                        var a    = document.createElement('a');
                        a.href = url;
                        a.download = 'Marksheet_<?php echo esc_js( $student ? $student->student_id : 'report' ); ?>.xls';
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                    });
                    </script>
                    <?php
                }
            }
        }

        // ==========================================================================
        // CASE B: CLASS TABULATION SHEET REPORT
        // ==========================================================================
        elseif ( $filter_exam > 0 && 'tabulation' === $report_type && ! empty( $filter_class ) ) {
            ?>
            <style>
                @media print {
                    @page { size: landscape; margin: 8mm; }
                }
            </style>
            <?php
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $exam = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table_exams}` WHERE id = %d LIMIT 1", $filter_exam ) );
            
            $students = array();
            if ( ! empty( $filter_section ) ) {
                $students = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT * FROM `{$table_students}` WHERE status = 'Active' AND (class_name = %s OR class_name = %s) AND section_name = %s ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC",
                        $filter_class,
                        $clean_filter_class,
                        $filter_section
                    )
                );
            } else {
                $students = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT * FROM `{$table_students}` WHERE status = 'Active' AND (class_name = %s OR class_name = %s) ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC",
                        $filter_class,
                        $clean_filter_class
                    )
                );
            }

            // Primary: Fetch evaluated subjects.
            $subjects_objects = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT r.subject_name, 
                            MAX(r.total_marks) as total_marks,
                            MAX(r.cq_marks) as max_cq,
                            MAX(r.mcq_marks) as max_mcq,
                            MAX(r.practical_marks) as max_pr,
                            MIN(COALESCE(s.subject_order, 999)) as s_order
                     FROM `{$table_results}` r
                     LEFT JOIN `{$table_units}` u ON (u.class_name = r.class_name OR u.class_name = TRIM(REPLACE(r.class_name, 'Class ', '')))
                     LEFT JOIN `{$table_subjects}` s ON (s.class_id = u.id AND s.subject_name = r.subject_name)
                     WHERE r.exam_id = %d AND (r.class_name = %s OR r.class_name = %s)
                     GROUP BY r.subject_name
                     ORDER BY s_order ASC, r.subject_name ASC",
                    $filter_exam,
                    $filter_class,
                    $clean_filter_class
                )
            );

            // Fallback to configured subjects.
            if ( empty( $subjects_objects ) ) {
                $subjects_objects = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT s.subject_name, s.total_marks, s.cq_marks as max_cq, s.mcq_marks as max_mcq, s.practical_marks as max_pr, s.subject_order as s_order
                         FROM `{$table_subjects}` s
                         INNER JOIN `{$table_units}` u ON s.class_id = u.id
                         WHERE u.class_name = %s OR u.class_name = %s
                         GROUP BY s.subject_name
                         ORDER BY s.subject_order ASC, s.subject_name ASC",
                        $filter_class,
                        $clean_filter_class
                    )
                );
            }
            // phpcs:enable

            if ( empty( $students ) || empty( $subjects_objects ) ) {
                $sec_label = ! empty( $filter_section ) ? ' (' . esc_html( $filter_section ) . ')' : '';
                echo '<div class="ifs-educore-bento-card no-print" style="text-align:center; color:#64748b; padding:30px;">' . sprintf( esc_html__( 'No students or subject configurations found for %1$s%2$s in this exam scheme.', 'ifsedu-school-management' ), '<strong>' . esc_html( $filter_class ) . '</strong>', '<strong>' . esc_html( $sec_label ) . '</strong>' ) . '</div>';
            } else {
                $all_student_ids = array_map( 'absint', wp_list_pluck( $students, 'id' ) );
                $results_map     = array();

                if ( ! empty( $all_student_ids ) ) {
                    $in_placeholders = implode( ',', array_map( 'absint', $all_student_ids ) );
                    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                    $raw_tab_results = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT student_id, subject_name, cq_marks, mcq_marks, practical_marks, obtained_marks, grade, gpa 
                             FROM `{$table_results}` 
                             WHERE exam_id = %d AND student_id IN ({$in_placeholders})",
                            $filter_exam
                        )
                    );
                    // phpcs:enable

                    if ( ! empty( $raw_tab_results ) ) {
                        foreach ( $raw_tab_results as $r_item ) {
                            $results_map[ $r_item->student_id ][ $r_item->subject_name ] = $r_item;
                        }
                    }
                }

                // Summary Statistics Calculation.
                $total_students_count = count( $students );
                $passed_count         = 0;
                $failed_count         = 0;
                $grade_counts         = array(
                    'A+' => 0,
                    'A'  => 0,
                    'A-' => 0,
                    'B'  => 0,
                    'C'  => 0,
                    'D'  => 0,
                    'F'  => 0,
                );

                foreach ( $students as $s_calc ) {
                    $student_calc_id = absint( $s_calc->id );
                    $st_res          = isset( $results_map[ $student_calc_id ] ) ? $results_map[ $student_calc_id ] : array();
                    $s_sum_gpa       = 0;
                    $s_sub_cnt       = 0;
                    $s_failed        = false;

                    foreach ( $subjects_objects as $sub_obj ) {
                        $sub_k = $sub_obj->subject_name;
                        if ( isset( $st_res[ $sub_k ] ) ) {
                            $s_sum_gpa += floatval( $st_res[ $sub_k ]->gpa );
                            $s_sub_cnt++;
                            if ( 'F' === strtoupper( trim( (string) $st_res[ $sub_k ]->grade ) ) || floatval( $st_res[ $sub_k ]->gpa ) <= 0 ) {
                                $s_failed = true;
                            }
                        }
                    }

                    if ( 0 === $s_sub_cnt || $s_failed ) {
                        $failed_count++;
                        $grade_counts['F']++;
                    } else {
                        $passed_count++;
                        $s_avg = $s_sum_gpa / $s_sub_cnt;
                        if ( $s_avg >= 5.0 ) {
                            $grade_counts['A+']++;
                        } elseif ( $s_avg >= 4.0 ) {
                            $grade_counts['A']++;
                        } elseif ( $s_avg >= 3.5 ) {
                            $grade_counts['A-']++;
                        } elseif ( $s_avg >= 3.0 ) {
                            $grade_counts['B']++;
                        } elseif ( $s_avg >= 2.0 ) {
                            $grade_counts['C']++;
                        } elseif ( $s_avg >= 1.0 ) {
                            $grade_counts['D']++;
                        } else {
                            $grade_counts['F']++;
                        }
                    }
                }

                $pass_percentage = ( $total_students_count > 0 ) ? number_format( ( $passed_count / $total_students_count ) * 100, 1 ) : 0;
                ?>

                <!-- Action Export & Print Toolbar -->
                <div class="ifs-report-actions-bar no-print">
                    <div class="ifs-col-toggles-bar">
                        <span><span class="dashicons dashicons-visibility" style="vertical-align:middle;"></span> <?php esc_html_e( 'Display Options:', 'ifsedu-school-management' ); ?></span>
                        <label><input type="checkbox" id="toggle_col_roll" checked> <?php esc_html_e( 'Roll', 'ifsedu-school-management' ); ?></label>
                        <label><input type="checkbox" id="toggle_col_id" checked> <?php esc_html_e( 'Student ID', 'ifsedu-school-management' ); ?></label>
                        <label><input type="checkbox" id="toggle_col_name" checked> <?php esc_html_e( 'Name', 'ifsedu-school-management' ); ?></label>
                    </div>

                    <div style="display:flex; gap:8px;">
                        <button type="button" onclick="printIsolatedElement('printableTabulationSheet', 'landscape');" class="ifs-report-action-btn pdf">
                            <span class="dashicons dashicons-printer"></span>
                            <?php esc_html_e( 'Download / Print PDF', 'ifsedu-school-management' ); ?>
                        </button>
                        <button type="button" id="btnExportTabulationExcel" class="ifs-report-action-btn excel">
                            <span class="dashicons dashicons-media-spreadsheet"></span>
                            <?php esc_html_e( 'Export Excel (.xls)', 'ifsedu-school-management' ); ?>
                        </button>
                    </div>
                </div>

                <div class="ifs-educore-tabulation-container" id="printableTabulationSheet">
                    <div class="ifs-educore-report-header">
                        <div class="ifs-educore-header-brand-row">
                            <?php if ( ! empty( $school_logo ) ) : ?>
                                <img src="<?php echo esc_url( $school_logo ); ?>" alt="<?php esc_attr_e( 'Logo', 'ifsedu-school-management' ); ?>" class="ifs-educore-header-logo">
                            <?php endif; ?>
                            <h3 class="ifs-educore-header-title"><?php echo esc_html( $school_name ); ?></h3>
                        </div>
                        <?php if ( ! empty( $school_tagline ) ) : ?>
                            <div class="ifs-educore-header-sub"><?php echo esc_html( $school_tagline ); ?></div>
                        <?php endif; ?>
                        <h5 style="margin: 6px 0 0 0; font-weight: 800; color: #1e293b; font-size: 15px;"><?php echo esc_html( $exam ? $exam->exam_name : '' ); ?> &mdash; <?php esc_html_e( 'Official Academic Tabulation Sheet', 'ifsedu-school-management' ); ?></h5>
                        <span style="display: inline-block; background: #f1f5f9; color: #475569; padding: 3px 14px; border-radius: 20px; font-size: 12px; font-weight: 700; margin-top: 6px; border: 1px solid #cbd5e1;">
                            <?php echo esc_html( preg_match( '/^class\s+/i', (string) $filter_class ) ? $filter_class : 'Class ' . $filter_class ); ?>
                            <?php if ( ! empty( $filter_section ) ) : ?>
                                (<?php esc_html_e( 'Section:', 'ifsedu-school-management' ); ?> <?php echo esc_html( $filter_section ); ?>)
                            <?php endif; ?>
                        </span>
                    </div>

                    <!-- Summary Metrics Dashboard -->
                    <div class="ifs-educore-summary-dashboard no-print">
                        <div class="ifs-educore-summary-card">
                            <div class="ifs-educore-summary-label"><?php esc_html_e( 'Total Students', 'ifsedu-school-management' ); ?></div>
                            <div class="ifs-educore-summary-val" style="color: #0f172a;"><?php echo esc_html( $total_students_count ); ?></div>
                        </div>
                        <div class="ifs-educore-summary-card" style="background:#f0fdf4; border-color:#bbf7d0;">
                            <div class="ifs-educore-summary-label" style="color:#15803d;"><?php esc_html_e( 'Passed', 'ifsedu-school-management' ); ?></div>
                            <div class="ifs-educore-summary-val" style="color:#166534;"><?php echo esc_html( $passed_count ); ?></div>
                        </div>
                        <div class="ifs-educore-summary-card" style="background:#fef2f2; border-color:#fecaca;">
                            <div class="ifs-educore-summary-label" style="color:#b91c1c;"><?php esc_html_e( 'Failed', 'ifsedu-school-management' ); ?></div>
                            <div class="ifs-educore-summary-val" style="color:#dc2626;"><?php echo esc_html( $failed_count ); ?></div>
                        </div>
                        <div class="ifs-educore-summary-card" style="background:#eff6ff; border-color:#bfdbfe;">
                            <div class="ifs-educore-summary-label" style="color:#1d4ed8;"><?php esc_html_e( 'Pass Rate', 'ifsedu-school-management' ); ?></div>
                            <div class="ifs-educore-summary-val" style="color:#1e40af;"><?php echo esc_html( $pass_percentage ); ?>%</div>
                        </div>
                    </div>

                    <!-- Grade Breakdown Bar -->
                    <div class="ifs-educore-grade-counts-bar no-print">
                        <div style="font-size:11.5px; font-weight:800; color:#475569; text-transform:capitalize; margin-right:6px;">
                            <span class="dashicons dashicons-chart-pie" style="font-size:15px; width:15px; height:15px; vertical-align:middle;"></span>
                            <?php esc_html_e( 'Grade Breakdown:', 'ifsedu-school-management' ); ?>
                        </div>
                        <span class="ifs-educore-grade-pill">A+: <strong><?php echo esc_html( $grade_counts['A+'] ); ?></strong></span>
                        <span class="ifs-educore-grade-pill">A: <strong><?php echo esc_html( $grade_counts['A'] ); ?></strong></span>
                        <span class="ifs-educore-grade-pill">A-: <strong><?php echo esc_html( $grade_counts['A-'] ); ?></strong></span>
                        <span class="ifs-educore-grade-pill">B: <strong><?php echo esc_html( $grade_counts['B'] ); ?></strong></span>
                        <span class="ifs-educore-grade-pill">C: <strong><?php echo esc_html( $grade_counts['C'] ); ?></strong></span>
                        <span class="ifs-educore-grade-pill">D: <strong><?php echo esc_html( $grade_counts['D'] ); ?></strong></span>
                        <span class="ifs-educore-grade-pill" style="color:#dc2626;">F: <strong><?php echo esc_html( $grade_counts['F'] ); ?></strong></span>
                    </div>

                    <!-- Matrix Table -->
                    <div class="ifs-educore-tabulation-scroll-wrapper">
                        <table class="ifs-educore-tabulation-table" id="exportableTabulationTable">
                            <thead>
                                <!-- ROW 1: Subjects Parent Header Groups -->
                                <tr>
                                    <th rowspan="2" class="col-roll-hdr" style="width: 45px;"><?php esc_html_e( 'Roll', 'ifsedu-school-management' ); ?></th>
                                    <th rowspan="2" class="col-id-hdr" style="width: 85px;"><?php esc_html_e( 'Student ID', 'ifsedu-school-management' ); ?></th>
                                    <th rowspan="2" class="col-name-hdr" style="min-width: 140px; text-align: left;"><?php esc_html_e( 'Student Name', 'ifsedu-school-management' ); ?></th>
                                    
                                    <?php foreach ( $subjects_objects as $sub_col ) : ?>
                                        <th colspan="4" class="subject-parent-col">
                                            <?php echo esc_html( $sub_col->subject_name ); ?>
                                            <span style="font-size:9.5px; font-weight:600; color:#475569; display:block;">(<?php echo floatval( $sub_col->total_marks ); ?>)</span>
                                        </th>
                                    <?php endforeach; ?>

                                    <th rowspan="2" style="min-width: 65px; background:#e2e8f0;"><?php esc_html_e( 'Total', 'ifsedu-school-management' ); ?></th>
                                    <th rowspan="2" style="min-width: 55px; background:#e2e8f0;"><?php esc_html_e( 'GPA', 'ifsedu-school-management' ); ?></th>
                                    <th rowspan="2" style="min-width: 65px; background:#e2e8f0;"><?php esc_html_e( 'Result', 'ifsedu-school-management' ); ?></th>
                                </tr>
                                <!-- ROW 2: Subject Components -->
                                <tr>
                                    <?php foreach ( $subjects_objects as $sub_col ) : ?>
                                        <th class="ifs-sub-component-hdr" style="width:30px;">MCQ</th>
                                        <th class="ifs-sub-component-hdr" style="width:30px;">CQ</th>
                                        <th class="ifs-sub-component-hdr" style="width:30px;">PR</th>
                                        <th class="ifs-sub-component-hdr" style="width:40px; background:#eef2f6;">Tot (GP)</th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $students as $s ) : 
                                    $student_tab_id  = absint( $s->id );
                                    $student_results = isset( $results_map[ $student_tab_id ] ) ? $results_map[ $student_tab_id ] : array();

                                    $total_obtained = 0;
                                    $sum_gpa        = 0;
                                    $sub_count      = 0;
                                    $has_failed     = false;
                                ?>
                                <tr>
                                    <td class="col-roll-cell"><strong>#<?php echo esc_html( $s->roll_no ); ?></strong></td>
                                    <td class="col-id-cell"><code><?php echo esc_html( (string) $s->student_id ); ?></code></td>
                                    <td class="col-name-cell" style="text-align: left; font-weight: 700; color: #0f172a; white-space: nowrap;"><?php echo esc_html( $s->full_name ); ?></td>
                                    
                                    <?php foreach ( $subjects_objects as $sub_col ) : 
                                        $sub_name = $sub_col->subject_name;
                                        if ( isset( $student_results[ $sub_name ] ) ) {
                                            $res            = $student_results[ $sub_name ];
                                            $total_obtained += floatval( $res->obtained_marks );
                                            $sum_gpa        += floatval( $res->gpa );
                                            $sub_count++;

                                            $sub_failed = ( 'F' === strtoupper( trim( (string) $res->grade ) ) || floatval( $res->gpa ) <= 0 );
                                            if ( $sub_failed ) {
                                                $has_failed = true;
                                            }

                                            $cq_val  = isset( $res->cq_marks ) && floatval( $res->cq_marks ) > 0 ? floatval( $res->cq_marks ) : '—';
                                            $mcq_val = isset( $res->mcq_marks ) && floatval( $res->mcq_marks ) > 0 ? floatval( $res->mcq_marks ) : '—';
                                            $pr_val  = isset( $res->practical_marks ) && floatval( $res->practical_marks ) > 0 ? floatval( $res->practical_marks ) : '—';
                                            ?>
                                            <td><?php echo esc_html( $mcq_val ); ?></td>
                                            <td><?php echo esc_html( $cq_val ); ?></td>
                                            <td><?php echo esc_html( $pr_val ); ?></td>
                                            <td style="background: <?php echo $sub_failed ? '#fef2f2' : '#f0fdf4'; ?>;">
                                                <strong><?php echo floatval( $res->obtained_marks ); ?></strong><br>
                                                <small style="font-weight: 800; font-size:9.5px; color: <?php echo $sub_failed ? '#dc2626' : '#047857'; ?>;">(<?php echo esc_html( $res->grade ); ?>)</small>
                                            </td>
                                        <?php } else { ?>
                                            <td style="color: #94a3b8;">—</td>
                                            <td style="color: #94a3b8;">—</td>
                                            <td style="color: #94a3b8;">—</td>
                                            <td style="color: #94a3b8; background:#f8fafc;">—</td>
                                        <?php }
                                    endforeach; 

                                    $avg_gpa   = ( $sub_count > 0 ) ? ( $sum_gpa / $sub_count ) : 0;
                                    $final_gpa = $has_failed ? '0.00' : number_format( $avg_gpa, 2 );
                                    ?>

                                    <td style="font-weight: 800; color:#0f172a;"><?php echo floatval( $total_obtained ); ?></td>
                                    <td style="font-weight: 800; color: <?php echo $has_failed ? '#dc2626' : '#00523c'; ?>;"><?php echo esc_html( $final_gpa ); ?></td>
                                    <td>
                                        <span style="padding: 2px 6px; border-radius: 12px; font-weight: 800; font-size: 10px; background: <?php echo $has_failed ? '#fee2e2' : '#ecfdf5'; ?>; color: <?php echo $has_failed ? '#dc2626' : '#047857'; ?>; border: 1px solid <?php echo $has_failed ? '#fecaca' : '#a7f3d0'; ?>;">
                                            <?php echo $has_failed ? esc_html__( 'FAIL', 'ifsedu-school-management' ) : esc_html__( 'PASS', 'ifsedu-school-management' ); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="ifs-educore-sign-row">
                        <div class="ifs-educore-signature-col">
                            <div class="ifs-educore-sign-line"><?php esc_html_e( 'Tabulator Signature', 'ifsedu-school-management' ); ?></div>
                        </div>
                        <div class="ifs-educore-signature-col">
                            <div class="ifs-educore-sign-line"><?php esc_html_e( 'Exam Controller', 'ifsedu-school-management' ); ?></div>
                        </div>
                        <div class="ifs-educore-signature-col">
                            <?php if ( ! empty( $principal_sig ) ) : ?>
                                <img src="<?php echo esc_url( $principal_sig ); ?>" alt="<?php esc_attr_e( 'Signature', 'ifsedu-school-management' ); ?>" class="ifs-educore-sig-img">
                            <?php endif; ?>
                            <div class="ifs-educore-sign-line"><?php esc_html_e( 'Principal / Headmaster', 'ifsedu-school-management' ); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Client-Side Excel Serialization -->
                <script type="text/javascript">
                document.getElementById('btnExportTabulationExcel').addEventListener('click', function() {
                    var cardContent = document.getElementById('printableTabulationSheet').innerHTML;
                    var excelTemplate = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">' +
                        '<head><meta charset="utf-8"><!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet>' +
                        '<x:Name>Tabulation Sheet</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->' +
                        '<style>table{border-collapse:collapse;} th,td{border:1px solid #000; text-align:center; padding:4px; font-family:Arial; font-size:10pt;}</style></head>' +
                        '<body>' + cardContent + '</body></html>';

                    var blob = new Blob([excelTemplate], { type: 'application/vnd.ms-excel;charset=utf-8' });
                    var url  = URL.createObjectURL(blob);
                    var a    = document.createElement('a');
                    a.href = url;
                    a.download = 'Tabulation_Sheet_<?php echo esc_js( $filter_class ); ?>.xls';
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                });
                </script>
                <?php
            }
        }
        ?>

    </div>
    <?php
}
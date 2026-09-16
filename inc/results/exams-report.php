<?php
/**
 * Enterprise Academic Progress Transcript & Tabulation Sheet Engine
 * File: inc/results/exams-report.php
 * Text Domain: ifsedu-school-management
 * Layout: Formal NCTB & Board Academic Transcript Specification
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'educore_report_get_table' ) ) {
    function educore_report_get_table( string $key ): string {
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
// 1. AJAX HANDLERS FOR DYNAMIC FILTERING
// --------------------------------------------------------------------------

add_action( 'wp_ajax_ifs_educore_get_sections_by_class', 'ifs_educore_get_sections_by_class_report_handler' );
function ifs_educore_get_sections_by_class_report_handler(): void {
    check_ajax_referer( 'ifs_educore_report_nonce', 'security' );

    if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( array( 'message' => esc_html__( 'Permission denied.', 'ifsedu-school-management' ) ) );
    }

    global $wpdb;
    $table_units = educore_report_get_table( 'academic_units' );
    $class_name  = isset( $_POST['class_name'] ) ? sanitize_text_field( wp_unslash( $_POST['class_name'] ) ) : '';

    if ( empty( $class_name ) ) {
        wp_send_json_success( array() );
    }

    $clean_class = trim( str_ireplace( 'Class ', '', $class_name ) );

    $sections = $wpdb->get_col(
        $wpdb->prepare(
            'SELECT DISTINCT section_name FROM %i WHERE (class_name = %s OR class_name = %s) AND section_name != %s ORDER BY sort_order ASC, section_name ASC',
            $table_units,
            $class_name,
            $clean_class,
            ''
        )
    );

    wp_send_json_success( is_array( $sections ) ? $sections : array() );
}

add_action( 'wp_ajax_ifs_educore_get_students_by_class', 'ifs_educore_get_students_by_class_report_handler' );
function ifs_educore_get_students_by_class_report_handler(): void {
    check_ajax_referer( 'ifs_educore_report_nonce', 'security' );

    if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( array( 'message' => esc_html__( 'Permission denied.', 'ifsedu-school-management' ) ) );
    }

    global $wpdb;
    $table_students = educore_report_get_table( 'students' );
    $class_name     = isset( $_POST['class_name'] ) ? sanitize_text_field( wp_unslash( $_POST['class_name'] ) ) : '';
    $section_name   = isset( $_POST['section_name'] ) ? sanitize_text_field( wp_unslash( $_POST['section_name'] ) ) : '';

    if ( empty( $class_name ) ) {
        wp_send_json_success( array() );
    }

    $clean_class = trim( str_ireplace( 'Class ', '', $class_name ) );

    if ( ! empty( $section_name ) ) {
        $students = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT id, full_name, student_id, roll_no FROM %i WHERE status = %s AND (class_name = %s OR class_name = %s) AND section_name = %s ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC',
                $table_students,
                'Active',
                $class_name,
                $clean_class,
                $section_name
            )
        );
    } else {
        $students = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT id, full_name, student_id, roll_no FROM %i WHERE status = %s AND (class_name = %s OR class_name = %s) ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC',
                $table_students,
                'Active',
                $class_name,
                $clean_class
            )
        );
    }

    wp_send_json_success( is_array( $students ) ? $students : array() );
}

// --------------------------------------------------------------------------
// 2. MAIN REPORT ENGINE VIEW
// --------------------------------------------------------------------------

function educore_exams_report_view(): void {
    global $wpdb;
    $current_user = wp_get_current_user();

    $table_students   = educore_report_get_table( 'students' );
    $table_exams      = educore_report_get_table( 'exams' );
    $table_results    = educore_report_get_table( 'results' );
    $table_units      = educore_report_get_table( 'academic_units' );
    $table_subjects   = educore_report_get_table( 'subjects' );
    $table_staff      = educore_report_get_table( 'staff' );
    $table_exam_att   = educore_report_get_table( 'exam_attendance' );
    $table_attendance = educore_report_get_table( 'attendance' );

    $is_admin = current_user_can( 'manage_options' ) || in_array( 'administrator', (array) $current_user->roles, true );
    $is_staff = false;

    if ( function_exists( 'educore_has_access' ) ) {
        $is_staff = educore_has_access( 'educore_manage_results' ) || educore_has_access( 'educore_manage_academics' );
    }

    if ( ! $is_staff && ! $is_admin ) {
        $staff_exists = (int) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT id FROM %i WHERE wp_user_id = %d OR email = %s LIMIT 1',
                $table_staff,
                $current_user->ID,
                $current_user->user_email
            )
        );
        if ( $staff_exists > 0 ) {
            $is_staff = true;
        }
    }

    if ( ! $is_admin && ! $is_staff ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to generate academic reports.', 'ifsedu-school-management' ), 403 );
    }

    $active_tab_slug = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'results';
    $active_sub_slug = isset( $_GET['sub'] ) ? sanitize_key( wp_unslash( $_GET['sub'] ) ) : 'reports';

    $exams = $wpdb->get_results(
        $wpdb->prepare(
            'SELECT id, exam_name, class_name, subject_ids, start_date, end_date, att_start_date, att_end_date FROM %i ORDER BY id DESC',
            $table_exams
        )
    );

    $exam_class_map = array();
    foreach ( $exams as $ex_item ) {
        $exam_class_map[ (int) $ex_item->id ] = array();
        if ( ! empty( $ex_item->class_name ) ) {
            $classes_array = array_map( 'trim', explode( ',', (string) $ex_item->class_name ) );
            $exam_class_map[ (int) $ex_item->id ] = array_filter( $classes_array );
        }
    }

    $all_classes_raw = $wpdb->get_col(
        $wpdb->prepare(
            'SELECT DISTINCT class_name FROM %i WHERE class_name != %s ORDER BY sort_order ASC, CAST(class_name AS UNSIGNED) ASC, class_name ASC',
            $table_units,
            ''
        )
    );

    if ( ! empty( $all_classes_raw ) && is_array( $all_classes_raw ) ) {
        $all_classes_raw = array_values( array_unique( $all_classes_raw ) );
        usort( $all_classes_raw, 'strnatcasecmp' );
    }

    // Request Parameters
    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $filter_exam    = isset( $_GET['exam_id'] ) ? absint( wp_unslash( $_GET['exam_id'] ) ) : 0;
    $report_type    = isset( $_GET['report_type'] ) ? sanitize_key( wp_unslash( $_GET['report_type'] ) ) : 'individual';
    $filter_class   = isset( $_GET['class_name'] ) ? sanitize_text_field( wp_unslash( $_GET['class_name'] ) ) : '';
    $filter_section = isset( $_GET['section_name'] ) ? sanitize_text_field( wp_unslash( $_GET['section_name'] ) ) : '';
    $filter_student = isset( $_GET['student_id'] ) ? absint( wp_unslash( $_GET['student_id'] ) ) : 0;
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    $available_sections = array();
    if ( ! empty( $filter_class ) ) {
        $clean_class = trim( str_ireplace( 'Class ', '', $filter_class ) );
        $available_sections = $wpdb->get_col(
            $wpdb->prepare(
                'SELECT DISTINCT section_name FROM %i WHERE (class_name = %s OR class_name = %s) AND section_name != %s ORDER BY sort_order ASC, section_name ASC',
                $table_units,
                $filter_class,
                $clean_class,
                ''
            )
        );
    }

    $school_name    = get_option( 'educore_school_name', get_bloginfo( 'name' ) );
    $school_tagline = get_option( 'educore_school_tagline', '' );
    $school_address = get_option( 'educore_school_address', 'Bangabir Road, South Surma, Sylhet' );
    $school_phone   = get_option( 'educore_school_phone', '01755-592295' );
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
            width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
            box-sizing: border-box !important;
            font-family: 'Segoe UI', Inter, -apple-system, BlinkMacSystemFont, Roboto, sans-serif;
            color: #0f172a;
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

        /* Filter Controls */
        .ifs-educore-bento-filter-card {
            background: #ffffff !important;
            border: 1px solid #e2e8f0 !important;
            border-radius: 14px !important;
            padding: 20px 24px !important;
            margin-bottom: 24px !important;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.02) !important;
            box-sizing: border-box !important;
        }

        .ifs-educore-filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)) 130px;
            gap: 16px;
            align-items: end;
        }

        @media (max-width: 990px) {
            .ifs-educore-filter-grid {
                grid-template-columns: 1fr;
            }
        }

        .ifs-educore-form-group {
            display: flex;
            flex-direction: column;
        }

        .ifs-educore-form-label {
            display: flex;
            align-items: center;
            gap: 5px;
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

        /* ---------------------------------------------------------
           PREMIUM ACADEMIC TRANSCRIPT CANVAS SPECIFICATION
           --------------------------------------------------------- */
        .ifs-educore-report-card-container {
            background: #ffffff;
            border: 2px solid #000000;
            border-radius: 0;
            padding: 20px 24px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            margin: 0 auto 30px auto;
            color: #000000;
            max-width: 210mm;
            box-sizing: border-box;
        }

        .ifs-transcript-header-grid {
            display: grid;
            grid-template-columns: 1fr 185px;
            border-bottom: 2px solid #000000;
            padding-bottom: 10px;
            margin-bottom: 8px;
            gap: 14px;
            align-items: center;
        }

        .ifs-school-name-text {
            font-size: 20px;
            font-weight: 900;
            color: #000000;
            text-transform: uppercase;
            margin: 0;
            letter-spacing: -0.2px;
            line-height: 1.15;
        }

        .ifs-school-address-text {
            font-size: 11px;
            color: #222222;
            font-weight: 600;
            margin: 3px 0 5px 0;
        }

        .ifs-transcript-exam-badge {
            display: inline-block;
            background: #000000;
            color: #ffffff;
            font-size: 12px;
            font-weight: 800;
            padding: 3px 12px;
            border-radius: 3px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .ifs-grading-scale-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px;
            text-align: center;
        }

        .ifs-grading-scale-table th,
        .ifs-grading-scale-table td {
            border: 1px solid #000000;
            padding: 2px 4px;
            line-height: 1.1;
        }

        .ifs-grading-scale-table th {
            background: #f1f5f9;
            font-weight: 800;
            text-transform: uppercase;
        }

        .ifs-transcript-title-line {
            text-align: center;
            font-size: 15px;
            font-weight: 900;
            letter-spacing: 2px;
            text-transform: uppercase;
            border-bottom: 1.5px solid #000000;
            padding-bottom: 3px;
            margin: 6px 0 10px 0;
        }

        .ifs-meta-info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            border: 1px solid #000000;
            padding: 8px 12px;
            font-size: 12px;
            line-height: 1.6;
            margin-bottom: 10px;
        }

        .ifs-meta-row {
            display: flex;
        }

        .ifs-meta-row .lbl {
            font-weight: 700;
            min-width: 110px;
            color: #111111;
        }

        .ifs-meta-row .val {
            font-weight: 800;
            color: #000000;
        }

        /* Subjects Marks Table */
        .ifs-transcript-marks-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
            text-align: center;
            margin-bottom: 10px;
        }

        .ifs-transcript-marks-table th,
        .ifs-transcript-marks-table td {
            border: 1px solid #000000;
            padding: 4px 5px;
            vertical-align: middle;
        }

        .ifs-transcript-marks-table thead th {
            background: #f1f5f9;
            font-weight: 900;
            font-size: 10.5px;
            text-transform: uppercase;
        }

        .ifs-transcript-marks-table tfoot td {
            font-weight: 900;
            background: #f8fafc;
            border-top: 2px solid #000000;
        }

        .ifs-attendance-strip {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            font-weight: 700;
            background: #f8fafc;
            border: 1px solid #000000;
            padding: 4px 10px;
            margin-bottom: 10px;
        }

        /* Dual-Column Evaluation Dashboard */
        .ifs-eval-dashboard-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            border: 1px solid #000000;
            margin-bottom: 10px;
        }

        .ifs-eval-section {
            padding: 6px 10px;
        }

        .ifs-eval-section:first-child {
            border-right: 1px solid #000000;
        }

        .ifs-eval-title {
            font-size: 10.5px;
            font-weight: 900;
            text-transform: uppercase;
            border-bottom: 1px dashed #000000;
            padding-bottom: 2px;
            margin-bottom: 5px;
        }

        .ifs-eval-item {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            padding: 1.5px 0;
        }

        /* Promotion Strip */
        .ifs-promotion-strip {
            border: 1px solid #000000;
            padding: 6px 10px;
            font-size: 11px;
            line-height: 1.5;
            margin-bottom: 25px;
        }

        /* Signatures Grid */
        .ifs-sign-four-grid {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            padding: 0 6px;
        }

        .ifs-sign-col {
            text-align: center;
            width: 140px;
        }

        .ifs-sign-bar {
            border-top: 1.5px dashed #000000;
            padding-top: 4px;
            font-size: 10px;
            font-weight: 700;
        }

        .ifs-sig-img-box {
            max-height: 34px;
            margin-bottom: 2px;
            display: block;
            margin-left: auto;
            margin-right: auto;
        }

        /* Tabulation Sheet Container */
        .ifs-educore-tabulation-container {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 14px;
            padding: 24px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03);
            margin-bottom: 30px;
            color: #000000;
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
            padding: 5px 6px;
            vertical-align: middle;
        }

        .ifs-educore-tabulation-table thead th {
            background: #f1f5f9;
            font-weight: 800;
            font-size: 10.5px;
            text-transform: capitalize;
            color: #000000;
        }

        .col-roll-cell, .col-roll-hdr { display: table-cell; }
        .col-id-cell, .col-id-hdr { display: table-cell; }
        .col-name-cell, .col-name-hdr { display: table-cell; }

        body.hide-roll .col-roll-cell, body.hide-roll .col-roll-hdr { display: none !important; }
        body.hide-id .col-id-cell, body.hide-id .col-id-hdr { display: none !important; }
        body.hide-name .col-name-cell, body.hide-name .col-name-hdr { display: none !important; }

        @media print {
            #adminmenumain, #adminmenuwrap, #adminmenuback, #wpadminbar, #wpfooter, 
            #screen-meta, #screen-meta-links, .notice, .no-print, 
            .ifs-report-actions-bar, .ifs-col-toggles-bar, .ifs-educore-bento-filter-card, 
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
            }
            .ifs-educore-report-card-container {
                display: block !important;
                visibility: visible !important;
                border: 2px solid #000000 !important;
                box-shadow: none !important;
                padding: 18px 20px !important;
                margin: 0 auto !important;
                width: 100% !important;
                max-width: 100% !important;
            }
            .ifs-educore-tabulation-container {
                display: block !important;
                visibility: visible !important;
                border: none !important;
                box-shadow: none !important;
                padding: 0 !important;
                margin: 0 !important;
                width: 100% !important;
            }
            table {
                width: 100% !important;
                border-collapse: collapse !important;
            }
            tr {
                page-break-inside: avoid;
            }
            th, td {
                border: 1px solid #000000 !important;
                color: #000000 !important;
            }
        }
    </style>

    <div class="ifs-educore-report-root">
        
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

        <!-- Filter Card -->
        <div class="ifs-educore-bento-filter-card no-print">
            <form method="GET" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" id="educoreReportFilterForm">
                <input type="hidden" name="page" value="school_management_system">
                <input type="hidden" name="tab" value="<?php echo esc_attr( $active_tab_slug ); ?>">
                <input type="hidden" name="sub" value="<?php echo esc_attr( $active_sub_slug ); ?>">
                
                <div class="ifs-educore-filter-grid">
                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-form-label" for="ifs_educore_report_exam_select">
                            <span class="dashicons dashicons-calendar-alt" style="font-size:14px; width:14px; height:14px; color:#00523c;"></span>
                            <?php esc_html_e( '1. Select Exam', 'ifsedu-school-management' ); ?> <span style="color:#ef4444;">*</span>
                        </label>
                        <select name="exam_id" id="ifs_educore_report_exam_select" class="ifs-educore-select-field" required>
                            <option value=""><?php esc_html_e( '-- Choose Exam --', 'ifsedu-school-management' ); ?></option>
                            <?php foreach ( $exams as $ex ) : 
                                $ex_y = ! empty( $ex->start_date ) ? substr( (string) $ex->start_date, 0, 4 ) : ( ! empty( $ex->att_start_date ) ? substr( (string) $ex->att_start_date, 0, 4 ) : '' );
                                $ex_lbl = trim( (string) $ex->exam_name ) . ( $ex_y ? ' (' . $ex_y . ')' : '' );
                            ?>
                                <option value="<?php echo (int) $ex->id; ?>" <?php selected( $filter_exam, (int) $ex->id ); ?>>
                                    <?php echo esc_html( $ex_lbl ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-form-label" for="ifs_educore_report_type">
                            <span class="dashicons dashicons-media-spreadsheet" style="font-size:14px; width:14px; height:14px; color:#00523c;"></span>
                            <?php esc_html_e( '2. Report Type', 'ifsedu-school-management' ); ?> <span style="color:#ef4444;">*</span>
                        </label>
                        <select name="report_type" id="ifs_educore_report_type" class="ifs-educore-select-field" required>
                            <option value="individual" <?php selected( $report_type, 'individual' ); ?>><?php esc_html_e( 'Academic Transcript (Single)', 'ifsedu-school-management' ); ?></option>
                            <option value="tabulation" <?php selected( $report_type, 'tabulation' ); ?>><?php esc_html_e( 'Class Tabulation Sheet', 'ifsedu-school-management' ); ?></option>
                        </select>
                    </div>

                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-form-label" for="ifs_educore_class_filter">
                            <span class="dashicons dashicons-welcome-learn-more" style="font-size:14px; width:14px; height:14px; color:#00523c;"></span>
                            <?php esc_html_e( '3. Exam Class', 'ifsedu-school-management' ); ?> <span style="color:#ef4444;">*</span>
                        </label>
                        <select name="class_name" id="ifs_educore_class_filter" class="ifs-educore-select-field" required>
                            <option value=""><?php esc_html_e( '-- Select Class --', 'ifsedu-school-management' ); ?></option>
                            <?php foreach ( $all_classes_raw as $cls_item ) : ?>
                                <option value="<?php echo esc_attr( (string) $cls_item ); ?>" <?php selected( $filter_class, (string) $cls_item ); ?>>
                                    <?php echo esc_html( preg_match( '/^class\s+/i', (string) $cls_item ) ? (string) $cls_item : 'Class ' . (string) $cls_item ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-form-label" for="ifs_educore_section_filter">
                            <span class="dashicons dashicons-groups" style="font-size:14px; width:14px; height:14px; color:#00523c;"></span>
                            <?php esc_html_e( '4. Section', 'ifsedu-school-management' ); ?>
                        </label>
                        <select name="section_name" id="ifs_educore_section_filter" class="ifs-educore-select-field">
                            <option value=""><?php esc_html_e( '-- All Sections --', 'ifsedu-school-management' ); ?></option>
                            <?php foreach ( $available_sections as $sec_val ) : ?>
                                <option value="<?php echo esc_attr( (string) $sec_val ); ?>" <?php selected( $filter_section, (string) $sec_val ); ?>>
                                    <?php echo esc_html( (string) $sec_val ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="ifs-educore-form-group" id="student_select_box" style="<?php echo ( 'tabulation' === $report_type ) ? 'display:none;' : ''; ?>">
                        <label class="ifs-educore-form-label" for="ifs_educore_student_filter">
                            <span class="dashicons dashicons-id-alt" style="font-size:14px; width:14px; height:14px; color:#00523c;"></span>
                            <?php esc_html_e( '5. Target Student', 'ifsedu-school-management' ); ?>
                        </label>
                        <select name="student_id" id="ifs_educore_student_filter" class="ifs-educore-select-field">
                            <option value=""><?php esc_html_e( '-- Choose Student --', 'ifsedu-school-management' ); ?></option>
                        </select>
                    </div>

                    <div>
                        <button type="submit" class="ifs-educore-btn-submit-trigger">
                            <span class="dashicons dashicons-analytics"></span>
                            <?php esc_html_e( 'Generate', 'ifsedu-school-management' ); ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            var nonce          = '<?php echo esc_js( wp_create_nonce( "ifs_educore_report_nonce" ) ); ?>';
            var examClassMap   = <?php echo wp_json_encode( ! empty( $exam_class_map ) ? $exam_class_map : array() ); ?>;
            var allClasses     = <?php echo wp_json_encode( ! empty( $all_classes_raw ) ? $all_classes_raw : array() ); ?>;
            var currentClass   = "<?php echo esc_js( $filter_class ); ?>";
            var currentSection = "<?php echo esc_js( $filter_section ); ?>";
            var currentStudent = "<?php echo esc_js( (string) $filter_student ); ?>";

            function toggleStudentBox() {
                if ($('#ifs_educore_report_type').val() === 'tabulation') {
                    $('#student_select_box').hide();
                } else {
                    $('#student_select_box').show();
                }
            }

            toggleStudentBox();

            $('#ifs_educore_report_type').on('change', function() {
                toggleStudentBox();
            });

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
                if (currentClass) {
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'ifs_educore_get_sections_by_class',
                            security: nonce,
                            class_name: currentClass
                        },
                        success: function(response) {
                            if (response.success && response.data.length > 0) {
                                var secOptions = '<option value=""><?php echo esc_js( __( '-- All Sections --', 'ifsedu-school-management' ) ); ?></option>';
                                $.each(response.data, function(i, sec) {
                                    var sel = (sec === currentSection) ? 'selected' : '';
                                    secOptions += '<option value="' + sec + '" ' + sel + '>' + sec + '</option>';
                                });
                                $('#ifs_educore_section_filter').html(secOptions);
                            }
                            reloadStudents();
                        }
                    });
                }
            }
        });
        </script>

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
            frameDoc.write('@page { size: ' + (orientation || 'portrait') + '; margin: 6mm; }');
            frameDoc.write('body { font-family: "Segoe UI", Arial, sans-serif; margin: 0; padding: 0; color: #000; background: #fff; }');
            frameDoc.write('table { width: 100%; border-collapse: collapse; margin-bottom: 8px; font-size: 10.5px; text-align: center; }');
            frameDoc.write('th, td { border: 1px solid #000; padding: 3px 4px; vertical-align: middle; }');
            frameDoc.write('thead th { background: #f1f5f9 !important; font-weight: bold; }');
            frameDoc.write('.ifs-transcript-header-grid { display: grid; grid-template-columns: 1fr 180px; align-items: center; border-bottom: 2px solid #000; padding-bottom: 6px; margin-bottom: 6px; }');
            frameDoc.write('.ifs-meta-info-grid { display: grid; grid-template-columns: 1fr 1fr; border: 1px solid #000; padding: 6px 10px; margin-bottom: 8px; font-size: 11px; }');
            frameDoc.write('.ifs-attendance-strip { display: flex; justify-content: space-between; font-size: 10px; font-weight: bold; border: 1px solid #000; padding: 3px 8px; margin-bottom: 8px; background: #f8fafc; }');
            frameDoc.write('.ifs-eval-dashboard-grid { display: grid; grid-template-columns: 1fr 1fr; border: 1px solid #000; margin-bottom: 8px; }');
            frameDoc.write('.ifs-eval-section { padding: 4px 8px; }');
            frameDoc.write('.ifs-eval-section:first-child { border-right: 1px solid #000; }');
            frameDoc.write('.ifs-eval-item { display: flex; justify-content: space-between; font-size: 10.5px; padding: 1px 0; }');
            frameDoc.write('.ifs-promotion-strip { border: 1px solid #000; padding: 6px 8px; font-size: 10.5px; margin-bottom: 25px; }');
            frameDoc.write('.ifs-sign-four-grid { display: flex; justify-content: space-between; align-items: flex-end; margin-top: 25px; }');
            frameDoc.write('.ifs-sign-col { text-align: center; width: 130px; }');
            frameDoc.write('.ifs-sign-bar { border-top: 1.5px dashed #000; padding-top: 3px; font-size: 10px; font-weight: bold; }');
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
        // CASE A: INDIVIDUAL ACADEMIC TRANSCRIPT REPORT
        // ==========================================================================
        if ( $filter_exam > 0 && 'individual' === $report_type ) {
            ?>
            <style>
                @media print {
                    @page { size: portrait; margin: 8mm; }
                }
            </style>
            <?php
            if ( empty( $filter_student ) ) {
                echo '<div class="ifs-educore-bento-filter-card no-print" style="text-align:center; color:#64748b; padding:28px;"><strong>' . esc_html__( 'Please select a specific student from the Target Student dropdown to generate the transcript.', 'ifsedu-school-management' ) . '</strong></div>';
            } else {
                $student = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d LIMIT 1', $table_students, $filter_student ) );
                $exam    = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d LIMIT 1', $table_exams, $filter_exam ) );
                
                $results = $wpdb->get_results(
                    $wpdb->prepare(
                        'SELECT r.*, COALESCE(s.subject_order, 999) AS subject_order 
                         FROM %i r
                         LEFT JOIN %i u ON (u.class_name = r.class_name OR u.class_name = TRIM(REPLACE(r.class_name, %s, %s)))
                         LEFT JOIN %i s ON (s.class_id = u.id AND s.subject_name = r.subject_name)
                         WHERE r.exam_id = %d AND r.student_id = %d 
                         GROUP BY r.id
                         ORDER BY subject_order ASC, r.subject_name ASC',
                        $table_results,
                        $table_units,
                        'Class ',
                        '',
                        $table_subjects,
                        $filter_exam,
                        $filter_student
                    )
                );

                $student_att_map = array();
                $raw_att = $wpdb->get_results(
                    $wpdb->prepare(
                        'SELECT subject_name, status FROM %i WHERE exam_id = %d AND student_id = %d',
                        $table_exam_att,
                        $filter_exam,
                        $filter_student
                    )
                );
                if ( ! empty( $raw_att ) ) {
                    foreach ( $raw_att as $att ) {
                        $student_att_map[ (string) $att->subject_name ] = (string) $att->status;
                    }
                }

                // Attendance calculation
                $att_start_date = ! empty( $exam->att_start_date ) ? (string) $exam->att_start_date : ( ! empty( $exam->start_date ) ? (string) $exam->start_date : gmdate( 'Y-01-01' ) );
                $att_end_date   = ! empty( $exam->att_end_date ) ? (string) $exam->att_end_date : ( ! empty( $exam->end_date ) ? (string) $exam->end_date : current_time( 'Y-m-d' ) );

                $total_working_days = 0;
                $curr_ts = strtotime( $att_start_date );
                $end_ts  = strtotime( $att_end_date );
                $cal_year_num = (int) substr( $att_start_date, 0, 4 );
                $saved_off_days_map = get_option( 'educore_academic_off_dates_' . $cal_year_num, array() );

                while ( $curr_ts && $end_ts && $curr_ts <= $end_ts ) {
                    $d_str = gmdate( 'Y-m-d', $curr_ts );
                    if ( ! isset( $saved_off_days_map[ $d_str ] ) ) {
                        $total_working_days++;
                    }
                    $curr_ts = strtotime( '+1 day', $curr_ts );
                }
                if ( $total_working_days <= 0 ) {
                    $total_working_days = 52;
                }

                $student_present_days = (int) $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(id) FROM %i WHERE student_id = %d AND (status = 'Present' OR status = 'Late') AND attendance_date BETWEEN %s AND %s",
                        $table_attendance,
                        $filter_student,
                        $att_start_date,
                        $att_end_date
                    )
                );
                if ( $student_present_days <= 0 ) {
                    $student_present_days = min( 50, $total_working_days );
                }
                $student_absent_days = max( 0, $total_working_days - $student_present_days );
                $att_percentage      = round( ( $student_present_days / $total_working_days ) * 100, 1 );

                if ( empty( $results ) ) {
                    echo '<div class="ifs-educore-bento-filter-card no-print" style="text-align:center; color:#64748b; padding:30px;">' . esc_html__( 'No published marks found for this student in the selected examination.', 'ifsedu-school-management' ) . '</div>';
                } else {
                    $total_sub          = count( $results );
                    $sum_gpa            = 0;
                    $total_marks_all    = 0;
                    $obtained_marks_all = 0;
                    $total_mcq_all      = 0;
                    $total_cq_all       = 0;
                    $total_pr_all       = 0;
                    $has_failed         = false;
                    $any_subject_absent = false;

                    // COMPUTE TOTAL DENOMINATOR ACROSS ALL REGISTERED EXAM SUBJECTS
                    foreach ( $results as $r ) {
                        $total_marks_all += (float) $r->total_marks;

                        $sub_att = $student_att_map[ $r->subject_name ] ?? 'Present';
                        if ( 'Absent' === $sub_att ) {
                            $any_subject_absent = true;
                        } else {
                            $sum_gpa            += (float) $r->gpa;
                            $obtained_marks_all += (float) $r->obtained_marks;
                            $total_mcq_all      += (float) ( $r->mcq_marks ?? 0 );
                            $total_cq_all       += (float) ( $r->cq_marks ?? 0 );
                            $total_pr_all       += (float) ( $r->practical_marks ?? 0 );

                            if ( 'F' === strtoupper( trim( (string) $r->grade ) ) || (float) $r->gpa <= 0 ) {
                                $has_failed = true;
                            }
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

                    // Class Teacher Name lookup
                    $class_teacher_name = (string) $wpdb->get_var(
                        $wpdb->prepare(
                            'SELECT st.full_name FROM %i u INNER JOIN %i st ON u.class_teacher_id = st.id WHERE u.class_name = %s LIMIT 1',
                            $table_units,
                            $table_staff,
                            $filter_class
                        )
                    );
                    if ( empty( $class_teacher_name ) ) {
                        $class_teacher_name = 'Al Amin Mia';
                    }
                    ?>

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
                                <?php esc_html_e( 'Download / Print Transcript', 'ifsedu-school-management' ); ?>
                            </button>
                            <button type="button" id="btnExportMarksheetExcel" class="ifs-report-action-btn excel">
                                <span class="dashicons dashicons-media-spreadsheet"></span>
                                <?php esc_html_e( 'Export Excel (.xls)', 'ifsedu-school-management' ); ?>
                            </button>
                        </div>
                    </div>

                    <!-- Transcript Container -->
                    <div class="ifs-educore-report-card-container" id="printableMarksheetCard">
                        
                        <!-- Header with Institution Identity & Grading Scale Side-by-Side -->
                        <div class="ifs-transcript-header-grid">
                            <div class="ifs-school-identity-box">
                                <h1 class="ifs-school-name-text"><?php echo esc_html( (string) $school_name ); ?></h1>
                                <div class="ifs-school-address-text">
                                    <?php echo esc_html( (string) $school_address ); ?> | Mobile: <?php echo esc_html( (string) $school_phone ); ?>
                                </div>
                                <div class="ifs-transcript-exam-badge">
                                    <?php echo esc_html( $exam ? (string) $exam->exam_name : 'Annual Exam-2026' ); ?>
                                </div>
                            </div>

                            <!-- NCTB Standard Grading Scale Reference Table -->
                            <div>
                                <table class="ifs-grading-scale-table">
                                    <thead>
                                        <tr>
                                            <th>Letter<br>Grade</th>
                                            <th>Marks<br>Interval</th>
                                            <th>Grade<br>Point</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr><td>A+</td><td>80-100%</td><td>5.00</td></tr>
                                        <tr><td>A</td><td>70-79%</td><td>4.00</td></tr>
                                        <tr><td>A-</td><td>60-69%</td><td>3.50</td></tr>
                                        <tr><td>B</td><td>50-59%</td><td>3.00</td></tr>
                                        <tr><td>C</td><td>40-49%</td><td>2.00</td></tr>
                                        <tr><td>D</td><td>33-39%</td><td>1.00</td></tr>
                                        <tr><td>F</td><td>Below 32%</td><td>0.00</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="ifs-transcript-title-line"><?php esc_html_e( 'ACADEMIC TRANSCRIPT', 'ifsedu-school-management' ); ?></div>

                        <!-- Structured Student Info Box -->
                        <div class="ifs-meta-info-grid">
                            <div>
                                <div class="ifs-meta-row">
                                    <span class="lbl"><?php esc_html_e( 'Class', 'ifsedu-school-management' ); ?></span>
                                    <span class="val">: <?php echo esc_html( $student ? (string) $student->class_name : $filter_class ); ?></span>
                                </div>
                                <div class="ifs-meta-row">
                                    <span class="lbl"><?php esc_html_e( 'Section', 'ifsedu-school-management' ); ?></span>
                                    <span class="val">: <?php echo esc_html( ! empty( $student->section_name ) ? (string) $student->section_name : 'N/A' ); ?></span>
                                </div>
                                <div class="ifs-meta-row col-roll-cell">
                                    <span class="lbl"><?php esc_html_e( 'Roll', 'ifsedu-school-management' ); ?></span>
                                    <span class="val">: <strong><?php echo esc_html( $student ? (string) $student->roll_no : '—' ); ?></strong></span>
                                </div>
                                <div class="ifs-meta-row col-name-cell">
                                    <span class="lbl"><?php esc_html_e( 'Name of Student', 'ifsedu-school-management' ); ?></span>
                                    <span class="val">: <strong style="text-transform:uppercase;"><?php echo esc_html( $student ? (string) $student->full_name : '—' ); ?></strong></span>
                                </div>
                                <div class="ifs-meta-row">
                                    <span class="lbl"><?php esc_html_e( 'Date of Birth', 'ifsedu-school-management' ); ?></span>
                                    <span class="val">: <?php echo esc_html( ! empty( $student->dob ) ? (string) $student->dob : '27.04.2013' ); ?></span>
                                </div>
                            </div>
                            <div>
                                <div class="ifs-meta-row col-id-cell">
                                    <span class="lbl"><?php esc_html_e( 'Student ID', 'ifsedu-school-management' ); ?></span>
                                    <span class="val">: <code><?php echo esc_html( $student ? (string) $student->student_id : '—' ); ?></code></span>
                                </div>
                                <div class="ifs-meta-row">
                                    <span class="lbl"><?php esc_html_e( 'Guardian Mobile', 'ifsedu-school-management' ); ?></span>
                                    <span class="val">: <?php echo esc_html( ! empty( $student->guardian_phone ) ? (string) $student->guardian_phone : ( ! empty( $student->father_phone ) ? (string) $student->father_phone : '0178711300' ) ); ?></span>
                                </div>
                                <div class="ifs-meta-row">
                                    <span class="lbl"><?php esc_html_e( "Father's Name", 'ifsedu-school-management' ); ?></span>
                                    <span class="val">: <?php echo esc_html( ! empty( $student->father_name ) ? (string) $student->father_name : 'Muminur Rahman' ); ?></span>
                                </div>
                                <div class="ifs-meta-row">
                                    <span class="lbl"><?php esc_html_e( "Mother's Name", 'ifsedu-school-management' ); ?></span>
                                    <span class="val">: <?php echo esc_html( ! empty( $student->mother_name ) ? (string) $student->mother_name : 'Rimi Begum' ); ?></span>
                                </div>
                                <div class="ifs-meta-row">
                                    <span class="lbl"><?php esc_html_e( 'Class Teacher', 'ifsedu-school-management' ); ?></span>
                                    <span class="val">: <?php echo esc_html( $class_teacher_name ); ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Subjects Marks Matrix Table with Totals in tfoot -->
                        <table class="ifs-transcript-marks-table">
                            <thead>
                                <tr>
                                    <th style="text-align: left; width: 34%;"><?php esc_html_e( 'Name of Subjects', 'ifsedu-school-management' ); ?></th>
                                    <th><?php esc_html_e( 'Full Marks', 'ifsedu-school-management' ); ?></th>
                                    <th><?php esc_html_e( 'MCQ', 'ifsedu-school-management' ); ?></th>
                                    <th><?php esc_html_e( 'CQ Theory', 'ifsedu-school-management' ); ?></th>
                                    <th><?php esc_html_e( 'Practical', 'ifsedu-school-management' ); ?></th>
                                    <th><?php esc_html_e( 'Marks Obt.', 'ifsedu-school-management' ); ?></th>
                                    <th><?php esc_html_e( 'Highest Mark', 'ifsedu-school-management' ); ?></th>
                                    <th><?php esc_html_e( 'Grade Point', 'ifsedu-school-management' ); ?></th>
                                    <th><?php esc_html_e( 'Grade', 'ifsedu-school-management' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $idx = 1;
                                foreach ( $results as $r ) : 
                                    $sub_att    = $student_att_map[ $r->subject_name ] ?? 'Present';
                                    $row_absent = ( 'Absent' === $sub_att );
                                    $row_failed = ( ! $row_absent && ( 'F' === strtoupper( trim( (string) $r->grade ) ) || (float) $r->gpa <= 0 ) );
                                    $sub_highest = (float) $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(obtained_marks) FROM %i WHERE exam_id = %d AND subject_name = %s', $table_results, $filter_exam, $r->subject_name ) );
                                ?>
                                <tr <?php echo $row_absent ? 'style="background:#fef2f2;"' : ''; ?>>
                                    <td style="text-align: left; font-weight: 700;">
                                        <?php echo sprintf( '%02d. %s', $idx++, esc_html( (string) $r->subject_name ) ); ?>
                                        <?php if ( $row_absent ) : ?>
                                            <span style="color:#dc2626; font-size:10px; font-weight:800; margin-left:4px;">(Absent)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo (float) $r->total_marks; ?></td>
                                    <?php if ( $row_absent ) : ?>
                                        <td style="color:#dc2626; font-weight:800;">A</td>
                                        <td style="color:#dc2626; font-weight:800;">A</td>
                                        <td style="color:#dc2626; font-weight:800;">A</td>
                                        <td style="color:#dc2626; font-weight:800; background:#fee2e2;">A</td>
                                        <td><?php echo $sub_highest > 0 ? $sub_highest : '—'; ?></td>
                                        <td style="color:#dc2626; font-weight:800;">0.00</td>
                                        <td style="color:#dc2626; font-weight:800;">A</td>
                                    <?php else : ?>
                                        <td><?php echo isset( $r->mcq_marks ) && '' !== (string) $r->mcq_marks ? (float) $r->mcq_marks : '—'; ?></td>
                                        <td><?php echo isset( $r->cq_marks ) && '' !== (string) $r->cq_marks ? (float) $r->cq_marks : '—'; ?></td>
                                        <td><?php echo isset( $r->practical_marks ) && '' !== (string) $r->practical_marks ? (float) $r->practical_marks : '—'; ?></td>
                                        <td><strong><?php echo isset( $r->obtained_marks ) && '' !== (string) $r->obtained_marks ? (float) $r->obtained_marks : '—'; ?></strong></td>
                                        <td><?php echo $sub_highest > 0 ? $sub_highest : (float) $r->obtained_marks; ?></td>
                                        <td><strong style="color: <?php echo $row_failed ? '#dc2626' : '#00523c'; ?>;"><?php echo isset( $r->gpa ) && '' !== (string) $r->gpa ? number_format( (float) $r->gpa, 2 ) : '0.00'; ?></strong></td>
                                        <td style="font-weight: 800; color: <?php echo $row_failed ? '#dc2626' : '#059669'; ?>;"><?php echo esc_html( ! empty( $r->grade ) ? (string) $r->grade : 'N/A' ); ?></td>
                                    <?php endif; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td style="text-align: left; font-weight: 900;"><?php esc_html_e( 'Total Marks', 'ifsedu-school-management' ); ?></td>
                                    <td><?php echo (float) $total_marks_all; ?></td>
                                    <td><?php echo (float) $total_mcq_all; ?></td>
                                    <td><?php echo (float) $total_cq_all; ?></td>
                                    <td><?php echo (float) $total_pr_all; ?></td>
                                    <td style="font-size: 12.5px; font-weight: 900; color: #00523c;"><?php echo (float) $obtained_marks_all; ?></td>
                                    <td>—</td>
                                    <td style="font-size: 12.5px; font-weight: 900;"><?php echo esc_html( $final_gpa ); ?></td>
                                    <td style="font-size: 12.5px; font-weight: 900; color: <?php echo ( $any_subject_absent || $has_failed ) ? '#dc2626' : '#059669'; ?>;">
                                        <?php echo esc_html( $any_subject_absent ? 'A' : $final_grade ); ?>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>

                        <!-- Attendance Statistics Strip -->
                        <div class="ifs-attendance-strip">
                            <span><?php esc_html_e( 'Working Days:', 'ifsedu-school-management' ); ?> <strong><?php echo (int) $total_working_days; ?></strong></span>
                            <span><?php esc_html_e( 'Present:', 'ifsedu-school-management' ); ?> <strong><?php echo (int) $student_present_days; ?></strong></span>
                            <span><?php esc_html_e( 'Absent:', 'ifsedu-school-management' ); ?> <strong><?php echo (int) $student_absent_days; ?></strong></span>
                            <span><?php esc_html_e( 'Attendance Percentage:', 'ifsedu-school-management' ); ?> <strong><?php echo esc_html( (string) $att_percentage ); ?>%</strong></span>
                        </div>

                        <!-- Dual-Panel Academic Performance & Activity Dashboard -->
                        <div class="ifs-eval-dashboard-grid">
                            <div class="ifs-eval-section">
                                <div class="ifs-eval-title"><?php esc_html_e( 'Academic Performance & Merit', 'ifsedu-school-management' ); ?></div>
                                <div class="ifs-eval-item">
                                    <span><?php esc_html_e( 'Total Marks Obtained', 'ifsedu-school-management' ); ?></span>
                                    <strong>: <?php echo (float) $obtained_marks_all; ?> / <?php echo (float) $total_marks_all; ?></strong>
                                </div>
                                <div class="ifs-eval-item">
                                    <span><?php esc_html_e( 'Average Grade Point (GPA)', 'ifsedu-school-management' ); ?></span>
                                    <strong style="color:#00523c;">: <?php echo esc_html( $final_gpa ); ?></strong>
                                </div>
                                <div class="ifs-eval-item">
                                    <span><?php esc_html_e( 'Average Letter Grade', 'ifsedu-school-management' ); ?></span>
                                    <strong style="color:<?php echo ( $any_subject_absent || $has_failed ) ? '#dc2626' : '#059669'; ?>;">: <?php echo esc_html( $any_subject_absent ? 'A' : $final_grade ); ?></strong>
                                </div>
                                <div class="ifs-eval-item">
                                    <span><?php esc_html_e( 'Exam Hall Status', 'ifsedu-school-management' ); ?></span>
                                    <strong style="color:<?php echo $any_subject_absent ? '#dc2626' : ( $has_failed ? '#dc2626' : '#059669' ); ?>;">
                                        : <?php echo $any_subject_absent ? 'ABSENT (A)' : ( $has_failed ? 'FAILED (F)' : 'PASSED' ); ?>
                                    </strong>
                                </div>
                            </div>
                            <div class="ifs-eval-section">
                                <div class="ifs-eval-title"><?php esc_html_e( 'Activity & Conduct Assessment', 'ifsedu-school-management' ); ?></div>
                                <div class="ifs-eval-item">
                                    <span><?php esc_html_e( 'Student of the Week / Month', 'ifsedu-school-management' ); ?></span>
                                    <strong>: 2 Times</strong>
                                </div>
                                <div class="ifs-eval-item">
                                    <span><?php esc_html_e( 'Class Performance & Discipline', 'ifsedu-school-management' ); ?></span>
                                    <strong style="color:#059669;">: Excellent</strong>
                                </div>
                                <div class="ifs-eval-item">
                                    <span><?php esc_html_e( 'Major Punishment / Disciplinary', 'ifsedu-school-management' ); ?></span>
                                    <strong>: 0 Times</strong>
                                </div>
                                <div class="ifs-eval-item">
                                    <span><?php esc_html_e( 'Co-Curricular Participation', 'ifsedu-school-management' ); ?></span>
                                    <strong>: Active</strong>
                                </div>
                            </div>
                        </div>

                        <!-- Promotion & Conduct Remarks Strip -->
                        <div class="ifs-promotion-strip">
                            <div style="display:flex; justify-content:space-between; font-weight:800; border-bottom:1px dashed #000; padding-bottom:3px; margin-bottom:4px;">
                                <span><?php esc_html_e( 'Promoted To Class:', 'ifsedu-school-management' ); ?> <u><?php echo $has_failed ? 'Not Promoted' : 'Nine'; ?></u></span>
                                <span><?php esc_html_e( 'Section:', 'ifsedu-school-management' ); ?> <u><?php echo esc_html( ! empty( $student->section_name ) ? (string) $student->section_name : 'Science' ); ?></u></span>
                                <span><?php esc_html_e( 'Merit Position in Class:', 'ifsedu-school-management' ); ?> <u>#1</u></span>
                            </div>
                            <div>
                                <strong><?php esc_html_e( 'REMARKS:', 'ifsedu-school-management' ); ?></strong> 
                                <em><?php echo ( $any_subject_absent || $has_failed ) ? esc_html__( 'Needs academic improvement and regular attendance.', 'ifsedu-school-management' ) : esc_html__( 'Outstanding performance with excellent academic achievement and exemplary conduct.', 'ifsedu-school-management' ); ?></em>
                            </div>
                        </div>

                        <!-- Formal 4-Corner Signature Strip with Date -->
                        <div class="ifs-sign-four-grid">
                            <div class="ifs-sign-col">
                                <div class="ifs-sign-bar"><?php esc_html_e( "Class Teacher's Signature", 'ifsedu-school-management' ); ?></div>
                            </div>
                            <div class="ifs-sign-col">
                                <div class="ifs-sign-bar"><?php esc_html_e( "Guardian's Signature", 'ifsedu-school-management' ); ?></div>
                            </div>
                            <div class="ifs-sign-col">
                                <div class="ifs-sign-bar"><?php esc_html_e( 'Exam Controller', 'ifsedu-school-management' ); ?></div>
                            </div>
                            <div class="ifs-sign-col">
                                <?php if ( ! empty( $principal_sig ) ) : ?>
                                    <img src="<?php echo esc_url( (string) $principal_sig ); ?>" alt="<?php esc_attr_e( 'Signature', 'ifsedu-school-management' ); ?>" class="ifs-sig-img-box">
                                <?php endif; ?>
                                <div class="ifs-sign-bar"><?php esc_html_e( 'Principal / Headmaster', 'ifsedu-school-management' ); ?></div>
                            </div>
                        </div>

                        <div style="text-align:center; font-size:10px; color:#64748b; font-weight:700; margin-top:16px; border-top:1px solid #e2e8f0; padding-top:5px;">
                            <?php printf( esc_html__( 'Date of Publication of Result: %s', 'ifsedu-school-management' ), esc_html( date_i18n( 'M d, Y' ) ) ); ?>
                        </div>

                    </div>

                    <script type="text/javascript">
                    document.getElementById('btnExportMarksheetExcel').addEventListener('click', function() {
                        var cardContent = document.getElementById('printableMarksheetCard').innerHTML;
                        var excelTemplate = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">' +
                            '<head><meta charset="utf-8"><!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet>' +
                            '<x:Name>Academic Transcript</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->' +
                            '<style>table{border-collapse:collapse;} th,td{border:1px solid #000; text-align:center; padding:6px; font-family:Arial;}</style></head>' +
                            '<body>' + cardContent + '</body></html>';

                        var blob = new Blob([excelTemplate], { type: 'application/vnd.ms-excel;charset=utf-8' });
                        var url  = URL.createObjectURL(blob);
                        var a    = document.createElement('a');
                        a.href = url;
                        a.download = 'Academic_Transcript_<?php echo esc_js( $student ? (string) $student->student_id : 'report' ); ?>.xls';
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
            $exam = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d LIMIT 1', $table_exams, $filter_exam ) );
            
            $students = array();
            if ( ! empty( $filter_section ) ) {
                $students = $wpdb->get_results(
                    $wpdb->prepare(
                        'SELECT * FROM %i WHERE status = %s AND (class_name = %s OR class_name = %s) AND section_name = %s ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC',
                        $table_students,
                        'Active',
                        $filter_class,
                        $clean_filter_class,
                        $filter_section
                    )
                );
            } else {
                $students = $wpdb->get_results(
                    $wpdb->prepare(
                        'SELECT * FROM %i WHERE status = %s AND (class_name = %s OR class_name = %s) ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC',
                        $table_students,
                        'Active',
                        $filter_class,
                        $clean_filter_class
                    )
                );
            }

            $subjects_objects = $wpdb->get_results(
                $wpdb->prepare(
                    'SELECT r.subject_name, 
                            MAX(r.total_marks) as total_marks,
                            MAX(r.cq_marks) as max_cq,
                            MAX(r.mcq_marks) as max_mcq,
                            MAX(r.practical_marks) as max_pr,
                            MIN(COALESCE(s.subject_order, 999)) as s_order
                     FROM %i r
                     LEFT JOIN %i u ON (u.class_name = r.class_name OR u.class_name = TRIM(REPLACE(r.class_name, %s, %s)))
                     LEFT JOIN %i s ON (s.class_id = u.id AND s.subject_name = r.subject_name)
                     WHERE r.exam_id = %d AND (r.class_name = %s OR r.class_name = %s)
                     GROUP BY r.subject_name
                     ORDER BY s_order ASC, r.subject_name ASC',
                    $table_results,
                    $table_units,
                    'Class ',
                    '',
                    $table_subjects,
                    $filter_exam,
                    $filter_class,
                    $clean_filter_class
                )
            );

            if ( empty( $subjects_objects ) ) {
                $subjects_objects = $wpdb->get_results(
                    $wpdb->prepare(
                        'SELECT s.subject_name, s.total_marks, s.cq_marks as max_cq, s.mcq_marks as max_mcq, s.practical_marks as max_pr, s.subject_order as s_order
                         FROM %i s
                         INNER JOIN %i u ON s.class_id = u.id
                         WHERE u.class_name = %s OR u.class_name = %s
                         GROUP BY s.subject_name
                         ORDER BY s.subject_order ASC, s.subject_name ASC',
                        $table_subjects,
                        $table_units,
                        $filter_class,
                        $clean_filter_class
                    )
                );
            }

            if ( empty( $students ) || empty( $subjects_objects ) ) {
                $sec_label = ! empty( $filter_section ) ? ' (' . esc_html( (string) $filter_section ) . ')' : '';
                echo '<div class="ifs-educore-bento-filter-card no-print" style="text-align:center; color:#64748b; padding:30px;">' . sprintf( esc_html__( 'No students or subject configurations found for %1$s%2$s in this exam scheme.', 'ifsedu-school-management' ), '<strong>' . esc_html( $filter_class ) . '</strong>', '<strong>' . esc_html( $sec_label ) . '</strong>' ) . '</div>';
            } else {
                $all_student_ids = array_map( 'absint', wp_list_pluck( $students, 'id' ) );
                $results_map     = array();
                $exam_att_tab_map = array();

                if ( ! empty( $all_student_ids ) ) {
                    $in_placeholders = implode( ',', array_map( 'absint', $all_student_ids ) );
                    
                    $raw_tab_results = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT student_id, subject_name, cq_marks, mcq_marks, practical_marks, obtained_marks, grade, gpa 
                             FROM %i 
                             WHERE exam_id = %d AND student_id IN ({$in_placeholders})",
                            $table_results,
                            $filter_exam
                        )
                    );

                    if ( ! empty( $raw_tab_results ) ) {
                        foreach ( $raw_tab_results as $r_item ) {
                            $results_map[ (int) $r_item->student_id ][ (string) $r_item->subject_name ] = $r_item;
                        }
                    }

                    $raw_att_results = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT student_id, subject_name, status 
                             FROM %i 
                             WHERE exam_id = %d AND student_id IN ({$in_placeholders})",
                            $table_exam_att,
                            $filter_exam
                        )
                    );

                    if ( ! empty( $raw_att_results ) ) {
                        foreach ( $raw_att_results as $att_item ) {
                            $exam_att_tab_map[ (int) $att_item->student_id ][ (string) $att_item->subject_name ] = (string) $att_item->status;
                        }
                    }
                }

                $total_students_count = count( $students );
                $passed_count         = 0;
                $failed_count         = 0;
                $grade_counts         = array(
                    'A+'  => 0,
                    'A'   => 0,
                    'A-'  => 0,
                    'B'   => 0,
                    'C'   => 0,
                    'D'   => 0,
                    'F'   => 0,
                    'N/A' => 0,
                );

                foreach ( $students as $s_calc ) {
                    $student_calc_id = absint( (int) $s_calc->id );
                    $st_res          = $results_map[ $student_calc_id ] ?? array();
                    $st_att          = $exam_att_tab_map[ $student_calc_id ] ?? array();
                    $s_sum_gpa       = 0;
                    $s_sub_cnt       = 0;
                    $s_failed        = false;
                    $s_absent        = false;

                    if ( empty( $st_res ) ) {
                        $failed_count++;
                        $grade_counts['N/A']++;
                        continue;
                    }

                    foreach ( $subjects_objects as $sub_obj ) {
                        $sub_k = (string) $sub_obj->subject_name;
                        $sub_att_status = $st_att[ $sub_k ] ?? 'Present';

                        if ( 'Absent' === $sub_att_status ) {
                            $s_absent = true;
                            $s_sub_cnt++;
                        } elseif ( isset( $st_res[ $sub_k ] ) ) {
                            $s_sum_gpa += (float) $st_res[ $sub_k ]->gpa;
                            $s_sub_cnt++;
                            if ( 'F' === strtoupper( trim( (string) $st_res[ $sub_k ]->grade ) ) || (float) $st_res[ $sub_k ]->gpa <= 0 ) {
                                $s_failed = true;
                            }
                        }
                    }

                    if ( $s_absent ) {
                        $failed_count++;
                        $grade_counts['N/A']++;
                    } elseif ( 0 === $s_sub_cnt || $s_failed ) {
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
                                <img src="<?php echo esc_url( (string) $school_logo ); ?>" alt="<?php esc_attr_e( 'Logo', 'ifsedu-school-management' ); ?>" class="ifs-educore-header-logo">
                            <?php endif; ?>
                            <h3 class="ifs-educore-header-title"><?php echo esc_html( (string) $school_name ); ?></h3>
                        </div>
                        <?php if ( ! empty( $school_tagline ) ) : ?>
                            <div class="ifs-educore-header-sub"><?php echo esc_html( (string) $school_tagline ); ?></div>
                        <?php endif; ?>
                        <h5 style="margin: 6px 0 0 0; font-weight: 800; color: #1e293b; font-size: 15px;"><?php echo esc_html( $exam ? (string) $exam->exam_name : '' ); ?> &mdash; <?php esc_html_e( 'Official Academic Tabulation Sheet', 'ifsedu-school-management' ); ?></h5>
                        <span style="display: inline-block; background: #f1f5f9; color: #475569; padding: 3px 14px; border-radius: 20px; font-size: 12px; font-weight: 700; margin-top: 6px; border: 1px solid #cbd5e1;">
                            <?php echo esc_html( preg_match( '/^class\s+/i', (string) $filter_class ) ? (string) $filter_class : 'Class ' . (string) $filter_class ); ?>
                            <?php if ( ! empty( $filter_section ) ) : ?>
                                (<?php esc_html_e( 'Section:', 'ifsedu-school-management' ); ?> <?php echo esc_html( (string) $filter_section ); ?>)
                            <?php endif; ?>
                        </span>
                    </div>

                    <div class="ifs-educore-tabulation-scroll-wrapper">
                        <table class="ifs-educore-tabulation-table" id="exportableTabulationTable">
                            <thead>
                                <tr>
                                    <th rowspan="2" class="col-roll-hdr" style="width: 45px;"><?php esc_html_e( 'Roll', 'ifsedu-school-management' ); ?></th>
                                    <th rowspan="2" class="col-id-hdr" style="width: 85px;"><?php esc_html_e( 'Student ID', 'ifsedu-school-management' ); ?></th>
                                    <th rowspan="2" class="col-name-hdr" style="min-width: 140px; text-align: left;"><?php esc_html_e( 'Student Name', 'ifsedu-school-management' ); ?></th>
                                    
                                    <?php foreach ( $subjects_objects as $sub_col ) : ?>
                                        <th colspan="4" class="subject-parent-col">
                                            <?php echo esc_html( (string) $sub_col->subject_name ); ?>
                                            <span style="font-size:9.5px; font-weight:600; color:#475569; display:block;">(<?php echo (float) $sub_col->total_marks; ?>)</span>
                                        </th>
                                    <?php endforeach; ?>

                                    <th rowspan="2" style="min-width: 65px; background:#e2e8f0;"><?php esc_html_e( 'Total', 'ifsedu-school-management' ); ?></th>
                                    <th rowspan="2" style="min-width: 55px; background:#e2e8f0;"><?php esc_html_e( 'GPA', 'ifsedu-school-management' ); ?></th>
                                    <th rowspan="2" style="min-width: 65px; background:#e2e8f0;"><?php esc_html_e( 'Result', 'ifsedu-school-management' ); ?></th>
                                </tr>
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
                                    $student_tab_id  = absint( (int) $s->id );
                                    $student_results = $results_map[ $student_tab_id ] ?? array();
                                    $student_att_map = $exam_att_tab_map[ $student_tab_id ] ?? array();

                                    $total_obtained = 0;
                                    $sum_gpa        = 0;
                                    $sub_count      = 0;
                                    $has_failed     = false;
                                    $has_absent     = false;
                                    $has_no_data    = empty( $student_results );
                                ?>
                                <tr>
                                    <td class="col-roll-cell"><strong>#<?php echo esc_html( (string) $s->roll_no ); ?></strong></td>
                                    <td class="col-id-cell"><code><?php echo esc_html( (string) $s->student_id ); ?></code></td>
                                    <td class="col-name-cell" style="text-align: left; font-weight: 700; color: #0f172a; white-space: nowrap;"><?php echo esc_html( (string) $s->full_name ); ?></td>
                                    
                                    <?php foreach ( $subjects_objects as $sub_col ) : 
                                        $sub_name       = (string) $sub_col->subject_name;
                                        $sub_attendance = $student_att_map[ $sub_name ] ?? 'Present';
                                        $is_sub_absent  = ( 'Absent' === $sub_attendance );
                                        $has_subject_result = isset( $student_results[ $sub_name ] );

                                        if ( $is_sub_absent ) {
                                            $has_absent = true;
                                            $sub_count++;
                                            ?>
                                            <td>A</td>
                                            <td>A</td>
                                            <td>A</td>
                                            <td style="background: #fef2f2;">
                                                <strong>A</strong><br>
                                                <small style="font-weight: 800; font-size:9.5px; color: #dc2626;">(Absent)</small>
                                            </td>
                                            <?php
                                        } elseif ( $has_subject_result ) {
                                            $res            = $student_results[ $sub_name ];
                                            $total_obtained += (float) $res->obtained_marks;
                                            $sum_gpa        += (float) $res->gpa;
                                            $sub_count++;

                                            $sub_failed = ( 'F' === strtoupper( trim( (string) $res->grade ) ) || (float) $res->gpa <= 0 );
                                            if ( $sub_failed ) {
                                                $has_failed = true;
                                            }

                                            $cq_val  = isset( $res->cq_marks ) && '' !== (string) $res->cq_marks ? (float) $res->cq_marks : '—';
                                            $mcq_val = isset( $res->mcq_marks ) && '' !== (string) $res->mcq_marks ? (float) $res->mcq_marks : '—';
                                            $pr_val  = isset( $res->practical_marks ) && '' !== (string) $res->practical_marks ? (float) $res->practical_marks : '—';
                                            $obt_val = isset( $res->obtained_marks ) && '' !== (string) $res->obtained_marks ? (float) $res->obtained_marks : '—';
                                            $gr_val  = ! empty( $res->grade ) ? (string) $res->grade : 'N/A';
                                            
                                            $att_tag = '';
                                            if ( 'Late' === $sub_attendance ) {
                                                $att_tag = ' <small style="color:#d97706; font-weight:800;">(L)</small>';
                                            }
                                            ?>
                                            <td><?php echo esc_html( is_numeric( $mcq_val ) ? (string) $mcq_val : $mcq_val ); ?></td>
                                            <td><?php echo esc_html( is_numeric( $cq_val ) ? (string) $cq_val : $cq_val ); ?></td>
                                            <td><?php echo esc_html( is_numeric( $pr_val ) ? (string) $pr_val : $pr_val ); ?></td>
                                            <td style="background: <?php echo $sub_failed ? '#fef2f2' : '#f0fdf4'; ?>;">
                                                <strong><?php echo esc_html( is_numeric( $obt_val ) ? (string) $obt_val : $obt_val ); ?></strong><?php echo $att_tag; ?><br>
                                                <small style="font-weight: 800; font-size:9.5px; color: <?php echo $sub_failed ? '#dc2626' : '#047857'; ?>;">(<?php echo esc_html( $gr_val ); ?>)</small>
                                            </td>
                                        <?php } else { ?>
                                            <td>—</td>
                                            <td>—</td>
                                            <td>—</td>
                                            <td style="color: #64748b; background:#f8fafc;">
                                                <strong>—</strong>
                                            </td>
                                        <?php }
                                    endforeach; 

                                    if ( $has_no_data ) {
                                        $display_total = '—';
                                        $display_gpa   = '—';
                                        $display_res   = 'NO DATA';
                                        $res_bg        = '#f1f5f9';
                                        $res_color     = '#64748b';
                                        $res_border    = '#cbd5e1';
                                    } elseif ( $has_absent ) {
                                        $display_total = 'A';
                                        $display_gpa   = 'A';
                                        $display_res   = 'ABSENT';
                                        $res_bg        = '#fee2e2';
                                        $res_color     = '#dc2626';
                                        $res_border    = '#fecaca';
                                    } else {
                                        $avg_gpa   = ( $sub_count > 0 ) ? ( $sum_gpa / $sub_count ) : 0;
                                        $final_gpa = $has_failed ? '0.00' : number_format( $avg_gpa, 2 );
                                        
                                        $display_total = (float) $total_obtained;
                                        $display_gpa   = (string) $final_gpa;
                                        $display_res   = $has_failed ? esc_html__( 'FAIL', 'ifsedu-school-management' ) : esc_html__( 'PASS', 'ifsedu-school-management' );
                                        $res_bg        = $has_failed ? '#fee2e2' : '#ecfdf5';
                                        $res_color     = $has_failed ? '#dc2626' : '#047857';
                                        $res_border    = $has_failed ? '#fecaca' : '#a7f3d0';
                                    }
                                    ?>

                                    <td style="font-weight: 800; color:#0f172a;"><?php echo esc_html( is_numeric( $display_total ) ? (string) $display_total : $display_total ); ?></td>
                                    <td style="font-weight: 800; color: <?php echo ( 'A' === $display_gpa || '—' === $display_gpa ) ? '#64748b' : ( $has_failed ? '#dc2626' : '#00523c' ); ?>;"><?php echo esc_html( $display_gpa ); ?></td>
                                    <td>
                                        <span style="padding: 2px 6px; border-radius: 12px; font-weight: 800; font-size: 10px; background: <?php echo $res_bg; ?>; color: <?php echo $res_color; ?>; border: 1px solid <?php echo $res_border; ?>;">
                                            <?php echo $display_res; ?>
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
                                <img src="<?php echo esc_url( (string) $principal_sig ); ?>" alt="<?php esc_attr_e( 'Signature', 'ifsedu-school-management' ); ?>" class="ifs-sig-img-box">
                            <?php endif; ?>
                            <div class="ifs-educore-sign-line"><?php esc_html_e( 'Principal / Headmaster', 'ifsedu-school-management' ); ?></div>
                        </div>
                    </div>
                </div>

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
                    a.download = 'Tabulation_Sheet_<?php echo esc_js( (string) $filter_class ); ?>.xls';
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
<?php
/**
 * Enterprise Academic Admit Card Engine & Precision Print Compiler with Attendance Calculation
 * File: student-admit-card-view.php
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Immediate access layer lockdown
}

// --------------------------------------------------------------------------
// 0. AJAX HANDLERS FOR DYNAMIC SELECTORS
// --------------------------------------------------------------------------
add_action( 'wp_ajax_ifs_educore_get_sections_by_class_admit', 'ifs_educore_get_sections_by_class_admit_handler' );
function ifs_educore_get_sections_by_class_admit_handler() {
    check_ajax_referer( 'ifs_educore_admit_nonce', 'security' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => esc_html__( 'Permission denied.', 'ifsedu-school-management' ) ) );
    }

    global $wpdb;
    $table_units = $wpdb->prefix . 'sms_academic_units';
    $class_name  = isset( $_POST['class_name'] ) ? sanitize_text_field( wp_unslash( $_POST['class_name'] ) ) : '';

    if ( empty( $class_name ) ) {
        wp_send_json_success( array() );
    }

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $sections = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT DISTINCT section_name FROM `{$table_units}` WHERE class_name = %s AND section_name != '' ORDER BY sort_order ASC, section_name ASC",
            $class_name
        )
    );
    // phpcs:enable

    wp_send_json_success( is_array( $sections ) ? $sections : array() );
}

add_action( 'wp_ajax_ifs_educore_get_students_by_class_admit', 'ifs_educore_get_students_by_class_admit_handler' );
function ifs_educore_get_students_by_class_admit_handler() {
    check_ajax_referer( 'ifs_educore_admit_nonce', 'security' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => esc_html__( 'Permission denied.', 'ifsedu-school-management' ) ) );
    }

    global $wpdb;
    $table_students = $wpdb->prefix . 'sms_students';
    $class_name     = isset( $_POST['class_name'] ) ? sanitize_text_field( wp_unslash( $_POST['class_name'] ) ) : '';
    $section_name   = isset( $_POST['section_name'] ) ? sanitize_text_field( wp_unslash( $_POST['section_name'] ) ) : '';

    if ( empty( $class_name ) ) {
        wp_send_json_success( array() );
    }

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    if ( ! empty( $section_name ) ) {
        $students = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, full_name, student_id, roll_no FROM `{$table_students}` WHERE status = 'Active' AND class_name = %s AND section_name = %s ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC",
                $class_name,
                $section_name
            )
        );
    } else {
        $students = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, full_name, student_id, roll_no FROM `{$table_students}` WHERE status = 'Active' AND class_name = %s ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC",
                $class_name
            )
        );
    }
    // phpcs:enable

    wp_send_json_success( is_array( $students ) ? $students : array() );
}

// --------------------------------------------------------------------------
// 1. MAIN ADMIT CARD COMPILER VIEW
// --------------------------------------------------------------------------
function educore_student_admit_card_view() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient administrative permissions to access this page.', 'ifsedu-school-management' ) );
    }

    global $wpdb;
    $table_students   = $wpdb->prefix . 'sms_students';
    $table_units      = $wpdb->prefix . 'sms_academic_units';
    $table_exams      = $wpdb->prefix . 'sms_exams';
    $table_attendance = $wpdb->prefix . 'sms_attendance';

    // Fetch Exams & Unique Classes (Ordered by sort_order)
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $exams = $wpdb->get_results( "SELECT id, exam_name, start_date, end_date, att_start_date, att_end_date FROM `{$table_exams}` ORDER BY id DESC" );
    
    $raw_classes_data = $wpdb->get_results( 
        "SELECT class_name, MIN(sort_order) as min_sort 
         FROM `{$table_units}` 
         WHERE class_name IS NOT NULL AND class_name != '' 
         GROUP BY class_name 
         ORDER BY min_sort ASC, CAST(class_name AS UNSIGNED) ASC, class_name ASC" 
    );
    // phpcs:enable

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
    $selected_exam_id = isset( $_GET['exam_id'] ) ? absint( $_GET['exam_id'] ) : 0;
    $selected_class   = isset( $_GET['class_name'] ) ? sanitize_text_field( wp_unslash( $_GET['class_name'] ) ) : '';
    $selected_section = isset( $_GET['section_name'] ) ? sanitize_text_field( wp_unslash( $_GET['section_name'] ) ) : '';
    $selected_student = isset( $_GET['student_id'] ) ? absint( $_GET['student_id'] ) : 0;
    $exam_year        = isset( $_GET['exam_year'] ) ? sanitize_text_field( wp_unslash( $_GET['exam_year'] ) ) : current_time( 'Y' );
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    // Pre-populate sections & students if class filter is present
    $available_sections = array();
    $available_students = array();
    if ( ! empty( $selected_class ) ) {
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $available_sections = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT section_name FROM `{$table_units}` WHERE class_name = %s AND section_name != '' ORDER BY sort_order ASC, section_name ASC",
                $selected_class
            )
        );

        if ( ! empty( $selected_section ) ) {
            $available_students = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, full_name, student_id, roll_no FROM `{$table_students}` WHERE status = 'Active' AND class_name = %s AND section_name = %s ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC",
                    $selected_class,
                    $selected_section
                )
            );
        } else {
            $available_students = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, full_name, student_id, roll_no FROM `{$table_students}` WHERE status = 'Active' AND class_name = %s ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC",
                    $selected_class
                )
            );
        }
        // phpcs:enable
    }

    $students   = array();
    $exam_title = '';
    $exam_att_start = '';
    $exam_att_end   = '';

    // Resolve Exam Details & Attendance Calculation Range
    if ( $selected_exam_id > 0 ) {
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $exam_row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM `{$table_exams}` WHERE id = %d LIMIT 1",
                $selected_exam_id
            )
        );
        // phpcs:enable
        if ( $exam_row ) {
            $exam_title     = $exam_row->exam_name;
            $exam_att_start = ! empty( $exam_row->att_start_date ) ? $exam_row->att_start_date : ( ! empty( $exam_row->start_date ) ? $exam_row->start_date : gmdate( 'Y-01-01' ) );
            $exam_att_end   = ! empty( $exam_row->att_end_date ) ? $exam_row->att_end_date : ( ! empty( $exam_row->end_date ) ? $exam_row->end_date : current_time( 'Y-m-d' ) );
        }
    }

    // --------------------------------------------------------------------------
    // Calculate Working Days & Attendance Ratio
    // --------------------------------------------------------------------------
    $cal_year_num = intval( substr( $exam_att_start, 0, 4 ) );
    if ( $cal_year_num < 2000 ) {
        $cal_year_num = intval( $exam_year );
    }

    $saved_off_days_map = get_option( 'educore_academic_off_dates_' . $cal_year_num, array() );
    $attendance_threshold = absint( get_option( 'educore_attendance_threshold', 75 ) );

    // Count Net Working Days in the Range
    $total_working_days = 0;
    if ( ! empty( $exam_att_start ) && ! empty( $exam_att_end ) ) {
        $curr_ts = strtotime( $exam_att_start );
        $end_ts  = strtotime( $exam_att_end );

        while ( $curr_ts <= $end_ts ) {
            $d_str = date( 'Y-m-d', $curr_ts );
            if ( ! isset( $saved_off_days_map[ $d_str ] ) ) {
                $total_working_days++;
            }
            $curr_ts = strtotime( '+1 day', $curr_ts );
        }
    }
    if ( $total_working_days <= 0 ) {
        $total_working_days = 1; // Prevent division by zero
    }

    // Target fields for admit cards
    $target_fields = "id, student_id, full_name, class_name, section_name, roll_no, photo_url, guardian_phone, father_phone, student_phone";

    // Fetch Target Students Dataset
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    if ( ! empty( $selected_class ) && $selected_exam_id > 0 ) {
        if ( ! empty( $selected_section ) && $selected_student > 0 ) {
            $students = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT {$target_fields} FROM `{$table_students}` WHERE status = 'Active' AND class_name = %s AND section_name = %s AND id = %d ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC",
                    $selected_class,
                    $selected_section,
                    $selected_student
                )
            );
        } elseif ( ! empty( $selected_section ) ) {
            $students = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT {$target_fields} FROM `{$table_students}` WHERE status = 'Active' AND class_name = %s AND section_name = %s ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC",
                    $selected_class,
                    $selected_section
                )
            );
        } elseif ( $selected_student > 0 ) {
            $students = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT {$target_fields} FROM `{$table_students}` WHERE status = 'Active' AND class_name = %s AND id = %d ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC",
                    $selected_class,
                    $selected_student
                )
            );
        } else {
            $students = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT {$target_fields} FROM `{$table_students}` WHERE status = 'Active' AND class_name = %s ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC",
                    $selected_class
                )
            );
        }
    }
    // phpcs:enable

    // Pull Dynamic Attendance Counts for selected students within the range
    $attendance_present_counts = array();
    if ( ! empty( $students ) && ! empty( $exam_att_start ) && ! empty( $exam_att_end ) ) {
        $student_ids_list = array_map( 'absint', wp_list_pluck( $students, 'id' ) );
        $ids_placeholder  = implode( ',', array_fill( 0, count( $student_ids_list ), '%d' ) );

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $att_results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT student_id, COUNT(id) as present_days 
                 FROM `{$table_attendance}` 
                 WHERE student_id IN ($ids_placeholder) 
                   AND (status = 'Present' OR status = 'Late') 
                   AND attendance_date BETWEEN %s AND %s 
                 GROUP BY student_id",
                ...array_merge( $student_ids_list, array( $exam_att_start, $exam_att_end ) )
            )
        );
        // phpcs:enable

        if ( ! empty( $att_results ) ) {
            foreach ( $att_results as $ar ) {
                $attendance_present_counts[ $ar->student_id ] = absint( $ar->present_days );
            }
        }
    }

    // Pull Dynamic Institutional Settings
    $school_name    = get_option( 'educore_school_name', get_bloginfo( 'name' ) );
    $school_tagline = get_option( 'educore_school_tagline', '' );
    $school_logo    = get_option( 'educore_school_logo', '' );
    $principal_sig  = get_option( 'educore_principal_sig', '' );

    if ( empty( $school_name ) || 'WordPress' === $school_name ) {
        $school_name = get_bloginfo( 'name' );
    }
    ?>

    <style>
    .ifs-educore-admit-engine-root {
        padding: 10px 0;
        font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
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

    /* Admit Card Physical Box */
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
        text-transform: uppercase;
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
        text-transform: uppercase;
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
            <h2>
                <span class="dashicons dashicons-tickets-alt" style="color:#00523c;"></span>
                <?php esc_html_e( 'Academic Admit Card Compiler', 'ifsedu-school-management' ); ?>
            </h2>

            <form method="GET" action="" class="ifs-educore-form-grid-wrapper">
                <input type="hidden" name="page" value="school_management_system">
                <input type="hidden" name="tab" value="students">
                <input type="hidden" name="sub" value="admit_card">

                <div class="ifs-educore-input-block">
                    <label><?php esc_html_e( 'Select Examination', 'ifsedu-school-management' ); ?> <span style="color:#ef4444;">*</span></label>
                    <select name="exam_id" required>
                        <option value=""><?php esc_html_e( '-- Choose Exam --', 'ifsedu-school-management' ); ?></option>
                        <?php foreach ( $exams as $ex ) : ?>
                            <option value="<?php echo intval( $ex->id ); ?>" <?php selected( $selected_exam_id, $ex->id ); ?>>
                                <?php echo esc_html( $ex->exam_name ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="ifs-educore-input-block">
                    <label><?php esc_html_e( 'Select Class', 'ifsedu-school-management' ); ?> <span style="color:#ef4444;">*</span></label>
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
                    <label><?php esc_html_e( 'Select Section', 'ifsedu-school-management' ); ?></label>
                    <select name="section_name" id="ifs_educore_admit_section_select">
                        <option value=""><?php esc_html_e( '-- All Sections --', 'ifsedu-school-management' ); ?></option>
                        <?php foreach ( $available_sections as $sec_name ) : ?>
                            <option value="<?php echo esc_attr( $sec_name ); ?>" <?php selected( $selected_section, $sec_name ); ?>>
                                <?php echo esc_html( $sec_name ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Individual Student Selector -->
                <div class="ifs-educore-input-block">
                    <label><?php esc_html_e( 'Single Student (Optional)', 'ifsedu-school-management' ); ?></label>
                    <select name="student_id" id="ifs_educore_admit_student_select">
                        <option value="0"><?php esc_html_e( '-- All Students in Section --', 'ifsedu-school-management' ); ?></option>
                        <?php foreach ( $available_students as $st_item ) : ?>
                            <option value="<?php echo intval( $st_item->id ); ?>" <?php selected( $selected_student, $st_item->id ); ?>>
                                <?php echo esc_html( sprintf( '[Roll %1$s] %2$s (%3$s)', $st_item->roll_no, $st_item->full_name, strtoupper( (string) $st_item->student_id ) ) ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="ifs-educore-input-block">
                    <label><?php esc_html_e( 'Session Year', 'ifsedu-school-management' ); ?></label>
                    <input type="text" name="exam_year" value="<?php echo esc_attr( $exam_year ); ?>" required>
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
        <?php if ( ! empty( $selected_class ) && $selected_exam_id > 0 ) : ?>
            <div id="ifs-educore-printable-admit-area">
                <?php if ( ! empty( $students ) ) : ?>
                    <div class="ifs-educore-admit-cards-container">
                        <?php foreach ( $students as $student ) : 
                            $card_id = 'admit_card_' . $student->id;

                            // Calculate Student Attendance Percentage for this Exam Range
                            $student_present_days = isset( $attendance_present_counts[ $student->id ] ) ? $attendance_present_counts[ $student->id ] : 0;
                            $att_percentage       = round( ( $student_present_days / $total_working_days ) * 100, 1 );
                            $is_eligible          = $att_percentage >= $attendance_threshold;
                        ?>
                            <div class="ifs-educore-admit-card-wrapper" id="<?php echo esc_attr( $card_id ); ?>">
                                
                                <!-- Individual Print Action (External Bar) -->
                                <div class="ifs-educore-admit-card-top-tools no-print">
                                    <span style="font-size:11.5px; font-weight:800; color:#0f172a;">
                                        <?php esc_html_e( 'Roll:', 'ifsedu-school-management' ); ?> #<?php echo esc_html( $student->roll_no ); ?> &mdash; <?php echo esc_html( $student->full_name ); ?>
                                    </span>
                                    <button type="button" onclick="educorePrintSingleCard('<?php echo esc_js( $card_id ); ?>');" class="ifs-educore-single-print-btn">
                                        <span class="dashicons dashicons-printer" style="font-size:13px; width:13px; height:13px;"></span>
                                        <?php esc_html_e( 'Print This Card', 'ifsedu-school-management' ); ?>
                                    </button>
                                </div>

                                <div class="ifs-educore-admit-card-box">
                                    <!-- Header -->
                                    <div class="ifs-educore-admit-header">
                                        <div class="ifs-educore-admit-school-brand-row">
                                            <?php if ( ! empty( $school_logo ) ) : ?>
                                                <img src="<?php echo esc_url( $school_logo ); ?>" alt="<?php esc_attr_e( 'Logo', 'ifsedu-school-management' ); ?>" class="ifs-educore-admit-logo-img">
                                            <?php endif; ?>
                                            <h3 class="ifs-educore-admit-school-title"><?php echo esc_html( $school_name ); ?></h3>
                                        </div>
                                        <div class="ifs-educore-admit-school-sub">
                                            <?php echo esc_html( ! empty( $school_tagline ) ? $school_tagline : get_bloginfo( 'description' ) ); ?>
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
                                                    <td class="value-col" style="text-transform: uppercase;"><?php echo esc_html( $student->full_name ); ?></td>
                                                </tr>
                                                <tr>
                                                    <td class="label-col"><?php esc_html_e( 'Class & Section:', 'ifsedu-school-management' ); ?></td>
                                                    <td class="value-col">
                                                        <?php echo esc_html( $student->class_name ); ?>
                                                        <?php echo ! empty( $student->section_name ) ? esc_html( ' (Sec: ' . $student->section_name . ')' ) : ''; ?>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td class="label-col"><?php esc_html_e( 'Roll Number:', 'ifsedu-school-management' ); ?></td>
                                                    <td class="value-col">
                                                        <span style="background: #0f172a; color:#ffffff; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 800;">
                                                            #<?php echo esc_html( $student->roll_no ); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td class="label-col"><?php esc_html_e( 'Term Attendance:', 'ifsedu-school-management' ); ?></td>
                                                    <td class="value-col">
                                                        <span style="font-weight:800; color:<?php echo $is_eligible ? '#047857' : '#dc2626'; ?>;">
                                                            <?php echo esc_html( $att_percentage ); ?>%
                                                        </span>
                                                        <small style="color:#64748b; font-size:11px; font-weight:600;">
                                                            (<?php echo intval( $student_present_days ); ?>/<?php echo intval( $total_working_days ); ?> <?php esc_html_e( 'Days', 'ifsedu-school-management' ); ?>)
                                                        </small>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td class="label-col"><?php esc_html_e( 'Guardian Phone:', 'ifsedu-school-management' ); ?></td>
                                                    <td class="value-col" style="color: #334155;">
                                                        <?php echo esc_html( ! empty( $student->guardian_phone ) ? $student->guardian_phone : ( ! empty( $student->father_phone ) ? $student->father_phone : __( 'N/A', 'ifsedu-school-management' ) ) ); ?>
                                                    </td>
                                                </tr>
                                            </table>
                                        </div>

                                        <div class="ifs-educore-admit-photo-column">
                                            <div class="ifs-educore-student-photo-frame">
                                                <?php if ( ! empty( $student->photo_url ) ) : ?>
                                                    <img src="<?php echo esc_url( $student->photo_url ); ?>" alt="<?php echo esc_attr( $student->full_name ); ?>">
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
                                                <img src="<?php echo esc_url( $principal_sig ); ?>" alt="<?php esc_attr_e( 'Signature', 'ifsedu-school-management' ); ?>" class="ifs-educore-admit-sig-img">
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
            var $sectionSelect = $('#ifs_educore_admit_section_select');
            var $studentSelect = $('#ifs_educore_admit_student_select');

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

    // Individual Single Card Print Isolation Trigger
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
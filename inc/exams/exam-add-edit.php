<?php
/**
 * Add / Edit Examination Scheme View & Controller
 * File: inc/exams/exam-add-edit.php
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Render Add/Edit Examination Scheme View & Handle Form Submission
 */
function educore_exam_add_edit_view() {
    if ( function_exists( 'educore_has_access' ) ) {
        if ( ! educore_has_access( 'educore_manage_exams' ) ) {
            wp_die(
                esc_html__( 'You do not have sufficient permissions to configure examinations.', 'ifsedu-school-management' ),
                403
            );
        }
    } elseif ( ! current_user_can( 'manage_options' ) ) {
        wp_die(
            esc_html__( 'You do not have sufficient permissions to configure examinations.', 'ifsedu-school-management' ),
            403
        );
    }

    global $wpdb;

    // Helper function for table name resolution
    $table_exams      = $wpdb->prefix . 'sms_exams';
    $table_units      = $wpdb->prefix . 'sms_academic_units';
    $table_subjects   = $wpdb->prefix . 'sms_subjects';
    $table_attendance = $wpdb->prefix . 'sms_attendance';

    if ( function_exists( 'educore_get_table_name' ) ) {
        $tbl_ex = educore_get_table_name( 'exams' );
        if ( ! empty( $tbl_ex ) ) { $table_exams = $tbl_ex; }
        $tbl_un = educore_get_table_name( 'academic_units' );
        if ( ! empty( $tbl_un ) ) { $table_units = $tbl_un; }
        $tbl_sb = educore_get_table_name( 'subjects' );
        if ( ! empty( $tbl_sb ) ) { $table_subjects = $tbl_sb; }
        $tbl_at = educore_get_table_name( 'attendance' );
        if ( ! empty( $tbl_at ) ) { $table_attendance = $tbl_at; }
    }

    $list_url = add_query_arg(
        array(
            'page' => 'school_management_system',
            'tab'  => 'exams',
            'sub'  => 'list',
        ),
        admin_url( 'admin.php' )
    );

    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $get_action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
    $get_id     = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0;
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    $is_edit   = ( 'edit' === $get_action && $get_id > 0 );
    $edit_exam = null;

    $edit_exam_title     = '';
    $edit_exam_year      = current_time( 'Y' );
    $selected_classes    = array();
    $selected_subjects   = array();
    $att_start_default   = gmdate( 'Y-01-01' );
    $att_end_default     = current_time( 'Y-m-d' );
    $min_att_pct         = 75.00;
    $total_work_days     = 0;
    $include_att         = 'yes';
    $fee_clear_month     = '';
    $weightage_pct       = 100.00;
    $enable_term_contrib = 'no';
    $term_weights_saved  = array();

    // Fetch all existing exams for the repeater dropdown
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $all_existing_exams = $wpdb->get_results( "SELECT id, exam_name FROM `{$table_exams}` ORDER BY id DESC" );
    // phpcs:enable

    if ( $is_edit ) {
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $edit_exam = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM `{$table_exams}` WHERE id = %d LIMIT 1",
                $get_id
            )
        );
        // phpcs:enable

        if ( $edit_exam ) {
            $parts = explode( ' - ', (string) $edit_exam->exam_name );
            if ( count( $parts ) > 1 && is_numeric( end( $parts ) ) ) {
                $edit_exam_year  = (string) array_pop( $parts );
                $edit_exam_title = implode( ' - ', $parts );
            } else {
                $edit_exam_title = (string) $edit_exam->exam_name;
            }

            if ( ! empty( $edit_exam->class_name ) ) {
                $selected_classes = array_map( 'trim', explode( ',', (string) $edit_exam->class_name ) );
            }

            if ( ! empty( $edit_exam->subject_ids ) ) {
                $decoded_sub       = json_decode( (string) $edit_exam->subject_ids, true );
                $selected_subjects = is_array( $decoded_sub ) ? $decoded_sub : array();
            }

            $att_start_default   = ! empty( $edit_exam->att_start_date ) ? (string) $edit_exam->att_start_date : (string) $edit_exam->start_date;
            $att_end_default     = ! empty( $edit_exam->att_end_date ) ? (string) $edit_exam->att_end_date : (string) $edit_exam->end_date;
            $min_att_pct         = isset( $edit_exam->min_attendance_pct ) ? (float) $edit_exam->min_attendance_pct : 75.00;
            $total_work_days     = isset( $edit_exam->total_working_days ) ? absint( $edit_exam->total_working_days ) : 0;
            $include_att         = isset( $edit_exam->include_attendance ) ? (string) $edit_exam->include_attendance : 'yes';
            $fee_clear_month     = isset( $edit_exam->fee_clear_month ) ? (string) $edit_exam->fee_clear_month : '';
            $weightage_pct       = isset( $edit_exam->weightage_pct ) ? (float) $edit_exam->weightage_pct : 100.00;
            $enable_term_contrib = isset( $edit_exam->enable_term_contrib ) ? (string) $edit_exam->enable_term_contrib : 'no';

            if ( ! empty( $edit_exam->term_weights_json ) ) {
                $decoded_tw = json_decode( (string) $edit_exam->term_weights_json, true );
                $term_weights_saved = is_array( $decoded_tw ) ? $decoded_tw : array();
            }
        } else {
            $is_edit = false;
        }
    }

    // --------------------------------------------------------------------------
    // Handle Save / Update Form Submission
    // --------------------------------------------------------------------------
    $req_method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';

    if ( 'POST' === $req_method && isset( $_POST['educore_exam_action'] ) && 'save_exam' === $_POST['educore_exam_action'] ) {
        $nonce = isset( $_POST['ifs_educore_exam_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['ifs_educore_exam_nonce'] ) ) : '';

        if ( ! wp_verify_nonce( $nonce, 'save_exam_action' ) ) {
            wp_die(
                esc_html__( 'Security verification failed. Please refresh the page and try again.', 'ifsedu-school-management' ),
                403
            );
        }

        $exam_id_input    = isset( $_POST['exam_id'] ) ? absint( wp_unslash( $_POST['exam_id'] ) ) : 0;
        $exam_title_input = isset( $_POST['exam_title'] ) ? sanitize_text_field( wp_unslash( $_POST['exam_title'] ) ) : '';
        $exam_year_input  = isset( $_POST['exam_year'] ) ? sanitize_text_field( wp_unslash( $_POST['exam_year'] ) ) : current_time( 'Y' );
        $full_exam_name   = ! empty( $exam_year_input ) ? $exam_title_input . ' - ' . $exam_year_input : $exam_title_input;

        $class_names_input = ( isset( $_POST['class_name'] ) && is_array( $_POST['class_name'] ) ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['class_name'] ) ) : array();
        $class_name        = ! empty( $class_names_input ) ? implode( ', ', $class_names_input ) : '';

        // Capture Class-Wise Subjects JSON map
        $raw_subjects_input = ( isset( $_POST['exam_subjects'] ) && is_array( $_POST['exam_subjects'] ) ) ? wp_unslash( $_POST['exam_subjects'] ) : array();
        $sanitized_subjects = array();
        foreach ( $raw_subjects_input as $cls_key => $sub_ids ) {
            if ( is_array( $sub_ids ) ) {
                $sanitized_subjects[ sanitize_text_field( (string) $cls_key ) ] = array_map( 'absint', $sub_ids );
            }
        }
        $subject_ids_json = wp_json_encode( $sanitized_subjects );

        $start_date = ! empty( $_POST['start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) : current_time( 'Y-m-d' );
        $end_date   = ! empty( $_POST['end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['end_date'] ) ) : current_time( 'Y-m-d' );

        // Normalize Dates
        $raw_att_start = ! empty( $_POST['att_start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['att_start_date'] ) ) : $start_date;
        $raw_att_end   = ! empty( $_POST['att_end_date'] ) ? sanitize_text_field( wp_unslash( $_POST['att_end_date'] ) ) : $end_date;

        $att_start_date = ( false !== strpos( $raw_att_start, '/' ) ) ? gmdate( 'Y-m-d', strtotime( str_replace( '/', '-', $raw_att_start ) ) ) : $raw_att_start;
        $att_end_date   = ( false !== strpos( $raw_att_end, '/' ) ) ? gmdate( 'Y-m-d', strtotime( str_replace( '/', '-', $raw_att_end ) ) ) : $raw_att_end;

        $min_att_pct         = isset( $_POST['min_attendance_pct'] ) ? (float) wp_unslash( $_POST['min_attendance_pct'] ) : 75.00;
        $include_att         = isset( $_POST['include_attendance'] ) ? 'yes' : 'no';
        $fee_clear_month     = isset( $_POST['fee_clear_month'] ) ? sanitize_text_field( wp_unslash( $_POST['fee_clear_month'] ) ) : '';
        $weightage_pct       = isset( $_POST['weightage_pct'] ) ? (float) wp_unslash( $_POST['weightage_pct'] ) : 100.00;
        $enable_term_contrib = isset( $_POST['enable_term_contrib'] ) ? 'yes' : 'no';
        $status              = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'Upcoming';

        // Capture repeater term weights
        $raw_term_weights = ( isset( $_POST['term_weights'] ) && is_array( $_POST['term_weights'] ) ) ? wp_unslash( $_POST['term_weights'] ) : array();
        $sanitized_term_weights = array();
        foreach ( $raw_term_weights as $tw_item ) {
            $t_id  = isset( $tw_item['exam_id'] ) ? absint( $tw_item['exam_id'] ) : 0;
            $t_pct = isset( $tw_item['percentage'] ) ? floatval( $tw_item['percentage'] ) : 0.00;
            if ( $t_id > 0 && $t_pct > 0 ) {
                $sanitized_term_weights[ $t_id ] = $t_pct;
            }
        }
        $term_weights_json = wp_json_encode( $sanitized_term_weights );

        // Auto-Calculate Working Days server-side (excluding Fridays)
        $total_working_days = 0;
        if ( 'yes' === $include_att && ! empty( $att_start_date ) && ! empty( $att_end_date ) ) {
            try {
                $begin    = new DateTime( $att_start_date );
                $end      = new DateTime( $att_end_date );
                $end->modify( '+1 day' );
                $interval = new DateInterval( 'P1D' );
                $period   = new DatePeriod( $begin, $interval, $end );

                $calculated_days = 0;
                foreach ( $period as $dt ) {
                    if ( '5' !== $dt->format( 'N' ) ) {
                        $calculated_days++;
                    }
                }
                $total_working_days = max( 1, $calculated_days );
            } catch ( Exception $e ) {
                $total_working_days = 1;
            }
        }

        $data = array(
            'exam_name'           => $full_exam_name,
            'class_name'          => $class_name,
            'subject_ids'         => $subject_ids_json,
            'start_date'          => $start_date,
            'end_date'            => $end_date,
            'att_start_date'      => $att_start_date,
            'att_end_date'        => $att_end_date,
            'min_attendance_pct'  => $min_att_pct,
            'total_working_days'  => $total_working_days,
            'include_attendance'  => $include_att,
            'fee_clear_month'     => $fee_clear_month,
            'weightage_pct'       => $weightage_pct,
            'enable_term_contrib' => $enable_term_contrib,
            'term_weights_json'   => $term_weights_json,
            'status'              => $status,
        );
        $format = array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%d', '%s', '%s', '%f', '%s', '%s', '%s' );

        // Ensure database columns exist safely
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $col_w = $wpdb->get_results( "SHOW COLUMNS FROM `{$table_exams}` LIKE 'weightage_pct'" );
        if ( empty( $col_w ) ) {
            $wpdb->query( "ALTER TABLE {$table_exams} ADD COLUMN weightage_pct DECIMAL(5,2) DEFAULT 100.00 NOT NULL AFTER fee_clear_month" );
        }
        $col_c = $wpdb->get_results( "SHOW COLUMNS FROM `{$table_exams}` LIKE 'enable_term_contrib'" );
        if ( empty( $col_c ) ) {
            $wpdb->query( "ALTER TABLE {$table_exams} ADD COLUMN enable_term_contrib VARCHAR(10) DEFAULT 'no' NOT NULL AFTER weightage_pct" );
        }
        $col_j = $wpdb->get_results( "SHOW COLUMNS FROM `{$table_exams}` LIKE 'term_weights_json'" );
        if ( empty( $col_j ) ) {
            $wpdb->query( "ALTER TABLE {$table_exams} ADD COLUMN term_weights_json LONGTEXT DEFAULT '' NOT NULL AFTER enable_term_contrib" );
        }

        if ( $exam_id_input > 0 ) {
            $wpdb->update( $table_exams, $data, array( 'id' => $exam_id_input ), $format, array( '%d' ) );
            $target_id = $exam_id_input;

            if ( function_exists( 'educore_log_activity' ) ) {
                educore_log_activity(
                    sprintf(
                        /* translators: %s: Exam Name */
                        __( 'Updated Examination Scheme: %s', 'ifsedu-school-management' ),
                        $full_exam_name
                    )
                );
            }
            $redirect_target = add_query_arg( array( 'status' => 'updated' ), $list_url );
        } else {
            $data['created_at'] = current_time( 'mysql' );
            $format[]           = '%s';
            $wpdb->insert( $table_exams, $data, $format );
            $target_id = (int) $wpdb->insert_id;

            if ( function_exists( 'educore_log_activity' ) ) {
                educore_log_activity(
                    sprintf(
                        /* translators: %s: Exam Name */
                        __( 'Created Examination Scheme: %s', 'ifsedu-school-management' ),
                        $full_exam_name
                    )
                );
            }
            $redirect_target = add_query_arg( array( 'status' => 'success' ), $list_url );
        }
        // phpcs:enable

        // Flush object caches
        if ( defined( 'EDUCORE_CACHE_GROUP' ) ) {
            wp_cache_delete( 'educore_exam_scheme_' . $target_id, EDUCORE_CACHE_GROUP );
            wp_cache_delete( 'educore_admin_metrics_' . current_time( 'YmdH' ), EDUCORE_CACHE_GROUP );
        }

        if ( function_exists( 'educore_safe_redirect' ) ) {
            educore_safe_redirect( $redirect_target );
        } else {
            wp_safe_redirect( $redirect_target );
            exit;
        }
    }

    // --------------------------------------------------------------------------
    // Query Classes and Associated Subjects
    // --------------------------------------------------------------------------
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $raw_classes_data = $wpdb->get_results(
        "SELECT class_name, MIN(sort_order) as min_sort 
         FROM `{$table_units}` 
         WHERE class_name IS NOT NULL AND class_name != '' 
         GROUP BY class_name 
         ORDER BY min_sort ASC, CAST(class_name AS UNSIGNED) ASC, class_name ASC"
    );

    $all_subjects_raw = $wpdb->get_results(
        "SELECT s.id, s.subject_name, s.subject_code, u.class_name 
         FROM `{$table_subjects}` s 
         INNER JOIN `{$table_units}` u ON s.class_id = u.id 
         ORDER BY u.sort_order ASC, s.subject_order ASC, s.subject_name ASC"
    );
    // phpcs:enable

    $class_list = array();
    if ( ! empty( $raw_classes_data ) && is_array( $raw_classes_data ) ) {
        foreach ( $raw_classes_data as $c_row ) {
            $c_name = trim( (string) $c_row->class_name );
            if ( ! empty( $c_name ) && ! in_array( $c_name, $class_list, true ) ) {
                $class_list[] = $c_name;
            }
        }
    }

    // Map subjects by class name
    $class_subjects_map = array();
    if ( ! empty( $all_subjects_raw ) ) {
        foreach ( $all_subjects_raw as $sub_item ) {
            $cn = trim( (string) $sub_item->class_name );
            if ( ! isset( $class_subjects_map[ $cn ] ) ) {
                $class_subjects_map[ $cn ] = array();
            }
            $exists_sub = false;
            foreach ( $class_subjects_map[ $cn ] as $es ) {
                if ( $es['id'] === (int) $sub_item->id || 0 === strcasecmp( (string) $es['name'], (string) $sub_item->subject_name ) ) {
                    $exists_sub = true;
                    break;
                }
            }
            if ( ! $exists_sub ) {
                $class_subjects_map[ $cn ][] = array(
                    'id'   => (int) $sub_item->id,
                    'name' => (string) $sub_item->subject_name,
                    'code' => (string) $sub_item->subject_code,
                );
            }
        }
    }

    $months_list = array(
        'January'   => __( 'January', 'ifsedu-school-management' ),
        'February'  => __( 'February', 'ifsedu-school-management' ),
        'March'     => __( 'March', 'ifsedu-school-management' ),
        'April'     => __( 'April', 'ifsedu-school-management' ),
        'May'       => __( 'May', 'ifsedu-school-management' ),
        'June'      => __( 'June', 'ifsedu-school-management' ),
        'July'      => __( 'July', 'ifsedu-school-management' ),
        'August'    => __( 'August', 'ifsedu-school-management' ),
        'September' => __( 'September', 'ifsedu-school-management' ),
        'October'   => __( 'October', 'ifsedu-school-management' ),
        'November'  => __( 'November', 'ifsedu-school-management' ),
        'December'  => __( 'December', 'ifsedu-school-management' ),
    );
    ?>

    <style>
        .ifs-educore-exam-card {
            background: #ffffff !important;
            border: 1px solid #e2e8f0 !important;
            border-radius: 16px !important;
            padding: 30px !important;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.03) !important;
            max-width: 960px !important;
            margin: 20px 0 !important;
            box-sizing: border-box !important;
        }
        .ifs-educore-form-group {
            margin-bottom: 22px !important;
        }
        .ifs-educore-form-label {
            display: block !important;
            font-size: 13px !important;
            font-weight: 700 !important;
            color: #334155 !important;
            letter-spacing: 0.3px !important;
            margin-bottom: 8px !important;
        }
        .ifs-educore-input-field,
        .ifs-educore-select-field {
            width: 100% !important;
            height: 44px !important;
            padding: 0 14px !important;
            border: 1.5px solid #cbd5e1 !important;
            border-radius: 8px !important;
            font-size: 14px !important;
            color: #0f172a !important;
            background: #ffffff !important;
            box-sizing: border-box !important;
            outline: none !important;
            transition: all 0.2s ease !important;
        }
        .ifs-educore-input-field:focus,
        .ifs-educore-select-field:focus {
            border-color: #00523c !important;
            box-shadow: 0 0 0 3px rgba(0, 82, 60, 0.12) !important;
        }
        .ifs-class-selector-panel {
            background: #f8fafc !important;
            border: 1.5px solid #e2e8f0 !important;
            border-radius: 12px !important;
            padding: 16px !important;
            box-sizing: border-box !important;
        }
        .ifs-class-panel-toolbar {
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            flex-wrap: wrap !important;
            gap: 10px !important;
            margin-bottom: 14px !important;
            padding-bottom: 12px !important;
            border-bottom: 1px solid #e2e8f0 !important;
        }
        .ifs-class-search-input {
            height: 34px !important;
            padding: 0 12px !important;
            font-size: 13px !important;
            border: 1px solid #cbd5e1 !important;
            border-radius: 6px !important;
            background: #ffffff !important;
            max-width: 220px !important;
            outline: none !important;
            box-sizing: border-box !important;
        }
        .ifs-class-search-input:focus {
            border-color: #00523c !important;
        }
        .ifs-class-toolbar-actions {
            display: flex !important;
            align-items: center !important;
            gap: 10px !important;
        }
        .ifs-class-count-badge {
            font-size: 12px !important;
            font-weight: 700 !important;
            background: #e2e8f0 !important;
            color: #475569 !important;
            padding: 4px 10px !important;
            border-radius: 999px !important;
            transition: all 0.2s ease !important;
        }
        .ifs-class-count-badge.has-selected {
            background: #dcfce7 !important;
            color: #15803d !important;
        }
        .ifs-class-btn-toggle {
            background: #ffffff !important;
            border: 1px solid #cbd5e1 !important;
            color: #334155 !important;
            padding: 5px 12px !important;
            border-radius: 6px !important;
            font-size: 12px !important;
            font-weight: 700 !important;
            cursor: pointer !important;
            transition: all 0.2s ease !important;
        }
        .ifs-class-btn-toggle:hover {
            background: #00523c !important;
            color: #ffffff !important;
            border-color: #00523c !important;
        }
        .ifs-class-grid {
            display: grid !important;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)) !important;
            gap: 10px !important;
            max-height: 250px !important;
            overflow-y: auto !important;
            padding: 2px !important;
            box-sizing: border-box !important;
        }
        .ifs-class-card {
            display: flex !important;
            align-items: center !important;
            gap: 8px !important;
            background: #ffffff !important;
            border: 1.5px solid #e2e8f0 !important;
            padding: 9px 12px !important;
            border-radius: 8px !important;
            font-size: 13.5px !important;
            font-weight: 600 !important;
            color: #1e293b !important;
            cursor: pointer !important;
            user-select: none !important;
            transition: all 0.15s ease !important;
            box-sizing: border-box !important;
        }
        .ifs-class-card:hover {
            border-color: #94a3b8 !important;
            background: #f8fafc !important;
        }
        .ifs-class-card.is-active {
            border-color: #00523c !important;
            background: #f0fdf4 !important;
            color: #00523c !important;
        }
        .ifs-class-card input[type="checkbox"] {
            margin: 0 !important;
            width: 16px !important;
            height: 16px !important;
            cursor: pointer !important;
            accent-color: #00523c !important;
            flex-shrink: 0 !important;
        }
        .ifs-class-name {
            white-space: nowrap !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
        }
        .ifs-subject-choice-wrapper {
            margin-top: 16px !important;
            display: flex !important;
            flex-direction: column !important;
            gap: 14px !important;
        }
        .ifs-class-subject-box {
            background: #ffffff !important;
            border: 1.5px solid #cbd5e1 !important;
            border-radius: 10px !important;
            padding: 14px 16px !important;
        }
        .ifs-class-subject-header {
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            border-bottom: 1px solid #f1f5f9 !important;
            padding-bottom: 8px !important;
            margin-bottom: 10px !important;
        }
        .ifs-subject-chips-grid {
            display: flex !important;
            flex-wrap: wrap !important;
            gap: 8px !important;
        }
        .ifs-subject-chip {
            background: #f8fafc !important;
            border: 1.5px solid #e2e8f0 !important;
            padding: 6px 10px !important;
            border-radius: 6px !important;
            font-size: 12.5px !important;
            font-weight: 600 !important;
            color: #334155 !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 6px !important;
            cursor: pointer !important;
            user-select: none !important;
            transition: all 0.15s ease !important;
        }
        .ifs-subject-chip:hover {
            border-color: #00523c !important;
            background: #f0fdf4 !important;
        }
        .ifs-subject-chip.is-active {
            border-color: #00523c !important;
            background: #ecfdf5 !important;
            color: #047857 !important;
        }
        .ifs-subject-chip input[type="checkbox"] {
            margin: 0 !important;
            accent-color: #00523c !important;
            cursor: pointer !important;
        }
        .ifs-attendance-range-card {
            background: #f0fdf4 !important;
            border: 1.5px solid #bbf7d0 !important;
            border-radius: 12px !important;
            padding: 16px 20px !important;
            margin-bottom: 22px !important;
            transition: opacity 0.2s ease;
        }
        .ifs-attendance-range-card.is-disabled {
            opacity: 0.55;
            background: #f8fafc !important;
            border-color: #cbd5e1 !important;
        }
        .ifs-term-contrib-card {
            background: #eff6ff !important;
            border: 1.5px solid #bfdbfe !important;
            border-radius: 12px !important;
            padding: 16px 20px !important;
            margin-bottom: 22px !important;
            transition: opacity 0.2s ease;
        }
        .ifs-term-contrib-card.is-disabled {
            opacity: 0.55;
            background: #f8fafc !important;
            border-color: #cbd5e1 !important;
        }
        .ifs-term-repeater-row {
            display: flex !important;
            align-items: center !important;
            gap: 12px !important;
            margin-bottom: 10px !important;
        }
        .ifs-educore-btn-save {
            background: #00523c !important;
            color: #ffffff !important;
            border: none !important;
            padding: 12px 28px !important;
            border-radius: 8px !important;
            font-weight: 700 !important;
            font-size: 14.5px !important;
            cursor: pointer !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 8px !important;
            box-shadow: 0 4px 12px rgba(0, 82, 60, 0.2) !important;
            transition: background 0.2s !important;
        }
        .ifs-educore-btn-save:hover {
            background: #047857 !important;
        }
        .ifs-weight-validation-banner {
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            background: #ffffff !important;
            border: 1.5px solid #93c5fd !important;
            border-radius: 8px !important;
            padding: 10px 14px !important;
            margin-top: 14px !important;
            font-size: 13px !important;
            font-weight: 700 !important;
            color: #1e40af !important;
        }
    </style>

    <div class="ifs-educore-exam-card">
        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #f1f5f9; padding-bottom:16px; margin-bottom:24px;">
            <h3 style="margin:0; font-size:18px; font-weight:800; color:#0f172a; display:flex; align-items:center; gap:8px;">
                <span class="dashicons dashicons-edit" style="color:#00523c;"></span>
                <?php echo $is_edit ? esc_html__( 'Edit Examination Scheme', 'ifsedu-school-management' ) : esc_html__( 'Create New Examination Scheme', 'ifsedu-school-management' ); ?>
            </h3>
            <a href="<?php echo esc_url( $list_url ); ?>" style="text-decoration:none; color:#475569; font-weight:700; font-size:13px; display:inline-flex; align-items:center; gap:4px;">
                <span class="dashicons dashicons-arrow-left-alt"></span> <?php esc_html_e( 'Back to List', 'ifsedu-school-management' ); ?>
            </a>
        </div>

        <form method="POST" action="" id="educoreExamForm">
            <?php wp_nonce_field( 'save_exam_action', 'ifs_educore_exam_nonce' ); ?>
            <input type="hidden" name="educore_exam_action" value="save_exam">
            <input type="hidden" name="exam_id" value="<?php echo $is_edit ? absint( (int) $edit_exam->id ) : 0; ?>">

            <!-- Exam Title, Academic Year & Current Exam Weight -->
            <div style="display:grid; grid-template-columns: 2fr 1fr 1fr; gap:16px;">
                <div class="ifs-educore-form-group">
                    <label class="ifs-educore-form-label" for="exam_title">
                        <?php esc_html_e( 'Exam Title', 'ifsedu-school-management' ); ?> <span style="color:#ef4444;">*</span>
                    </label>
                    <input type="text" name="exam_title" id="exam_title" class="ifs-educore-input-field" placeholder="<?php esc_attr_e( 'e.g. First Term / Final Exam', 'ifsedu-school-management' ); ?>" value="<?php echo esc_attr( $edit_exam_title ); ?>" required>
                </div>
                <div class="ifs-educore-form-group">
                    <label class="ifs-educore-form-label" for="exam_year">
                        <?php esc_html_e( 'Academic Year', 'ifsedu-school-management' ); ?> <span style="color:#ef4444;">*</span>
                    </label>
                    <input type="number" name="exam_year" id="exam_year" class="ifs-educore-input-field" min="2020" max="2099" value="<?php echo esc_attr( $edit_exam_year ); ?>" required>
                </div>
                <div class="ifs-educore-form-group">
                    <label class="ifs-educore-form-label" for="weightage_pct">
                        <?php esc_html_e( 'Current Exam Weight (%)', 'ifsedu-school-management' ); ?>
                    </label>
                    <input type="number" step="0.01" min="0" max="100" name="weightage_pct" id="weightage_pct" class="ifs-educore-input-field" value="<?php echo esc_attr( (string) $weightage_pct ); ?>" placeholder="100">
                    <small style="color:#64748b; font-size:11px; display:block; margin-top:3px;"><?php esc_html_e( 'e.g. 80 for 80%', 'ifsedu-school-management' ); ?></small>
                </div>
            </div>

            <!-- Multi-Term Cumulative Grade Contribution Switcher & Repeater -->
            <div class="ifs-term-contrib-card <?php echo ( 'yes' !== $enable_term_contrib ) ? 'is-disabled' : ''; ?>" id="termContribCard">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; border-bottom:1px solid #bfdbfe; padding-bottom:10px;">
                    <div>
                        <strong style="color:#1d4ed8; font-size:13.5px; text-transform:capitalize; display:flex; align-items:center; gap:6px;">
                            <span class="dashicons dashicons-analytics"></span>
                            <?php esc_html_e( 'Cumulative Term Mark Contribution (%)', 'ifsedu-school-management' ); ?>
                        </strong>
                    </div>
                    <div>
                        <label style="font-weight:700; font-size:13px; color:#1e40af; cursor:pointer; display:inline-flex; align-items:center; gap:6px;">
                            <input type="checkbox" name="enable_term_contrib" id="enableTermContribCheckbox" value="yes" <?php checked( $enable_term_contrib, 'yes' ); ?> style="width:16px; height:16px; accent-color:#1d4ed8; cursor:pointer;">
                            <?php esc_html_e( 'Enable Mark % Contribution from Previous Exams', 'ifsedu-school-management' ); ?>
                        </label>
                    </div>
                </div>

                <div id="termContribFieldsWrapper" style="display: <?php echo ( 'yes' === $enable_term_contrib ) ? 'block' : 'none'; ?>;">
                    <small style="color:#475569; font-size:12px; display:block; margin-bottom:12px;">
                        <?php esc_html_e( 'Configure how much percentage (%) marks from other exams contribute toward this final exam (e.g., 1st Term 10%, 2nd Term 10%).', 'ifsedu-school-management' ); ?>
                    </small>

                    <div id="termRepeaterContainer">
                        <?php 
                        if ( ! empty( $term_weights_saved ) && is_array( $term_weights_saved ) ) {
                            foreach ( $term_weights_saved as $s_ex_id => $s_pct ) {
                                ?>
                                <div class="ifs-term-repeater-row">
                                    <select name="term_weights[][exam_id]" class="ifs-educore-select-field" style="flex:2;">
                                        <option value=""><?php esc_html_e( '-- Select Exam --', 'ifsedu-school-management' ); ?></option>
                                        <?php foreach ( $all_existing_exams as $ex_item ) : 
                                            if ( $get_id && (int) $ex_item->id === (int) $get_id ) { continue; } // Exclude current exam
                                        ?>
                                            <option value="<?php echo absint( $ex_item->id ); ?>" <?php selected( $s_ex_id, $ex_item->id ); ?>>
                                                <?php echo esc_html( $ex_item->exam_name ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div style="flex:1; display:flex; align-items:center; gap:6px;">
                                        <input type="number" step="0.01" min="0" max="100" name="term_weights[][percentage]" class="ifs-educore-input-field term-weight-input" value="<?php echo esc_attr( (string) $s_pct ); ?>" placeholder="10">
                                        <span style="font-weight:700; color:#475569;">%</span>
                                    </div>
                                    <button type="button" class="ifs-class-btn-toggle btn-remove-term-row" style="background:#fee2e2; color:#b91c1c; border-color:#fca5a5;">
                                        <span class="dashicons dashicons-trash" style="font-size:14px; width:14px; height:14px; vertical-align:middle;"></span>
                                    </button>
                                </div>
                                <?php
                            }
                        }
                        ?>
                    </div>

                    <button type="button" id="btnAddTermRow" class="ifs-class-btn-toggle" style="margin-top:8px; background:#eff6ff; color:#1d4ed8; border-color:#bfdbfe;">
                        <span class="dashicons dashicons-plus-alt2" style="font-size:14px; width:14px; height:14px; vertical-align:middle;"></span>
                        <?php esc_html_e( 'Add Exam Contribution', 'ifsedu-school-management' ); ?>
                    </button>

                    <!-- Live Subtracted Weightage Balance Indicator -->
                    <div class="ifs-weight-validation-banner">
                        <span><?php esc_html_e( 'Current Exam Calculated Weight (100% minus repeater):', 'ifsedu-school-management' ); ?></span>
                        <span><strong id="displayCurrentExamWeight">100</strong>%</span>
                    </div>
                </div>
            </div>

            <!-- Applicable Classes Matrix -->
            <div class="ifs-educore-form-group">
                <label class="ifs-educore-form-label"><?php esc_html_e( 'Applicable Classes', 'ifsedu-school-management' ); ?> <span style="color:#ef4444;">*</span></label>
                
                <div class="ifs-class-selector-panel">
                    <div class="ifs-class-panel-toolbar">
                        <input type="text" id="classSearchInput" class="ifs-class-search-input" placeholder="<?php esc_attr_e( 'Search class...', 'ifsedu-school-management' ); ?>" autocomplete="off">
                        
                        <div class="ifs-class-toolbar-actions">
                            <span class="ifs-class-count-badge" id="selectedClassCountBadge">0 Selected</span>
                            <button type="button" class="ifs-class-btn-toggle" id="btnToggleAllClasses"><?php esc_html_e( 'Select All', 'ifsedu-school-management' ); ?></button>
                        </div>
                    </div>

                    <div class="ifs-class-grid" id="classGridContainer">
                        <?php if ( ! empty( $class_list ) ) : foreach ( $class_list as $cls_name ) : 
                            $is_checked = in_array( $cls_name, $selected_classes, true );
                        ?>
                            <label class="ifs-class-card <?php echo $is_checked ? 'is-active' : ''; ?>" data-class-text="<?php echo esc_attr( strtolower( $cls_name ) ); ?>">
                                <input type="checkbox" name="class_name[]" value="<?php echo esc_attr( $cls_name ); ?>" class="cb-class" <?php checked( $is_checked ); ?>>
                                <span class="ifs-class-name"><?php echo esc_html( $cls_name ); ?></span>
                            </label>
                        <?php endforeach; else : ?>
                            <div style="font-size:13px; color:#ef4444; padding: 12px; font-weight:700; grid-column: 1 / -1; text-align: center;">
                                <?php esc_html_e( 'No classes configured yet in Academic Setup.', 'ifsedu-school-management' ); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Class-Wise Subject Choice Section -->
            <div class="ifs-educore-form-group">
                <label class="ifs-educore-form-label"><?php esc_html_e( 'Class-Wise Included Exam Subjects', 'ifsedu-school-management' ); ?></label>
                <small style="color:#64748b; font-size:12px; display:block; margin-top:-4px; margin-bottom:8px;">
                    <?php esc_html_e( 'Select which specific subjects are evaluated in this exam for each chosen class.', 'ifsedu-school-management' ); ?>
                </small>

                <div class="ifs-subject-choice-wrapper" id="ifs_class_subjects_container">
                    <?php if ( ! empty( $class_list ) ) : foreach ( $class_list as $cls_name ) : 
                        $cls_subs           = $class_subjects_map[ $cls_name ] ?? array();
                        $saved_subs_for_cls = $selected_subjects[ $cls_name ] ?? array();
                        $is_cls_active      = in_array( $cls_name, $selected_classes, true );
                    ?>
                        <div class="ifs-class-subject-box" data-class-box="<?php echo esc_attr( $cls_name ); ?>" style="display: <?php echo $is_cls_active ? 'block' : 'none'; ?>;">
                            <div class="ifs-class-subject-header">
                                <strong style="color:#0f172a; font-size:13.5px;"><?php printf( esc_html__( 'Class: %s', 'ifsedu-school-management' ), esc_html( $cls_name ) ); ?></strong>
                                <?php if ( ! empty( $cls_subs ) ) : ?>
                                    <button type="button" class="btn-toggle-class-subjects" data-class-target="<?php echo esc_attr( $cls_name ); ?>" style="background:none; border:none; color:#00523c; font-size:11.5px; font-weight:700; cursor:pointer;">
                                        <?php esc_html_e( 'Select All Subjects', 'ifsedu-school-management' ); ?>
                                    </button>
                                <?php endif; ?>
                            </div>

                            <div class="ifs-subject-chips-grid">
                                <?php if ( ! empty( $cls_subs ) ) : foreach ( $cls_subs as $s_item ) : 
                                    $is_sub_checked = empty( $selected_subjects ) || in_array( $s_item['id'], $saved_subs_for_cls, true );
                                ?>
                                    <label class="ifs-subject-chip <?php echo $is_sub_checked ? 'is-active' : ''; ?>">
                                        <input type="checkbox" name="exam_subjects[<?php echo esc_attr( $cls_name ); ?>][]" value="<?php echo esc_attr( (string) $s_item['id'] ); ?>" class="cb-sub-choice" <?php checked( $is_sub_checked ); ?>>
                                        <span><?php echo esc_html( $s_item['name'] . ( ! empty( $s_item['code'] ) ? ' (' . $s_item['code'] . ')' : '' ) ); ?></span>
                                    </label>
                                <?php endforeach; else : ?>
                                    <span style="font-size:12px; color:#94a3b8; font-style:italic;">
                                        <?php esc_html_e( 'No subjects configured for this class yet in Academics -> Class Wise Subjects.', 'ifsedu-school-management' ); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>

            <!-- Attendance Calculation Period & Target Thresholds -->
            <div class="ifs-attendance-range-card <?php echo ( 'yes' !== $include_att ) ? 'is-disabled' : ''; ?>" id="attendanceSettingsCard">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; border-bottom:1px solid #bbf7d0; padding-bottom:10px;">
                    <div>
                        <strong style="color:#00523c; font-size:13.5px; text-transform:capitalize; display:flex; align-items:center; gap:6px;">
                            <span class="dashicons dashicons-calendar-alt"></span>
                            <?php esc_html_e( 'Attendance Percentage & Days Calculation', 'ifsedu-school-management' ); ?>
                        </strong>
                    </div>
                    <div>
                        <label style="font-weight:700; font-size:13px; color:#065f46; cursor:pointer; display:inline-flex; align-items:center; gap:6px;">
                            <input type="checkbox" name="include_attendance" id="includeAttendanceCheckbox" value="yes" <?php checked( $include_att, 'yes' ); ?> style="width:16px; height:16px; accent-color:#00523c; cursor:pointer;">
                            <?php esc_html_e( 'Calculate Attendance Percentage in Report Cards', 'ifsedu-school-management' ); ?>
                        </label>
                    </div>
                </div>

                <div id="attendanceFieldsWrapper" style="display: <?php echo ( 'yes' === $include_att ) ? 'block' : 'none'; ?>;">
                    <small style="color:#475569; font-size:12px; display:block; margin-bottom:12px;">
                        <?php esc_html_e( 'Specifies the working date range and minimum attendance quota. Total Working Days and Minimum Required Days are auto-calculated dynamically.', 'ifsedu-school-management' ); ?>
                    </small>

                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom:14px;">
                        <div>
                            <label class="ifs-educore-form-label" style="font-size:12px; color:#065f46;" for="att_start_date">
                                <?php esc_html_e( 'Attendance Count Starts From', 'ifsedu-school-management' ); ?> <span style="color:#ef4444;">*</span>
                            </label>
                            <input type="date" name="att_start_date" id="att_start_date" class="ifs-educore-input-field" value="<?php echo esc_attr( $att_start_default ); ?>">
                        </div>

                        <div>
                            <label class="ifs-educore-form-label" style="font-size:12px; color:#065f46;" for="att_end_date">
                                <?php esc_html_e( 'Attendance Count Ends On', 'ifsedu-school-management' ); ?> <span style="color:#ef4444;">*</span>
                            </label>
                            <input type="date" name="att_end_date" id="att_end_date" class="ifs-educore-input-field" value="<?php echo esc_attr( $att_end_default ); ?>">
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:16px; align-items: center;">
                        <div>
                            <label class="ifs-educore-form-label" style="font-size:12px; color:#065f46;" for="min_attendance_pct_input">
                                <?php esc_html_e( 'Minimum Attendance Quota (%)', 'ifsedu-school-management' ); ?>
                            </label>
                            <input type="number" step="0.01" min="0" max="100" name="min_attendance_pct" id="min_attendance_pct_input" class="ifs-educore-input-field" value="<?php echo esc_attr( (string) $min_att_pct ); ?>" placeholder="75.00">
                        </div>

                        <div>
                            <label class="ifs-educore-form-label" style="font-size:12px; color:#065f46;">
                                <?php esc_html_e( 'Min. Days Required', 'ifsedu-school-management' ); ?>
                            </label>
                            <div style="background: #ffffff; border: 1.5px solid #bbf7d0; border-radius: 8px; height: 44px; display: flex; align-items: center; padding: 0 14px; font-weight: 800; color: #0284c7; font-size: 15px;">
                                <span id="displayMinDaysRequired">0</span>&nbsp;<?php esc_html_e( 'Days', 'ifsedu-school-management' ); ?>
                            </div>
                        </div>

                        <div>
                            <label class="ifs-educore-form-label" style="font-size:12px; color:#065f46;">
                                <?php esc_html_e( 'Total Working Days', 'ifsedu-school-management' ); ?>
                            </label>
                            <div style="background: #ffffff; border: 1.5px solid #bbf7d0; border-radius: 8px; height: 44px; display: flex; align-items: center; padding: 0 14px; font-weight: 800; color: #047857; font-size: 15px;">
                                <span id="displayWorkingDaysCount">0</span>&nbsp;<?php esc_html_e( 'Days', 'ifsedu-school-management' ); ?>
                            </div>
                            <input type="hidden" name="total_working_days" id="total_working_days_hidden" value="<?php echo esc_attr( (string) $total_work_days ); ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Examination Dates, Fee Clearance & Status -->
            <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:16px;">
                <div class="ifs-educore-form-group">
                    <label class="ifs-educore-form-label" for="start_date">
                        <?php esc_html_e( 'Exam Start Date', 'ifsedu-school-management' ); ?> <span style="color:#ef4444;">*</span>
                    </label>
                    <input type="date" name="start_date" id="start_date" class="ifs-educore-input-field" value="<?php echo $is_edit ? esc_attr( (string) $edit_exam->start_date ) : esc_attr( current_time( 'Y-m-d' ) ); ?>" required>
                </div>

                <div class="ifs-educore-form-group">
                    <label class="ifs-educore-form-label" for="end_date">
                        <?php esc_html_e( 'Exam End Date', 'ifsedu-school-management' ); ?> <span style="color:#ef4444;">*</span>
                    </label>
                    <input type="date" name="end_date" id="end_date" class="ifs-educore-input-field" value="<?php echo $is_edit ? esc_attr( (string) $edit_exam->end_date ) : esc_attr( current_time( 'Y-m-d' ) ); ?>" required>
                </div>

                <!-- Fees Clearance Month Field -->
                <div class="ifs-educore-form-group">
                    <label class="ifs-educore-form-label" for="fee_clear_month">
                        <?php esc_html_e( 'Fees Clearance Month', 'ifsedu-school-management' ); ?>
                    </label>
                    <select name="fee_clear_month" id="fee_clear_month" class="ifs-educore-select-field">
                        <option value=""><?php esc_html_e( '-- No Clearance Rule --', 'ifsedu-school-management' ); ?></option>
                        <?php foreach ( $months_list as $m_key => $m_label ) : ?>
                            <option value="<?php echo esc_attr( $m_key ); ?>" <?php selected( $fee_clear_month, $m_key ); ?>>
                                <?php printf( esc_html__( 'Up to %s', 'ifsedu-school-management' ), esc_html( $m_label ) ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr; gap:16px; max-width: 300px;">
                <div class="ifs-educore-form-group">
                    <label class="ifs-educore-form-label" for="status">
                        <?php esc_html_e( 'Status', 'ifsedu-school-management' ); ?>
                    </label>
                    <select name="status" id="status" class="ifs-educore-select-field">
                        <option value="Upcoming" <?php selected( $is_edit ? (string) $edit_exam->status : '', 'Upcoming' ); ?>><?php esc_html_e( 'Upcoming', 'ifsedu-school-management' ); ?></option>
                        <option value="Ongoing" <?php selected( $is_edit ? (string) $edit_exam->status : '', 'Ongoing' ); ?>><?php esc_html_e( 'Ongoing', 'ifsedu-school-management' ); ?></option>
                        <option value="Completed" <?php selected( $is_edit ? (string) $edit_exam->status : '', 'Completed' ); ?>><?php esc_html_e( 'Completed', 'ifsedu-school-management' ); ?></option>
                    </select>
                </div>
            </div>

            <div style="margin-top:24px; text-align:right;">
                <button type="submit" class="ifs-educore-btn-save">
                    <span class="dashicons dashicons-saved"></span>
                    <?php echo $is_edit ? esc_html__( 'Update Exam Scheme', 'ifsedu-school-management' ) : esc_html__( 'Save Exam Scheme', 'ifsedu-school-management' ); ?>
                </button>
            </div>
        </form>
    </div>

    <!-- Hidden template for repeater row -->
    <script type="text/template" id="termRowTemplate">
        <div class="ifs-term-repeater-row">
            <select name="term_weights[][exam_id]" class="ifs-educore-select-field" style="flex:2;">
                <option value=""><?php esc_html_e( '-- Select Exam --', 'ifsedu-school-management' ); ?></option>
                <?php foreach ( $all_existing_exams as $ex_item ) : 
                    if ( $get_id && (int) $ex_item->id === (int) $get_id ) { continue; }
                ?>
                    <option value="<?php echo absint( $ex_item->id ); ?>"><?php echo esc_html( $ex_item->exam_name ); ?></option>
                <?php endforeach; ?>
            </select>
            <div style="flex:1; display:flex; align-items:center; gap:6px;">
                <input type="number" step="0.01" min="0" max="100" name="term_weights[][percentage]" class="ifs-educore-input-field term-weight-input" value="10" placeholder="10">
                <span style="font-weight:700; color:#475569;">%</span>
            </div>
            <button type="button" class="ifs-class-btn-toggle btn-remove-term-row" style="background:#fee2e2; color:#b91c1c; border-color:#fca5a5;">
                <span class="dashicons dashicons-trash" style="font-size:14px; width:14px; height:14px; vertical-align:middle;"></span>
            </button>
        </div>
    </script>

    <script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function() {
        var grid              = document.getElementById('classGridContainer');
        var toggleBtn         = document.getElementById('btnToggleAllClasses');
        var searchInput       = document.getElementById('classSearchInput');
        var countBadge        = document.getElementById('selectedClassCountBadge');
        var form              = document.getElementById('educoreExamForm');
        var includeAttCheckbox = document.getElementById('includeAttendanceCheckbox');
        var attSettingsCard     = document.getElementById('attendanceSettingsCard');
        var attFieldsWrapper    = document.getElementById('attendanceFieldsWrapper');
        
        // Term contribution elements
        var enableTermCheckbox  = document.getElementById('enableTermContribCheckbox');
        var termContribCard     = document.getElementById('termContribCard');
        var termFieldsWrapper   = document.getElementById('termContribFieldsWrapper');
        var termRepeaterContainer = document.getElementById('termRepeaterContainer');
        var btnAddTermRow       = document.getElementById('btnAddTermRow');
        var termRowTemplate     = document.getElementById('termRowTemplate');
        var displayCurrentWeight = document.getElementById('displayCurrentExamWeight');

        function calculateSubtractedExamWeight() {
            if (!displayCurrentWeight) return;
            var repeaterSum = 0;

            if (enableTermCheckbox && enableTermCheckbox.checked) {
                document.querySelectorAll('.term-weight-input').forEach(function(inp) {
                    repeaterSum += parseFloat(inp.value) || 0;
                });
            }

            var currentExamWeight = 100 - repeaterSum;
            displayCurrentWeight.textContent = currentExamWeight.toFixed(2);
            if (currentExamWeight >= 0 && currentExamWeight <= 100) {
                displayCurrentWeight.style.color = '#047857';
            } else {
                displayCurrentWeight.style.color = '#dc2626';
            }
        }

        document.addEventListener('input', function(e) {
            if (e.target.classList.contains('term-weight-input')) {
                calculateSubtractedExamWeight();
            }
        });

        if (enableTermCheckbox) {
            enableTermCheckbox.addEventListener('change', function() {
                if (this.checked) {
                    termContribCard.classList.remove('is-disabled');
                    termFieldsWrapper.style.display = 'block';
                } else {
                    termContribCard.classList.add('is-disabled');
                    termFieldsWrapper.style.display = 'none';
                }
                calculateSubtractedExamWeight();
            });
        }

        if (btnAddTermRow && termRepeaterContainer && termRowTemplate) {
            btnAddTermRow.addEventListener('click', function() {
                var tempDiv = document.createElement('div');
                tempDiv.innerHTML = termRowTemplate.innerHTML.trim();
                var newRow = tempDiv.firstChild;
                termRepeaterContainer.appendChild(newRow);
                calculateSubtractedExamWeight();
            });
        }

        document.addEventListener('click', function(e) {
            if (e.target.closest('.btn-remove-term-row')) {
                var row = e.target.closest('.ifs-term-repeater-row');
                if (row) {
                    row.remove();
                    calculateSubtractedExamWeight();
                }
            }
        });

        calculateSubtractedExamWeight();
        
        var attStartInput       = document.getElementById('att_start_date');
        var attEndInput         = document.getElementById('att_end_date');
        var displayDaysSpan     = document.getElementById('displayWorkingDaysCount');
        var hiddenDaysInput     = document.getElementById('total_working_days_hidden');
        var minPctInput         = document.getElementById('min_attendance_pct_input');
        var displayMinDaysSpan  = document.getElementById('displayMinDaysRequired');

        function calculateLiveWorkingDays() {
            if (!attStartInput || !attEndInput || !displayDaysSpan) return;
            var startVal = attStartInput.value;
            var endVal   = attEndInput.value;
            if (!startVal || !endVal) return;

            if (startVal.indexOf('/') !== -1) {
                var p1   = startVal.split('/');
                startVal = p1[2] + '-' + p1[0] + '-' + p1[1];
            }
            if (endVal.indexOf('/') !== -1) {
                var p2 = endVal.split('/');
                endVal = p2[2] + '-' + p2[0] + '-' + p2[1];
            }

            var d1 = new Date(startVal);
            var d2 = new Date(endVal);
            if (isNaN(d1) || isNaN(d2) || d1 > d2) return;

            var count = 0;
            var cur   = new Date(d1.getTime());
            while (cur <= d2) {
                if (cur.getDay() !== 5) { // Exclude Friday
                    count++;
                }
                cur.setDate(cur.getDate() + 1);
            }

            displayDaysSpan.textContent = count;
            if (hiddenDaysInput) {
                hiddenDaysInput.value = count;
            }

            if (minPctInput && displayMinDaysSpan) {
                var pct          = parseFloat(minPctInput.value) || 0;
                var requiredDays = Math.ceil((count * pct) / 100);
                displayMinDaysSpan.textContent = requiredDays;
            }
        }

        if (attStartInput && attEndInput) {
            attStartInput.addEventListener('change', calculateLiveWorkingDays);
            attEndInput.addEventListener('change', calculateLiveWorkingDays);
            calculateLiveWorkingDays();
        }

        if (minPctInput) {
            minPctInput.addEventListener('input', calculateLiveWorkingDays);
        }

        if (includeAttCheckbox) {
            includeAttCheckbox.addEventListener('change', function() {
                if (this.checked) {
                    attSettingsCard.classList.remove('is-disabled');
                    attFieldsWrapper.style.display = 'block';
                } else {
                    attSettingsCard.classList.add('is-disabled');
                    attFieldsWrapper.style.display = 'none';
                }
            });
        }

        function syncClassSubjectBoxes() {
            var checkedClasses = [];
            grid.querySelectorAll('.cb-class:checked').forEach(function(cb) {
                checkedClasses.push(cb.value);
            });

            document.querySelectorAll('.ifs-class-subject-box').forEach(function(box) {
                var cName = box.getAttribute('data-class-box');
                if (checkedClasses.indexOf(cName) !== -1) {
                    box.style.display = 'block';
                } else {
                    box.style.display = 'none';
                }
            });
        }

        function updateSelectionState() {
            var allCheckboxes     = grid.querySelectorAll('.cb-class');
            var checkedCheckboxes = grid.querySelectorAll('.cb-class:checked');
            var count             = checkedCheckboxes.length;

            countBadge.textContent = count + ' Selected';
            if (count > 0) {
                countBadge.classList.add('has-selected');
            } else {
                countBadge.classList.remove('has-selected');
            }

            allCheckboxes.forEach(function(cb) {
                var card = cb.closest('.ifs-class-card');
                if (card) {
                    if (cb.checked) {
                        card.classList.add('is-active');
                    } else {
                        card.classList.remove('is-active');
                    }
                }
            });

            if (toggleBtn) {
                var visibleCheckboxes = grid.querySelectorAll('.ifs-class-card:not([style*="display: none"]) .cb-class');
                var visibleChecked    = grid.querySelectorAll('.ifs-class-card:not([style*="display: none"]) .cb-class:checked');
                var allVisibleChecked = visibleCheckboxes.length > 0 && visibleCheckboxes.length === visibleChecked.length;
                toggleBtn.textContent = allVisibleChecked ? '<?php echo esc_js( __( 'Deselect All', 'ifsedu-school-management' ) ); ?>' : '<?php echo esc_js( __( 'Select All', 'ifsedu-school-management' ) ); ?>';
            }

            syncClassSubjectBoxes();
        }

        if (searchInput && grid) {
            searchInput.addEventListener('input', function() {
                var q     = this.value.toLowerCase().trim();
                var cards = grid.querySelectorAll('.ifs-class-card');
                cards.forEach(function(card) {
                    var text = card.getAttribute('data-class-text') || '';
                    if (!q || text.indexOf(q) !== -1) {
                        card.style.display = 'flex';
                    } else {
                        card.style.display = 'none';
                    }
                });
                updateSelectionState();
            });
        }

        if (grid) {
            grid.addEventListener('change', function(e) {
                if (e.target.classList.contains('cb-class')) {
                    updateSelectionState();
                }
            });
        }

        if (toggleBtn && grid) {
            toggleBtn.addEventListener('click', function(e) {
                e.preventDefault();
                var visibleCheckboxes = grid.querySelectorAll('.ifs-class-card:not([style*="display: none"]) .cb-class');
                var visibleChecked    = grid.querySelectorAll('.ifs-class-card:not([style*="display: none"]) .cb-class:checked');
                var shouldCheckAll    = visibleCheckboxes.length !== visibleChecked.length;

                visibleCheckboxes.forEach(function(cb) {
                    cb.checked = shouldCheckAll;
                });
                updateSelectionState();
            });
        }

        document.querySelectorAll('.cb-sub-choice').forEach(function(cb) {
            cb.addEventListener('change', function() {
                var chip = this.closest('.ifs-subject-chip');
                if (chip) {
                    if (this.checked) {
                        chip.classList.add('is-active');
                    } else {
                        chip.classList.remove('is-active');
                    }
                }
            });
        });

        document.querySelectorAll('.btn-toggle-class-subjects').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var cTarget = this.getAttribute('data-class-target');
                var box     = document.querySelector('.ifs-class-subject-box[data-class-box="' + cTarget + '"]');
                if (!box) return;

                var subCbs     = box.querySelectorAll('.cb-sub-choice');
                var allChecked = true;
                subCbs.forEach(function(c) { if (!c.checked) allChecked = false; });

                subCbs.forEach(function(c) {
                    c.checked = !allChecked;
                    var chip  = c.closest('.ifs-subject-chip');
                    if (chip) {
                        if (!allChecked) chip.classList.add('is-active');
                        else chip.classList.remove('is-active');
                    }
                });

                this.textContent = allChecked ? '<?php echo esc_js( __( 'Select All Subjects', 'ifsedu-school-management' ) ); ?>' : '<?php echo esc_js( __( 'Deselect All Subjects', 'ifsedu-school-management' ) ); ?>';
            });
        });

        if (form) {
            form.addEventListener('submit', function(e) {
                var checkedClasses = form.querySelectorAll('input[name="class_name[]"]:checked');
                if (checkedClasses.length === 0) {
                    e.preventDefault();
                    alert('<?php echo esc_js( __( 'Please select at least one class.', 'ifsedu-school-management' ) ); ?>');
                }
            });
        }

        updateSelectionState();
    });
    </script>
    <?php
}
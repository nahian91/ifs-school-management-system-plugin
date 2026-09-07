<?php
/**
 * Enterprise Bangladesh NCTB Standard Question Paper Generator & Bank Engine
 * File: inc/exams/exam-questions.php
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// --------------------------------------------------------------------------
// 1. AJAX HANDLERS
// --------------------------------------------------------------------------

// Handler A: Auto-load subjects and marks setup for chosen Class
add_action( 'wp_ajax_ifs_educore_get_subjects_for_qp', 'ifs_educore_get_subjects_for_qp_handler' );
function ifs_educore_get_subjects_for_qp_handler() {
    check_ajax_referer( 'ifs_educore_qp_ajax_nonce', 'security' );
    global $wpdb;

    if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => esc_html__( 'Permission denied.', 'ifsedu-school-management' ) ) );
    }

    $table_subjects = $wpdb->prefix . 'sms_subjects';
    $table_units    = $wpdb->prefix . 'sms_academic_units';
    $class_name     = isset( $_POST['class_name'] ) ? sanitize_text_field( wp_unslash( $_POST['class_name'] ) ) : '';

    if ( empty( $class_name ) ) {
        wp_send_json_success( array() );
    }

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $subjects = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT DISTINCT s.id, s.subject_name, s.subject_code, s.total_marks, s.cq_marks, s.mcq_marks 
             FROM `{$table_subjects}` s 
             INNER JOIN `{$table_units}` u ON s.class_id = u.id 
             WHERE u.class_name = %s 
             ORDER BY s.subject_order ASC, s.subject_name ASC",
            $class_name
        )
    );
    // phpcs:enable

    wp_send_json_success( is_array( $subjects ) ? $subjects : array() );
}

// Handler B: Load full question data for editing/re-printing
add_action( 'wp_ajax_ifs_educore_load_saved_question_paper', 'ifs_educore_load_saved_question_paper_handler' );
function ifs_educore_load_saved_question_paper_handler() {
    check_ajax_referer( 'ifs_educore_qp_ajax_nonce', 'security' );
    global $wpdb;

    if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => esc_html__( 'Permission denied.', 'ifsedu-school-management' ) ) );
    }

    $table_questions = $wpdb->prefix . 'sms_exam_questions';
    $paper_id        = isset( $_POST['paper_id'] ) ? absint( $_POST['paper_id'] ) : 0;

    if ( $paper_id <= 0 ) {
        wp_send_json_error( array( 'message' => esc_html__( 'Invalid question paper reference.', 'ifsedu-school-management' ) ) );
    }

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $paper = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table_questions}` WHERE id = %d LIMIT 1", $paper_id ) );
    // phpcs:enable

    if ( $paper ) {
        $paper->cq_data  = json_decode( (string) $paper->cq_data, true );
        $paper->mcq_data = json_decode( (string) $paper->mcq_data, true );
        wp_send_json_success( $paper );
    }

    wp_send_json_error( array( 'message' => esc_html__( 'Question paper not found.', 'ifsedu-school-management' ) ) );
}

// --------------------------------------------------------------------------
// 2. MAIN VIEW ENGINE
// --------------------------------------------------------------------------
function educore_exam_questions_view() {
    global $wpdb;

    if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Permission denied.', 'ifsedu-school-management' ) );
    }

    $table_questions = $wpdb->prefix . 'sms_exam_questions';
    $table_exams     = $wpdb->prefix . 'sms_exams';
    $table_units     = $wpdb->prefix . 'sms_academic_units';

    // Auto-create questions table if not exists
    $table_check = $wpdb->get_var( "SHOW TABLES LIKE '{$table_questions}'" );
    if ( empty( $table_check ) ) {
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$table_questions} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            exam_id bigint(20) unsigned NOT NULL,
            class_name varchar(100) NOT NULL,
            question_type varchar(20) DEFAULT 'CQ' NOT NULL,
            subject_name varchar(255) NOT NULL,
            subject_code varchar(50) DEFAULT '' NOT NULL,
            exam_duration varchar(100) NOT NULL,
            total_marks decimal(6,2) DEFAULT 70.00 NOT NULL,
            instructions text NOT NULL,
            cq_data longtext NOT NULL,
            mcq_data longtext NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            KEY exam_id_idx (exam_id),
            KEY class_name_idx (class_name)
        ) {$charset_collate};";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    // Dynamic Institutional Identity Settings
    $school_name    = get_option( 'educore_school_name', get_bloginfo( 'name' ) );
    $school_tagline = get_option( 'educore_school_tagline', '' );
    $school_logo    = get_option( 'educore_school_logo', '' );

    if ( empty( $school_name ) || 'WordPress' === $school_name ) {
        $school_name = get_bloginfo( 'name' );
    }

    $notice_msg = '';

    if ( function_exists( 'wp_enqueue_media' ) ) {
        wp_enqueue_media();
    }

    // Handle Delete Action
    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    if ( isset( $_GET['action'] ) && 'delete_paper' === $_GET['action'] && isset( $_GET['id'] ) ) {
        $del_id = absint( $_GET['id'] );
        check_admin_referer( 'delete_question_paper_' . $del_id );
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $wpdb->delete( $table_questions, array( 'id' => $del_id ), array( '%d' ) );
        // phpcs:enable
        wp_safe_redirect( add_query_arg( array( 'page' => 'school_management_system', 'tab' => 'exams', 'sub' => 'questions', 'msg' => 'deleted' ), admin_url( 'admin.php' ) ) );
        exit;
    }

    if ( isset( $_GET['msg'] ) && 'deleted' === $_GET['msg'] ) {
        $notice_msg = __( 'Question paper successfully removed.', 'ifsedu-school-management' );
    }
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    // Handle Form Submission (Save & Update)
    $request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
    if ( 'POST' === $request_method && isset( $_POST['educore_save_question_paper'] ) ) {
        if ( isset( $_POST['ifs_educore_question_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ifs_educore_question_nonce'] ) ), 'save_question_paper_action' ) ) {
            
            $paper_id      = isset( $_POST['paper_id'] ) ? absint( $_POST['paper_id'] ) : 0;
            $exam_id       = isset( $_POST['exam_id'] ) ? absint( $_POST['exam_id'] ) : 0;
            $class_name    = isset( $_POST['class_name'] ) ? sanitize_text_field( wp_unslash( $_POST['class_name'] ) ) : '';
            $question_type = isset( $_POST['question_type'] ) ? sanitize_text_field( wp_unslash( $_POST['question_type'] ) ) : 'CQ';
            $subject_name  = isset( $_POST['subject_name'] ) ? sanitize_text_field( wp_unslash( $_POST['subject_name'] ) ) : '';
            $subject_code  = isset( $_POST['subject_code'] ) ? sanitize_text_field( wp_unslash( $_POST['subject_code'] ) ) : '';
            $exam_duration = isset( $_POST['exam_duration'] ) ? sanitize_text_field( wp_unslash( $_POST['exam_duration'] ) ) : '২ ঘণ্টা ৩০ মিনিট';
            $total_marks   = isset( $_POST['total_marks'] ) ? floatval( wp_unslash( $_POST['total_marks'] ) ) : 70.00;
            $instructions  = isset( $_POST['instructions'] ) ? sanitize_textarea_field( wp_unslash( $_POST['instructions'] ) ) : '';

            // Process CQ Payload
            $cq_sections = ( isset( $_POST['cq_section'] ) && is_array( $_POST['cq_section'] ) ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['cq_section'] ) ) : array();
            $cq_stems    = ( isset( $_POST['cq_stem'] ) && is_array( $_POST['cq_stem'] ) ) ? array_map( 'sanitize_textarea_field', wp_unslash( $_POST['cq_stem'] ) ) : array();
            $cq_images   = ( isset( $_POST['cq_image_url'] ) && is_array( $_POST['cq_image_url'] ) ) ? array_map( 'esc_url_raw', wp_unslash( $_POST['cq_image_url'] ) ) : array();
            $cq_a        = ( isset( $_POST['cq_a'] ) && is_array( $_POST['cq_a'] ) ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['cq_a'] ) ) : array();
            $cq_b        = ( isset( $_POST['cq_b'] ) && is_array( $_POST['cq_b'] ) ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['cq_b'] ) ) : array();
            $cq_c        = ( isset( $_POST['cq_c'] ) && is_array( $_POST['cq_c'] ) ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['cq_c'] ) ) : array();
            $cq_d        = ( isset( $_POST['cq_d'] ) && is_array( $_POST['cq_d'] ) ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['cq_d'] ) ) : array();
            $cq_mark_a   = ( isset( $_POST['cq_mark_a'] ) && is_array( $_POST['cq_mark_a'] ) ) ? array_map( 'floatval', wp_unslash( $_POST['cq_mark_a'] ) ) : array();
            $cq_mark_b   = ( isset( $_POST['cq_mark_b'] ) && is_array( $_POST['cq_mark_b'] ) ) ? array_map( 'floatval', wp_unslash( $_POST['cq_mark_b'] ) ) : array();
            $cq_mark_c   = ( isset( $_POST['cq_mark_c'] ) && is_array( $_POST['cq_mark_c'] ) ) ? array_map( 'floatval', wp_unslash( $_POST['cq_mark_c'] ) ) : array();
            $cq_mark_d   = ( isset( $_POST['cq_mark_d'] ) && is_array( $_POST['cq_mark_d'] ) ) ? array_map( 'floatval', wp_unslash( $_POST['cq_mark_d'] ) ) : array();

            $cq_array = array();
            if ( 'CQ' === $question_type ) {
                foreach ( $cq_stems as $i => $stem ) {
                    $img_url = isset( $cq_images[ $i ] ) ? $cq_images[ $i ] : '';
                    if ( ! empty( trim( (string) $stem ) ) || ! empty( trim( (string) ( $cq_a[ $i ] ?? '' ) ) ) || ! empty( $img_url ) ) {
                        $cq_array[] = array(
                            'section' => isset( $cq_sections[ $i ] ) ? $cq_sections[ $i ] : '',
                            'stem'    => $stem,
                            'image'   => $img_url,
                            'a'       => isset( $cq_a[ $i ] ) ? $cq_a[ $i ] : '',
                            'b'       => isset( $cq_b[ $i ] ) ? $cq_b[ $i ] : '',
                            'c'       => isset( $cq_c[ $i ] ) ? $cq_c[ $i ] : '',
                            'd'       => isset( $cq_d[ $i ] ) ? $cq_d[ $i ] : '',
                            'mark_a'  => isset( $cq_mark_a[ $i ] ) ? $cq_mark_a[ $i ] : 1,
                            'mark_b'  => isset( $cq_mark_b[ $i ] ) ? $cq_mark_b[ $i ] : 2,
                            'mark_c'  => isset( $cq_mark_c[ $i ] ) ? $cq_mark_c[ $i ] : 3,
                            'mark_d'  => isset( $cq_mark_d[ $i ] ) ? $cq_mark_d[ $i ] : 4,
                        );
                    }
                }
            }

            // Process MCQ Payload
            $mcq_q     = ( isset( $_POST['mcq_question'] ) && is_array( $_POST['mcq_question'] ) ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['mcq_question'] ) ) : array();
            $mcq_cols  = ( isset( $_POST['mcq_columns'] ) && is_array( $_POST['mcq_columns'] ) ) ? array_map( 'absint', wp_unslash( $_POST['mcq_columns'] ) ) : array();
            $mcq_marks = ( isset( $_POST['mcq_mark'] ) && is_array( $_POST['mcq_mark'] ) ) ? array_map( 'floatval', wp_unslash( $_POST['mcq_mark'] ) ) : array();
            $mcq_ans   = ( isset( $_POST['mcq_answer'] ) && is_array( $_POST['mcq_answer'] ) ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['mcq_answer'] ) ) : array();
            $mcq_op1   = ( isset( $_POST['mcq_opt_1'] ) && is_array( $_POST['mcq_opt_1'] ) ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['mcq_opt_1'] ) ) : array();
            $mcq_op2   = ( isset( $_POST['mcq_opt_2'] ) && is_array( $_POST['mcq_opt_2'] ) ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['mcq_opt_2'] ) ) : array();
            $mcq_op3   = ( isset( $_POST['mcq_opt_3'] ) && is_array( $_POST['mcq_opt_3'] ) ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['mcq_opt_3'] ) ) : array();
            $mcq_op4   = ( isset( $_POST['mcq_opt_4'] ) && is_array( $_POST['mcq_opt_4'] ) ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['mcq_opt_4'] ) ) : array();

            $mcq_array = array();
            if ( 'MCQ' === $question_type ) {
                foreach ( $mcq_q as $i => $q_text ) {
                    if ( ! empty( trim( (string) $q_text ) ) ) {
                        $col_val = isset( $mcq_cols[ $i ] ) ? absint( $mcq_cols[ $i ] ) : 2;
                        if ( ! in_array( $col_val, array( 1, 2, 4 ), true ) ) {
                            $col_val = 2;
                        }

                        $mcq_array[] = array(
                            'q'       => $q_text,
                            'columns' => $col_val,
                            'mark'    => isset( $mcq_marks[ $i ] ) ? $mcq_marks[ $i ] : 1,
                            'ans'     => isset( $mcq_ans[ $i ] ) ? $mcq_ans[ $i ] : 'opt1',
                            'opt1'    => isset( $mcq_op1[ $i ] ) ? $mcq_op1[ $i ] : '',
                            'opt2'    => isset( $mcq_op2[ $i ] ) ? $mcq_op2[ $i ] : '',
                            'opt3'    => isset( $mcq_op3[ $i ] ) ? $mcq_op3[ $i ] : '',
                            'opt4'    => isset( $mcq_op4[ $i ] ) ? $mcq_op4[ $i ] : '',
                        );
                    }
                }
            }

            $data = array(
                'exam_id'       => $exam_id,
                'class_name'    => $class_name,
                'question_type' => $question_type,
                'subject_name'  => $subject_name,
                'subject_code'  => $subject_code,
                'exam_duration' => $exam_duration,
                'total_marks'   => $total_marks,
                'instructions'  => $instructions,
                'cq_data'       => wp_json_encode( $cq_array ),
                'mcq_data'      => wp_json_encode( $mcq_array ),
            );

            $formats = array( '%d', '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%s' );

            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
            if ( $paper_id > 0 ) {
                $wpdb->update( $table_questions, $data, array( 'id' => $paper_id ), $formats, array( '%d' ) );
                $notice_msg = esc_html__( 'Question paper updated successfully.', 'ifsedu-school-management' );
            } else {
                $wpdb->insert( $table_questions, $data, $formats );
                $notice_msg = esc_html__( 'New question paper generated and archived.', 'ifsedu-school-management' );
            }
            // phpcs:enable
        }
    }

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $exams = $wpdb->get_results( "SELECT id, exam_name FROM `{$table_exams}` ORDER BY id DESC" );

    $raw_classes_data = $wpdb->get_results( 
        "SELECT class_name, MIN(sort_order) as min_sort 
         FROM `{$table_units}` 
         WHERE class_name IS NOT NULL AND class_name != '' 
         GROUP BY class_name 
         ORDER BY min_sort ASC, CAST(class_name AS UNSIGNED) ASC, class_name ASC" 
    );
    $classes = array();
    if ( ! empty( $raw_classes_data ) ) {
        foreach ( $raw_classes_data as $cr ) {
            $classes[] = $cr->class_name;
        }
    }

    $saved_papers = $wpdb->get_results(
        "SELECT q.id, q.class_name, q.question_type, q.subject_name, q.subject_code, q.total_marks, q.created_at, e.exam_name 
         FROM `{$table_questions}` q 
         LEFT JOIN `{$table_exams}` e ON q.exam_id = e.id 
         ORDER BY q.id DESC"
    );
    // phpcs:enable
    ?>

    <style>
        .ifs-educore-qp-root {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            margin: 10px 0 40px 0;
        }

        /* Repository Bento */
        .ifs-qp-repo-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 20px 24px;
            margin-bottom: 22px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.02);
        }

        /* Split Builder Workspace Layout */
        .ifs-educore-qp-split-layout {
            display: grid;
            grid-template-columns: 480px 1fr;
            gap: 22px;
            align-items: flex-start;
        }
        @media (max-width: 1200px) {
            .ifs-educore-qp-split-layout {
                grid-template-columns: 1fr;
            }
        }

        .ifs-qp-builder-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 24px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.02);
        }

        /* Segmented Controller */
        .ifs-segmented-ctrl {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px;
            background: #f1f5f9;
            padding: 4px;
            border-radius: 10px;
            margin-bottom: 18px;
        }
        .ifs-segmented-opt {
            padding: 8px 12px;
            text-align: center;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 800;
            color: #475569;
            cursor: pointer;
            transition: all 0.2s ease;
            user-select: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        .ifs-segmented-opt.is-active {
            background: #00523c;
            color: #ffffff;
            box-shadow: 0 2px 6px rgba(0, 82, 60, 0.2);
        }

        /* Question Item Repeater Blocks */
        .ifs-educore-q-card {
            background: #f8fafc;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            padding: 14px;
            margin-bottom: 14px;
            position: relative;
            transition: border-color 0.2s;
        }
        .ifs-educore-q-card:hover {
            border-color: #cbd5e1;
            background: #ffffff;
        }
        .ifs-educore-q-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }

        /* Sub-question Flex Grid */
        .ifs-sub-q-builder-grid {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-top: 10px;
        }
        .ifs-sub-q-row-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* NCTB A4 Paper Standard Preview Canvas */
        .ifs-educore-preview-sticky-wrapper {
            position: sticky;
            top: 35px;
        }
        .ifs-educore-preview-topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            padding: 12px 18px;
            border-radius: 10px;
            margin-bottom: 14px;
        }

        .nctb-qp-paper {
            width: 100%;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            padding: 30px 36px;
            box-sizing: border-box;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.06);
            color: #000000;
            font-family: 'Kalpurush', 'SolaimanLipi', 'SutonnyMJ', 'Nikosh', 'Arial', sans-serif;
            line-height: 1.55;
        }
        .nctb-board-header {
            text-align: center;
            border-bottom: 2px solid #000000;
            padding-bottom: 12px;
            margin-bottom: 14px;
        }
        .nctb-brand-row {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .nctb-logo-img {
            max-height: 38px;
            object-fit: contain;
        }
        .nctb-school-title {
            margin: 0;
            font-size: 22px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: -0.2px;
            color: #000;
        }
        .nctb-exam-title {
            margin: 2px 0;
            font-size: 16px;
            font-weight: 800;
        }
        .nctb-subject-title {
            margin: 2px 0;
            font-size: 15px;
            font-weight: 800;
        }
        .nctb-meta-line {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 13.5px;
            font-weight: 800;
            margin-top: 8px;
            border-top: 1px dashed #000;
            padding-top: 6px;
        }
        .nctb-instructions-box {
            font-size: 12.5px;
            font-weight: 700;
            font-style: italic;
            margin-bottom: 16px;
            padding-bottom: 6px;
            border-bottom: 1px solid #000;
        }

        /* CQ Item Preview Layout */
        .nctb-cq-item {
            margin-bottom: 20px;
            page-break-inside: avoid;
        }
        .nctb-cq-stem-wrapper {
            display: flex;
            gap: 8px;
            font-size: 14px;
            text-align: justify;
            margin-bottom: 8px;
        }
        .nctb-section-header {
            text-align: center;
            font-weight: 900;
            font-size: 14px;
            text-decoration: underline;
            margin: 16px 0 10px 0;
        }
        .nctb-cq-figure {
            text-align: center;
            margin: 10px 0;
        }
        .nctb-cq-figure img {
            max-height: 140px;
            border: 1px solid #000;
            padding: 4px;
        }
        .nctb-sub-questions-list {
            padding-left: 22px;
        }
        .nctb-sub-q-row {
            display: flex;
            justify-content: space-between;
            font-size: 13.5px;
            margin-bottom: 3px;
        }
        .nctb-sub-q-text {
            font-weight: 700;
        }
        .nctb-sub-q-mark {
            font-weight: 800;
        }

        /* MCQ Grid Layouts */
        .bd-mcq-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            column-gap: 24px;
            row-gap: 14px;
        }
        .bd-mcq-grid.layout-single-col {
            grid-template-columns: 1fr;
        }
        .bd-mcq-item {
            page-break-inside: avoid;
            font-size: 13px;
        }
        .bd-mcq-options {
            display: grid;
            margin-top: 4px;
            padding-left: 14px;
            font-size: 12.5px;
        }
        .bd-mcq-options.cols-1 { grid-template-columns: 1fr; }
        .bd-mcq-options.cols-2 { grid-template-columns: 1fr 1fr; column-gap: 8px; }
        .bd-mcq-options.cols-4 { grid-template-columns: repeat(4, 1fr); column-gap: 6px; }

        .bd-ans-key-tag {
            display: inline-block;
            background: #f0fdf4;
            color: #047857;
            border: 1px solid #a7f3d0;
            padding: 1px 6px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 800;
        }

        /* Print Override */
        @media print {
            #adminmenuwrap, #adminmenuback, #wpadminbar, #wpfooter, .no-print {
                display: none !important;
            }
            body, .ifs-educore-qp-root, .ifs-educore-qp-split-layout {
                background: transparent !important;
                padding: 0 !important;
                margin: 0 !important;
                display: block !important;
            }
            .ifs-educore-preview-sticky-wrapper {
                position: static !important;
            }
            .nctb-qp-paper {
                box-shadow: none !important;
                border: none !important;
                padding: 0 !important;
                width: 100% !important;
            }
            .print-hide-ans {
                display: none !important;
            }
        }
    </style>

    <div class="ifs-educore-qp-root">

        <?php if ( ! empty( $notice_msg ) ) : ?>
            <div class="no-print" style="background:#ecfdf5; color:#065f46; border-left:4px solid #00523c; padding:12px 16px; border-radius:8px; font-weight:700; margin-bottom:18px;">
                <span class="dashicons dashicons-yes-alt" style="vertical-align:middle;"></span> <?php echo esc_html( $notice_msg ); ?>
            </div>
        <?php endif; ?>

        <!-- SAVED QUESTION REPOSITORY -->
        <div class="ifs-qp-repo-card no-print">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; flex-wrap:wrap; gap:10px;">
                <h4 style="margin:0; font-size:15.5px; font-weight:800; color:#0f172a; display:flex; align-items:center; gap:6px;">
                    <span class="dashicons dashicons-database" style="color:#00523c;"></span>
                    <?php esc_html_e( 'Institutional Question Paper Bank', 'ifsedu-school-management' ); ?>
                </h4>
                <span style="font-size:12px; font-weight:800; background:#f1f5f9; color:#475569; padding:4px 12px; border-radius:999px;">
                    <?php echo count( $saved_papers ); ?> <?php esc_html_e( 'Question Papers Stored', 'ifsedu-school-management' ); ?>
                </span>
            </div>

            <div style="display:grid; grid-template-columns: 2fr 1fr 1fr; gap:12px; margin-bottom:14px;">
                <input type="text" id="filter_keyword" class="ifs-educore-input" placeholder="<?php esc_attr_e( 'Search paper by subject or exam title...', 'ifsedu-school-management' ); ?>" style="height:36px; font-size:13px;">
                <select id="filter_class_search" class="ifs-educore-select" style="height:36px; font-size:13px;">
                    <option value=""><?php esc_html_e( '-- All Classes --', 'ifsedu-school-management' ); ?></option>
                    <?php foreach ( $classes as $c ) : ?>
                        <option value="<?php echo esc_attr( $c ); ?>"><?php echo esc_html( $c ); ?></option>
                    <?php endforeach; ?>
                </select>
                <select id="filter_type_search" class="ifs-educore-select" style="height:36px; font-size:13px;">
                    <option value=""><?php esc_html_e( '-- All Formats --', 'ifsedu-school-management' ); ?></option>
                    <option value="CQ"><?php esc_html_e( 'Creative (CQ)', 'ifsedu-school-management' ); ?></option>
                    <option value="MCQ"><?php esc_html_e( 'Multiple Choice (MCQ)', 'ifsedu-school-management' ); ?></option>
                </select>
            </div>

            <div style="max-height: 190px; overflow-y:auto; border:1.5px solid #e2e8f0; border-radius:10px;">
                <table class="ifs-educore-table" id="savedPapersTable" style="width:100%; border-collapse:collapse; font-size:13px;">
                    <thead>
                        <tr style="background:#f8fafc; border-bottom:1px solid #e2e8f0;">
                            <th style="padding:8px 12px;"><?php esc_html_e( 'Exam Scheme', 'ifsedu-school-management' ); ?></th>
                            <th style="padding:8px 12px;"><?php esc_html_e( 'Class', 'ifsedu-school-management' ); ?></th>
                            <th style="padding:8px 12px;"><?php esc_html_e( 'Format', 'ifsedu-school-management' ); ?></th>
                            <th style="padding:8px 12px;"><?php esc_html_e( 'Subject', 'ifsedu-school-management' ); ?></th>
                            <th style="padding:8px 12px;"><?php esc_html_e( 'Marks', 'ifsedu-school-management' ); ?></th>
                            <th style="padding:8px 12px; text-align:right;"><?php esc_html_e( 'Action', 'ifsedu-school-management' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( ! empty( $saved_papers ) ) : foreach ( $saved_papers as $sp ) : 
                            $sp_id = absint( $sp->id );
                        ?>
                            <tr data-class="<?php echo esc_attr( $sp->class_name ); ?>" data-type="<?php echo esc_attr( $sp->question_type ); ?>" data-text="<?php echo esc_attr( strtolower( (string) ( $sp->subject_name . ' ' . $sp->exam_name ) ) ); ?>" style="border-bottom:1px solid #f1f5f9;">
                                <td style="padding:8px 12px;"><strong><?php echo esc_html( $sp->exam_name ); ?></strong></td>
                                <td style="padding:8px 12px;"><?php echo esc_html( $sp->class_name ); ?></td>
                                <td style="padding:8px 12px;"><span style="background:<?php echo 'CQ' === $sp->question_type ? '#eff6ff' : '#f0fdf4'; ?>; color:<?php echo 'CQ' === $sp->question_type ? '#1d4ed8' : '#047857'; ?>; font-weight:800; font-size:11px; padding:2px 6px; border-radius:4px;"><?php echo esc_html( $sp->question_type ); ?></span></td>
                                <td style="padding:8px 12px;"><?php echo esc_html( $sp->subject_name . ( $sp->subject_code ? ' (' . $sp->subject_code . ')' : '' ) ); ?></td>
                                <td style="padding:8px 12px; font-weight:700;"><?php echo esc_html( floatval( $sp->total_marks ) ); ?></td>
                                <td style="padding:8px 12px; text-align:right;">
                                    <button type="button" class="btn-load-paper" data-id="<?php echo esc_attr( $sp_id ); ?>" style="background:#00523c; color:#fff; border:none; padding:4px 10px; border-radius:5px; font-size:12px; font-weight:700; cursor:pointer;">
                                        <span class="dashicons dashicons-edit" style="font-size:12px; width:12px; height:12px;"></span> <?php esc_html_e( 'Load', 'ifsedu-school-management' ); ?>
                                    </button>
                                    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=school_management_system&tab=exams&sub=questions&action=delete_paper&id=' . $sp_id ), 'delete_question_paper_' . $sp_id ) ); ?>" 
                                       style="color:#dc2626; text-decoration:none; padding:3px 6px; margin-left:4px;"
                                       onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete this question paper?', 'ifsedu-school-management' ) ); ?>');">
                                        <span class="dashicons dashicons-trash" style="font-size:14px; width:14px; height:14px;"></span>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; else : ?>
                            <tr><td colspan="6" style="text-align:center; color:#94a3b8; padding:20px;"><?php esc_html_e( 'No saved question papers in the repository.', 'ifsedu-school-management' ); ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="ifs-educore-qp-split-layout">
            
            <!-- LEFT PANEL: QUESTION BUILDER FORM -->
            <div class="ifs-qp-builder-card no-print">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; border-bottom:1px solid #f1f5f9; padding-bottom:12px;">
                    <h3 style="margin:0; color:#0f172a; font-size:16px; font-weight:800; display:flex; align-items:center; gap:6px;">
                        <span class="dashicons dashicons-welcome-write-blog" style="color:#00523c;"></span>
                        <?php esc_html_e( 'Question Paper Designer', 'ifsedu-school-management' ); ?>
                    </h3>
                    <button type="button" id="btnResetForm" style="background:#f1f5f9; border:1px solid #cbd5e1; color:#475569; padding:5px 12px; border-radius:6px; font-weight:700; font-size:12px; cursor:pointer;">
                        <span class="dashicons dashicons-plus-alt" style="font-size:13px; width:13px; height:13px;"></span> <?php esc_html_e( 'New Draft', 'ifsedu-school-management' ); ?>
                    </button>
                </div>

                <form method="POST" action="" id="dptQuestionForm">
                    <?php wp_nonce_field( 'save_question_paper_action', 'ifs_educore_question_nonce' ); ?>
                    <input type="hidden" name="paper_id" id="inp_paper_id" value="0">

                    <!-- Question Type Segmented Switch -->
                    <div class="ifs-segmented-ctrl">
                        <div class="ifs-segmented-opt is-active" id="opt_type_cq" data-type="CQ">
                            <span class="dashicons dashicons-media-document"></span>
                            <?php esc_html_e( 'Creative (CQ / সৃজনশীল)', 'ifsedu-school-management' ); ?>
                        </div>
                        <div class="ifs-segmented-opt" id="opt_type_mcq" data-type="MCQ">
                            <span class="dashicons dashicons-editor-ol"></span>
                            <?php esc_html_e( 'Objective (MCQ / নৈর্ব্যক্তিক)', 'ifsedu-school-management' ); ?>
                        </div>
                    </div>
                    <input type="hidden" name="question_type" id="inp_question_type" value="CQ">

                    <!-- Academic Configurations -->
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;">
                        <div>
                            <label style="font-size:12px; font-weight:700; color:#475569; display:block; margin-bottom:4px;"><?php esc_html_e( 'Target Exam', 'ifsedu-school-management' ); ?> *</label>
                            <select name="exam_id" id="inp_exam_id" class="ifs-educore-select" style="width:100%; height:38px;" required>
                                <?php foreach ( $exams as $ex ) : ?>
                                    <option value="<?php echo absint( $ex->id ); ?>"><?php echo esc_html( $ex->exam_name ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label style="font-size:12px; font-weight:700; color:#475569; display:block; margin-bottom:4px;"><?php esc_html_e( 'Academic Class', 'ifsedu-school-management' ); ?> *</label>
                            <select name="class_name" id="inp_class_name" class="ifs-educore-select" style="width:100%; height:38px;" required>
                                <option value=""><?php esc_html_e( '-- Select Class --', 'ifsedu-school-management' ); ?></option>
                                <?php foreach ( $classes as $c ) : ?>
                                    <option value="<?php echo esc_attr( $c ); ?>"><?php echo esc_html( $c ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns:2fr 1fr; gap:12px; margin-bottom:12px;">
                        <div>
                            <label style="font-size:12px; font-weight:700; color:#475569; display:block; margin-bottom:4px;"><?php esc_html_e( 'Subject', 'ifsedu-school-management' ); ?> *</label>
                            <select name="subject_dropdown" id="inp_subject_dropdown" class="ifs-educore-select" style="width:100%; height:38px;">
                                <option value=""><?php esc_html_e( '-- Select Class First --', 'ifsedu-school-management' ); ?></option>
                            </select>
                            <input type="hidden" name="subject_name" id="inp_subject_name" value="">
                        </div>

                        <div>
                            <label style="font-size:12px; font-weight:700; color:#475569; display:block; margin-bottom:4px;"><?php esc_html_e( 'Subject Code', 'ifsedu-school-management' ); ?></label>
                            <input type="text" name="subject_code" id="inp_subject_code" class="ifs-educore-input" style="width:100%; height:38px;" placeholder="১০১">
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px;">
                        <div>
                            <label style="font-size:12px; font-weight:700; color:#475569; display:block; margin-bottom:4px;"><?php esc_html_e( 'Duration', 'ifsedu-school-management' ); ?></label>
                            <input type="text" name="exam_duration" id="inp_exam_duration" class="ifs-educore-input" style="width:100%; height:38px;" value="২ ঘণ্টা ৩০ মিনিট">
                        </div>

                        <div>
                            <label style="font-size:12px; font-weight:700; color:#475569; display:block; margin-bottom:4px;"><?php esc_html_e( 'Total Marks', 'ifsedu-school-management' ); ?> *</label>
                            <input type="number" step="0.5" name="total_marks" id="inp_total_marks" class="ifs-educore-input" style="width:100%; height:38px; font-weight:800; color:#00523c;" value="70">
                        </div>
                    </div>

                    <div style="margin-bottom: 16px;">
                        <label style="font-size:12px; font-weight:700; color:#475569; display:block; margin-bottom:4px;"><?php esc_html_e( 'Exam Instructions (Bangla/English)', 'ifsedu-school-management' ); ?></label>
                        <textarea name="instructions" id="inp_instructions" class="ifs-educore-textarea" rows="2" style="width:100%; font-size:13px;">[দ্রষ্টব্য: ডান পাশের সংখ্যা প্রশ্নের পূর্ণমান জ্ঞাপক। প্রদত্ত উদ্দীপকগুলো মনোযোগ সহকারে পড়ে সংশ্লিষ্ট প্রশ্নের উত্তর দাও।]</textarea>
                    </div>

                    <!-- 1. CQ BUILDER SECTION -->
                    <div id="section_cq_builder">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; padding:6px 0; border-top:1px solid #f1f5f9;">
                            <strong style="font-size:13.5px; color:#0f172a;"><?php esc_html_e( 'Creative Questions Structure', 'ifsedu-school-management' ); ?></strong>
                            <span style="font-size:12px; font-weight:800; color:#00523c;" id="lbl_cq_count">মোট প্রশ্ন: ০</span>
                        </div>

                        <div id="cq-repeater-container"></div>

                        <button type="button" id="btnAddCQ" style="width:100%; height:38px; background:#f8fafc; border:1.5px dashed #00523c; color:#00523c; font-weight:800; border-radius:8px; cursor:pointer; margin-top:8px;">
                            <span class="dashicons dashicons-plus-alt2" style="vertical-align:middle;"></span> <?php esc_html_e( 'Add Creative Question (CQ)', 'ifsedu-school-management' ); ?>
                        </button>
                    </div>

                    <!-- 2. MCQ BUILDER SECTION -->
                    <div id="section_mcq_builder" style="display:none;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; padding:6px 0; border-top:1px solid #f1f5f9; flex-wrap:wrap; gap:8px;">
                            <strong style="font-size:13.5px; color:#0f172a;"><?php esc_html_e( 'MCQ Questions Matrix', 'ifsedu-school-management' ); ?></strong>
                            <div style="display:flex; align-items:center; gap:10px;">
                                <label style="font-size:12px; font-weight:700; color:#475569;">
                                    <?php esc_html_e( 'Grid Layout:', 'ifsedu-school-management' ); ?>
                                    <select id="sel_mcq_page_layout" style="height:28px; font-size:12px; border:1px solid #cbd5e1; border-radius:5px;">
                                        <option value="2"><?php esc_html_e( '2 Column (A4)', 'ifsedu-school-management' ); ?></option>
                                        <option value="1"><?php esc_html_e( '1 Column', 'ifsedu-school-management' ); ?></option>
                                    </select>
                                </label>
                                <span style="font-size:12px; font-weight:800; color:#00523c;" id="lbl_mcq_count">মোট প্রশ্ন: ০</span>
                            </div>
                        </div>

                        <div id="mcq-repeater-container"></div>

                        <button type="button" id="btnAddMCQ" style="width:100%; height:38px; background:#f8fafc; border:1.5px dashed #00523c; color:#00523c; font-weight:800; border-radius:8px; cursor:pointer; margin-top:8px;">
                            <span class="dashicons dashicons-plus-alt2" style="vertical-align:middle;"></span> <?php esc_html_e( 'Add Multiple Choice Question (MCQ)', 'ifsedu-school-management' ); ?>
                        </button>
                    </div>

                    <div style="text-align:right; margin-top:24px; border-top:1px solid #f1f5f9; padding-top:16px;">
                        <button type="submit" name="educore_save_question_paper" style="width:100%; height:44px; background:#00523c; color:#fff; font-weight:800; font-size:14px; border:none; border-radius:8px; cursor:pointer; box-shadow:0 4px 12px rgba(0,82,60,0.2);">
                            <span class="dashicons dashicons-saved" style="vertical-align:middle;"></span> <?php esc_html_e( 'Save & Publish Question Paper', 'ifsedu-school-management' ); ?>
                        </button>
                    </div>
                </form>
            </div>

            <!-- RIGHT PANEL: LIVE DYNAMIC A4 PREVIEW & PRINT -->
            <div class="ifs-educore-preview-sticky-wrapper">
                <div class="ifs-educore-preview-topbar no-print">
                    <div style="display:flex; align-items:center; gap:10px;">
                        <span style="font-size:13.5px; font-weight:800; color:#0f172a; display:flex; align-items:center; gap:6px;">
                            <span class="dashicons dashicons-visibility" style="color:#00523c;"></span> <?php esc_html_e( 'A4 Paper Preview', 'ifsedu-school-management' ); ?>
                        </span>
                        <label style="font-size:12px; font-weight:700; color:#475569; display:inline-flex; align-items:center; gap:4px; cursor:pointer;" id="lblToggleAnsKey">
                            <input type="checkbox" id="chkShowAnsKey"> <?php esc_html_e( 'Show Answer Key', 'ifsedu-school-management' ); ?>
                        </label>
                    </div>
                    <button type="button" onclick="window.print();" style="background:#00523c; color:#fff; border:none; padding:8px 18px; border-radius:6px; font-weight:700; font-size:13px; cursor:pointer; display:inline-flex; align-items:center; gap:6px;">
                        <span class="dashicons dashicons-printer"></span> <?php esc_html_e( 'Print Question Paper', 'ifsedu-school-management' ); ?>
                    </button>
                </div>

                <!-- NCTB BOARD STANDARD QUESTION PAPER PREVIEW -->
                <div class="nctb-qp-paper" id="livePaperPreview">
                    <div class="nctb-board-header">
                        <div class="nctb-brand-row">
                            <?php if ( ! empty( $school_logo ) ) : ?>
                                <img src="<?php echo esc_url( $school_logo ); ?>" alt="<?php esc_attr_e( 'Logo', 'ifsedu-school-management' ); ?>" class="nctb-logo-img">
                            <?php endif; ?>
                            <h2 class="nctb-school-title" id="pv_school_name"><?php echo esc_html( $school_name ); ?></h2>
                        </div>
                        <?php if ( ! empty( $school_tagline ) ) : ?>
                            <div style="font-size: 11.5px; color: #475569; font-weight: 700; margin-bottom: 4px; text-transform: uppercase;">
                                <?php echo esc_html( $school_tagline ); ?>
                            </div>
                        <?php endif; ?>
                        <h3 class="nctb-exam-title" id="pv_exam_name"><?php esc_html_e( 'Annual Examination', 'ifsedu-school-management' ); ?></h3>
                        <h4 class="nctb-subject-title">
                            <span id="pv_class_name"><?php esc_html_e( 'Class', 'ifsedu-school-management' ); ?></span> &mdash; <span id="pv_subject_name"><?php esc_html_e( 'Subject', 'ifsedu-school-management' ); ?></span>
                        </h4>
                        <div id="pv_code_box" style="font-size: 13px; font-weight: 700; margin-top: 2px;">
                            <?php esc_html_e( 'বিষয় কোড:', 'ifsedu-school-management' ); ?> <span id="pv_subject_code">১০১</span>
                        </div>
                        <div class="nctb-meta-line">
                            <span><?php esc_html_e( 'সময়:', 'ifsedu-school-management' ); ?> <span id="pv_duration">২ ঘণ্টা ৩০ মিনিট</span></span>
                            <span id="pv_type_badge"><?php esc_html_e( 'সৃজনশীল প্রশ্ন', 'ifsedu-school-management' ); ?></span>
                            <span><?php esc_html_e( 'পূর্ণমান:', 'ifsedu-school-management' ); ?> <span id="pv_total_marks">৭০</span></span>
                        </div>
                    </div>

                    <div class="nctb-instructions-box" id="pv_instructions">
                        [দ্রষ্টব্য: ডান পাশের সংখ্যা প্রশ্নের পূর্ণমান জ্ঞাপক।]
                    </div>

                    <!-- CQ Section Preview -->
                    <div id="pv_cq_wrapper">
                        <div id="pv_cq_list"></div>
                    </div>

                    <!-- MCQ Section Preview -->
                    <div id="pv_mcq_wrapper" style="display:none;">
                        <div id="pv_mcq_list" class="bd-mcq-grid"></div>
                    </div>
                </div>
            </div>

        </div>

    </div>

    <!-- Client-Side Realtime Execution -->
    <script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function() {
        var ajaxNonce = '<?php echo esc_js( wp_create_nonce( "ifs_educore_qp_ajax_nonce" ) ); ?>';
        var bnDigits  = {'0':'০','1':'১','2':'২','3':'৩','4':'৪','5':'৫','6':'৬','7':'৭','8':'৮','9':'৯'};

        function toBn(num) {
            if (num === null || num === undefined) return '';
            return String(num).replace(/[0-9]/g, function(d) { return bnDigits[d] || d; });
        }

        var classSelect   = document.getElementById('inp_class_name');
        var subDropdown   = document.getElementById('inp_subject_dropdown');
        var subNameHidden = document.getElementById('inp_subject_name');
        var codeInput     = document.getElementById('inp_subject_code');
        var marksInput    = document.getElementById('inp_total_marks');
        var qTypeHidden   = document.getElementById('inp_question_type');
        var cqContainer   = document.getElementById('cq-repeater-container');
        var mcqContainer  = document.getElementById('mcq-repeater-container');

        var loadedSubjectsMap = {};

        // Type Segmented Toggle Handlers
        var typeTabs = document.querySelectorAll('.ifs-segmented-opt');
        typeTabs.forEach(function(tab) {
            tab.addEventListener('click', function() {
                typeTabs.forEach(function(t) { t.classList.remove('is-active'); });
                this.classList.add('is-active');
                
                var chosenType = this.getAttribute('data-type');
                qTypeHidden.value = chosenType;
                
                var cqBox  = document.getElementById('section_cq_builder');
                var mcqBox = document.getElementById('section_mcq_builder');

                if (chosenType === 'MCQ') {
                    cqBox.style.display  = 'none';
                    mcqBox.style.display = 'block';
                    document.getElementById('inp_instructions').value = '[দ্রষ্টব্য: সকল প্রশ্নের উত্তর দিতে হবে। ডান পাশের সংখ্যা প্রশ্নের পূর্ণমান জ্ঞাপক।]';
                    document.getElementById('inp_exam_duration').value = '৩০ মিনিট';
                } else {
                    cqBox.style.display  = 'block';
                    mcqBox.style.display = 'none';
                    document.getElementById('inp_instructions').value = '[দ্রষ্টব্য: ডান পাশের সংখ্যা প্রশ্নের পূর্ণমান জ্ঞাপক। প্রদত্ত উদ্দীপকগুলো মনোযোগ সহকারে পড়ে সংশ্লিষ্ট প্রশ্নের উত্তর দাও।]';
                    document.getElementById('inp_exam_duration').value = '২ ঘণ্টা ৩০ মিনিট';
                }

                var activeSub = subNameHidden.value;
                if (activeSub && loadedSubjectsMap[activeSub]) {
                    var subObj = loadedSubjectsMap[activeSub];
                    marksInput.value = (chosenType === 'MCQ') ? (subObj.mcq_marks || 30) : (subObj.cq_marks || 70);
                }

                updateLivePreview();
            });
        });

        // Dynamic Subject Loading
        if (classSelect) {
            classSelect.addEventListener('change', function() {
                var selectedClass = this.value;
                subDropdown.innerHTML = '<option value=""><?php echo esc_js( __( '-- Loading Subjects... --', 'ifsedu-school-management' ) ); ?></option>';

                if (!selectedClass) {
                    subDropdown.innerHTML = '<option value=""><?php echo esc_js( __( '-- Select Class First --', 'ifsedu-school-management' ) ); ?></option>';
                    return;
                }

                var formData = new FormData();
                formData.append('action', 'ifs_educore_get_subjects_for_qp');
                formData.append('security', ajaxNonce);
                formData.append('class_name', selectedClass);

                fetch('<?php echo esc_url( admin_url( "admin-ajax.php" ) ); ?>', { method: 'POST', body: formData })
                .then(function(res) { return res.json(); })
                .then(function(response) {
                    if (response.success && response.data.length > 0) {
                        loadedSubjectsMap = {};
                        var opts = '<option value=""><?php echo esc_js( __( '-- Select Subject --', 'ifsedu-school-management' ) ); ?></option>';
                        response.data.forEach(function(sub) {
                            loadedSubjectsMap[sub.subject_name] = sub;
                            var codeTxt = sub.subject_code ? ' (' + sub.subject_code + ')' : '';
                            opts += '<option value="' + sub.subject_name + '">' + sub.subject_name + codeTxt + '</option>';
                        });
                        subDropdown.innerHTML = opts;
                    } else {
                        subDropdown.innerHTML = '<option value=""><?php echo esc_js( __( 'No subjects found for this class', 'ifsedu-school-management' ) ); ?></option>';
                    }
                });
            });
        }

        if (subDropdown) {
            subDropdown.addEventListener('change', function() {
                var subName = this.value;
                subNameHidden.value = subName;

                if (subName && loadedSubjectsMap[subName]) {
                    var subObj = loadedSubjectsMap[subName];
                    if (codeInput) codeInput.value = subObj.subject_code || '';

                    var currentType = qTypeHidden.value;
                    if (currentType === 'CQ') {
                        marksInput.value = parseFloat(subObj.cq_marks) > 0 ? subObj.cq_marks : (subObj.total_marks || 70);
                    } else {
                        marksInput.value = parseFloat(subObj.mcq_marks) > 0 ? subObj.mcq_marks : 30;
                    }
                }
                updateLivePreview();
            });
        }

        // CQ Card Element Creator
        function createCQCard(data, num) {
            data = data || {};
            num  = num || 1;
            var div = document.createElement('div');
            div.className = 'ifs-educore-q-card cq-item';
            div.innerHTML = `
                <div class="ifs-educore-q-card-header">
                    <strong class="cq-item-num" style="color:#00523c; font-size:13.5px;"><?php echo esc_js( __( 'Question No.', 'ifsedu-school-management' ) ); ?> ${toBn(num)}</strong>
                    <div style="display:flex; align-items:center; gap:8px;" onclick="event.stopPropagation();">
                        <input type="text" name="cq_section[]" class="ifs-educore-input cq-inp-sec" style="width:130px; height:28px; font-size:12px;" placeholder="<?php esc_attr_e( "Section (e.g. 'ক-বিভাগ')", 'ifsedu-school-management' ); ?>" value="${data.section || ''}">
                        <button type="button" class="btn-remove-cq" style="background:#fee2e2; color:#dc2626; border:1px solid #fecaca; border-radius:5px; padding:3px 8px; cursor:pointer; font-weight:800;">&times;</button>
                    </div>
                </div>
                <div>
                    <div style="margin-bottom:8px;">
                        <textarea name="cq_stem[]" class="ifs-educore-textarea cq-inp-stem" rows="2" style="width:100%; font-size:13px;" placeholder="<?php esc_attr_e( 'Write the stimulus / stem text here...', 'ifsedu-school-management' ); ?>">${data.stem || ''}</textarea>
                    </div>
                    <div class="ifs-educore-image-attach-box" style="margin-bottom:8px; display:flex; align-items:center; gap:8px;">
                        <img src="${data.image || ''}" class="cq-img-preview" alt="Diagram" style="max-height:40px; border:1px solid #cbd5e1; border-radius:4px; ${data.image ? 'display:block;' : 'display:none;'}">
                        <input type="hidden" name="cq_image_url[]" class="cq-inp-img-url" value="${data.image || ''}">
                        <button type="button" class="btn-choose-diagram" style="background:#f1f5f9; border:1px solid #cbd5e1; padding:4px 10px; border-radius:6px; font-size:12px; font-weight:700; cursor:pointer;">
                            <span class="dashicons dashicons-format-image" style="font-size:13px; width:13px; height:13px; vertical-align:middle;"></span> <?php echo esc_js( __( 'Attach Diagram', 'ifsedu-school-management' ) ); ?>
                        </button>
                        <button type="button" class="btn-remove-diagram" style="background:#fee2e2; color:#dc2626; border:none; padding:4px 8px; border-radius:4px; font-size:11px; font-weight:700; cursor:pointer; ${data.image ? 'display:inline-flex;' : 'display:none;'}"><?php echo esc_js( __( 'Remove', 'ifsedu-school-management' ) ); ?></button>
                    </div>
                    <div class="ifs-sub-q-builder-grid">
                        <div class="ifs-sub-q-row-item">
                            <input type="text" name="cq_a[]" class="ifs-educore-input cq-inp-a" placeholder="ক. জ্ঞানমূলক প্রশ্ন লিখুন" value="${data.a || ''}" style="flex:1; height:32px;">
                            <input type="number" step="0.5" name="cq_mark_a[]" class="ifs-educore-input cq-mark-a" value="${data.mark_a || 1}" style="width:50px; height:32px; font-weight:800; text-align:center;">
                        </div>
                        <div class="ifs-sub-q-row-item">
                            <input type="text" name="cq_b[]" class="ifs-educore-input cq-inp-b" placeholder="খ. অনুধাবনমূলক প্রশ্ন লিখুন" value="${data.b || ''}" style="flex:1; height:32px;">
                            <input type="number" step="0.5" name="cq_mark_b[]" class="ifs-educore-input cq-mark-b" value="${data.mark_b || 2}" style="width:50px; height:32px; font-weight:800; text-align:center;">
                        </div>
                        <div class="ifs-sub-q-row-item">
                            <input type="text" name="cq_c[]" class="ifs-educore-input cq-inp-c" placeholder="গ. প্রয়োগমূলক প্রশ্ন লিখুন" value="${data.c || ''}" style="flex:1; height:32px;">
                            <input type="number" step="0.5" name="cq_mark_c[]" class="ifs-educore-input cq-mark-c" value="${data.mark_c || 3}" style="width:50px; height:32px; font-weight:800; text-align:center;">
                        </div>
                        <div class="ifs-sub-q-row-item">
                            <input type="text" name="cq_d[]" class="ifs-educore-input cq-inp-d" placeholder="ঘ. উচ্চতর দক্ষতামূলক প্রশ্ন লিখুন" value="${data.d || ''}" style="flex:1; height:32px;">
                            <input type="number" step="0.5" name="cq_mark_d[]" class="ifs-educore-input cq-mark-d" value="${data.mark_d || 4}" style="width:50px; height:32px; font-weight:800; text-align:center;">
                        </div>
                    </div>
                </div>
            `;
            return div;
        }

        // MCQ Card Element Creator
        function createMCQCard(data, num) {
            data = data || {};
            num  = num || 1;
            var currentCols = parseInt(data.columns, 10) || 2;
            var div = document.createElement('div');
            div.className = 'ifs-educore-q-card mcq-item';
            div.innerHTML = `
                <div class="ifs-educore-q-card-header">
                    <strong class="mcq-item-num" style="color:#00523c; font-size:13.5px;">MCQ ${toBn(num)}</strong>
                    <div style="display:flex; align-items:center; gap:8px;" onclick="event.stopPropagation();">
                        <select name="mcq_columns[]" class="mcq-inp-cols" style="height:26px; font-size:11.5px; border:1px solid #cbd5e1; border-radius:4px;">
                            <option value="1" ${currentCols === 1 ? 'selected' : ''}>১ কলাম</option>
                            <option value="2" ${currentCols === 2 ? 'selected' : ''}>২ কলাম</option>
                            <option value="4" ${currentCols === 4 ? 'selected' : ''}>৪ কলাম</option>
                        </select>
                        <select name="mcq_answer[]" class="mcq-inp-ans" style="height:26px; font-size:11.5px; border:1px solid #cbd5e1; border-radius:4px; font-weight:700; color:#00523c;">
                            <option value="opt1" ${data.ans === 'opt1' ? 'selected' : ''}>উ: (ক)</option>
                            <option value="opt2" ${data.ans === 'opt2' ? 'selected' : ''}>উ: (খ)</option>
                            <option value="opt3" ${data.ans === 'opt3' ? 'selected' : ''}>উ: (গ)</option>
                            <option value="opt4" ${data.ans === 'opt4' ? 'selected' : ''}>উ: (ঘ)</option>
                        </select>
                        <input type="number" step="0.5" name="mcq_mark[]" class="mcq-inp-mark" style="width:45px; height:26px; font-size:12px; font-weight:800; text-align:center; border:1px solid #cbd5e1; border-radius:4px;" value="${data.mark || 1}">
                        <button type="button" class="btn-remove-mcq" style="background:#fee2e2; color:#dc2626; border:1px solid #fecaca; border-radius:5px; padding:3px 8px; cursor:pointer; font-weight:800;">&times;</button>
                    </div>
                </div>
                <div>
                    <input type="text" name="mcq_question[]" class="ifs-educore-input mcq-inp-q" placeholder="<?php esc_attr_e( 'Enter MCQ question title...', 'ifsedu-school-management' ); ?>" value="${data.q || ''}" style="width:100%; height:32px; margin-bottom:6px; font-weight:600;">
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:6px;">
                        <input type="text" name="mcq_opt_1[]" class="ifs-educore-input mcq-inp-o1" placeholder="(ক) প্রথম বিকল্প" value="${data.opt1 || ''}" style="height:30px; font-size:12.5px;">
                        <input type="text" name="mcq_opt_2[]" class="ifs-educore-input mcq-inp-o2" placeholder="(খ) দ্বিতীয় বিকল্প" value="${data.opt2 || ''}" style="height:30px; font-size:12.5px;">
                        <input type="text" name="mcq_opt_3[]" class="ifs-educore-input mcq-inp-o3" placeholder="(গ) তৃতীয় বিকল্প" value="${data.opt3 || ''}" style="height:30px; font-size:12.5px;">
                        <input type="text" name="mcq_opt_4[]" class="ifs-educore-input mcq-inp-o4" placeholder="(ঘ) চতুর্থ বিকল্প" value="${data.opt4 || ''}" style="height:30px; font-size:12.5px;">
                    </div>
                </div>
            `;
            return div;
        }

        // Live Paper Preview Compiler
        function updateLivePreview() {
            var examSelect = document.getElementById('inp_exam_id');
            var qType      = qTypeHidden.value;
            var showAns    = document.getElementById('chkShowAnsKey').checked;
            var pageLayout = document.getElementById('sel_mcq_page_layout') ? document.getElementById('sel_mcq_page_layout').value : '2';

            document.getElementById('pv_exam_name').textContent = examSelect.options[examSelect.selectedIndex] ? examSelect.options[examSelect.selectedIndex].text : 'বার্ষিক পরীক্ষা';
            document.getElementById('pv_class_name').textContent = classSelect.options[classSelect.selectedIndex] ? classSelect.options[classSelect.selectedIndex].text : 'শ্রেণি';
            document.getElementById('pv_subject_name').textContent = subNameHidden.value || 'বিষয়';
            
            var codeVal = codeInput.value;
            var codeBox = document.getElementById('pv_code_box');
            if (codeVal) {
                codeBox.style.display = 'block';
                document.getElementById('pv_subject_code').textContent = toBn(codeVal);
            } else {
                codeBox.style.display = 'none';
            }

            document.getElementById('pv_duration').textContent     = document.getElementById('inp_exam_duration').value;
            document.getElementById('pv_instructions').textContent = document.getElementById('inp_instructions').value;
            document.getElementById('pv_type_badge').textContent   = (qType === 'MCQ') ? 'বহুনির্বাচনী প্রশ্ন' : 'সৃজনশীল প্রশ্ন';

            if (qType === 'CQ') {
                document.getElementById('pv_cq_wrapper').style.display  = 'block';
                document.getElementById('pv_mcq_wrapper').style.display = 'none';

                var cqItems = document.querySelectorAll('#cq-repeater-container .cq-item');
                document.getElementById('lbl_cq_count').textContent = 'মোট প্রশ্ন: ' + toBn(cqItems.length);
                var pvCqList = document.getElementById('pv_cq_list');
                var cqHtml = '';
                var lastSection = '';
                var totalMarks = 0;

                cqItems.forEach(function(item, idx) {
                    var qNum    = toBn(idx + 1);
                    var section = item.querySelector('.cq-inp-sec') ? item.querySelector('.cq-inp-sec').value.trim() : '';
                    var stem    = item.querySelector('.cq-inp-stem').value;
                    var img     = item.querySelector('.cq-inp-img-url').value;
                    var a       = item.querySelector('.cq-inp-a').value;
                    var b       = item.querySelector('.cq-inp-b').value;
                    var c       = item.querySelector('.cq-inp-c').value;
                    var d       = item.querySelector('.cq-inp-d').value;

                    var ma = parseFloat(item.querySelector('.cq-mark-a').value) || 1;
                    var mb = parseFloat(item.querySelector('.cq-mark-b').value) || 2;
                    var mc = parseFloat(item.querySelector('.cq-mark-c').value) || 3;
                    var md = parseFloat(item.querySelector('.cq-mark-d').value) || 4;

                    if (stem || a || img) {
                        totalMarks += (ma + mb + mc + md);
                        if (section && section !== lastSection) {
                            cqHtml += '<div class="nctb-section-header">' + section + '</div>';
                            lastSection = section;
                        }
                        cqHtml += `
                            <div class="nctb-cq-item">
                                <div class="nctb-cq-stem-wrapper">
                                    <span style="font-weight:900;">${qNum}.</span>
                                    <div style="flex:1;">${stem.replace(/\n/g, '<br>')}</div>
                                </div>
                                ${img ? `<div class="nctb-cq-figure"><img src="${img}" alt="চিত্র"></div>` : ''}
                                <div class="nctb-sub-questions-list">
                                    ${a ? `<div class="nctb-sub-q-row"><span class="nctb-sub-q-text">ক. ${a}</span><span class="nctb-sub-q-mark">${toBn(ma)}</span></div>` : ''}
                                    ${b ? `<div class="nctb-sub-q-row"><span class="nctb-sub-q-text">খ. ${b}</span><span class="nctb-sub-q-mark">${toBn(mb)}</span></div>` : ''}
                                    ${c ? `<div class="nctb-sub-q-row"><span class="nctb-sub-q-text">গ. ${c}</span><span class="nctb-sub-q-mark">${toBn(mc)}</span></div>` : ''}
                                    ${d ? `<div class="nctb-sub-q-row"><span class="nctb-sub-q-text">ঘ. ${d}</span><span class="nctb-sub-q-mark">${toBn(md)}</span></div>` : ''}
                                </div>
                            </div>
                        `;
                    }
                });
                pvCqList.innerHTML = cqHtml;
                document.getElementById('pv_total_marks').textContent = toBn(marksInput.value || totalMarks);
            } else {
                document.getElementById('pv_cq_wrapper').style.display  = 'none';
                document.getElementById('pv_mcq_wrapper').style.display = 'block';

                var mcqItems  = document.querySelectorAll('#mcq-repeater-container .mcq-item');
                document.getElementById('lbl_mcq_count').textContent = 'মোট প্রশ্ন: ' + toBn(mcqItems.length);
                var pvMcqList = document.getElementById('pv_mcq_list');
                
                pvMcqList.className = 'bd-mcq-grid ' + (pageLayout === '1' ? 'layout-single-col' : '');
                
                var mcqHtml = '';
                var totalMarks = 0;
                var ansMap = {'opt1':'(ক)','opt2':'(খ)','opt3':'(গ)','opt4':'(ঘ)'};

                mcqItems.forEach(function(item, idx) {
                    var qNum = toBn(idx + 1);
                    var q    = item.querySelector('.mcq-inp-q').value;
                    var mark = parseFloat(item.querySelector('.mcq-inp-mark').value) || 1;
                    var cols = parseInt(item.querySelector('.mcq-inp-cols').value, 10) || 2;
                    var ans  = item.querySelector('.mcq-inp-ans').value;
                    var o1   = item.querySelector('.mcq-inp-o1').value;
                    var o2   = item.querySelector('.mcq-inp-o2').value;
                    var o3   = item.querySelector('.mcq-inp-o3').value;
                    var o4   = item.querySelector('.mcq-inp-o4').value;

                    var colClass = 'cols-2';
                    if (cols === 1) {
                        colClass = 'cols-1';
                    } else if (cols === 4) {
                        colClass = 'cols-4';
                    }

                    if (q) {
                        totalMarks += mark;
                        mcqHtml += `
                            <div class="bd-mcq-item">
                                <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                                    <strong>${qNum}. ${q}</strong>
                                    <span style="font-weight:700; font-size:11.5px; color:#64748b;">[${toBn(mark)}]</span>
                                </div>
                                <div class="bd-mcq-options ${colClass}">
                                    <span>(ক) ${o1}</span>
                                    <span>(খ) ${o2}</span>
                                    <span>(গ) ${o3}</span>
                                    <span>(ঘ) ${o4}</span>
                                </div>
                                ${showAns ? `<div class="print-hide-ans" style="margin-top:3px;"><span class="bd-ans-key-tag">সঠিক উত্তর: ${ansMap[ans] || ''}</span></div>` : ''}
                            </div>
                        `;
                    }
                });
                pvMcqList.innerHTML = mcqHtml;
                document.getElementById('pv_total_marks').textContent = toBn(marksInput.value || totalMarks);
            }
        }

        document.getElementById('dptQuestionForm').addEventListener('input', updateLivePreview);
        document.getElementById('chkShowAnsKey').addEventListener('change', updateLivePreview);
        
        var selPageLayout = document.getElementById('sel_mcq_page_layout');
        if (selPageLayout) {
            selPageLayout.addEventListener('change', updateLivePreview);
        }

        // Add / Remove Questions Handlers
        document.getElementById('btnAddCQ').addEventListener('click', function() {
            var count = cqContainer.querySelectorAll('.cq-item').length + 1;
            cqContainer.appendChild(createCQCard({}, count));
            updateLivePreview();
        });

        cqContainer.addEventListener('click', function(e) {
            if (e.target.closest('.btn-remove-cq')) {
                e.target.closest('.cq-item').remove();
                cqContainer.querySelectorAll('.cq-item').forEach(function(item, idx) {
                    item.querySelector('.cq-item-num').textContent = '<?php echo esc_js( __( "Question No.", "ifsedu-school-management" ) ); ?> ' + toBn(idx + 1);
                });
                updateLivePreview();
            }
        });

        document.getElementById('btnAddMCQ').addEventListener('click', function() {
            var count = mcqContainer.querySelectorAll('.mcq-item').length + 1;
            mcqContainer.appendChild(createMCQCard({}, count));
            updateLivePreview();
        });

        mcqContainer.addEventListener('click', function(e) {
            if (e.target.closest('.btn-remove-mcq')) {
                e.target.closest('.mcq-item').remove();
                mcqContainer.querySelectorAll('.mcq-item').forEach(function(item, idx) {
                    item.querySelector('.mcq-item-num').textContent = 'MCQ ' + toBn(idx + 1);
                });
                updateLivePreview();
            }
        });

        // Media Library Diagram Uploader
        var currentMediaBox = null;
        cqContainer.addEventListener('click', function(e) {
            var chooseBtn = e.target.closest('.btn-choose-diagram');
            var removeImg = e.target.closest('.btn-remove-diagram');

            if (chooseBtn) {
                currentMediaBox = chooseBtn.closest('.ifs-educore-image-attach-box');
                var frame = wp.media({
                    title: '<?php echo esc_js( __( "Choose Image / Diagram", "ifsedu-school-management" ) ); ?>',
                    button: { text: '<?php echo esc_js( __( "Insert Image", "ifsedu-school-management" ) ); ?>' },
                    multiple: false
                });

                frame.on('select', function() {
                    var attachment = frame.state().get('selection').first().toJSON();
                    if (currentMediaBox && attachment && attachment.url) {
                        currentMediaBox.querySelector('.cq-inp-img-url').value = attachment.url;
                        var img = currentMediaBox.querySelector('.cq-img-preview');
                        img.src = attachment.url;
                        img.style.display = 'block';
                        currentMediaBox.querySelector('.btn-remove-diagram').style.display = 'inline-flex';
                        updateLivePreview();
                    }
                });

                frame.open();
            }

            if (removeImg) {
                var box = removeImg.closest('.ifs-educore-image-attach-box');
                box.querySelector('.cq-inp-img-url').value = '';
                box.querySelector('.cq-img-preview').src = '';
                box.querySelector('.cq-img-preview').style.display = 'none';
                removeImg.style.display = 'none';
                updateLivePreview();
            }
        });

        // Search & Filter Repository
        var kFilter   = document.getElementById('filter_keyword');
        var cFilter   = document.getElementById('filter_class_search');
        var tFilter   = document.getElementById('filter_type_search');
        var tableRows = document.querySelectorAll('#savedPapersTable tbody tr');

        function filterSavedPapers() {
            var kw  = kFilter.value.toLowerCase().trim();
            var cls = cFilter.value;
            var typ = tFilter.value;

            tableRows.forEach(function(row) {
                var rCls = row.getAttribute('data-class');
                var rTyp = row.getAttribute('data-type');
                var rTxt = row.getAttribute('data-text');

                var matchKw  = !kw || (rTxt && rTxt.indexOf(kw) !== -1);
                var matchCls = !cls || (rCls === cls);
                var matchTyp = !typ || (rTyp === typ);

                row.style.display = (matchKw && matchCls && matchTyp) ? '' : 'none';
            });
        }

        if (kFilter) kFilter.addEventListener('input', filterSavedPapers);
        if (cFilter) cFilter.addEventListener('change', filterSavedPapers);
        if (tFilter) tFilter.addEventListener('change', filterSavedPapers);

        // Load Saved Question Paper Handler
        document.querySelectorAll('.btn-load-paper').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var paperId  = this.getAttribute('data-id');
                var formData = new FormData();
                formData.append('action', 'ifs_educore_load_saved_question_paper');
                formData.append('security', ajaxNonce);
                formData.append('paper_id', paperId);

                fetch('<?php echo esc_url( admin_url( "admin-ajax.php" ) ); ?>', { method: 'POST', body: formData })
                .then(function(res) { return res.json(); })
                .then(function(res) {
                    if (res.success && res.data) {
                        var p = res.data;
                        document.getElementById('inp_paper_id').value = p.id;
                        document.getElementById('inp_exam_id').value = p.exam_id;
                        classSelect.value = p.class_name;
                        qTypeHidden.value = p.question_type;
                        subNameHidden.value = p.subject_name;
                        codeInput.value = p.subject_code;
                        document.getElementById('inp_exam_duration').value = p.exam_duration;
                        marksInput.value = p.total_marks;
                        document.getElementById('inp_instructions').value = p.instructions;

                        // Switch segmented tab styling
                        typeTabs.forEach(function(tab) {
                            if (tab.getAttribute('data-type') === p.question_type) {
                                tab.classList.add('is-active');
                            } else {
                                tab.classList.remove('is-active');
                            }
                        });

                        var cqBox  = document.getElementById('section_cq_builder');
                        var mcqBox = document.getElementById('section_mcq_builder');

                        if (p.question_type === 'CQ') {
                            cqBox.style.display  = 'block';
                            mcqBox.style.display = 'none';
                            cqContainer.innerHTML = '';
                            if (Array.isArray(p.cq_data)) {
                                p.cq_data.forEach(function(cq, idx) {
                                    cqContainer.appendChild(createCQCard(cq, idx + 1));
                                });
                            }
                        } else {
                            cqBox.style.display  = 'none';
                            mcqBox.style.display = 'block';
                            mcqContainer.innerHTML = '';
                            if (Array.isArray(p.mcq_data)) {
                                p.mcq_data.forEach(function(mcq, idx) {
                                    mcqContainer.appendChild(createMCQCard(mcq, idx + 1));
                                });
                            }
                        }

                        classSelect.dispatchEvent(new Event('change'));
                        updateLivePreview();
                        window.scrollTo({ top: document.getElementById('dptQuestionForm').offsetTop - 30, behavior: 'smooth' });
                    }
                });
            });
        });

        // Reset Button
        document.getElementById('btnResetForm').addEventListener('click', function() {
            document.getElementById('inp_paper_id').value = '0';
            document.getElementById('dptQuestionForm').reset();
            cqContainer.innerHTML = '';
            mcqContainer.innerHTML = '';
            cqContainer.appendChild(createCQCard({}, 1));
            mcqContainer.appendChild(createMCQCard({}, 1));
            updateLivePreview();
        });

        // Initialize Defaults
        cqContainer.appendChild(createCQCard({
            stem: "অনুপম উচ্চশিক্ষিত হলেও ব্যক্তিত্বহীন ও আত্মমর্যাদাহীন এক যুবক। মামার অভিভাবকত্বে সে বড় হয়েছে।",
            a: "অনুপমের ভাষায় সুপুরুষ কাকে বলা হয়েছে?",
            b: "‘এ অপরাধ আমি আজীবন বহন করিব’—উক্তিটি ব্যাখ্যা কর।",
            c: "উদ্দীপকের সাথে পঠিত গল্পের সাদৃশ্য বর্ণনা কর।",
            d: "“ব্যক্তিত্বহীনতাই অনুপমের জীবনের বড় ট্র্যাজেডি”—মূল্যায়ন কর।",
            mark_a: 1, mark_b: 2, mark_c: 3, mark_d: 4
        }, 1));

        mcqContainer.appendChild(createMCQCard({
            q: "বাংলাদেশের জাতীয় কবির নাম কী?",
            opt1: "রবীন্দ্রনাথ ঠাকুর", opt2: "কাজী নজরুল ইসলাম", opt3: "জসীমউদ্দীন", opt4: "জীবনানন্দ দাশ",
            ans: "opt2", mark: 1, columns: 2
        }, 1));

        updateLivePreview();
    });
    </script>
    <?php
}
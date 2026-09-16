<?php
/**
 * Academic Student Promotion & Roll Re-assignment Engine
 * File: inc/students/students-promotion.php
 * Text Domain: ifsedu-school-management
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

if ( ! function_exists( 'educore_promotion_get_table' ) ) {
    /**
     * Helper: Defensive table name resolver for promotion module
     */
    function educore_promotion_get_table( string $key ): string {
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
// 1. AJAX HANDLERS FOR DYNAMIC SELECTORS
// --------------------------------------------------------------------------
add_action( 'wp_ajax_ifs_educore_get_target_sections_promotion', 'ifs_educore_get_target_sections_promotion_handler' );
/**
 * AJAX Handler: Fetch target sections by class for student promotion.
 */
function ifs_educore_get_target_sections_promotion_handler(): void {
    check_ajax_referer( 'ifs_educore_promotion_nonce', 'security' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => esc_html__( 'Permission denied.', 'ifsedu-school-management' ) ) );
    }

    global $wpdb;
    $table_units = educore_promotion_get_table( 'academic_units' );
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

/**
 * Render Academic Student Promotion & Roll Re-assignment Workspace View & Handle Execution
 */
function educore_student_promotion_view(): void {
    global $wpdb;
    $table_students = educore_promotion_get_table( 'students' );
    $table_exams    = educore_promotion_get_table( 'exams' );
    $table_results  = educore_promotion_get_table( 'results' );
    $table_units    = educore_promotion_get_table( 'academic_units' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to promote students.', 'ifsedu-school-management' ), 403 );
    }

    $raw_req_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
    $current_uri = remove_query_arg( array( 'status', 'msg' ), $raw_req_uri );
    $base_url    = esc_url_raw( $current_uri );
    $notice_msg  = '';

    // --------------------------------------------------------------------------
    // 2. BULK PROMOTION EXECUTION ENGINE
    // --------------------------------------------------------------------------
    $req_method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
    if ( 'POST' === $req_method && isset( $_POST['educore_execute_promotion'] ) ) {
        if ( isset( $_POST['ifs_educore_promotion_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ifs_educore_promotion_nonce'] ) ), 'execute_promotion_action' ) ) {
            $target_class   = isset( $_POST['target_class'] ) ? sanitize_text_field( wp_unslash( $_POST['target_class'] ) ) : '';
            $selected_stids = ( isset( $_POST['promote_student'] ) && is_array( $_POST['promote_student'] ) ) ? array_map( 'absint', wp_unslash( $_POST['promote_student'] ) ) : array();
            $new_rolls      = ( isset( $_POST['new_roll'] ) && is_array( $_POST['new_roll'] ) ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['new_roll'] ) ) : array();
            $new_sections   = ( isset( $_POST['new_section'] ) && is_array( $_POST['new_section'] ) ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['new_section'] ) ) : array();

            if ( ! empty( $target_class ) && ! empty( $selected_stids ) ) {
                $promoted_count = 0;

                foreach ( $selected_stids as $st_id ) {
                    $roll_val    = isset( $new_rolls[ $st_id ] ) ? (int) $new_rolls[ $st_id ] : 0;
                    $section_val = isset( $new_sections[ $st_id ] ) ? sanitize_text_field( $new_sections[ $st_id ] ) : '';

                    $updated = $wpdb->update(
                        $table_students,
                        array(
                            'class_name'   => $target_class,
                            'section_name' => $section_val,
                            'roll_no'      => $roll_val,
                        ),
                        array( 'id' => $st_id ),
                        array( '%s', '%s', '%d' ),
                        array( '%d' )
                    );

                    if ( false !== $updated ) {
                        $promoted_count++;
                    }
                }

                if ( function_exists( 'educore_log_activity' ) ) {
                    educore_log_activity(
                        sprintf(
                            /* translators: 1: Number of promoted students, 2: Target class name */
                            __( 'Promoted %1$d students to Class %2$s', 'ifsedu-school-management' ),
                            $promoted_count,
                            $target_class
                        )
                    );
                }

                $notice_msg = sprintf(
                    /* translators: 1: Number of promoted students, 2: Target class name */
                    esc_html__( 'Successfully promoted %1$d student(s) to Class %2$s.', 'ifsedu-school-management' ),
                    $promoted_count,
                    esc_html( $target_class )
                );
            }
        }
    }

    // --------------------------------------------------------------------------
    // 3. QUERY CONTEXT & FILTERS
    // --------------------------------------------------------------------------
    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $filter_exam    = isset( $_GET['exam_id'] ) ? absint( $_GET['exam_id'] ) : 0;
    $filter_class   = isset( $_GET['class_name'] ) ? sanitize_text_field( wp_unslash( $_GET['class_name'] ) ) : '';
    $filter_section = isset( $_GET['section_name'] ) ? sanitize_text_field( wp_unslash( $_GET['section_name'] ) ) : '';
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    $exams = $wpdb->get_results(
        $wpdb->prepare(
            'SELECT id, exam_name, start_date, att_start_date FROM %i ORDER BY id DESC',
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

    $academic_classes = array();
    if ( ! empty( $raw_classes_data ) && is_array( $raw_classes_data ) ) {
        foreach ( $raw_classes_data as $c_row ) {
            $c_name = trim( (string) $c_row->class_name );
            if ( ! empty( $c_name ) && ! in_array( $c_name, $academic_classes, true ) ) {
                $academic_classes[] = $c_name;
            }
        }
    }

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

    // --------------------------------------------------------------------------
    // 4. COMPUTE MERIT POSITIONS & PASS/FAIL FOR CANDIDATES
    // --------------------------------------------------------------------------
    $display_candidates = array();

    if ( 0 < $filter_exam && ! empty( $filter_class ) ) {
        $clean_class = trim( str_ireplace( 'Class ', '', $filter_class ) );

        if ( ! empty( $filter_section ) ) {
            $class_students = $wpdb->get_results(
                $wpdb->prepare(
                    'SELECT id, full_name, student_id, roll_no, class_name, section_name FROM %i WHERE status = %s AND (class_name = %s OR class_name = %s) AND section_name = %s ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC',
                    $table_students,
                    'Active',
                    $filter_class,
                    $clean_class,
                    $filter_section
                )
            );
        } else {
            $class_students = $wpdb->get_results(
                $wpdb->prepare(
                    'SELECT id, full_name, student_id, roll_no, class_name, section_name FROM %i WHERE status = %s AND (class_name = %s OR class_name = %s) ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC',
                    $table_students,
                    'Active',
                    $filter_class,
                    $clean_class
                )
            );
        }

        if ( ! empty( $class_students ) ) {
            $candidate_pool = array();

            foreach ( $class_students as $s ) {
                $results = $wpdb->get_results(
                    $wpdb->prepare(
                        'SELECT obtained_marks, grade, gpa FROM %i WHERE exam_id = %d AND student_id = %d',
                        $table_results,
                        $filter_exam,
                        $s->id
                    )
                );

                if ( empty( $results ) ) {
                    continue;
                }

                $total_obt = 0;
                $sum_gpa   = 0;
                $sub_count = count( $results );
                $has_fail  = false;

                foreach ( $results as $res ) {
                    $total_obt += (float) $res->obtained_marks;
                    $sum_gpa   += (float) $res->gpa;
                    if ( 'F' === strtoupper( trim( (string) $res->grade ) ) || (float) $res->gpa <= 0 ) {
                        $has_fail = true;
                    }
                }

                $avg_gpa   = ( 0 < $sub_count ) ? ( $sum_gpa / $sub_count ) : 0;
                $final_gpa = $has_fail ? 0.00 : round( $avg_gpa, 2 );

                $candidate_pool[] = array(
                    'student' => $s,
                    'total'   => $total_obt,
                    'gpa'     => $final_gpa,
                    'failed'  => $has_fail,
                    'section' => $s->section_name ? trim( (string) $s->section_name ) : '',
                );
            }

            // Merit sort: Passed first -> GPA (DESC) -> Total Marks (DESC).
            usort( $candidate_pool, static function( $a, $b ): int {
                if ( $a['failed'] !== $b['failed'] ) {
                    return $a['failed'] ? 1 : -1;
                }
                if ( $b['gpa'] != $a['gpa'] ) {
                    return ( $b['gpa'] < $a['gpa'] ) ? -1 : 1;
                }
                return ( $b['total'] < $a['total'] ) ? -1 : 1;
            } );

            $class_pos_counter = 1;
            $section_counters  = array();

            foreach ( $candidate_pool as $item ) {
                $sec = $item['section'];
                if ( ! isset( $section_counters[ $sec ] ) ) {
                    $section_counters[ $sec ] = 1;
                }

                if ( ! $item['failed'] ) {
                    $item['class_position']   = $class_pos_counter++;
                    $item['section_position'] = $section_counters[ $sec ]++;
                } else {
                    $item['class_position']   = 0;
                    $item['section_position'] = 0;
                }

                $display_candidates[] = $item;
            }
        }
    }
    ?>

    <style id="ifs-educore-students-promotion-styles">
        .ifs-educore-promotion-root {
            max-width: 100%;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: inherit;
            color: #0f172a;
        }

        .ifs-educore-bento-filter-card {
            background: #ffffff !important;
            border: 1px solid #e2e8f0 !important;
            border-radius: 14px !important;
            padding: 20px 24px !important;
            margin-bottom: 20px !important;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.02) !important;
            box-sizing: border-box !important;
        }

        .ifs-educore-bento-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 24px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.02);
            margin-bottom: 24px;
            box-sizing: border-box;
        }

        .ifs-educore-filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)) 140px;
            gap: 16px;
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

        .ifs-educore-select {
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
            -moz-appearance: none !important;
            transition: all 0.2s ease !important;
            cursor: pointer;
        }

        .ifs-educore-select:hover:not(:disabled) {
            border-color: #94a3b8 !important;
        }

        .ifs-educore-select:focus {
            border-color: #00523c !important;
            box-shadow: 0 0 0 3px rgba(0, 82, 60, 0.12) !important;
        }

        .ifs-educore-select:disabled {
            background-color: #f8fafc !important;
            color: #94a3b8 !important;
            border-color: #e2e8f0 !important;
            cursor: not-allowed;
            opacity: 0.85;
        }

        .ifs-educore-btn-load {
            width: 100% !important;
            height: 42px !important;
            background: #00523c !important;
            color: #ffffff !important;
            font-weight: 700 !important;
            font-size: 13.5px !important;
            border: none !important;
            border-radius: 9px !important;
            cursor: pointer !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 6px !important;
            box-shadow: 0 4px 12px rgba(0, 82, 60, 0.18) !important;
            transition: all 0.2s ease !important;
        }

        .ifs-educore-btn-load:hover {
            background: #047857 !important;
        }

        .ifs-educore-promotion-target-bar {
            padding: 16px 20px;
            background: #f0fdf4;
            border: 1.5px solid #bbf7d0;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }

        .ifs-educore-promotion-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 13px;
        }

        .ifs-educore-promotion-table th {
            padding: 12px 14px;
            color: #475569;
            background: #f8fafc;
            border-bottom: 2px solid #e2e8f0;
            font-size: 11px;
            text-transform: capitalize;
            font-weight: 800;
        }

        .ifs-educore-promotion-table td {
            padding: 12px 14px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        .ifs-educore-rank-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 2px 8px;
            border-radius: 6px;
            font-size: 11.5px;
            font-weight: 800;
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #cbd5e1;
        }

        .ifs-educore-rank-badge.top {
            background: #ecfdf5;
            color: #047857;
            border-color: #a7f3d0;
        }

        .ifs-educore-status-pill {
            display: inline-flex;
            align-items: center;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 800;
        }

        .status-pass {
            background: #ecfdf5;
            color: #059669;
            border: 1px solid #bbf7d0;
        }

        .status-fail {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        .ifs-educore-cell-input-sm {
            height: 36px;
            border: 1.5px solid #cbd5e1;
            border-radius: 6px;
            padding: 0 10px;
            font-size: 13px;
            color: #0f172a;
            background: #ffffff;
            outline: none;
            width: 75px;
            box-sizing: border-box;
            transition: border-color 0.2s;
        }

        .ifs-educore-cell-input-sm:focus {
            border-color: #00523c;
            box-shadow: 0 0 0 2px rgba(0, 82, 60, 0.1);
        }

        tr.row-failed {
            background-color: #fffbfa;
        }
    </style>

    <div class="ifs-educore-promotion-root">

        <?php if ( ! empty( $notice_msg ) ) : ?>
            <div class="notice notice-success is-dismissible" style="padding:12px; margin:0 0 16px 0; font-weight:700; border-left:4px solid #00523c; background:#ecfdf5; color:#065f46; border-radius:8px;">
                <span class="dashicons dashicons-yes-alt" style="vertical-align:middle; margin-right:4px;"></span>
                <?php echo esc_html( $notice_msg ); ?>
            </div>
        <?php endif; ?>

        <!-- Step 1: Exam & Source Cohort Selection (Neo-Bento Layout) -->
        <div class="ifs-educore-bento-filter-card">
            <form method="GET" action="<?php echo esc_url( $base_url ); ?>" id="ifs_educore_promotion_filter_form">
                <input type="hidden" name="page" value="school_management_system">
                <input type="hidden" name="tab" value="students">
                <input type="hidden" name="sub" value="promotion">

                <div class="ifs-educore-filter-grid">
                    <!-- Exam Selection -->
                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-form-label" for="ifs_educore_prom_exam_select">
                            <span class="dashicons dashicons-calendar-alt" style="font-size:14px; width:14px; height:14px; color:#00523c;"></span>
                            <?php esc_html_e( '1. Evaluation Exam', 'ifsedu-school-management' ); ?> <span style="color:#ef4444;">*</span>
                        </label>
                        <select name="exam_id" id="ifs_educore_prom_exam_select" class="ifs-educore-select" required>
                            <option value=""><?php esc_html_e( '-- Choose Exam --', 'ifsedu-school-management' ); ?></option>
                            <?php foreach ( $exams as $ex ) : 
                                $ex_y = ! empty( $ex->start_date ) ? substr( (string) $ex->start_date, 0, 4 ) : ( ! empty( $ex->att_start_date ) ? substr( (string) $ex->att_start_date, 0, 4 ) : current_time( 'Y' ) );
                                $ex_label = trim( (string) $ex->exam_name ) . ' (' . $ex_y . ')';
                            ?>
                                <option value="<?php echo (int) $ex->id; ?>" <?php selected( $filter_exam, (int) $ex->id ); ?>>
                                    <?php echo esc_html( $ex_label ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Source Class Selection -->
                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-form-label" for="ifs_educore_source_class_select">
                            <span class="dashicons dashicons-welcome-learn-more" style="font-size:14px; width:14px; height:14px; color:#00523c;"></span>
                            <?php esc_html_e( '2. Source Class', 'ifsedu-school-management' ); ?> <span style="color:#ef4444;">*</span>
                        </label>
                        <select name="class_name" id="ifs_educore_source_class_select" class="ifs-educore-select" required>
                            <option value=""><?php esc_html_e( '-- Choose Class --', 'ifsedu-school-management' ); ?></option>
                            <?php foreach ( $academic_classes as $cls_name ) : ?>
                                <option value="<?php echo esc_attr( $cls_name ); ?>" <?php selected( $filter_class, $cls_name ); ?>>
                                    <?php echo esc_html( preg_match( '/^class\s+/i', $cls_name ) ? $cls_name : 'Class ' . $cls_name ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Source Section Selection -->
                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-form-label" for="ifs_educore_source_section_select">
                            <span class="dashicons dashicons-groups" style="font-size:14px; width:14px; height:14px; color:#00523c;"></span>
                            <?php esc_html_e( '3. Section (Optional)', 'ifsedu-school-management' ); ?>
                        </label>
                        <select name="section_name" id="ifs_educore_source_section_select" class="ifs-educore-select">
                            <option value=""><?php esc_html_e( '-- All Sections --', 'ifsedu-school-management' ); ?></option>
                            <?php foreach ( $available_sections as $sec_val ) : ?>
                                <option value="<?php echo esc_attr( $sec_val ); ?>" <?php selected( $filter_section, $sec_val ); ?>>
                                    <?php echo esc_html( $sec_val ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Fetch Button -->
                    <div>
                        <button type="submit" class="ifs-educore-btn-load">
                            <span class="dashicons dashicons-search" style="font-size:15px; width:15px; height:15px;"></span>
                            <?php esc_html_e( 'Load Merit', 'ifsedu-school-management' ); ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            var nonce = '<?php echo esc_js( wp_create_nonce( "ifs_educore_promotion_nonce" ) ); ?>';

            $('#ifs_educore_source_class_select').on('change', function() {
                var selectedClass = $(this).val();
                var $secSelect = $('#ifs_educore_source_section_select');
                $secSelect.html('<option value=""><?php echo esc_js( __( '-- Loading... --', 'ifsedu-school-management' ) ); ?></option>');

                if (!selectedClass) {
                    $secSelect.html('<option value=""><?php echo esc_js( __( '-- All Sections --', 'ifsedu-school-management' ) ); ?></option>');
                    return;
                }

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'ifs_educore_get_target_sections_promotion',
                        security: nonce,
                        class_name: selectedClass
                    },
                    success: function(response) {
                        $secSelect.empty().append($('<option>', {
                            value: '',
                            text: '<?php echo esc_js( __( '-- All Sections --', 'ifsedu-school-management' ) ); ?>'
                        }));

                        if (response.success && response.data.length > 0) {
                            $.each(response.data, function(i, sec) {
                                $secSelect.append($('<option>', {
                                    value: sec,
                                    text: sec
                                }));
                            });
                        }
                    }
                });
            });
        });
        </script>

        <!-- Step 2: Promotion Processing Matrix Table -->
        <?php if ( 0 < $filter_exam && ! empty( $filter_class ) ) : ?>
            <div class="ifs-educore-bento-card">
                <form method="POST" action="">
                    <?php wp_nonce_field( 'execute_promotion_action', 'ifs_educore_promotion_nonce' ); ?>

                    <!-- Target Class & Section Configuration Strip -->
                    <div class="ifs-educore-promotion-target-bar">
                        <div style="display:flex; align-items:center; gap:16px; flex-wrap:wrap;">
                            <div>
                                <label class="ifs-educore-form-label" style="color:#065f46;"><?php esc_html_e( 'Promote To Next Class', 'ifsedu-school-management' ); ?> <span style="color:#dc2626;">*</span></label>
                                <select name="target_class" id="ifs_educore_target_class_dropdown" class="ifs-educore-select" style="min-width:200px; background:#ffffff;" required>
                                    <option value=""><?php esc_html_e( '-- Choose Target Class --', 'ifsedu-school-management' ); ?></option>
                                    <?php foreach ( $academic_classes as $cls_name ) : ?>
                                        <option value="<?php echo esc_attr( $cls_name ); ?>">
                                            <?php echo esc_html( preg_match( '/^class\s+/i', $cls_name ) ? $cls_name : 'Class ' . $cls_name ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <label class="ifs-educore-form-label" style="color:#065f46;"><?php esc_html_e( 'Assign Section (Optional)', 'ifsedu-school-management' ); ?></label>
                                <select name="bulk_target_section" id="ifs_educore_bulk_target_section" class="ifs-educore-select" style="min-width:180px; background:#ffffff;">
                                    <option value=""><?php esc_html_e( '-- Select Class First --', 'ifsedu-school-management' ); ?></option>
                                </select>
                            </div>
                        </div>

                        <div style="display:flex; gap:10px; align-items:center;">
                            <button type="button" id="ifs_educore_btn_autofill_rolls" class="ifs-educore-select" style="width:auto; height:42px; cursor:pointer; background:#ffffff; font-weight:700;">
                                <span class="dashicons dashicons-sort" style="vertical-align:middle;"></span> <?php esc_html_e( 'Auto-Assign Rolls by Merit', 'ifsedu-school-management' ); ?>
                            </button>

                            <button type="submit" name="educore_execute_promotion" class="ifs-educore-btn-load" style="width:auto; height:42px; padding:0 24px;">
                                <span class="dashicons dashicons-saved" style="font-size:15px; width:15px; height:15px;"></span> <?php esc_html_e( 'Execute Promotion', 'ifsedu-school-management' ); ?>
                            </button>
                        </div>
                    </div>

                    <div style="overflow-x:auto;">
                        <table class="ifs-educore-promotion-table" id="ifs_educore_promotion_table">
                            <thead>
                                <tr>
                                    <th style="width: 4%;"><input type="checkbox" id="ifs_educore_check_all_promoted"></th>
                                    <th style="width: 7%;"><?php esc_html_e( 'Class Rank', 'ifsedu-school-management' ); ?></th>
                                    <th style="width: 7%;"><?php esc_html_e( 'Sec Rank', 'ifsedu-school-management' ); ?></th>
                                    <th style="width: 7%;"><?php esc_html_e( 'Curr Roll', 'ifsedu-school-management' ); ?></th>
                                    <th style="width: 11%;"><?php esc_html_e( 'Student ID', 'ifsedu-school-management' ); ?></th>
                                    <th style="text-align:left;"><?php esc_html_e( 'Student Name', 'ifsedu-school-management' ); ?></th>
                                    <th style="width: 10%;"><?php esc_html_e( 'Total Marks', 'ifsedu-school-management' ); ?></th>
                                    <th style="width: 8%;"><?php esc_html_e( 'GPA', 'ifsedu-school-management' ); ?></th>
                                    <th style="width: 9%;"><?php esc_html_e( 'Exam Status', 'ifsedu-school-management' ); ?></th>
                                    <th style="width: 11%;"><?php esc_html_e( 'New Roll No.', 'ifsedu-school-management' ); ?> <span style="color:#dc2626;">*</span></th>
                                    <th style="width: 11%;"><?php esc_html_e( 'New Section', 'ifsedu-school-management' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ( ! empty( $display_candidates ) ) : 
                                    foreach ( $display_candidates as $item ) : 
                                        $s      = $item['student'];
                                        $failed = $item['failed'];
                                        $c_pos  = $item['class_position'];
                                        $s_pos  = $item['section_position'];
                                ?>
                                    <tr class="<?php echo $failed ? 'row-failed' : 'row-passed'; ?>">
                                        <td>
                                            <input type="checkbox" name="promote_student[]" value="<?php echo esc_attr( (string) $s->id ); ?>" class="st-promote-check" <?php echo ! $failed ? 'checked' : ''; ?>>
                                        </td>
                                        <td>
                                            <?php if ( ! $failed && 0 < $c_pos ) : ?>
                                                <span class="ifs-educore-rank-badge <?php echo 3 >= $c_pos ? 'top' : ''; ?>">#<?php echo esc_html( (string) $c_pos ); ?></span>
                                            <?php else : ?>
                                                <span style="color:#94a3b8;">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ( ! $failed && 0 < $s_pos ) : ?>
                                                <span class="ifs-educore-rank-badge">#<?php echo esc_html( (string) $s_pos ); ?></span>
                                            <?php else : ?>
                                                <span style="color:#94a3b8;">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong>#<?php echo esc_html( (string) $s->roll_no ); ?></strong></td>
                                        <td><code><?php echo esc_html( strtoupper( (string) $s->student_id ) ); ?></code></td>
                                        <td style="text-align:left; font-weight:700; color:#0f172a;"><?php echo esc_html( (string) $s->full_name ); ?></td>
                                        <td><strong><?php echo esc_html( (string) (float) $item['total'] ); ?></strong></td>
                                        <td style="font-weight:800; color:<?php echo $failed ? '#dc2626' : '#00523c'; ?>;"><?php echo esc_html( number_format( (float) $item['gpa'], 2 ) ); ?></td>
                                        <td>
                                            <span class="ifs-educore-status-pill <?php echo $failed ? 'status-fail' : 'status-pass'; ?>">
                                                <?php echo $failed ? esc_html__( 'FAIL', 'ifsedu-school-management' ) : esc_html__( 'PASS', 'ifsedu-school-management' ); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <input type="number" name="new_roll[<?php echo esc_attr( (string) $s->id ); ?>]" 
                                                   class="ifs-educore-cell-input-sm st-new-roll" 
                                                   value="<?php echo ! $failed ? esc_attr( (string) $c_pos ) : esc_attr( (string) $s->roll_no ); ?>" 
                                                   data-merit-pos="<?php echo esc_attr( (string) ( 0 < $c_pos ? $c_pos : 999 ) ); ?>">
                                        </td>
                                        <td>
                                            <input type="text" name="new_section[<?php echo esc_attr( (string) $s->id ); ?>]" 
                                                   class="ifs-educore-cell-input-sm st-new-section" 
                                                   value="<?php echo esc_attr( (string) $s->section_name ); ?>" 
                                                   style="width:90px;" placeholder="<?php esc_attr_e( 'Section', 'ifsedu-school-management' ); ?>">
                                        </td>
                                    </tr>
                                <?php endforeach; else : ?>
                                    <tr>
                                        <td colspan="11" style="padding:40px; color:#94a3b8; text-align:center;">
                                            <?php esc_html_e( 'No student examination results found matching the selected class and exam.', 'ifsedu-school-management' ); ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </form>
            </div>

            <!-- Client-side Bulk Roll & Target Section Helpers -->
            <script type="text/javascript">
            document.addEventListener('DOMContentLoaded', function() {
                var checkAll = document.getElementById('ifs_educore_check_all_promoted');
                if (checkAll) {
                    checkAll.addEventListener('change', function() {
                        document.querySelectorAll('.st-promote-check').forEach(function(cb) {
                            cb.checked = checkAll.checked;
                        });
                    });
                }

                // Auto-fill rolls sequentially based on Merit Position.
                var autoRollBtn = document.getElementById('ifs_educore_btn_autofill_rolls');
                if (autoRollBtn) {
                    autoRollBtn.addEventListener('click', function() {
                        var rankCounter = 1;
                        document.querySelectorAll('#ifs_educore_promotion_table tbody tr.row-passed').forEach(function(row) {
                            var rollInp = row.querySelector('.st-new-roll');
                            var cb = row.querySelector('.st-promote-check');
                            if (rollInp && cb && cb.checked) {
                                rollInp.value = rankCounter++;
                            }
                        });
                    });
                }

                // Dynamic Section Loader for Target Class.
                var targetClassDropdown   = document.getElementById('ifs_educore_target_class_dropdown');
                var targetSectionDropdown = document.getElementById('ifs_educore_bulk_target_section');

                if (targetClassDropdown && targetSectionDropdown) {
                    targetClassDropdown.addEventListener('change', function() {
                        var targetCls = this.value;
                        targetSectionDropdown.innerHTML = '<option value=""><?php echo esc_js( __( '-- Loading... --', 'ifsedu-school-management' ) ); ?></option>';

                        if (!targetCls) {
                            targetSectionDropdown.innerHTML = '<option value=""><?php echo esc_js( __( '-- Select Class First --', 'ifsedu-school-management' ) ); ?></option>';
                            return;
                        }

                        jQuery.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'ifs_educore_get_target_sections_promotion',
                                security: '<?php echo esc_js( wp_create_nonce( "ifs_educore_promotion_nonce" ) ); ?>',
                                class_name: targetCls
                            },
                            success: function(response) {
                                targetSectionDropdown.innerHTML = '';
                                var defaultOpt = document.createElement('option');
                                defaultOpt.value = '';
                                defaultOpt.textContent = '<?php echo esc_js( __( '-- Apply Bulk Section --', 'ifsedu-school-management' ) ); ?>';
                                targetSectionDropdown.appendChild(defaultOpt);

                                if (response.success && response.data.length > 0) {
                                    response.data.forEach(function(sec) {
                                        var opt = document.createElement('option');
                                        opt.value = sec;
                                        opt.textContent = sec;
                                        targetSectionDropdown.appendChild(opt);
                                    });
                                } else {
                                    defaultOpt.textContent = '<?php echo esc_js( __( '-- No Sections Found --', 'ifsedu-school-management' ) ); ?>';
                                }
                            }
                        });
                    });

                    // Sync bulk section selector across individual inputs.
                    targetSectionDropdown.addEventListener('change', function() {
                        var chosenSec = this.value;
                        if (chosenSec) {
                            document.querySelectorAll('.st-new-section').forEach(function(inp) {
                                inp.value = chosenSec;
                            });
                        }
                    });
                }
            });
            </script>
        <?php endif; ?>

    </div>
    <?php
}
<?php
/**
 * Exam Marks Entry Status & Progress Report Engine
 * File: inc/results/exams-mark-report.php
 * Text Domain: ifsedu-school-management
 * Architecture: Modern Neo-Bento Interface with Enhanced Visual Hierarchy & Quick Matrix Jump Links
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

if ( ! function_exists( 'educore_mark_report_get_table' ) ) {
    /**
     * Helper: Defensive table name resolver for mark report module
     */
    function educore_mark_report_get_table( string $key ): string {
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

/**
 * Render Exam Marks Entry Completion Report View
 */
function educore_exam_mark_report_view(): void {
    global $wpdb;
    $current_user = wp_get_current_user();

    $table_students = educore_mark_report_get_table( 'students' );
    $table_exams    = educore_mark_report_get_table( 'exams' );
    $table_results  = educore_mark_report_get_table( 'results' );
    $table_units    = educore_mark_report_get_table( 'academic_units' );
    $table_subjects = educore_mark_report_get_table( 'subjects' );
    $table_staff    = educore_mark_report_get_table( 'staff' );
    $table_exam_att = educore_mark_report_get_table( 'exam_attendance' );

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
        wp_die(
            esc_html__( 'You do not have sufficient permissions to view exam mark reports.', 'ifsedu-school-management' ),
            403
        );
    }

    // Request Parameters
    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $filter_exam    = isset( $_GET['exam_id'] ) ? absint( wp_unslash( $_GET['exam_id'] ) ) : 0;
    $filter_class   = isset( $_GET['class_name'] ) ? sanitize_text_field( wp_unslash( $_GET['class_name'] ) ) : '';
    $filter_section = isset( $_GET['section_name'] ) ? sanitize_text_field( wp_unslash( $_GET['section_name'] ) ) : '';
    $status_filter  = isset( $_GET['status_filter'] ) ? sanitize_key( wp_unslash( $_GET['status_filter'] ) ) : 'all';
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    $exams = $wpdb->get_results(
        $wpdb->prepare(
            'SELECT id, exam_name, class_name, subject_ids FROM %i ORDER BY id DESC',
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

    // Dataset collection for progress tracking
    $subjects_progress_list = array();
    $total_students_count   = 0;
    $completed_subs_count   = 0;
    $pending_subs_count     = 0;
    $total_subs_count       = 0;

    if ( $filter_exam > 0 && ! empty( $filter_class ) ) {
        if ( ! empty( $filter_section ) ) {
            $total_students_count = (int) $wpdb->get_var(
                $wpdb->prepare(
                    'SELECT COUNT(*) FROM %i WHERE status = %s AND class_name = %s AND section_name = %s',
                    $table_students,
                    'Active',
                    $filter_class,
                    $filter_section
                )
            );
        } else {
            $total_students_count = (int) $wpdb->get_var(
                $wpdb->prepare(
                    'SELECT COUNT(*) FROM %i WHERE status = %s AND class_name = %s',
                    $table_students,
                    'Active',
                    $filter_class
                )
            );
        }

        $exam_row = null;
        foreach ( $exams as $ex ) {
            if ( (int) $ex->id === $filter_exam ) {
                $exam_row = $ex;
                break;
            }
        }

        $exam_subject_ids = array();
        if ( $exam_row && ! empty( $exam_row->subject_ids ) ) {
            $decoded_map = json_decode( (string) $exam_row->subject_ids, true );
            if ( isset( $decoded_map[ $filter_class ] ) && is_array( $decoded_map[ $filter_class ] ) ) {
                $exam_subject_ids = array_map( 'absint', $decoded_map[ $filter_class ] );
            }
        }

        if ( ! empty( $filter_section ) ) {
            $raw_subs = $wpdb->get_results(
                $wpdb->prepare(
                    'SELECT DISTINCT s.* FROM %i s 
                     INNER JOIN %i u ON s.class_id = u.id 
                     WHERE u.class_name = %s AND u.section_name = %s 
                     ORDER BY s.subject_order ASC, s.subject_name ASC',
                    $table_subjects,
                    $table_units,
                    $filter_class,
                    $filter_section
                )
            );
        } else {
            $raw_subs = $wpdb->get_results(
                $wpdb->prepare(
                    'SELECT DISTINCT s.* FROM %i s 
                     INNER JOIN %i u ON s.class_id = u.id 
                     WHERE u.class_name = %s 
                     ORDER BY s.subject_order ASC, s.subject_name ASC',
                    $table_subjects,
                    $table_units,
                    $filter_class
                )
            );
        }

        if ( ! empty( $exam_subject_ids ) && ! empty( $raw_subs ) ) {
            $raw_subs = array_values( array_filter( $raw_subs, static function( $sub ) use ( $exam_subject_ids ): bool {
                return in_array( (int) $sub->id, $exam_subject_ids, true );
            } ) );
        }

        $seen_sub_names = array();
        if ( ! empty( $raw_subs ) ) {
            foreach ( $raw_subs as $s_item ) {
                $s_norm = trim( strtolower( (string) $s_item->subject_name ) );
                if ( ! in_array( $s_norm, $seen_sub_names, true ) ) {
                    $seen_sub_names[] = $s_norm;

                    // Count students who either have a saved score OR are marked Absent
                    if ( ! empty( $filter_section ) ) {
                        $entered_count = (int) $wpdb->get_var(
                            $wpdb->prepare(
                                'SELECT COUNT(DISTINCT st.id) FROM %i st 
                                 LEFT JOIN %i r ON r.student_id = st.id AND r.exam_id = %d AND r.subject_name = %s AND r.obtained_marks IS NOT NULL AND r.obtained_marks != ""
                                 LEFT JOIN %i ea ON ea.student_id = st.id AND ea.exam_id = %d AND ea.subject_name = %s AND ea.status = "Absent"
                                 WHERE st.status = "Active" AND st.class_name = %s AND st.section_name = %s AND (r.id IS NOT NULL OR ea.id IS NOT NULL)',
                                $table_students,
                                $table_results,
                                $filter_exam,
                                $s_item->subject_name,
                                $table_exam_att,
                                $filter_exam,
                                $s_item->subject_name,
                                $filter_class,
                                $filter_section
                            )
                        );
                    } else {
                        $entered_count = (int) $wpdb->get_var(
                            $wpdb->prepare(
                                'SELECT COUNT(DISTINCT st.id) FROM %i st 
                                 LEFT JOIN %i r ON r.student_id = st.id AND r.exam_id = %d AND r.subject_name = %s AND r.obtained_marks IS NOT NULL AND r.obtained_marks != ""
                                 LEFT JOIN %i ea ON ea.student_id = st.id AND ea.exam_id = %d AND ea.subject_name = %s AND ea.status = "Absent"
                                 WHERE st.status = "Active" AND st.class_name = %s AND (r.id IS NOT NULL OR ea.id IS NOT NULL)',
                                $table_students,
                                $table_results,
                                $filter_exam,
                                $s_item->subject_name,
                                $table_exam_att,
                                $filter_exam,
                                $s_item->subject_name,
                                $filter_class
                            )
                        );
                    }

                    $is_complete = ( $total_students_count > 0 && $entered_count >= $total_students_count );
                    if ( $is_complete ) {
                        $completed_subs_count++;
                    } else {
                        $pending_subs_count++;
                    }
                    $total_subs_count++;

                    $s_item->entered_count  = $entered_count;
                    $s_item->total_students = $total_students_count;
                    $s_item->is_complete    = $is_complete;

                    // Apply status filter for display
                    if ( 'complete' === $status_filter && ! $is_complete ) {
                        continue;
                    }
                    if ( 'pending' === $status_filter && $is_complete ) {
                        continue;
                    }

                    $subjects_progress_list[] = $s_item;
                }
            }
        }
    }

    $overall_progress_pct = ( $total_subs_count > 0 ) ? round( ( $completed_subs_count / $total_subs_count ) * 100 ) : 0;
    ?>

    <style>
        .ifs-educore-mark-report-root {
            max-width: 100%;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #0f172a;
        }
        .ifs-educore-bento-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 4px 20px -3px rgba(0, 0, 0, 0.03);
            box-sizing: border-box;
        }
        .ifs-educore-filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)) 130px;
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
            gap: 6px;
            font-size: 12px;
            font-weight: 700;
            color: #475569;
            text-transform: capitalize;
            letter-spacing: 0.3px;
            margin-bottom: 8px;
        }
        .ifs-educore-select-field {
            width: 100% !important;
            height: 44px !important;
            padding: 0 36px 0 14px !important;
            border: 1.5px solid #cbd5e1 !important;
            border-radius: 10px !important;
            font-size: 13.5px !important;
            font-weight: 600 !important;
            color: #0f172a !important;
            background-color: #ffffff !important;
            background-image: url('data:image/svg+xml;utf8,<svg fill="%2364748b" height="20" viewBox="0 0 24 24" width="20" xmlns="http://www.w3.org/2000/svg"><path d="M7 10l5 5 5-5z"/></svg>') !important;
            background-repeat: no-repeat !important;
            background-position: right 12px center !important;
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
        .ifs-educore-btn-load {
            width: 100% !important;
            height: 44px !important;
            background: #00523c !important;
            color: #ffffff !important;
            font-weight: 700 !important;
            font-size: 14px !important;
            border: none !important;
            border-radius: 10px !important;
            cursor: pointer !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 6px !important;
            box-shadow: 0 4px 14px rgba(0, 82, 60, 0.2) !important;
            transition: all 0.2s ease !important;
        }
        .ifs-educore-btn-load:hover {
            background: #047857 !important;
        }

        /* Hero Progress Banner */
        .ifs-mark-progress-banner {
            background: linear-gradient(135deg, #00523c 0%, #04392b 100%);
            border-radius: 16px;
            padding: 30px 36px;
            color: #ffffff;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            box-shadow: 0 12px 30px -6px rgba(0, 82, 60, 0.3);
            flex-wrap: wrap;
            gap: 20px;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .ifs-mark-progress-bar-track {
            width: 100%;
            height: 8px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 6px;
            margin-top: 14px;
            overflow: hidden;
        }
        .ifs-mark-progress-bar-fill {
            height: 100%;
            background: #34d399;
            border-radius: 6px;
            transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Filter Tab Bar */
        .ifs-mark-filter-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 22px;
            flex-wrap: wrap;
        }
        .ifs-mark-tab-pill {
            background: #ffffff;
            border: 1.5px solid #cbd5e1;
            color: #475569;
            padding: 8px 18px;
            border-radius: 24px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .ifs-mark-tab-pill:hover {
            border-color: #00523c;
            color: #00523c;
            background: #f0fdf4;
        }
        .ifs-mark-tab-pill.is-active {
            background: #00523c;
            color: #ffffff;
            border-color: #00523c;
            box-shadow: 0 4px 12px rgba(0, 82, 60, 0.25);
        }

        /* Subject Cards Grid */
        .ifs-mark-cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(310px, 1fr));
            gap: 20px;
        }
        .ifs-mark-subject-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 22px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 18px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }
        .ifs-mark-subject-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 28px -6px rgba(0, 0, 0, 0.08);
            border-color: #cbd5e1;
        }
        .ifs-mark-subject-card.is-complete {
            border-left: 5px solid #059669;
        }
        .ifs-mark-subject-card.is-pending {
            border-left: 5px solid #d97706;
        }
        .ifs-mark-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
        }
        .ifs-mark-card-title {
            margin: 0;
            font-size: 16px;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.2px;
        }
        .ifs-mark-card-code {
            font-size: 12px;
            color: #64748b;
            font-weight: 600;
            margin-top: 4px;
        }
        .ifs-mark-status-pill {
            font-size: 11px;
            font-weight: 800;
            padding: 5px 12px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            flex-shrink: 0;
            letter-spacing: 0.2px;
        }
        .ifs-mark-status-pill.complete {
            background: #ecfdf5;
            color: #047857;
            border: 1px solid #a7f3d0;
        }
        .ifs-mark-status-pill.pending {
            background: #fffbeb;
            color: #b45309;
            border: 1px solid #fde68a;
        }
        .ifs-mark-card-footer {
            border-top: 1px solid #f1f5f9;
            padding-top: 14px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .ifs-mark-jump-btn {
            background: #f8fafc;
            border: 1.5px solid #cbd5e1;
            color: #334155;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
        }
        .ifs-mark-jump-btn:hover {
            background: #00523c;
            color: #ffffff;
            border-color: #00523c;
            box-shadow: 0 4px 12px rgba(0, 82, 60, 0.25);
        }
    </style>

    <div class="ifs-educore-mark-report-root">

        <!-- Filter Bento Card -->
        <div class="ifs-educore-bento-card">
            <form method="GET" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" id="educoreMarkReportForm">
                <input type="hidden" name="page" value="school_management_system">
                <input type="hidden" name="tab" value="results">
                <input type="hidden" name="sub" value="mark-report">

                <div class="ifs-educore-filter-grid">
                    <!-- 1. Exam Selection -->
                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-form-label" for="ifs_educore_mrep_exam_select">
                            <span class="dashicons dashicons-calendar-alt" style="font-size:15px; width:15px; height:15px; color:#00523c;"></span>
                            <?php esc_html_e( '1. Select Exam', 'ifsedu-school-management' ); ?> <span style="color:#ef4444;">*</span>
                        </label>
                        <select name="exam_id" id="ifs_educore_mrep_exam_select" class="ifs-educore-select-field" required>
                            <option value=""><?php esc_html_e( '-- Choose Exam --', 'ifsedu-school-management' ); ?></option>
                            <?php foreach ( $exams as $ex ) : ?>
                                <option value="<?php echo (int) $ex->id; ?>" <?php selected( $filter_exam, (int) $ex->id ); ?>>
                                    <?php echo esc_html( (string) $ex->exam_name ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- 2. Class Selection -->
                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-form-label" for="ifs_educore_mrep_class_select">
                            <span class="dashicons dashicons-welcome-learn-more" style="font-size:15px; width:15px; height:15px; color:#00523c;"></span>
                            <?php esc_html_e( '2. Exam Class', 'ifsedu-school-management' ); ?> <span style="color:#ef4444;">*</span>
                        </label>
                        <select name="class_name" id="ifs_educore_mrep_class_select" class="ifs-educore-select-field" required <?php disabled( empty( $all_classes_raw ) ); ?>>
                            <option value=""><?php esc_html_e( '-- Choose Class --', 'ifsedu-school-management' ); ?></option>
                            <?php foreach ( $all_classes_raw as $cls_name ) : ?>
                                <option value="<?php echo esc_attr( (string) $cls_name ); ?>" <?php selected( $filter_class, (string) $cls_name ); ?>>
                                    <?php echo esc_html( preg_match( '/^class\s+/i', (string) $cls_name ) ? (string) $cls_name : 'Class ' . (string) $cls_name ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- 3. Section Selection -->
                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-form-label" for="ifs_educore_mrep_section_select">
                            <span class="dashicons dashicons-groups" style="font-size:15px; width:15px; height:15px; color:#00523c;"></span>
                            <?php esc_html_e( '3. Section (Optional)', 'ifsedu-school-management' ); ?>
                        </label>
                        <select name="section_name" id="ifs_educore_mrep_section_select" class="ifs-educore-select-field">
                            <option value=""><?php esc_html_e( '-- All Sections --', 'ifsedu-school-management' ); ?></option>
                            <?php foreach ( $available_sections as $sec_val ) : ?>
                                <option value="<?php echo esc_attr( (string) $sec_val ); ?>" <?php selected( $filter_section, (string) $sec_val ); ?>>
                                    <?php echo esc_html( (string) $sec_val ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- 4. Submit Button -->
                    <div>
                        <button type="submit" class="ifs-educore-btn-load">
                            <span class="dashicons dashicons-search" style="font-size:16px; width:16px; height:16px;"></span>
                            <?php esc_html_e( 'Check Status', 'ifsedu-school-management' ); ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Cascading AJAX Script for Exam -> Class -> Section -->
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            var nonce        = '<?php echo esc_js( wp_create_nonce( "ifs_educore_report_nonce" ) ); ?>';
            var examClassMap = <?php echo wp_json_encode( ! empty( $exam_class_map ) ? $exam_class_map : array() ); ?>;
            var allClasses   = <?php echo wp_json_encode( ! empty( $all_classes_raw ) ? $all_classes_raw : array() ); ?>;
            var currentClass = "<?php echo esc_js( $filter_class ); ?>";
            var currentSec   = "<?php echo esc_js( $filter_section ); ?>";

            function populateClasses(examId, selectedClass) {
                var $classSelect = $('#ifs_educore_mrep_class_select');
                $classSelect.html('<option value=""><?php echo esc_js( __( '-- Choose Class --', 'ifsedu-school-management' ) ); ?></option>');

                if (!examId) {
                    $classSelect.prop('disabled', true);
                    return;
                }

                var classesToLoad = (examClassMap[examId] && examClassMap[examId].length > 0) ? examClassMap[examId] : allClasses;
                $.each(classesToLoad, function(i, cls) {
                    var sel = (cls === selectedClass) ? 'selected' : '';
                    var disp = (/^class\s+/i.test(cls)) ? cls : 'Class ' + cls;
                    $classSelect.append('<option value="' + cls + '" ' + sel + '>' + disp + '</option>');
                });
                $classSelect.prop('disabled', false);
            }

            $('#ifs_educore_mrep_exam_select').on('change', function() {
                populateClasses($(this).val(), '');
                $('#ifs_educore_mrep_class_select').trigger('change');
            });

            $('#ifs_educore_mrep_class_select').on('change', function() {
                var selectedClass = $(this).val();
                var $secSelect    = $('#ifs_educore_mrep_section_select');

                $secSelect.html('<option value=""><?php echo esc_js( __( '-- All Sections --', 'ifsedu-school-management' ) ); ?></option>');
                if (!selectedClass) return;

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
                            var secOpts = '<option value=""><?php echo esc_js( __( '-- All Sections --', 'ifsedu-school-management' ) ); ?></option>';
                            $.each(response.data, function(i, sec) {
                                var sel = (sec === currentSec) ? 'selected' : '';
                                secOpts += '<option value="' + sec + '" ' + sel + '>' + sec + '</option>';
                            });
                            $secSelect.html(secOpts);
                        }
                    }
                });
            });

            if ($('#ifs_educore_mrep_exam_select').val()) {
                populateClasses($('#ifs_educore_mrep_exam_select').val(), currentClass);
            }
        });
        </script>

        <!-- Results Display Grid -->
        <?php if ( $filter_exam > 0 && ! empty( $filter_class ) ) : 
            $tab_base_args = array(
                'page'         => 'school_management_system',
                'tab'          => 'results',
                'sub'          => 'mark-report',
                'exam_id'      => $filter_exam,
                'class_name'   => $filter_class,
                'section_name' => $filter_section,
            );
        ?>
            
            <!-- Progress Banner -->
            <div class="ifs-mark-progress-banner">
                <div>
                    <span style="font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; background: rgba(255,255,255,0.18); padding: 5px 14px; border-radius: 20px;">
                        <?php esc_html_e( 'Entry Completion Summary', 'ifsedu-school-management' ); ?>
                    </span>
                    <h3 style="margin: 12px 0 4px 0; font-size: 24px; font-weight: 900; color: #ffffff;">
                        <?php echo esc_html( $filter_class ); ?> <?php echo ! empty( $filter_section ) ? '(Sec: ' . esc_html( $filter_section ) . ')' : ''; ?>
                    </h3>
                    <p style="margin: 0; font-size: 13.5px; color: #a7f3d0; font-weight: 600;">
                        <?php 
                        printf(
                            /* translators: 1: Completed subjects count, 2: Total subjects count */
                            esc_html__( '%1$d of %2$s subjects fully evaluated (%3$d Active Students)', 'ifsedu-school-management' ),
                            $completed_subs_count,
                            $total_subs_count,
                            $total_students_count
                        );
                        ?>
                    </p>
                </div>
                <div style="text-align: right;">
                    <div style="font-size: 36px; font-weight: 900; letter-spacing: -0.5px; line-height: 1;"><?php echo (int) $overall_progress_pct; ?>%</div>
                    <span style="font-size: 12px; font-weight: 700; color: #cbd5e1; display: inline-block; margin-top: 6px;"><?php esc_html_e( 'Overall Progress', 'ifsedu-school-management' ); ?></span>
                </div>
                <div class="ifs-mark-progress-bar-track">
                    <div class="ifs-mark-progress-bar-fill" style="width: <?php echo esc_attr( (string) min( 100, $overall_progress_pct ) ); ?>%;"></div>
                </div>
            </div>

            <!-- Filter Status Tab Pills -->
            <div class="ifs-mark-filter-tabs">
                <a href="<?php echo esc_url( add_query_arg( array_merge( $tab_base_args, array( 'status_filter' => 'all' ) ), admin_url( 'admin.php' ) ) ); ?>" class="ifs-mark-tab-pill <?php echo ( 'all' === $status_filter ) ? 'is-active' : ''; ?>">
                    <span class="dashicons dashicons-grid-view" style="font-size:15px; width:15px; height:15px;"></span>
                    <?php esc_html_e( 'All Subjects', 'ifsedu-school-management' ); ?> (<?php echo (int) $total_subs_count; ?>)
                </a>
                <a href="<?php echo esc_url( add_query_arg( array_merge( $tab_base_args, array( 'status_filter' => 'complete' ) ), admin_url( 'admin.php' ) ) ); ?>" class="ifs-mark-tab-pill <?php echo ( 'complete' === $status_filter ) ? 'is-active' : ''; ?>">
                    <span class="dashicons dashicons-yes-alt" style="font-size:15px; width:15px; height:15px;"></span>
                    <?php esc_html_e( 'Completed', 'ifsedu-school-management' ); ?> (<?php echo (int) $completed_subs_count; ?>)
                </a>
                <a href="<?php echo esc_url( add_query_arg( array_merge( $tab_base_args, array( 'status_filter' => 'pending' ) ), admin_url( 'admin.php' ) ) ); ?>" class="ifs-mark-tab-pill <?php echo ( 'pending' === $status_filter ) ? 'is-active' : ''; ?>">
                    <span class="dashicons dashicons-clock" style="font-size:15px; width:15px; height:15px;"></span>
                    <?php esc_html_e( 'Pending', 'ifsedu-school-management' ); ?> (<?php echo (int) $pending_subs_count; ?>)
                </a>
            </div>

            <!-- Subject Cards Matrix -->
            <?php if ( ! empty( $subjects_progress_list ) ) : ?>
                <div class="ifs-mark-cards-grid">
                    <?php foreach ( $subjects_progress_list as $sub_item ) : 
                        $is_done = (bool) $sub_item->is_complete;
                        $jump_url = add_query_arg(
                            array(
                                'page'         => 'school_management_system',
                                'tab'          => 'results',
                                'sub'          => 'marks',
                                'exam_id'      => $filter_exam,
                                'class_name'   => $filter_class,
                                'section_name' => $filter_section,
                                'subject_name' => $sub_item->subject_name,
                            ),
                            admin_url( 'admin.php' )
                        );
                    ?>
                        <div class="ifs-mark-subject-card <?php echo $is_done ? 'is-complete' : 'is-pending'; ?>">
                            <div>
                                <div class="ifs-mark-card-header">
                                    <div>
                                        <h4 class="ifs-mark-card-title"><?php echo esc_html( (string) $sub_item->subject_name ); ?></h4>
                                        <?php if ( ! empty( $sub_item->subject_code ) ) : ?>
                                            <div class="ifs-mark-card-code">Code: <?php echo esc_html( (string) $sub_item->subject_code ); ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <span class="ifs-mark-status-pill <?php echo $is_done ? 'complete' : 'pending'; ?>">
                                        <?php if ( $is_done ) : ?>
                                            <span class="dashicons dashicons-yes" style="font-size:13px; width:13px; height:13px;"></span> <?php esc_html_e( 'Completed', 'ifsedu-school-management' ); ?>
                                        <?php else : ?>
                                            <span class="dashicons dashicons-clock" style="font-size:13px; width:13px; height:13px;"></span> <?php esc_html_e( 'Pending', 'ifsedu-school-management' ); ?>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </div>

                            <div class="ifs-mark-card-footer">
                                <div style="font-size: 12.5px; font-weight: 700; color: #475569;">
                                    <?php 
                                    printf(
                                        /* translators: 1: Entered count, 2: Total students */
                                        esc_html__( 'Entries: %1$d / %2$d', 'ifsedu-school-management' ),
                                        (int) $sub_item->entered_count,
                                        (int) $sub_item->total_students
                                    );
                                    ?>
                                </div>
                                <a href="<?php echo esc_url( $jump_url ); ?>" class="ifs-mark-jump-btn">
                                    <span class="dashicons dashicons-edit" style="font-size:14px; width:14px; height:14px;"></span>
                                    <?php esc_html_e( 'Enter Marks &rarr;', 'ifsedu-school-management' ); ?>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <div class="ifs-educore-bento-card" style="text-align: center; color: #64748b; padding: 40px;">
                    <span class="dashicons dashicons-info" style="font-size: 32px; width: 32px; height: 32px; color: #94a3b8; margin-bottom: 8px;"></span>
                    <p style="margin: 0; font-weight: 700; font-size: 14px;"><?php esc_html_e( 'No subjects match the selected filter criteria.', 'ifsedu-school-management' ); ?></p>
                </div>
            <?php endif; ?>

        <?php else : ?>
            <div class="ifs-educore-bento-card" style="text-align: center; color: #64748b; padding: 40px;">
                <span class="dashicons dashicons-filter" style="font-size: 32px; width: 32px; height: 32px; color: #94a3b8; margin-bottom: 8px;"></span>
                <p style="margin: 0; font-weight: 700; font-size: 14px;"><?php esc_html_e( 'Please select an Examination and Class above to inspect marks entry status.', 'ifsedu-school-management' ); ?></p>
            </div>
        <?php endif; ?>

    </div>
    <?php
}
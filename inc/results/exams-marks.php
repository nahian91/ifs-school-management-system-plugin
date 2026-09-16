<?php
/**
 * High-End Marks Entry Matrix & Grading Evaluation Engine
 * File: inc/results/exams-marks.php
 * Text Domain: ifsedu-school-management
 * Architecture: Neo-Bento Interface with Real-time Auto Grading, Live Exam Attendance Linkage & Strict Attendance Lockout State
 * Teacher Scope: Restricts Class/Section/Subject dropdowns to `sms_teacher_subjects` for logged-in Teachers.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

if ( ! function_exists( 'educore_marks_get_table' ) ) {
    /**
     * Helper: Defensive table name resolver for results module
     */
    function educore_marks_get_table( string $key ): string {
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
// 1. AJAX HANDLERS (Filtered by Exam, Class, Section & Teacher Assignments)
// --------------------------------------------------------------------------
add_action( 'wp_ajax_ifs_educore_get_classes_by_exam_marks', 'ifs_educore_get_classes_by_exam_marks_handler' );
/**
 * AJAX Handler: Get classes filtered by exam and teacher assignment.
 */
function ifs_educore_get_classes_by_exam_marks_handler(): void {
    check_ajax_referer( 'ifs_educore_marks_nonce', 'security' );

    $current_user = wp_get_current_user();
    $is_admin     = current_user_can( 'manage_options' ) || in_array( 'administrator', (array) $current_user->roles, true );
    $is_staff     = false;

    if ( function_exists( 'educore_has_access' ) ) {
        $is_staff = educore_has_access( 'educore_manage_results' ) || educore_has_access( 'educore_manage_academics' );
    }

    global $wpdb;
    $table_staff              = educore_marks_get_table( 'staff' );
    $table_exams              = educore_marks_get_table( 'exams' );
    $table_units              = educore_marks_get_table( 'academic_units' );
    $table_teacher_subjects = educore_marks_get_table( 'teacher_subjects' );

    if ( ! $is_admin && ! $is_staff ) {
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
        wp_send_json_error( array( 'message' => esc_html__( 'Permission denied.', 'ifsedu-school-management' ) ) );
    }

    $exam_id = isset( $_POST['exam_id'] ) ? absint( wp_unslash( $_POST['exam_id'] ) ) : 0;
    if ( $exam_id <= 0 ) {
        wp_send_json_success( array() );
    }

    $raw_exam_classes = $wpdb->get_var( $wpdb->prepare( 'SELECT class_name FROM %i WHERE id = %d LIMIT 1', $table_exams, $exam_id ) );
    if ( empty( $raw_exam_classes ) ) {
        wp_send_json_success( array() );
    }

    $exam_classes = array_map( 'trim', explode( ',', (string) $raw_exam_classes ) );

    // Fetch class sort order dictionary.
    $class_order_rows = $wpdb->get_results( $wpdb->prepare( 'SELECT class_name, MIN(sort_order) as min_sort FROM %i GROUP BY class_name', $table_units ) );
    $class_order_map  = array();
    if ( ! empty( $class_order_rows ) ) {
        foreach ( $class_order_rows as $cor ) {
            $class_order_map[ $cor->class_name ] = (int) $cor->min_sort;
        }
    }

    if ( ! $is_admin ) {
        $teacher_id = (int) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT id FROM %i WHERE wp_user_id = %d OR email = %s OR full_name = %s LIMIT 1',
                $table_staff,
                $current_user->ID,
                $current_user->user_email,
                $current_user->display_name
            )
        );

        if ( $teacher_id > 0 ) {
            $teacher_classes = $wpdb->get_col(
                $wpdb->prepare(
                    'SELECT DISTINCT u.class_name 
                     FROM %i ts
                     INNER JOIN %i u ON ts.class_id = u.id 
                     WHERE ts.teacher_id = %d',
                    $table_teacher_subjects,
                    $table_units,
                    $teacher_id
                )
            );
            $exam_classes = array_intersect( $exam_classes, array_map( 'trim', (array) $teacher_classes ) );
        }
    }

    $exam_classes = array_values( array_unique( array_filter( $exam_classes ) ) );

    usort(
        $exam_classes,
        static function( $a, $b ) use ( $class_order_map ): int {
            $order_a = $class_order_map[ $a ] ?? 0;
            $order_b = $class_order_map[ $b ] ?? 0;
            if ( $order_a !== $order_b ) {
                return $order_a <=> $order_b;
            }
            return strnatcasecmp( $a, $b );
        }
    );

    wp_send_json_success( array_values( $exam_classes ) );
}

add_action( 'wp_ajax_ifs_educore_get_sections_by_class_marks', 'ifs_educore_get_sections_by_class_marks_handler' );
/**
 * AJAX Handler: Get sections filtered by class and teacher allocation.
 */
function ifs_educore_get_sections_by_class_marks_handler(): void {
    check_ajax_referer( 'ifs_educore_marks_nonce', 'security' );

    $current_user = wp_get_current_user();
    $is_admin     = current_user_can( 'manage_options' ) || in_array( 'administrator', (array) $current_user->roles, true );
    $is_staff     = false;

    if ( function_exists( 'educore_has_access' ) ) {
        $is_staff = educore_has_access( 'educore_manage_results' ) || educore_has_access( 'educore_manage_academics' );
    }

    global $wpdb;
    $table_staff = educore_marks_get_table( 'staff' );

    if ( ! $is_admin && ! $is_staff ) {
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
        wp_send_json_error( array( 'message' => esc_html__( 'Permission denied.', 'ifsedu-school-management' ) ) );
    }

    $table_units              = educore_marks_get_table( 'academic_units' );
    $table_teacher_subjects = educore_marks_get_table( 'teacher_subjects' );
    $class_name               = isset( $_POST['class_name'] ) ? sanitize_text_field( wp_unslash( $_POST['class_name'] ) ) : '';

    if ( empty( $class_name ) ) {
        wp_send_json_success( array() );
    }

    if ( ! $is_admin ) {
        $teacher_id = (int) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT id FROM %i WHERE wp_user_id = %d OR email = %s OR full_name = %s LIMIT 1',
                $table_staff,
                $current_user->ID,
                $current_user->user_email,
                $current_user->display_name
            )
        );

        if ( $teacher_id > 0 ) {
            $sections = $wpdb->get_col(
                $wpdb->prepare(
                    'SELECT DISTINCT u.section_name 
                     FROM %i ts
                     INNER JOIN %i u ON ts.class_id = u.id 
                     WHERE ts.teacher_id = %d AND u.class_name = %s AND u.section_name != %s 
                     ORDER BY u.sort_order ASC, u.section_name ASC',
                    $table_teacher_subjects,
                    $table_units,
                    $teacher_id,
                    $class_name,
                    ''
                )
            );
            wp_send_json_success( is_array( $sections ) ? $sections : array() );
        }
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

add_action( 'wp_ajax_ifs_educore_get_subjects_for_marks_matrix', 'ifs_educore_get_subjects_for_marks_matrix_handler' );
/**
 * AJAX Handler: Get subjects for the marks matrix.
 */
function ifs_educore_get_subjects_for_marks_matrix_handler(): void {
    check_ajax_referer( 'ifs_educore_marks_nonce', 'security' );

    $current_user = wp_get_current_user();
    $is_admin     = current_user_can( 'manage_options' ) || in_array( 'administrator', (array) $current_user->roles, true );
    $is_staff     = false;

    if ( function_exists( 'educore_has_access' ) ) {
        $is_staff = educore_has_access( 'educore_manage_results' ) || educore_has_access( 'educore_manage_academics' );
    }

    global $wpdb;
    $table_staff = educore_marks_get_table( 'staff' );

    if ( ! $is_admin && ! $is_staff ) {
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
        wp_send_json_error( array( 'message' => esc_html__( 'Permission denied.', 'ifsedu-school-management' ) ) );
    }

    $table_exams    = educore_marks_get_table( 'exams' );
    $table_subjects = educore_marks_get_table( 'subjects' );
    $table_units    = educore_marks_get_table( 'academic_units' );
    $table_results  = educore_marks_get_table( 'results' );
    $table_students = educore_marks_get_table( 'students' );

    $exam_id      = isset( $_POST['exam_id'] ) ? absint( wp_unslash( $_POST['exam_id'] ) ) : 0;
    $class_name   = isset( $_POST['class_name'] ) ? sanitize_text_field( wp_unslash( $_POST['class_name'] ) ) : '';
    $section_name = isset( $_POST['section_name'] ) ? sanitize_text_field( wp_unslash( $_POST['section_name'] ) ) : '';

    if ( empty( $class_name ) ) {
        wp_send_json_success( array() );
    }

    $exam_subject_ids = array();
    if ( $exam_id > 0 ) {
        $subject_json = $wpdb->get_var( $wpdb->prepare( 'SELECT subject_ids FROM %i WHERE id = %d LIMIT 1', $table_exams, $exam_id ) );
        if ( ! empty( $subject_json ) ) {
            $decoded_map = json_decode( (string) $subject_json, true );
            if ( isset( $decoded_map[ $class_name ] ) && is_array( $decoded_map[ $class_name ] ) && ! empty( $decoded_map[ $class_name ] ) ) {
                $exam_subject_ids = array_map( 'absint', $decoded_map[ $class_name ] );
            }
        }
    }

    $subjects = array();
    if ( ! empty( $section_name ) ) {
        $subjects = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT DISTINCT s.id, s.subject_name, s.subject_code, s.subject_order, s.total_marks, s.pass_marks, s.cq_marks, s.cq_pass, s.mcq_marks, s.mcq_pass, s.practical_marks, s.practical_pass, s.breakdown_data 
                 FROM %i s 
                 INNER JOIN %i u ON s.class_id = u.id 
                 WHERE u.class_name = %s AND u.section_name = %s 
                 ORDER BY s.subject_order ASC, s.subject_name ASC',
                $table_subjects,
                $table_units,
                $class_name,
                $section_name
            )
        );
    }

    if ( empty( $subjects ) ) {
        $subjects = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT DISTINCT s.id, s.subject_name, s.subject_code, s.subject_order, s.total_marks, s.pass_marks, s.cq_marks, s.cq_pass, s.mcq_marks, s.mcq_pass, s.practical_marks, s.practical_pass, s.breakdown_data 
                 FROM %i s 
                 INNER JOIN %i u ON s.class_id = u.id 
                 WHERE u.class_name = %s 
                 ORDER BY s.subject_order ASC, s.subject_name ASC',
                $table_subjects,
                $table_units,
                $class_name
            )
        );
    }

    if ( ! empty( $exam_subject_ids ) && ! empty( $subjects ) ) {
        $subjects = array_values( array_filter( $subjects, static function( $sub ) use ( $exam_subject_ids ): bool {
            return in_array( (int) $sub->id, $exam_subject_ids, true );
        } ) );
    }

    if ( ! empty( $section_name ) ) {
        $total_students = (int) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*) FROM %i WHERE status = %s AND class_name = %s AND section_name = %s',
                $table_students,
                'Active',
                $class_name,
                $section_name
            )
        );
    } else {
        $total_students = (int) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*) FROM %i WHERE status = %s AND class_name = %s',
                $table_students,
                'Active',
                $class_name
            )
        );
    }

    $unique_subjects = array();
    $seen_sub_names  = array();
    if ( ! empty( $subjects ) ) {
        foreach ( $subjects as $s_item ) {
            $s_norm = trim( strtolower( (string) $s_item->subject_name ) );
            if ( ! in_array( $s_norm, $seen_sub_names, true ) ) {
                $seen_sub_names[] = $s_norm;

                if ( $exam_id > 0 ) {
                    if ( ! empty( $section_name ) ) {
                        $entered_count = (int) $wpdb->get_var(
                            $wpdb->prepare(
                                'SELECT COUNT(r.id) FROM %i r INNER JOIN %i st ON r.student_id = st.id WHERE r.exam_id = %d AND r.class_name = %s AND st.section_name = %s AND r.subject_name = %s',
                                $table_results,
                                $table_students,
                                $exam_id,
                                $class_name,
                                $section_name,
                                $s_item->subject_name
                            )
                        );
                    } else {
                        $entered_count = (int) $wpdb->get_var(
                            $wpdb->prepare(
                                'SELECT COUNT(r.id) FROM %i r WHERE r.exam_id = %d AND r.class_name = %s AND r.subject_name = %s',
                                $table_results,
                                $exam_id,
                                $class_name,
                                $s_item->subject_name
                            )
                        );
                    }
                } else {
                    $entered_count = 0;
                }

                $s_item->entered_count  = $entered_count;
                $s_item->total_students = $total_students;

                $unique_subjects[] = $s_item;
            }
        }
    }

    wp_send_json_success( $unique_subjects );
}

// --------------------------------------------------------------------------
// 2. STANDARD BD NCTB GRADING FUNCTION
// --------------------------------------------------------------------------
if ( ! function_exists( 'educore_calculate_grade' ) ) {
    /**
     * Calculate Standard Bangladeshi NCTB Grade and GPA.
     *
     * @param float $obtained Obtained marks.
     * @param float $total    Total marks.
     * @return array Grade letter and GPA.
     */
    function educore_calculate_grade( float $obtained, float $total = 100.00 ): array {
        $total = $total > 0 ? $total : 100.00;
        $pct   = ( $obtained / $total ) * 100;

        if ( $pct >= 80 ) {
            return array( 'A+', 5.00 );
        } elseif ( $pct >= 70 ) {
            return array( 'A', 4.00 );
        } elseif ( $pct >= 60 ) {
            return array( 'A-', 3.50 );
        } elseif ( $pct >= 50 ) {
            return array( 'B', 3.00 );
        } elseif ( $pct >= 40 ) {
            return array( 'C', 2.00 );
        } elseif ( $pct >= 33 ) {
            return array( 'D', 1.00 );
        } else {
            return array( 'F', 0.00 );
        }
    }
}

// --------------------------------------------------------------------------
// 3. MAIN MARKS ENTRY MATRIX VIEW
// --------------------------------------------------------------------------
/**
 * Render Marks Entry Matrix & Evaluation View.
 */
function educore_exams_marks_view(): void {
    global $wpdb;
    $current_user = wp_get_current_user();

    $table_students         = educore_marks_get_table( 'students' );
    $table_exams            = educore_marks_get_table( 'exams' );
    $table_results          = educore_marks_get_table( 'results' );
    $table_units            = educore_marks_get_table( 'academic_units' );
    $table_subjects         = educore_marks_get_table( 'subjects' );
    $table_staff            = educore_marks_get_table( 'staff' );
    $table_teacher_subjects = educore_marks_get_table( 'teacher_subjects' );
    $table_exam_att         = educore_marks_get_table( 'exam_attendance' );

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
            esc_html__( 'You do not have sufficient permissions to enter examination marks.', 'ifsedu-school-management' ),
            403
        );
    }

    $base_url = add_query_arg(
        array(
            'page' => 'school_management_system',
            'tab'  => 'results',
            'sub'  => 'marks',
        ),
        admin_url( 'admin.php' )
    );
    $notice_msg = '';

    // Unified Parameter Resolution.
    $filter_exam    = isset( $_REQUEST['exam_id'] ) ? absint( wp_unslash( $_REQUEST['exam_id'] ) ) : 0;
    $filter_class   = isset( $_REQUEST['class_name'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['class_name'] ) ) : '';
    $filter_section = isset( $_REQUEST['section_name'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['section_name'] ) ) : '';
    $filter_subject = isset( $_REQUEST['subject_name'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['subject_name'] ) ) : '';

    // Fetch Class Sort Order Dictionary.
    $class_order_rows = $wpdb->get_results( $wpdb->prepare( 'SELECT class_name, MIN(sort_order) as min_sort FROM %i GROUP BY class_name', $table_units ) );
    $class_order_map  = array();
    if ( ! empty( $class_order_rows ) ) {
        foreach ( $class_order_rows as $cor ) {
            $class_order_map[ $cor->class_name ] = (int) $cor->min_sort;
        }
    }

    // Resolve Teacher Allocations.
    $teacher_assigned_classes = array();
    $teacher_assigned_subs    = array();
    $teacher_id               = 0;

    if ( ! $is_admin ) {
        $teacher_id = (int) $wpdb->get_var(
            $wpdb->prepare(
                'SELECT id FROM %i WHERE wp_user_id = %d OR email = %s OR full_name = %s LIMIT 1',
                $table_staff,
                $current_user->ID,
                $current_user->user_email,
                $current_user->display_name
            )
        );

        if ( $teacher_id > 0 ) {
            $allocations = $wpdb->get_results(
                $wpdb->prepare(
                    'SELECT DISTINCT u.class_name, u.section_name, s.subject_name 
                     FROM %i ts
                     INNER JOIN %i u ON ts.class_id = u.id 
                     INNER JOIN %i s ON ts.subject_id = s.id 
                     WHERE ts.teacher_id = %d 
                     ORDER BY u.sort_order ASC, CAST(u.class_name AS UNSIGNED) ASC, u.class_name ASC, s.subject_order ASC',
                    $table_teacher_subjects,
                    $table_units,
                    $table_subjects,
                    $teacher_id
                )
            );

            if ( ! empty( $allocations ) ) {
                foreach ( $allocations as $al ) {
                    $c_val = trim( (string) $al->class_name );
                    $s_val = trim( (string) $al->subject_name );
                    if ( ! empty( $c_val ) && ! in_array( $c_val, $teacher_assigned_classes, true ) ) {
                        $teacher_assigned_classes[] = $c_val;
                    }
                    if ( ! empty( $s_val ) ) {
                        $teacher_assigned_subs[ $c_val ][] = $s_val;
                    }
                }
            }
        }
    }

    // Handle Form Submission.
    $request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
    if ( 'POST' === $request_method && isset( $_POST['educore_save_marks_matrix'] ) ) {
        $nonce_field = isset( $_POST['ifs_educore_marks_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['ifs_educore_marks_nonce'] ) ) : '';

        if ( wp_verify_nonce( $nonce_field, 'save_marks_action' ) ) {
            
            if ( ! $is_admin && ! empty( $teacher_assigned_classes ) && ( ! in_array( $filter_class, $teacher_assigned_classes, true ) || ! in_array( $filter_subject, (array) ( $teacher_assigned_subs[ $filter_class ] ?? array() ), true ) ) ) {
                wp_die(
                    esc_html__( 'Security Check: You are not authorized to submit marks for this class/subject allocation.', 'ifsedu-school-management' ),
                    403
                );
            }

            $total_marks  = isset( $_POST['total_marks_limit'] ) ? (float) wp_unslash( $_POST['total_marks_limit'] ) : 100.00;
            $pass_marks   = isset( $_POST['pass_marks_limit'] ) ? (float) wp_unslash( $_POST['pass_marks_limit'] ) : 33.00;
            $cq_lim_post  = isset( $_POST['cq_marks_limit'] ) ? (float) wp_unslash( $_POST['cq_marks_limit'] ) : 70.00;
            $mcq_lim_post = isset( $_POST['mcq_marks_limit'] ) ? (float) wp_unslash( $_POST['mcq_marks_limit'] ) : 30.00;
            $pr_lim_post  = isset( $_POST['pr_marks_limit'] ) ? (float) wp_unslash( $_POST['pr_marks_limit'] ) : 0.00;

            $cq_pass  = isset( $_POST['cq_pass_limit'] ) ? (float) wp_unslash( $_POST['cq_pass_limit'] ) : 0.00;
            $mcq_pass = isset( $_POST['mcq_pass_limit'] ) ? (float) wp_unslash( $_POST['mcq_pass_limit'] ) : 0.00;
            $pr_pass  = isset( $_POST['pr_pass_limit'] ) ? (float) wp_unslash( $_POST['pr_pass_limit'] ) : 0.00;

            $is_custom_breakdown = isset( $_POST['is_custom_breakdown'] ) && '1' === $_POST['is_custom_breakdown'];
            $custom_comp_totals  = isset( $_POST['custom_comp_total'] ) && is_array( $_POST['custom_comp_total'] ) ? array_map( 'floatval', wp_unslash( $_POST['custom_comp_total'] ) ) : array();
            $custom_comp_passes  = isset( $_POST['custom_comp_pass'] ) && is_array( $_POST['custom_comp_pass'] ) ? array_map( 'floatval', wp_unslash( $_POST['custom_comp_pass'] ) ) : array();
            $custom_student_vals = isset( $_POST['custom_comp_val'] ) && is_array( $_POST['custom_comp_val'] ) ? wp_unslash( $_POST['custom_comp_val'] ) : array();

            $raw_cq  = ( isset( $_POST['cq_marks'] ) && is_array( $_POST['cq_marks'] ) ) ? wp_unslash( $_POST['cq_marks'] ) : array();
            $raw_mcq = ( isset( $_POST['mcq_marks'] ) && is_array( $_POST['mcq_marks'] ) ) ? wp_unslash( $_POST['mcq_marks'] ) : array();
            $raw_pr  = ( isset( $_POST['practical_marks'] ) && is_array( $_POST['practical_marks'] ) ) ? wp_unslash( $_POST['practical_marks'] ) : array();

            $saved_count = 0;
            if ( $filter_exam > 0 && ! empty( $filter_class ) && ! empty( $filter_subject ) ) {
                
                // 1. Fetch live exam attendance statuses to handle lockout for absentees and check if attendance exists
                $live_att_check = array();
                $att_rows_check = $wpdb->get_results(
                    $wpdb->prepare(
                        'SELECT student_id, status FROM %i WHERE exam_id = %d AND class_name = %s AND subject_name = %s',
                        $table_exam_att,
                        $filter_exam,
                        $filter_class,
                        $filter_subject
                    )
                );
                if ( ! empty( $att_rows_check ) ) {
                    foreach ( $att_rows_check as $ar ) {
                        $live_att_check[ (int) $ar->student_id ] = (string) $ar->status;
                    }
                }

                // Gather ALL active students registered for this class/section directly from database
                $target_students_sql = 'SELECT id FROM %i WHERE status = %s AND class_name = %s';
                $target_params       = array( $table_students, 'Active', $filter_class );
                if ( ! empty( $filter_section ) ) {
                    $target_students_sql .= ' AND section_name = %s';
                    $target_params[]     = $filter_section;
                }
                $enrolled_student_ids = $wpdb->get_col( $wpdb->prepare( $target_students_sql, ...$target_params ) );

                if ( ! empty( $enrolled_student_ids ) ) {
                    foreach ( $enrolled_student_ids as $s_id_str ) {
                        $s_id_int = absint( $s_id_str );
                        if ( $s_id_int <= 0 ) {
                            continue;
                        }

                        // RULE: IF NO ATTENDANCE RECORD EXISTS FOR THIS STUDENT, SKIP MARK ENTRY COMPLETELY
                        if ( ! isset( $live_att_check[ $s_id_int ] ) ) {
                            continue;
                        }

                        $is_absent_db = ( 'Absent' === $live_att_check[ $s_id_int ] );
                        if ( $is_absent_db ) {
                            $obtained   = 0.00;
                            $has_failed = false; 
                            $grade      = 'A';
                            $gpa        = 0.00;
                            $cq_val     = 0.00;
                            $mcq_val    = 0.00;
                            $pr_val     = 0.00;
                            $comp_json  = '';
                        } else {
                            $obtained   = 0.00;
                            $has_failed = false;
                            $comp_json  = '';

                            $cq_val  = 0.00;
                            $mcq_val = 0.00;
                            $pr_val  = 0.00;

                            if ( $is_custom_breakdown && isset( $custom_student_vals[ $s_id_int ] ) && is_array( $custom_student_vals[ $s_id_int ] ) ) {
                                $comp_saved_data = array();
                                foreach ( $custom_student_vals[ $s_id_int ] as $comp_k => $comp_val ) {
                                    if ( '' === trim( (string) $comp_val ) ) {
                                        continue;
                                    }
                                    $c_val  = (float) $comp_val;
                                    $c_max  = isset( $custom_comp_totals[ $comp_k ] ) ? (float) $custom_comp_totals[ $comp_k ] : 100.00;
                                    $c_pass = isset( $custom_comp_passes[ $comp_k ] ) ? (float) $custom_comp_passes[ $comp_k ] : 0.00;
                                    
                                    $c_val_clamped = max( 0.00, min( $c_val, $c_max ) );
                                    $obtained += $c_val_clamped;

                                    if ( $c_pass > 0 && $c_val_clamped < $c_pass ) {
                                        $has_failed = true;
                                    }

                                    $comp_saved_data[ sanitize_text_field( (string) $comp_k ) ] = $c_val_clamped;
                                }
                                $comp_json = wp_json_encode( $comp_saved_data );
                                $obtained  = min( $obtained, $total_marks );
                            } else {
                                $has_any_input = false;
                                if ( isset( $raw_cq[ $s_id_int ] ) && '' !== trim( (string) $raw_cq[ $s_id_int ] ) ) {
                                    $cq_raw = (float) $raw_cq[ $s_id_int ];
                                    $cq_val = max( 0.00, min( $cq_raw, $cq_lim_post ) );
                                    $has_any_input = true;
                                }
                                if ( isset( $raw_mcq[ $s_id_int ] ) && '' !== trim( (string) $raw_mcq[ $s_id_int ] ) ) {
                                    $mcq_raw = (float) $raw_mcq[ $s_id_int ];
                                    $mcq_val = max( 0.00, min( $mcq_raw, $mcq_lim_post ) );
                                    $has_any_input = true;
                                }
                                if ( isset( $raw_pr[ $s_id_int ] ) && '' !== trim( (string) $raw_pr[ $s_id_int ] ) ) {
                                    $pr_raw = (float) $raw_pr[ $s_id_int ];
                                    $pr_val = max( 0.00, min( $pr_raw, $pr_lim_post ) );
                                    $has_any_input = true;
                                }

                                if ( ! $has_any_input ) {
                                    $cq_val  = 0.00;
                                    $mcq_val = 0.00;
                                    $pr_val  = 0.00;
                                }

                                $obtained = min( $cq_val + $mcq_val + $pr_val, $total_marks );

                                if ( $cq_pass > 0 && $cq_val < $cq_pass ) {
                                    $has_failed = true;
                                }
                                if ( $mcq_pass > 0 && $mcq_val < $mcq_pass ) {
                                    $has_failed = true;
                                }
                                if ( $pr_pass > 0 && $pr_val < $pr_pass ) {
                                    $has_failed = true;
                                }
                            }

                            if ( $obtained < $pass_marks ) {
                                $has_failed = true;
                            }

                            if ( $has_failed ) {
                                $grade = 'F';
                                $gpa   = 0.00;
                            } else {
                                $grade_eval = educore_calculate_grade( $obtained, $total_marks );
                                $grade      = (string) $grade_eval[0];
                                $gpa        = (float) $grade_eval[1];
                            }
                        }

                        $existing_id = (int) $wpdb->get_var(
                            $wpdb->prepare(
                                'SELECT id FROM %i WHERE exam_id = %d AND student_id = %d AND subject_name = %s LIMIT 1',
                                $table_results,
                                $filter_exam,
                                $s_id_int,
                                $filter_subject
                            )
                        );

                        $data = array(
                            'exam_id'         => $filter_exam,
                            'student_id'      => $s_id_int,
                            'class_name'      => $filter_class,
                            'section_name'    => $filter_section,
                            'subject_name'    => $filter_subject,
                            'total_marks'     => $total_marks,
                            'obtained_marks'  => $obtained,
                            'cq_marks'        => $cq_val,
                            'mcq_marks'       => $mcq_val,
                            'practical_marks' => $pr_val,
                            'component_marks' => $comp_json,
                            'grade'           => $grade,
                            'gpa'             => $gpa,
                        );

                        $format = array( '%d', '%d', '%s', '%s', '%s', '%f', '%f', '%f', '%f', '%f', '%s', '%s', '%f' );

                        if ( $existing_id > 0 ) {
                            $wpdb->update( $table_results, $data, array( 'id' => $existing_id ), $format, array( '%d' ) );
                        } else {
                            $wpdb->insert( $table_results, $data, $format );
                        }
                        $saved_count++;
                    }
                }

                if ( function_exists( 'educore_log_activity' ) ) {
                    educore_log_activity(
                        sprintf(
                            /* translators: 1: Saved student count, 2: Filter subject */
                            __( 'Evaluated and saved marks for %1$d students in %2$s', 'ifsedu-school-management' ),
                            $saved_count,
                            $filter_subject
                        )
                    );
                }

                $notice_msg = sprintf(
                    esc_html__( 'Successfully evaluated and saved marks for %d students.', 'ifsedu-school-management' ),
                    $saved_count
                );
            }
        }
    }

    // Fetch Examinations.
    $exams = $wpdb->get_results(
        $wpdb->prepare(
            'SELECT id, exam_name, class_name, subject_ids FROM %i ORDER BY id DESC',
            $table_exams
        )
    );

    // Fetch Classes ONLY assigned to the active selected Exam (Ordered by sort_order).
    $academic_classes   = array();
    $exam_subject_map   = array();

    if ( $filter_exam > 0 ) {
        $exam_row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT class_name, subject_ids FROM %i WHERE id = %d LIMIT 1',
                $table_exams,
                $filter_exam
            )
        );
        if ( ! empty( $exam_row ) ) {
            if ( ! empty( $exam_row->class_name ) ) {
                $parsed_classes = array_map( 'trim', explode( ',', (string) $exam_row->class_name ) );
                if ( ! $is_admin && ! empty( $teacher_assigned_classes ) ) {
                    $academic_classes = array_intersect( $parsed_classes, $teacher_assigned_classes );
                } else {
                    $academic_classes = $parsed_classes;
                }
                $academic_classes = array_values( array_unique( array_filter( $academic_classes ) ) );
                
                usort(
                    $academic_classes,
                    static function( $a, $b ) use ( $class_order_map ): int {
                        $order_a = $class_order_map[ $a ] ?? 0;
                        $order_b = $class_order_map[ $b ] ?? 0;
                        if ( $order_a !== $order_b ) {
                            return $order_a <=> $order_b;
                        }
                        return strnatcasecmp( $a, $b );
                    }
                );
            }

            if ( ! empty( $exam_row->subject_ids ) ) {
                $decoded = json_decode( (string) $exam_row->subject_ids, true );
                if ( is_array( $decoded ) ) {
                    $exam_subject_map = $decoded;
                }
            }
        }
    }

    // Pre-populate Available Sections (Ordered by sort_order).
    $available_sections = array();
    if ( ! empty( $filter_class ) ) {
        if ( ! empty( $teacher_id ) && ! $is_admin ) {
            $available_sections = $wpdb->get_col(
                $wpdb->prepare(
                    'SELECT DISTINCT u.section_name 
                     FROM %i ts
                     INNER JOIN %i u ON ts.class_id = u.id 
                     WHERE ts.teacher_id = %d AND u.class_name = %s AND u.section_name != %s 
                     ORDER BY u.sort_order ASC, u.section_name ASC',
                    $table_teacher_subjects,
                    $table_units,
                    $teacher_id,
                    $filter_class,
                    ''
                )
            );
        } else {
            $available_sections = $wpdb->get_col(
                $wpdb->prepare(
                    'SELECT DISTINCT section_name FROM %i WHERE class_name = %s AND section_name != %s ORDER BY sort_order ASC, section_name ASC',
                    $table_units,
                    $filter_class,
                    ''
                )
            );
        }
    }

    // Total active students in class/section for entry status calculation.
    if ( ! empty( $filter_class ) ) {
        if ( ! empty( $filter_section ) ) {
            $total_class_students = (int) $wpdb->get_var(
                $wpdb->prepare(
                    'SELECT COUNT(*) FROM %i WHERE status = %s AND class_name = %s AND section_name = %s',
                    $table_students,
                    'Active',
                    $filter_class,
                    $filter_section
                )
            );
        } else {
            $total_class_students = (int) $wpdb->get_var(
                $wpdb->prepare(
                    'SELECT COUNT(*) FROM %i WHERE status = %s AND class_name = %s',
                    $table_students,
                    'Active',
                    $filter_class
                )
            );
        }
    } else {
        $total_class_students = 0;
    }

    // Fetch Mapped Subjects with Section Context & Unique Deduplication.
    $available_subjects = array();
    $active_subject_obj = null;

    if ( ! empty( $filter_class ) ) {
        $raw_subs = array();
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
        }

        if ( empty( $raw_subs ) ) {
            if ( ! $is_admin && $teacher_id > 0 ) {
                $raw_subs = $wpdb->get_results(
                    $wpdb->prepare(
                        'SELECT DISTINCT s.id, s.subject_name, s.subject_code, s.subject_order, s.total_marks, s.pass_marks, s.cq_marks, s.cq_pass, s.mcq_marks, s.mcq_pass, s.practical_marks, s.practical_pass, s.breakdown_data, s.class_id  
                         FROM %i ts
                         INNER JOIN %i s ON ts.subject_id = s.id 
                         INNER JOIN %i u ON ts.class_id = u.id 
                         WHERE ts.teacher_id = %d AND u.class_name = %s 
                         ORDER BY s.subject_order ASC, s.subject_name ASC',
                        $table_teacher_subjects,
                        $table_subjects,
                        $table_units,
                        $teacher_id,
                        $filter_class
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
        }

        // Filter by Exam Scheme Subject Configuration if applicable.
        if ( ! empty( $exam_subject_map[ $filter_class ] ) && is_array( $exam_subject_map[ $filter_class ] ) ) {
            $allowed_ids = array_map( 'absint', $exam_subject_map[ $filter_class ] );
            $raw_subs    = array_values( array_filter( $raw_subs, static function( $sub ) use ( $allowed_ids ): bool {
                return in_array( (int) $sub->id, $allowed_ids, true );
            } ) );
        }

        // Deduplicate unique subject names.
        $seen_names = array();
        if ( ! empty( $raw_subs ) ) {
            foreach ( $raw_subs as $s_item ) {
                $norm_name = trim( strtolower( (string) $s_item->subject_name ) );
                if ( ! in_array( $norm_name, $seen_names, true ) ) {
                    $seen_names[] = $norm_name;

                    if ( $filter_exam > 0 ) {
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
                    } else {
                        $entered_count = 0;
                    }

                    $s_item->entered_count  = $entered_count;
                    $s_item->total_students = $total_class_students;

                    $available_subjects[] = $s_item;
                }
            }
        }

        if ( ! empty( $filter_subject ) && ! empty( $available_subjects ) ) {
            foreach ( $available_subjects as $sub_item ) {
                if ( $sub_item->subject_name === $filter_subject ) {
                    $active_subject_obj = $sub_item;
                    break;
                }
            }
        }
    }

    // Parse Custom Breakdown Data if configured.
    $custom_breakdown_components = array();
    if ( $active_subject_obj && ! empty( $active_subject_obj->breakdown_data ) ) {
        $parsed_bd = json_decode( (string) $active_subject_obj->breakdown_data, true );
        if ( is_array( $parsed_bd ) && ! empty( $parsed_bd ) ) {
            $custom_breakdown_components = $parsed_bd;
        }
    }
    $has_custom_breakdown = ! empty( $custom_breakdown_components );

    // Determine component visibility limits
    $tot_limit = $active_subject_obj ? (float) $active_subject_obj->total_marks : 100.00;
    $pass_lim  = $active_subject_obj ? (float) $active_subject_obj->pass_marks : 33.00;
    $cq_lim    = $active_subject_obj ? (float) $active_subject_obj->cq_marks : 70.00;
    $cq_p_lim  = $active_subject_obj ? (float) $active_subject_obj->cq_pass : 23.00;
    $mcq_lim   = $active_subject_obj ? (float) $active_subject_obj->mcq_marks : 30.00;
    $mcq_p_lim = $active_subject_obj ? (float) $active_subject_obj->mcq_pass : 10.00;
    $pr_lim    = $active_subject_obj ? (float) $active_subject_obj->practical_marks : 0.00;
    $pr_p_lim  = $active_subject_obj ? (float) $active_subject_obj->practical_pass : 0.00;

    // Fetch Active Students Dataset & Pre-existing Marks and Examination Hall Attendance.
    $students_list = array();
    $saved_marks   = array();
    $exam_att_map  = array();

    if ( $filter_exam > 0 && ! empty( $filter_class ) && ! empty( $filter_subject ) ) {
        if ( ! empty( $filter_section ) ) {
            $students_list = $wpdb->get_results(
                $wpdb->prepare(
                    'SELECT id, full_name, student_id, roll_no, class_name, section_name 
                     FROM %i 
                     WHERE status = %s AND class_name = %s AND section_name = %s 
                     ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC',
                    $table_students,
                    'Active',
                    $filter_class,
                    $filter_section
                )
            );
        } else {
            $students_list = $wpdb->get_results(
                $wpdb->prepare(
                    'SELECT id, full_name, student_id, roll_no, class_name, section_name 
                     FROM %i 
                     WHERE status = %s AND class_name = %s 
                     ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC',
                    $table_students,
                    'Active',
                    $filter_class
                )
            );
        }

        $existing_results = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT student_id, cq_marks, mcq_marks, practical_marks, component_marks, obtained_marks, total_marks, grade, gpa 
                 FROM %i 
                 WHERE exam_id = %d AND class_name = %s AND subject_name = %s',
                $table_results,
                $filter_exam,
                $filter_class,
                $filter_subject
            ),
            OBJECT_K
        );

        if ( ! empty( $existing_results ) ) {
            $saved_marks = $existing_results;
        }

        // Fetch Exam Hall Attendance Status to check if student is Absent
        $exam_att_rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT student_id, status FROM %i WHERE exam_id = %d AND class_name = %s AND subject_name = %s',
                $table_exam_att,
                $filter_exam,
                $filter_class,
                $filter_subject
            )
        );
        if ( ! empty( $exam_att_rows ) ) {
            foreach ( $exam_att_rows as $ear ) {
                $exam_att_map[ (int) $ear->student_id ] = (string) $ear->status;
            }
        }
    }

    $admin_page_url = admin_url( 'admin.php' );
    ?>

    <style>
        .ifs-educore-marks-root {
            max-width: 100%;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: inherit;
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
            gap: 4px;
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
        .ifs-absent-row-dimmed {
            background-color: #f8fafc !important;
            opacity: 0.8;
        }
        .ifs-unrecorded-row-dimmed {
            background-color: #fffbeb !important;
        }
    </style>

    <div class="ifs-educore-marks-root">

        <?php if ( ! empty( $notice_msg ) ) : ?>
            <div class="notice notice-success is-dismissible" style="padding:12px; margin:0 0 16px 0; font-weight:700; border-left:4px solid #00523c; background:#ecfdf5; color:#065f46; border-radius:8px;">
                <span class="dashicons dashicons-yes-alt" style="vertical-align:middle; margin-right:4px;"></span>
                <?php echo esc_html( $notice_msg ); ?>
            </div>
        <?php endif; ?>

        <!-- Search & Selection Bento Filter Card -->
        <div class="ifs-educore-bento-filter-card">
            <form method="GET" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" id="educoreMarksFilterForm">
                <input type="hidden" name="page" value="school_management_system">
                <input type="hidden" name="tab" value="results">
                <input type="hidden" name="sub" value="marks">

                <div class="ifs-educore-filter-grid">
                    <!-- 1. Select Exam -->
                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-form-label" for="ifs_educore_marks_exam_select">
                            <span class="dashicons dashicons-calendar-alt" style="font-size:14px; width:14px; height:14px; color:#00523c;"></span>
                            <?php esc_html_e( '1. Select Exam', 'ifsedu-school-management' ); ?> <span style="color:#ef4444;">*</span>
                        </label>
                        <select name="exam_id" id="ifs_educore_marks_exam_select" class="ifs-educore-select" required>
                            <option value=""><?php esc_html_e( '-- Choose Exam --', 'ifsedu-school-management' ); ?></option>
                            <?php foreach ( $exams as $ex ) : ?>
                                <option value="<?php echo (int) $ex->id; ?>" <?php selected( $filter_exam, (int) $ex->id ); ?>>
                                    <?php echo esc_html( (string) $ex->exam_name ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- 2. Exam Class Selection -->
                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-form-label" for="ifs_educore_marks_class_select">
                            <span class="dashicons dashicons-welcome-learn-more" style="font-size:14px; width:14px; height:14px; color:#00523c;"></span>
                            <?php esc_html_e( '2. Exam Class', 'ifsedu-school-management' ); ?> <span style="color:#ef4444;">*</span>
                        </label>
                        <select name="class_name" id="ifs_educore_marks_class_select" class="ifs-educore-select" required <?php disabled( empty( $academic_classes ) && empty( $filter_exam ) ); ?>>
                            <option value=""><?php esc_html_e( '-- Choose Class --', 'ifsedu-school-management' ); ?></option>
                            <?php foreach ( $academic_classes as $cls_name ) : ?>
                                <option value="<?php echo esc_attr( $cls_name ); ?>" <?php selected( $filter_class, $cls_name ); ?>>
                                    <?php echo esc_html( $cls_name ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- 3. Section Selection -->
                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-form-label" for="ifs_educore_marks_section_select">
                            <span class="dashicons dashicons-groups" style="font-size:14px; width:14px; height:14px; color:#00523c;"></span>
                            <?php esc_html_e( '3. Section / Stream', 'ifsedu-school-management' ); ?>
                        </label>
                        <select name="section_name" id="ifs_educore_marks_section_select" class="ifs-educore-select">
                            <option value=""><?php esc_html_e( '-- All Sections --', 'ifsedu-school-management' ); ?></option>
                            <?php foreach ( $available_sections as $sec_name ) : ?>
                                <option value="<?php echo esc_attr( $sec_name ); ?>" <?php selected( $filter_section, $sec_name ); ?>>
                                    <?php echo esc_html( $sec_name ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- 4. Subject Selection -->
                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-form-label" for="ifs_educore_marks_subject_select">
                            <span class="dashicons dashicons-book" style="font-size:14px; width:14px; height:14px; color:#00523c;"></span>
                            <?php esc_html_e( '4. Subject', 'ifsedu-school-management' ); ?> <span style="color:#ef4444;">*</span>
                        </label>
                        <select name="subject_name" id="ifs_educore_marks_subject_select" class="ifs-educore-select" required>
                            <option value=""><?php esc_html_e( '-- Choose Subject --', 'ifsedu-school-management' ); ?></option>
                            <?php
                            $completed_options = '';
                            $pending_options   = '';

                            foreach ( $available_subjects as $sub_item ) {
                                $is_completed = ( $total_class_students > 0 && $sub_item->entered_count >= $total_class_students );
                                $status_badge = sprintf( ' [%d/%d Entered]', (int) $sub_item->entered_count, (int) $total_class_students );
                                $label_text   = $sub_item->subject_name . ( ! empty( $sub_item->subject_code ) ? ' (' . $sub_item->subject_code . ')' : '' ) . $status_badge;
                                
                                $opt_html = sprintf(
                                    '<option value="%s" %s>%s</option>',
                                    esc_attr( (string) $sub_item->subject_name ),
                                    selected( $filter_subject, (string) $sub_item->subject_name, false ),
                                    esc_html( $label_text )
                                );

                                if ( $is_completed ) {
                                    $completed_options .= $opt_html;
                                } else {
                                    $pending_options   .= $opt_html;
                                }
                            }

                            if ( ! empty( $pending_options ) ) {
                                echo '<optgroup label="' . esc_attr__( '⏳ Pending Entries', 'ifsedu-school-management' ) . '">' . $pending_options . '</optgroup>';
                            }
                            if ( ! empty( $completed_options ) ) {
                                echo '<optgroup label="' . esc_attr__( '✅ Completed Entries', 'ifsedu-school-management' ) . '">' . $completed_options . '</optgroup>';
                            }
                            ?>
                        </select>
                    </div>

                    <!-- 5. Submit Filter -->
                    <div>
                        <button type="submit" class="ifs-educore-btn-load">
                            <span class="dashicons dashicons-filter" style="font-size:15px; width:15px; height:15px;"></span>
                            <?php esc_html_e( 'Load', 'ifsedu-school-management' ); ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Dynamic Cascading Dropdown Scripts -->
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            var nonce = '<?php echo esc_js( wp_create_nonce( "ifs_educore_marks_nonce" ) ); ?>';

            $('#ifs_educore_marks_exam_select').on('change', function() {
                var selectedExamId = $(this).val();
                var $classSelect   = $('#ifs_educore_marks_class_select');
                var $secSelect     = $('#ifs_educore_marks_section_select');
                var $subjectSelect = $('#ifs_educore_marks_subject_select');

                $classSelect.html('<option value=""><?php echo esc_js( __( '-- Loading Classes... --', 'ifsedu-school-management' ) ); ?></option>').prop('disabled', true);
                $secSelect.html('<option value=""><?php echo esc_js( __( '-- All Sections --', 'ifsedu-school-management' ) ); ?></option>');
                $subjectSelect.html('<option value=""><?php echo esc_js( __( '-- Choose Subject --', 'ifsedu-school-management' ) ); ?></option>');

                if (!selectedExamId) {
                    $classSelect.html('<option value=""><?php echo esc_js( __( '-- Choose Class --', 'ifsedu-school-management' ) ); ?></option>');
                    return;
                }

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'ifs_educore_get_classes_by_exam_marks',
                        security: nonce,
                        exam_id: selectedExamId
                    },
                    success: function(response) {
                        if (response.success && response.data.length > 0) {
                            var options = '<option value=""><?php echo esc_js( __( '-- Choose Class --', 'ifsedu-school-management' ) ); ?></option>';
                            $.each(response.data, function(i, cls) {
                                options += '<option value="' + cls + '">' + cls + '</option>';
                            });
                            $classSelect.html(options).prop('disabled', false);
                        } else {
                            $classSelect.html('<option value=""><?php echo esc_js( __( 'No Classes Assigned To Exam', 'ifsedu-school-management' ) ); ?></option>').prop('disabled', true);
                        }
                    }
                });
            });

            function reloadSubjectsForClassAndSection() {
                var selectedClass   = $('#ifs_educore_marks_class_select').val();
                var selectedSection = $('#ifs_educore_marks_section_select').val();
                var selectedExamId  = $('#ifs_educore_marks_exam_select').val();
                var $subjectSelect  = $('#ifs_educore_marks_subject_select');

                if (!selectedClass) return;

                $subjectSelect.html('<option value=""><?php echo esc_js( __( '-- Loading Subjects & Entry Status... --', 'ifsedu-school-management' ) ); ?></option>');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'ifs_educore_get_subjects_for_marks_matrix',
                        security: nonce,
                        exam_id: selectedExamId,
                        class_name: selectedClass,
                        section_name: selectedSection
                    },
                    success: function(response) {
                        if (response.success && response.data.length > 0) {
                            var completedHtml = '';
                            var pendingHtml   = '';

                            $.each(response.data, function(i, sub) {
                                var codeStr = sub.subject_code ? ' (' + sub.subject_code + ')' : '';
                                var isCompleted = (sub.total_students > 0 && sub.entered_count >= sub.total_students);
                                var progressBadge = ' [' + sub.entered_count + '/' + sub.total_students + ' Entered]';
                                var optHtml = '<option value="' + sub.subject_name + '">' + sub.subject_name + codeStr + progressBadge + '</option>';

                                if (isCompleted) {
                                    completedHtml += optHtml;
                                } else {
                                    pendingHtml   += optHtml;
                                }
                            });

                            var finalDropdown = '<option value=""><?php echo esc_js( __( '-- Choose Subject --', 'ifsedu-school-management' ) ); ?></option>';
                            if (pendingHtml !== '') {
                                finalDropdown += '<optgroup label="<?php echo esc_js( __( '⏳ Pending Entries', 'ifsedu-school-management' ) ); ?>">' + pendingHtml + '</optgroup>';
                            }
                            if (completedHtml !== '') {
                                finalDropdown += '<optgroup label="<?php echo esc_js( __( '✅ Completed Entries', 'ifsedu-school-management' ) ); ?>">' + completedHtml + '</optgroup>';
                            }

                            $subjectSelect.html(finalDropdown);
                        } else {
                            $subjectSelect.html('<option value=""><?php echo esc_js( __( 'No Mapped Subjects Found', 'ifsedu-school-management' ) ); ?></option>');
                        }
                    }
                });
            }

            $('#ifs_educore_marks_class_select').on('change', function() {
                var selectedClass = $(this).val();
                currentSelectedSection = '';
                currentSelectedSubject = '';
                
                var $secSelect     = $('#ifs_educore_marks_section_select');
                var $subjectSelect = $('#ifs_educore_marks_subject_select');

                $secSelect.html('<option value=""><?php echo esc_js( __( '-- Loading Sections... --', 'ifsedu-school-management' ) ); ?></option>');
                $subjectSelect.html('<option value=""><?php echo esc_js( __( '-- Choose Subject --', 'ifsedu-school-management' ) ); ?></option>');

                if (!selectedClass) {
                    $secSelect.html('<option value=""><?php echo esc_js( __( '-- All Sections --', 'ifsedu-school-management' ) ); ?></option>');
                    return;
                }

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'ifs_educore_get_sections_by_class_marks',
                        security: nonce,
                        class_name: selectedClass
                    },
                    success: function(response) {
                        var options = '<option value=""><?php echo esc_js( __( '-- All Sections --', 'ifsedu-school-management' ) ); ?></option>';
                        if (response.success && response.data.length > 0) {
                            $.each(response.data, function(i, sec) {
                                options += '<option value="' + sec + '">' + sec + '</option>';
                            });
                        }
                        $secSelect.html(options);
                    }
                });

                reloadSubjectsForClassAndSection();
            });

            $('#ifs_educore_marks_section_select').on('change', function() {
                reloadSubjectsForClassAndSection();
            });
        });
        </script>

        <!-- Marks Entry Matrix Table with Dynamic Subject Breakdown Adaptation -->
        <?php if ( $filter_exam > 0 && ! empty( $filter_class ) && ! empty( $filter_subject ) ) : 
            $tot_limit = $active_subject_obj ? (float) $active_subject_obj->total_marks : 100.00;
            $pass_lim  = $active_subject_obj ? (float) $active_subject_obj->pass_marks : 33.00;
            $cq_lim    = $active_subject_obj ? (float) $active_subject_obj->cq_marks : 70.00;
            $cq_p_lim  = $active_subject_obj ? (float) $active_subject_obj->cq_pass : 23.00;
            $mcq_lim   = $active_subject_obj ? (float) $active_subject_obj->mcq_marks : 30.00;
            $mcq_p_lim = $active_subject_obj ? (float) $active_subject_obj->mcq_pass : 10.00;
            $pr_lim    = $active_subject_obj ? (float) $active_subject_obj->practical_marks : 0.00;
            $pr_p_lim  = $active_subject_obj ? (float) $active_subject_obj->practical_pass : 0.00;

            $matrix_action_url = add_query_arg(
                array(
                    'page'         => 'school_management_system',
                    'tab'          => 'results',
                    'sub'          => 'marks',
                    'exam_id'      => $filter_exam,
                    'class_name'   => $filter_class,
                    'section_name' => $filter_section,
                    'subject_name' => $filter_subject,
                ),
                admin_url( 'admin.php' )
            );
        ?>
            <div class="ifs-educore-bento-card" style="background:#ffffff; border:1px solid #e2e8f0; border-radius:14px; padding:24px; box-shadow:0 4px 15px rgba(0,0,0,0.02);">
                <form method="POST" id="educoreMarksMatrixForm" action="<?php echo esc_url( $matrix_action_url ); ?>">
                    <?php wp_nonce_field( 'save_marks_action', 'ifs_educore_marks_nonce' ); ?>
                    <input type="hidden" name="exam_id" value="<?php echo esc_attr( (string) $filter_exam ); ?>">
                    <input type="hidden" name="class_name" value="<?php echo esc_attr( $filter_class ); ?>">
                    <input type="hidden" name="section_name" value="<?php echo esc_attr( $filter_section ); ?>">
                    <input type="hidden" name="subject_name" value="<?php echo esc_attr( $filter_subject ); ?>">
                    <input type="hidden" name="is_custom_breakdown" id="is_custom_breakdown" value="<?php echo $has_custom_breakdown ? '1' : '0'; ?>">

                    <!-- Standard Limits Hidden Configuration -->
                    <input type="hidden" name="total_marks_limit" id="total_marks_limit" value="<?php echo esc_attr( (string) $tot_limit ); ?>">
                    <input type="hidden" name="pass_marks_limit" id="pass_marks_limit" value="<?php echo esc_attr( (string) $pass_lim ); ?>">
                    <input type="hidden" name="cq_marks_limit" id="cq_marks_limit" value="<?php echo esc_attr( (string) $cq_lim ); ?>">
                    <input type="hidden" name="cq_pass_limit" id="cq_pass_limit" value="<?php echo esc_attr( (string) $cq_p_lim ); ?>">
                    <input type="hidden" name="mcq_marks_limit" id="mcq_marks_limit" value="<?php echo esc_attr( (string) $mcq_lim ); ?>">
                    <input type="hidden" name="mcq_pass_limit" id="mcq_pass_limit" value="<?php echo esc_attr( (string) $mcq_p_lim ); ?>">
                    <input type="hidden" name="pr_marks_limit" id="pr_marks_limit" value="<?php echo esc_attr( (string) $pr_lim ); ?>">
                    <input type="hidden" name="pr_pass_limit" id="pr_pass_limit" value="<?php echo esc_attr( (string) $pr_p_lim ); ?>">

                    <?php if ( $has_custom_breakdown ) : ?>
                        <?php foreach ( $custom_breakdown_components as $comp ) : 
                            $c_name = sanitize_text_field( (string) $comp['name'] );
                            $c_tot  = (float) $comp['total'];
                            $c_pas  = (float) $comp['pass'];
                        ?>
                            <input type="hidden" name="custom_comp_total[<?php echo esc_attr( $c_name ); ?>]" class="custom-comp-tot-limit" data-comp="<?php echo esc_attr( $c_name ); ?>" value="<?php echo esc_attr( (string) $c_tot ); ?>">
                            <input type="hidden" name="custom_comp_pass[<?php echo esc_attr( $c_name ); ?>]" class="custom-comp-pass-limit" data-comp="<?php echo esc_attr( $c_name ); ?>" value="<?php echo esc_attr( (string) $c_pas ); ?>">
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:18px; padding-bottom:14px; border-bottom:1px solid #e2e8f0;">
                        <div>
                            <strong style="font-size:16px; color:#0f172a;"><?php echo esc_html( $filter_subject ); ?></strong>
                            <span style="font-size:12px; color:#64748b; margin-left:8px;">(Total: <?php echo esc_html( (string) $tot_limit ); ?> | Pass: <?php echo esc_html( (string) $pass_lim ); ?>)</span>
                        </div>
                        <div>
                            <button type="submit" name="educore_save_marks_matrix" class="ifs-educore-btn-submit" style="background:#00523c; color:#fff; border:none; padding:9px 20px; border-radius:8px; font-weight:700; cursor:pointer; display:inline-flex; align-items:center; gap:6px;">
                                <span class="dashicons dashicons-saved"></span>
                                <?php esc_html_e( 'Save All Marks', 'ifsedu-school-management' ); ?>
                            </button>
                        </div>
                    </div>

                    <div style="overflow-x:auto;">
                        <table class="ifs-educore-matrix-table" id="dptMarksEntryTable" style="width:100%; border-collapse:collapse; font-size:13px;">
                            <thead>
                                <tr style="background:#f8fafc; text-align:center; border-bottom:2px solid #e2e8f0;">
                                    <th style="padding:10px; width: 6%;"><?php esc_html_e( 'Roll', 'ifsedu-school-management' ); ?></th>
                                    <th style="padding:10px; width: 12%;"><?php esc_html_e( 'Student ID', 'ifsedu-school-management' ); ?></th>
                                    <th style="padding:10px; text-align: left; width: 22%;"><?php esc_html_e( 'Student Name', 'ifsedu-school-management' ); ?></th>
                                    
                                    <?php if ( $has_custom_breakdown ) : ?>
                                        <!-- Dynamic Custom Component Columns -->
                                        <?php foreach ( $custom_breakdown_components as $comp ) : ?>
                                            <th style="padding:10px;">
                                                <?php echo esc_html( (string) $comp['name'] ); ?><br>
                                                <span class="lbl-comp-summary" data-comp="<?php echo esc_attr( (string) $comp['name'] ); ?>" style="font-size:10px; color:#64748b; font-weight:600;">
                                                    Max: <?php echo (float) $comp['total']; ?> | &ge; <?php echo (float) $comp['pass']; ?>
                                                </span>
                                            </th>
                                        <?php endforeach; ?>
                                    <?php else : ?>
                                        <!-- Conditionally show MCQ column only if limit > 0 -->
                                        <?php if ( $mcq_lim > 0 ) : ?>
                                            <th style="padding:10px; width: 14%;">
                                                <?php esc_html_e( 'MCQ', 'ifsedu-school-management' ); ?><br>
                                                <span class="lbl-mcq-summary" style="font-size:10px; color:#64748b; font-weight:600;">Max: <?php echo esc_html( (string) $mcq_lim ); ?> | &ge; <?php echo esc_html( (string) $mcq_p_lim ); ?></span>
                                            </th>
                                        <?php endif; ?>

                                        <!-- Conditionally show CQ column only if limit > 0 -->
                                        <?php if ( $cq_lim > 0 ) : ?>
                                            <th style="padding:10px; width: 14%;">
                                                <?php esc_html_e( 'CQ Theory', 'ifsedu-school-management' ); ?><br>
                                                <span class="lbl-cq-summary" style="font-size:10px; color:#64748b; font-weight:600;">Max: <?php echo esc_html( (string) $cq_lim ); ?> | &ge; <?php echo esc_html( (string) $cq_p_lim ); ?></span>
                                            </th>
                                        <?php endif; ?>

                                        <!-- Conditionally show Practical column only if limit > 0 -->
                                        <?php if ( $pr_lim > 0 ) : ?>
                                            <th style="padding:10px; width: 14%;">
                                                <?php esc_html_e( 'Practical', 'ifsedu-school-management' ); ?><br>
                                                <span class="lbl-pr-summary" style="font-size:10px; color:#64748b; font-weight:600;">Max: <?php echo esc_html( (string) $pr_lim ); ?> | &ge; <?php echo esc_html( (string) $pr_p_lim ); ?></span>
                                            </th>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    <th style="padding:10px; width: 10%;"><?php esc_html_e( 'Total', 'ifsedu-school-management' ); ?></th>
                                    <th style="padding:10px; width: 8%;"><?php esc_html_e( 'Grade', 'ifsedu-school-management' ); ?></th>
                                    <th style="padding:10px; width: 8%;"><?php esc_html_e( 'GPA', 'ifsedu-school-management' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ( ! empty( $students_list ) ) : foreach ( $students_list as $s ) : 
                                    $student_internal_id = absint( (int) $s->id );
                                    $curr_res = $saved_marks[ $student_internal_id ] ?? null;
                                    
                                    // Check Exam Hall Attendance status
                                    $exam_att_status = $exam_att_map[ $student_internal_id ] ?? '';
                                    $has_attendance  = ( '' !== $exam_att_status );
                                    $is_absent       = ( 'Absent' === $exam_att_status );
                                    $is_unrecorded   = ( '' === $exam_att_status );

                                    if ( $is_absent ) {
                                        $curr_tot = '0.00';
                                        $curr_grd = 'A';
                                        $curr_gpa = '0.00';
                                        $is_fail  = false;
                                    } elseif ( $curr_res && isset( $curr_res->obtained_marks ) && '' !== trim( (string) $curr_res->obtained_marks ) ) {
                                        $curr_tot = number_format( (float) $curr_res->obtained_marks, 2, '.', '' );
                                        $curr_grd = esc_html( (string) $curr_res->grade );
                                        $curr_gpa = number_format( (float) $curr_res->gpa, 2 );
                                        $is_fail  = ( 'F' === $curr_grd );
                                    } else {
                                        $curr_tot = '—';
                                        $curr_grd = 'N/A';
                                        $curr_gpa = '—';
                                        $is_fail  = false;
                                    }

                                    // Parse component marks if present.
                                    $student_comp_marks = array();
                                    if ( $curr_res && ! empty( $curr_res->component_marks ) ) {
                                        $decoded_cm = json_decode( (string) $curr_res->component_marks, true );
                                        if ( is_array( $decoded_cm ) ) {
                                            $student_comp_marks = $decoded_cm;
                                        }
                                    }
                                ?>
                                <tr data-student-id="<?php echo esc_attr( (string) $student_internal_id ); ?>" class="<?php echo $is_absent ? 'ifs-absent-row-dimmed' : ( $is_unrecorded ? 'ifs-unrecorded-row-dimmed' : '' ); ?>" style="border-bottom:1px solid #f1f5f9; text-align:center;">
                                    <td style="padding:10px;"><strong>#<?php echo esc_html( (string) $s->roll_no ); ?></strong></td>
                                    <td style="padding:10px;"><code><?php echo esc_html( strtoupper( (string) $s->student_id ) ); ?></code></td>
                                    <td style="padding:10px; text-align: left; font-weight: 700; color: #0f172a;">
                                        <?php echo esc_html( (string) $s->full_name ); ?>
                                        <?php if ( $is_absent ) : ?>
                                            <span style="background:#fee2e2; color:#dc2626; font-size:10px; font-weight:800; padding:1px 6px; border-radius:4px; margin-left:6px;"><?php esc_html_e( 'ABSENT (A)', 'ifsedu-school-management' ); ?></span>
                                        <?php elseif ( $is_unrecorded ) : ?>
                                            <span style="background:#fef3c7; color:#d97706; font-size:10px; font-weight:800; padding:1px 6px; border-radius:4px; margin-left:6px;"><?php esc_html_e( 'ATTENDANCE PENDING', 'ifsedu-school-management' ); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <?php if ( $has_custom_breakdown ) : ?>
                                        <!-- Dynamic Custom Breakdown Inputs -->
                                        <?php foreach ( $custom_breakdown_components as $comp ) : 
                                            $comp_name = (string) $comp['name'];
                                            if ( $is_absent || $is_unrecorded ) {
                                                $val_c = 0.00;
                                            } else {
                                                $val_c = ( $curr_res && isset( $student_comp_marks[ $comp_name ] ) && '' !== trim( (string) $student_comp_marks[ $comp_name ] ) ) ? (float) $student_comp_marks[ $comp_name ] : '';
                                            }
                                        ?>
                                            <td style="padding:10px;">
                                                <input type="number" step="0.5" min="0" max="<?php echo (float) $comp['total']; ?>" 
                                                       name="custom_comp_val[<?php echo esc_attr( (string) $student_internal_id ); ?>][<?php echo esc_attr( $comp_name ); ?>]" 
                                                       class="ifs-educore-mark-cell-input inp-custom-comp" 
                                                       data-comp="<?php echo esc_attr( $comp_name ); ?>" 
                                                       data-max="<?php echo (float) $comp['total']; ?>" 
                                                       value="<?php echo esc_attr( ( $is_absent || $is_unrecorded ) ? '0' : ( '' !== $val_c ? (string) $val_c : '' ) ); ?>" 
                                                       placeholder="0" 
                                                       <?php disabled( $is_absent || $is_unrecorded ); ?>
                                                       style="width:75px; height:34px; text-align:center; border:1px solid #cbd5e1; border-radius:6px;">
                                            </td>
                                        <?php endforeach; ?>
                                    <?php else : 
                                        if ( $is_absent || $is_unrecorded ) {
                                            $curr_cq  = 0.00;
                                            $curr_mcq = 0.00;
                                            $curr_pr  = 0.00;
                                        } else {
                                            $curr_cq  = ( $curr_res && '' !== trim( (string) $curr_res->cq_marks ) ) ? (float) $curr_res->cq_marks : '';
                                            $curr_mcq = ( $curr_res && '' !== trim( (string) $curr_res->mcq_marks ) ) ? (float) $curr_res->mcq_marks : '';
                                            $curr_pr  = ( $curr_res && '' !== trim( (string) $curr_res->practical_marks ) ) ? (float) $curr_res->practical_marks : '';
                                        }
                                    ?>
                                        <!-- Conditionally render MCQ input -->
                                        <?php if ( $mcq_lim > 0 ) : ?>
                                            <td style="padding:10px;">
                                                <input type="number" step="0.5" min="0" max="<?php echo esc_attr( (string) $mcq_lim ); ?>" 
                                                       name="mcq_marks[<?php echo esc_attr( (string) $student_internal_id ); ?>]" 
                                                       class="ifs-educore-mark-cell-input inp-mcq" 
                                                       data-max="<?php echo esc_attr( (string) $mcq_lim ); ?>" 
                                                       value="<?php echo esc_attr( ( $is_absent || $is_unrecorded ) ? '0' : ( '' !== $curr_mcq ? (string) $curr_mcq : '' ) ); ?>" 
                                                       placeholder="0" 
                                                       <?php disabled( $is_absent || $is_unrecorded ); ?>
                                                       style="width:75px; height:34px; text-align:center; border:1px solid #cbd5e1; border-radius:6px;">
                                            </td>
                                        <?php endif; ?>

                                        <!-- Conditionally render CQ input -->
                                        <?php if ( $cq_lim > 0 ) : ?>
                                            <td style="padding:10px;">
                                                <input type="number" step="0.5" min="0" max="<?php echo esc_attr( (string) $cq_lim ); ?>" 
                                                       name="cq_marks[<?php echo esc_attr( (string) $student_internal_id ); ?>]" 
                                                       class="ifs-educore-mark-cell-input inp-cq" 
                                                       data-max="<?php echo esc_attr( (string) $cq_lim ); ?>" 
                                                       value="<?php echo esc_attr( ( $is_absent || $is_unrecorded ) ? '0' : ( '' !== $curr_cq ? (string) $curr_cq : '' ) ); ?>" 
                                                       placeholder="0" 
                                                       <?php disabled( $is_absent || $is_unrecorded ); ?>
                                                       style="width:75px; height:34px; text-align:center; border:1px solid #cbd5e1; border-radius:6px;">
                                            </td>
                                        <?php endif; ?>

                                        <!-- Conditionally render Practical input -->
                                        <?php if ( $pr_lim > 0 ) : ?>
                                            <td style="padding:10px;">
                                                <input type="number" step="0.5" min="0" max="<?php echo esc_attr( (string) $pr_lim ); ?>" 
                                                       name="practical_marks[<?php echo esc_attr( (string) $student_internal_id ); ?>]" 
                                                       class="ifs-educore-mark-cell-input inp-pr" 
                                                       data-max="<?php echo esc_attr( (string) $pr_lim ); ?>" 
                                                       value="<?php echo esc_attr( ( $is_absent || $is_unrecorded ) ? '0' : ( '' !== $curr_pr ? (string) $curr_pr : '' ) ); ?>" 
                                                       placeholder="0" 
                                                       <?php disabled( $is_absent || $is_unrecorded ); ?>
                                                       style="width:75px; height:34px; text-align:center; border:1px solid #cbd5e1; border-radius:6px;">
                                            </td>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    <!-- Calculated Total -->
                                    <td style="padding:10px;"><strong class="cell-total-obt" style="font-size: 14px; color: #0f172a;"><?php echo esc_html( $curr_tot ); ?></strong></td>

                                    <!-- Evaluated Grade -->
                                    <td style="padding:10px;">
                                        <span class="cell-grade" style="display:inline-block; padding:3px 8px; border-radius:4px; font-weight:700; font-size:12px; background:<?php echo ( 'N/A' === $curr_grd ) ? '#f1f5f9' : ( $is_fail ? '#fee2e2' : '#ecfdf5' ); ?>; color:<?php echo ( 'N/A' === $curr_grd ) ? '#64748b' : ( $is_fail ? '#dc2626' : '#047857' ); ?>;">
                                            <?php echo esc_html( $curr_grd ); ?>
                                        </span>
                                    </td>

                                    <!-- Evaluated GPA -->
                                    <td style="padding:10px;"><strong class="cell-gpa" style="color: <?php echo ( '—' === $curr_gpa ) ? '#64748b' : ( $is_fail ? '#dc2626' : '#00523c' ); ?>;"><?php echo esc_html( $curr_gpa ); ?></strong></td>
                                </tr>
                                <?php endforeach; else : ?>
                                    <tr>
                                        <td colspan="10" style="padding: 40px; color: #94a3b8; text-align:center;">
                                            <?php esc_html_e( 'No active students found matching the selected academic parameters.', 'ifsedu-school-management' ); ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ( ! empty( $students_list ) ) : ?>
                        <div style="text-align: right; margin-top: 20px;">
                            <button type="submit" name="educore_save_marks_matrix" class="ifs-educore-btn-submit" style="background:#00523c; color:#fff; border:none; padding:10px 24px; border-radius:8px; font-weight:700; cursor:pointer;">
                                <span class="dashicons dashicons-saved"></span>
                                <?php esc_html_e( 'Save All Marks', 'ifsedu-school-management' ); ?>
                            </button>
                        </div>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Client-Side Real-time Grading, Clamping, Session Storage & Attendance Lockout Handler -->
            <script type="text/javascript">
            document.addEventListener('DOMContentLoaded', function() {
                var isCustomBreakdown = document.getElementById('is_custom_breakdown').value === '1';
                var totalLimitInput   = document.getElementById('total_marks_limit');
                var passLimitInput    = document.getElementById('pass_marks_limit');

                var examId      = '<?php echo esc_js( (string) $filter_exam ); ?>';
                var className   = '<?php echo esc_js( (string) $filter_class ); ?>';
                var sectionName = '<?php echo esc_js( (string) $filter_section ); ?>';
                var subjectName = '<?php echo esc_js( (string) $filter_subject ); ?>';
                var storageKey  = 'educore_marks_draft_' + examId + '_' + className + '_' + sectionName + '_' + subjectName;

                var isDirty = false;

                function getLimits() {
                    var total = parseFloat(totalLimitInput ? totalLimitInput.value : 100) || 100;
                    var pass  = parseFloat(passLimitInput ? passLimitInput.value : 33) || 33;

                    if (isCustomBreakdown) {
                        var customMap = {};
                        document.querySelectorAll('.custom-comp-tot-limit').forEach(function(inp) {
                            var comp = inp.getAttribute('data-comp');
                            var passInp = document.querySelector('.custom-comp-pass-limit[data-comp="' + comp + '"]');
                            customMap[comp] = {
                                max: parseFloat(inp.value) || 0,
                                pass: parseFloat(passInp ? passInp.value : 0) || 0
                            };
                        });
                        return { total: total, pass: pass, custom: customMap };
                    } else {
                        return {
                            total: total,
                            pass: pass,
                            cqMax: parseFloat(document.getElementById('cq_marks_limit') ? document.getElementById('cq_marks_limit').value : 0) || 0,
                            cqPass: parseFloat(document.getElementById('cq_pass_limit') ? document.getElementById('cq_pass_limit').value : 0) || 0,
                            mcqMax: parseFloat(document.getElementById('mcq_marks_limit') ? document.getElementById('mcq_marks_limit').value : 0) || 0,
                            mcqPass: parseFloat(document.getElementById('mcq_pass_limit') ? document.getElementById('mcq_pass_limit').value : 0) || 0,
                            prMax: parseFloat(document.getElementById('pr_marks_limit') ? document.getElementById('pr_marks_limit').value : 0) || 0,
                            prPass: parseFloat(document.getElementById('pr_pass_limit') ? document.getElementById('pr_pass_limit').value : 0) || 0
                        };
                    }
                }

                function computeGradeAndGpa(obtained, total) {
                    var pct = (obtained / total) * 100;
                    if (pct >= 80) return { grade: 'A+', gpa: '5.00' };
                    if (pct >= 70) return { grade: 'A',  gpa: '4.00' };
                    if (pct >= 60) return { grade: 'A-', gpa: '3.50' };
                    if (pct >= 50) return { grade: 'B',  gpa: '3.00' };
                    if (pct >= 40) return { grade: 'C',  gpa: '2.00' };
                    if (pct >= 33) return { grade: 'D',  gpa: '1.00' };
                    return { grade: 'F', gpa: '0.00' };
                }

                function enforceBounds(input, maxAllowed) {
                    if (input.value.trim() === '') return;
                    var val = parseFloat(input.value);
                    if (val > maxAllowed) {
                        input.value = maxAllowed;
                    } else if (val < 0) {
                        input.value = 0;
                    }
                }

                function evaluateRow(row) {
                    var limits   = getLimits();
                    var obtained = 0;
                    var failed   = false;
                    var hasAnyInput = false;

                    var isAbsentRow = row.classList.contains('ifs-absent-row-dimmed');
                    var isUnrecordedRow = row.classList.contains('ifs-unrecorded-row-dimmed');

                    if (isAbsentRow || isUnrecordedRow) {
                        return;
                    }

                    if (isCustomBreakdown) {
                        row.querySelectorAll('.inp-custom-comp').forEach(function(inp) {
                            var comp = inp.getAttribute('data-comp');
                            var compRule = limits.custom[comp] || { max: 100, pass: 0 };
                            
                            inp.setAttribute('data-max', compRule.max);
                            enforceBounds(inp, compRule.max);

                            if (inp.value.trim() !== '') {
                                hasAnyInput = true;
                                var val = parseFloat(inp.value) || 0;
                                obtained += val;

                                if (compRule.pass > 0 && val < compRule.pass) {
                                    failed = true;
                                }
                            }
                        });
                        obtained = Math.min(obtained, limits.total);
                    } else {
                        var inpCq  = row.querySelector('.inp-cq');
                        var inpMcq = row.querySelector('.inp-mcq');
                        var inpPr  = row.querySelector('.inp-pr');

                        if (inpCq) { inpCq.setAttribute('data-max', limits.cqMax); enforceBounds(inpCq, limits.cqMax); }
                        if (inpMcq) { inpMcq.setAttribute('data-max', limits.mcqMax); enforceBounds(inpMcq, limits.mcqMax); }
                        if (inpPr) { inpPr.setAttribute('data-max', limits.prMax); enforceBounds(inpPr, limits.prMax); }

                        var valCq = 0, valMcq = 0, valPr = 0;

                        if (inpCq && inpCq.value.trim() !== '') {
                            hasAnyInput = true;
                            valCq = parseFloat(inpCq.value) || 0;
                            if (limits.cqPass > 0 && valCq < limits.cqPass) failed = true;
                        }
                        if (inpMcq && inpMcq.value.trim() !== '') {
                            hasAnyInput = true;
                            valMcq = parseFloat(inpMcq.value) || 0;
                            if (limits.mcqPass > 0 && valMcq < limits.mcqPass) failed = true;
                        }
                        if (inpPr && inpPr.value.trim() !== '') {
                            hasAnyInput = true;
                            valPr = parseFloat(inpPr.value) || 0;
                            if (limits.prPass > 0 && valPr < limits.prPass) failed = true;
                        }

                        obtained = Math.min(valCq + valMcq + valPr, limits.total);
                    }

                    var gradeBadge = row.querySelector('.cell-grade');
                    var gpaCell    = row.querySelector('.cell-gpa');
                    var totalCell  = row.querySelector('.cell-total-obt');

                    if (!hasAnyInput) {
                        totalCell.textContent = '—';
                        gradeBadge.textContent = 'N/A';
                        gradeBadge.style.background = '#f1f5f9';
                        gradeBadge.style.color = '#64748b';
                        gpaCell.textContent    = '—';
                        gpaCell.style.color    = '#64748b';
                        return;
                    }

                    if (obtained < limits.pass) failed = true;

                    totalCell.textContent = obtained.toFixed(2);

                    if (failed) {
                        gradeBadge.textContent = 'F';
                        gradeBadge.style.background = '#fee2e2';
                        gradeBadge.style.color = '#dc2626';
                        gpaCell.textContent    = '0.00';
                        gpaCell.style.color    = '#dc2626';
                    } else {
                        var res = computeGradeAndGpa(obtained, limits.total);
                        gradeBadge.textContent = res.grade;
                        gradeBadge.style.background = '#ecfdf5';
                        gradeBadge.style.color = '#047857';
                        gpaCell.textContent    = res.gpa;
                        gpaCell.style.color    = '#00523c';
                    }
                }

                var table = document.getElementById('dptMarksEntryTable');
                var form  = document.getElementById('educoreMarksMatrixForm');

                if (table) {
                    try {
                        var savedDraft = JSON.parse(sessionStorage.getItem(storageKey));
                        if (savedDraft && typeof savedDraft === 'object') {
                            var restoredAny = false;
                            Object.keys(savedDraft).forEach(function(inputName) {
                                var input = form.querySelector('[name="' + inputName + '"]');
                                if (input && !input.disabled) {
                                    input.value = savedDraft[inputName];
                                    restoredAny = true;
                                }
                            });
                            if (restoredAny) {
                                isDirty = true;
                                table.querySelectorAll('tbody tr').forEach(function(row) {
                                    evaluateRow(row);
                                });
                            }
                        }
                    } catch (e) {}

                    table.querySelectorAll('tbody tr').forEach(function(row) {
                        evaluateRow(row);
                    });

                    table.addEventListener('input', function(e) {
                        if (e.target.classList.contains('ifs-educore-mark-cell-input') && !e.target.disabled) {
                            isDirty = true;
                            var row = e.target.closest('tr');
                            if (row) evaluateRow(row);

                            try {
                                var draftData = JSON.parse(sessionStorage.getItem(storageKey)) || {};
                                draftData[e.target.name] = e.target.value;
                                sessionStorage.setItem(storageKey, JSON.stringify(draftData));
                            } catch (err) {}
                        }
                    });
                }

                if (form) {
                    form.addEventListener('submit', function() {
                        isDirty = false;
                        sessionStorage.removeItem(storageKey);
                    });
                }

                window.addEventListener('beforeunload', function(e) {
                    if (isDirty) {
                        e.preventDefault();
                        e.returnValue = '';
                    }
                });
            });
            </script>
        <?php endif; ?>

    </div>
    <?php
}
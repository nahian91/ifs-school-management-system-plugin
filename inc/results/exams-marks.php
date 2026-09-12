<?php
/**
 * High-End Marks Entry Matrix & Grading Evaluation Engine
 * File: inc/results/exams-marks.php
 * Text Domain: ifsedu-school-management
 * Architecture: Neo-Bento Interface with Real-time Auto Grading, Section-Aware Subject Loading, Auto-Draft Session Storage & Unsaved Warning
 * Teacher Scope: Restricts Class/Section/Subject dropdowns to `sms_teacher_subjects` for logged-in Teachers.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// --------------------------------------------------------------------------
// 1. AJAX HANDLERS (Filtered by Exam, Class, Section & Teacher Assignments)
// --------------------------------------------------------------------------
add_action( 'wp_ajax_ifs_educore_get_classes_by_exam_marks', 'ifs_educore_get_classes_by_exam_marks_handler' );
/**
 * AJAX Handler: Get classes filtered by exam and teacher assignment.
 */
function ifs_educore_get_classes_by_exam_marks_handler() {
    check_ajax_referer( 'ifs_educore_marks_nonce', 'security' );

    $current_user = wp_get_current_user();
    $is_admin     = current_user_can( 'manage_options' ) || in_array( 'administrator', (array) $current_user->roles, true );
    $is_staff     = false;

    if ( function_exists( 'educore_has_access' ) ) {
        $is_staff = educore_has_access( array( 'teacher', 'staff', 'operator', 'instructor', 'editor', 'author' ) );
    }

    global $wpdb;
    $table_staff            = $wpdb->prefix . 'sms_staff';
    $table_exams            = $wpdb->prefix . 'sms_exams';
    $table_units            = $wpdb->prefix . 'sms_academic_units';
    $table_teacher_subjects = $wpdb->prefix . 'sms_teacher_subjects';

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
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

    $exam_id = isset( $_POST['exam_id'] ) ? absint( $_POST['exam_id'] ) : 0;
    if ( $exam_id <= 0 ) {
        wp_send_json_success( array() );
    }

    $raw_exam_classes = $wpdb->get_var( $wpdb->prepare( "SELECT class_name FROM `{$table_exams}` WHERE id = %d LIMIT 1", $exam_id ) );
    if ( empty( $raw_exam_classes ) ) {
        wp_send_json_success( array() );
    }

    $exam_classes = array_map( 'trim', explode( ',', (string) $raw_exam_classes ) );

    // Fetch class sort order dictionary.
    $class_order_rows = $wpdb->get_results( "SELECT class_name, MIN(sort_order) as min_sort FROM `{$table_units}` GROUP BY class_name" );
    $class_order_map  = array();
    if ( ! empty( $class_order_rows ) ) {
        foreach ( $class_order_rows as $cor ) {
            $class_order_map[ $cor->class_name ] = (int) $cor->min_sort;
        }
    }

    // If teacher, intersect with teacher's assigned classes.
    if ( ! $is_admin ) {
        $teacher_id = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM `{$table_staff}` WHERE wp_user_id = %d OR email = %s OR full_name = %s LIMIT 1",
                $current_user->ID,
                $current_user->user_email,
                $current_user->display_name
            )
        );

        if ( $teacher_id > 0 ) {
            $teacher_classes = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT DISTINCT u.class_name 
                     FROM `{$table_teacher_subjects}` ts
                     INNER JOIN `{$table_units}` u ON ts.class_id = u.id 
                     WHERE ts.teacher_id = %d",
                    $teacher_id
                )
            );
            $exam_classes = array_intersect( $exam_classes, array_map( 'trim', (array) $teacher_classes ) );
        }
    }

    $exam_classes = array_values( array_unique( array_filter( $exam_classes ) ) );

    // Sort by sort_order first, then natural case comparison.
    usort( $exam_classes, function( $a, $b ) use ( $class_order_map ) {
        $order_a = isset( $class_order_map[ $a ] ) ? $class_order_map[ $a ] : 0;
        $order_b = isset( $class_order_map[ $b ] ) ? $class_order_map[ $b ] : 0;
        if ( $order_a !== $order_b ) {
            return $order_a - $order_b;
        }
        return strnatcasecmp( $a, $b );
    } );
    // phpcs:enable

    wp_send_json_success( array_values( $exam_classes ) );
}

add_action( 'wp_ajax_ifs_educore_get_sections_by_class_marks', 'ifs_educore_get_sections_by_class_marks_handler' );
/**
 * AJAX Handler: Get sections filtered by class and teacher allocation.
 */
function ifs_educore_get_sections_by_class_marks_handler() {
    check_ajax_referer( 'ifs_educore_marks_nonce', 'security' );

    $current_user = wp_get_current_user();
    $is_admin     = current_user_can( 'manage_options' ) || in_array( 'administrator', (array) $current_user->roles, true );
    $is_staff     = false;

    if ( function_exists( 'educore_has_access' ) ) {
        $is_staff = educore_has_access( array( 'teacher', 'staff', 'operator', 'instructor', 'editor', 'author' ) );
    }

    global $wpdb;
    $table_staff = $wpdb->prefix . 'sms_staff';

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
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

    $table_units            = $wpdb->prefix . 'sms_academic_units';
    $table_teacher_subjects = $wpdb->prefix . 'sms_teacher_subjects';
    $class_name             = isset( $_POST['class_name'] ) ? sanitize_text_field( wp_unslash( $_POST['class_name'] ) ) : '';

    if ( empty( $class_name ) ) {
        wp_send_json_success( array() );
    }

    if ( ! $is_admin ) {
        $teacher_id = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM `{$table_staff}` WHERE wp_user_id = %d OR email = %s OR full_name = %s LIMIT 1",
                $current_user->ID,
                $current_user->user_email,
                $current_user->display_name
            )
        );

        if ( $teacher_id > 0 ) {
            $sections = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT DISTINCT u.section_name 
                     FROM `{$table_teacher_subjects}` ts
                     INNER JOIN `{$table_units}` u ON ts.class_id = u.id 
                     WHERE ts.teacher_id = %d AND u.class_name = %s AND u.section_name != '' 
                     ORDER BY u.sort_order ASC, u.section_name ASC",
                    $teacher_id,
                    $class_name
                )
            );
            wp_send_json_success( is_array( $sections ) ? $sections : array() );
        }
    }

    $sections = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT DISTINCT section_name FROM `{$table_units}` WHERE class_name = %s AND section_name != '' ORDER BY sort_order ASC, section_name ASC",
            $class_name
        )
    );
    // phpcs:enable

    wp_send_json_success( is_array( $sections ) ? $sections : array() );
}

add_action( 'wp_ajax_ifs_educore_get_subjects_for_marks_matrix', 'ifs_educore_get_subjects_for_marks_matrix_handler' );
/**
 * AJAX Handler: Get subjects for the marks matrix.
 */
function ifs_educore_get_subjects_for_marks_matrix_handler() {
    check_ajax_referer( 'ifs_educore_marks_nonce', 'security' );

    $current_user = wp_get_current_user();
    $is_admin     = current_user_can( 'manage_options' ) || in_array( 'administrator', (array) $current_user->roles, true );
    $is_staff     = false;

    if ( function_exists( 'educore_has_access' ) ) {
        $is_staff = educore_has_access( array( 'teacher', 'staff', 'operator', 'instructor', 'editor', 'author' ) );
    }

    global $wpdb;
    $table_staff = $wpdb->prefix . 'sms_staff';

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
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

    $table_exams    = $wpdb->prefix . 'sms_exams';
    $table_subjects = $wpdb->prefix . 'sms_subjects';
    $table_units    = $wpdb->prefix . 'sms_academic_units';
    $table_results  = $wpdb->prefix . 'sms_results';
    $table_students = $wpdb->prefix . 'sms_students';

    $exam_id      = isset( $_POST['exam_id'] ) ? absint( $_POST['exam_id'] ) : 0;
    $class_name   = isset( $_POST['class_name'] ) ? sanitize_text_field( wp_unslash( $_POST['class_name'] ) ) : '';
    $section_name = isset( $_POST['section_name'] ) ? sanitize_text_field( wp_unslash( $_POST['section_name'] ) ) : '';

    if ( empty( $class_name ) ) {
        wp_send_json_success( array() );
    }

    $exam_subject_ids = array();
    if ( $exam_id > 0 ) {
        $subject_json = $wpdb->get_var( $wpdb->prepare( "SELECT subject_ids FROM `{$table_exams}` WHERE id = %d LIMIT 1", $exam_id ) );
        if ( ! empty( $subject_json ) ) {
            $decoded_map = json_decode( $subject_json, true );
            if ( isset( $decoded_map[ $class_name ] ) && is_array( $decoded_map[ $class_name ] ) && ! empty( $decoded_map[ $class_name ] ) ) {
                $exam_subject_ids = array_map( 'absint', $decoded_map[ $class_name ] );
            }
        }
    }

    $subjects = array();
    if ( ! empty( $section_name ) ) {
        $subjects = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DISTINCT s.id, s.subject_name, s.subject_code, s.subject_order, s.total_marks, s.pass_marks, s.cq_marks, s.cq_pass, s.mcq_marks, s.mcq_pass, s.practical_marks, s.practical_pass, s.breakdown_data 
                 FROM `{$table_subjects}` s 
                 INNER JOIN `{$table_units}` u ON s.class_id = u.id 
                 WHERE u.class_name = %s AND u.section_name = %s 
                 ORDER BY s.subject_order ASC, s.subject_name ASC",
                $class_name,
                $section_name
            )
        );
    }

    if ( empty( $subjects ) ) {
        $subjects = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DISTINCT s.id, s.subject_name, s.subject_code, s.subject_order, s.total_marks, s.pass_marks, s.cq_marks, s.cq_pass, s.mcq_marks, s.mcq_pass, s.practical_marks, s.practical_pass, s.breakdown_data 
                 FROM `{$table_subjects}` s 
                 INNER JOIN `{$table_units}` u ON s.class_id = u.id 
                 WHERE u.class_name = %s 
                 ORDER BY s.subject_order ASC, s.subject_name ASC",
                $class_name
            )
        );
    }

    if ( ! empty( $exam_subject_ids ) && ! empty( $subjects ) ) {
        $subjects = array_values( array_filter( $subjects, function( $sub ) use ( $exam_subject_ids ) {
            return in_array( (int) $sub->id, $exam_subject_ids, true );
        } ) );
    }

    // Count total active students in this class/section.
    if ( ! empty( $section_name ) ) {
        $total_students = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM `{$table_students}` WHERE status = 'Active' AND class_name = %s AND section_name = %s",
                $class_name,
                $section_name
            )
        );
    } else {
        $total_students = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM `{$table_students}` WHERE status = 'Active' AND class_name = %s",
                $class_name
            )
        );
    }

    // Deduplicate subjects and calculate entry counts.
    $unique_subjects = array();
    $seen_sub_names  = array();
    if ( ! empty( $subjects ) ) {
        foreach ( $subjects as $s_item ) {
            $s_norm = trim( strtolower( (string) $s_item->subject_name ) );
            if ( ! in_array( $s_norm, $seen_sub_names, true ) ) {
                $seen_sub_names[] = $s_norm;

                if ( $exam_id > 0 ) {
                    if ( ! empty( $section_name ) ) {
                        // Fixed: Changed r.section_name to st.section_name.
                        $entered_count = (int) $wpdb->get_var(
                            $wpdb->prepare(
                                "SELECT COUNT(r.id) FROM `{$table_results}` r INNER JOIN `{$table_students}` st ON r.student_id = st.id WHERE r.exam_id = %d AND r.class_name = %s AND st.section_name = %s AND r.subject_name = %s",
                                $exam_id,
                                $class_name,
                                $section_name,
                                $s_item->subject_name
                            )
                        );
                    } else {
                        $entered_count = (int) $wpdb->get_var(
                            $wpdb->prepare(
                                "SELECT COUNT(r.id) FROM `{$table_results}` r WHERE r.exam_id = %d AND r.class_name = %s AND r.subject_name = %s",
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
    // phpcs:enable

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
    function educore_calculate_grade( $obtained, $total = 100 ) {
        $total = floatval( $total ) > 0 ? floatval( $total ) : 100;
        $pct   = ( floatval( $obtained ) / $total ) * 100;

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
function educore_exams_marks_view() {
    global $wpdb;
    $current_user = wp_get_current_user();

    $table_students         = $wpdb->prefix . 'sms_students';
    $table_exams            = $wpdb->prefix . 'sms_exams';
    $table_results          = $wpdb->prefix . 'sms_results';
    $table_units            = $wpdb->prefix . 'sms_academic_units';
    $table_subjects         = $wpdb->prefix . 'sms_subjects';
    $table_staff            = $wpdb->prefix . 'sms_staff';
    $table_teacher_subjects = $wpdb->prefix . 'sms_teacher_subjects';

    // Auto-migrate component_marks column in results if missing.
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $col_check = $wpdb->get_results( "SHOW COLUMNS FROM `{$table_results}` LIKE 'component_marks'" );
    if ( empty( $col_check ) ) {
        $wpdb->query( "ALTER TABLE `{$table_results}` ADD COLUMN `component_marks` longtext DEFAULT '' NOT NULL AFTER `practical_marks`" );
    }
    // phpcs:enable

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

    if ( ! $is_admin && ! $is_staff ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to enter examination marks.', 'ifsedu-school-management' ) );
    }

    $raw_req_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
    $current_uri = remove_query_arg( array( 'status', 'msg' ), $raw_req_uri );
    $base_url    = esc_url_raw( $current_uri );
    $notice_msg  = '';

    // Unified Parameter Resolution.
    $filter_exam    = isset( $_REQUEST['exam_id'] ) ? absint( $_REQUEST['exam_id'] ) : 0;
    $filter_class   = isset( $_REQUEST['class_name'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['class_name'] ) ) : '';
    $filter_section = isset( $_REQUEST['section_name'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['section_name'] ) ) : '';
    $filter_subject = isset( $_REQUEST['subject_name'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['subject_name'] ) ) : '';

    // Fetch Class Sort Order Dictionary.
    $class_order_rows = $wpdb->get_results( "SELECT class_name, MIN(sort_order) as min_sort FROM `{$table_units}` GROUP BY class_name" );
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
                "SELECT id FROM `{$table_staff}` WHERE wp_user_id = %d OR email = %s OR full_name = %s LIMIT 1",
                $current_user->ID,
                $current_user->user_email,
                $current_user->display_name
            )
        );

        if ( $teacher_id > 0 ) {
            $allocations = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT DISTINCT u.class_name, u.section_name, s.subject_name 
                     FROM `{$table_teacher_subjects}` ts
                     INNER JOIN `{$table_units}` u ON ts.class_id = u.id 
                     INNER JOIN `{$table_subjects}` s ON ts.subject_id = s.id 
                     WHERE ts.teacher_id = %d 
                     ORDER BY u.sort_order ASC, CAST(u.class_name AS UNSIGNED) ASC, u.class_name ASC, s.subject_order ASC",
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
        if ( isset( $_POST['ifs_educore_marks_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ifs_educore_marks_nonce'] ) ), 'save_marks_action' ) ) {
            
            if ( ! $is_admin && ! empty( $teacher_assigned_classes ) && ( ! in_array( $filter_class, $teacher_assigned_classes, true ) || ! in_array( $filter_subject, (array) ( $teacher_assigned_subs[ $filter_class ] ?? array() ), true ) ) ) {
                wp_die( esc_html__( 'Security Check: You are not authorized to submit marks for this class/subject allocation.', 'ifsedu-school-management' ) );
            }

            $total_marks  = isset( $_POST['total_marks_limit'] ) ? floatval( $_POST['total_marks_limit'] ) : 100.00;
            $pass_marks   = isset( $_POST['pass_marks_limit'] ) ? floatval( $_POST['pass_marks_limit'] ) : 33.00;
            $cq_lim_post  = isset( $_POST['cq_marks_limit'] ) ? floatval( $_POST['cq_marks_limit'] ) : 70.00;
            $mcq_lim_post = isset( $_POST['mcq_marks_limit'] ) ? floatval( $_POST['mcq_marks_limit'] ) : 30.00;
            $pr_lim_post  = isset( $_POST['pr_marks_limit'] ) ? floatval( $_POST['pr_marks_limit'] ) : 0.00;

            $cq_pass      = isset( $_POST['cq_pass_limit'] ) ? floatval( $_POST['cq_pass_limit'] ) : 0.00;
            $mcq_pass     = isset( $_POST['mcq_pass_limit'] ) ? floatval( $_POST['mcq_pass_limit'] ) : 0.00;
            $pr_pass      = isset( $_POST['pr_pass_limit'] ) ? floatval( $_POST['pr_pass_limit'] ) : 0.00;

            $is_custom_breakdown = isset( $_POST['is_custom_breakdown'] ) && '1' === $_POST['is_custom_breakdown'];
            $custom_comp_totals  = isset( $_POST['custom_comp_total'] ) && is_array( $_POST['custom_comp_total'] ) ? array_map( 'floatval', wp_unslash( $_POST['custom_comp_total'] ) ) : array();
            $custom_comp_passes  = isset( $_POST['custom_comp_pass'] ) && is_array( $_POST['custom_comp_pass'] ) ? array_map( 'floatval', wp_unslash( $_POST['custom_comp_pass'] ) ) : array();
            $custom_student_vals = isset( $_POST['custom_comp_val'] ) && is_array( $_POST['custom_comp_val'] ) ? wp_unslash( $_POST['custom_comp_val'] ) : array();

            $raw_cq       = ( isset( $_POST['cq_marks'] ) && is_array( $_POST['cq_marks'] ) ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['cq_marks'] ) ) : array();
            $raw_mcq      = ( isset( $_POST['mcq_marks'] ) && is_array( $_POST['mcq_marks'] ) ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['mcq_marks'] ) ) : array();
            $raw_pr       = ( isset( $_POST['practical_marks'] ) && is_array( $_POST['practical_marks'] ) ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['practical_marks'] ) ) : array();

            $students_cq  = array_map( 'floatval', $raw_cq );
            $students_mcq = array_map( 'floatval', $raw_mcq );
            $students_pr  = array_map( 'floatval', $raw_pr );

            $saved_count = 0;
            if ( $filter_exam > 0 && ! empty( $filter_class ) && ! empty( $filter_subject ) ) {
                
                $student_ids = array();
                if ( $is_custom_breakdown ) {
                    $student_ids = array_keys( $custom_student_vals );
                } else {
                    $student_ids = array_keys( $students_cq );
                }

                foreach ( $student_ids as $s_id ) {
                    $s_id_int   = absint( $s_id );
                    $obtained   = 0.00;
                    $has_failed = false;
                    $comp_json  = '';

                    $cq_val  = 0.00;
                    $mcq_val = 0.00;
                    $pr_val  = 0.00;

                    if ( $is_custom_breakdown && isset( $custom_student_vals[ $s_id_int ] ) && is_array( $custom_student_vals[ $s_id_int ] ) ) {
                        $comp_saved_data = array();
                        foreach ( $custom_student_vals[ $s_id_int ] as $comp_k => $comp_val ) {
                            $c_val  = floatval( $comp_val );
                            $c_max  = isset( $custom_comp_totals[ $comp_k ] ) ? floatval( $custom_comp_totals[ $comp_k ] ) : 100.00;
                            $c_pass = isset( $custom_comp_passes[ $comp_k ] ) ? floatval( $custom_comp_passes[ $comp_k ] ) : 0.00;
                            
                            $c_val_clamped = max( 0, min( $c_val, $c_max ) );
                            $obtained += $c_val_clamped;

                            if ( $c_pass > 0 && $c_val_clamped < $c_pass ) {
                                $has_failed = true;
                            }

                            $comp_saved_data[ sanitize_text_field( $comp_k ) ] = $c_val_clamped;
                        }
                        $comp_json = wp_json_encode( $comp_saved_data );
                        $obtained  = min( $obtained, $total_marks );
                    } else {
                        $cq_raw  = isset( $students_cq[ $s_id_int ] ) ? floatval( $students_cq[ $s_id_int ] ) : 0.00;
                        $cq_val  = max( 0, min( $cq_raw, $cq_lim_post ) );

                        $mcq_raw = isset( $students_mcq[ $s_id_int ] ) ? floatval( $students_mcq[ $s_id_int ] ) : 0.00;
                        $mcq_val = max( 0, min( $mcq_raw, $mcq_lim_post ) );

                        $pr_raw  = isset( $students_pr[ $s_id_int ] ) ? floatval( $students_pr[ $s_id_int ] ) : 0.00;
                        $pr_val  = max( 0, min( $pr_raw, $pr_lim_post ) );

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
                        $grade      = $grade_eval[0];
                        $gpa        = $grade_eval[1];
                    }

                    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $existing_id = (int) $wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT id FROM `{$table_results}` WHERE exam_id = %d AND student_id = %d AND subject_name = %s LIMIT 1",
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
                    // phpcs:enable
                    $saved_count++;
                }

                if ( function_exists( 'educore_log_activity' ) ) {
                    /* translators: 1: Saved student count, 2: Filter subject */
                    educore_log_activity( sprintf( __( 'Evaluated and saved marks for %1$d students in %2$s', 'ifsedu-school-management' ), $saved_count, $filter_subject ) );
                }

                $notice_msg = sprintf(
                    esc_html__( 'Successfully evaluated and saved marks for %d students.', 'ifsedu-school-management' ),
                    $saved_count
                );
            }
        }
    }

    // Fetch Examinations.
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $exams = $wpdb->get_results( "SELECT id, exam_name, class_name, subject_ids FROM `{$table_exams}` ORDER BY id DESC" );

    // Fetch Classes ONLY assigned to the active selected Exam (Ordered by sort_order).
    $academic_classes = array();
    $exam_subject_map = array();

    if ( $filter_exam > 0 ) {
        $exam_row = $wpdb->get_row( $wpdb->prepare( "SELECT class_name, subject_ids FROM `{$table_exams}` WHERE id = %d LIMIT 1", $filter_exam ) );
        if ( ! empty( $exam_row ) ) {
            if ( ! empty( $exam_row->class_name ) ) {
                $parsed_classes = array_map( 'trim', explode( ',', (string) $exam_row->class_name ) );
                if ( ! $is_admin && ! empty( $teacher_assigned_classes ) ) {
                    $academic_classes = array_intersect( $parsed_classes, $teacher_assigned_classes );
                } else {
                    $academic_classes = $parsed_classes;
                }
                $academic_classes = array_values( array_unique( array_filter( $academic_classes ) ) );
                
                usort( $academic_classes, function( $a, $b ) use ( $class_order_map ) {
                    $order_a = isset( $class_order_map[ $a ] ) ? $class_order_map[ $a ] : 0;
                    $order_b = isset( $class_order_map[ $b ] ) ? $class_order_map[ $b ] : 0;
                    if ( $order_a !== $order_b ) {
                        return $order_a - $order_b;
                    }
                    return strnatcasecmp( $a, $b );
                } );
            }

            if ( ! empty( $exam_row->subject_ids ) ) {
                $decoded = json_decode( $exam_row->subject_ids, true );
                if ( is_array( $decoded ) ) {
                    $exam_subject_map = $decoded;
                }
            }
        }
    }

    // Pre-populate Available Sections (Ordered by sort_order).
    $available_sections = array();
    if ( ! empty( $filter_class ) ) {
        if ( ! $is_admin && $teacher_id > 0 ) {
            $available_sections = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT DISTINCT u.section_name 
                     FROM `{$table_teacher_subjects}` ts
                     INNER JOIN `{$table_units}` u ON ts.class_id = u.id 
                     WHERE ts.teacher_id = %d AND u.class_name = %s AND u.section_name != '' 
                     ORDER BY u.sort_order ASC, u.section_name ASC",
                    $teacher_id,
                    $filter_class
                )
            );
        } else {
            $available_sections = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT DISTINCT section_name FROM `{$table_units}` WHERE class_name = %s AND section_name != '' ORDER BY sort_order ASC, section_name ASC",
                    $filter_class
                )
            );
        }
    }

    // Total active students in class/section for entry status calculation.
    if ( ! empty( $filter_class ) ) {
        if ( ! empty( $filter_section ) ) {
            $total_class_students = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM `{$table_students}` WHERE status = 'Active' AND class_name = %s AND section_name = %s",
                    $filter_class,
                    $filter_section
                )
            );
        } else {
            $total_class_students = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM `{$table_students}` WHERE status = 'Active' AND class_name = %s",
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
                    "SELECT DISTINCT s.* FROM `{$table_subjects}` s 
                     INNER JOIN `{$table_units}` u ON s.class_id = u.id 
                     WHERE u.class_name = %s AND u.section_name = %s 
                     ORDER BY s.subject_order ASC, s.subject_name ASC",
                    $filter_class,
                    $filter_section
                )
            );
        }

        if ( empty( $raw_subs ) ) {
            if ( ! $is_admin && $teacher_id > 0 ) {
                $raw_subs = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT DISTINCT s.id, s.subject_name, s.subject_code, s.subject_order, s.total_marks, s.pass_marks, s.cq_marks, s.cq_pass, s.mcq_marks, s.mcq_pass, s.practical_marks, s.practical_pass, s.breakdown_data, s.class_id  
                         FROM `{$table_teacher_subjects}` ts
                         INNER JOIN `{$table_subjects}` s ON ts.subject_id = s.id 
                         INNER JOIN `{$table_units}` u ON ts.class_id = u.id 
                         WHERE ts.teacher_id = %d AND u.class_name = %s 
                         ORDER BY s.subject_order ASC, s.subject_name ASC",
                        $teacher_id,
                        $filter_class
                    )
                );
            } else {
                $raw_subs = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT DISTINCT s.* FROM `{$table_subjects}` s 
                         INNER JOIN `{$table_units}` u ON s.class_id = u.id 
                         WHERE u.class_name = %s 
                         ORDER BY s.subject_order ASC, s.subject_name ASC",
                        $filter_class
                    )
                );
            }
        }

        // Filter by Exam Scheme Subject Configuration if applicable.
        if ( ! empty( $exam_subject_map[ $filter_class ] ) && is_array( $exam_subject_map[ $filter_class ] ) ) {
            $allowed_ids = array_map( 'absint', $exam_subject_map[ $filter_class ] );
            $raw_subs    = array_values( array_filter( $raw_subs, function( $sub ) use ( $allowed_ids ) {
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
                                    "SELECT COUNT(r.id) FROM `{$table_results}` r INNER JOIN `{$table_students}` st ON r.student_id = st.id WHERE r.exam_id = %d AND r.class_name = %s AND st.section_name = %s AND r.subject_name = %s",
                                    $filter_exam,
                                    $filter_class,
                                    $filter_section,
                                    $s_item->subject_name
                                )
                            );
                        } else {
                            $entered_count = (int) $wpdb->get_var(
                                $wpdb->prepare(
                                    "SELECT COUNT(r.id) FROM `{$table_results}` r WHERE r.exam_id = %d AND r.class_name = %s AND r.subject_name = %s",
                                    $filter_exam,
                                    $filter_class,
                                    $s_item->subject_name
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
    // phpcs:enable

    // Parse Custom Breakdown Data if configured.
    $custom_breakdown_components = array();
    if ( $active_subject_obj && ! empty( $active_subject_obj->breakdown_data ) ) {
        $parsed_bd = json_decode( $active_subject_obj->breakdown_data, true );
        if ( is_array( $parsed_bd ) && ! empty( $parsed_bd ) ) {
            $custom_breakdown_components = $parsed_bd;
        }
    }
    $has_custom_breakdown = ! empty( $custom_breakdown_components );

    // Fetch Active Students Dataset & Pre-existing Marks.
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $students_list = array();
    $saved_marks   = array();

    if ( $filter_exam > 0 && ! empty( $filter_class ) && ! empty( $filter_subject ) ) {
        if ( ! empty( $filter_section ) ) {
            $students_list = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, full_name, student_id, roll_no, class_name, section_name 
                     FROM `{$table_students}` 
                     WHERE status = 'Active' AND class_name = %s AND section_name = %s 
                     ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC",
                    $filter_class,
                    $filter_section
                )
            );
        } else {
            $students_list = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, full_name, student_id, roll_no, class_name, section_name 
                     FROM `{$table_students}` 
                     WHERE status = 'Active' AND class_name = %s 
                     ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC",
                    $filter_class
                )
            );
        }

        $existing_results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT student_id, cq_marks, mcq_marks, practical_marks, component_marks, obtained_marks, total_marks, grade, gpa 
                 FROM `{$table_results}` 
                 WHERE exam_id = %d AND class_name = %s AND subject_name = %s",
                $filter_exam,
                $filter_class,
                $filter_subject
            ),
            OBJECT_K
        );

        if ( ! empty( $existing_results ) ) {
            $saved_marks = $existing_results;
        }
    }
    // phpcs:enable
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
            background-image: url('data:image/svg+xml;utf8,<svg fill="%2300523c" height="20" viewBox="0 0 24 24" width="20" xmlns="http://www.w3.org/2000/svg"><path d="M7 10l5 5 5-5z"/></svg>') !important;
        }
        .ifs-educore-select:disabled {
            background-color: #f8fafc !important;
            color: #94a3b8 !important;
            border-color: #e2e8f0 !important;
            cursor: not-allowed;
            opacity: 0.85;
            background-image: url('data:image/svg+xml;utf8,<svg fill="%23cbd5e1" height="20" viewBox="0 0 24 24" width="20" xmlns="http://www.w3.org/2000/svg"><path d="M7 10l5 5 5-5z"/></svg>') !important;
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
            <form method="GET" action="<?php echo esc_url( $base_url ); ?>" id="educoreMarksFilterForm">
                <?php 
                $parsed_url = wp_parse_url( $base_url );
                if ( isset( $parsed_url['query'] ) ) {
                    parse_str( $parsed_url['query'], $query_params );
                    foreach ( $query_params as $param_key => $param_val ) {
                        if ( ! in_array( $param_key, array( 'exam_id', 'class_name', 'section_name', 'subject_name' ), true ) ) {
                            echo '<input type="hidden" name="' . esc_attr( $param_key ) . '" value="' . esc_attr( $param_val ) . '">';
                        }
                    }
                }
                ?>
                <input type="hidden" name="sub" value="marks">

                <div class="ifs-educore-filter-grid">
                    <!-- 1. Select Exam -->
                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-form-label">
                            <span class="dashicons dashicons-calendar-alt" style="font-size:14px; width:14px; height:14px; color:#00523c;"></span>
                            <?php esc_html_e( '1. Select Exam', 'ifsedu-school-management' ); ?> <span style="color:#ef4444;">*</span>
                        </label>
                        <select name="exam_id" id="ifs_educore_marks_exam_select" class="ifs-educore-select" required>
                            <option value=""><?php esc_html_e( '-- Choose Exam --', 'ifsedu-school-management' ); ?></option>
                            <?php foreach ( $exams as $ex ) : ?>
                                <option value="<?php echo absint( $ex->id ); ?>" <?php selected( $filter_exam, $ex->id ); ?>>
                                    <?php echo esc_html( $ex->exam_name ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- 2. Exam Class Selection -->
                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-form-label">
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
                        <label class="ifs-educore-form-label">
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

                    <!-- 4. Subject Selection Separated into Completed vs Pending Optgroups -->
                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-form-label">
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
                                $status_badge = sprintf( ' [%d/%d Entered]', intval( $sub_item->entered_count ), intval( $total_class_students ) );
                                $label_text   = $sub_item->subject_name . ( $sub_item->subject_code ? ' (' . $sub_item->subject_code . ')' : '' ) . $status_badge;
                                
                                $opt_html = sprintf(
                                    '<option value="%s" %s>%s</option>',
                                    esc_attr( $sub_item->subject_name ),
                                    selected( $filter_subject, $sub_item->subject_name, false ),
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

            // When Exam is changed -> Load only classes assigned to that Exam.
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

            // Helper function to trigger reloading of section-aware subjects with separated groups.
            function reloadSubjectsForClassAndSection() {
                var selectedClass  = $('#ifs_educore_marks_class_select').val();
                var selectedSection = $('#ifs_educore_marks_section_select').val();
                var selectedExamId = $('#ifs_educore_marks_exam_select').val();
                var $subjectSelect = $('#ifs_educore_marks_subject_select');

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
                            var pendingHtml = '';

                            $.each(response.data, function(i, sub) {
                                var codeStr = sub.subject_code ? ' (' + sub.subject_code + ')' : '';
                                var isCompleted = (sub.total_students > 0 && sub.entered_count >= sub.total_students);
                                var progressBadge = ' [' + sub.entered_count + '/' + sub.total_students + ' Entered]';
                                var optHtml = '<option value="' + sub.subject_name + '">' + sub.subject_name + codeStr + progressBadge + '</option>';

                                if (isCompleted) {
                                    completedHtml += optHtml;
                                } else {
                                    pendingHtml += optHtml;
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

            // When Class is changed -> Load corresponding sections & subjects.
            $('#ifs_educore_marks_class_select').on('change', function() {
                var selectedClass  = $(this).val();
                var $secSelect     = $('#ifs_educore_marks_section_select');
                var $subjectSelect = $('#ifs_educore_marks_subject_select');

                $secSelect.html('<option value=""><?php echo esc_js( __( '-- Loading Sections... --', 'ifsedu-school-management' ) ); ?></option>');
                $subjectSelect.html('<option value=""><?php echo esc_js( __( '-- Choose Subject --', 'ifsedu-school-management' ) ); ?></option>');

                if (!selectedClass) {
                    $secSelect.html('<option value=""><?php echo esc_js( __( '-- All Sections --', 'ifsedu-school-management' ) ); ?></option>');
                    return;
                }

                // Load Sections.
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

            // When Section is changed -> Reload subjects specific to this section.
            $('#ifs_educore_marks_section_select').on('change', function() {
                reloadSubjectsForClassAndSection();
            });
        });
        </script>

        <!-- Marks Entry Matrix Table with Dynamic Subject Breakdown -->
        <?php if ( $filter_exam > 0 && ! empty( $filter_class ) && ! empty( $filter_subject ) ) : 
            $tot_limit = $active_subject_obj ? floatval( $active_subject_obj->total_marks ) : 100.00;
            $pass_lim  = $active_subject_obj ? floatval( $active_subject_obj->pass_marks ) : 33.00;
            $cq_lim    = $active_subject_obj ? floatval( $active_subject_obj->cq_marks ) : 70.00;
            $cq_p_lim  = $active_subject_obj ? floatval( $active_subject_obj->cq_pass ) : 23.00;
            $mcq_lim   = $active_subject_obj ? floatval( $active_subject_obj->mcq_marks ) : 30.00;
            $mcq_p_lim = $active_subject_obj ? floatval( $active_subject_obj->mcq_pass ) : 10.00;
            $pr_lim    = $active_subject_obj ? floatval( $active_subject_obj->practical_marks ) : 0.00;
            $pr_p_lim  = $active_subject_obj ? floatval( $active_subject_obj->practical_pass ) : 0.00;
        ?>
            <div class="ifs-educore-bento-card" style="background:#ffffff; border:1px solid #e2e8f0; border-radius:14px; padding:24px; box-shadow:0 4px 15px rgba(0,0,0,0.02);">
                <form method="POST" id="educoreMarksMatrixForm" action="<?php echo esc_url( add_query_arg( array( 'exam_id' => $filter_exam, 'class_name' => $filter_class, 'section_name' => $filter_section, 'subject_name' => $filter_subject, 'sub' => 'marks' ), $base_url ) ); ?>">
                    <?php wp_nonce_field( 'save_marks_action', 'ifs_educore_marks_nonce' ); ?>
                    <input type="hidden" name="exam_id" value="<?php echo esc_attr( $filter_exam ); ?>">
                    <input type="hidden" name="class_name" value="<?php echo esc_attr( $filter_class ); ?>">
                    <input type="hidden" name="section_name" value="<?php echo esc_attr( $filter_section ); ?>">
                    <input type="hidden" name="subject_name" value="<?php echo esc_attr( $filter_subject ); ?>">
                    <input type="hidden" name="is_custom_breakdown" id="is_custom_breakdown" value="<?php echo $has_custom_breakdown ? '1' : '0'; ?>">

                    <!-- Standard Limits Hidden Configuration -->
                    <input type="hidden" name="total_marks_limit" id="total_marks_limit" value="<?php echo esc_attr( $tot_limit ); ?>">
                    <input type="hidden" name="pass_marks_limit" id="pass_marks_limit" value="<?php echo esc_attr( $pass_lim ); ?>">
                    <input type="hidden" name="cq_marks_limit" id="cq_marks_limit" value="<?php echo esc_attr( $cq_lim ); ?>">
                    <input type="hidden" name="cq_pass_limit" id="cq_pass_limit" value="<?php echo esc_attr( $cq_p_lim ); ?>">
                    <input type="hidden" name="mcq_marks_limit" id="mcq_marks_limit" value="<?php echo esc_attr( $mcq_lim ); ?>">
                    <input type="hidden" name="mcq_pass_limit" id="mcq_pass_limit" value="<?php echo esc_attr( $mcq_p_lim ); ?>">
                    <input type="hidden" name="pr_marks_limit" id="pr_marks_limit" value="<?php echo esc_attr( $pr_lim ); ?>">
                    <input type="hidden" name="pr_pass_limit" id="pr_pass_limit" value="<?php echo esc_attr( $pr_p_lim ); ?>">

                    <?php if ( $has_custom_breakdown ) : ?>
                        <?php foreach ( $custom_breakdown_components as $comp ) : 
                            $c_name = sanitize_text_field( $comp['name'] );
                            $c_tot  = floatval( $comp['total'] );
                            $c_pas  = floatval( $comp['pass'] );
                        ?>
                            <input type="hidden" name="custom_comp_total[<?php echo esc_attr( $c_name ); ?>]" class="custom-comp-tot-limit" data-comp="<?php echo esc_attr( $c_name ); ?>" value="<?php echo esc_attr( $c_tot ); ?>">
                            <input type="hidden" name="custom_comp_pass[<?php echo esc_attr( $c_name ); ?>]" class="custom-comp-pass-limit" data-comp="<?php echo esc_attr( $c_name ); ?>" value="<?php echo esc_attr( $c_pas ); ?>">
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:18px; padding-bottom:14px; border-bottom:1px solid #e2e8f0;">
                        <div>
                            <strong style="font-size:16px; color:#0f172a;"><?php echo esc_html( $filter_subject ); ?></strong>
                            <span style="font-size:12px; color:#64748b; margin-left:8px;">(Total: <?php echo esc_html( $tot_limit ); ?> | Pass: <?php echo esc_html( $pass_lim ); ?>)</span>
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
                                                <?php echo esc_html( $comp['name'] ); ?><br>
                                                <span class="lbl-comp-summary" data-comp="<?php echo esc_attr( $comp['name'] ); ?>" style="font-size:10px; color:#64748b; font-weight:600;">
                                                    Max: <?php echo floatval( $comp['total'] ); ?> | &ge; <?php echo floatval( $comp['pass'] ); ?>
                                                </span>
                                            </th>
                                        <?php endforeach; ?>
                                    <?php else : ?>
                                        <!-- MCQ Column -->
                                        <th style="padding:10px; width: 14%;">
                                            <?php esc_html_e( 'MCQ', 'ifsedu-school-management' ); ?><br>
                                            <span class="lbl-mcq-summary" style="font-size:10px; color:#64748b; font-weight:600;">Max: <?php echo esc_html( $mcq_lim ); ?> | &ge; <?php echo esc_html( $mcq_p_lim ); ?></span>
                                        </th>

                                        <!-- CQ Column -->
                                        <th style="padding:10px; width: 14%;">
                                            <?php esc_html_e( 'CQ Theory', 'ifsedu-school-management' ); ?><br>
                                            <span class="lbl-cq-summary" style="font-size:10px; color:#64748b; font-weight:600;">Max: <?php echo esc_html( $cq_lim ); ?> | &ge; <?php echo esc_html( $cq_p_lim ); ?></span>
                                        </th>

                                        <th style="padding:10px; width: 14%;" class="th-pr-col" style="<?php echo ( $pr_lim <= 0 ) ? 'display:none;' : ''; ?>">
                                            <?php esc_html_e( 'Practical', 'ifsedu-school-management' ); ?><br>
                                            <span class="lbl-pr-summary" style="font-size:10px; color:#64748b; font-weight:600;">Max: <?php echo esc_html( $pr_lim ); ?> | &ge; <?php echo esc_html( $pr_p_lim ); ?></span>
                                        </th>
                                    <?php endif; ?>

                                    <th style="padding:10px; width: 10%;"><?php esc_html_e( 'Total', 'ifsedu-school-management' ); ?></th>
                                    <th style="padding:10px; width: 8%;"><?php esc_html_e( 'Grade', 'ifsedu-school-management' ); ?></th>
                                    <th style="padding:10px; width: 8%;"><?php esc_html_e( 'GPA', 'ifsedu-school-management' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ( ! empty( $students_list ) ) : foreach ( $students_list as $s ) : 
                                    $student_internal_id = absint( $s->id );
                                    $curr_res = isset( $saved_marks[ $student_internal_id ] ) ? $saved_marks[ $student_internal_id ] : null;
                                    $curr_tot = $curr_res ? number_format( floatval( $curr_res->obtained_marks ), 2, '.', '' ) : '0.00';
                                    $curr_grd = $curr_res ? esc_html( (string) $curr_res->grade ) : '—';
                                    $curr_gpa = $curr_res ? number_format( floatval( $curr_res->gpa ), 2 ) : '0.00';
                                    $is_fail  = ( 'F' === $curr_grd );

                                    // Parse component marks if present.
                                    $student_comp_marks = array();
                                    if ( $curr_res && ! empty( $curr_res->component_marks ) ) {
                                        $decoded_cm = json_decode( $curr_res->component_marks, true );
                                        if ( is_array( $decoded_cm ) ) {
                                            $student_comp_marks = $decoded_cm;
                                        }
                                    }
                                ?>
                                    <tr data-student-id="<?php echo esc_attr( $student_internal_id ); ?>" style="border-bottom:1px solid #f1f5f9; text-align:center;">
                                        <td style="padding:10px;"><strong>#<?php echo esc_html( $s->roll_no ); ?></strong></td>
                                        <td style="padding:10px;"><code><?php echo esc_html( strtoupper( (string) $s->student_id ) ); ?></code></td>
                                        <td style="padding:10px; text-align: left; font-weight: 700; color: #0f172a;"><?php echo esc_html( $s->full_name ); ?></td>
                                        
                                        <?php if ( $has_custom_breakdown ) : ?>
                                            <!-- Dynamic Custom Breakdown Inputs -->
                                            <?php foreach ( $custom_breakdown_components as $comp ) : 
                                                $comp_name = $comp['name'];
                                                $val_c = isset( $student_comp_marks[ $comp_name ] ) ? floatval( $student_comp_marks[ $comp_name ] ) : '';
                                            ?>
                                                <td style="padding:10px;">
                                                    <input type="number" step="0.5" min="0" max="<?php echo floatval( $comp['total'] ); ?>" 
                                                           name="custom_comp_val[<?php echo esc_attr( $student_internal_id ); ?>][<?php echo esc_attr( $comp_name ); ?>]" 
                                                           class="ifs-educore-mark-cell-input inp-custom-comp" 
                                                           data-comp="<?php echo esc_attr( $comp_name ); ?>" 
                                                           data-max="<?php echo floatval( $comp['total'] ); ?>" 
                                                           value="<?php echo esc_attr( $val_c ); ?>" placeholder="0" style="width:75px; height:34px; text-align:center; border:1px solid #cbd5e1; border-radius:6px;">
                                                </td>
                                            <?php endforeach; ?>
                                        <?php else : 
                                            $curr_cq  = $curr_res ? floatval( $curr_res->cq_marks ) : '';
                                            $curr_mcq = $curr_res ? floatval( $curr_res->mcq_marks ) : '';
                                            $curr_pr  = $curr_res ? floatval( $curr_res->practical_marks ) : '';
                                        ?>
                                            <!-- MCQ Input -->
                                            <td style="padding:10px;">
                                                <input type="number" step="0.5" min="0" max="<?php echo esc_attr( $mcq_lim ); ?>" 
                                                       name="mcq_marks[<?php echo esc_attr( $student_internal_id ); ?>]" 
                                                       class="ifs-educore-mark-cell-input inp-mcq" 
                                                       data-max="<?php echo esc_attr( $mcq_lim ); ?>" 
                                                       value="<?php echo esc_attr( $curr_mcq ); ?>" placeholder="0" style="width:75px; height:34px; text-align:center; border:1px solid #cbd5e1; border-radius:6px;">
                                            </td>

                                            <!-- CQ Input -->
                                            <td style="padding:10px;">
                                                <input type="number" step="0.5" min="0" max="<?php echo esc_attr( $cq_lim ); ?>" 
                                                       name="cq_marks[<?php echo esc_attr( $student_internal_id ); ?>]" 
                                                       class="ifs-educore-mark-cell-input inp-cq" 
                                                       data-max="<?php echo esc_attr( $cq_lim ); ?>" 
                                                       value="<?php echo esc_attr( $curr_cq ); ?>" placeholder="0" style="width:75px; height:34px; text-align:center; border:1px solid #cbd5e1; border-radius:6px;">
                                            </td>

                                            <!-- Practical Input -->
                                            <td style="padding:10px;" class="td-pr-col">
                                                <input type="number" step="0.5" min="0" max="<?php echo esc_attr( $pr_lim ); ?>" 
                                                       name="practical_marks[<?php echo esc_attr( $student_internal_id ); ?>]" 
                                                       class="ifs-educore-mark-cell-input inp-pr" 
                                                       data-max="<?php echo esc_attr( $pr_lim ); ?>" 
                                                       value="<?php echo esc_attr( $curr_pr ); ?>" placeholder="0" style="width:75px; height:34px; text-align:center; border:1px solid #cbd5e1; border-radius:6px;">
                                            </td>
                                        <?php endif; ?>

                                        <!-- Calculated Total -->
                                        <td style="padding:10px;"><strong class="cell-total-obt" style="font-size: 14px; color: #0f172a;"><?php echo esc_html( $curr_tot ); ?></strong></td>

                                        <!-- Evaluated Grade -->
                                        <td style="padding:10px;">
                                            <span class="cell-grade" style="display:inline-block; padding:3px 8px; border-radius:4px; font-weight:700; font-size:12px; background:<?php echo $is_fail ? '#fee2e2' : '#ecfdf5'; ?>; color:<?php echo $is_fail ? '#dc2626' : '#047857'; ?>;">
                                                <?php echo esc_html( $curr_grd ); ?>
                                            </span>
                                        </td>

                                        <!-- Evaluated GPA -->
                                        <td style="padding:10px;"><strong class="cell-gpa" style="color: <?php echo $is_fail ? '#dc2626' : '#00523c'; ?>;"><?php echo esc_html( $curr_gpa ); ?></strong></td>
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

            <!-- Client-Side Real-time Grading, Clamping, Session Storage & Custom Component Evaluator -->
            <script type="text/javascript">
            document.addEventListener('DOMContentLoaded', function() {
                var isCustomBreakdown = document.getElementById('is_custom_breakdown').value === '1';
                var totalLimitInput   = document.getElementById('total_marks_limit');
                var passLimitInput    = document.getElementById('pass_marks_limit');

                var examId      = '<?php echo esc_js( $filter_exam ); ?>';
                var className   = '<?php echo esc_js( $filter_class ); ?>';
                var sectionName = '<?php echo esc_js( $filter_section ); ?>';
                var subjectName = '<?php echo esc_js( $filter_subject ); ?>';
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

                    if (isCustomBreakdown) {
                        row.querySelectorAll('.inp-custom-comp').forEach(function(inp) {
                            var comp = inp.getAttribute('data-comp');
                            var compRule = limits.custom[comp] || { max: 100, pass: 0 };
                            
                            inp.setAttribute('data-max', compRule.max);
                            enforceBounds(inp, compRule.max);

                            var val = parseFloat(inp.value) || 0;
                            obtained += val;

                            if (compRule.pass > 0 && val < compRule.pass) {
                                failed = true;
                            }
                        });
                        obtained = Math.min(obtained, limits.total);
                    } else {
                        var inpCq  = row.querySelector('.inp-cq');
                        var inpMcq = row.querySelector('.inp-mcq');
                        var inpPr  = row.querySelector('.inp-pr');

                        if (inpCq) {
                            inpCq.setAttribute('data-max', limits.cqMax);
                            enforceBounds(inpCq, limits.cqMax);
                        }
                        if (inpMcq) {
                            inpMcq.setAttribute('data-max', limits.mcqMax);
                            enforceBounds(inpMcq, limits.mcqMax);
                        }
                        if (inpPr) {
                            inpPr.setAttribute('data-max', limits.prMax);
                            enforceBounds(inpPr, limits.prMax);
                        }

                        var valCq  = parseFloat(inpCq ? inpCq.value : 0) || 0;
                        var valMcq = parseFloat(inpMcq ? inpMcq.value : 0) || 0;
                        var valPr  = parseFloat(inpPr ? inpPr.value : 0) || 0;

                        obtained = Math.min(valCq + valMcq + valPr, limits.total);

                        if (limits.cqPass > 0 && valCq < limits.cqPass) failed = true;
                        if (limits.mcqPass > 0 && valMcq < limits.mcqPass) failed = true;
                        if (limits.prPass > 0 && valPr < limits.prPass) failed = true;
                    }

                    if (obtained < limits.pass) failed = true;

                    row.querySelector('.cell-total-obt').textContent = obtained.toFixed(2);

                    var gradeBadge = row.querySelector('.cell-grade');
                    var gpaCell    = row.querySelector('.cell-gpa');

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
                                if (input) {
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

                    table.addEventListener('input', function(e) {
                        if (e.target.classList.contains('ifs-educore-mark-cell-input')) {
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
<?php
/**
 * Examination Hall Attendance Roster & Hall Invigilator Log View
 * File: inc/attendance/attendance-exam.php
 * Text Domain: ifsedu-school-management
 * Teacher Scope: Restricts Class/Section/Subject dropdowns to `sms_teacher_subjects` for logged-in Teachers.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

function educore_exam_attendance_view() {
    global $wpdb;
    $current_user = wp_get_current_user();

    $table_students         = $wpdb->prefix . 'sms_students';
    $table_exams            = $wpdb->prefix . 'sms_exams';
    $table_units            = $wpdb->prefix . 'sms_academic_units';
    $table_subjects         = $wpdb->prefix . 'sms_subjects';
    $table_exam_att         = $wpdb->prefix . 'sms_exam_attendance';
    $table_staff            = $wpdb->prefix . 'sms_staff';
    $table_teacher_subjects = $wpdb->prefix . 'sms_teacher_subjects';

    // 1. Procedural Role & Capability Validation
    $is_admin = current_user_can( 'manage_options' ) || in_array( 'administrator', (array) $current_user->roles, true );
    
    $is_staff = false;
    if ( function_exists( 'educore_has_access' ) ) {
        $is_staff = educore_has_access( array( 'teacher', 'staff', 'operator', 'instructor', 'editor', 'author', 'contributor', 'subscriber' ) );
    }

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    if ( ! $is_staff && ! $is_admin ) {
        $staff_exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM `{$table_staff}` WHERE wp_user_id = %d OR email = %s LIMIT 1",
                $current_user->ID,
                $current_user->user_email
            )
        );
        if ( $staff_exists ) {
            $is_staff = true;
        }
    }
    // phpcs:enable

    if ( ! $is_admin && ! $is_staff ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to view examination hall attendance.', 'ifsedu-school-management' ) );
    }

    $saved_notice = '';

    // Capture GET Request Parameters
    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $filter_exam    = isset( $_GET['exam_id'] ) ? absint( wp_unslash( $_GET['exam_id'] ) ) : 0;
    $filter_class   = isset( $_GET['class_name'] ) ? sanitize_text_field( wp_unslash( $_GET['class_name'] ) ) : '';
    $filter_section = isset( $_GET['section_name'] ) ? sanitize_text_field( wp_unslash( $_GET['section_name'] ) ) : '';
    $filter_subject = isset( $_GET['subject_name'] ) ? sanitize_text_field( wp_unslash( $_GET['subject_name'] ) ) : '';
    $filter_date    = isset( $_GET['attendance_date'] ) ? sanitize_text_field( wp_unslash( $_GET['attendance_date'] ) ) : current_time( 'Y-m-d' );
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    // --------------------------------------------------------------------------
    // RESOLVE TEACHER SUBJECT & CLASS ALLOCATIONS
    // --------------------------------------------------------------------------
    $teacher_assigned_classes = array();
    $teacher_assigned_subs    = array();
    $teacher_id               = 0;

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
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
                    "SELECT DISTINCT u.class_name, u.section_name, u.sort_order, s.subject_name 
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
    // phpcs:enable

    // --------------------------------------------------------------------------
    // 1. SAVE EXAM ATTENDANCE FORM SUBMISSION
    // --------------------------------------------------------------------------
    $req_method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
    if ( 'POST' === $req_method && isset( $_POST['educore_save_exam_attendance'] ) ) {
        if ( isset( $_POST['ifs_educore_exam_att_nonce_field'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ifs_educore_exam_att_nonce_field'] ) ), 'save_exam_attendance_action' ) ) {
            
            // Boundary check: Non-admin teacher can only submit for their assigned class and subject if allocations exist
            if ( ! $is_admin && ! empty( $teacher_assigned_classes ) && ( ! in_array( $filter_class, $teacher_assigned_classes, true ) || ! in_array( $filter_subject, (array) ( $teacher_assigned_subs[ $filter_class ] ?? array() ), true ) ) ) {
                wp_die( esc_html__( 'Security Check: You are not authorized to submit examination attendance for this allocation.', 'ifsedu-school-management' ) );
            }

            $exam_id         = isset( $_POST['exam_id'] ) ? absint( wp_unslash( $_POST['exam_id'] ) ) : 0;
            $class_name      = isset( $_POST['class_name'] ) ? sanitize_text_field( wp_unslash( $_POST['class_name'] ) ) : '';
            $section_name    = isset( $_POST['section_name'] ) ? sanitize_text_field( wp_unslash( $_POST['section_name'] ) ) : '';
            $subject_name    = isset( $_POST['subject_name'] ) ? sanitize_text_field( wp_unslash( $_POST['subject_name'] ) ) : '';
            $attendance_date = isset( $_POST['attendance_date'] ) ? sanitize_text_field( wp_unslash( $_POST['attendance_date'] ) ) : current_time( 'Y-m-d' );
            
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $raw_att         = ( isset( $_POST['att_status'] ) && is_array( $_POST['att_status'] ) ) ? wp_unslash( $_POST['att_status'] ) : array();
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $raw_remarks     = ( isset( $_POST['invigilator_remarks'] ) && is_array( $_POST['invigilator_remarks'] ) ) ? wp_unslash( $_POST['invigilator_remarks'] ) : array();

            $allowed_statuses = array( 'Present', 'Absent', 'Late' );
            $saved_count      = 0;

            if ( $exam_id > 0 && ! empty( $class_name ) && ! empty( $subject_name ) && ! empty( $raw_att ) ) {
                foreach ( $raw_att as $student_id => $status_val ) {
                    $st_id   = absint( $student_id );
                    $status  = sanitize_text_field( (string) $status_val );
                    if ( ! in_array( $status, $allowed_statuses, true ) ) {
                        $status = 'Present';
                    }

                    $remarks = isset( $raw_remarks[ $student_id ] ) ? sanitize_text_field( (string) $raw_remarks[ $student_id ] ) : '';

                    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                    $existing_id = (int) $wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT id FROM `{$table_exam_att}` WHERE exam_id = %d AND student_id = %d AND subject_name = %s AND attendance_date = %s",
                            $exam_id,
                            $st_id,
                            $subject_name,
                            $attendance_date
                        )
                    );

                    $data = array(
                        'exam_id'             => $exam_id,
                        'student_id'          => $st_id,
                        'class_name'          => $class_name,
                        'section_name'        => $section_name,
                        'subject_name'        => $subject_name,
                        'attendance_date'     => $attendance_date,
                        'status'              => $status,
                        'invigilator_remarks' => $remarks,
                        'recorded_by'         => get_current_user_id(),
                    );

                    $formats = array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d' );

                    if ( $existing_id > 0 ) {
                        $wpdb->update( $table_exam_att, $data, array( 'id' => $existing_id ), $formats, array( '%d' ) );
                    } else {
                        $wpdb->insert( $table_exam_att, $data, $formats );
                    }
                    // phpcs:enable
                    $saved_count++;
                }

                $saved_notice = sprintf(
                    /* translators: %d: Number of candidates */
                    esc_html__( 'Successfully recorded examination hall attendance for %d candidates.', 'ifsedu-school-management' ),
                    $saved_count
                );
            }
        }
    }

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $exams = $wpdb->get_results( "SELECT id, exam_name FROM `{$table_exams}` ORDER BY id DESC" );

    // Fetch Unique Classes and build section maps prioritizing sort_order
    $raw_units = $wpdb->get_results( 
        "SELECT id, class_name, section_name, dept_name, sort_order 
         FROM `{$table_units}` 
         WHERE class_name != '' 
         ORDER BY sort_order ASC, CAST(class_name AS UNSIGNED) ASC, class_name ASC, section_name ASC" 
    );
    // phpcs:enable

    $academic_classes   = array();
    $class_order_map    = array();
    $class_section_map  = array();
    $class_subject_map  = array();

    if ( ! empty( $raw_units ) ) {
        foreach ( $raw_units as $unit ) {
            $c_name = trim( (string) $unit->class_name );
            $s_ord  = isset( $unit->sort_order ) ? (int) $unit->sort_order : 0;

            if ( ! isset( $class_order_map[ $c_name ] ) || $s_ord < $class_order_map[ $c_name ] ) {
                $class_order_map[ $c_name ] = $s_ord;
            }

            // If teacher mode and has assigned classes, filter dropdowns accordingly
            if ( ! $is_admin && ! empty( $teacher_assigned_classes ) && ! in_array( $c_name, $teacher_assigned_classes, true ) ) {
                continue;
            }

            if ( ! isset( $class_section_map[ $c_name ] ) ) {
                $class_section_map[ $c_name ] = array();
                $class_subject_map[ $c_name ] = array();
                $academic_classes[] = $c_name;
            }
            if ( ! empty( $unit->section_name ) ) {
                $class_section_map[ $c_name ][] = trim( (string) $unit->section_name );
            }
            if ( ! empty( $unit->dept_name ) ) {
                $class_section_map[ $c_name ][] = trim( (string) $unit->dept_name );
            }

            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            if ( ! $is_admin && $teacher_id > 0 ) {
                $subs = $wpdb->get_results(
                    $wpdb->prepare( 
                        "SELECT DISTINCT s.subject_name, s.subject_code, s.subject_order, s.total_marks, s.pass_marks, s.cq_marks, s.cq_pass, s.mcq_marks, s.mcq_pass, s.practical_marks, s.practical_pass 
                         FROM `{$table_teacher_subjects}` ts
                         INNER JOIN `{$table_subjects}` s ON ts.subject_id = s.id
                         WHERE ts.teacher_id = %d AND ts.class_id = %d
                         ORDER BY s.subject_order ASC, s.subject_name ASC", 
                        $teacher_id,
                        intval( $unit->id )
                    )
                );
            } else {
                $subs = $wpdb->get_results(
                    $wpdb->prepare( 
                        "SELECT subject_name, subject_code, subject_order, total_marks, pass_marks, cq_marks, cq_pass, mcq_marks, mcq_pass, practical_marks, practical_pass 
                         FROM `{$table_subjects}` 
                         WHERE class_id = %d
                         ORDER BY subject_order ASC, subject_name ASC", 
                        intval( $unit->id )
                    )
                );
            }
            // phpcs:enable

            if ( ! empty( $subs ) ) {
                foreach ( $subs as $sub ) {
                    $class_subject_map[ $c_name ][] = array(
                        'name'            => $sub->subject_name,
                        'code'            => $sub->subject_code ? ' (' . $sub->subject_code . ')' : '',
                        'total_marks'     => $sub->total_marks,
                        'pass_marks'      => $sub->pass_marks,
                        'cq_marks'        => $sub->cq_marks,
                        'cq_pass'         => $sub->cq_pass,
                        'mcq_marks'       => $sub->mcq_marks,
                        'mcq_pass'        => $sub->mcq_pass,
                        'practical_marks' => $sub->practical_marks,
                        'practical_pass'  => $sub->practical_pass,
                    );
                }
            }
        }

        foreach ( $class_section_map as $c_name => $secs ) {
            $class_section_map[ $c_name ] = array_values( array_unique( array_filter( $secs ) ) );
            usort( $class_section_map[ $c_name ], 'strnatcasecmp' );
        }

        foreach ( $class_subject_map as $c_name => $subs ) {
            $unique_subs = array();
            foreach ( $subs as $s ) {
                $unique_subs[ $s['name'] ] = $s;
            }
            $class_subject_map[ $c_name ] = array_values( $unique_subs );
        }

        if ( $is_admin || empty( $teacher_assigned_classes ) ) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $all_global_subs = $wpdb->get_results( "SELECT subject_name, subject_code, subject_order, total_marks, pass_marks, cq_marks, cq_pass, mcq_marks, mcq_pass, practical_marks, practical_pass FROM `{$table_subjects}` ORDER BY subject_order ASC, subject_name ASC" );
            // phpcs:enable
            
            foreach ( $academic_classes as $c_name ) {
                if ( empty( $class_subject_map[ $c_name ] ) && ! empty( $all_global_subs ) ) {
                    foreach ( $all_global_subs as $gs ) {
                        $class_subject_map[ $c_name ][] = array(
                            'name'            => $gs->subject_name,
                            'code'            => $gs->subject_code ? ' (' . $gs->subject_code . ')' : '',
                            'total_marks'     => $gs->total_marks,
                            'pass_marks'      => $gs->pass_marks,
                            'cq_marks'        => $gs->cq_marks,
                            'cq_pass'         => $gs->cq_pass,
                            'mcq_marks'       => $gs->mcq_marks,
                            'mcq_pass'        => $gs->mcq_pass,
                            'practical_marks' => $gs->practical_marks,
                            'practical_pass'  => $gs->practical_pass,
                        );
                    }
                }
            }
        }

        // Apply sort_order then Natural Numeric Sorting to Classes
        $academic_classes = array_values( array_unique( $academic_classes ) );
        usort( $academic_classes, function( $a, $b ) use ( $class_order_map ) {
            $order_a = isset( $class_order_map[ $a ] ) ? $class_order_map[ $a ] : 0;
            $order_b = isset( $class_order_map[ $b ] ) ? $class_order_map[ $b ] : 0;
            if ( $order_a !== $order_b ) {
                return $order_a - $order_b;
            }
            return strnatcasecmp( $a, $b );
        } );
    }

    $available_sections = array();
    $available_subjects = array();
    if ( ! empty( $filter_class ) ) {
        if ( isset( $class_section_map[ $filter_class ] ) ) {
            $available_sections = $class_section_map[ $filter_class ];
        }
        if ( isset( $class_subject_map[ $filter_class ] ) ) {
            $available_subjects = $class_subject_map[ $filter_class ];
        }
    }

    $students_list = array();
    $saved_logs    = array();

    if ( $filter_exam > 0 && ! empty( $filter_class ) && ! empty( $filter_subject ) ) {
        $st_sql = "SELECT id, full_name, student_id, roll_no, class_name, section_name, shift, photo_url FROM `{$table_students}` WHERE status = 'Active' AND class_name = %s";
        $st_params = array( $filter_class );

        if ( ! empty( $filter_section ) ) {
            $st_sql    .= ' AND section_name = %s';
            $st_params[] = $filter_section;
        }

        $st_sql .= ' ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC';
        
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $students_list = $wpdb->get_results( $wpdb->prepare( $st_sql, ...$st_params ) );

        $existing_logs = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT student_id, status, invigilator_remarks 
                 FROM `{$table_exam_att}` 
                 WHERE exam_id = %d AND class_name = %s AND subject_name = %s AND attendance_date = %s",
                $filter_exam,
                $filter_class,
                $filter_subject,
                $filter_date
            ),
            OBJECT_K
        );
        // phpcs:enable

        if ( ! empty( $existing_logs ) ) {
            $saved_logs = $existing_logs;
        }
    }

    $admin_page_url = admin_url( 'admin.php' );
    ?>

    <div class="ifs-educore-exam-att-root">

        <?php if ( ! empty( $saved_notice ) ) : ?>
            <div class="ifs-educore-success-banner">
                <span class="dashicons dashicons-yes-alt"></span>
                <?php echo esc_html( $saved_notice ); ?>
            </div>
        <?php endif; ?>

        <!-- Exam Attendance Filter Console -->
        <div class="ifs-educore-bento-card no-print">

            <form method="GET" action="<?php echo esc_url( $admin_page_url ); ?>">
                <input type="hidden" name="page" value="school_management_system">
                <input type="hidden" name="tab" value="attendance">
                <input type="hidden" name="sub" value="exam">

                <div class="ifs-educore-filter-grid-5">
                    <!-- 1. Select Exam -->
                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-form-label"><?php esc_html_e( '1. Select Exam', 'ifsedu-school-management' ); ?> <span style="color:#ef4444;">*</span></label>
                        <select name="exam_id" id="ifs_educore_exam_att_exam_select" class="ifs-educore-select-field" required>
                            <option value=""><?php esc_html_e( '-- Choose Exam --', 'ifsedu-school-management' ); ?></option>
                            <?php foreach ( $exams as $ex ) : ?>
                                <option value="<?php echo intval( $ex->id ); ?>" <?php selected( $filter_exam, $ex->id ); ?>>
                                    <?php echo esc_html( $ex->exam_name ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- 2. Class Selection -->
                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-form-label"><?php esc_html_e( '2. Class Name', 'ifsedu-school-management' ); ?> <span style="color:#ef4444;">*</span></label>
                        <select name="class_name" id="ifs_educore_exam_att_class_select" class="ifs-educore-select-field" required>
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
                        <label class="ifs-educore-form-label"><?php esc_html_e( '3. Section (Optional)', 'ifsedu-school-management' ); ?></label>
                        <select name="section_name" id="ifs_educore_exam_att_section_select" class="ifs-educore-select-field">
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
                        <label class="ifs-educore-form-label"><?php esc_html_e( '4. Exam Subject', 'ifsedu-school-management' ); ?> <span style="color:#ef4444;">*</span></label>
                        <select name="subject_name" id="ifs_educore_exam_att_subject_select" class="ifs-educore-select-field" required>
                            <option value=""><?php esc_html_e( '-- Choose Subject --', 'ifsedu-school-management' ); ?></option>
                            <?php if ( ! empty( $available_subjects ) ) : ?>
                                <?php foreach ( $available_subjects as $sub_item ) : ?>
                                    <option value="<?php echo esc_attr( $sub_item['name'] ); ?>" <?php selected( $filter_subject, $sub_item['name'] ); ?>>
                                        <?php echo esc_html( $sub_item['name'] . $sub_item['code'] ); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <!-- 5. Exam Date -->
                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-form-label"><?php esc_html_e( '5. Exam Date', 'ifsedu-school-management' ); ?></label>
                        <input type="date" name="attendance_date" class="ifs-educore-input-field" value="<?php echo esc_attr( $filter_date ); ?>">
                    </div>

                    <!-- Submit Trigger -->
                    <div>
                        <button type="submit" class="ifs-educore-btn-submit-trigger" style="width: 100%;">
                            <span class="dashicons dashicons-search"></span>
                            <?php esc_html_e( 'Load Roster', 'ifsedu-school-management' ); ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Local Instant Cascade Script for Class-wise Subjects -->
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            var classSectionMap = <?php echo wp_json_encode( $class_section_map ); ?>;
            var classSubjectMap = <?php echo wp_json_encode( $class_subject_map ); ?>;
            var currentSelectedSection = "<?php echo esc_js( $filter_section ); ?>";
            var currentSelectedSubject = "<?php echo esc_js( $filter_subject ); ?>";

            function updateDropdowns(className) {
                var $secSelect     = $('#ifs_educore_exam_att_section_select');
                var $subjectSelect = $('#ifs_educore_exam_att_subject_select');

                $secSelect.empty().append('<option value=""><?php echo esc_js( __( "-- All Sections --", "ifsedu-school-management" ) ); ?></option>');
                $subjectSelect.empty().append('<option value=""><?php echo esc_js( __( "-- Choose Subject --", "ifsedu-school-management" ) ); ?></option>');

                if (!className) return;

                // Populate Sections
                if (classSectionMap[className] && classSectionMap[className].length > 0) {
                    $.each(classSectionMap[className], function(i, sec) {
                        var isSelected = (sec === currentSelectedSection) ? 'selected' : '';
                        $secSelect.append('<option value="' + sec + '" ' + isSelected + '>' + sec + '</option>');
                    });
                }

                // Populate Class-Related Subjects
                if (classSubjectMap[className] && classSubjectMap[className].length > 0) {
                    $.each(classSubjectMap[className], function(i, sub) {
                        var isSelected = (sub.name === currentSelectedSubject) ? 'selected' : '';
                        $subjectSelect.append('<option value="' + sub.name + '" ' + isSelected + '>' + sub.name + sub.code + '</option>');
                    });
                } else {
                    $subjectSelect.html('<option value=""><?php echo esc_js( __( "No Subjects Configured for this Class", "ifsedu-school-management" ) ); ?></option>');
                }
            }

            $('#ifs_educore_exam_att_class_select').on('change', function() {
                var selectedClass = $(this).val();
                currentSelectedSection = '';
                currentSelectedSubject = '';
                updateDropdowns(selectedClass);
            });
        });
        </script>

        <!-- Exam Hall Attendance Roster Form -->
        <?php if ( $filter_exam > 0 && ! empty( $filter_class ) && ! empty( $filter_subject ) ) : ?>
            <div class="ifs-educore-bento-card" style="padding: 0; overflow: hidden;">
                <form method="POST" action="">
                    <?php wp_nonce_field( 'save_exam_attendance_action', 'ifs_educore_exam_att_nonce_field' ); ?>
                    <input type="hidden" name="exam_id" value="<?php echo esc_attr( $filter_exam ); ?>">
                    <input type="hidden" name="class_name" value="<?php echo esc_attr( $filter_class ); ?>">
                    <input type="hidden" name="section_name" value="<?php echo esc_attr( $filter_section ); ?>">
                    <input type="hidden" name="subject_name" value="<?php echo esc_attr( $filter_subject ); ?>">
                    <input type="hidden" name="attendance_date" value="<?php echo esc_attr( $filter_date ); ?>">

                    <!-- Meta Summary Bar -->
                    <div class="ifs-educore-roster-meta-bar">
                        <div>
                            <strong style="font-size:16px; color:#00523c;"><?php echo esc_html( $filter_subject ); ?></strong>
                            <span style="font-size:13px; color:#475569; margin-left:8px;">
                                &mdash; Class <?php echo esc_html( $filter_class ); ?> 
                                <?php echo ! empty( $filter_section ) ? '(' . esc_html( $filter_section ) . ')' : ''; ?> 
                                | Date: <?php 
                                    $ex_timestamp = strtotime( $filter_date );
                                    echo esc_html( $ex_timestamp ? date_i18n( 'd M, Y', $ex_timestamp ) : '—' ); 
                                ?>
                            </span>
                        </div>
                        <div class="ifs-educore-counter-cluster">
                            <span class="ifs-educore-badge-pill ifs-educore-badge-total" id="examAttTotalCount"><?php echo esc_html__( 'Total:', 'ifsedu-school-management' ) . ' ' . count( $students_list ); ?></span>
                            <span class="ifs-educore-badge-pill ifs-educore-badge-present" id="examAttPresentCount"><?php echo esc_html__( 'Present:', 'ifsedu-school-management' ); ?> 0</span>
                            <span class="ifs-educore-badge-pill ifs-educore-badge-absent" id="examAttAbsentCount"><?php echo esc_html__( 'Absent:', 'ifsedu-school-management' ); ?> 0</span>
                            <span class="ifs-educore-badge-pill ifs-educore-badge-late" id="examAttLateCount"><?php echo esc_html__( 'Late/Expelled:', 'ifsedu-school-management' ); ?> 0</span>
                        </div>
                    </div>

                    <!-- Bulk Automation Buttons -->
                    <div class="ifs-educore-bulk-automation-row no-print">
                        <div style="font-size: 13px; font-weight: 700; color: #475569;">
                            <span class="dashicons dashicons-admin-tools" style="vertical-align:middle;"></span>
                            <?php esc_html_e( 'Quick Automation Tools:', 'ifsedu-school-management' ); ?>
                        </div>
                        <div style="display:flex; gap:8px;">
                            <button type="button" class="ifs-educore-bulk-btn exam-bulk-btn" data-target-status="Present">
                                <span class="dashicons dashicons-yes" style="font-size:14px; width:14px; height:14px;"></span> <?php esc_html_e( 'Mark All Present', 'ifsedu-school-management' ); ?>
                            </button>
                            <button type="button" class="ifs-educore-bulk-btn exam-bulk-btn" data-target-status="Absent">
                                <span class="dashicons dashicons-no" style="font-size:14px; width:14px; height:14px;"></span> <?php esc_html_e( 'Mark All Absent', 'ifsedu-school-management' ); ?>
                            </button>
                        </div>
                    </div>

                    <!-- Roster Table -->
                    <div class="ifs-educore-table-responsive">
                        <table class="ifs-educore-attendance-matrix-table">
                            <thead>
                                <tr>
                                    <th style="width: 8%;"><?php esc_html_e( 'Roll', 'ifsedu-school-management' ); ?></th>
                                    <th style="width: 14%;"><?php esc_html_e( 'Student ID', 'ifsedu-school-management' ); ?></th>
                                    <th style="width: 25%;"><?php esc_html_e( 'Candidate Name', 'ifsedu-school-management' ); ?></th>
                                    <th style="width: 30%; text-align: center;"><?php esc_html_e( 'Exam Hall Status', 'ifsedu-school-management' ); ?></th>
                                    <th style="width: 23%;"><?php esc_html_e( 'Invigilator Notes / Expel Remarks', 'ifsedu-school-management' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ( ! empty( $students_list ) ) : foreach ( $students_list as $s ) : 
                                    $student_internal_id = absint( $s->id );
                                    $saved_status  = isset( $saved_logs[ $student_internal_id ] ) ? $saved_logs[ $student_internal_id ]->status : 'Present';
                                    $saved_remarks = isset( $saved_logs[ $student_internal_id ] ) ? $saved_logs[ $student_internal_id ]->invigilator_remarks : '';
                                    $first_letter  = function_exists( 'mb_substr' ) ? mb_substr( $s->full_name, 0, 1, 'utf-8' ) : substr( $s->full_name, 0, 1 );
                                ?>
                                    <tr>
                                        <td><strong>#<?php echo esc_html( $s->roll_no ); ?></strong></td>
                                        <td><span class="ifs-educore-exam-card-badge"><?php echo esc_html( strtoupper( (string) $s->student_id ) ); ?></span></td>
                                        <td>
                                            <div class="ifs-educore-avatar-cell">
                                                <?php if ( ! empty( $s->photo_url ) ) : ?>
                                                    <img src="<?php echo esc_url( $s->photo_url ); ?>" class="ifs-educore-avatar-mini" alt="<?php echo esc_attr( $s->full_name ); ?>">
                                                <?php else : ?>
                                                    <div class="ifs-educore-avatar-fallback-mini"><?php echo esc_html( strtoupper( $first_letter ) ); ?></div>
                                                <?php endif; ?>
                                                <div>
                                                    <strong style="color:#0f172a;"><?php echo esc_html( $s->full_name ); ?></strong>
                                                </div>
                                            </div>
                                        </td>
                                        <td style="text-align: center;">
                                            <div class="ifs-educore-checkbox-group">
                                                <input type="radio" class="ifs-educore-checkbox-item exam-att-radio" name="att_status[<?php echo esc_attr( $student_internal_id ); ?>]" id="att_present_<?php echo esc_attr( $student_internal_id ); ?>" value="Present" <?php checked( $saved_status, 'Present' ); ?>>
                                                <label class="ifs-educore-checkbox-label" for="att_present_<?php echo esc_attr( $student_internal_id ); ?>">
                                                    <span class="dashicons dashicons-yes" style="font-size:13px; width:13px; height:13px;"></span>
                                                    <?php esc_html_e( 'Present', 'ifsedu-school-management' ); ?>
                                                </label>

                                                <input type="radio" class="ifs-educore-checkbox-item exam-att-radio" name="att_status[<?php echo esc_attr( $student_internal_id ); ?>]" id="att_absent_<?php echo esc_attr( $student_internal_id ); ?>" value="Absent" <?php checked( $saved_status, 'Absent' ); ?>>
                                                <label class="ifs-educore-checkbox-label" for="att_absent_<?php echo esc_attr( $student_internal_id ); ?>">
                                                    <span class="dashicons dashicons-no" style="font-size:13px; width:13px; height:13px;"></span>
                                                    <?php esc_html_e( 'Absent', 'ifsedu-school-management' ); ?>
                                                </label>

                                                <input type="radio" class="ifs-educore-checkbox-item exam-att-radio" name="att_status[<?php echo esc_attr( $student_internal_id ); ?>]" id="att_late_<?php echo esc_attr( $student_internal_id ); ?>" value="Late" <?php checked( $saved_status, 'Late' ); ?>>
                                                <label class="ifs-educore-checkbox-label" for="att_late_<?php echo esc_attr( $student_internal_id ); ?>">
                                                    <span class="dashicons dashicons-warning" style="font-size:13px; width:13px; height:13px;"></span>
                                                    <?php esc_html_e( 'Late / Expelled', 'ifsedu-school-management' ); ?>
                                                </label>
                                            </div>
                                        </td>
                                        <td>
                                            <input type="text" name="invigilator_remarks[<?php echo esc_attr( $student_internal_id ); ?>]" class="ifs-educore-remarks-input" placeholder="<?php esc_attr_e( 'e.g. Expelled, 15m Late, Seat No. 4', 'ifsedu-school-management' ); ?>" value="<?php echo esc_attr( $saved_remarks ); ?>">
                                        </td>
                                    </tr>
                                <?php endforeach; else : ?>
                                    <tr>
                                        <td colspan="5" style="text-align:center; padding:30px; color:#94a3b8;">
                                            <?php esc_html_e( 'No active students found in the selected class/section.', 'ifsedu-school-management' ); ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ( ! empty( $students_list ) ) : ?>
                        <div style="text-align: right; margin: 20px; padding: 0;">
                            <button type="submit" name="educore_save_exam_attendance" class="ifs-educore-btn-submit-trigger" style="height: 44px; padding: 0 32px; font-size: 15px; width: auto;">
                                <span class="dashicons dashicons-saved"></span>
                                <?php esc_html_e( 'Save Exam Hall Attendance', 'ifsedu-school-management' ); ?>
                            </button>
                        </div>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Client-Side Summary Counters & Quick Automation Script -->
            <script type="text/javascript">
            document.addEventListener('DOMContentLoaded', function() {
                function updateCounters() {
                    var present = document.querySelectorAll('.exam-att-radio[value="Present"]:checked').length;
                    var absent  = document.querySelectorAll('.exam-att-radio[value="Absent"]:checked').length;
                    var late    = document.querySelectorAll('.exam-att-radio[value="Late"]:checked').length;

                    var elPres = document.getElementById('examAttPresentCount');
                    var elAbs  = document.getElementById('examAttAbsentCount');
                    var elLate = document.getElementById('examAttLateCount');

                    if (elPres) elPres.textContent = '<?php echo esc_js( __( "Present:", "ifsedu-school-management" ) ); ?> ' + present;
                    if (elAbs)  elAbs.textContent  = '<?php echo esc_js( __( "Absent:", "ifsedu-school-management" ) ); ?> ' + absent;
                    if (elLate) elLate.textContent = '<?php echo esc_js( __( "Late/Expelled:", "ifsedu-school-management" ) ); ?> ' + late;
                }

                document.querySelectorAll('.exam-att-radio').forEach(function(radio) {
                    radio.addEventListener('change', updateCounters);
                });

                document.querySelectorAll('.exam-bulk-btn').forEach(function(btn) {
                    btn.addEventListener('click', function() {
                        var targetStatus = this.getAttribute('data-target-status');
                        document.querySelectorAll('.exam-att-radio[value="' + targetStatus + '"]').forEach(function(radio) {
                            radio.checked = true;
                        });
                        updateCounters();
                    });
                });

                updateCounters();
            });
            </script>
        <?php endif; ?>

    </div>
    <?php
}
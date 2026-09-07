<?php
/**
 * Enterprise Multi-Step Student Admission & Profile Engine
 * File: student-add-edit.php
 * Target Tables: sms_students, sms_academic_units, sms_staff
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Direct access buffer safety row
}

function educore_student_add_edit_view() {
    global $wpdb;

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient administrative permissions to manage student records.', 'ifsedu-school-management' ) );
    }

    $table_students = $wpdb->prefix . 'sms_students';
    $table_units    = $wpdb->prefix . 'sms_academic_units';
    $table_staff    = $wpdb->prefix . 'sms_staff';

    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $is_edit    = isset( $_GET['sub'] ) && 'edit' === sanitize_key( wp_unslash( $_GET['sub'] ) );
    $student_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    $student = null;
    if ( $is_edit && $student_id > 0 ) {
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $student = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table_students}` WHERE id = %d LIMIT 1", $student_id ) );
        // phpcs:enable
    }

    // 1. Calculate Auto-Sequential Student ID
    $configured_prefix = get_option( 'educore_prefix_student', 'STU-' );
    if ( empty( $configured_prefix ) ) {
        $configured_prefix = 'STU-';
    }

    $generated_student_id = $configured_prefix . '0001';
    if ( ! $is_edit ) {
        $escaped_like = $wpdb->esc_like( $configured_prefix ) . '%';
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $latest_id_row = $wpdb->get_var( $wpdb->prepare( "SELECT student_id FROM `{$table_students}` WHERE student_id LIKE %s ORDER BY id DESC LIMIT 1", $escaped_like ) );
        // phpcs:enable
        
        $max_num = 0;
        if ( ! empty( $latest_id_row ) ) {
            $pattern = '/^' . preg_quote( $configured_prefix, '/' ) . '(\d+)$/i';
            if ( preg_match( $pattern, trim( $latest_id_row ), $matches ) ) {
                $max_num = intval( $matches[1] );
            }
        }
        $next_num = $max_num + 1;
        $generated_student_id = $configured_prefix . str_pad( (string) $next_num, 4, '0', STR_PAD_LEFT );
    }

    // Fetch Academic Units ordered primarily by sort_order
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $raw_units = $wpdb->get_results( "SELECT class_name, section_name, dept_name, sort_order FROM `{$table_units}` WHERE class_name != '' ORDER BY sort_order ASC, CAST(class_name AS UNSIGNED) ASC, class_name ASC, section_name ASC" );
    // phpcs:enable
    $class_section_map = array();
    $class_order_map   = array();
    $academic_classes  = array();

    if ( ! empty( $raw_units ) ) {
        foreach ( $raw_units as $unit ) {
            $c_name = trim( $unit->class_name );
            $s_ord  = isset( $unit->sort_order ) ? (int) $unit->sort_order : 0;

            if ( ! isset( $class_order_map[ $c_name ] ) || $s_ord < $class_order_map[ $c_name ] ) {
                $class_order_map[ $c_name ] = $s_ord;
            }

            if ( ! isset( $class_section_map[ $c_name ] ) ) {
                $class_section_map[ $c_name ] = array();
                $academic_classes[] = $c_name;
            }
            if ( ! empty( $unit->section_name ) ) {
                $class_section_map[ $c_name ][] = trim( $unit->section_name );
            }
            if ( ! empty( $unit->dept_name ) ) {
                $class_section_map[ $c_name ][] = trim( $unit->dept_name );
            }
        }

        foreach ( $class_section_map as $c_name => $secs ) {
            $class_section_map[ $c_name ] = array_values( array_unique( array_filter( $secs ) ) );
            usort( $class_section_map[ $c_name ], 'strnatcasecmp' );
        }

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

    // Fetch active Staff Members for Waiver Reference
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $staff_members = $wpdb->get_results( "SELECT id, full_name, designation, staff_type FROM `{$table_staff}` WHERE status = 'Active' ORDER BY full_name ASC" );
    // phpcs:enable

    // --------------------------------------------------------------------------
    // FORM SUBMISSION PROCESSING
    // --------------------------------------------------------------------------
    if ( isset( $_POST['educore_student_action'] ) && 'save_student' === $_POST['educore_student_action'] ) {
        check_admin_referer( 'save_student_action', 'educore_student_nonce' );

        $photo_url = $student ? $student->photo_url : '';

        // Secure Portrait Image Upload Handler
        if ( ! empty( $_FILES['student_photo']['name'] ) && ! empty( $_FILES['student_photo']['tmp_name'] ) ) {
            $allowed_mimes = array(
                'jpg|jpeg|jpe' => 'image/jpeg',
                'png'          => 'image/png',
                'webp'         => 'image/webp',
            );

            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $file_check = wp_check_filetype_and_ext( $_FILES['student_photo']['tmp_name'], sanitize_file_name( $_FILES['student_photo']['name'] ), $allowed_mimes );

            if ( ! $file_check['type'] || ! in_array( $file_check['type'], $allowed_mimes, true ) ) {
                wp_die( esc_html__( 'Security Error: Only valid JPG, PNG, and WEBP image files are allowed.', 'ifsedu-school-management' ) );
            }

            require_once ABSPATH . 'wp-admin/includes/file.php';
            $uploaded_file = wp_handle_upload( $_FILES['student_photo'], array( 'test_form' => false, 'mimes' => $allowed_mimes ) );
            if ( ! isset( $uploaded_file['error'] ) && isset( $uploaded_file['url'] ) ) {
                $photo_url = esc_url_raw( $uploaded_file['url'] );
            }
        }

        $adm_date = ! empty( $_POST['admission_date'] ) ? sanitize_text_field( wp_unslash( $_POST['admission_date'] ) ) : current_time( 'Y-m-d' );
        $fee_date = ! empty( $_POST['fee_start_date'] ) ? sanitize_text_field( wp_unslash( $_POST['fee_start_date'] ) ) : null;
        $dob_date = ! empty( $_POST['dob'] ) ? sanitize_text_field( wp_unslash( $_POST['dob'] ) ) : '2008-01-01';

        $data = array(
            'student_id'         => isset( $_POST['student_id'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['student_id'] ) ) ) : '',
            'full_name'          => isset( $_POST['full_name'] ) ? sanitize_text_field( wp_unslash( $_POST['full_name'] ) ) : '',
            'class_name'         => isset( $_POST['class_name'] ) ? sanitize_text_field( wp_unslash( $_POST['class_name'] ) ) : '',
            'section_name'       => isset( $_POST['section_name'] ) ? sanitize_text_field( wp_unslash( $_POST['section_name'] ) ) : '',
            'shift'              => isset( $_POST['shift'] ) ? sanitize_text_field( wp_unslash( $_POST['shift'] ) ) : 'No Shift',
            'roll_no'            => isset( $_POST['roll_no'] ) ? intval( $_POST['roll_no'] ) : 0,
            'admission_date'     => $adm_date,
            'fee_start_date'     => $fee_date,
            'birth_reg_no'       => isset( $_POST['birth_reg_no'] ) ? sanitize_text_field( wp_unslash( $_POST['birth_reg_no'] ) ) : '',
            'dob'                => $dob_date,
            'birth_place'        => isset( $_POST['birth_place'] ) ? sanitize_text_field( wp_unslash( $_POST['birth_place'] ) ) : '',
            'gender'             => isset( $_POST['gender'] ) ? sanitize_text_field( wp_unslash( $_POST['gender'] ) ) : 'Male',
            'blood_group'        => isset( $_POST['blood_group'] ) ? sanitize_text_field( wp_unslash( $_POST['blood_group'] ) ) : '',
            'religion'           => isset( $_POST['religion'] ) ? sanitize_text_field( wp_unslash( $_POST['religion'] ) ) : 'Islam',
            'nationality'        => isset( $_POST['nationality'] ) ? sanitize_text_field( wp_unslash( $_POST['nationality'] ) ) : 'Bangladeshi',
            'student_email'      => isset( $_POST['student_email'] ) ? sanitize_email( wp_unslash( $_POST['student_email'] ) ) : '',
            'student_phone'      => isset( $_POST['student_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['student_phone'] ) ) : '',
            'quota'              => isset( $_POST['quota'] ) ? sanitize_text_field( wp_unslash( $_POST['quota'] ) ) : 'General',

            // Financial Waiver
            'waiver_staff_id'    => isset( $_POST['waiver_staff_id'] ) ? absint( $_POST['waiver_staff_id'] ) : 0,
            'waiver_percentage'  => isset( $_POST['waiver_percentage'] ) ? min( 100, max( 0, floatval( $_POST['waiver_percentage'] ) ) ) : 0.00,

            'father_name'        => isset( $_POST['father_name'] ) ? sanitize_text_field( wp_unslash( $_POST['father_name'] ) ) : '',
            'father_nid'         => isset( $_POST['father_nid'] ) ? sanitize_text_field( wp_unslash( $_POST['father_nid'] ) ) : '',
            'father_phone'       => isset( $_POST['father_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['father_phone'] ) ) : '',
            'father_profession'  => isset( $_POST['father_profession'] ) ? sanitize_text_field( wp_unslash( $_POST['father_profession'] ) ) : '',

            'mother_name'        => isset( $_POST['mother_name'] ) ? sanitize_text_field( wp_unslash( $_POST['mother_name'] ) ) : '',
            'mother_nid'         => isset( $_POST['mother_nid'] ) ? sanitize_text_field( wp_unslash( $_POST['mother_nid'] ) ) : '',
            'mother_phone'       => isset( $_POST['mother_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['mother_phone'] ) ) : '',
            'mother_profession'  => isset( $_POST['mother_profession'] ) ? sanitize_text_field( wp_unslash( $_POST['mother_profession'] ) ) : '',

            'guardian_name'      => isset( $_POST['guardian_name'] ) ? sanitize_text_field( wp_unslash( $_POST['guardian_name'] ) ) : '',
            'guardian_phone'     => isset( $_POST['guardian_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['guardian_phone'] ) ) : '',
            'guardian_relation'  => isset( $_POST['guardian_relation'] ) ? sanitize_text_field( wp_unslash( $_POST['guardian_relation'] ) ) : '',
            'guardian_nid'       => isset( $_POST['guardian_nid'] ) ? sanitize_text_field( wp_unslash( $_POST['guardian_nid'] ) ) : '',
            'guardian_income'    => isset( $_POST['guardian_income'] ) ? sanitize_text_field( wp_unslash( $_POST['guardian_income'] ) ) : '',

            'prev_school_name'   => isset( $_POST['prev_school_name'] ) ? sanitize_text_field( wp_unslash( $_POST['prev_school_name'] ) ) : '',
            'prev_eiin'          => isset( $_POST['prev_eiin'] ) ? sanitize_text_field( wp_unslash( $_POST['prev_eiin'] ) ) : '',
            'prev_class'         => isset( $_POST['prev_class'] ) ? sanitize_text_field( wp_unslash( $_POST['prev_class'] ) ) : '',
            'prev_gpa'           => isset( $_POST['prev_gpa'] ) ? sanitize_text_field( wp_unslash( $_POST['prev_gpa'] ) ) : '',

            'address'            => isset( $_POST['address'] ) ? sanitize_textarea_field( wp_unslash( $_POST['address'] ) ) : '',
            'permanent_address'  => isset( $_POST['permanent_address'] ) ? sanitize_textarea_field( wp_unslash( $_POST['permanent_address'] ) ) : '',
            'residential_status' => isset( $_POST['residential_status'] ) ? sanitize_text_field( wp_unslash( $_POST['residential_status'] ) ) : 'Non-Residential',
            'co_curricular'      => ( isset( $_POST['co_curricular'] ) && is_array( $_POST['co_curricular'] ) ) ? implode( ', ', array_map( 'sanitize_text_field', wp_unslash( $_POST['co_curricular'] ) ) ) : '',

            'photo_url'          => $photo_url,
            'status'             => isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'Active',
        );

        $formats = array(
            '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s',
            '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%f',
            '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s',
            '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s',
            '%s', '%s', '%s'
        );

        if ( $is_edit ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $result = $wpdb->update( $table_students, $data, array( 'id' => $student_id ), $formats, array( '%d' ) );
            $redirect_url = admin_url( 'admin.php?page=school_management_system&tab=students&sub=list&msg=updated' );
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $result = $wpdb->insert( $table_students, $data, $formats );
            $redirect_url = admin_url( 'admin.php?page=school_management_system&tab=students&sub=list&msg=success' );
        }

        if ( false === $result ) {
            /* translators: %s: Database error message */
            wp_die( esc_html( sprintf( __( 'Database Transaction Error: %s', 'ifsedu-school-management' ), $wpdb->last_error ) ) );
        }

        // Safe redirection handling
        if ( ! headers_sent() ) {
            wp_safe_redirect( $redirect_url );
            exit;
        } else {
            echo '<script type="text/javascript">window.location.href = ' . wp_json_encode( esc_url_raw( $redirect_url ) ) . ';</script>';
            echo '<noscript><meta http-equiv="refresh" content="0;url=' . esc_url( $redirect_url ) . '" /></noscript>';
            exit;
        }
    }

    $back_url       = admin_url( 'admin.php?page=school_management_system&tab=students&sub=list' );
    $today_date     = current_time( 'Y-m-d' );
    $default_avatar = 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="150" height="150" viewBox="0 0 150 150"><rect fill="%23e2e8f0" width="150" height="150"/><text fill="%2364748b" font-family="sans-serif" font-size="14" dy="5" font-weight="bold" x="50%" y="50%" text-anchor="middle">No Photo</text></svg>';
    ?>

    <div class="ifs-educore-admission-root">
        <!-- Top Header Navigation -->
        <div class="ifs-educore-header-bar">
            <a href="<?php echo esc_url( $back_url ); ?>" class="ifs-educore-back-btn">
                <span class="dashicons dashicons-arrow-left-alt"></span>
                <?php esc_html_e( 'Back to Student Directory', 'ifsedu-school-management' ); ?>
            </a>
        </div>

        <div class="ifs-educore-form-card">
            <h3 class="ifs-educore-form-title">
                <span class="dashicons dashicons-id" style="color:#00523c;"></span>
                <?php echo $is_edit ? esc_html__( 'Edit Student Profile Record', 'ifsedu-school-management' ) : esc_html__( 'Admit Comprehensive New Student', 'ifsedu-school-management' ); ?>
            </h3>

            <!-- Dynamic 5-Step Progress Bar -->
            <div class="ifs-educore-stepper-bar" id="ifs_educore_student_stepper">
                <button type="button" class="ifs-educore-step-node active" data-step="1">
                    <div class="ifs-educore-step-circle">1</div>
                    <div class="ifs-educore-step-info">
                        <span class="ifs-educore-step-title"><?php esc_html_e( 'Basic & Academic', 'ifsedu-school-management' ); ?></span>
                        <span class="ifs-educore-step-sub"><?php esc_html_e( 'Identity & Class', 'ifsedu-school-management' ); ?></span>
                    </div>
                </button>

                <button type="button" class="ifs-educore-step-node" data-step="2">
                    <div class="ifs-educore-step-circle">2</div>
                    <div class="ifs-educore-step-info">
                        <span class="ifs-educore-step-title"><?php esc_html_e( 'Parents Info', 'ifsedu-school-management' ); ?></span>
                        <span class="ifs-educore-step-sub"><?php esc_html_e( 'Father & Mother', 'ifsedu-school-management' ); ?></span>
                    </div>
                </button>

                <button type="button" class="ifs-educore-step-node" data-step="3">
                    <div class="ifs-educore-step-circle">3</div>
                    <div class="ifs-educore-step-info">
                        <span class="ifs-educore-step-title"><?php esc_html_e( 'Guardian & History', 'ifsedu-school-management' ); ?></span>
                        <span class="ifs-educore-step-sub"><?php esc_html_e( 'Emergency & Previous', 'ifsedu-school-management' ); ?></span>
                    </div>
                </button>

                <button type="button" class="ifs-educore-step-node" data-step="4">
                    <div class="ifs-educore-step-circle">4</div>
                    <div class="ifs-educore-step-info">
                        <span class="ifs-educore-step-title"><?php esc_html_e( 'Financial Waiver', 'ifsedu-school-management' ); ?></span>
                        <span class="ifs-educore-step-sub"><?php esc_html_e( 'Staff Ref. & Discount %', 'ifsedu-school-management' ); ?></span>
                    </div>
                </button>

                <button type="button" class="ifs-educore-step-node" data-step="5">
                    <div class="ifs-educore-step-circle">5</div>
                    <div class="ifs-educore-step-info">
                        <span class="ifs-educore-step-title"><?php esc_html_e( 'Address & Photo', 'ifsedu-school-management' ); ?></span>
                        <span class="ifs-educore-step-sub"><?php esc_html_e( 'Logistics & Submit', 'ifsedu-school-management' ); ?></span>
                    </div>
                </button>
            </div>

            <!-- Form Workspace -->
            <form method="POST" action="" enctype="multipart/form-data" id="ifs_educore_student_form" novalidate>
                <?php wp_nonce_field( 'save_student_action', 'educore_student_nonce' ); ?>
                <input type="hidden" name="educore_student_action" value="save_student">

                <!-- STEP 1: Academic & Basic Personal Profile -->
                <div class="ifs-educore-step-panel active" id="ifs_educore_step_1">
                    <div class="ifs-educore-grid-3">
                        <div class="ifs-educore-field-group">
                            <label class="ifs-educore-field-label"><?php esc_html_e( 'Student Unique ID / UID', 'ifsedu-school-management' ); ?> <span style="color:#ef4444;">*</span></label>
                            <input type="text" name="student_id" id="ifs_educore_student_id_input" class="ifs-educore-input" style="font-weight:800; color:#00523c; text-transform:uppercase;" value="<?php echo $student ? esc_attr( $student->student_id ) : esc_attr( $generated_student_id ); ?>" required readonly>
                            <span class="ifs-educore-hint-text"><?php esc_html_e( 'Unique Student Identity (Auto-generated)', 'ifsedu-school-management' ); ?></span>
                        </div>

                        <div class="ifs-educore-field-group">
                            <label class="ifs-educore-field-label"><?php esc_html_e( 'Full Name (English)', 'ifsedu-school-management' ); ?> <span style="color:#ef4444;">*</span></label>
                            <input type="text" name="full_name" class="ifs-educore-input" value="<?php echo $student ? esc_attr( $student->full_name ) : ''; ?>" placeholder="<?php esc_attr_e( 'e.g. Tanvir Ahmed', 'ifsedu-school-management' ); ?>" required>
                            <span class="ifs-educore-hint-text"><?php esc_html_e( 'Full student name in English capital letters', 'ifsedu-school-management' ); ?></span>
                        </div>

                        <div class="ifs-educore-field-group">
                            <label class="ifs-educore-field-label"><?php esc_html_e( 'Class / Grade', 'ifsedu-school-management' ); ?> <span style="color:#ef4444;">*</span></label>
                            <select name="class_name" id="ifs_educore_class_select" class="ifs-educore-select" required>
                                <option value=""><?php esc_html_e( '-- Select Class --', 'ifsedu-school-management' ); ?></option>
                                <?php foreach ( $academic_classes as $ac ) : ?>
                                    <option value="<?php echo esc_attr( $ac ); ?>" <?php selected( $student ? $student->class_name : '', $ac ); ?>><?php echo esc_html( $ac ); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <span class="ifs-educore-hint-text"><?php esc_html_e( 'Select admission target class', 'ifsedu-school-management' ); ?></span>
                        </div>
                    </div>

                    <div class="ifs-educore-grid-4">
                        <div class="ifs-educore-field-group">
                            <label class="ifs-educore-field-label"><?php esc_html_e( 'Section / Group', 'ifsedu-school-management' ); ?></label>
                            <select name="section_name" id="ifs_educore_section_select" class="ifs-educore-select">
                                <option value=""><?php esc_html_e( '-- Select Section --', 'ifsedu-school-management' ); ?></option>
                            </select>
                            <span class="ifs-educore-hint-text"><?php esc_html_e( 'Select designated class section or department', 'ifsedu-school-management' ); ?></span>
                        </div>

                        <div class="ifs-educore-field-group">
                            <label class="ifs-educore-field-label"><?php esc_html_e( 'Roll Number', 'ifsedu-school-management' ); ?> <span style="color:#ef4444;">*</span></label>
                            <input type="number" name="roll_no" class="ifs-educore-input" value="<?php echo $student ? esc_attr( $student->roll_no ) : ''; ?>" placeholder="<?php esc_attr_e( 'e.g. 101', 'ifsedu-school-management' ); ?>" required>
                            <span class="ifs-educore-hint-text"><?php esc_html_e( 'Official student roll number', 'ifsedu-school-management' ); ?></span>
                        </div>

                        <div class="ifs-educore-field-group">
                            <label class="ifs-educore-field-label"><?php esc_html_e( 'Admission Date', 'ifsedu-school-management' ); ?> <span style="color:#ef4444;">*</span></label>
                            <input type="date" name="admission_date" id="ifs_educore_admission_date_input" class="ifs-educore-input" value="<?php echo $student ? esc_attr( $student->admission_date ) : esc_attr( $today_date ); ?>" required>
                            <span class="ifs-educore-hint-text"><?php esc_html_e( 'Date of institutional admission', 'ifsedu-school-management' ); ?></span>
                        </div>

                        <div class="ifs-educore-field-group">
                            <label class="ifs-educore-field-label"><?php esc_html_e( 'Academic Shift', 'ifsedu-school-management' ); ?></label>
                            <select name="shift" class="ifs-educore-select">
                                <option value="No Shift" <?php selected( $student ? $student->shift : '', 'No Shift' ); ?>><?php esc_html_e( 'No Shift', 'ifsedu-school-management' ); ?></option>
                                <option value="Morning Shift" <?php selected( $student ? $student->shift : '', 'Morning Shift' ); ?>><?php esc_html_e( 'Morning Shift', 'ifsedu-school-management' ); ?></option>
                                <option value="Day Shift" <?php selected( $student ? $student->shift : '', 'Day Shift' ); ?>><?php esc_html_e( 'Day Shift', 'ifsedu-school-management' ); ?></option>
                            </select>
                            <span class="ifs-educore-hint-text"><?php esc_html_e( 'Select shift where applicable', 'ifsedu-school-management' ); ?></span>
                        </div>
                    </div>

                    <div class="ifs-educore-grid-4">
                        <div class="ifs-educore-field-group">
                            <label class="ifs-educore-field-label"><?php esc_html_e( 'Start Date for Monthly Fees', 'ifsedu-school-management' ); ?></label>
                            <input type="date" name="fee_start_date" id="ifs_educore_fee_start_date_input" class="ifs-educore-input" value="<?php echo ( $student && ! empty( $student->fee_start_date ) ) ? esc_attr( $student->fee_start_date ) : esc_attr( current_time( 'Y-m-01' ) ); ?>">
                            <span class="ifs-educore-hint-text"><?php esc_html_e( 'Billing start date for tuition', 'ifsedu-school-management' ); ?></span>
                        </div>

                        <div class="ifs-educore-field-group">
                            <label class="ifs-educore-field-label"><?php esc_html_e( 'Birth Reg. Number (17 Digits)', 'ifsedu-school-management' ); ?></label>
                            <input type="text" name="birth_reg_no" maxlength="17" class="ifs-educore-input" value="<?php echo $student ? esc_attr( $student->birth_reg_no ) : ''; ?>" placeholder="<?php esc_attr_e( '17-digit Birth Certificate Number', 'ifsedu-school-management' ); ?>">
                            <span class="ifs-educore-hint-text"><?php esc_html_e( 'Online Verified Birth Registration Number', 'ifsedu-school-management' ); ?></span>
                        </div>

                        <div class="ifs-educore-field-group">
                            <label class="ifs-educore-field-label"><?php esc_html_e( 'Date of Birth', 'ifsedu-school-management' ); ?> <span style="color:#ef4444;">*</span></label>
                            <input type="date" name="dob" id="ifs_educore_dob_input" class="ifs-educore-input" value="<?php echo $student ? esc_attr( $student->dob ) : '2008-01-01'; ?>" required>
                            <span class="ifs-educore-hint-text"><?php esc_html_e( 'Date of birth', 'ifsedu-school-management' ); ?></span>
                        </div>

                        <div class="ifs-educore-field-group">
                            <label class="ifs-educore-field-label"><?php esc_html_e( 'Birth District', 'ifsedu-school-management' ); ?></label>
                            <input type="text" name="birth_place" class="ifs-educore-input" value="<?php echo $student ? esc_attr( $student->birth_place ) : ''; ?>" placeholder="<?php esc_attr_e( 'e.g. Sylhet', 'ifsedu-school-management' ); ?>">
                            <span class="ifs-educore-hint-text"><?php esc_html_e( 'District of birth', 'ifsedu-school-management' ); ?></span>
                        </div>
                    </div>

                    <div class="ifs-educore-grid-4">
                        <div class="ifs-educore-field-group">
                            <label class="ifs-educore-field-label"><?php esc_html_e( 'Gender', 'ifsedu-school-management' ); ?></label>
                            <select name="gender" class="ifs-educore-select">
                                <option value="Male" <?php selected( $student ? $student->gender : '', 'Male' ); ?>><?php esc_html_e( 'Male', 'ifsedu-school-management' ); ?></option>
                                <option value="Female" <?php selected( $student ? $student->gender : '', 'Female' ); ?>><?php esc_html_e( 'Female', 'ifsedu-school-management' ); ?></option>
                                <option value="Other" <?php selected( $student ? $student->gender : '', 'Other' ); ?>><?php esc_html_e( 'Other', 'ifsedu-school-management' ); ?></option>
                            </select>
                        </div>

                        <div class="ifs-educore-field-group">
                            <label class="ifs-educore-field-label"><?php esc_html_e( 'Blood Group', 'ifsedu-school-management' ); ?></label>
                            <select name="blood_group" class="ifs-educore-select">
                                <option value=""><?php esc_html_e( '-- Select Group --', 'ifsedu-school-management' ); ?></option>
                                <?php foreach ( array('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-') as $bg ) : ?>
                                    <option value="<?php echo esc_attr( $bg ); ?>" <?php selected( $student ? $student->blood_group : '', $bg ); ?>><?php echo esc_html( $bg ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="ifs-educore-field-group">
                            <label class="ifs-educore-field-label"><?php esc_html_e( 'Religion', 'ifsedu-school-management' ); ?></label>
                            <select name="religion" class="ifs-educore-select">
                                <option value="Islam" <?php selected( $student ? $student->religion : '', 'Islam' ); ?>><?php esc_html_e( 'Islam', 'ifsedu-school-management' ); ?></option>
                                <option value="Hinduism" <?php selected( $student ? $student->religion : '', 'Hinduism' ); ?>><?php esc_html_e( 'Hinduism', 'ifsedu-school-management' ); ?></option>
                                <option value="Christianity" <?php selected( $student ? $student->religion : '', 'Christianity' ); ?>><?php esc_html_e( 'Christianity', 'ifsedu-school-management' ); ?></option>
                                <option value="Buddhism" <?php selected( $student ? $student->religion : '', 'Buddhism' ); ?>><?php esc_html_e( 'Buddhism', 'ifsedu-school-management' ); ?></option>
                            </select>
                        </div>

                        <div class="ifs-educore-field-group">
                            <label class="ifs-educore-field-label"><?php esc_html_e( 'Nationality', 'ifsedu-school-management' ); ?></label>
                            <input type="text" name="nationality" class="ifs-educore-input" value="<?php echo $student ? esc_attr( $student->nationality ) : 'Bangladeshi'; ?>">
                        </div>
                    </div>

                    <div class="ifs-educore-grid-3">
                        <div class="ifs-educore-field-group">
                            <label class="ifs-educore-field-label"><?php esc_html_e( 'Quota Category', 'ifsedu-school-management' ); ?></label>
                            <select name="quota" class="ifs-educore-select">
                                <option value="General" <?php selected( $student ? $student->quota : '', 'General' ); ?>><?php esc_html_e( 'General', 'ifsedu-school-management' ); ?></option>
                                <option value="Freedom Fighter" <?php selected( $student ? $student->quota : '', 'Freedom Fighter' ); ?>><?php esc_html_e( 'Freedom Fighter', 'ifsedu-school-management' ); ?></option>
                                <option value="Tribal" <?php selected( $student ? $student->quota : '', 'Tribal' ); ?>><?php esc_html_e( 'Tribal', 'ifsedu-school-management' ); ?></option>
                                <option value="Physically Challenged" <?php selected( $student ? $student->quota : '', 'Physically Challenged' ); ?>><?php esc_html_e( 'Physically Challenged', 'ifsedu-school-management' ); ?></option>
                            </select>
                        </div>

                        <div class="ifs-educore-field-group">
                            <label class="ifs-educore-field-label"><?php esc_html_e( 'Student WhatsApp Number', 'ifsedu-school-management' ); ?></label>
                            <input type="text" name="student_phone" class="ifs-educore-input" value="<?php echo $student ? esc_attr( $student->student_phone ) : ''; ?>" placeholder="<?php esc_attr_e( '01700000000', 'ifsedu-school-management' ); ?>">
                        </div>

                        <div class="ifs-educore-field-group">
                            <label class="ifs-educore-field-label"><?php esc_html_e( 'Student Email Address', 'ifsedu-school-management' ); ?></label>
                            <input type="email" name="student_email" class="ifs-educore-input" value="<?php echo $student ? esc_attr( $student->student_email ) : ''; ?>" placeholder="<?php esc_attr_e( 'student@example.com', 'ifsedu-school-management' ); ?>">
                        </div>
                    </div>
                </div>

                <!-- STEP 2: Parents Information -->
                <div class="ifs-educore-step-panel" id="ifs_educore_step_2">
                    <div class="ifs-educore-grid-2">
                        <!-- Father's Card -->
                        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:20px;">
                            <h4 style="margin:0 0 16px 0; font-size:15px; font-weight:800; color:#0f172a; border-bottom:1px solid #e2e8f0; padding-bottom:8px;">
                                <?php esc_html_e( 'Father Information', 'ifsedu-school-management' ); ?>
                            </h4>

                            <div class="ifs-educore-field-group" style="margin-bottom:14px;">
                                <label class="ifs-educore-field-label"><?php esc_html_e( 'Father Name (English)', 'ifsedu-school-management' ); ?></label>
                                <input type="text" name="father_name" class="ifs-educore-input" value="<?php echo $student ? esc_attr( $student->father_name ) : ''; ?>" placeholder="<?php esc_attr_e( 'e.g. MD Rafiqul Islam', 'ifsedu-school-management' ); ?>">
                            </div>

                            <div class="ifs-educore-field-group" style="margin-bottom:14px;">
                                <label class="ifs-educore-field-label"><?php esc_html_e( 'Father NID', 'ifsedu-school-management' ); ?></label>
                                <input type="text" name="father_nid" class="ifs-educore-input" value="<?php echo $student ? esc_attr( $student->father_nid ) : ''; ?>" placeholder="<?php esc_attr_e( 'National ID Card Number', 'ifsedu-school-management' ); ?>">
                            </div>

                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                                <div class="ifs-educore-field-group">
                                    <label class="ifs-educore-field-label"><?php esc_html_e( 'Father Phone', 'ifsedu-school-management' ); ?></label>
                                    <input type="text" name="father_phone" class="ifs-educore-input" value="<?php echo $student ? esc_attr( $student->father_phone ) : ''; ?>" placeholder="<?php esc_attr_e( '01700000000', 'ifsedu-school-management' ); ?>">
                                </div>
                                <div class="ifs-educore-field-group">
                                    <label class="ifs-educore-field-label"><?php esc_html_e( 'Profession', 'ifsedu-school-management' ); ?></label>
                                    <input type="text" name="father_profession" class="ifs-educore-input" value="<?php echo $student ? esc_attr( $student->father_profession ) : ''; ?>" placeholder="<?php esc_attr_e( 'e.g. Businessman / Teacher', 'ifsedu-school-management' ); ?>">
                                </div>
                            </div>
                        </div>

                        <!-- Mother's Card -->
                        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:20px;">
                            <h4 style="margin:0 0 16px 0; font-size:15px; font-weight:800; color:#0f172a; border-bottom:1px solid #e2e8f0; padding-bottom:8px;">
                                <?php esc_html_e( 'Mother Information', 'ifsedu-school-management' ); ?>
                            </h4>

                            <div class="ifs-educore-field-group" style="margin-bottom:14px;">
                                <label class="ifs-educore-field-label"><?php esc_html_e( 'Mother Name (English)', 'ifsedu-school-management' ); ?></label>
                                <input type="text" name="mother_name" class="ifs-educore-input" value="<?php echo $student ? esc_attr( $student->mother_name ) : ''; ?>" placeholder="<?php esc_attr_e( 'e.g. Nasima Begum', 'ifsedu-school-management' ); ?>">
                            </div>

                            <div class="ifs-educore-field-group" style="margin-bottom:14px;">
                                <label class="ifs-educore-field-label"><?php esc_html_e( 'Mother NID', 'ifsedu-school-management' ); ?></label>
                                <input type="text" name="mother_nid" class="ifs-educore-input" value="<?php echo $student ? esc_attr( $student->mother_nid ) : ''; ?>" placeholder="<?php esc_attr_e( 'National ID Card Number', 'ifsedu-school-management' ); ?>">
                            </div>

                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px;">
                                <div class="ifs-educore-field-group">
                                    <label class="ifs-educore-field-label"><?php esc_html_e( 'Mother Phone', 'ifsedu-school-management' ); ?></label>
                                    <input type="text" name="mother_phone" class="ifs-educore-input" value="<?php echo $student ? esc_attr( $student->mother_phone ) : ''; ?>" placeholder="<?php esc_attr_e( '01700000000', 'ifsedu-school-management' ); ?>">
                                </div>
                                <div class="ifs-educore-field-group">
                                    <label class="ifs-educore-field-label"><?php esc_html_e( 'Profession', 'ifsedu-school-management' ); ?></label>
                                    <input type="text" name="mother_profession" class="ifs-educore-input" value="<?php echo $student ? esc_attr( $student->mother_profession ) : ''; ?>" placeholder="<?php esc_attr_e( 'e.g. Homemaker / Doctor', 'ifsedu-school-management' ); ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STEP 3: Guardian & Academic History -->
                <div class="ifs-educore-step-panel" id="ifs_educore_step_3">
                    <div class="ifs-educore-section-heading">
                        <span><?php esc_html_e( 'Legal Guardian Details (SMS Notifications Target)', 'ifsedu-school-management' ); ?></span>
                    </div>

                    <div class="ifs-educore-grid-4">
                        <div class="ifs-educore-field-group">
                            <label class="ifs-educore-field-label"><?php esc_html_e( 'Guardian Name', 'ifsedu-school-management' ); ?> <span style="color:#ef4444;">*</span></label>
                            <input type="text" name="guardian_name" class="ifs-educore-input" value="<?php echo $student ? esc_attr( $student->guardian_name ) : ''; ?>" required>
                        </div>

                        <div class="ifs-educore-field-group">
                            <label class="ifs-educore-field-label"><?php esc_html_e( 'Guardian Phone (SMS Alert Number)', 'ifsedu-school-management' ); ?> <span style="color:#ef4444;">*</span></label>
                            <input type="text" name="guardian_phone" class="ifs-educore-input" value="<?php echo $student ? esc_attr( $student->guardian_phone ) : ''; ?>" placeholder="<?php esc_attr_e( '01700000000', 'ifsedu-school-management' ); ?>" required>
                        </div>

                        <div class="ifs-educore-field-group">
                            <label class="ifs-educore-field-label"><?php esc_html_e( 'Relation with Guardian', 'ifsedu-school-management' ); ?></label>
                            <input type="text" name="guardian_relation" class="ifs-educore-input" value="<?php echo $student ? esc_attr( $student->guardian_relation ) : 'Father'; ?>">
                        </div>

                        <div class="ifs-educore-field-group">
                            <label class="ifs-educore-field-label"><?php esc_html_e( 'Guardian NID / Annual Income', 'ifsedu-school-management' ); ?></label>
                            <input type="text" name="guardian_nid" class="ifs-educore-input" value="<?php echo $student ? esc_attr( $student->guardian_nid ) : ''; ?>" placeholder="<?php esc_attr_e( 'NID or Annual Income', 'ifsedu-school-management' ); ?>">
                        </div>
                    </div>

                    <div class="ifs-educore-section-heading">
                        <span><?php esc_html_e( 'Previous Academic Background', 'ifsedu-school-management' ); ?></span>
                    </div>

                    <div class="ifs-educore-grid-4">
                        <div class="ifs-educore-field-group">
                            <label class="ifs-educore-field-label"><?php esc_html_e( 'Previous School Name', 'ifsedu-school-management' ); ?></label>
                            <input type="text" name="prev_school_name" class="ifs-educore-input" value="<?php echo $student ? esc_attr( $student->prev_school_name ) : ''; ?>">
                        </div>

                        <div class="ifs-educore-field-group">
                            <label class="ifs-educore-field-label"><?php esc_html_e( 'Previous Institute EIIN', 'ifsedu-school-management' ); ?></label>
                            <input type="text" name="prev_eiin" class="ifs-educore-input" value="<?php echo $student ? esc_attr( $student->prev_eiin ) : ''; ?>">
                        </div>

                        <div class="ifs-educore-field-group">
                            <label class="ifs-educore-field-label"><?php esc_html_e( 'Last Passed Class', 'ifsedu-school-management' ); ?></label>
                            <input type="text" name="prev_class" class="ifs-educore-input" value="<?php echo $student ? esc_attr( $student->prev_class ) : ''; ?>" placeholder="<?php esc_attr_e( 'e.g. Class 5', 'ifsedu-school-management' ); ?>">
                        </div>

                        <div class="ifs-educore-field-group">
                            <label class="ifs-educore-field-label"><?php esc_html_e( 'Obtained GPA / Marks', 'ifsedu-school-management' ); ?></label>
                            <input type="text" name="prev_gpa" class="ifs-educore-input" value="<?php echo $student ? esc_attr( $student->prev_gpa ) : ''; ?>" placeholder="<?php esc_attr_e( 'e.g. 5.00', 'ifsedu-school-management' ); ?>">
                        </div>
                    </div>
                </div>

                <!-- STEP 4: Financial Waiver -->
                <div class="ifs-educore-step-panel" id="ifs_educore_step_4">
                    <div class="ifs-educore-section-heading">
                        <span><?php esc_html_e( 'Permanent Financial Waiver & Reference Scheme', 'ifsedu-school-management' ); ?></span>
                    </div>

                    <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:12px; padding:20px; margin-bottom:24px;">
                        <p style="margin:0 0 16px 0; color:#166534; font-size:13.5px; font-weight:600;">
                            <?php esc_html_e( 'Select any institutional faculty member to link this student and assign an ongoing percentage waiver for monthly tuition fees.', 'ifsedu-school-management' ); ?>
                        </p>

                        <div class="ifs-educore-grid-2">
                            <div class="ifs-educore-field-group">
                                <label class="ifs-educore-field-label"><?php esc_html_e( 'Referred Teacher / Staff / Officer', 'ifsedu-school-management' ); ?></label>
                                <select name="waiver_staff_id" class="ifs-educore-select">
                                    <option value="0"><?php esc_html_e( '-- No Staff Reference / General Student --', 'ifsedu-school-management' ); ?></option>
                                    <?php foreach ( $staff_members as $sm ) : ?>
                                        <option value="<?php echo intval( $sm->id ); ?>" <?php selected( $student ? $student->waiver_staff_id : 0, $sm->id ); ?>>
                                            <?php echo esc_html( $sm->full_name . ' (' . $sm->designation . ' - ' . $sm->staff_type . ')' ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="ifs-educore-field-group">
                                <label class="ifs-educore-field-label"><?php esc_html_e( 'All-Time Waiver / Discount Percentage (%)', 'ifsedu-school-management' ); ?></label>
                                <div style="display:flex; align-items:center; gap:8px;">
                                    <input type="number" step="0.01" min="0" max="100" name="waiver_percentage" class="ifs-educore-input" style="font-weight:800; font-size:15px; color:#00523c;" value="<?php echo $student ? esc_attr( $student->waiver_percentage ) : '0.00'; ?>" placeholder="<?php esc_attr_e( '0.00', 'ifsedu-school-management' ); ?>">
                                    <span style="font-size:18px; font-weight:800; color:#00523c;">%</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STEP 5: Address, Photo & Logistics -->
                <div class="ifs-educore-step-panel" id="ifs_educore_step_5">
                    <div class="ifs-educore-section-heading">
                        <span><?php esc_html_e( 'Address Details', 'ifsedu-school-management' ); ?></span>
                        <button type="button" id="ifs_educore_btnCopyAddress" style="background:none; border:none; color:#00523c; font-weight:700; font-size:12px; cursor:pointer;">
                            <span class="dashicons dashicons-admin-page" style="vertical-align:middle;"></span> <?php esc_html_e( 'Same as Present Address', 'ifsedu-school-management' ); ?>
                        </button>
                    </div>

                    <div class="ifs-educore-grid-2">
                        <div class="ifs-educore-field-group">
                            <label class="ifs-educore-field-label"><?php esc_html_e( 'Present Address', 'ifsedu-school-management' ); ?> <span style="color:#ef4444;">*</span></label>
                            <textarea name="address" id="ifs_educore_present_address" rows="3" class="ifs-educore-textarea" required><?php echo $student ? esc_textarea( $student->address ) : ''; ?></textarea>
                        </div>

                        <div class="ifs-educore-field-group">
                            <label class="ifs-educore-field-label"><?php esc_html_e( 'Permanent Address', 'ifsedu-school-management' ); ?></label>
                            <textarea name="permanent_address" id="ifs_educore_permanent_address" rows="3" class="ifs-educore-textarea"><?php echo $student ? esc_textarea( $student->permanent_address ) : ''; ?></textarea>
                        </div>
                    </div>

                    <div class="ifs-educore-section-heading">
                        <span><?php esc_html_e( 'Logistics & Account Setup', 'ifsedu-school-management' ); ?></span>
                    </div>

                    <div class="ifs-educore-photo-uploader-box">
                        <img src="<?php echo ( $student && ! empty( $student->photo_url ) ) ? esc_url( $student->photo_url ) : esc_url( $default_avatar ); ?>" id="ifs_educore_studentPhotoPreview" class="ifs-educore-avatar-preview" alt="<?php esc_attr_e( 'Student Preview', 'ifsedu-school-management' ); ?>">

                        <div class="ifs-educore-field-group" style="flex:1;">
                            <label class="ifs-educore-field-label"><?php esc_html_e( 'Upload Student Portrait Photo', 'ifsedu-school-management' ); ?></label>
                            <input type="file" name="student_photo" id="ifs_educore_studentPhotoInput" accept="image/jpeg,image/png,image/webp" class="ifs-educore-input" style="padding-top:8px;">
                            <span class="ifs-educore-hint-text"><?php esc_html_e( 'Upload passport-size student photograph (JPG, PNG, or WEBP)', 'ifsedu-school-management' ); ?></span>
                        </div>
                    </div>

                    <div class="ifs-educore-grid-3">
                        <div class="ifs-educore-field-group">
                            <label class="ifs-educore-field-label"><?php esc_html_e( 'Residential Status', 'ifsedu-school-management' ); ?></label>
                            <select name="residential_status" class="ifs-educore-select">
                                <option value="Non-Residential" <?php selected( $student ? $student->residential_status : '', 'Non-Residential' ); ?>><?php esc_html_e( 'Non-Residential', 'ifsedu-school-management' ); ?></option>
                                <option value="Residential (School Hostel)" <?php selected( $student ? $student->residential_status : '', 'Residential (School Hostel)' ); ?>><?php esc_html_e( 'Residential (School Hostel)', 'ifsedu-school-management' ); ?></option>
                                <option value="Mess / Private Care" <?php selected( $student ? $student->residential_status : '', 'Mess / Private Care' ); ?>><?php esc_html_e( 'Mess / Private Care', 'ifsedu-school-management' ); ?></option>
                            </select>
                        </div>

                        <div class="ifs-educore-field-group">
                            <label class="ifs-educore-field-label"><?php esc_html_e( 'Co-Curricular Activities', 'ifsedu-school-management' ); ?></label>
                            <div style="display:flex; flex-wrap:wrap; gap:10px; margin-top:8px;">
                                <?php
                                $activities = array('Scout', 'BNCC', 'Red Crescent', 'Sports Club', 'Cultural Club');
                                $current_acts = ( $student && ! empty( $student->co_curricular ) ) ? array_map('trim', explode(',', $student->co_curricular)) : array();
                                foreach ( $activities as $act ) : ?>
                                    <label style="font-size:12px; font-weight:600; cursor:pointer; display:flex; align-items:center; gap:4px;">
                                        <input type="checkbox" name="co_curricular[]" value="<?php echo esc_attr( $act ); ?>" <?php checked( in_array( $act, $current_acts, true ), true ); ?>> <?php echo esc_html( $act ); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="ifs-educore-field-group">
                            <label class="ifs-educore-field-label"><?php esc_html_e( 'Account Status', 'ifsedu-school-management' ); ?></label>
                            <select name="status" class="ifs-educore-select">
                                <option value="Active" <?php selected( $student ? $student->status : '', 'Active' ); ?>><?php esc_html_e( 'Active', 'ifsedu-school-management' ); ?></option>
                                <option value="Inactive" <?php selected( $student ? $student->status : '', 'Inactive' ); ?>><?php esc_html_e( 'Inactive', 'ifsedu-school-management' ); ?></option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Actions Footer Bar -->
                <div class="ifs-educore-actions-footer">
                    <button type="button" class="ifs-educore-btn ifs-educore-btn-prev" id="ifs_educore_btnPrevStep" style="visibility:hidden;">
                        <span class="dashicons dashicons-arrow-left-alt2"></span> <?php esc_html_e( 'Previous', 'ifsedu-school-management' ); ?>
                    </button>

                    <div>
                        <button type="button" class="ifs-educore-btn ifs-educore-btn-next" id="ifs_educore_btnNextStep">
                            <?php esc_html_e( 'Next Step', 'ifsedu-school-management' ); ?> <span class="dashicons dashicons-arrow-right-alt2"></span>
                        </button>

                        <button type="submit" id="ifs_educore_btnSubmitForm" class="ifs-educore-btn ifs-educore-btn-submit" style="display:none;">
                            <span class="dashicons dashicons-saved"></span> <?php echo $is_edit ? esc_html__( 'Update Record', 'ifsedu-school-management' ) : esc_html__( 'Finalize Admission', 'ifsedu-school-management' ); ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Client-Side Navigation, Live Preview & Class-Section Sync -->
    <script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function() {
        var currentStep = 1;
        var totalSteps = 5;

        var form = document.getElementById('ifs_educore_student_form');
        var btnNext = document.getElementById('ifs_educore_btnNextStep');
        var btnPrev = document.getElementById('ifs_educore_btnPrevStep');
        var btnSubmit = document.getElementById('ifs_educore_btnSubmitForm');
        var stepNodes = document.querySelectorAll('#ifs_educore_student_stepper .ifs-educore-step-node');

        var classSectionMap = <?php echo wp_json_encode( $class_section_map ); ?>;
        var classSelect = document.getElementById('ifs_educore_class_select');
        var sectionSelect = document.getElementById('ifs_educore_section_select');
        var currentSavedSection = "<?php echo $student ? esc_js( $student->section_name ) : ''; ?>";

        function updateSections(className, selectedSec) {
            selectedSec = selectedSec || '';
            if (!sectionSelect) return;
            sectionSelect.innerHTML = '<option value=""><?php echo esc_js( __( '-- Select Section --', 'ifsedu-school-management' ) ); ?></option>';

            if (className && classSectionMap[className] && classSectionMap[className].length > 0) {
                classSectionMap[className].forEach(function(sec) {
                    var opt = document.createElement('option');
                    opt.value = sec;
                    opt.textContent = sec;
                    if (sec === selectedSec) {
                        opt.selected = true;
                    }
                    sectionSelect.appendChild(opt);
                });
            }
        }

        if (classSelect) {
            classSelect.addEventListener('change', function() {
                updateSections(this.value, '');
            });

            if (classSelect.value) {
                updateSections(classSelect.value, currentSavedSection);
            }
        }

        // Live Image Preview
        var photoInput = document.getElementById('ifs_educore_studentPhotoInput');
        var photoPreview = document.getElementById('ifs_educore_studentPhotoPreview');
        if (photoInput && photoPreview) {
            photoInput.addEventListener('change', function(e) {
                var file = e.target.files[0];
                if (file) {
                    var reader = new FileReader();
                    reader.onload = function(evt) {
                        photoPreview.src = evt.target.result;
                    };
                    reader.readAsDataURL(file);
                }
            });
        }

        // Copy Address Helper
        var btnCopy = document.getElementById('ifs_educore_btnCopyAddress');
        if (btnCopy) {
            btnCopy.addEventListener('click', function() {
                var present = document.getElementById('ifs_educore_present_address');
                var permanent = document.getElementById('ifs_educore_permanent_address');
                if (present && permanent) {
                    permanent.value = present.value;
                }
            });
        }

        function renderStep() {
            document.querySelectorAll('.ifs-educore-step-panel').forEach(function(panel) {
                panel.classList.remove('active');
            });
            
            var activePanel = document.getElementById('ifs_educore_step_' + currentStep);
            if (activePanel) {
                activePanel.classList.add('active');
            }

            stepNodes.forEach(function(node) {
                var stepNum = parseInt(node.getAttribute('data-step'), 10);
                node.classList.remove('active', 'completed');
                if (stepNum === currentStep) {
                    node.classList.add('active');
                } else if (stepNum < currentStep) {
                    node.classList.add('completed');
                }
            });

            btnPrev.style.visibility = (currentStep === 1) ? 'hidden' : 'visible';

            if (currentStep === totalSteps) {
                btnNext.style.display = 'none';
                btnSubmit.style.display = 'inline-flex';
            } else {
                btnNext.style.display = 'inline-flex';
                btnSubmit.style.display = 'none';
            }
        }

        function validateStep(stepNumber) {
            var activePanel = document.getElementById('ifs_educore_step_' + stepNumber);
            if (!activePanel) return true;

            var requiredFields = activePanel.querySelectorAll('[required]');
            var valid = true;

            requiredFields.forEach(function(field) {
                if (!field.value.trim()) {
                    valid = false;
                    field.style.borderColor = '#ef4444';
                    field.addEventListener('input', function tmp() {
                        if (field.value.trim()) {
                            field.style.borderColor = '#cbd5e1';
                            field.removeEventListener('input', tmp);
                        }
                    });
                }
            });

            if (!valid) {
                alert('<?php echo esc_js( __( 'Please complete all required (*) fields in this step before proceeding.', 'ifsedu-school-management' ) ); ?>');
            }
            return valid;
        }

        btnNext.addEventListener('click', function() {
            if (validateStep(currentStep) && currentStep < totalSteps) {
                currentStep++;
                renderStep();
            }
        });

        btnPrev.addEventListener('click', function() {
            if (currentStep > 1) {
                currentStep--;
                renderStep();
            }
        });

        stepNodes.forEach(function(node) {
            node.addEventListener('click', function() {
                var target = parseInt(this.getAttribute('data-step'), 10);
                if (target < currentStep || validateStep(currentStep)) {
                    currentStep = target;
                    renderStep();
                }
            });
        });

        if (form) {
            form.addEventListener('submit', function(e) {
                for (var i = 1; i <= totalSteps; i++) {
                    if (!validateStep(i)) {
                        e.preventDefault();
                        currentStep = i;
                        renderStep();
                        return false;
                    }
                }
            });
        }
    });
    </script>
    <?php
}
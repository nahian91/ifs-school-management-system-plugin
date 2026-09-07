<?php
/**
 * Enterprise Multi-Step Staff Profile & Management Engine
 * File: inc/staff/staff-add-edit.php
 * Target Table: sms_staff
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Immediate access layer lockdown
}

function educore_staff_add_edit_view() {
    global $wpdb;
    $table_staff = $wpdb->prefix . 'sms_staff';

    // 1. Security & Capability Verification
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have permission to manage staff profiles.', 'ifsedu-school-management' ) );
    }

    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $is_edit  = isset( $_GET['sub'] ) && 'edit' === sanitize_key( wp_unslash( $_GET['sub'] ) );
    $staff_id = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0;
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    $db_error = '';

    $staff = null;
    if ( $is_edit && $staff_id > 0 ) {
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $staff = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table_staff}` WHERE id = %d LIMIT 1", $staff_id ) );
        // phpcs:enable
        if ( ! $staff ) {
            $is_edit = false;
        }
    }

    // 2. Fetch Configured ID Prefixes from Settings
    $prefix_teacher = get_option( 'educore_prefix_teacher', 'TCH-' );
    $prefix_staff   = get_option( 'educore_prefix_staff', 'STF-' );
    $prefix_officer = get_option( 'educore_prefix_officer', 'OFC-' );

    // Determine Prefix according to staff type or default to Teacher
    $initial_staff_type = $staff ? $staff->staff_type : 'Teacher (School)';
    $selected_prefix = $prefix_teacher;

    if ( 'Officer' === $initial_staff_type ) {
        $selected_prefix = $prefix_officer;
    } elseif ( 'Staff' === $initial_staff_type ) {
        $selected_prefix = $prefix_staff;
    }

    // Generate Auto-Sequential ID based on prefix
    if ( ! $is_edit || empty( $staff->staff_id ) ) {
        $escaped_like = $wpdb->esc_like( $selected_prefix ) . '%';
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $existing_ids = $wpdb->get_col( $wpdb->prepare( "SELECT staff_id FROM `{$table_staff}` WHERE staff_id LIKE %s", $escaped_like ) );
        // phpcs:enable

        $max_num = 0;
        if ( ! empty( $existing_ids ) ) {
            $pattern = '/^' . preg_quote( $selected_prefix, '/' ) . '(\d+)$/i';
            foreach ( $existing_ids as $sid ) {
                if ( preg_match( $pattern, trim( (string) $sid ), $matches ) ) {
                    $num = intval( $matches[1] );
                    if ( $num > $max_num ) {
                        $max_num = $num;
                    }
                }
            }
        }
        $next_num = $max_num + 1;
        $generated_staff_id = $selected_prefix . str_pad( (string) $next_num, 4, '0', STR_PAD_LEFT );
    } else {
        $generated_staff_id = $staff->staff_id;
    }

    // 3. Handle Form Submission
    $req_method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
    if ( 'POST' === $req_method && isset( $_POST['educore_staff_action'] ) && 'save_staff' === $_POST['educore_staff_action'] && isset( $_POST['ifs_educore_staff_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ifs_educore_staff_nonce'] ) ), 'save_staff_action' ) ) {

        $profile_image   = ( $staff && isset( $staff->profile_image ) ) ? $staff->profile_image : '';
        $posted_staff_id = isset( $_POST['staff_id'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['staff_id'] ) ) ) : $generated_staff_id;

        // Check if Staff ID is already registered for another profile
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        if ( $is_edit && $staff_id > 0 ) {
            $exists = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$table_staff}` WHERE staff_id = %s AND id != %d LIMIT 1", $posted_staff_id, $staff_id ) );
        } else {
            $exists = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$table_staff}` WHERE staff_id = %s LIMIT 1", $posted_staff_id ) );
        }
        // phpcs:enable

        if ( $exists > 0 ) {
            $db_error = esc_html__( 'Error: This Staff ID is already registered. Please use a unique identifier.', 'ifsedu-school-management' );
        } else {
            // Handle Portrait Image Upload with MIME Check
            if ( ! empty( $_FILES['staff_photo']['name'] ) ) {
                $allowed_mimes = array(
                    'jpg|jpeg|jpe' => 'image/jpeg',
                    'png'          => 'image/png',
                    'webp'         => 'image/webp',
                );

                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                $file_info = wp_check_filetype( sanitize_file_name( $_FILES['staff_photo']['name'] ), $allowed_mimes );

                if ( ! in_array( $file_info['type'], $allowed_mimes, true ) ) {
                    wp_die( esc_html__( 'Security Error: Only valid JPG, PNG, and WEBP image formats are accepted.', 'ifsedu-school-management' ) );
                }

                require_once ABSPATH . 'wp-admin/includes/file.php';
                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                $uploaded_file = wp_handle_upload( $_FILES['staff_photo'], array( 'test_form' => false, 'mimes' => $allowed_mimes ) );
                if ( ! isset( $uploaded_file['error'] ) && isset( $uploaded_file['url'] ) ) {
                    $profile_image = esc_url_raw( $uploaded_file['url'] );
                }
            }

            $dob_date     = ! empty( $_POST['dob'] ) ? sanitize_text_field( wp_unslash( $_POST['dob'] ) ) : '1980-01-01';
            $joining_date = ! empty( $_POST['joining_date'] ) ? sanitize_text_field( wp_unslash( $_POST['joining_date'] ) ) : current_time( 'Y-m-d' );

            $data = array(
                'staff_id'           => $posted_staff_id,
                'order_number'       => isset( $_POST['order_number'] ) ? absint( wp_unslash( $_POST['order_number'] ) ) : 0,
                'staff_type'         => isset( $_POST['staff_type'] ) ? sanitize_text_field( wp_unslash( $_POST['staff_type'] ) ) : '',
                'designation'        => isset( $_POST['designation'] ) ? sanitize_text_field( wp_unslash( $_POST['designation'] ) ) : '',
                'full_name'          => isset( $_POST['full_name'] ) ? sanitize_text_field( wp_unslash( $_POST['full_name'] ) ) : '',
                'name_bn'            => '', 
                'father_name'        => isset( $_POST['father_name'] ) ? sanitize_text_field( wp_unslash( $_POST['father_name'] ) ) : '',
                'mother_name'        => isset( $_POST['mother_name'] ) ? sanitize_text_field( wp_unslash( $_POST['mother_name'] ) ) : '',
                'pay_grade'          => isset( $_POST['pay_grade'] ) ? sanitize_text_field( wp_unslash( $_POST['pay_grade'] ) ) : '',
                'index_no'           => isset( $_POST['index_no'] ) ? sanitize_text_field( wp_unslash( $_POST['index_no'] ) ) : '',
                'nid_no'             => isset( $_POST['nid_no'] ) ? sanitize_text_field( wp_unslash( $_POST['nid_no'] ) ) : '',
                'dob'                => $dob_date,
                'gender'             => isset( $_POST['gender'] ) ? sanitize_text_field( wp_unslash( $_POST['gender'] ) ) : 'Male',
                'phone'              => isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '',
                'whatsapp_no'        => isset( $_POST['whatsapp_no'] ) ? sanitize_text_field( wp_unslash( $_POST['whatsapp_no'] ) ) : '',
                'email'              => isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '',
                'blood_group'        => isset( $_POST['blood_group'] ) ? sanitize_text_field( wp_unslash( $_POST['blood_group'] ) ) : '',
                'quota_type'         => isset( $_POST['quota_type'] ) ? sanitize_text_field( wp_unslash( $_POST['quota_type'] ) ) : 'General',
                'joining_date'       => $joining_date,
                'salary'             => isset( $_POST['salary'] ) ? floatval( wp_unslash( $_POST['salary'] ) ) : 0.00,
                'subject_expert'     => isset( $_POST['subject_expert'] ) ? sanitize_text_field( wp_unslash( $_POST['subject_expert'] ) ) : '',
                'highest_degree'     => isset( $_POST['highest_degree'] ) ? sanitize_text_field( wp_unslash( $_POST['highest_degree'] ) ) : '',

                'emergency_name'     => isset( $_POST['emergency_name'] ) ? sanitize_text_field( wp_unslash( $_POST['emergency_name'] ) ) : '',
                'emergency_phone'    => isset( $_POST['emergency_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['emergency_phone'] ) ) : '',
                'emergency_relation' => isset( $_POST['emergency_relation'] ) ? sanitize_text_field( wp_unslash( $_POST['emergency_relation'] ) ) : '',

                'bank_name'          => isset( $_POST['bank_name'] ) ? sanitize_text_field( wp_unslash( $_POST['bank_name'] ) ) : '',
                'bank_acc_no'        => isset( $_POST['bank_acc_no'] ) ? sanitize_text_field( wp_unslash( $_POST['bank_acc_no'] ) ) : '',
                'bank_routing'       => isset( $_POST['bank_routing'] ) ? sanitize_text_field( wp_unslash( $_POST['bank_routing'] ) ) : '',

                'address'            => isset( $_POST['address'] ) ? sanitize_textarea_field( wp_unslash( $_POST['address'] ) ) : '',
                'permanent_address'  => isset( $_POST['permanent_address'] ) ? sanitize_textarea_field( wp_unslash( $_POST['permanent_address'] ) ) : '',

                'linkedin_url'       => isset( $_POST['linkedin_url'] ) ? sanitize_url( wp_unslash( $_POST['linkedin_url'] ) ) : '',
                'facebook_url'       => isset( $_POST['facebook_url'] ) ? sanitize_url( wp_unslash( $_POST['facebook_url'] ) ) : '',
                'website_url'        => isset( $_POST['website_url'] ) ? sanitize_url( wp_unslash( $_POST['website_url'] ) ) : '',

                'profile_image'      => $profile_image,
                'status'             => isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'Active',
            );

            $formats = array(
                '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'
            );

            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            if ( $is_edit && $staff_id > 0 ) {
                $updated = $wpdb->update( $table_staff, $data, array( 'id' => $staff_id ), $formats, array( '%d' ) );
                if ( false !== $updated ) {
                    $redirect_url = admin_url( 'admin.php?page=school_management_system&tab=staff&sub=list&msg=updated' );
                    if ( ! headers_sent() ) {
                        wp_safe_redirect( $redirect_url );
                        exit;
                    } else {
                        echo '<script type="text/javascript">window.location.href=' . wp_json_encode( esc_url_raw( $redirect_url ) ) . ';</script>';
                        exit;
                    }
                } else {
                    $db_error = $wpdb->last_error ? $wpdb->last_error : esc_html__( 'Failed to update database record.', 'ifsedu-school-management' );
                }
            } else {
                $inserted = $wpdb->insert( $table_staff, $data, $formats );
                if ( false !== $inserted ) {
                    if ( function_exists( 'educore_log_activity' ) ) {
                        /* translators: %s: Staff full name */
                        educore_log_activity( sprintf( __( 'Added new staff record: %s', 'ifsedu-school-management' ), $data['full_name'] ) );
                    }
                    $redirect_url = admin_url( 'admin.php?page=school_management_system&tab=staff&sub=list&msg=success' );
                    if ( ! headers_sent() ) {
                        wp_safe_redirect( $redirect_url );
                        exit;
                    } else {
                        echo '<script type="text/javascript">window.location.href=' . wp_json_encode( esc_url_raw( $redirect_url ) ) . ';</script>';
                        exit;
                    }
                } else {
                    $db_error = $wpdb->last_error ? $wpdb->last_error : esc_html__( 'Failed to insert staff record to database.', 'ifsedu-school-management' );
                }
            }
            // phpcs:enable
        }
    }

    $back_url = admin_url( 'admin.php?page=school_management_system&tab=staff&sub=list' );
    ?>

    <div class="mb-3">
        <a href="<?php echo esc_url( $back_url ); ?>" class="btn btn-secondary btn-sm">&larr; <?php esc_html_e( 'Back to Directory', 'ifsedu-school-management' ); ?></a>
    </div>

    <?php if ( ! empty( $db_error ) ) : ?>
        <div class="alert alert-danger mb-3 font-weight-bold">
            <?php echo esc_html( $db_error ); ?>
        </div>
    <?php endif; ?>

    <div class="bg-white p-4 rounded shadow-sm border">
        <h3 class="pb-2 mb-4 text-success fw-bold border-bottom">
            <?php echo $is_edit ? esc_html__( 'Edit Staff Details', 'ifsedu-school-management' ) : esc_html__( 'Add New Staff / Teacher', 'ifsedu-school-management' ); ?>
        </h3>
        
        <!-- Tab Indicators -->
        <ul class="nav nav-tabs mb-4 flex-column flex-sm-row" id="educoreStaffTabs" role="tablist">
            <li class="nav-item flex-sm-fill text-center">
                <a class="nav-link active" id="step-1-tab" data-step="1" href="javascript:void(0);"><?php esc_html_e( '1. Personal Info', 'ifsedu-school-management' ); ?></a>
            </li>
            <li class="nav-item flex-sm-fill text-center">
                <a class="nav-link" id="step-2-tab" data-step="2" href="javascript:void(0);"><?php esc_html_e( '2. Employment & Academic', 'ifsedu-school-management' ); ?></a>
            </li>
            <li class="nav-item flex-sm-fill text-center">
                <a class="nav-link" id="step-3-tab" data-step="3" href="javascript:void(0);"><?php esc_html_e( '3. Payroll & Banking', 'ifsedu-school-management' ); ?></a>
            </li>
            <li class="nav-item flex-sm-fill text-center">
                <a class="nav-link" id="step-4-tab" data-step="4" href="javascript:void(0);"><?php esc_html_e( '4. Address & Socials', 'ifsedu-school-management' ); ?></a>
            </li>
        </ul>

        <form method="POST" action="" enctype="multipart/form-data" id="educoreStaffForm" novalidate>
            <?php wp_nonce_field( 'save_staff_action', 'ifs_educore_staff_nonce' ); ?>
            <input type="hidden" name="educore_staff_action" value="save_staff">
            
            <!-- STEP 1: Personal Identification -->
            <div class="educore-step-content active" id="educore-step-1">
                <?php if ( $is_edit && $staff && ! empty( $staff->profile_image ) ) : ?>
                    <div class="mb-4">
                        <label class="form-label d-block fw-bold"><?php esc_html_e( 'Current Photo', 'ifsedu-school-management' ); ?></label>
                        <img src="<?php echo esc_url( $staff->profile_image ); ?>" alt="<?php esc_attr_e( 'Staff Photo', 'ifsedu-school-management' ); ?>" class="rounded border" style="width: 100px; height: 100px; object-fit: cover;">
                    </div>
                <?php endif; ?>

                <h5 class="mb-3 text-success border-bottom pb-2"><?php esc_html_e( 'Personal Identification', 'ifsedu-school-management' ); ?></h5>
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold"><?php esc_html_e( 'Staff / Employee ID', 'ifsedu-school-management' ); ?> <span class="text-danger">*</span></label>
                        <input type="text" name="staff_id" id="educore_staff_id_input" class="form-control text-uppercase fw-bold text-success bg-light" value="<?php echo esc_attr( $generated_staff_id ); ?>" required readonly>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold"><?php esc_html_e( 'Order Number', 'ifsedu-school-management' ); ?></label>
                        <input type="number" name="order_number" class="form-control" placeholder="<?php esc_attr_e( 'e.g., 1', 'ifsedu-school-management' ); ?>" value="<?php echo ( $staff && isset( $staff->order_number ) ) ? absint( $staff->order_number ) : 0; ?>" min="0">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold"><?php esc_html_e( 'Employment Type', 'ifsedu-school-management' ); ?> <span class="text-danger">*</span></label>
                        <select name="staff_type" id="educore_staff_type_select" class="form-control" required>
                            <option value=""><?php esc_html_e( '-- Select Type --', 'ifsedu-school-management' ); ?></option>
                            <option value="Teacher (School)" <?php selected( ( $staff && isset( $staff->staff_type ) ) ? $staff->staff_type : '', 'Teacher (School)' ); ?>><?php esc_html_e( 'Teacher (School)', 'ifsedu-school-management' ); ?></option>
                            <option value="Teacher (College)" <?php selected( ( $staff && isset( $staff->staff_type ) ) ? $staff->staff_type : '', 'Teacher (College)' ); ?>><?php esc_html_e( 'Teacher (College)', 'ifsedu-school-management' ); ?></option>
                            <option value="Officer" <?php selected( ( $staff && isset( $staff->staff_type ) ) ? $staff->staff_type : '', 'Officer' ); ?>><?php esc_html_e( 'Officer', 'ifsedu-school-management' ); ?></option>
                            <option value="Staff" <?php selected( ( $staff && isset( $staff->staff_type ) ) ? $staff->staff_type : '', 'Staff' ); ?>><?php esc_html_e( 'Staff', 'ifsedu-school-management' ); ?></option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold"><?php esc_html_e( 'Designation (Official Role)', 'ifsedu-school-management' ); ?> <span class="text-danger">*</span></label>
                        <input type="text" name="designation" class="form-control" placeholder="<?php esc_attr_e( 'e.g., Assistant Teacher, Lecturer', 'ifsedu-school-management' ); ?>" value="<?php echo ( $staff && isset( $staff->designation ) ) ? esc_attr( $staff->designation ) : ''; ?>" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold"><?php esc_html_e( 'Full Name (English)', 'ifsedu-school-management' ); ?> <span class="text-danger">*</span></label>
                        <input type="text" name="full_name" class="form-control" value="<?php echo ( $staff && isset( $staff->full_name ) ) ? esc_attr( $staff->full_name ) : ''; ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold"><?php esc_html_e( 'National ID / NID No', 'ifsedu-school-management' ); ?> <span class="text-danger">*</span></label>
                        <input type="text" name="nid_no" class="form-control" maxlength="17" value="<?php echo ( $staff && isset( $staff->nid_no ) ) ? esc_attr( $staff->nid_no ) : ''; ?>" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold"><?php esc_html_e( "Father's Name", 'ifsedu-school-management' ); ?></label>
                        <input type="text" name="father_name" class="form-control" value="<?php echo ( $staff && isset( $staff->father_name ) ) ? esc_attr( $staff->father_name ) : ''; ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold"><?php esc_html_e( "Mother's Name", 'ifsedu-school-management' ); ?></label>
                        <input type="text" name="mother_name" class="form-control" value="<?php echo ( $staff && isset( $staff->mother_name ) ) ? esc_attr( $staff->mother_name ) : ''; ?>">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold"><?php esc_html_e( 'Date of Birth', 'ifsedu-school-management' ); ?> <span class="text-danger">*</span></label>
                        <input type="date" name="dob" class="form-control" value="<?php echo ( $staff && isset( $staff->dob ) && '1970-01-01' !== $staff->dob ) ? esc_attr( $staff->dob ) : ''; ?>" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold"><?php esc_html_e( 'Gender', 'ifsedu-school-management' ); ?> <span class="text-danger">*</span></label>
                        <select name="gender" class="form-control" required>
                            <option value="Male" <?php selected( ( $staff && isset( $staff->gender ) ) ? $staff->gender : '', 'Male' ); ?>><?php esc_html_e( 'Male', 'ifsedu-school-management' ); ?></option>
                            <option value="Female" <?php selected( ( $staff && isset( $staff->gender ) ) ? $staff->gender : '', 'Female' ); ?>><?php esc_html_e( 'Female', 'ifsedu-school-management' ); ?></option>
                            <option value="Other" <?php selected( ( $staff && isset( $staff->gender ) ) ? $staff->gender : '', 'Other' ); ?>><?php esc_html_e( 'Other', 'ifsedu-school-management' ); ?></option>
                        </select>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold"><?php esc_html_e( 'Mobile Number', 'ifsedu-school-management' ); ?> <span class="text-danger">*</span></label>
                        <input type="text" name="phone" class="form-control" value="<?php echo ( $staff && isset( $staff->phone ) ) ? esc_attr( $staff->phone ) : ''; ?>" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold"><?php esc_html_e( 'WhatsApp Number', 'ifsedu-school-management' ); ?></label>
                        <input type="text" name="whatsapp_no" class="form-control" placeholder="<?php esc_attr_e( 'e.g., 01XXXXXXXXX', 'ifsedu-school-management' ); ?>" value="<?php echo ( $staff && isset( $staff->whatsapp_no ) ) ? esc_attr( $staff->whatsapp_no ) : ''; ?>">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-8 mb-3">
                        <label class="form-label fw-bold"><?php esc_html_e( 'Email Address', 'ifsedu-school-management' ); ?></label>
                        <input type="email" name="email" class="form-control" value="<?php echo ( $staff && isset( $staff->email ) ) ? esc_attr( $staff->email ) : ''; ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold"><?php esc_html_e( 'Blood Group', 'ifsedu-school-management' ); ?></label>
                        <select name="blood_group" class="form-control">
                            <option value=""><?php esc_html_e( 'Select Blood Group', 'ifsedu-school-management' ); ?></option>
                            <?php
                            $blood_groups = array( 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-' );
                            foreach ( $blood_groups as $bg ) {
                                echo '<option value="' . esc_attr( $bg ) . '" ' . selected( ( $staff && isset( $staff->blood_group ) ) ? $staff->blood_group : '', $bg, false ) . '>' . esc_html( $bg ) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- STEP 2: Employment & Academic Structure -->
            <div class="educore-step-content" id="educore-step-2">
                <h5 class="mb-3 text-success border-bottom pb-2"><?php esc_html_e( 'Employment & Academic Setup', 'ifsedu-school-management' ); ?></h5>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold"><?php esc_html_e( 'National Pay Scale Grade', 'ifsedu-school-management' ); ?></label>
                        <select name="pay_grade" class="form-control">
                            <option value=""><?php esc_html_e( '-- Select Pay Grade --', 'ifsedu-school-management' ); ?></option>
                            <?php
                            for ( $i = 1; $i <= 20; $i++ ) {
                                $grade_str = 'Grade ' . $i;
                                echo '<option value="' . esc_attr( $grade_str ) . '" ' . selected( ( $staff && isset( $staff->pay_grade ) ) ? $staff->pay_grade : '', $grade_str, false ) . '>' . esc_html( $grade_str ) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold"><?php esc_html_e( 'MPO Index Number', 'ifsedu-school-management' ); ?></label>
                        <input type="text" name="index_no" class="form-control" placeholder="<?php esc_attr_e( 'e.g., T1029384', 'ifsedu-school-management' ); ?>" value="<?php echo ( $staff && isset( $staff->index_no ) ) ? esc_attr( $staff->index_no ) : ''; ?>">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold"><?php esc_html_e( 'Subject Expertise', 'ifsedu-school-management' ); ?></label>
                        <input type="text" name="subject_expert" class="form-control" placeholder="<?php esc_attr_e( 'e.g., Mathematics, English', 'ifsedu-school-management' ); ?>" value="<?php echo ( $staff && isset( $staff->subject_expert ) ) ? esc_attr( $staff->subject_expert ) : ''; ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold"><?php esc_html_e( 'Highest Qualification', 'ifsedu-school-management' ); ?></label>
                        <input type="text" name="highest_degree" class="form-control" placeholder="<?php esc_attr_e( 'e.g., MA in English, B.Sc', 'ifsedu-school-management' ); ?>" value="<?php echo ( $staff && isset( $staff->highest_degree ) ) ? esc_attr( $staff->highest_degree ) : ''; ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold"><?php esc_html_e( 'Quota Category', 'ifsedu-school-management' ); ?></label>
                        <select name="quota_type" class="form-control">
                            <option value="General" <?php selected( ( $staff && isset( $staff->quota_type ) ) ? $staff->quota_type : '', 'General' ); ?>><?php esc_html_e( 'General', 'ifsedu-school-management' ); ?></option>
                            <option value="Freedom Fighter" <?php selected( ( $staff && isset( $staff->quota_type ) ) ? $staff->quota_type : '', 'Freedom Fighter' ); ?>><?php esc_html_e( 'Freedom Fighter', 'ifsedu-school-management' ); ?></option>
                            <option value="Tribal" <?php selected( ( $staff && isset( $staff->quota_type ) ) ? $staff->quota_type : '', 'Tribal' ); ?>><?php esc_html_e( 'Tribal', 'ifsedu-school-management' ); ?></option>
                            <option value="Other" <?php selected( ( $staff && isset( $staff->quota_type ) ) ? $staff->quota_type : '', 'Other' ); ?>><?php esc_html_e( 'Other', 'ifsedu-school-management' ); ?></option>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold"><?php esc_html_e( 'Joining Date', 'ifsedu-school-management' ); ?> <span class="text-danger">*</span></label>
                        <input type="date" name="joining_date" class="form-control" value="<?php echo ( $staff && isset( $staff->joining_date ) && '1970-01-01' !== $staff->joining_date ) ? esc_attr( $staff->joining_date ) : esc_attr( current_time( 'Y-m-d' ) ); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold"><?php esc_html_e( 'Gross / Basic Salary (৳)', 'ifsedu-school-management' ); ?> <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" name="salary" class="form-control" value="<?php echo ( $staff && isset( $staff->salary ) ) ? floatval( $staff->salary ) : '0.00'; ?>" required>
                    </div>
                </div>
            </div>

            <!-- STEP 3: Payroll, Banking & Emergencies -->
            <div class="educore-step-content" id="educore-step-3">
                <h5 class="mb-3 text-success border-bottom pb-2"><?php esc_html_e( 'Bank Accounts & Payroll Mechanics', 'ifsedu-school-management' ); ?></h5>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold"><?php esc_html_e( 'Bank Name', 'ifsedu-school-management' ); ?></label>
                        <input type="text" name="bank_name" class="form-control" placeholder="<?php esc_attr_e( 'e.g., Sonali Bank PLC', 'ifsedu-school-management' ); ?>" value="<?php echo ( $staff && isset( $staff->bank_name ) ) ? esc_attr( $staff->bank_name ) : ''; ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold"><?php esc_html_e( 'Bank Account Number', 'ifsedu-school-management' ); ?></label>
                        <input type="text" name="bank_acc_no" class="form-control" placeholder="<?php esc_attr_e( '13-17 Digit', 'ifsedu-school-management' ); ?>" value="<?php echo ( $staff && isset( $staff->bank_acc_no ) ) ? esc_attr( $staff->bank_acc_no ) : ''; ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold"><?php esc_html_e( 'Bank Routing Number', 'ifsedu-school-management' ); ?></label>
                        <input type="text" name="bank_routing" class="form-control" placeholder="<?php esc_attr_e( '9 Digit Routing Code', 'ifsedu-school-management' ); ?>" value="<?php echo ( $staff && isset( $staff->bank_routing ) ) ? esc_attr( $staff->bank_routing ) : ''; ?>">
                    </div>
                </div>

                <h5 class="mb-3 text-success border-bottom pb-2 mt-4"><?php esc_html_e( 'Emergency Contact Protocol', 'ifsedu-school-management' ); ?></h5>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold"><?php esc_html_e( 'Emergency Contact Name', 'ifsedu-school-management' ); ?></label>
                        <input type="text" name="emergency_name" class="form-control" value="<?php echo ( $staff && isset( $staff->emergency_name ) ) ? esc_attr( $staff->emergency_name ) : ''; ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold"><?php esc_html_e( 'Emergency Contact Relation', 'ifsedu-school-management' ); ?></label>
                        <input type="text" name="emergency_relation" class="form-control" placeholder="<?php esc_attr_e( 'e.g., Spouse, Brother', 'ifsedu-school-management' ); ?>" value="<?php echo ( $staff && isset( $staff->emergency_relation ) ) ? esc_attr( $staff->emergency_relation ) : ''; ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold"><?php esc_html_e( 'Emergency Contact Phone', 'ifsedu-school-management' ); ?></label>
                        <input type="text" name="emergency_phone" class="form-control" value="<?php echo ( $staff && isset( $staff->emergency_phone ) ) ? esc_attr( $staff->emergency_phone ) : ''; ?>">
                    </div>
                </div>
            </div>

            <!-- STEP 4: Logistics, Address & Socials -->
            <div class="educore-step-content" id="educore-step-4">
                <h5 class="mb-3 text-success border-bottom pb-2"><?php esc_html_e( 'Logistics & Status', 'ifsedu-school-management' ); ?></h5>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold"><?php esc_html_e( 'Upload Profile Photo', 'ifsedu-school-management' ); ?></label>
                        <input type="file" name="staff_photo" class="form-control" accept="image/jpeg,image/png,image/webp">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold"><?php esc_html_e( 'Account Status', 'ifsedu-school-management' ); ?></label>
                        <select name="status" class="form-control">
                            <option value="Active" <?php selected( ( $staff && isset( $staff->status ) ) ? $staff->status : '', 'Active' ); ?>><?php esc_html_e( 'Active', 'ifsedu-school-management' ); ?></option>
                            <option value="Resigned" <?php selected( ( $staff && isset( $staff->status ) ) ? $staff->status : '', 'Resigned' ); ?>><?php esc_html_e( 'Resigned / Left', 'ifsedu-school-management' ); ?></option>
                            <option value="Suspended" <?php selected( ( $staff && isset( $staff->status ) ) ? $staff->status : '', 'Suspended' ); ?>><?php esc_html_e( 'Suspended', 'ifsedu-school-management' ); ?></option>
                        </select>
                    </div>
                </div>

                <h5 class="mb-3 text-success border-bottom pb-2 mt-4"><?php esc_html_e( 'Address Details', 'ifsedu-school-management' ); ?></h5>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold"><?php esc_html_e( 'Present Address', 'ifsedu-school-management' ); ?></label>
                        <textarea name="address" class="form-control" rows="3" placeholder="<?php esc_attr_e( 'Vill/Road, Post Office, Upazila, District', 'ifsedu-school-management' ); ?>"><?php echo ( $staff && isset( $staff->address ) ) ? esc_textarea( $staff->address ) : ''; ?></textarea>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold"><?php esc_html_e( 'Permanent Address', 'ifsedu-school-management' ); ?></label>
                        <textarea name="permanent_address" class="form-control" rows="3" placeholder="<?php esc_attr_e( 'Vill/Road, Post Office, Upazila, District', 'ifsedu-school-management' ); ?>"><?php echo ( $staff && isset( $staff->permanent_address ) ) ? esc_textarea( $staff->permanent_address ) : ''; ?></textarea>
                    </div>
                </div>

                <h5 class="mb-3 text-success border-bottom pb-2 mt-4"><?php esc_html_e( 'Social Profiles & Professional Connect', 'ifsedu-school-management' ); ?></h5>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold"><?php esc_html_e( 'LinkedIn URL', 'ifsedu-school-management' ); ?></label>
                        <input type="url" name="linkedin_url" class="form-control" placeholder="https://linkedin.com/in/username" value="<?php echo ( $staff && isset( $staff->linkedin_url ) ) ? esc_url( $staff->linkedin_url ) : ''; ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold"><?php esc_html_e( 'Facebook Profile URL', 'ifsedu-school-management' ); ?></label>
                        <input type="url" name="facebook_url" class="form-control" placeholder="https://facebook.com/username" value="<?php echo ( $staff && isset( $staff->facebook_url ) ) ? esc_url( $staff->facebook_url ) : ''; ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold"><?php esc_html_e( 'Portfolio / Personal Website', 'ifsedu-school-management' ); ?></label>
                        <input type="url" name="website_url" class="form-control" placeholder="https://example.com" value="<?php echo ( $staff && isset( $staff->website_url ) ) ? esc_url( $staff->website_url ) : ''; ?>">
                    </div>
                </div>
            </div>

            <!-- Dynamic Form Control Steering Infrastructure -->
            <div class="form-step-actions d-flex justify-content-between">
                <button type="button" class="btn btn-secondary px-4" id="educorePrevBtn" style="display: none;">&larr; <?php esc_html_e( 'Previous Step', 'ifsedu-school-management' ); ?></button>
                <div class="ms-auto">
                    <button type="button" class="btn btn-primary px-4" id="educoreNextBtn" style="background-color: #2563eb; border: none;"><?php esc_html_e( 'Next Step &rarr;', 'ifsedu-school-management' ); ?></button>
                    <button type="submit" class="btn btn-success px-5" id="educoreSubmitBtn" style="display: none; background-color: #00523c; border: none; font-weight: bold;">
                        <?php echo $is_edit ? esc_html__( 'Update Record Stack', 'ifsedu-school-management' ) : esc_html__( 'Save Staff Member Details', 'ifsedu-school-management' ); ?>
                    </button>
                </div>
            </div>
        </form>
    </div>

    <script type="text/javascript">
        jQuery(document).ready(function($) {
            var currentStep = 1;
            var totalSteps = 4;
            var isEditMode = <?php echo $is_edit ? 'true' : 'false'; ?>;

            var prefixMap = {
                'Teacher (School)': '<?php echo esc_js( $prefix_teacher ); ?>',
                'Teacher (College)': '<?php echo esc_js( $prefix_teacher ); ?>',
                'Officer': '<?php echo esc_js( $prefix_officer ); ?>',
                'Staff': '<?php echo esc_js( $prefix_staff ); ?>'
            };

            // Dynamic Prefix Switcher based on Employment Type (New Records Only)
            $('#educore_staff_type_select').on('change', function() {
                if (isEditMode) return;
                var staffType = $(this).val();
                var prefix = prefixMap[staffType] || '<?php echo esc_js( $prefix_teacher ); ?>';
                var currentVal = $('#educore_staff_id_input').val();
                var numPart = currentVal.split('-')[1] || '0001';
                $('#educore_staff_id_input').val(prefix + numPart);
            });

            // Enforce Uppercase conversion on Staff ID input field
            $('input[name="staff_id"]').on('input', function() {
                this.value = this.value.toUpperCase();
            });

            function updateStepVisibility() {
                $('.educore-step-content').removeClass('active');
                $('#educore-step-' + currentStep).addClass('active');

                // Update Tab Indicator Highlights
                $('#educoreStaffTabs .nav-link').removeClass('active');
                $('#step-' + currentStep + '-tab').addClass('active');

                // Control Dynamic Action Buttons
                if (currentStep === 1) {
                    $('#educorePrevBtn').hide();
                } else {
                    $('#educorePrevBtn').show();
                }

                if (currentStep === totalSteps) {
                    $('#educoreNextBtn').hide();
                    $('#educoreSubmitBtn').show();
                } else {
                    $('#educoreNextBtn').show();
                    $('#educoreSubmitBtn').hide();
                }
            }

            function validateStep(stepNum) {
                var currentStepFields = $('#educore-step-' + stepNum).find('input[required], select[required], textarea[required]');
                var isValid = true;

                currentStepFields.each(function() {
                    if (!this.value.trim()) {
                        isValid = false;
                        $(this).addClass('is-invalid');
                        $(this).on('input change', function tmp() {
                            if (this.value.trim()) {
                                $(this).removeClass('is-invalid');
                                $(this).off('input change', tmp);
                            }
                        });
                    }
                });

                if (!isValid) {
                    alert('<?php echo esc_js( __( 'Please fill in all required (*) fields in this step before proceeding.', 'ifsedu-school-management' ) ); ?>');
                }

                return isValid;
            }

            // Step Forward Mechanics with Validation Check
            $('#educoreNextBtn').on('click', function() {
                if (validateStep(currentStep)) {
                    $('#step-' + currentStep + '-tab').addClass('completed');
                    if (currentStep < totalSteps) {
                        currentStep++;
                        updateStepVisibility();
                    }
                }
            });

            // Step Backward Mechanics
            $('#educorePrevBtn').on('click', function() {
                if (currentStep > 1) {
                    currentStep--;
                    updateStepVisibility();
                }
            });

            // Direct Tab Click Navigation
            $('#educoreStaffTabs .nav-link').on('click', function() {
                var targetStep = parseInt($(this).data('step'), 10);
                if (targetStep < currentStep || validateStep(currentStep)) {
                    currentStep = targetStep;
                    updateStepVisibility();
                }
            });

            // Final Form Submit Validation
            $('#educoreStaffForm').on('submit', function(e) {
                for (var i = 1; i <= totalSteps; i++) {
                    if (!validateStep(i)) {
                        e.preventDefault();
                        currentStep = i;
                        updateStepVisibility();
                        return false;
                    }
                }
            });
        });
    </script>
    <?php
}
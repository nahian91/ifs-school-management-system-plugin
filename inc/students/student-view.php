<?php
/**
 * Render Core Student Comprehensive Profile Single View
 * Architecture: Neo-Bento Dashboard with Interactive Tab Matrix
 * Database Scope: sms_students, sms_results, sms_exams, sms_fees, sms_attendance, sms_staff
 * File: student-profile-view.php
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Direct access safety buffer
}

function educore_student_profile_view() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient administrative permissions to access this page.', 'ifsedu-school-management' ) );
    }

    global $wpdb;

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $student_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

    if ( ! $student_id ) {
        echo '<div style="background:#fef2f2; border:1px solid #fecaca; color:#dc2626; padding:16px; border-radius:12px; margin:20px 20px 20px 0; font-weight:700;">' . esc_html__( 'Invalid student ID provided.', 'ifsedu-school-management' ) . '</div>';
        return;
    }

    $table_students   = $wpdb->prefix . 'sms_students';
    $results_table    = $wpdb->prefix . 'sms_results';
    $exams_table      = $wpdb->prefix . 'sms_exams';
    $fees_table       = $wpdb->prefix . 'sms_fees';
    $attendance_table = $wpdb->prefix . 'sms_attendance';
    $staff_table      = $wpdb->prefix . 'sms_staff';

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $student = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table_students}` WHERE id = %d LIMIT 1", $student_id ) );

    if ( ! $student ) {
        echo '<div style="background:#fef2f2; border:1px solid #fecaca; color:#dc2626; padding:16px; border-radius:12px; margin:20px 20px 20px 0; font-weight:700;">' . esc_html__( 'Student record not found in system database.', 'ifsedu-school-management' ) . '</div>';
        return;
    }

    // Query Referred Staff Member for Financial Waiver if assigned
    $referred_staff_name = '—';
    if ( ! empty( $student->waiver_staff_id ) && $student->waiver_staff_id > 0 ) {
        $staff_row = $wpdb->get_row( $wpdb->prepare( "SELECT full_name, designation, staff_type FROM `{$staff_table}` WHERE id = %d LIMIT 1", $student->waiver_staff_id ) );
        if ( $staff_row ) {
            $referred_staff_name = $staff_row->full_name . ' (' . $staff_row->designation . ' - ' . $staff_row->staff_type . ')';
        }
    }

    // Query exam results, fee ledgers, and attendance records with optimized column selection
    $exam_results = $wpdb->get_results( $wpdb->prepare(
        "SELECT r.id, r.exam_id, r.subject_name, r.total_marks, r.obtained_marks, r.grade, r.gpa, e.exam_name 
         FROM `{$results_table}` r 
         LEFT JOIN `{$exams_table}` e ON r.exam_id = e.id 
         WHERE r.student_id = %d 
         ORDER BY r.id DESC",
        $student->id
    ) );

    $fee_ledgers = $wpdb->get_results( $wpdb->prepare(
        "SELECT invoice_id, fee_month, fee_year, fee_type, net_payable, paid_amount, due_amount, payment_status 
         FROM `{$fees_table}` 
         WHERE student_id = %d 
         ORDER BY id DESC",
        $student->id
    ) );

    $attendance_logs = $wpdb->get_results( $wpdb->prepare(
        "SELECT attendance_date, status 
         FROM `{$attendance_table}` 
         WHERE student_id = %d 
         ORDER BY attendance_date DESC 
         LIMIT 30",
        $student->id
    ) );
    // phpcs:enable

    // Financial Summary
    $total_paid = 0.00;
    $total_due  = 0.00;
    if ( ! empty( $fee_ledgers ) ) {
        foreach ( $fee_ledgers as $ledger ) {
            $total_paid += floatval( $ledger->paid_amount );
            $total_due  += floatval( $ledger->due_amount );
        }
    }

    // Attendance Calculations
    $total_present = 0;
    $total_absent  = 0;
    $total_late    = 0;

    if ( ! empty( $attendance_logs ) ) {
        foreach ( $attendance_logs as $att ) {
            $st = strtolower( trim( (string) $att->status ) );
            if ( 'present' === $st ) {
                $total_present++;
            } elseif ( 'absent' === $st ) {
                $total_absent++;
            } elseif ( 'late' === $st ) {
                $total_late++;
            }
        }
    }

    $total_days       = $total_present + $total_absent + $total_late;
    $attendance_ratio = $total_days > 0 ? round( ( $total_present / $total_days ) * 100, 1 ) : 0;

    $back_url = admin_url( 'admin.php?page=school_management_system&tab=students&sub=list' );
    $edit_url = add_query_arg(
        array(
            'page' => 'school_management_system',
            'tab'  => 'students',
            'sub'  => 'edit',
            'id'   => absint( $student->id ),
        ),
        admin_url( 'admin.php' )
    );

    $name_for_letter = ! empty( $student->full_name ) ? $student->full_name : 'S';
    $first_letter    = function_exists( 'mb_substr' ) ? mb_substr( $name_for_letter, 0, 1, 'utf-8' ) : substr( $name_for_letter, 0, 1 );
    ?>

    <div class="ifs-educore-profile-wrapper">
        <!-- Action Bar -->
        <div class="ifs-educore-action-bar no-print">
            <a href="<?php echo esc_url( $back_url ); ?>" class="ifs-educore-btn ifs-educore-btn-secondary">
                <span class="dashicons dashicons-arrow-left-alt"></span>
                <?php esc_html_e( 'Back to Directory', 'ifsedu-school-management' ); ?>
            </a>
            <div style="display:flex; gap:10px;">
                <button type="button" id="ifs_educore_print_profile_btn" class="ifs-educore-btn ifs-educore-btn-secondary">
                    <span class="dashicons dashicons-printer"></span>
                    <?php esc_html_e( 'Print Profile', 'ifsedu-school-management' ); ?>
                </button>
                <a href="<?php echo esc_url( $edit_url ); ?>" class="ifs-educore-btn ifs-educore-btn-primary">
                    <span class="dashicons dashicons-edit"></span>
                    <?php esc_html_e( 'Edit Profile', 'ifsedu-school-management' ); ?>
                </a>
            </div>
        </div>

        <!-- Banner Card -->
        <div class="ifs-educore-profile-header-card">
            <div class="ifs-educore-hero-flex">
                <div>
                    <?php if ( ! empty( $student->photo_url ) ) : ?>
                        <img src="<?php echo esc_url( $student->photo_url ); ?>" alt="<?php echo esc_attr( $student->full_name ); ?>" class="ifs-educore-avatar-img">
                    <?php else : ?>
                        <div class="ifs-educore-avatar-placeholder">
                            <?php echo esc_html( function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $first_letter, 'utf-8' ) : strtoupper( $first_letter ) ); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div>
                    <div style="display:flex; align-items:center; gap:12px; margin-bottom:10px;">
                        <h2 style="margin:0; font-size:26px; font-weight:800; color:#ffffff;"><?php echo esc_html( $student->full_name ); ?></h2>
                        <span style="background:#ffffff; color:#0f172a; padding:3px 12px; border-radius:20px; font-size:12px; font-weight:800;">
                            <?php echo esc_html( ucfirst( $student->status ) ); ?>
                        </span>
                    </div>

                    <div style="display:flex; gap:8px; flex-wrap:wrap;">
                        <div class="ifs-educore-glass-id-badge"><strong><?php esc_html_e( 'ID:', 'ifsedu-school-management' ); ?></strong> <?php echo esc_html( strtoupper( $student->student_id ) ); ?></div>
                        <div class="ifs-educore-glass-id-badge"><strong><?php esc_html_e( 'Class:', 'ifsedu-school-management' ); ?></strong> <?php echo esc_html( $student->class_name ); ?></div>
                        <div class="ifs-educore-glass-id-badge"><strong><?php esc_html_e( 'Roll:', 'ifsedu-school-management' ); ?></strong> #<?php echo esc_html( $student->roll_no ); ?></div>
                        <?php if ( ! empty( $student->section_name ) ) : ?>
                            <div class="ifs-educore-glass-id-badge"><strong><?php esc_html_e( 'Section:', 'ifsedu-school-management' ); ?></strong> <?php echo esc_html( $student->section_name ); ?></div>
                        <?php endif; ?>
                        <?php if ( ! empty( $student->shift ) && 'No Shift' !== $student->shift ) : ?>
                            <div class="ifs-educore-glass-id-badge"><strong><?php esc_html_e( 'Shift:', 'ifsedu-school-management' ); ?></strong> <?php echo esc_html( $student->shift ); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Micro Stats -->
        <div class="ifs-educore-bento-grid">
            <div class="ifs-educore-bento-card">
                <div class="ifs-educore-bento-icon" style="background:#eff6ff; color:#2563eb;"><span class="dashicons dashicons-clipboard"></span></div>
                <div>
                    <div style="font-size:11.5px; color:#64748b; font-weight:800; text-transform:uppercase;"><?php esc_html_e( 'Exams Evaluated', 'ifsedu-school-management' ); ?></div>
                    <div style="font-size:22px; font-weight:800; color:#0f172a;"><?php 
                        $unique_exams = ! empty( $exam_results ) ? array_unique( array_column( $exam_results, 'exam_id' ) ) : array();
                        echo esc_html( count( $unique_exams ) ); 
                    ?></div>
                </div>
            </div>

            <div class="ifs-educore-bento-card">
                <div class="ifs-educore-bento-icon" style="background:#ecfdf5; color:#059669;"><span class="dashicons dashicons-yes-alt"></span></div>
                <div>
                    <div style="font-size:11.5px; color:#64748b; font-weight:800; text-transform:uppercase;"><?php esc_html_e( 'Attendance Ratio', 'ifsedu-school-management' ); ?></div>
                    <div style="font-size:22px; font-weight:800; color:#059669;"><?php echo esc_html( $attendance_ratio ); ?>%</div>
                </div>
            </div>

            <div class="ifs-educore-bento-card">
                <div class="ifs-educore-bento-icon" style="background:#f0fdf4; color:#00523c;"><span class="dashicons dashicons-money-alt"></span></div>
                <div>
                    <div style="font-size:11.5px; color:#64748b; font-weight:800; text-transform:uppercase;"><?php esc_html_e( 'Total Fees Paid', 'ifsedu-school-management' ); ?></div>
                    <div style="font-size:22px; font-weight:800; color:#00523c;">৳<?php echo esc_html( number_format( $total_paid, 2 ) ); ?></div>
                </div>
            </div>

            <div class="ifs-educore-bento-card">
                <div class="ifs-educore-bento-icon" style="background:#fef2f2; color:#dc2626;"><span class="dashicons dashicons-warning"></span></div>
                <div>
                    <div style="font-size:11.5px; color:#64748b; font-weight:800; text-transform:uppercase;"><?php esc_html_e( 'Total Due Balance', 'ifsedu-school-management' ); ?></div>
                    <div style="font-size:22px; font-weight:800; color:#dc2626;">৳<?php echo esc_html( number_format( $total_due, 2 ) ); ?></div>
                </div>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="ifs-educore-profile-tabs no-print">
            <button type="button" class="nav-link active" data-target="ifs_educore_details_tab">
                <span class="dashicons dashicons-admin-users"></span>
                <?php esc_html_e( 'Personal & Academic Info', 'ifsedu-school-management' ); ?>
            </button>
            <button type="button" class="nav-link" data-target="ifs_educore_parents_tab">
                <span class="dashicons dashicons-groups"></span>
                <?php esc_html_e( 'Parents & Guardian', 'ifsedu-school-management' ); ?>
            </button>
            <button type="button" class="nav-link" data-target="ifs_educore_waiver_tab">
                <span class="dashicons dashicons-tag"></span>
                <?php esc_html_e( 'Financial Waiver', 'ifsedu-school-management' ); ?>
            </button>
            <button type="button" class="nav-link" data-target="ifs_educore_history_tab">
                <span class="dashicons dashicons-welcome-learn-more"></span>
                <?php esc_html_e( 'Previous History & Misc', 'ifsedu-school-management' ); ?>
            </button>
            <button type="button" class="nav-link" data-target="ifs_educore_results_tab">
                <span class="dashicons dashicons-awards"></span>
                <?php esc_html_e( 'Academic Results', 'ifsedu-school-management' ); ?>
            </button>
            <button type="button" class="nav-link" data-target="ifs_educore_payments_tab">
                <span class="dashicons dashicons-tickets-alt"></span>
                <?php esc_html_e( 'Fee History', 'ifsedu-school-management' ); ?>
            </button>
            <button type="button" class="nav-link" data-target="ifs_educore_attendance_tab">
                <span class="dashicons dashicons-calendar-alt"></span>
                <?php esc_html_e( 'Attendance Logs', 'ifsedu-school-management' ); ?>
            </button>
        </div>

        <!-- Tab Workspace -->
        <div class="ifs-educore-tab-workspace">
            
            <!-- 1. Personal & Academic Info Tab -->
            <div id="ifs_educore_details_tab" class="ifs-educore-tab-content-block">
                <div class="ifs-educore-grid-2col">
                    <div>
                        <div class="ifs-educore-section-title"><span class="dashicons dashicons-welcome-learn-more"></span> <?php esc_html_e( 'Academic Information', 'ifsedu-school-management' ); ?></div>
                        <table class="ifs-educore-profile-table">
                            <tr><td class="ifs-educore-label-bg"><?php esc_html_e( 'Student Unique ID', 'ifsedu-school-management' ); ?></td><td style="font-weight:700; color:#0f172a;"><?php echo esc_html( strtoupper( $student->student_id ) ); ?></td></tr>
                            <tr><td class="ifs-educore-label-bg"><?php esc_html_e( 'Academic Class', 'ifsedu-school-management' ); ?></td><td style="font-weight:700; color:#00523c;"><?php echo esc_html( $student->class_name ); ?></td></tr>
                            <tr><td class="ifs-educore-label-bg"><?php esc_html_e( 'Section / Group', 'ifsedu-school-management' ); ?></td><td><?php echo ! empty( $student->section_name ) ? esc_html( $student->section_name ) : '—'; ?></td></tr>
                            <tr><td class="ifs-educore-label-bg"><?php esc_html_e( 'Academic Shift', 'ifsedu-school-management' ); ?></td><td><span style="font-weight:700; color:#00523c;"><?php echo ! empty( $student->shift ) ? esc_html( $student->shift ) : esc_html__( 'No Shift', 'ifsedu-school-management' ); ?></span></td></tr>
                            <tr><td class="ifs-educore-label-bg"><?php esc_html_e( 'Roll Number', 'ifsedu-school-management' ); ?></td><td style="font-weight:700;">#<?php echo esc_html( $student->roll_no ); ?></td></tr>
                            <tr>
                                <td class="ifs-educore-label-bg"><?php esc_html_e( 'Admission Date', 'ifsedu-school-management' ); ?></td>
                                <td>
                                    <?php 
                                    $adm_time = ( ! empty( $student->admission_date ) && '0000-00-00' !== $student->admission_date && '1970-01-01' !== $student->admission_date ) ? strtotime( $student->admission_date ) : false;
                                    echo esc_html( $adm_time ? date_i18n( 'd M Y', $adm_time ) : '—' ); 
                                    ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="ifs-educore-label-bg"><?php esc_html_e( 'Fee Start Date', 'ifsedu-school-management' ); ?></td>
                                <td style="font-weight:700; color:#2563eb;">
                                    <?php 
                                    $fee_start_time = ( ! empty( $student->fee_start_date ) && '0000-00-00' !== $student->fee_start_date ) ? strtotime( $student->fee_start_date ) : false;
                                    echo esc_html( $fee_start_time ? date_i18n( 'd M Y', $fee_start_time ) : esc_html__( 'From Admission', 'ifsedu-school-management' ) ); 
                                    ?>
                                </td>
                            </tr>
                            <tr><td class="ifs-educore-label-bg"><?php esc_html_e( 'Residential Status', 'ifsedu-school-management' ); ?></td><td><?php echo ! empty( $student->residential_status ) ? esc_html( $student->residential_status ) : esc_html__( 'Non-Residential', 'ifsedu-school-management' ); ?></td></tr>
                            <tr><td class="ifs-educore-label-bg"><?php esc_html_e( 'Status', 'ifsedu-school-management' ); ?></td><td><span style="font-weight:700; color:<?php echo ( 'active' === strtolower( (string) $student->status ) ) ? '#059669' : '#dc2626'; ?>;"><?php echo esc_html( ucfirst( $student->status ) ); ?></span></td></tr>
                        </table>
                    </div>

                    <div>
                        <div class="ifs-educore-section-title"><span class="dashicons dashicons-id-alt"></span> <?php esc_html_e( 'Basic Identity Profile', 'ifsedu-school-management' ); ?></div>
                        <table class="ifs-educore-profile-table">
                            <tr><td class="ifs-educore-label-bg"><?php esc_html_e( 'Full Name (English)', 'ifsedu-school-management' ); ?></td><td style="font-weight:700;"><?php echo esc_html( $student->full_name ); ?></td></tr>
                            <tr><td class="ifs-educore-label-bg"><?php esc_html_e( 'Birth Reg. Number', 'ifsedu-school-management' ); ?></td><td><?php echo ! empty( $student->birth_reg_no ) ? esc_html( $student->birth_reg_no ) : '—'; ?></td></tr>
                            <tr>
                                <td class="ifs-educore-label-bg"><?php esc_html_e( 'Date of Birth', 'ifsedu-school-management' ); ?></td>
                                <td>
                                    <?php 
                                    $dob_time = ( ! empty( $student->dob ) && '0000-00-00' !== $student->dob && '1970-01-01' !== $student->dob ) ? strtotime( $student->dob ) : false;
                                    echo esc_html( $dob_time ? date_i18n( 'd M Y', $dob_time ) : '—' ); 
                                    ?>
                                </td>
                            </tr>
                            <tr><td class="ifs-educore-label-bg"><?php esc_html_e( 'Birth District', 'ifsedu-school-management' ); ?></td><td><?php echo ! empty( $student->birth_place ) ? esc_html( $student->birth_place ) : '—'; ?></td></tr>
                            <tr><td class="ifs-educore-label-bg"><?php esc_html_e( 'Gender', 'ifsedu-school-management' ); ?></td><td><?php echo esc_html( ucfirst( $student->gender ) ); ?></td></tr>
                            <tr><td class="ifs-educore-label-bg"><?php esc_html_e( 'Blood Group', 'ifsedu-school-management' ); ?></td><td><strong style="color:#dc2626;"><?php echo ! empty( $student->blood_group ) ? esc_html( $student->blood_group ) : '—'; ?></strong></td></tr>
                            <tr><td class="ifs-educore-label-bg"><?php esc_html_e( 'Religion', 'ifsedu-school-management' ); ?></td><td><?php echo esc_html( $student->religion ); ?></td></tr>
                            <tr><td class="ifs-educore-label-bg"><?php esc_html_e( 'Nationality', 'ifsedu-school-management' ); ?></td><td><?php echo esc_html( $student->nationality ); ?></td></tr>
                            <tr><td class="ifs-educore-label-bg"><?php esc_html_e( 'Quota Category', 'ifsedu-school-management' ); ?></td><td><?php echo esc_html( $student->quota ); ?></td></tr>
                            <tr><td class="ifs-educore-label-bg"><?php esc_html_e( 'Student Mobile', 'ifsedu-school-management' ); ?></td><td><?php echo ! empty( $student->student_phone ) ? esc_html( $student->student_phone ) : '—'; ?></td></tr>
                            <tr><td class="ifs-educore-label-bg"><?php esc_html_e( 'Student Email', 'ifsedu-school-management' ); ?></td><td><?php echo ! empty( $student->student_email ) ? esc_html( $student->student_email ) : '—'; ?></td></tr>
                        </table>
                    </div>
                </div>

                <div class="ifs-educore-grid-2col" style="margin-bottom: 0;">
                    <div>
                        <div class="ifs-educore-section-title"><span class="dashicons dashicons-location"></span> <?php esc_html_e( 'Present Address', 'ifsedu-school-management' ); ?></div>
                        <div style="padding:16px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; color:#334155; font-size:13.5px; line-height:1.6; min-height:60px;">
                            <?php echo ! empty( $student->address ) ? nl2br( esc_html( $student->address ) ) : esc_html__( 'No registered present address found.', 'ifsedu-school-management' ); ?>
                        </div>
                    </div>
                    <div>
                        <div class="ifs-educore-section-title"><span class="dashicons dashicons-admin-home"></span> <?php esc_html_e( 'Permanent Address', 'ifsedu-school-management' ); ?></div>
                        <div style="padding:16px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; color:#334155; font-size:13.5px; line-height:1.6; min-height:60px;">
                            <?php echo ! empty( $student->permanent_address ) ? nl2br( esc_html( $student->permanent_address ) ) : esc_html__( 'Same as present address or not provided.', 'ifsedu-school-management' ); ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. Parents & Guardian Tab -->
            <div id="ifs_educore_parents_tab" class="ifs-educore-tab-content-block" style="display:none;">
                <div class="ifs-educore-grid-2col">
                    <div>
                        <div class="ifs-educore-section-title"><span class="dashicons dashicons-businessman"></span> <?php esc_html_e( 'Father Information', 'ifsedu-school-management' ); ?></div>
                        <table class="ifs-educore-profile-table">
                            <tr><td class="ifs-educore-label-bg"><?php esc_html_e( 'Father Name (English)', 'ifsedu-school-management' ); ?></td><td style="font-weight:700;"><?php echo ! empty( $student->father_name ) ? esc_html( $student->father_name ) : '—'; ?></td></tr>
                            <tr><td class="ifs-educore-label-bg"><?php esc_html_e( 'Father NID', 'ifsedu-school-management' ); ?></td><td><?php echo ! empty( $student->father_nid ) ? esc_html( $student->father_nid ) : '—'; ?></td></tr>
                            <tr><td class="ifs-educore-label-bg"><?php esc_html_e( 'Father Phone', 'ifsedu-school-management' ); ?></td><td><?php echo ! empty( $student->father_phone ) ? esc_html( $student->father_phone ) : '—'; ?></td></tr>
                            <tr><td class="ifs-educore-label-bg"><?php esc_html_e( 'Father Profession', 'ifsedu-school-management' ); ?></td><td><?php echo ! empty( $student->father_profession ) ? esc_html( $student->father_profession ) : '—'; ?></td></tr>
                        </table>
                    </div>

                    <div>
                        <div class="ifs-educore-section-title"><span class="dashicons dashicons-businesswoman"></span> <?php esc_html_e( 'Mother Information', 'ifsedu-school-management' ); ?></div>
                        <table class="ifs-educore-profile-table">
                            <tr><td class="ifs-educore-label-bg"><?php esc_html_e( 'Mother Name (English)', 'ifsedu-school-management' ); ?></td><td style="font-weight:700;"><?php echo ! empty( $student->mother_name ) ? esc_html( $student->mother_name ) : '—'; ?></td></tr>
                            <tr><td class="ifs-educore-label-bg"><?php esc_html_e( 'Mother NID', 'ifsedu-school-management' ); ?></td><td><?php echo ! empty( $student->mother_nid ) ? esc_html( $student->mother_nid ) : '—'; ?></td></tr>
                            <tr><td class="ifs-educore-label-bg"><?php esc_html_e( 'Mother Phone', 'ifsedu-school-management' ); ?></td><td><?php echo ! empty( $student->mother_phone ) ? esc_html( $student->mother_phone ) : '—'; ?></td></tr>
                            <tr><td class="ifs-educore-label-bg"><?php esc_html_e( 'Mother Profession', 'ifsedu-school-management' ); ?></td><td><?php echo ! empty( $student->mother_profession ) ? esc_html( $student->mother_profession ) : '—'; ?></td></tr>
                        </table>
                    </div>
                </div>

                <div style="margin-top: 10px;">
                    <div class="ifs-educore-section-title"><span class="dashicons dashicons-shield"></span> <?php esc_html_e( 'Legal Guardian Details (SMS Notifications Contact)', 'ifsedu-school-management' ); ?></div>
                    <table class="ifs-educore-profile-table">
                        <tr><td class="ifs-educore-label-bg"><?php esc_html_e( 'Guardian Name', 'ifsedu-school-management' ); ?></td><td style="font-weight:700; color:#0f172a;"><?php echo ! empty( $student->guardian_name ) ? esc_html( $student->guardian_name ) : esc_html( $student->father_name ); ?></td></tr>
                        <tr><td class="ifs-educore-label-bg"><?php esc_html_e( 'Guardian Phone (SMS)', 'ifsedu-school-management' ); ?></td><td style="font-weight:700; color:#00523c;"><?php echo ! empty( $student->guardian_phone ) ? esc_html( $student->guardian_phone ) : '—'; ?></td></tr>
                        <tr><td class="ifs-educore-label-bg"><?php esc_html_e( 'Relation with Student', 'ifsedu-school-management' ); ?></td><td><?php echo ! empty( $student->guardian_relation ) ? esc_html( $student->guardian_relation ) : esc_html__( 'Father', 'ifsedu-school-management' ); ?></td></tr>
                        <tr><td class="ifs-educore-label-bg"><?php esc_html_e( 'Guardian NID / Annual Income', 'ifsedu-school-management' ); ?></td><td><?php echo ! empty( $student->guardian_nid ) ? esc_html( $student->guardian_nid ) : ( ! empty( $student->guardian_income ) ? esc_html( $student->guardian_income ) : '—' ); ?></td></tr>
                    </table>
                </div>
            </div>

            <!-- 3. Financial Waiver Tab -->
            <div id="ifs_educore_waiver_tab" class="ifs-educore-tab-content-block" style="display:none;">
                <div class="ifs-educore-section-title"><span class="dashicons dashicons-tag"></span> <?php esc_html_e( 'Financial Waiver & Faculty Reference Details', 'ifsedu-school-management' ); ?></div>
                
                <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:12px; padding:24px; margin-bottom:20px;">
                    <table class="ifs-educore-profile-table">
                        <tr>
                            <td class="ifs-educore-label-bg"><?php esc_html_e( 'All-Time Waiver / Discount', 'ifsedu-school-management' ); ?></td>
                            <td>
                                <span style="font-size:22px; font-weight:800; color:#00523c;">
                                    <?php echo esc_html( number_format( (float) ( $student->waiver_percentage ?? 0 ), 2 ) ); ?>%
                                </span>
                                <small style="color:#166534; font-weight:600; display:block; margin-top:2px;">
                                    <?php esc_html_e( 'Automatically applied to all monthly tuition fee invoices.', 'ifsedu-school-management' ); ?>
                                </small>
                            </td>
                        </tr>
                        <tr>
                            <td class="ifs-educore-label-bg"><?php esc_html_e( 'Referred Teacher / Staff', 'ifsedu-school-management' ); ?></td>
                            <td style="font-weight:700; color:#0f172a; font-size:14px;">
                                <?php echo esc_html( $referred_staff_name ); ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="ifs-educore-label-bg"><?php esc_html_e( 'Fee Effective Start Date', 'ifsedu-school-management' ); ?></td>
                            <td style="font-weight:700; color:#2563eb;">
                                <?php 
                                $fee_date_time = ( ! empty( $student->fee_start_date ) && '0000-00-00' !== $student->fee_start_date ) ? strtotime( $student->fee_start_date ) : false;
                                echo esc_html( $fee_date_time ? date_i18n( 'd F, Y', $fee_date_time ) : esc_html__( 'From Admission Date', 'ifsedu-school-management' ) ); 
                                ?>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- 4. Previous History & Misc Tab -->
            <div id="ifs_educore_history_tab" class="ifs-educore-tab-content-block" style="display:none;">
                <div class="ifs-educore-grid-2col">
                    <div>
                        <div class="ifs-educore-section-title"><span class="dashicons dashicons-building"></span> <?php esc_html_e( 'Previous Academic Background', 'ifsedu-school-management' ); ?></div>
                        <table class="ifs-educore-profile-table">
                            <tr><td class="ifs-educore-label-bg"><?php esc_html_e( 'Previous School Name', 'ifsedu-school-management' ); ?></td><td style="font-weight:700;"><?php echo ! empty( $student->prev_school_name ) ? esc_html( $student->prev_school_name ) : '—'; ?></td></tr>
                            <tr><td class="ifs-educore-label-bg"><?php esc_html_e( 'Previous Institute EIIN', 'ifsedu-school-management' ); ?></td><td><?php echo ! empty( $student->prev_eiin ) ? esc_html( $student->prev_eiin ) : '—'; ?></td></tr>
                            <tr><td class="ifs-educore-label-bg"><?php esc_html_e( 'Last Passed Class', 'ifsedu-school-management' ); ?></td><td><?php echo ! empty( $student->prev_class ) ? esc_html( $student->prev_class ) : '—'; ?></td></tr>
                            <tr><td class="ifs-educore-label-bg"><?php esc_html_e( 'Obtained GPA / Marks', 'ifsedu-school-management' ); ?></td><td style="font-weight:700; color:#00523c;"><?php echo ! empty( $student->prev_gpa ) ? esc_html( $student->prev_gpa ) : '—'; ?></td></tr>
                        </table>
                    </div>

                    <div>
                        <div class="ifs-educore-section-title"><span class="dashicons dashicons-art"></span> <?php esc_html_e( 'Co-Curricular & Extracurricular Activities', 'ifsedu-school-management' ); ?></div>
                        <div style="padding:20px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px;">
                            <?php if ( ! empty( $student->co_curricular ) ) : 
                                $activities = explode( ',', (string) $student->co_curricular );
                            ?>
                                <div style="display:flex; flex-wrap:wrap; gap:8px;">
                                    <?php foreach ( $activities as $act ) : ?>
                                        <span style="background:#e0f2fe; color:#0369a1; border:1px solid #bae6fd; font-weight:700; padding:6px 14px; border-radius:20px; font-size:12.5px;">
                                            <span class="dashicons dashicons-yes" style="font-size:14px; width:14px; height:14px; vertical-align:middle;"></span> <?php echo esc_html( trim( $act ) ); ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php else : ?>
                                <p style="color:#94a3b8; margin:0;"><?php esc_html_e( 'No co-curricular activities selected.', 'ifsedu-school-management' ); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 5. Results Tab -->
            <div id="ifs_educore_results_tab" class="ifs-educore-tab-content-block" style="display:none;">
                <div class="ifs-educore-section-title"><?php esc_html_e( 'Academic Marks Matrix', 'ifsedu-school-management' ); ?></div>
                
                <?php 
                $grouped_results = array();
                if ( ! empty( $exam_results ) ) {
                    foreach ( $exam_results as $res ) {
                        $grouped_results[ $res->exam_name ][] = $res;
                    }
                }

                if ( ! empty( $grouped_results ) ) :
                    foreach ( $grouped_results as $exam_title => $results ) :
                        $total_obtained = 0;
                        $total_max      = 0;
                        $sum_gpa        = 0;
                        $has_failed     = false;
                        
                        foreach ( $results as $r ) {
                            $total_obtained += floatval( $r->obtained_marks );
                            $total_max      += floatval( $r->total_marks );
                            $sum_gpa        += floatval( $r->gpa );
                            if ( 'F' === strtoupper( trim( (string) $r->grade ) ) ) {
                                $has_failed = true;
                            }
                        }
                        
                        $sub_count    = count( $results );
                        $avg_gpa      = $sub_count > 0 ? ( $sum_gpa / $sub_count ) : 0;
                        $final_gpa    = $has_failed ? '0.00' : number_format( $avg_gpa, 2 );
                        $pass_status  = $has_failed ? esc_html__( 'Failed', 'ifsedu-school-management' ) : esc_html__( 'Passed', 'ifsedu-school-management' );
                        $status_color = $has_failed ? '#dc2626' : '#059669';
                ?>
                    <div class="ifs-educore-exam-card">
                        <div class="ifs-educore-exam-header">
                            <div class="ifs-educore-exam-title">
                                <span class="dashicons dashicons-awards" style="color: #00523c;"></span>
                                <?php echo esc_html( $exam_title ); ?>
                            </div>
                            <span style="font-size: 12px; font-weight: 700; color: #64748b; background: #e2e8f0; padding: 4px 12px; border-radius: 20px;">
                                <?php echo esc_html( $sub_count ); ?> <?php esc_html_e( 'Subjects Evaluated', 'ifsedu-school-management' ); ?>
                            </span>
                        </div>
                        
                        <div style="overflow-x:auto;">
                            <table class="ifs-educore-data-responsive-table" style="border: none; border-radius: 0;">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e( 'Subject Title', 'ifsedu-school-management' ); ?></th>
                                        <th><?php esc_html_e( 'Total Marks', 'ifsedu-school-management' ); ?></th>
                                        <th><?php esc_html_e( 'Obtained Marks', 'ifsedu-school-management' ); ?></th>
                                        <th><?php esc_html_e( 'Grade', 'ifsedu-school-management' ); ?></th>
                                        <th><?php esc_html_e( 'GPA', 'ifsedu-school-management' ); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ( $results as $res ) : 
                                        $is_f = 'F' === strtoupper( trim( (string) $res->grade ) );
                                    ?>
                                        <tr>
                                            <td><strong style="color:#0f172a;"><?php echo esc_html( $res->subject_name ); ?></strong></td>
                                            <td><?php echo esc_html( floatval( $res->total_marks ) ); ?></td>
                                            <td><strong style="color:<?php echo esc_attr( $is_f ? '#dc2626' : '#0f172a' ); ?>;"><?php echo esc_html( floatval( $res->obtained_marks ) ); ?></strong></td>
                                            <td><span style="background:<?php echo esc_attr( $is_f ? '#fef2f2' : '#f1f5f9' ); ?>; color:<?php echo esc_attr( $is_f ? '#dc2626' : 'inherit' ); ?>; padding:3px 8px; border-radius:4px; font-weight:700; border:1px solid <?php echo esc_attr( $is_f ? '#fecaca' : '#cbd5e1' ); ?>;"><?php echo esc_html( $res->grade ); ?></span></td>
                                            <td><strong style="color:<?php echo esc_attr( $is_f ? '#dc2626' : '#2563eb' ); ?>;"><?php echo esc_html( number_format( floatval( $res->gpa ), 2 ) ); ?></strong></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="ifs-educore-exam-summary">
                            <div class="ifs-educore-summary-item">
                                <span class="ifs-educore-summary-label"><?php esc_html_e( 'Total Marks', 'ifsedu-school-management' ); ?></span>
                                <span class="ifs-educore-summary-value" style="color: #0f172a;"><?php echo esc_html( $total_obtained ); ?> / <?php echo esc_html( $total_max ); ?></span>
                            </div>
                            <div class="ifs-educore-summary-item">
                                <span class="ifs-educore-summary-label"><?php esc_html_e( 'Final GPA', 'ifsedu-school-management' ); ?></span>
                                <span class="ifs-educore-summary-value"><?php echo esc_html( $final_gpa ); ?></span>
                            </div>
                            <div class="ifs-educore-summary-item">
                                <span class="ifs-educore-summary-label"><?php esc_html_e( 'Exam Status', 'ifsedu-school-management' ); ?></span>
                                <span class="ifs-educore-summary-value" style="color: <?php echo esc_attr( $status_color ); ?>;"><?php echo esc_html( $pass_status ); ?></span>
                            </div>
                        </div>
                    </div>
                <?php 
                    endforeach; 
                else : 
                ?>
                    <div style="text-align:center; color:#94a3b8; padding:40px; background:#f8fafc; border-radius:12px; border:1px dashed #cbd5e1;">
                        <span class="dashicons dashicons-welcome-learn-more" style="font-size: 32px; width:32px; height:32px; opacity:0.5; margin-bottom:12px;"></span><br>
                        <?php esc_html_e( 'No examination records evaluated for this student yet.', 'ifsedu-school-management' ); ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- 6. Payments Tab -->
            <div id="ifs_educore_payments_tab" class="ifs-educore-tab-content-block" style="display:none;">
                <div class="ifs-educore-section-title"><?php esc_html_e( 'Fee Payment History & Invoices', 'ifsedu-school-management' ); ?></div>
                <div style="overflow-x:auto;">
                    <table class="ifs-educore-data-responsive-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Invoice ID', 'ifsedu-school-management' ); ?></th>
                                <th><?php esc_html_e( 'Period', 'ifsedu-school-management' ); ?></th>
                                <th><?php esc_html_e( 'Fee Type', 'ifsedu-school-management' ); ?></th>
                                <th><?php esc_html_e( 'Net Payable', 'ifsedu-school-management' ); ?></th>
                                <th><?php esc_html_e( 'Paid Amount', 'ifsedu-school-management' ); ?></th>
                                <th><?php esc_html_e( 'Due Balance', 'ifsedu-school-management' ); ?></th>
                                <th><?php esc_html_e( 'Status', 'ifsedu-school-management' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ( ! empty( $fee_ledgers ) ) : foreach ( $fee_ledgers as $fee ) : 
                                $pay_status   = strtolower( trim( (string) $fee->payment_status ) );
                                $status_class = ( 'paid' === $pay_status ) ? 'ifs-educore-status-paid' : ( ( 'partial' === $pay_status ) ? 'ifs-educore-status-partial' : 'ifs-educore-status-unpaid' );
                            ?>
                                <tr>
                                    <td><code style="background:#f1f5f9; padding:2px 6px; border-radius:4px; border:1px solid #cbd5e1; font-weight:700; color:#0f172a;"><?php echo esc_html( $fee->invoice_id ); ?></code></td>
                                    <td><?php echo esc_html( $fee->fee_month . ' / ' . $fee->fee_year ); ?></td>
                                    <td><?php echo esc_html( $fee->fee_type ); ?></td>
                                    <td>৳<?php echo esc_html( number_format( (float) $fee->net_payable, 2 ) ); ?></td>
                                    <td style="color:#00523c; font-weight:800;">৳<?php echo esc_html( number_format( (float) $fee->paid_amount, 2 ) ); ?></td>
                                    <td style="color:#dc2626; font-weight:700;">৳<?php echo esc_html( number_format( (float) $fee->due_amount, 2 ) ); ?></td>
                                    <td><span class="ifs-educore-badge-status <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( ucfirst( $fee->payment_status ) ); ?></span></td>
                                </tr>
                            <?php endforeach; else : ?>
                                <tr><td colspan="7" style="text-align:center; color:#94a3b8; padding:30px;"><?php esc_html_e( 'No fee collection logs found.', 'ifsedu-school-management' ); ?></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 7. Attendance Tab -->
            <div id="ifs_educore_attendance_tab" class="ifs-educore-tab-content-block" style="display:none;">
                <div class="ifs-educore-section-title"><?php esc_html_e( 'Daily Attendance Audit Logs (Recent 30 Days)', 'ifsedu-school-management' ); ?></div>
                <div style="overflow-x:auto;">
                    <table class="ifs-educore-data-responsive-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Date', 'ifsedu-school-management' ); ?></th>
                                <th><?php esc_html_e( 'Day', 'ifsedu-school-management' ); ?></th>
                                <th><?php esc_html_e( 'Status', 'ifsedu-school-management' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ( ! empty( $attendance_logs ) ) : foreach ( $attendance_logs as $att ) : 
                                $status_lower = strtolower( trim( (string) $att->status ) );
                                $badge_color  = ( 'present' === $status_lower ) ? '#059669' : ( ( 'late' === $status_lower ) ? '#d97706' : '#dc2626' );
                                $att_time     = ! empty( $att->attendance_date ) ? strtotime( $att->attendance_date ) : false;
                            ?>
                                <tr>
                                    <td><strong><?php echo esc_html( $att_time ? date_i18n( 'd F, Y', $att_time ) : '—' ); ?></strong></td>
                                    <td><?php echo esc_html( $att_time ? date_i18n( 'l', $att_time ) : '—' ); ?></td>
                                    <td><strong style="color:<?php echo esc_attr( $badge_color ); ?>;"><?php echo esc_html( ucfirst( $att->status ) ); ?></strong></td>
                                </tr>
                            <?php endforeach; else : ?>
                                <tr><td colspan="3" style="text-align:center; color:#94a3b8; padding:30px;"><?php esc_html_e( 'No daily attendance records logged.', 'ifsedu-school-management' ); ?></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>

    <script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function() {
        const printBtn = document.getElementById('ifs_educore_print_profile_btn');
        if (printBtn) {
            printBtn.addEventListener('click', function() {
                window.print();
            });
        }

        const tabButtons = document.querySelectorAll('.ifs-educore-profile-tabs .nav-link');
        tabButtons.forEach(function(btn) {
            btn.addEventListener('click', function() {
                const targetTabId = this.getAttribute('data-target');
                
                document.querySelectorAll('.ifs-educore-tab-content-block').forEach(function(block) {
                    block.style.display = 'none';
                });

                tabButtons.forEach(function(b) {
                    b.classList.remove('active');
                });

                const activeBlock = document.getElementById(targetTabId);
                if (activeBlock) {
                    activeBlock.style.display = 'block';
                }
                this.classList.add('active');
            });
        });
    });
    </script>
    <?php
}
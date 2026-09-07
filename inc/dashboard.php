<?php
/**
 * Enterprise Multi-Role Dashboard Dispatcher & Elite Neo-Bento Matrix
 * File: inc/dashboard.php
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Direct access safety
}

/**
 * Main Dashboard View Router
 */
function educore_dashboard_view() {
    $current_user = wp_get_current_user();
    if ( ! $current_user || ! $current_user->exists() ) {
        wp_die( esc_html__( 'You must be logged in to view this page.', 'ifsedu-school-management' ) );
    }

    $roles = (array) $current_user->roles;

    // 1. Administrators and Super Admins always have full access
    if ( in_array( 'administrator', $roles, true ) || current_user_can( 'manage_options' ) ) {
        educore_admin_dashboard_view( $current_user );
        return;
    }

    // 2. Fetch configured role-based module permissions from settings
    $saved_permissions = get_option( 'educore_role_permissions', array() );
    $saved_permissions = is_array( $saved_permissions ) ? $saved_permissions : array();

    // Determine current user's primary permission key based on WordPress capabilities/roles
    $user_role_key = 'student';
    if ( in_array( 'teacher', $roles, true ) || in_array( 'instructor', $roles, true ) || current_user_can( 'edit_posts' ) ) {
        $user_role_key = 'teacher';
    } elseif ( in_array( 'accountant', $roles, true ) ) {
        $user_role_key = 'accountant';
    } elseif ( in_array( 'staff', $roles, true ) ) {
        $user_role_key = 'staff';
    }

    // Route based on role
    if ( 'teacher' === $user_role_key ) {
        educore_teacher_dashboard_view( $current_user );
    } elseif ( 'accountant' === $user_role_key ) {
        educore_accountant_dashboard_view( $current_user );
    } else {
        educore_student_guardian_dashboard_view( $current_user );
    }
}

/**
 * Global Shared Design Stylesheet & User Profile Hero Section
 */
function educore_dashboard_render_hero_profile( $user, $role_title, $extra_meta = array(), $action_btn = null ) {
    global $wpdb;

    $table_staff    = $wpdb->prefix . 'sms_staff';
    $table_students = $wpdb->prefix . 'sms_students';
    
    $custom_avatar = '';
    $designation   = '';
    $display_name  = $user->display_name;

    // 1. Resolve Profile Details from Staff Table
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $staff_row = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT full_name, designation, profile_image FROM `{$table_staff}` WHERE wp_user_id = %d OR email = %s LIMIT 1",
            $user->ID,
            $user->user_email
        )
    );
    // phpcs:enable
    
    if ( $staff_row ) {
        $display_name  = ! empty( $staff_row->full_name ) ? $staff_row->full_name : $display_name;
        $designation   = $staff_row->designation;
        $custom_avatar = $staff_row->profile_image;
    } else {
        // 2. Resolve Profile Details from Student Table
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $student_row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT full_name, photo_url, class_name, section_name, roll_no FROM `{$table_students}` WHERE student_email = %s LIMIT 1",
                $user->user_email
            )
        );
        // phpcs:enable
        if ( $student_row ) {
            $display_name  = ! empty( $student_row->full_name ) ? $student_row->full_name : $display_name;
            $custom_avatar = $student_row->photo_url;
            $designation   = sprintf(
                /* translators: 1: Class name, 2: Section name, 3: Student roll number */
                esc_html__( 'Class %1$s (Sec: %2$s, Roll: #%3$d)', 'ifsedu-school-management' ),
                esc_html( $student_row->class_name ),
                esc_html( $student_row->section_name ? $student_row->section_name : 'A' ),
                intval( $student_row->roll_no )
            );
        }
    }

    if ( empty( $custom_avatar ) ) {
        $custom_avatar = get_avatar_url( $user->ID, array( 'size' => 128 ) );
    }

    $current_hour = (int) current_time( 'G' );
    $greeting     = __( 'Good Morning', 'ifsedu-school-management' );
    if ( $current_hour >= 12 && $current_hour < 17 ) {
        $greeting = __( 'Good Afternoon', 'ifsedu-school-management' );
    } elseif ( $current_hour >= 17 ) {
        $greeting = __( 'Good Evening', 'ifsedu-school-management' );
    }
    ?>

    <div class="ifs-educore-dash-hero-green">
        <div class="ifs-educore-profile-flex">
            <img src="<?php echo esc_url( $custom_avatar ); ?>" alt="<?php esc_attr_e( 'User Avatar', 'ifsedu-school-management' ); ?>" class="ifs-educore-profile-avatar">
            <div>
                <span class="ifs-educore-hero-badge"><?php echo esc_html( $role_title ); ?></span>
                <h2 class="ifs-educore-hero-title">
                    <?php
                    printf(
                        /* translators: 1: Greeting phrase (e.g. Good Morning), 2: User display name */
                        esc_html__( '%1$s, %2$s', 'ifsedu-school-management' ),
                        esc_html( $greeting ),
                        esc_html( $display_name )
                    );
                    ?>
                </h2>
                <div class="ifs-educore-hero-meta-strip">
                    <?php if ( ! empty( $designation ) ) : ?>
                        <span class="ifs-educore-hero-meta-item">
                            <span class="dashicons dashicons-id" style="font-size: 15px; width: 15px; height: 15px;"></span>
                            <?php echo esc_html( $designation ); ?>
                        </span>
                    <?php endif; ?>

                    <?php foreach ( $extra_meta as $meta_label => $meta_val ) : ?>
                        &bull;
                        <span class="ifs-educore-hero-meta-item">
                            <strong><?php echo esc_html( $meta_label ); ?>:</strong> <?php echo esc_html( $meta_val ); ?>
                        </span>
                    <?php endforeach; ?>

                    &bull;
                    <span class="ifs-educore-hero-meta-item" style="background: rgba(16, 185, 129, 0.25); color: #a7f3d0; padding: 3px 10px; border-radius: 8px; font-weight: 700; font-size: 12px; border: 1px solid rgba(16,185,129,0.3);">
                        <span class="dashicons dashicons-yes-alt" style="font-size: 14px; width: 14px; height: 14px; vertical-align: middle;"></span> <?php esc_html_e( 'ACCOUNT ACTIVE', 'ifsedu-school-management' ); ?>
                    </span>
                </div>
            </div>
        </div>

        <div class="ifs-educore-banner-datetime-pill">
            <div class="ifs-educore-banner-date">
                <span class="dashicons dashicons-calendar-alt" style="font-size: 13px; width: 13px; height: 13px;"></span>
                <?php echo esc_html( date_i18n( 'l, jS F Y' ) ); ?>
            </div>
            <div class="ifs-educore-banner-clock">
                <span class="dashicons dashicons-clock" style="font-size: 16px; width: 16px; height: 16px; color: #a7f3d0;"></span>
                <span id="educoreLiveClock"><?php echo esc_html( current_time( 'H:i:s' ) ); ?></span>
            </div>
        </div>
    </div>

    <script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function() {
        var clockEl = document.getElementById('educoreLiveClock');
        if (clockEl) {
            setInterval(function() {
                var now = new Date();
                var hours = String(now.getHours()).padStart(2, '0');
                var minutes = String(now.getMinutes()).padStart(2, '0');
                var seconds = String(now.getSeconds()).padStart(2, '0');
                clockEl.textContent = hours + ':' + minutes + ':' + seconds;
            }, 1000);
        }
    });
    </script>
    <?php
}

// ==============================================================================
// 1. ADMIN / HEADMASTER DASHBOARD
// ==============================================================================
function educore_admin_dashboard_view( $user ) {
    global $wpdb;
    $table_students   = $wpdb->prefix . 'sms_students';
    $table_staff      = $wpdb->prefix . 'sms_staff';
    $table_accounting = $wpdb->prefix . 'sms_accounting';
    $table_fees       = $wpdb->prefix . 'sms_fees';
    $table_exams      = $wpdb->prefix . 'sms_exams';
    $table_attendance = $wpdb->prefix . 'sms_attendance';
    $table_notices    = $wpdb->prefix . 'sms_notices';

    // Metrics Aggregation
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $total_students = (int) $wpdb->get_var( "SELECT COUNT(id) FROM `{$table_students}` WHERE status = 'Active'" );
    
    $male_students = (int) $wpdb->get_var( "SELECT COUNT(id) FROM `{$table_students}` WHERE status = 'Active' AND (gender = 'Male' OR gender = 'M')" );
    $female_students = max( 0, $total_students - $male_students );

    $total_teachers = (int) $wpdb->get_var( "SELECT COUNT(id) FROM `{$table_staff}` WHERE status = 'Active'" );

    $today_date = current_time( 'Y-m-d' );
    
    $present_today = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM `{$table_attendance}` WHERE attendance_date = %s AND status = 'Present'", $today_date ) );
    $attendance_pct = $total_students > 0 ? round( ( $present_today / $total_students ) * 100 ) : 0;
    $absent_today   = max( 0, $total_students - $present_today );

    $today_fee_collection = (float) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(paid_amount) FROM `{$table_fees}` WHERE DATE(payment_date) = %s", $today_date ) );
    
    $pending_receivables  = (float) $wpdb->get_var( "SELECT SUM(due_amount) FROM `{$table_fees}` WHERE due_amount > 0" );

    $month_start = current_time( 'Y-m-01' );
    $month_end   = current_time( 'Y-m-t' );

    $month_collections = (float) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(paid_amount) FROM `{$table_fees}` WHERE payment_date BETWEEN %s AND %s", $month_start, $month_end ) );

    $month_expenses    = (float) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(amount) FROM `{$table_accounting}` WHERE entry_type = 'Expense' AND entry_date BETWEEN %s AND %s", $month_start, $month_end ) );
    $net_operating_cash = $month_collections - $month_expenses;

    $exams_count = (int) $wpdb->get_var( "SELECT COUNT(id) FROM `{$table_exams}`" );

    // Optimized column selections
    $recent_ledger = $wpdb->get_results( "SELECT invoice_id, fee_type, payment_method, payment_date, paid_amount FROM `{$table_fees}` ORDER BY id DESC LIMIT 5" );

    // 1. Class-wise attendance breakdown
    $class_attendance_summary = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT 
                s.class_name,
                COUNT(s.id) as total_enrolled,
                SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) as present_count
             FROM `{$table_students}` s
             LEFT JOIN `{$table_attendance}` a 
                ON s.id = a.student_id AND a.attendance_date = %s
             WHERE s.status = 'Active'
             GROUP BY s.class_name
             ORDER BY CAST(s.class_name AS UNSIGNED) ASC, s.class_name ASC
             LIMIT 5",
            $today_date
        )
    );

    // 2. Upcoming Examinations
    $upcoming_exams = $wpdb->get_results(
        "SELECT id, exam_name, class_name, start_date FROM `{$table_exams}` ORDER BY id DESC LIMIT 4"
    );

    // 3. School Notices & Administrative Circulars
    $recent_admin_notices = $wpdb->get_results(
        "SELECT id, title, target_audience, event_date, publish_date, created_at FROM `{$table_notices}` ORDER BY id DESC LIMIT 4"
    );
    // phpcs:enable

    educore_dashboard_render_hero_profile( $user, __( 'System Administrator', 'ifsedu-school-management' ), array(
        __( 'Students', 'ifsedu-school-management' ) => $total_students,
        __( 'Faculty', 'ifsedu-school-management' )  => $total_teachers,
    ) );
    ?>
    <div class="ifs-educore-dash-root">
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
            <div class="ifs-educore-stat-bento">
                <div class="ifs-educore-stat-top-row">
                    <div>
                        <span class="ifs-educore-sub-stat-label"><?php esc_html_e( 'Total Active Students', 'ifsedu-school-management' ); ?></span>
                        <div style="font-size: 30px; font-weight: 800; color: #0f172a; margin-top: 6px; letter-spacing: -0.5px;"><?php echo esc_html( number_format( $total_students ) ); ?></div>
                        <span style="font-size: 12px; color: #64748b; font-weight: 700; display: inline-block; margin-top: 2px;">
                            (<?php echo esc_html( $male_students ); ?> Male / <?php echo esc_html( $female_students ); ?> Female)
                        </span>
                    </div>
                    <div class="ifs-educore-stat-icon-badge" style="background: #eff6ff; color: #2563eb;">
                        <span class="dashicons dashicons-groups"></span>
                    </div>
                </div>
                <div class="ifs-educore-stat-footer-row">
                    <span style="color: #00523c; font-weight: 700;"><?php esc_html_e( 'Academic Classes', 'ifsedu-school-management' ); ?></span>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=school_management_system&tab=students&sub=list' ) ); ?>" style="color: #2563eb; font-weight: 700;"><?php esc_html_e( 'Directory &rarr;', 'ifsedu-school-management' ); ?></a>
                </div>
            </div>

            <div class="ifs-educore-stat-bento">
                <div class="ifs-educore-stat-top-row">
                    <div>
                        <span class="ifs-educore-sub-stat-label"><?php esc_html_e( 'Attendance Present Today', 'ifsedu-school-management' ); ?></span>
                        <div style="font-size: 30px; font-weight: 800; color: #0f172a; margin-top: 6px; letter-spacing: -0.5px;">
                            <?php echo esc_html( number_format( $present_today ) ); ?> <span style="font-size: 14px; font-weight: 700; color: #059669;">(<?php echo esc_html( $attendance_pct ); ?>%)</span>
                        </div>
                        <span style="font-size: 12px; color: #64748b; font-weight: 700; display: inline-block; margin-top: 2px;">
                            <?php esc_html_e( 'Real-time classroom sync', 'ifsedu-school-management' ); ?>
                        </span>
                    </div>
                    <div class="ifs-educore-stat-icon-badge" style="background: #ecfdf5; color: #059669;">
                        <span class="dashicons dashicons-yes-alt"></span>
                    </div>
                </div>
                <div class="ifs-educore-stat-footer-row">
                    <span style="color: #059669; font-weight: 700;"><?php esc_html_e( 'Active Logins', 'ifsedu-school-management' ); ?></span>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=school_management_system&tab=attendance&sub=daily' ) ); ?>" style="color: #059669; font-weight: 700;"><?php esc_html_e( 'Attendance &rarr;', 'ifsedu-school-management' ); ?></a>
                </div>
            </div>

            <div class="ifs-educore-stat-bento">
                <div class="ifs-educore-stat-top-row">
                    <div>
                        <span class="ifs-educore-sub-stat-label"><?php esc_html_e( 'Absent Count Today', 'ifsedu-school-management' ); ?></span>
                        <div style="font-size: 30px; font-weight: 800; color: #dc2626; margin-top: 6px; letter-spacing: -0.5px;">
                            <?php echo esc_html( number_format( $absent_today ) ); ?>
                        </div>
                        <span style="font-size: 12px; color: #64748b; font-weight: 700; display: inline-block; margin-top: 2px;">
                            <?php esc_html_e( 'Requires attention/followup', 'ifsedu-school-management' ); ?>
                        </span>
                    </div>
                    <div class="ifs-educore-stat-icon-badge" style="background: #fef2f2; color: #dc2626;">
                        <span class="dashicons dashicons-warning"></span>
                    </div>
                </div>
                <div class="ifs-educore-stat-footer-row">
                    <span style="background: #fef2f2; color: #dc2626; padding: 2px 8px; border-radius: 4px; font-size: 11px;"><?php esc_html_e( 'Unexcused', 'ifsedu-school-management' ); ?></span>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=school_management_system&tab=attendance&sub=reports' ) ); ?>" style="color: #dc2626; font-weight: 700;"><?php esc_html_e( 'Logs &rarr;', 'ifsedu-school-management' ); ?></a>
                </div>
            </div>

            <div class="ifs-educore-stat-bento">
                <div class="ifs-educore-stat-top-row">
                    <div>
                        <span class="ifs-educore-sub-stat-label"><?php esc_html_e( 'Today\'s Fee Collection', 'ifsedu-school-management' ); ?></span>
                        <div style="font-size: 28px; font-weight: 800; color: #00523c; margin-top: 6px; letter-spacing: -0.5px;">৳<?php echo esc_html( number_format( $today_fee_collection, 2 ) ); ?></div>
                        <span style="font-size: 12px; color: #64748b; font-weight: 700; display: inline-block; margin-top: 2px;">
                            <?php esc_html_e( 'Daily cash & online inflows', 'ifsedu-school-management' ); ?>
                        </span>
                    </div>
                    <div class="ifs-educore-stat-icon-badge" style="background: #ecfdf5; color: #00523c;">
                        <span class="dashicons dashicons-money-alt"></span>
                    </div>
                </div>
                <div class="ifs-educore-stat-footer-row">
                    <span style="color: #64748b; font-weight: 700;"><?php esc_html_e( 'Daily Inflow', 'ifsedu-school-management' ); ?></span>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=school_management_system&tab=fees&sub=collect' ) ); ?>" style="color: #00523c; font-weight: 700;"><?php esc_html_e( 'Collect Fee &rarr;', 'ifsedu-school-management' ); ?></a>
                </div>
            </div>

            <div class="ifs-educore-stat-bento">
                <div class="ifs-educore-stat-top-row">
                    <div>
                        <span class="ifs-educore-sub-stat-label"><?php esc_html_e( 'Pending Dues (Receivables)', 'ifsedu-school-management' ); ?></span>
                        <div style="font-size: 28px; font-weight: 800; color: #dc2626; margin-top: 6px; letter-spacing: -0.5px;">৳<?php echo esc_html( number_format( $pending_receivables, 2 ) ); ?></div>
                        <span style="font-size: 12px; color: #64748b; font-weight: 700; display: inline-block; margin-top: 2px;">
                            <?php esc_html_e( 'Total outstanding student dues', 'ifsedu-school-management' ); ?>
                        </span>
                    </div>
                    <div class="ifs-educore-stat-icon-badge" style="background: #fef2f2; color: #dc2626;">
                        <span class="dashicons dashicons-shield-alt"></span>
                    </div>
                </div>
                <div class="ifs-educore-stat-footer-row">
                    <span style="color: #64748b; font-weight: 700;"><?php esc_html_e( 'Outstanding', 'ifsedu-school-management' ); ?></span>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=school_management_system&tab=fees&sub=list' ) ); ?>" style="color: #dc2626; font-weight: 700;"><?php esc_html_e( 'Audit Report &rarr;', 'ifsedu-school-management' ); ?></a>
                </div>
            </div>

            <div class="ifs-educore-stat-bento">
                <div class="ifs-educore-stat-top-row">
                    <div>
                        <span class="ifs-educore-sub-stat-label"><?php esc_html_e( 'Faculty & Staff Members', 'ifsedu-school-management' ); ?></span>
                        <div style="font-size: 30px; font-weight: 800; color: #0f172a; margin-top: 6px; letter-spacing: -0.5px;"><?php echo esc_html( $total_teachers ); ?></div>
                        <span style="font-size: 12px; color: #64748b; font-weight: 700; display: inline-block; margin-top: 2px;">
                            <?php esc_html_e( 'Active academic personnel', 'ifsedu-school-management' ); ?>
                        </span>
                    </div>
                    <div class="ifs-educore-stat-icon-badge" style="background: #ecfdf5; color: #00523c;">
                        <span class="dashicons dashicons-businessman"></span>
                    </div>
                </div>
                <div class="ifs-educore-stat-footer-row">
                    <span style="color: #059669; font-weight: 700;"><?php esc_html_e( 'Active Payroll', 'ifsedu-school-management' ); ?></span>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=school_management_system&tab=staff' ) ); ?>" style="color: #00523c; font-weight: 700;"><?php esc_html_e( 'Faculty List &rarr;', 'ifsedu-school-management' ); ?></a>
                </div>
            </div>
        </div>

        <div class="ifs-educore-bento-grid-4-sub">
            <div class="ifs-educore-sub-stat-box">
                <span class="ifs-educore-sub-stat-label"><?php esc_html_e( 'Month Collections (Fees)', 'ifsedu-school-management' ); ?></span>
                <span class="ifs-educore-sub-stat-val" style="color: #00523c;">৳<?php echo esc_html( number_format( $month_collections, 2 ) ); ?></span>
            </div>
            <div class="ifs-educore-sub-stat-box">
                <span class="ifs-educore-sub-stat-label"><?php esc_html_e( 'Month General Expenses', 'ifsedu-school-management' ); ?></span>
                <span class="ifs-educore-sub-stat-val" style="color: #dc2626;">৳<?php echo esc_html( number_format( $month_expenses, 2 ) ); ?></span>
            </div>
            <div class="ifs-educore-sub-stat-box">
                <span class="ifs-educore-sub-stat-label"><?php esc_html_e( 'Net Operating Cash (Total)', 'ifsedu-school-management' ); ?></span>
                <span class="ifs-educore-sub-stat-val" style="color: #2563eb;">৳<?php echo esc_html( number_format( $net_operating_cash, 2 ) ); ?></span>
            </div>
            <div class="ifs-educore-sub-stat-box">
                <span class="ifs-educore-sub-stat-label"><?php esc_html_e( 'Examinations Evaluated', 'ifsedu-school-management' ); ?></span>
                <span class="ifs-educore-sub-stat-val"><?php echo esc_html( $exams_count ); ?></span>
            </div>
        </div>

        <!-- Balanced Equal-Height Columns Layout -->
        <div class="ifs-educore-split-layout">
            <div class="ifs-educore-col">
                <div class="ifs-educore-panel-card">
                    <div class="ifs-educore-panel-header" style="margin-bottom: 16px;">
                        <h3 class="ifs-educore-panel-title">
                            <span class="dashicons dashicons-admin-generic" style="color:#00523c;"></span>
                            <?php esc_html_e( 'Administrative Command Dock', 'ifsedu-school-management' ); ?>
                        </h3>
                    </div>
                    <div class="ifs-educore-command-dock-grid">
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=school_management_system&tab=students&sub=add' ) ); ?>" class="ifs-educore-command-tile">
                            <span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e( 'Admit Student', 'ifsedu-school-management' ); ?>
                        </a>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=school_management_system&tab=attendance&sub=daily' ) ); ?>" class="ifs-educore-command-tile">
                            <span class="dashicons dashicons-calendar-alt"></span> <?php esc_html_e( 'Attendance', 'ifsedu-school-management' ); ?>
                        </a>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=school_management_system&tab=fees&sub=collect' ) ); ?>" class="ifs-educore-command-tile">
                            <span class="dashicons dashicons-money-alt"></span> <?php esc_html_e( 'Collect Fee', 'ifsedu-school-management' ); ?>
                        </a>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=school_management_system&tab=results&sub=marks' ) ); ?>" class="ifs-educore-command-tile">
                            <span class="dashicons dashicons-edit"></span> <?php esc_html_e( 'Marks Matrix', 'ifsedu-school-management' ); ?>
                        </a>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=school_management_system&tab=students&sub=id_card' ) ); ?>" class="ifs-educore-command-tile">
                            <span class="dashicons dashicons-id"></span> <?php esc_html_e( 'ID Cards', 'ifsedu-school-management' ); ?>
                        </a>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=school_management_system&tab=accounting&sub=add' ) ); ?>" class="ifs-educore-command-tile">
                            <span class="dashicons dashicons-cart"></span> <?php esc_html_e( 'Add Expense', 'ifsedu-school-management' ); ?>
                        </a>
                    </div>
                </div>

                <div class="ifs-educore-panel-card">
                    <div class="ifs-educore-panel-header">
                        <h3 class="ifs-educore-panel-title">
                            <span class="dashicons dashicons-book-alt" style="color:#00523c;"></span>
                            <?php esc_html_e( 'Recent Financial Ledger Activity', 'ifsedu-school-management' ); ?>
                        </h3>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=school_management_system&tab=fees&sub=list' ) ); ?>" style="font-size: 12px; font-weight: 800; color: #00523c; text-decoration: none;"><?php esc_html_e( 'VIEW ALL &rarr;', 'ifsedu-school-management' ); ?></a>
                    </div>
                    <div style="overflow-x: auto;">
                        <table class="ifs-educore-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e( 'Reference', 'ifsedu-school-management' ); ?></th>
                                    <th><?php esc_html_e( 'Particulars', 'ifsedu-school-management' ); ?></th>
                                    <th><?php esc_html_e( 'Method', 'ifsedu-school-management' ); ?></th>
                                    <th><?php esc_html_e( 'Date', 'ifsedu-school-management' ); ?></th>
                                    <th style="text-align: right;"><?php esc_html_e( 'Amount', 'ifsedu-school-management' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ( ! empty( $recent_ledger ) ) : foreach ( $recent_ledger as $trx ) : 
                                    $payment_timestamp = strtotime( $trx->payment_date );
                                    $payment_date_formatted = $payment_timestamp ? date_i18n( 'd M, Y', $payment_timestamp ) : '—';
                                ?>
                                    <tr>
                                        <td><code>#<?php echo esc_html( $trx->invoice_id ); ?></code></td>
                                        <td>
                                            <strong style="color: #0f172a; display: block;"><?php echo esc_html( $trx->fee_type ); ?></strong>
                                            <span style="font-size: 11px; color: #64748b;"><?php esc_html_e( 'Student Fee Ledger', 'ifsedu-school-management' ); ?></span>
                                        </td>
                                        <td><span style="background: #f1f5f9; padding: 3px 8px; border-radius: 6px; font-size: 11px; font-weight: 700; color: #475569;"><?php echo esc_html( $trx->payment_method ); ?></span></td>
                                        <td style="font-size: 12px; color: #64748b;"><?php echo esc_html( $payment_date_formatted ); ?></td>
                                        <td style="text-align: right;"><strong style="color: #059669;">+৳<?php echo esc_html( number_format( (float) $trx->paid_amount, 2 ) ); ?></strong></td>
                                    </tr>
                                <?php endforeach; else : ?>
                                    <tr><td colspan="5" style="text-align: center; color: #94a3b8; padding: 24px;"><?php esc_html_e( 'No recent financial transactions found.', 'ifsedu-school-management' ); ?></td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Academic Examinations Hub -->
                <div class="ifs-educore-panel-card ifs-card-fill">
                    <div class="ifs-educore-panel-header">
                        <h3 class="ifs-educore-panel-title">
                            <span class="dashicons dashicons-welcome-learn-more" style="color:#00523c;"></span>
                            <?php esc_html_e( 'Examinations & Assessments Hub', 'ifsedu-school-management' ); ?>
                        </h3>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=school_management_system&tab=exams' ) ); ?>" style="font-size: 12px; font-weight: 800; color: #00523c; text-decoration: none;"><?php esc_html_e( 'MANAGE EXAMS &rarr;', 'ifsedu-school-management' ); ?></a>
                    </div>
                    <?php if ( ! empty( $upcoming_exams ) ) : ?>
                        <div class="ifs-educore-dash-list">
                            <?php foreach ( $upcoming_exams as $exam ) : 
                                $exam_timestamp = ! empty( $exam->start_date ) ? strtotime( $exam->start_date ) : false;
                                $exam_date_formatted = $exam_timestamp ? date_i18n( 'd M Y', $exam_timestamp ) : esc_html__( 'Active Session', 'ifsedu-school-management' );
                            ?>
                                <div class="ifs-educore-list-item" style="padding: 12px 14px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; margin-bottom: 8px; display: flex; justify-content: space-between; align-items: center;">
                                    <div>
                                        <strong style="color: #0f172a; font-size: 14px; display: block;"><?php echo esc_html( $exam->exam_name ); ?></strong>
                                        <span style="font-size: 12px; color: #64748b;">
                                            <?php if ( ! empty( $exam->class_name ) ) : ?>
                                                <?php esc_html_e( 'Class:', 'ifsedu-school-management' ); ?> <?php echo esc_html( $exam->class_name ); ?> &bull; 
                                            <?php endif; ?>
                                            <?php echo esc_html( $exam_date_formatted ); ?>
                                        </span>
                                    </div>
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=school_management_system&tab=results&sub=marks' ) ); ?>" class="ifs-educore-command-tile" style="padding: 6px 12px; font-size: 12px; flex-direction: row; gap: 4px;">
                                        <span class="dashicons dashicons-edit" style="font-size: 14px; width: 14px; height: 14px;"></span> <?php esc_html_e( 'Marks', 'ifsedu-school-management' ); ?>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else : ?>
                        <p style="color:#94a3b8; font-size:13.5px; margin:0;"><?php esc_html_e( 'No examination records found. Click below to schedule a new term exam.', 'ifsedu-school-management' ); ?></p>
                        <div style="margin-top: 12px;">
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=school_management_system&tab=exams&sub=add' ) ); ?>" class="ifs-educore-command-tile" style="padding: 8px 14px; font-size: 12px; display: inline-flex; flex-direction: row; gap: 6px;">
                                <span class="dashicons dashicons-plus"></span> <?php esc_html_e( 'Create Examination', 'ifsedu-school-management' ); ?>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="ifs-educore-col">
                <!-- Class Attendance Overview -->
                <div class="ifs-educore-panel-card">
                    <div class="ifs-educore-panel-header">
                        <h3 class="ifs-educore-panel-title">
                            <span class="dashicons dashicons-chart-bar" style="color:#00523c;"></span>
                            <?php esc_html_e( 'Class Attendance Overview', 'ifsedu-school-management' ); ?>
                        </h3>
                        <span class="ifs-educore-badge-pill"><?php echo esc_html( $attendance_pct ); ?>% Overall</span>
                    </div>

                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; margin-bottom: 20px;">
                        <div style="display: flex; justify-content: space-between; font-size: 12px; font-weight: 800; color: #475569; margin-bottom: 8px;">
                            <span><?php esc_html_e( 'PRESENT VS TOTAL ENROLLED', 'ifsedu-school-management' ); ?></span>
                            <span><?php echo esc_html( $present_today ); ?> / <?php echo esc_html( $total_students ); ?></span>
                        </div>
                        <div style="height: 10px; background: #e2e8f0; border-radius: 10px; overflow: hidden;">
                            <div style="width: <?php echo esc_attr( min( 100, $attendance_pct ) ); ?>%; height: 100%; background: #00523c; border-radius: 10px; transition: width 0.6s ease;"></div>
                        </div>
                    </div>

                    <!-- Class-Wise Breakdown Progress Bars -->
                    <h4 style="font-size: 13px; font-weight: 800; color: #334155; margin: 0 0 14px 0; text-transform: uppercase; letter-spacing: 0.3px;">
                        <?php esc_html_e( 'Class-Wise Attendance Ratio', 'ifsedu-school-management' ); ?>
                    </h4>
                    <?php if ( ! empty( $class_attendance_summary ) ) : ?>
                        <div style="display: flex; flex-direction: column; gap: 12px;">
                            <?php foreach ( $class_attendance_summary as $c_stat ) : 
                                $c_enrolled = (int) $c_stat->total_enrolled;
                                $c_present  = (int) $c_stat->present_count;
                                $c_pct      = $c_enrolled > 0 ? round( ( $c_present / $c_enrolled ) * 100 ) : 0;
                                $c_name     = preg_match( '/^class\s+/i', $c_stat->class_name ) ? $c_stat->class_name : 'Class ' . $c_stat->class_name;
                            ?>
                                <div>
                                    <div style="display: flex; justify-content: space-between; font-size: 12.5px; font-weight: 700; color: #0f172a; margin-bottom: 4px;">
                                        <span><?php echo esc_html( $c_name ); ?></span>
                                        <span style="color: #059669;"><?php echo esc_html( $c_present ); ?>/<?php echo esc_html( $c_enrolled ); ?> (<?php echo esc_html( $c_pct ); ?>%)</span>
                                    </div>
                                    <div style="height: 7px; background: #f1f5f9; border-radius: 6px; overflow: hidden; border: 1px solid #e2e8f0;">
                                        <div style="width: <?php echo esc_attr( min( 100, $c_pct ) ); ?>%; height: 100%; background: linear-gradient(90deg, #047857 0%, #10b981 100%); border-radius: 6px;"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else : ?>
                        <p style="font-size: 12.5px; color: #94a3b8; margin: 0;"><?php esc_html_e( 'No student enrollment records found to compute class breakdown.', 'ifsedu-school-management' ); ?></p>
                    <?php endif; ?>
                </div>

                <!-- Institutional Circulars & Notices -->
                <div class="ifs-educore-panel-card">
                    <div class="ifs-educore-panel-header">
                        <h3 class="ifs-educore-panel-title">
                            <span class="dashicons dashicons-bell" style="color:#00523c;"></span>
                            <?php esc_html_e( 'Institutional Notices & Feeds', 'ifsedu-school-management' ); ?>
                        </h3>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=school_management_system&tab=notices&sub=add' ) ); ?>" class="ifs-educore-command-tile" style="padding: 4px 10px; font-size: 11px; flex-direction: row;">
                            + <?php esc_html_e( 'Publish', 'ifsedu-school-management' ); ?>
                        </a>
                    </div>
                    <?php if ( ! empty( $recent_admin_notices ) ) : ?>
                        <div class="ifs-educore-dash-list">
                            <?php foreach ( $recent_admin_notices as $notice ) : 
                                $n_raw_date = ( ! empty( $notice->event_date ) && '1970-01-01' !== $notice->event_date ) ? $notice->event_date : ( ! empty( $notice->publish_date ) ? $notice->publish_date : $notice->created_at );
                                $n_timestamp = strtotime( $n_raw_date );
                                $n_date_formatted = $n_timestamp ? date_i18n( 'd M Y', $n_timestamp ) : '—';
                                $n_aud = ! empty( $notice->target_audience ) ? $notice->target_audience : 'All';
                            ?>
                                <div class="ifs-educore-list-item" style="padding: 10px 0; border-bottom: 1px solid #f1f5f9;">
                                    <div>
                                        <strong style="color: #0f172a; font-size: 13.5px; display: block; line-height: 1.4;"><?php echo esc_html( $notice->title ); ?></strong>
                                        <div style="font-size: 11.5px; color: #64748b; margin-top: 3px;">
                                            <span><?php echo esc_html( $n_date_formatted ); ?></span> &bull; 
                                            <span class="ifs-educore-badge-pill" style="font-size: 10px; padding: 2px 6px;"><?php echo esc_html( $n_aud ); ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else : ?>
                        <p style="color:#94a3b8; font-size:13px; margin:0;"><?php esc_html_e( 'No notices published yet.', 'ifsedu-school-management' ); ?></p>
                    <?php endif; ?>
                </div>

                <!-- System & Environment Diagnostics -->
                <div class="ifs-educore-panel-card ifs-card-fill" style="background: #f8fafc;">
                    <div class="ifs-educore-panel-header" style="margin-bottom: 10px;">
                        <h3 class="ifs-educore-panel-title" style="font-size: 14px;">
                            <span class="dashicons dashicons-dashboard" style="color:#00523c;"></span>
                            <?php esc_html_e( 'System & License Status', 'ifsedu-school-management' ); ?>
                        </h3>
                        <span style="font-size: 11px; font-weight: 800; color: #059669; background: #ecfdf5; padding: 2px 8px; border-radius: 6px; border: 1px solid #a7f3d0;">
                            <?php esc_html_e( 'ONLINE', 'ifsedu-school-management' ); ?>
                        </span>
                    </div>
                    <div style="font-size: 12px; color: #64748b; line-height: 1.8;">
                        <div><strong><?php esc_html_e( 'Software Engine:', 'ifsedu-school-management' ); ?></strong> Educore ERP Enterprise</div>
                        <div><strong><?php esc_html_e( 'PHP Version:', 'ifsedu-school-management' ); ?></strong> <?php echo esc_html( phpversion() ); ?></div>
                        <div><strong><?php esc_html_e( 'Database Engine:', 'ifsedu-school-management' ); ?></strong> MySQL <?php echo esc_html( $wpdb->db_version() ); ?></div>
                        <div><strong><?php esc_html_e( 'Server Clock:', 'ifsedu-school-management' ); ?></strong> <?php echo esc_html( current_time( 'Y-m-d H:i:s' ) ); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}

// ==============================================================================
// 2. TEACHER / FACULTY DASHBOARD
// ==============================================================================
function educore_teacher_dashboard_view( $user ) {
    global $wpdb;
    $table_staff            = $wpdb->prefix . 'sms_staff';
    $table_teacher_subjects = $wpdb->prefix . 'sms_teacher_subjects';
    $table_units            = $wpdb->prefix . 'sms_academic_units';
    $table_subjects         = $wpdb->prefix . 'sms_subjects';
    $table_notices          = $wpdb->prefix . 'sms_notices';

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $teacher_profile = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT id, designation, phone FROM `{$table_staff}` WHERE wp_user_id = %d OR email = %s LIMIT 1",
            $user->ID,
            $user->user_email
        )
    );

    $extra_meta = array();
    if ( $teacher_profile ) {
        if ( ! empty( $teacher_profile->designation ) ) {
            $extra_meta[ __( 'Designation', 'ifsedu-school-management' ) ] = $teacher_profile->designation;
        }
        if ( ! empty( $teacher_profile->phone ) ) {
            $extra_meta[ __( 'Phone', 'ifsedu-school-management' ) ] = $teacher_profile->phone;
        }
    }

    $assigned_units_data  = array();
    $unique_classes_count = 0;
    $total_subjects_count = 0;

    if ( $teacher_profile ) {
        $raw_allocations = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT 
                    ts.id AS assign_id,
                    u.id AS class_unit_id,
                    u.class_name,
                    u.section_name,
                    s.id AS subject_id,
                    s.subject_name,
                    s.subject_code
                   FROM `{$table_teacher_subjects}` ts
                   INNER JOIN `{$table_units}` u ON ts.class_id = u.id
                   INNER JOIN `{$table_subjects}` s ON ts.subject_id = s.id
                   WHERE ts.teacher_id = %d
                   ORDER BY CAST(u.class_name AS UNSIGNED) ASC, u.class_name ASC, u.section_name ASC, s.subject_name ASC",
                $teacher_profile->id
            )
        );

        if ( ! empty( $raw_allocations ) ) {
            $total_subjects_count = count( $raw_allocations );
            foreach ( $raw_allocations as $row ) {
                $group_key = $row->class_name . '|' . $row->section_name;
                if ( ! isset( $assigned_units_data[ $group_key ] ) ) {
                    $assigned_units_data[ $group_key ] = array(
                        'class_name'   => $row->class_name,
                        'section_name' => $row->section_name,
                        'unit_id'      => $row->class_unit_id,
                        'subjects'     => array(),
                    );
                }
                $assigned_units_data[ $group_key ]['subjects'][] = array(
                    'id'   => $row->subject_id,
                    'name' => $row->subject_name,
                    'code' => $row->subject_code,
                );
            }
            $unique_classes_count = count( $assigned_units_data );
        }
    }

    if ( $unique_classes_count > 0 ) {
        $extra_meta[ __( 'Assigned Classes', 'ifsedu-school-management' ) ] = $unique_classes_count . ' Units';
    }

    $teacher_notices = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, title, notice_type, event_date, publish_date, created_at FROM `{$table_notices}` WHERE target_audience IN (%s, %s) ORDER BY id DESC LIMIT 4",
            'All',
            'Teachers'
        )
    );
    // phpcs:enable

    educore_dashboard_render_hero_profile( $user, __( 'Faculty & Teacher Workspace', 'ifsedu-school-management' ), $extra_meta );
    ?>
    <div class="ifs-educore-dash-root">
        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px;">
            <div class="ifs-educore-stat-bento">
                <div class="ifs-educore-stat-top-row">
                    <div>
                        <span class="ifs-educore-sub-stat-label"><?php esc_html_e( 'Assigned Class Units', 'ifsedu-school-management' ); ?></span>
                        <div style="font-size: 30px; font-weight: 800; color: #0f172a; margin-top: 6px;"><?php echo esc_html( $unique_classes_count ); ?></div>
                    </div>
                    <div class="ifs-educore-stat-icon-badge" style="background: #ecfdf5; color: #00523c;">
                        <span class="dashicons dashicons-welcome-learn-more"></span>
                    </div>
                </div>
            </div>

            <div class="ifs-educore-stat-bento">
                <div class="ifs-educore-stat-top-row">
                    <div>
                        <span class="ifs-educore-sub-stat-label"><?php esc_html_e( 'Assigned Subjects', 'ifsedu-school-management' ); ?></span>
                        <div style="font-size: 30px; font-weight: 800; color: #0f172a; margin-top: 6px;"><?php echo esc_html( $total_subjects_count ); ?></div>
                    </div>
                    <div class="ifs-educore-stat-icon-badge" style="background: #eff6ff; color: #2563eb;">
                        <span class="dashicons dashicons-book"></span>
                    </div>
                </div>
            </div>

            <div class="ifs-educore-stat-bento">
                <div class="ifs-educore-stat-top-row">
                    <div>
                        <span class="ifs-educore-sub-stat-label"><?php esc_html_e( 'Academic Term', 'ifsedu-school-management' ); ?></span>
                        <div style="font-size: 20px; font-weight: 800; color: #059669; margin-top: 8px;">
                            <?php echo esc_html( date_i18n( 'Y' ) . ' Session' ); ?>
                        </div>
                    </div>
                    <div class="ifs-educore-stat-icon-badge" style="background: #ecfdf5; color: #059669;">
                        <span class="dashicons dashicons-calendar-alt"></span>
                    </div>
                </div>
            </div>

            <div class="ifs-educore-stat-bento">
                <div class="ifs-educore-stat-top-row">
                    <div>
                        <span class="ifs-educore-sub-stat-label"><?php esc_html_e( 'Faculty Circulars', 'ifsedu-school-management' ); ?></span>
                        <div style="font-size: 30px; font-weight: 800; color: #0f172a; margin-top: 6px;"><?php echo esc_html( count( $teacher_notices ) ); ?></div>
                    </div>
                    <div class="ifs-educore-stat-icon-badge" style="background: #fef2f2; color: #dc2626;">
                        <span class="dashicons dashicons-bell"></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="ifs-educore-split-layout">
            <div class="ifs-educore-col">
                <div class="ifs-educore-panel-card ifs-card-fill">
                    <div class="ifs-educore-panel-header">
                        <h3 class="ifs-educore-panel-title">
                            <span class="dashicons dashicons-welcome-learn-more" style="color:#00523c;"></span>
                            <?php esc_html_e( 'Academic Setup: My Assigned Classes & Subjects', 'ifsedu-school-management' ); ?>
                        </h3>
                    </div>

                    <?php if ( ! empty( $assigned_units_data ) ) : ?>
                        <div class="ifs-educore-teacher-unit-grid">
                            <?php foreach ( $assigned_units_data as $unit ) : 
                                $att_url   = add_query_arg(
                                    array(
                                        'page'         => 'school_management_system',
                                        'tab'          => 'attendance',
                                        'sub'          => 'daily',
                                        'class_name'   => $unit['class_name'],
                                        'section_name' => $unit['section_name'],
                                    ),
                                    admin_url( 'admin.php' )
                                );

                                $first_sub = ! empty( $unit['subjects'][0]['name'] ) ? $unit['subjects'][0]['name'] : '';
                                $marks_query_args = array(
                                    'page'         => 'school_management_system',
                                    'tab'          => 'results',
                                    'sub'          => 'marks',
                                    'class_name'   => $unit['class_name'],
                                    'section_name' => $unit['section_name'],
                                );
                                if ( $first_sub ) {
                                    $marks_query_args['subject_name'] = $first_sub;
                                }
                                $marks_url = add_query_arg( $marks_query_args, admin_url( 'admin.php' ) );

                                $display_class_name = preg_match( '/^class\s+/i', $unit['class_name'] ) ? $unit['class_name'] : 'Class ' . $unit['class_name'];
                            ?>
                                <div class="ifs-educore-teacher-unit-card">
                                    <div>
                                        <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                                            <div>
                                                <strong style="font-size:18px; color:#0f172a; display:block;">
                                                    <?php echo esc_html( $display_class_name ); ?>
                                                </strong>
                                                <span style="font-size:12.5px; color:#64748b; font-weight:700;">
                                                   <?php
/* translators: %s: Section name */
echo ! empty( $unit['section_name'] ) ? esc_html( sprintf( __( 'Section: %s', 'ifsedu-school-management' ), $unit['section_name'] ) ) : esc_html__( 'All Sections', 'ifsedu-school-management' );
?>
                                                </span>
                                            </div>
                                            <span style="background:#f1f5f9; border:1px solid #cbd5e1; color:#334155; font-size:11.5px; font-weight:800; padding:3px 10px; border-radius:12px;">
                                                <?php echo esc_html( count( $unit['subjects'] ) ); ?> <?php esc_html_e( 'Subjects', 'ifsedu-school-management' ); ?>
                                            </span>
                                        </div>

                                        <div style="margin-top:14px;">
                                            <span style="font-size:11px; font-weight:800; text-transform:uppercase; color:#64748b; letter-spacing:0.3px; display:block;">
                                                <?php esc_html_e( 'Assigned Curriculum:', 'ifsedu-school-management' ); ?>
                                            </span>
                                            <div class="ifs-educore-subject-tag-list">
                                                <?php foreach ( $unit['subjects'] as $sub ) : ?>
                                                    <span class="ifs-educore-subject-pill" title="<?php echo esc_attr( $sub['code'] ? 'Code: ' . $sub['code'] : '' ); ?>">
                                                        <span class="dashicons dashicons-book-alt" style="font-size:13px; width:13px; height:13px; vertical-align:middle;"></span>
                                                        <?php echo esc_html( $sub['name'] ); ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <div style="display:flex; gap:10px; border-top:1px solid #e2e8f0; padding-top:14px; margin-top:4px;">
                                        <a href="<?php echo esc_url( $att_url ); ?>" class="ifs-educore-command-tile" style="padding: 10px; font-size:12px; flex:1; flex-direction:row; gap:6px;">
                                            <span class="dashicons dashicons-calendar-alt"></span> <?php esc_html_e( 'Attendance', 'ifsedu-school-management' ); ?>
                                        </a>
                                        <a href="<?php echo esc_url( $marks_url ); ?>" class="ifs-educore-command-tile" style="padding: 10px; font-size:12px; flex:1; flex-direction:row; gap:6px; background:#00523c; color:#ffffff; border-color:#00523c;">
                                            <span class="dashicons dashicons-edit" style="color:#ffffff !important;"></span> <?php esc_html_e( 'Marks', 'ifsedu-school-management' ); ?>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else : ?>
                        <div style="background:#f8fafc; border:1px dashed #cbd5e1; border-radius:14px; padding:40px 20px; text-align:center;">
                            <span class="dashicons dashicons-info" style="font-size:36px; width:36px; height:36px; color:#94a3b8; margin-bottom:10px;"></span>
                            <p style="margin:0; font-size:14.5px; font-weight:700; color:#475569;">
                                <?php esc_html_e( 'No subjects or classes are currently assigned to your teacher account in Academic Setup.', 'ifsedu-school-management' ); ?>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="ifs-educore-col">
                <div class="ifs-educore-panel-card ifs-card-fill">
                    <div class="ifs-educore-panel-header">
                        <h3 class="ifs-educore-panel-title">
                            <span class="dashicons dashicons-bell" style="color:#00523c;"></span>
                            <?php esc_html_e( 'Faculty Circulars & Notices', 'ifsedu-school-management' ); ?>
                        </h3>
                    </div>
                    <?php if ( ! empty( $teacher_notices ) ) : ?>
                        <div class="ifs-educore-dash-list">
                            <?php foreach ( $teacher_notices as $n ) : 
                                $n_category = ! empty( $n->notice_type ) ? $n->notice_type : 'Notice';
                                $n_raw_date = ( ! empty( $n->event_date ) && '1970-01-01' !== $n->event_date ) ? $n->event_date : ( ! empty( $n->publish_date ) ? $n->publish_date : $n->created_at );
                                $n_timestamp = strtotime( $n_raw_date );
                                $n_date_formatted = $n_timestamp ? date_i18n( 'd M Y', $n_timestamp ) : '—';
                                
                                $notice_url = add_query_arg(
                                    array(
                                        'page' => 'school_management_system',
                                        'tab'  => 'notices',
                                        'type' => 'notice',
                                        'sub'  => 'view',
                                        'id'   => intval( $n->id ),
                                    ),
                                    admin_url( 'admin.php' )
                                );
                            ?>
                                <div class="ifs-educore-list-item">
                                    <div>
                                        <strong style="color:#0f172a; font-size:14px;"><?php echo esc_html( $n->title ); ?></strong>
                                        <div style="font-size:12px; color:#64748b; margin-top:4px;">
                                            <?php echo esc_html( $n_date_formatted ); ?> &bull; 
                                            <span class="ifs-educore-badge-pill"><?php echo esc_html( $n_category ); ?></span>
                                        </div>
                                    </div>
                                    <a href="<?php echo esc_url( $notice_url ); ?>" class="ifs-educore-command-tile" style="padding: 6px 14px; font-size: 12px; flex-direction:row;">
                                        <?php esc_html_e( 'Read', 'ifsedu-school-management' ); ?>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else : ?>
                        <p style="color:#94a3b8; font-size:13.5px; margin:0;"><?php esc_html_e( 'No notices found.', 'ifsedu-school-management' ); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php
}

// ==============================================================================
// 3. ACCOUNTANT DASHBOARD
// ==============================================================================
function educore_accountant_dashboard_view( $user ) {
    global $wpdb;
    $table_accounting = $wpdb->prefix . 'sms_accounting';
    $table_staff      = $wpdb->prefix . 'sms_staff';

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $accountant_profile = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT id, phone FROM `{$table_staff}` WHERE wp_user_id = %d OR email = %s LIMIT 1",
            $user->ID,
            $user->user_email
        )
    );
    $extra_meta = array();
    if ( $accountant_profile && ! empty( $accountant_profile->phone ) ) {
        $extra_meta[ __( 'Desk Phone', 'ifsedu-school-management' ) ] = $accountant_profile->phone;
    }

    $today = current_time( 'Y-m-d' );
    $month_start = current_time( 'Y-m-01' );
    $month_end   = current_time( 'Y-m-t' );

    $today_income = (float) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(amount) FROM `{$table_accounting}` WHERE entry_type = 'Income' AND entry_date = %s", $today ) );
    
    $month_income = (float) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(amount) FROM `{$table_accounting}` WHERE entry_type = 'Income' AND entry_date BETWEEN %s AND %s", $month_start, $month_end ) );
    
    $month_exp    = (float) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(amount) FROM `{$table_accounting}` WHERE entry_type = 'Expense' AND entry_date BETWEEN %s AND %s", $month_start, $month_end ) );
    
    $recent_trans = $wpdb->get_results( "SELECT voucher_no, title, entry_type, amount, entry_date FROM `{$table_accounting}` ORDER BY entry_date DESC, id DESC LIMIT 6" );
    // phpcs:enable

    educore_dashboard_render_hero_profile( $user, __( 'Accounts & Financial Officer', 'ifsedu-school-management' ), $extra_meta );
    ?>
    <div class="ifs-educore-dash-root">
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
            <div class="ifs-educore-stat-bento">
                <div class="ifs-educore-stat-top-row">
                    <div>
                        <span class="ifs-educore-sub-stat-label"><?php esc_html_e( 'Today Collected', 'ifsedu-school-management' ); ?></span>
                        <div style="font-size: 30px; font-weight: 800; color: #059669; margin-top: 6px;">৳<?php echo esc_html( number_format( $today_income, 2 ) ); ?></div>
                    </div>
                    <div class="ifs-educore-stat-icon-badge" style="background: #ecfdf5; color: #059669;">
                        <span class="dashicons dashicons-money"></span>
                    </div>
                </div>
            </div>

            <div class="ifs-educore-stat-bento">
                <div class="ifs-educore-stat-top-row">
                    <div>
                        <span class="ifs-educore-sub-stat-label"><?php esc_html_e( 'Month Collections', 'ifsedu-school-management' ); ?></span>
                        <div style="font-size: 30px; font-weight: 800; color: #2563eb; margin-top: 6px;">৳<?php echo esc_html( number_format( $month_income, 2 ) ); ?></div>
                    </div>
                    <div class="ifs-educore-stat-icon-badge" style="background: #eff6ff; color: #2563eb;">
                        <span class="dashicons dashicons-calendar-alt"></span>
                    </div>
                </div>
            </div>

            <div class="ifs-educore-stat-bento">
                <div class="ifs-educore-stat-top-row">
                    <div>
                        <span class="ifs-educore-sub-stat-label"><?php esc_html_e( 'Month Expenses', 'ifsedu-school-management' ); ?></span>
                        <div style="font-size: 30px; font-weight: 800; color: #dc2626; margin-top: 6px;">৳<?php echo esc_html( number_format( $month_exp, 2 ) ); ?></div>
                    </div>
                    <div class="ifs-educore-stat-icon-badge" style="background: #fef2f2; color: #dc2626;">
                        <span class="dashicons dashicons-cart"></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="ifs-educore-panel-card">
            <div class="ifs-educore-panel-header">
                <h3 class="ifs-educore-panel-title">
                    <span class="dashicons dashicons-list-view" style="color:#00523c;"></span>
                    <?php esc_html_e( 'Recent Accounting Ledger Records', 'ifsedu-school-management' ); ?>
                </h3>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=school_management_system&tab=accounting&sub=add' ) ); ?>" class="ifs-educore-command-tile" style="padding: 6px 14px; font-size: 12px; flex-direction:row;">
                    + <?php esc_html_e( 'Record Voucher', 'ifsedu-school-management' ); ?>
                </a>
            </div>
            <div style="overflow-x:auto;">
                <table class="ifs-educore-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Date', 'ifsedu-school-management' ); ?></th>
                            <th><?php esc_html_e( 'Voucher No', 'ifsedu-school-management' ); ?></th>
                            <th><?php esc_html_e( 'Title', 'ifsedu-school-management' ); ?></th>
                            <th><?php esc_html_e( 'Type', 'ifsedu-school-management' ); ?></th>
                            <th style="text-align: right;"><?php esc_html_e( 'Amount', 'ifsedu-school-management' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( ! empty( $recent_trans ) ) : foreach ( $recent_trans as $t ) : 
                            $trx_timestamp = strtotime( $t->entry_date );
                            $trx_date_formatted = $trx_timestamp ? date_i18n( 'd M Y', $trx_timestamp ) : '—';
                        ?>
                            <tr>
                                <td><?php echo esc_html( $trx_date_formatted ); ?></td>
                                <td><code><?php echo esc_html( $t->voucher_no ); ?></code></td>
                                <td><strong><?php echo esc_html( $t->title ); ?></strong></td>
                                <td>
                                    <span class="ifs-educore-badge-pill" style="<?php echo 'Income' === $t->entry_type ? 'background: #ecfdf5; color: #059669;' : 'background: #fef2f2; color: #dc2626;'; ?>">
                                        <?php echo esc_html( $t->entry_type ); ?>
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    <strong style="color: <?php echo 'Income' === $t->entry_type ? '#059669' : '#dc2626'; ?>;">
                                        ৳<?php echo esc_html( number_format( (float) $t->amount, 2 ) ); ?>
                                    </strong>
                                </td>
                            </tr>
                        <?php endforeach; else : ?>
                            <tr><td colspan="5" style="text-align:center; color:#94a3b8; padding:24px;"><?php esc_html_e( 'No transactions recorded yet.', 'ifsedu-school-management' ); ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php
}

// ==============================================================================
// 4. STUDENT / GUARDIAN PORTAL DASHBOARD
// ==============================================================================
function educore_student_guardian_dashboard_view( $user ) {
    global $wpdb;
    $table_students = $wpdb->prefix . 'sms_students';
    $table_notices  = $wpdb->prefix . 'sms_notices';

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $student = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT student_id, full_name, class_name, section_name, roll_no FROM `{$table_students}` WHERE student_email = %s LIMIT 1",
            $user->user_email
        )
    );

    $student_notices = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, title, event_date, publish_date FROM `{$table_notices}` WHERE target_audience IN (%s, %s) ORDER BY event_date DESC, id DESC LIMIT 4",
            'All',
            'Students'
        )
    );
    // phpcs:enable

    $extra_meta = array();
    if ( $student ) {
        $extra_meta[ __( 'Class', 'ifsedu-school-management' ) ]   = $student->class_name;
        $extra_meta[ __( 'Section', 'ifsedu-school-management' ) ] = $student->section_name ? $student->section_name : 'A';
        $extra_meta[ __( 'Roll', 'ifsedu-school-management' ) ]    = '#' . $student->roll_no;
        $extra_meta[ __( 'ID', 'ifsedu-school-management' ) ]      = $student->student_id;
    }

    educore_dashboard_render_hero_profile( $user, __( 'Student & Parent Academic Portal', 'ifsedu-school-management' ), $extra_meta );
    ?>
    <div class="ifs-educore-dash-root">
        <div class="ifs-educore-split-layout">
            <div class="ifs-educore-col">
                <div class="ifs-educore-panel-card ifs-card-fill">
                    <div class="ifs-educore-panel-header">
                        <h3 class="ifs-educore-panel-title">
                            <span class="dashicons dashicons-bell" style="color:#00523c;"></span>
                            <?php esc_html_e( 'School Notices & Announcements', 'ifsedu-school-management' ); ?>
                        </h3>
                    </div>
                    <?php if ( ! empty( $student_notices ) ) : ?>
                        <div class="ifs-educore-dash-list">
                            <?php foreach ( $student_notices as $n ) : 
                                $n_raw_date = ( ! empty( $n->event_date ) && '1970-01-01' !== $n->event_date ) ? $n->event_date : $n->publish_date;
                                $n_timestamp = strtotime( $n_raw_date );
                                $n_date_formatted = $n_timestamp ? date_i18n( 'd M Y', $n_timestamp ) : '—';
                                
                                $notice_url = add_query_arg(
                                    array(
                                        'page' => 'school_management_system',
                                        'tab'  => 'notices',
                                        'type' => 'notice',
                                        'sub'  => 'view',
                                        'id'   => intval( $n->id ),
                                    ),
                                    admin_url( 'admin.php' )
                                );
                            ?>
                                <div class="ifs-educore-list-item">
                                    <div>
                                        <strong style="color:#0f172a; font-size:14px;"><?php echo esc_html( $n->title ); ?></strong>
                                        <div style="font-size:12px; color:#64748b; margin-top:4px;">
                                            <?php echo esc_html( $n_date_formatted ); ?>
                                        </div>
                                    </div>
                                    <a href="<?php echo esc_url( $notice_url ); ?>" class="ifs-educore-command-tile" style="padding: 6px 14px; font-size: 12px; flex-direction:row;">
                                        <?php esc_html_e( 'Read', 'ifsedu-school-management' ); ?>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else : ?>
                        <p style="color:#94a3b8; font-size:13.5px; margin:0;"><?php esc_html_e( 'No active notices.', 'ifsedu-school-management' ); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="ifs-educore-col">
                <div class="ifs-educore-panel-card ifs-card-fill">
                    <div class="ifs-educore-panel-header">
                        <h3 class="ifs-educore-panel-title">
                            <span class="dashicons dashicons-id-alt" style="color:#00523c;"></span>
                            <?php esc_html_e( 'Student Documents & Cards', 'ifsedu-school-management' ); ?>
                        </h3>
                    </div>
                    <div style="display:flex; flex-direction:column; gap:12px;">
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=school_management_system&tab=students&sub=id_card' ) ); ?>" class="ifs-educore-command-tile" style="flex-direction: row; justify-content: flex-start; padding: 14px 18px; gap: 12px;">
                            <span class="dashicons dashicons-id" style="font-size:22px;"></span> 
                            <div style="text-align: left;">
                                <strong style="display:block; font-size:13.5px; color:#0f172a;"><?php esc_html_e( 'Digital ID Card', 'ifsedu-school-management' ); ?></strong>
                                <span style="font-size:11.5px; color:#64748b; font-weight:normal;"><?php esc_html_e( 'Generate & print official school ID', 'ifsedu-school-management' ); ?></span>
                            </div>
                        </a>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=school_management_system&tab=students&sub=admit_card' ) ); ?>" class="ifs-educore-command-tile" style="flex-direction: row; justify-content: flex-start; padding: 14px 18px; gap: 12px;">
                            <span class="dashicons dashicons-tickets-alt" style="font-size:22px;"></span> 
                            <div style="text-align: left;">
                                <strong style="display:block; font-size:13.5px; color:#0f172a;"><?php esc_html_e( 'Admit Card', 'ifsedu-school-management' ); ?></strong>
                                <span style="font-size:11.5px; color:#64748b; font-weight:normal;"><?php esc_html_e( 'Examination entry pass card', 'ifsedu-school-management' ); ?></span>
                            </div>
                        </a>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=school_management_system&tab=results&sub=report' ) ); ?>" class="ifs-educore-command-tile" style="flex-direction: row; justify-content: flex-start; padding: 14px 18px; gap: 12px;">
                            <span class="dashicons dashicons-media-document" style="font-size:22px;"></span> 
                            <div style="text-align: left;">
                                <strong style="display:block; font-size:13.5px; color:#0f172a;"><?php esc_html_e( 'Academic Transcript', 'ifsedu-school-management' ); ?></strong>
                                <span style="font-size:11.5px; color:#64748b; font-weight:normal;"><?php esc_html_e( 'Term marksheets & GPA records', 'ifsedu-school-management' ); ?></span>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}
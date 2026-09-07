<?php
/**
 * Teacher & Staff Attendance Router & Dispatcher
 * File: inc/attendance.php
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Direct access safety buffer
}

function educore_attendance_tab() {
    global $wpdb;

    $current_user = wp_get_current_user();
    $table_units  = $wpdb->prefix . 'sms_academic_units';
    $table_staff  = $wpdb->prefix . 'sms_staff';
    $table_assign = $wpdb->prefix . 'sms_teacher_subjects';

    // 1. Role & Capability Checks
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
        wp_die( esc_html__( 'You do not have sufficient permissions to access the attendance module.', 'ifsedu-school-management' ) );
    }

    $allowed_sub_tabs = array( 'daily', 'roster', 'exam', 'monthly', 'staff', 'reports' );

    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $raw_sub_tab = isset( $_GET['sub'] ) ? sanitize_key( wp_unslash( $_GET['sub'] ) ) : 'daily';
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    $sub_tab = in_array( $raw_sub_tab, $allowed_sub_tabs, true ) ? $raw_sub_tab : 'daily';

    // Restrict staff from accessing admin-only tabs
    $admin_only_tabs = array( 'staff', 'reports' );
    if ( ! $is_admin && in_array( $sub_tab, $admin_only_tabs, true ) ) {
        $sub_tab = 'daily';
    }

    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $filter_class   = isset( $_REQUEST['class_name'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['class_name'] ) ) : '';
    $filter_section = isset( $_REQUEST['section_name'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['section_name'] ) ) : '';
    $filter_date    = isset( $_REQUEST['attendance_date'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['attendance_date'] ) ) : current_time( 'Y-m-d' );
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    // 2. Fetch Assigned Classes & Sections
    $classes  = array();
    $sections = array();

    if ( ! $is_admin ) {
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $teacher = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id FROM `{$table_staff}` WHERE wp_user_id = %d OR email = %s LIMIT 1",
                $current_user->ID,
                $current_user->user_email
            )
        );

        if ( $teacher ) {
            $assigned_units = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT DISTINCT u.class_name, u.section_name 
                     FROM `{$table_assign}` ts
                     INNER JOIN `{$table_units}` u ON ts.class_id = u.id
                     WHERE ts.teacher_id = %d
                     ORDER BY CAST(u.class_name AS UNSIGNED) ASC, u.class_name ASC, u.section_name ASC",
                    $teacher->id
                )
            );

            if ( ! empty( $assigned_units ) ) {
                foreach ( $assigned_units as $unit ) {
                    $c_val = trim( (string) $unit->class_name );
                    $s_val = trim( (string) $unit->section_name );
                    if ( ! empty( $c_val ) && ! in_array( $c_val, $classes, true ) ) {
                        $classes[] = $c_val;
                    }
                    if ( ! empty( $s_val ) && ! in_array( $s_val, $sections, true ) ) {
                        $sections[] = $s_val;
                    }
                }
            }
        }

        // Fallback: If no specific units assigned, allow all active classes
        if ( empty( $classes ) ) {
            $raw_classes = $wpdb->get_results( "SELECT DISTINCT class_name FROM `{$table_units}` WHERE class_name != '' ORDER BY CAST(class_name AS UNSIGNED) ASC, class_name ASC" );
            if ( ! empty( $raw_classes ) ) {
                foreach ( $raw_classes as $cls_obj ) {
                    $c_val = trim( (string) $cls_obj->class_name );
                    if ( ! empty( $c_val ) && ! in_array( $c_val, $classes, true ) ) {
                        $classes[] = $c_val;
                    }
                }
            }
        }
        // phpcs:enable

        if ( empty( $filter_class ) && ! empty( $classes[0] ) ) {
            $filter_class = $classes[0];
        }
        if ( empty( $filter_section ) && ! empty( $sections[0] ) ) {
            $filter_section = $sections[0];
        }
    } else {
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $raw_classes = $wpdb->get_results( "SELECT DISTINCT class_name FROM `{$table_units}` WHERE class_name != '' ORDER BY CAST(class_name AS UNSIGNED) ASC, class_name ASC" );
        // phpcs:enable
        
        if ( ! empty( $raw_classes ) ) {
            foreach ( $raw_classes as $cls_obj ) {
                $c_val = trim( (string) $cls_obj->class_name );
                if ( ! empty( $c_val ) && ! in_array( $c_val, $classes, true ) ) {
                    $classes[] = $c_val;
                }
            }
        }
    }

    if ( ! empty( $filter_class ) && empty( $sections ) ) {
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $raw_sections = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DISTINCT section_name FROM `{$table_units}` WHERE class_name = %s AND section_name != '' ORDER BY section_name ASC",
                $filter_class
            )
        );
        // phpcs:enable
        
        if ( ! empty( $raw_sections ) ) {
            foreach ( $raw_sections as $sec_obj ) {
                $s_val = trim( (string) $sec_obj->section_name );
                if ( ! empty( $s_val ) && ! in_array( $s_val, $sections, true ) ) {
                    $sections[] = $s_val;
                }
            }
        }
    }

    // Dynamic Navigation URLs
    $base_admin_url = admin_url( 'admin.php' );
    $daily_url      = add_query_arg( array( 'page' => 'school_management_system', 'tab' => 'attendance', 'sub' => 'daily' ), $base_admin_url );
    $exam_url       = add_query_arg( array( 'page' => 'school_management_system', 'tab' => 'attendance', 'sub' => 'exam' ), $base_admin_url );
    $monthly_url    = add_query_arg( array( 'page' => 'school_management_system', 'tab' => 'attendance', 'sub' => 'monthly' ), $base_admin_url );
    $staff_url      = add_query_arg( array( 'page' => 'school_management_system', 'tab' => 'attendance', 'sub' => 'staff' ), $base_admin_url );
    $reports_url    = add_query_arg( array( 'page' => 'school_management_system', 'tab' => 'attendance', 'sub' => 'reports' ), $base_admin_url );
    ?>

    <div class="ifs-educore-attendance-root">
        
        <!-- Sub-Navigation Header Bar -->
        <div class="ifs-educore-top-nav-wrapper no-print">
            <div class="ifs-educore-nav-button-group">
                <a href="<?php echo esc_url( $daily_url ); ?>" class="ifs-educore-nav-link <?php echo ( 'daily' === $sub_tab || 'roster' === $sub_tab ) ? 'ifs-educore-nav-link-active' : 'ifs-educore-nav-link-inactive'; ?>">
                    <span class="dashicons dashicons-clipboard"></span>
                    <?php esc_html_e( 'Daily Attendance', 'ifsedu-school-management' ); ?>
                </a>

                <a href="<?php echo esc_url( $exam_url ); ?>" class="ifs-educore-nav-link <?php echo ( 'exam' === $sub_tab ) ? 'ifs-educore-nav-link-active' : 'ifs-educore-nav-link-inactive'; ?>">
                    <span class="dashicons dashicons-welcome-write-blog"></span>
                    <?php esc_html_e( 'Exam Attendance', 'ifsedu-school-management' ); ?>
                </a>

                <a href="<?php echo esc_url( $monthly_url ); ?>" class="ifs-educore-nav-link <?php echo ( 'monthly' === $sub_tab ) ? 'ifs-educore-nav-link-active' : 'ifs-educore-nav-link-inactive'; ?>">
                    <span class="dashicons dashicons-chart-bar"></span>
                    <?php esc_html_e( 'Monthly Summary', 'ifsedu-school-management' ); ?>
                </a>

                <?php if ( $is_admin ) : ?>
                    <a href="<?php echo esc_url( $staff_url ); ?>" class="ifs-educore-nav-link <?php echo ( 'staff' === $sub_tab ) ? 'ifs-educore-nav-link-active' : 'ifs-educore-nav-link-inactive'; ?>">
                        <span class="dashicons dashicons-businessman"></span>
                        <?php esc_html_e( 'Staff Attendance', 'ifsedu-school-management' ); ?>
                    </a>

                    <a href="<?php echo esc_url( $reports_url ); ?>" class="ifs-educore-nav-link <?php echo ( 'reports' === $sub_tab ) ? 'ifs-educore-nav-link-active' : 'ifs-educore-nav-link-inactive'; ?>">
                        <span class="dashicons dashicons-search"></span>
                        <?php esc_html_e( 'Attendance Reports', 'ifsedu-school-management' ); ?>
                    </a>
                <?php endif; ?>
            </div>

            <?php if ( ! $is_admin && ! empty( $classes ) ) : ?>
                <div class="ifs-educore-assigned-pill">
                    <span class="dashicons dashicons-id-alt" style="font-size:14px; width:14px; height:14px;"></span>
                    <?php 
                        $sec_label = ! empty( $sections ) ? ' (' . implode( ', ', $sections ) . ')' : '';
                        printf(
                            /* translators: 1: Class name(s), 2: Section label */
                            esc_html__( 'Assigned: Class %1$s%2$s', 'ifsedu-school-management' ),
                            esc_html( implode( ', ', $classes ) ),
                            esc_html( $sec_label )
                        ); 
                    ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="ifs-educore-module-viewport-container">
            <?php
            $attendance_dir = EDUCORE_PATH . 'inc/attendance/';

            switch ( $sub_tab ) {
                case 'exam':
                    $exam_file = $attendance_dir . 'attendance-exam.php';
                    if ( file_exists( $exam_file ) ) {
                        require_once $exam_file;
                    }
                    if ( function_exists( 'educore_exam_attendance_view' ) ) {
                        educore_exam_attendance_view();
                    }
                    break;

                case 'monthly':
                    $monthly_file = $attendance_dir . 'attendance-monthly.php';
                    if ( file_exists( $monthly_file ) ) {
                        require_once $monthly_file;
                    }
                    if ( function_exists( 'educore_monthly_attendance_summary_view' ) ) {
                        educore_monthly_attendance_summary_view( $classes, $sections, $filter_class, $filter_section );
                    }
                    break;

                case 'staff':
                    if ( $is_admin ) {
                        $staff_file = $attendance_dir . 'attendance-staff.php';
                        if ( file_exists( $staff_file ) ) {
                            require_once $staff_file;
                        }
                        if ( function_exists( 'educore_staff_attendance_view' ) ) {
                            educore_staff_attendance_view();
                        }
                    }
                    break;

                case 'reports':
                    if ( $is_admin ) {
                        $reports_file = $attendance_dir . 'attendance-reports.php';
                        if ( file_exists( $reports_file ) ) {
                            require_once $reports_file;
                        }
                        if ( function_exists( 'educore_student_attendance_log_view' ) ) {
                            educore_student_attendance_log_view( $classes );
                        }
                    }
                    break;

                case 'daily':
                case 'roster':
                default:
                    $daily_file = $attendance_dir . 'attendance-daily.php';
                    if ( file_exists( $daily_file ) ) {
                        require_once $daily_file;
                    }
                    if ( function_exists( 'educore_daily_attendance_view' ) ) {
                        educore_daily_attendance_view( $classes, $sections, $filter_class, $filter_section, $filter_date );
                    }
                    break;
            }
            ?>
        </div>
    </div>
    <?php
}
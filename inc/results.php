<?php
/**
 * Academic Results & Evaluation Matrix Module Router
 * File: inc/results.php
 * Subtabs: Marks Entry Matrix, Mark Report, Progress & Tabulation Sheet, Merit List & Positions
 * Custom Prefixes Applied: dpt-, afdp-
 * Text Domain: ifsedu-school-management
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Load Modular Dependency Sub-Files.
$educore_results_dir = defined( 'EDUCORE_PATH' ) ? EDUCORE_PATH . 'inc/results/' : plugin_dir_path( __FILE__ ) . 'results/';

if ( file_exists( $educore_results_dir . 'exams-marks.php' ) ) {
    require_once $educore_results_dir . 'exams-marks.php';
}
if ( file_exists( $educore_results_dir . 'exams-mark-report.php' ) ) {
    require_once $educore_results_dir . 'exams-mark-report.php';
}
if ( file_exists( $educore_results_dir . 'exams-report.php' ) ) {
    require_once $educore_results_dir . 'exams-report.php';
}
if ( file_exists( $educore_results_dir . 'exams-merit.php' ) ) {
    require_once $educore_results_dir . 'exams-merit.php';
}

/**
 * Main Results Tab View Router
 */
function educore_results_tab(): void {
    global $wpdb;

    $current_user = wp_get_current_user();
    $table_staff  = $wpdb->prefix . 'sms_staff';

    // 1. Procedural Role Capability Validations.
    $is_admin = current_user_can( 'manage_options' ) || in_array( 'administrator', (array) $current_user->roles, true );

    $is_staff = false;
    if ( function_exists( 'educore_has_access' ) ) {
        $is_staff = educore_has_access( array( 'teacher', 'staff', 'operator', 'instructor', 'editor', 'author', 'contributor', 'subscriber' ) );
    }

    if ( ! $is_staff && ! $is_admin ) {
        $staff_exists = $wpdb->get_var(
            $wpdb->prepare(
                'SELECT id FROM %i WHERE wp_user_id = %d OR email = %s LIMIT 1',
                $table_staff,
                $current_user->ID,
                $current_user->user_email
            )
        );
        if ( $staff_exists ) {
            $is_staff = true;
        }
    }

    if ( ! $is_admin && ! $is_staff ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to access examination marks & results.', 'ifsedu-school-management' ) );
    }

    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $sub_tab = isset( $_GET['sub'] ) ? sanitize_key( wp_unslash( $_GET['sub'] ) ) : 'marks';
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    // 2. Role Boundary: Allow Teachers/Staff access to 'marks', 'mark-report', and 'report'.
    $allowed_teacher_tabs = array( 'marks', 'mark-report', 'report' );
    if ( ! $is_admin && ! in_array( $sub_tab, $allowed_teacher_tabs, true ) ) {
        $sub_tab = 'marks';
    }

    // 3. Query Assigned Classes & Subjects for Logged-In Teacher.
    $assigned_teacher_info = array();
    if ( ! $is_admin ) {
        $table_teacher_subjects = $wpdb->prefix . 'sms_teacher_subjects';
        $table_subjects         = $wpdb->prefix . 'sms_subjects';
        $table_units            = $wpdb->prefix . 'sms_academic_units';

        $teacher_id = $wpdb->get_var(
            $wpdb->prepare(
                'SELECT id FROM %i WHERE wp_user_id = %d OR email = %s OR full_name = %s LIMIT 1',
                $table_staff,
                $current_user->ID,
                $current_user->user_email,
                $current_user->display_name
            )
        );

        if ( $teacher_id ) {
            $assigned_teacher_info = $wpdb->get_results(
                $wpdb->prepare(
                    'SELECT DISTINCT u.class_name, u.section_name, s.subject_name, s.subject_code 
                     FROM %i ts
                     INNER JOIN %i u ON ts.class_id = u.id
                     INNER JOIN %i s ON ts.subject_id = s.id
                     WHERE ts.teacher_id = %d AND u.class_name != %s
                     ORDER BY CAST(u.class_name AS UNSIGNED) ASC, u.class_name ASC, s.subject_name ASC',
                    $table_teacher_subjects,
                    $table_units,
                    $table_subjects,
                    $teacher_id,
                    ''
                )
            );
        }
    }

    // Construct URLs for Submenu Tabs.
    $marks_url       = admin_url( 'admin.php?page=school_management_system&tab=results&sub=marks' );
    $mark_report_url = admin_url( 'admin.php?page=school_management_system&tab=results&sub=mark-report' );
    $report_url      = admin_url( 'admin.php?page=school_management_system&tab=results&sub=report' );
    $merit_url       = admin_url( 'admin.php?page=school_management_system&tab=results&sub=merit' );
    ?>

    <style id="dpt-results-nav-styles">
        .dpt-results-nav-root {
            margin: 20px 20px 24px 0;
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #0f172a;
        }

        .afdp-results-nav-bar {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 16px 20px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            box-shadow: 0 4px 15px -3px rgba(0, 0, 0, 0.03);
        }

        .dpt-nav-button-group {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .dpt-nav-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 18px;
            border-radius: 10px;
            font-size: 13.5px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.2s ease-in-out;
            border: 1px solid transparent;
        }

        .dpt-nav-link .dashicons {
            font-size: 18px;
            width: 18px;
            height: 18px;
        }

        .dpt-nav-link-inactive {
            color: #64748b;
            background: #f8fafc;
            border-color: #e2e8f0;
        }

        .dpt-nav-link-inactive .dashicons {
            color: #64748b;
        }

        .dpt-nav-link-inactive:hover {
            color: #00523c;
            background: #f0fdf4;
            border-color: #a7f3d0;
        }

        .dpt-nav-link-inactive:hover .dashicons {
            color: #00523c;
        }

        .dpt-nav-link-active {
            color: #ffffff !important;
            background: #00523c !important;
            border-color: #00523c !important;
            box-shadow: 0 4px 12px rgba(0, 106, 78, 0.2);
        }

        .dpt-nav-link-active .dashicons {
            color: #a7f3d0 !important;
        }

        .dpt-assigned-context-pill {
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            color: #065f46;
            font-size: 12px;
            font-weight: 700;
            padding: 5px 12px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .dpt-assigned-context-pill .dashicons {
            font-size: 14px;
            width: 14px;
            height: 14px;
        }

        .dpt-status-context-pill {
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            color: #475569;
            font-size: 12px;
            font-weight: 700;
            padding: 5px 12px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .dpt-status-context-pill .dashicons {
            font-size: 14px;
            width: 14px;
            height: 14px;
            vertical-align: middle;
        }

        .dpt-module-viewport-container {
            width: 100%;
        }

        @media print {
            .no-print {
                display: none !important;
            }
        }
    </style>

    <div class="dpt-results-nav-root">
        
        <!-- Top Sub-Navigation Menu Bar -->
        <div class="afdp-results-nav-bar no-print">
            <div class="dpt-nav-button-group">
                <!-- 1. Marks Entry Matrix -->
                <a href="<?php echo esc_url( $marks_url ); ?>" 
                   class="dpt-nav-link <?php echo ( $sub_tab === 'marks' ) ? 'dpt-nav-link-active' : 'dpt-nav-link-inactive'; ?>">
                    <span class="dashicons dashicons-edit"></span>
                    <?php esc_html_e( 'Marks Entry Matrix', 'ifsedu-school-management' ); ?>
                </a>

                <!-- 2. Mark Report (New Subtab) -->
                <a href="<?php echo esc_url( $mark_report_url ); ?>" 
                   class="dpt-nav-link <?php echo ( $sub_tab === 'mark-report' ) ? 'dpt-nav-link-active' : 'dpt-nav-link-inactive'; ?>">
                    <span class="dashicons dashicons-media-document"></span>
                    <?php esc_html_e( 'Mark Report', 'ifsedu-school-management' ); ?>
                </a>
                
                <!-- 3. Progress & Tabulation Sheet -->
                <a href="<?php echo esc_url( $report_url ); ?>" 
                   class="dpt-nav-link <?php echo ( $sub_tab === 'report' ) ? 'dpt-nav-link-active' : 'dpt-nav-link-inactive'; ?>">
                    <span class="dashicons dashicons-clipboard"></span>
                    <?php esc_html_e( 'Progress & Tabulation Sheet', 'ifsedu-school-management' ); ?>
                </a>

                <!-- 4. Merit List & Positions (Admin Only) -->
                <?php if ( $is_admin ) : ?>
                    <a href="<?php echo esc_url( $merit_url ); ?>" 
                       class="dpt-nav-link <?php echo ( $sub_tab === 'merit' ) ? 'dpt-nav-link-active' : 'dpt-nav-link-inactive'; ?>">
                        <span class="dashicons dashicons-awards"></span>
                        <?php esc_html_e( 'Merit List & Positions', 'ifsedu-school-management' ); ?>
                    </a>
                <?php endif; ?>
            </div>

            <div>
                <?php if ( ! $is_admin && ! empty( $assigned_teacher_info ) ) : ?>
                    <span class="dpt-assigned-context-pill">
                        <span class="dashicons dashicons-welcome-learn-more"></span>
                        <?php
                        printf(
                            /* translators: %d: Number of subject allocations assigned to the teacher */
                            esc_html__( 'Assigned Subjects: %d Allocations', 'ifsedu-school-management' ),
                            count( $assigned_teacher_info )
                        );
                        ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <!-- System Sub-View Execution Core -->
        <div class="dpt-module-viewport-container">
            <?php
            switch ( $sub_tab ) {
                case 'mark-report':
                    if ( function_exists( 'educore_exam_mark_report_view' ) ) {
                        educore_exam_mark_report_view();
                    } else {
                        echo '<div class="afdp-notice-card">' . esc_html__( 'Mark Report module is initializing.', 'ifsedu-school-management' ) . '</div>';
                    }
                    break;

                case 'report':
                    if ( function_exists( 'educore_exams_report_view' ) ) {
                        educore_exams_report_view();
                    } else {
                        echo '<div class="afdp-notice-card">' . esc_html__( 'Progress & Tabulation Sheet module is initializing.', 'ifsedu-school-management' ) . '</div>';
                    }
                    break;

                case 'merit':
                    if ( $is_admin ) {
                        if ( function_exists( 'educore_merit_list_view' ) ) {
                            educore_merit_list_view();
                        } else {
                            echo '<div class="afdp-notice-card">' . esc_html__( 'Merit List module is initializing.', 'ifsedu-school-management' ) . '</div>';
                        }
                    }
                    break;

                case 'marks':
                default:
                    if ( function_exists( 'educore_exams_marks_view' ) ) {
                        educore_exams_marks_view();
                    } else {
                        echo '<div class="afdp-notice-card">' . esc_html__( 'Marks Entry Matrix module is initializing.', 'ifsedu-school-management' ) . '</div>';
                    }
                    break;
            }
            ?>
        </div>
    </div>
    <?php
}
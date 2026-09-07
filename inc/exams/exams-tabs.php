<?php
/**
 * High-End Academic Examinations Sub-Navigation Engine & Router Matrix
 * File: inc/exams.php
 * Text Domain: ifsedu-school-management
 * Subtabs: All Examinations, Add Examination, Exam Routine, Exam Question Generator, Exam Details View
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Immediate access layer lockdown
}

function educore_exams_tab() {
    if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'edit_posts' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to access the examinations module.', 'ifsedu-school-management' ) );
    }

    $allowed_sub_tabs = array( 'list', 'add', 'edit', 'view', 'routine', 'questions' );

    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $raw_sub_tab = isset( $_GET['sub'] ) ? sanitize_key( wp_unslash( $_GET['sub'] ) ) : 'list';
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    $sub_tab = in_array( $raw_sub_tab, $allowed_sub_tabs, true ) ? $raw_sub_tab : 'list';

    // Construct URLs for top submenu links using add_query_arg()
    $base_admin_url     = admin_url( 'admin.php' );
    $all_exams_url      = add_query_arg( array( 'page' => 'school_management_system', 'tab' => 'exams', 'sub' => 'list' ), $base_admin_url );
    $add_exam_url       = add_query_arg( array( 'page' => 'school_management_system', 'tab' => 'exams', 'sub' => 'add' ), $base_admin_url );
    $exam_routine_url   = add_query_arg( array( 'page' => 'school_management_system', 'tab' => 'exams', 'sub' => 'routine' ), $base_admin_url );
    $question_gen_url   = add_query_arg( array( 'page' => 'school_management_system', 'tab' => 'exams', 'sub' => 'questions' ), $base_admin_url );
    ?>

    <div class="dpt-exams-nav-root">
        
        <!-- Top Sub-Navigation Menu Bar -->
        <div class="afdp-top-nav-wrapper no-print">
            <div class="dpt-nav-button-group">
                <!-- 1. All Examinations -->
                <a href="<?php echo esc_url( $all_exams_url ); ?>" 
                   class="dpt-nav-link <?php echo ( 'list' === $sub_tab ) ? 'dpt-nav-link-active' : 'dpt-nav-link-inactive'; ?>">
                    <span class="dashicons dashicons-welcome-write-blog"></span>
                    <?php esc_html_e( 'All Examinations', 'ifsedu-school-management' ); ?>
                </a>
                
                <!-- 2. Add Examination -->
                <a href="<?php echo esc_url( $add_exam_url ); ?>" 
                   class="dpt-nav-link <?php echo ( 'add' === $sub_tab || 'edit' === $sub_tab ) ? 'dpt-nav-link-active' : 'dpt-nav-link-inactive'; ?>">
                    <span class="dashicons dashicons-plus-alt2"></span>
                    <?php esc_html_e( 'Add Examination', 'ifsedu-school-management' ); ?>
                </a>
                
                <!-- 3. Exam Routine -->
                <a href="<?php echo esc_url( $exam_routine_url ); ?>" 
                   class="dpt-nav-link <?php echo ( 'routine' === $sub_tab ) ? 'dpt-nav-link-active' : 'dpt-nav-link-inactive'; ?>">
                    <span class="dashicons dashicons-calendar-alt"></span>
                    <?php esc_html_e( 'Exam Routine', 'ifsedu-school-management' ); ?>
                </a>

                <!-- 4. Question Generator -->
                <a href="<?php echo esc_url( $question_gen_url ); ?>" 
                   class="dpt-nav-link <?php echo ( 'questions' === $sub_tab ) ? 'dpt-nav-link-active' : 'dpt-nav-link-inactive'; ?>">
                    <span class="dashicons dashicons-editor-help"></span>
                    <?php esc_html_e( 'Question Generator', 'ifsedu-school-management' ); ?>
                </a>
            </div>

            <?php if ( 'edit' === $sub_tab ) : ?>
                <span style="background:#eff6ff; color:#1d4ed8; font-size:12px; font-weight:700; padding:5px 12px; border-radius:20px; border:1px solid #bfdbfe;">
                    <?php esc_html_e( 'Editing Exam Scheme', 'ifsedu-school-management' ); ?>
                </span>
            <?php elseif ( 'view' === $sub_tab ) : ?>
                <span style="background:#f0fdf4; color:#047857; font-size:12px; font-weight:700; padding:5px 12px; border-radius:20px; border:1px solid #bbf7d0;">
                    <?php esc_html_e( 'Viewing Scheme Details', 'ifsedu-school-management' ); ?>
                </span>
            <?php endif; ?>
        </div>

        <!-- Viewport Loader -->
        <div class="dpt-module-viewport-container">
            <?php
            $exam_dir = defined( 'EDUCORE_PATH' ) ? EDUCORE_PATH . 'inc/exams/' : plugin_dir_path( __FILE__ ) . 'exams/';

            switch ( $sub_tab ) {
                case 'view':
                    $view_files = array(
                        $exam_dir . 'exam-view.php',
                        $exam_dir . 'exams-view.php',
                    );
                    foreach ( $view_files as $file ) {
                        if ( file_exists( $file ) ) {
                            require_once $file;
                            break;
                        }
                    }
                    if ( function_exists( 'educore_exam_single_view' ) ) {
                        educore_exam_single_view();
                    } elseif ( function_exists( 'educore_exam_view' ) ) {
                        educore_exam_view();
                    }
                    break;

                case 'add':
                case 'edit':
                    $add_edit_files = array(
                        $exam_dir . 'exam-add-edit.php',
                        $exam_dir . 'exams-add-edit.php',
                    );
                    foreach ( $add_edit_files as $file ) {
                        if ( file_exists( $file ) ) {
                            require_once $file;
                            break;
                        }
                    }
                    if ( function_exists( 'educore_exam_add_edit_view' ) ) {
                        educore_exam_add_edit_view();
                    } elseif ( function_exists( 'educore_exams_add_view' ) ) {
                        educore_exams_add_view();
                    }
                    break;

                case 'routine':
                    $routine_files = array(
                        $exam_dir . 'exam-routine.php',
                        $exam_dir . 'exams-routine.php',
                    );
                    foreach ( $routine_files as $file ) {
                        if ( file_exists( $file ) ) {
                            require_once $file;
                            break;
                        }
                    }
                    if ( function_exists( 'educore_exam_routine_view' ) ) {
                        educore_exam_routine_view();
                    } elseif ( function_exists( 'educore_exams_routine_view' ) ) {
                        educore_exams_routine_view();
                    }
                    break;

                case 'questions':
                    $question_files = array(
                        $exam_dir . 'exam-questions.php',
                    );
                    foreach ( $question_files as $file ) {
                        if ( file_exists( $file ) ) {
                            require_once $file;
                            break;
                        }
                    }
                    if ( function_exists( 'educore_exam_questions_view' ) ) {
                        educore_exam_questions_view();
                    } elseif ( function_exists( 'educore_exam_question_generator_view' ) ) {
                        educore_exam_question_generator_view();
                    } elseif ( function_exists( 'educore_exams_questions_view' ) ) {
                        educore_exams_questions_view();
                    }
                    break;

                case 'list':
                default:
                    $list_files = array(
                        $exam_dir . 'exam-list.php',
                        $exam_dir . 'exams-list.php',
                        $exam_dir . 'exams-list-view.php',
                    );
                    foreach ( $list_files as $file ) {
                        if ( file_exists( $file ) ) {
                            require_once $file;
                            break;
                        }
                    }
                    if ( function_exists( 'educore_exam_list_view' ) ) {
                        educore_exam_list_view();
                    } elseif ( function_exists( 'educore_exams_list_view' ) ) {
                        educore_exams_list_view();
                    }
                    break;
            }
            ?>
        </div>
    </div>
    <?php
}
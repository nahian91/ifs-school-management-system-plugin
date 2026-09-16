<?php
/**
 * Academic Operations & Dashboard Router Matrix
 * File: inc/academics.php
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'edit_posts' ) ) {
    wp_die( esc_html__( 'You do not have sufficient permissions to access the academics module.', 'ifsedu-school-management' ) );
}

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$educore_allowed_subtabs = array( 'units', 'subjects', 'teacher_subjects', 'routine', 'available_teachers' );

// Process Status Messages.
$educore_message_text = '';
// phpcs:disable WordPress.Security.NonceVerification.Recommended
if ( isset( $_GET['status'] ) ) {
    $educore_status = sanitize_key( wp_unslash( $_GET['status'] ) );
    if ( 'success' === $educore_status ) {
        $educore_message_text = esc_html__( 'Class added successfully.', 'ifsedu-school-management' );
    } elseif ( 'updated' === $educore_status ) {
        $educore_message_text = esc_html__( 'Class updated successfully.', 'ifsedu-school-management' );
    } elseif ( 'deleted' === $educore_status ) {
        $educore_message_text = esc_html__( 'Record deleted successfully.', 'ifsedu-school-management' );
    } elseif ( 'subjects_added' === $educore_status ) {
        $educore_count = isset( $_GET['count'] ) ? absint( wp_unslash( $_GET['count'] ) ) : 0;
        /* translators: %s: Number of subjects added */
        $educore_message_text = sprintf(
            esc_html(
                /* translators: %s: number of subjects */
                _n( 'Successfully added %s subject.', 'Successfully added %s subjects.', $educore_count, 'ifsedu-school-management' )
            ),
            number_format_i18n( $educore_count )
        );
    }
}

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$educore_raw_subtab = isset( $_GET['subtab'] ) ? sanitize_key( wp_unslash( $_GET['subtab'] ) ) : 'units';
// phpcs:enable WordPress.Security.NonceVerification.Recommended

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$educore_current_subtab = in_array( $educore_raw_subtab, $educore_allowed_subtabs, true ) ? $educore_raw_subtab : 'units';

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$educore_base_admin_url = admin_url( 'admin.php' );
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$educore_base_url       = add_query_arg( array( 'page' => 'school_management_system', 'tab' => 'academics' ), $educore_base_admin_url );
?>

<style id="ifs-educore-academics-router-styles">
    .ifs-educore-academics-root {
        font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        color: #0f172a;
    }

    .ifs-educore-tab-nav {
        display: flex;
        gap: 10px;
        align-items: center;
        flex-wrap: wrap;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        padding: 16px 20px;
        border-radius: 16px;
        box-shadow: 0 4px 15px -3px rgba(0, 0, 0, 0.03);
        margin-bottom: 24px;
    }

    .ifs-educore-tab-link {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 9px 18px;
        border-radius: 10px;
        font-size: 13.5px;
        font-weight: 700;
        text-decoration: none;
        transition: all 0.2s ease-in-out;
        border: 1px solid #e2e8f0;
        color: #64748b;
        background: #f8fafc;
    }

    .ifs-educore-tab-link:hover {
        color: #00523c;
        background: #f0fdf4;
        border-color: #a7f3d0;
    }

    .ifs-educore-tab-link .dashicons {
        font-size: 18px;
        width: 18px;
        height: 18px;
    }

    .ifs-educore-tab-link-active {
        color: #ffffff !important;
        background: #00523c !important;
        border-color: #00523c !important;
        box-shadow: 0 4px 12px rgba(0, 106, 78, 0.2);
    }

    .ifs-educore-tab-link-active .dashicons {
        color: #a7f3d0 !important;
    }

    .ifs-educore-alert-node {
        padding: 14px 18px;
        border-radius: 10px;
        font-size: 13.5px;
        font-weight: 600;
        margin-bottom: 24px;
        display: flex;
        align-items: center;
        gap: 10px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.03);
    }

    .ifs-educore-alert-success {
        background: #ecfdf5;
        border: 1px solid #a7f3d0;
        color: #047857;
    }

    .ifs-educore-subtab-viewport {
        width: 100%;
    }
</style>

<div class="ifs-educore-academics-root">

    <!-- Sub-Tab Navigation -->
    <div class="ifs-educore-tab-nav">
        <a href="<?php echo esc_url( add_query_arg( 'subtab', 'units', $educore_base_url ) ); ?>" class="ifs-educore-tab-link <?php echo 'units' === $educore_current_subtab ? 'ifs-educore-tab-link-active' : ''; ?>">
            <span class="dashicons dashicons-category"></span> <?php esc_html_e( 'Classes Setup', 'ifsedu-school-management' ); ?>
        </a>
        <a href="<?php echo esc_url( add_query_arg( 'subtab', 'subjects', $educore_base_url ) ); ?>" class="ifs-educore-tab-link <?php echo 'subjects' === $educore_current_subtab ? 'ifs-educore-tab-link-active' : ''; ?>">
            <span class="dashicons dashicons-book"></span> <?php esc_html_e( 'Class Wise Subjects', 'ifsedu-school-management' ); ?>
        </a>
        <a href="<?php echo esc_url( add_query_arg( 'subtab', 'teacher_subjects', $educore_base_url ) ); ?>" class="ifs-educore-tab-link <?php echo 'teacher_subjects' === $educore_current_subtab ? 'ifs-educore-tab-link-active' : ''; ?>">
            <span class="dashicons dashicons-businessman"></span> <?php esc_html_e( 'Teacher Wise Subjects', 'ifsedu-school-management' ); ?>
        </a>
        <a href="<?php echo esc_url( add_query_arg( 'subtab', 'routine', $educore_base_url ) ); ?>" class="ifs-educore-tab-link <?php echo 'routine' === $educore_current_subtab ? 'ifs-educore-tab-link-active' : ''; ?>">
            <span class="dashicons dashicons-calendar-alt"></span> <?php esc_html_e( 'Class Routine', 'ifsedu-school-management' ); ?>
        </a>
        <a href="<?php echo esc_url( add_query_arg( 'subtab', 'available_teachers', $educore_base_url ) ); ?>" class="ifs-educore-tab-link <?php echo 'available_teachers' === $educore_current_subtab ? 'ifs-educore-tab-link-active' : ''; ?>">
            <span class="dashicons dashicons-clock"></span> <?php esc_html_e( 'Available Teachers', 'ifsedu-school-management' ); ?>
        </a>
    </div>

    <!-- Feedback Notice -->
    <?php if ( ! empty( $educore_message_text ) ) : ?>
        <div class="ifs-educore-alert-node ifs-educore-alert-success">
            <span class="dashicons dashicons-yes-alt"></span>
            <strong><?php esc_html_e( 'Success:', 'ifsedu-school-management' ); ?></strong> <?php echo esc_html( $educore_message_text ); ?>
        </div>
    <?php endif; ?>

    <!-- Subtab Viewport Execution Core -->
    <div class="ifs-educore-subtab-viewport">
        <?php
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
        $educore_academics_dir = defined( 'EDUCORE_PATH' ) ? EDUCORE_PATH . 'inc/academics/' : plugin_dir_path( __FILE__ ) . 'academics/';

        switch ( $educore_current_subtab ) {
            case 'subjects':
                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
                $educore_file = $educore_academics_dir . 'subjects.php';
                if ( file_exists( $educore_file ) ) {
                    require_once $educore_file;
                }
                if ( function_exists( 'educore_academics_subjects_view' ) ) {
                    educore_academics_subjects_view();
                }
                break;

            case 'teacher_subjects':
                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
                $educore_file = $educore_academics_dir . 'teacher-subjects.php';
                if ( file_exists( $educore_file ) ) {
                    require_once $educore_file;
                }
                if ( function_exists( 'educore_academics_teacher_subjects_view' ) ) {
                    educore_academics_teacher_subjects_view();
                }
                break;

            case 'routine':
                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
                $educore_file = $educore_academics_dir . 'routine.php';
                if ( file_exists( $educore_file ) ) {
                    require_once $educore_file;
                }
                if ( function_exists( 'educore_academics_routine_view' ) ) {
                    educore_academics_routine_view();
                }
                break;

            case 'available_teachers':
                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
                $educore_file = $educore_academics_dir . 'academic-available-teachers.php';
                
                // Fallback check in case the file is named 'available-teachers.php' instead
                if ( ! file_exists( $educore_file ) && file_exists( $educore_academics_dir . 'available-teachers.php' ) ) {
                    $educore_file = $educore_academics_dir . 'available-teachers.php';
                }

                if ( file_exists( $educore_file ) ) {
                    require_once $educore_file;
                }
                
                if ( function_exists( 'educore_academics_available_teachers_view' ) ) {
                    educore_academics_available_teachers_view();
                } else {
                    echo '<div style="padding:20px; color:red; border:1px solid red; background:#fff;">Error: The function educore_academics_available_teachers_view() could not be found. Please ensure the file was saved correctly.</div>';
                }
                break;

            case 'units':
            default:
                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
                $educore_file = $educore_academics_dir . 'units.php';
                if ( file_exists( $educore_file ) ) {
                    require_once $educore_file;
                }
                if ( function_exists( 'educore_academics_units_view' ) ) {
                    educore_academics_units_view();
                }
                break;
        }
        ?>
    </div>

</div>
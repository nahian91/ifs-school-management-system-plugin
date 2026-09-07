<?php
/**
 * High-End Academic Analytics Reports Sub-Navigation Engine & Router Matrix
 * File: inc/reports.php
 * Text Domain: ifsedu-school-management
 * Architecture: Bento Layout Viewports with Integrated Hardware Print Lockdown
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Immediate access layer lockdown
}

function educore_reports_tab() {
    // Strict Capability Check
    if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'edit_posts' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to access system reports.', 'ifsedu-school-management' ) );
    }

    $allowed_sub_tabs = array( 'finance', 'attendance' );

    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $raw_sub_tab = isset( $_GET['sub'] ) ? sanitize_key( wp_unslash( $_GET['sub'] ) ) : 'finance';
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    $sub_tab = in_array( $raw_sub_tab, $allowed_sub_tabs, true ) ? $raw_sub_tab : 'finance';

    // Dynamic Navigation URLs using add_query_arg()
    $base_admin_url = admin_url( 'admin.php' );
    $finance_url    = add_query_arg( array( 'page' => 'school_management_system', 'tab' => 'reports', 'sub' => 'finance' ), $base_admin_url );
    $attendance_url = add_query_arg( array( 'page' => 'school_management_system', 'tab' => 'reports', 'sub' => 'attendance' ), $base_admin_url );
    ?>

    <div class="ifs-educore-reports-nav-root">
        
        <!-- Bento Top Header Frame Component -->
        <div class="ifs-educore-top-nav-wrapper no-print">
            <h2>
                <span class="dashicons dashicons-chart-pie"></span> <?php esc_html_e( 'System Reports', 'ifsedu-school-management' ); ?>
            </h2>
            
            <div class="ifs-educore-nav-button-group">
                <a href="<?php echo esc_url( $finance_url ); ?>" 
                   class="ifs-educore-nav-link <?php echo ( 'finance' === $sub_tab ) ? 'ifs-educore-nav-link-active' : 'ifs-educore-nav-link-inactive'; ?>">
                    <span class="dashicons dashicons-money-alt"></span> <?php esc_html_e( 'Financial Report', 'ifsedu-school-management' ); ?>
                </a>
                
                <a href="<?php echo esc_url( $attendance_url ); ?>" 
                   class="ifs-educore-nav-link <?php echo ( 'attendance' === $sub_tab ) ? 'ifs-educore-nav-link-active' : 'ifs-educore-nav-link-inactive'; ?>">
                    <span class="dashicons dashicons-clipboard"></span> <?php esc_html_e( 'Attendance Report', 'ifsedu-school-management' ); ?>
                </a>
            </div>
        </div>

        <!-- System Analytics Viewport Execution Core -->
        <div class="ifs-educore-module-viewport-container">
            <?php
            switch ( $sub_tab ) {
                case 'attendance':
                    if ( function_exists( 'educore_reports_attendance_view' ) ) {
                        educore_reports_attendance_view();
                    } else {
                        echo '<div class="ifs-educore-notice-card"><span class="dashicons dashicons-info" style="vertical-align:middle; margin-right:6px;"></span> ' . 
                            wp_kses(
                                sprintf(
                                    /* translators: %s: Function name */
                                    __( 'Attendance Report module is initializing. Define %s.', 'ifsedu-school-management' ),
                                    '<code>educore_reports_attendance_view()</code>'
                                ),
                                array( 'code' => array(), 'span' => array( 'style' => array() ) )
                            ) . '</div>';
                    }
                    break;

                case 'finance':
                default:
                    if ( function_exists( 'educore_reports_finance_view' ) ) {
                        educore_reports_finance_view();
                    } else {
                        echo '<div class="ifs-educore-notice-card"><span class="dashicons dashicons-info" style="vertical-align:middle; margin-right:6px;"></span> ' . 
                            wp_kses(
                                sprintf(
                                    /* translators: %s: Function name */
                                    __( 'Financial Report module is initializing. Define %s.', 'ifsedu-school-management' ),
                                    '<code>educore_reports_finance_view()</code>'
                                ),
                                array( 'code' => array(), 'span' => array( 'style' => array() ) )
                            ) . '</div>';
                    }
                    break;
            }
            ?>
        </div>
    </div>
    <?php
}
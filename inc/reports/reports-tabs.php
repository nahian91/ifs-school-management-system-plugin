<?php
/**
 * High-End Academic Analytics Reports Sub-Navigation Engine & Router Matrix
 * File: inc/reports.php
 * Text Domain: ifsedu-school-management
 * Architecture: Bento Layout Viewports with Integrated Hardware Print Lockdown
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Immediate access layer lockdown.
}

/**
 * Main Reports Tab Router & Execution Controller
 */
function educore_reports_tab() {
    // Strict Capability Check.
    if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'edit_posts' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to access system reports.', 'ifsedu-school-management' ) );
    }

    $allowed_sub_tabs = array( 'finance', 'attendance' );

    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $raw_sub_tab = isset( $_GET['sub'] ) ? sanitize_key( wp_unslash( $_GET['sub'] ) ) : 'finance';
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    $sub_tab = in_array( $raw_sub_tab, $allowed_sub_tabs, true ) ? $raw_sub_tab : 'finance';

    // Dynamic Navigation URLs using add_query_arg().
    $base_admin_url = admin_url( 'admin.php' );
    $finance_url    = add_query_arg( array( 'page' => 'school_management_system', 'tab' => 'reports', 'sub' => 'finance' ), $base_admin_url );
    $attendance_url = add_query_arg( array( 'page' => 'school_management_system', 'tab' => 'reports', 'sub' => 'attendance' ), $base_admin_url );
    ?>

    <style id="ifs-educore-reports-router-styles">
        .ifs-educore-reports-nav-root {
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #0f172a;
        }

        .ifs-educore-top-nav-wrapper {
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

        .ifs-educore-top-nav-wrapper h2 {
            margin: 0;
            font-size: 18px;
            font-weight: 800;
            color: #00523c;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .ifs-educore-nav-button-group {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .ifs-educore-nav-link {
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

        .ifs-educore-nav-link .dashicons {
            font-size: 18px;
            width: 18px;
            height: 18px;
        }

        .ifs-educore-nav-link-inactive {
            color: #64748b;
            background: #f8fafc;
            border-color: #e2e8f0;
        }

        .ifs-educore-nav-link-inactive:hover {
            color: #00523c;
            background: #f0fdf4;
            border-color: #a7f3d0;
        }

        .ifs-educore-nav-link-active {
            color: #ffffff !important;
            background: #00523c !important;
            border-color: #00523c !important;
            box-shadow: 0 4px 12px rgba(0, 106, 78, 0.2);
        }

        .ifs-educore-nav-link-active .dashicons {
            color: #a7f3d0 !important;
        }

        .ifs-educore-notice-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            color: #475569;
            font-size: 14px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.02);
        }

        .ifs-educore-notice-icon {
            vertical-align: middle;
            margin-right: 6px;
        }

        .ifs-educore-module-viewport-container {
            width: 100%;
        }

        @media print {
            .no-print { display: none !important; }
        }
    </style>

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
                        echo '<div class="ifs-educore-notice-card"><span class="dashicons dashicons-info ifs-educore-notice-icon"></span> ' . 
                            wp_kses(
                                sprintf(
                                    /* translators: %s: Function name */
                                    __( 'Attendance Report module is initializing. Define %s.', 'ifsedu-school-management' ),
                                    '<code>educore_reports_attendance_view()</code>'
                                ),
                                array( 'code' => array(), 'span' => array( 'class' => array() ) )
                            ) . '</div>';
                    }
                    break;

                case 'finance':
                default:
                    if ( function_exists( 'educore_reports_finance_view' ) ) {
                        educore_reports_finance_view();
                    } else {
                        echo '<div class="ifs-educore-notice-card"><span class="dashicons dashicons-info ifs-educore-notice-icon"></span> ' . 
                            wp_kses(
                                sprintf(
                                    /* translators: %s: Function name */
                                    __( 'Financial Report module is initializing. Define %s.', 'ifsedu-school-management' ),
                                    '<code>educore_reports_finance_view()</code>'
                                ),
                                array( 'code' => array(), 'span' => array( 'class' => array() ) )
                            ) . '</div>';
                    }
                    break;
            }
            ?>
        </div>
    </div>
    <?php
}
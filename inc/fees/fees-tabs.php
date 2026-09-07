<?php
/**
 * High-End Academic Financial Fees Sub-Navigation Engine & Router Matrix (Role-Filtered for Accountant & Admin)
 * File: fees-tab.php
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Immediate access layer lockdown
}

function educore_fees_tab() {
    $current_user = wp_get_current_user();
    $roles        = (array) $current_user->roles;

    // 1. Multi-Role Capability Security Matrix (Admins & Accountants)
    $is_admin      = current_user_can( 'manage_options' );
    $is_accountant = in_array( 'accountant', $roles, true ) || current_user_can( 'edit_posts' );

    if ( ! $is_admin && ! $is_accountant ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to access the financial fees module.', 'ifsedu-school-management' ) );
    }

    $allowed_sub_tabs = array( 'list', 'collect', 'print' );

    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $raw_sub_tab = isset( $_GET['sub'] ) ? sanitize_key( wp_unslash( $_GET['sub'] ) ) : 'list';
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    $sub_tab = in_array( $raw_sub_tab, $allowed_sub_tabs, true ) ? $raw_sub_tab : 'list';

    // Construct URLs for top submenu links
    $base_admin_url = admin_url( 'admin.php' );
    $all_fees_url   = add_query_arg( array( 'page' => 'school_management_system', 'tab' => 'fees', 'sub' => 'list' ), $base_admin_url );
    $collect_url    = add_query_arg( array( 'page' => 'school_management_system', 'tab' => 'fees', 'sub' => 'collect' ), $base_admin_url );
    ?>

    <div class="ifs-educore-fees-nav-root">
        
        <!-- Top Sub-Navigation Menu Bar (Bento Frame Layer) -->
        <div class="ifs-educore-top-nav-wrapper no-print">
            <div class="ifs-educore-nav-button-group">
                <a href="<?php echo esc_url( $all_fees_url ); ?>" 
                   class="ifs-educore-nav-link <?php echo ( 'list' === $sub_tab ) ? 'ifs-educore-nav-link-active' : 'ifs-educore-nav-link-inactive'; ?>">
                    <span class="dashicons dashicons-money-alt"></span>
                    <?php esc_html_e( 'All Fee Invoices', 'ifsedu-school-management' ); ?>
                </a>
                
                <a href="<?php echo esc_url( $collect_url ); ?>" 
                   class="ifs-educore-nav-link <?php echo ( 'collect' === $sub_tab ) ? 'ifs-educore-nav-link-active' : 'ifs-educore-nav-link-inactive'; ?>">
                    <span class="dashicons dashicons-plus-alt2"></span>
                    <?php esc_html_e( '+ Collect Student Fee', 'ifsedu-school-management' ); ?>
                </a>
            </div>

            <?php if ( 'print' === $sub_tab ) : ?>
                <div>
                    <span class="ifs-educore-context-badge">
                        <span class="dashicons dashicons-printer" style="font-size:14px; width:14px; height:14px;"></span>
                        <?php esc_html_e( 'Printing Invoice Receipt', 'ifsedu-school-management' ); ?>
                    </span>
                </div>
            <?php endif; ?>
        </div>

        <!-- System Financial Routing Execution Core -->
        <div class="ifs-educore-module-viewport-container">
            <?php
            switch ( $sub_tab ) {
                case 'collect':
                    if ( function_exists( 'educore_fees_collect_view' ) ) {
                        educore_fees_collect_view();
                    } else {
                        echo '<div class="ifs-educore-notice-card"><span class="dashicons dashicons-info" style="vertical-align:middle; margin-right:6px;"></span> ' . 
                            wp_kses(
                                sprintf(
                                    /* translators: %s: Function name */
                                    __( 'Fee Collection module is initializing. Define %s.', 'ifsedu-school-management' ),
                                    '<code>educore_fees_collect_view()</code>'
                                ),
                                array( 'code' => array() )
                            ) . '</div>';
                    }
                    break;

                case 'print':
                    if ( function_exists( 'educore_fees_invoice_print_view' ) ) {
                        educore_fees_invoice_print_view();
                    } else {
                        echo '<div class="ifs-educore-notice-card"><span class="dashicons dashicons-info" style="vertical-align:middle; margin-right:6px;"></span> ' . 
                            wp_kses(
                                sprintf(
                                    /* translators: %s: Function name */
                                    __( 'Invoice Print module is initializing. Define %s.', 'ifsedu-school-management' ),
                                    '<code>educore_fees_invoice_print_view()</code>'
                                ),
                                array( 'code' => array() )
                            ) . '</div>';
                    }
                    break;

                case 'list':
                default:
                    if ( function_exists( 'educore_fees_list_view' ) ) {
                        educore_fees_list_view();
                    } else {
                        echo '<div class="ifs-educore-notice-card"><span class="dashicons dashicons-info" style="vertical-align:middle; margin-right:6px;"></span> ' . 
                            wp_kses(
                                sprintf(
                                    /* translators: %s: Function name */
                                    __( 'Fees List View module is initializing. Define %s.', 'ifsedu-school-management' ),
                                    '<code>educore_fees_list_view()</code>'
                                ),
                                array( 'code' => array() )
                            ) . '</div>';
                    }
                    break;
            }
            ?>
        </div>
    </div>
    <?php
}
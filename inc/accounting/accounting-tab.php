<?php
/**
 * Accounting Sub-Navigation Router Matrix (Multi-Role Support for Admin & Accountant)
 * File: inc/accounting.php
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Direct access safety buffer
}

// Load Modular Dependency Sub-Files if segregated
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$educore_acct_dir = defined( 'EDUCORE_PATH' ) ? EDUCORE_PATH . 'inc/accounting/' : plugin_dir_path( __FILE__ ) . 'accounting/';

if ( file_exists( $educore_acct_dir . 'accounting-list.php' ) ) {
    require_once $educore_acct_dir . 'accounting-list.php';
}
if ( file_exists( $educore_acct_dir . 'accounting-add.php' ) ) {
    require_once $educore_acct_dir . 'accounting-add.php';
}
if ( file_exists( $educore_acct_dir . 'accounting-view.php' ) ) {
    require_once $educore_acct_dir . 'accounting-view.php';
}
if ( file_exists( $educore_acct_dir . 'accounting-delete.php' ) ) {
    require_once $educore_acct_dir . 'accounting-delete.php';
}

function educore_accounting_tab() {
    global $wpdb;
    $current_user = wp_get_current_user();
    $table_staff  = $wpdb->prefix . 'sms_staff';

    // 1. Procedural Capability & Role Verification
    $is_admin = current_user_can( 'manage_options' ) || in_array( 'administrator', (array) $current_user->roles, true );
    
    $is_accountant = false;
    if ( function_exists( 'educore_has_access' ) ) {
        $is_accountant = educore_has_access( array( 'accountant', 'accounts_officer', 'finance', 'staff' ) );
    }

    if ( ! $is_admin && ! $is_accountant ) {
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $staff_row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT designation, staff_type FROM `{$table_staff}` WHERE wp_user_id = %d OR email = %s LIMIT 1",
                $current_user->ID,
                $current_user->user_email
            )
        );
        // phpcs:enable

        if ( $staff_row ) {
            $desig = strtolower( (string) ( $staff_row->designation . ' ' . $staff_row->staff_type ) );
            if ( strpos( $desig, 'account' ) !== false || strpos( $desig, 'finance' ) !== false || strpos( $desig, 'cash' ) !== false ) {
                $is_accountant = true;
            }
        }
    }

    if ( ! $is_admin && ! $is_accountant ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to manage financial ledger records.', 'ifsedu-school-management' ) );
    }

    $allowed_sub_tabs = array( 'list', 'add', 'edit', 'view', 'delete' );

    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $raw_sub_tab = isset( $_GET['sub'] ) ? sanitize_key( wp_unslash( $_GET['sub'] ) ) : 'list';
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    $sub_tab = in_array( $raw_sub_tab, $allowed_sub_tabs, true ) ? $raw_sub_tab : 'list';

    // Submenu URLs
    $base_admin_url = admin_url( 'admin.php' );
    $list_url       = add_query_arg( array( 'page' => 'school_management_system', 'tab' => 'accounting', 'sub' => 'list' ), $base_admin_url );
    $add_url        = add_query_arg( array( 'page' => 'school_management_system', 'tab' => 'accounting', 'sub' => 'add' ), $base_admin_url );
    ?>

    <div class="ifs-educore-acct-nav-root">
        
        <!-- Top Sub-Navigation Menu Bar -->
        <div class="ifs-educore-top-nav-wrapper no-print">
            <div class="ifs-educore-nav-button-group">
                <a href="<?php echo esc_url( $list_url ); ?>" class="ifs-educore-nav-link <?php echo ( 'list' === $sub_tab ) ? 'ifs-educore-nav-link-active' : 'ifs-educore-nav-link-inactive'; ?>">
                    <span class="dashicons dashicons-chart-line"></span>
                    <?php esc_html_e( 'Financial Ledger', 'ifsedu-school-management' ); ?>
                </a>
                
                <a href="<?php echo esc_url( $add_url ); ?>" class="ifs-educore-nav-link <?php echo ( 'add' === $sub_tab || 'edit' === $sub_tab ) ? 'ifs-educore-nav-link-active' : 'ifs-educore-nav-link-inactive'; ?>">
                    <span class="dashicons dashicons-plus-alt2"></span>
                    <?php esc_html_e( 'Record Transaction', 'ifsedu-school-management' ); ?>
                </a>
            </div>

            <div>
                <?php if ( 'view' === $sub_tab ) : ?>
                    <span style="background:#f0fdf4; color:#047857; font-size:12px; font-weight:700; padding:5px 12px; border-radius:20px; border:1px solid #bbf7d0; margin-right:10px;">
                        <?php esc_html_e( 'Viewing Voucher Details', 'ifsedu-school-management' ); ?>
                    </span>
                <?php endif; ?>

                <?php if ( ! $is_admin ) : ?>
                    <span class="ifs-educore-assigned-context-pill">
                        <span class="dashicons dashicons-businessman" style="font-size:14px; width:14px; height:14px;"></span>
                        <?php esc_html_e( 'Accounts & Cashier Desk', 'ifsedu-school-management' ); ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Router Engine Execution -->
        <div class="ifs-educore-module-viewport-container">
            <?php
            switch ( $sub_tab ) {
                case 'view':
                    if ( function_exists( 'educore_accounting_single_view' ) ) {
                        educore_accounting_single_view();
                    } else {
                        echo '<div class="ifs-educore-notice-card">' . esc_html__( 'Voucher Details View initializing.', 'ifsedu-school-management' ) . '</div>';
                    }
                    break;

                case 'add':
                case 'edit':
                    if ( function_exists( 'educore_accounting_add_edit_view' ) ) {
                        educore_accounting_add_edit_view();
                    } else {
                        echo '<div class="ifs-educore-notice-card">' . esc_html__( 'Record Transaction Module initializing.', 'ifsedu-school-management' ) . '</div>';
                    }
                    break;

                case 'delete':
                    if ( function_exists( 'educore_accounting_delete_handler' ) ) {
                        educore_accounting_delete_handler();
                    }
                    break;

                case 'list':
                default:
                    if ( function_exists( 'educore_accounting_list_view' ) ) {
                        educore_accounting_list_view();
                    } else {
                        echo '<div class="ifs-educore-notice-card">' . esc_html__( 'Accounting Ledger View initializing.', 'ifsedu-school-management' ) . '</div>';
                    }
                    break;
            }
            ?>
        </div>
    </div>
    <?php
}
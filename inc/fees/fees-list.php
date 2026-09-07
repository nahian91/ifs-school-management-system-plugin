<?php
/**
 * Fees Directory & Financial Ledger View Engine (Role-Filtered for Accountant & Admin)
 * File: inc/fees/fees-list.php
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Handle Fee Invoice AJAX Update Action
add_action( 'wp_ajax_ifs_educore_update_fee_invoice', 'ifs_educore_handle_update_fee_invoice_ajax' );
function ifs_educore_handle_update_fee_invoice_ajax() {
    check_ajax_referer( 'ifs_educore_edit_fee_nonce', 'security' );

    $current_user  = wp_get_current_user();
    $roles         = (array) $current_user->roles;
    $is_admin      = current_user_can( 'manage_options' );
    $is_accountant = in_array( 'accountant', $roles, true ) || current_user_can( 'edit_posts' );

    if ( ! $is_admin && ! $is_accountant ) {
        wp_send_json_error( array( 'message' => esc_html__( 'Unauthorized access.', 'ifsedu-school-management' ) ) );
    }

    global $wpdb;
    $table_fees = $wpdb->prefix . 'sms_fees';

    $fee_id         = isset( $_POST['fee_id'] ) ? absint( $_POST['fee_id'] ) : 0;
    $fee_type       = isset( $_POST['fee_type'] ) ? sanitize_text_field( wp_unslash( $_POST['fee_type'] ) ) : '';
    $fee_month      = isset( $_POST['fee_month'] ) ? sanitize_text_field( wp_unslash( $_POST['fee_month'] ) ) : '';
    $fee_year       = isset( $_POST['fee_year'] ) ? sanitize_text_field( wp_unslash( $_POST['fee_year'] ) ) : '';
    $amount         = isset( $_POST['amount'] ) ? floatval( $_POST['amount'] ) : 0.00;
    $late_fine      = isset( $_POST['late_fine'] ) ? floatval( $_POST['late_fine'] ) : 0.00;
    $discount       = isset( $_POST['discount'] ) ? floatval( $_POST['discount'] ) : 0.00;
    $net_payable    = isset( $_POST['net_payable'] ) ? floatval( $_POST['net_payable'] ) : 0.00;
    $paid_amount    = isset( $_POST['paid_amount'] ) ? floatval( $_POST['paid_amount'] ) : 0.00;
    $due_amount     = isset( $_POST['due_amount'] ) ? floatval( $_POST['due_amount'] ) : 0.00;
    $payment_status = isset( $_POST['payment_status'] ) ? sanitize_text_field( wp_unslash( $_POST['payment_status'] ) ) : 'Unpaid';

    if ( ! $fee_id ) {
        wp_send_json_error( array( 'message' => esc_html__( 'Invalid invoice record specified.', 'ifsedu-school-management' ) ) );
    }

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $updated = $wpdb->update(
        $table_fees,
        array(
            'fee_type'       => $fee_type,
            'fee_month'      => $fee_month,
            'fee_year'       => $fee_year,
            'amount'         => $amount,
            'late_fine'      => $late_fine,
            'discount'       => $discount,
            'net_payable'    => $net_payable,
            'paid_amount'    => $paid_amount,
            'due_amount'     => $due_amount,
            'payment_status' => $payment_status,
        ),
        array( 'id' => $fee_id ),
        array( '%s', '%s', '%s', '%f', '%f', '%f', '%f', '%f', '%f', '%s' ),
        array( '%d' )
    );
    // phpcs:enable

    if ( false !== $updated ) {
        if ( function_exists( 'educore_log_activity' ) ) {
            /* translators: %d: Fee Invoice ID */
            educore_log_activity( sprintf( __( 'Updated Fee Invoice ID #%d', 'ifsedu-school-management' ), $fee_id ) );
        }
        wp_send_json_success( array( 'message' => esc_html__( 'Fee record updated successfully.', 'ifsedu-school-management' ) ) );
    } else {
        wp_send_json_error( array( 'message' => esc_html__( 'Failed to update database record.', 'ifsedu-school-management' ) ) );
    }
}

function educore_fees_list_view() {
    global $wpdb;
    $current_user = wp_get_current_user();
    $roles         = (array) $current_user->roles;

    // 1. Multi-Role Capability Security Matrix (Admins & Accountants)
    $is_admin      = current_user_can( 'manage_options' );
    $is_accountant = in_array( 'accountant', $roles, true ) || current_user_can( 'edit_posts' );

    if ( ! $is_admin && ! $is_accountant ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to view financial ledger records.', 'ifsedu-school-management' ) );
    }

    $table_fees     = $wpdb->prefix . 'sms_fees';
    $table_students = $wpdb->prefix . 'sms_students';
    $table_units    = $wpdb->prefix . 'sms_academic_units';
    $table_staff    = $wpdb->prefix . 'sms_staff';

    // 2. Sanitize and Extract Filter Request Inputs
    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $filter_class      = isset( $_GET['filter_class'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_class'] ) ) : '';
    $filter_section    = isset( $_GET['filter_section'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_section'] ) ) : '';
    $filter_shift      = isset( $_GET['filter_shift'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_shift'] ) ) : '';
    $filter_accountant = isset( $_GET['filter_accountant'] ) ? absint( wp_unslash( $_GET['filter_accountant'] ) ) : 0;
    $filter_student    = isset( $_GET['filter_student'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_student'] ) ) : '';
    $filter_date_from  = isset( $_GET['filter_date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_date_from'] ) ) : '';
    $filter_date_to    = isset( $_GET['filter_date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_date_to'] ) ) : '';
    $filter_status     = isset( $_GET['filter_status'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_status'] ) ) : '';
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    // 3. Fetch Dropdown Options Dynamically ordered by sort_order
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $raw_classes_data = $wpdb->get_results( 
        "SELECT class_name, MIN(sort_order) as min_sort 
         FROM `{$table_units}` 
         WHERE class_name IS NOT NULL AND class_name != '' 
         GROUP BY class_name 
         ORDER BY min_sort ASC, CAST(class_name AS UNSIGNED) ASC, class_name ASC" 
    );
    // phpcs:enable

    $available_classes = array();
    if ( ! empty( $raw_classes_data ) && is_array( $raw_classes_data ) ) {
        foreach ( $raw_classes_data as $c_row ) {
            $c_name = trim( (string) $c_row->class_name );
            if ( ! empty( $c_name ) && ! in_array( $c_name, $available_classes, true ) ) {
                $available_classes[] = $c_name;
            }
        }
    }

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $all_units = $wpdb->get_results( "SELECT id, class_name, section_name, sort_order FROM `{$table_units}` WHERE section_name != '' ORDER BY sort_order ASC, section_name ASC" );
    
    // Fetch Distinct Collectors / Accountants for the Filter Dropdown
    $collectors_staff = $wpdb->get_results(
        "SELECT id, full_name, designation FROM `{$table_staff}` WHERE status = 'Active' ORDER BY full_name ASC"
    );
    $collectors_users = $wpdb->get_results(
        "SELECT ID as id, display_name as full_name FROM `{$wpdb->users}` ORDER BY display_name ASC"
    );
    // phpcs:enable

    // 4. Construct SQL Query WHERE Conditions
    $where_clauses = array( '1=1' );
    $query_args    = array();

    if ( ! empty( $filter_class ) ) {
        $where_clauses[] = 's.class_name = %s';
        $query_args[]    = $filter_class;
    }

    if ( ! empty( $filter_section ) ) {
        $where_clauses[] = 's.section_name = %s';
        $query_args[]    = $filter_section;
    }

    if ( ! empty( $filter_shift ) ) {
        $where_clauses[] = 's.shift = %s';
        $query_args[]    = $filter_shift;
    }

    if ( $filter_accountant > 0 ) {
        $where_clauses[] = 'f.collected_by = %d';
        $query_args[]    = $filter_accountant;
    }

    if ( ! empty( $filter_student ) ) {
        $where_clauses[] = '(s.full_name LIKE %s OR s.student_id LIKE %s OR f.invoice_id LIKE %s)';
        $student_like    = '%' . $wpdb->esc_like( $filter_student ) . '%';
        $query_args[]    = $student_like;
        $query_args[]    = $student_like;
        $query_args[]    = $student_like;
    }

    if ( ! empty( $filter_date_from ) ) {
        $where_clauses[] = 'DATE(f.payment_date) >= %s';
        $query_args[]    = $filter_date_from;
    }

    if ( ! empty( $filter_date_to ) ) {
        $where_clauses[] = 'DATE(f.payment_date) <= %s';
        $query_args[]    = $filter_date_to;
    }

    if ( ! empty( $filter_status ) ) {
        $where_clauses[] = 'f.payment_status = %s';
        $query_args[]    = $filter_status;
    }

    $where_sql = ' WHERE ' . implode( ' AND ', $where_clauses );

    // 5. Aggregate Ledger Totals with Active Filters Applied
    $totals_sql = "SELECT 
        SUM(f.net_payable) as total_invoiced, 
        SUM(f.paid_amount) as total_collected, 
        SUM(f.due_amount) as total_due 
        FROM `{$table_fees}` f 
        LEFT JOIN `{$table_students}` s ON f.student_id = s.id" . $where_sql;

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    if ( ! empty( $query_args ) ) {
        $totals = $wpdb->get_row( $wpdb->prepare( $totals_sql, ...$query_args ) );
    } else {
        $totals = $wpdb->get_row( $totals_sql );
    }

    // 6. Fetch Filtered Ledger Records with Student, Waiver & Entry Collector Details
    $query = "SELECT f.*, s.full_name, s.student_id as s_id, s.class_name, s.section_name, s.shift, s.waiver_percentage, 
                     st.full_name as ref_staff_name, u.display_name as collector_name, col_staff.full_name as col_staff_name
              FROM `{$table_fees}` f 
              LEFT JOIN `{$table_students}` s ON f.student_id = s.id
              LEFT JOIN `{$table_staff}` st ON s.waiver_staff_id = st.id
              LEFT JOIN `{$wpdb->users}` u ON f.collected_by = u.ID
              LEFT JOIN `{$table_staff}` col_staff ON f.collected_by = col_staff.id" . $where_sql . " 
              ORDER BY f.id DESC";

    if ( ! empty( $query_args ) ) {
        $fees_records = $wpdb->get_results( $wpdb->prepare( $query, ...$query_args ) );
    } else {
        $fees_records = $wpdb->get_results( $query );
    }
    // phpcs:enable

    $collect_url = add_query_arg( array( 'page' => 'school_management_system', 'tab' => 'fees', 'sub' => 'collect' ), admin_url( 'admin.php' ) );
    $page_url    = add_query_arg( array( 'page' => 'school_management_system', 'tab' => 'fees' ), admin_url( 'admin.php' ) );
    $months_list = array( 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December' );
    ?>

    <div class="ifs-educore-fees-list-container">

        <!-- Flash Notice Feedback Banner -->
        <?php
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        if ( isset( $_GET['msg'] ) ) : 
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $msg_type = sanitize_text_field( wp_unslash( $_GET['msg'] ) );
        ?>
            <?php if ( $msg_type === 'collected' || $msg_type === 'success' ) : ?>
                <div class="ifs-educore-notice-banner">
                    <span class="dashicons dashicons-yes-alt" style="font-size:20px; width:20px; height:20px;"></span>
                    <span><?php esc_html_e( 'Fee payment received and recorded successfully.', 'ifsedu-school-management' ); ?></span>
                </div>
            <?php elseif ( $msg_type === 'updated' ) : ?>
                <div class="ifs-educore-notice-banner updated">
                    <span class="dashicons dashicons-saved" style="font-size:20px; width:20px; height:20px;"></span>
                    <span><?php esc_html_e( 'Fee invoice record updated successfully.', 'ifsedu-school-management' ); ?></span>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Financial Ledger Overview Metrics Bento Box -->
        <div class="ifs-educore-metrics-bento">
            <div class="ifs-educore-metric-card invoiced">
                <span class="ifs-educore-metric-label"><?php esc_html_e( 'Total Invoiced Amount', 'ifsedu-school-management' ); ?></span>
                <div class="ifs-educore-metric-value blue">৳<?php echo esc_html( number_format( $totals ? (float) $totals->total_invoiced : 0, 2 ) ); ?></div>
            </div>
            <div class="ifs-educore-metric-card collected">
                <span class="ifs-educore-metric-label"><?php esc_html_e( 'Total Fees Collected', 'ifsedu-school-management' ); ?></span>
                <div class="ifs-educore-metric-value green">৳<?php echo esc_html( number_format( $totals ? (float) $totals->total_collected : 0, 2 ) ); ?></div>
            </div>
            <div class="ifs-educore-metric-card due">
                <span class="ifs-educore-metric-label"><?php esc_html_e( 'Total Outstanding Dues', 'ifsedu-school-management' ); ?></span>
                <div class="ifs-educore-metric-value red">৳<?php echo esc_html( number_format( $totals ? (float) $totals->total_due : 0, 2 ) ); ?></div>
            </div>
        </div>

        <!-- Dynamic Filter Controls Card -->
        <div class="ifs-educore-filter-card">
            <form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="ifs-educore-filter-form">
                <input type="hidden" name="page" value="school_management_system" />
                <input type="hidden" name="tab" value="fees" />

                <!-- Class Filter -->
                <div class="ifs-educore-filter-group">
                    <label for="filter_class"><?php esc_html_e( 'Class', 'ifsedu-school-management' ); ?></label>
                    <select name="filter_class" id="filter_class" class="ifs-educore-filter-select">
                        <option value=""><?php esc_html_e( 'All Classes', 'ifsedu-school-management' ); ?></option>
                        <?php if ( ! empty( $available_classes ) ) : foreach ( $available_classes as $class ) : 
                            $class_label = $class;
                            if ( ! preg_match( '/^class\s+/i', $class_label ) ) {
                                $class_label = sprintf( __( 'Class %s', 'ifsedu-school-management' ), $class );
                            }
                        ?>
                            <option value="<?php echo esc_attr( $class ); ?>" <?php selected( $filter_class, $class ); ?>>
                                <?php echo esc_html( $class_label ); ?>
                            </option>
                        <?php endforeach; endif; ?>
                    </select>
                </div>

                <!-- Section Filter (Dynamic Dropdown via JS) -->
                <div class="ifs-educore-filter-group">
                    <label for="filter_section"><?php esc_html_e( 'Section', 'ifsedu-school-management' ); ?></label>
                    <select name="filter_section" id="filter_section" class="ifs-educore-filter-select">
                        <option value=""><?php esc_html_e( 'All Sections', 'ifsedu-school-management' ); ?></option>
                    </select>
                </div>

                <!-- Shift Filter -->
                <div class="ifs-educore-filter-group">
                    <label for="filter_shift"><?php esc_html_e( 'Shift', 'ifsedu-school-management' ); ?></label>
                    <select name="filter_shift" id="filter_shift" class="ifs-educore-filter-select">
                        <option value=""><?php esc_html_e( 'All Shifts', 'ifsedu-school-management' ); ?></option>
                        <option value="No Shift" <?php selected( $filter_shift, 'No Shift' ); ?>><?php esc_html_e( 'No Shift', 'ifsedu-school-management' ); ?></option>
                        <option value="Morning Shift" <?php selected( $filter_shift, 'Morning Shift' ); ?>><?php esc_html_e( 'Morning Shift', 'ifsedu-school-management' ); ?></option>
                        <option value="Day Shift" <?php selected( $filter_shift, 'Day Shift' ); ?>><?php esc_html_e( 'Day Shift', 'ifsedu-school-management' ); ?></option>
                    </select>
                </div>

                <!-- Accountant / Entry Collector Filter -->
                <div class="ifs-educore-filter-group">
                    <label for="filter_accountant"><?php esc_html_e( 'Entry / Accountant', 'ifsedu-school-management' ); ?></label>
                    <select name="filter_accountant" id="filter_accountant" class="ifs-educore-filter-select">
                        <option value="0"><?php esc_html_e( 'All Accountants', 'ifsedu-school-management' ); ?></option>
                        <?php if ( ! empty( $collectors_staff ) ) : foreach ( $collectors_staff as $c_st ) : ?>
                            <option value="<?php echo esc_attr( $c_st->id ); ?>" <?php selected( $filter_accountant, $c_st->id ); ?>>
                                <?php echo esc_html( $c_st->full_name . ( $c_st->designation ? ' (' . $c_st->designation . ')' : '' ) ); ?>
                            </option>
                        <?php endforeach; endif; ?>
                    </select>
                </div>

                <!-- Payment Status Filter -->
                <div class="ifs-educore-filter-group">
                    <label for="filter_status"><?php esc_html_e( 'Status', 'ifsedu-school-management' ); ?></label>
                    <select name="filter_status" id="filter_status" class="ifs-educore-filter-select">
                        <option value=""><?php esc_html_e( 'All Statuses', 'ifsedu-school-management' ); ?></option>
                        <option value="Paid" <?php selected( $filter_status, 'Paid' ); ?>><?php esc_html_e( 'Paid', 'ifsedu-school-management' ); ?></option>
                        <option value="Partial" <?php selected( $filter_status, 'Partial' ); ?>><?php esc_html_e( 'Partial', 'ifsedu-school-management' ); ?></option>
                        <option value="Unpaid" <?php selected( $filter_status, 'Unpaid' ); ?>><?php esc_html_e( 'Unpaid', 'ifsedu-school-management' ); ?></option>
                    </select>
                </div>

                <!-- Student Filter -->
                <div class="ifs-educore-filter-group">
                    <label for="filter_student"><?php esc_html_e( 'Student / Invoice', 'ifsedu-school-management' ); ?></label>
                    <input type="text" name="filter_student" id="filter_student" class="ifs-educore-filter-input" placeholder="<?php esc_attr_e( 'Name, ID, or Invoice...', 'ifsedu-school-management' ); ?>" value="<?php echo esc_attr( $filter_student ); ?>" />
                </div>

                <!-- Date Range From -->
                <div class="ifs-educore-filter-group">
                    <label for="filter_date_from"><?php esc_html_e( 'From Date', 'ifsedu-school-management' ); ?></label>
                    <input type="date" name="filter_date_from" id="filter_date_from" class="ifs-educore-filter-input" value="<?php echo esc_attr( $filter_date_from ); ?>" />
                </div>

                <!-- Date Range To -->
                <div class="ifs-educore-filter-group">
                    <label for="filter_date_to"><?php esc_html_e( 'To Date', 'ifsedu-school-management' ); ?></label>
                    <input type="date" name="filter_date_to" id="filter_date_to" class="ifs-educore-filter-input" value="<?php echo esc_attr( $filter_date_to ); ?>" />
                </div>

                <!-- Filter Action Buttons -->
                <div class="ifs-educore-filter-actions">
                    <button type="submit" class="ifs-educore-btn-filter-submit">
                        <span class="dashicons dashicons-filter" style="font-size:16px; width:16px; height:16px;"></span>
                        <?php esc_html_e( 'Filter Ledger', 'ifsedu-school-management' ); ?>
                    </button>
                    <a href="<?php echo esc_url( $page_url ); ?>" class="ifs-educore-btn-filter-reset" title="<?php esc_attr_e( 'Reset Filters', 'ifsedu-school-management' ); ?>">
                        <span class="dashicons dashicons-dismiss" style="font-size:16px; width:16px; height:16px;"></span>
                        <?php esc_html_e( 'Reset', 'ifsedu-school-management' ); ?>
                    </a>
                </div>
            </form>
        </div>

        <!-- Action Header -->
        <div class="ifs-educore-actions-bar">
            <h2 class="ifs-educore-title">
                <span class="dashicons dashicons-money-alt" style="color:#00523c; font-size:24px; width:24px; height:24px;"></span>
                <?php esc_html_e( 'Fee Collection & Due Ledger', 'ifsedu-school-management' ); ?>
            </h2>
            <a href="<?php echo esc_url( $collect_url ); ?>" class="ifs-educore-btn-collect">
                <span class="dashicons dashicons-plus-alt2" style="font-size:16px; width:16px; height:16px;"></span>
                <?php esc_html_e( 'Collect New Fee', 'ifsedu-school-management' ); ?>
            </a>
        </div>

        <!-- Main Invoices Table Card -->
        <div class="ifs-educore-bento-card">
            <table class="ifs-educore-table educore-datatable">
                <thead>
                    <tr>
                        <th style="width: 105px;"><?php esc_html_e( 'Invoice ID', 'ifsedu-school-management' ); ?></th>
                        <th><?php esc_html_e( 'Student Details', 'ifsedu-school-management' ); ?></th>
                        <th><?php esc_html_e( 'Month / Year', 'ifsedu-school-management' ); ?></th>
                        <th><?php esc_html_e( 'Fee Category', 'ifsedu-school-management' ); ?></th>
                        <th><?php esc_html_e( 'Net Payable', 'ifsedu-school-management' ); ?></th>
                        <th><?php esc_html_e( 'Paid', 'ifsedu-school-management' ); ?></th>
                        <th><?php esc_html_e( 'Due', 'ifsedu-school-management' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'ifsedu-school-management' ); ?></th>
                        <th><?php esc_html_e( 'Entry / Collector', 'ifsedu-school-management' ); ?></th>
                        <th style="text-align: right; width: 105px;"><?php esc_html_e( 'Actions', 'ifsedu-school-management' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( ! empty( $fees_records ) ) : foreach ( $fees_records as $fee ) : 
                        $print_url = add_query_arg(
                            array(
                                'page'    => 'school_management_system',
                                'tab'     => 'fees',
                                'sub'     => 'print',
                                'invoice' => $fee->invoice_id,
                            ),
                            admin_url( 'admin.php' )
                        );
                        
                        // Status Badge Mapping
                        $status_class = 'unpaid';
                        if ( $fee->payment_status === 'Paid' ) { 
                            $status_class = 'paid'; 
                        } elseif ( $fee->payment_status === 'Partial' ) { 
                            $status_class = 'partial'; 
                        }

                        $student_id_str = $fee->s_id ? strtoupper( (string) $fee->s_id ) : 'DELETED';
                        $class_str      = $fee->class_name ? $fee->class_name : 'Unassigned';
                        $section_str    = ! empty( $fee->section_name ) ? $fee->section_name : 'N/A';
                        $shift_str      = ( ! empty( $fee->shift ) && 'No Shift' !== $fee->shift ) ? ' | ' . $fee->shift : '';

                        // Determine Entry Collector Name & Date
                        $collector_display = ! empty( $fee->col_staff_name ) ? $fee->col_staff_name : ( ! empty( $fee->collector_name ) ? $fee->collector_name : ( ! empty( $fee->recorded_by ) ? '#' . $fee->recorded_by : __( 'Admin / System', 'ifsedu-school-management' ) ) );
                        $entry_date_str    = ! empty( $fee->created_at ) ? date_i18n( 'M j, Y h:i A', strtotime( $fee->created_at ) ) : ( ! empty( $fee->payment_date ) ? date_i18n( 'M j, Y', strtotime( $fee->payment_date ) ) : '—' );
                    ?>
                    <tr data-fee-id="<?php echo esc_attr( $fee->id ); ?>">
                        <td>
                            <span class="ifs-educore-invoice-code">#<?php echo esc_html( $fee->invoice_id ); ?></span>
                        </td>
                        <td>
                            <strong style="color: #0f172a;" class="cell-student-name"><?php echo esc_html( $fee->full_name ? $fee->full_name : 'N/A Record' ); ?></strong><br>
                            <span style="font-size: 11.5px; color: #64748b;">
                                <?php echo esc_html( sprintf( 'ID: %s | Class: %s (%s)%s', $student_id_str, $class_str, $section_str, $shift_str ) ); ?>
                            </span>
                            <?php if ( ! empty( $fee->waiver_percentage ) && floatval( $fee->waiver_percentage ) > 0 ) : ?>
                                <br><span class="ifs-educore-waiver-tag">
                                    <?php echo esc_html( floatval( $fee->waiver_percentage ) ); ?>% <?php esc_html_e( 'Waiver', 'ifsedu-school-management' ); ?> <?php echo ! empty( $fee->ref_staff_name ) ? esc_html( '[' . $fee->ref_staff_name . ']' ) : ''; ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span style="background: #f1f5f9; border: 1px solid #e2e8f0; padding: 2px 8px; border-radius: 4px; font-weight: 600; font-size: 11.5px;" class="cell-month-year">
                                <?php echo esc_html( ucfirst( $fee->fee_month ) . ' ' . $fee->fee_year ); ?>
                            </span>
                        </td>
                        <td>
                            <strong style="color: #475569;" class="cell-fee-type"><?php echo esc_html( $fee->fee_type ); ?></strong>
                        </td>
                        <td class="cell-net-payable">৳<?php echo esc_html( number_format( (float) $fee->net_payable, 2 ) ); ?></td>
                        <td class="cell-paid-amount"><strong style="color: #00523c;">৳<?php echo esc_html( number_format( (float) $fee->paid_amount, 2 ) ); ?></strong></td>
                        <td class="cell-due-amount"><strong style="color: #dc2626;">৳<?php echo esc_html( number_format( (float) $fee->due_amount, 2 ) ); ?></strong></td>
                        <td>
                            <span class="ifs-educore-status-badge <?php echo esc_attr( $status_class ); ?> cell-status">
                                <?php echo esc_html( $fee->payment_status ); ?>
                            </span>
                        </td>
                        <td>
                            <strong style="color: #0f172a; font-size: 12px;"><?php echo esc_html( $collector_display ); ?></strong>
                            <span style="font-size: 10.5px; color: #64748b; display: block; margin-top: 2px;">
                                <span class="dashicons dashicons-clock" style="font-size: 11px; width: 11px; height: 11px; vertical-align: middle;"></span>
                                <?php echo esc_html( $entry_date_str ); ?>
                            </span>
                        </td>
                        <td style="text-align: right;">
                            <div class="ifs-educore-action-group">
                                <!-- Trigger Edit Modal -->
                                <button type="button" 
                                        class="ifs-educore-square-btn ifs-educore-btn-edit btn-trigger-edit-fee" 
                                        data-id="<?php echo esc_attr( $fee->id ); ?>"
                                        data-invoice="<?php echo esc_attr( $fee->invoice_id ); ?>"
                                        data-class="<?php echo esc_attr( $fee->class_name ); ?>"
                                        data-type="<?php echo esc_attr( $fee->fee_type ); ?>"
                                        data-month="<?php echo esc_attr( ucfirst( $fee->fee_month ) ); ?>"
                                        data-year="<?php echo esc_attr( $fee->fee_year ); ?>"
                                        data-amount="<?php echo esc_attr( $fee->amount ); ?>"
                                        data-fine="<?php echo esc_attr( $fee->late_fine ); ?>"
                                        data-discount="<?php echo esc_attr( $fee->discount ); ?>"
                                        data-net="<?php echo esc_attr( $fee->net_payable ); ?>"
                                        data-paid="<?php echo esc_attr( $fee->paid_amount ); ?>"
                                        data-due="<?php echo esc_attr( $fee->due_amount ); ?>"
                                        data-status="<?php echo esc_attr( $fee->payment_status ); ?>"
                                        title="<?php esc_attr_e( 'Edit Invoice Record', 'ifsedu-school-management' ); ?>">
                                    <span class="dashicons dashicons-edit"></span>
                                </button>

                                <a href="<?php echo esc_url( $print_url ); ?>" class="ifs-educore-btn-action-print" target="_blank" title="<?php esc_attr_e( 'Print Invoice Receipt', 'ifsedu-school-management' ); ?>">
                                    <span class="dashicons dashicons-printer" style="font-size: 14px; width: 14px; height: 14px;"></span>
                                    <?php esc_html_e( 'Print', 'ifsedu-school-management' ); ?>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

    </div>

    <!-- Dynamic Edit Fee Invoice Modal -->
    <div class="ifs-educore-modal-backdrop" id="ifs_educore_edit_fee_modal">
        <div class="ifs-educore-modal-card">
            <div class="ifs-educore-modal-header">
                <h4 class="ifs-educore-modal-title"><?php esc_html_e( 'Edit Fee Invoice Record', 'ifsedu-school-management' ); ?></h4>
                <button type="button" class="ifs-educore-modal-close" id="ifs_educore_close_fee_modal">&times;</button>
            </div>
            <form id="ifs_educore_edit_fee_form">
                <input type="hidden" id="edit_fee_id" name="fee_id" value="">
                <input type="hidden" id="edit_fee_class" name="fee_class" value="">
                <input type="hidden" id="edit_fee_amount" name="amount" value="0.00">
                <input type="hidden" id="edit_fee_fine" name="late_fine" value="0.00">
                <input type="hidden" id="edit_fee_discount" name="discount" value="0.00">

                <?php wp_nonce_field( 'ifs_educore_edit_fee_nonce', 'edit_fee_nonce_field' ); ?>

                <div class="ifs-educore-filter-group" style="margin-bottom: 12px;">
                    <label><?php esc_html_e( 'Fee Category Type', 'ifsedu-school-management' ); ?> <span style="color:#dc2626;">*</span></label>
                    <select id="edit_fee_type" name="fee_type" class="ifs-educore-filter-select" required>
                        <option value=""><?php esc_html_e( '-- Choose Category --', 'ifsedu-school-management' ); ?></option>
                    </select>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                    <div class="ifs-educore-filter-group">
                        <label><?php esc_html_e( 'Fee Month', 'ifsedu-school-management' ); ?> <span style="color:#dc2626;">*</span></label>
                        <select id="edit_fee_month" name="fee_month" class="ifs-educore-filter-select" required>
                            <?php foreach ( $months_list as $m ) : ?>
                                <option value="<?php echo esc_attr( $m ); ?>"><?php echo esc_html( $m ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="ifs-educore-filter-group">
                        <label><?php esc_html_e( 'Fee Year', 'ifsedu-school-management' ); ?> <span style="color:#dc2626;">*</span></label>
                        <input type="number" id="edit_fee_year" name="fee_year" class="ifs-educore-filter-input" min="2020" max="2099" required>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; margin-bottom: 12px;">
                    <div class="ifs-educore-filter-group">
                        <label><?php esc_html_e( 'Net Payable', 'ifsedu-school-management' ); ?></label>
                        <input type="number" step="0.01" id="edit_net_payable" name="net_payable" class="ifs-educore-filter-input" required>
                    </div>
                    <div class="ifs-educore-filter-group">
                        <label><?php esc_html_e( 'Paid Amount', 'ifsedu-school-management' ); ?></label>
                        <input type="number" step="0.01" id="edit_paid_amount" name="paid_amount" class="ifs-educore-filter-input" required>
                    </div>
                    <div class="ifs-educore-filter-group">
                        <label><?php esc_html_e( 'Due Amount', 'ifsedu-school-management' ); ?></label>
                        <input type="number" step="0.01" id="edit_due_amount" name="due_amount" class="ifs-educore-filter-input" readonly style="background:#fffbeb; color:#b45309; font-weight:800;">
                    </div>
                </div>

                <div class="ifs-educore-filter-group">
                    <label><?php esc_html_e( 'Payment Status', 'ifsedu-school-management' ); ?> <span style="color:#dc2626;">*</span></label>
                    <select id="edit_payment_status" name="payment_status" class="ifs-educore-filter-select" required>
                        <option value="Paid"><?php esc_html_e( 'Paid', 'ifsedu-school-management' ); ?></option>
                        <option value="Partial"><?php esc_html_e( 'Partial', 'ifsedu-school-management' ); ?></option>
                        <option value="Unpaid"><?php esc_html_e( 'Unpaid', 'ifsedu-school-management' ); ?></option>
                    </select>
                </div>

                <div class="ifs-educore-modal-footer">
                    <button type="button" class="ifs-educore-btn-cancel" id="ifs_educore_cancel_fee_edit"><?php esc_html_e( 'Cancel', 'ifsedu-school-management' ); ?></button>
                    <button type="submit" class="ifs-educore-btn-collect" id="ifs_educore_save_fee_edit_btn" style="height: auto; padding: 9px 20px;">
                        <span class="dashicons dashicons-saved" style="font-size:16px; width:16px; height:16px;"></span>
                        <?php esc_html_e( 'Update Invoice', 'ifsedu-school-management' ); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Dynamic Script Layer: Section Chaining, Modal Control & DataTables Engine -->
    <script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function() {
        var unitsMap       = <?php echo wp_json_encode( ! empty( $all_units ) ? $all_units : array() ); ?>;
        var currentSection = "<?php echo esc_js( $filter_section ); ?>";
        var classSelect    = document.getElementById('filter_class');
        var sectionSelect  = document.getElementById('filter_section');

        // Populate Sections based on selected Class
        function populateSections(selectedClass, selectedSecName) {
            selectedSecName = selectedSecName || '';
            if (!sectionSelect) return;
            sectionSelect.innerHTML = '<option value=""><?php echo esc_js( __( 'All Sections', 'ifsedu-school-management' ) ); ?></option>';
            if (!selectedClass) return;

            var filtered = unitsMap.filter(function(item) { return item.class_name == selectedClass; });
            var uniqueSections = [];
            filtered.forEach(function(item) {
                if (item.section_name && uniqueSections.indexOf(item.section_name) === -1) {
                    uniqueSections.push(item.section_name);
                }
            });

            uniqueSections.sort(function(a, b) {
                return a.localeCompare(b, undefined, { numeric: true, sensitivity: 'base' });
            });

            uniqueSections.forEach(function(secName) {
                var opt = document.createElement('option');
                opt.value = secName;
                opt.textContent = secName;
                if (secName == selectedSecName) {
                    opt.selected = true;
                }
                sectionSelect.appendChild(opt);
            });
        }

        if (classSelect && sectionSelect) {
            populateSections(classSelect.value, currentSection);

            classSelect.addEventListener('change', function() {
                populateSections(this.value);
            });
        }

        // --------------------------------------------------------------------------
        // EDIT MODAL AJAX ENGINE FOR FEES LEDGER
        // --------------------------------------------------------------------------
        var modal          = document.getElementById('ifs_educore_edit_fee_modal');
        var closeModalBtn  = document.getElementById('ifs_educore_close_fee_modal');
        var cancelModalBtn = document.getElementById('ifs_educore_cancel_fee_edit');
        var editForm       = document.getElementById('ifs_educore_edit_fee_form');

        function hideModal() {
            if (modal) modal.classList.remove('is-visible');
        }

        if (closeModalBtn) closeModalBtn.addEventListener('click', hideModal);
        if (cancelModalBtn) cancelModalBtn.addEventListener('click', hideModal);

        var netInput     = document.getElementById('edit_net_payable');
        var paidInput    = document.getElementById('edit_paid_amount');
        var dueInput     = document.getElementById('edit_due_amount');
        var statusSelect = document.getElementById('edit_payment_status');

        function updateModalCalculations() {
            var net  = parseFloat(netInput.value) || 0;
            var paid = parseFloat(paidInput.value) || 0;
            var due  = Math.max(0, net - paid);
            
            dueInput.value = due.toFixed(2);

            if (paid >= net && net > 0) {
                statusSelect.value = 'Paid';
            } else if (paid > 0 && paid < net) {
                statusSelect.value = 'Partial';
            } else {
                statusSelect.value = 'Unpaid';
            }
        }

        if (netInput && paidInput) {
            netInput.addEventListener('input', updateModalCalculations);
            paidInput.addEventListener('input', updateModalCalculations);
        }

        // Trigger Modal Open & Load Class Specific Categories
        document.addEventListener('click', function(e) {
            var editBtn = e.target.closest('.btn-trigger-edit-fee');
            if (editBtn) {
                var id        = editBtn.getAttribute('data-id');
                var className = editBtn.getAttribute('data-class');
                var type      = editBtn.getAttribute('data-type');
                var month     = editBtn.getAttribute('data-month');
                var year      = editBtn.getAttribute('data-year');
                var amount    = editBtn.getAttribute('data-amount');
                var fine      = editBtn.getAttribute('data-fine');
                var discount  = editBtn.getAttribute('data-discount');
                var net       = editBtn.getAttribute('data-net');
                var paid      = editBtn.getAttribute('data-paid');
                var due       = editBtn.getAttribute('data-due');
                var status    = editBtn.getAttribute('data-status');

                document.getElementById('edit_fee_id').value         = id;
                document.getElementById('edit_fee_class').value      = className;
                document.getElementById('edit_fee_month').value      = month;
                document.getElementById('edit_fee_year').value       = year;
                document.getElementById('edit_fee_amount').value     = amount;
                document.getElementById('edit_fee_fine').value       = fine;
                document.getElementById('edit_fee_discount').value   = discount;
                document.getElementById('edit_net_payable').value    = net;
                document.getElementById('edit_paid_amount').value    = paid;
                document.getElementById('edit_due_amount').value     = due;
                document.getElementById('edit_payment_status').value = status;

                // Load Class-specific Fee Types
                var $feeTypeSelect = jQuery('#edit_fee_type');
                $feeTypeSelect.html('<option value=""><?php echo esc_js( __( "-- Loading Categories... --", "ifsedu-school-management" ) ); ?></option>');

                jQuery.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'ifs_educore_get_fee_types_by_class',
                        security: '<?php echo esc_js( wp_create_nonce( "ifs_educore_fee_nonce" ) ); ?>',
                        class_name: className
                    },
                    success: function(response) {
                        if (response.success && response.data && response.data.fee_types && response.data.fee_types.length > 0) {
                            var options = '<option value=""><?php echo esc_js( __( "-- Select Fee Category --", "ifsedu-school-management" ) ); ?></option>';
                            var matched = false;
                            response.data.fee_types.forEach(function(item) {
                                var isSelected = (item.fee_title === type) ? 'selected' : '';
                                if (isSelected) matched = true;
                                options += '<option value="' + item.fee_title + '" data-amount="' + item.amount + '" ' + isSelected + '>' + item.fee_title + ' (৳' + parseFloat(item.amount).toFixed(2) + ')</option>';
                            });
                            if (!matched && type) {
                                options += '<option value="' + type + '" selected>' + type + ' (Custom/Legacy)</option>';
                            }
                            $feeTypeSelect.html(options);
                        } else {
                            $feeTypeSelect.html('<option value="' + type + '" selected>' + type + '</option>');
                        }
                    },
                    error: function() {
                        $feeTypeSelect.html('<option value="' + type + '" selected>' + type + '</option>');
                    }
                });

                modal.classList.add('is-visible');
            }
        });

        // When Fee Type changed in Edit Modal, update Net Payable accordingly
        jQuery('#edit_fee_type').on('change', function() {
            var opt = jQuery(this).find(':selected');
            var newAmt = parseFloat(opt.data('amount'));
            if (!isNaN(newAmt)) {
                document.getElementById('edit_fee_amount').value = newAmt.toFixed(2);
                document.getElementById('edit_net_payable').value = newAmt.toFixed(2);
                updateModalCalculations();
            }
        });

        // Submit AJAX Handler
        if (editForm) {
            editForm.addEventListener('submit', function(e) {
                e.preventDefault();

                var submitBtn    = document.getElementById('ifs_educore_save_fee_edit_btn');
                var originalText = submitBtn.innerHTML;
                submitBtn.disabled  = true;
                submitBtn.innerHTML = '<span class="dashicons dashicons-update spin"></span> Save...';

                var formData = new FormData();
                formData.append('action', 'ifs_educore_update_fee_invoice');
                formData.append('security', document.getElementById('edit_fee_nonce_field').value);
                formData.append('fee_id', document.getElementById('edit_fee_id').value);
                formData.append('fee_type', document.getElementById('edit_fee_type').value);
                formData.append('fee_month', document.getElementById('edit_fee_month').value);
                formData.append('fee_year', document.getElementById('edit_fee_year').value);
                formData.append('amount', document.getElementById('edit_fee_amount').value);
                formData.append('late_fine', document.getElementById('edit_fee_fine').value);
                formData.append('discount', document.getElementById('edit_fee_discount').value);
                formData.append('net_payable', document.getElementById('edit_net_payable').value);
                formData.append('paid_amount', document.getElementById('edit_paid_amount').value);
                formData.append('due_amount', document.getElementById('edit_due_amount').value);
                formData.append('payment_status', document.getElementById('edit_payment_status').value);

                var ajaxUrl = '<?php echo esc_url( admin_url( "admin-ajax.php" ) ); ?>';

                fetch(ajaxUrl, {
                    method: 'POST',
                    body: formData
                })
                .then(function(response) {
                    var contentType = response.headers.get('content-type');
                    var isJson = contentType && contentType.indexOf('application/json') !== -1;
                    return isJson ? response.json() : Promise.reject(response.statusText);
                })
                .then(function(data) {
                    submitBtn.disabled  = false;
                    submitBtn.innerHTML = originalText;

                    if (data && data.success) {
                        hideModal();
                        var url = new URL(window.location.href);
                        url.searchParams.set('msg', 'updated');
                        window.location.href = url.toString();
                    } else {
                        alert((data && data.data && data.data.message) || 'Error occurred while updating fee invoice.');
                    }
                })
                .catch(function(err) {
                    submitBtn.disabled  = false;
                    submitBtn.innerHTML = originalText;
                    console.error('AJAX Error:', err);
                    alert('Request failed: ' + (typeof err === 'string' ? err : 'Connection/Server error.'));
                });
            });
        }
    });

    jQuery(document).ready(function($) {
        if ($.fn.DataTable) {
            $('.educore-datatable').DataTable({ 
                "pageLength": 15, 
                "ordering": false,
                "responsive": true,
                "language": {
                    "search": "<?php echo esc_js( __( 'Search Ledger:', 'ifsedu-school-management' ) ); ?>",
                    "lengthMenu": "<?php echo esc_js( __( 'Show _MENU_ entries', 'ifsedu-school-management' ) ); ?>"
                }
            });
        }
    });
    </script>
    <?php
}
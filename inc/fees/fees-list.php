<?php
/**
 * Fees Directory & Financial Ledger View Engine (Role-Filtered for Accountant & Admin)
 * File: inc/fees/fees-list.php
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Handle Fee Invoice AJAX Update Action.
add_action( 'wp_ajax_ifs_educore_update_fee_invoice', 'ifs_educore_handle_update_fee_invoice_ajax' );
/**
 * AJAX Handler: Handle Fee Invoice Update Action
 */
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

/**
 * Render Fees Directory & Financial Ledger View Engine
 */
function educore_fees_list_view() {
    global $wpdb;
    $current_user = wp_get_current_user();
    $roles         = (array) $current_user->roles;

    // 1. Multi-Role Capability Security Matrix (Admins & Accountants).
    $is_admin      = current_user_can( 'manage_options' );
    $is_accountant = in_array( 'accountant', $roles, true ) || current_user_can( 'edit_posts' );

    if ( ! $is_admin && ! $is_accountant ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to view financial ledger records.', 'ifsedu-school-management' ) );
    }

    $table_fees     = $wpdb->prefix . 'sms_fees';
    $table_students = $wpdb->prefix . 'sms_students';
    $table_units    = $wpdb->prefix . 'sms_academic_units';
    $table_staff    = $wpdb->prefix . 'sms_staff';

    // 2. Sanitize and Extract Filter Request Inputs.
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

    // 3. Fetch Dropdown Options Dynamically ordered by sort_order.
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
    
    // Fetch Distinct Collectors / Accountants for the Filter Dropdown.
    $collectors_staff = $wpdb->get_results(
        "SELECT id, full_name, designation FROM `{$table_staff}` WHERE status = 'Active' ORDER BY full_name ASC"
    );
    // phpcs:enable

    // 4. Construct SQL Query WHERE Conditions.
    $where_clauses = array( '1=1' );
    $query_args     = array();

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

    // 5. Aggregate Ledger Totals with Active Filters Applied.
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

    // 6. Fetch Filtered Ledger Records with Student, Waiver & Entry Collector Details.
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

    <style>
        /* Enterprise Financial Dashboard & Ledger Styling Suite */
        .ifs-educore-fees-list-container {
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
            color: #0f172a !important;
        }

        /* Flash Feedback Banner */
        .ifs-educore-notice-banner {
            background: #ecfdf5 !important;
            border-left: 4px solid #00523c !important;
            color: #065f46 !important;
            padding: 14px 18px !important;
            border-radius: 10px !important;
            font-weight: 700 !important;
            font-size: 13.5px !important;
            margin-bottom: 24px !important;
            display: flex !important;
            align-items: center !important;
            gap: 10px !important;
            box-shadow: 0 4px 12px rgba(0, 82, 60, 0.05) !important;
        }
        .ifs-educore-notice-banner.updated {
            background: #eff6ff !important;
            border-left-color: #2563eb !important;
            color: #1e40af !important;
        }

        /* Professional Bento Grid Metrics */
        .ifs-educore-metrics-bento {
            display: grid !important;
            grid-template-columns: repeat(3, 1fr) !important;
            gap: 20px !important;
            margin-bottom: 28px !important;
        }
        .ifs-educore-metric-card {
            background: #ffffff !important;
            border: 1px solid #e2e8f0 !important;
            border-radius: 16px !important;
            padding: 24px 26px !important;
            box-shadow: 0 4px 20px -4px rgba(0, 0, 0, 0.03) !important;
            position: relative !important;
            overflow: hidden !important;
            display: flex !important;
            flex-direction: column !important;
            gap: 8px !important;
            box-sizing: border-box !important;
            border-top: 4px solid #00523c !important;
        }
        .ifs-educore-metric-card.invoiced { border-top-color: #2563eb !important; }
        .ifs-educore-metric-card.collected { border-top-color: #00523c !important; }
        .ifs-educore-metric-card.due { border-top-color: #dc2626 !important; }

        .ifs-educore-metric-label {
            font-size: 12.5px !important;
            font-weight: 700 !important;
            color: #64748b !important;
            text-transform: uppercase !important;
            letter-spacing: 0.05em !important;
        }
        .ifs-educore-metric-value {
            font-size: 28px !important;
            font-weight: 800 !important;
            letter-spacing: -0.02em !important;
        }
        .ifs-educore-metric-value.blue { color: #1d4ed8 !important; }
        .ifs-educore-metric-value.green { color: #047857 !important; }
        .ifs-educore-metric-value.red { color: #b91c1c !important; }

        /* Modern Pro Filter Card */
        .ifs-educore-filter-card {
            background: #ffffff !important;
            border: 1px solid #e2e8f0 !important;
            border-radius: 16px !important;
            padding: 26px 28px !important;
            margin-bottom: 28px !important;
            box-shadow: 0 4px 20px -4px rgba(0, 0, 0, 0.04) !important;
            box-sizing: border-box !important;
        }
        .ifs-educore-filter-form {
            display: grid !important;
            grid-template-columns: repeat(4, 1fr) !important;
            gap: 20px !important;
            align-items: flex-end !important;
            box-sizing: border-box !important;
        }
        .ifs-educore-filter-group {
            display: flex !important;
            flex-direction: column !important;
            gap: 7px !important;
            box-sizing: border-box !important;
        }
        .ifs-educore-filter-group label {
            font-size: 11.5px !important;
            font-weight: 800 !important;
            color: #475569 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.05em !important;
        }
        .ifs-educore-filter-select,
        .ifs-educore-filter-input {
            width: 100% !important;
            height: 42px !important;
            padding: 0 14px !important;
            background: #f8fafc !important;
            border: 1.5px solid #cbd5e1 !important;
            border-radius: 9px !important;
            font-size: 13.5px !important;
            color: #0f172a !important;
            box-sizing: border-box !important;
            outline: none !important;
            transition: all 0.2s ease !important;
        }
        .ifs-educore-filter-select:focus,
        .ifs-educore-filter-input:focus {
            border-color: #00523c !important;
            background: #ffffff !important;
            box-shadow: 0 0 0 3px rgba(0, 82, 60, 0.12) !important;
        }
        .ifs-educore-filter-actions {
            display: flex !important;
            gap: 10px !important;
            grid-column: span 4 !important;
            justify-content: flex-end !important;
            margin-top: 6px !important;
            padding-top: 16px !important;
            border-top: 1px solid #f1f5f9 !important;
        }
        .ifs-educore-btn-filter-submit {
            background: #00523c !important;
            color: #ffffff !important;
            border: none !important;
            height: 42px !important;
            padding: 0 24px !important;
            border-radius: 9px !important;
            font-size: 14px !important;
            font-weight: 700 !important;
            cursor: pointer !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 8px !important;
            box-shadow: 0 4px 12px rgba(0, 82, 60, 0.2) !important;
            transition: all 0.2s ease !important;
        }
        .ifs-educore-btn-filter-submit:hover {
            background: #047857 !important;
            transform: translateY(-1px);
        }
        .ifs-educore-btn-filter-reset {
            background: #f1f5f9 !important;
            color: #64748b !important;
            border: 1.5px solid #cbd5e1 !important;
            height: 42px !important;
            padding: 0 18px !important;
            border-radius: 9px !important;
            font-size: 13.5px !important;
            font-weight: 700 !important;
            text-decoration: none !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 6px !important;
            transition: all 0.2s ease !important;
            box-sizing: border-box !important;
        }
        .ifs-educore-btn-filter-reset:hover {
            background: #e2e8f0 !important;
            color: #0f172a !important;
            border-color: #94a3b8 !important;
        }

        /* Action Header Bar */
        .ifs-educore-actions-bar {
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            margin-bottom: 20px !important;
            flex-wrap: wrap !important;
            gap: 15px !important;
        }
        .ifs-educore-title {
            margin: 0 !important;
            font-size: 18px !important;
            font-weight: 800 !important;
            color: #0f172a !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 10px !important;
        }
        .ifs-educore-btn-collect {
            background: #00523c !important;
            color: #ffffff !important;
            padding: 10px 20px !important;
            border-radius: 10px !important;
            font-weight: 700 !important;
            font-size: 13.5px !important;
            text-decoration: none !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 8px !important;
            box-shadow: 0 4px 14px rgba(0, 82, 60, 0.2) !important;
            transition: background 0.2s ease !important;
        }
        .ifs-educore-btn-collect:hover {
            background: #047857 !important;
            color: #ffffff !important;
        }

        /* Table Bento Card Wrapper */
        .ifs-educore-bento-card {
            background: #ffffff !important;
            border: 1px solid #e2e8f0 !important;
            border-radius: 16px !important;
            padding: 24px !important;
            box-shadow: 0 4px 20px -4px rgba(0, 0, 0, 0.03) !important;
            box-sizing: border-box !important;
            overflow-x: auto !important;
        }
        .ifs-educore-table {
            width: 100% !important;
            border-collapse: collapse !important;
            font-size: 13px !important;
            text-align: left !important;
        }
        .ifs-educore-table th {
            background: #f8fafc !important;
            color: #475569 !important;
            font-weight: 800 !important;
            font-size: 11.5px !important;
            text-transform: uppercase !important;
            letter-spacing: 0.05em !important;
            padding: 12px 14px !important;
            border-bottom: 2px solid #e2e8f0 !important;
        }
        .ifs-educore-table td {
            padding: 14px 14px !important;
            border-bottom: 1px solid #f1f5f9 !important;
            vertical-align: middle !important;
            color: #1e293b !important;
        }
        .ifs-educore-table tbody tr {
            transition: background 0.15s ease !important;
        }
        .ifs-educore-table tbody tr:hover {
            background: #f8fafc !important;
        }

        /* Invoice Badge & Tags */
        .ifs-educore-invoice-code {
            font-family: monospace !important;
            font-weight: 800 !important;
            background: #f1f5f9 !important;
            color: #0f172a !important;
            padding: 4px 8px !important;
            border-radius: 6px !important;
            border: 1px solid #cbd5e1 !important;
            font-size: 12px !important;
        }
        .ifs-educore-waiver-tag {
            display: inline-block !important;
            background: #f0fdf4 !important;
            color: #15803d !important;
            border: 1px solid #bbf7d0 !important;
            padding: 2px 6px !important;
            border-radius: 4px !important;
            font-size: 10.5px !important;
            font-weight: 700 !important;
            margin-top: 4px !important;
        }

        /* Action Buttons & Details Icon */
        .ifs-educore-action-group {
            display: flex !important;
            align-items: center !important;
            justify-content: flex-end !important;
            gap: 6px !important;
        }
        .ifs-educore-square-btn {
            width: 32px !important;
            height: 32px !important;
            border-radius: 8px !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            border: 1px solid transparent !important;
            cursor: pointer !important;
            transition: all 0.2s ease !important;
            text-decoration: none !important;
        }
        .ifs-educore-square-btn .dashicons {
            font-size: 16px !important;
            width: 16px !important;
            height: 16px !important;
        }
        .ifs-educore-btn-details {
            background: #f8fafc !important;
            color: #0f172a !important;
            border-color: #cbd5e1 !important;
        }
        .ifs-educore-btn-details:hover {
            background: #00523c !important;
            color: #ffffff !important;
            border-color: #00523c !important;
        }
        .ifs-educore-btn-edit {
            background: #eff6ff !important;
            color: #2563eb !important;
            border-color: #bfdbfe !important;
        }
        .ifs-educore-btn-edit:hover {
            background: #2563eb !important;
            color: #ffffff !important;
        }
        .ifs-educore-btn-action-print {
            background: #f1f5f9 !important;
            color: #475569 !important;
            border: 1px solid #cbd5e1 !important;
            padding: 6px 10px !important;
            border-radius: 8px !important;
            font-size: 12px !important;
            font-weight: 700 !important;
            text-decoration: none !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 4px !important;
            transition: all 0.2s ease !important;
        }
        .ifs-educore-btn-action-print:hover {
            background: #e2e8f0 !important;
            color: #0f172a !important;
        }

        /* Native Pagination Layout Matching Students Directory */
        .ifs-educore-dt-footer-layout {
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            background: #ffffff !important;
            border: 1px solid #e2e8f0 !important;
            border-radius: 12px !important;
            padding: 14px 20px !important;
            font-size: 13px !important;
            font-weight: 600 !important;
            color: #475569 !important;
            box-shadow: 0 2px 8px rgba(0,0,0,0.02) !important;
            margin-top: 20px !important;
        }
        .ifs-educore-pagination-btn {
            height: 36px !important;
            padding: 0 16px !important;
            background: #f1f5f9 !important;
            color: #475569 !important;
            font-size: 13px !important;
            font-weight: 700 !important;
            border-radius: 8px !important;
            border: 1px solid #cbd5e1 !important;
            cursor: pointer !important;
            transition: all 0.2s ease !important;
        }
        .ifs-educore-pagination-btn:hover:not(:disabled) {
            background: #e2e8f0 !important;
            color: #0f172a !important;
        }
        .ifs-educore-pagination-btn:disabled {
            opacity: 0.5 !important;
            cursor: not-allowed !important;
        }

        /* Modal Overlay & Card Styling */
        .ifs-educore-modal-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.6);
            z-index: 999999;
            align-items: center;
            justify-content: center;
            padding: 20px;
            box-sizing: border-box;
            backdrop-filter: blur(4px);
        }
        .ifs-educore-modal-backdrop.is-visible {
            display: flex;
        }
        .ifs-educore-modal-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 28px;
            width: 100%;
            max-width: 500px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            box-sizing: border-box;
            animation: educoreModalFadeIn 0.25s ease;
        }
        @keyframes educoreModalFadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .ifs-educore-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 14px;
            margin-bottom: 20px;
        }
        .ifs-educore-modal-title {
            margin: 0;
            font-size: 17px;
            font-weight: 800;
            color: #0f172a;
        }
        .ifs-educore-modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #64748b;
            padding: 0;
            line-height: 1;
        }
        .ifs-educore-modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 24px;
            padding-top: 16px;
            border-top: 1px solid #f1f5f9;
        }
        .ifs-educore-btn-cancel {
            background: #f1f5f9;
            color: #64748b;
            border: 1.5px solid #cbd5e1;
            padding: 9px 18px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 13.5px;
            cursor: pointer;
        }
        .ifs-educore-btn-cancel:hover {
            background: #e2e8f0;
            color: #0f172a;
        }

        /* Responsive Breakpoints */
        @media screen and (max-width: 1024px) {
            .ifs-educore-metrics-bento {
                grid-template-columns: 1fr !important;
            }
        }
        @media screen and (max-width: 1200px) {
            .ifs-educore-filter-form {
                grid-template-columns: repeat(2, 1fr) !important;
            }
            .ifs-educore-filter-actions {
                grid-column: span 2 !important;
            }
        }
        @media screen and (max-width: 700px) {
            .ifs-educore-filter-form {
                grid-template-columns: 1fr !important;
            }
            .ifs-educore-filter-actions {
                grid-column: span 1 !important;
            }
        }
    </style>

    <div class="ifs-educore-fees-list-container">

        <!-- Flash Notice Feedback Banner -->
        <?php
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        if ( isset( $_GET['msg'] ) ) : 
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $msg_type = sanitize_text_field( wp_unslash( $_GET['msg'] ) );
        ?>
            <?php if ( 'collected' === $msg_type || 'success' === $msg_type ) : ?>
                <div class="ifs-educore-notice-banner">
                    <span class="dashicons dashicons-yes-alt" style="font-size:20px; width:20px; height:20px;"></span>
                    <span><?php esc_html_e( 'Fee payment received and recorded successfully.', 'ifsedu-school-management' ); ?></span>
                </div>
            <?php elseif ( 'updated' === $msg_type ) : ?>
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

        <!-- Modern Dynamic Filter Controls Card -->
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

        <!-- Main Invoices Table Card with Native Pagination Engine -->
        <div class="ifs-educore-bento-card">
            <table class="ifs-educore-table" id="ifs_educore_fees_main_table">
                <thead>
                    <tr>
                        <th style="width: 105px;"><?php esc_html_e( 'Invoice ID', 'ifsedu-school-management' ); ?></th>
                        <th><?php esc_html_e( 'Student Details', 'ifsedu-school-management' ); ?></th>
                        <th><?php esc_html_e( 'Month / Year', 'ifsedu-school-management' ); ?></th>
                        <th><?php esc_html_e( 'Fee Category', 'ifsedu-school-management' ); ?></th>
                        <th><?php esc_html_e( 'Net Payable', 'ifsedu-school-management' ); ?></th>
                        <th><?php esc_html_e( 'Entry / Collector', 'ifsedu-school-management' ); ?></th>
                        <th style="text-align: right; width: 130px;"><?php esc_html_e( 'Actions', 'ifsedu-school-management' ); ?></th>
                    </tr>
                </thead>
                <tbody id="ifs_educore_fees_table_body">
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

                        $view_url = add_query_arg(
                            array(
                                'page'    => 'school_management_system',
                                'tab'     => 'fees',
                                'sub'     => 'view',
                                'invoice' => $fee->invoice_id,
                            ),
                            admin_url( 'admin.php' )
                        );

                        $student_id_str = $fee->s_id ? strtoupper( (string) $fee->s_id ) : 'DELETED';
                        $class_str      = $fee->class_name ? $fee->class_name : 'Unassigned';
                        $section_str    = ! empty( $fee->section_name ) ? $fee->section_name : 'N/A';
                        $shift_str      = ( ! empty( $fee->shift ) && 'No Shift' !== $fee->shift ) ? ' | ' . $fee->shift : '';

                        // Determine Entry Collector Name & Date.
                        $collector_display = ! empty( $fee->col_staff_name ) ? $fee->col_staff_name : ( ! empty( $fee->collector_name ) ? $fee->collector_name : ( ! empty( $fee->recorded_by ) ? '#' . $fee->recorded_by : __( 'Admin / System', 'ifsedu-school-management' ) ) );
                        $entry_date_str    = ! empty( $fee->created_at ) ? date_i18n( 'M j, Y h:i A', strtotime( $fee->created_at ) ) : ( ! empty( $fee->payment_date ) ? date_i18n( 'M j, Y', strtotime( $fee->payment_date ) ) : '—' );
                    ?>
                    <tr class="ifs-educore-fees-row" data-fee-id="<?php echo esc_attr( $fee->id ); ?>">
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
                        <td>
                            <strong style="color: #0f172a; font-size: 12px;"><?php echo esc_html( $collector_display ); ?></strong>
                            <span style="font-size: 10.5px; color: #64748b; display: block; margin-top: 2px;">
                                <span class="dashicons dashicons-clock" style="font-size: 11px; width: 11px; height: 11px; vertical-align: middle;"></span>
                                <?php echo esc_html( $entry_date_str ); ?>
                            </span>
                        </td>
                        <td style="text-align: right;">
                            <div class="ifs-educore-action-group">
                                <!-- Details / View Button linking to View Page -->
                                <a href="<?php echo esc_url( $view_url ); ?>" class="ifs-educore-square-btn ifs-educore-btn-details" title="<?php esc_attr_e( 'View Invoice Details', 'ifsedu-school-management' ); ?>">
                                    <span class="dashicons dashicons-visibility"></span>
                                </a>

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

            <!-- Native Pagination Footer Engine -->
            <div id="ifs_educore_dt_footer_target" class="ifs-educore-dt-footer-layout">
                <div id="ifs_educore_table_info"><?php esc_html_e( 'Initializing...', 'ifsedu-school-management' ); ?></div>
                <div style="display: flex; gap: 8px;">
                    <button type="button" id="ifs_educore_prev_btn" class="ifs-educore-pagination-btn"><?php esc_html_e( 'Previous', 'ifsedu-school-management' ); ?></button>
                    <button type="button" id="ifs_educore_next_btn" class="ifs-educore-pagination-btn"><?php esc_html_e( 'Next', 'ifsedu-school-management' ); ?></button>
                </div>
            </div>
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

    <!-- Dynamic Script Layer: Section Chaining, Modal Control & Native Pagination Engine -->
    <script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function() {
        var unitsMap       = <?php echo wp_json_encode( ! empty( $all_units ) ? $all_units : array() ); ?>;
        var currentSection = "<?php echo esc_js( $filter_section ); ?>";
        var classSelect    = document.getElementById('filter_class');
        var sectionSelect  = document.getElementById('filter_section');

        // Populate Sections based on selected Class.
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
        // NATIVE PAGINATION & TABLE ENGINE (Matching All Students Module)
        // --------------------------------------------------------------------------
        const allRows = Array.from(document.querySelectorAll('#ifs_educore_fees_table_body tr.ifs-educore-fees-row'));
        const tableInfo = document.getElementById('ifs_educore_table_info');
        const prevBtn = document.getElementById('ifs_educore_prev_btn');
        const nextBtn = document.getElementById('ifs_educore_next_btn');

        let currentPage = 1;
        const pageSize = 15;
        let visibleRows = allRows;

        function renderPagination() {
            const total = visibleRows.length;
            const totalPages = Math.ceil(total / pageSize) || 1;

            if (currentPage > totalPages) currentPage = totalPages;
            if (currentPage < 1) currentPage = 1;

            const startIdx = (currentPage - 1) * pageSize;
            const endIdx = startIdx + pageSize;

            allRows.forEach(row => row.style.display = 'none');

            visibleRows.slice(startIdx, endIdx).forEach(row => {
                row.style.display = '';
            });

            if (total === 0) {
                tableInfo.textContent = '<?php echo esc_js( __( 'No matching fee invoice records found', 'ifsedu-school-management' ) ); ?>';
            } else {
                tableInfo.textContent = '<?php echo esc_js( __( 'Showing', 'ifsedu-school-management' ) ); ?> ' + (startIdx + 1) + ' <?php echo esc_js( __( 'to', 'ifsedu-school-management' ) ); ?> ' + Math.min(endIdx, total) + ' <?php echo esc_js( __( 'of', 'ifsedu-school-management' ) ); ?> ' + total + ' <?php echo esc_js( __( 'entries', 'ifsedu-school-management' ) ); ?>';
            }

            prevBtn.disabled = (currentPage === 1);
            nextBtn.disabled = (currentPage === totalPages || total === 0);
        }

        if (prevBtn) {
            prevBtn.addEventListener('click', function() {
                if (currentPage > 1) {
                    currentPage--;
                    renderPagination();
                }
            });
        }

        if (nextBtn) {
            nextBtn.addEventListener('click', function() {
                const totalPages = Math.ceil(visibleRows.length / pageSize) || 1;
                if (currentPage < totalPages) {
                    currentPage++;
                    renderPagination();
                }
            });
        }

        renderPagination();

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

        // Trigger Modal Open & Load Class Specific Categories.
        document.addEventListener('click', function(e) {
            var editBtn = e.target.closest('.btn-trigger-edit-fee');
            if (editBtn) {
                var id          = editBtn.getAttribute('data-id');
                var className = editBtn.getAttribute('data-class');
                var type        = editBtn.getAttribute('data-type');
                var month     = editBtn.getAttribute('data-month');
                var year        = editBtn.getAttribute('data-year');
                var amount    = editBtn.getAttribute('data-amount');
                var fine        = editBtn.getAttribute('data-fine');
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

                // Load Class-specific Fee Types.
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

        // When Fee Type changed in Edit Modal, update Net Payable accordingly.
        jQuery('#edit_fee_type').on('change', function() {
            var opt = jQuery(this).find(':selected');
            var newAmt = parseFloat(opt.data('amount'));
            if (!isNaN(newAmt)) {
                document.getElementById('edit_fee_amount').value = newAmt.toFixed(2);
                document.getElementById('edit_net_payable').value = newAmt.toFixed(2);
                updateModalCalculations();
            }
        });

        // Submit AJAX Handler.
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
    </script>
    <?php
}
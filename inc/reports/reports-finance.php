<?php
/**
 * Premium Financial Analytics & Transaction Audit Module
 * File: inc/reports/reports-finance-view.php
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Render Financial Analytics & Transaction Audit Report View
 */
function educore_reports_finance_view() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient administrative permissions to access financial analytics.', 'ifsedu-school-management' ) );
    }

    global $wpdb;
    $table_fees       = $wpdb->prefix . 'sms_fees';
    $table_accounting = $wpdb->prefix . 'sms_accounting';
    $table_students   = $wpdb->prefix . 'sms_students';
    $table_staff      = $wpdb->prefix . 'sms_staff';

    // --------------------------------------------------------------------------
    // 1. CAPTURE FILTER REQUEST INPUTS
    // --------------------------------------------------------------------------
    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $start_date      = isset( $_GET['start_date'] )     ? sanitize_text_field( wp_unslash( $_GET['start_date'] ) )     : gmdate( 'Y-m-01' );
    $end_date        = isset( $_GET['end_date'] )       ? sanitize_text_field( wp_unslash( $_GET['end_date'] ) )       : gmdate( 'Y-m-t' );
    $scope_filter    = isset( $_GET['scope'] )          ? sanitize_text_field( wp_unslash( $_GET['scope'] ) )          : 'all';
    $category_filter = isset( $_GET['fee_category'] )   ? sanitize_text_field( wp_unslash( $_GET['fee_category'] ) )   : '';
    $status_filter   = isset( $_GET['payment_status'] ) ? sanitize_text_field( wp_unslash( $_GET['payment_status'] ) ) : '';
    $method_filter   = isset( $_GET['payment_method'] ) ? sanitize_text_field( wp_unslash( $_GET['payment_method'] ) ) : '';
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    // --------------------------------------------------------------------------
    // 2. UNIFIED FILTERED TRANSACTION LOGS QUERY
    // --------------------------------------------------------------------------
    $combined_logs = array();

    // Query Student Fees if matching scope.
    if ( in_array( $scope_filter, array( 'all', 'fees' ), true ) ) {
        $where_fees  = array( 'DATE(f.payment_date) BETWEEN %s AND %s' );
        $params_fees = array( $start_date, $end_date );

        if ( ! empty( $category_filter ) ) {
            $where_fees[]  = 'f.fee_type = %s';
            $params_fees[] = $category_filter;
        }

        if ( ! empty( $status_filter ) ) {
            $where_fees[]  = 'f.payment_status = %s';
            $params_fees[] = $status_filter;
        }

        if ( ! empty( $method_filter ) ) {
            $where_fees[]  = 'f.payment_method = %s';
            $params_fees[] = $method_filter;
        }

        $where_fees_sql = implode( ' AND ', $where_fees );

        $fees_sql = "SELECT 
            f.payment_date as trans_date,
            f.invoice_id as ref_code,
            'Student Fee' as flow_group,
            'Income' as flow_type,
            f.fee_type as category,
            COALESCE(s.full_name, 'Direct Fee Payment') as party_name,
            s.student_id as student_uid,
            s.class_name,
            s.section_name,
            s.shift,
            s.waiver_percentage,
            st.full_name as waiver_ref_staff,
            f.payment_method,
            f.net_payable,
            f.paid_amount,
            f.due_amount,
            f.payment_status
            FROM `{$table_fees}` f
            LEFT JOIN `{$table_students}` s ON f.student_id = s.id
            LEFT JOIN `{$table_staff}` st ON s.waiver_staff_id = st.id
            WHERE {$where_fees_sql}";

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $fees_rows = $wpdb->get_results( $wpdb->prepare( $fees_sql, ...$params_fees ) );
        // phpcs:enable
        
        if ( ! empty( $fees_rows ) && is_array( $fees_rows ) ) {
            $combined_logs = array_merge( $combined_logs, $fees_rows );
        }
    }

    // Query General Accounting if matching scope.
    if ( in_array( $scope_filter, array( 'all', 'general_income', 'general_expense' ), true ) ) {
        $where_acct  = array( 'a.entry_date BETWEEN %s AND %s' );
        $params_acct = array( $start_date, $end_date );

        if ( 'general_income' === $scope_filter ) {
            $where_acct[] = "a.entry_type = 'Income'";
        } elseif ( 'general_expense' === $scope_filter ) {
            $where_acct[] = "a.entry_type = 'Expense'";
        }

        if ( ! empty( $category_filter ) ) {
            $where_acct[]  = 'a.category_name = %s';
            $params_acct[] = $category_filter;
        }

        if ( ! empty( $method_filter ) ) {
            $where_acct[]  = 'a.payment_method = %s';
            $params_acct[] = $method_filter;
        }

        if ( empty( $status_filter ) || 'Paid' === $status_filter ) {
            $where_acct_sql = implode( ' AND ', $where_acct );

            $acct_sql = "SELECT 
                a.entry_date as trans_date,
                a.voucher_no as ref_code,
                CONCAT('General ', a.entry_type) as flow_group,
                a.entry_type as flow_type,
                a.category_name as category,
                COALESCE(NULLIF(a.party_name, ''), a.title) as party_name,
                '' as student_uid,
                '' as class_name,
                '' as section_name,
                '' as shift,
                0.00 as waiver_percentage,
                '' as waiver_ref_staff,
                a.payment_method,
                a.amount as net_payable,
                a.amount as paid_amount,
                0.00 as due_amount,
                'Paid' as payment_status
                FROM `{$table_accounting}` a
                WHERE {$where_acct_sql}";

            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $acct_rows = $wpdb->get_results( $wpdb->prepare( $acct_sql, ...$params_acct ) );
            // phpcs:enable
            
            if ( ! empty( $acct_rows ) && is_array( $acct_rows ) ) {
                $combined_logs = array_merge( $combined_logs, $acct_rows );
            }
        }
    }

    // Sort Combined Logs chronologically descending.
    if ( ! empty( $combined_logs ) && is_array( $combined_logs ) ) {
        usort( $combined_logs, function( $a, $b ) {
            $time_a = ! empty( $a->trans_date ) ? strtotime( $a->trans_date ) : 0;
            $time_b = ! empty( $b->trans_date ) ? strtotime( $b->trans_date ) : 0;
            return $time_b - $time_a;
        } );
    }

    // --------------------------------------------------------------------------
    // 3. DYNAMIC SUMMARY METRICS CALCULATION
    // --------------------------------------------------------------------------
    $total_revenue_inflow = 0.00;
    $total_expenses       = 0.00;
    $total_pending_dues   = 0.00;

    foreach ( $combined_logs as $log_item ) {
        if ( 'Income' === $log_item->flow_type ) {
            $total_revenue_inflow += (float) $log_item->paid_amount;
        } elseif ( 'Expense' === $log_item->flow_type ) {
            $total_expenses += (float) $log_item->paid_amount;
        }
        $total_pending_dues += (float) $log_item->due_amount;
    }

    $net_operating_cash = $total_revenue_inflow - $total_expenses;

    $base_report_url = add_query_arg(
        array(
            'page' => 'school_management_system',
            'tab'  => 'reports',
            'sub'  => 'finance',
        ),
        admin_url( 'admin.php' )
    );
    ?>

    <style id="ifs-educore-finance-report-styles">
        .ifs-educore-finance-root {
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #0f172a;
        }

        .ifs-educore-header-frame {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 15px -3px rgba(0, 0, 0, 0.03);
            margin-bottom: 24px;
        }

        .ifs-educore-header-content h2 {
            margin: 0 0 4px 0;
            font-size: 18px;
            font-weight: 800;
            color: #00523c;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .ifs-educore-header-content p {
            margin: 0;
            font-size: 13.5px;
            color: #64748b;
        }

        .ifs-educore-filter-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 15px -3px rgba(0, 0, 0, 0.03);
            margin-bottom: 24px;
        }

        .ifs-educore-filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)) auto;
            gap: 16px;
            align-items: flex-end;
        }

        .ifs-educore-field-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .ifs-educore-label {
            font-size: 12.5px;
            font-weight: 700;
            color: #334155;
            letter-spacing: -0.1px;
        }

        .ifs-educore-input-control {
            width: 100%;
            height: 40px;
            border: 1.5px solid #cbd5e1;
            border-radius: 8px;
            padding: 0 12px;
            font-size: 13px;
            color: #0f172a;
            background: #ffffff;
            box-sizing: border-box;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .ifs-educore-input-control:focus {
            border-color: #00523c;
            box-shadow: 0 0 0 3px rgba(0, 82, 60, 0.12);
        }

        .ifs-educore-btn-generate {
            height: 40px;
            padding: 0 18px;
            background: #00523c;
            color: #ffffff;
            border: none;
            border-radius: 8px;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0, 82, 60, 0.18);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: background 0.2s;
            text-decoration: none;
        }

        .ifs-educore-btn-generate:hover {
            background: #047857;
            color: #ffffff;
        }

        .ifs-educore-btn-reset {
            height: 40px;
            padding: 0 14px;
            background: #f1f5f9;
            color: #475569;
            border: 1.5px solid #cbd5e1;
            border-radius: 8px;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: background 0.2s;
        }

        .ifs-educore-btn-reset:hover {
            background: #e2e8f0;
            color: #0f172a;
        }

        /* Metrics Bento Grid */
        .ifs-educore-metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }

        .ifs-educore-metric-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 4px 15px -3px rgba(0, 0, 0, 0.03);
            position: relative;
            overflow: hidden;
            border-left: 4px solid #00523c;
        }

        .ifs-educore-card-emerald { border-left-color: #059669; }
        .ifs-educore-card-rose { border-left-color: #dc2626; }
        .ifs-educore-card-blue { border-left-color: #2563eb; }
        .ifs-educore-card-amber { border-left-color: #d97706; }

        .ifs-educore-metric-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .ifs-educore-metric-label {
            font-size: 11.5px;
            font-weight: 800;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .ifs-educore-metric-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #00523c;
        }

        .ifs-educore-metric-value {
            font-size: 22px;
            font-weight: 900;
            color: #0f172a;
        }

        /* Table Card */
        .ifs-educore-table-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.03);
            margin-bottom: 30px;
        }

        .ifs-educore-table-header {
            padding: 20px 24px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            background: #f8fafc;
        }

        .ifs-educore-table-title {
            margin: 0;
            font-size: 15px;
            font-weight: 800;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .ifs-educore-btn-print {
            height: 36px;
            padding: 0 16px;
            background: #ffffff;
            color: #334155;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-weight: 700;
            font-size: 12.5px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
            transition: all 0.2s ease;
        }

        .ifs-educore-btn-print:hover {
            background: #f1f5f9;
            color: #0f172a;
        }

        .ifs-educore-table-wrapper {
            overflow-x: auto;
        }

        .ifs-educore-data-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 13px;
        }

        .ifs-educore-data-table th {
            padding: 12px 18px;
            color: #475569;
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            font-size: 11.5px;
            text-transform: capitalize;
            font-weight: 800;
        }

        .ifs-educore-data-table td {
            padding: 12px 18px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        .ifs-educore-ref-badge {
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            color: #334155;
            font-size: 10.5px;
            font-weight: 700;
            padding: 1px 6px;
            border-radius: 4px;
        }

        .ifs-educore-badge {
            display: inline-flex;
            align-items: center;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11.5px;
            font-weight: 700;
        }

        .ifs-educore-badge-income { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
        .ifs-educore-badge-expense { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }

        .ifs-educore-badge-paid { background: #f0fdf4; color: #059669; border: 1px solid #bbf7d0; }
        .ifs-educore-badge-partial { background: #fff7ed; color: #d97706; border: 1px solid #fed7aa; }
        .ifs-educore-badge-unpaid { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }

        .ifs-educore-waiver-tag {
            display: inline-block;
            background: #fef3c7;
            color: #b45309;
            font-size: 10.5px;
            font-weight: 700;
            padding: 1px 6px;
            border-radius: 4px;
            margin-top: 3px;
            border: 1px solid #fde68a;
        }

        @media print {
            .no-print { display: none !important; }
            body, .ifs-educore-finance-root { background: #ffffff !important; padding: 0 !important; }
            .ifs-educore-table-card { border: none !important; box-shadow: none !important; }
        }
    </style>

    <div class="ifs-educore-finance-root">
        
        <!-- Header Banner -->
        <div class="ifs-educore-header-frame no-print">
            <div class="ifs-educore-header-content">
                <h2>
                    <span class="dashicons dashicons-chart-bar" style="color:#00523c;"></span>
                    <?php esc_html_e( 'Financial Statement & Revenue Audit', 'ifsedu-school-management' ); ?>
                </h2>
                <p><?php esc_html_e( 'Comprehensive period-wise fee collection, operating expenses, and cash flow audit trail.', 'ifsedu-school-management' ); ?></p>
            </div>
        </div>

        <!-- Filter Control Matrix Card -->
        <div class="ifs-educore-filter-card no-print">
            <form method="GET" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
                <input type="hidden" name="page" value="school_management_system">
                <input type="hidden" name="tab" value="reports">
                <input type="hidden" name="sub" value="finance">
                
                <div class="ifs-educore-filter-grid">
                    
                    <!-- 1. Scope Selector -->
                    <div class="ifs-educore-field-group">
                        <label class="ifs-educore-label"><?php esc_html_e( 'Financial Scope', 'ifsedu-school-management' ); ?></label>
                        <select name="scope" class="ifs-educore-input-control">
                            <option value="all" <?php selected( $scope_filter, 'all' ); ?>><?php esc_html_e( 'All Cash & Ledger (Income + Expense)', 'ifsedu-school-management' ); ?></option>
                            <option value="fees" <?php selected( $scope_filter, 'fees' ); ?>><?php esc_html_e( 'Student Academic Fees Only', 'ifsedu-school-management' ); ?></option>
                            <option value="general_income" <?php selected( $scope_filter, 'general_income' ); ?>><?php esc_html_e( 'General Incomes (+)', 'ifsedu-school-management' ); ?></option>
                            <option value="general_expense" <?php selected( $scope_filter, 'general_expense' ); ?>><?php esc_html_e( 'Operating Expenses (-)', 'ifsedu-school-management' ); ?></option>
                        </select>
                    </div>

                    <!-- 2. Category Dropdown -->
                    <div class="ifs-educore-field-group">
                        <label class="ifs-educore-label"><?php esc_html_e( 'Fee / Entry Category', 'ifsedu-school-management' ); ?></label>
                        <select name="fee_category" class="ifs-educore-input-control">
                            <option value=""><?php esc_html_e( '-- All Categories --', 'ifsedu-school-management' ); ?></option>
                            <optgroup label="<?php esc_attr_e( 'Student Academic Fees', 'ifsedu-school-management' ); ?>">
                                <option value="Tuition Fee" <?php selected( $category_filter, 'Tuition Fee' ); ?>><?php esc_html_e( 'Tuition Fee', 'ifsedu-school-management' ); ?></option>
                                <option value="Admission Fee" <?php selected( $category_filter, 'Admission Fee' ); ?>><?php esc_html_e( 'Admission Fee', 'ifsedu-school-management' ); ?></option>
                                <option value="Exam Fee" <?php selected( $category_filter, 'Exam Fee' ); ?>><?php esc_html_e( 'Exam Fee', 'ifsedu-school-management' ); ?></option>
                                <option value="Transport Fee" <?php selected( $category_filter, 'Transport Fee' ); ?>><?php esc_html_e( 'Transport Fee', 'ifsedu-school-management' ); ?></option>
                                <option value="Hostel Fee" <?php selected( $category_filter, 'Hostel Fee' ); ?>><?php esc_html_e( 'Hostel Fee', 'ifsedu-school-management' ); ?></option>
                                <option value="Other Charges" <?php selected( $category_filter, 'Other Charges' ); ?>><?php esc_html_e( 'Other Charges', 'ifsedu-school-management' ); ?></option>
                            </optgroup>
                            <optgroup label="<?php esc_attr_e( 'General Ledger Categories', 'ifsedu-school-management' ); ?>">
                                <option value="Staff Salary & Remuneration" <?php selected( $category_filter, 'Staff Salary & Remuneration' ); ?>><?php esc_html_e( 'Staff Salary', 'ifsedu-school-management' ); ?></option>
                                <option value="Utility Bills (Electricity/Gas/Water)" <?php selected( $category_filter, 'Utility Bills (Electricity/Gas/Water)' ); ?>><?php esc_html_e( 'Utility Bills', 'ifsedu-school-management' ); ?></option>
                                <option value="Maintenance & Infrastructure Repair" <?php selected( $category_filter, 'Maintenance & Infrastructure Repair' ); ?>><?php esc_html_e( 'Maintenance & Repairs', 'ifsedu-school-management' ); ?></option>
                                <option value="Government Grant" <?php selected( $category_filter, 'Government Grant' ); ?>><?php esc_html_e( 'Government Grant', 'ifsedu-school-management' ); ?></option>
                                <option value="Donation & Sponsorship" <?php selected( $category_filter, 'Donation & Sponsorship' ); ?>><?php esc_html_e( 'Donation & Sponsorship', 'ifsedu-school-management' ); ?></option>
                                <option value="Other Expenses" <?php selected( $category_filter, 'Other Expenses' ); ?>><?php esc_html_e( 'Other Expenses', 'ifsedu-school-management' ); ?></option>
                            </optgroup>
                        </select>
                    </div>

                    <!-- 3. Payment Status -->
                    <div class="ifs-educore-field-group">
                        <label class="ifs-educore-label"><?php esc_html_e( 'Status', 'ifsedu-school-management' ); ?></label>
                        <select name="payment_status" class="ifs-educore-input-control">
                            <option value=""><?php esc_html_e( '-- All Statuses --', 'ifsedu-school-management' ); ?></option>
                            <option value="Paid" <?php selected( $status_filter, 'Paid' ); ?>><?php esc_html_e( 'Paid (Settled)', 'ifsedu-school-management' ); ?></option>
                            <option value="Partial" <?php selected( $status_filter, 'Partial' ); ?>><?php esc_html_e( 'Partial Payment', 'ifsedu-school-management' ); ?></option>
                            <option value="Unpaid" <?php selected( $status_filter, 'Unpaid' ); ?>><?php esc_html_e( 'Unpaid / Due', 'ifsedu-school-management' ); ?></option>
                        </select>
                    </div>

                    <!-- 4. Payment Method -->
                    <div class="ifs-educore-field-group">
                        <label class="ifs-educore-label"><?php esc_html_e( 'Method', 'ifsedu-school-management' ); ?></label>
                        <select name="payment_method" class="ifs-educore-input-control">
                            <option value=""><?php esc_html_e( '-- All Methods --', 'ifsedu-school-management' ); ?></option>
                            <option value="Cash" <?php selected( $method_filter, 'Cash' ); ?>><?php esc_html_e( 'Cash', 'ifsedu-school-management' ); ?></option>
                            <option value="Bank Transfer" <?php selected( $method_filter, 'Bank Transfer' ); ?>><?php esc_html_e( 'Bank Transfer', 'ifsedu-school-management' ); ?></option>
                            <option value="bKash" <?php selected( $method_filter, 'bKash' ); ?>><?php esc_html_e( 'bKash', 'ifsedu-school-management' ); ?></option>
                            <option value="Nagad" <?php selected( $method_filter, 'Nagad' ); ?>><?php esc_html_e( 'Nagad', 'ifsedu-school-management' ); ?></option>
                            <option value="Cheque" <?php selected( $method_filter, 'Cheque' ); ?>><?php esc_html_e( 'Cheque', 'ifsedu-school-management' ); ?></option>
                        </select>
                    </div>

                    <!-- 5. Date From -->
                    <div class="ifs-educore-field-group">
                        <label class="ifs-educore-label"><?php esc_html_e( 'From Date', 'ifsedu-school-management' ); ?></label>
                        <input type="date" name="start_date" class="ifs-educore-input-control" value="<?php echo esc_attr( $start_date ); ?>" required>
                    </div>

                    <!-- 6. Date To -->
                    <div class="ifs-educore-field-group">
                        <label class="ifs-educore-label"><?php esc_html_e( 'To Date', 'ifsedu-school-management' ); ?></label>
                        <input type="date" name="end_date" class="ifs-educore-input-control" value="<?php echo esc_attr( $end_date ); ?>" required>
                    </div>

                    <!-- Actions -->
                    <div style="display:flex; gap:8px;">
                        <button type="submit" class="ifs-educore-btn-generate">
                            <span class="dashicons dashicons-filter"></span> <?php esc_html_e( 'Filter', 'ifsedu-school-management' ); ?>
                        </button>
                        <a href="<?php echo esc_url( $base_report_url ); ?>" class="ifs-educore-btn-reset">
                            <?php esc_html_e( 'Reset', 'ifsedu-school-management' ); ?>
                        </a>
                    </div>

                </div>
            </form>
        </div>

        <!-- Summary Metric Bento Cards Matrix -->
        <div class="ifs-educore-metrics-grid">
            
            <!-- Card 1: Total Revenue Inflow -->
            <div class="ifs-educore-metric-card ifs-educore-card-emerald">
                <div class="ifs-educore-metric-header">
                    <span class="ifs-educore-metric-label"><?php esc_html_e( 'Total Inflow (+)', 'ifsedu-school-management' ); ?></span>
                    <div class="ifs-educore-metric-icon">
                        <span class="dashicons dashicons-money-alt"></span>
                    </div>
                </div>
                <div class="ifs-educore-metric-value">
                    ৳<?php echo esc_html( number_format( $total_revenue_inflow, 2 ) ); ?>
                </div>
            </div>

            <!-- Card 2: Operating Expenses Outflow -->
            <div class="ifs-educore-metric-card ifs-educore-card-rose">
                <div class="ifs-educore-metric-header">
                    <span class="ifs-educore-metric-label"><?php esc_html_e( 'Total Outflow (-)', 'ifsedu-school-management' ); ?></span>
                    <div class="ifs-educore-metric-icon">
                        <span class="dashicons dashicons-arrow-down-alt"></span>
                    </div>
                </div>
                <div class="ifs-educore-metric-value">
                    ৳<?php echo esc_html( number_format( $total_expenses, 2 ) ); ?>
                </div>
            </div>

            <!-- Card 3: Net Cash Balance -->
            <div class="ifs-educore-metric-card ifs-educore-card-blue">
                <div class="ifs-educore-metric-header">
                    <span class="ifs-educore-metric-label"><?php esc_html_e( 'Net Operating Cash', 'ifsedu-school-management' ); ?></span>
                    <div class="ifs-educore-metric-icon">
                        <span class="dashicons dashicons-chart-line"></span>
                    </div>
                </div>
                <div class="ifs-educore-metric-value">
                    ৳<?php echo esc_html( number_format( $net_operating_cash, 2 ) ); ?>
                </div>
            </div>

            <!-- Card 4: Total Pending Student Dues -->
            <div class="ifs-educore-metric-card ifs-educore-card-amber">
                <div class="ifs-educore-metric-header">
                    <span class="ifs-educore-metric-label"><?php esc_html_e( 'Pending Dues', 'ifsedu-school-management' ); ?></span>
                    <div class="ifs-educore-metric-icon">
                        <span class="dashicons dashicons-warning"></span>
                    </div>
                </div>
                <div class="ifs-educore-metric-value">
                    ৳<?php echo esc_html( number_format( $total_pending_dues, 2 ) ); ?>
                </div>
            </div>

        </div>

        <!-- Transaction Audit Log Table -->
        <div class="ifs-educore-table-card">
            <div class="ifs-educore-table-header">
                <h3 class="ifs-educore-table-title">
                    <span class="dashicons dashicons-list-view" style="color:#00523c;"></span> 
                    <?php 
                    $start_ts = ! empty( $start_date ) ? strtotime( $start_date ) : false;
                    $end_ts   = ! empty( $end_date ) ? strtotime( $end_date ) : false;
                    $start_str = $start_ts ? date_i18n( 'd M Y', $start_ts ) : '—';
                    $end_str   = $end_ts ? date_i18n( 'd M Y', $end_ts ) : '—';

                    printf( 
                        /* translators: 1: Start date, 2: End date */
                        esc_html__( 'Transaction Audit Trail (%1$s - %2$s)', 'ifsedu-school-management' ),
                        esc_html( $start_str ),
                        esc_html( $end_str )
                    ); 
                    ?>
                </h3>
                <button onclick="window.print()" class="ifs-educore-btn-print no-print">
                    <span class="dashicons dashicons-printer"></span> <?php esc_html_e( 'Print Financial Statement', 'ifsedu-school-management' ); ?>
                </button>
            </div>

            <div class="ifs-educore-table-wrapper">
                <table class="ifs-educore-data-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Date & Voucher', 'ifsedu-school-management' ); ?></th>
                            <th><?php esc_html_e( 'Flow', 'ifsedu-school-management' ); ?></th>
                            <th><?php esc_html_e( 'Category', 'ifsedu-school-management' ); ?></th>
                            <th><?php esc_html_e( 'Student / Payer / Payee Details', 'ifsedu-school-management' ); ?></th>
                            <th><?php esc_html_e( 'Method', 'ifsedu-school-management' ); ?></th>
                            <th><?php esc_html_e( 'Net Payable (৳)', 'ifsedu-school-management' ); ?></th>
                            <th><?php esc_html_e( 'Paid / Settled (৳)', 'ifsedu-school-management' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'ifsedu-school-management' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( ! empty( $combined_logs ) && is_array( $combined_logs ) ) : foreach ( $combined_logs as $log ) : 
                            $is_income    = ( 'Income' === $log->flow_type );
                            $status_class = 'ifs-educore-badge-unpaid';
                            if ( 'Paid' === $log->payment_status ) {
                                $status_class = 'ifs-educore-badge-paid';
                            } elseif ( 'Partial' === $log->payment_status ) {
                                $status_class = 'ifs-educore-badge-partial';
                            }

                            $trans_ts = ! empty( $log->trans_date ) ? strtotime( $log->trans_date ) : false;
                            $trans_str = $trans_ts ? date_i18n( 'd M Y', $trans_ts ) : '—';
                        ?>
                        <tr>
                            <td>
                                <strong style="color:#0f172a;"><?php echo esc_html( $trans_str ); ?></strong><br>
                                <span class="ifs-educore-ref-badge">#<?php echo esc_html( $log->ref_code ); ?></span>
                            </td>
                            <td>
                                <span class="ifs-educore-badge <?php echo $is_income ? 'ifs-educore-badge-income' : 'ifs-educore-badge-expense'; ?>">
                                    <?php echo esc_html( $log->flow_group ); ?>
                                </span>
                            </td>
                            <td><strong><?php echo esc_html( $log->category ); ?></strong></td>
                            <td>
                                <span style="font-weight:700; color:#1e293b;"><?php echo esc_html( $log->party_name ); ?></span>
                                <?php if ( ! empty( $log->student_uid ) ) : ?>
                                    <small style="display:block; color:#64748b; font-size:11.5px;">
                                        <?php esc_html_e( 'ID:', 'ifsedu-school-management' ); ?> <?php echo esc_html( (string) $log->student_uid ); ?> | <?php esc_html_e( 'Class:', 'ifsedu-school-management' ); ?> <?php echo esc_html( $log->class_name ); ?><?php echo ! empty( $log->section_name ) ? ' (' . esc_html( $log->section_name ) . ')' : ''; ?><?php echo ( ! empty( $log->shift ) && 'No Shift' !== $log->shift ) ? ' [' . esc_html( $log->shift ) . ']' : ''; ?>
                                    </small>
                                    <?php if ( ! empty( $log->waiver_percentage ) && floatval( $log->waiver_percentage ) > 0 ) : ?>
                                        <span class="ifs-educore-waiver-tag">
                                            <?php echo esc_html( floatval( $log->waiver_percentage ) ); ?>% <?php esc_html_e( 'Waiver', 'ifsedu-school-management' ); ?> <?php echo ! empty( $log->waiver_ref_staff ) ? esc_html( '[' . $log->waiver_ref_staff . ']' ) : ''; ?>
                                        </span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span style="background:#f8fafc; border:1px solid #e2e8f0; padding:2px 8px; border-radius:4px; font-weight:600; font-size:12px;">
                                    <?php echo esc_html( $log->payment_method ); ?>
                                </span>
                            </td>
                            <td>৳<?php echo esc_html( number_format( (float) $log->net_payable, 2 ) ); ?></td>
                            <td style="color:<?php echo $is_income ? '#00523c' : '#dc2626'; ?>; font-weight:800;">
                                <?php echo $is_income ? '+' : '-'; ?>৳<?php echo esc_html( number_format( (float) $log->paid_amount, 2 ) ); ?>
                            </td>
                            <td>
                                <span class="ifs-educore-badge <?php echo esc_attr( $status_class ); ?>">
                                    <?php echo esc_html( $log->payment_status ); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; else : ?>
                        <tr>
                            <td colspan="8" style="padding: 40px; text-align: center; color: #94a3b8;">
                                <span class="dashicons dashicons-chart-bar" style="font-size:32px; width:32px; height:32px; margin-bottom:8px;"></span>
                                <p style="margin:0; font-weight:600;"><?php esc_html_e( 'No financial transaction records matched your filter criteria.', 'ifsedu-school-management' ); ?></p>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
    <?php
}
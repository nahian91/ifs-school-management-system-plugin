<?php
/**
 * Master Financial Ledger Table View (Enterprise Neo-Bento Dashboard)
 * File: inc/accounting/accounting-list.php
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Direct access safety buffer
}

function educore_accounting_list_view() {
    global $wpdb;
    $current_user = wp_get_current_user();
    $table_staff  = $wpdb->prefix . 'sms_staff';

    // --------------------------------------------------------------------------
    // 0. CAPABILITY & ROLE PERMISSION VALIDATION
    // --------------------------------------------------------------------------
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
        wp_die( esc_html__( 'You do not have sufficient permissions to access the financial ledger.', 'ifsedu-school-management' ) );
    }

    $table_accounting = $wpdb->prefix . 'sms_accounting';

    // --------------------------------------------------------------------------
    // 1. FILTER & SEARCH QUERY PROCESSING
    // --------------------------------------------------------------------------
    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $filter_type     = isset( $_GET['entry_type'] ) ? sanitize_text_field( wp_unslash( $_GET['entry_type'] ) ) : 'all';
    $filter_category = isset( $_GET['category'] ) ? sanitize_text_field( wp_unslash( $_GET['category'] ) ) : '';
    $filter_method   = isset( $_GET['payment_method'] ) ? sanitize_text_field( wp_unslash( $_GET['payment_method'] ) ) : '';
    $search_query    = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
    $from_date       = isset( $_GET['from_date'] ) ? sanitize_text_field( wp_unslash( $_GET['from_date'] ) ) : '';
    $to_date         = isset( $_GET['to_date'] ) ? sanitize_text_field( wp_unslash( $_GET['to_date'] ) ) : '';
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    $where_clauses = array();
    $query_params  = array();

    if ( in_array( $filter_type, array( 'Income', 'Expense' ), true ) ) {
        $where_clauses[] = 'ac.entry_type = %s';
        $query_params[]  = $filter_type;
    }

    if ( ! empty( $filter_category ) ) {
        $where_clauses[] = 'ac.category_name = %s';
        $query_params[]  = $filter_category;
    }

    if ( ! empty( $filter_method ) ) {
        $where_clauses[] = 'ac.payment_method = %s';
        $query_params[]  = $filter_method;
    }

    if ( ! empty( $from_date ) ) {
        $where_clauses[] = 'ac.entry_date >= %s';
        $query_params[]  = $from_date;
    }

    if ( ! empty( $to_date ) ) {
        $where_clauses[] = 'ac.entry_date <= %s';
        $query_params[]  = $to_date;
    }

    if ( ! empty( $search_query ) ) {
        $where_clauses[] = '(ac.title LIKE %s OR ac.voucher_no LIKE %s OR ac.party_name LIKE %s OR ac.note LIKE %s)';
        $search_like     = '%' . $wpdb->esc_like( $search_query ) . '%';
        $query_params[]  = $search_like;
        $query_params[]  = $search_like;
        $query_params[]  = $search_like;
        $query_params[]  = $search_like;
    }

    $where_sql = ! empty( $where_clauses ) ? ' WHERE ' . implode( ' AND ', $where_clauses ) : '';

    // Fetch Filtered Ledger Records with Staff Name Mapping
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $query = "SELECT ac.*, st.full_name as staff_full_name, u.display_name as wp_user_name 
              FROM `{$table_accounting}` ac 
              LEFT JOIN `{$table_staff}` st ON ac.created_by = st.wp_user_id 
              LEFT JOIN `{$wpdb->users}` u ON ac.created_by = u.ID" . $where_sql . " 
              ORDER BY ac.entry_date DESC, ac.id DESC";

    if ( ! empty( $where_clauses ) ) {
        $ledger_records = $wpdb->get_results( $wpdb->prepare( $query, ...$query_params ) );
    } else {
        $ledger_records = $wpdb->get_results( $query );
    }

    // Dynamic Categories for Dropdown
    $available_categories = $wpdb->get_col( "SELECT DISTINCT category_name FROM `{$table_accounting}` WHERE category_name != '' ORDER BY category_name ASC" );

    // --------------------------------------------------------------------------
    // 2. FINANCIAL METRICS & ANALYTICS
    // --------------------------------------------------------------------------
    $total_income  = (float) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(amount) FROM `{$table_accounting}` WHERE entry_type = %s", 'Income' ) );
    $total_expense = (float) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(amount) FROM `{$table_accounting}` WHERE entry_type = %s", 'Expense' ) );
    $net_balance   = $total_income - $total_expense;

    $current_month_start = current_time( 'Y-m-01' );
    $current_month_end   = current_time( 'Y-m-t' );

    $month_income = (float) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT SUM(amount) FROM `{$table_accounting}` WHERE entry_type = %s AND entry_date BETWEEN %s AND %s",
            'Income',
            $current_month_start,
            $current_month_end
        )
    );

    $month_expense = (float) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT SUM(amount) FROM `{$table_accounting}` WHERE entry_type = %s AND entry_date BETWEEN %s AND %s",
            'Expense',
            $current_month_start,
            $current_month_end
        )
    );
    // phpcs:enable

    $month_net = $month_income - $month_expense;

    // Navigation URLs
    $base_tab_url = add_query_arg( array( 'page' => 'school_management_system', 'tab' => 'accounting', 'sub' => 'list' ), admin_url( 'admin.php' ) );
    $add_new_url  = add_query_arg( array( 'page' => 'school_management_system', 'tab' => 'accounting', 'sub' => 'add' ), admin_url( 'admin.php' ) );
    ?>

    <style>
        .ifs-educore-action-btn-sm.view {
            background: #f0fdf4;
            color: #047857;
            border: 1px solid #bbf7d0;
        }
        .ifs-educore-action-btn-sm.view:hover {
            background: #00523c;
            color: #ffffff;
            border-color: #00523c;
        }
    </style>

    <div class="ifs-educore-acct-container">

        <!-- Top Headline & Action -->
        <div class="ifs-educore-header-headline-bar">
            <h2 class="ifs-educore-page-title">
                <span class="dashicons dashicons-money-alt" style="font-size:26px; width:26px; height:26px; color:#00523c;"></span>
                <?php esc_html_e( 'General Accounting & Financial Ledger', 'ifsedu-school-management' ); ?>
            </h2>
            <div style="display:flex; gap:10px;">
                <button type="button" onclick="window.print();" class="ifs-educore-btn-action ifs-educore-btn-secondary">
                    <span class="dashicons dashicons-printer"></span> <?php esc_html_e( 'Print Report', 'ifsedu-school-management' ); ?>
                </button>
                <a href="<?php echo esc_url( $add_new_url ); ?>" class="ifs-educore-btn-action ifs-educore-btn-primary">
                    <span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e( 'Record Transaction', 'ifsedu-school-management' ); ?>
                </a>
            </div>
        </div>

        <!-- Feedback Alert Messages -->
        <?php 
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        if ( isset( $_GET['msg'] ) ) : 
            $msg = sanitize_text_field( wp_unslash( $_GET['msg'] ) );
        ?>
            <?php if ( 'success' === $msg ) : ?>
                <div class="ifs-educore-feedback-banner success">
                    <span class="dashicons dashicons-yes-alt" style="color:#00523c;"></span>
                    <span><?php esc_html_e( 'Financial transaction recorded successfully.', 'ifsedu-school-management' ); ?></span>
                </div>
            <?php elseif ( 'updated' === $msg ) : ?>
                <div class="ifs-educore-feedback-banner success" style="background:#eff6ff; border-color:#bfdbfe; color:#1e40af;">
                    <span class="dashicons dashicons-saved" style="color:#2563eb;"></span>
                    <span><?php esc_html_e( 'Ledger entry updated successfully.', 'ifsedu-school-management' ); ?></span>
                </div>
            <?php elseif ( 'deleted' === $msg ) : ?>
                <div class="ifs-educore-feedback-banner success" style="background:#fef2f2; border-color:#fecaca; color:#991b1b;">
                    <span class="dashicons dashicons-trash" style="color:#dc2626;"></span>
                    <span><?php esc_html_e( 'Transaction record deleted permanently.', 'ifsedu-school-management' ); ?></span>
                </div>
            <?php endif; ?>
        <?php 
        endif;
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        ?>

        <!-- Top Metrics Stats Grid -->
        <div class="ifs-educore-bento-grid-stats">
            <div class="ifs-educore-stat-card income-card">
                <div class="ifs-educore-stat-icon" style="background: #ecfdf5; color: #00523c;">
                    <svg viewBox="0 0 24 24"><path d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 3.93 2.5.42 3 1.34 3 2.22 0 1.02-.9 1.83-2.7 1.83-2.1 0-2.88-.95-2.98-2.25H6.88c.11 2.25 1.77 3.45 3.62 3.97V21h3v-2.11c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-5.2-4.44z"/></svg>
                </div>
                <div class="ifs-educore-stat-meta">
                    <span class="ifs-educore-stat-label"><?php esc_html_e( 'Total Revenue (+)', 'ifsedu-school-management' ); ?></span>
                    <span class="ifs-educore-stat-value" style="color: #059669;">৳<?php echo esc_html( number_format( $total_income, 2 ) ); ?></span>
                </div>
            </div>

            <div class="ifs-educore-stat-card expense-card">
                <div class="ifs-educore-stat-icon" style="background: #fef2f2; color: #ef4444;">
                    <svg viewBox="0 0 24 24"><path d="M19 13H5v-2h14v2z"/></svg>
                </div>
                <div class="ifs-educore-stat-meta">
                    <span class="ifs-educore-stat-label"><?php esc_html_e( 'Total Expenses (-)', 'ifsedu-school-management' ); ?></span>
                    <span class="ifs-educore-stat-value" style="color: #dc2626;">৳<?php echo esc_html( number_format( $total_expense, 2 ) ); ?></span>
                </div>
            </div>

            <div class="ifs-educore-stat-card net-card">
                <div class="ifs-educore-stat-icon" style="background: #eff6ff; color: #3b82f6;">
                    <svg viewBox="0 0 24 24"><path d="M21 18v1c0 1.1-.9 2-2 2H3c-1.11 0-2-.9-2-2V5c0-1.1.89-2 2-2h16c1.1 0 2 .9 2 2v1h-9c-1.11 0-2 .9-2 2v8c0 1.1.89 2 2 2h9zm-9-2h10V8H12v8zm4-2.5c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/></svg>
                </div>
                <div class="ifs-educore-stat-meta">
                    <span class="ifs-educore-stat-label"><?php esc_html_e( 'Net Cash Balance', 'ifsedu-school-management' ); ?></span>
                    <span class="ifs-educore-stat-value" style="color: <?php echo $net_balance >= 0 ? '#059669' : '#dc2626'; ?>;">
                        ৳<?php echo esc_html( number_format( $net_balance, 2 ) ); ?>
                    </span>
                </div>
            </div>

            <div class="ifs-educore-stat-card month-card">
                <div class="ifs-educore-stat-icon" style="background: #f0f9ff; color: #0284c7;">
                    <svg viewBox="0 0 24 24"><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11z"/></svg>
                </div>
                <div class="ifs-educore-stat-meta">
                    <span class="ifs-educore-stat-label"><?php esc_html_e( 'Current Month Net', 'ifsedu-school-management' ); ?></span>
                    <span class="ifs-educore-stat-value" style="color: #0284c7;">৳<?php echo esc_html( number_format( $month_net, 2 ) ); ?></span>
                </div>
            </div>
        </div>

        <!-- Filter Controls Bento Box -->
        <div class="ifs-educore-filter-bento-card">
            <form method="GET" action="" class="ifs-educore-filter-grid">
                <input type="hidden" name="page" value="school_management_system">
                <input type="hidden" name="tab" value="accounting">
                <input type="hidden" name="sub" value="list">

                <!-- Search Input -->
                <div class="ifs-educore-filter-field" style="grid-column: span 2;">
                    <label><?php esc_html_e( 'Search Keyword', 'ifsedu-school-management' ); ?></label>
                    <input type="text" name="s" placeholder="<?php esc_attr_e( 'Search Title, Voucher No, or Payer/Payee...', 'ifsedu-school-management' ); ?>" value="<?php echo esc_attr( $search_query ); ?>">
                </div>

                <!-- Category -->
                <div class="ifs-educore-filter-field">
                    <label><?php esc_html_e( 'Category', 'ifsedu-school-management' ); ?></label>
                    <select name="category">
                        <option value=""><?php esc_html_e( '-- All Categories --', 'ifsedu-school-management' ); ?></option>
                        <?php if ( ! empty( $available_categories ) && is_array( $available_categories ) ) : foreach ( $available_categories as $cat ) : ?>
                            <option value="<?php echo esc_attr( $cat ); ?>" <?php selected( $filter_category, $cat ); ?>><?php echo esc_html( $cat ); ?></option>
                        <?php endforeach; endif; ?>
                    </select>
                </div>

                <!-- Payment Method -->
                <div class="ifs-educore-filter-field">
                    <label><?php esc_html_e( 'Method', 'ifsedu-school-management' ); ?></label>
                    <select name="payment_method">
                        <option value=""><?php esc_html_e( '-- All Methods --', 'ifsedu-school-management' ); ?></option>
                        <option value="Cash" <?php selected( $filter_method, 'Cash' ); ?>><?php esc_html_e( 'Cash', 'ifsedu-school-management' ); ?></option>
                        <option value="Bank Transfer" <?php selected( $filter_method, 'Bank Transfer' ); ?>><?php esc_html_e( 'Bank Transfer', 'ifsedu-school-management' ); ?></option>
                        <option value="bKash" <?php selected( $filter_method, 'bKash' ); ?>><?php esc_html_e( 'bKash', 'ifsedu-school-management' ); ?></option>
                        <option value="Nagad" <?php selected( $filter_method, 'Nagad' ); ?>><?php esc_html_e( 'Nagad', 'ifsedu-school-management' ); ?></option>
                        <option value="Cheque" <?php selected( $filter_method, 'Cheque' ); ?>><?php esc_html_e( 'Cheque', 'ifsedu-school-management' ); ?></option>
                    </select>
                </div>

                <!-- From Date -->
                <div class="ifs-educore-filter-field">
                    <label><?php esc_html_e( 'From Date', 'ifsedu-school-management' ); ?></label>
                    <input type="date" name="from_date" value="<?php echo esc_attr( $from_date ); ?>">
                </div>

                <!-- To Date -->
                <div class="ifs-educore-filter-field">
                    <label><?php esc_html_e( 'To Date', 'ifsedu-school-management' ); ?></label>
                    <input type="date" name="to_date" value="<?php echo esc_attr( $to_date ); ?>">
                </div>

                <!-- Submit & Reset Action -->
                <div style="display:flex; gap:8px;">
                    <button type="submit" class="ifs-educore-btn-action ifs-educore-btn-primary" style="height:38px; width:100%;">
                        <span class="dashicons dashicons-filter"></span> <?php esc_html_e( 'Filter', 'ifsedu-school-management' ); ?>
                    </button>
                    <a href="<?php echo esc_url( $base_tab_url ); ?>" class="ifs-educore-btn-action ifs-educore-btn-secondary" style="height:38px;">
                        <?php esc_html_e( 'Reset', 'ifsedu-school-management' ); ?>
                    </a>
                </div>
            </form>
        </div>

        <!-- Master Financial Table Container -->
        <div class="ifs-educore-bento-table-card">

            <div class="ifs-educore-table-header-toolbar">
                <h4 class="ifs-educore-page-title" style="font-size:16px;">
                    <span class="dashicons dashicons-list-view" style="color: #00523c;"></span>
                    <?php esc_html_e( 'Financial Ledger Entries', 'ifsedu-school-management' ); ?>
                </h4>

                <div class="ifs-educore-filter-pills">
                    <a href="<?php echo esc_url( add_query_arg( 'entry_type', 'all', $base_tab_url ) ); ?>" class="ifs-educore-filter-pill-btn <?php echo $filter_type === 'all' ? 'active' : ''; ?>">
                        <?php esc_html_e( 'All Entries', 'ifsedu-school-management' ); ?>
                    </a>
                    <a href="<?php echo esc_url( add_query_arg( 'entry_type', 'Income', $base_tab_url ) ); ?>" class="ifs-educore-filter-pill-btn <?php echo $filter_type === 'Income' ? 'active' : ''; ?>">
                        <?php esc_html_e( 'Incomes', 'ifsedu-school-management' ); ?>
                    </a>
                    <a href="<?php echo esc_url( add_query_arg( 'entry_type', 'Expense', $base_tab_url ) ); ?>" class="ifs-educore-filter-pill-btn <?php echo $filter_type === 'Expense' ? 'active' : ''; ?>">
                        <?php esc_html_e( 'Expenses', 'ifsedu-school-management' ); ?>
                    </a>
                </div>
            </div>

            <!-- Table Matrix -->
            <div class="ifs-educore-table-responsive">
                <table class="ifs-educore-matrix-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Date & Voucher', 'ifsedu-school-management' ); ?></th>
                            <th><?php esc_html_e( 'Flow', 'ifsedu-school-management' ); ?></th>
                            <th><?php esc_html_e( 'Particulars / Title', 'ifsedu-school-management' ); ?></th>
                            <th><?php esc_html_e( 'Payer / Payee', 'ifsedu-school-management' ); ?></th>
                            <th><?php esc_html_e( 'Recorded By', 'ifsedu-school-management' ); ?></th>
                            <th><?php esc_html_e( 'Method', 'ifsedu-school-management' ); ?></th>
                            <th><?php esc_html_e( 'Amount', 'ifsedu-school-management' ); ?></th>
                            <th style="text-align: right;"><?php esc_html_e( 'Actions', 'ifsedu-school-management' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( ! empty( $ledger_records ) ) : foreach ( $ledger_records as $item ) : 
                            $item_id = absint( $item->id );
                            
                            $view_url = add_query_arg(
                                array(
                                    'page' => 'school_management_system',
                                    'tab'  => 'accounting',
                                    'sub'  => 'view',
                                    'id'   => $item_id,
                                ),
                                admin_url( 'admin.php' )
                            );

                            $edit_url  = add_query_arg(
                                array(
                                    'page'     => 'school_management_system',
                                    'tab'      => 'accounting',
                                    'sub'      => 'add',
                                    'sub_mode' => 'edit',
                                    'id'       => $item_id,
                                ),
                                admin_url( 'admin.php' )
                            );
                            $delete_url = wp_nonce_url(
                                add_query_arg(
                                    array(
                                        'page' => 'school_management_system',
                                        'tab'  => 'accounting',
                                        'sub'  => 'delete',
                                        'id'   => $item_id,
                                    ),
                                    admin_url( 'admin.php' )
                                ),
                                'delete_acct_' . $item_id
                            );
                            $is_income = ( 'Income' === $item->entry_type );
                            
                            $date_timestamp = ! empty( $item->entry_date ) ? strtotime( $item->entry_date ) : false;
                            $date_formatted = $date_timestamp ? date_i18n( 'd M Y', $date_timestamp ) : '—';
                            $recorder_name  = ! empty( $item->staff_full_name ) ? $item->staff_full_name : ( ! empty( $item->wp_user_name ) ? $item->wp_user_name : __( 'System / Admin', 'ifsedu-school-management' ) );
                        ?>
                            <tr>
                                <td>
                                    <strong style="color:#0f172a; font-weight:700;"><?php echo esc_html( $date_formatted ); ?></strong><br>
                                    <span class="ifs-educore-ref-code"><?php echo esc_html( $item->voucher_no ); ?></span>
                                </td>
                                <td>
                                    <?php if ( $is_income ) : ?>
                                        <span class="ifs-educore-badge-type-income">
                                            <span class="dashicons dashicons-arrow-up-alt2" style="font-size:12px; width:12px; height:12px;"></span> <?php esc_html_e( 'Income', 'ifsedu-school-management' ); ?>
                                        </span>
                                    <?php else : ?>
                                        <span class="ifs-educore-badge-type-expense">
                                            <span class="dashicons dashicons-arrow-down-alt2" style="font-size:12px; width:12px; height:12px;"></span> <?php esc_html_e( 'Expense', 'ifsedu-school-management' ); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong style="color:#0f172a; font-size:13.5px;"><?php echo esc_html( $item->title ); ?></strong>
                                    <div style="margin-top:2px; font-size:12px; color:#64748b; font-weight:600;">
                                        <?php echo esc_html( $item->category_name ); ?>
                                    </div>
                                    <?php if ( ! empty( $item->note ) ) : ?>
                                        <p style="margin:3px 0 0 0; color:#94a3b8; font-size:11.5px;"><?php echo esc_html( $item->note ); ?></p>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ( ! empty( $item->party_name ) ) : ?>
                                        <span style="font-weight:700; color:#334155;"><?php echo esc_html( $item->party_name ); ?></span>
                                    <?php else : ?>
                                        <span style="color:#94a3b8;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span style="font-size: 12px; font-weight: 700; color: #475569;"><?php echo esc_html( $recorder_name ); ?></span>
                                </td>
                                <td>
                                    <span class="ifs-educore-payment-chip"><?php echo esc_html( $item->payment_method ); ?></span>
                                </td>
                                <td style="font-weight:800; font-size:15px; color: <?php echo $is_income ? '#059669' : '#dc2626'; ?>;">
                                    <?php echo $is_income ? '+' : '-'; ?>৳<?php echo esc_html( number_format( (float) $item->amount, 2 ) ); ?>
                                </td>
                                <td style="text-align: right;">
                                    <div class="ifs-educore-row-actions">
                                        <!-- View Details Page Button -->
                                        <a href="<?php echo esc_url( $view_url ); ?>" class="ifs-educore-action-btn-sm view" title="<?php esc_attr_e( 'View Voucher Details', 'ifsedu-school-management' ); ?>">
                                            <span class="dashicons dashicons-visibility" style="font-size:15px; width:15px; height:15px;"></span>
                                        </a>

                                        <?php if ( ! empty( $item->attachment_url ) ) : ?>
                                            <a href="<?php echo esc_url( $item->attachment_url ); ?>" target="_blank" class="ifs-educore-action-btn-sm attachment" title="<?php esc_attr_e( 'View Attached Bill / Slip', 'ifsedu-school-management' ); ?>">
                                                <span class="dashicons dashicons-media-document"></span>
                                            </a>
                                        <?php endif; ?>

                                        <a href="<?php echo esc_url( $edit_url ); ?>" class="ifs-educore-action-btn-sm edit" title="<?php esc_attr_e( 'Edit Entry', 'ifsedu-school-management' ); ?>">
                                            <span class="dashicons dashicons-edit"></span>
                                        </a>

                                        <a href="<?php echo esc_url( $delete_url ); ?>" class="ifs-educore-action-btn-sm delete" title="<?php esc_attr_e( 'Delete Ledger Record', 'ifsedu-school-management' ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to permanently delete this transaction record?', 'ifsedu-school-management' ) ); ?>');">
                                            <span class="dashicons dashicons-trash"></span>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; else : ?>
                            <tr>
                                <td colspan="8">
                                    <div style="padding:40px 20px; text-align:center;">
                                        <span class="dashicons dashicons-money-alt" style="font-size:36px; width:36px; height:36px; color:#cbd5e1; margin-bottom:8px;"></span>
                                        <h4 style="margin:0; color:#0f172a; font-weight:700;"><?php esc_html_e( 'No Financial Records Found', 'ifsedu-school-management' ); ?></h4>
                                        <p style="margin:4px 0 0 0; color:#64748b; font-size:13px;"><?php esc_html_e( 'No income or expense transactions matched your current search parameters.', 'ifsedu-school-management' ); ?></p>
                                    </div>
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
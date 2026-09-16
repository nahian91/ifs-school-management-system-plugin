<?php
/**
 * Single Fee Invoice & Payment Ledger Detail View Engine
 * File: inc/fees/fees-view.php
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Render Single Fee Invoice Detail View
 */
function educore_fees_single_view() {
    global $wpdb;

    // Capability Check.
    if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'edit_posts' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to access this financial record.', 'ifsedu-school-management' ) );
    }

    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $invoice_id = isset( $_GET['invoice'] ) ? sanitize_text_field( wp_unslash( $_GET['invoice'] ) ) : '';
    $record_id  = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0;
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    if ( empty( $invoice_id ) && $record_id <= 0 ) {
        echo '<div class="notice notice-error"><p>' . esc_html__( 'Invalid invoice specification provided.', 'ifsedu-school-management' ) . '</p></div>';
        return;
    }

    $table_fees     = $wpdb->prefix . 'sms_fees';
    $table_students = $wpdb->prefix . 'sms_students';
    $table_staff    = $wpdb->prefix . 'sms_staff';

    // Fetch Invoice Record with Student and Collector Joins.
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    if ( ! empty( $invoice_id ) ) {
        $invoice = $wpdb->get_row( $wpdb->prepare(
            "SELECT f.*, s.full_name, s.student_id as s_id, s.class_name, s.section_name, s.shift, s.student_phone, s.guardian_name, s.waiver_percentage, 
                    st.full_name as ref_staff_name, u.display_name as collector_name, col_staff.full_name as col_staff_name
             FROM `{$table_fees}` f 
             LEFT JOIN `{$table_students}` s ON f.student_id = s.id
             LEFT JOIN `{$table_staff}` st ON s.waiver_staff_id = st.id
             LEFT JOIN `{$wpdb->users}` u ON f.collected_by = u.ID
             LEFT JOIN `{$table_staff}` col_staff ON f.collected_by = col_staff.id
             WHERE f.invoice_id = %s LIMIT 1",
            $invoice_id
        ) );
    } else {
        $invoice = $wpdb->get_row( $wpdb->prepare(
            "SELECT f.*, s.full_name, s.student_id as s_id, s.class_name, s.section_name, s.shift, s.student_phone, s.guardian_name, s.waiver_percentage, 
                    st.full_name as ref_staff_name, u.display_name as collector_name, col_staff.full_name as col_staff_name
             FROM `{$table_fees}` f 
             LEFT JOIN `{$table_students}` s ON f.student_id = s.id
             LEFT JOIN `{$table_staff}` st ON s.waiver_staff_id = st.id
             LEFT JOIN `{$wpdb->users}` u ON f.collected_by = u.ID
             LEFT JOIN `{$table_staff}` col_staff ON f.collected_by = col_staff.id
             WHERE f.id = %d LIMIT 1",
            $record_id
        ) );
    }
    // phpcs:enable

    if ( ! $invoice ) {
        echo '<div class="notice notice-error"><p>' . esc_html__( 'Requested fee invoice record could not be located in the database.', 'ifsedu-school-management' ) . '</p></div>';
        return;
    }

    $back_url  = add_query_arg( array( 'page' => 'school_management_system', 'tab' => 'fees' ), admin_url( 'admin.php' ) );
    $print_url = add_query_arg( array( 'page' => 'school_management_system', 'tab' => 'fees', 'sub' => 'print', 'invoice' => $invoice->invoice_id ), admin_url( 'admin.php' ) );

    $status_class = 'unpaid';
    if ( 'Paid' === $invoice->payment_status ) {
        $status_class = 'paid';
    } elseif ( 'Partial' === $invoice->payment_status ) {
        $status_class = 'partial';
    }

    $collector_display = ! empty( $invoice->col_staff_name ) ? $invoice->col_staff_name : ( ! empty( $invoice->collector_name ) ? $invoice->collector_name : ( ! empty( $invoice->recorded_by ) ? '#' . $invoice->recorded_by : __( 'Admin / System', 'ifsedu-school-management' ) ) );
    $entry_date_str    = ! empty( $invoice->created_at ) ? date_i18n( 'M j, Y h:i A', strtotime( $invoice->created_at ) ) : ( ! empty( $invoice->payment_date ) ? date_i18n( 'M j, Y', strtotime( $invoice->payment_date ) ) : '—' );
    ?>

    <style>
        .ifs-educore-view-root {
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
            color: #0f172a !important;
            max-width: 950px !important;
        }
        .ifs-educore-view-header {
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            margin-bottom: 24px !important;
            flex-wrap: wrap !important;
            gap: 15px !important;
        }
        .ifs-educore-back-link {
            text-decoration: none !important;
            color: #475569 !important;
            font-weight: 700 !important;
            font-size: 13.5px !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 6px !important;
            transition: color 0.2s ease !important;
            background: #ffffff !important;
            border: 1px solid #e2e8f0 !important;
            padding: 8px 16px !important;
            border-radius: 10px !important;
            box-shadow: 0 2px 5px rgba(0,0,0,0.02) !important;
        }
        .ifs-educore-back-link:hover { color: #00523c !important; border-color: #cbd5e1 !important; }

        .ifs-educore-view-bento {
            background: #ffffff !important;
            border: 1px solid #e2e8f0 !important;
            border-radius: 16px !important;
            padding: 36px !important;
            box-shadow: 0 4px 20px -4px rgba(0, 0, 0, 0.04) !important;
            box-sizing: border-box !important;
        }
        .ifs-educore-view-grid {
            display: grid !important;
            grid-template-columns: repeat(2, 1fr) !important;
            gap: 24px !important;
            margin-top: 24px !important;
        }
        .ifs-educore-view-section {
            background: #f8fafc !important;
            border: 1px solid #e2e8f0 !important;
            border-radius: 14px !important;
            padding: 24px !important;
            box-sizing: border-box !important;
        }
        .ifs-educore-view-section h4 {
            margin: 0 0 16px 0 !important;
            font-size: 13.5px !important;
            font-weight: 800 !important;
            color: #00523c !important;
            text-transform: uppercase !important;
            letter-spacing: 0.05em !important;
            border-bottom: 2px solid #e2e8f0 !important;
            padding-bottom: 10px !important;
            display: flex !important;
            align-items: center !important;
            gap: 8px !important;
        }
        .ifs-educore-kv-row {
            display: flex !important;
            justify-content: space-between !important;
            padding: 9px 0 !important;
            border-bottom: 1px dashed #e2e8f0 !important;
            font-size: 13.5px !important;
        }
        .ifs-educore-kv-row:last-child {
            border-bottom: none !important;
        }
        .ifs-educore-kv-label {
            color: #64748b !important;
            font-weight: 600 !important;
        }
        .ifs-educore-kv-val {
            color: #0f172a !important;
            font-weight: 700 !important;
            text-align: right !important;
        }
        .ifs-educore-status-badge {
            display: inline-flex !important;
            align-items: center !important;
            padding: 5px 12px !important;
            border-radius: 20px !important;
            font-size: 11.5px !important;
            font-weight: 800 !important;
            text-transform: uppercase !important;
        }
        .ifs-educore-status-badge.paid {
            background: #ecfdf5 !important;
            color: #047857 !important;
            border: 1px solid #a7f3d0 !important;
        }
        .ifs-educore-status-badge.partial {
            background: #fffbeb !important;
            color: #b45309 !important;
            border: 1px solid #fde68a !important;
        }
        .ifs-educore-status-badge.unpaid {
            background: #fef2f2 !important;
            color: #dc2626 !important;
            border: 1px solid #fca5a5 !important;
        }
        .ifs-educore-btn-print-action {
            background: #00523c !important;
            color: #ffffff !important;
            padding: 10px 22px !important;
            border-radius: 10px !important;
            font-weight: 700 !important;
            font-size: 13.5px !important;
            text-decoration: none !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 8px !important;
            box-shadow: 0 4px 14px rgba(0, 82, 60, 0.2) !important;
            transition: all 0.2s ease !important;
        }
        .ifs-educore-btn-print-action:hover {
            background: #047857 !important;
            color: #ffffff !important;
            transform: translateY(-1px);
        }
        @media screen and (max-width: 768px) {
            .ifs-educore-view-grid {
                grid-template-columns: 1fr !important;
            }
        }
    </style>

    <div class="ifs-educore-view-root">
        <!-- Header & Navigation -->
        <div class="ifs-educore-view-header">
            <a href="<?php echo esc_url( $back_url ); ?>" class="ifs-educore-back-link">
                <span class="dashicons dashicons-arrow-left-alt" style="font-size:16px; width:16px; height:16px;"></span>
                <?php esc_html_e( 'Back to Financial Ledger', 'ifsedu-school-management' ); ?>
            </a>
            
            <a href="<?php echo esc_url( $print_url ); ?>" class="ifs-educore-btn-print-action" target="_blank">
                <span class="dashicons dashicons-printer" style="font-size:16px; width:16px; height:16px;"></span>
                <?php esc_html_e( 'Print Official Receipt', 'ifsedu-school-management' ); ?>
            </a>
        </div>

        <!-- Main Bento Summary Card -->
        <div class="ifs-educore-view-bento">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #f1f5f9; padding-bottom: 24px;">
                <div>
                    <span style="font-family: monospace; font-size: 13.5px; font-weight: 800; background: #f1f5f9; padding: 5px 10px; border-radius: 6px; border: 1px solid #cbd5e1; color: #0f172a;">
                        #<?php echo esc_html( $invoice->invoice_id ); ?>
                    </span>
                    <h2 style="margin: 14px 0 6px 0; font-size: 24px; font-weight: 800; color: #0f172a; letter-spacing: -0.02em;">
                        <?php echo esc_html( $invoice->full_name ? $invoice->full_name : __( 'Unknown Student', 'ifsedu-school-management' ) ); ?>
                    </h2>
                    <p style="margin: 0; color: #64748b; font-size: 13.5px; font-weight: 600;">
                        <?php 
                        $st_id = $invoice->s_id ? strtoupper( (string) $invoice->s_id ) : 'N/A';
                        $cls   = $invoice->class_name ? $invoice->class_name : 'Unassigned';
                        $sec   = ! empty( $invoice->section_name ) ? $invoice->section_name : 'N/A';
                        $shft  = ( ! empty( $invoice->shift ) && 'No Shift' !== $invoice->shift ) ? ' | ' . $invoice->shift : '';
                        printf( esc_html__( 'Student ID: %s | Class: %s (%s)%s', 'ifsedu-school-management' ), esc_html( $st_id ), esc_html( $cls ), esc_html( $sec ), esc_html( $shft ) ); 
                        ?>
                    </p>
                </div>
                <div>
                    <span class="ifs-educore-status-badge <?php echo esc_attr( $status_class ); ?>">
                        <?php echo esc_html( $invoice->payment_status ); ?>
                    </span>
                </div>
            </div>

            <!-- Two-Column Information Sections -->
            <div class="ifs-educore-view-grid">
                <!-- Billing Details Section -->
                <div class="ifs-educore-view-section">
                    <h4>
                        <span class="dashicons dashicons-media-text" style="color:#00523c;"></span>
                        <?php esc_html_e( 'Invoice Particulars', 'ifsedu-school-management' ); ?>
                    </h4>
                    <div class="ifs-educore-kv-row">
                        <span class="ifs-educore-kv-label"><?php esc_html_e( 'Fee Category:', 'ifsedu-school-management' ); ?></span>
                        <span class="ifs-educore-kv-val" style="color: #00523c;"><?php echo esc_html( $invoice->fee_type ); ?></span>
                    </div>
                    <div class="ifs-educore-kv-row">
                        <span class="ifs-educore-kv-label"><?php esc_html_e( 'Billing Month & Year:', 'ifsedu-school-management' ); ?></span>
                        <span class="ifs-educore-kv-val"><?php echo esc_html( ucfirst( $invoice->fee_month ) . ' ' . $invoice->fee_year ); ?></span>
                    </div>
                    <div class="ifs-educore-kv-row">
                        <span class="ifs-educore-kv-label"><?php esc_html_e( 'Base Amount:', 'ifsedu-school-management' ); ?></span>
                        <span class="ifs-educore-kv-val">৳<?php echo esc_html( number_format( (float) $invoice->amount, 2 ) ); ?></span>
                    </div>
                    <div class="ifs-educore-kv-row">
                        <span class="ifs-educore-kv-label"><?php esc_html_e( 'Late Fine:', 'ifsedu-school-management' ); ?></span>
                        <span class="ifs-educore-kv-val" style="color: #dc2626;">৳<?php echo esc_html( number_format( (float) $invoice->late_fine, 2 ) ); ?></span>
                    </div>
                    <div class="ifs-educore-kv-row">
                        <span class="ifs-educore-kv-label"><?php esc_html_e( 'Discount / Waiver:', 'ifsedu-school-management' ); ?></span>
                        <span class="ifs-educore-kv-val" style="color: #16a34a;">৳<?php echo esc_html( number_format( (float) $invoice->discount, 2 ) ); ?></span>
                    </div>
                    <div class="ifs-educore-kv-row" style="background: #ffffff; padding: 10px 12px; border-radius: 8px; margin-top: 10px; border: 1px solid #cbd5e1;">
                        <span class="ifs-educore-kv-label" style="font-weight: 800; color: #0f172a;"><?php esc_html_e( 'Net Payable:', 'ifsedu-school-management' ); ?></span>
                        <span class="ifs-educore-kv-val" style="font-size: 15px; font-weight: 800; color: #1d4ed8;">৳<?php echo esc_html( number_format( (float) $invoice->net_payable, 2 ) ); ?></span>
                    </div>
                </div>

                <!-- Payment & Transaction Audit Section -->
                <div class="ifs-educore-view-section">
                    <h4>
                        <span class="dashicons dashicons-shield" style="color:#00523c;"></span>
                        <?php esc_html_e( 'Transaction Audit Trail', 'ifsedu-school-management' ); ?>
                    </h4>
                    <div class="ifs-educore-kv-row">
                        <span class="ifs-educore-kv-label"><?php esc_html_e( 'Paid Amount:', 'ifsedu-school-management' ); ?></span>
                        <span class="ifs-educore-kv-val" style="color: #047857; font-size: 14px;">৳<?php echo esc_html( number_format( (float) $invoice->paid_amount, 2 ) ); ?></span>
                    </div>
                    <div class="ifs-educore-kv-row">
                        <span class="ifs-educore-kv-label"><?php esc_html_e( 'Due Balance:', 'ifsedu-school-management' ); ?></span>
                        <span class="ifs-educore-kv-val" style="color: #b91c1c; font-size: 14px;">৳<?php echo esc_html( number_format( (float) $invoice->due_amount, 2 ) ); ?></span>
                    </div>
                    <div class="ifs-educore-kv-row">
                        <span class="ifs-educore-kv-label"><?php esc_html_e( 'Recorded By / Collector:', 'ifsedu-school-management' ); ?></span>
                        <span class="ifs-educore-kv-val"><?php echo esc_html( $collector_display ); ?></span>
                    </div>
                    <div class="ifs-educore-kv-row">
                        <span class="ifs-educore-kv-label"><?php esc_html_e( 'Transaction Timestamp:', 'ifsedu-school-management' ); ?></span>
                        <span class="ifs-educore-kv-val"><?php echo esc_html( $entry_date_str ); ?></span>
                    </div>
                    <?php if ( ! empty( $invoice->waiver_percentage ) && floatval( $invoice->waiver_percentage ) > 0 ) : ?>
                        <div class="ifs-educore-kv-row">
                            <span class="ifs-educore-kv-label"><?php esc_html_e( 'Assigned Waiver:', 'ifsedu-school-management' ); ?></span>
                            <span class="ifs-educore-kv-val" style="color: #15803d;"><?php echo esc_html( floatval( $invoice->waiver_percentage ) ); ?>% <?php echo ! empty( $invoice->ref_staff_name ) ? esc_html( '[' . $invoice->ref_staff_name . ']' ) : ''; ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php
}
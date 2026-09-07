<?php
/**
 * Standalone Single Financial Voucher Ledger Details View
 * File: inc/accounting/accounting-view.php
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function educore_accounting_single_view() {
    global $wpdb;

    if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'edit_posts' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to view accounting vouchers.', 'ifsedu-school-management' ) );
    }

    $table_accounting = $wpdb->prefix . 'sms_accounting';
    $table_staff      = $wpdb->prefix . 'sms_staff';

    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $entry_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    $list_url = add_query_arg(
        array(
            'page' => 'school_management_system',
            'tab'  => 'accounting',
            'sub'  => 'list',
        ),
        admin_url( 'admin.php' )
    );

    $edit_url = add_query_arg(
        array(
            'page'     => 'school_management_system',
            'tab'      => 'accounting',
            'sub'      => 'add',
            'sub_mode' => 'edit',
            'id'       => $entry_id,
        ),
        admin_url( 'admin.php' )
    );

    // Fetch Ledger Record with Staff Mapping
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $entry = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT ac.*, st.full_name as staff_full_name, u.display_name as wp_user_name 
             FROM `{$table_accounting}` ac 
             LEFT JOIN `{$table_staff}` st ON ac.created_by = st.wp_user_id 
             LEFT JOIN `{$wpdb->users}` u ON ac.created_by = u.ID 
             WHERE ac.id = %d LIMIT 1",
            $entry_id
        )
    );
    // phpcs:enable

    if ( ! $entry ) {
        ?>
        <div class="ifs-educore-acct-container">
            <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:14px; padding:40px 20px; text-align:center; max-width:600px; margin:40px auto; box-shadow:0 4px 15px rgba(0,0,0,0.03);">
                <span class="dashicons dashicons-warning" style="font-size:42px; width:42px; height:42px; color:#dc2626;"></span>
                <h3 style="font-size:18px; font-weight:800; color:#0f172a; margin:14px 0 6px 0;"><?php esc_html_e( 'Voucher Record Not Found', 'ifsedu-school-management' ); ?></h3>
                <p style="font-weight:600; color:#64748b; font-size:13.5px; margin-bottom:20px;"><?php esc_html_e( 'The requested transaction entry does not exist or has been removed from the database.', 'ifsedu-school-management' ); ?></p>
                <a href="<?php echo esc_url( $list_url ); ?>" style="display:inline-flex; align-items:center; gap:6px; background:#00523c; color:#fff; text-decoration:none; padding:9px 20px; border-radius:8px; font-weight:700; font-size:13px;">
                    <span class="dashicons dashicons-arrow-left-alt"></span> <?php esc_html_e( 'Back to Ledger List', 'ifsedu-school-management' ); ?>
                </a>
            </div>
        </div>
        <?php
        return;
    }

    $is_income      = ( 'Income' === $entry->entry_type );
    $date_timestamp = ! empty( $entry->entry_date ) ? strtotime( $entry->entry_date ) : false;
    $date_formatted = $date_timestamp ? date_i18n( 'd M, Y', $date_timestamp ) : '—';
    $recorder_name  = ! empty( $entry->staff_full_name ) ? $entry->staff_full_name : ( ! empty( $entry->wp_user_name ) ? $entry->wp_user_name : __( 'System / Admin', 'ifsedu-school-management' ) );

    $school_name    = get_option( 'educore_school_name', get_bloginfo( 'name' ) );
    $school_tagline = get_option( 'educore_school_tagline', get_bloginfo( 'description' ) );
    $school_logo    = get_option( 'educore_school_logo', '' );
    ?>

    <style>
        .ifs-acct-view-wrapper {
            max-width: 1000px;
            margin: 15px 0 40px 0;
            font-family: inherit;
        }

        /* Top Action Bar */
        .ifs-acct-view-top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            padding: 14px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.02);
        }
        .ifs-acct-btn-back {
            text-decoration: none;
            color: #475569;
            font-weight: 700;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: color 0.15s ease;
        }
        .ifs-acct-btn-back:hover {
            color: #00523c;
        }
        .ifs-acct-btn-edit {
            background: #00523c;
            color: #ffffff !important;
            text-decoration: none;
            padding: 8px 18px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 2px 8px rgba(0, 82, 60, 0.2);
            transition: all 0.2s ease;
        }
        .ifs-acct-btn-edit:hover {
            background: #047857;
            transform: translateY(-1px);
        }
        .ifs-acct-btn-print {
            background: #f8fafc;
            color: #334155 !important;
            border: 1px solid #cbd5e1;
            text-decoration: none;
            padding: 7px 16px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .ifs-acct-btn-print:hover {
            background: #e2e8f0;
            color: #0f172a !important;
        }

        /* Treasury Voucher Document Sheet */
        .ifs-acct-voucher-sheet {
            background: #ffffff;
            border: 1.5px solid #cbd5e1;
            border-radius: 14px;
            padding: 36px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.04);
            box-sizing: border-box;
            position: relative;
        }
        .ifs-acct-voucher-header {
            text-align: center;
            border-bottom: 2.5px solid #0f172a;
            padding-bottom: 16px;
            margin-bottom: 24px;
        }
        .ifs-acct-brand-row {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }
        .ifs-acct-logo-img {
            max-height: 48px;
            object-fit: contain;
        }
        .ifs-acct-school-title {
            margin: 0;
            font-size: 24px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: -0.3px;
            color: #0f172a;
        }
        .ifs-acct-meta-strip {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 24px;
        }
        .ifs-acct-meta-item label {
            font-size: 11px;
            font-weight: 800;
            color: #64748b;
            text-transform: uppercase;
            display: block;
            margin-bottom: 2px;
        }
        .ifs-acct-meta-item span {
            font-size: 14px;
            font-weight: 800;
            color: #0f172a;
        }

        /* Financial Bento Grid */
        .ifs-acct-bento-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .ifs-acct-bento-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 16px 18px;
        }
        .ifs-acct-bento-card.highlight {
            background: #f0fdf4;
            border-color: #bbf7d0;
        }
        .ifs-acct-bento-card.highlight-exp {
            background: #fef2f2;
            border-color: #fecaca;
        }

        /* Signature Row */
        .ifs-acct-sign-row {
            display: flex;
            justify-content: space-between;
            margin-top: 60px;
            padding: 0 10px;
        }
        .ifs-acct-sign-col {
            text-align: center;
            width: 200px;
        }
        .ifs-acct-sign-line {
            border-top: 1.5px dashed #64748b;
            padding-top: 6px;
            font-size: 12px;
            font-weight: 700;
            color: #334155;
        }

        @media print {
            #adminmenuwrap, #adminmenuback, #wpadminbar, #wpfooter, .no-print {
                display: none !important;
            }
            body, .ifs-acct-view-wrapper {
                background: transparent !important;
                padding: 0 !important;
                margin: 0 !important;
                width: 100% !important;
            }
            .ifs-acct-voucher-sheet {
                border: none !important;
                box-shadow: none !important;
                padding: 0 !important;
            }
        }
    </style>

    <div class="ifs-acct-view-wrapper">

        <!-- Top Actions Bar -->
        <div class="ifs-acct-view-top-bar no-print">
            <a href="<?php echo esc_url( $list_url ); ?>" class="ifs-acct-btn-back">
                <span class="dashicons dashicons-arrow-left-alt"></span>
                <?php esc_html_e( 'Back to Ledger List', 'ifsedu-school-management' ); ?>
            </a>

            <div style="display: flex; align-items: center; gap: 10px;">
                <button type="button" onclick="window.print();" class="ifs-acct-btn-print">
                    <span class="dashicons dashicons-printer" style="font-size:15px; width:15px; height:15px;"></span>
                    <?php esc_html_e( 'Print Official Voucher', 'ifsedu-school-management' ); ?>
                </button>
                <a href="<?php echo esc_url( $edit_url ); ?>" class="ifs-acct-btn-edit">
                    <span class="dashicons dashicons-edit" style="font-size:15px; width:15px; height:15px;"></span>
                    <?php esc_html_e( 'Edit Transaction', 'ifsedu-school-management' ); ?>
                </a>
            </div>
        </div>

        <!-- Official Treasury Voucher Sheet -->
        <div class="ifs-acct-voucher-sheet">
            
            <!-- Document Header -->
            <div class="ifs-acct-voucher-header">
                <div class="ifs-acct-brand-row">
                    <?php if ( ! empty( $school_logo ) ) : ?>
                        <img src="<?php echo esc_url( $school_logo ); ?>" alt="<?php esc_attr_e( 'Logo', 'ifsedu-school-management' ); ?>" class="ifs-acct-logo-img">
                    <?php endif; ?>
                    <h2 class="ifs-acct-school-title"><?php echo esc_html( $school_name ); ?></h2>
                </div>
                <?php if ( ! empty( $school_tagline ) ) : ?>
                    <div style="font-size: 11.5px; color: #475569; font-weight: 700; margin-top: 2px; text-transform: uppercase; letter-spacing: 0.5px;">
                        <?php echo esc_html( $school_tagline ); ?>
                    </div>
                <?php endif; ?>
                <div style="font-size: 14px; font-weight: 800; color: #00523c; margin-top: 6px; text-transform: uppercase; letter-spacing: 1px;">
                    <?php esc_html_e( 'Official General Ledger Voucher Slip', 'ifsedu-school-management' ); ?>
                </div>
            </div>

            <!-- Voucher Serial & Date Strip -->
            <div class="ifs-acct-meta-strip">
                <div class="ifs-acct-meta-item">
                    <label><?php esc_html_e( 'Voucher Serial No.', 'ifsedu-school-management' ); ?></label>
                    <span><code><?php echo esc_html( $entry->voucher_no ); ?></code></span>
                </div>
                <div class="ifs-acct-meta-item">
                    <label><?php esc_html_e( 'Transaction Date', 'ifsedu-school-management' ); ?></label>
                    <span><?php echo esc_html( $date_formatted ); ?></span>
                </div>
                <div class="ifs-acct-meta-item">
                    <label><?php esc_html_e( 'Fiscal Year', 'ifsedu-school-management' ); ?></label>
                    <span>FY <?php echo esc_html( $entry->fiscal_year ); ?></span>
                </div>
                <div class="ifs-acct-meta-item">
                    <label><?php esc_html_e( 'Flow Classification', 'ifsedu-school-management' ); ?></label>
                    <span style="color:<?php echo $is_income ? '#047857' : '#dc2626'; ?>; text-transform:uppercase;">
                        <?php echo esc_html( $entry->entry_type ); ?>
                    </span>
                </div>
            </div>

            <!-- Particulars & Purpose -->
            <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:10px; padding:18px 20px; margin-bottom:20px;">
                <span style="font-size:11px; font-weight:800; color:#64748b; text-transform:uppercase; display:block; margin-bottom:4px;"><?php esc_html_e( 'Particulars / Transaction Purpose', 'ifsedu-school-management' ); ?></span>
                <h3 style="margin:0; font-size:17px; font-weight:900; color:#0f172a;"><?php echo esc_html( $entry->title ); ?></h3>
                <div style="margin-top:6px; font-size:13px; font-weight:700; color:#00523c;">
                    <?php esc_html_e( 'Category:', 'ifsedu-school-management' ); ?> <?php echo esc_html( $entry->category_name ); ?>
                </div>
            </div>

            <!-- Financial Metrics Bento Matrix -->
            <div class="ifs-acct-bento-grid">
                <div class="ifs-acct-bento-card <?php echo $is_income ? 'highlight' : 'highlight-exp'; ?>" style="grid-column: span 2;">
                    <span style="font-size:11.5px; font-weight:800; color:<?php echo $is_income ? '#047857' : '#b91c1c'; ?>; text-transform:uppercase; display:block; margin-bottom:4px;">
                        <?php echo $is_income ? esc_html__( 'Total Net Revenue Received', 'ifsedu-school-management' ) : esc_html__( 'Total Net Expense Disbursed', 'ifsedu-school-management' ); ?>
                    </span>
                    <strong style="font-size:26px; font-weight:900; color:<?php echo $is_income ? '#059669' : '#dc2626'; ?>;">
                        <?php echo $is_income ? '+' : '-'; ?>৳<?php echo esc_html( number_format( (float) $entry->amount, 2 ) ); ?>
                    </strong>
                    <?php if ( floatval( $entry->tax_vat_deducted ) > 0 ) : ?>
                        <div style="font-size:12px; font-weight:700; color:#64748b; margin-top:4px;">
                            <?php esc_html_e( 'Tax / VAT Deducted:', 'ifsedu-school-management' ); ?> ৳<?php echo esc_html( number_format( (float) $entry->tax_vat_deducted, 2 ) ); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="ifs-acct-bento-card">
                    <span style="font-size:11px; font-weight:800; color:#64748b; text-transform:uppercase; display:block; margin-bottom:4px;"><?php esc_html_e( 'Payment Method', 'ifsedu-school-management' ); ?></span>
                    <strong style="font-size:14px; font-weight:800; color:#0f172a; display:block;"><?php echo esc_html( $entry->payment_method ); ?></strong>
                    <span style="font-size:12px; color:#475569; font-weight:600;"><?php echo esc_html( $entry->bank_account ); ?></span>
                </div>

                <div class="ifs-acct-bento-card">
                    <span style="font-size:11px; font-weight:800; color:#64748b; text-transform:uppercase; display:block; margin-bottom:4px;"><?php esc_html_e( 'Payer / Payee Entity', 'ifsedu-school-management' ); ?></span>
                    <strong style="font-size:14px; font-weight:800; color:#0f172a; display:block;"><?php echo esc_html( ! empty( $entry->party_name ) ? $entry->party_name : '—' ); ?></strong>
                    <span style="font-size:12px; color:#475569; font-weight:600;"><?php echo esc_html( $entry->department ); ?></span>
                </div>
            </div>

            <!-- Cost Accounting Dimensions -->
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:12px; margin-bottom:20px; font-size:12.5px; background:#f1f5f9; padding:12px 16px; border-radius:8px;">
                <div><strong><?php esc_html_e( 'Cost Center:', 'ifsedu-school-management' ); ?></strong> <code><?php echo esc_html( $entry->cost_center_code ); ?></code></div>
                <div><strong><?php esc_html_e( 'Project Tag:', 'ifsedu-school-management' ); ?></strong> <?php echo esc_html( ! empty( $entry->project_tag ) ? $entry->project_tag : '—' ); ?></div>
                <div><strong><?php esc_html_e( 'Audit Status:', 'ifsedu-school-management' ); ?></strong> <span style="color:#047857; font-weight:700;"><?php echo esc_html( $entry->approval_status ); ?></span></div>
                <div><strong><?php esc_html_e( 'Recorded By:', 'ifsedu-school-management' ); ?></strong> <?php echo esc_html( $recorder_name ); ?></div>
            </div>

            <?php if ( ! empty( $entry->note ) ) : ?>
                <div style="margin-bottom:20px;">
                    <span style="font-size:11.5px; font-weight:800; color:#64748b; text-transform:uppercase; display:block; margin-bottom:4px;"><?php esc_html_e( 'Internal Auditor Memo / Note', 'ifsedu-school-management' ); ?></span>
                    <div style="background:#fff; border:1px solid #e2e8f0; padding:12px 16px; border-radius:8px; font-size:13px; color:#334155; line-height:1.5;">
                        <?php echo nl2br( esc_html( $entry->note ) ); ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ( ! empty( $entry->attachment_url ) ) : ?>
                <div class="no-print" style="margin-bottom:20px;">
                    <a href="<?php echo esc_url( $entry->attachment_url ); ?>" target="_blank" style="display:inline-flex; align-items:center; gap:6px; background:#f1f5f9; color:#00523c; border:1px solid #cbd5e1; text-decoration:none; padding:8px 16px; border-radius:8px; font-weight:700; font-size:13px;">
                        <span class="dashicons dashicons-media-document"></span>
                        <?php esc_html_e( 'View Attached Voucher Slip / Receipt Document', 'ifsedu-school-management' ); ?>
                    </a>
                </div>
            <?php endif; ?>

            <!-- Formal Signatures -->
            <div class="ifs-acct-sign-row">
                <div class="ifs-acct-sign-col">
                    <div class="ifs-acct-sign-line"><?php esc_html_e( 'Prepared / Cashier', 'ifsedu-school-management' ); ?></div>
                </div>
                <div class="ifs-acct-sign-col">
                    <div class="ifs-acct-sign-line"><?php esc_html_e( 'Accounts Officer', 'ifsedu-school-management' ); ?></div>
                </div>
                <div class="ifs-acct-sign-col">
                    <div class="ifs-acct-sign-line"><?php esc_html_e( 'Principal / Auditor', 'ifsedu-school-management' ); ?></div>
                </div>
            </div>

        </div>

    </div>
    <?php
}
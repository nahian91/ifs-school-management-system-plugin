<?php
/**
 * Triplicate Fee Invoice Print Controller
 * File: fees-invoice-print.php
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Helper: Convert Numeric Amount into Words
 */
if ( ! function_exists( 'educore_number_to_words' ) ) {
    function educore_number_to_words( $amount ) {
        $amount = floatval( $amount );
        if ( $amount <= 0 ) {
            return esc_html__( 'Zero Only', 'ifsedu-school-management' );
        }

        $words = array(
            0  => '',
            1  => 'One',
            2  => 'Two',
            3  => 'Three',
            4  => 'Four',
            5  => 'Five',
            6  => 'Six',
            7  => 'Seven',
            8  => 'Eight',
            9  => 'Nine',
            10 => 'Ten',
            11 => 'Eleven',
            12 => 'Twelve',
            13 => 'Thirteen',
            14 => 'Fourteen',
            15 => 'Fifteen',
            16 => 'Sixteen',
            17 => 'Seventeen',
            18 => 'Eighteen',
            19 => 'Nineteen',
            20 => 'Twenty',
            30 => 'Thirty',
            40 => 'Forty',
            50 => 'Fifty',
            60 => 'Sixty',
            70 => 'Seventy',
            80 => 'Eighty',
            90 => 'Ninety',
        );

        $number = floor( $amount );
        $paisa  = round( ( $amount - $number ) * 100 );
        $str    = array();

        if ( $number >= 10000000 ) { // Crore
            $crore   = floor( $number / 10000000 );
            $number %= 10000000;
            $str[]   = educore_number_to_words( $crore ) . ' Crore';
        }
        if ( $number >= 100000 ) { // Lakh
            $lakh    = floor( $number / 100000 );
            $number %= 100000;
            $str[]   = educore_number_to_words( $lakh ) . ' Lakh';
        }
        if ( $number >= 1000 ) { // Thousand
            $thousand = floor( $number / 1000 );
            $number  %= 1000;
            $str[]    = educore_number_to_words( $thousand ) . ' Thousand';
        }
        if ( $number >= 100 ) { // Hundred
            $hundred  = floor( $number / 100 );
            $number  %= 100;
            $str[]    = $words[ $hundred ] . ' Hundred';
        }
        if ( $number > 0 ) {
            if ( $number < 20 ) {
                $str[] = $words[ $number ];
            } else {
                $ten   = floor( $number / 10 ) * 10;
                $unit  = $number % 10;
                $str[] = $words[ $ten ] . ( $unit ? ' ' . $words[ $unit ] : '' );
            }
        }

        $result = implode( ' ', array_filter( $str ) );
        if ( $paisa > 0 ) {
            $result .= ' and ' . $paisa . ' Cents/Paisa';
        }
        return $result . ' Only';
    }
}

function educore_fees_invoice_print_view() {
    global $wpdb;

    if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'edit_posts' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to view or print payment receipts.', 'ifsedu-school-management' ) );
    }

    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $invoice_id = isset( $_GET['invoice'] ) ? sanitize_text_field( wp_unslash( $_GET['invoice'] ) ) : '';
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    if ( empty( $invoice_id ) ) {
        echo '<div class="ifs-educore-alert-danger">' . esc_html__( 'No invoice identifier specified.', 'ifsedu-school-management' ) . '</div>';
        return;
    }

    $table_fees     = $wpdb->prefix . 'sms_fees';
    $table_students = $wpdb->prefix . 'sms_students';
    $table_staff    = $wpdb->prefix . 'sms_staff';

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $receipt = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT f.*, s.full_name, s.student_id as s_id, s.class_name, s.section_name, s.shift, s.roll_no, s.guardian_phone, s.waiver_percentage, st.full_name as ref_staff_name 
            FROM `{$table_fees}` f 
            LEFT JOIN `{$table_students}` s ON f.student_id = s.id 
            LEFT JOIN `{$table_staff}` st ON s.waiver_staff_id = st.id
            WHERE f.invoice_id = %s LIMIT 1",
            $invoice_id
        )
    );
    // phpcs:enable

    if ( ! $receipt ) {
        echo '<div class="ifs-educore-alert-danger">' . esc_html__( 'Invoice receipt record not found in system schema.', 'ifsedu-school-management' ) . '</div>';
        return;
    }

    $back_url       = add_query_arg( array( 'page' => 'school_management_system', 'tab' => 'fees', 'sub' => 'list' ), admin_url( 'admin.php' ) );
    $school_name    = get_option( 'educore_school_name', get_bloginfo( 'name' ) );
    $school_tagline = get_option( 'educore_school_tagline', '' );
    $school_logo    = get_option( 'educore_school_logo', '' );
    $principal_sig  = get_option( 'educore_principal_sig', '' );

    if ( empty( $school_name ) || 'WordPress' === $school_name ) {
        $school_name = get_bloginfo( 'name' );
    }

    $copies = array(
        __( 'Student Copy', 'ifsedu-school-management' ),
        __( 'Office Copy', 'ifsedu-school-management' ),
        __( 'Accounts / Bank Copy', 'ifsedu-school-management' ),
    );

    $pay_timestamp = ! empty( $receipt->payment_date ) ? strtotime( $receipt->payment_date ) : false;
    $pay_date_formatted = $pay_timestamp ? date_i18n( 'd-M-Y', $pay_timestamp ) : '—';
    ?>

    <!-- Top Action Toolbar -->
    <div class="ifs-educore-print-toolbar no-print">
        <button type="button" onclick="window.print();" class="ifs-educore-btn-print">
            <span class="dashicons dashicons-printer" style="font-size:16px; width:16px; height:16px;"></span>
            <?php esc_html_e( 'Print 3-Part Receipt Voucher', 'ifsedu-school-management' ); ?>
        </button>
        <a href="<?php echo esc_url( $back_url ); ?>" class="ifs-educore-btn-back">
            <span class="dashicons dashicons-arrow-left-alt" style="font-size:14px; width:14px; height:14px;"></span>
            <?php esc_html_e( 'Back to Fee Directory', 'ifsedu-school-management' ); ?>
        </a>
    </div>

    <!-- Printable Receipt Container -->
    <div class="ifs-educore-invoice-print-wrapper" id="educore-printable-receipt-area">
        <div class="ifs-educore-triplicate-grid">
            <?php foreach ( $copies as $copy_label ) : ?>
                <div class="ifs-educore-receipt-card">
                    <div>
                        <!-- Header -->
                        <div class="ifs-educore-receipt-card-header">
                            <?php if ( ! empty( $school_logo ) ) : ?>
                                <img src="<?php echo esc_url( $school_logo ); ?>" alt="<?php esc_attr_e( 'Logo', 'ifsedu-school-management' ); ?>" class="ifs-educore-receipt-logo">
                            <?php endif; ?>
                            <h5 class="ifs-educore-receipt-school-title">
                                <?php echo esc_html( $school_name ); ?>
                            </h5>
                            <?php if ( ! empty( $school_tagline ) ) : ?>
                                <p class="ifs-educore-receipt-school-sub">
                                    <?php echo esc_html( $school_tagline ); ?>
                                </p>
                            <?php endif; ?>
                            <div style="margin: 2px 0;">
                                <span class="ifs-educore-copy-type-badge"><?php echo esc_html( $copy_label ); ?></span>
                            </div>
                        </div>

                        <!-- Student & Meta Info Table -->
                        <table class="ifs-educore-table-receipt-data">
                            <tr>
                                <td><strong><?php esc_html_e( 'Invoice:', 'ifsedu-school-management' ); ?></strong> #<?php echo esc_html( $receipt->invoice_id ); ?></td>
                                <td style="text-align: right;"><strong><?php esc_html_e( 'Date:', 'ifsedu-school-management' ); ?></strong> <?php echo esc_html( $pay_date_formatted ); ?></td>
                            </tr>
                            <tr>
                                <td><strong><?php esc_html_e( 'Student ID:', 'ifsedu-school-management' ); ?></strong> <?php echo esc_html( $receipt->s_id ? strtoupper( (string) $receipt->s_id ) : __( 'N/A', 'ifsedu-school-management' ) ); ?></td>
                                <td style="text-align: right;"><strong><?php esc_html_e( 'Roll:', 'ifsedu-school-management' ); ?></strong> #<?php echo esc_html( $receipt->roll_no ? $receipt->roll_no : __( 'N/A', 'ifsedu-school-management' ) ); ?></td>
                            </tr>
                            <tr>
                                <td colspan="2"><strong><?php esc_html_e( 'Name:', 'ifsedu-school-management' ); ?></strong> <span style="text-transform: uppercase; font-weight: 800;"><?php echo esc_html( $receipt->full_name ? $receipt->full_name : __( 'N/A', 'ifsedu-school-management' ) ); ?></span></td>
                            </tr>
                            <tr>
                                <td>
                                    <strong><?php esc_html_e( 'Class:', 'ifsedu-school-management' ); ?></strong> <?php echo esc_html( $receipt->class_name ); ?> 
                                    <?php echo ! empty( $receipt->section_name ) ? '(' . esc_html( $receipt->section_name ) . ')' : ''; ?>
                                </td>
                                <td style="text-align: right;">
                                    <strong><?php esc_html_e( 'Shift:', 'ifsedu-school-management' ); ?></strong> <?php echo ( ! empty( $receipt->shift ) && 'No Shift' !== $receipt->shift ) ? esc_html( $receipt->shift ) : esc_html__( 'Regular', 'ifsedu-school-management' ); ?>
                                </td>
                            </tr>
                        </table>

                        <!-- Breakdown Table -->
                        <table class="ifs-educore-table-receipt-data ifs-educore-bordered-table">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e( 'Description', 'ifsedu-school-management' ); ?></th>
                                    <th style="text-align: right;"><?php esc_html_e( 'Amount', 'ifsedu-school-management' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html( $receipt->fee_type ); ?></strong><br>
                                        <span style="font-size: 9px; color: #64748b;">(<?php echo esc_html( ucfirst( (string) $receipt->fee_month ) . ' ' . $receipt->fee_year ); ?>)</span>
                                    </td>
                                    <td style="text-align: right; font-weight: 600;"><?php echo esc_html( number_format( (float) $receipt->amount, 2 ) ); ?></td>
                                </tr>
                                <?php if ( floatval( $receipt->late_fine ) > 0 ) : ?>
                                <tr>
                                    <td style="color: #dc2626;"><?php esc_html_e( 'Late Fine (+)', 'ifsedu-school-management' ); ?></td>
                                    <td style="text-align: right; color: #dc2626; font-weight: 600;"><?php echo esc_html( number_format( (float) $receipt->late_fine, 2 ) ); ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if ( floatval( $receipt->discount ) > 0 ) : ?>
                                <tr>
                                    <td style="color: #2563eb;">
                                        <?php esc_html_e( 'Waiver / Discount (-)', 'ifsedu-school-management' ); ?>
                                        <?php if ( ! empty( $receipt->waiver_percentage ) && floatval( $receipt->waiver_percentage ) > 0 ) : ?>
                                            <span style="font-size:8.5px; color:#15803d; display:block;">
                                                [<?php echo esc_html( floatval( $receipt->waiver_percentage ) ); ?>% <?php esc_html_e( 'Waiver', 'ifsedu-school-management' ); ?><?php echo ! empty( $receipt->ref_staff_name ) ? ' - ' . esc_html( $receipt->ref_staff_name ) : ''; ?>]
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: right; color: #2563eb; font-weight: 600;"><?php echo esc_html( number_format( (float) $receipt->discount, 2 ) ); ?></td>
                                </tr>
                                <?php endif; ?>
                                <tr>
                                    <td style="font-weight: 700; background: #f8fafc;"><?php esc_html_e( 'Net Payable', 'ifsedu-school-management' ); ?></td>
                                    <td style="text-align: right; font-weight: 800; background: #f8fafc;"><?php echo esc_html( number_format( (float) $receipt->net_payable, 2 ) ); ?></td>
                                </tr>
                                <tr>
                                    <td style="font-weight: 800; color: #00523c;"><?php esc_html_e( 'Paid Amount', 'ifsedu-school-management' ); ?></td>
                                    <td style="text-align: right; font-weight: 800; color: #00523c;"><?php echo esc_html( number_format( (float) $receipt->paid_amount, 2 ) ); ?></td>
                                </tr>
                                <?php if ( floatval( $receipt->due_amount ) > 0 ) : ?>
                                <tr>
                                    <td style="font-weight: 700; color: #dc2626;"><?php esc_html_e( 'Due Balance', 'ifsedu-school-management' ); ?></td>
                                    <td style="text-align: right; font-weight: 800; color: #dc2626;"><?php echo esc_html( number_format( (float) $receipt->due_amount, 2 ) ); ?></td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>

                        <!-- Payment Method & Amount in Words -->
                        <div class="ifs-educore-words-box">
                            <div><strong><?php esc_html_e( 'Method:', 'ifsedu-school-management' ); ?></strong> <?php echo esc_html( $receipt->payment_method ); ?><?php echo ! empty( $receipt->transaction_id ) ? ' (' . esc_html__( 'Trx:', 'ifsedu-school-management' ) . ' ' . esc_html( $receipt->transaction_id ) . ')' : ''; ?></div>
                            <div style="margin-top:2px;"><strong><?php esc_html_e( 'In Words:', 'ifsedu-school-management' ); ?></strong> <em><?php echo esc_html( educore_number_to_words( $receipt->paid_amount ) ); ?></em></div>
                        </div>
                    </div>

                    <!-- Footer Signatures -->
                    <div class="ifs-educore-signature-area">
                        <div class="ifs-educore-sig-box">
                            <div class="ifs-educore-signature-line-box">
                                <?php esc_html_e( 'Student / Guardian', 'ifsedu-school-management' ); ?>
                            </div>
                        </div>
                        <div class="ifs-educore-sig-box">
                            <?php if ( ! empty( $principal_sig ) ) : ?>
                                <img src="<?php echo esc_url( $principal_sig ); ?>" alt="<?php esc_attr_e( 'Signature', 'ifsedu-school-management' ); ?>" class="ifs-educore-invoice-sig-img">
                            <?php endif; ?>
                            <div class="ifs-educore-signature-line-box">
                                <?php esc_html_e( 'Cashier / Officer', 'ifsedu-school-management' ); ?>
                            </div>
                        </div>
                    </div>

                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
}
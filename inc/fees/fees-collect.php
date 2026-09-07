<?php
/**
 * Fee Collection Module Engine with Automated Late Fine Rules, Prior Due Checks & Billing Month Restrictions
 * File: inc/fees/fees-collect.php
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Fee Collection Module Engine
}

// 1. AJAX Handler to dynamically load Sections based on Class
add_action( 'wp_ajax_ifs_educore_get_sections_by_class_fee', 'ifs_educore_get_sections_by_class_fee_handler' );
function ifs_educore_get_sections_by_class_fee_handler() {
    check_ajax_referer( 'ifs_educore_fee_nonce', 'security' );

    $current_user  = wp_get_current_user();
    $roles         = (array) $current_user->roles;
    $is_admin      = current_user_can( 'manage_options' );
    $is_accountant = in_array( 'accountant', $roles, true ) || current_user_can( 'edit_posts' );

    if ( ! $is_admin && ! $is_accountant ) {
        wp_send_json_error( array( 'message' => esc_html__( 'Permission denied.', 'ifsedu-school-management' ) ) );
    }

    global $wpdb;
    $table_units = $wpdb->prefix . 'sms_academic_units';
    $class_name  = isset( $_POST['class_name'] ) ? sanitize_text_field( wp_unslash( $_POST['class_name'] ) ) : '';

    if ( empty( $class_name ) ) {
        wp_send_json_success( array() );
    }

    $clean_class = trim( str_ireplace( 'Class ', '', $class_name ) );

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $sections = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT DISTINCT section_name FROM `{$table_units}` WHERE (class_name = %s OR class_name = %s) AND section_name != '' ORDER BY sort_order ASC, section_name ASC",
            $class_name,
            $clean_class
        )
    );
    // phpcs:enable

    if ( ! empty( $sections ) && is_array( $sections ) ) {
        usort( $sections, 'strnatcasecmp' );
    }

    wp_send_json_success( is_array( $sections ) ? $sections : array() );
}

// 2. AJAX Handler to fetch configured Fee Categories & Amounts strictly from Fees Settings
add_action( 'wp_ajax_ifs_educore_get_fee_types_by_class', 'ifs_educore_get_fee_types_by_class_handler' );
function ifs_educore_get_fee_types_by_class_handler() {
    check_ajax_referer( 'ifs_educore_fee_nonce', 'security' );

    $current_user  = wp_get_current_user();
    $roles         = (array) $current_user->roles;
    $is_admin      = current_user_can( 'manage_options' );
    $is_accountant = in_array( 'accountant', $roles, true ) || current_user_can( 'edit_posts' );

    if ( ! $is_admin && ! $is_accountant ) {
        wp_send_json_error( array( 'message' => esc_html__( 'Permission denied.', 'ifsedu-school-management' ) ) );
    }

    global $wpdb;
    $table_fee_types = $wpdb->prefix . 'sms_fee_types';
    $table_late_cfg  = $wpdb->prefix . 'sms_late_fee_config';

    $class_name      = isset( $_POST['class_name'] ) ? sanitize_text_field( wp_unslash( $_POST['class_name'] ) ) : '';
    $billing_month   = isset( $_POST['billing_month'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_month'] ) ) : gmdate( 'F' );
    $billing_year    = isset( $_POST['billing_year'] ) ? absint( wp_unslash( $_POST['billing_year'] ) ) : (int) gmdate( 'Y' );
    $clean_class     = trim( str_ireplace( 'Class ', '', $class_name ) );

    $fee_types = array();
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    if ( ! empty( $class_name ) ) {
        $fee_types = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, fee_title, amount, period_type FROM `{$table_fee_types}` WHERE class_name = %s OR class_name = %s ORDER BY id ASC",
                $class_name,
                $clean_class
            )
        );
    }

    // Calculate Late Fine based on Settings Table Rules
    $calculated_fine = 0.00;
    $late_cfg = $wpdb->get_row( "SELECT * FROM `{$table_late_cfg}` LIMIT 1" );
    // phpcs:enable

    if ( $late_cfg && 'active' === strtolower( (string) $late_cfg->status ) ) {
        $today_timestamp = time();
        
        $month_num     = gmdate( 'm', strtotime( $billing_month . ' 1' ) );
        $due_date_str  = sprintf( '%04d-%02d-%02d', $billing_year, $month_num, absint( $late_cfg->fine_start_date ) );
        $due_timestamp = strtotime( $due_date_str );

        if ( $due_timestamp && $today_timestamp > $due_timestamp ) {
            $overdue_days = floor( ( $today_timestamp - $due_timestamp ) / DAY_IN_SECONDS );

            if ( $overdue_days > absint( $late_cfg->grace_days ) ) {
                $effective_days = $overdue_days - absint( $late_cfg->grace_days );
                $fine_type      = $late_cfg->fine_type;
                $rate_val       = floatval( $late_cfg->fine_amount );
                $max_cap        = floatval( $late_cfg->max_fine_cap );

                if ( 'Fixed' === $fine_type ) {
                    $calculated_fine = $rate_val;
                } elseif ( 'Daily' === $fine_type ) {
                    $calculated_fine = $rate_val * $effective_days;
                } elseif ( 'Percentage' === $fine_type ) {
                    $calculated_fine = $rate_val; 
                }

                if ( $max_cap > 0 && $calculated_fine > $max_cap ) {
                    $calculated_fine = $max_cap;
                }
            }
        }
    }

    wp_send_json_success( array(
        'fee_types'   => is_array( $fee_types ) ? $fee_types : array(),
        'late_fine'   => $calculated_fine,
        'fine_type'   => $late_cfg ? $late_cfg->fine_type : 'Fixed',
        'fine_amount' => $late_cfg ? floatval( $late_cfg->fine_amount ) : 0.00,
        'grace_days'  => $late_cfg ? absint( $late_cfg->grace_days ) : 0,
        'start_date'  => $late_cfg ? absint( $late_cfg->fine_start_date ) : 12,
    ) );
}

// 3. AJAX Handler to dynamically filter student list by Class & Section
add_action( 'wp_ajax_ifs_educore_get_students_for_fee_collect', 'ifs_educore_get_students_for_fee_collect_handler' );
function ifs_educore_get_students_for_fee_collect_handler() {
    check_ajax_referer( 'ifs_educore_fee_nonce', 'security' );

    $current_user  = wp_get_current_user();
    $roles         = (array) $current_user->roles;
    $is_admin      = current_user_can( 'manage_options' );
    $is_accountant = in_array( 'accountant', $roles, true ) || current_user_can( 'edit_posts' );

    if ( ! $is_admin && ! $is_accountant ) {
        wp_send_json_error( array( 'message' => esc_html__( 'Permission denied.', 'ifsedu-school-management' ) ) );
    }

    global $wpdb;
    $table_students = $wpdb->prefix . 'sms_students';
    $class_name     = isset( $_POST['class_name'] ) ? sanitize_text_field( wp_unslash( $_POST['class_name'] ) ) : '';
    $section_name   = isset( $_POST['section_name'] ) ? sanitize_text_field( wp_unslash( $_POST['section_name'] ) ) : '';
    $clean_class    = trim( str_ireplace( 'Class ', '', $class_name ) );

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    if ( empty( $class_name ) ) {
        $students = $wpdb->get_results(
            "SELECT id, full_name, student_id, roll_no, class_name, section_name, shift, admission_date, fee_start_date, waiver_percentage, waiver_staff_id 
             FROM `{$table_students}` WHERE status = 'Active' 
             ORDER BY class_name ASC, CAST(roll_no AS UNSIGNED) ASC, roll_no ASC"
        );
    } else {
        if ( ! empty( $section_name ) ) {
            $students = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, full_name, student_id, roll_no, class_name, section_name, shift, admission_date, fee_start_date, waiver_percentage, waiver_staff_id 
                     FROM `{$table_students}` WHERE status = 'Active' AND (class_name = %s OR class_name = %s) AND section_name = %s 
                     ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC",
                    $class_name,
                    $clean_class,
                    $section_name
                )
            );
        } else {
            $students = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, full_name, student_id, roll_no, class_name, section_name, shift, admission_date, fee_start_date, waiver_percentage, waiver_staff_id 
                     FROM `{$table_students}` WHERE status = 'Active' AND (class_name = %s OR class_name = %s) 
                     ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC",
                    $class_name,
                    $clean_class
                )
            );
        }
    }
    // phpcs:enable

    $data = array();
    if ( ! empty( $students ) ) {
        foreach ( $students as $s ) {
            $sec_str   = ! empty( $s->section_name ) ? ' (' . $s->section_name . ')' : '';
            $shift_str = ( ! empty( $s->shift ) && 'No Shift' !== $s->shift ) ? ' [' . $s->shift . ']' : '';
            $data[] = array(
                'id'                => absint( $s->id ),
                'full_name'         => esc_html( $s->full_name ),
                'student_id'        => esc_html( strtoupper( (string) $s->student_id ) ),
                'roll_no'           => esc_html( $s->roll_no ),
                'class_name'        => esc_html( $s->class_name ),
                'section_name'      => esc_html( $s->section_name ? $s->section_name : '' ),
                'class_info'        => esc_html( $s->class_name . $sec_str . $shift_str ),
                'admission_date'    => esc_html( $s->admission_date ? $s->admission_date : '' ),
                'fee_start_date'    => esc_html( $s->fee_start_date ? $s->fee_start_date : ( $s->admission_date ? $s->admission_date : '' ) ),
                'waiver_percentage' => floatval( $s->waiver_percentage ?? 0 ),
                'waiver_staff_id'   => absint( $s->waiver_staff_id ?? 0 ),
            );
        }
    }

    wp_send_json_success( $data );
}

// 4. AJAX Handler to fetch details of a single student, missing unbilled months & recorded past dues
add_action( 'wp_ajax_ifs_educore_get_single_student_waiver_info', 'ifs_educore_get_single_student_waiver_info_handler' );
function ifs_educore_get_single_student_waiver_info_handler() {
    check_ajax_referer( 'ifs_educore_fee_nonce', 'security' );

    $current_user  = wp_get_current_user();
    $roles         = (array) $current_user->roles;
    $is_admin      = current_user_can( 'manage_options' );
    $is_accountant = in_array( 'accountant', $roles, true ) || current_user_can( 'edit_posts' );

    if ( ! $is_admin && ! $is_accountant ) {
        wp_send_json_error( array( 'message' => esc_html__( 'Permission denied.', 'ifsedu-school-management' ) ) );
    }

    global $wpdb;
    $table_students  = $wpdb->prefix . 'sms_students';
    $table_staff     = $wpdb->prefix . 'sms_staff';
    $table_fees      = $wpdb->prefix . 'sms_fees';
    $table_fee_types = $wpdb->prefix . 'sms_fee_types';
    
    // phpcs:disable WordPress.Security.NonceVerification.Missing
    $student_id_num = isset( $_POST['student_id'] ) ? absint( wp_unslash( $_POST['student_id'] ) ) : 0;
    $search_uid     = isset( $_POST['search_uid'] ) ? sanitize_text_field( wp_unslash( $_POST['search_uid'] ) ) : '';
    $current_target_month = isset( $_POST['target_month'] ) ? sanitize_text_field( wp_unslash( $_POST['target_month'] ) ) : gmdate( 'F' );
    $current_target_year  = isset( $_POST['target_year'] ) ? absint( wp_unslash( $_POST['target_year'] ) ) : (int) gmdate( 'Y' );
    // phpcs:enable WordPress.Security.NonceVerification.Missing

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    if ( $student_id_num > 0 ) {
        $student = $wpdb->get_row( $wpdb->prepare( "SELECT id, full_name, student_id, roll_no, class_name, section_name, shift, admission_date, fee_start_date, waiver_percentage, waiver_staff_id FROM `{$table_students}` WHERE id = %d AND status = 'Active' LIMIT 1", $student_id_num ) );
    } elseif ( ! empty( $search_uid ) ) {
        $student = $wpdb->get_row( $wpdb->prepare( "SELECT id, full_name, student_id, roll_no, class_name, section_name, shift, admission_date, fee_start_date, waiver_percentage, waiver_staff_id FROM `{$table_students}` WHERE (student_id = %s OR student_id LIKE %s) AND status = 'Active' LIMIT 1", $search_uid, '%' . $wpdb->esc_like( $search_uid ) . '%' ) );
    } else {
        wp_send_json_error( array( 'message' => esc_html__( 'Invalid parameters provided.', 'ifsedu-school-management' ) ) );
    }

    if ( ! $student ) {
        wp_send_json_error( array( 'message' => esc_html__( 'Student not found.', 'ifsedu-school-management' ) ) );
    }

    $staff_ref_name = '';
    $staff_id_val   = absint( $student->waiver_staff_id );
    if ( $staff_id_val > 0 ) {
        $staff_row = $wpdb->get_row( $wpdb->prepare( "SELECT full_name, designation FROM `{$table_staff}` WHERE id = %d LIMIT 1", $staff_id_val ) );
        if ( $staff_row ) {
            $staff_ref_name = $staff_row->full_name . ' (' . $staff_row->designation . ')';
        }
    }

    $effective_fee_start = ! empty( $student->fee_start_date ) ? $student->fee_start_date : ( ! empty( $student->admission_date ) ? $student->admission_date : '' );

    // 4.1 Check Existing Recorded Invoices for this Student
    $recorded_invoices = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT invoice_id, fee_type, fee_month, fee_year, amount, discount, net_payable, paid_amount, due_amount, payment_status 
             FROM `{$table_fees}` 
             WHERE student_id = %d",
            $student->id
        )
    );

    $recorded_due_total = 0.00;
    $due_breakdown_items = array();
    $recorded_paid_months = array();

    if ( ! empty( $recorded_invoices ) ) {
        foreach ( $recorded_invoices as $inv ) {
            $key = strtolower( trim( $inv->fee_month ) ) . '_' . trim( $inv->fee_year );
            if ( 'Paid' === $inv->payment_status || floatval( $inv->due_amount ) <= 0 ) {
                $recorded_paid_months[] = $key;
            } else {
                $due_amt = floatval( $inv->due_amount );
                $recorded_due_total += $due_amt;
                $due_breakdown_items[] = array(
                    'fee_title' => $inv->fee_type,
                    'month'     => ucfirst( $inv->fee_month ),
                    'year'      => $inv->fee_year,
                    'status'    => 'Unpaid Invoice',
                    'amount'    => $due_amt,
                );
            }
        }
    }

    // 4.2 Detect Unbilled Past Monthly Fees between fee_start_date and current_target_month/year
    $unbilled_due_total = 0.00;
    $clean_class = trim( str_ireplace( 'Class ', '', $student->class_name ) );

    // Fetch monthly tuition base amount configured for this class
    $monthly_fee_row = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT amount, fee_title FROM `{$table_fee_types}` WHERE (class_name = %s OR class_name = %s) AND period_type = 'Monthly' ORDER BY id ASC LIMIT 1",
            $student->class_name,
            $clean_class
        )
    );
    // phpcs:enable

    $standard_monthly_amt = $monthly_fee_row ? floatval( $monthly_fee_row->amount ) : 0.00;
    $fee_title_name       = $monthly_fee_row ? $monthly_fee_row->fee_title : __( 'Tuition Fee', 'ifsedu-school-management' );
    $waiver_pct           = floatval( $student->waiver_percentage ?? 0 );

    if ( ! empty( $effective_fee_start ) ) {
        $start_ts  = strtotime( gmdate( 'Y-m-01', strtotime( $effective_fee_start ) ) );
        $target_ts = strtotime( gmdate( 'Y-m-01', strtotime( $current_target_month . ' 1, ' . $current_target_year ) ) );

        $cursor_ts = $start_ts;
        while ( $cursor_ts < $target_ts ) {
            $m_name = gmdate( 'F', $cursor_ts );
            $m_year = gmdate( 'Y', $cursor_ts );
            $m_key  = strtolower( $m_name ) . '_' . $m_year;

            // Check if student already paid this month
            if ( ! in_array( $m_key, $recorded_paid_months, true ) ) {
                $month_cost = $standard_monthly_amt;
                if ( $waiver_pct > 0 && $month_cost > 0 ) {
                    $month_cost = max( 0, $month_cost - ( ( $month_cost * $waiver_pct ) / 100 ) );
                }

                if ( $month_cost > 0 ) {
                    $unbilled_due_total += $month_cost;
                    $due_breakdown_items[] = array(
                        'fee_title' => $fee_title_name,
                        'month'     => $m_name,
                        'year'      => $m_year,
                        'status'    => 'Unbilled Month',
                        'amount'    => $month_cost,
                    );
                }
            }

            // Move to next month
            $cursor_ts = strtotime( '+1 month', $cursor_ts );
        }
    }

    $total_all_past_dues = $recorded_due_total + $unbilled_due_total;

    wp_send_json_success( array(
        'id'                  => absint( $student->id ),
        'full_name'           => esc_html( $student->full_name ),
        'student_id'          => esc_html( strtoupper( (string) $student->student_id ) ),
        'roll_no'             => esc_html( $student->roll_no ),
        'class_name'          => esc_html( $student->class_name ),
        'section_name'        => esc_html( $student->section_name ? $student->section_name : '' ),
        'shift'               => esc_html( $student->shift ? $student->shift : 'No Shift' ),
        'admission_date'      => esc_html( $student->admission_date ? $student->admission_date : '' ),
        'fee_start_date'      => esc_html( $effective_fee_start ),
        'fee_start_display'   => esc_html( $effective_fee_start ? date_i18n( 'F Y', strtotime( $effective_fee_start ) ) : __( 'From Admission', 'ifsedu-school-management' ) ),
        'waiver_percentage'   => $waiver_pct,
        'staff_ref_name'      => esc_html( $staff_ref_name ),
        'previous_due'        => $total_all_past_dues,
        'due_breakdown_items' => $due_breakdown_items,
    ) );
}

function educore_fees_collect_view() {
    global $wpdb;
    $current_user = wp_get_current_user();
    $roles        = (array) $current_user->roles;

    $is_admin      = current_user_can( 'manage_options' );
    $is_accountant = in_array( 'accountant', $roles, true ) || current_user_can( 'edit_posts' );

    if ( ! $is_admin && ! $is_accountant ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to collect fees.', 'ifsedu-school-management' ) );
    }

    $table_students  = $wpdb->prefix . 'sms_students';
    $table_fees      = $wpdb->prefix . 'sms_fees';
    $table_units     = $wpdb->prefix . 'sms_academic_units';
    $table_late_cfg  = $wpdb->prefix . 'sms_late_fee_config';

    $db_error = '';
    $back_url = add_query_arg(
        array(
            'page' => 'school_management_system',
            'tab'  => 'fees',
            'sub'  => 'list',
        ),
        admin_url( 'admin.php' )
    );

    // Handle Form Submission
    $req_method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
    if ( 'POST' === $req_method && isset( $_POST['educore_collect_fee'] ) ) {
        $fee_nonce = isset( $_POST['ifs_educore_fee_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['ifs_educore_fee_nonce'] ) ) : '';
        if ( wp_verify_nonce( $fee_nonce, 'collect_fee_action' ) ) {
            
            $student_id   = isset( $_POST['student_id'] ) ? absint( wp_unslash( $_POST['student_id'] ) ) : 0;
            $amount       = isset( $_POST['amount'] ) ? max( 0, floatval( wp_unslash( $_POST['amount'] ) ) ) : 0;
            $late_fine    = isset( $_POST['late_fine'] ) ? max( 0, floatval( wp_unslash( $_POST['late_fine'] ) ) ) : 0;
            $previous_due = isset( $_POST['previous_due'] ) ? max( 0, floatval( wp_unslash( $_POST['previous_due'] ) ) ) : 0;
            $discount     = isset( $_POST['discount'] ) ? max( 0, floatval( wp_unslash( $_POST['discount'] ) ) ) : 0;
            $paid_amount  = isset( $_POST['paid_amount'] ) ? max( 0, floatval( wp_unslash( $_POST['paid_amount'] ) ) ) : 0;
            
            // Mathematical Ledger Rules (Includes Previous Due)
            $gross_total = $amount + $late_fine + $previous_due;
            $net_payable = max( 0, $gross_total - $discount );
            $due_amount  = max( 0, $net_payable - $paid_amount );
            
            // Payment Status Logic
            $payment_status = 'Unpaid';
            if ( $paid_amount >= $net_payable && $net_payable > 0 ) {
                $payment_status = 'Paid';
                $due_amount     = 0;
            } elseif ( $paid_amount > 0 && $paid_amount < $net_payable ) {
                $payment_status = 'Partial';
            }

            $prefix_fee = get_option( 'educore_prefix_fee', 'INV-' );
            if ( empty( $prefix_fee ) ) {
                $prefix_fee = 'INV-';
            }
            $invoice_id = $prefix_fee . gmdate( 'ym' ) . '-' . wp_rand( 10000, 99999 );

            $fee_month = isset( $_POST['fee_month'] ) ? sanitize_text_field( wp_unslash( $_POST['fee_month'] ) ) : '';
            $fee_year  = isset( $_POST['fee_year'] ) ? absint( wp_unslash( $_POST['fee_year'] ) ) : (int) gmdate( 'Y' );
            $fee_type  = isset( $_POST['fee_type'] ) ? sanitize_text_field( wp_unslash( $_POST['fee_type'] ) ) : '';
            $p_method  = isset( $_POST['payment_method'] ) ? sanitize_text_field( wp_unslash( $_POST['payment_method'] ) ) : 'Cash';
            $trx_id    = isset( $_POST['transaction_id'] ) ? sanitize_text_field( wp_unslash( $_POST['transaction_id'] ) ) : '';
            $remarks   = isset( $_POST['remarks'] ) ? sanitize_text_field( wp_unslash( $_POST['remarks'] ) ) : '';

            $data = array(
                'invoice_id'     => $invoice_id,
                'student_id'     => $student_id,
                'fee_month'      => $fee_month,
                'fee_year'       => $fee_year,
                'fee_type'       => $fee_type,
                'amount'         => $amount,
                'late_fine'      => $late_fine,
                'discount'       => $discount,
                'net_payable'    => $net_payable,
                'paid_amount'    => $paid_amount,
                'due_amount'     => $due_amount,
                'payment_status' => $payment_status,
                'payment_method' => $p_method,
                'transaction_id' => $trx_id,
                'remarks'        => $remarks,
                'payment_date'   => current_time( 'mysql' ),
                'collected_by'   => get_current_user_id(),
            );

            $format = array(
                '%s', '%d', '%s', '%d', '%s', '%f', '%f', '%f', '%f', '%f', '%f', '%s', '%s', '%s', '%s', '%s', '%d'
            );

            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $inserted = $wpdb->insert( "{$table_fees}", $data, $format );
            // phpcs:enable
            
            if ( $inserted ) {
                if ( function_exists( 'educore_log_activity' ) ) {
                    /* translators: 1: Invoice ID, 2: Paid Amount */
                    educore_log_activity( sprintf( __( 'Collected fee invoice: (%1$s) Amount: %2$.2f', 'ifsedu-school-management' ), $invoice_id, $paid_amount ) );
                }

                $redirect_url = add_query_arg(
                    array(
                        'page' => 'school_management_system',
                        'tab'  => 'fees',
                        'sub'  => 'list',
                        'msg'  => 'collected',
                    ),
                    admin_url( 'admin.php' )
                );

                if ( ! headers_sent() ) {
                    wp_safe_redirect( $redirect_url );
                    exit;
                } else {
                    echo '<script type="text/javascript">window.location.href=' . wp_json_encode( esc_url_raw( $redirect_url ) ) . ';</script>';
                    exit;
                }
            } else {
                $db_error = $wpdb->last_error ? $wpdb->last_error : __( 'Failed to record fee entry in database.', 'ifsedu-school-management' );
            }
        } else {
            $db_error = __( 'Security nonce mismatch. Please refresh and try again.', 'ifsedu-school-management' );
        }
    }

    // Fetch Unique Classes ordered primarily by sort_order
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $raw_classes_data = $wpdb->get_results( 
        "SELECT class_name, MIN(sort_order) as min_sort 
         FROM `{$table_units}` 
         WHERE class_name IS NOT NULL AND class_name != '' 
         GROUP BY class_name 
         ORDER BY min_sort ASC, CAST(class_name AS UNSIGNED) ASC, class_name ASC" 
    );
    // phpcs:enable

    $raw_classes = array();
    if ( ! empty( $raw_classes_data ) && is_array( $raw_classes_data ) ) {
        foreach ( $raw_classes_data as $c_row ) {
            $raw_classes[] = (object) array( 'class_name' => $c_row->class_name );
        }
    }

    // Fetch Initial Active Students List
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $students = $wpdb->get_results(
        "SELECT id, full_name, student_id, roll_no, class_name, section_name, shift, admission_date, fee_start_date, waiver_percentage, waiver_staff_id 
         FROM `{$table_students}` WHERE status = 'Active' 
         ORDER BY class_name ASC, CAST(roll_no AS UNSIGNED) ASC, roll_no ASC"
    );
    // phpcs:enable

    $months        = array( 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December' );
    $current_month = gmdate( 'F' );
    $current_year  = gmdate( 'Y' );
    ?>

    <div class="ifs-educore-fees-container">

        <!-- Navigation Bar -->
        <div class="ifs-educore-top-bar">
            <a href="<?php echo esc_url( $back_url ); ?>" class="ifs-educore-btn-back">
                <span class="dashicons dashicons-arrow-left-alt" style="font-size:14px; width:14px; height:14px;"></span>
                <?php esc_html_e( 'Back to Fee Directory', 'ifsedu-school-management' ); ?>
            </a>
        </div>

        <?php if ( ! empty( $db_error ) ) : ?>
            <div class="ifs-educore-alert-error">
                <span class="dashicons dashicons-warning" style="font-size:18px; width:18px; height:18px;"></span>
                <span><strong><?php esc_html_e( 'Database Error:', 'ifsedu-school-management' ); ?></strong> <?php echo esc_html( $db_error ); ?></span>
            </div>
        <?php endif; ?>

        <!-- Main Entry Workspace Bento Box -->
        <div class="ifs-educore-bento-card">

            <form method="POST" action="" id="ifs_educore_fee_collect_form">
                <?php wp_nonce_field( 'collect_fee_action', 'ifs_educore_fee_nonce' ); ?>
                
                <!-- Live Search + Cascade Category Filter -->
                <div class="ifs-educore-grid-filter-live">
                    
                    <!-- 1. Live Instant ID Search -->
                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-form-label" style="color:#00523c;">
                            <span class="dashicons dashicons-search" style="font-size:13px; width:13px; height:13px; vertical-align:middle;"></span>
                            <?php esc_html_e( 'Live ID Search', 'ifsedu-school-management' ); ?>
                        </label>
                        <input type="text" id="ifs_educore_live_id_search" class="ifs-educore-field-input" placeholder="<?php esc_attr_e( 'Type ID e.g. STU-0001', 'ifsedu-school-management' ); ?>" style="font-weight:700; border-color:#00523c; background:#ffffff; text-transform:uppercase;" autocomplete="off">
                    </div>

                    <!-- 2. Class Filter -->
                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-form-label"><?php esc_html_e( 'Class', 'ifsedu-school-management' ); ?></label>
                        <select id="ifs_educore_fee_class_filter" class="ifs-educore-field-select" style="font-weight:600;">
                            <option value=""><?php esc_html_e( '-- All Classes --', 'ifsedu-school-management' ); ?></option>
                            <?php if ( ! empty( $raw_classes ) && is_array( $raw_classes ) ) : ?>
                                <?php foreach ( $raw_classes as $cls_obj ) : ?>
                                    <option value="<?php echo esc_attr( $cls_obj->class_name ); ?>"><?php echo esc_html( $cls_obj->class_name ); ?></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <!-- 3. Section Filter (Dynamic) -->
                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-form-label"><?php esc_html_e( 'Section', 'ifsedu-school-management' ); ?></label>
                        <select id="ifs_educore_fee_section_filter" class="ifs-educore-field-select" style="font-weight:600;">
                            <option value=""><?php esc_html_e( '-- All Sections --', 'ifsedu-school-management' ); ?></option>
                        </select>
                    </div>

                    <!-- 4. Student Dropdown -->
                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-form-label"><?php esc_html_e( 'Target Student', 'ifsedu-school-management' ); ?> <span style="color:#dc2626;">*</span></label>
                        <select name="student_id" id="ifs_educore_fee_student_select" class="ifs-educore-field-select" style="font-size:13.5px; font-weight:700;" required>
                            <option value=""><?php esc_html_e( '-- Choose Active Student --', 'ifsedu-school-management' ); ?></option>
                            <?php if ( ! empty( $students ) ) : ?>
                                <?php foreach ( $students as $s ) : 
                                    $sec_info = ! empty( $s->section_name ) ? ' (' . $s->section_name . ')' : '';
                                    $shift_info = ( ! empty( $s->shift ) && 'No Shift' !== $s->shift ) ? ' [' . $s->shift . ']' : '';
                                ?>
                                    <option value="<?php echo esc_attr( $s->id ); ?>" data-uid="<?php echo esc_attr( strtoupper( (string) $s->student_id ) ); ?>">
                                        <?php
                                        printf(
                                            /* translators: 1: Student roll number, 2: Student full name, 3: Student ID, 4: Class name, 5: Section info (optional), 6: Shift info (optional) */
                                            esc_html__( '[Roll: %1$s] - %2$s (ID: %3$s) | %4$s%5$s%6$s', 'ifsedu-school-management' ),
                                            esc_html( $s->roll_no ),
                                            esc_html( $s->full_name ),
                                            esc_html( strtoupper( (string) $s->student_id ) ),
                                            esc_html( $s->class_name ),
                                            esc_html( $sec_info ),
                                            esc_html( $shift_info )
                                        );
                                        ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>

                <!-- Dynamic Student Waiver, Fee Start Date & Previous Arrears Info Strip -->
                <div id="ifs_educore_student_info_strip" class="ifs-educore-student-quick-strip">
                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                        <div>
                            <strong id="ifs_educore_strip_student_name" style="font-size:15px; color:#065f46;"></strong> 
                            <span id="ifs_educore_strip_student_class" style="font-weight:600; margin-left:6px;"></span>
                            <span id="ifs_educore_strip_fee_start_badge" style="background:#eff6ff; color:#1e40af; border:1px solid #bfdbfe; padding:2px 8px; border-radius:4px; font-size:11.5px; font-weight:700; margin-left:8px;"></span>
                        </div>
                        <div style="display:flex; gap:8px; align-items:center;">
                            <div id="ifs_educore_strip_due_badge" style="display:none; background:#fef2f2; border:1.5px solid #ef4444; padding:4px 12px; border-radius:20px; font-weight:800; color:#dc2626; font-size:12px;">
                                <span class="dashicons dashicons-warning" style="font-size:14px; width:14px; height:14px; vertical-align:middle;"></span>
                                <span id="ifs_educore_strip_due_text"></span>
                            </div>
                            <div id="ifs_educore_strip_waiver_badge" style="display:none; background:#ffffff; border:1.5px solid #059669; padding:4px 12px; border-radius:20px; font-weight:800; color:#059669; font-size:12px;">
                                <span class="dashicons dashicons-tag" style="font-size:14px; width:14px; height:14px; vertical-align:middle;"></span>
                                <span id="ifs_educore_strip_waiver_text"></span>
                            </div>
                        </div>
                    </div>
                    <!-- Detailed Unpaid Invoices Breakdown Box (Table View) -->
                    <div id="ifs_educore_strip_due_details" style="display:none; margin-top:12px;"></div>
                </div>

                <!-- Parameters Grid -->
                <div class="ifs-educore-grid-3" style="margin-bottom: 20px;">
                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-form-label"><?php esc_html_e( 'Fee Category Type', 'ifsedu-school-management' ); ?> <span style="color:#dc2626;">*</span></label>
                        <select name="fee_type" id="ifs_educore_fee_type_select" class="ifs-educore-field-select" required>
                            <option value=""><?php esc_html_e( '-- Select Class or Student First --', 'ifsedu-school-management' ); ?></option>
                        </select>
                    </div>
                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-form-label"><?php esc_html_e( 'Billing Month', 'ifsedu-school-management' ); ?></label>
                        <select name="fee_month" id="ifs_educore_fee_month_select" class="ifs-educore-field-select" required>
                            <?php foreach ( $months as $m ) : ?>
                                <option value="<?php echo esc_attr( $m ); ?>" <?php selected( $current_month, $m ); ?>>
                                    <?php echo esc_html( $m ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-form-label"><?php esc_html_e( 'Billing Year', 'ifsedu-school-management' ); ?></label>
                        <input type="number" name="fee_year" id="ifs_educore_fee_year_input" class="ifs-educore-field-input" value="<?php echo esc_attr( $current_year ); ?>" required>
                    </div>
                </div>

                <!-- Mathematical Ledger & Quick Waiver Panel -->
                <div class="ifs-educore-ledger-panel">
                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-form-label"><?php esc_html_e( 'Current Month Fee (৳)', 'ifsedu-school-management' ); ?> <span style="color:#dc2626;">*</span></label>
                        <input type="number" step="0.01" name="amount" id="ifs_educore_fee_amount" class="ifs-educore-field-input" value="0.00" min="0" required>
                    </div>
                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-form-label" style="color: #dc2626;"><?php esc_html_e( 'Late Fine (৳)', 'ifsedu-school-management' ); ?></label>
                        <input type="number" step="0.01" name="late_fine" id="ifs_educore_fee_fine" class="ifs-educore-field-input" value="0.00" min="0">
                    </div>
                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-form-label" style="color: #b91c1c;">
                            <?php esc_html_e( 'Previous Arrears / Due (৳)', 'ifsedu-school-management' ); ?>
                            <button type="button" id="btn_clear_past_due" style="background:none; border:none; color:#2563eb; font-size:10px; cursor:pointer; font-weight:700;"><?php esc_html_e( '[Clear/Ignore]', 'ifsedu-school-management' ); ?></button>
                        </label>
                        <input type="number" step="0.01" name="previous_due" id="ifs_educore_fee_previous_due" class="ifs-educore-field-input" value="0.00" min="0" readonly style="border-color:#fca5a5; font-weight:800; color:#b91c1c; background:#fef2f2; cursor:not-allowed;">
                    </div>
                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-form-label" style="color: #2563eb;"><?php esc_html_e( 'Waiver / Discount (৳)', 'ifsedu-school-management' ); ?></label>
                        <input type="number" step="0.01" name="discount" id="ifs_educore_fee_discount" class="ifs-educore-field-input" value="0.00" min="0">
                        <div class="ifs-educore-pct-group">
                            <button type="button" class="ifs-educore-btn-pct ifs-educore-discount-btn" data-pct="5">5%</button>
                            <button type="button" class="ifs-educore-btn-pct ifs-educore-discount-btn" data-pct="10">10%</button>
                            <button type="button" class="ifs-educore-btn-pct ifs-educore-discount-btn" data-pct="50">50%</button>
                            <button type="button" class="ifs-educore-btn-pct ifs-educore-discount-btn" data-pct="100">100%</button>
                        </div>
                    </div>
                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-form-label" style="color: #047857;"><?php esc_html_e( 'Net Payable (৳)', 'ifsedu-school-management' ); ?></label>
                        <input type="number" id="ifs_educore_fee_net" class="ifs-educore-field-input ifs-educore-readonly-net" value="0.00" readonly>
                    </div>
                </div>

                <!-- Payment Details Module -->
                <div class="ifs-educore-grid-3" style="margin-bottom: 20px;">
                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-form-label"><?php esc_html_e( 'Payment Method', 'ifsedu-school-management' ); ?></label>
                        <select name="payment_method" id="payment_method" class="ifs-educore-field-select" required>
                            <option value="Cash"><?php esc_html_e( 'Cash Clearing', 'ifsedu-school-management' ); ?></option>
                            <option value="bKash"><?php esc_html_e( 'bKash Mobile Banking', 'ifsedu-school-management' ); ?></option>
                            <option value="Nagad"><?php esc_html_e( 'Nagad Mobile Banking', 'ifsedu-school-management' ); ?></option>
                            <option value="Bank Transfer"><?php esc_html_e( 'Direct Bank Wire', 'ifsedu-school-management' ); ?></option>
                            <option value="Cheque"><?php esc_html_e( 'Cheque Payment', 'ifsedu-school-management' ); ?></option>
                        </select>
                    </div>
                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-form-label" style="color: #00523c;"><?php esc_html_e( 'Actually Paid (৳)', 'ifsedu-school-management' ); ?> <span style="color:#dc2626;">*</span></label>
                        <input type="number" step="0.01" name="paid_amount" id="ifs_educore_fee_paid" class="ifs-educore-field-input" style="border-color: #00523c; font-weight: 800; color: #00523c;" value="0.00" min="0" required>
                    </div>
                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-form-label" style="color: #b45309;"><?php esc_html_e( 'Outstanding Due (৳)', 'ifsedu-school-management' ); ?></label>
                        <input type="number" id="ifs_educore_fee_due" class="ifs-educore-field-input ifs-educore-readonly-due" value="0.00" readonly>
                    </div>
                </div>

                <!-- Audit Meta Info -->
                <div class="ifs-educore-grid-2" style="margin-bottom: 24px;">
                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-form-label"><?php esc_html_e( 'Transaction / Reference ID', 'ifsedu-school-management' ); ?></label>
                        <input type="text" name="transaction_id" class="ifs-educore-field-input" placeholder="<?php esc_attr_e( 'e.g. TRX98234723 or Cheque No.', 'ifsedu-school-management' ); ?>">
                    </div>
                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-form-label"><?php esc_html_e( 'Notes / Remarks', 'ifsedu-school-management' ); ?></label>
                        <input type="text" name="remarks" class="ifs-educore-field-input" placeholder="<?php esc_attr_e( 'e.g. Includes previous month due settlement', 'ifsedu-school-management' ); ?>">
                    </div>
                </div>

                <!-- Action Button -->
                <button type="submit" name="educore_collect_fee" class="ifs-educore-btn-submit">
                    <span class="dashicons dashicons-saved" style="font-size:20px; width:20px; height:20px;"></span>
                    <?php esc_html_e( 'Receive Payment & Generate Receipt', 'ifsedu-school-management' ); ?>
                </button>
            </form>
        </div>

    </div>

    <!-- Live Calculations, Automated Late Fines, Auto-Waiver & Fee Start Date Boundary Script -->
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        var nonce = '<?php echo esc_js( wp_create_nonce( "ifs_educore_fee_nonce" ) ); ?>';
        var activeStudentWaiverPct = 0;
        var activeStudentFeeStartDate = ''; // Format: YYYY-MM-DD
        var searchDebounceTimer;

        var monthMap = {
            "January": 1, "February": 2, "March": 3, "April": 4, "May": 5, "June": 6,
            "July": 7, "August": 8, "September": 9, "October": 10, "November": 11, "December": 12
        };

        // Restrict / Disable previous months in Billing Month dropdown that precede student's fee start date
        function enforceBillingMonthRestrictions() {
            var $monthSelect = $('#ifs_educore_fee_month_select');
            var selectedYear = parseInt($('#ifs_educore_fee_year_input').val(), 10) || 0;

            if (!activeStudentFeeStartDate) {
                $monthSelect.find('option').prop('disabled', false);
                return;
            }

            var parts = activeStudentFeeStartDate.split('-');
            if (parts.length >= 2) {
                var startYear  = parseInt(parts[0], 10) || 0;
                var startMonth = parseInt(parts[1], 10) || 1;

                $monthSelect.find('option').each(function() {
                    var mVal = $(this).val();
                    var mNum = monthMap[mVal] || 1;
                    var shouldDisable = false;

                    if (selectedYear < startYear) {
                        shouldDisable = true;
                    } else if (selectedYear === startYear && mNum < startMonth) {
                        shouldDisable = true;
                    }

                    $(this).prop('disabled', shouldDisable);
                });

                // If currently selected month is disabled, auto-shift to first valid month
                var currentSelectedOpt = $monthSelect.find('option:selected');
                if (currentSelectedOpt.prop('disabled')) {
                    var firstEnabled = $monthSelect.find('option:not(:disabled)').first();
                    if (firstEnabled.length > 0) {
                        $monthSelect.val(firstEnabled.val());
                    }
                }
            }
        }

        // Trigger dynamic single student info reload including previous due detection
        function reloadCurrentStudentInfo() {
            var stId = $('#ifs_educore_fee_student_select').val();
            var targetMonth = $('#ifs_educore_fee_month_select').val();
            var targetYear = $('#ifs_educore_fee_year_input').val();

            if (!stId) return;

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ifs_educore_get_single_student_waiver_info',
                    security: nonce,
                    student_id: stId,
                    target_month: targetMonth,
                    target_year: targetYear
                },
                success: function(response) {
                    if (response.success && response.data) {
                        applyStudentDetails(response.data);
                    }
                }
            });
        }

        // 1. Live Instant Student ID Search with Auto-Fill
        $('#ifs_educore_live_id_search').on('input', function() {
            var searchUid = $(this).val().trim();
            clearTimeout(searchDebounceTimer);

            if (searchUid.length < 2) return;

            searchDebounceTimer = setTimeout(function() {
                var targetMonth = $('#ifs_educore_fee_month_select').val();
                var targetYear = $('#ifs_educore_fee_year_input').val();

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'ifs_educore_get_single_student_waiver_info',
                        security: nonce,
                        search_uid: searchUid,
                        target_month: targetMonth,
                        target_year: targetYear
                    },
                    success: function(response) {
                        if (response.success && response.data) {
                            var d = response.data;
                            $('#ifs_educore_fee_class_filter').val(d.class_name);
                            loadFeeTypesAndFine(d.class_name, function() {
                                loadSectionsAndSelect(d.class_name, d.section_name, d.id, function() {
                                    applyStudentDetails(d);
                                });
                            });
                        }
                    }
                });
            }, 300);
        });

        // 2. Fetch Sections & Fee Types when Class Filter Changes
        $('#ifs_educore_fee_class_filter').on('change', function() {
            var selectedClass = $(this).val();
            loadSectionsAndSelect(selectedClass, '', 0);
            loadFeeTypesAndFine(selectedClass);
        });

        // Re-calculate fine & previous due when billing month or year changes
        $('#ifs_educore_fee_month_select, #ifs_educore_fee_year_input').on('change', function() {
            var selectedClass = $('#ifs_educore_fee_class_filter').val();
            enforceBillingMonthRestrictions();
            if (selectedClass) {
                loadFeeTypesAndFine(selectedClass);
            }
            reloadCurrentStudentInfo();
        });

        // 3. Dynamic Fee Types & Automated Fine Loader
        function loadFeeTypesAndFine(className, callback) {
            var $feeTypeSelect = $('#ifs_educore_fee_type_select');
            var billingMonth   = $('#ifs_educore_fee_month_select').val();
            var billingYear    = $('#ifs_educore_fee_year_input').val();
            
            if (!className) {
                $feeTypeSelect.html('<option value=""><?php echo esc_js( __( '-- Select Class or Student First --', 'ifsedu-school-management' ) ); ?></option>');
                $('#ifs_educore_fee_amount').val('0.00');
                $('#ifs_educore_fee_fine').val('0.00');
                applyWaiverDiscount();
                if (typeof callback === 'function') callback();
                return;
            }

            $feeTypeSelect.html('<option value=""><?php echo esc_js( __( '-- Loading Fee Categories... --', 'ifsedu-school-management' ) ); ?></option>');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ifs_educore_get_fee_types_by_class',
                    security: nonce,
                    class_name: className,
                    billing_month: billingMonth,
                    billing_year: billingYear
                },
                success: function(response) {
                    if (response.success && response.data) {
                        var resData = response.data;
                        
                        // Populate Fee Categories
                        if (resData.fee_types && resData.fee_types.length > 0) {
                            var options = '<option value=""><?php echo esc_js( __( '-- Select Fee Category --', 'ifsedu-school-management' ) ); ?></option>';
                            $.each(resData.fee_types, function(i, item) {
                                options += '<option value="' + item.fee_title + '" data-amount="' + item.amount + '" data-period="' + item.period_type + '">' + item.fee_title + ' (৳' + parseFloat(item.amount).toFixed(2) + ' - ' + item.period_type + ')</option>';
                            });
                            $feeTypeSelect.html(options);

                            var firstAmount = parseFloat(resData.fee_types[0].amount) || 0;
                            $feeTypeSelect.prop('selectedIndex', 1);
                            $('#ifs_educore_fee_amount').val(firstAmount.toFixed(2));
                            
                            var calculatedFine = parseFloat(resData.late_fine) || 0;
                            if (resData.fine_type === 'Percentage') {
                                var curBase = parseFloat($('#ifs_educore_fee_amount').val()) || 0;
                                calculatedFine = (curBase * parseFloat(resData.fine_amount)) / 100;
                            }
                            $('#ifs_educore_fee_fine').val(calculatedFine.toFixed(2));
                        } else {
                            $feeTypeSelect.html('<option value=""><?php echo esc_js( __( 'No Fee Settings Configured for this Class', 'ifsedu-school-management' ) ); ?></option>');
                            $('#ifs_educore_fee_amount').val('0.00');
                            $('#ifs_educore_fee_fine').val('0.00');
                        }
                    }
                    applyWaiverDiscount();
                    if (typeof callback === 'function') callback();
                },
                error: function() {
                    $feeTypeSelect.html('<option value=""><?php echo esc_js( __( '-- Error Loading Fee Categories --', 'ifsedu-school-management' ) ); ?></option>');
                    if (typeof callback === 'function') callback();
                }
            });
        }

        // When Fee Type Changes, auto-set Base Amount
        $('#ifs_educore_fee_type_select').on('change', function() {
            var selectedOpt = $(this).find(':selected');
            var selectedAmount = selectedOpt.data('amount');
            
            if (typeof selectedAmount !== 'undefined' && selectedAmount !== '') {
                $('#ifs_educore_fee_amount').val(parseFloat(selectedAmount).toFixed(2));
            } else {
                $('#ifs_educore_fee_amount').val('0.00');
            }
            applyWaiverDiscount();
        });

        // 4. Reload Students when Section Filter Changes
        $('#ifs_educore_fee_section_filter').on('change', function() {
            var selectedClass   = $('#ifs_educore_fee_class_filter').val();
            var selectedSection = $(this).val();
            reloadFeeStudents(selectedClass, selectedSection, 0);
        });

        function loadSectionsAndSelect(selectedClass, targetSection, selectStudentId, callback) {
            var $sectionSelect = $('#ifs_educore_fee_section_filter');
            $sectionSelect.html('<option value=""><?php echo esc_js( __( '-- All Sections --', 'ifsedu-school-management' ) ); ?></option>');

            if (!selectedClass) {
                reloadFeeStudents('', '', 0);
                if (typeof callback === 'function') callback();
                return;
            }

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ifs_educore_get_sections_by_class_fee',
                    security: nonce,
                    class_name: selectedClass
                },
                success: function(response) {
                    if (response.success && response.data.length > 0) {
                        var secOptions = '<option value=""><?php echo esc_js( __( '-- All Sections --', 'ifsedu-school-management' ) ); ?></option>';
                        $.each(response.data, function(i, sec) {
                            var isSelected = (sec === targetSection) ? 'selected' : '';
                            secOptions += '<option value="' + sec + '" ' + isSelected + '>' + sec + '</option>';
                        });
                        $sectionSelect.html(secOptions);
                    }
                    reloadFeeStudents(selectedClass, targetSection, selectStudentId, callback);
                }
            });
        }

        function reloadFeeStudents(selectedClass, selectedSection, selectStudentId, callback) {
            var $studentSelect = $('#ifs_educore_fee_student_select');
            $studentSelect.html('<option value=""><?php echo esc_js( __( '-- Loading Active Students... --', 'ifsedu-school-management' ) ); ?></option>');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ifs_educore_get_students_for_fee_collect',
                    security: nonce,
                    class_name: selectedClass,
                    section_name: selectedSection
                },
                success: function(response) {
                    if (response.success && response.data.length > 0) {
                        var options = '<option value=""><?php echo esc_js( __( '-- Search & Select Active Student --', 'ifsedu-school-management' ) ); ?></option>';
                        $.each(response.data, function(index, student) {
                            var isSelected = (selectStudentId && student.id === selectStudentId) ? 'selected' : '';
                            options += '<option value="' + student.id + '" data-uid="' + student.student_id + '" ' + isSelected + '>[Roll: ' + student.roll_no + '] - ' + student.full_name + ' (ID: ' + student.student_id + ') | ' + student.class_info + '</option>';
                        });
                        $studentSelect.html(options);

                        if (selectStudentId) {
                            $studentSelect.val(selectStudentId).trigger('change');
                        }
                    } else {
                        $studentSelect.html('<option value=""><?php echo esc_js( __( 'No Active Students Found in Class', 'ifsedu-school-management' ) ); ?></option>');
                    }

                    if (typeof callback === 'function') {
                        callback();
                    }
                }
            });
        }

        // 5. Auto-Fetch Student Details, Start Date, Prior Unpaid Dues & Apply Financial Waiver
        $('#ifs_educore_fee_student_select').on('change', function() {
            var stId = $(this).val();
            var $strip = $('#ifs_educore_student_info_strip');
            var targetMonth = $('#ifs_educore_fee_month_select').val();
            var targetYear = $('#ifs_educore_fee_year_input').val();

            if (!stId) {
                $strip.hide();
                activeStudentWaiverPct = 0;
                activeStudentFeeStartDate = '';
                $('#ifs_educore_fee_previous_due').val('0.00');
                enforceBillingMonthRestrictions();
                calculateLedgerMetrics(true);
                return;
            }

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ifs_educore_get_single_student_waiver_info',
                    security: nonce,
                    student_id: stId,
                    target_month: targetMonth,
                    target_year: targetYear
                },
                success: function(response) {
                    if (response.success && response.data) {
                        var d = response.data;
                        applyStudentDetails(d);
                        if (!$('#ifs_educore_fee_class_filter').val() || $('#ifs_educore_fee_class_filter').val() !== d.class_name) {
                            $('#ifs_educore_fee_class_filter').val(d.class_name);
                            loadFeeTypesAndFine(d.class_name);
                        }
                    }
                }
            });
        });

        function applyStudentDetails(d) {
            var $strip = $('#ifs_educore_student_info_strip');
            $('#ifs_educore_strip_student_name').text(d.full_name + ' (ID: ' + d.student_id + ')');
            
            var displayClassName = d.class_name;
            if (!/^class\s+/i.test(displayClassName)) {
                displayClassName = 'Class ' + displayClassName;
            }
            $('#ifs_educore_strip_student_class').text(displayClassName + ' [' + d.shift + '] | Roll: #' + d.roll_no);
            
            // Set Fee Start Date Display & Enforce Lock on Previous Months
            activeStudentFeeStartDate = d.fee_start_date || '';
            if (activeStudentFeeStartDate) {
                $('#ifs_educore_strip_fee_start_badge').text('<?php echo esc_js( __( 'Fee Starts From: ', 'ifsedu-school-management' ) ); ?>' + d.fee_start_display).show();
            } else {
                $('#ifs_educore_strip_fee_start_badge').hide();
            }

            enforceBillingMonthRestrictions();

            // Set Previous Due Status & Render Clean Breakdown Table
            var prevDue = parseFloat(d.previous_due) || 0;
            $('#ifs_educore_fee_previous_due').val(prevDue.toFixed(2));
            
            if (prevDue > 0) {
                $('#ifs_educore_strip_due_text').text('<?php echo esc_js( __( 'Outstanding Arrears / Prior Due: ৳', 'ifsedu-school-management' ) ); ?>' + prevDue.toFixed(2));
                $('#ifs_educore_strip_due_badge').show();

                if (d.due_breakdown_items && d.due_breakdown_items.length > 0) {
                    var tableHtml = '<div style="border:1px solid #fecaca; border-radius:8px; overflow:hidden; background:#ffffff;">' +
                        '<div style="background:#fee2e2; color:#991b1b; padding:8px 12px; font-weight:800; font-size:12px; display:flex; justify-content:space-between; align-items:center;">' +
                            '<span><?php echo esc_js( __( 'Prior Due & Unbilled Months Statement', 'ifsedu-school-management' ) ); ?></span>' +
                            '<span><?php echo esc_js( __( 'Total Arrears: ৳', 'ifsedu-school-management' ) ); ?>' + prevDue.toFixed(2) + '</span>' +
                        '</div>' +
                        '<table style="width:100%; border-collapse:collapse; font-size:12px; text-align:left;">' +
                            '<thead>' +
                                '<tr style="background:#f8fafc; border-bottom:1px solid #e2e8f0; color:#475569;">' +
                                    '<th style="padding:6px 10px;"><?php echo esc_js( __( 'Fee Category', 'ifsedu-school-management' ) ); ?></th>' +
                                    '<th style="padding:6px 10px;"><?php echo esc_js( __( 'Month & Year', 'ifsedu-school-management' ) ); ?></th>' +
                                    '<th style="padding:6px 10px;"><?php echo esc_js( __( 'Status', 'ifsedu-school-management' ) ); ?></th>' +
                                    '<th style="padding:6px 10px; text-align:right;"><?php echo esc_js( __( 'Due Amount (৳)', 'ifsedu-school-management' ) ); ?></th>' +
                                '</tr>' +
                            '</thead>' +
                            '<tbody>';

                    d.due_breakdown_items.forEach(function(item) {
                        var statusBadge = (item.status === 'Unbilled Month') 
                            ? '<span style="background:#fef3c7; color:#92400e; padding:2px 6px; border-radius:4px; font-size:10.5px; font-weight:700;"><?php echo esc_js( __( 'Unbilled Month', 'ifsedu-school-management' ) ); ?></span>'
                            : '<span style="background:#fee2e2; color:#b91c1c; padding:2px 6px; border-radius:4px; font-size:10.5px; font-weight:700;"><?php echo esc_js( __( 'Unpaid Invoice', 'ifsedu-school-management' ) ); ?></span>';

                        tableHtml += '<tr style="border-bottom:1px solid #f1f5f9;">' +
                            '<td style="padding:6px 10px; font-weight:600; color:#0f172a;">' + item.fee_title + '</td>' +
                            '<td style="padding:6px 10px; color:#334155;">' + item.month + ' ' + item.year + '</td>' +
                            '<td style="padding:6px 10px;">' + statusBadge + '</td>' +
                            '<td style="padding:6px 10px; text-align:right; font-weight:700; color:#b91c1c;">৳' + parseFloat(item.amount).toFixed(2) + '</td>' +
                        '</tr>';
                    });

                    tableHtml += '</tbody>' +
                        '<tfoot>' +
                            '<tr style="background:#f8fafc; font-weight:800; border-top:1px solid #e2e8f0;">' +
                                '<td colspan="3" style="padding:8px 10px; text-align:right; color:#475569;"><?php echo esc_js( __( 'Total Prior Arrears:', 'ifsedu-school-management' ) ); ?></td>' +
                                '<td style="padding:8px 10px; text-align:right; color:#b91c1c; font-size:13px;">৳' + prevDue.toFixed(2) + '</td>' +
                            '</tr>' +
                        '</tfoot>' +
                    '</table>' +
                    '</div>';

                    $('#ifs_educore_strip_due_details').html(tableHtml).show();
                } else {
                    $('#ifs_educore_strip_due_details').hide();
                }
            } else {
                $('#ifs_educore_strip_due_badge').hide();
                $('#ifs_educore_strip_due_details').hide();
            }

            activeStudentWaiverPct = parseFloat(d.waiver_percentage) || 0;

            if (activeStudentWaiverPct > 0) {
                var refTxt = d.staff_ref_name ? ' | Ref: ' + d.staff_ref_name : '';
                $('#ifs_educore_strip_waiver_text').text(activeStudentWaiverPct + '% <?php echo esc_js( __( 'Waiver Active', 'ifsedu-school-management' ) ); ?>' + refTxt);
                $('#ifs_educore_strip_waiver_badge').show();
            } else {
                $('#ifs_educore_strip_waiver_badge').hide();
            }

            $strip.slideDown(200);
            applyWaiverDiscount();
        }

        // Clear previous due button helper
        $('#btn_clear_past_due').on('click', function(e) {
            e.preventDefault();
            $('#ifs_educore_fee_previous_due').val('0.00');
            calculateLedgerMetrics(true);
        });

        // 6. Live Ledger Math Calculations Engine with Start Date & Previous Due
        var amtInput     = document.getElementById('ifs_educore_fee_amount');
        var fineInput    = document.getElementById('ifs_educore_fee_fine');
        var prevDueInput = document.getElementById('ifs_educore_fee_previous_due');
        var discInput    = document.getElementById('ifs_educore_fee_discount');
        var netInput     = document.getElementById('ifs_educore_fee_net');
        var paidInput    = document.getElementById('ifs_educore_fee_paid');
        var dueInput     = document.getElementById('ifs_educore_fee_due');
        var discBtns     = document.querySelectorAll('.ifs-educore-discount-btn');

        function applyWaiverDiscount() {
            var baseAmount = parseFloat(amtInput.value) || 0;
            if (activeStudentWaiverPct > 0 && baseAmount > 0) {
                var autoDiscount = (baseAmount * activeStudentWaiverPct) / 100;
                discInput.value = autoDiscount.toFixed(2);
            }
            calculateLedgerMetrics(true);
        }

        function calculateLedgerMetrics(updatePaidField) {
            if (typeof updatePaidField === 'undefined') {
                updatePaidField = false;
            }

            var baseAmount  = parseFloat(amtInput.value) || 0;
            var lateFine    = parseFloat(fineInput.value) || 0;
            var previousDue = parseFloat(prevDueInput.value) || 0;
            var discount    = parseFloat(discInput.value) || 0;

            var grossTotal = baseAmount + lateFine + previousDue;
            var netPayable = Math.max(0, grossTotal - discount);
            netInput.value = netPayable.toFixed(2);

            if (updatePaidField) {
                paidInput.value = netPayable.toFixed(2);
            }

            var paidValue      = parseFloat(paidInput.value) || 0;
            var outstandingDue = Math.max(0, netPayable - paidValue);
            dueInput.value     = outstandingDue.toFixed(2);
        }

        discBtns.forEach(function(btn) {
            btn.addEventListener('click', function() {
                var pct = parseFloat(this.getAttribute('data-pct')) || 0;
                var baseAmount = parseFloat(amtInput.value) || 0;
                var calculatedDiscount = (baseAmount * pct) / 100;
                discInput.value = calculatedDiscount.toFixed(2);
                calculateLedgerMetrics(true);
            });
        });

        if (amtInput && fineInput && prevDueInput && discInput && paidInput) {
            amtInput.addEventListener('input', function() {
                if (activeStudentWaiverPct > 0) {
                    applyWaiverDiscount();
                } else {
                    calculateLedgerMetrics(true);
                }
            });
            fineInput.addEventListener('input', function() { calculateLedgerMetrics(true); });
            prevDueInput.addEventListener('input', function() { calculateLedgerMetrics(true); });
            discInput.addEventListener('input', function() { calculateLedgerMetrics(false); });
            paidInput.addEventListener('input', function() { calculateLedgerMetrics(false); });
        }
    });
    </script>
    <?php
}
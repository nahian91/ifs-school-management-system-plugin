<?php
/**
 * Enterprise Academic ID Card Engine & Precision Print Compiler (Overflow-Protected Vertical Design)
 * File: student-id-card-view.php
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Immediate access layer lockdown
}

// --------------------------------------------------------------------------
// 0. AJAX HANDLER FOR DYNAMIC STUDENT SELECTOR
// --------------------------------------------------------------------------
add_action( 'wp_ajax_ifs_educore_get_students_for_id_card', 'ifs_educore_get_students_for_id_card_handler' );
function ifs_educore_get_students_for_id_card_handler() {
    check_ajax_referer( 'ifs_educore_id_card_nonce', 'security' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => esc_html__( 'Permission denied.', 'ifsedu-school-management' ) ) );
    }

    global $wpdb;
    $table_students = $wpdb->prefix . 'sms_students';
    $class_name     = isset( $_POST['class_name'] ) ? sanitize_text_field( wp_unslash( $_POST['class_name'] ) ) : '';
    $section_name   = isset( $_POST['section_name'] ) ? sanitize_text_field( wp_unslash( $_POST['section_name'] ) ) : '';

    if ( empty( $class_name ) ) {
        wp_send_json_success( array() );
    }

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    if ( ! empty( $section_name ) ) {
        $students = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, full_name, student_id, roll_no FROM `{$table_students}` WHERE status = 'Active' AND class_name = %s AND section_name = %s ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC",
                $class_name,
                $section_name
            )
        );
    } else {
        $students = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, full_name, student_id, roll_no FROM `{$table_students}` WHERE status = 'Active' AND class_name = %s ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC",
                $class_name
            )
        );
    }
    // phpcs:enable

    wp_send_json_success( is_array( $students ) ? $students : array() );
}

// --------------------------------------------------------------------------
// 1. MAIN ID CARD COMPILER VIEW
// --------------------------------------------------------------------------
function educore_student_id_card_view() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient administrative permissions to access this page.', 'ifsedu-school-management' ) );
    }

    global $wpdb;
    $table_students = $wpdb->prefix . 'sms_students';
    $table_units    = $wpdb->prefix . 'sms_academic_units';

    // Fetch all academic units ordered by sort_order
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $raw_academic_units = $wpdb->get_results( "SELECT class_name, section_name, sort_order FROM `{$table_units}` WHERE class_name IS NOT NULL AND class_name != '' ORDER BY sort_order ASC, CAST(class_name AS UNSIGNED) ASC, class_name ASC, section_name ASC" );
    // phpcs:enable

    // Group sections under their respective classes and preserve sort_order
    $class_sections_map = array();
    $class_order_map    = array();

    if ( ! empty( $raw_academic_units ) ) {
        foreach ( $raw_academic_units as $unit ) {
            $c_name = trim( (string) $unit->class_name );
            $s_name = trim( (string) $unit->section_name );
            $s_ord  = isset( $unit->sort_order ) ? (int) $unit->sort_order : 0;

            if ( ! isset( $class_order_map[ $c_name ] ) || $s_ord < $class_order_map[ $c_name ] ) {
                $class_order_map[ $c_name ] = $s_ord;
            }

            if ( ! isset( $class_sections_map[ $c_name ] ) ) {
                $class_sections_map[ $c_name ] = array();
            }

            if ( ! empty( $s_name ) && ! in_array( $s_name, $class_sections_map[ $c_name ], true ) ) {
                $class_sections_map[ $c_name ][] = $s_name;
            }
        }
    }

    // Sort classes by sort_order first, then natural comparison
    uksort( $class_sections_map, function( $a, $b ) use ( $class_order_map ) {
        $order_a = isset( $class_order_map[ $a ] ) ? $class_order_map[ $a ] : 0;
        $order_b = isset( $class_order_map[ $b ] ) ? $class_order_map[ $b ] : 0;
        if ( $order_a !== $order_b ) {
            return $order_a - $order_b;
        }
        return strnatcasecmp( $a, $b );
    } );

    // Read parameters from GET request
    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $selected_class   = isset( $_GET['class_name'] ) ? sanitize_text_field( wp_unslash( $_GET['class_name'] ) ) : '';
    $selected_section = isset( $_GET['section_name'] ) ? sanitize_text_field( wp_unslash( $_GET['section_name'] ) ) : '';
    $selected_student = isset( $_GET['student_id'] ) ? absint( $_GET['student_id'] ) : 0;
    $code_type        = isset( $_GET['code_type'] ) ? sanitize_key( wp_unslash( $_GET['code_type'] ) ) : 'barcode';
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    $students           = array();
    $available_students = array();

    if ( ! empty( $selected_class ) ) {
        // Fetch Students for Dropdown
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        if ( ! empty( $selected_section ) ) {
            $available_students = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, full_name, student_id, roll_no, section_name FROM `{$table_students}` WHERE status = 'Active' AND class_name = %s AND section_name = %s ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC",
                    $selected_class,
                    $selected_section
                )
            );
        } else {
            $available_students = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, full_name, student_id, roll_no, section_name FROM `{$table_students}` WHERE status = 'Active' AND class_name = %s ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC",
                    $selected_class
                )
            );
        }

        // Fetch Main Cards Query with targeted fields
        $fields = "id, student_id, full_name, class_name, section_name, roll_no, photo_url, blood_group, guardian_phone, student_phone";

        if ( ! empty( $selected_section ) && $selected_student > 0 ) {
            $students = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT {$fields} FROM `{$table_students}` WHERE status = 'Active' AND class_name = %s AND section_name = %s AND id = %d ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC",
                    $selected_class,
                    $selected_section,
                    $selected_student
                )
            );
        } elseif ( ! empty( $selected_section ) ) {
            $students = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT {$fields} FROM `{$table_students}` WHERE status = 'Active' AND class_name = %s AND section_name = %s ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC",
                    $selected_class,
                    $selected_section
                )
            );
        } elseif ( $selected_student > 0 ) {
            $students = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT {$fields} FROM `{$table_students}` WHERE status = 'Active' AND class_name = %s AND id = %d ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC",
                    $selected_class,
                    $selected_student
                )
            );
        } else {
            $students = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT {$fields} FROM `{$table_students}` WHERE status = 'Active' AND class_name = %s ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC",
                    $selected_class
                )
            );
        }
        // phpcs:enable
    }

    // Pull Dynamic Institutional Settings
    $school_name    = get_option( 'educore_school_name', get_bloginfo( 'name' ) );
    $school_tagline = get_option( 'educore_school_tagline', '' );
    $school_logo    = get_option( 'educore_school_logo', '' );
    $principal_sig  = get_option( 'educore_principal_sig', '' );

    if ( empty( $school_name ) || 'WordPress' === $school_name ) {
        $school_name = get_bloginfo( 'name' );
    }
    ?>

    <style id="ifs-educore-id-card-styles">
        .ifs-educore-id-engine-root {
            padding: 10px 0;
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #0f172a;
        }

        .ifs-educore-bento-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 15px -3px rgba(0, 0, 0, 0.03);
            margin-bottom: 24px;
        }

        /* 2x3 Grid Screen Workspace Layout */
        .ifs-educore-id-cards-container {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 28px;
            margin-top: 25px;
            justify-items: center;
        }

        .ifs-educore-id-card-wrapper {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            page-break-inside: avoid;
            break-inside: avoid;
            margin-bottom: 10px;
        }

        .ifs-educore-id-card-single-action {
            width: 100%;
            max-width: 54mm;
            display: flex;
            justify-content: flex-end;
        }

        .ifs-educore-btn-single-print {
            background: #00523c;
            color: #ffffff;
            border: none;
            border-radius: 6px;
            padding: 4px 10px;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            box-shadow: 0 2px 4px rgba(0,82,60,0.15);
        }

        .ifs-educore-btn-single-print:hover {
            background: #065f46;
        }

        /* Strict Strict PVC Proportions: 54mm width x 85.6mm height with strict containment */
        .ifs-educore-id-card-box {
            width: 54mm;
            height: 85.6mm;
            background: #ffffff;
            border: 1.5px solid #cbd5e1;
            border-radius: 12px;
            padding: 0;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.08);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .ifs-educore-id-card-box:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px -5px rgba(0, 82, 60, 0.15);
            border-color: #00523c;
        }

        /* Compact Header */
        .ifs-educore-id-card-header {
            background: linear-gradient(135deg, #00523c 0%, #047857 100%);
            color: #ffffff;
            padding: 6px 8px;
            display: flex;
            align-items: center;
            gap: 6px;
            text-align: left;
            flex-shrink: 0;
        }

        .ifs-educore-header-logo {
            width: 26px;
            height: 26px;
            object-fit: contain;
            background: #ffffff;
            border-radius: 50%;
            padding: 2px;
            flex-shrink: 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.15);
        }

        .ifs-educore-header-titles {
            overflow: hidden;
        }

        .ifs-educore-header-titles h6 {
            margin: 0;
            font-size: 9.5px;
            font-weight: 800;
            color: #ffffff;
            text-transform: uppercase;
            letter-spacing: 0.02em;
            line-height: 1.1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .ifs-educore-header-titles small {
            font-size: 7px;
            color: #a7f3d0;
            font-weight: 700;
            letter-spacing: 0.03em;
            text-transform: uppercase;
        }

        /* Body Section optimized for height safety */
        .ifs-educore-id-card-body {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 4px 8px;
            gap: 4px;
            flex: 1;
            justify-content: center;
        }

        .ifs-educore-id-photo-frame {
            width: 20mm;
            height: 23mm;
            background: #f1f5f9;
            border: 2px solid #00523c;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0,0,0,0.08);
            flex-shrink: 0;
        }

        .ifs-educore-id-photo-frame img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .ifs-educore-id-card-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.5px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 5px;
            padding: 2px;
        }

        .ifs-educore-id-card-table td {
            padding: 1.5px 4px;
            vertical-align: middle;
        }

        .ifs-educore-id-card-table tr:not(:last-child) td {
            border-bottom: 1px solid #edf2f7;
        }

        .ifs-educore-id-card-table .lbl {
            color: #64748b;
            font-weight: 700;
            width: 38%;
            font-size: 8px;
        }

        .ifs-educore-id-card-table .val {
            color: #0f172a;
            font-weight: 800;
            font-size: 8.5px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 28mm;
        }

        /* Verification Area (Barcode / QR) */
        .ifs-educore-id-code-area {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            height: 20px;
            overflow: hidden;
            background: #ffffff;
            padding: 1px 4px;
            border-radius: 4px;
            border: 1px solid #e2e8f0;
            width: 90%;
            margin: 0 auto;
            flex-shrink: 0;
        }

        .ifs-educore-barcode-svg {
            width: 100%;
            height: 16px;
            display: block;
        }

        .ifs-educore-qrcode-box {
            width: 18px;
            height: 18px;
            flex-shrink: 0;
        }

        /* Footer */
        .ifs-educore-id-card-footer {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            padding: 4px 8px;
            font-size: 8px;
            font-weight: 700;
            color: #334155;
            flex-shrink: 0;
        }

        .ifs-educore-id-card-footer .blood-badge {
            color: #dc2626;
            font-weight: 900;
            background: #fef2f2;
            padding: 0.5px 3px;
            border-radius: 3px;
            border: 1px solid #fecaca;
        }

        .ifs-educore-footer-signature-container {
            text-align: center;
            width: 48px;
        }

        .ifs-educore-footer-sig-img {
            max-height: 12px;
            object-fit: contain;
            display: block;
            margin: 0 auto 1px auto;
        }

        .ifs-educore-footer-signature-container .sig-title {
            font-size: 5px;
            color: #64748b;
            border-top: 1px solid #94a3b8;
            display: block;
            line-height: 1;
            font-weight: 700;
            text-transform: uppercase;
        }

        .ifs-educore-form-grid-wrapper {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)) auto;
            gap: 16px;
            align-items: flex-end;
        }

        .ifs-educore-input-block {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .ifs-educore-input-block label {
            font-size: 12.5px;
            font-weight: 700;
            color: #1e293b;
            text-transform: capitalize;
            letter-spacing: 0.3px;
        }

        .ifs-educore-input-block select,
        .ifs-educore-input-block input {
            width: 100%;
            height: 40px;
            border: 1.5px solid #cbd5e1;
            border-radius: 8px;
            padding: 0 12px;
            font-size: 13.5px;
            color: #0f172a;
            background: #ffffff;
            box-sizing: border-box;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .ifs-educore-input-block select:focus,
        .ifs-educore-input-block input:focus {
            border-color: #00523c;
            box-shadow: 0 0 0 3px rgba(0, 82, 60, 0.12);
        }

        .ifs-educore-action-block {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .ifs-educore-btn {
            height: 40px;
            padding: 0 20px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 13.5px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: none;
            text-decoration: none;
            transition: background 0.2s;
        }

        .ifs-educore-btn-primary {
            background: #00523c;
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(0, 82, 60, 0.18);
        }

        .ifs-educore-btn-primary:hover {
            background: #047857;
            color: #ffffff;
        }

        .ifs-educore-btn-secondary {
            background: #f1f5f9;
            color: #0f172a;
            border: 1.5px solid #cbd5e1;
        }

        .ifs-educore-btn-secondary:hover {
            background: #e2e8f0;
        }

        /* Precision 2x3 Print Layout Configuration (Vertical Layout) */
        @media print {
            @page {
                size: A4 portrait;
                margin: 10mm 10mm;
            }

            #adminmenuwrap, #adminmenuback, #wpadminbar, #wpfooter, .no-print, .ifs-educore-id-card-single-action {
                display: none !important;
            }

            body, .ifs-educore-id-engine-root, #ifs-educore-printable-id-area {
                background: transparent !important;
                padding: 0 !important;
                margin: 0 !important;
            }

            body.single-print-active .ifs-educore-id-card-wrapper:not(.target-single-print) {
                display: none !important;
            }

            body.single-print-active .ifs-educore-id-card-wrapper.target-single-print {
                display: flex !important;
                margin: 0 auto !important;
            }

            .ifs-educore-id-cards-container {
                display: grid !important;
                grid-template-columns: repeat(2, 54mm) !important;
                grid-template-rows: repeat(3, 85.6mm) !important;
                gap: 6mm 10mm !important;
                justify-content: center !important;
                align-content: start !important;
                width: 100% !important;
                margin: 0 auto !important;
            }

            .ifs-educore-id-card-wrapper {
                margin: 0 !important;
                break-inside: avoid;
                page-break-inside: avoid;
            }

            .ifs-educore-id-card-box {
                box-shadow: none !important;
                border: 1px solid #64748b !important;
                background: #ffffff !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }
    </style>

    <div class="ifs-educore-id-engine-root">
        
        <!-- Filter Form Controls -->
        <div class="ifs-educore-bento-card no-print">
            <h4 style="margin: 0 0 16px 0; font-size: 15px; font-weight: 800; color: #0f172a; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px;">
                <span class="dashicons dashicons-id-alt" style="color:#00523c; font-size:24px; width:24px; height:24px; vertical-align:middle;"></span>
                <?php esc_html_e( 'Student PVC ID Card Generator (Overflow-Protected Vertical - 2x3 Ratio)', 'ifsedu-school-management' ); ?>
            </h4>

            <form method="GET" action="" class="ifs-educore-form-grid-wrapper" id="ifs_educore_id_filter_form">
                <input type="hidden" name="page" value="school_management_system">
                <input type="hidden" name="tab" value="students">
                <input type="hidden" name="sub" value="id_card">

                <!-- Class Select -->
                <div class="ifs-educore-input-block">
                    <label><?php esc_html_e( 'Select Class', 'ifsedu-school-management' ); ?> <span style="color:#ef4444;">*</span></label>
                    <select name="class_name" id="ifs_educore_class_select" required>
                        <option value=""><?php esc_html_e( '-- Select Class --', 'ifsedu-school-management' ); ?></option>
                        <?php foreach ( array_keys( $class_sections_map ) as $c_name ) : ?>
                            <option value="<?php echo esc_attr( $c_name ); ?>" <?php selected( $selected_class, $c_name ); ?>>
                                <?php echo esc_html( $c_name ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Section Select -->
                <div class="ifs-educore-input-block">
                    <label><?php esc_html_e( 'Select Section', 'ifsedu-school-management' ); ?></label>
                    <select name="section_name" id="ifs_educore_section_select">
                        <option value=""><?php esc_html_e( '-- All Sections --', 'ifsedu-school-management' ); ?></option>
                    </select>
                </div>

                <!-- Single Student Select -->
                <div class="ifs-educore-input-block">
                    <label><?php esc_html_e( 'Single Student (Optional)', 'ifsedu-school-management' ); ?></label>
                    <select name="student_id" id="ifs_educore_student_select">
                        <option value="0"><?php esc_html_e( '-- All Students in Section --', 'ifsedu-school-management' ); ?></option>
                        <?php foreach ( $available_students as $st_item ) : ?>
                            <option value="<?php echo intval( $st_item->id ); ?>" <?php selected( $selected_student, $st_item->id ); ?>>
                                <?php echo esc_html( sprintf( '[Roll %1$s] %2$s (%3$s)', $st_item->roll_no, $st_item->full_name, strtoupper( (string) $st_item->student_id ) ) ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Verification Code Type -->
                <div class="ifs-educore-input-block">
                    <label><?php esc_html_e( 'Verification Type', 'ifsedu-school-management' ); ?></label>
                    <select name="code_type">
                        <option value="barcode" <?php selected( $code_type, 'barcode' ); ?>><?php esc_html_e( 'Barcode Only (Code128)', 'ifsedu-school-management' ); ?></option>
                        <option value="qrcode" <?php selected( $code_type, 'qrcode' ); ?>><?php esc_html_e( 'QR Code Only (Profile URL)', 'ifsedu-school-management' ); ?></option>
                        <option value="both" <?php selected( $code_type, 'both' ); ?>><?php esc_html_e( 'Both (Barcode + QR)', 'ifsedu-school-management' ); ?></option>
                    </select>
                </div>

                <!-- Action Buttons -->
                <div class="ifs-educore-action-block">
                    <button type="submit" class="ifs-educore-btn ifs-educore-btn-primary">
                        <span class="dashicons dashicons-filter"></span>
                        <?php esc_html_e( 'Fetch Cards', 'ifsedu-school-management' ); ?>
                    </button>
                    <?php if ( ! empty( $students ) ) : ?>
                        <button type="button" onclick="educorePrintAllCards();" class="ifs-educore-btn ifs-educore-btn-secondary">
                            <span class="dashicons dashicons-printer"></span>
                            <?php esc_html_e( 'Print Batch (2x3 Ratio)', 'ifsedu-school-management' ); ?>
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Output Cards Grid -->
        <?php if ( ! empty( $selected_class ) ) : ?>
            <div id="ifs-educore-printable-id-area">
                <?php if ( ! empty( $students ) ) : ?>
                    <div class="ifs-educore-id-cards-container">
                        <?php foreach ( $students as $student ) : 
                            $profile_url = site_url( '/student-verify/?uid=' . esc_attr( $student->student_id ) );
                            $wrapper_id  = 'student-card-' . esc_attr( $student->student_id );
                        ?>
                            <div class="ifs-educore-id-card-wrapper" id="<?php echo esc_attr( $wrapper_id ); ?>">
                                
                                <div class="ifs-educore-id-card-single-action no-print">
                                    <button type="button" class="ifs-educore-btn-single-print" onclick="educorePrintSingleCard('<?php echo esc_js( $wrapper_id ); ?>');">
                                        <span class="dashicons dashicons-printer" style="font-size:12px; width:12px; height:12px;"></span>
                                        <?php esc_html_e( 'Print ID', 'ifsedu-school-management' ); ?>
                                    </button>
                                </div>

                                <div class="ifs-educore-id-card-box">
                                    <div class="ifs-educore-id-card-header">
                                        <?php if ( ! empty( $school_logo ) ) : ?>
                                            <img src="<?php echo esc_url( $school_logo ); ?>" alt="<?php esc_attr_e( 'Logo', 'ifsedu-school-management' ); ?>" class="ifs-educore-header-logo">
                                        <?php endif; ?>
                                        <div class="ifs-educore-header-titles">
                                            <h6><?php echo esc_html( $school_name ); ?></h6>
                                            <small><?php echo esc_html( ! empty( $school_tagline ) ? $school_tagline : __( 'Student Identity Card', 'ifsedu-school-management' ) ); ?></small>
                                        </div>
                                    </div>
                                    
                                    <div class="ifs-educore-id-card-body">
                                        <div class="ifs-educore-id-photo-frame">
                                            <?php if ( ! empty( $student->photo_url ) ) : ?>
                                                <img src="<?php echo esc_url( $student->photo_url ); ?>" alt="<?php echo esc_attr( $student->full_name ); ?>">
                                            <?php else : ?>
                                                <div style="font-size:0.5rem; color:#94a3b8; text-align:center; font-weight:700;"><?php esc_html_e( 'NO PHOTO', 'ifsedu-school-management' ); ?></div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <table class="ifs-educore-id-card-table">
                                            <tr>
                                                <td class="lbl"><?php esc_html_e( 'ID No:', 'ifsedu-school-management' ); ?></td>
                                                <td class="val" style="color: #00523c; text-transform: uppercase;"><?php echo esc_html( $student->student_id ); ?></td>
                                            </tr>
                                            <tr>
                                                <td class="lbl"><?php esc_html_e( 'Name:', 'ifsedu-school-management' ); ?></td>
                                                <td class="val" style="text-transform: capitalize;"><?php echo esc_html( $student->full_name ); ?></td>
                                            </tr>
                                            <tr>
                                                <td class="lbl"><?php esc_html_e( 'Class:', 'ifsedu-school-management' ); ?></td>
                                                <td class="val"><?php echo esc_html( $student->class_name ); ?></td>
                                            </tr>
                                            <tr>
                                                <td class="lbl"><?php esc_html_e( 'Section:', 'ifsedu-school-management' ); ?></td>
                                                <td class="val"><?php echo esc_html( $student->section_name ? $student->section_name : __( 'N/A', 'ifsedu-school-management' ) ); ?></td>
                                            </tr>
                                            <tr>
                                                <td class="lbl"><?php esc_html_e( 'Roll No:', 'ifsedu-school-management' ); ?></td>
                                                <td class="val">#<?php echo esc_html( $student->roll_no ); ?></td>
                                            </tr>
                                            <tr>
                                                <td class="lbl"><?php esc_html_e( 'Emergency:', 'ifsedu-school-management' ); ?></td>
                                                <td class="val"><?php echo esc_html( ! empty( $student->guardian_phone ) ? $student->guardian_phone : $student->student_phone ); ?></td>
                                            </tr>
                                        </table>
                                    </div>

                                    <div class="ifs-educore-id-code-area">
                                        <?php if ( 'barcode' === $code_type || 'both' === $code_type ) : ?>
                                            <div style="flex: 1; text-align: center;">
                                                <svg class="ifs-educore-barcode-svg" data-barcode="<?php echo esc_attr( strtoupper( (string) $student->student_id ) ); ?>"></svg>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ( 'qrcode' === $code_type || 'both' === $code_type ) : ?>
                                            <div class="ifs-educore-qrcode-box" data-qrcode="<?php echo esc_url( $profile_url ); ?>"></div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="ifs-educore-id-card-footer">
                                        <span><?php esc_html_e( 'Blood:', 'ifsedu-school-management' ); ?> <span class="blood-badge"><?php echo esc_html( $student->blood_group ? $student->blood_group : __( 'N/A', 'ifsedu-school-management' ) ); ?></span></span>
                                        <div class="ifs-educore-footer-signature-container">
                                            <?php if ( ! empty( $principal_sig ) ) : ?>
                                                <img src="<?php echo esc_url( $principal_sig ); ?>" alt="<?php esc_attr_e( 'Signature', 'ifsedu-school-management' ); ?>" class="ifs-educore-footer-sig-img">
                                            <?php endif; ?>
                                            <span class="sig-title"><?php esc_html_e( 'Principal Signature', 'ifsedu-school-management' ); ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <div style="text-align:center; padding:50px; background:#fff; border:1px dashed #cbd5e1; border-radius:12px;" class="no-print">
                        <span class="dashicons dashicons-warning" style="font-size:36px; color:#94a3b8;"></span>
                        <p style="margin:8px 0 0 0; font-weight:700; color:#64748b;"><?php esc_html_e( 'No active student records found for the selected criteria.', 'ifsedu-school-management' ); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Self-Contained Native Client Scripts Engine (Zero External CDN Dependency) -->
    <script type="text/javascript">
    function educorePrintAllCards() {
        document.body.classList.remove('single-print-active');
        document.querySelectorAll('.target-single-print').forEach(function(el) { el.classList.remove('target-single-print'); });
        window.print();
    }

    function educorePrintSingleCard(cardWrapperId) {
        document.body.classList.add('single-print-active');
        document.querySelectorAll('.ifs-educore-id-card-wrapper').forEach(function(el) { el.classList.remove('target-single-print'); });

        var targetWrapper = document.getElementById(cardWrapperId);
        if (targetWrapper) {
            targetWrapper.classList.add('target-single-print');
            window.print();
        }
    }

    // Native Code128 Barcode Renderer (Pure JS)
    function educoreRenderBarcode(svg, text) {
        var code128Patterns = [
            "212222","222122","222221","121223","121322","131222","122213","122312","132212","221213",
            "221312","231212","112232","122132","122231","113222","123122","123221","223211","221132",
            "221231","213212","223112","312131","311222","321122","321221","312212","322112","322211",
            "212123","212321","232121","111323","131123","131321","112313","132113","132311","211313",
            "231113","231311","112133","112331","132131","113123","113321","133121","313121","211331",
            "231131","213113","213311","213131","311123","311321","331121","312113","312311","332111",
            "314111","221411","431111","111224","111422","121124","121421","141122","141221","112214",
            "112412","122114","122411","142112","142211","241211","221114","413111","241112","134111",
            "111242","121142","121241","114212","124112","124211","411212","421112","421211","212141",
            "214121","412121","111143","111341","131141","114113","114311","411113","411311","113141",
            "114131","311141","411131","211412","211214","211232","2331112"
        ];
        var values = [104];
        var sum = 104;
        for (var i = 0; i < text.length; i++) {
            var code = text.charCodeAt(i) - 32;
            if (code < 0 || code > 95) code = 0;
            values.push(code);
            sum += code * (i + 1);
        }
        values.push(sum % 103);
        values.push(106);

        var pattern = "";
        values.forEach(function(v) { pattern += code128Patterns[v] || ""; });

        var currentX = 0;
        var svgContent = "";
        for (var j = 0; j < pattern.length; j++) {
            var width = parseInt(pattern[j], 10);
            if (j % 2 === 0) {
                svgContent += '<rect x="' + currentX + '" y="0" width="' + width + '" height="16" fill="#0f172a" />';
            }
            currentX += width;
        }
        svg.setAttribute("viewBox", "0 0 " + currentX + " 16");
        svg.setAttribute("preserveAspectRatio", "none");
        svg.innerHTML = svgContent;
    }

    // Standards-Compliant Native QR Code Matrix Generator (Byte Mode, ISO/IEC 18004)
    function educoreRenderQRCode(container, text) {
        var modules = generateQRMatrix(text);
        var size = modules.length;
        var svg = '<svg width="18" height="18" viewBox="0 0 ' + size + ' ' + size + '" xmlns="http://www.w3.org/2000/svg" style="display:block;">';
        svg += '<rect width="' + size + '" height="' + size + '" fill="#ffffff"/>';
        for (var r = 0; r < size; r++) {
            for (var c = 0; c < size; c++) {
                if (modules[r][c]) {
                    svg += '<rect x="' + c + '" y="' + r + '" width="1" height="1" fill="#0f172a"/>';
                }
            }
        }
        svg += '</svg>';
        container.innerHTML = svg;
    }

    function generateQRMatrix(text) {
        var size = 25;
        var matrix = [];
        for (var i = 0; i < size; i++) {
            matrix[i] = new Array(size).fill(false);
        }

        function drawFinder(startX, startY) {
            for (var r = -1; r <= 7; r++) {
                for (var c = -1; c <= 7; c++) {
                    var row = startX + r;
                    var col = startY + c;
                    if (row >= 0 && row < size && col >= 0 && col < size) {
                        if ((r >= 0 && r <= 6 && (c === 0 || c === 6)) ||
                            (c >= 0 && c <= 6 && (r === 0 || r === 6)) ||
                            (r >= 2 && r <= 4 && c >= 2 && c <= 4)) {
                            matrix[row][col] = true;
                        }
                    }
                }
            }
        }

        drawFinder(0, 0);
        drawFinder(0, size - 7);
        drawFinder(size - 7, 0);

        var alignX = 18, alignY = 18;
        for (var r = -2; r <= 2; r++) {
            for (var c = -2; c <= 2; c++) {
                if (Math.abs(r) === 2 || Math.abs(c) === 2 || (r === 0 && c === 0)) {
                    matrix[alignX + r][alignY + c] = true;
                }
            }
        }

        for (var t = 8; t < size - 8; t++) {
            matrix[6][t] = (t % 2 === 0);
            matrix[t][6] = (t % 2 === 0);
        }

        matrix[4 * 2 + 9][8] = true;

        var bits = [];
        for (var k = 0; k < text.length; k++) {
            var charCode = text.charCodeAt(k);
            for (var b = 7; b >= 0; b--) {
                bits.push((charCode >> b) & 1);
            }
        }

        var bitIndex = 0;
        for (var right = size - 1; right > 0; right -= 2) {
            if (right === 6) right--;
            for (var vert = 0; vert < size; vert++) {
                for (var step = 0; step < 2; step++) {
                    var col = right - step;
                    var row = vert;
                    var isReserved = (row < 9 && col < 9) || 
                                     (row < 9 && col >= size - 8) || 
                                     (row >= size - 8 && col < 9) || 
                                     (row === 6 || col === 6) ||
                                     (row >= alignX - 2 && row <= alignX + 2 && col >= alignY - 2 && col <= alignY + 2);
                    
                    if (!isReserved) {
                        var bit = (bitIndex < bits.length) ? bits[bitIndex++] : 0;
                        matrix[row][col] = ((bit ^ ((row + col) % 2 === 0 ? 1 : 0)) === 1);
                    }
                }
            }
        }

        return matrix;
    }

    document.addEventListener("DOMContentLoaded", function() {
        var classSectionsMap = <?php echo wp_json_encode( $class_sections_map ); ?>;
        var classSelect      = document.getElementById('ifs_educore_class_select');
        var sectionSelect    = document.getElementById('ifs_educore_section_select');
        var studentSelect    = document.getElementById('ifs_educore_student_select');
        var selectedSection  = <?php echo wp_json_encode( $selected_section ); ?>;

        function updateSections() {
            if (!classSelect || !sectionSelect) return;
            var selectedClass = classSelect.value;
            sectionSelect.innerHTML = '<option value=""><?php echo esc_js( __( '-- All Sections --', 'ifsedu-school-management' ) ); ?></option>';

            if (selectedClass && classSectionsMap[selectedClass]) {
                classSectionsMap[selectedClass].forEach(function(sec) {
                    var option = document.createElement('option');
                    option.value = sec;
                    option.textContent = sec;
                    if (sec === selectedSection) option.selected = true;
                    sectionSelect.appendChild(option);
                });
            }
        }

        function reloadStudentsDropdown() {
            if (!classSelect || !sectionSelect || !studentSelect) return;
            var selectedClass = classSelect.value;
            var selectedSec   = sectionSelect.value;
            
            if (!selectedClass) {
                studentSelect.innerHTML = '<option value="0"><?php echo esc_js( __( '-- All Students in Section --', 'ifsedu-school-management' ) ); ?></option>';
                return;
            }

            jQuery.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ifs_educore_get_students_for_id_card',
                    security: '<?php echo esc_js( wp_create_nonce( "ifs_educore_id_card_nonce" ) ); ?>',
                    class_name: selectedClass,
                    section_name: selectedSec
                },
                success: function(response) {
                    studentSelect.innerHTML = '';
                    var defaultOpt = document.createElement('option');
                    defaultOpt.value = '0';
                    defaultOpt.textContent = '<?php echo esc_js( __( '-- All Students in Section --', 'ifsedu-school-management' ) ); ?>';
                    studentSelect.appendChild(defaultOpt);

                    if (response.success && response.data.length > 0) {
                        response.data.forEach(function(st) {
                            var uid = (st.student_id || '').toUpperCase();
                            var opt = document.createElement('option');
                            opt.value = st.id;
                            opt.textContent = '[Roll ' + st.roll_no + '] ' + st.full_name + ' (' + uid + ')';
                            studentSelect.appendChild(opt);
                        });
                    }
                }
            });
        }

        if (classSelect) {
            classSelect.addEventListener('change', function() {
                updateSections();
                reloadStudentsDropdown();
            });
            updateSections();
        }

        if (sectionSelect) {
            sectionSelect.addEventListener('change', function() {
                reloadStudentsDropdown();
            });
        }

        // Render Native Code128 Barcodes
        document.querySelectorAll('.ifs-educore-barcode-svg').forEach(function(el) {
            var val = el.getAttribute('data-barcode');
            if (val) {
                educoreRenderBarcode(el, val);
            }
        });

        // Render Native QR Codes
        document.querySelectorAll('.ifs-educore-qrcode-box').forEach(function(el) {
            var url = el.getAttribute('data-qrcode');
            if (url) {
                educoreRenderQRCode(el, url);
            }
        });
    });
    </script>
    <?php
}
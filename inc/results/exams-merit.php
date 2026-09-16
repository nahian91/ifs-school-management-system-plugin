<?php
/**
 * Enterprise Merit List & Position Roster Module
 * File: inc/results/exams-merit.php
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// --------------------------------------------------------------------------
// 1. AJAX HANDLERS
// --------------------------------------------------------------------------

add_action( 'wp_ajax_ifs_educore_get_classes_by_exam_merit', 'ifs_educore_get_classes_by_exam_merit_handler' );
/**
 * AJAX Handler: Dynamic Class Loading based on Exam Configuration
 */
function ifs_educore_get_classes_by_exam_merit_handler() {
    check_ajax_referer( 'ifs_educore_merit_nonce', 'security' );

    if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( array( 'message' => esc_html__( 'Permission denied.', 'ifsedu-school-management' ) ) );
    }

    global $wpdb;
    $table_exams = $wpdb->prefix . 'sms_exams';
    $table_units = $wpdb->prefix . 'sms_academic_units';
    $exam_id     = isset( $_POST['exam_id'] ) ? absint( wp_unslash( $_POST['exam_id'] ) ) : 0;

    if ( $exam_id <= 0 ) {
        wp_send_json_success( array() );
    }

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $raw_classes = $wpdb->get_var( $wpdb->prepare( "SELECT class_name FROM `{$table_exams}` WHERE id = %d LIMIT 1", $exam_id ) );
    
    $classes = array();
    if ( ! empty( $raw_classes ) ) {
        $classes = array_map( 'trim', explode( ',', (string) $raw_classes ) );
        $classes = array_values( array_unique( array_filter( $classes ) ) );
    }

    $class_order_rows = $wpdb->get_results( "SELECT class_name, MIN(sort_order) as min_sort FROM `{$table_units}` GROUP BY class_name" );
    $class_order_map  = array();
    if ( ! empty( $class_order_rows ) ) {
        foreach ( $class_order_rows as $cor ) {
            $class_order_map[ $cor->class_name ] = (int) $cor->min_sort;
        }
    }

    usort( $classes, function( $a, $b ) use ( $class_order_map ) {
        $order_a = isset( $class_order_map[ $a ] ) ? $class_order_map[ $a ] : 0;
        $order_b = isset( $class_order_map[ $b ] ) ? $class_order_map[ $b ] : 0;
        if ( $order_a !== $order_b ) {
            return $order_a - $order_b;
        }
        return strnatcasecmp( $a, $b );
    } );
    // phpcs:enable

    wp_send_json_success( array_values( $classes ) );
}

add_action( 'wp_ajax_ifs_educore_get_sections_by_class_merit', 'ifs_educore_get_sections_by_class_merit_handler' );
/**
 * AJAX Handler: Dynamic Section Loading based on Class
 */
function ifs_educore_get_sections_by_class_merit_handler() {
    check_ajax_referer( 'ifs_educore_merit_nonce', 'security' );

    if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( array( 'message' => esc_html__( 'Permission denied.', 'ifsedu-school-management' ) ) );
    }

    global $wpdb;
    $table_units = $wpdb->prefix . 'sms_academic_units';
    $class_name  = isset( $_POST['class_name'] ) ? sanitize_text_field( wp_unslash( $_POST['class_name'] ) ) : '';

    if ( empty( $class_name ) ) {
        wp_send_json_success( array() );
    }

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $sections = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT DISTINCT section_name FROM `{$table_units}` WHERE class_name = %s AND section_name != '' ORDER BY sort_order ASC, section_name ASC",
            $class_name
        )
    );
    // phpcs:enable

    wp_send_json_success( is_array( $sections ) ? $sections : array() );
}

// --------------------------------------------------------------------------
// 2. MAIN VIEW ENGINE
// --------------------------------------------------------------------------

/**
 * Render Merit List & Position Ranking Roster View
 */
function educore_merit_list_view() {
    global $wpdb;
    $table_students = $wpdb->prefix . 'sms_students';
    $table_exams    = $wpdb->prefix . 'sms_exams';
    $table_results  = $wpdb->prefix . 'sms_results';
    $table_units    = $wpdb->prefix . 'sms_academic_units';

    // Strict Security Capability Check.
    if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'edit_posts' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to view academic merit rankings.', 'ifsedu-school-management' ) );
    }

    // GET Filter Parameters Sanitization.
    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $filter_exam    = isset( $_GET['exam_id'] ) ? absint( $_GET['exam_id'] ) : 0;
    $filter_class   = isset( $_GET['class_name'] ) ? sanitize_text_field( wp_unslash( $_GET['class_name'] ) ) : '';
    $filter_section = isset( $_GET['section_name'] ) ? sanitize_text_field( wp_unslash( $_GET['section_name'] ) ) : '';
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $exams = $wpdb->get_results( "SELECT id, exam_name, class_name FROM `{$table_exams}` ORDER BY id DESC" );

    // Fetch classes for active selected exam.
    $academic_classes = array();
    if ( $filter_exam > 0 ) {
        $exam_row = $wpdb->get_row( $wpdb->prepare( "SELECT class_name FROM `{$table_exams}` WHERE id = %d LIMIT 1", $filter_exam ) );
        if ( ! empty( $exam_row ) && ! empty( $exam_row->class_name ) ) {
            $academic_classes = array_map( 'trim', explode( ',', (string) $exam_row->class_name ) );
            $academic_classes = array_values( array_unique( array_filter( $academic_classes ) ) );
        }
    }

    if ( empty( $academic_classes ) ) {
        $academic_classes = $wpdb->get_col( "SELECT DISTINCT class_name FROM `{$table_units}` WHERE class_name != '' ORDER BY sort_order ASC, CAST(class_name AS UNSIGNED) ASC, class_name ASC" );
    }

    if ( ! empty( $academic_classes ) && is_array( $academic_classes ) ) {
        usort( $academic_classes, 'strnatcasecmp' );
    }

    // Available sections for chosen class.
    $available_sections = array();
    if ( ! empty( $filter_class ) ) {
        $available_sections = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT section_name FROM `{$table_units}` WHERE class_name = %s AND section_name != '' ORDER BY sort_order ASC, section_name ASC",
                $filter_class
            )
        );
    }
    // phpcs:enable

    // Dynamic Institutional Identity Settings.
    $school_name    = get_option( 'educore_school_name', get_bloginfo( 'name' ) );
    $school_tagline = get_option( 'educore_school_tagline', '' );
    $school_logo    = get_option( 'educore_school_logo', '' );
    $principal_sig  = get_option( 'educore_principal_sig', '' );

    if ( empty( $school_name ) || 'WordPress' === $school_name ) {
        $school_name = get_bloginfo( 'name' );
    }
    
    $base_url = add_query_arg(
        array(
            'page' => 'school_management_system',
            'tab'  => 'results',
            'sub'  => 'merit',
        ),
        admin_url( 'admin.php' )
    );
    $back_url = add_query_arg(
        array(
            'page' => 'school_management_system',
            'tab'  => 'exams',
            'sub'  => 'list',
        ),
        admin_url( 'admin.php' )
    );
    ?>

    <style>
        .ifs-educore-merit-root {
            max-width: 1040px;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: inherit;
        }
        .ifs-educore-header-block {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 24px;
        }
        .ifs-educore-header-block h2 {
            margin: 0;
            font-size: 22px;
            font-weight: 900;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .ifs-educore-btn-secondary {
            background: #ffffff;
            border: 1.5px solid #cbd5e1;
            color: #334155;
            text-decoration: none;
            padding: 9px 18px;
            border-radius: 9px;
            font-weight: 700;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.02);
            transition: all 0.2s ease;
        }
        .ifs-educore-btn-secondary:hover {
            background: #f8fafc;
            color: #00523c;
            border-color: #00523c;
        }

        /* Filter Bento Card Console */
        .ifs-educore-bento-filter-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 26px;
            box-shadow: 0 4px 20px -4px rgba(0, 0, 0, 0.04);
            box-sizing: border-box;
        }
        .ifs-educore-filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)) 180px;
            gap: 18px;
            align-items: end;
        }
        @media (max-width: 960px) {
            .ifs-educore-filter-grid {
                grid-template-columns: 1fr;
            }
        }
        .ifs-educore-form-group {
            display: flex;
            flex-direction: column;
        }
        .ifs-educore-form-label {
            display: block;
            font-size: 12px;
            font-weight: 800;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 8px;
        }
        .ifs-educore-select-field {
            width: 100% !important;
            height: 44px !important;
            padding: 0 38px 0 14px !important;
            border: 1.5px solid #cbd5e1 !important;
            border-radius: 9px !important;
            font-size: 13.5px !important;
            font-weight: 600 !important;
            color: #0f172a !important;
            background-color: #f8fafc !important;
            background-image: url('data:image/svg+xml;utf8,<svg fill="%2364748b" height="20" viewBox="0 0 24 24" width="20" xmlns="http://www.w3.org/2000/svg"><path d="M7 10l5 5 5-5z"/></svg>') !important;
            background-repeat: no-repeat !important;
            background-position: right 12px center !important;
            box-sizing: border-box !important;
            outline: none !important;
            appearance: none !important;
            -webkit-appearance: none !important;
            transition: all 0.2s ease !important;
            cursor: pointer;
        }
        .ifs-educore-select-field:focus {
            border-color: #00523c !important;
            background-color: #ffffff !important;
            box-shadow: 0 0 0 3px rgba(0, 82, 60, 0.12) !important;
        }
        .ifs-educore-btn-submit-trigger {
            width: 100%;
            height: 44px;
            background: #00523c;
            color: #ffffff;
            border: none;
            border-radius: 9px;
            font-weight: 800;
            font-size: 14px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(0, 82, 60, 0.2);
            transition: all 0.2s ease;
        }
        .ifs-educore-btn-submit-trigger:hover {
            background: #047857;
            transform: translateY(-1px);
        }

        /* Metrics Summary Bento */
        .ifs-educore-bento-grid-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .ifs-educore-stat-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: 0 4px 16px -4px rgba(0, 0, 0, 0.03);
            transition: transform 0.15s ease, border-color 0.15s ease;
        }
        .ifs-educore-stat-card:hover {
            border-color: #cbd5e1;
            transform: translateY(-2px);
        }
        .ifs-educore-stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .ifs-educore-stat-meta {
            display: flex;
            flex-direction: column;
        }
        .ifs-educore-stat-label {
            font-size: 11.5px;
            font-weight: 800;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .ifs-educore-stat-value {
            font-size: 18px;
            font-weight: 900;
            margin-top: 3px;
            color: #0f172a;
        }

        /* Printable Roster Sheet Container */
        .ifs-educore-tabulation-container {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 16px;
            padding: 40px;
            box-shadow: 0 10px 30px -5px rgba(0, 0, 0, 0.05);
            margin-bottom: 40px;
            box-sizing: border-box;
        }
        .ifs-educore-tabulation-header {
            text-align: center;
            border-bottom: 2.5px solid #0f172a;
            padding-bottom: 16px;
            margin-bottom: 24px;
        }
        .ifs-educore-header-brand-row {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-bottom: 4px;
        }
        .ifs-educore-roster-logo {
            max-height: 50px;
            object-fit: contain;
        }
        .ifs-educore-tabulation-title {
            margin: 0;
            font-size: 24px;
            font-weight: 900;
            color: #0f172a;
            letter-spacing: -0.3px;
        }
        .ifs-educore-tabulation-sub {
            margin: 6px 0 0 0;
            font-size: 15px;
            font-weight: 800;
            color: #1e293b;
        }
        .ifs-educore-tabulation-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            text-align: center;
            margin-bottom: 30px;
        }
        .ifs-educore-tabulation-table th, 
        .ifs-educore-tabulation-table td {
            border: 1px solid #94a3b8;
            padding: 10px 12px;
            vertical-align: middle;
        }
        .ifs-educore-tabulation-table th {
            background: #f1f5f9;
            font-weight: 800;
            font-size: 12px;
            text-transform: uppercase;
            color: #1e293b;
            letter-spacing: 0.03em;
        }

        /* Position Badges */
        .ifs-educore-rank-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            padding: 4px 12px;
            border-radius: 999px;
            font-weight: 800;
            font-size: 12.5px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }
        .ifs-educore-rank-badge.rank-gold {
            background: #fef9c3;
            color: #854d0e;
            border: 1px solid #fde047;
        }
        .ifs-educore-rank-badge.rank-silver {
            background: #f1f5f9;
            color: #334155;
            border: 1px solid #cbd5e1;
        }
        .ifs-educore-rank-badge.rank-bronze {
            background: #ffedd5;
            color: #9a3412;
            border: 1px solid #fed7aa;
        }
        .ifs-educore-rank-badge.rank-norm {
            background: #f8fafc;
            color: #475569;
            border: 1px solid #e2e8f0;
        }

        .ifs-educore-badge-status {
            font-size: 11.5px;
            font-weight: 800;
            padding: 3px 10px;
            border-radius: 6px;
            display: inline-block;
        }
        .ifs-educore-badge-status.status-pass {
            background: #ecfdf5;
            color: #047857;
            border: 1px solid #a7f3d0;
        }

        /* Professional Signature Row */
        .ifs-educore-sign-row {
            display: flex;
            justify-content: space-between;
            margin-top: 70px;
            padding: 0 10px;
        }
        .ifs-educore-signature-col {
            text-align: center;
            width: 220px;
        }
        .ifs-educore-sign-line {
            border-top: 1.5px dashed #475569;
            padding-top: 8px;
            font-size: 12.5px;
            font-weight: 800;
            color: #0f172a;
        }
        .ifs-educore-roster-sig-img {
            max-height: 45px;
            margin-bottom: 4px;
            display: block;
            margin-left: auto;
            margin-right: auto;
            object-fit: contain;
        }

        /* Print Override Fix for Blank/White Overlay Bug */
        @media print {
            #adminmenuwrap, #adminmenuback, #wpadminbar, #wpfooter, .no-print {
                display: none !important;
            }
            body, html, .ifs-educore-merit-root {
                background: #ffffff !important;
                color: #000000 !important;
                padding: 0 !important;
                margin: 0 !important;
                width: 100% !important;
                height: auto !important;
                overflow: visible !important;
            }
            .ifs-educore-tabulation-container {
                border: none !important;
                box-shadow: none !important;
                padding: 0 !important;
                margin: 0 !important;
                width: 100% !important;
            }
            .ifs-educore-tabulation-table {
                page-break-inside: auto;
            }
            .ifs-educore-tabulation-table tr {
                page-break-inside: avoid;
                page-break-after: auto;
            }
            .ifs-educore-tabulation-table th {
                background: #f1f5f9 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }
    </style>

    <div class="ifs-educore-merit-root">

        <!-- Top Navigation Header -->
        <div class="ifs-educore-header-block no-print">
            <h2>
                <span class="dashicons dashicons-awards" style="color:#00523c; font-size:26px; width:26px; height:26px;"></span>
                <?php esc_html_e( 'Enterprise Merit List & Position Roster', 'ifsedu-school-management' ); ?>
            </h2>
            <a href="<?php echo esc_url( $back_url ); ?>" class="ifs-educore-btn-secondary">
                <span class="dashicons dashicons-arrow-left-alt" style="font-size:15px; width:15px; height:15px; vertical-align:middle;"></span>
                <?php esc_html_e( 'Back to Exams Directory', 'ifsedu-school-management' ); ?>
            </a>
        </div>

        <!-- Merit List Filter Console Bento Card -->
        <div class="ifs-educore-bento-filter-card no-print">
            <form method="GET" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" id="educoreMeritFilterForm">
                <input type="hidden" name="page" value="school_management_system">
                <input type="hidden" name="tab" value="results">
                <input type="hidden" name="sub" value="merit">

                <div class="ifs-educore-filter-grid">
                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-form-label"><?php esc_html_e( '1. Select Exam Scheme', 'ifsedu-school-management' ); ?> <span style="color:#ef4444;">*</span></label>
                        <select name="exam_id" id="ifs_educore_merit_exam_select" class="ifs-educore-select-field" required>
                            <option value=""><?php esc_html_e( '-- Choose Exam Scheme --', 'ifsedu-school-management' ); ?></option>
                            <?php foreach ( $exams as $ex ) : ?>
                                <option value="<?php echo absint( $ex->id ); ?>" <?php selected( $filter_exam, $ex->id ); ?>>
                                    <?php echo esc_html( $ex->exam_name ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-form-label"><?php esc_html_e( '2. Academic Class', 'ifsedu-school-management' ); ?> <span style="color:#ef4444;">*</span></label>
                        <select name="class_name" id="ifs_educore_merit_class_select" class="ifs-educore-select-field" required <?php disabled( empty( $academic_classes ) && 0 === $filter_exam ); ?>>
                            <option value=""><?php esc_html_e( '-- Choose Class --', 'ifsedu-school-management' ); ?></option>
                            <?php foreach ( $academic_classes as $cls_name ) : ?>
                                <option value="<?php echo esc_attr( $cls_name ); ?>" <?php selected( $filter_class, $cls_name ); ?>>
                                    <?php echo esc_html( $cls_name ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-form-label"><?php esc_html_e( '3. Section Filter (Optional)', 'ifsedu-school-management' ); ?></label>
                        <select name="section_name" id="ifs_educore_merit_section_select" class="ifs-educore-select-field">
                            <option value=""><?php esc_html_e( '-- All Sections (Entire Class) --', 'ifsedu-school-management' ); ?></option>
                            <?php foreach ( $available_sections as $sec_val ) : ?>
                                <option value="<?php echo esc_attr( $sec_val ); ?>" <?php selected( $filter_section, $sec_val ); ?>>
                                    <?php echo esc_html( $sec_val ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <button type="submit" class="ifs-educore-btn-submit-trigger">
                            <span class="dashicons dashicons-analytics"></span>
                            <?php esc_html_e( 'Generate Roster', 'ifsedu-school-management' ); ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Dynamic Ajax Filter Linking Script -->
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            var nonce = '<?php echo esc_js( wp_create_nonce( "ifs_educore_merit_nonce" ) ); ?>';

            $('#ifs_educore_merit_exam_select').on('change', function() {
                var selectedExamId = $(this).val();
                var $classSelect   = $('#ifs_educore_merit_class_select');
                var $secSelect     = $('#ifs_educore_merit_section_select');

                $classSelect.html('<option value=""><?php echo esc_js( __( '-- Loading Classes... --', 'ifsedu-school-management' ) ); ?></option>').prop('disabled', true);
                $secSelect.html('<option value=""><?php echo esc_js( __( '-- All Sections (Entire Class) --', 'ifsedu-school-management' ) ); ?></option>');

                if (!selectedExamId) {
                    $classSelect.html('<option value=""><?php echo esc_js( __( '-- Choose Class --', 'ifsedu-school-management' ) ); ?></option>');
                    return;
                }

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'ifs_educore_get_classes_by_exam_merit',
                        security: nonce,
                        exam_id: selectedExamId
                    },
                    success: function(response) {
                        if (response.success && response.data.length > 0) {
                            var options = '<option value=""><?php echo esc_js( __( '-- Choose Class --', 'ifsedu-school-management' ) ); ?></option>';
                            $.each(response.data, function(i, cls) {
                                options += '<option value="' + cls + '">' + cls + '</option>';
                            });
                            $classSelect.html(options).prop('disabled', false);
                        } else {
                            $classSelect.html('<option value=""><?php echo esc_js( __( 'No Classes Assigned To Exam', 'ifsedu-school-management' ) ); ?></option>').prop('disabled', true);
                        }
                    }
                });
            });

            $('#ifs_educore_merit_class_select').on('change', function() {
                var selectedClass = $(this).val();
                var $secSelect    = $('#ifs_educore_merit_section_select');

                $secSelect.html('<option value=""><?php echo esc_js( __( '-- Loading Sections... --', 'ifsedu-school-management' ) ); ?></option>');

                if (!selectedClass) {
                    $secSelect.html('<option value=""><?php echo esc_js( __( '-- All Sections (Entire Class) --', 'ifsedu-school-management' ) ); ?></option>');
                    return;
                }

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'ifs_educore_get_sections_by_class_merit',
                        security: nonce,
                        class_name: selectedClass
                    },
                    success: function(response) {
                        if (response.success && response.data.length > 0) {
                            var options = '<option value=""><?php echo esc_js( __( '-- All Sections (Entire Class) --', 'ifsedu-school-management' ) ); ?></option>';
                            $.each(response.data, function(i, sec) {
                                options += '<option value="' + sec + '">' + sec + '</option>';
                            });
                            $secSelect.html(options);
                        } else {
                            $secSelect.html('<option value=""><?php echo esc_js( __( '-- All Sections (Entire Class) --', 'ifsedu-school-management' ) ); ?></option>');
                        }
                    }
                });
            });
        });
        </script>

        <?php
        if ( $filter_exam > 0 && ! empty( $filter_class ) ) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $exam = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table_exams}` WHERE id = %d LIMIT 1", $filter_exam ) );

            // 1. Fetch ALL students of the class to determine Global Class Rank.
            $all_class_students = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id, full_name, student_id, roll_no, class_name, section_name 
                     FROM `{$table_students}` 
                     WHERE status = 'Active' AND class_name = %s 
                     ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC",
                    $filter_class
                )
            );
            // phpcs:enable

            if ( ! empty( $all_class_students ) ) {
                $all_ranked_pool = array();

                foreach ( $all_class_students as $s ) {
                    $student_internal_id = absint( $s->id );

                    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $results = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT obtained_marks, grade, gpa FROM `{$table_results}` WHERE exam_id = %d AND student_id = %d",
                            $filter_exam,
                            $student_internal_id
                        )
                    );
                    // phpcs:enable

                    if ( empty( $results ) ) {
                        continue;
                    }

                    $total_obt = 0;
                    $sum_gpa   = 0;
                    $sub_count = count( $results );
                    $has_fail  = false;

                    foreach ( $results as $res ) {
                        $total_obt += floatval( $res->obtained_marks );
                        $sum_gpa   += floatval( $res->gpa );
                        if ( 'F' === strtoupper( trim( (string) $res->grade ) ) || floatval( $res->gpa ) <= 0 ) {
                            $has_fail = true;
                        }
                    }

                    $avg_gpa   = ( $sub_count > 0 ) ? ( $sum_gpa / $sub_count ) : 0;
                    $final_gpa = $has_fail ? 0.00 : round( $avg_gpa, 2 );

                    $all_ranked_pool[] = array(
                        'student' => $s,
                        'total'   => $total_obt,
                        'gpa'     => $final_gpa,
                        'failed'  => $has_fail,
                        'section' => $s->section_name ? trim( (string) $s->section_name ) : '',
                    );
                }

                // Global Sort: Passed students first -> GPA (DESC) -> Total Score (DESC) -> Roll No (ASC).
                usort( $all_ranked_pool, function( $a, $b ) {
                    if ( $a['failed'] !== $b['failed'] ) {
                        return $a['failed'] ? 1 : -1;
                    }
                    if ( $b['gpa'] != $a['gpa'] ) {
                        return ( $b['gpa'] < $a['gpa'] ) ? -1 : 1;
                    }
                    if ( $b['total'] != $a['total'] ) {
                        return ( $b['total'] < $a['total'] ) ? -1 : 1;
                    }
                    return ( (int) $a['student']->roll_no < (int) $b['student']->roll_no ) ? -1 : 1;
                } );

                // Assign Global Class Positions & Section Positions (Filter ONLY Pass List).
                $class_pos_counter = 1;
                $section_counters  = array();
                $display_roster    = array();

                $sum_passed_gpa     = 0;
                $top_performer_name = '—';

                foreach ( $all_ranked_pool as $item ) {
                    // Skip failed students to generate ONLY the pass list
                    if ( $item['failed'] ) {
                        continue;
                    }

                    $sec = $item['section'];
                    if ( ! isset( $section_counters[ $sec ] ) ) {
                        $section_counters[ $sec ] = 1;
                    }

                    $item['class_position']   = $class_pos_counter++;
                    $item['section_position'] = $section_counters[ $sec ]++;
                    
                    if ( empty( $filter_section ) || $sec === $filter_section ) {
                        $sum_passed_gpa += $item['gpa'];
                        if ( '—' === $top_performer_name ) {
                            $top_performer_name = $item['student']->full_name;
                        }
                    }

                    if ( empty( $filter_section ) || $sec === $filter_section ) {
                        $display_roster[] = $item;
                    }
                }

                $total_passed_count = count( $display_roster );
                $cohort_avg_gpa     = ( $total_passed_count > 0 ) ? number_format( $sum_passed_gpa / $total_passed_count, 2 ) : '0.00';

                if ( ! empty( $display_roster ) ) :
                ?>

                <!-- Summary Metrics Bento Grid -->
                <div class="ifs-educore-bento-grid-stats no-print">
                    <div class="ifs-educore-stat-card">
                        <div class="ifs-educore-stat-icon" style="background: #fefce8; color: #ca8a04;">
                            <span class="dashicons dashicons-star-filled" style="font-size:24px; width:24px; height:24px;"></span>
                        </div>
                        <div class="ifs-educore-stat-meta">
                            <span class="ifs-educore-stat-label"><?php esc_html_e( 'Top Performer (Pass List)', 'ifsedu-school-management' ); ?></span>
                            <span class="ifs-educore-stat-value" style="color: #854d0e; font-size:15px;"><?php echo esc_html( $top_performer_name ); ?></span>
                        </div>
                    </div>

                    <div class="ifs-educore-stat-card">
                        <div class="ifs-educore-stat-icon" style="background: #ecfdf5; color: #047857;">
                            <span class="dashicons dashicons-chart-bar" style="font-size:24px; width:24px; height:24px;"></span>
                        </div>
                        <div class="ifs-educore-stat-meta">
                            <span class="ifs-educore-stat-label"><?php esc_html_e( 'Passed Candidates Avg. GPA', 'ifsedu-school-management' ); ?></span>
                            <span class="ifs-educore-stat-value" style="color: #047857;"><?php echo esc_html( $cohort_avg_gpa ); ?></span>
                        </div>
                    </div>

                    <div class="ifs-educore-stat-card">
                        <div class="ifs-educore-stat-icon" style="background: #f0f9ff; color: #0284c7;">
                            <span class="dashicons dashicons-groups" style="font-size:24px; width:24px; height:24px;"></span>
                        </div>
                        <div class="ifs-educore-stat-meta">
                            <span class="ifs-educore-stat-label"><?php esc_html_e( 'Total Passed Students', 'ifsedu-school-management' ); ?></span>
                            <span class="ifs-educore-stat-value" style="color: #0284c7;"><?php echo esc_html( $total_passed_count ); ?></span>
                        </div>
                    </div>
                </div>

                <div style="text-align: right; margin-bottom: 20px;" class="no-print">
                    <button type="button" onclick="window.print();" class="ifs-educore-btn-submit-trigger" style="width: auto; padding: 0 28px;">
                        <span class="dashicons dashicons-printer"></span>
                        <?php esc_html_e( 'Print Official Pass Merit Roster', 'ifsedu-school-management' ); ?>
                    </button>
                </div>

                <!-- Printable Official Roster Card -->
                <div class="ifs-educore-tabulation-container">
                    <div class="ifs-educore-tabulation-header">
                        <div class="ifs-educore-header-brand-row">
                            <?php if ( ! empty( $school_logo ) ) : ?>
                                <img src="<?php echo esc_url( $school_logo ); ?>" alt="<?php esc_attr_e( 'Logo', 'ifsedu-school-management' ); ?>" class="ifs-educore-roster-logo">
                            <?php endif; ?>
                            <h3 class="ifs-educore-tabulation-title"><?php echo esc_html( $school_name ); ?></h3>
                        </div>
                        <?php if ( ! empty( $school_tagline ) ) : ?>
                            <div style="font-size: 11.5px; color: #475569; font-weight: 700; margin-bottom: 4px; text-transform: capitalize;">
                                <?php echo esc_html( $school_tagline ); ?>
                            </div>
                        <?php endif; ?>
                        <h5 class="ifs-educore-tabulation-sub">
                            <?php echo esc_html( $exam ? $exam->exam_name : '' ); ?> &mdash; <?php esc_html_e( 'Official Passed Candidates Merit Roster', 'ifsedu-school-management' ); ?>
                        </h5>
                        <span style="display: inline-block; background: #f1f5f9; color: #475569; padding: 4px 14px; border-radius: 20px; font-size: 12px; font-weight: 700; margin-top: 8px; border: 1px solid #cbd5e1;">
                            <?php esc_html_e( 'Class:', 'ifsedu-school-management' ); ?> <?php echo esc_html( $filter_class ); ?>
                            <?php if ( ! empty( $filter_section ) ) : ?>
                                &nbsp;|&nbsp; <?php esc_html_e( 'Section:', 'ifsedu-school-management' ); ?> <?php echo esc_html( $filter_section ); ?>
                            <?php else : ?>
                                &nbsp;|&nbsp; <?php esc_html_e( 'All Sections Combined', 'ifsedu-school-management' ); ?>
                            <?php endif; ?>
                        </span>
                    </div>

                    <div style="overflow-x: auto;">
                        <table class="ifs-educore-tabulation-table">
                            <thead>
                                <tr>
                                    <th style="width: 10%;"><?php esc_html_e( 'Class Rank', 'ifsedu-school-management' ); ?></th>
                                    <th style="width: 10%;"><?php esc_html_e( 'Sec. Rank', 'ifsedu-school-management' ); ?></th>
                                    <th style="width: 8%;"><?php esc_html_e( 'Roll', 'ifsedu-school-management' ); ?></th>
                                    <th style="width: 13%;"><?php esc_html_e( 'Student ID', 'ifsedu-school-management' ); ?></th>
                                    <th style="text-align: left;"><?php esc_html_e( 'Student Full Name', 'ifsedu-school-management' ); ?></th>
                                    <th style="width: 10%;"><?php esc_html_e( 'Section', 'ifsedu-school-management' ); ?></th>
                                    <th style="width: 12%;"><?php esc_html_e( 'Total Score', 'ifsedu-school-management' ); ?></th>
                                    <th style="width: 10%;"><?php esc_html_e( 'GPA', 'ifsedu-school-management' ); ?></th>
                                    <th style="width: 11%;"><?php esc_html_e( 'Status', 'ifsedu-school-management' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                foreach ( $display_roster as $item ) : 
                                    $s     = $item['student'];
                                    $c_pos = $item['class_position'];
                                    $s_pos = $item['section_position'];

                                    // Rank Badge Styling Logic.
                                    $rank_class = 'rank-norm';
                                    if ( 1 === $c_pos ) {
                                        $rank_class = 'rank-gold';
                                    } elseif ( 2 === $c_pos ) {
                                        $rank_class = 'rank-silver';
                                    } elseif ( 3 === $c_pos ) {
                                        $rank_class = 'rank-bronze';
                                    }
                                ?>
                                <tr>
                                    <!-- Class Position -->
                                    <td>
                                        <?php if ( $c_pos > 0 ) : ?>
                                            <span class="ifs-educore-rank-badge <?php echo esc_attr( $rank_class ); ?>">
                                                <?php if ( 1 === $c_pos ) : ?>🏆<?php endif; ?>
                                                #<?php echo esc_html( $c_pos ); ?>
                                            </span>
                                        <?php else : ?>
                                            <span style="color: #94a3b8;">—</span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Section Position -->
                                    <td>
                                        <?php if ( $s_pos > 0 ) : ?>
                                            <span class="ifs-educore-rank-badge <?php echo ( 1 === $s_pos ) ? 'rank-gold' : 'rank-norm'; ?>">
                                                #<?php echo esc_html( $s_pos ); ?>
                                            </span>
                                        <?php else : ?>
                                            <span style="color: #94a3b8;">—</span>
                                        <?php endif; ?>
                                    </td>

                                    <td><strong>#<?php echo esc_html( $s->roll_no ); ?></strong></td>
                                    <td><code><?php echo esc_html( strtoupper( (string) $s->student_id ) ); ?></code></td>
                                    <td style="text-align: left; font-weight: 700; color: #0f172a;"><?php echo esc_html( $s->full_name ); ?></td>
                                    <td><span style="background: #f1f5f9; padding: 2px 8px; border-radius: 4px; font-weight: 700; font-size: 12px;"><?php echo esc_html( ! empty( $s->section_name ) ? $s->section_name : 'N/A' ); ?></span></td>
                                    <td><strong><?php echo esc_html( floatval( $item['total'] ) ); ?></strong></td>
                                    <td style="font-weight: 800; color: #00523c;"><?php echo esc_html( number_format( floatval( $item['gpa'] ), 2 ) ); ?></td>
                                    <td>
                                        <span class="ifs-educore-badge-status status-pass">
                                            <?php esc_html_e( 'PASS', 'ifsedu-school-management' ); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="ifs-educore-sign-row">
                        <div class="ifs-educore-signature-col">
                            <div class="ifs-educore-sign-line"><?php esc_html_e( 'Tabulator Signature', 'ifsedu-school-management' ); ?></div>
                        </div>
                        <div class="ifs-educore-signature-col">
                            <div class="ifs-educore-sign-line"><?php esc_html_e( 'Exam Controller', 'ifsedu-school-management' ); ?></div>
                        </div>
                        <div class="ifs-educore-signature-col">
                            <?php if ( ! empty( $principal_sig ) ) : ?>
                                <img src="<?php echo esc_url( $principal_sig ); ?>" alt="<?php esc_attr_e( 'Signature', 'ifsedu-school-management' ); ?>" class="ifs-educore-roster-sig-img">
                            <?php endif; ?>
                            <div class="ifs-educore-sign-line"><?php esc_html_e( 'Headmaster / Principal', 'ifsedu-school-management' ); ?></div>
                        </div>
                    </div>
                </div>

                <?php 
                else :
                    echo '<div style="background:#fff; border:1px solid #e2e8f0; padding:20px; border-radius:8px; text-align:center; color:#64748b;">' . esc_html__( 'No passing candidates found matching the selected criteria.', 'ifsedu-school-management' ) . '</div>';
                endif;

            } else {
                echo '<div style="background:#fff; border:1px solid #e2e8f0; padding:20px; border-radius:8px; text-align:center; color:#64748b;">' . esc_html__( 'No active students found in this class.', 'ifsedu-school-management' ) . '</div>';
            }
        }
        ?>

    </div>
    <?php
}
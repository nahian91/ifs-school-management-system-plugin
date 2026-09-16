<?php
/**
 * Academic Staff ID Card Printing Engine
 * File: inc/staff/staff-id-cards.php
 * Target Table: sms_staff
 * Text Domain: ifsedu-school-management
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Lockdown direct access.
}

if ( ! function_exists( 'educore_staff_id_get_table' ) ) {
    /**
     * Helper: Defensive table name resolver for staff ID card module
     */
    function educore_staff_id_get_table( string $key ): string {
        global $wpdb;
        if ( function_exists( 'educore_get_table_name' ) ) {
            $tbl = educore_get_table_name( $key );
            if ( ! empty( $tbl ) ) {
                return $tbl;
            }
        }
        return $wpdb->prefix . 'sms_' . $key;
    }
}

/**
 * AJAX Handler: Fetch Staff Names by Staff Type
 */
add_action( 'wp_ajax_ifs_educore_get_staff_names_by_type', 'ifs_educore_get_staff_names_by_type_handler' );
function ifs_educore_get_staff_names_by_type_handler(): void {
    check_ajax_referer( 'ifs_educore_staff_id_nonce', 'security' );

    global $wpdb;
    $table_staff = educore_staff_id_get_table( 'staff' );
    $staff_type  = isset( $_POST['staff_type'] ) ? sanitize_text_field( wp_unslash( $_POST['staff_type'] ) ) : '';

    if ( empty( $staff_type ) ) {
        wp_send_json_success( array() );
    }

    $results = $wpdb->get_results(
        $wpdb->prepare(
            'SELECT id, full_name, index_no FROM %i WHERE staff_type = %s ORDER BY full_name ASC',
            $table_staff,
            $staff_type
        )
    );

    wp_send_json_success( is_array( $results ) ? $results : array() );
}

/**
 * High-End Academic Staff ID Card Printing Engine
 * Schema: {$wpdb->prefix}sms_staff
 * Dimensions: CR80 Standard (54mm x 86mm)
 */
function educore_staff_id_cards_view(): void {
    global $wpdb;
    $table_staff = educore_staff_id_get_table( 'staff' );

    // Check Table Existence.
    if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_staff ) ) !== $table_staff ) {
        ?>
        <div class="ifs-educore-id-db-error-alert" style="padding:14px; background:#fef2f2; border-left:4px solid #dc2626; color:#991b1b; font-weight:700; border-radius:8px; margin-top:20px;">
            Database Error: Table <code><?php echo esc_html( $table_staff ); ?></code> does not exist.
        </div>
        <?php
        return;
    }

    // Trigger Parameters.
    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $is_loaded     = isset( $_GET['load_staff'] ) && '1' === $_GET['load_staff'];
    $selected_type = isset( $_GET['staff_type'] ) ? sanitize_text_field( wp_unslash( $_GET['staff_type'] ) ) : '';
    $selected_id   = isset( $_GET['staff_id'] ) ? absint( wp_unslash( $_GET['staff_id'] ) ) : 0;
    $search_query  = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    // Fetch Staff Types.
    $staff_types = $wpdb->get_results(
        $wpdb->prepare(
            'SELECT DISTINCT staff_type FROM %i WHERE staff_type != %s ORDER BY staff_type ASC',
            $table_staff,
            ''
        )
    );

    // Pre-fetch staff list for selected staff type if reloading page.
    $type_staff_members = array();
    if ( ! empty( $selected_type ) ) {
        $type_staff_members = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT id, full_name, index_no FROM %i WHERE staff_type = %s ORDER BY full_name ASC',
                $table_staff,
                $selected_type
            )
        );
    }

    $staff_members = array();

    // Query executed ONLY when requested.
    if ( $is_loaded || ! empty( $selected_type ) || ! empty( $search_query ) || $selected_id > 0 ) {
        $where_clauses = array( '1=1' );
        $params        = array();

        if ( ! empty( $selected_type ) ) {
            $where_clauses[] = 'staff_type = %s';
            $params[]        = $selected_type;
        }

        if ( $selected_id > 0 ) {
            $where_clauses[] = 'id = %d';
            $params[]        = $selected_id;
        }

        if ( ! empty( $search_query ) ) {
            $where_clauses[] = '(full_name LIKE %s OR designation LIKE %s OR phone LIKE %s OR index_no LIKE %s OR nid_no LIKE %s)';
            $like_s          = '%' . $wpdb->esc_like( $search_query ) . '%';
            $params[]        = $like_s;
            $params[]        = $like_s;
            $params[]        = $like_s;
            $params[]        = $like_s;
            $params[]        = $like_s;
        }

        $where_sql = implode( ' AND ', $where_clauses );
        $sql       = "SELECT * FROM `{$table_staff}` WHERE {$where_sql} ORDER BY order_number ASC, id DESC";

        if ( ! empty( $params ) ) {
            $staff_members = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
        } else {
            $staff_members = $wpdb->get_results( $sql );
        }
    }

    // Pull Dynamic Institutional Settings.
    $school_name   = get_option( 'educore_school_name', get_bloginfo( 'name' ) );
    $school_logo   = get_option( 'educore_school_logo', '' );
    $principal_sig = get_option( 'educore_principal_sig', '' );

    if ( empty( $school_name ) || 'WordPress' === $school_name ) {
        $school_name = get_bloginfo( 'name' );
    }

    $ajax_nonce = wp_create_nonce( 'ifs_educore_staff_id_nonce' );
    ?>

    <style>
        .ifs-educore-id-cards-root {
            width: 100% !important;
            max-width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
            box-sizing: border-box !important;
            font-family: inherit;
            color: #0f172a;
        }

        /* Full Width Neo-Bento Filter Card */
        .ifs-educore-bento-filter-card {
            width: 100% !important;
            background: #ffffff !important;
            border: 1px solid #e2e8f0 !important;
            border-radius: 14px !important;
            padding: 20px 24px !important;
            margin-bottom: 24px !important;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.02) !important;
            box-sizing: border-box !important;
        }

        /* Strict Single Row Layout */
        .ifs-educore-filter-single-row {
            width: 100% !important;
            display: grid !important;
            grid-template-columns: minmax(180px, 1.2fr) minmax(200px, 1.4fr) minmax(180px, 1.2fr) auto auto !important;
            gap: 12px !important;
            align-items: end !important;
            box-sizing: border-box !important;
        }

        @media (max-width: 1100px) {
            .ifs-educore-filter-single-row {
                grid-template-columns: 1fr 1fr !important;
            }
            .ifs-action-item {
                grid-column: span 1 !important;
            }
        }

        @media (max-width: 600px) {
            .ifs-educore-filter-single-row {
                grid-template-columns: 1fr !important;
            }
        }

        .ifs-educore-form-group {
            width: 100% !important;
            display: flex;
            flex-direction: column;
            box-sizing: border-box !important;
        }

        .ifs-educore-form-label {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 11px;
            font-weight: 700;
            color: #475569;
            text-transform: capitalize;
            letter-spacing: 0.3px;
            margin-bottom: 6px;
            white-space: nowrap;
        }

        .ifs-educore-select,
        .ifs-educore-input {
            width: 100% !important;
            height: 42px !important;
            border: 1.5px solid #cbd5e1 !important;
            border-radius: 9px !important;
            font-size: 13.5px !important;
            font-weight: 600 !important;
            color: #0f172a !important;
            background-color: #ffffff !important;
            box-sizing: border-box !important;
            outline: none !important;
            transition: all 0.2s ease !important;
        }

        .ifs-educore-select {
            padding: 0 34px 0 14px !important;
            background-image: url('data:image/svg+xml;utf8,<svg fill="%2364748b" height="20" viewBox="0 0 24 24" width="20" xmlns="http://www.w3.org/2000/svg"><path d="M7 10l5 5 5-5z"/></svg>') !important;
            background-repeat: no-repeat !important;
            background-position: right 10px center !important;
            appearance: none !important;
            -webkit-appearance: none !important;
            cursor: pointer;
        }

        .ifs-educore-input {
            padding: 0 14px !important;
        }

        .ifs-educore-select:hover:not(:disabled),
        .ifs-educore-input:hover:not(:disabled) {
            border-color: #94a3b8 !important;
        }

        .ifs-educore-select:focus,
        .ifs-educore-input:focus {
            border-color: #00523c !important;
            box-shadow: 0 0 0 3px rgba(0, 82, 60, 0.12) !important;
        }

        .ifs-educore-select:disabled {
            background-color: #f8fafc !important;
            color: #94a3b8 !important;
            border-color: #e2e8f0 !important;
            cursor: not-allowed;
            opacity: 0.85;
        }

        .ifs-educore-btn-primary {
            height: 42px !important;
            padding: 0 20px !important;
            background: #00523c !important;
            color: #ffffff !important;
            border: none !important;
            border-radius: 9px !important;
            font-weight: 700 !important;
            font-size: 13px !important;
            cursor: pointer !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 6px !important;
            text-decoration: none !important;
            box-shadow: 0 4px 12px rgba(0, 82, 60, 0.18) !important;
            transition: all 0.2s ease !important;
            white-space: nowrap !important;
        }

        .ifs-educore-btn-primary:hover {
            background: #047857 !important;
            color: #ffffff !important;
        }

        .ifs-educore-btn-secondary {
            height: 42px !important;
            padding: 0 18px !important;
            background: #f1f5f9 !important;
            color: #334155 !important;
            border: 1.5px solid #cbd5e1 !important;
            border-radius: 9px !important;
            font-weight: 700 !important;
            font-size: 13px !important;
            cursor: pointer !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 6px !important;
            text-decoration: none !important;
            transition: all 0.2s ease !important;
            white-space: nowrap !important;
        }

        .ifs-educore-btn-secondary:hover {
            background: #e2e8f0 !important;
            color: #0f172a !important;
        }

        /* Empty & Idle States */
        .ifs-educore-empty-state-card, .ifs-educore-idle-state-card {
            width: 100%;
            background: #ffffff;
            border: 1px dashed #cbd5e1;
            border-radius: 14px;
            padding: 50px 20px;
            text-align: center;
            box-sizing: border-box;
        }

        /* CR80 Standard ID Card Styles (54mm x 86mm) */
        .ifs-educore-id-cards-grid {
            width: 100%;
            display: flex;
            flex-wrap: wrap;
            gap: 24px;
            justify-content: flex-start;
            box-sizing: border-box;
        }

        .ifs-educore-card-wrapper {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            transition: opacity 0.2s;
        }

        .ifs-educore-card-checkbox-label {
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            color: #ffffff;
            background: #1e293b;
            padding: 4px 12px;
            border-radius: 6px;
            white-space: nowrap;
        }
        
        .ifs-educore-card-checkbox-label input {
            margin: 0;
            accent-color: #10b981;
        }

        .ifs-educore-id-card-unit {
            width: 54mm;
            height: 86mm;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            box-sizing: border-box !important;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0,0,0,0.08);
        }

        .ifs-educore-id-card-unit * {
            box-sizing: border-box !important;
        }

        .ifs-educore-card-header {
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 28mm;
            background: linear-gradient(135deg, #00523c 0%, #047857 100%);
            border-bottom: 3px solid #f59e0b;
            text-align: center;
            padding-top: 3.5mm;
            z-index: 1;
            display: block !important;
        }

        .ifs-educore-staff-logo {
            max-height: 9mm;
            max-width: 35mm;
            object-fit: contain;
            margin: 0 auto 1.5mm auto !important;
            display: block !important;
        }

        .ifs-educore-inst-name {
            color: #ffffff;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            line-height: 1.2;
            padding: 0 3mm;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: block !important;
            text-align: center !important;
        }

        .ifs-educore-photo-box {
            position: absolute;
            top: 22mm; 
            left: 50%;
            transform: translateX(-50%);
            width: 22mm;
            height: 22mm;
            background: #f1f5f9;
            border-radius: 4px;
            border: 2px solid #ffffff;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            z-index: 2;
            overflow: hidden;
            display: flex !important;
            align-items: center;
            justify-content: center;
        }

        .ifs-educore-photo-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block !important;
        }

        .ifs-educore-card-body {
            position: absolute;
            top: 46mm;
            left: 0; right: 0;
            text-align: center;
            padding: 0 4mm;
            z-index: 1;
            display: block !important;
            width: 100% !important;
        }

        .ifs-educore-info-name {
            font-size: 12px;
            font-weight: 800;
            color: #0f172a;
            text-transform: uppercase;
            line-height: 1.1;
            margin-bottom: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: block !important;
            width: 100% !important;
            text-align: center !important;
        }

        .ifs-educore-info-designation {
            font-size: 8px;
            font-weight: 700;
            color: #059669;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: block !important;
            width: 100% !important;
            text-align: center !important;
        }

        .ifs-educore-info-table {
            width: 100% !important;
            border-collapse: collapse !important;
            margin: 0 auto !important;
            text-align: left !important;
            display: table !important;
        }

        .ifs-educore-info-table tr {
            display: table-row !important;
        }

        .ifs-educore-info-table td {
            font-size: 7.5px !important;
            padding: 2.5px 0 !important;
            line-height: 1.1 !important;
            border: none !important;
            background: transparent !important;
            display: table-cell !important;
            vertical-align: middle !important;
        }

        .ifs-educore-info-table .lbl {
            color: #64748b !important;
            font-weight: 700 !important;
            width: 32% !important;
            text-align: left !important;
        }

        .ifs-educore-info-table .val {
            color: #0f172a !important;
            font-weight: 800 !important;
            text-align: left !important;
        }

        .ifs-educore-card-footer {
            position: absolute;
            bottom: 0; left: 0; right: 0;
            height: 13mm;
            background: #f8fafc;
            border-top: 2px solid #e2e8f0;
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            padding: 0 4mm !important;
            z-index: 1;
        }

        .ifs-educore-barcode-sim {
            font-family: 'Courier New', Courier, monospace;
            font-size: 10px;
            font-weight: 800;
            color: #334155;
            letter-spacing: 1px;
            line-height: 1;
            display: block !important;
        }

        .ifs-educore-sign-block {
            text-align: center !important;
            width: 16mm !important;
            display: block !important;
        }

        .ifs-educore-staff-sig-img {
            max-height: 5mm;
            max-width: 100%;
            display: block !important;
            margin: 0 auto 1px auto !important;
        }

        .ifs-educore-sign-line {
            border-top: 1px solid #94a3b8 !important;
            width: 100% !important;
            margin: 0 auto 1px auto !important;
            display: block !important;
        }

        .ifs-educore-sign-text {
            font-size: 5.5px;
            color: #64748b;
            font-weight: 700;
            text-transform: uppercase;
            display: block !important;
        }

        .ifs-educore-print-hide {
            opacity: 0.3;
        }

        @media print {
            @page { size: A4 portrait; margin: 10mm; }

            body * {
                visibility: hidden !important;
            }

            #ifsEducoreIDCardsGrid, #ifsEducoreIDCardsGrid * {
                visibility: visible !important;
            }

            #ifsEducoreIDCardsGrid {
                position: absolute !important;
                left: 0 !important;
                top: 0 !important;
                width: 100% !important;
                display: flex !important;
                flex-wrap: wrap !important;
                gap: 5mm !important;
            }

            .ifs-educore-card-wrapper {
                margin: 0 !important;
                page-break-inside: avoid !important;
                break-inside: avoid !important;
                display: flex !important;
                flex-direction: column !important;
                align-items: center !important;
            }

            .ifs-educore-card-checkbox-label, .ifs-educore-print-hide {
                display: none !important;
            }

            .ifs-educore-id-card-unit {
                border: 1px solid #000000 !important;
                box-shadow: none !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }
    </style>

    <div class="ifs-educore-id-cards-root">
        <!-- Top Navigation & Control Panel (Strict Single-Row Layout) -->
        <div class="ifs-educore-bento-filter-card no-print">
            <form method="get" class="ifs-educore-filter-single-row">
                <input type="hidden" name="page" value="school_management_system">
                <input type="hidden" name="tab" value="staff">
                <input type="hidden" name="sub" value="id_card">

                <!-- Primary Filter: Staff Type -->
                <div class="ifs-educore-form-group">
                    <label class="ifs-educore-form-label" for="staff_type_select">
                        <span class="dashicons dashicons-category" style="font-size:14px; width:14px; height:14px; color:#00523c;"></span>
                        <?php esc_html_e( '1. Staff Type', 'ifsedu-school-management' ); ?>
                    </label>
                    <select name="staff_type" id="staff_type_select" class="ifs-educore-select" onchange="ifsEducoreFetchStaffNames(this.value)">
                        <option value=""><?php esc_html_e( '-- All Staff Types --', 'ifsedu-school-management' ); ?></option>
                        <?php if ( ! empty( $staff_types ) ) : ?>
                            <?php foreach ( $staff_types as $st ) : ?>
                                <option value="<?php echo esc_attr( $st->staff_type ); ?>" <?php selected( $selected_type, $st->staff_type ); ?>>
                                    <?php echo esc_html( ucfirst( $st->staff_type ) ); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <!-- Dependent Dropdown: Staff Names -->
                <div class="ifs-educore-form-group">
                    <label class="ifs-educore-form-label" for="staff_name_select">
                        <span class="dashicons dashicons-businessman" style="font-size:14px; width:14px; height:14px; color:#00523c;"></span>
                        <?php esc_html_e( '2. Specific Member', 'ifsedu-school-management' ); ?>
                    </label>
                    <select name="staff_id" id="staff_name_select" class="ifs-educore-select" <?php echo empty( $selected_type ) ? 'disabled' : ''; ?>>
                        <option value=""><?php esc_html_e( '-- All Persons --', 'ifsedu-school-management' ); ?></option>
                        <?php if ( ! empty( $type_staff_members ) ) : ?>
                            <?php foreach ( $type_staff_members as $person ) : 
                                $person_id = absint( $person->id );
                            ?>
                                <option value="<?php echo absint( $person_id ); ?>" <?php selected( $selected_id, $person_id ); ?>>
                                    <?php echo esc_html( $person->full_name . ( $person->index_no ? ' (' . $person->index_no . ')' : '' ) ); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <!-- Keyword Search -->
                <div class="ifs-educore-form-group">
                    <label class="ifs-educore-form-label" for="staff_search_input">
                        <span class="dashicons dashicons-search" style="font-size:14px; width:14px; height:14px; color:#00523c;"></span>
                        <?php esc_html_e( '3. Search', 'ifsedu-school-management' ); ?>
                    </label>
                    <input type="text" name="s" id="staff_search_input" class="ifs-educore-input" placeholder="<?php esc_attr_e( 'Phone, index, NID...', 'ifsedu-school-management' ); ?>" value="<?php echo esc_attr( $search_query ); ?>">
                </div>

                <!-- Filter & Load Button -->
                <div class="ifs-action-item">
                    <button type="submit" name="load_staff" value="1" class="ifs-educore-btn-primary">
                        <span class="dashicons dashicons-filter"></span> <?php esc_html_e( 'Filter & Load', 'ifsedu-school-management' ); ?>
                    </button>
                </div>

                <!-- Reset / Load All Button -->
                <div class="ifs-action-item">
                    <?php if ( ! $is_loaded && empty( $selected_type ) && empty( $search_query ) && 0 === $selected_id ) : ?>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=school_management_system&tab=staff&sub=id_card&load_staff=1' ) ); ?>" class="ifs-educore-btn-secondary">
                            <span class="dashicons dashicons-groups"></span> <?php esc_html_e( 'Load All', 'ifsedu-school-management' ); ?>
                        </a>
                    <?php else : ?>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=school_management_system&tab=staff&sub=id_card' ) ); ?>" class="ifs-educore-btn-secondary">
                            <?php esc_html_e( 'Reset', 'ifsedu-school-management' ); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </form>

            <?php if ( ! empty( $staff_members ) ) : ?>
                <div style="display: flex; gap: 10px; align-items: center; border-top: 1px solid #f1f5f9; padding-top: 16px; margin-top: 16px;">
                    <button type="button" class="ifs-educore-btn-secondary" onclick="ifsEducoreToggleSelectAll(this)">
                        <span class="dashicons dashicons-editor-ul"></span> <?php esc_html_e( 'Toggle Select All', 'ifsedu-school-management' ); ?>
                    </button>
                    <button type="button" class="ifs-educore-btn-primary" onclick="window.print();">
                        <span class="dashicons dashicons-printer"></span> <?php esc_html_e( 'Print Selected Cards', 'ifsedu-school-management' ); ?>
                    </button>
                </div>
            <?php endif; ?>
        </div>

        <!-- Cards Layout Grid -->
        <?php if ( ! empty( $staff_members ) ) : ?>
            <div class="ifs-educore-id-cards-grid" id="ifsEducoreIDCardsGrid">
                <?php foreach ( $staff_members as $staff ) : 
                    $staff_internal_id = absint( $staff->id );
                    $photo_url    = ! empty( $staff->profile_image ) ? $staff->profile_image : '';
                    $staff_code   = ! empty( $staff->index_no ) ? $staff->index_no : 'STF-' . str_pad( (string) $staff_internal_id, 4, '0', STR_PAD_LEFT );
                    
                    $join_ts      = ( ! empty( $staff->joining_date ) && '1970-01-01' !== $staff->joining_date ) ? strtotime( $staff->joining_date ) : false;
                    $joining_date = $join_ts ? date_i18n( 'M Y', $join_ts ) : 'N/A';
                    
                    $blood_group  = ! empty( $staff->blood_group ) ? $staff->blood_group : 'N/A';
                    $full_name    = ! empty( $staff->full_name ) ? $staff->full_name : 'Staff Member';
                    $phone        = ! empty( $staff->phone ) ? $staff->phone : 'N/A';
                    ?>
                    
                    <div class="ifs-educore-card-wrapper" id="card-wrap-<?php echo absint( $staff_internal_id ); ?>">
                        <label class="ifs-educore-card-checkbox-label no-print">
                            <input type="checkbox" class="ifs-educore-card-select-cb" checked data-target="card-wrap-<?php echo absint( $staff_internal_id ); ?>" onchange="ifsEducoreSyncCardPrintState(this)">
                            <?php esc_html_e( 'Print Card', 'ifsedu-school-management' ); ?>
                        </label>

                        <div class="ifs-educore-id-card-unit">
                            <!-- Absolute Header -->
                            <div class="ifs-educore-card-header">
                                <?php if ( ! empty( $school_logo ) ) : ?>
                                    <img src="<?php echo esc_url( $school_logo ); ?>" alt="Logo" class="ifs-educore-staff-logo">
                                <?php endif; ?>
                                <div class="ifs-educore-inst-name"><?php echo esc_html( $school_name ); ?></div>
                            </div>

                            <!-- Absolute Overlapping Photo -->
                            <div class="ifs-educore-photo-box">
                                <?php if ( $photo_url ) : ?>
                                    <img src="<?php echo esc_url( $photo_url ); ?>" alt="Photo">
                                <?php else : ?>
                                    <span class="dashicons dashicons-admin-users" style="font-size: 32px; width: 32px; height: 32px; color: #cbd5e1;"></span>
                                <?php endif; ?>
                            </div>

                            <!-- Absolute Body -->
                            <div class="ifs-educore-card-body">
                                <div class="ifs-educore-info-name"><?php echo esc_html( $full_name ); ?></div>
                                <div class="ifs-educore-info-designation"><?php echo esc_html( ! empty( $staff->designation ) ? $staff->designation : 'Staff Member' ); ?></div>

                                <table class="ifs-educore-info-table">
                                    <tr>
                                        <td class="lbl"><?php esc_html_e( 'ID No:', 'ifsedu-school-management' ); ?></td>
                                        <td class="val"><?php echo esc_html( $staff_code ); ?></td>
                                    </tr>
                                    <tr>
                                        <td class="lbl"><?php esc_html_e( 'Phone:', 'ifsedu-school-management' ); ?></td>
                                        <td class="val"><?php echo esc_html( $phone ); ?></td>
                                    </tr>
                                    <tr>
                                        <td class="lbl"><?php esc_html_e( 'Joined:', 'ifsedu-school-management' ); ?></td>
                                        <td class="val"><?php echo esc_html( $joining_date ); ?></td>
                                    </tr>
                                    <tr>
                                        <td class="lbl"><?php esc_html_e( 'Blood:', 'ifsedu-school-management' ); ?></td>
                                        <td class="val" style="color:#dc2626;"><?php echo esc_html( $blood_group ); ?></td>
                                    </tr>
                                </table>
                            </div>

                            <!-- Absolute Footer -->
                            <div class="ifs-educore-card-footer">
                                <div class="ifs-educore-barcode-sim">||| ||||| || |||</div>
                                <div class="ifs-educore-sign-block">
                                    <?php if ( ! empty( $principal_sig ) ) : ?>
                                        <img src="<?php echo esc_url( $principal_sig ); ?>" alt="Signature" class="ifs-educore-staff-sig-img">
                                    <?php endif; ?>
                                    <div class="ifs-educore-sign-line"></div>
                                    <div class="ifs-educore-sign-text"><?php esc_html_e( 'Authority Sign', 'ifsedu-school-management' ); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                <?php endforeach; ?>
            </div>
        <?php elseif ( $is_loaded || ! empty( $selected_type ) || ! empty( $search_query ) || $selected_id > 0 ) : ?>
            <div class="ifs-educore-empty-state-card">
                <span class="dashicons dashicons-id-alt" style="font-size: 48px; width: 48px; height: 48px; color: #cbd5e1;"></span>
                <h3 style="margin: 12px 0 6px 0; color: #0f172a; font-weight:700;"><?php esc_html_e( 'No Staff Records Found', 'ifsedu-school-management' ); ?></h3>
                <p style="color: #64748b; margin: 0;"><?php echo sprintf( esc_html__( 'No matching records were found in %s for your filter criteria.', 'ifsedu-school-management' ), '<code>' . esc_html( $table_staff ) . '</code>' ); ?></p>
            </div>
        <?php else : ?>
            <!-- Idle State prior to loading -->
            <div class="ifs-educore-idle-state-card">
                <span class="dashicons dashicons-groups" style="font-size: 52px; width: 52px; height: 52px; color: #00523c; opacity: 0.8;"></span>
                <h3 style="margin: 16px 0 8px 0; color: #0f172a; font-weight:700; font-size:18px;"><?php esc_html_e( 'Staff ID Card Printing Panel', 'ifsedu-school-management' ); ?></h3>
                <p style="color: #64748b; max-width: 460px; margin: 0 auto 20px auto; font-size:14px; line-height:1.5;">
                    <?php esc_html_e( 'Select a staff type (e.g. Office, Teacher) to view specific names, or click Load All Staff to preview and print CR80 standard physical ID cards.', 'ifsedu-school-management' ); ?>
                </p>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=school_management_system&tab=staff&sub=id_card&load_staff=1' ) ); ?>" class="ifs-educore-btn-primary">
                    <span class="dashicons dashicons-download"></span> <?php esc_html_e( 'Load All Staff Records', 'ifsedu-school-management' ); ?>
                </a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function ifsEducoreFetchStaffNames(staffType) {
            var nameSelect = document.getElementById('staff_name_select');
            
            if (!staffType) {
                nameSelect.innerHTML = '<option value="">-- All Persons --</option>';
                nameSelect.disabled = true;
                return;
            }

            nameSelect.disabled = true;
            nameSelect.innerHTML = '<option value="">Loading...</option>';

            var formData = new FormData();
            formData.append('action', 'ifs_educore_get_staff_names_by_type');
            formData.append('security', '<?php echo esc_js( $ajax_nonce ); ?>');
            formData.append('staff_type', staffType);

            fetch(ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    var options = '<option value="">-- All Persons --</option>';
                    data.data.forEach(function(person) {
                        var extra = person.index_no ? ' (' + person.index_no + ')' : '';
                        options += '<option value="' + person.id + '">' + person.full_name + extra + '</option>';
                    });
                    nameSelect.innerHTML = options;
                    nameSelect.disabled = false;
                } else {
                    nameSelect.innerHTML = '<option value="">-- All Persons --</option>';
                }
            })
            .catch(function() {
                nameSelect.innerHTML = '<option value="">-- All Persons --</option>';
            });
        }

        function ifsEducoreSyncCardPrintState(cb) {
            var targetId = cb.getAttribute('data-target');
            var wrapper = document.getElementById(targetId);
            if (wrapper) {
                if (cb.checked) {
                    wrapper.classList.remove('ifs-educore-print-hide');
                } else {
                    wrapper.classList.add('ifs-educore-print-hide');
                }
            }
        }

        function ifsEducoreToggleSelectAll(btn) {
            var checkboxes = document.querySelectorAll('.ifs-educore-card-select-cb');
            var allChecked = true;

            checkboxes.forEach(function(cb) {
                if (!cb.checked) allChecked = false;
            });

            checkboxes.forEach(function(cb) {
                cb.checked = !allChecked;
                ifsEducoreSyncCardPrintState(cb);
            });
        }
    </script>
    <?php
}
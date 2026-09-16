<?php
/**
 * Staff Directory List View
 * File: inc/staff/staff-list.php
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Direct access lockdown.
}

/**
 * Render Staff Directory List View & Handle Tab Navigation
 */
function educore_staff_list_view() {
    global $wpdb;
    $table_staff = $wpdb->prefix . 'sms_staff';
    
    // 1. Capability Check.
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have permission to view the staff directory.', 'ifsedu-school-management' ) );
    }

    // Active Tab Handler (URL Key).
    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $active_tab = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : 'school_teacher';
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    // Flexible mapping allowing matching against multiple potential DB `staff_type` values.
    $tab_to_db_map = array(
        'school_teacher'  => array( 'Teacher', 'Teacher (School)' ),
        'college_teacher' => array( 'Teacher (College)' ),
        'staff'           => array( 'Staff' ),
        'officer'         => array( 'Officer' ),
    );

    $tab_labels = array(
        'school_teacher'  => __( 'School Teachers', 'ifsedu-school-management' ),
        'college_teacher' => __( 'College Teachers', 'ifsedu-school-management' ),
        'staff'           => __( 'Staff Members', 'ifsedu-school-management' ),
        'officer'         => __( 'Officers', 'ifsedu-school-management' ),
    );

    // Fallback to School Teacher if tab is invalid.
    if ( ! array_key_exists( $active_tab, $tab_to_db_map ) ) {
        $active_tab = 'school_teacher';
    }

    $allowed_types = $tab_to_db_map[ $active_tab ];
    $current_label = $tab_labels[ $active_tab ];

    // Detect Order Column dynamically in db safely.
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $db_columns = $wpdb->get_col( "DESCRIBE `{$table_staff}`", 0 );
    // phpcs:enable
    
    $order_col          = 'id';
    $allowed_order_cols = array( 'sort_order', 'serial_number', 'position', 'order_no', 'serial', 'id' );

    foreach ( $allowed_order_cols as $col ) {
        if ( is_array( $db_columns ) && in_array( $col, $db_columns, true ) ) {
            $order_col = $col;
            break;
        }
    }

    // Safely construct a dynamic IN clause query for multiple staff_type variations.
    $placeholders = implode( ',', array_fill( 0, count( $allowed_types ), '%s' ) );
    $query_string = "SELECT *, `{$order_col}` AS db_order_number 
                     FROM `{$table_staff}` 
                     WHERE staff_type IN ({$placeholders}) 
                     ORDER BY `{$order_col}` ASC, id DESC";
    
    $query_params = array_merge( array( $query_string ), $allowed_types );

    // Fetch DB records ordered strictly by DB order column.
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
    $staff_members = $wpdb->get_results( call_user_func_array( array( $wpdb, 'prepare' ), $query_params ) );
    // phpcs:enable

    // Extract unique designations for the filter dropdown
    $unique_designations = array();
    if ( ! empty( $staff_members ) ) {
        foreach ( $staff_members as $sm ) {
            $desig = trim( (string) $sm->designation );
            if ( ! empty( $desig ) && ! in_array( $desig, $unique_designations, true ) ) {
                $unique_designations[] = $desig;
            }
        }
        sort( $unique_designations );
    }

    // Tab Base URL Generator.
    $base_tab_url = add_query_arg( array( 'page' => 'school_management_system', 'tab' => 'staff' ), admin_url( 'admin.php' ) );
    $school_url   = add_query_arg( 'type', 'school_teacher', $base_tab_url );
    $college_url  = add_query_arg( 'type', 'college_teacher', $base_tab_url );
    $staff_url    = add_query_arg( 'type', 'staff', $base_tab_url );
    $officer_url  = add_query_arg( 'type', 'officer', $base_tab_url );
    $add_url      = add_query_arg( 'sub', 'add', $base_tab_url );
    ?>

    <style id="ifs-educore-staff-list-styles">
        .ifs-educore-dt-container {
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #0f172a;
        }

        /* Metric Cards Grid Styling */
        .ifs-educore-metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .ifs-educore-metric-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 16px 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.02);
            position: relative;
            overflow: hidden;
            border-left: 4px solid #00523c;
        }

        .metric-card-title {
            font-size: 13.5px;
            font-weight: 800;
            color: #1e293b;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .metric-card-stats {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px dashed #f1f5f9;
            padding-top: 10px;
        }

        .metric-stat-item {
            display: flex;
            flex-direction: column;
        }

        .metric-stat-label {
            font-size: 11px;
            color: #64748b;
            font-weight: 700;
            text-transform: uppercase;
        }

        .metric-stat-value {
            font-size: 16px;
            font-weight: 800;
            color: #0f172a;
        }

        .ifs-educore-dt-toolbar {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 16px 20px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.02);
        }

        .ifs-educore-dt-filter-box {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 14px;
        }

        .ifs-educore-filter-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .ifs-educore-select-element,
        .ifs-educore-search-input {
            height: 38px;
            border: 1.5px solid #cbd5e1;
            border-radius: 8px;
            padding: 0 12px;
            font-size: 13px;
            color: #0f172a;
            background: #ffffff;
            outline: none;
            transition: all 0.2s ease;
        }

        .ifs-educore-select-element:focus,
        .ifs-educore-search-input:focus {
            border-color: #00523c;
            box-shadow: 0 0 0 3px rgba(0, 82, 60, 0.12);
        }

        .ifs-educore-table-responsive {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.03);
            margin-bottom: 20px;
        }

        .ifs-educore-main-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 13.5px;
        }

        .ifs-educore-main-table th {
            padding: 14px 20px;
            color: #475569;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            font-size: 11.5px;
            text-transform: capitalize;
            font-weight: 800;
        }

        .ifs-educore-main-table td {
            padding: 12px 20px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        .ifs-educore-avatar-cell {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .ifs-educore-avatar-img {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            object-fit: cover;
            border: 1px solid #cbd5e1;
        }

        .ifs-educore-avatar-fallback {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #f1f5f9;
            color: #00523c;
            font-weight: 800;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #cbd5e1;
        }

        .ifs-educore-row-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 6px;
        }

        .ifs-educore-btn-action {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 6px 10px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.2s ease;
            border: 1px solid transparent;
        }

        .ifs-educore-btn-action svg {
            width: 14px;
            height: 14px;
            fill: currentColor;
        }

        .ifs-educore-btn-view {
            background: #f0f9ff;
            color: #0284c7;
            border-color: #bae6fd;
        }
        .ifs-educore-btn-view:hover {
            background: #0284c7;
            color: #ffffff;
        }

        .ifs-educore-btn-edit {
            background: #f0fdf4;
            color: #16a34a;
            border-color: #bbf7d0;
        }
        .ifs-educore-btn-edit:hover {
            background: #16a34a;
            color: #ffffff;
        }

        .ifs-educore-btn-delete {
            background: #fef2f2;
            color: #dc2626;
            border-color: #fecaca;
        }
        .ifs-educore-btn-delete:hover {
            background: #dc2626;
            color: #ffffff;
        }

        .ifs-educore-dt-footer-layout {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 14px 20px;
            font-size: 13px;
            font-weight: 600;
            color: #475569;
            box-shadow: 0 2px 8px rgba(0,0,0,0.02);
        }

        .ifs-educore-pagination-btn {
            height: 36px;
            padding: 0 16px;
            background: #f1f5f9;
            color: #475569;
            font-size: 13px;
            font-weight: 700;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .ifs-educore-pagination-btn:hover:not(:disabled) {
            background: #e2e8f0;
            color: #0f172a;
        }

        .ifs-educore-pagination-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
    </style>

    <div class="ifs-educore-dt-container">
        
        <!-- Header Title & Action CTA -->
        <div class="ifs-educore-staff-header-flex" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h2 class="ifs-educore-staff-main-title" style="margin:0; font-size:20px; font-weight:800; color:#0f172a; display:flex; align-items:center; gap:8px;">
                <span class="dashicons dashicons-groups ifs-educore-staff-title-icon" style="color:#00523c;"></span> 
                <?php esc_html_e( 'Teachers & Staff Directory', 'ifsedu-school-management' ); ?>
            </h2>
            <a href="<?php echo esc_url( $add_url ); ?>" class="ifs-educore-btn-success-primary" style="background:#00523c; color:#fff; padding:8px 16px; border-radius:8px; text-decoration:none; font-weight:700; font-size:13px; box-shadow:0 4px 10px rgba(0,82,60,0.2);">
                + <?php esc_html_e( 'Add New Staff Member', 'ifsedu-school-management' ); ?>
            </a>
        </div>

        <!-- Category Tabs Navigation (4 Tabs) -->
        <div class="ifs-educore-tabs-wrapper-block" style="display:flex; gap:10px; margin-bottom:24px; flex-wrap:wrap;">
            <a href="<?php echo esc_url( $school_url ); ?>" style="padding:8px 16px; border-radius:8px; font-weight:700; font-size:13px; text-decoration:none; display:inline-flex; align-items:center; gap:6px; border:1px solid <?php echo ( 'school_teacher' === $active_tab ) ? '#00523c' : '#e2e8f0'; ?>; background:<?php echo ( 'school_teacher' === $active_tab ) ? '#00523c' : '#f8fafc'; ?>; color:<?php echo ( 'school_teacher' === $active_tab ) ? '#ffffff' : '#64748b'; ?>;">
                <span class="dashicons dashicons-welcome-learn-more" style="font-size:16px; width:16px; height:16px;"></span>
                <?php esc_html_e( 'School Teacher', 'ifsedu-school-management' ); ?>
            </a>

            <a href="<?php echo esc_url( $college_url ); ?>" style="padding:8px 16px; border-radius:8px; font-weight:700; font-size:13px; text-decoration:none; display:inline-flex; align-items:center; gap:6px; border:1px solid <?php echo ( 'college_teacher' === $active_tab ) ? '#00523c' : '#e2e8f0'; ?>; background:<?php echo ( 'college_teacher' === $active_tab ) ? '#00523c' : '#f8fafc'; ?>; color:<?php echo ( 'college_teacher' === $active_tab ) ? '#ffffff' : '#64748b'; ?>;">
                <span class="dashicons dashicons-bank" style="font-size:16px; width:16px; height:16px;"></span>
                <?php esc_html_e( 'College Teacher', 'ifsedu-school-management' ); ?>
            </a>

            <a href="<?php echo esc_url( $staff_url ); ?>" style="padding:8px 16px; border-radius:8px; font-weight:700; font-size:13px; text-decoration:none; display:inline-flex; align-items:center; gap:6px; border:1px solid <?php echo ( 'staff' === $active_tab ) ? '#00523c' : '#e2e8f0'; ?>; background:<?php echo ( 'staff' === $active_tab ) ? '#00523c' : '#f8fafc'; ?>; color:<?php echo ( 'staff' === $active_tab ) ? '#ffffff' : '#64748b'; ?>;">
                <span class="dashicons dashicons-id" style="font-size:16px; width:16px; height:16px;"></span>
                <?php esc_html_e( 'Staff', 'ifsedu-school-management' ); ?>
            </a>

            <a href="<?php echo esc_url( $officer_url ); ?>" style="padding:8px 16px; border-radius:8px; font-weight:700; font-size:13px; text-decoration:none; display:inline-flex; align-items:center; gap:6px; border:1px solid <?php echo ( 'officer' === $active_tab ) ? '#00523c' : '#e2e8f0'; ?>; background:<?php echo ( 'officer' === $active_tab ) ? '#00523c' : '#f8fafc'; ?>; color:<?php echo ( 'officer' === $active_tab ) ? '#ffffff' : '#64748b'; ?>;">
                <span class="dashicons dashicons-businessperson" style="font-size:16px; width:16px; height:16px;"></span>
                <?php esc_html_e( 'Officers', 'ifsedu-school-management' ); ?>
            </a>
        </div>

        <!-- Dynamic Success / Update Notice Alerts -->
        <?php 
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        $status_msg = '';
        if ( isset( $_GET['msg'] ) ) {
            $status_msg = sanitize_text_field( wp_unslash( $_GET['msg'] ) );
        } elseif ( isset( $_GET['status'] ) ) {
            $status_msg = sanitize_text_field( wp_unslash( $_GET['status'] ) );
        }
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        if ( ! empty( $status_msg ) ) : ?>
            <?php if ( 'success' === $status_msg ) : ?>
                <div class="notice notice-success is-dismissible" style="padding: 12px 16px; margin: 0 0 20px 0; background: #ecfdf5; border-left: 4px solid #00523c; color: #065f46; border-radius: 8px; font-weight: 600;">
                    <p style="margin: 0;"><span class="dashicons dashicons-yes-alt" style="color: #00523c; vertical-align: middle; margin-right: 5px;"></span> <?php esc_html_e( 'Staff record saved successfully.', 'ifsedu-school-management' ); ?></p>
                </div>
            <?php elseif ( 'updated' === $status_msg ) : ?>
                <div class="notice notice-success is-dismissible" style="padding: 12px 16px; margin: 0 0 20px 0; background: #eff6ff; border-left: 4px solid #2563eb; color: #1e40af; border-radius: 8px; font-weight: 600;">
                    <p style="margin: 0;"><span class="dashicons dashicons-saved" style="color: #2563eb; vertical-align: middle; margin-right: 5px;"></span> <?php esc_html_e( 'Staff profile updated successfully.', 'ifsedu-school-management' ); ?></p>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Dynamic Metric Cards Section -->
        <div class="ifs-educore-metrics-grid">
            <div class="ifs-educore-metric-card">
                <div class="metric-card-title">
                    <span><?php echo esc_html( $current_label ); ?> <?php esc_html_e( 'Overview', 'ifsedu-school-management' ); ?></span>
                </div>
                <div class="metric-card-stats">
                    <div class="metric-stat-item">
                        <span class="metric-stat-label"><?php esc_html_e( 'Male', 'ifsedu-school-management' ); ?></span>
                        <span class="metric-stat-value" id="metric-male-count" style="color: #2563eb;">0</span>
                    </div>
                    <div class="metric-stat-item">
                        <span class="metric-stat-label"><?php esc_html_e( 'Female', 'ifsedu-school-management' ); ?></span>
                        <span class="metric-stat-value" id="metric-female-count" style="color: #db2777;">0</span>
                    </div>
                    <div class="metric-stat-item">
                        <span class="metric-stat-label"><?php esc_html_e( 'Total', 'ifsedu-school-management' ); ?></span>
                        <span class="metric-stat-value" id="metric-total-count" style="color: #00523c;">0</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Filter & Search Toolbar -->
        <div class="ifs-educore-dt-toolbar">
            <div class="ifs-educore-dt-filter-box">
                <div class="ifs-educore-filter-group">
                    <label for="ifs_educore_designation_filter" style="font-weight: 700; color: #475569; font-size: 13px; white-space: nowrap;">
                        <span class="dashicons dashicons-filter" style="font-size: 18px; vertical-align: middle; margin-right: 4px;"></span>
                        <?php esc_html_e( 'Filter Designation:', 'ifsedu-school-management' ); ?>
                    </label>
                    <select id="ifs_educore_designation_filter" class="ifs-educore-select-element">
                        <option value=""><?php esc_html_e( 'All Designations', 'ifsedu-school-management' ); ?></option>
                        <?php foreach ( $unique_designations as $desig ) : ?>
                            <option value="<?php echo esc_attr( $desig ); ?>"><?php echo esc_html( $desig ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div style="flex-grow:1; max-width: 300px;">
                <input type="text" id="ifs_educore_client_search" class="ifs-educore-search-input" style="width: 100%;" placeholder="<?php esc_attr_e( 'Search by name or ID...', 'ifsedu-school-management' ); ?>">
            </div>
        </div>

        <!-- Main DataTable (Native Responsive HTML) -->
        <div class="ifs-educore-table-responsive">
            <table id="ifs_educore_staff_main_table" class="ifs-educore-main-table">
                <thead>
                    <tr>
                        <th style="width: 70px; text-align: center;"><?php esc_html_e( 'Order', 'ifsedu-school-management' ); ?></th>
                        <th style="width: 130px;"><?php esc_html_e( 'Staff ID', 'ifsedu-school-management' ); ?></th>
                        <th><?php esc_html_e( 'Name', 'ifsedu-school-management' ); ?></th>
                        <th><?php esc_html_e( 'Designation', 'ifsedu-school-management' ); ?></th>
                        <th style="width: 160px;"><?php esc_html_e( 'Employment Type', 'ifsedu-school-management' ); ?></th>
                        <th style="text-align: right; white-space: nowrap; width: 220px;"><?php esc_html_e( 'Actions', 'ifsedu-school-management' ); ?></th>
                    </tr>
                </thead>
                <tbody id="ifs_educore_table_body">
                    <?php if ( ! empty( $staff_members ) ) : foreach ( $staff_members as $staff ) : 
                        $staff_id   = absint( $staff->id );
                        $view_url   = add_query_arg( array( 'page' => 'school_management_system', 'tab' => 'staff', 'sub' => 'view', 'id' => $staff_id ), admin_url( 'admin.php' ) );
                        $edit_url   = add_query_arg( array( 'page' => 'school_management_system', 'tab' => 'staff', 'sub' => 'edit', 'id' => $staff_id ), admin_url( 'admin.php' ) );
                        $delete_url = wp_nonce_url( add_query_arg( array( 'page' => 'school_management_system', 'tab' => 'staff', 'sub' => 'delete', 'id' => $staff_id ), admin_url( 'admin.php' ) ), 'delete_staff_' . $staff_id );

                        $order_no = isset( $staff->db_order_number ) ? absint( $staff->db_order_number ) : 0;
                        $full_name = ! empty( $staff->full_name ) ? $staff->full_name : ( ! empty( $staff->name ) ? $staff->name : '' );
                        $display_staff_id = ! empty( $staff->staff_id ) ? strtoupper( (string) $staff->staff_id ) : '—';
                        $emp_type_label = ! empty( $staff->staff_type ) ? $staff->staff_type : ucfirst( $active_tab );
                        $designation = ! empty( $staff->designation ) ? $staff->designation : __( 'Staff Member', 'ifsedu-school-management' );
                        
                        // Grab Gender for the JS Metric counters
                        $raw_gender = isset( $staff->gender ) ? strtolower( trim( $staff->gender ) ) : '';
                        $first_letter = function_exists( 'mb_substr' ) ? mb_substr( $full_name, 0, 1 ) : substr( $full_name, 0, 1 );
                    ?>
                        <tr class="ifs-educore-data-row" data-designation="<?php echo esc_attr( $designation ); ?>" data-gender="<?php echo esc_attr( $raw_gender ); ?>">
                            <td style="text-align:center;">
                                <span style="background:#f1f5f9; color:#475569; padding:4px 8px; border-radius:6px; font-weight:700; font-size:12px;"><?php echo esc_html( $order_no ); ?></span>
                            </td>
                            <td>
                                <code style="background:#f8fafc; border:1px solid #e2e8f0; padding:4px 8px; border-radius:4px; color:#0f172a; font-weight:700; font-size:12px;"><?php echo esc_html( $display_staff_id ); ?></code>
                            </td>
                            <td>
                                <div class="ifs-educore-avatar-cell">
                                    <?php if ( ! empty( $staff->profile_image ) || ! empty( $staff->photo_url ) ) : 
                                        $img_src = ! empty( $staff->profile_image ) ? $staff->profile_image : $staff->photo_url;
                                    ?>
                                        <img src="<?php echo esc_url( $img_src ); ?>" class="ifs-educore-avatar-img" alt="<?php echo esc_attr( $full_name ); ?>">
                                    <?php else : ?>
                                        <div class="ifs-educore-avatar-fallback"><?php echo esc_html( strtoupper( $first_letter ) ); ?></div>
                                    <?php endif; ?>
                                    <div>
                                        <strong style="color:#0f172a; font-size:14px;"><?php echo esc_html( $full_name ); ?></strong>
                                        <?php if ( ! empty( $staff->wp_user_id ) ) : ?>
                                            <div style="margin-top:2px;">
                                                <small style="color:#64748b; font-size:11px; font-weight:600;">
                                                    <span class="dashicons dashicons-admin-users" style="font-size: 13px; width: 13px; height: 13px; vertical-align:middle;"></span> 
                                                    <?php printf( esc_html__( 'WP User #%d', 'ifsedu-school-management' ), absint( $staff->wp_user_id ) ); ?>
                                                </small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span style="color:#475569; font-weight:600; font-size:13px;"><?php echo esc_html( $designation ); ?></span>
                            </td>
                            <td>
                                <span style="background:#eff6ff; color:#2563eb; padding:4px 10px; border-radius:6px; font-weight:700; font-size:11.5px; border:1px solid #bfdbfe;">
                                    <?php echo esc_html( $emp_type_label ); ?>
                                </span>
                            </td>
                            <td style="text-align: right;">
                                <div class="ifs-educore-row-actions">
                                    <a href="<?php echo esc_url( $view_url ); ?>" class="ifs-educore-btn-action ifs-educore-btn-view" title="<?php esc_attr_e( 'View Profile', 'ifsedu-school-management' ); ?>">
                                        <svg viewBox="0 0 24 24"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>
                                        <?php esc_html_e( 'Profile', 'ifsedu-school-management' ); ?>
                                    </a>
                                    <a href="<?php echo esc_url( $edit_url ); ?>" class="ifs-educore-btn-action ifs-educore-btn-edit" title="<?php esc_attr_e( 'Edit Record', 'ifsedu-school-management' ); ?>">
                                        <svg viewBox="0 0 24 24"><path d="M3 17.25V21h4.75L17.81 9.94l-4.75-4.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 4.75 4.75 1.83-1.83z"/></svg>
                                        <?php esc_html_e( 'Edit', 'ifsedu-school-management' ); ?>
                                    </a>
                                    <a href="<?php echo esc_url( $delete_url ); ?>" class="ifs-educore-btn-action ifs-educore-btn-delete" title="<?php esc_attr_e( 'Delete Record', 'ifsedu-school-management' ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to completely delete this staff record?', 'ifsedu-school-management' ) ); ?>');">
                                        <svg viewBox="0 0 24 24"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
                                        <?php esc_html_e( 'Delete', 'ifsedu-school-management' ); ?>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <div id="ifs_educore_dt_footer_target" class="ifs-educore-dt-footer-layout">
            <div id="ifs_educore_table_info"><?php esc_html_e( 'Initializing...', 'ifsedu-school-management' ); ?></div>
            <div style="display: flex; gap: 8px;">
                <button type="button" id="ifs_educore_prev_btn" class="ifs-educore-pagination-btn"><?php esc_html_e( 'Previous', 'ifsedu-school-management' ); ?></button>
                <button type="button" id="ifs_educore_next_btn" class="ifs-educore-pagination-btn"><?php esc_html_e( 'Next', 'ifsedu-school-management' ); ?></button>
            </div>
        </div>
    </div>

    <!-- Dynamic Filter, Live Metric Switcher & Pagination Engine -->
    <script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function() {
        const designationFilter = document.getElementById('ifs_educore_designation_filter');
        const searchInput = document.getElementById('ifs_educore_client_search');
        const allRows = Array.from(document.querySelectorAll('#ifs_educore_table_body tr.ifs-educore-data-row'));
        const tableInfo = document.getElementById('ifs_educore_table_info');
        const prevBtn = document.getElementById('ifs_educore_prev_btn');
        const nextBtn = document.getElementById('ifs_educore_next_btn');
        
        const metricTotal = document.getElementById('metric-total-count');
        const metricMale = document.getElementById('metric-male-count');
        const metricFemale = document.getElementById('metric-female-count');

        let currentPage = 1;
        const pageSize = 15;
        let visibleRows = allRows;

        function updateMetrics(rows) {
            let total = rows.length;
            let male = 0;
            let female = 0;

            rows.forEach(row => {
                const gender = row.getAttribute('data-gender');
                if (gender === 'male') male++;
                if (gender === 'female') female++;
            });

            if (metricTotal) metricTotal.textContent = total;
            if (metricMale) metricMale.textContent = male;
            if (metricFemale) metricFemale.textContent = female;
        }

        function applyFilters() {
            const selectedDesignation = (designationFilter.value || '').trim();
            const searchTerm = (searchInput.value || '').trim().toLowerCase();

            visibleRows = allRows.filter(function(row) {
                const rowDesig = (row.getAttribute('data-designation') || '').trim();
                const textContent = row.textContent.toLowerCase();

                if (selectedDesignation !== '' && rowDesig !== selectedDesignation) return false;
                if (searchTerm !== '' && !textContent.includes(searchTerm)) return false;

                return true;
            });

            updateMetrics(visibleRows);

            currentPage = 1;
            renderPagination();
        }

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
                tableInfo.textContent = '<?php echo esc_js( __( 'No matching staff records found', 'ifsedu-school-management' ) ); ?>';
            } else {
                tableInfo.textContent = '<?php echo esc_js( __( 'Showing', 'ifsedu-school-management' ) ); ?> ' + (startIdx + 1) + ' <?php echo esc_js( __( 'to', 'ifsedu-school-management' ) ); ?> ' + Math.min(endIdx, total) + ' <?php echo esc_js( __( 'of', 'ifsedu-school-management' ) ); ?> ' + total + ' <?php echo esc_js( __( 'entries', 'ifsedu-school-management' ) ); ?>';
            }

            prevBtn.disabled = (currentPage === 1);
            nextBtn.disabled = (currentPage === totalPages || total === 0);
        }

        if (designationFilter) {
            designationFilter.addEventListener('change', applyFilters);
        }

        if (searchInput) {
            searchInput.addEventListener('input', applyFilters);
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

        // Initialize state on load
        applyFilters();
    });
    </script>
    <?php
}
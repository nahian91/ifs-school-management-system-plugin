<?php
/**
 * Enterprise Core Students Directory & Interactive DataTables Workspace
 * Database Scope: sms_students & sms_academic_units
 * File: students-list-view.php
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Direct access safety buffer.
}

/**
 * Render Enterprise Core Students Directory & Interactive DataTables Workspace
 */
function educore_students_list_view() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient administrative permissions to access the student directory.', 'ifsedu-school-management' ) );
    }

    global $wpdb;
    $table_students = $wpdb->prefix . 'sms_students';
    $table_units    = $wpdb->prefix . 'sms_academic_units';

    // 1. Fetch only required columns for active students to prevent memory bloat.
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $students_records = $wpdb->get_results(
        "SELECT id, student_id, full_name, class_name, section_name, roll_no, gender, student_phone, guardian_phone, guardian_name, father_name, photo_url 
         FROM `{$table_students}` 
         WHERE status = 'Active' 
         ORDER BY id DESC"
    );

    // 2. Fetch Classes & Sections Map with sort_order Priority.
    $raw_units = $wpdb->get_results(
        "SELECT class_name, section_name, dept_name, sort_order 
         FROM `{$table_units}` 
         WHERE class_name != '' 
         ORDER BY sort_order ASC, CAST(class_name AS UNSIGNED) ASC, class_name ASC, section_name ASC"
    );
    // phpcs:enable
    
    $class_section_map = array();
    $class_order_map   = array();
    $available_classes = array();

    // Arrays to compute analytics for metric cards.
    $class_gender_stats     = array();
    $total_active_students = count( $students_records );
    $total_male_count      = 0;
    $total_female_count    = 0;

    if ( ! empty( $raw_units ) ) {
        foreach ( $raw_units as $unit ) {
            $c_name = trim( $unit->class_name );
            $s_ord  = isset( $unit->sort_order ) ? (int) $unit->sort_order : 0;

            if ( ! isset( $class_order_map[ $c_name ] ) || $s_ord < $class_order_map[ $c_name ] ) {
                $class_order_map[ $c_name ] = $s_ord;
            }

            if ( ! isset( $class_section_map[ $c_name ] ) ) {
                $class_section_map[ $c_name ] = array();
                $available_classes[] = $c_name;
            }
            if ( ! empty( $unit->section_name ) ) {
                $class_section_map[ $c_name ][] = trim( $unit->section_name );
            }
            if ( ! empty( $unit->dept_name ) ) {
                $class_section_map[ $c_name ][] = trim( $unit->dept_name );
            }
        }

        foreach ( $class_section_map as $c_name => $secs ) {
            $class_section_map[ $c_name ] = array_values( array_unique( array_filter( $secs ) ) );
            usort( $class_section_map[ $c_name ], 'strnatcasecmp' );
        }

        $available_classes = array_values( array_unique( $available_classes ) );
        usort( $available_classes, function( $a, $b ) use ( $class_order_map ) {
            $order_a = isset( $class_order_map[ $a ] ) ? $class_order_map[ $a ] : 0;
            $order_b = isset( $class_order_map[ $b ] ) ? $class_order_map[ $b ] : 0;
            if ( $order_a !== $order_b ) {
                return $order_a - $order_b;
            }
            return strnatcasecmp( $a, $b );
        } );
    }

    // Initialize stats structure for all available classes to ensure 0 counts appear accurately.
    foreach ( $available_classes as $c_name ) {
        $class_gender_stats[ $c_name ] = array( 'male' => 0, 'female' => 0, 'total' => 0 );
    }

    if ( ! empty( $students_records ) ) {
        foreach ( $students_records as $student ) {
            $c_name = trim( $student->class_name );
            $gender = strtolower( trim( $student->gender ) );

            if ( ! isset( $class_gender_stats[ $c_name ] ) ) {
                $class_gender_stats[ $c_name ] = array( 'male' => 0, 'female' => 0, 'total' => 0 );
            }

            if ( 'male' === $gender ) {
                $class_gender_stats[ $c_name ]['male']++;
                $total_male_count++;
            } elseif ( 'female' === $gender ) {
                $class_gender_stats[ $c_name ]['female']++;
                $total_female_count++;
            }
            $class_gender_stats[ $c_name ]['total']++;
        }
    }
    ?>

    <style id="ifs-educore-students-list-styles">
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
            display: none; /* Managed dynamically via JavaScript */
        }

        .ifs-educore-metric-card.is-visible {
            display: block;
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

        .ifs-educore-badge-gender {
            display: inline-flex;
            align-items: center;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11.5px;
            font-weight: 700;
        }

        .gender-male {
            background: #eff6ff;
            color: #2563eb;
            border: 1px solid #bfdbfe;
        }

        .gender-female {
            background: #fdf2f8;
            color: #db2777;
            border: 1px solid #fbcfe8;
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
                    <p style="margin: 0;"><span class="dashicons dashicons-yes-alt" style="color: #00523c; vertical-align: middle; margin-right: 5px;"></span> <?php esc_html_e( 'Student record saved successfully.', 'ifsedu-school-management' ); ?></p>
                </div>
            <?php elseif ( 'updated' === $status_msg ) : ?>
                <div class="notice notice-success is-dismissible" style="padding: 12px 16px; margin: 0 0 20px 0; background: #eff6ff; border-left: 4px solid #2563eb; color: #1e40af; border-radius: 8px; font-weight: 600;">
                    <p style="margin: 0;"><span class="dashicons dashicons-saved" style="color: #2563eb; vertical-align: middle; margin-right: 5px;"></span> <?php esc_html_e( 'Student profile updated successfully.', 'ifsedu-school-management' ); ?></p>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Dynamic Metric Cards Section -->
        <div class="ifs-educore-metrics-grid">
            <!-- Overall Total Card (Shown initially or when "All Classes" is selected) -->
            <div class="ifs-educore-metric-card is-visible" data-metric-card-id="all" style="border-left-color: #2563eb;">
                <div class="metric-card-title">
                    <span><?php esc_html_e( 'All Classes Overview', 'ifsedu-school-management' ); ?></span>
                </div>
                <div class="metric-card-stats">
                    <div class="metric-stat-item">
                        <span class="metric-stat-label"><?php esc_html_e( 'Boys', 'ifsedu-school-management' ); ?></span>
                        <span class="metric-stat-value" style="color: #2563eb;"><?php echo esc_html( $total_male_count ); ?></span>
                    </div>
                    <div class="metric-stat-item">
                        <span class="metric-stat-label"><?php esc_html_e( 'Girls', 'ifsedu-school-management' ); ?></span>
                        <span class="metric-stat-value" style="color: #db2777;"><?php echo esc_html( $total_female_count ); ?></span>
                    </div>
                    <div class="metric-stat-item">
                        <span class="metric-stat-label"><?php esc_html_e( 'Total', 'ifsedu-school-management' ); ?></span>
                        <span class="metric-stat-value" style="color: #00523c;"><?php echo esc_html( $total_active_students ); ?></span>
                    </div>
                </div>
            </div>

            <!-- Individual Class Cards (Class 1, Class 2, etc. - Shown live when specific class is selected) -->
            <?php foreach ( $available_classes as $c_name ) : 
                $c_male   = isset( $class_gender_stats[ $c_name ]['male'] ) ? $class_gender_stats[ $c_name ]['male'] : 0;
                $c_female = isset( $class_gender_stats[ $c_name ]['female'] ) ? $class_gender_stats[ $c_name ]['female'] : 0;
                $c_total  = isset( $class_gender_stats[ $c_name ]['total'] ) ? $class_gender_stats[ $c_name ]['total'] : 0;
            ?>
                <div class="ifs-educore-metric-card" data-metric-card-id="<?php echo esc_attr( $c_name ); ?>">
                    <div class="metric-card-title">
                        <span><?php echo esc_html( $c_name ); ?></span>
                    </div>
                    <div class="metric-card-stats">
                        <div class="metric-stat-item">
                            <span class="metric-stat-label"><?php esc_html_e( 'Boys', 'ifsedu-school-management' ); ?></span>
                            <span class="metric-stat-value" style="color: #2563eb;"><?php echo esc_html( $c_male ); ?></span>
                        </div>
                        <div class="metric-stat-item">
                            <span class="metric-stat-label"><?php esc_html_e( 'Girls', 'ifsedu-school-management' ); ?></span>
                            <span class="metric-stat-value" style="color: #db2777;"><?php echo esc_html( $c_female ); ?></span>
                        </div>
                        <div class="metric-stat-item">
                            <span class="metric-stat-label"><?php esc_html_e( 'Total', 'ifsedu-school-management' ); ?></span>
                            <span class="metric-stat-value" style="color: #00523c;"><?php echo esc_html( $c_total ); ?></span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Filter & Search Toolbar -->
        <div class="ifs-educore-dt-toolbar">
            <div class="ifs-educore-dt-filter-box">
                <div class="ifs-educore-filter-group">
                    <label for="ifs_educore_class_custom_filter" style="font-weight: 700; color: #475569; font-size: 13px; white-space: nowrap;">
                        <span class="dashicons dashicons-filter" style="font-size: 18px; vertical-align: middle; margin-right: 4px;"></span>
                        <?php esc_html_e( 'Filter Class:', 'ifsedu-school-management' ); ?>
                    </label>
                    <select id="ifs_educore_class_custom_filter" class="ifs-educore-select-element">
                        <option value=""><?php esc_html_e( 'Show All Classes', 'ifsedu-school-management' ); ?></option>
                        <?php foreach ( $available_classes as $class_name ) : ?>
                            <option value="<?php echo esc_attr( $class_name ); ?>"><?php echo esc_html( $class_name ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="ifs-educore-filter-group">
                    <label for="ifs_educore_section_custom_filter" style="font-weight: 700; color: #475569; font-size: 13px; white-space: nowrap;">
                        <?php esc_html_e( 'Section:', 'ifsedu-school-management' ); ?>
                    </label>
                    <select id="ifs_educore_section_custom_filter" class="ifs-educore-select-element" disabled>
                        <option value=""><?php esc_html_e( 'Select Class First', 'ifsedu-school-management' ); ?></option>
                    </select>
                </div>
            </div>

            <div id="ifs_educore_dt_search_target">
                <input type="text" id="ifs_educore_client_search" class="ifs-educore-search-input" placeholder="<?php esc_attr_e( 'Search student name, ID, roll...', 'ifsedu-school-management' ); ?>">
            </div>
        </div>

        <!-- Main DataTable (Native Responsive HTML) -->
        <div class="ifs-educore-table-responsive">
            <table id="ifs_educore_students_main_table" class="ifs-educore-main-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Student ID', 'ifsedu-school-management' ); ?></th>
                        <th><?php esc_html_e( 'Student Name', 'ifsedu-school-management' ); ?></th>
                        <th><?php esc_html_e( 'Academic Class', 'ifsedu-school-management' ); ?></th>
                        <th><?php esc_html_e( 'Roll No', 'ifsedu-school-management' ); ?></th>
                        <th><?php esc_html_e( 'Gender', 'ifsedu-school-management' ); ?></th>
                        <th><?php esc_html_e( 'Guardian Contact', 'ifsedu-school-management' ); ?></th>
                        <th style="text-align: right; white-space: nowrap;"><?php esc_html_e( 'Actions', 'ifsedu-school-management' ); ?></th>
                    </tr>
                </thead>
                <tbody id="ifs_educore_table_body">
                    <?php if ( ! empty( $students_records ) ) : foreach ( $students_records as $student ) : 
                        $view_url = add_query_arg(
                            array(
                                'page' => 'school_management_system',
                                'tab'  => 'students',
                                'sub'  => 'view',
                                'id'   => absint( $student->id ),
                            ),
                            admin_url( 'admin.php' )
                        );

                        $edit_url = add_query_arg(
                            array(
                                'page' => 'school_management_system',
                                'tab'  => 'students',
                                'sub'  => 'edit',
                                'id'   => absint( $student->id ),
                            ),
                            admin_url( 'admin.php' )
                        );

                        $delete_url = wp_nonce_url(
                            add_query_arg(
                                array(
                                    'page' => 'school_management_system',
                                    'tab'  => 'students',
                                    'sub'  => 'delete',
                                    'id'   => absint( $student->id ),
                                ),
                                admin_url( 'admin.php' )
                            ),
                            'delete_student_' . $student->id
                        );

                        $gender_style  = ( 'male' === strtolower( trim( $student->gender ) ) ) ? 'gender-male' : 'gender-female';
                        $phone_display = ! empty( $student->student_phone ) ? $student->student_phone : $student->guardian_phone;
                        $first_letter  = function_exists( 'mb_substr' ) ? mb_substr( $student->full_name, 0, 1 ) : substr( $student->full_name, 0, 1 );
                    ?>
                        <tr class="ifs-educore-data-row" data-class="<?php echo esc_attr( trim( $student->class_name ) ); ?>" data-section="<?php echo esc_attr( trim( $student->section_name ) ); ?>" data-id="<?php echo esc_attr( $student->id ); ?>">
                            <td><code style="background:#f1f5f9; padding:2px 6px; border-radius:4px; border:1px solid #cbd5e1; font-weight:700; color:#0f172a;"><?php echo esc_html( $student->student_id ); ?></code></td>
                            <td>
                                <div class="ifs-educore-avatar-cell">
                                    <?php if ( ! empty( $student->photo_url ) ) : ?>
                                        <img src="<?php echo esc_url( $student->photo_url ); ?>" class="ifs-educore-avatar-img" alt="<?php echo esc_attr( $student->full_name ); ?>">
                                    <?php else : ?>
                                        <div class="ifs-educore-avatar-fallback"><?php echo esc_html( strtoupper( $first_letter ) ); ?></div>
                                    <?php endif; ?>
                                    <div>
                                        <div style="font-weight: 700; color: #0f172a;"><?php echo esc_html( $student->full_name ); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div style="font-weight: 600; color:#00523c;"><?php echo esc_html( $student->class_name ); ?></div>
                                <small style="color: #64748b; font-size: 11.5px;">
                                    <?php
                                    /* translators: %s: Section name */
                                    echo esc_html( sprintf( __( 'Section: %s', 'ifsedu-school-management' ), ! empty( $student->section_name ) ? $student->section_name : __( 'N/A', 'ifsedu-school-management' ) ) );
                                    ?>
                                </small>
                            </td>
                            <td style="font-weight: 800; color: #334155;">
                                #<?php echo esc_html( $student->roll_no ); ?>
                            </td>
                            <td>
                                <span class="ifs-educore-badge-gender <?php echo esc_attr( $gender_style ); ?>">
                                    <?php echo esc_html( ucfirst( $student->gender ) ); ?>
                                </span>
                            </td>
                            <td>
                                <div style="font-weight: 600; color:#1e293b;"><?php echo esc_html( $student->guardian_name ? $student->guardian_name : $student->father_name ); ?></div>
                                <div style="font-size: 12px; color: #64748b;"><span class="dashicons dashicons-phone" style="font-size: 12px; width:12px; height:12px; vertical-align:middle;"></span> <?php echo esc_html( $phone_display ); ?></div>
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

                                    <a href="<?php echo esc_url( $delete_url ); ?>" class="ifs-educore-btn-action ifs-educore-btn-delete" title="<?php esc_attr_e( 'Delete Record', 'ifsedu-school-management' ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to completely delete this student file?', 'ifsedu-school-management' ) ); ?>');">
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
            <div id="ifs_educore_table_info"><?php esc_html_e( 'Showing all students', 'ifsedu-school-management' ); ?></div>
            <div style="display: flex; gap: 8px;">
                <button type="button" id="ifs_educore_prev_btn" class="ifs-educore-pagination-btn"><?php esc_html_e( 'Previous', 'ifsedu-school-management' ); ?></button>
                <button type="button" id="ifs_educore_next_btn" class="ifs-educore-pagination-btn"><?php esc_html_e( 'Next', 'ifsedu-school-management' ); ?></button>
            </div>
        </div>
    </div>

    <!-- Dynamic Filter, Live Metric Switcher & Pagination Engine -->
    <script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function() {
        const classSectionMap = <?php echo wp_json_encode( $class_section_map ); ?>;
        const classFilter = document.getElementById('ifs_educore_class_custom_filter');
        const sectionFilter = document.getElementById('ifs_educore_section_custom_filter');
        const searchInput = document.getElementById('ifs_educore_client_search');
        const allRows = Array.from(document.querySelectorAll('#ifs_educore_table_body tr.ifs-educore-data-row'));
        const metricCards = Array.from(document.querySelectorAll('.ifs-educore-metric-card'));
        const tableInfo = document.getElementById('ifs_educore_table_info');
        const prevBtn = document.getElementById('ifs_educore_prev_btn');
        const nextBtn = document.getElementById('ifs_educore_next_btn');

        let currentPage = 1;
        const pageSize = 20;
        let visibleRows = allRows;

        function updateMetricCards(selectedClass) {
            metricCards.forEach(card => {
                const cardId = card.getAttribute('data-metric-card-id');
                if (!selectedClass || selectedClass === '') {
                    // Show global overview card, hide class cards.
                    if (cardId === 'all') {
                        card.classList.add('is-visible');
                    } else {
                        card.classList.remove('is-visible');
                    }
                } else {
                    // Hide global overview card, show selected class card live.
                    if (cardId === selectedClass) {
                        card.classList.add('is-visible');
                    } else {
                        card.classList.remove('is-visible');
                    }
                }
            });
        }

        function applyFilters() {
            const selectedClass = (classFilter.value || '').trim();
            const selectedSection = (sectionFilter.value || '').trim();
            const searchTerm = (searchInput.value || '').trim().toLowerCase();

            updateMetricCards(selectedClass);

            visibleRows = allRows.filter(function(row) {
                const rowClass = (row.getAttribute('data-class') || '').trim();
                const rowSection = (row.getAttribute('data-section') || '').trim();
                const textContent = row.textContent.toLowerCase();

                if (selectedClass !== '' && rowClass !== selectedClass) return false;
                if (selectedSection !== '' && rowSection !== selectedSection) return false;
                if (searchTerm !== '' && !textContent.includes(searchTerm)) return false;

                return true;
            });

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
                tableInfo.textContent = '<?php echo esc_js( __( 'No matching student records found', 'ifsedu-school-management' ) ); ?>';
            } else {
                tableInfo.textContent = '<?php echo esc_js( __( 'Showing', 'ifsedu-school-management' ) ); ?> ' + (startIdx + 1) + ' <?php echo esc_js( __( 'to', 'ifsedu-school-management' ) ); ?> ' + Math.min(endIdx, total) + ' <?php echo esc_js( __( 'of', 'ifsedu-school-management' ) ); ?> ' + total + ' <?php echo esc_js( __( 'students', 'ifsedu-school-management' ) ); ?>';
            }

            prevBtn.disabled = (currentPage === 1);
            nextBtn.disabled = (currentPage === totalPages || total === 0);
        }

        if (classFilter) {
            classFilter.addEventListener('change', function() {
                const selClass = this.value.trim();
                sectionFilter.innerHTML = '';

                if (selClass !== '' && classSectionMap[selClass] && classSectionMap[selClass].length > 0) {
                    sectionFilter.innerHTML = '<option value=""><?php echo esc_js( __( 'All Sections', 'ifsedu-school-management' ) ); ?></option>';
                    classSectionMap[selClass].forEach(function(sec) {
                        const opt = document.createElement('option');
                        opt.value = sec;
                        opt.textContent = sec;
                        sectionFilter.appendChild(opt);
                    });
                    sectionFilter.disabled = false;
                } else if (selClass !== '') {
                    sectionFilter.innerHTML = '<option value=""><?php echo esc_js( __( 'No Sections Available', 'ifsedu-school-management' ) ); ?></option>';
                    sectionFilter.disabled = true;
                } else {
                    sectionFilter.innerHTML = '<option value=""><?php echo esc_js( __( 'Select Class First', 'ifsedu-school-management' ) ); ?></option>';
                    sectionFilter.disabled = true;
                }

                applyFilters();
            });
        }

        if (sectionFilter) {
            sectionFilter.addEventListener('change', applyFilters);
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
                currentPage++;
                renderPagination();
            });
        }

        applyFilters();
    });
    </script>
    <?php
}
<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Lockdown direct access
}

/**
 * 100% Fully Dynamic Staff Profile Bento Visualizer with Multi-Tabs
 * Fetches real Attendance logs, Salary details, and Duty Proxy records directly from the database.
 * File: inc/staff/staff-profile-view.php
 */
function educore_staff_profile_view() {
    global $wpdb;
    $table_staff      = $wpdb->prefix . 'sms_staff';
    $table_attendance = $wpdb->prefix . 'sms_staff_attendance';
    $table_proxies    = $wpdb->prefix . 'sms_staff_proxies';

    // 1. Security & Permission Check
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have permission to view staff profiles.', 'ifsedu-school-management' ) );
    }

    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $staff_id      = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0;
    $active_subtab = isset( $_GET['subtab'] ) ? sanitize_key( wp_unslash( $_GET['subtab'] ) ) : 'general';
    $cal_year      = isset( $_GET['cal_year'] ) ? absint( wp_unslash( $_GET['cal_year'] ) ) : absint( get_option( 'educore_academic_year', gmdate( 'Y' ) ) );
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    if ( $cal_year < 2000 || $cal_year > 2099 ) {
        $cal_year = absint( gmdate( 'Y' ) );
    }

    // Fetch Staff Record
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $staff = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table_staff}` WHERE id = %d LIMIT 1", $staff_id ) );
    // phpcs:enable

    if ( ! $staff ) {
        ?>
        <div class="ifs-educore-alert-box-danger my-4">
            <?php esc_html_e( 'Staff record not found.', 'ifsedu-school-management' ); ?>
        </div>
        <?php
        return;
    }

    // Retrieve Institutional Academic Off Days Map for the Selected Year
    $institutional_holidays = get_option( 'educore_academic_off_dates_' . $cal_year, array() );
    if ( ! is_array( $institutional_holidays ) ) {
        $institutional_holidays = array();
    }

    // Fetch Dynamic Attendance Records for this Staff Member in the Selected Year
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $raw_attendance = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT attendance_date, status FROM `{$table_attendance}` WHERE staff_id = %d AND YEAR(attendance_date) = %d",
            $staff_id,
            $cal_year
        )
    );

    $attendance_map = array();
    if ( ! empty( $raw_attendance ) ) {
        foreach ( $raw_attendance as $att ) {
            $attendance_map[ $att->attendance_date ] = ucfirst( strtolower( $att->status ) );
        }
    }

    // Fetch Dynamic Duty Proxies for this Staff Member
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $proxy_records = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM `{$table_proxies}` WHERE staff_id = %d ORDER BY proxy_date DESC",
            $staff_id
        )
    );

    // Processing variables & Tab Links
    $base_profile_url = admin_url( 'admin.php?page=school_management_system&tab=staff&sub=view&id=' . absint( $staff->id ) );
    $back_url         = admin_url( 'admin.php?page=school_management_system&tab=staff&sub=list' );
    $edit_url         = admin_url( 'admin.php?page=school_management_system&tab=staff&sub=edit&id=' . absint( $staff->id ) );
    
    $tab_general    = add_query_arg( array( 'subtab' => 'general', 'cal_year' => $cal_year ), $base_profile_url );
    $tab_attendance = add_query_arg( array( 'subtab' => 'attendance', 'cal_year' => $cal_year ), $base_profile_url );
    $tab_salary     = add_query_arg( array( 'subtab' => 'salary', 'cal_year' => $cal_year ), $base_profile_url );
    $tab_proxy      = add_query_arg( array( 'subtab' => 'proxy', 'cal_year' => $cal_year ), $base_profile_url );

    $is_active = strtolower( trim( (string) ( $staff->status ?? '' ) ) ) === 'active';

    // Date Format Handling
    $dob_ts       = ( ! empty( $staff->dob ) && '1970-01-01' !== $staff->dob && '0000-00-00' !== $staff->dob ) ? strtotime( $staff->dob ) : false;
    $dob          = $dob_ts ? date_i18n( 'd F, Y', $dob_ts ) : '—';

    $joining_ts   = ( ! empty( $staff->joining_date ) && '1970-01-01' !== $staff->joining_date && '0000-00-00' !== $staff->joining_date ) ? strtotime( $staff->joining_date ) : false;
    $joining_date = $joining_ts ? date_i18n( 'd F, Y', $joining_ts ) : '—';

    $salary       = number_format( (float) ( $staff->salary ?? 0 ), 2 );
    $staff_id_num = ! empty( $staff->staff_id ) ? strtoupper( (string) $staff->staff_id ) : '—';
    ?>

    <style id="ifs-educore-staff-profile-view-styles">
        .ifs-educore-profile-root {
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #0f172a;
        }

        .ifs-educore-alert-box-danger {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
            padding: 14px 18px;
            border-radius: 10px;
            font-weight: 700;
        }

        .ifs-educore-profile-actions-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .ifs-educore-profile-btn-outline,
        .ifs-educore-profile-btn-light,
        .ifs-educore-profile-btn-primary {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            height: 38px;
            padding: 0 16px;
            font-size: 13px;
            font-weight: 700;
            border-radius: 8px;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .ifs-educore-profile-btn-outline {
            background: #ffffff;
            color: #475569;
            border: 1px solid #cbd5e1;
        }
        .ifs-educore-profile-btn-outline:hover {
            background: #f1f5f9;
            color: #0f172a;
        }

        .ifs-educore-profile-btn-light {
            background: #f8fafc;
            color: #0f172a;
            border: 1px solid #e2e8f0;
        }
        .ifs-educore-profile-btn-light:hover {
            background: #f1f5f9;
        }

        .ifs-educore-profile-btn-primary {
            background: #00523c;
            color: #ffffff;
            border: none;
            font-weight: 800;
            box-shadow: 0 4px 12px rgba(0, 106, 78, 0.25);
        }
        .ifs-educore-profile-btn-primary:hover {
            background: #003e2d;
            color: #ffffff;
        }

        /* Profile Internal Tabs Navigation */
        .ifs-educore-profile-tabs {
            display: flex;
            gap: 8px;
            border-bottom: 2px solid #e2e8f0;
            margin-bottom: 24px;
            padding-bottom: 0;
            flex-wrap: wrap;
        }

        .ifs-educore-profile-tab-item {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            font-size: 13.5px;
            font-weight: 700;
            color: #64748b;
            text-decoration: none;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
            transition: all 0.2s ease;
        }

        .ifs-educore-profile-tab-item:hover {
            color: #00523c;
        }

        .ifs-educore-profile-tab-item.active {
            color: #00523c;
            border-bottom-color: #00523c;
            background: #f0fdf4;
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
        }

        .ifs-educore-bento-header-card {
            background: linear-gradient(135deg, #00523c 0%, #047857 100%);
            border-radius: 16px;
            padding: 32px;
            color: #ffffff;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 25px -5px rgba(0, 82, 60, 0.2);
            margin-bottom: 24px;
        }

        .ifs-educore-bento-header-bg-pattern {
            position: absolute;
            right: -20px;
            bottom: -20px;
            opacity: 0.08;
            pointer-events: none;
            width: 200px;
            height: 200px;
        }

        .ifs-educore-profile-avatar-wrapper {
            width: 96px;
            height: 96px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            border: 3px solid rgba(255, 255, 255, 0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            margin: 0 auto;
        }

        .ifs-educore-profile-avatar-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .ifs-educore-profile-avatar-placeholder {
            font-size: 36px;
            font-weight: 800;
            color: #ffffff;
            text-align: center;
            line-height: 96px;
        }

        .ifs-educore-status-badge {
            background: #ffffff;
            color: #0f172a;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
        }

        .ifs-educore-status-indicator-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
        }

        .ifs-educore-status-dot-active {
            background-color: #10b981;
        }

        .ifs-educore-status-dot-inactive {
            background-color: #ef4444;
        }

        .ifs-educore-glass-id-badge {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12.5px;
            font-weight: 600;
            color: #ffffff;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .ifs-educore-bento-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 15px -3px rgba(0, 0, 0, 0.03);
            height: 100%;
        }

        .ifs-educore-bento-section-title {
            font-size: 15px;
            font-weight: 800;
            color: #00523c;
            border-bottom: 2px solid #f1f5f9;
            padding-bottom: 12px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .ifs-educore-info-label {
            font-size: 11.5px;
            font-weight: 700;
            color: #64748b;
            text-transform: capitalize;
            letter-spacing: 0.3px;
            margin-bottom: 4px;
        }

        .ifs-educore-info-value {
            font-size: 14px;
            font-weight: 700;
            color: #0f172a;
        }

        .ifs-educore-address-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 16px;
            min-height: 80px;
            color: #475569;
            font-size: 13.5px;
            line-height: 1.5;
        }

        .ifs-educore-social-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            font-size: 12.5px;
            font-weight: 700;
            color: #334155;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .ifs-educore-social-pill:hover {
            background: #00523c;
            color: #ffffff;
            border-color: #00523c;
        }
        .ifs-educore-social-pill svg {
            width: 14px;
            height: 14px;
            fill: currentColor;
        }

        .ifs-educore-emergency-card {
            background: #fff8f8;
            border: 1px solid #fecaca;
        }
        .ifs-educore-emergency-title {
            color: #dc2626;
            border-bottom-color: #fee2e2;
        }

        /* Attendance Report Matrix & Calendar Grid Styles */
        .ifs-educore-cal-year-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 12px 18px;
            border-radius: 12px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 12px;
        }
        .ifs-educore-cal-legend {
            display: flex;
            gap: 15px;
            align-items: center;
            font-size: 12px;
            font-weight: 600;
            color: #475569;
            flex-wrap: wrap;
        }
        .legend-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 4px;
        }
        .legend-dot-present { background: #ecfdf5; border: 1px solid #10b981; }
        .legend-dot-absent { background: #fef2f2; border: 1px solid #ef4444; }
        .legend-dot-leave { background: #fffbeb; border: 1px solid #f59e0b; }
        .legend-dot-holiday { background: #f1f5f9; border: 1px solid #cbd5e1; }

        .ifs-educore-cal-container-12 {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 16px;
        }
        .ifs-educore-cal-month-box {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 14px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.01);
        }
        .ifs-educore-cal-m-title {
            font-size: 13.5px;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 8px;
            text-align: center;
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 6px;
        }
        .ifs-educore-cal-wk-row {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            text-align: center;
            font-size: 10.5px;
            font-weight: 700;
            color: #94a3b8;
            margin-bottom: 4px;
        }
        .ifs-educore-cal-days-row {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 3px;
        }
        .ifs-educore-cal-cell {
            aspect-ratio: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 700;
            border-radius: 6px;
            background: #f8fafc;
            color: #334155;
            cursor: default;
        }
        .ifs-educore-cal-cell.is-empty {
            background: transparent;
        }
        .cal-status-present { background: #ecfdf5 !important; color: #065f46; border: 1px solid #a7f3d0; }
        .cal-status-absent { background: #fef2f2 !important; color: #991b1b; border: 1px solid #fecaca; }
        .cal-status-leave { background: #fffbeb !important; color: #92400e; border: 1px solid #fde68a; }
        .cal-status-holiday { background: #f1f5f9 !important; color: #64748b; border: 1px solid #cbd5e1; }

        @media print {
            .no-print { display: none !important; }
            body * { visibility: hidden; }
            .ifs-educore-profile-root, .ifs-educore-profile-root * { visibility: visible; }
            .ifs-educore-profile-root { position: absolute; left: 0; top: 0; width: 100%; }
        }
    </style>

    <div class="ifs-educore-profile-root my-3">
        
        <!-- Navigation Controls Bar -->
        <div class="ifs-educore-profile-actions-bar no-print">
            <a href="<?php echo esc_url( $back_url ); ?>" class="ifs-educore-profile-btn-outline">
                &larr; <?php esc_html_e( 'Back to Directory', 'ifsedu-school-management' ); ?>
            </a>
            <div style="display: flex; gap: 8px;">
                <button onclick="window.print();" class="ifs-educore-profile-btn-light">
                    <span class="dashicons dashicons-printer" style="vertical-align:middle;"></span> <?php esc_html_e( 'Print Profile', 'ifsedu-school-management' ); ?>
                </button>
                <a href="<?php echo esc_url( $edit_url ); ?>" class="ifs-educore-profile-btn-primary">
                    <span class="dashicons dashicons-edit" style="vertical-align:middle;"></span> <?php esc_html_e( 'Edit Profile', 'ifsedu-school-management' ); ?>
                </a>
            </div>
        </div>

        <!-- Hero Header Card -->
        <div class="ifs-educore-bento-header-card">
            <svg class="ifs-educore-bento-header-bg-pattern" width="200" height="200" viewBox="0 0 24 24"><path fill="#ffffff" d="M12 2l-7 7v11c0 1.1.9 2 2 2h10c1.1 0 2-.9 2-2V9l-7-7zm0 2.84L17.16 9H6.84L12 4.84zM7 19v-8h10v8H7z"/></svg>

            <div class="row align-items-center">
                <div class="col-md-auto text-center text-md-start mb-3 mb-md-0">
                    <div class="ifs-educore-profile-avatar-wrapper">
                        <?php if ( ! empty( $staff->profile_image ) ) : ?>
                            <img src="<?php echo esc_url( $staff->profile_image ); ?>" alt="<?php echo esc_attr( $staff->full_name ); ?>" class="ifs-educore-profile-avatar-img">
                        <?php else : 
                            $first_letter = mb_substr( (string) ( $staff->full_name ?? 'S' ), 0, 1, 'UTF-8' );
                        ?>
                            <div class="ifs-educore-profile-avatar-placeholder">
                                <?php echo esc_html( mb_strtoupper( $first_letter, 'UTF-8' ) ); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-md text-center text-md-start">
                    <div class="d-flex flex-wrap align-items-center justify-content-center justify-content-md-start gap-2 mb-2">
                        <h2 class="m-0 fw-bold text-white" style="letter-spacing: -0.5px;"><?php echo esc_html( $staff->full_name ); ?></h2>
                        <span class="ifs-educore-status-badge">
                            <span class="ifs-educore-status-indicator-dot <?php echo $is_active ? 'ifs-educore-status-dot-active' : 'ifs-educore-status-dot-inactive'; ?>"></span>
                            <?php echo esc_html( ucfirst( (string) $staff->status ) ); ?>
                        </span>
                    </div>

                    <div class="d-flex flex-wrap align-items-center justify-content-center justify-content-md-start gap-2">
                        <div class="ifs-educore-glass-id-badge">
                            <span class="dashicons dashicons-id text-white"></span>
                            <span>ID: <strong><?php echo esc_html( $staff_id_num ); ?></strong></span>
                        </div>
                        <div class="ifs-educore-glass-id-badge">
                            <span class="dashicons dashicons-businessperson text-white"></span>
                            <span class="fw-bold text-white"><?php echo esc_html( $staff->designation ); ?></span>
                        </div>
                        <?php if ( ! empty( $staff->staff_type ) ) : ?>
                            <div class="ifs-educore-glass-id-badge">
                                <span class="dashicons dashicons-category text-white"></span>
                                <span><?php echo esc_html( $staff->staff_type ); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sub-Tabs Navigation Header -->
        <div class="ifs-educore-profile-tabs no-print">
            <a href="<?php echo esc_url( $tab_general ); ?>" class="ifs-educore-profile-tab-item <?php echo ( 'general' === $active_subtab ) ? 'active' : ''; ?>">
                <span class="dashicons dashicons-id-alt" style="vertical-align: middle;"></span>
                <?php esc_html_e( 'General Information', 'ifsedu-school-management' ); ?>
            </a>
            <a href="<?php echo esc_url( $tab_attendance ); ?>" class="ifs-educore-profile-tab-item <?php echo ( 'attendance' === $active_subtab ) ? 'active' : ''; ?>">
                <span class="dashicons dashicons-calendar-alt" style="vertical-align: middle;"></span>
                <?php esc_html_e( 'Attendance Report', 'ifsedu-school-management' ); ?>
            </a>
            <a href="<?php echo esc_url( $tab_salary ); ?>" class="ifs-educore-profile-tab-item <?php echo ( 'salary' === $active_subtab ) ? 'active' : ''; ?>">
                <span class="dashicons dashicons-money-alt" style="vertical-align: middle;"></span>
                <?php esc_html_e( 'Salary & Payroll', 'ifsedu-school-management' ); ?>
            </a>
            <a href="<?php echo esc_url( $tab_proxy ); ?>" class="ifs-educore-profile-tab-item <?php echo ( 'proxy' === $active_subtab ) ? 'active' : ''; ?>">
                <span class="dashicons dashicons-randomize" style="vertical-align: middle;"></span>
                <?php esc_html_e( 'Duty Proxy', 'ifsedu-school-management' ); ?>
            </a>
        </div>

        <!-- ======================================================== -->
        <!-- TAB 1: GENERAL INFO (DEFAULT) -->
        <!-- ======================================================== -->
        <?php if ( 'general' === $active_subtab ) : ?>
            <div class="row g-4">
                <!-- Left Column: Primary Details -->
                <div class="col-lg-8">
                    <div class="row g-4">
                        <div class="col-12">
                            <div class="ifs-educore-bento-card">
                                <div class="ifs-educore-bento-section-title">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                                    <?php esc_html_e( 'Personal Details & Identity', 'ifsedu-school-management' ); ?>
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="ifs-educore-info-label"><?php esc_html_e( 'National ID (NID)', 'ifsedu-school-management' ); ?></div>
                                        <div class="ifs-educore-info-value font-monospace"><?php echo esc_html( $staff->nid_no ?: '—' ); ?></div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="ifs-educore-info-label"><?php esc_html_e( 'Date of Birth', 'ifsedu-school-management' ); ?></div>
                                        <div class="ifs-educore-info-value"><?php echo esc_html( $dob ); ?></div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="ifs-educore-info-label"><?php esc_html_e( 'Gender', 'ifsedu-school-management' ); ?></div>
                                        <div class="ifs-educore-info-value"><?php echo esc_html( $staff->gender ?: 'Male' ); ?></div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="ifs-educore-info-label"><?php esc_html_e( "Father's Name", 'ifsedu-school-management' ); ?></div>
                                        <div class="ifs-educore-info-value"><?php echo esc_html( $staff->father_name ?: '—' ); ?></div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="ifs-educore-info-label"><?php esc_html_e( "Mother's Name", 'ifsedu-school-management' ); ?></div>
                                        <div class="ifs-educore-info-value"><?php echo esc_html( $staff->mother_name ?: '—' ); ?></div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="ifs-educore-info-label"><?php esc_html_e( 'Blood Group', 'ifsedu-school-management' ); ?></div>
                                        <div class="ifs-educore-info-value text-danger fw-bold"><?php echo esc_html( $staff->blood_group ?: 'N/A' ); ?></div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="ifs-educore-info-label"><?php esc_html_e( 'Quota Category', 'ifsedu-school-management' ); ?></div>
                                        <div class="ifs-educore-info-value"><?php echo esc_html( $staff->quota_type ?: 'General' ); ?></div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="ifs-educore-info-label"><?php esc_html_e( 'Linked WP User', 'ifsedu-school-management' ); ?></div>
                                        <div class="ifs-educore-info-value"><?php echo ( isset( $staff->wp_user_id ) && $staff->wp_user_id ) ? 'User #' . absint( $staff->wp_user_id ) : 'Unlinked'; ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Academic & Service -->
                        <div class="col-12">
                            <div class="ifs-educore-bento-card">
                                <div class="ifs-educore-bento-section-title">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 10v6M2 10l10-5 10 5-10 5"></path><path d="M6 12v5c3 3 9 3 12 0v-5"></path></svg>
                                    <?php esc_html_e( 'Academic & Service Portfolio', 'ifsedu-school-management' ); ?>
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="ifs-educore-info-label"><?php esc_html_e( 'Subject Expertise', 'ifsedu-school-management' ); ?></div>
                                        <div class="ifs-educore-info-value text-success fw-bold"><?php echo esc_html( $staff->subject_expert ?: '—' ); ?></div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="ifs-educore-info-label"><?php esc_html_e( 'Highest Qualification', 'ifsedu-school-management' ); ?></div>
                                        <div class="ifs-educore-info-value"><?php echo esc_html( $staff->highest_degree ?: '—' ); ?></div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="ifs-educore-info-label"><?php esc_html_e( 'Joining Date', 'ifsedu-school-management' ); ?></div>
                                        <div class="ifs-educore-info-value"><?php echo esc_html( $joining_date ); ?></div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="ifs-educore-info-label"><?php esc_html_e( 'Pay Scale Grade', 'ifsedu-school-management' ); ?></div>
                                        <div class="ifs-educore-info-value"><?php echo esc_html( $staff->pay_grade ?: '—' ); ?></div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="ifs-educore-info-label"><?php esc_html_e( 'Gross Monthly Salary', 'ifsedu-school-management' ); ?></div>
                                        <div class="ifs-educore-info-value text-success fw-bold">৳<?php echo esc_html( $salary ); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Addresses -->
                        <div class="col-12">
                            <div class="ifs-educore-bento-card">
                                <div class="ifs-educore-bento-section-title">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                                    <?php esc_html_e( 'Residential Address Records', 'ifsedu-school-management' ); ?>
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="ifs-educore-info-label"><?php esc_html_e( 'Present Address', 'ifsedu-school-management' ); ?></div>
                                        <div class="ifs-educore-address-box"><?php echo nl2br( esc_html( $staff->address ?: '—' ) ); ?></div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="ifs-educore-info-label"><?php esc_html_e( 'Permanent Address', 'ifsedu-school-management' ); ?></div>
                                        <div class="ifs-educore-address-box"><?php echo nl2br( esc_html( $staff->permanent_address ?: '—' ) ); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Contact & Emergency -->
                <div class="col-lg-4">
                    <div class="row g-4">
                        <div class="col-12">
                            <div class="ifs-educore-bento-card">
                                <div class="ifs-educore-bento-section-title">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.7 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                                    <?php esc_html_e( 'Direct Contact Channels', 'ifsedu-school-management' ); ?>
                                </div>
                                <div class="mb-3">
                                    <div class="ifs-educore-info-label"><?php esc_html_e( 'Mobile Phone', 'ifsedu-school-management' ); ?></div>
                                    <div class="ifs-educore-info-value"><a href="tel:<?php echo esc_attr( $staff->phone ); ?>" class="text-decoration-none text-dark fw-bold"><?php echo esc_html( $staff->phone ); ?></a></div>
                                </div>
                                <?php if ( ! empty( $staff->whatsapp_no ) ) : ?>
                                    <div class="mb-3">
                                        <div class="ifs-educore-info-label"><?php esc_html_e( 'WhatsApp Number', 'ifsedu-school-management' ); ?></div>
                                        <div class="ifs-educore-info-value"><a href="https://wa.me/<?php echo esc_attr( preg_replace( '/[^0-9]/', '', (string) $staff->whatsapp_no ) ); ?>" target="_blank" class="text-decoration-none text-success fw-bold"><?php echo esc_html( $staff->whatsapp_no ); ?></a></div>
                                    </div>
                                <?php endif; ?>
                                <div class="mb-2">
                                    <div class="ifs-educore-info-label"><?php esc_html_e( 'Email Address', 'ifsedu-school-management' ); ?></div>
                                    <div class="ifs-educore-info-value"><?php echo esc_html( $staff->email ?: '—' ); ?></div>
                                </div>
                            </div>
                        </div>

                        <!-- Emergency -->
                        <div class="col-12">
                            <div class="ifs-educore-bento-card ifs-educore-emergency-card">
                                <div class="ifs-educore-bento-section-title ifs-educore-emergency-title">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                                    <?php esc_html_e( 'Emergency Contact', 'ifsedu-school-management' ); ?>
                                </div>
                                <div class="mb-2">
                                    <div class="ifs-educore-info-label"><?php esc_html_e( 'Contact Person', 'ifsedu-school-management' ); ?></div>
                                    <div class="ifs-educore-info-value fw-bold"><?php echo esc_html( $staff->emergency_name ?: '—' ); ?></div>
                                </div>
                                <div class="mb-2">
                                    <div class="ifs-educore-info-label"><?php esc_html_e( 'Relationship', 'ifsedu-school-management' ); ?></div>
                                    <div class="ifs-educore-info-value"><?php echo esc_html( $staff->emergency_relation ?: '—' ); ?></div>
                                </div>
                                <div>
                                    <div class="ifs-educore-info-label"><?php esc_html_e( 'Phone Number', 'ifsedu-school-management' ); ?></div>
                                    <div class="ifs-educore-info-value text-danger fw-bold"><?php echo esc_html( $staff->emergency_phone ?: '—' ); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        <!-- ======================================================== -->
        <!-- TAB 2: STAFF ATTENDANCE REPORT & MONTHLY MATRIX -->
        <!-- ======================================================== -->
        <?php elseif ( 'attendance' === $active_subtab ) : ?>
            <div class="ifs-educore-bento-card">
                <div class="ifs-educore-bento-section-title">
                    <span class="dashicons dashicons-calendar-alt" style="font-size: 20px;"></span>
                    <?php printf( esc_html__( 'Staff Attendance Report & Monthly Matrix: %s', 'ifsedu-school-management' ), esc_html( $cal_year ) ); ?>
                </div>

                <!-- Year Selection Bar & Status Legend -->
                <div class="ifs-educore-cal-year-bar no-print">
                    <div class="ifs-educore-cal-legend">
                        <span><span class="legend-dot legend-dot-present"></span><?php esc_html_e( 'Present (P)', 'ifsedu-school-management' ); ?></span>
                        <span><span class="legend-dot legend-dot-absent"></span><?php esc_html_e( 'Absent (A)', 'ifsedu-school-management' ); ?></span>
                        <span><span class="legend-dot legend-dot-leave"></span><?php esc_html_e( 'Leave (L)', 'ifsedu-school-management' ); ?></span>
                        <span><span class="legend-dot legend-dot-holiday"></span><?php esc_html_e( 'Holiday / Off (H)', 'ifsedu-school-management' ); ?></span>
                    </div>
                    <div>
                        <label class="fw-bold me-2" style="font-size: 13px;"><?php esc_html_e( 'Report Year:', 'ifsedu-school-management' ); ?></label>
                        <select onchange="window.location.href='<?php echo esc_url( add_query_arg( array( 'subtab' => 'attendance' ), $base_profile_url ) ); ?>&cal_year=' + this.value;" class="form-select form-select-sm d-inline-block w-auto">
                            <?php for ( $y = (int) gmdate( 'Y' ) - 2; $y <= (int) gmdate( 'Y' ) + 4; $y++ ) : ?>
                                <option value="<?php echo esc_attr( $y ); ?>" <?php selected( $cal_year, $y ); ?>><?php echo esc_html( $y ); ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>

                <?php
                $total_present = 0;
                $total_absent  = 0;
                $total_leave   = 0;
                $total_holiday = count( $institutional_holidays );
                
                for ( $m = 1; $m <= 12; $m++ ) {
                    $days_in_m = cal_days_in_month( CAL_GREGORIAN, $m, $cal_year );
                    for ( $d = 1; $d <= $days_in_m; $d++ ) {
                        $date_str = sprintf( '%04d-%02d-%02d', $cal_year, $m, $d );
                        if ( isset( $institutional_holidays[ $date_str ] ) ) {
                            continue; // Skip counting holidays as working days
                        }

                        if ( isset( $attendance_map[ $date_str ] ) ) {
                            $status = $attendance_map[ $date_str ];
                            if ( 'Present' === $status ) {
                                $total_present++;
                            } elseif ( 'Absent' === $status ) {
                                $total_absent++;
                            } elseif ( 'Leave' === $status ) {
                                $total_leave++;
                            }
                        }
                    }
                }
                ?>

                <!-- Attendance Summary Counter Cards -->
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="p-3 bg-light border rounded-3 text-center">
                            <div class="text-muted small text-uppercase fw-bold"><?php esc_html_e( 'Total Present', 'ifsedu-school-management' ); ?></div>
                            <div class="fs-4 fw-extrabold text-success"><?php echo esc_html( $total_present ); ?></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3 bg-light border rounded-3 text-center">
                            <div class="text-muted small text-uppercase fw-bold"><?php esc_html_e( 'Total Absent', 'ifsedu-school-management' ); ?></div>
                            <div class="fs-4 fw-extrabold text-danger"><?php echo esc_html( $total_absent ); ?></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3 bg-light border rounded-3 text-center">
                            <div class="text-muted small text-uppercase fw-bold"><?php esc_html_e( 'Approved Leave', 'ifsedu-school-management' ); ?></div>
                            <div class="fs-4 fw-extrabold text-warning"><?php echo esc_html( $total_leave ); ?></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3 bg-light border rounded-3 text-center">
                            <div class="text-muted small text-uppercase fw-bold"><?php esc_html_e( 'Holidays / Off', 'ifsedu-school-management' ); ?></div>
                            <div class="fs-4 fw-extrabold text-secondary"><?php echo esc_html( $total_holiday ); ?></div>
                        </div>
                    </div>
                </div>

                <!-- 12 Months Individual Attendance Grid Layout -->
                <div class="ifs-educore-cal-container-12">
                    <?php
                    $month_names = array(
                        1  => __( 'January', 'ifsedu-school-management' ),
                        2  => __( 'February', 'ifsedu-school-management' ),
                        3  => __( 'March', 'ifsedu-school-management' ),
                        4  => __( 'April', 'ifsedu-school-management' ),
                        5  => __( 'May', 'ifsedu-school-management' ),
                        6  => __( 'June', 'ifsedu-school-management' ),
                        7  => __( 'July', 'ifsedu-school-management' ),
                        8  => __( 'August', 'ifsedu-school-management' ),
                        9  => __( 'September', 'ifsedu-school-management' ),
                        10 => __( 'October', 'ifsedu-school-management' ),
                        11 => __( 'November', 'ifsedu-school-management' ),
                        12 => __( 'December', 'ifsedu-school-management' ),
                    );

                    for ( $m = 1; $m <= 12; $m++ ) :
                        $first_day_of_month = mktime( 0, 0, 0, $m, 1, $cal_year );
                        $days_in_this_month = cal_days_in_month( CAL_GREGORIAN, $m, $cal_year );
                        $start_weekday      = date( 'w', $first_day_of_month );
                    ?>
                        <div class="ifs-educore-cal-month-box">
                            <div class="ifs-educore-cal-m-title"><?php echo esc_html( $month_names[ $m ] ); ?></div>
                            <div class="ifs-educore-cal-wk-row">
                                <span>Su</span><span>Mo</span><span>Tu</span><span>We</span><span>Th</span><span>Fr</span><span>Sa</span>
                            </div>
                            <div class="ifs-educore-cal-days-row">
                                <?php
                                for ( $b = 0; $b < $start_weekday; $b++ ) {
                                    echo '<div class="ifs-educore-cal-cell is-empty"></div>';
                                }

                                for ( $d = 1; $d <= $days_in_this_month; $d++ ) {
                                    $date_str = sprintf( '%04d-%02d-%02d', $cal_year, $m, $d );
                                    $is_off   = isset( $institutional_holidays[ $date_str ] );
                                    
                                    if ( $is_off ) {
                                        $cell_cls = 'cal-status-holiday';
                                        $tooltip  = $date_str . ': [Holiday] ' . $institutional_holidays[ $date_str ];
                                    } elseif ( isset( $attendance_map[ $date_str ] ) ) {
                                        $status = $attendance_map[ $date_str ];
                                        if ( 'Present' === $status ) {
                                            $cell_cls = 'cal-status-present';
                                            $tooltip  = $date_str . ': Present';
                                        } elseif ( 'Absent' === $status ) {
                                            $cell_cls = 'cal-status-absent';
                                            $tooltip  = $date_str . ': Absent';
                                        } else {
                                            $cell_cls = 'cal-status-leave';
                                            $tooltip  = $date_str . ': Leave';
                                        }
                                    } else {
                                        $cell_cls = ''; // Unrecorded / Pending log
                                        $tooltip  = $date_str . ': No log';
                                    }
                                    ?>
                                    <div class="ifs-educore-cal-cell <?php echo esc_attr( $cell_cls ); ?>" title="<?php echo esc_attr( $tooltip ); ?>">
                                        <span><?php echo esc_html( $d ); ?></span>
                                    </div>
                                    <?php
                                }
                                ?>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>

        <!-- ======================================================== -->
        <!-- TAB 3: SALARY & PAYROLL DETAILS -->
        <!-- ======================================================== -->
        <?php elseif ( 'salary' === $active_subtab ) : ?>
            <div class="row g-4">
                <div class="col-lg-7">
                    <div class="ifs-educore-bento-card">
                        <div class="ifs-educore-bento-section-title">
                            <span class="dashicons dashicons-money-alt" style="font-size: 20px;"></span>
                            <?php esc_html_e( 'Payroll Structure & Bank Routing', 'ifsedu-school-management' ); ?>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="ifs-educore-info-label"><?php esc_html_e( 'Pay Scale Grade', 'ifsedu-school-management' ); ?></div>
                                <div class="ifs-educore-info-value"><?php echo esc_html( $staff->pay_grade ?: '—' ); ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="ifs-educore-info-label"><?php esc_html_e( 'Base Monthly Salary', 'ifsedu-school-management' ); ?></div>
                                <div class="ifs-educore-info-value text-success">৳<?php echo esc_html( $salary ); ?></div>
                            </div>
                            <div class="col-md-12">
                                <hr class="text-muted">
                            </div>
                            <div class="col-md-6">
                                <div class="ifs-educore-info-label"><?php esc_html_e( 'Bank Name', 'ifsedu-school-management' ); ?></div>
                                <div class="ifs-educore-info-value"><?php echo esc_html( $staff->bank_name ?: '—' ); ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="ifs-educore-info-label"><?php esc_html_e( 'Bank Account Number', 'ifsedu-school-management' ); ?></div>
                                <div class="ifs-educore-info-value font-monospace bg-light p-2 rounded text-dark border"><?php echo esc_html( $staff->bank_acc_no ?: '—' ); ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="ifs-educore-info-label"><?php esc_html_e( 'Routing Number', 'ifsedu-school-management' ); ?></div>
                                <div class="ifs-educore-info-value font-monospace"><?php echo esc_html( $staff->bank_routing ?: '—' ); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-5">
                    <div class="ifs-educore-bento-card">
                        <div class="ifs-educore-bento-section-title">
                            <?php esc_html_e( 'Disbursal Records', 'ifsedu-school-management' ); ?>
                        </div>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <span><?php esc_html_e( 'Current Salary Status', 'ifsedu-school-management' ); ?></span>
                                <span class="badge bg-success rounded-pill"><?php esc_html_e( 'Active & Verified', 'ifsedu-school-management' ); ?></span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

        <!-- ======================================================== -->
        <!-- TAB 4: DUTY PROXY RECORDS -->
        <!-- ======================================================== -->
        <?php elseif ( 'proxy' === $active_subtab ) : ?>
            <div class="ifs-educore-bento-card">
                <div class="ifs-educore-bento-section-title">
                    <span class="dashicons dashicons-randomize" style="font-size: 20px;"></span>
                    <?php esc_html_e( 'Assigned Class & Duty Proxies', 'ifsedu-school-management' ); ?>
                </div>
                <p class="text-muted small mb-3"><?php esc_html_e( 'Active substitution and proxy duties assigned to this staff member from the database.', 'ifsedu-school-management' ); ?></p>
                
                <table class="table table-striped table-bordered align-middle m-0">
                    <thead class="table-light">
                        <tr>
                            <th><?php esc_html_e( 'Date', 'ifsedu-school-management' ); ?></th>
                            <th><?php esc_html_e( 'Period / Slot', 'ifsedu-school-management' ); ?></th>
                            <th><?php esc_html_e( 'Original Teacher', 'ifsedu-school-management' ); ?></th>
                            <th><?php esc_html_e( 'Class / Section', 'ifsedu-school-management' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'ifsedu-school-management' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( ! empty( $proxy_records ) ) : ?>
                            <?php foreach ( $proxy_records as $proxy ) : ?>
                                <tr>
                                    <td><?php echo esc_html( date_i18n( 'd M, Y', strtotime( $proxy->proxy_date ) ) ); ?></td>
                                    <td><?php echo esc_html( $proxy->period_slot ); ?></td>
                                    <td><?php echo esc_html( $proxy->original_teacher ); ?></td>
                                    <td><?php echo esc_html( $proxy->class_section ); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo ( 'Completed' === $proxy->status ) ? 'success' : 'warning text-dark'; ?>">
                                            <?php echo esc_html( $proxy->status ); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-3">
                                    <?php esc_html_e( 'No duty proxy records found for this staff member.', 'ifsedu-school-management' ); ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    </div>
    <?php
}
<?php
/**
 * Institutional Academic Defaults, Session & Interactive 12-Month Holiday Calendar with Reason Management
 * File: inc/settings/academics.php
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Render Academic Settings & 12-Month Calendar View
 *
 * @param string $base_url Base URL for settings subtabs.
 */
function educore_render_settings_academics_view( $base_url ) {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient administrative permissions to manage academic settings.', 'ifsedu-school-management' ) );
    }

    $settings_updated = false;
    $req_method       = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';

    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $calendar_year = isset( $_GET['cal_year'] ) ? absint( wp_unslash( $_GET['cal_year'] ) ) : absint( get_option( 'educore_academic_year', gmdate( 'Y' ) ) );
    if ( 2000 > $calendar_year || 2099 < $calendar_year ) {
        $calendar_year = absint( gmdate( 'Y' ) );
    }
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    if ( 'POST' === $req_method && isset( $_POST['educore_save_academic_settings'] ) ) {
        if ( ! isset( $_POST['educore_academic_settings_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['educore_academic_settings_nonce'] ) ), 'save_academic_settings_action' ) ) {
            wp_die( esc_html__( 'Security check failed.', 'ifsedu-school-management' ) );
        }

        $academic_year        = isset( $_POST['academic_year'] ) ? sanitize_text_field( wp_unslash( $_POST['academic_year'] ) ) : gmdate( 'Y' );
        $default_shift        = isset( $_POST['default_shift'] ) ? sanitize_text_field( wp_unslash( $_POST['default_shift'] ) ) : 'Morning';
        $passing_grade_scale  = isset( $_POST['passing_grade_scale'] ) ? sanitize_text_field( wp_unslash( $_POST['passing_grade_scale'] ) ) : 'National Standard (GPA 5.0)';
        $attendance_threshold = isset( $_POST['attendance_threshold'] ) ? absint( wp_unslash( $_POST['attendance_threshold'] ) ) : 75;

        // Custom Holiday Dates with Reasons JSON.
        $raw_off_dates_json = isset( $_POST['academic_off_dates_json'] ) ? wp_unslash( $_POST['academic_off_dates_json'] ) : '';
        $decoded_map        = json_decode( $raw_off_dates_json, true );
        $sanitized_map      = array();

        if ( is_array( $decoded_map ) ) {
            foreach ( $decoded_map as $date_key => $reason_val ) {
                $clean_date   = sanitize_text_field( $date_key );
                $clean_reason = sanitize_text_field( $reason_val );
                if ( ! empty( $clean_date ) ) {
                    $sanitized_map[ $clean_date ] = ! empty( $clean_reason ) ? $clean_reason : __( 'Holiday', 'ifsedu-school-management' );
                }
            }
        }

        update_option( 'educore_academic_year', $academic_year );
        update_option( 'educore_default_shift', $default_shift );
        update_option( 'educore_passing_grade_scale', $passing_grade_scale );
        update_option( 'educore_attendance_threshold', $attendance_threshold );
        update_option( 'educore_academic_off_dates_' . $calendar_year, $sanitized_map );

        if ( function_exists( 'educore_log_activity' ) ) {
            educore_log_activity( __( 'Updated academic setup criteria and 12-month holiday calendar with holiday reasons.', 'ifsedu-school-management' ) );
        }

        $settings_updated = true;
    }

    $academic_year        = get_option( 'educore_academic_year', gmdate( 'Y' ) );
    $default_shift        = get_option( 'educore_default_shift', 'Morning' );
    $passing_grade_scale  = get_option( 'educore_passing_grade_scale', 'National Standard (GPA 5.0)' );
    $attendance_threshold = get_option( 'educore_attendance_threshold', 75 );

    // Retrieve Saved Custom Off Days for this selected year.
    $saved_off_days_map = get_option( 'educore_academic_off_dates_' . $calendar_year, null );

    // First time initialization: populate Friday & Saturday as default "Weekly Holiday".
    if ( is_null( $saved_off_days_map ) || ! is_array( $saved_off_days_map ) ) {
        $saved_off_days_map = array();
        for ( $m = 1; $m <= 12; $m++ ) {
            $days_in_month = cal_days_in_month( CAL_GREGORIAN, $m, $calendar_year );
            for ( $d = 1; $d <= $days_in_month; $d++ ) {
                $date_str    = sprintf( '%04d-%02d-%02d', $calendar_year, $m, $d );
                $day_of_week = (int) gmdate( 'w', strtotime( $date_str ) ); // 5 = Friday, 6 = Saturday.
                if ( 5 === $day_of_week || 6 === $day_of_week ) {
                    $saved_off_days_map[ $date_str ] = __( 'Weekly Holiday', 'ifsedu-school-management' );
                }
            }
        }
    }
    $saved_off_days_json = wp_json_encode( $saved_off_days_map );
    ?>

    <style id="ifs-educore-academics-styles">
        .ifs-educore-academics-root {
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #0f172a;
        }

        .ifs-educore-alert {
            background: #ecfdf5;
            border-left: 4px solid #10b981;
            color: #065f46;
            padding: 12px 18px;
            border-radius: 8px;
            font-weight: 700;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .ifs-educore-settings-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.03);
            margin-bottom: 30px;
        }

        .ifs-educore-grid-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }

        @media (max-width: 768px) {
            .ifs-educore-grid-row {
                grid-template-columns: 1fr;
            }
        }

        .ifs-educore-field-node {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .ifs-educore-label {
            font-size: 12.5px;
            font-weight: 700;
            color: #334155;
            letter-spacing: -0.1px;
        }

        .ifs-educore-required {
            color: #ef4444;
        }

        .ifs-educore-input {
            width: 100%;
            border: 1.5px solid #cbd5e1;
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 13.5px;
            color: #0f172a;
            background-color: #f8fafc;
            box-sizing: border-box;
            outline: none;
            transition: all 0.2s ease;
        }

        input.ifs-educore-input,
        select.ifs-educore-input {
            height: 42px;
        }

        .ifs-educore-input:focus {
            border-color: #00523c;
            background-color: #ffffff;
            box-shadow: 0 0 0 3px rgba(0, 82, 60, 0.1);
        }

        .ifs-educore-help-text {
            font-size: 11.5px;
            color: #64748b;
        }

        .ifs-educore-academics-section-divider {
            margin-top: 36px;
            border-top: 2px solid #f1f5f9;
            padding-top: 24px;
        }

        .ifs-educore-academics-header-flex {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 16px;
        }

        .ifs-educore-academics-title {
            margin: 0 0 4px 0;
            font-size: 17px;
            font-weight: 800;
            color: #00523c;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .ifs-educore-calendar-icon {
            color: #00523c;
        }

        .ifs-educore-academics-subtitle {
            font-size: 12.5px;
            color: #64748b;
            font-weight: 600;
        }

        .ifs-educore-year-control-wrap {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .ifs-educore-year-label {
            font-size: 13px;
            font-weight: 700;
            color: #334155;
        }

        .ifs-educore-year-select {
            width: 100px;
            height: 38px;
            padding: 0 10px;
        }

        .ifs-educore-off-days-pill {
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12.5px;
            font-weight: 800;
        }

        /* 12 Months Calendar Grid */
        .ifs-educore-cal-grid-12 {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }

        .ifs-educore-cal-month-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 16px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
        }

        .ifs-educore-cal-month-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            font-size: 14px;
            font-weight: 800;
            color: #0f172a;
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 8px;
        }

        .ifs-educore-cal-month-year-small {
            font-size: 11.5px;
            color: #64748b;
            font-weight: 600;
        }

        .ifs-educore-cal-weekdays {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            text-align: center;
            font-size: 11px;
            font-weight: 800;
            color: #64748b;
            margin-bottom: 6px;
            text-transform: uppercase;
        }

        .ifs-educore-weekend-hdr {
            color: #dc2626;
        }

        .ifs-educore-cal-days-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 3px;
        }

        .ifs-educore-cal-day-cell {
            aspect-ratio: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 700;
            color: #334155;
            background: #f8fafc;
            border-radius: 6px;
            cursor: pointer;
            border: 1px solid transparent;
            user-select: none;
            transition: all 0.15s ease;
        }

        .ifs-educore-cal-day-cell:hover {
            background: #e2e8f0;
            border-color: #cbd5e1;
        }

        .ifs-educore-cal-day-cell.ifs-educore-is-empty {
            background: transparent;
            cursor: default;
            border: none;
        }

        .ifs-educore-cal-day-cell.ifs-educore-is-off-day {
            background: #fef2f2;
            color: #dc2626;
            border-color: #fecaca;
            font-weight: 900;
        }

        .ifs-educore-cal-day-cell.ifs-educore-is-off-day:hover {
            background: #fee2e2;
            border-color: #f87171;
        }

        .ifs-educore-academics-footer-divider {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: flex-end;
        }

        .ifs-educore-btn-submit {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            height: 42px;
            padding: 0 24px;
            background: #00523c;
            color: #ffffff;
            font-size: 13.5px;
            font-weight: 800;
            border-radius: 10px;
            border: none;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0, 82, 60, 0.25);
            transition: all 0.2s ease;
        }

        .ifs-educore-btn-submit:hover {
            background: #003e2d;
        }

        /* Modal Styles */
        .ifs-educore-cal-modal-backdrop {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            z-index: 100000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .ifs-educore-cal-modal-card {
            background: #ffffff;
            border-radius: 16px;
            width: 100%;
            max-width: 440px;
            padding: 28px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            animation: ifsEducoreModalPopup 0.25s ease-out;
        }

        @keyframes ifsEducoreModalPopup {
            from { opacity: 0; transform: translateY(10px) scale(0.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .ifs-educore-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #f1f5f9;
            padding-bottom: 14px;
        }

        .ifs-educore-modal-title-wrap {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .ifs-educore-modal-icon-badge {
            width: 40px;
            height: 40px;
            background: #ecfdf5;
            color: #00523c;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        .ifs-educore-modal-title-text {
            margin: 0;
            font-size: 16px;
            font-weight: 800;
            color: #0f172a;
        }

        .ifs-educore-modal-subtitle-text {
            font-size: 12px;
            color: #64748b;
            font-weight: 600;
        }

        .ifs-educore-modal-close-btn {
            background: #f1f5f9;
            border: none;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            font-size: 18px;
            font-weight: 700;
            color: #64748b;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }

        .ifs-educore-modal-close-btn:hover {
            background: #e2e8f0;
            color: #0f172a;
        }

        .ifs-educore-date-banner {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 12px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }

        .ifs-educore-date-banner-left {
            display: flex;
            flex-direction: column;
        }

        .ifs-educore-date-banner-title {
            font-size: 15px;
            font-weight: 800;
            color: #0f172a;
        }

        .ifs-educore-date-banner-day {
            font-size: 12px;
            color: #64748b;
            font-weight: 600;
        }

        .ifs-educore-date-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11.5px;
            font-weight: 800;
        }

        .ifs-educore-badge-open { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
        .ifs-educore-badge-off { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }

        .ifs-educore-hidden-status-select {
            display: none;
        }

        .ifs-educore-status-switch-group {
            display: flex;
            background: #f1f5f9;
            padding: 4px;
            border-radius: 10px;
            gap: 4px;
            margin-bottom: 16px;
        }

        .ifs-educore-status-option {
            flex: 1;
            text-align: center;
            padding: 10px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            color: #64748b;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: all 0.2s ease;
        }

        .ifs-educore-status-option .dashicons {
            font-size: 16px;
            width: 16px;
            height: 16px;
        }

        .ifs-educore-status-option.active {
            background: #ffffff;
            color: #00523c;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
        }

        .ifs-educore-reason-label-wrap {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px;
        }

        .ifs-educore-reason-hint {
            font-size: 11px;
            color: #94a3b8;
            font-weight: 600;
        }

        .ifs-educore-reason-input {
            margin-bottom: 12px;
        }

        .ifs-educore-presets-label {
            font-size: 11.5px;
            font-weight: 700;
            color: #64748b;
            margin-bottom: 6px;
            display: block;
        }

        .ifs-educore-reason-chips-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-bottom: 24px;
        }

        .ifs-educore-reason-preset-btn {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            color: #334155;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 11.5px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .ifs-educore-reason-preset-btn:hover {
            background: #f1f5f9;
            border-color: #94a3b8;
            color: #0f172a;
        }

        .ifs-educore-modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            border-top: 1px solid #e2e8f0;
            padding-top: 16px;
        }

        .ifs-educore-btn-cancel {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #cbd5e1;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .ifs-educore-btn-cancel:hover {
            background: #e2e8f0;
            color: #0f172a;
        }

        .ifs-educore-btn-apply {
            background: #00523c;
            color: #ffffff;
            border: none;
            padding: 8px 20px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 4px 12px rgba(0, 82, 60, 0.2);
            transition: all 0.2s ease;
        }

        .ifs-educore-btn-apply:hover {
            background: #047857;
        }
    </style>

    <div class="ifs-educore-academics-root">
        <?php if ( $settings_updated ) : ?>
            <div class="ifs-educore-alert">
                <span class="dashicons dashicons-yes-alt"></span>
                <?php esc_html_e( 'Academic setup and holiday calendar updated successfully.', 'ifsedu-school-management' ); ?>
            </div>
        <?php endif; ?>

        <div class="ifs-educore-settings-card">
            <form method="POST" action="">
                <?php wp_nonce_field( 'save_academic_settings_action', 'educore_academic_settings_nonce' ); ?>
                <input type="hidden" name="academic_off_dates_json" id="ifs_academic_off_dates_json" value="<?php echo esc_attr( $saved_off_dates_json ); ?>">

                <!-- Global Academic Parameters -->
                <div class="ifs-educore-grid-row">
                    <div class="ifs-educore-field-node">
                        <label class="ifs-educore-label">
                            <?php esc_html_e( 'Current Academic Session / Year', 'ifsedu-school-management' ); ?> <span class="ifs-educore-required">*</span>
                        </label>
                        <input type="text" name="academic_year" class="ifs-educore-input" value="<?php echo esc_attr( $academic_year ); ?>" required placeholder="<?php echo esc_attr( gmdate( 'Y' ) ); ?>">
                        <span class="ifs-educore-help-text"><?php esc_html_e( 'e.g. 2026 or 2026-2027', 'ifsedu-school-management' ); ?></span>
                    </div>

                    <div class="ifs-educore-field-node">
                        <label class="ifs-educore-label">
                            <?php esc_html_e( 'Default Academic Shift', 'ifsedu-school-management' ); ?>
                        </label>
                        <select name="default_shift" class="ifs-educore-input">
                            <option value="Morning" <?php selected( $default_shift, 'Morning' ); ?>><?php esc_html_e( 'Morning Shift', 'ifsedu-school-management' ); ?></option>
                            <option value="Day" <?php selected( $default_shift, 'Day' ); ?>><?php esc_html_e( 'Day Shift', 'ifsedu-school-management' ); ?></option>
                            <option value="Combined" <?php selected( $default_shift, 'Combined' ); ?>><?php esc_html_e( 'Combined / Single Shift', 'ifsedu-school-management' ); ?></option>
                        </select>
                    </div>
                </div>

                <div class="ifs-educore-grid-row">
                    <div class="ifs-educore-field-node">
                        <label class="ifs-educore-label">
                            <?php esc_html_e( 'Grading System Benchmark', 'ifsedu-school-management' ); ?>
                        </label>
                        <input type="text" name="passing_grade_scale" class="ifs-educore-input" value="<?php echo esc_attr( $passing_grade_scale ); ?>">
                        <span class="ifs-educore-help-text"><?php esc_html_e( 'Default grading scale applied across report cards.', 'ifsedu-school-management' ); ?></span>
                    </div>

                    <div class="ifs-educore-field-node">
                        <label class="ifs-educore-label">
                            <?php esc_html_e( 'Minimum Exam Eligibility Attendance (%)', 'ifsedu-school-management' ); ?>
                        </label>
                        <input type="number" min="1" max="100" name="attendance_threshold" class="ifs-educore-input" value="<?php echo esc_attr( $attendance_threshold ); ?>">
                        <span class="ifs-educore-help-text"><?php esc_html_e( 'Minimum attendance required for exam admit card issuance.', 'ifsedu-school-management' ); ?></span>
                    </div>
                </div>

                <!-- 12-Month Academic Year Holiday & Off-Days Calendar -->
                <div class="ifs-educore-academics-section-divider">
                    <div class="ifs-educore-academics-header-flex">
                        <div>
                            <h4 class="ifs-educore-academics-title">
                                <span class="dashicons dashicons-calendar-alt ifs-educore-calendar-icon"></span>
                                <?php printf( esc_html__( 'Academic Calendar & Off-Days: %s', 'ifsedu-school-management' ), esc_html( $calendar_year ) ); ?>
                            </h4>
                            <small class="ifs-educore-academics-subtitle">
                                <?php esc_html_e( 'Friday & Saturday default to Weekly Holiday. Click any date to edit holiday reason or toggle status.', 'ifsedu-school-management' ); ?>
                            </small>
                        </div>

                        <!-- Change Year Selector -->
                        <div class="ifs-educore-year-control-wrap">
                            <label class="ifs-educore-year-label"><?php esc_html_e( 'View Year:', 'ifsedu-school-management' ); ?></label>
                            <select onchange="window.location.href='<?php echo esc_url( add_query_arg( array( 'subtab' => 'academics' ), $base_url ) ); ?>&cal_year=' + this.value;" class="ifs-educore-input ifs-educore-year-select">
                                <?php for ( $y = (int) gmdate( 'Y' ) - 2; $y <= (int) gmdate( 'Y' ) + 4; $y++ ) : ?>
                                    <option value="<?php echo esc_attr( $y ); ?>" <?php selected( $calendar_year, $y ); ?>><?php echo esc_html( $y ); ?></option>
                                <?php endfor; ?>
                            </select>
                            <span class="ifs-educore-off-days-pill" id="ifs_total_off_days_pill">
                                <?php echo count( (array) $saved_off_days_map ); ?> <?php esc_html_e( 'Total Off Days', 'ifsedu-school-management' ); ?>
                            </span>
                        </div>
                    </div>

                    <!-- 12 Months Grid Display -->
                    <div class="ifs-educore-cal-grid-12">
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
                            $first_day_of_month = gmmktime( 0, 0, 0, $m, 1, $calendar_year );
                            $days_in_this_month = cal_days_in_month( CAL_GREGORIAN, $m, $calendar_year );
                            $start_weekday      = (int) gmdate( 'w', $first_day_of_month ); // 0 (Sun) to 6 (Sat).
                        ?>
                            <div class="ifs-educore-cal-month-card">
                                <div class="ifs-educore-cal-month-header">
                                    <span><?php echo esc_html( $month_names[ $m ] ); ?></span>
                                    <small class="ifs-educore-cal-month-year-small"><?php echo esc_html( $calendar_year ); ?></small>
                                </div>

                                <div class="ifs-educore-cal-weekdays">
                                    <span>Su</span><span>Mo</span><span>Tu</span><span>We</span><span>Th</span>
                                    <span class="ifs-educore-weekend-hdr">Fr</span>
                                    <span class="ifs-educore-weekend-hdr">Sa</span>
                                </div>

                                <div class="ifs-educore-cal-days-grid">
                                    <?php
                                    for ( $blank = 0; $blank < $start_weekday; $blank++ ) {
                                        echo '<div class="ifs-educore-cal-day-cell ifs-educore-is-empty"></div>';
                                    }

                                    for ( $d = 1; $d <= $days_in_this_month; $d++ ) {
                                        $current_date_str = sprintf( '%04d-%02d-%02d', $calendar_year, $m, $d );
                                        $is_off           = isset( $saved_off_days_map[ $current_date_str ] );
                                        $reason           = $is_off ? $saved_off_days_map[ $current_date_str ] : '';
                                        $tooltip          = $current_date_str . ( $is_off ? ' (' . $reason . ')' : '' );
                                    ?>
                                        <div class="ifs-educore-cal-day-cell <?php echo $is_off ? 'ifs-educore-is-off-day' : ''; ?>" 
                                             data-date="<?php echo esc_attr( $current_date_str ); ?>" 
                                             data-reason="<?php echo esc_attr( $reason ); ?>"
                                             title="<?php echo esc_attr( $tooltip ); ?>">
                                            <?php echo esc_html( $d ); ?>
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>

                <div class="ifs-educore-academics-footer-divider">
                    <button type="submit" name="educore_save_academic_settings" class="ifs-educore-btn-submit">
                        <span class="dashicons dashicons-saved"></span>
                        <?php esc_html_e( 'Save Academic Settings & Calendar', 'ifsedu-school-management' ); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Redesigned High-End Modal -->
    <div class="ifs-educore-cal-modal-backdrop" id="ifs_holiday_modal">
        <div class="ifs-educore-cal-modal-card">
            <!-- Modal Header -->
            <div class="ifs-educore-modal-header">
                <div class="ifs-educore-modal-title-wrap">
                    <div class="ifs-educore-modal-icon-badge">
                        <span class="dashicons dashicons-calendar-alt"></span>
                    </div>
                    <div>
                        <h4 class="ifs-educore-modal-title-text">
                            <?php esc_html_e( 'Configure Academic Day', 'ifsedu-school-management' ); ?>
                        </h4>
                        <small class="ifs-educore-modal-subtitle-text"><?php esc_html_e( 'Manage schedule status and reason', 'ifsedu-school-management' ); ?></small>
                    </div>
                </div>
                <button type="button" id="ifs_close_holiday_modal" class="ifs-educore-modal-close-btn">&times;</button>
            </div>

            <!-- Modal Content Body -->
            <div class="ifs-educore-modal-body">
                <!-- Highlighted Date Banner -->
                <div class="ifs-educore-date-banner">
                    <div class="ifs-educore-date-banner-left">
                        <span class="ifs-educore-date-banner-title" id="modal_display_date">YYYY-MM-DD</span>
                        <span class="ifs-educore-date-banner-day" id="modal_display_weekday">Weekday</span>
                    </div>
                    <span class="ifs-educore-date-badge ifs-educore-badge-open" id="modal_display_badge"><?php esc_html_e( 'Academic Day', 'ifsedu-school-management' ); ?></span>
                </div>

                <!-- Hidden native select for JS engine compatibility -->
                <select id="modal_day_status" class="ifs-educore-hidden-status-select">
                    <option value="open"><?php esc_html_e( 'Open Academic Day', 'ifsedu-school-management' ); ?></option>
                    <option value="off"><?php esc_html_e( 'Off / Holiday (Blocked Day)', 'ifsedu-school-management' ); ?></option>
                </select>

                <!-- Interactive Segmented Pill Switcher -->
                <div class="ifs-educore-status-switch-group">
                    <div class="ifs-educore-status-option" data-value="open" id="ifs_switch_open">
                        <span class="dashicons dashicons-yes"></span>
                        <?php esc_html_e( 'Open Day', 'ifsedu-school-management' ); ?>
                    </div>
                    <div class="ifs-educore-status-option" data-value="off" id="ifs_switch_off">
                        <span class="dashicons dashicons-lock"></span>
                        <?php esc_html_e( 'Holiday / Off', 'ifsedu-school-management' ); ?>
                    </div>
                </div>

                <!-- Reason Section with Quick Tags -->
                <div id="modal_reason_wrap">
                    <label class="ifs-educore-label ifs-educore-reason-label-wrap">
                        <span><?php esc_html_e( 'Holiday / Block Reason', 'ifsedu-school-management' ); ?></span>
                        <small class="ifs-educore-reason-hint"><?php esc_html_e( 'Required for off days', 'ifsedu-school-management' ); ?></small>
                    </label>
                    <input type="text" id="modal_holiday_reason" class="ifs-educore-input ifs-educore-reason-input" placeholder="<?php esc_attr_e( 'e.g. National Holiday, Eid Vacation', 'ifsedu-school-management' ); ?>">
                    
                    <div>
                        <span class="ifs-educore-presets-label">
                            <?php esc_html_e( 'Quick Presets:', 'ifsedu-school-management' ); ?>
                        </span>
                        <div class="ifs-educore-reason-chips-grid">
                            <button type="button" class="ifs-educore-reason-preset-btn"><?php esc_html_e( 'Weekly Holiday', 'ifsedu-school-management' ); ?></button>
                            <button type="button" class="ifs-educore-reason-preset-btn"><?php esc_html_e( 'National Holiday', 'ifsedu-school-management' ); ?></button>
                            <button type="button" class="ifs-educore-reason-preset-btn"><?php esc_html_e( 'Eid Vacation', 'ifsedu-school-management' ); ?></button>
                            <button type="button" class="ifs-educore-reason-preset-btn"><?php esc_html_e( 'Puja Vacation', 'ifsedu-school-management' ); ?></button>
                            <button type="button" class="ifs-educore-reason-preset-btn"><?php esc_html_e( 'Summer Vacation', 'ifsedu-school-management' ); ?></button>
                            <button type="button" class="ifs-educore-reason-preset-btn"><?php esc_html_e( 'Winter Vacation', 'ifsedu-school-management' ); ?></button>
                            <button type="button" class="ifs-educore-reason-preset-btn"><?php esc_html_e( 'Exam Preparation', 'ifsedu-school-management' ); ?></button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Action Footer -->
            <div class="ifs-educore-modal-footer">
                <button type="button" id="ifs_cancel_holiday_btn" class="ifs-educore-btn-cancel">
                    <?php esc_html_e( 'Cancel', 'ifsedu-school-management' ); ?>
                </button>
                <button type="button" id="ifs_save_holiday_btn" class="ifs-educore-btn-apply">
                    <span class="dashicons dashicons-saved"></span>
                    <?php esc_html_e( 'Save Status', 'ifsedu-school-management' ); ?>
                </button>
            </div>
        </div>
    </div>

    <!-- Calendar Interactive Script -->
    <script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function() {
        var offDaysMap = <?php echo wp_json_encode( $saved_off_days_map ); ?> || {};
        var currentSelectedDate = '';
        
        var modal = document.getElementById('ifs_holiday_modal');
        var closeBtn = document.getElementById('ifs_close_holiday_modal');
        var cancelBtn = document.getElementById('ifs_cancel_holiday_btn');
        var saveBtn = document.getElementById('ifs_save_holiday_btn');
        
        var displayDateEl = document.getElementById('modal_display_date');
        var displayWeekdayEl = document.getElementById('modal_display_weekday');
        var displayBadgeEl = document.getElementById('modal_display_badge');
        var statusSelect = document.getElementById('modal_day_status');
        var reasonInput = document.getElementById('modal_holiday_reason');
        var reasonWrap = document.getElementById('modal_reason_wrap');
        
        var switchOpen = document.getElementById('ifs_switch_open');
        var switchOff = document.getElementById('ifs_switch_off');
        var hiddenJsonInput = document.getElementById('ifs_academic_off_dates_json');
        var totalOffDaysPill = document.getElementById('ifs_total_off_days_pill');

        function updateModalState(status) {
            if (status === 'off') {
                statusSelect.value = 'off';
                switchOff.classList.add('active');
                switchOpen.classList.remove('active');
                reasonWrap.style.display = 'block';
                displayBadgeEl.textContent = '<?php echo esc_js( __( 'Holiday / Off', 'ifsedu-school-management' ) ); ?>';
                displayBadgeEl.className = 'ifs-educore-date-badge ifs-educore-badge-off';
            } else {
                statusSelect.value = 'open';
                switchOpen.classList.add('active');
                switchOff.classList.remove('active');
                reasonWrap.style.display = 'none';
                displayBadgeEl.textContent = '<?php echo esc_js( __( 'Academic Day', 'ifsedu-school-management' ) ); ?>';
                displayBadgeEl.className = 'ifs-educore-date-badge ifs-educore-badge-open';
            }
        }

        if (switchOpen && switchOff) {
            switchOpen.addEventListener('click', function() { updateModalState('open'); });
            switchOff.addEventListener('click', function() { updateModalState('off'); });
        }

        document.querySelectorAll('.ifs-educore-cal-day-cell:not(.ifs-educore-is-empty)').forEach(function(cell) {
            cell.addEventListener('click', function() {
                var dateStr = this.getAttribute('data-date');
                currentSelectedDate = dateStr;
                
                var dObj = new Date(dateStr + 'T00:00:00');
                var weekdayName = dObj.toLocaleDateString(undefined, { weekday: 'long' });
                
                if (displayDateEl) displayDateEl.textContent = dateStr;
                if (displayWeekdayEl) displayWeekdayEl.textContent = weekdayName;
                
                var isOff = offDaysMap.hasOwnProperty(dateStr);
                if (isOff) {
                    updateModalState('off');
                    if (reasonInput) reasonInput.value = offDaysMap[dateStr];
                } else {
                    updateModalState('open');
                    if (reasonInput) reasonInput.value = '';
                }
                
                if (modal) modal.style.display = 'flex';
            });
        });

        function closeModal() {
            if (modal) modal.style.display = 'none';
        }

        if (closeBtn) closeBtn.addEventListener('click', closeModal);
        if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
        if (modal) {
            modal.addEventListener('click', function(e) {
                if (e.target === modal) closeModal();
            });
        }

        document.querySelectorAll('.ifs-educore-reason-preset-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                if (reasonInput) reasonInput.value = this.textContent.trim();
            });
        });

        if (saveBtn) {
            saveBtn.addEventListener('click', function() {
                if (!currentSelectedDate) return;
                var status = statusSelect.value;
                var reason = reasonInput ? reasonInput.value.trim() : '';
                
                if (status === 'off') {
                    offDaysMap[currentSelectedDate] = reason !== '' ? reason : '<?php echo esc_js( __( 'Holiday', 'ifsedu-school-management' ) ); ?>';
                    var cell = document.querySelector('.ifs-educore-cal-day-cell[data-date="' + currentSelectedDate + '"]');
                    if (cell) {
                        cell.classList.add('ifs-educore-is-off-day');
                        cell.setAttribute('title', currentSelectedDate + ' (' + offDaysMap[currentSelectedDate] + ')');
                    }
                } else {
                    delete offDaysMap[currentSelectedDate];
                    var cell = document.querySelector('.ifs-educore-cal-day-cell[data-date="' + currentSelectedDate + '"]');
                    if (cell) {
                        cell.classList.remove('ifs-educore-is-off-day');
                        cell.setAttribute('title', currentSelectedDate);
                    }
                }
                
                if (hiddenJsonInput) hiddenJsonInput.value = JSON.stringify(offDaysMap);
                var count = Object.keys(offDaysMap).length;
                if (totalOffDaysPill) {
                    totalOffDaysPill.textContent = count + ' <?php echo esc_js( __( 'Total Off Days', 'ifsedu-school-management' ) ); ?>';
                }
                closeModal();
            });
        }
    });
    </script>
    <?php
}
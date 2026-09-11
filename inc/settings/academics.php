<?php
/**
 * Institutional Academic Defaults, Session & Interactive 12-Month Holiday Calendar with Reason Management
 * File: inc/settings/academics.php
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function educore_render_settings_academics_view( $base_url ) {
    $settings_updated = false;
    $req_method       = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';

    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $calendar_year = isset( $_GET['cal_year'] ) ? absint( wp_unslash( $_GET['cal_year'] ) ) : absint( get_option( 'educore_academic_year', gmdate( 'Y' ) ) );
    if ( $calendar_year < 2000 || $calendar_year > 2099 ) {
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

        // Custom Holiday Dates with Reasons JSON
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

    // Retrieve Saved Custom Off Days for this selected year
    $saved_off_days_map = get_option( 'educore_academic_off_dates_' . $calendar_year, null );

    // First time initialization: populate Friday & Saturday as default "Weekly Holiday"
    if ( is_null( $saved_off_days_map ) || ! is_array( $saved_off_days_map ) ) {
        $saved_off_days_map = array();
        for ( $m = 1; $m <= 12; $m++ ) {
            $days_in_month = cal_days_in_month( CAL_GREGORIAN, $m, $calendar_year );
            for ( $d = 1; $d <= $days_in_month; $d++ ) {
                $date_str    = sprintf( '%04d-%02d-%02d', $calendar_year, $m, $d );
                $day_of_week = date( 'w', strtotime( $date_str ) ); // 5 = Friday, 6 = Saturday
                if ( 5 == $day_of_week || 6 == $day_of_week ) {
                    $saved_off_days_map[ $date_str ] = __( 'Weekly Holiday', 'ifsedu-school-management' );
                }
            }
        }
    }
    $saved_off_days_json = wp_json_encode( $saved_off_days_map );
    ?>

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
                <input type="hidden" name="academic_off_dates_json" id="ifs_academic_off_dates_json" value="<?php echo esc_attr( $saved_off_days_json ); ?>">

                <!-- Global Academic Parameters -->
                <div class="ifs-educore-grid-row">
                    <div class="ifs-educore-field-node">
                        <label class="ifs-educore-label">
                            <?php esc_html_e( 'Current Academic Session / Year', 'ifsedu-school-management' ); ?> <span class="required">*</span>
                        </label>
                        <input type="text" name="academic_year" class="ifs-educore-input" value="<?php echo esc_attr( $academic_year ); ?>" required placeholder="<?php echo esc_attr( gmdate( 'Y' ) ); ?>">
                        <span class="ifs-educore-help-text"><?php esc_html_e( 'e.g. 2026 or 2026-2027', 'ifsedu-school-management' ); ?></span>
                    </div>

                    <div class="ifs-educore-field-node">
                        <label class="ifs-educore-label">
                            <?php esc_html_e( 'Default Academic Shift', 'ifsedu-school-management' ); ?>
                        </label>
                        <select name="default_shift" class="ifs-educore-input ifs-educore-shift-select">
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
                    <div class="ifs-cal-grid-12">
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
                            $first_day_of_month = mktime( 0, 0, 0, $m, 1, $calendar_year );
                            $days_in_this_month = cal_days_in_month( CAL_GREGORIAN, $m, $calendar_year );
                            $start_weekday      = date( 'w', $first_day_of_month ); // 0 (Sun) to 6 (Sat)
                        ?>
                            <div class="ifs-cal-month-card">
                                <div class="ifs-cal-month-header">
                                    <span><?php echo esc_html( $month_names[ $m ] ); ?></span>
                                    <small class="ifs-cal-month-year-small"><?php echo esc_html( $calendar_year ); ?></small>
                                </div>

                                <div class="ifs-cal-weekdays">
                                    <span>Su</span><span>Mo</span><span>Tu</span><span>We</span><span>Th</span>
                                    <span class="weekend-hdr">Fr</span>
                                    <span class="weekend-hdr">Sa</span>
                                </div>

                                <div class="ifs-cal-days-grid">
                                    <?php
                                    for ( $blank = 0; $blank < $start_weekday; $blank++ ) {
                                        echo '<div class="ifs-cal-day-cell is-empty"></div>';
                                    }

                                    for ( $d = 1; $d <= $days_in_this_month; $d++ ) {
                                        $current_date_str = sprintf( '%04d-%02d-%02d', $calendar_year, $m, $d );
                                        $is_off           = isset( $saved_off_days_map[ $current_date_str ] );
                                        $reason           = $is_off ? $saved_off_days_map[ $current_date_str ] : '';
                                        $tooltip          = $current_date_str . ( $is_off ? ' (' . $reason . ')' : '' );
                                    ?>
                                        <div class="ifs-cal-day-cell <?php echo $is_off ? 'is-off-day' : ''; ?>" 
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
    <div class="ifs-cal-modal-backdrop" id="ifs_holiday_modal">
        <div class="ifs-cal-modal-card">
            <!-- Modal Header -->
            <div class="ifs-modal-header">
                <div class="ifs-modal-title-wrap">
                    <div class="ifs-modal-icon-badge">
                        <span class="dashicons dashicons-calendar-alt"></span>
                    </div>
                    <div>
                        <h4 class="ifs-educore-modal-title-text">
                            <?php esc_html_e( 'Configure Academic Day', 'ifsedu-school-management' ); ?>
                        </h4>
                        <small class="ifs-educore-modal-subtitle-text"><?php esc_html_e( 'Manage schedule status and reason', 'ifsedu-school-management' ); ?></small>
                    </div>
                </div>
                <button type="button" id="ifs_close_holiday_modal" class="ifs-modal-close-btn">&times;</button>
            </div>

            <!-- Modal Content Body -->
            <div class="ifs-modal-body">
                <!-- Highlighted Date Banner -->
                <div class="ifs-date-banner">
                    <div class="ifs-date-banner-left">
                        <span class="ifs-date-banner-title" id="modal_display_date">YYYY-MM-DD</span>
                        <span class="ifs-date-banner-day" id="modal_display_weekday">Weekday</span>
                    </div>
                    <span class="ifs-date-badge" id="modal_display_badge"><?php esc_html_e( 'Academic Day', 'ifsedu-school-management' ); ?></span>
                </div>

                <!-- Hidden native select for JS engine compatibility -->
                <select id="modal_day_status" class="ifs-educore-hidden-status-select">
                    <option value="open"><?php esc_html_e( 'Open Academic Day', 'ifsedu-school-management' ); ?></option>
                    <option value="off"><?php esc_html_e( 'Off / Holiday (Blocked Day)', 'ifsedu-school-management' ); ?></option>
                </select>

                <!-- Interactive Segmented Pill Switcher -->
                <div class="ifs-status-switch-group">
                    <div class="ifs-status-option" data-value="open" id="ifs_switch_open">
                        <span class="dashicons dashicons-yes"></span>
                        <?php esc_html_e( 'Open Day', 'ifsedu-school-management' ); ?>
                    </div>
                    <div class="ifs-status-option" data-value="off" id="ifs_switch_off">
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
                        <div class="ifs-reason-chips-grid">
                            <button type="button" class="ifs-reason-preset-btn"><?php esc_html_e( 'Weekly Holiday', 'ifsedu-school-management' ); ?></button>
                            <button type="button" class="ifs-reason-preset-btn"><?php esc_html_e( 'National Holiday', 'ifsedu-school-management' ); ?></button>
                            <button type="button" class="ifs-reason-preset-btn"><?php esc_html_e( 'Eid Vacation', 'ifsedu-school-management' ); ?></button>
                            <button type="button" class="ifs-reason-preset-btn"><?php esc_html_e( 'Puja Vacation', 'ifsedu-school-management' ); ?></button>
                            <button type="button" class="ifs-reason-preset-btn"><?php esc_html_e( 'Summer Vacation', 'ifsedu-school-management' ); ?></button>
                            <button type="button" class="ifs-reason-preset-btn"><?php esc_html_e( 'Winter Vacation', 'ifsedu-school-management' ); ?></button>
                            <button type="button" class="ifs-reason-preset-btn"><?php esc_html_e( 'Exam Preparation', 'ifsedu-school-management' ); ?></button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Action Footer -->
            <div class="ifs-modal-footer">
                <button type="button" id="ifs_cancel_holiday_btn" class="ifs-btn-cancel">
                    <?php esc_html_e( 'Cancel', 'ifsedu-school-management' ); ?>
                </button>
                <button type="button" id="ifs_save_holiday_btn" class="ifs-btn-apply">
                    <span class="dashicons dashicons-saved"></span>
                    <?php esc_html_e( 'Save Status', 'ifsedu-school-management' ); ?>
                </button>
            </div>
        </div>
    </div>
    <?php
}
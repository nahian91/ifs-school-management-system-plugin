<?php
/**
 * Examination Timetable & Routine Scheduler View
 * File: inc/exams/exam-routine.php
 * Text Domain: ifsedu-school-management
 * Updated: WordPress Settings Timezone Alignment, BD Standard 12-Hour Format, Full-Width Form
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Helper to conditionally append section name only for classes 9, 10, 11, and 12.
 *
 * @param string $class_name   Class name.
 * @param string $section_name Section name.
 * @return string Formatted class label.
 */
function educore_format_exam_class_label( string $class_name, string $section_name = '' ): string {
    $class_name   = trim( $class_name );
    $section_name = trim( $section_name );

    preg_match( '/\d+/', $class_name, $matches );
    $class_num = ! empty( $matches ) ? intval( $matches[0] ) : 0;

    // Show section ONLY for classes 9, 10, 11, 12.
    if ( in_array( $class_num, array( 9, 10, 11, 12 ), true ) && ! empty( $section_name ) ) {
        return $class_name . ' (' . $section_name . ')';
    }

    return $class_name;
}

/**
 * Render Examination Timetable & Routine Scheduler View
 */
function educore_exam_routine_view(): void {
    global $wpdb;

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die(
            esc_html__( 'You do not have sufficient permissions to configure exam routines.', 'ifsedu-school-management' ),
            403
        );
    }

    $table_exams        = $wpdb->prefix . 'sms_exams';
    $table_units        = $wpdb->prefix . 'sms_academic_units';
    $table_subjects     = $wpdb->prefix . 'sms_subjects';
    $table_exam_routine = $wpdb->prefix . 'sms_exam_routine';

    $base_url   = add_query_arg( array( 'page' => 'school_management_system', 'tab' => 'exams', 'sub' => 'routine' ), admin_url( 'admin.php' ) );
    $notice_msg = '';

    // 1. Handle Add Slot.
    $req_method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
    if ( 'POST' === $req_method && isset( $_POST['save_exam_slot'] ) ) {
        $nonce_field = isset( $_POST['ifs_educore_er_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['ifs_educore_er_nonce'] ) ) : '';

        if ( wp_verify_nonce( $nonce_field, 'exam_routine_action' ) ) {
            $exam_id    = isset( $_POST['exam_id'] ) ? absint( wp_unslash( $_POST['exam_id'] ) ) : 0;
            $class_id   = isset( $_POST['class_id'] ) ? absint( wp_unslash( $_POST['class_id'] ) ) : 0;
            $subject_id = isset( $_POST['subject_id'] ) ? absint( wp_unslash( $_POST['subject_id'] ) ) : 0;
            $shift      = isset( $_POST['shift'] ) ? sanitize_text_field( wp_unslash( $_POST['shift'] ) ) : 'No Shift';
            $exam_date  = isset( $_POST['exam_date'] ) ? sanitize_text_field( wp_unslash( $_POST['exam_date'] ) ) : '';
            $start_time = isset( $_POST['start_time'] ) ? sanitize_text_field( wp_unslash( $_POST['start_time'] ) ) : '';
            $end_time   = isset( $_POST['end_time'] ) ? sanitize_text_field( wp_unslash( $_POST['end_time'] ) ) : '';
            $room_no    = isset( $_POST['room_no'] ) ? sanitize_text_field( wp_unslash( $_POST['room_no'] ) ) : '';

            if ( $exam_id > 0 && $class_id > 0 && $subject_id > 0 && ! empty( $exam_date ) ) {
                // Keep literal local time string to strictly mirror what is selected in input fields
                $formatted_start = ! empty( $start_time ) ? sanitize_text_field( $start_time ) . ':00' : '00:00:00';
                $formatted_end   = ! empty( $end_time ) ? sanitize_text_field( $end_time ) . ':00' : '00:00:00';

                $data = array(
                    'exam_id'    => $exam_id,
                    'class_id'   => $class_id,
                    'subject_id' => $subject_id,
                    'shift'      => $shift,
                    'exam_date'  => $exam_date,
                    'start_time' => $formatted_start,
                    'end_time'   => $formatted_end,
                    'room_no'    => $room_no,
                );
                $formats = array( '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s' );

                $inserted = $wpdb->insert( $table_exam_routine, $data, $formats );

                if ( false !== $inserted ) {
                    if ( function_exists( 'educore_log_activity' ) ) {
                        educore_log_activity(
                            sprintf(
                                /* translators: 1: Exam Date, 2: Room Number */
                                __( 'Added exam routine slot on %1$s in Room %2$s', 'ifsedu-school-management' ),
                                $exam_date,
                                $room_no
                            )
                        );
                    }
                    $notice_msg = esc_html__( 'Exam routine slot added successfully.', 'ifsedu-school-management' );
                }
            }
        }
    }

    // 2. Handle Delete Slot.
    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    if ( isset( $_GET['action'] ) && 'delete_slot' === $_GET['action'] && isset( $_GET['slot_id'] ) ) {
        $slot_id = absint( $_GET['slot_id'] );
        if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'delete_slot_' . $slot_id ) ) {
            $wpdb->delete( $table_exam_routine, array( 'id' => $slot_id ), array( '%d' ) );
            $notice_msg = esc_html__( 'Exam routine slot removed.', 'ifsedu-school-management' );
        }
    }

    // Filters.
    $filter_exam_id  = isset( $_GET['filter_exam'] ) ? absint( $_GET['filter_exam'] ) : 0;
    $filter_class_id = isset( $_GET['filter_class'] ) ? absint( $_GET['filter_class'] ) : 0;
    $filter_shift    = isset( $_GET['filter_shift'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_shift'] ) ) : '';
    $has_filtered    = isset( $_GET['routine_filter_submitted'] ) || $filter_exam_id > 0 || $filter_class_id > 0 || ! empty( $filter_shift );
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    $exams    = $wpdb->get_results( $wpdb->prepare( 'SELECT id, exam_name FROM %i ORDER BY id DESC', $table_exams ) );
    $classes  = $wpdb->get_results( $wpdb->prepare( 'SELECT id, class_name, section_name, sort_order FROM %i WHERE class_name != %s ORDER BY sort_order ASC, CAST(class_name AS UNSIGNED) ASC, class_name ASC, section_name ASC', $table_units, '' ) );
    $subjects = $wpdb->get_results( $wpdb->prepare( 'SELECT id, subject_name, subject_code, class_id, subject_order FROM %i ORDER BY subject_order ASC, subject_name ASC', $table_subjects ) );

    $schedules = array();
    $preview_by_date = array();

    if ( $has_filtered ) {
        $where_clauses = array( '1=1' );
        $query_params  = array();

        if ( $filter_exam_id > 0 ) {
            $where_clauses[] = 'er.exam_id = %d';
            $query_params[]  = $filter_exam_id;
        }

        if ( $filter_class_id > 0 ) {
            $where_clauses[] = 'er.class_id = %d';
            $query_params[]  = $filter_class_id;
        }

        if ( ! empty( $filter_shift ) ) {
            $where_clauses[] = 'er.shift = %s';
            $query_params[]  = $filter_shift;
        }

        $where_sql = ' WHERE ' . implode( ' AND ', $where_clauses );

        $schedules_sql = "SELECT er.*, e.exam_name, u.class_name, u.section_name, u.sort_order as class_sort_order, s.subject_name, s.subject_code, s.subject_order 
                          FROM {$table_exam_routine} er
                          INNER JOIN {$table_exams} e ON er.exam_id = e.id
                          INNER JOIN {$table_units} u ON er.class_id = u.id
                          INNER JOIN {$table_subjects} s ON er.subject_id = s.id
                          {$where_sql}
                          ORDER BY er.exam_date ASC, u.sort_order ASC, CAST(u.class_name AS UNSIGNED) ASC, u.class_name ASC, u.section_name ASC, er.start_time ASC";

        if ( ! empty( $query_params ) ) {
            $schedules = $wpdb->get_results( $wpdb->prepare( $schedules_sql, ...$query_params ) );
        } else {
            $schedules = $wpdb->get_results( $schedules_sql );
        }

        if ( ! empty( $schedules ) ) {
            foreach ( $schedules as $slot ) {
                $preview_by_date[ (string) $slot->exam_date ][] = $slot;
            }
        }
    }

    $school_name     = get_option( 'educore_school_name', get_bloginfo( 'name' ) );
    $site_timezone   = wp_timezone();
    $current_date_wp = wp_date( 'Y-m-d', null, $site_timezone );
    ?>

    <style>
        .ifs-educore-routine-root {
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #0f172a;
            max-width: 100%;
            box-sizing: border-box;
        }
        .ifs-educore-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 4px 20px -3px rgba(0, 0, 0, 0.03);
            box-sizing: border-box;
        }
        .ifs-educore-form-group {
            margin-bottom: 16px;
        }
        .ifs-educore-label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            color: #475569;
            margin-bottom: 6px;
            text-transform: capitalize;
            letter-spacing: 0.3px;
        }
        .ifs-educore-select,
        .ifs-educore-input {
            width: 100% !important;
            height: 42px !important;
            padding: 0 14px !important;
            border: 1.5px solid #cbd5e1 !important;
            border-radius: 9px !important;
            font-size: 13.5px !important;
            color: #0f172a !important;
            background: #ffffff !important;
            box-sizing: border-box !important;
            outline: none !important;
            transition: all 0.2s ease !important;
        }
        .ifs-educore-select:focus,
        .ifs-educore-input:focus {
            border-color: #00523c !important;
            box-shadow: 0 0 0 3px rgba(0, 82, 60, 0.12) !important;
        }
        .ifs-educore-shift-badge {
            font-size: 10.5px;
            font-weight: 800;
            padding: 2px 8px;
            border-radius: 6px;
            display: inline-block;
            margin-left: 4px;
        }
        .ifs-educore-shift-morning {
            background: #fef3c7;
            color: #d97706;
            border: 1px solid #fde68a;
        }
        .ifs-educore-shift-day {
            background: #e0f2fe;
            color: #0284c7;
            border: 1px solid #bae6fd;
        }
        .ifs-educore-shift-none {
            background: #f1f5f9;
            color: #64748b;
        }
        .ifs-educore-exam-timeline-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
            margin-top: 16px;
        }
        .ifs-educore-date-card {
            background: #f8fafc;
            border: 1.5px solid #e2e8f0;
            border-radius: 12px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        .ifs-educore-date-header {
            background: #00523c;
            color: #ffffff;
            padding: 12px 16px;
            font-weight: 800;
            font-size: 14px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .ifs-educore-date-slots-body {
            padding: 14px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .ifs-educore-exam-slot-item {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 12px 14px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.02);
        }
        .ifs-educore-slot-badge-time {
            background: #ecfdf5;
            color: #047857;
            border: 1px solid #a7f3d0;
            font-weight: 800;
            font-size: 11.5px;
            padding: 3px 8px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .ifs-educore-btn-print {
            background: #ffffff;
            border: 1.5px solid #cbd5e1;
            color: #334155;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .ifs-educore-btn-print:hover {
            background: #f8fafc;
            color: #00523c;
            border-color: #00523c;
        }

        @media print {
            #adminmenuwrap, #adminmenuback, #wpadminbar, #wpfooter, .no-print {
                display: none !important;
            }
            body, .ifs-educore-routine-root, .ifs-educore-card {
                background: transparent !important;
                padding: 0 !important;
                margin: 0 !important;
                box-shadow: none !important;
                border: none !important;
            }
            .ifs-educore-exam-timeline-grid {
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 12px !important;
            }
        }
    </style>

    <div class="ifs-educore-routine-root">

        <?php if ( ! empty( $notice_msg ) ) : ?>
            <div class="no-print" style="background:#ecfdf5; border:1px solid #a7f3d0; color:#065f46; padding:12px 16px; border-radius:8px; font-weight:700; margin-bottom:20px;">
                <?php echo esc_html( $notice_msg ); ?>
            </div>
        <?php endif; ?>

        <!-- 1. FULL WIDTH SCHEDULE EXAM SLOT FORM -->
        <div class="ifs-educore-card no-print">
            <h3 style="margin:0 0 16px 0; font-size:17px; font-weight:800; color:#0f172a; border-bottom:1px solid #f1f5f9; padding-bottom:12px; display:flex; align-items:center; gap:8px;">
                <span class="dashicons dashicons-calendar-alt" style="color:#00523c;"></span>
                <?php esc_html_e( 'Schedule Examination Slot', 'ifsedu-school-management' ); ?>
            </h3>

            <form method="POST" action="">
                <?php wp_nonce_field( 'exam_routine_action', 'ifs_educore_er_nonce' ); ?>

                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px;">
                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-label"><?php esc_html_e( 'Examination Scheme', 'ifsedu-school-management' ); ?> <span style="color:#ef4444;">*</span></label>
                        <select name="exam_id" class="ifs-educore-select" required>
                            <option value=""><?php esc_html_e( '-- Choose Exam --', 'ifsedu-school-management' ); ?></option>
                            <?php foreach ( $exams as $ex ) : ?>
                                <option value="<?php echo absint( $ex->id ); ?>"><?php echo esc_html( $ex->exam_name ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-label"><?php esc_html_e( 'Class / Section', 'ifsedu-school-management' ); ?> <span style="color:#ef4444;">*</span></label>
                        <select name="class_id" id="ifs_educore_er_class_select" class="ifs-educore-select" required>
                            <option value=""><?php esc_html_e( '-- Choose Class --', 'ifsedu-school-management' ); ?></option>
                            <?php foreach ( $classes as $cl ) : 
                                $class_display = educore_format_exam_class_label( (string) $cl->class_name, (string) $cl->section_name );
                            ?>
                                <option value="<?php echo absint( $cl->id ); ?>"><?php echo esc_html( $class_display ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-label"><?php esc_html_e( 'Academic Shift', 'ifsedu-school-management' ); ?></label>
                        <select name="shift" class="ifs-educore-select">
                            <option value="No Shift"><?php esc_html_e( 'No Shift', 'ifsedu-school-management' ); ?></option>
                            <option value="Morning Shift"><?php esc_html_e( 'Morning Shift', 'ifsedu-school-management' ); ?></option>
                            <option value="Day Shift"><?php esc_html_e( 'Day Shift', 'ifsedu-school-management' ); ?></option>
                        </select>
                    </div>

                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-label"><?php esc_html_e( 'Subject', 'ifsedu-school-management' ); ?> <span style="color:#ef4444;">*</span></label>
                        <select name="subject_id" id="ifs_educore_er_subject_select" class="ifs-educore-select" required>
                            <option value=""><?php esc_html_e( '-- Choose Subject --', 'ifsedu-school-management' ); ?></option>
                            <?php foreach ( $subjects as $sub ) : ?>
                                <option value="<?php echo absint( $sub->id ); ?>" data-classid="<?php echo absint( $sub->class_id ); ?>">
                                    <?php echo esc_html( $sub->subject_name . ( ! empty( $sub->subject_code ) ? ' [' . $sub->subject_code . ']' : '' ) ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-label"><?php esc_html_e( 'Exam Date', 'ifsedu-school-management' ); ?> <span style="color:#ef4444;">*</span></label>
                        <input type="date" name="exam_date" class="ifs-educore-input" value="<?php echo esc_attr( $current_date_wp ); ?>" required>
                    </div>

                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-label"><?php esc_html_e( 'Start Time', 'ifsedu-school-management' ); ?> <span style="color:#ef4444;">*</span></label>
                        <input type="time" name="start_time" class="ifs-educore-input" required>
                    </div>

                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-label"><?php esc_html_e( 'End Time', 'ifsedu-school-management' ); ?> <span style="color:#ef4444;">*</span></label>
                        <input type="time" name="end_time" class="ifs-educore-input" required>
                    </div>

                    <div class="ifs-educore-form-group">
                        <label class="ifs-educore-label"><?php esc_html_e( 'Room No / Hall', 'ifsedu-school-management' ); ?></label>
                        <input type="text" name="room_no" class="ifs-educore-input" placeholder="<?php esc_attr_e( 'e.g. Room 204', 'ifsedu-school-management' ); ?>">
                    </div>
                </div>

                <div style="margin-top: 16px; text-align: right;">
                    <button type="submit" name="save_exam_slot" style="height:44px; padding:0 32px; background:#00523c; color:#fff; font-weight:700; font-size:14px; border:none; border-radius:9px; cursor:pointer; box-shadow:0 4px 12px rgba(0,82,60,0.2);">
                        <span class="dashicons dashicons-saved" style="vertical-align:middle;"></span> <?php esc_html_e( 'Save Exam Slot', 'ifsedu-school-management' ); ?>
                    </button>
                </div>
            </form>
        </div>

        <!-- 2. UNIFIED SCHEDULED SLOTS & NOTICE BOARD PREVIEW -->
        <div class="ifs-educore-card">
            <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #f1f5f9; padding-bottom:14px; margin-bottom:20px; flex-wrap:wrap; gap:12px;">
                <div>
                    <h3 style="margin:0 0 4px 0; font-size:18px; font-weight:800; color:#0f172a; display:flex; align-items:center; gap:8px;">
                        <span class="dashicons dashicons-visibility" style="color:#00523c; font-size:22px; width:22px; height:22px;"></span>
                        <?php esc_html_e( 'Scheduled Exam Slots & Notice Board Preview', 'ifsedu-school-management' ); ?>
                    </h3>
                    <small style="color:#64748b; font-size:12px; font-weight:600;">
                        <?php echo esc_html( $school_name ); ?> — <?php esc_html_e( 'Filter parameters required to display schedule slots', 'ifsedu-school-management' ); ?>
                    </small>
                </div>

                <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                    <form method="GET" action="" class="no-print" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                        <input type="hidden" name="page" value="school_management_system">
                        <input type="hidden" name="tab" value="exams">
                        <input type="hidden" name="sub" value="routine">
                        <input type="hidden" name="routine_filter_submitted" value="1">
                        
                        <select name="filter_exam" style="height:36px; border:1.5px solid #cbd5e1; border-radius:8px; font-size:12.5px; font-weight:600; padding:0 10px;">
                            <option value=""><?php esc_html_e( '-- All Exams --', 'ifsedu-school-management' ); ?></option>
                            <?php foreach ( $exams as $ex ) : ?>
                                <option value="<?php echo absint( $ex->id ); ?>" <?php selected( $filter_exam_id, $ex->id ); ?>><?php echo esc_html( $ex->exam_name ); ?></option>
                            <?php endforeach; ?>
                        </select>

                        <select name="filter_class" style="height:36px; border:1.5px solid #cbd5e1; border-radius:8px; font-size:12.5px; font-weight:600; padding:0 10px;">
                            <option value=""><?php esc_html_e( '-- All Classes --', 'ifsedu-school-management' ); ?></option>
                            <?php foreach ( $classes as $cl ) : 
                                $class_display = educore_format_exam_class_label( (string) $cl->class_name, (string) $cl->section_name );
                            ?>
                                <option value="<?php echo absint( $cl->id ); ?>" <?php selected( $filter_class_id, $cl->id ); ?>><?php echo esc_html( $class_display ); ?></option>
                            <?php endforeach; ?>
                        </select>

                        <select name="filter_shift" style="height:36px; border:1.5px solid #cbd5e1; border-radius:8px; font-size:12.5px; font-weight:600; padding:0 10px;">
                            <option value=""><?php esc_html_e( '-- All Shifts --', 'ifsedu-school-management' ); ?></option>
                            <option value="No Shift" <?php selected( $filter_shift, 'No Shift' ); ?>><?php esc_html_e( 'No Shift', 'ifsedu-school-management' ); ?></option>
                            <option value="Morning Shift" <?php selected( $filter_shift, 'Morning Shift' ); ?>><?php esc_html_e( 'Morning Shift', 'ifsedu-school-management' ); ?></option>
                            <option value="Day Shift" <?php selected( $filter_shift, 'Day Shift' ); ?>><?php esc_html_e( 'Day Shift', 'ifsedu-school-management' ); ?></option>
                        </select>

                        <button type="submit" class="ifs-educore-btn-print" style="height:36px; padding:0 14px; background:#00523c; color:#ffffff; border-color:#00523c;">
                            <?php esc_html_e( 'Filter', 'ifsedu-school-management' ); ?>
                        </button>
                        
                        <?php if ( $has_filtered ) : ?>
                            <a href="<?php echo esc_url( $base_url ); ?>" class="ifs-educore-btn-print" style="height:36px; padding:0 12px; text-decoration:none;">
                                <?php esc_html_e( 'Reset', 'ifsedu-school-management' ); ?>
                            </a>
                        <?php endif; ?>
                    </form>

                    <?php if ( $has_filtered ) : ?>
                        <button type="button" onclick="window.print();" class="ifs-educore-btn-print no-print" style="height:36px; background:#00523c; color:#fff; border-color:#00523c;">
                            <span class="dashicons dashicons-printer" style="font-size:15px; width:15px; height:15px;"></span> <?php esc_html_e( 'Print Routine', 'ifsedu-school-management' ); ?>
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ( ! $has_filtered ) : ?>
                <div style="text-align:center; padding:50px 20px; background:#f8fafc; border:1px dashed #cbd5e1; border-radius:12px; color:#64748b;">
                    <span class="dashicons dashicons-filter" style="font-size:36px; width:36px; height:36px; opacity:0.6; margin-bottom:10px;"></span>
                    <h4 style="margin:0 0 4px 0; font-size:15px; font-weight:800; color:#0f172a;"><?php esc_html_e( 'Awaiting Filter Selection', 'ifsedu-school-management' ); ?></h4>
                    <p style="margin:0; font-weight:600; font-size:13px;"><?php esc_html_e( 'Please select your filter criteria above and click "Filter" to view scheduled exam slots and preview the notice board.', 'ifsedu-school-management' ); ?></p>
                </div>
            <?php else : ?>
                <!-- Management Table View -->
                <div style="overflow-x:auto; margin-bottom: 24px;">
                    <table style="width:100%; border-collapse:separate; border-spacing:0; font-size:13px;">
                        <thead>
                            <tr style="background:#f8fafc;">
                                <th style="padding:10px 12px; text-align:left; border-bottom:2px solid #e2e8f0; font-size:11.5px; text-transform:capitalize; color:#475569;"><?php esc_html_e( 'Date & Time', 'ifsedu-school-management' ); ?></th>
                                <th style="padding:10px 12px; text-align:left; border-bottom:2px solid #e2e8f0; font-size:11.5px; text-transform:capitalize; color:#475569;"><?php esc_html_e( 'Exam', 'ifsedu-school-management' ); ?></th>
                                <th style="padding:10px 12px; text-align:left; border-bottom:2px solid #e2e8f0; font-size:11.5px; text-transform:capitalize; color:#475569;"><?php esc_html_e( 'Class / Shift', 'ifsedu-school-management' ); ?></th>
                                <th style="padding:10px 12px; text-align:left; border-bottom:2px solid #e2e8f0; font-size:11.5px; text-transform:capitalize; color:#475569;"><?php esc_html_e( 'Subject', 'ifsedu-school-management' ); ?></th>
                                <th style="padding:10px 12px; text-align:left; border-bottom:2px solid #e2e8f0; font-size:11.5px; text-transform:capitalize; color:#475569;"><?php esc_html_e( 'Room', 'ifsedu-school-management' ); ?></th>
                                <th style="padding:10px 12px; text-align:right; border-bottom:2px solid #e2e8f0; font-size:11.5px; text-transform:capitalize; color:#475569;" class="no-print"><?php esc_html_e( 'Action', 'ifsedu-school-management' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ( ! empty( $schedules ) ) : foreach ( $schedules as $s ) : 
                                $slot_id   = absint( (int) $s->id );
                                $del_url   = wp_nonce_url( add_query_arg( array( 'action' => 'delete_slot', 'slot_id' => $slot_id ), $base_url ), 'delete_slot_' . $slot_id );
                                $shift_val = ! empty( $s->shift ) ? (string) $s->shift : 'No Shift';
                                $shift_badge_class = 'ifs-educore-shift-none';
                                if ( 'Morning Shift' === $shift_val ) {
                                    $shift_badge_class = 'ifs-educore-shift-morning';
                                } elseif ( 'Day Shift' === $shift_val ) {
                                    $shift_badge_class = 'ifs-educore-shift-day';
                                }

                                $date_timestamp  = ! empty( $s->exam_date ) ? strtotime( (string) $s->exam_date ) : false;
                                
                                // Format time using site timezone settings correctly via wp_date
                                $start_str = '—';
                                $end_str   = '—';
                                if ( ! empty( $s->start_time ) ) {
                                    $start_dt = date_create( (string) $s->exam_date . ' ' . (string) $s->start_time, $site_timezone );
                                    if ( $start_dt ) {
                                        $start_str = $start_dt->format( 'g:i A' );
                                    }
                                }
                                if ( ! empty( $s->end_time ) ) {
                                    $end_dt = date_create( (string) $s->exam_date . ' ' . (string) $s->end_time, $site_timezone );
                                    if ( $end_dt ) {
                                        $end_str = $end_dt->format( 'g:i A' );
                                    }
                                }
                                
                                $formatted_class_entry = educore_format_exam_class_label( (string) $s->class_name, (string) $s->section_name );
                            ?>
                                <tr>
                                    <td style="padding:10px 12px; border-bottom:1px solid #f1f5f9;">
                                        <strong style="color:#0f172a;"><?php echo esc_html( $date_timestamp ? date_i18n( 'd M Y', $date_timestamp ) : '—' ); ?></strong><br>
                                        <small style="color:#64748b; font-weight:600;">
                                            <?php echo esc_html( $start_str . ' - ' . $end_str ); ?>
                                        </small>
                                    </td>
                                    <td style="padding:10px 12px; border-bottom:1px solid #f1f5f9;">
                                        <span style="font-weight:700; color:#00523c;"><?php echo esc_html( (string) $s->exam_name ); ?></span>
                                    </td>
                                    <td style="padding:10px 12px; border-bottom:1px solid #f1f5f9;">
                                        <span style="background:#eff6ff; color:#2563eb; padding:2px 8px; border-radius:10px; font-weight:700; font-size:11.5px;">
                                            <?php echo esc_html( $formatted_class_entry ); ?>
                                        </span>
                                        <?php if ( 'No Shift' !== $shift_val ) : ?>
                                            <span class="ifs-educore-shift-badge <?php echo esc_attr( $shift_badge_class ); ?>">
                                                <?php echo esc_html( $shift_val ); ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding:10px 12px; border-bottom:1px solid #f1f5f9;">
                                        <strong><?php echo esc_html( (string) $s->subject_name ); ?></strong>
                                    </td>
                                    <td style="padding:10px 12px; border-bottom:1px solid #f1f5f9;">
                                        <code><?php echo esc_html( ! empty( $s->room_no ) ? (string) $s->room_no : 'N/A' ); ?></code>
                                    </td>
                                    <td style="padding:10px 12px; border-bottom:1px solid #f1f5f9; text-align:right;" class="no-print">
                                        <a href="<?php echo esc_url( $del_url ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Remove this routine slot?', 'ifsedu-school-management' ) ); ?>');" style="color:#dc2626; text-decoration:none; padding:4px 8px; border:1px solid #fecaca; border-radius:6px; background:#fef2f2;">
                                            <span class="dashicons dashicons-trash" style="font-size:14px; width:14px; height:14px; vertical-align:middle;"></span>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; else : ?>
                                <tr>
                                    <td colspan="6" style="padding:24px; text-align:center; color:#94a3b8;">
                                        <?php esc_html_e( 'No examination routine slots found for selected filters.', 'ifsedu-school-management' ); ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Notice Board Timeline Grid Preview -->
                <h4 style="margin: 24px 0 12px 0; font-size: 15px; font-weight: 800; color: #0f172a; border-top: 1px solid #f1f5f9; padding-top: 16px;">
                    <?php esc_html_e( 'Chronological Timeline Notice Board', 'ifsedu-school-management' ); ?>
                </h4>

                <?php if ( ! empty( $preview_by_date ) ) : ?>
                    <div class="ifs-educore-exam-timeline-grid">
                        <?php foreach ( $preview_by_date as $date_str => $day_slots ) : 
                            $date_ts        = ! empty( $date_str ) ? strtotime( $date_str ) : false;
                            $day_name       = $date_ts ? date_i18n( 'l', $date_ts ) : '—';
                            $formatted_date = $date_ts ? date_i18n( 'd M, Y', $date_ts ) : $date_str;
                        ?>
                            <div class="ifs-educore-date-card">
                                <div class="ifs-educore-date-header">
                                    <span><?php echo esc_html( $formatted_date ); ?></span>
                                    <span style="font-size:11px; opacity:0.9; text-transform:capitalize; font-weight:700;"><?php echo esc_html( $day_name ); ?></span>
                                </div>

                                <div class="ifs-educore-date-slots-body">
                                    <?php foreach ( $day_slots as $slot_item ) : 
                                        $slot_shift       = ! empty( $slot_item->shift ) ? (string) $slot_item->shift : 'No Shift';
                                        $slot_badge_class = 'ifs-educore-shift-none';
                                        if ( 'Morning Shift' === $slot_shift ) {
                                            $slot_badge_class = 'ifs-educore-shift-morning';
                                        } elseif ( 'Day Shift' === $slot_shift ) {
                                            $slot_badge_class = 'ifs-educore-shift-day';
                                        }

                                        $p_start_str = '—';
                                        $p_end_str   = '—';
                                        if ( ! empty( $slot_item->start_time ) ) {
                                            $pst = date_create( (string) $slot_item->exam_date . ' ' . (string) $slot_item->start_time, $site_timezone );
                                            if ( $pst ) { $p_start_str = $pst->format( 'g:i A' ); }
                                        }
                                        if ( ! empty( $slot_item->end_time ) ) {
                                            $pet = date_create( (string) $slot_item->exam_date . ' ' . (string) $slot_item->end_time, $site_timezone );
                                            if ( $pet ) { $p_end_str = $pet->format( 'g:i A' ); }
                                        }
                                        
                                        $preview_class_label = educore_format_exam_class_label( (string) $slot_item->class_name, (string) $slot_item->section_name );
                                    ?>
                                        <div class="ifs-educore-exam-slot-item">
                                            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:4px; margin-bottom:6px;">
                                                <div class="ifs-educore-slot-badge-time" style="margin-bottom:0;">
                                                    <span class="dashicons dashicons-clock" style="font-size:12px; width:12px; height:12px;"></span>
                                                    <span><?php echo esc_html( $p_start_str . ' - ' . $p_end_str ); ?></span>
                                                </div>
                                                <?php if ( 'No Shift' !== $slot_shift ) : ?>
                                                    <span class="ifs-educore-shift-badge <?php echo esc_attr( $slot_badge_class ); ?>">
                                                        <?php echo esc_html( $slot_shift ); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>

                                            <div style="font-size:14px; font-weight:800; color:#0f172a; margin-bottom:4px;">
                                                <?php echo esc_html( (string) $slot_item->subject_name ); ?>
                                                <?php if ( ! empty( $slot_item->subject_code ) ) : ?>
                                                    <span style="font-size:11px; font-weight:600; color:#64748b;">(<?php echo esc_html( (string) $slot_item->subject_code ); ?>)</span>
                                                <?php endif; ?>
                                            </div>

                                            <div style="display:flex; justify-content:space-between; align-items:center; font-size:12px; color:#475569; font-weight:600; margin-top:6px; padding-top:6px; border-top:1px dashed #cbd5e1;">
                                                <span><?php esc_html_e( 'Class:', 'ifsedu-school-management' ); ?> <strong><?php echo esc_html( $preview_class_label ); ?></strong></span>
                                                <span><?php esc_html_e( 'Room:', 'ifsedu-school-management' ); ?> <strong><?php echo esc_html( ! empty( $slot_item->room_no ) ? (string) $slot_item->room_no : 'N/A' ); ?></strong></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Client-Side Class-Subject Linker -->
    <script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function() {
        var classSelect   = document.getElementById('ifs_educore_er_class_select');
        var subjectSelect = document.getElementById('ifs_educore_er_subject_select');
        if (!classSelect || !subjectSelect) return;

        var allOptions = Array.from(subjectSelect.querySelectorAll('option')).slice(1);

        classSelect.addEventListener('change', function() {
            var selectedClassId = this.value;
            subjectSelect.innerHTML = '<option value=""><?php echo esc_js( __( '-- Choose Subject --', 'ifsedu-school-management' ) ); ?></option>';

            allOptions.forEach(function(opt) {
                if (!selectedClassId || opt.getAttribute('data-classid') === selectedClassId) {
                    subjectSelect.appendChild(opt.cloneNode(true));
                }
            });
        });
    });
    </script>
    <?php
}
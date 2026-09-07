<?php
/**
 * Class Routine & Timetable Scheduler View
 * File: inc/academics/class-routine.php
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) { 
    exit; 
}

function educore_class_routine_view() {
    global $wpdb;
    $table_routine          = $wpdb->prefix . 'sms_routine';
    $table_units            = $wpdb->prefix . 'sms_academic_units';
    $table_subjects         = $wpdb->prefix . 'sms_subjects';
    $table_teacher_subjects = $wpdb->prefix . 'sms_teacher_subjects';
    $table_staff            = $wpdb->prefix . 'sms_staff';

    // Strict Capability Check
    if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'edit_posts' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to manage class routines.', 'ifsedu-school-management' ) );
    }

    // Dynamic Base URL preservation
    $base_url = add_query_arg(
        array(
            'page'   => 'school_management_system',
            'tab'    => 'academics',
            'subtab' => 'routine',
        ),
        admin_url( 'admin.php' )
    );

    // Handle Routine Deletion
    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    if ( isset( $_GET['action'] ) && 'delete_routine' === $_GET['action'] && isset( $_GET['id'] ) ) {
        $delete_id = absint( $_GET['id'] );
        $del_nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

        if ( $delete_id > 0 && wp_verify_nonce( $del_nonce, 'delete_routine_' . $delete_id ) ) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $wpdb->delete( $table_routine, array( 'id' => $delete_id ), array( '%d' ) );
            // phpcs:enable
            
            if ( function_exists( 'educore_log_activity' ) ) {
                /* translators: %d: Routine Slot ID */
                educore_log_activity( sprintf( __( 'Deleted class routine slot ID #%d', 'ifsedu-school-management' ), $delete_id ) );
            }
            
            $redirect_target = add_query_arg( array( 'status' => 'deleted' ), $base_url );

            if ( ! headers_sent() ) {
                wp_safe_redirect( $redirect_target );
                exit;
            } else {
                echo '<script type="text/javascript">window.location.href=' . wp_json_encode( esc_url_raw( $redirect_target ) ) . ';</script>';
                exit;
            }
        }
    }
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    // Handle Routine Submission
    $req_method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
    if ( 'POST' === $req_method && isset( $_POST['save_routine'] ) ) {
        check_admin_referer( 'routine_action', 'routine_nonce' );
        
        $class_unit_id  = isset( $_POST['section_id'] ) ? absint( $_POST['section_id'] ) : 0;
        $class_name_val = isset( $_POST['class_id'] ) ? sanitize_text_field( wp_unslash( $_POST['class_id'] ) ) : '';
        $subject_id     = isset( $_POST['subject_id'] ) ? absint( $_POST['subject_id'] ) : 0;
        $day_name       = isset( $_POST['day_name'] ) ? sanitize_text_field( wp_unslash( $_POST['day_name'] ) ) : '';
        $shift          = isset( $_POST['shift'] ) ? sanitize_text_field( wp_unslash( $_POST['shift'] ) ) : 'No Shift';
        
        // Time formatting for MySQL
        $raw_start  = isset( $_POST['start_time'] ) ? sanitize_text_field( wp_unslash( $_POST['start_time'] ) ) : '';
        $raw_end    = isset( $_POST['end_time'] ) ? sanitize_text_field( wp_unslash( $_POST['end_time'] ) ) : '';
        $start_time = ! empty( $raw_start ) ? gmdate( 'H:i:s', strtotime( $raw_start ) ) : '00:00:00';
        $end_time   = ! empty( $raw_end ) ? gmdate( 'H:i:s', strtotime( $raw_end ) ) : '00:00:00';

        $room_no = isset( $_POST['room_no'] ) ? sanitize_text_field( wp_unslash( $_POST['room_no'] ) ) : '';

        $final_class_id = $class_unit_id > 0 ? $class_unit_id : 0;

        // Fallback if no specific section unit was chosen
        if ( 0 === $final_class_id && ! empty( $class_name_val ) ) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $unit_match = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM `{$table_units}` WHERE class_name = %s ORDER BY sort_order ASC, id ASC LIMIT 1", $class_name_val ) );
            // phpcs:enable
            if ( $unit_match ) {
                $final_class_id = (int) $unit_match->id;
            }
        }

        if ( $final_class_id > 0 && $subject_id > 0 && ! empty( $day_name ) ) {
            $data = array(
                'class_id'   => $final_class_id,
                'subject_id' => $subject_id,
                'day_name'   => $day_name,
                'shift'      => $shift,
                'start_time' => $start_time,
                'end_time'   => $end_time,
                'room_no'    => $room_no,
            );
            $format = array( '%d', '%d', '%s', '%s', '%s', '%s', '%s' );

            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $inserted = $wpdb->insert( $table_routine, $data, $format );
            // phpcs:enable

            if ( $inserted ) {
                if ( function_exists( 'educore_log_activity' ) ) {
                    /* translators: 1: Day name, 2: Shift name */
                    educore_log_activity( sprintf( __( 'Added new class routine slot for %1$s (%2$s)', 'ifsedu-school-management' ), $day_name, $shift ) );
                }
                
                $clean_url       = remove_query_arg( array( 'filter_class', 'filter_section', 'filter_shift' ), $base_url );
                $redirect_target = add_query_arg( array( 'status' => 'success' ), $clean_url );

                if ( ! headers_sent() ) {
                    wp_safe_redirect( $redirect_target );
                    exit;
                } else {
                    echo '<script type="text/javascript">window.location.href=' . wp_json_encode( esc_url_raw( $redirect_target ) ) . ';</script>';
                    exit;
                }
            } else {
                $db_error        = $wpdb->last_error;
                $redirect_target = add_query_arg( array( 'status' => 'db_error', 'msg' => urlencode( $db_error ) ), $base_url );
                if ( ! headers_sent() ) {
                    wp_safe_redirect( $redirect_target );
                    exit;
                } else {
                    echo '<script type="text/javascript">window.location.href=' . wp_json_encode( esc_url_raw( $redirect_target ) ) . ';</script>';
                    exit;
                }
            }
        } else {
            $redirect_target = add_query_arg( array( 'status' => 'validation_error' ), $base_url );
            if ( ! headers_sent() ) {
                wp_safe_redirect( $redirect_target );
                exit;
            } else {
                echo '<script type="text/javascript">window.location.href=' . wp_json_encode( esc_url_raw( $redirect_target ) ) . ';</script>';
                exit;
            }
        }
    }

    // 1. Fetch Distinct Classes ordered by sort_order
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $raw_classes_data = $wpdb->get_results( 
        "SELECT class_name, MIN(sort_order) as min_sort_order 
         FROM `{$table_units}` 
         WHERE class_name IS NOT NULL AND class_name != '' 
         GROUP BY class_name 
         ORDER BY min_sort_order ASC, CAST(class_name AS UNSIGNED) ASC, class_name ASC"
    );
    // phpcs:enable

    $classes = array();
    if ( ! empty( $raw_classes_data ) && is_array( $raw_classes_data ) ) {
        foreach ( $raw_classes_data as $c_row ) {
            $classes[] = $c_row->class_name;
        }
    }

    // 2. Fetch All Academic Units (Classes & Sections) ordered by sort_order
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $all_units = $wpdb->get_results( 
        "SELECT id, class_name, section_name, dept_name, sort_order 
         FROM `{$table_units}` 
         WHERE class_name != '' 
         ORDER BY sort_order ASC, CAST(class_name AS UNSIGNED) ASC, class_name ASC, section_name ASC"
    );

    // 3. Fetch Subjects mapped with Class/Unit Setup ordered by sort_order and subject_order
    $subjects = $wpdb->get_results(
        "SELECT s.id, s.subject_name, s.subject_code, s.class_id, s.subject_order, u.class_name, u.section_name, u.sort_order as class_sort_order 
         FROM `{$table_subjects}` s 
         LEFT JOIN `{$table_units}` u ON s.class_id = u.id 
         ORDER BY u.sort_order ASC, CAST(u.class_name AS UNSIGNED) ASC, u.class_name ASC, u.section_name ASC, s.subject_order ASC, s.subject_name ASC"
    );
    // phpcs:enable

    $days = array( 'Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday' );

    // Preview Filter Params
    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $filter_class = isset( $_GET['filter_class'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_class'] ) ) : '';
    $filter_sec   = isset( $_GET['filter_section'] ) ? absint( wp_unslash( $_GET['filter_section'] ) ) : 0;
    $filter_shift = isset( $_GET['filter_shift'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_shift'] ) ) : '';
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    // Fetch Routines with Assigned Teacher Resolution via Unified Dynamic Builder (Ordered by Class sort_order)
    $where_clauses = array( '1=1' );
    $query_params  = array();

    if ( ! empty( $filter_class ) ) {
        $where_clauses[] = 'u.class_name = %s';
        $query_params[]  = $filter_class;
    }

    if ( $filter_sec > 0 ) {
        $where_clauses[] = 'r.class_id = %d';
        $query_params[]  = $filter_sec;
    }

    if ( ! empty( $filter_shift ) ) {
        $where_clauses[] = 'r.shift = %s';
        $query_params[]  = $filter_shift;
    }

    $where_sql = ' WHERE ' . implode( ' AND ', $where_clauses );

    $routines_sql = "SELECT r.*, u.class_name, u.section_name, u.sort_order as class_sort_order, s.subject_name, st.full_name as teacher_name, st.designation 
                     FROM `{$table_routine}` r 
                     LEFT JOIN `{$table_units}` u ON r.class_id = u.id 
                     LEFT JOIN `{$table_subjects}` s ON r.subject_id = s.id
                     LEFT JOIN `{$table_teacher_subjects}` ts ON (ts.subject_id = r.subject_id AND ts.class_id = r.class_id)
                     LEFT JOIN `{$table_staff}` st ON ts.teacher_id = st.id
                     {$where_sql}
                     ORDER BY FIELD(r.day_name, 'Saturday','Sunday','Monday','Tuesday','Wednesday','Thursday','Friday'), u.sort_order ASC, CAST(u.class_name AS UNSIGNED) ASC, u.class_name ASC, u.section_name ASC, r.start_time ASC";

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    if ( ! empty( $query_params ) ) {
        $routines = $wpdb->get_results( $wpdb->prepare( $routines_sql, ...$query_params ) );
    } else {
        $routines = $wpdb->get_results( $routines_sql );
    }
    // phpcs:enable

    // Map routines by Day for weekly matrix preview
    $matrix_routine = array();
    if ( ! empty( $routines ) ) {
        foreach ( $routines as $rt ) {
            $matrix_routine[ $rt->day_name ][] = $rt;
        }
    }
    ?>

    <div class="ifs-educore-routine-container">

        <!-- Notifications -->
        <?php 
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        if ( isset( $_GET['status'] ) && 'success' === $_GET['status'] ) : ?>
            <div class="ifs-educore-alert-success">
                <span class="dashicons dashicons-yes-alt"></span>
                <?php esc_html_e( 'New class routine slot saved successfully.', 'ifsedu-school-management' ); ?>
            </div>
        <?php elseif ( isset( $_GET['status'] ) && 'deleted' === $_GET['status'] ) : ?>
            <div class="ifs-educore-alert-success">
                <span class="dashicons dashicons-yes-alt"></span>
                <?php esc_html_e( 'Routine slot deleted successfully.', 'ifsedu-school-management' ); ?>
            </div>
        <?php elseif ( isset( $_GET['status'] ) && 'db_error' === $_GET['status'] ) : ?>
            <div class="ifs-educore-alert-error">
                <span class="dashicons dashicons-warning"></span>
                <div>
                    <strong><?php esc_html_e( 'Database Error:', 'ifsedu-school-management' ); ?></strong>
                    <?php esc_html_e( 'Could not save the routine slot.', 'ifsedu-school-management' ); ?>
                    <?php if ( ! empty( $_GET['msg'] ) ) : ?>
                        <br><span style="font-size: 12px; opacity: 0.8; font-family: monospace;"><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['msg'] ) ) ); ?></span>
                    <?php endif; ?>
                </div>
            </div>
        <?php elseif ( isset( $_GET['status'] ) && 'validation_error' === $_GET['status'] ) : ?>
            <div class="ifs-educore-alert-error">
                <span class="dashicons dashicons-warning"></span>
                <?php esc_html_e( 'Validation Error: Please ensure you have selected a Target Class and an Academic Subject.', 'ifsedu-school-management' ); ?>
            </div>
        <?php endif; 
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        ?>

        <!-- 1. Add New Routine Bento Box -->
        <div class="ifs-educore-bento-card no-print">
            
            <form method="POST" action="<?php echo esc_url( $base_url ); ?>">
                <?php wp_nonce_field( 'routine_action', 'routine_nonce' ); ?>
                
                <div class="ifs-educore-routine-form-grid">
                    <!-- Target Class -->
                    <div class="ifs-educore-input-wrapper">
                        <label class="ifs-educore-form-label"><?php esc_html_e( 'Target Class', 'ifsedu-school-management' ); ?> <span style="color:#ef4444;">*</span></label>
                        <select name="class_id" id="ifs_educore_class_select" class="ifs-educore-field-select" required>
                            <option value=""><?php esc_html_e( '-- Choose Class --', 'ifsedu-school-management' ); ?></option>
                            <?php foreach ( $classes as $cls_name ) : ?>
                                <option value="<?php echo esc_attr( $cls_name ); ?>"><?php echo esc_html( $cls_name ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Section Selection -->
                    <div class="ifs-educore-input-wrapper">
                        <label class="ifs-educore-form-label"><?php esc_html_e( 'Section', 'ifsedu-school-management' ); ?></label>
                        <select name="section_id" id="ifs_educore_section_select" class="ifs-educore-field-select">
                            <option value=""><?php esc_html_e( '-- Choose Section --', 'ifsedu-school-management' ); ?></option>
                        </select>
                    </div>

                    <!-- Academic Shift -->
                    <div class="ifs-educore-input-wrapper">
                        <label class="ifs-educore-form-label"><?php esc_html_e( 'Shift', 'ifsedu-school-management' ); ?></label>
                        <select name="shift" class="ifs-educore-field-select">
                            <option value="No Shift"><?php esc_html_e( 'No Shift', 'ifsedu-school-management' ); ?></option>
                            <option value="Morning Shift"><?php esc_html_e( 'Morning Shift', 'ifsedu-school-management' ); ?></option>
                            <option value="Day Shift"><?php esc_html_e( 'Day Shift', 'ifsedu-school-management' ); ?></option>
                        </select>
                    </div>

                    <!-- Subject Selection -->
                    <div class="ifs-educore-input-wrapper">
                        <label class="ifs-educore-form-label"><?php esc_html_e( 'Academic Subject', 'ifsedu-school-management' ); ?> <span style="color:#ef4444;">*</span></label>
                        <select name="subject_id" id="ifs_educore_subject_select" class="ifs-educore-field-select" required>
                            <option value=""><?php esc_html_e( '-- Choose Subject --', 'ifsedu-school-management' ); ?></option>
                        </select>
                    </div>

                    <!-- Day Selection -->
                    <div class="ifs-educore-input-wrapper">
                        <label class="ifs-educore-form-label"><?php esc_html_e( 'Day', 'ifsedu-school-management' ); ?> <span style="color:#ef4444;">*</span></label>
                        <select name="day_name" class="ifs-educore-field-select" required>
                            <?php foreach ( $days as $d ) : ?>
                                <option value="<?php echo esc_attr( $d ); ?>"><?php echo esc_html( $d ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Start Time -->
                    <div class="ifs-educore-input-wrapper">
                        <label class="ifs-educore-form-label"><?php esc_html_e( 'Start Time', 'ifsedu-school-management' ); ?> <span style="color:#ef4444;">*</span></label>
                        <input type="time" name="start_time" class="ifs-educore-field-input" required>
                    </div>

                    <!-- End Time -->
                    <div class="ifs-educore-input-wrapper">
                        <label class="ifs-educore-form-label"><?php esc_html_e( 'End Time', 'ifsedu-school-management' ); ?> <span style="color:#ef4444;">*</span></label>
                        <input type="time" name="end_time" class="ifs-educore-field-input" required>
                    </div>

                    <!-- Room No -->
                    <div class="ifs-educore-input-wrapper">
                        <label class="ifs-educore-form-label"><?php esc_html_e( 'Room No', 'ifsedu-school-management' ); ?></label>
                        <input type="text" name="room_no" class="ifs-educore-field-input" placeholder="<?php esc_attr_e( 'e.g. 101', 'ifsedu-school-management' ); ?>">
                    </div>

                    <!-- Submit Button -->
                    <div class="ifs-educore-input-wrapper">
                        <button type="submit" name="save_routine" class="ifs-educore-btn-save">
                            <span class="dashicons dashicons-plus-alt2" style="font-size:16px; width:16px; height:16px;"></span>
                            <?php esc_html_e( 'Save Slot', 'ifsedu-school-management' ); ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- 2. Interactive Weekly Matrix Preview -->
        <div class="ifs-educore-bento-card">
            <div class="ifs-educore-card-header">
                <h5 class="ifs-educore-card-title">
                    <span class="dashicons dashicons-visibility"></span>
                    <?php esc_html_e( 'Weekly Timetable Preview', 'ifsedu-school-management' ); ?>
                </h5>
            </div>

            <!-- Filter Bar -->
            <form method="GET" action="" class="ifs-educore-filter-bar no-print">
                <?php 
                // phpcs:disable WordPress.Security.NonceVerification.Recommended
                foreach ( $_GET as $key => $val ) {
                    if ( ! in_array( $key, array( 'filter_class', 'filter_section', 'filter_shift' ), true ) ) {
                        echo '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( sanitize_text_field( wp_unslash( $val ) ) ) . '">';
                    }
                }
                // phpcs:enable WordPress.Security.NonceVerification.Recommended
                ?>
                <div style="font-weight: 700; font-size: 13px; color: #475569;"><?php esc_html_e( 'Filter Schedule:', 'ifsedu-school-management' ); ?></div>
                
                <!-- Filter Class -->
                <select name="filter_class" id="ifs_educore_filter_class" class="ifs-educore-field-select" style="width: auto; height: 36px;">
                    <option value=""><?php esc_html_e( '-- All Classes --', 'ifsedu-school-management' ); ?></option>
                    <?php foreach ( $classes as $cls_name ) : ?>
                        <option value="<?php echo esc_attr( $cls_name ); ?>" <?php selected( $filter_class, $cls_name ); ?>>
                            <?php echo esc_html( $cls_name ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <!-- Filter Section -->
                <select name="filter_section" id="ifs_educore_filter_section" class="ifs-educore-field-select" style="width: auto; height: 36px;">
                    <option value=""><?php esc_html_e( '-- All Sections --', 'ifsedu-school-management' ); ?></option>
                </select>

                <!-- Filter Shift -->
                <select name="filter_shift" class="ifs-educore-field-select" style="width: auto; height: 36px;">
                    <option value=""><?php esc_html_e( '-- All Shifts --', 'ifsedu-school-management' ); ?></option>
                    <option value="No Shift" <?php selected( $filter_shift, 'No Shift' ); ?>><?php esc_html_e( 'No Shift', 'ifsedu-school-management' ); ?></option>
                    <option value="Morning Shift" <?php selected( $filter_shift, 'Morning Shift' ); ?>><?php esc_html_e( 'Morning Shift', 'ifsedu-school-management' ); ?></option>
                    <option value="Day Shift" <?php selected( $filter_shift, 'Day Shift' ); ?>><?php esc_html_e( 'Day Shift', 'ifsedu-school-management' ); ?></option>
                </select>

                <button type="submit" class="ifs-educore-btn-secondary" style="height: 36px; padding: 0 14px;">
                    <span class="dashicons dashicons-filter" style="font-size:14px; width:14px; height:14px;"></span>
                    <?php esc_html_e( 'Apply Filter', 'ifsedu-school-management' ); ?>
                </button>

                <?php if ( ! empty( $filter_class ) || $filter_sec > 0 || ! empty( $filter_shift ) ) : ?>
                    <a href="<?php echo esc_url( $base_url ); ?>" class="ifs-educore-square-btn" style="width: auto; padding: 0 10px; height: 34px; border-radius: 8px; text-decoration: none;" title="<?php esc_attr_e( 'Clear Filters', 'ifsedu-school-management' ); ?>">
                        <?php esc_html_e( 'Reset Filter', 'ifsedu-school-management' ); ?>
                    </a>
                <?php endif; ?>
            </form>

            <!-- Weekly Matrix Layout Grid -->
            <div class="ifs-educore-weekly-matrix">
                <?php foreach ( $days as $day ) : ?>
                    <div class="ifs-educore-day-column">
                        <div class="ifs-educore-day-header"><?php echo esc_html( $day ); ?></div>
                        <div class="ifs-educore-day-slots">
                            <?php if ( ! empty( $matrix_routine[ $day ] ) ) : ?>
                                <?php foreach ( $matrix_routine[ $day ] as $slot ) : 
                                    $slot_id           = absint( $slot->id );
                                    $shift_val         = ! empty( $slot->shift ) ? $slot->shift : 'No Shift';
                                    $shift_badge_class = 'ifs-educore-shift-none';
                                    if ( 'Morning Shift' === $shift_val ) {
                                        $shift_badge_class = 'ifs-educore-shift-morning';
                                    } elseif ( 'Day Shift' === $shift_val ) {
                                        $shift_badge_class = 'ifs-educore-shift-day';
                                    }

                                    $delete_slot_url = wp_nonce_url(
                                        add_query_arg(
                                            array(
                                                'page'   => 'school_management_system',
                                                'tab'    => 'academics',
                                                'subtab' => 'routine',
                                                'action' => 'delete_routine',
                                                'id'     => $slot_id,
                                            ),
                                            admin_url( 'admin.php' )
                                        ),
                                        'delete_routine_' . $slot_id
                                    );

                                    $start_ts = ! empty( $slot->start_time ) ? strtotime( $slot->start_time ) : false;
                                    $end_ts   = ! empty( $slot->end_time ) ? strtotime( $slot->end_time ) : false;
                                ?>
                                    <div class="ifs-educore-slot-card">
                                        <div style="display: flex; justify-content: space-between; align-items: center;">
                                            <div class="ifs-educore-slot-time">
                                                <span class="dashicons dashicons-clock" style="font-size:11px; width:11px; height:11px;"></span>
                                                <span>
                                                    <?php 
                                                    $s_str = $start_ts ? wp_date( 'g:i A', $start_ts ) : '—';
                                                    $e_str = $end_ts ? wp_date( 'g:i A', $end_ts ) : '—';
                                                    echo esc_html( $s_str . ' - ' . $e_str ); 
                                                    ?>
                                                </span>
                                            </div>
                                            <a href="<?php echo esc_url( $delete_slot_url ); ?>" class="ifs-educore-square-btn no-print" style="width: 20px; height: 20px;" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete this routine slot?', 'ifsedu-school-management' ) ); ?>');" title="<?php esc_attr_e( 'Delete Slot', 'ifsedu-school-management' ); ?>">
                                                <span class="dashicons dashicons-trash" style="font-size: 11px; width: 11px; height: 11px;"></span>
                                            </a>
                                        </div>
                                        <div class="ifs-educore-slot-subject"><?php echo esc_html( $slot->subject_name ); ?></div>
                                        <div class="ifs-educore-slot-meta">
                                            <span>
                                                <?php esc_html_e( 'Cls:', 'ifsedu-school-management' ); ?> <strong><?php echo esc_html( $slot->class_name ); ?></strong> <?php echo ! empty( $slot->section_name ) ? '(' . esc_html( $slot->section_name ) . ')' : ''; ?>
                                                <?php if ( 'No Shift' !== $shift_val ) : ?>
                                                    <span class="ifs-educore-shift-badge <?php echo esc_attr( $shift_badge_class ); ?>"><?php echo esc_html( $shift_val ); ?></span>
                                                <?php endif; ?>
                                            </span>
                                            <span><?php esc_html_e( 'Rm:', 'ifsedu-school-management' ); ?> <strong><?php echo esc_html( $slot->room_no ? $slot->room_no : 'N/A' ); ?></strong></span>
                                        </div>
                                        <?php if ( ! empty( $slot->teacher_name ) ) : ?>
                                            <div class="ifs-educore-slot-teacher">
                                                <span class="dashicons dashicons-businessman" style="font-size:12px; width:12px; height:12px;"></span>
                                                <span><?php echo esc_html( $slot->teacher_name ); ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <div style="text-align: center; color: #cbd5e1; font-size: 12px; padding: 20px 0; font-weight: 600;">
                                    <?php esc_html_e( 'No Classes', 'ifsedu-school-management' ); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>

    <!-- Dynamic Script for Class Setup Cascading Resolution -->
    <script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function() {
        var unitsMap = <?php echo wp_json_encode( ! empty( $all_units ) ? $all_units : array() ); ?>;
        var subjectsMap = <?php echo wp_json_encode( ! empty( $subjects ) ? $subjects : array() ); ?>;
        var currentFilterSection = <?php echo wp_json_encode( $filter_sec ); ?>;

        // Populate dynamic sections strictly from Class Setup (sms_academic_units)
        function populateSections(classSelectElem, sectionSelectElem, selectedSecId) {
            selectedSecId = selectedSecId || '';
            var selectedClass = classSelectElem.value;
            sectionSelectElem.innerHTML = '<option value=""><?php echo esc_js( __( '-- Choose Section --', 'ifsedu-school-management' ) ); ?></option>';

            if (!selectedClass) return;

            var filtered = unitsMap.filter(function(item) { return item.class_name === selectedClass; });

            filtered.forEach(function(unit) {
                if (unit.section_name) {
                    var opt = document.createElement('option');
                    opt.value = unit.id;
                    opt.textContent = unit.section_name;
                    if (String(unit.id) === String(selectedSecId)) {
                        opt.selected = true;
                    }
                    sectionSelectElem.appendChild(opt);
                }
            });
        }

        // Populate dynamic subjects based on Class and Section Setup
        function populateSubjects(classSelectElem, sectionSelectElem, subjectSelectElem) {
            var selectedClass = classSelectElem.value;
            var selectedUnitId = sectionSelectElem ? sectionSelectElem.value : '';

            subjectSelectElem.innerHTML = '<option value=""><?php echo esc_js( __( '-- Choose Subject --', 'ifsedu-school-management' ) ); ?></option>';

            if (!selectedClass) return;

            var filteredSubjects = [];

            if (selectedUnitId) {
                filteredSubjects = subjectsMap.filter(function(item) { return String(item.class_id) === String(selectedUnitId); });
                if (filteredSubjects.length === 0) {
                    filteredSubjects = subjectsMap.filter(function(item) { return item.class_name === selectedClass; });
                }
            } else {
                filteredSubjects = subjectsMap.filter(function(item) { return item.class_name === selectedClass; });
            }

            var seen = new Set();
            filteredSubjects.forEach(function(subject) {
                if (!seen.has(subject.id)) {
                    seen.add(subject.id);
                    var opt = document.createElement('option');
                    opt.value = subject.id;
                    opt.textContent = subject.subject_name + (subject.subject_code ? ' [' + subject.subject_code + ']' : '');
                    subjectSelectElem.appendChild(opt);
                }
            });
        }

        // 1. Creation Form Setup
        var formClassSelect = document.getElementById('ifs_educore_class_select');
        var formSecSelect   = document.getElementById('ifs_educore_section_select');
        var formSubjectSelect = document.getElementById('ifs_educore_subject_select');
        
        if (formClassSelect && formSecSelect && formSubjectSelect) {
            formClassSelect.addEventListener('change', function() {
                populateSections(formClassSelect, formSecSelect);
                populateSubjects(formClassSelect, formSecSelect, formSubjectSelect);
            });

            formSecSelect.addEventListener('change', function() {
                populateSubjects(formClassSelect, formSecSelect, formSubjectSelect);
            });
        }

        // 2. Filter Bar Setup
        var filterClassSelect = document.getElementById('ifs_educore_filter_class');
        var filterSecSelect   = document.getElementById('ifs_educore_filter_section');
        if (filterClassSelect && filterSecSelect) {
            populateSections(filterClassSelect, filterSecSelect, currentFilterSection);

            filterClassSelect.addEventListener('change', function() {
                populateSections(filterClassSelect, filterSecSelect);
            });
        }
    });
    </script>
    <?php
}

educore_class_routine_view();
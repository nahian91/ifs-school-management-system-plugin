<?php
/**
 * Monthly Attendance Summary Audit & Reports
 * File: inc/attendance/attendance-monthly.php
 * Teacher Scope: Restricts Class/Section/Student dropdowns to `sms_teacher_subjects` for logged-in Teachers.
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Direct access safety buffer
}

function educore_monthly_attendance_summary_view( $classes, $sections, $filter_class, $filter_section ) {
    global $wpdb;

    $current_user = wp_get_current_user();
    $is_admin     = current_user_can( 'manage_options' );

    $table_students         = $wpdb->prefix . 'sms_students';
    $table_attendance       = $wpdb->prefix . 'sms_attendance';
    $table_units            = $wpdb->prefix . 'sms_academic_units';
    $table_staff            = $wpdb->prefix . 'sms_staff';
    $table_teacher_subjects = $wpdb->prefix . 'sms_teacher_subjects';

    // 1. Resolve Exact Assigned Classes & Sections for Non-Admin Teachers from sms_teacher_subjects
    $teacher_assigned_classes  = array();
    $teacher_assigned_sections = array();
    $assigned_unit_ids         = array();

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    if ( ! $is_admin ) {
        $teacher_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM `{$table_staff}` WHERE email = %s OR full_name = %s LIMIT 1",
                $current_user->user_email,
                $current_user->display_name
            )
        );

        if ( $teacher_id ) {
            $allocations = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT DISTINCT u.id AS unit_id, u.class_name, u.section_name, u.sort_order 
                     FROM `{$table_teacher_subjects}` ts
                     INNER JOIN `{$table_units}` u ON ts.class_id = u.id
                     WHERE ts.teacher_id = %d AND u.class_name != ''
                     ORDER BY u.sort_order ASC, CAST(u.class_name AS UNSIGNED) ASC, u.class_name ASC, u.section_name ASC",
                    intval( $teacher_id )
                )
            );

            if ( ! empty( $allocations ) ) {
                foreach ( $allocations as $al ) {
                    $assigned_unit_ids[] = intval( $al->unit_id );
                    $c_val = trim( (string) $al->class_name );
                    $s_val = trim( (string) $al->section_name );
                    if ( ! empty( $c_val ) && ! in_array( $c_val, $teacher_assigned_classes, true ) ) {
                        $teacher_assigned_classes[] = $c_val;
                    }
                    if ( ! empty( $s_val ) && ! in_array( $s_val, $teacher_assigned_sections, true ) ) {
                        $teacher_assigned_sections[] = $s_val;
                    }
                }
            }
        }
        // phpcs:enable
        $classes = $teacher_assigned_classes;
    }

    // Build sort_order dictionary for classes
    $class_order_rows = $wpdb->get_results( "SELECT class_name, MIN(sort_order) as min_sort FROM `{$table_units}` GROUP BY class_name" );
    $class_order_map  = array();
    if ( ! empty( $class_order_rows ) ) {
        foreach ( $class_order_rows as $cor ) {
            $class_order_map[ $cor->class_name ] = (int) $cor->min_sort;
        }
    }

    // Apply sort_order then natural comparison to classes
    if ( ! empty( $classes ) ) {
        usort( $classes, function( $a, $b ) use ( $class_order_map ) {
            $order_a = isset( $class_order_map[ $a ] ) ? $class_order_map[ $a ] : 0;
            $order_b = isset( $class_order_map[ $b ] ) ? $class_order_map[ $b ] : 0;
            if ( $order_a !== $order_b ) {
                return $order_a - $order_b;
            }
            return strnatcasecmp( $a, $b );
        } );
    }

    // 2. Fetch academic units scoped to teacher's assignments or global (Ordered by sort_order)
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    if ( ! $is_admin && ! empty( $assigned_unit_ids ) ) {
        $unit_placeholders = implode( ',', array_map( 'absint', $assigned_unit_ids ) );
        $all_units = $wpdb->get_results(
            "SELECT id, class_name, section_name, sort_order FROM `{$table_units}` WHERE id IN ({$unit_placeholders}) AND section_name != '' ORDER BY sort_order ASC, section_name ASC"
        );
    } else {
        $all_units = $wpdb->get_results( "SELECT id, class_name, section_name, sort_order FROM `{$table_units}` WHERE section_name != '' ORDER BY sort_order ASC, CAST(class_name AS UNSIGNED) ASC, class_name ASC, section_name ASC" );
    }
    // phpcs:enable

    // Auto-select class & section for teachers if not explicitly chosen
    if ( ! $is_admin && empty( $filter_class ) && ! empty( $classes[0] ) ) {
        $filter_class = $classes[0];
    }
    if ( ! $is_admin && empty( $filter_section ) && ! empty( $all_units ) ) {
        foreach ( $all_units as $unit_row ) {
            if ( $unit_row->class_name === $filter_class && ! empty( $unit_row->section_name ) ) {
                $filter_section = $unit_row->section_name;
                break;
            }
        }
    }

    // 3. Fetch all active students scoped to Assigned Classes & Sections
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    if ( ! $is_admin && ! empty( $classes ) ) {
        $class_placeholders = implode( ',', array_map( function( $val ) use ( $wpdb ) {
            return "'" . esc_sql( $val ) . "'";
        }, $classes ) );

        $st_query = "SELECT id, class_name, section_name, full_name, roll_no FROM `{$table_students}` WHERE status = 'Active' AND class_name IN ({$class_placeholders})";

        if ( ! empty( $teacher_assigned_sections ) ) {
            $sec_placeholders = implode( ',', array_map( function( $val ) use ( $wpdb ) {
                return "'" . esc_sql( $val ) . "'";
            }, $teacher_assigned_sections ) );
            $st_query        .= " AND section_name IN ({$sec_placeholders})";
        }

        $st_query .= ' ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC';
        $all_active_students = $wpdb->get_results( $st_query );
    } else {
        $all_active_students = $wpdb->get_results( "SELECT id, class_name, section_name, full_name, roll_no FROM `{$table_students}` WHERE status = 'Active' ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC" );
    }
    // phpcs:enable

    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $selected_month = isset( $_GET['month'] ) ? sanitize_text_field( wp_unslash( $_GET['month'] ) ) : current_time( 'Y-m' );
    $filter_student = isset( $_GET['filter_student'] ) ? absint( wp_unslash( $_GET['filter_student'] ) ) : 0;
    // phpcs:enable WordPress.Security.NonceVerification.Recommended
    
    $start_date    = $selected_month . '-01';
    $end_date      = gmdate( 'Y-m-t', strtotime( $start_date ) );
    $days_in_month = (int) gmdate( 't', strtotime( $start_date ) );

    $students       = array();
    $daily_records  = array();
    $summary_counts = array();

    if ( ! empty( $filter_class ) ) {
        // Enforce boundary check for non-admin teachers
        if ( ! $is_admin && ! in_array( $filter_class, $classes, true ) ) {
            echo '<div class="ifs-educore-alert-danger">' . esc_html__( 'You are not authorized to view the monthly summary for this class.', 'ifsedu-school-management' ) . '</div>';
            return;
        }

        $query  = "SELECT id, student_id, full_name, roll_no FROM `{$table_students}` WHERE status = 'Active' AND class_name = %s";
        $params = array( $filter_class );

        if ( ! empty( $filter_section ) ) {
            $query   .= ' AND section_name = %s';
            $params[] = $filter_section;
        } elseif ( ! $is_admin && ! empty( $teacher_assigned_sections ) ) {
            $sec_placeholders = implode( ',', array_fill( 0, count( $teacher_assigned_sections ), '%s' ) );
            $query            .= " AND section_name IN ({$sec_placeholders})";
            $params            = array_merge( $params, $teacher_assigned_sections );
        }

        if ( $filter_student > 0 ) {
            $query   .= ' AND id = %d';
            $params[] = $filter_student;
        }

        $query .= ' ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC';

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $students = $wpdb->get_results( $wpdb->prepare( $query, ...$params ) );

        if ( ! empty( $students ) ) {
            $student_ids  = array_map( 'absint', wp_list_pluck( $students, 'id' ) );
            $placeholders = implode( ',', $student_ids );

            // Fetch day-by-day attendance entries for matrix grid
            $raw_daily = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT student_id, attendance_date, status
                     FROM `{$table_attendance}`
                     WHERE attendance_date BETWEEN %s AND %s AND student_id IN ({$placeholders})",
                    $start_date,
                    $end_date
                )
            );

            if ( ! empty( $raw_daily ) ) {
                foreach ( $raw_daily as $entry ) {
                    $day_num = (int) gmdate( 'j', strtotime( $entry->attendance_date ) );
                    $daily_records[ $entry->student_id ][ $day_num ] = $entry->status;
                    
                    if ( ! isset( $summary_counts[ $entry->student_id ][ $entry->status ] ) ) {
                        $summary_counts[ $entry->student_id ][ $entry->status ] = 0;
                    }
                    $summary_counts[ $entry->student_id ][ $entry->status ]++;
                }
            }
        }
        // phpcs:enable
    }
    ?>

    <div class="ifs-educore-attendance-root">

        <!-- Monthly Filter Control Bento Card -->
        <div class="ifs-educore-bento-card no-print" style="margin-bottom:24px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:14px;">
                <h4 style="margin:0; font-size:16px; font-weight:800; color:#00523c;"><?php esc_html_e( 'Monthly Attendance Summary & Audit', 'ifsedu-school-management' ); ?></h4>
                <?php if ( ! $is_admin ) : ?>
                    <span style="background:#ecfdf5; color:#065f46; border:1px solid #a7f3d0; padding:4px 12px; border-radius:20px; font-size:12px; font-weight:700;">
                        <span class="dashicons dashicons-lock" style="font-size:14px; width:14px; height:14px; vertical-align:middle;"></span>
                        <?php esc_html_e( 'Teacher Mode: Assigned Allocations Only', 'ifsedu-school-management' ); ?>
                    </span>
                <?php endif; ?>
            </div>

            <form method="GET" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="display:flex; gap:16px; align-items:flex-end; flex-wrap:wrap;">
                <input type="hidden" name="page" value="school_management_system">
                <input type="hidden" name="tab" value="attendance">
                <input type="hidden" name="sub" value="monthly">

                <div class="ifs-educore-form-group" style="flex:1; min-width:160px;">
                    <label style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:6px;"><?php esc_html_e( 'Target Month', 'ifsedu-school-management' ); ?> *</label>
                    <input type="month" name="month" class="ifs-educore-input-field" value="<?php echo esc_attr( $selected_month ); ?>" max="<?php echo esc_attr( current_time( 'Y-m' ) ); ?>" required>
                </div>

                <div class="ifs-educore-form-group" style="flex:1; min-width:160px;">
                    <label style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:6px;">
                        <?php esc_html_e( 'Class', 'ifsedu-school-management' ); ?> *
                        <?php if ( ! $is_admin ) : ?>
                            <span style="color:#059669; font-size:11px; font-weight:700;">(<?php esc_html_e( 'Assigned Only', 'ifsedu-school-management' ); ?>)</span>
                        <?php endif; ?>
                    </label>
                    <select name="class_name" id="ifs_educore_attendance_class_select" class="ifs-educore-select-field" required>
                        <option value=""><?php esc_html_e( '-- Select Class --', 'ifsedu-school-management' ); ?></option>
                        <?php foreach ( $classes as $cls ) : ?>
                            <option value="<?php echo esc_attr( $cls ); ?>" <?php selected( $filter_class, $cls ); ?>><?php echo esc_html( $cls ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="ifs-educore-form-group" style="flex:1; min-width:160px;">
                    <label style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:6px;">
                        <?php esc_html_e( 'Section', 'ifsedu-school-management' ); ?>
                        <?php if ( ! $is_admin ) : ?>
                            <span style="color:#059669; font-size:11px; font-weight:700;">(<?php esc_html_e( 'Assigned', 'ifsedu-school-management' ); ?>)</span>
                        <?php endif; ?>
                    </label>
                    <select name="section_name" id="ifs_educore_attendance_section_select" class="ifs-educore-select-field">
                        <option value=""><?php esc_html_e( '-- All Sections --', 'ifsedu-school-management' ); ?></option>
                    </select>
                </div>
                
                <div class="ifs-educore-form-group" style="flex:1; min-width:200px;">
                    <label style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:6px;"><?php esc_html_e( 'Student (Optional)', 'ifsedu-school-management' ); ?></label>
                    <select name="filter_student" id="ifs_educore_attendance_student_select" class="ifs-educore-select-field">
                        <option value=""><?php esc_html_e( '-- All Students --', 'ifsedu-school-management' ); ?></option>
                    </select>
                </div>

                <div class="ifs-educore-form-group">
                    <button type="submit" class="ifs-educore-btn-primary"><?php esc_html_e( 'Generate Monthly Audit', 'ifsedu-school-management' ); ?></button>
                </div>
            </form>
        </div>

        <?php if ( ! empty( $filter_class ) && ! empty( $students ) ) : ?>
            <div class="ifs-educore-bento-card">
                <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #f1f5f9; padding-bottom:16px; margin-bottom:20px;">
                    <div>
                    <h3 style="margin:0; font-size:18px; font-weight:800; color:#0f172a;">
                            <?php
                            $start_time = strtotime( $start_date );
                            printf(
                                /* translators: %s: Month and year (e.g. August 2026) */
                                esc_html__( 'Monthly Attendance Audit Statement: %s', 'ifsedu-school-management' ),
                                esc_html( $start_time ? date_i18n( 'F Y', $start_time ) : $selected_month )
                            );
                            ?>
                        </h3>
                        <span style="color:#64748b; font-size:13px; font-weight:600;">
                            <?php
                            printf(
                                /* translators: %1$s: Class name, %2$s: Section name (optional) */
                                esc_html__( 'Class: %1$s %2$s', 'ifsedu-school-management' ),
                                esc_html( $filter_class ),
                                esc_html( $filter_section ? '(' . $filter_section . ')' : '' )
                            );
                            ?>
                        </span>
                    </div>
                    <button type="button" onclick="window.print();" class="no-print" style="height:36px; padding:0 16px; background:#0f172a; color:#fff; border:none; border-radius:6px; font-weight:600; cursor:pointer;">
                        <span class="dashicons dashicons-printer" style="vertical-align:middle; font-size:16px; width:16px; height:16px;"></span>
                        <?php esc_html_e( 'Print Summary', 'ifsedu-school-management' ); ?>
                    </button>
                </div>

                <div style="overflow-x:auto;">
                    <table style="width:100%; border-collapse:collapse; text-align:left; font-size:12.5px;">
                        <thead>
                            <tr style="background:#f8fafc;">
                                <th style="padding:10px 8px; color:#475569; border-bottom:1px solid #e2e8f0; position:sticky; left:0; background:#f8fafc; min-width:50px;"><?php esc_html_e( 'Roll', 'ifsedu-school-management' ); ?></th>
                                <th style="padding:10px 8px; color:#475569; border-bottom:1px solid #e2e8f0; position:sticky; left:0; background:#f8fafc; min-width:140px;"><?php esc_html_e( 'Student Name', 'ifsedu-school-management' ); ?></th>
                                
                                <!-- Calendar Days Columns -->
                                <?php for ( $d = 1; $d <= $days_in_month; $d++ ) : ?>
                                    <th style="padding:6px 2px; color:#475569; border-bottom:1px solid #e2e8f0; text-align:center; min-width:28px; font-size:11px;"><?php echo esc_html( $d ); ?></th>
                                <?php endfor; ?>

                                <th style="padding:10px 8px; border-bottom:1px solid #e2e8f0; text-align:center; color:#059669; font-weight:800;"><?php esc_html_e( 'P', 'ifsedu-school-management' ); ?></th>
                                <th style="padding:10px 8px; border-bottom:1px solid #e2e8f0; text-align:center; color:#dc2626; font-weight:800;"><?php esc_html_e( 'A', 'ifsedu-school-management' ); ?></th>
                                <th style="padding:10px 8px; border-bottom:1px solid #e2e8f0; text-align:center; color:#d97706; font-weight:800;"><?php esc_html_e( 'L', 'ifsedu-school-management' ); ?></th>
                                <th style="padding:10px 8px; color:#475569; border-bottom:1px solid #e2e8f0; text-align:right; min-width:100px;"><?php esc_html_e( 'Ratio', 'ifsedu-school-management' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $students as $st ) : 
                                $st_id          = (int) $st->id;
                                $p_cnt          = isset( $summary_counts[ $st_id ]['Present'] ) ? $summary_counts[ $st_id ]['Present'] : 0;
                                $a_cnt          = isset( $summary_counts[ $st_id ]['Absent'] ) ? $summary_counts[ $st_id ]['Absent'] : 0;
                                $l_cnt          = isset( $summary_counts[ $st_id ]['Late'] ) ? $summary_counts[ $st_id ]['Late'] : 0;
                                $total_recorded = $p_cnt + $a_cnt + $l_cnt;
                                $pct            = $total_recorded > 0 ? round( ( $p_cnt / $total_recorded ) * 100, 1 ) : 0;
                                $pct_color      = $pct >= 80 ? '#059669' : ( $pct >= 60 ? '#d97706' : '#dc2626' );
                            ?>
                                <tr style="border-bottom:1px solid #f1f5f9;">
                                    <td style="padding:10px 8px;"><strong>#<?php echo esc_html( $st->roll_no ); ?></strong></td>
                                    <td style="padding:10px 8px;"><strong style="color:#0f172a;"><?php echo esc_html( $st->full_name ); ?></strong></td>
                                    
                                    <?php for ( $d = 1; $d <= $days_in_month; $d++ ) : 
                                        $st_status = isset( $daily_records[ $st_id ][ $d ] ) ? $daily_records[ $st_id ][ $d ] : '';
                                    ?>
                                        <td style="padding:4px 1px; text-align:center;">
                                            <?php if ( 'Present' === $st_status ) : ?>
                                                <span class="ifs-educore-att-status-badge ifs-educore-att-badge-p">P</span>
                                            <?php elseif ( 'Absent' === $st_status ) : ?>
                                                <span class="ifs-educore-att-status-badge ifs-educore-att-badge-a">A</span>
                                            <?php elseif ( 'Late' === $st_status ) : ?>
                                                <span class="ifs-educore-att-status-badge ifs-educore-att-badge-l">L</span>
                                            <?php else : ?>
                                                <span class="ifs-educore-att-status-badge ifs-educore-att-badge-empty">-</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endfor; ?>

                                    <td style="padding:10px 6px; text-align:center; font-weight:800; color:#059669; background:rgba(5, 150, 105, 0.03);"><?php echo esc_html( $p_cnt ); ?></td>
                                    <td style="padding:10px 6px; text-align:center; font-weight:800; color:#dc2626; background:rgba(220, 38, 38, 0.03);"><?php echo esc_html( $a_cnt ); ?></td>
                                    <td style="padding:10px 6px; text-align:center; font-weight:800; color:#d97706; background:rgba(217, 119, 6, 0.03);"><?php echo esc_html( $l_cnt ); ?></td>
                                    <td style="padding:10px 8px; text-align:right;">
                                        <strong style="color:<?php echo esc_attr( $pct_color ); ?>; font-size:12px;"><?php echo esc_html( $pct ); ?>%</strong>
                                        <div style="height:5px; background:#e2e8f0; border-radius:10px; overflow:hidden; margin-top:4px;">
                                            <div style="width:<?php echo esc_attr( $pct ); ?>%; height:100%; background:<?php echo esc_attr( $pct_color ); ?>; border-radius:10px;"></div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php elseif ( ! empty( $filter_class ) ) : ?>
            <div class="ifs-educore-alert-warning"><span class="dashicons dashicons-warning" style="font-size:24px; width:24px; height:24px; margin-bottom:10px; display:block; margin:0 auto;"></span><p style="margin:0;"><?php esc_html_e( 'No active student records found for the selected Class/Section.', 'ifsedu-school-management' ); ?></p></div>
        <?php else : ?>
            <div class="ifs-educore-alert-info"><span class="dashicons dashicons-info" style="font-size:24px; width:24px; height:24px; margin-bottom:10px; display:block; margin:0 auto;"></span><p style="margin:0;"><?php esc_html_e( 'Select a Target Month and Academic Class above to generate the attendance audit statement.', 'ifsedu-school-management' ); ?></p></div>
        <?php endif; ?>

        <!-- Dynamic JS Engine: Safe Class->Section->Student Chaining -->
        <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function() {
            var rawUnits = <?php echo wp_json_encode( ! empty( $all_units ) ? $all_units : array() ); ?>;
            var rawStudents = <?php echo wp_json_encode( ! empty( $all_active_students ) ? $all_active_students : array() ); ?>;
            
            var unitsMap = Array.isArray(rawUnits) ? rawUnits : [];
            var studentsMap = Array.isArray(rawStudents) ? rawStudents : [];
            
            var currentFilterSection = "<?php echo esc_js( $filter_section ); ?>";
            var currentFilterStudent = "<?php echo esc_js( $filter_student ); ?>";
            
            var classSelect = document.getElementById('ifs_educore_attendance_class_select');
            var sectionSelect = document.getElementById('ifs_educore_attendance_section_select');
            var studentSelect = document.getElementById('ifs_educore_attendance_student_select');

            function populateSections(selectedClass, selectedSecName) {
                selectedSecName = selectedSecName || '';
                if (!sectionSelect) return;
                sectionSelect.innerHTML = '<option value=""><?php echo esc_js( __( '-- All Sections --', 'ifsedu-school-management' ) ); ?></option>';
                if (!selectedClass) return;

                var filtered = unitsMap.filter(function(item) { return item.class_name == selectedClass; });
                var uniqueSections = [];
                filtered.forEach(function(item) {
                    if (item.section_name && uniqueSections.indexOf(item.section_name) === -1) {
                        uniqueSections.push(item.section_name);
                    }
                });

                uniqueSections.sort(function(a, b) {
                    return a.localeCompare(b, undefined, { numeric: true, sensitivity: 'base' });
                });

                uniqueSections.forEach(function(secName) {
                    var opt = document.createElement('option');
                    opt.value = secName;
                    opt.textContent = secName;
                    if (secName == selectedSecName) {
                        opt.selected = true;
                    }
                    sectionSelect.appendChild(opt);
                });
            }

            function populateStudents(selectedClass, selectedSecName, selectedStudentId) {
                selectedStudentId = selectedStudentId || '';
                if (!studentSelect) return;
                studentSelect.innerHTML = '<option value=""><?php echo esc_js( __( '-- All Students --', 'ifsedu-school-management' ) ); ?></option>';
                if (!selectedClass) return;

                var filteredStudents = studentsMap.filter(function(item) { return item.class_name == selectedClass; });
                
                if (selectedSecName) {
                    filteredStudents = filteredStudents.filter(function(item) { return item.section_name == selectedSecName; });
                }

                filteredStudents.forEach(function(stu) {
                    var opt = document.createElement('option');
                    opt.value = stu.id;
                    opt.textContent = stu.roll_no ? '[Roll: ' + stu.roll_no + '] ' + stu.full_name : stu.full_name;
                    
                    if (String(stu.id) === String(selectedStudentId)) {
                        opt.selected = true;
                    }
                    studentSelect.appendChild(opt);
                });
            }

            if (classSelect && sectionSelect && studentSelect) {
                populateSections(classSelect.value, currentFilterSection);
                populateStudents(classSelect.value, currentFilterSection, currentFilterStudent);

                classSelect.addEventListener('change', function() {
                    populateSections(classSelect.value);
                    populateStudents(classSelect.value, sectionSelect.value);
                });

                sectionSelect.addEventListener('change', function() {
                    populateStudents(classSelect.value, sectionSelect.value);
                });
            }
        });
        </script>
    </div>
    <?php
}
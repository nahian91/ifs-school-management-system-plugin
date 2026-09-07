<?php
/**
 * Teacher-Subject Allocation & Matrix Directory Engine
 * File: inc/academics/teacher-subjects.php
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function educore_teacher_subjects_view() {
    global $wpdb;

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient administrative permissions to access this page.', 'ifsedu-school-management' ) );
    }

    $table_staff            = $wpdb->prefix . 'sms_staff';
    $table_subjects         = $wpdb->prefix . 'sms_subjects';
    $table_units            = $wpdb->prefix . 'sms_academic_units';
    $table_teacher_subjects = $wpdb->prefix . 'sms_teacher_subjects';

    // Auto-migrate column `role_type` if missing
    $col_check = $wpdb->get_results( "SHOW COLUMNS FROM `{$table_teacher_subjects}` LIKE 'role_type'" );
    if ( empty( $col_check ) ) {
        $wpdb->query( "ALTER TABLE `{$table_teacher_subjects}` ADD COLUMN `role_type` varchar(50) DEFAULT 'Subject Teacher' NOT NULL AFTER `subject_id`" );
    }

    $notice_msg = '';

    // Handle Form Submission
    $req_method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
    if ( 'POST' === $req_method && isset( $_POST['assign_teacher_subject'] ) && check_admin_referer( 'assign_teacher_subject_action', 'educore_ts_nonce' ) ) {
        $teacher_id  = isset( $_POST['teacher_id'] ) ? absint( wp_unslash( $_POST['teacher_id'] ) ) : 0;
        $unit_id     = isset( $_POST['unit_id'] ) ? absint( wp_unslash( $_POST['unit_id'] ) ) : 0;
        $role_type   = isset( $_POST['role_type'] ) && 'Class Teacher' === $_POST['role_type'] ? 'Class Teacher' : 'Subject Teacher';
        $subject_ids = ( isset( $_POST['subject_ids'] ) && is_array( $_POST['subject_ids'] ) ) ? array_map( 'absint', wp_unslash( $_POST['subject_ids'] ) ) : array();

        if ( $teacher_id > 0 && $unit_id > 0 ) {
            $assigned_count = 0;

            // If designated as Class Teacher without specific subjects, assign as primary class in-charge (subject_id = 0)
            if ( 'Class Teacher' === $role_type && empty( $subject_ids ) ) {
                $exists = (int) $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT id FROM `{$table_teacher_subjects}` WHERE teacher_id = %d AND class_id = %d AND role_type = 'Class Teacher' LIMIT 1",
                        $teacher_id,
                        $unit_id
                    )
                );

                if ( $exists <= 0 ) {
                    $wpdb->insert(
                        $table_teacher_subjects,
                        array(
                            'teacher_id' => $teacher_id,
                            'class_id'   => $unit_id,
                            'subject_id' => 0,
                            'role_type'  => 'Class Teacher',
                        ),
                        array( '%d', '%d', '%d', '%s' )
                    );
                    $assigned_count++;
                }
            } else {
                foreach ( $subject_ids as $sub_id ) {
                    $sub_id_int = absint( $sub_id );
                    if ( $sub_id_int <= 0 ) {
                        continue;
                    }

                    $exists = (int) $wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT id FROM `{$table_teacher_subjects}` WHERE teacher_id = %d AND class_id = %d AND subject_id = %d LIMIT 1",
                            $teacher_id,
                            $unit_id,
                            $sub_id_int
                        )
                    );

                    if ( $exists <= 0 ) {
                        $wpdb->insert(
                            $table_teacher_subjects,
                            array(
                                'teacher_id' => $teacher_id,
                                'class_id'   => $unit_id,
                                'subject_id' => $sub_id_int,
                                'role_type'  => $role_type,
                            ),
                            array( '%d', '%d', '%d', '%s' )
                        );
                        $assigned_count++;
                    } else {
                        // Update existing role type if changed
                        $wpdb->update(
                            $table_teacher_subjects,
                            array( 'role_type' => $role_type ),
                            array( 'id' => $exists ),
                            array( '%s' ),
                            array( '%d' )
                        );
                    }
                }
            }

            if ( $assigned_count > 0 ) {
                if ( function_exists( 'educore_log_activity' ) ) {
                    educore_log_activity( sprintf( __( 'Assigned %1$s to teacher ID #%2$d', 'ifsedu-school-management' ), $role_type, $teacher_id ) );
                }
                $notice_msg = sprintf( esc_html__( 'Successfully allocated assignment(s) as %s.', 'ifsedu-school-management' ), esc_html( $role_type ) );
            } else {
                $notice_msg = esc_html__( 'Teacher assignment already active for this class & section.', 'ifsedu-school-management' );
            }
        }
    }

    // Handle Delete Assignment
    if ( isset( $_GET['action'] ) && 'delete_assignment' === $_GET['action'] && isset( $_GET['assign_id'] ) ) {
        $assign_id = absint( wp_unslash( $_GET['assign_id'] ) );
        $del_nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

        if ( $assign_id > 0 && wp_verify_nonce( $del_nonce, 'delete_ts_' . $assign_id ) ) {
            $wpdb->delete( $table_teacher_subjects, array( 'id' => $assign_id ), array( '%d' ) );

            if ( function_exists( 'educore_log_activity' ) ) {
                educore_log_activity( sprintf( __( 'Removed teacher assignment ID #%d', 'ifsedu-school-management' ), $assign_id ) );
            }
            $notice_msg = esc_html__( 'Teacher allocation removed successfully.', 'ifsedu-school-management' );
        }
    }

    // Data Queries
    $teachers = $wpdb->get_results( "SELECT id, full_name, designation, phone FROM `{$table_staff}` WHERE status = 'Active' ORDER BY full_name ASC" );

    $units_raw = $wpdb->get_results( "
        SELECT id, class_name, section_name, sort_order 
        FROM `{$table_units}` 
        WHERE class_name != '' 
        ORDER BY sort_order ASC, CAST(class_name AS UNSIGNED) ASC, class_name ASC, section_name ASC
    " );

    $subjects = $wpdb->get_results( "
        SELECT s.id, s.subject_name, s.subject_code, s.class_id, u.class_name, u.section_name, u.sort_order as class_sort_order 
        FROM `{$table_subjects}` s 
        LEFT JOIN `{$table_units}` u ON s.class_id = u.id 
        ORDER BY u.sort_order ASC, CAST(u.class_name AS UNSIGNED) ASC, u.class_name ASC, u.section_name ASC, s.subject_order ASC, s.subject_name ASC
    " );

    // Ordered by Class first (sort_order -> numeric class -> class name -> section), then subject order and teacher name
    $assignments = $wpdb->get_results( "
        SELECT ts.id, ts.teacher_id, ts.class_id, ts.subject_id, ts.role_type, t.full_name as teacher_name, t.designation, 
               s.subject_name, s.subject_code, s.subject_order, u.class_name, u.section_name, u.sort_order as class_sort_order 
        FROM `{$table_teacher_subjects}` ts
        INNER JOIN `{$table_staff}` t ON ts.teacher_id = t.id
        LEFT JOIN `{$table_subjects}` s ON ts.subject_id = s.id
        INNER JOIN `{$table_units}` u ON ts.class_id = u.id
        ORDER BY u.sort_order ASC, CAST(u.class_name AS UNSIGNED) ASC, u.class_name ASC, u.section_name ASC, ts.role_type DESC, s.subject_order ASC, s.subject_name ASC, t.full_name ASC
    " );

    $unique_classes     = array();
    $class_sections_map = array();

    if ( ! empty( $units_raw ) && is_array( $units_raw ) ) {
        foreach ( $units_raw as $u_item ) {
            $c_name = trim( (string) $u_item->class_name );
            $s_name = trim( (string) $u_item->section_name );

            if ( ! in_array( $c_name, $unique_classes, true ) ) {
                $unique_classes[] = $c_name;
            }

            if ( ! isset( $class_sections_map[ $c_name ] ) ) {
                $class_sections_map[ $c_name ] = array();
            }

            $class_sections_map[ $c_name ][] = array(
                'id'           => absint( $u_item->id ),
                'section_name' => ! empty( $s_name ) ? $s_name : __( 'General / All', 'ifsedu-school-management' ),
            );
        }
    }

    $raw_subtab = isset( $_GET['subtab'] ) ? sanitize_key( wp_unslash( $_GET['subtab'] ) ) : 'teacher_subjects';
    $base_url   = add_query_arg(
        array(
            'page'   => 'school_management_system',
            'tab'    => 'academics',
            'subtab' => $raw_subtab,
        ),
        admin_url( 'admin.php' )
    );
    ?>

    <style>
        .ifs-educore-ts-layout {
            display: grid;
            grid-template-columns: 380px 1fr;
            gap: 20px;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: inherit;
        }
        @media (max-width: 1080px) {
            .ifs-educore-ts-layout {
                grid-template-columns: 1fr;
            }
        }
        .ifs-educore-ts-card {
            background: #ffffff !important;
            border: 1px solid #e2e8f0 !important;
            border-radius: 14px !important;
            padding: 22px !important;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.02) !important;
            box-sizing: border-box !important;
        }
        .ifs-educore-field-label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin-bottom: 6px;
        }
        .ifs-educore-field-input,
        .ifs-educore-field-select {
            width: 100%;
            height: 40px;
            padding: 0 12px;
            border: 1.5px solid #cbd5e1;
            border-radius: 8px;
            font-size: 13.5px;
            color: #0f172a;
            background: #ffffff;
            box-sizing: border-box;
            outline: none;
            transition: border-color 0.2s;
        }
        .ifs-educore-field-input:focus,
        .ifs-educore-field-select:focus {
            border-color: #00523c;
            box-shadow: 0 0 0 3px rgba(0, 82, 60, 0.12);
        }
        .ifs-educore-sub-chip-container {
            background: #f8fafc;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            padding: 12px;
            min-height: 80px;
            max-height: 200px;
            overflow-y: auto;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .ifs-educore-sub-chip {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            background: #ffffff;
            border: 1.5px solid #e2e8f0;
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            color: #1e293b;
            cursor: pointer;
            user-select: none;
            transition: all 0.15s ease;
        }
        .ifs-educore-sub-chip:hover {
            border-color: #94a3b8;
            background: #f8fafc;
        }
        .ifs-educore-sub-chip.is-selected {
            border-color: #00523c;
            background: #f0fdf4;
            color: #00523c;
        }
        .ifs-educore-sub-chip input[type="checkbox"] {
            margin: 0;
            width: 16px;
            height: 16px;
            accent-color: #00523c;
            cursor: pointer;
            flex-shrink: 0;
        }
        .ifs-teacher-avatar-tag {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #e2e8f0;
            color: #334155;
            font-size: 12px;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .ifs-role-badge {
            font-size: 11px;
            font-weight: 800;
            padding: 3px 8px;
            border-radius: 5px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .ifs-role-badge.badge-class-teacher {
            background: #fef3c7;
            color: #b45309;
            border: 1px solid #fde68a;
        }
        .ifs-role-badge.badge-subject-teacher {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
        }
        .ifs-educore-matrix-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 13px;
        }
        .ifs-educore-matrix-table th {
            padding: 10px 12px;
            background: #f8fafc;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            color: #475569;
            border-bottom: 2px solid #e2e8f0;
            text-align: left;
        }
        .ifs-educore-matrix-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }
        .ifs-educore-btn-del {
            color: #dc2626;
            text-decoration: none;
            padding: 5px 8px;
            border: 1px solid #fecaca;
            border-radius: 6px;
            background: #fef2f2;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.15s ease;
        }
        .ifs-educore-btn-del:hover {
            background: #dc2626;
            color: #ffffff;
            border-color: #dc2626;
        }
    </style>

    <?php if ( ! empty( $notice_msg ) ) : ?>
        <div style="background:#ecfdf5; border-left:4px solid #00523c; color:#065f46; padding:10px 14px; border-radius:6px; font-weight:700; margin-bottom:16px;">
            <span class="dashicons dashicons-yes-alt" style="vertical-align:middle;"></span>
            <?php echo esc_html( $notice_msg ); ?>
        </div>
    <?php endif; ?>

    <div class="ifs-educore-ts-layout">

        <!-- Allocation Form -->
        <div class="ifs-educore-ts-card" style="height: fit-content;">
            <h3 style="margin:0 0 16px 0; font-size:15px; font-weight:800; color:#0f172a; border-bottom:1px solid #f1f5f9; padding-bottom:12px; display:flex; align-items:center; gap:8px;">
                <span class="dashicons dashicons-id-alt" style="color:#00523c;"></span>
                <?php esc_html_e( 'Assign Teacher & Role', 'ifsedu-school-management' ); ?>
            </h3>

            <form method="POST" action="<?php echo esc_url( $base_url ); ?>" id="ifs_ts_quick_form">
                <?php wp_nonce_field( 'assign_teacher_subject_action', 'educore_ts_nonce' ); ?>

                <!-- Role Selection Dropdown -->
                <div style="margin-bottom: 14px;">
                    <label class="ifs-educore-field-label"><?php esc_html_e( 'Allocation Role', 'ifsedu-school-management' ); ?> <span style="color:#ef4444;">*</span></label>
                    <select name="role_type" id="ts_role_select" class="ifs-educore-field-select" required>
                        <option value="Subject Teacher"><?php esc_html_e( 'Subject Teacher', 'ifsedu-school-management' ); ?></option>
                        <option value="Class Teacher"><?php esc_html_e( 'Class Teacher (In-Charge)', 'ifsedu-school-management' ); ?></option>
                    </select>
                </div>

                <!-- Step 1: Select Instructor -->
                <div style="margin-bottom: 14px;">
                    <label class="ifs-educore-field-label"><?php esc_html_e( '1. Select Instructor', 'ifsedu-school-management' ); ?> <span style="color:#ef4444;">*</span></label>
                    <input type="text" id="ts_teacher_search" class="ifs-educore-field-input" placeholder="<?php esc_attr_e( 'Search teacher name...', 'ifsedu-school-management' ); ?>" autocomplete="off" style="margin-bottom:6px; height:34px;">
                    <select name="teacher_id" id="ts_teacher_select" class="ifs-educore-field-select" required>
                        <option value=""><?php esc_html_e( '-- Choose Instructor --', 'ifsedu-school-management' ); ?></option>
                        <?php if ( ! empty( $teachers ) ) : foreach ( $teachers as $t ) : 
                            $t_id = absint( $t->id );
                        ?>
                            <option value="<?php echo absint( $t_id ); ?>" data-name="<?php echo esc_attr( strtolower( (string) ( $t->full_name . ' ' . $t->designation . ' ' . $t->phone ) ) ); ?>">
                                <?php echo esc_html( $t->full_name . ' (' . $t->designation . ')' ); ?>
                            </option>
                        <?php endforeach; endif; ?>
                    </select>
                </div>

                <!-- Step 2: Target Class & Target Section Side by Side -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 14px;">
                    <div>
                        <label class="ifs-educore-field-label"><?php esc_html_e( 'Target Class', 'ifsedu-school-management' ); ?> <span style="color:#ef4444;">*</span></label>
                        <select id="ts_class_select" class="ifs-educore-field-select" required>
                            <option value=""><?php esc_html_e( '-- Class --', 'ifsedu-school-management' ); ?></option>
                            <?php foreach ( $unique_classes as $c_name ) : ?>
                                <option value="<?php echo esc_attr( $c_name ); ?>"><?php echo esc_html( $c_name ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="ifs-educore-field-label"><?php esc_html_e( 'Target Section', 'ifsedu-school-management' ); ?> <span style="color:#ef4444;">*</span></label>
                        <select name="unit_id" id="ts_section_select" class="ifs-educore-field-select" required disabled>
                            <option value=""><?php esc_html_e( '-- Section --', 'ifsedu-school-management' ); ?></option>
                        </select>
                    </div>
                </div>

                <!-- Step 3: Subject Selection -->
                <div style="margin-bottom: 18px;" id="ts_subject_step_wrap">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                        <label class="ifs-educore-field-label" style="margin-bottom:0;" id="ts_sub_label">
                            <?php esc_html_e( 'Assigned Subjects', 'ifsedu-school-management' ); ?> <span style="color:#ef4444;" id="ts_sub_required_star">*</span>
                        </label>
                        <button type="button" id="btn_toggle_subjects" style="background:none; border:none; color:#00523c; font-size:11px; font-weight:700; cursor:pointer; display:none;">
                            <?php esc_html_e( 'Select All', 'ifsedu-school-management' ); ?>
                        </button>
                    </div>

                    <div id="ts_subjects_checkbox_box" class="ifs-educore-sub-chip-container">
                        <div style="text-align:center; padding:20px 10px; color:#94a3b8; font-size:12px;" id="ts_no_sub_hint">
                            <?php esc_html_e( 'Select class & section to view subjects.', 'ifsedu-school-management' ); ?>
                        </div>
                    </div>
                </div>

                <button type="submit" name="assign_teacher_subject" style="width:100%; height:42px; background:#00523c; color:#ffffff; font-weight:700; font-size:13.5px; border:none; border-radius:8px; cursor:pointer; box-shadow:0 4px 12px rgba(0,82,60,0.15); transition:background 0.2s;">
                    <span class="dashicons dashicons-saved" style="vertical-align:middle; font-size:16px;"></span>
                    <?php esc_html_e( 'Confirm Allocation', 'ifsedu-school-management' ); ?>
                </button>
            </form>
        </div>

        <!-- Allocation Matrix Directory (Ordered by Class) -->
        <div class="ifs-educore-ts-card">
            <div class="ifs-educore-card-header" style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #f1f5f9; padding-bottom:12px; margin-bottom:16px; flex-wrap:wrap; gap:10px;">
                <h3 class="ifs-educore-card-title" style="margin:0; font-size:15px; font-weight:800; color:#0f172a; display:flex; align-items:center; gap:8px;">
                    <span class="dashicons dashicons-networking" style="color:#00523c;"></span>
                    <?php esc_html_e( 'Instructor Allocation Matrix', 'ifsedu-school-management' ); ?>
                </h3>
                <div style="display:flex; align-items:center; gap:10px;">
                    <input type="text" id="ts_matrix_search" class="ifs-educore-field-input" placeholder="<?php esc_attr_e( 'Search allocations...', 'ifsedu-school-management' ); ?>" style="max-width:200px; height:34px;">
                    <span style="background:#f1f5f9; padding:5px 10px; border-radius:6px; font-size:12px; font-weight:700; color:#475569;" id="ts_matrix_count_pill">
                        <?php echo count( $assignments ); ?> <?php esc_html_e( 'Allocated', 'ifsedu-school-management' ); ?>
                    </span>
                </div>
            </div>

            <div style="overflow-x:auto;">
                <table class="ifs-educore-matrix-table" id="ts_matrix_table">
                    <thead>
                        <tr>
                            <th style="width: 18%;"><?php esc_html_e( 'Class', 'ifsedu-school-management' ); ?></th>
                            <th style="width: 16%;"><?php esc_html_e( 'Section', 'ifsedu-school-management' ); ?></th>
                            <th style="width: 18%;"><?php esc_html_e( 'Role', 'ifsedu-school-management' ); ?></th>
                            <th style="width: 22%;"><?php esc_html_e( 'Subject', 'ifsedu-school-management' ); ?></th>
                            <th style="width: 20%;"><?php esc_html_e( 'Teacher', 'ifsedu-school-management' ); ?></th>
                            <th style="width: 6%; text-align:right;"><?php esc_html_e( 'Action', 'ifsedu-school-management' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( ! empty( $assignments ) ) : foreach ( $assignments as $row ) : 
                            $assign_id = absint( $row->id );
                            $del_url = wp_nonce_url( add_query_arg( array( 'action' => 'delete_assignment', 'assign_id' => $assign_id ), $base_url ), 'delete_ts_' . $assign_id );
                            $section_display = ! empty( $row->section_name ) ? $row->section_name : '—';
                            $initial = ! empty( $row->teacher_name ) ? strtoupper( mb_substr( trim( $row->teacher_name ), 0, 1 ) ) : 'T';
                            $is_class_teacher = ( 'Class Teacher' === ( $row->role_type ?? 'Subject Teacher' ) );
                        ?>
                            <tr class="ts-matrix-row" data-searchable="<?php echo esc_attr( strtolower( (string) ( $row->class_name . ' ' . $row->section_name . ' ' . ( $row->subject_name ?: 'Class Teacher In-Charge' ) . ' ' . $row->teacher_name . ' ' . ( $row->role_type ?? '' ) ) ) ); ?>">
                                <td>
                                    <span style="background:#eff6ff; color:#2563eb; padding:2px 8px; border-radius:5px; font-weight:700; font-size:12px;">
                                        <?php echo esc_html( $row->class_name ); ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="background:#f8fafc; border:1px solid #e2e8f0; color:#334155; padding:2px 8px; border-radius:5px; font-weight:600; font-size:12px;">
                                        <?php echo esc_html( $section_display ); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ( $is_class_teacher ) : ?>
                                        <span class="ifs-role-badge badge-class-teacher">
                                            <span class="dashicons dashicons-awards" style="font-size:13px; width:13px; height:13px;"></span>
                                            <?php esc_html_e( 'Class Teacher', 'ifsedu-school-management' ); ?>
                                        </span>
                                    <?php else : ?>
                                        <span class="ifs-role-badge badge-subject-teacher">
                                            <span class="dashicons dashicons-book" style="font-size:13px; width:13px; height:13px;"></span>
                                            <?php esc_html_e( 'Subject Teacher', 'ifsedu-school-management' ); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ( ! empty( $row->subject_name ) ) : ?>
                                        <strong style="color:#00523c;"><?php echo esc_html( $row->subject_name ); ?></strong>
                                        <?php if ( $row->subject_code ) : ?>
                                            <code style="font-size:11px; background:#f1f5f9; padding:2px 4px; border-radius:4px; margin-left:4px;"><?php echo esc_html( $row->subject_code ); ?></code>
                                        <?php endif; ?>
                                    <?php else : ?>
                                        <span style="color:#b45309; font-weight:700; font-size:12px; font-style:italic;">
                                            <?php esc_html_e( 'Class In-Charge (All Subjects)', 'ifsedu-school-management' ); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display:flex; align-items:center; gap:8px;">
                                        <div class="ifs-teacher-avatar-tag"><?php echo esc_html( $initial ); ?></div>
                                        <div>
                                            <strong style="color:#0f172a; display:block;"><?php echo esc_html( $row->teacher_name ); ?></strong>
                                            <small style="color:#64748b; font-size:11px;"><?php echo esc_html( $row->designation ); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td style="text-align:right;">
                                    <a href="<?php echo esc_url( $del_url ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Remove this allocation?', 'ifsedu-school-management' ) ); ?>');" class="ifs-educore-btn-del" title="<?php esc_attr_e( 'Delete Assignment', 'ifsedu-school-management' ); ?>">
                                        <span class="dashicons dashicons-trash" style="font-size:14px; width:14px; height:14px;"></span>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; else : ?>
                            <tr>
                                <td colspan="6" style="padding:28px; text-align:center; color:#94a3b8;">
                                    <?php esc_html_e( 'No instructors assigned to any class or subject yet.', 'ifsedu-school-management' ); ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- Client Script -->
    <script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function() {
        var classSectionsMap = <?php echo wp_json_encode( ! empty( $class_sections_map ) ? $class_sections_map : array() ); ?>;
        var allSubjects = <?php echo wp_json_encode( ! empty( $subjects ) ? $subjects : array() ); ?>;

        var teacherSearch = document.getElementById('ts_teacher_search');
        var teacherSelect = document.getElementById('ts_teacher_select');
        var teacherOptions = teacherSelect ? Array.from(teacherSelect.options).slice(1) : [];

        var classSelect = document.getElementById('ts_class_select');
        var sectionSelect = document.getElementById('ts_section_select');
        var subContainer = document.getElementById('ts_subjects_checkbox_box');
        var toggleSubBtn = document.getElementById('btn_toggle_subjects');
        var form = document.getElementById('ifs_ts_quick_form');

        var roleSelect = document.getElementById('ts_role_select');
        var subStar = document.getElementById('ts_sub_required_star');

        // Role Dropdown Change Event
        if (roleSelect) {
            roleSelect.addEventListener('change', function() {
                if (this.value === 'Class Teacher') {
                    if (subStar) subStar.style.display = 'none';
                } else {
                    if (subStar) subStar.style.display = 'inline';
                }
            });
        }

        // 1. Teacher Search Filter
        if (teacherSearch && teacherSelect) {
            teacherSearch.addEventListener('input', function() {
                var query = this.value.toLowerCase().trim();
                var currentVal = teacherSelect.value;
                
                teacherSelect.innerHTML = '<option value=""><?php echo esc_js( __( '-- Choose Instructor --', 'ifsedu-school-management' ) ); ?></option>';

                teacherOptions.forEach(function(opt) {
                    var searchStr = opt.getAttribute('data-name') || '';
                    if (!query || searchStr.indexOf(query) !== -1) {
                        var cloned = opt.cloneNode(true);
                        if (cloned.value === currentVal) cloned.selected = true;
                        teacherSelect.appendChild(cloned);
                    }
                });
            });
        }

        // 2. Class Selection & Populating Target Section
        if (classSelect && sectionSelect) {
            classSelect.addEventListener('change', function() {
                var selectedClass = this.value;
                sectionSelect.innerHTML = '<option value=""><?php echo esc_js( __( '-- Section --', 'ifsedu-school-management' ) ); ?></option>';

                if (selectedClass && classSectionsMap[selectedClass]) {
                    classSectionsMap[selectedClass].forEach(function(sec) {
                        var opt = document.createElement('option');
                        opt.value = sec.id;
                        opt.textContent = sec.section_name;
                        sectionSelect.appendChild(opt);
                    });

                    sectionSelect.disabled = false;

                    // If only 1 section exists, auto-select it
                    if (classSectionsMap[selectedClass].length === 1) {
                        sectionSelect.selectedIndex = 1;
                        renderSubjectChips(sectionSelect.value);
                    } else {
                        renderSubjectChips('');
                    }
                } else {
                    sectionSelect.disabled = true;
                    renderSubjectChips('');
                }
            });

            sectionSelect.addEventListener('change', function() {
                renderSubjectChips(this.value);
            });
        }

        // 3. Render Subject Chips
        function renderSubjectChips(unitId) {
            if (!unitId) {
                subContainer.innerHTML = '<div style="text-align:center; padding:20px 10px; color:#94a3b8; font-size:12px;"><?php echo esc_js( __( 'Select class & section to view subjects.', 'ifsedu-school-management' ) ); ?></div>';
                toggleSubBtn.style.display = 'none';
                return;
            }

            var filtered = allSubjects.filter(function(s) {
                return String(s.class_id) === String(unitId);
            });

            if (filtered.length === 0) {
                subContainer.innerHTML = '<div style="text-align:center; padding:20px 10px; color:#dc2626; font-size:12px; font-weight:700;"><?php echo esc_js( __( 'No subjects found for this class & section. (Optional for Class Teacher)', 'ifsedu-school-management' ) ); ?></div>';
                toggleSubBtn.style.display = 'none';
                return;
            }

            var html = '';
            filtered.forEach(function(s) {
                var codeTag = s.subject_code ? ' <code style="font-size:10px; background:#f1f5f9; padding:1px 4px; border-radius:4px;">' + s.subject_code + '</code>' : '';
                html += `
                    <label class="ifs-educore-sub-chip is-selected">
                        <span>${s.subject_name}${codeTag}</span>
                        <input type="checkbox" name="subject_ids[]" value="${s.id}" checked class="cb-sub-item">
                    </label>
                `;
            });

            subContainer.innerHTML = html;
            toggleSubBtn.style.display = 'inline-block';
            toggleSubBtn.textContent = '<?php echo esc_js( __( 'Deselect All', 'ifsedu-school-management' ) ); ?>';

            subContainer.querySelectorAll('.cb-sub-item').forEach(function(cb) {
                cb.addEventListener('change', function() {
                    var card = this.closest('.ifs-educore-sub-chip');
                    if (this.checked) {
                        card.classList.add('is-selected');
                    } else {
                        card.classList.remove('is-selected');
                    }
                });
            });
        }

        // 4. Toggle All Button
        if (toggleSubBtn) {
            toggleSubBtn.addEventListener('click', function() {
                var cbs = subContainer.querySelectorAll('.cb-sub-item');
                var allChecked = true;
                cbs.forEach(function(cb) { if (!cb.checked) allChecked = false; });

                cbs.forEach(function(cb) {
                    cb.checked = !allChecked;
                    var card = cb.closest('.ifs-educore-sub-chip');
                    if (cb.checked) {
                        card.classList.add('is-selected');
                    } else {
                        card.classList.remove('is-selected');
                    }
                });
                toggleSubBtn.textContent = allChecked ? '<?php echo esc_js( __( 'Select All', 'ifsedu-school-management' ) ); ?>' : '<?php echo esc_js( __( 'Deselect All', 'ifsedu-school-management' ) ); ?>';
            });
        }

        // 5. Form Validation
        if (form) {
            form.addEventListener('submit', function(e) {
                var selectedRole = roleSelect ? roleSelect.value : 'Subject Teacher';
                var checkedCount = subContainer.querySelectorAll('.cb-sub-item:checked').length;
                if (selectedRole === 'Subject Teacher' && checkedCount === 0) {
                    alert('<?php echo esc_js( __( 'Please select at least one subject to allocate for a Subject Teacher.', 'ifsedu-school-management' ) ); ?>');
                    e.preventDefault();
                }
            });
        }

        // 6. Matrix Search Filter
        var searchInp = document.getElementById('ts_matrix_search');
        var countPill = document.getElementById('ts_matrix_count_pill');
        if (searchInp) {
            searchInp.addEventListener('input', function() {
                var query = this.value.toLowerCase().trim();
                var count = 0;
                document.querySelectorAll('.ts-matrix-row').forEach(function(row) {
                    var text = row.getAttribute('data-searchable') || '';
                    if (!query || text.indexOf(query) !== -1) {
                        row.style.display = '';
                        count++;
                    } else {
                        row.style.display = 'none';
                    }
                });
                if (countPill) countPill.textContent = count + ' <?php echo esc_js( __( 'Allocated', 'ifsedu-school-management' ) ); ?>';
            });
        }
    });
    </script>
    <?php
}

educore_teacher_subjects_view();
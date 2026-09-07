<?php
/**
 * Available Teacher Finder & Proxy/Substitution Assignment Workspace
 * File: inc/academics/academic-available-teachers.php
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'ifsedu-school-management' ) );
}

global $wpdb;

$table_staff    = $wpdb->prefix . 'sms_staff';
$table_routine  = $wpdb->prefix . 'sms_routine';
$table_units    = $wpdb->prefix . 'sms_academic_units';
$table_subjects = $wpdb->prefix . 'sms_subjects';
$table_proxy    = $wpdb->prefix . 'sms_teacher_proxy_logs';

// --------------------------------------------------------------------------
// 1. Auto-Create Proxy Log Table for Payroll & Audit
// --------------------------------------------------------------------------
$proxy_table_exists = $wpdb->get_var( "SHOW TABLES LIKE '{$table_proxy}'" );
if ( empty( $proxy_table_exists ) ) {
    $charset_collate = $wpdb->get_charset_collate();
    $sql_proxy = "CREATE TABLE {$table_proxy} (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        proxy_date date NOT NULL,
        day_name varchar(20) NOT NULL,
        period_name varchar(100) DEFAULT '' NOT NULL,
        start_time time NOT NULL,
        end_time time NOT NULL,
        proxy_teacher_id bigint(20) NOT NULL,
        original_teacher_id bigint(20) DEFAULT 0 NOT NULL,
        class_id bigint(20) NOT NULL,
        subject_id bigint(20) DEFAULT 0 NOT NULL,
        remuneration_amount decimal(10,2) DEFAULT 0.00 NOT NULL,
        payment_status varchar(30) DEFAULT 'Pending' NOT NULL,
        remarks text DEFAULT '' NOT NULL,
        created_by bigint(20) NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id),
        KEY proxy_date_idx (proxy_date),
        KEY proxy_teacher_idx (proxy_teacher_id),
        KEY payment_status_idx (payment_status)
    ) {$charset_collate};";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql_proxy );
}

$notice_msg = '';

// --------------------------------------------------------------------------
// 2. Handle Proxy Class Assignment Submission
// --------------------------------------------------------------------------
$req_method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
if ( 'POST' === $req_method && isset( $_POST['educore_assign_proxy'] ) && check_admin_referer( 'assign_proxy_action', 'educore_proxy_nonce' ) ) {
    $proxy_date          = isset( $_POST['proxy_date'] ) ? sanitize_text_field( wp_unslash( $_POST['proxy_date'] ) ) : current_time( 'Y-m-d' );
    $day_name            = isset( $_POST['day_name'] ) ? sanitize_text_field( wp_unslash( $_POST['day_name'] ) ) : current_time( 'l' );
    $time_slot           = isset( $_POST['time_slot'] ) ? sanitize_text_field( wp_unslash( $_POST['time_slot'] ) ) : '';
    $proxy_teacher_id    = isset( $_POST['proxy_teacher_id'] ) ? absint( wp_unslash( $_POST['proxy_teacher_id'] ) ) : 0;
    $original_teacher_id = isset( $_POST['original_teacher_id'] ) ? absint( wp_unslash( $_POST['original_teacher_id'] ) ) : 0;
    $unit_id             = isset( $_POST['unit_id'] ) ? absint( wp_unslash( $_POST['unit_id'] ) ) : 0;
    $subject_id          = isset( $_POST['subject_id'] ) ? absint( wp_unslash( $_POST['subject_id'] ) ) : 0;
    $remuneration_amount = isset( $_POST['remuneration_amount'] ) ? floatval( wp_unslash( $_POST['remuneration_amount'] ) ) : 0.00;
    $remarks             = isset( $_POST['remarks'] ) ? sanitize_textarea_field( wp_unslash( $_POST['remarks'] ) ) : '';

    $start_time = '';
    $end_time   = '';
    if ( ! empty( $time_slot ) && strpos( $time_slot, '|' ) !== false ) {
        list( $start_time, $end_time ) = explode( '|', $time_slot );
    } else {
        $start_time = isset( $_POST['start_time'] ) ? sanitize_text_field( wp_unslash( $_POST['start_time'] ) ) : current_time( 'H:i:00' );
        $end_time   = isset( $_POST['end_time'] ) ? sanitize_text_field( wp_unslash( $_POST['end_time'] ) ) : date( 'H:i:00', strtotime( '+45 minutes', strtotime( $start_time ) ) );
    }

    if ( $proxy_teacher_id > 0 && $unit_id > 0 ) {
        $wpdb->insert(
            $table_proxy,
            array(
                'proxy_date'          => $proxy_date,
                'day_name'            => $day_name,
                'period_name'         => date( 'h:i A', strtotime( $start_time ) ) . ' - ' . date( 'h:i A', strtotime( $end_time ) ),
                'start_time'          => $start_time,
                'end_time'            => $end_time,
                'proxy_teacher_id'    => $proxy_teacher_id,
                'original_teacher_id' => $original_teacher_id,
                'class_id'            => $unit_id,
                'subject_id'          => $subject_id,
                'remuneration_amount' => $remuneration_amount,
                'payment_status'      => 'Pending',
                'remarks'             => $remarks,
                'created_by'          => get_current_user_id(),
            ),
            array( '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%f', '%s', '%s', '%d' )
        );

        if ( function_exists( 'educore_log_activity' ) ) {
            educore_log_activity( sprintf( __( 'Assigned proxy class to teacher ID #%d on %s', 'ifsedu-school-management' ), $proxy_teacher_id, $proxy_date ) );
        }

        $notice_msg = esc_html__( 'Proxy class assigned successfully and logged for payroll.', 'ifsedu-school-management' );
    }
}

// Handle Delete Proxy Log
if ( isset( $_GET['action'] ) && 'delete_proxy' === $_GET['action'] && isset( $_GET['proxy_id'] ) ) {
    $proxy_id  = absint( wp_unslash( $_GET['proxy_id'] ) );
    $del_nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

    if ( $proxy_id > 0 && wp_verify_nonce( $del_nonce, 'delete_proxy_' . $proxy_id ) ) {
        $wpdb->delete( $table_proxy, array( 'id' => $proxy_id ), array( '%d' ) );
        $notice_msg = esc_html__( 'Proxy record deleted successfully.', 'ifsedu-school-management' );
    }
}

// --------------------------------------------------------------------------
// 3. Filter Parameters & Schedule Collisions
// --------------------------------------------------------------------------
// phpcs:disable WordPress.Security.NonceVerification.Recommended
$filter_date = isset( $_GET['filter_date'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_date'] ) ) : current_time( 'Y-m-d' );
$filter_day  = isset( $_GET['filter_day'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_day'] ) ) : date( 'l', strtotime( $filter_date ) );
$filter_slot = isset( $_GET['filter_slot'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_slot'] ) ) : '';
$filter_time = isset( $_GET['filter_time'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_time'] ) ) : current_time( 'H:i' );
// phpcs:enable WordPress.Security.NonceVerification.Recommended

$days_list = array( 'Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday' );

// Fetch distinct routine time slots from wp_sms_routine
$slots = $wpdb->get_results( "SELECT DISTINCT start_time, end_time FROM `{$table_routine}` WHERE start_time IS NOT NULL AND end_time IS NOT NULL ORDER BY start_time ASC" );

// All Active Teachers
$teachers = $wpdb->get_results( "SELECT id, full_name, designation, phone FROM `{$table_staff}` WHERE status = 'Active' ORDER BY full_name ASC" );

// Units and Subjects for Proxy modal assignment
$units    = $wpdb->get_results( "SELECT id, class_name, section_name FROM `{$table_units}` WHERE class_name != '' ORDER BY sort_order ASC, class_name ASC, section_name ASC" );
$subjects = $wpdb->get_results( "SELECT id, subject_name, class_id FROM `{$table_subjects}` ORDER BY subject_name ASC" );

// Detect Busy Teachers
$busy_teacher_data = array();
if ( ! empty( $filter_slot ) && strpos( $filter_slot, '|' ) !== false ) {
    list( $s_time, $e_time ) = explode( '|', $filter_slot );
    $busy_rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT r.teacher_id, r.start_time, r.end_time, r.room_no, u.class_name, u.section_name, s.subject_name 
             FROM `{$table_routine}` r 
             LEFT JOIN `{$table_units}` u ON r.class_id = u.id 
             LEFT JOIN `{$table_subjects}` s ON r.subject_id = s.id 
             WHERE r.day_name = %s AND r.start_time = %s AND r.end_time = %s AND r.teacher_id > 0",
            $filter_day,
            $s_time,
            $e_time
        )
    );
} else {
    $time_formatted = ( strlen( $filter_time ) === 5 ) ? $filter_time . ':00' : $filter_time;
    $busy_rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT r.teacher_id, r.start_time, r.end_time, r.room_no, u.class_name, u.section_name, s.subject_name 
             FROM `{$table_routine}` r 
             LEFT JOIN `{$table_units}` u ON r.class_id = u.id 
             LEFT JOIN `{$table_subjects}` s ON r.subject_id = s.id 
             WHERE r.day_name = %s AND %s >= r.start_time AND %s < r.end_time AND r.teacher_id > 0",
            $filter_day,
            $time_formatted,
            $time_formatted
        )
    );
}

if ( ! empty( $busy_rows ) ) {
    foreach ( $busy_rows as $br ) {
        $busy_teacher_data[ $br->teacher_id ] = $br;
    }
}

// Fetch Logged Proxy Records
$proxy_logs = $wpdb->get_results(
    "SELECT p.*, pt.full_name as proxy_teacher_name, ot.full_name as original_teacher_name, 
            u.class_name, u.section_name, s.subject_name 
     FROM `{$table_proxy}` p 
     LEFT JOIN `{$table_staff}` pt ON p.proxy_teacher_id = pt.id 
     LEFT JOIN `{$table_staff}` ot ON p.original_teacher_id = ot.id 
     LEFT JOIN `{$table_units}` u ON p.class_id = u.id 
     LEFT JOIN `{$table_subjects}` s ON p.subject_id = s.id 
     ORDER BY p.proxy_date DESC, p.id DESC LIMIT 30"
);

$base_url = admin_url( 'admin.php?page=school_management_system&tab=academics&subtab=available_teachers' );
?>

<style>
    .ifs-proxy-grid {
        display: grid;
        grid-template-columns: 1fr 340px;
        gap: 20px;
    }
    @media (max-width: 1080px) {
        .ifs-proxy-grid {
            grid-template-columns: 1fr;
        }
    }
    .ifs-proxy-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.02);
    }
    .ifs-status-pill-free {
        background: #ecfdf5;
        color: #047857;
        padding: 4px 10px;
        border-radius: 6px;
        font-weight: 700;
        font-size: 12px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        border: 1px solid #a7f3d0;
    }
    .ifs-status-pill-busy {
        background: #fef2f2;
        color: #dc2626;
        padding: 4px 10px;
        border-radius: 6px;
        font-weight: 700;
        font-size: 12px;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        border: 1px solid #fca5a5;
    }
    .ifs-btn-proxy-action {
        background: #00523c;
        color: #ffffff;
        border: none;
        padding: 6px 12px;
        border-radius: 6px;
        font-weight: 700;
        font-size: 12px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        transition: background 0.2s;
    }
    .ifs-btn-proxy-action:hover {
        background: #065f46;
    }
    .ifs-modal-backdrop {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(15, 23, 42, 0.6);
        z-index: 999999;
        align-items: center;
        justify-content: center;
    }
    .ifs-modal-backdrop.is-visible {
        display: flex;
    }
    .ifs-modal-box {
        background: #ffffff;
        border-radius: 12px;
        padding: 24px;
        width: 100%;
        max-width: 520px;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.15);
    }
</style>

<?php if ( ! empty( $notice_msg ) ) : ?>
    <div style="background:#ecfdf5; border-left:4px solid #00523c; color:#065f46; padding:12px 16px; border-radius:6px; font-weight:700; margin-bottom:20px;">
        <span class="dashicons dashicons-yes-alt" style="vertical-align:middle;"></span> <?php echo esc_html( $notice_msg ); ?>
    </div>
<?php endif; ?>

<!-- Filter Toolbar -->
<div class="ifs-proxy-card" style="margin-bottom: 22px;">
    <form method="GET" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="display:flex; gap:14px; align-items:flex-end; flex-wrap:wrap;">
        <input type="hidden" name="page" value="school_management_system">
        <input type="hidden" name="tab" value="academics">
        <input type="hidden" name="subtab" value="available_teachers">

        <div style="flex:1; min-width:160px;">
            <label style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:6px;"><?php esc_html_e( 'Target Date', 'ifsedu-school-management' ); ?></label>
            <input type="date" name="filter_date" id="ifs_filter_date" value="<?php echo esc_attr( $filter_date ); ?>" style="width:100%; height:40px; border:1px solid #cbd5e1; border-radius:8px; padding:0 12px; font-size:13px;" required>
        </div>

        <div style="flex:1; min-width:140px;">
            <label style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:6px;"><?php esc_html_e( 'Day of Week', 'ifsedu-school-management' ); ?></label>
            <select name="filter_day" id="ifs_filter_day" style="width:100%; height:40px; border:1px solid #cbd5e1; border-radius:8px; padding:0 12px; font-size:13px;">
                <?php foreach ( $days_list as $day ) : ?>
                    <option value="<?php echo esc_attr( $day ); ?>" <?php selected( $filter_day, $day ); ?>><?php echo esc_html( $day ); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="flex:1; min-width:180px;">
            <label style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:6px;"><?php esc_html_e( 'Routine Slot', 'ifsedu-school-management' ); ?></label>
            <select name="filter_slot" style="width:100%; height:40px; border:1px solid #cbd5e1; border-radius:8px; padding:0 12px; font-size:13px;">
                <option value=""><?php esc_html_e( '-- Exact Time Input --', 'ifsedu-school-management' ); ?></option>
                <?php if ( ! empty( $slots ) ) : foreach ( $slots as $s ) : 
                    $slot_val = $s->start_time . '|' . $s->end_time;
                ?>
                    <option value="<?php echo esc_attr( $slot_val ); ?>" <?php selected( $filter_slot, $slot_val ); ?>>
                        <?php echo esc_html( date( 'h:i A', strtotime( $s->start_time ) ) . ' - ' . date( 'h:i A', strtotime( $s->end_time ) ) ); ?>
                    </option>
                <?php endforeach; endif; ?>
            </select>
        </div>

        <div style="flex:1; min-width:130px;">
            <label style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:6px;"><?php esc_html_e( 'Or Specific Time', 'ifsedu-school-management' ); ?></label>
            <input type="time" name="filter_time" value="<?php echo esc_attr( $filter_time ); ?>" style="width:100%; height:40px; border:1px solid #cbd5e1; border-radius:8px; padding:0 12px; font-size:13px;">
        </div>

        <div>
            <button type="submit" style="height:40px; padding:0 20px; background:#00523c; color:#fff; font-weight:700; border:none; border-radius:8px; cursor:pointer;">
                <span class="dashicons dashicons-search" style="vertical-align:middle;"></span> <?php esc_html_e( 'Check Routine', 'ifsedu-school-management' ); ?>
            </button>
        </div>
    </form>
</div>

<div class="ifs-proxy-grid">

    <!-- Available & Busy Teachers Matrix -->
    <div class="ifs-proxy-card">
        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #f1f5f9; padding-bottom:12px; margin-bottom:16px;">
            <h3 style="margin:0; font-size:15px; font-weight:800; color:#0f172a; display:flex; align-items:center; gap:8px;">
                <span class="dashicons dashicons-groups" style="color:#00523c;"></span>
                <?php printf( esc_html__( 'Teacher Status for %1$s at %2$s', 'ifsedu-school-management' ), esc_html( $filter_day ), esc_html( ! empty( $filter_slot ) ? str_replace( '|', ' to ', $filter_slot ) : date( 'h:i A', strtotime( $filter_time ) ) ) ); ?>
            </h3>
        </div>

        <div style="overflow-x:auto;">
            <table style="width:100%; border-collapse:collapse; font-size:13px; text-align:left;">
                <thead>
                    <tr style="background:#f8fafc; border-bottom:2px solid #e2e8f0;">
                        <th style="padding:10px 12px; width:30%;"><?php esc_html_e( 'Teacher Name', 'ifsedu-school-management' ); ?></th>
                        <th style="padding:10px 12px; width:20%;"><?php esc_html_e( 'Designation', 'ifsedu-school-management' ); ?></th>
                        <th style="padding:10px 12px; width:30%;"><?php esc_html_e( 'Schedule Status', 'ifsedu-school-management' ); ?></th>
                        <th style="padding:10px 12px; width:20%; text-align:right;"><?php esc_html_e( 'Proxy Action', 'ifsedu-school-management' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( ! empty( $teachers ) ) : foreach ( $teachers as $t ) : 
                        $is_busy = isset( $busy_teacher_data[ $t->id ] );
                        $busy_info = $is_busy ? $busy_teacher_data[ $t->id ] : null;
                    ?>
                        <tr style="border-bottom:1px solid #f1f5f9;">
                            <td style="padding:10px 12px; font-weight:700; color:#0f172a;">
                                <?php echo esc_html( $t->full_name ); ?>
                                <small style="display:block; color:#64748b; font-weight:500; font-size:11px;"><?php echo esc_html( $t->phone ? $t->phone : '—' ); ?></small>
                            </td>
                            <td style="padding:10px 12px; color:#475569; font-weight:600;"><?php echo esc_html( $t->designation ? $t->designation : 'Faculty' ); ?></td>
                            <td style="padding:10px 12px;">
                                <?php if ( ! $is_busy ) : ?>
                                    <span class="ifs-status-pill-free">
                                        <span class="dashicons dashicons-yes-alt" style="font-size:13px; width:13px; height:13px;"></span>
                                        <?php esc_html_e( 'Free (Available)', 'ifsedu-school-management' ); ?>
                                    </span>
                                <?php else : ?>
                                    <span class="ifs-status-pill-busy">
                                        <span class="dashicons dashicons-lock" style="font-size:13px; width:13px; height:13px;"></span>
                                        <?php printf( esc_html__( 'Busy: %1$s (%2$s)', 'ifsedu-school-management' ), esc_html( $busy_info->class_name ), esc_html( $busy_info->subject_name ? $busy_info->subject_name : 'Class' ) ); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td style="padding:10px 12px; text-align:right;">
                                <?php if ( ! $is_busy ) : ?>
                                    <button type="button" class="ifs-btn-proxy-action btn-open-proxy-modal" 
                                            data-teacher-id="<?php echo esc_attr( $t->id ); ?>" 
                                            data-teacher-name="<?php echo esc_attr( $t->full_name ); ?>">
                                        <span class="dashicons dashicons-plus-alt"></span>
                                        <?php esc_html_e( 'Assign Proxy', 'ifsedu-school-management' ); ?>
                                    </button>
                                <?php else : ?>
                                    <span style="color:#94a3b8; font-size:12px;">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; else : ?>
                        <tr><td colspan="4" style="text-align:center; padding:30px; color:#94a3b8;"><?php esc_html_e( 'No active faculty found.', 'ifsedu-school-management' ); ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Proxy Logs Ledger -->
    <div class="ifs-proxy-card">
        <h3 style="margin:0 0 14px 0; font-size:14.5px; font-weight:800; color:#0f172a; border-bottom:1px solid #f1f5f9; padding-bottom:10px; display:flex; align-items:center; gap:6px;">
            <span class="dashicons dashicons-money-alt" style="color:#00523c;"></span>
            <?php esc_html_e( 'Proxy Log & Payroll Ledger', 'ifsedu-school-management' ); ?>
        </h3>

        <div style="display:flex; flex-direction:column; gap:10px; max-height:550px; overflow-y:auto;">
            <?php if ( ! empty( $proxy_logs ) ) : foreach ( $proxy_logs as $pl ) : 
                $del_proxy_url = wp_nonce_url( add_query_arg( array( 'action' => 'delete_proxy', 'proxy_id' => $pl->id ), $base_url ), 'delete_proxy_' . $pl->id );
            ?>
                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px; font-size:12px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                        <strong style="color:#00523c; font-size:12.5px;"><?php echo esc_html( $pl->proxy_teacher_name ); ?></strong>
                        <span style="background:<?php echo 'Paid' === $pl->payment_status ? '#ecfdf5' : '#fffbeb'; ?>; color:<?php echo 'Paid' === $pl->payment_status ? '#047857' : '#b45309'; ?>; padding:2px 6px; border-radius:4px; font-weight:700; font-size:11px;">
                            <?php echo esc_html( $pl->payment_status ); ?>
                        </span>
                    </div>
                    <div style="color:#475569;">
                        <?php printf( esc_html__( 'Class: %1$s (%2$s)', 'ifsedu-school-management' ), esc_html( $pl->class_name ), esc_html( $pl->section_name ? $pl->section_name : 'All' ) ); ?>
                        <?php if ( $pl->subject_name ) : ?> | <strong><?php echo esc_html( $pl->subject_name ); ?></strong><?php endif; ?>
                    </div>
                    <div style="color:#64748b; font-size:11px; margin-top:2px;">
                        <?php echo esc_html( date( 'd M, Y', strtotime( $pl->proxy_date ) ) ); ?> &bull; <?php echo esc_html( $pl->start_time . ' - ' . $pl->end_time ); ?>
                    </div>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-top:6px; padding-top:6px; border-top:1px dashed #cbd5e1;">
                        <span style="font-weight:800; color:#0f172a;"><?php esc_html_e( 'Rate:', 'ifsedu-school-management' ); ?> <?php echo number_format( $pl->remuneration_amount, 2 ); ?></span>
                        <a href="<?php echo esc_url( $del_proxy_url ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Remove this proxy record?', 'ifsedu-school-management' ) ); ?>');" style="color:#dc2626; text-decoration:none;">
                            <span class="dashicons dashicons-trash" style="font-size:14px; width:14px; height:14px;"></span>
                        </a>
                    </div>
                </div>
            <?php endforeach; else : ?>
                <div style="text-align:center; padding:20px; color:#94a3b8;"><?php esc_html_e( 'No proxy records recorded yet.', 'ifsedu-school-management' ); ?></div>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- Assign Proxy Class Modal -->
<div class="ifs-modal-backdrop" id="ifs_proxy_modal">
    <div class="ifs-modal-box">
        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #f1f5f9; padding-bottom:12px; margin-bottom:16px;">
            <h4 style="margin:0; font-size:16px; font-weight:800; color:#0f172a;">
                <span class="dashicons dashicons-calendar-alt" style="color:#00523c; vertical-align:middle;"></span>
                <?php esc_html_e( 'Assign Proxy / Substitution Class', 'ifsedu-school-management' ); ?>
            </h4>
            <button type="button" id="ifs_close_proxy_modal" style="background:none; border:none; font-size:22px; cursor:pointer; color:#64748b;">&times;</button>
        </div>

        <form method="POST" action="<?php echo esc_url( $base_url ); ?>">
            <?php wp_nonce_field( 'assign_proxy_action', 'educore_proxy_nonce' ); ?>
            <input type="hidden" name="educore_assign_proxy" value="1">
            <input type="hidden" name="proxy_teacher_id" id="modal_proxy_teacher_id" value="">
            <input type="hidden" name="proxy_date" value="<?php echo esc_attr( $filter_date ); ?>">
            <input type="hidden" name="day_name" value="<?php echo esc_attr( $filter_day ); ?>">
            <input type="hidden" name="time_slot" value="<?php echo esc_attr( $filter_slot ); ?>">
            <input type="hidden" name="start_time" value="<?php echo esc_attr( $filter_time ); ?>">
            <input type="hidden" name="end_time" value="<?php echo esc_attr( date( 'H:i', strtotime( '+45 minutes', strtotime( $filter_time ) ) ) ); ?>">

            <!-- Teacher Info Badge -->
            <div style="background:#f0fdf4; border:1px solid #bbf7d0; padding:10px 14px; border-radius:8px; margin-bottom:14px;">
                <div style="font-size:12px; color:#166534; font-weight:700;"><?php esc_html_e( 'Assigned Proxy Teacher:', 'ifsedu-school-management' ); ?></div>
                <div style="font-size:14px; font-weight:800; color:#0f172a;" id="modal_display_teacher_name"></div>
                <small style="color:#64748b;"><?php echo esc_html( $filter_date . ' (' . $filter_day . ') - ' . ( $filter_slot ? str_replace( '|', ' - ', $filter_slot ) : $filter_time ) ); ?></small>
            </div>

            <!-- Target Class & Section -->
            <div style="margin-bottom:12px;">
                <label style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:4px;"><?php esc_html_e( 'Target Class & Section', 'ifsedu-school-management' ); ?> *</label>
                <select name="unit_id" id="modal_proxy_unit_id" style="width:100%; height:38px; border:1px solid #cbd5e1; border-radius:6px; padding:0 10px;" required>
                    <option value=""><?php esc_html_e( '-- Choose Class & Section --', 'ifsedu-school-management' ); ?></option>
                    <?php foreach ( $units as $u ) : ?>
                        <option value="<?php echo esc_attr( $u->id ); ?>">
                            <?php echo esc_html( $u->class_name . ( $u->section_name ? ' (' . $u->section_name . ')' : '' ) ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Target Subject -->
            <div style="margin-bottom:12px;">
                <label style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:4px;"><?php esc_html_e( 'Subject Taught (Optional)', 'ifsedu-school-management' ); ?></label>
                <select name="subject_id" id="modal_proxy_subject_id" style="width:100%; height:38px; border:1px solid #cbd5e1; border-radius:6px; padding:0 10px;">
                    <option value="0"><?php esc_html_e( '-- Select Subject --', 'ifsedu-school-management' ); ?></option>
                    <?php foreach ( $subjects as $sub ) : ?>
                        <option value="<?php echo esc_attr( $sub->id ); ?>" data-class-id="<?php echo esc_attr( $sub->class_id ); ?>">
                            <?php echo esc_html( $sub->subject_name ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Absent / Original Teacher -->
            <div style="margin-bottom:12px;">
                <label style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:4px;"><?php esc_html_e( 'Original Teacher (Absent)', 'ifsedu-school-management' ); ?></label>
                <select name="original_teacher_id" style="width:100%; height:38px; border:1px solid #cbd5e1; border-radius:6px; padding:0 10px;">
                    <option value="0"><?php esc_html_e( '-- None / General Replacement --', 'ifsedu-school-management' ); ?></option>
                    <?php foreach ( $teachers as $t ) : ?>
                        <option value="<?php echo esc_attr( $t->id ); ?>"><?php echo esc_html( $t->full_name ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Remuneration for Later Payment -->
            <div style="margin-bottom:12px;">
                <label style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:4px;"><?php esc_html_e( 'Proxy Remuneration / Allowance Amount', 'ifsedu-school-management' ); ?></label>
                <input type="number" step="0.01" name="remuneration_amount" placeholder="0.00" value="0.00" style="width:100%; height:38px; border:1px solid #cbd5e1; border-radius:6px; padding:0 10px;">
                <span style="font-size:11px; color:#64748b;"><?php esc_html_e( 'Calculated in monthly payroll or disbursed on approval.', 'ifsedu-school-management' ); ?></span>
            </div>

            <!-- Remarks -->
            <div style="margin-bottom:16px;">
                <label style="display:block; font-size:12px; font-weight:700; color:#475569; margin-bottom:4px;"><?php esc_html_e( 'Remarks / Class Notes', 'ifsedu-school-management' ); ?></label>
                <textarea name="remarks" rows="2" style="width:100%; border:1px solid #cbd5e1; border-radius:6px; padding:8px;" placeholder="<?php esc_attr_e( 'e.g. Chapter 4 revision completed.', 'ifsedu-school-management' ); ?>"></textarea>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:8px;">
                <button type="button" id="ifs_cancel_proxy_modal" style="background:#f1f5f9; border:none; padding:8px 14px; border-radius:6px; font-weight:700; cursor:pointer; color:#475569;">
                    <?php esc_html_e( 'Cancel', 'ifsedu-school-management' ); ?>
                </button>
                <button type="submit" style="background:#00523c; color:#fff; border:none; padding:8px 18px; border-radius:6px; font-weight:700; cursor:pointer;">
                    <?php esc_html_e( 'Confirm & Log Proxy', 'ifsedu-school-management' ); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function() {
    var filterDateInput = document.getElementById('ifs_filter_date');
    var filterDaySelect = document.getElementById('ifs_filter_day');

    if (filterDateInput && filterDaySelect) {
        filterDateInput.addEventListener('change', function() {
            if (this.value) {
                var d = new Date(this.value);
                var weekdays = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                var dayName = weekdays[d.getDay()];
                if (dayName) {
                    filterDaySelect.value = dayName;
                }
            }
        });
    }

    var modal = document.getElementById('ifs_proxy_modal');
    var closeModalBtn = document.getElementById('ifs_close_proxy_modal');
    var cancelModalBtn = document.getElementById('ifs_cancel_proxy_modal');
    var modalTeacherId = document.getElementById('modal_proxy_teacher_id');
    var modalTeacherName = document.getElementById('modal_display_teacher_name');

    document.querySelectorAll('.btn-open-proxy-modal').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var teacherId = this.getAttribute('data-teacher-id');
            var teacherName = this.getAttribute('data-teacher-name');

            if (modalTeacherId) modalTeacherId.value = teacherId;
            if (modalTeacherName) modalTeacherName.textContent = teacherName;
            if (modal) modal.classList.add('is-visible');
        });
    });

    function closeModal() {
        if (modal) modal.classList.remove('is-visible');
    }

    if (closeModalBtn) closeModalBtn.addEventListener('click', closeModal);
    if (cancelModalBtn) cancelModalBtn.addEventListener('click', closeModal);

    var modalUnitSelect = document.getElementById('modal_proxy_unit_id');
    var modalSubjectSelect = document.getElementById('modal_proxy_subject_id');

    if (modalUnitSelect && modalSubjectSelect) {
        modalUnitSelect.addEventListener('change', function() {
            var selectedUnit = this.value;
            var options = modalSubjectSelect.querySelectorAll('option');

            options.forEach(function(opt) {
                if (opt.value === '0' || !selectedUnit) {
                    opt.style.display = '';
                } else {
                    var classId = opt.getAttribute('data-class-id');
                    opt.style.display = (classId === selectedUnit) ? '' : 'none';
                }
            });
            modalSubjectSelect.value = '0';
        });
    }
});
</script>
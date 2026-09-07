<?php
/**
 * Plugin Name: IFSEdu - School Management System
 * Description: Standalone, high-performance management system for Schools featuring student admissions, attendance, fees, exams, results, and HR.
 * Version:     1.2.2
 * Author:      DevNahian
 * License:     GPL-2.0-or-later
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * 1. Constants & Path Definitions
 */
define( 'EDUCORE_VERSION', '1.2.2' );
define( 'EDUCORE_PATH', plugin_dir_path( __FILE__ ) );
define( 'EDUCORE_URL', plugin_dir_url( __FILE__ ) );

/**
 * 2. Include Modular Sub-Files Securely
 */
function educore_load_modular_dependencies() {
    $files = array(
        'dashboard', 'students', 'attendance', 'fees', 
        'exams', 'results', 'staff', 'academics', 'communication', 
        'reports', 'frontend-bridge', 'users', 'settings', 'notices', 'accounting'
    );

    foreach ( $files as $file ) {
        $path = EDUCORE_PATH . 'inc/' . $file . '.php';
        if ( file_exists( $path ) ) {
            require_once $path;
        }
    }
}
educore_load_modular_dependencies();

/**
 * Helper: Check if current user is authorized to manage settings
 * Fixed: Removed hardcoded email vulnerability; purely capability-driven.
 */
function educore_is_settings_manager() {
    $current_user = wp_get_current_user();
    if ( ! $current_user || ! $current_user->exists() ) {
        return false;
    }

    /**
     * Filter the capability required to manage Educore settings.
     * Default: 'manage_options'
     */
    $required_cap = apply_filters( 'educore_settings_manager_capability', 'manage_options' );

    return current_user_can( $required_cap );
}

/**
 * 3. Role-Based Access Control Core (RBAC Engine)
 */
function educore_has_access( $allowed_roles = array() ) {
    $current_user = wp_get_current_user();
    if ( ! $current_user || ! $current_user->exists() ) {
        return false;
    }

    if ( empty( $allowed_roles ) ) {
        return true;
    }

    if ( in_array( 'administrator', (array) $current_user->roles, true ) || current_user_can( 'manage_options' ) ) {
        return true;
    }

    foreach ( $allowed_roles as $role ) {
        if ( in_array( $role, (array) $current_user->roles, true ) ) {
            return true;
        }
    }
    
    return false;
}

/**
 * Helper: Allowed HTML tags for SVG rendering
 */
function educore_get_allowed_svg_html() {
    return array(
        'svg' => array(
            'xmlns'       => true,
            'viewbox'     => true,
            'viewBox'     => true,
            'width'       => true,
            'height'      => true,
            'fill'        => true,
            'class'       => true,
            'aria-hidden' => true,
        ),
        'path' => array(
            'd'    => true,
            'fill' => true,
        ),
    );
}

/**
 * 4. Styles & Dynamic Assets Loading Processor
 */
function educore_enqueue_admin_assets( $hook ) {
    if ( strpos( $hook, 'school_management_system' ) === false ) {
        return;
    }

    wp_enqueue_style( 'bootstrap', EDUCORE_URL . 'assets/css/bootstrap.min.css', array(), EDUCORE_VERSION );
    wp_enqueue_style( 'datatables', EDUCORE_URL . 'assets/css/jquery.dataTables.min.css', array(), EDUCORE_VERSION );
    wp_enqueue_style( 'main-style', EDUCORE_URL . 'assets/css/style.css', array(), EDUCORE_VERSION );
    wp_enqueue_style( 'educore-admin-style', EDUCORE_URL . 'assets/css/admin-style.css', array(), EDUCORE_VERSION );

    wp_enqueue_script( 'jquery' );
    wp_enqueue_script( 'bootstrap', EDUCORE_URL . 'assets/js/bootstrap.bundle.min.js', array( 'jquery' ), EDUCORE_VERSION, true );
    wp_enqueue_script( 'datatables', EDUCORE_URL . 'assets/js/jquery.dataTables.min.js', array( 'jquery' ), EDUCORE_VERSION, true );
    wp_enqueue_script( 'datepicker', EDUCORE_URL . 'assets/js/bootstrap-datepicker.js', array( 'jquery' ), EDUCORE_VERSION, true );
    wp_enqueue_script( 'educore-main', EDUCORE_URL . 'assets/js/admin-script.js', array( 'jquery' ), EDUCORE_VERSION, true );
}
add_action( 'admin_enqueue_scripts', 'educore_enqueue_admin_assets' );

/**
 * 5. Global Database Migration & Auto-Update Engine (Strict dbDelta Compliant)
 */
function educore_execute_database_migration() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    // 1. Students Table
    $table_students = $wpdb->prefix . 'sms_students';
    $sql_students = "CREATE TABLE {$table_students} (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        student_id varchar(50) NOT NULL,
        full_name varchar(255) NOT NULL,
        class_name varchar(50) NOT NULL,
        section_name varchar(50) DEFAULT '' NOT NULL,
        shift varchar(50) DEFAULT 'No Shift' NOT NULL,
        roll_no int(11) NOT NULL,
        admission_date date DEFAULT '1970-01-01' NOT NULL,
        fee_start_date date NULL DEFAULT NULL,
        birth_reg_no varchar(50) DEFAULT '' NOT NULL,
        dob date DEFAULT '1970-01-01' NOT NULL,
        birth_place varchar(100) DEFAULT '' NOT NULL,
        gender varchar(20) DEFAULT 'Male' NOT NULL,
        blood_group varchar(10) DEFAULT '' NOT NULL,
        religion varchar(50) DEFAULT 'Islam' NOT NULL,
        nationality varchar(50) DEFAULT 'Bangladeshi' NOT NULL,
        student_email varchar(100) DEFAULT '' NOT NULL,
        student_phone varchar(50) DEFAULT '' NOT NULL,
        quota varchar(50) DEFAULT 'General' NOT NULL,
        waiver_staff_id bigint(20) DEFAULT 0 NOT NULL,
        waiver_percentage decimal(5,2) DEFAULT 0.00 NOT NULL,
        father_name varchar(255) DEFAULT '' NOT NULL,
        father_nid varchar(50) DEFAULT '' NOT NULL,
        father_phone varchar(50) DEFAULT '' NOT NULL,
        father_profession varchar(100) DEFAULT '' NOT NULL,
        mother_name varchar(255) DEFAULT '' NOT NULL,
        mother_nid varchar(50) DEFAULT '' NOT NULL,
        mother_phone varchar(50) DEFAULT '' NOT NULL,
        mother_profession varchar(100) DEFAULT '' NOT NULL,
        guardian_name varchar(255) NOT NULL,
        guardian_phone varchar(50) NOT NULL,
        guardian_relation varchar(50) DEFAULT '' NOT NULL,
        guardian_nid varchar(50) DEFAULT '' NOT NULL,
        guardian_income varchar(50) DEFAULT '' NOT NULL,
        prev_school_name varchar(255) DEFAULT '' NOT NULL,
        prev_eiin varchar(50) DEFAULT '' NOT NULL,
        prev_class varchar(50) DEFAULT '' NOT NULL,
        prev_gpa varchar(20) DEFAULT '' NOT NULL,
        address text NOT NULL,
        permanent_address text NOT NULL,
        residential_status varchar(50) DEFAULT 'Non-Residential' NOT NULL,
        co_curricular text NOT NULL,
        photo_url text NULL DEFAULT NULL,
        status varchar(30) DEFAULT 'Active' NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY student_id (student_id),
        KEY class_section_roll (class_name, section_name, roll_no),
        KEY shift_idx (shift),
        KEY waiver_staff_idx (waiver_staff_id),
        KEY status_idx (status)
    ) {$charset_collate};";
    dbDelta( $sql_students );

    // 2. Staff Table
    $table_staff = $wpdb->prefix . 'sms_staff';
    $sql_staff = "CREATE TABLE {$table_staff} (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        wp_user_id bigint(20) NULL DEFAULT NULL,
        staff_id varchar(50) NOT NULL,
        full_name varchar(255) NOT NULL,
        name_bn varchar(255) DEFAULT '' NOT NULL,
        father_name varchar(255) DEFAULT '' NOT NULL,
        mother_name varchar(255) DEFAULT '' NOT NULL,
        designation varchar(100) NOT NULL,
        staff_type varchar(50) DEFAULT '' NOT NULL,
        pay_grade varchar(50) DEFAULT '' NOT NULL,
        index_no varchar(50) DEFAULT '' NOT NULL,
        nid_no varchar(50) DEFAULT '' NOT NULL,
        dob date DEFAULT '1970-01-01' NOT NULL,
        gender varchar(20) DEFAULT 'Male' NOT NULL,
        phone varchar(50) NOT NULL,
        whatsapp_no varchar(50) DEFAULT '' NOT NULL,
        email varchar(100) NOT NULL,
        blood_group varchar(10) DEFAULT '' NOT NULL,
        quota_type varchar(50) DEFAULT 'General' NOT NULL,
        joining_date date DEFAULT '1970-01-01' NOT NULL,
        salary decimal(10,2) DEFAULT '0.00' NOT NULL,
        subject_expert varchar(255) DEFAULT '' NOT NULL,
        highest_degree varchar(255) DEFAULT '' NOT NULL,
        emergency_name varchar(255) DEFAULT '' NOT NULL,
        emergency_phone varchar(50) DEFAULT '' NOT NULL,
        emergency_relation varchar(50) DEFAULT '' NOT NULL,
        bank_name varchar(255) DEFAULT '' NOT NULL,
        bank_acc_no varchar(100) DEFAULT '' NOT NULL,
        bank_routing varchar(50) DEFAULT '' NOT NULL,
        address text NOT NULL,
        permanent_address text NOT NULL,
        linkedin_url varchar(255) DEFAULT '' NOT NULL,
        facebook_url varchar(255) DEFAULT '' NOT NULL,
        website_url varchar(255) DEFAULT '' NOT NULL,
        profile_image varchar(255) DEFAULT '' NOT NULL,
        order_number int(11) DEFAULT 0 NOT NULL,
        status varchar(30) DEFAULT 'Active' NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY staff_id (staff_id),
        KEY status_idx (status),
        KEY wp_user_idx (wp_user_id)
    ) {$charset_collate};";
    dbDelta( $sql_staff );

    $table_staff_att = $wpdb->prefix . 'sms_staff_attendance';
    $sql = "CREATE TABLE IF NOT EXISTS `$table_staff_att` (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        staff_id bigint(20) NOT NULL,
        attendance_date date NOT NULL,
        status varchar(20) NOT NULL DEFAULT 'Present',
        remarks text DEFAULT NULL,
        recorded_by bigint(20) NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY staff_id (staff_id),
        KEY attendance_date (attendance_date)
    ) $charset_collate;";
    dbDelta( $sql );

    // 3. Attendance Table
    $table_attendance = $wpdb->prefix . 'sms_attendance';
    $sql_attendance = "CREATE TABLE {$table_attendance} (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        student_id bigint(20) NOT NULL,
        attendance_date date NOT NULL,
        status varchar(20) DEFAULT 'Present' NOT NULL,
        remarks text NOT NULL,
        recorded_by bigint(20) NOT NULL,
        PRIMARY KEY  (id),
        KEY student_date_idx (student_id, attendance_date),
        KEY date_status_idx (attendance_date, status)
    ) {$charset_collate};";
    dbDelta( $sql_attendance );

    // 4. Fees Table
    $table_fees = $wpdb->prefix . 'sms_fees';
    $sql_fees = "CREATE TABLE {$table_fees} (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        invoice_id varchar(50) NOT NULL,
        student_id bigint(20) NOT NULL,
        fee_month varchar(20) NOT NULL,
        fee_year varchar(10) NOT NULL,
        fee_type varchar(50) DEFAULT 'Tuition Fee' NOT NULL,
        amount decimal(10,2) DEFAULT '0.00' NOT NULL,
        late_fine decimal(10,2) DEFAULT '0.00' NOT NULL,
        discount decimal(10,2) DEFAULT '0.00' NOT NULL,
        net_payable decimal(10,2) DEFAULT '0.00' NOT NULL,
        paid_amount decimal(10,2) DEFAULT '0.00' NOT NULL,
        due_amount decimal(10,2) DEFAULT '0.00' NOT NULL,
        payment_status varchar(20) DEFAULT 'Unpaid' NOT NULL,
        payment_method varchar(30) DEFAULT 'Cash' NOT NULL,
        transaction_id varchar(100) DEFAULT '' NOT NULL,
        remarks text,
        payment_date datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        collected_by bigint(20) NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY invoice_id (invoice_id),
        KEY student_id (student_id),
        KEY payment_status (payment_status),
        KEY payment_date (payment_date)
    ) {$charset_collate};";
    dbDelta( $sql_fees );

    // 5. Exams Table (Updated with Attendance Calculation Range)
    $table_exams = $wpdb->prefix . 'sms_exams';
    $sql_exams = "CREATE TABLE {$table_exams} (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        exam_name varchar(255) NOT NULL,
        class_name varchar(255) NOT NULL,
        start_date date NOT NULL,
        end_date date NOT NULL,
        att_start_date date DEFAULT NULL,
        att_end_date date DEFAULT NULL,
        status varchar(30) DEFAULT 'Upcoming' NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id),
        KEY status_idx (status),
        KEY date_range_idx (start_date, end_date),
        KEY att_date_range_idx (att_start_date, att_end_date)
    ) {$charset_collate};";
    dbDelta( $sql_exams );

    // 6. Results Table
    $table_results = $wpdb->prefix . 'sms_results';
    $sql_results = "CREATE TABLE {$table_results} (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        exam_id bigint(20) NOT NULL,
        student_id bigint(20) NOT NULL,
        class_name varchar(50) DEFAULT '' NOT NULL,
        subject_name varchar(100) NOT NULL,
        total_marks decimal(5,2) DEFAULT '100.00' NOT NULL,
        obtained_marks decimal(5,2) DEFAULT '0.00' NOT NULL,
        mcq_marks decimal(5,2) DEFAULT '0.00' NOT NULL,
        cq_marks decimal(5,2) DEFAULT '0.00' NOT NULL,
        practical_marks decimal(5,2) DEFAULT '0.00' NOT NULL,
        grade varchar(10) DEFAULT '' NOT NULL,
        gpa decimal(4,2) DEFAULT '0.00' NOT NULL,
        evaluated_by bigint(20) NOT NULL,
        PRIMARY KEY  (id),
        KEY exam_student_idx (exam_id, student_id)
    ) {$charset_collate};";
    dbDelta( $sql_results );

    // 7. Audit Logs Table
    $table_audit = $wpdb->prefix . 'sms_audit_logs';
    $sql_audit = "CREATE TABLE {$table_audit} (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL,
        user_role varchar(50) NOT NULL,
        action_performed text NOT NULL,
        ip_address varchar(45) NOT NULL,
        timestamp datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id),
        KEY user_id_idx (user_id)
    ) {$charset_collate};";
    dbDelta( $sql_audit );

    // 8. Academic Units Table
    $table_academic_units = $wpdb->prefix . 'sms_academic_units';
    $sql_academic_units = "CREATE TABLE {$table_academic_units} (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        unit_type varchar(50) NOT NULL,
        class_name varchar(100) NOT NULL,
        section_name varchar(100) DEFAULT '' NOT NULL,
        dept_name varchar(100) DEFAULT '' NOT NULL,
        sort_order int(11) DEFAULT 0 NOT NULL,
        PRIMARY KEY  (id)
    ) {$charset_collate};";
    dbDelta( $sql_academic_units );

   // 9. Subjects Table (Enhanced with Dynamic Breakdown Data)
    $table_subjects = $wpdb->prefix . 'sms_subjects';
    $sql_subjects = "CREATE TABLE {$table_subjects} (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        class_id bigint(20) NOT NULL,
        subject_name varchar(150) NOT NULL,
        subject_code varchar(50) DEFAULT '' NOT NULL,
        subject_order int(11) DEFAULT 0 NOT NULL,
        subject_type varchar(20) DEFAULT 'Mandatory' NOT NULL,
        total_marks decimal(5,2) DEFAULT '100.00' NOT NULL,
        pass_marks decimal(5,2) DEFAULT '33.00' NOT NULL,
        cq_marks decimal(5,2) DEFAULT '70.00' NOT NULL,
        cq_pass decimal(5,2) DEFAULT '23.00' NOT NULL,
        mcq_marks decimal(5,2) DEFAULT '30.00' NOT NULL,
        mcq_pass decimal(5,2) DEFAULT '10.00' NOT NULL,
        practical_marks decimal(5,2) DEFAULT '0.00' NOT NULL,
        practical_pass decimal(5,2) DEFAULT '0.00' NOT NULL,
        breakdown_data longtext NULL DEFAULT NULL,
        PRIMARY KEY  (id),
        KEY class_id_idx (class_id)
    ) {$charset_collate};";
    dbDelta( $sql_subjects );

    // 10. Routine Table
$table_routine = $wpdb->prefix . 'sms_routine';
$sql_routine = "CREATE TABLE {$table_routine} (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    class_id bigint(20) unsigned NOT NULL,
    section_id bigint(20) unsigned DEFAULT 0 NOT NULL,
    subject_id bigint(20) unsigned NOT NULL,
    teacher_id bigint(20) unsigned DEFAULT 0 NOT NULL,
    day_name varchar(15) NOT NULL,
    shift varchar(50) DEFAULT 'Day' NOT NULL,
    start_time time NOT NULL,
    end_time time NOT NULL,
    room_no varchar(50) DEFAULT '' NOT NULL,
    academic_year varchar(20) DEFAULT '' NOT NULL,
    created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
    PRIMARY KEY  (id),
    KEY class_section_idx (class_id, section_id),
    KEY subject_id_idx (subject_id),
    KEY teacher_id_idx (teacher_id),
    KEY day_time_idx (day_name, start_time, end_time)
) {$charset_collate};";

require_once ABSPATH . 'wp-admin/includes/upgrade.php';
dbDelta( $sql_routine );

    // 11. Teacher-Subjects Mapping Table
    $table_teacher_subjects = $wpdb->prefix . 'sms_teacher_subjects';
    $sql_teacher_subjects = "CREATE TABLE {$table_teacher_subjects} (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        teacher_id bigint(20) NOT NULL,
        subject_id bigint(20) NOT NULL,
        class_id bigint(20) NOT NULL,
        assigned_date datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id),
        KEY teacher_idx (teacher_id),
        KEY subject_idx (subject_id),
        KEY class_idx (class_id)
    ) {$charset_collate};";
    dbDelta( $sql_teacher_subjects );

    // 12. Accounting Table
    $table_accounting = $wpdb->prefix . 'sms_accounting';
    $sql_accounting = "CREATE TABLE {$table_accounting} (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        voucher_no varchar(50) NOT NULL,
        entry_type varchar(20) DEFAULT 'Income' NOT NULL,
        category_name varchar(100) NOT NULL,
        title varchar(255) NOT NULL,
        party_name varchar(150) DEFAULT '' NOT NULL,
        amount decimal(10,2) DEFAULT '0.00' NOT NULL,
        payment_method varchar(50) DEFAULT 'Cash' NOT NULL,
        entry_date date NOT NULL,
        note text NOT NULL,
        attachment_url text NULL DEFAULT NULL,
        created_by bigint(20) NOT NULL,
        PRIMARY KEY  (id),
        KEY entry_type_date_idx (entry_type, entry_date),
        KEY entry_date_idx (entry_date)
    ) {$charset_collate};";
    dbDelta( $sql_accounting );

    // 13. Notices & Events Table
    $table_notices = $wpdb->prefix . 'sms_notices';
    $sql_notices = "CREATE TABLE {$table_notices} (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        title varchar(255) NOT NULL,
        notice_type varchar(50) DEFAULT 'General' NOT NULL,
        priority varchar(20) DEFAULT 'Normal' NOT NULL,
        target_audience varchar(50) DEFAULT 'All' NOT NULL,
        description text NOT NULL,
        event_date date NULL DEFAULT NULL,
        publish_date date NULL DEFAULT NULL,
        attachment_url text NULL DEFAULT NULL,
        created_by bigint(20) NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        status varchar(20) DEFAULT 'Published' NOT NULL,
        featured_image varchar(255) DEFAULT '' NOT NULL,
        item_type varchar(30) DEFAULT 'notice' NOT NULL,
        content longtext NOT NULL,
        PRIMARY KEY  (id),
        KEY notice_type_idx (notice_type),
        KEY item_type_idx (item_type),
        KEY status_idx (status),
        KEY publish_date_idx (publish_date)
    ) {$charset_collate};";
    dbDelta( $sql_notices );

    // 14. Exam Questions Table
    $table_questions = $wpdb->prefix . 'sms_exam_questions';
    $sql_questions = "CREATE TABLE {$table_questions} (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        exam_id bigint(20) NOT NULL,
        class_name varchar(50) NOT NULL,
        question_type varchar(20) DEFAULT 'CQ' NOT NULL,
        subject_name varchar(150) NOT NULL,
        subject_code varchar(50) DEFAULT '' NOT NULL,
        exam_duration varchar(50) DEFAULT '২ ঘণ্টা ৩০ মিনিট' NOT NULL,
        total_marks decimal(5,2) DEFAULT '70.00' NOT NULL,
        instructions text NOT NULL,
        cq_data longtext NOT NULL,
        mcq_data longtext NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id),
        KEY exam_class_idx (exam_id, class_name),
        KEY qtype_idx (question_type)
    ) {$charset_collate};";
    dbDelta( $sql_questions );

    // 15. Gallery Albums Table
    $table_albums = $wpdb->prefix . 'sms_gallery_albums';
    $sql_albums = "CREATE TABLE {$table_albums} (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        title varchar(255) NOT NULL,
        category varchar(100) DEFAULT 'General' NOT NULL,
        description text NULL DEFAULT NULL,
        cover_image text NULL DEFAULT NULL,
        status varchar(20) DEFAULT 'Published' NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id)
    ) {$charset_collate};";
    dbDelta( $sql_albums );

    // 16. Gallery Photos Table
    $table_photos = $wpdb->prefix . 'sms_gallery_photos';
    $sql_photos = "CREATE TABLE {$table_photos} (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        album_id bigint(20) NOT NULL,
        image_url text NOT NULL,
        caption varchar(255) DEFAULT '' NOT NULL,
        uploaded_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id),
        KEY album_idx (album_id)
    ) {$charset_collate};";
    dbDelta( $sql_photos );

    // Update DB Version
    $db_version = defined( 'EDUCORE_VERSION' ) ? EDUCORE_VERSION : '1.2.2';
    update_option( 'educore_db_version', $db_version );
}
register_activation_hook( __FILE__, 'educore_execute_database_migration' );

add_action( 'plugins_loaded', function() {
    $current_ver = get_option( 'educore_db_version', '0' );
    $target_ver  = defined( 'EDUCORE_VERSION' ) ? EDUCORE_VERSION : '1.2.2';
    if ( version_compare( (string) $current_ver, (string) $target_ver, '<' ) ) {
        educore_execute_database_migration();
    }
} );

/**
 * 6. Security Action & Event Logging Engine
 */
function educore_log_activity( $action_description ) {
    global $wpdb;
    $current_user = wp_get_current_user();
    $user_id   = ( $current_user && $current_user->exists() ) ? $current_user->ID : 0;
    $user_role = ( $current_user && $current_user->exists() ) ? implode( ', ', (array) $current_user->roles ) : 'guest';
    
    $ip_address = '0.0.0.0';
    if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
        $raw_ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
        if ( filter_var( $raw_ip, FILTER_VALIDATE_IP ) ) {
            $ip_address = $raw_ip;
        }
    }

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->insert(
        $wpdb->prefix . 'sms_audit_logs',
        array(
            'user_id'            => $user_id,
            'user_role'          => $user_role,
            'action_performed' => sanitize_text_field( $action_description ),
            'ip_address'       => $ip_address,
            'timestamp'        => current_time( 'mysql' ),
        ),
        array( '%d', '%s', '%s', '%s', '%s' )
    );
    // phpcs:enable
}

/**
 * 7. Data Map Configuration
 */
function educore_get_tabs_config() {
    $tabs = array(
        'dashboard' => array(
            'label' => __( 'Dashboard', 'ifsedu-school-management' ),
            'svg'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 544 512"><path d="M528 0H16C7.2 0 0 7.2 0 16v480c0 8.8 7.2 16 16 16h512c8.8 0 16-7.2 16-16V16c0-8.8-7.2-16-16-16zM272 248v-88c0-4.4 3.6-8 8-8h184c4.4 0 8 3.6 8 8v88c0 4.4-3.6 8-8 8H280c-4.4 0-8-3.6-8-8zm0 176v-88c0-4.4 3.6-8 8-8h184c4.4 0 8 3.6 8 8v88c0 4.4-3.6 8-8 8H280c-4.4 0-8-3.6-8-8zM72 152c0-4.4 3.6-8 8-8h112c4.4 0 8 3.6 8 8v208c0 4.4-3.6 8-8 8H80c-4.4 0-8-3.6-8-8V152z"/></svg>',
            'roles' => array(),
        ),
        'students' => array(
            'label' => __( 'Students', 'ifsedu-school-management' ),
            'svg'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 512"><path d="M320 32c-8.1 0-16.1 1.4-23.7 4.1L15.8 137.4C6.3 140.9 0 149.9 0 160s6.3 19.1 15.8 22.6l57.9 20.9C57.3 229.3 48 259.8 48 291.9v28.1c0 28.4-10.8 57.7-22.3 80.8-6.5 13-13.9 25.8-22.5 37.6-4.1 5.6-3.8 13.3 .9 18.6s12.5 5.5 18.6 1c43.6-32.3 75.3-78.8 89.6-132.3L320 380c103.5 0 197.5-44.5 259.5-114.7l44.6-16.1c9.5-3.5 15.8-12.5 15.8-22.6s-6.3-19.1-15.8-22.6L343.7 36.1C336.1 33.4 328.1 32 320 32zM128 408c0 35.3 86 72 192 72s192-36.7 192-72L496.7 262.6C454.4 316.5 390 348 320 348S185.6 316.5 143.3 262.6L128 408z"/></svg>',
            'roles' => array( 'administrator', 'teacher' ),
        ),
        'attendance' => array(
            'label' => __( 'Attendance', 'ifsedu-school-management' ),
            'svg'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512"><path d="M152 24c0-13.3-10.7-24-24-24s-24 10.7-24 24V64H64C28.7 64 0 92.7 0 128v16 48V448c0 35.3 28.7 64 64 64H384c35.3 0 64-28.7 64-64V192 144 128c0-35.3-28.7-64-64-64H344V24c0-13.3-10.7-24-24-24s-24 10.7-24 24V64H152V24zM48 192h352v256c0 8.8-7.2 16-16 16H64c-8.8 0-16-7.2-16-16V192zm278.6 57.4c-12.5-12.5-32.8-12.5-45.3 0L192 338.7l-57.4-57.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3l80 80c12.5 12.5 32.8 12.5 45.3 0l112-112c12.5-12.5 12.5-32.8 0-45.3z"/></svg>',
            'roles' => array( 'administrator', 'teacher' ),
        ),
        'fees' => array(
            'label' => __( 'Fee Collection', 'ifsedu-school-management' ),
            'svg'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512"><path d="M64 64C28.7 64 0 92.7 0 128v256c0 35.3 28.7 64 64 64H512c35.3 0 64-28.7 64-64V128c0-35.3-28.7-64-64-64H64zm64 320H64V320c35.3 0 64 28.7 64 64zM64 192V128h64c0 35.3-28.7 64-64 64zM448 384c0-35.3 28.7-64 64-64v64H448zm64-192c-35.3 0-64-28.7-64-64h64v64zM288 160a96 96 0 1 1 0 192 96 96 0 1 1 0-192z"/></svg>',
            'roles' => array( 'administrator', 'accountant' ),
        ),
        'accounting' => array(
            'label' => __( 'Accounting & Ledger', 'ifsedu-school-management' ),
            'svg'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path d="M64 32C28.7 32 0 60.7 0 96V416c0 35.3 28.7 64 64 64H448c35.3 0 64-28.7 64-64V96c0-35.3-28.7-64-64-64H64zM336 96c17.7 0 32 14.3 32 32s-14.3 32-32 32s-32-14.3-32-32s14.3-32 32-32zm0 128c17.7 0 32 14.3 32 32s-14.3 32-32 32s-32-14.3-32-32s14.3-32 32-32zM128 288h96c13.3 0 24 10.7 24 24s-10.7 24-24 24H128c-13.3 0-24-10.7-24-24s10.7-24 24-24zm0-96h96c13.3 0 24 10.7 24 24s-10.7 24-24 24H128c-13.3 0-24-10.7-24-24s10.7-24 24-24zm0-96h96c13.3 0 24 10.7 24 24s-10.7 24-24 24H128c-13.3 0-24-10.7-24-24s10.7-24 24-24z"/></svg>',
            'roles' => array( 'administrator', 'accountant' ),
        ),
        'exams' => array(
            'label' => __( 'Exams Setup', 'ifsedu-school-management' ),
            'svg'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path d="M152.1 38.2c9.9 8.9 10.7 24 1.8 33.9l-72 80c-4.8 5.3-11.2 8.1-18.1 7.8s-13.1-3.6-17.5-9L14.4 115.1c-8.2-10-6.8-24.8 3.2-33s24.8-6.8 33 3.2l16 19.5 51.5-57.3c8.9-9.9 24-10.7 33.9-1.8zM416 128H256c-17.7 0-32-14.3-32-32s14.3-32 32-32H416c17.7 0 32 14.3 32 32s-14.3 32-32 32zM152.1 198.2c9.9 8.9 10.7 24 1.8 33.9l-72 80c-4.8 5.3-11.2 8.1-18.1 7.8s-13.1-3.6-17.5-9L14.4 275.1c-8.2-10-6.8-24.8 3.2-33s24.8-6.8 33 3.2l16 19.5 51.5-57.3c8.9-9.9 24-10.7 33.9-1.8zM416 288H256c-17.7 0-32-14.3-32-32s14.3-32 32-32H416c17.7 0 32 14.3 32 32s-14.3 32-32 32zM152.1 358.2c9.9 8.9 10.7 24 1.8 33.9l-72 80c-4.8 5.3-11.2 8.1-18.1 7.8s-13.1-3.6-17.5-9L14.4 435.1c-8.2-10-6.8-24.8 3.2-33s24.8-6.8 33 3.2l16 19.5 51.5-57.3c8.9-9.9 24-10.7 33.9-1.8zM416 448H256c-17.7 0-32-14.3-32-32s14.3-32 32-32H416c17.7 0 32 14.3 32 32s-14.3 32-32 32z"/></svg>',
            'roles' => array( 'administrator' ),
        ),
        'results' => array(
            'label' => __( 'Results & Marks', 'ifsedu-school-management' ),
            'svg'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512"><path d="M400 0H176c-26.5 0-48 21.5-48 48v416c0 26.5 21.5 48 48 48h224c26.5 0 48-21.5 48-48V48c0-26.5-21.5-48-48-48zm-16 416H192v-32h192v32zm0-96H192v-32h192v32zm0-96H192v-32h192v32zm0-96H192V96h192v32zM547.6 156.4L502.2 111c-12.5-12.5-32.8-12.5-45.3 0l-26.9 26.9 71.9 71.9 26.9-26.9c12.5-12.5 12.5-32.8-1.2-46.5zM28.4 156.4l45.4-45.4c12.5-12.5 32.8-12.5 45.3 0l26.9 26.9-71.9 71.9-26.9-26.9c-12.5-12.5-12.5-32.8 1.2-46.5z"/></svg>',
            'roles' => array( 'administrator', 'teacher' ),
        ),
        'staff' => array(
            'label' => __( 'Teachers & Staff', 'ifsedu-school-management' ),
            'svg'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 512"><path d="M224 256A128 128 0 1 0 224 0a128 128 0 1 0 0 256zm-45.7 48C79.8 304 0 383.8 0 482.3C0 498.7 13.3 512 29.7 512H322.8c-3.1-8.8-3.7-18.4-1.4-27.8l15-60.1c2.8-11.3 8.6-21.5 16.8-29.7l40.3-40.3c-32.1-31-75.7-50.1-123.9-50.1H178.3zm435.5-68.3c-15.6-15.6-40.9-15.6-56.6 0l-29.4 29.4 71 71 29.4-29.4c15.6-15.6 15.6-40.9 0-56.6l-14.4-14.4zM375.9 417c-4.1 4.1-7 9.2-8.4 14.9l-15 60.1c-1.4 5.5 .2 11.2 4.2 15.2s9.7 5.6 15.2 4.2l60.1-15c5.6-1.4 10.8-4.3 14.9-8.4L576.1 358.7l-71-71L375.9 417z"/></svg>',
            'roles' => array( 'administrator' ),
        ),
        'academics' => array(
            'label' => __( 'Academic Setup', 'ifsedu-school-management' ),
            'svg'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 448 512"><path d="M96 0C43 0 0 43 0 96V416c0 53 43 96 96 96H384h32c17.7 0 32-14.3 32-32s-14.3-32-32-32V384c17.7 0 32-14.3 32-32V32c0-17.7-14.3-32-32-32H384 96zm0 384H352v64H96c-17.7 0-32-14.3-32-32s14.3-32 32-32zm32-240c0-8.8 7.2-16 16-16H336c8.8 0 16 7.2 16 16s-7.2 16-16 16H144c-8.8 0-16-7.2-16-16zm16 48H336c8.8 0 16 7.2 16 16s-7.2 16-16 16H144c-8.8 0-16-7.2-16-16s7.2-16 16-16z"/></svg>',
            'roles' => array( 'administrator' ),
        ),
        'notices' => array(
            'label' => __( 'Notices & Events', 'ifsedu-school-management' ),
            'svg'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path d="M160 368c26.5 0 48 21.5 48 48v16l72.5-54.4c8.3-6.2 18.4-9.6 28.8-9.6H448c8.8 0 16-7.2 16-16V64c0-8.8-7.2-16-16-16H64c-8.8 0-16 7.2-16 16V352c0 8.8 7.2 16 16 16h96zm48 124l-.2 .2-5.1 3.8-17.1 12.8c-4.8 3.6-11.3 4.2-16.8 1.5s-8.8-8.2-8.8-14.3V474.7v-4.5V416H160c-53 0-96-43-96-96V64C64 11 107-32 160-32H448c53 0 96 43 96 96V352c0 53-43 96-96 96H309.3L208 504z"/></svg>',
            'roles' => array( 'administrator' ),
        ),
        'reports' => array(
            'label' => __( 'Reports', 'ifsedu-school-management' ),
            'svg'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512"><path d="M336 0H48C21.5 0 0 21.5 0 48v416c0 26.5 21.5 48 48 48h288c26.5 0 48-21.5 48-48V48c0-26.5-21.5-48-48-48zM144 432H96v-48h48v48zm0-96H96v-48h48v48zm0-96H96v-48h48v48zm144 192H176v-48h112v48zm0-96H176v-48h112v48zm0-96H176v-48h112v48zm0-112H96V80h192v48z"/></svg>',
            'roles' => array( 'administrator' ),
        ),
        'users' => array(
            'label' => __( 'Users & Roles', 'ifsedu-school-management' ),
            'svg'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 512"><path d="M144 0a80 80 0 1 1 0 160A80 80 0 1 1 144 0zM512 0a80 80 0 1 1 0 160A80 80 0 1 1 512 0zM0 298.7C0 239.8 47.8 192 106.7 192h74.7c58.9 0 106.7 47.8 106.7 106.7V352H0v-53.3zM352 352v-53.3c0-58.9 47.8-106.7 106.7-106.7h74.7c58.9 0 106.7 47.8 106.7 106.7V352H352zM320 256a64 64 0 1 0 0-128 64 64 0 1 0 0 128zm-96 160c0-35.3 28.7-64 64-64h64c35.3 0 64 28.7 64 64v32H224v-32z"/></svg>',
            'roles' => array( 'administrator' ),
        ),
        'settings' => array(
            'label' => __( 'Settings', 'ifsedu-school-management' ),
            'svg'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path d="M487.4 315.7l-42.6-24.6c2.3-14.2 3.5-28.7 3.5-43.1s-1.2-28.9-3.5-43.1l42.6-24.6c11.5-6.6 15.4-21.3 8.7-32.8L447.5 61.2c-6.6-11.5-21.3-15.4-32.8-8.7L372 77.1c-22.1-14.8-46.7-26.3-72.9-33.8L292.8 12C291.1 5.2 285 0 278.1 0h-44.2c-6.9 0-13 5.2-14.7 12L213 43.3c-26.2 7.5-50.8 19-72.9 33.8l-42.7-24.6c-11.5-6.7-26.2-2.8-32.8 8.7L16.1 147.3c-6.7 11.5-2.8 26.2 8.7 32.8l42.6 24.6c-2.3 14.2-3.5 28.7-3.5 43.1s1.2 28.9 3.5 43.1l-42.6 24.6c-11.5 6.6-15.4 21.3-8.7 32.8l48.6 84.3c6.6 11.5 21.3 15.4 32.8 8.7l42.7-24.6c22.1 14.8 46.7 26.3 72.9 33.8L219.2 500c1.7 6.8 7.8 12 14.7 12h44.2c6.9 0 13-5.2 14.7-12l6.3-31.3c26.2-7.5 50.8-19 72.9-33.8l42.7 24.6c11.5 6.7 26.2 2.8 32.8-8.7l48.6-84.3c6.7-11.5 2.8-26.2-8.7-32.9zM256 336c-44.2 0-80-35.8-80-80s35.8-80 80-80 80 35.8 80 80-35.8 80-80 80z"/></svg>',
            'roles' => array( 'administrator' ),
        ),
        'logout' => array(
            'label' => __( 'Log Out', 'ifsedu-school-management' ),
            'svg'   => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><path d="M160 96c17.7 0 32-14.3 32-32s-14.3-32-32-32H96C43 32 0 75 0 128v256c0 53 43 96 96 96h64c17.7 0 32-14.3 32-32s-14.3-32-32-32H96c-17.7 0-32-14.3-32-32V128c0-17.7 14.3-32 32-32h64zm273 135L313 111c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3l123 123H192c-17.7 0-32 14.3-32 32s14.3 32 32 32h198.7L267.7 401.7c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0l120-120c12.5-12.5 12.5-32.8 0-45.3z"/></svg>',
            'roles' => array(),
        ),
    );

    if ( ! educore_is_settings_manager() ) {
        unset( $tabs['settings'] );
    }

    return $tabs;
}

/**
 * 8. Mount Core Dashboard Admin Navigation Routing Nodes
 */
function educore_mount_core_erp_menu() {
    add_menu_page(
        __( 'EduCore - School Management System', 'ifsedu-school-management' ),
        __( 'School ERP', 'ifsedu-school-management' ),
        'read', 
        'school_management_system',
        'educore_render_dynamic_router_interface', 
        'dashicons-welcome-learn-more',
        20
    );

    $tabs = educore_get_tabs_config();

    foreach ( $tabs as $slug => $config ) {
        if ( 'logout' === $slug ) {
            continue;
        }

        $cap = 'read';
        if ( in_array( 'administrator', (array) $config['roles'], true ) ) {
            $cap = 'manage_options';
        }

        add_submenu_page(
            'school_management_system',
            $config['label'] . ' - ' . __( 'School ERP', 'ifsedu-school-management' ),
            $config['label'],
            $cap,
            'school_management_system_' . $slug,
            'educore_render_dynamic_router_interface'
        );
    }
}
add_action( 'admin_menu', 'educore_mount_core_erp_menu' );

/**
 * 9. Component Render Router Module Interface with Sidebar Toggle
 */
function educore_render_dynamic_router_interface() {
    $all_tabs = educore_get_tabs_config();

    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
    
    if ( empty( $active_tab ) && isset( $_GET['page'] ) ) {
        $page = sanitize_key( wp_unslash( $_GET['page'] ) );
        if ( strpos( $page, 'school_management_system_' ) === 0 ) {
            $active_tab = str_replace( 'school_management_system_', '', $page );
        }
    }
    // phpcs:enable WordPress.Security.NonceVerification.Recommended
    
    if ( empty( $active_tab ) || ! array_key_exists( $active_tab, $all_tabs ) ) {
        $active_tab = 'dashboard';
    }

    if ( 'settings' === $active_tab && ! educore_is_settings_manager() ) {
        echo '<div class="notice notice-error" style="margin:20px 20px 20px 0;"><p>' . esc_html__( 'Access Denied: You do not possess the required privilege level for this module.', 'ifsedu-school-management' ) . '</p></div>';
        return;
    }

    if ( ! educore_has_access( $all_tabs[ $active_tab ]['roles'] ) ) {
        echo '<div class="notice notice-error" style="margin:20px 20px 20px 0;"><p>' . esc_html__( 'Access Denied: You do not possess the required privilege level for this module.', 'ifsedu-school-management' ) . '</p></div>';
        return;
    }

    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $action_param  = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
    $sub_param     = isset( $_GET['sub'] ) ? sanitize_key( wp_unslash( $_GET['sub'] ) ) : '';
    $is_print_mode = ( 'print' === $action_param ) || ( in_array( $sub_param, array( 'print', 'id_card', 'admit_card' ), true ) && isset( $_GET['print_mode'] ) );
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    global $wpdb;
    $user_id       = get_current_user_id();
    $display_name  = '';
    $designation   = '';
    $custom_avatar = '';

    $table_staff = $wpdb->prefix . 'sms_staff';
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $staff_row = $wpdb->get_row( $wpdb->prepare( "SELECT full_name, designation, profile_image FROM `{$table_staff}` WHERE wp_user_id = %d LIMIT 1", $user_id ) );
    // phpcs:enable

    if ( $staff_row ) {
        $display_name  = $staff_row->full_name;
        $designation   = $staff_row->designation;
        $custom_avatar = $staff_row->profile_image;
    }

    if ( empty( $display_name ) ) {
        $current_user = wp_get_current_user();
        $display_name = ( $current_user && $current_user->display_name ) ? $current_user->display_name : __( 'Staff Member', 'ifsedu-school-management' );
    }
    if ( empty( $designation ) ) {
        $designation = __( 'School Administrator', 'ifsedu-school-management' );
    }
    ?>

    <div id="educore-wrapper" class="school-management-system <?php echo $is_print_mode ? 'educore-print' : ''; ?>">
        
        <?php if ( ! $is_print_mode ) : ?>
            <aside class="educore-sidebar-container" id="educoreSidebar">
                <div class="educore-author-profile">
                    <div class="profile-avatar">
                        <?php 
                        if ( ! empty( $custom_avatar ) ) {
                            echo '<img src="' . esc_url( $custom_avatar ) . '" alt="' . esc_attr( $display_name ) . '" />';
                        } else {
                            $default_avatar_url = EDUCORE_URL . 'assets/img/logo.png'; 
                            echo '<img src="' . esc_url( $default_avatar_url ) . '" alt="' . esc_attr( $display_name ) . '" />'; 
                        }
                        ?>
                    </div>
                    <div class="profile-meta">
                        <h4 class="profile-name" title="<?php echo esc_attr( $display_name ); ?>"><?php echo esc_html( $display_name ); ?></h4>
                        <span class="profile-designation"><?php echo esc_html( $designation ); ?></span>
                    </div>
                    <button type="button" class="educore-sidebar-toggle-btn" id="educoreToggleSidebar" title="<?php esc_attr_e( 'Toggle Sidebar Width', 'ifsedu-school-management' ); ?>">
                        <span class="dashicons dashicons-menu-alt3"></span>
                    </button>
                </div>

                <ul class="educore-left-tabs">
                    <?php 
                    foreach ( $all_tabs as $slug => $config ) : 
                        if ( ! educore_has_access( $config['roles'] ) ) {
                            continue; 
                        }
                        $active_class = ( $active_tab === $slug ) ? 'active' : '';
                        $target_url   = ( 'logout' === $slug ) 
                            ? wp_logout_url( admin_url( 'admin.php?page=school_management_system' ) ) 
                            : admin_url( 'admin.php?page=school_management_system&tab=' . $slug );
                        ?>
                        <li class="<?php echo esc_attr( 'tab-' . $slug ); ?>">
                            <a class="<?php echo esc_attr( $active_class ); ?>" href="<?php echo esc_url( $target_url ); ?>" title="<?php echo esc_attr( $config['label'] ); ?>">
                                <?php echo wp_kses( $config['svg'], educore_get_allowed_svg_html() ); ?>
                                <span class="educore-tab-label"><?php echo esc_html( $config['label'] ); ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </aside>
        <?php endif; ?>

        <main class="educore-right-box">
            <?php
            $callback = 'educore_' . $active_tab . '_tab';
            if ( function_exists( $callback ) ) {
                call_user_func( $callback );
            } else {
                $alt_callback = 'educore_' . $active_tab . '_view';
                if ( function_exists( $alt_callback ) ) {
                    call_user_func( $alt_callback );
                }
            }
            ?>

            <!-- Footer Copyright Section -->
            <footer class="educore-dashboard-footer" style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #e2e8f0; text-align: center; color: #64748b; font-size: 13px;">
                <p style="margin: 0;">&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php esc_html_e( 'Copyright by Infinity flame soft', 'ifsedu-school-management' ); ?></p>
            </footer>
        </main>
    </div>
    <?php
}

/**
 * 10. Dashboard Shell Custom Layout Injection
 */
function educore_inject_dashboard_white_label_layout() {
    $screen = get_current_screen();
    if ( $screen && strpos( $screen->id, 'school_management_system' ) !== false ) {
        ?>
        <style>
        #wpadminbar, #adminmenu, #adminmenuback, #adminmenuwrap, #wpfooter { 
            display: none !important; 
        }
        #wpcontent, #wpbody-content { 
            margin-left: 0 !important; 
            padding: 0 !important; 
            width: 100% !important; 
        }
        body.wp-admin { 
            background: #f8fafc; 
            overflow-x: hidden; 
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }

        .school-management-system { 
            display: flex; 
            position: relative; 
            min-height: 100vh; 
            width: 100%;
        }

        .educore-sidebar-container { 
            width: 250px; 
            flex-shrink: 0; 
            background: #ffffff; 
            border-right: 1px solid #e2e8f0; 
            position: sticky;
            top: 0;
            height: 100vh;
            display: flex;
            flex-direction: column;
            box-sizing: border-box;
            z-index: 99;
            transition: width 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .educore-sidebar-container.collapsed {
            width: 78px;
        }

        .educore-author-profile { 
            width: 100%;
            display: flex; 
            align-items: center; 
            gap: 12px; 
            padding: 18px 16px; 
            border-bottom: 1px solid #e2e8f0; 
            box-sizing: border-box;
            flex-shrink: 0;
            background: #ffffff;
            position: relative;
        }

        .educore-author-profile .profile-avatar img { 
            width: 44px; 
            height: 44px; 
            border-radius: 50%; 
            border: 2px solid #00523c; 
            object-fit: cover;
            flex-shrink: 0;
        }

        .educore-author-profile .profile-meta { 
            display: flex; 
            flex-direction: column; 
            gap: 2px; 
            overflow: hidden; 
            transition: opacity 0.2s ease, width 0.2s ease; 
            white-space: nowrap; 
        }

        .educore-author-profile .profile-name { 
            margin: 0; 
            font-size: 14px; 
            font-weight: 800; 
            color: #0f172a;
            overflow: hidden; 
            text-overflow: ellipsis; 
        }

        .educore-author-profile .profile-designation { 
            font-size: 11.5px; 
            font-weight: 600; 
            color: #64748b;
            overflow: hidden; 
            text-overflow: ellipsis; 
        }

        .educore-sidebar-toggle-btn { 
            background: transparent; 
            border: none; 
            color: #64748b; 
            cursor: pointer; 
            padding: 4px; 
            border-radius: 6px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            margin-left: auto; 
            transition: background-color 0.2s, color 0.2s; 
        }
        .educore-sidebar-toggle-btn:hover { 
            background: #f1f5f9; 
            color: #00523c; 
        }

        .educore-sidebar-container.collapsed .profile-meta,
        .educore-sidebar-container.collapsed .educore-tab-label { 
            display: none !important; 
        }

        .educore-sidebar-container.collapsed .educore-author-profile { 
            justify-content: center; 
            padding: 16px 8px; 
        }
        .educore-sidebar-container.collapsed .educore-sidebar-toggle-btn { 
            position: absolute; 
            bottom: -14px; 
            right: 50%; 
            transform: translateX(50%); 
            background: #ffffff; 
            border: 1px solid #cbd5e1; 
            border-radius: 50%; 
            width: 26px; 
            height: 26px; 
            box-shadow: 0 2px 6px rgba(0,0,0,0.08); 
        }

        .educore-left-tabs { 
            width: 100%;
            margin: 0; 
            padding: 12px 8px; 
            list-style: none; 
            flex: 1;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 4px;
            box-sizing: border-box;
            scrollbar-width: thin;
            scrollbar-color: #cbd5e1 transparent;
        }

        .educore-left-tabs li a { 
            display: flex; 
            align-items: center; 
            gap: 12px; 
            padding: 10px 14px; 
            color: #475569; 
            text-decoration: none; 
            font-weight: 700; 
            font-size: 13.5px; 
            border-radius: 10px;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); 
            white-space: nowrap;
        }

        .educore-sidebar-container.collapsed .educore-left-tabs li a {
            justify-content: center;
            padding: 10px;
        }

        .educore-left-tabs li a svg { 
            width: 18px; 
            height: 18px; 
            fill: #64748b; 
            flex-shrink: 0; 
            transition: fill 0.2s ease; 
        }

        .educore-left-tabs li a:hover { 
            background: #f0fdf4; 
            color: #065f46; 
        }
        .educore-left-tabs li a:hover svg { fill: #00523c; }

        .educore-left-tabs li a.active { 
            background: #00523c; 
            color: #ffffff; 
            box-shadow: 0 4px 12px rgba(0, 106, 78, 0.2);
        }
        .educore-left-tabs li a.active svg { fill: #ffffff; }

        .educore-left-tabs li.tab-logout a:hover { 
            background: #fef2f2; 
            color: #dc2626; 
        }
        .educore-left-tabs li.tab-logout a:hover svg { fill: #dc2626; }

        .educore-right-box { 
            flex: 1; 
            background: #f8fafc; 
            padding: 30px 34px; 
            min-width: 0;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        @media print {
            .educore-sidebar-container, .no-print { display: none !important; }
            .educore-right-box { padding: 0 !important; background: #ffffff !important; }
        }
        </style>
        <?php
    }
}
add_action( 'admin_head', 'educore_inject_dashboard_white_label_layout' );

/**
 * 11. Security & Redirection Hooks
 */
function educore_handle_secure_logout_redirection() {
    wp_safe_redirect( home_url() );
    exit;
}
add_action( 'wp_logout', 'educore_handle_secure_logout_redirection' );

/**
 * 12. Enhanced White-Label Login Page & Side Demo Credentials Panel
 */
function educore_apply_white_label_login_styles() {
    $custom_logo_url = plugin_dir_url( __FILE__ ) . 'assets/img/logo.png';
    ?>
    <style type="text/css">
        body.login { 
            background: #f1f5f9 !important; 
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important; 
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
            box-sizing: border-box;
        }
        
        body.login::after {
            content: "";
            clear: both;
            display: table;
        }

        #login { 
            width: 380px !important; 
            padding: 0 !important;
            margin: 0 !important;
            float: none !important;
            display: inline-block;
            vertical-align: middle;
        }

        .login h1 {
            width: 100% !important;
            text-align: center;
        }

        #login h1 a, .login h1 a {
            background-image: url('<?php echo esc_url( $custom_logo_url ); ?>') !important;
            height: 70px !important;
            width: 100% !important;
            background-size: contain !important;
            background-position: center !important;
            margin-bottom: 20px !important;
            background-color: transparent;
        }
        .login form { 
            background: #ffffff !important; 
            border: 1px solid #e2e8f0 !important; 
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.05) !important; 
            border-radius: 14px !important; 
            padding: 30px !important; 
        }
        .login label {
            color: #334155 !important;
            font-weight: 600 !important;
            font-size: 13.5px !important;
        }
        .login input.input {
            background: #f8fafc !important;
            border: 1px solid #cbd5e1 !important;
            border-radius: 8px !important;
            padding: 10px 12px !important;
            color: #0f172a !important;
            font-size: 14px !important;
            box-shadow: none !important;
            transition: all 0.2s ease;
        }
        .login input.input:focus {
            border-color: #00523c !important;
            background: #ffffff !important;
            box-shadow: 0 0 0 3px rgba(0, 86, 167, 0.15) !important;
        }
        .wp-core-ui .button-primary { 
            background: #00523c !important; 
            border: none !important; 
            border-radius: 8px !important; 
            height: 42px !important; 
            font-weight: 600 !important;
            font-size: 14px !important;
            width: 100% !important;
            transition: background 0.2s ease;
        }
        .wp-core-ui .button-primary:hover {
            background: #004080 !important;
        }
        .login #backtoblog, .login #nav, .privacy-policy-page-link { 
            display: none !important; 
        }

        .educore-demo-credentials {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 40px !important;
            width: 290px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
            font-size: 13px;
            color: #475569;
            display: inline-block;
            vertical-align: middle;
            margin-left: 30px;
            box-sizing: border-box;
        }
        .educore-demo-credentials h3 {
            margin: 0 0 12px 0;
            font-size: 14px;
            color: #0f172a;
            font-weight: 700;
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 8px;
            letter-spacing: -0.01em;
        }
        .educore-demo-credentials ul {
            margin: 0;
            padding-left: 0;
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .educore-demo-credentials li {
            background: #f8fafc;
            border: 1px solid #f1f5f9;
            padding: 8px 10px;
            border-radius: 8px;
            line-height: 1.4;
        }
        .educore-demo-credentials strong {
            color: #1e293b;
            display: block;
            margin-bottom: 2px;
        }
        .educore-demo-credentials code {
            background: #e2e8f0;
            padding: 1px 4px;
            border-radius: 4px;
            color: #00523c;
            font-weight: 600;
            font-size: 11.5px;
        }

        @media screen and (max-width: 768px) {
            body.login {
                flex-direction: column;
                height: auto;
            }
            .educore-demo-credentials {
                margin-left: 0;
                margin-top: 20px;
                width: 380px;
            }
        }
    </style>
    <?php
}
add_action( 'login_enqueue_scripts', 'educore_apply_white_label_login_styles' );

function educore_get_login_logo_url() {
    return home_url();
}
add_filter( 'login_headerurl', 'educore_get_login_logo_url' );

function educore_get_login_logo_title() {
    return get_bloginfo( 'name' );
}
add_filter( 'login_headertext', 'educore_get_login_logo_title' );

/**
 * 13. Captcha Validation Engine (Stateless HMAC-Signed Verification)
 * Fixed: Avoids transient race conditions and object-cache dependency on failed logins.
 */
function educore_display_mathematical_captcha() {
    $num1 = wp_rand( 1, 9 );
    $num2 = wp_rand( 1, 9 );
    $sum  = $num1 + $num2;
    $time = time();

    // Generate tamper-proof signature with expiration window
    $payload = $sum . '|' . $time;
    $token   = hash_hmac( 'sha256', $payload, wp_salt( 'nonce' ) ) . ':' . $payload;
    ?>
    <div class="educore-captcha-container" style="margin: 15px 0;">
        <label for="educore_captcha_answer" style="display: block; margin-bottom: 4px; font-weight: bold; color: #475569;">
            <?php esc_html_e( 'Security Question', 'ifsedu-school-management' ); ?>
        </label>
        <p style="margin: 0 0 6px 0; color: #64748b; font-size: 13px;">
            <?php
            printf(
                /* translators: 1: First number, 2: Second number */
                esc_html__( 'Calculate: %1$d + %2$d = ?', 'ifsedu-school-management' ),
                intval( $num1 ),
                intval( $num2 )
            );
            ?>
        </p>
        <input type="number" name="educore_captcha_answer" id="educore_captcha_answer" class="input" style="width: 100%; border-radius: 6px; border: 1px solid #cbd5e1; padding: 6px 10px;" autocomplete="off" required />
        <input type="hidden" name="educore_captcha_token" value="<?php echo esc_attr( $token ); ?>" />
    </div>
    <?php
}
add_action( 'login_form', 'educore_display_mathematical_captcha' );

function educore_validate_mathematical_captcha( $user, $username, $password ) {
    $req_method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';

// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Native WP login form does not include a nonce.
if ( is_wp_error( $user ) || 'POST' !== $req_method || empty( $_POST['log'] ) ) { 
    return $user; 
}

    // phpcs:disable WordPress.Security.NonceVerification.Missing
    $user_answer = isset( $_POST['educore_captcha_answer'] ) ? sanitize_text_field( wp_unslash( $_POST['educore_captcha_answer'] ) ) : '';
    $token       = isset( $_POST['educore_captcha_token'] ) ? sanitize_text_field( wp_unslash( $_POST['educore_captcha_token'] ) ) : '';
    // phpcs:enable WordPress.Security.NonceVerification.Missing

    if ( empty( $token ) || strpos( $token, ':' ) === false ) {
        return new WP_Error( 'authentication_failed', __( '<strong>ERROR</strong>: Missing security verification token.', 'ifsedu-school-management' ) );
    }

    list( $hash, $payload ) = explode( ':', $token, 2 );
    $expected_hash = hash_hmac( 'sha256', $payload, wp_salt( 'nonce' ) );

    // Timing-attack safe hash comparison
    if ( ! hash_equals( $expected_hash, $hash ) ) {
        return new WP_Error( 'authentication_failed', __( '<strong>ERROR</strong>: Security verification failed. Please try again.', 'ifsedu-school-management' ) );
    }

    list( $correct_answer, $timestamp ) = explode( '|', $payload );

    // 10-minute token lifespan check
    if ( ( time() - intval( $timestamp ) ) > 600 ) {
        return new WP_Error( 'authentication_failed', __( '<strong>ERROR</strong>: Security question expired. Please refresh and try again.', 'ifsedu-school-management' ) );
    }

    if ( intval( $user_answer ) !== intval( $correct_answer ) ) {
        return new WP_Error( 'authentication_failed', __( '<strong>ERROR</strong>: Incorrect security verification answer.', 'ifsedu-school-management' ) );
    }

    return $user;
}
add_filter( 'authenticate', 'educore_validate_mathematical_captcha', 25, 3 );

/**
 * 14. Custom Login Redirect
 */
function educore_custom_login_redirect( $redirect_to, $request, $user ) {
    if ( isset( $user->roles ) && is_array( $user->roles ) ) {
        return admin_url( 'admin.php?page=school_management_system&tab=dashboard' );
    }
    return $redirect_to;
}
add_filter( 'login_redirect', 'educore_custom_login_redirect', 10, 3 );

/**
 * 15. Restrict Non-Admin Users & Lock Custom Roles to School ERP
 */
function educore_restrict_backend_access() {
    if ( ! is_user_logged_in() ) {
        return;
    }

    // Allow full access to true administrators
    if ( current_user_can( 'manage_options' ) ) {
        return;
    }

    global $pagenow;
    
    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $is_erp_page = isset( $_GET['page'] ) && strpos( sanitize_text_field( wp_unslash( $_GET['page'] ) ), 'school_management_system' ) !== false;
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    $is_allowed_script = in_array( $pagenow, array( 'admin-ajax.php', 'admin-post.php', 'async-upload.php' ), true );

    // If accessing standard WordPress admin screens, redirect directly to ERP Dashboard
    if ( ! $is_erp_page && ! $is_allowed_script ) {
        wp_safe_redirect( admin_url( 'admin.php?page=school_management_system&tab=dashboard' ) );
        exit;
    }
}
add_action( 'admin_init', 'educore_restrict_backend_access' );

/**
 * 16. Hide Default WordPress Admin Menu Items for Non-Administrators
 */
function educore_hide_default_wp_admin_menus() {
    if ( ! current_user_can( 'manage_options' ) ) {
        remove_menu_page( 'index.php' );                    // Dashboard
        remove_menu_page( 'edit.php' );                     // Posts
        remove_menu_page( 'upload.php' );                   // Media
        remove_menu_page( 'edit.php?post_type=page' );    // Pages
        remove_menu_page( 'edit-comments.php' );            // Comments
        remove_menu_page( 'themes.php' );                   // Appearance
        remove_menu_page( 'plugins.php' );                  // Plugins
        remove_menu_page( 'users.php' );                    // Users
        remove_menu_page( 'tools.php' );                    // Tools
        remove_menu_page( 'options-general.php' );        // Settings
    }
}
add_action( 'admin_menu', 'educore_hide_default_wp_admin_menus', 999 );
<?php
/**
 * Master Attendance Module Loader
 * File: inc/attendance.php
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

$educore_attendance_dir = plugin_dir_path( __FILE__ ) . 'attendance/';

$educore_attendance_files = array(
    'attendance-ajax.php',
    'attendance-daily.php',
    'attendance-monthly.php',
    'attendance-reports.php',
    'attendance-tab.php',
);

foreach ( $educore_attendance_files as $educore_file ) {
    $educore_file_path = $educore_attendance_dir . $educore_file;
    if ( file_exists( $educore_file_path ) ) {
        require_once $educore_file_path;
    } else {
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log( 'EduCore Attendance Error: Missing file ' . $educore_file_path );
    }
}
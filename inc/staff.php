<?php
/**
 * Master Staff & HR Module Loader
 * File: inc/staff.php
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

$educore_staff_dir = plugin_dir_path( __FILE__ ) . 'staff/';

$educore_staff_files = array(
    'staff-tabs.php',
    'staff-list.php',
    'staff-add-edit.php',
    'staff-delete.php',
    'staff-view.php',
    'staff-card.php',
);

foreach ( $educore_staff_files as $educore_file ) {
    $educore_file_path = $educore_staff_dir . $educore_file;
    if ( file_exists( $educore_file_path ) ) {
        require_once $educore_file_path;
    } else {
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log( 'EduCore Staff Error: Missing file ' . $educore_file_path );
    }
}
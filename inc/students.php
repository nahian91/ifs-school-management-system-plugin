<?php
/**
 * Master Students & Admissions Module Loader
 * File: inc/students.php
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

$educore_students_dir = plugin_dir_path( __FILE__ ) . 'students/';

$educore_student_files = array(
    'student-delete.php',
    'student-tabs.php',
    'student-list.php',
    'student-add-edit.php',
    'student-view.php',
    'student-admit-card.php',
    'student-id-card.php',
    'student-certificate.php',
    'student-promotion.php',
);

foreach ( $educore_student_files as $educore_file ) {
    $educore_file_path = $educore_students_dir . $educore_file;
    if ( file_exists( $educore_file_path ) ) {
        require_once $educore_file_path;
    } else {
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log( 'EduCore Students Error: Missing file ' . $educore_file_path );
    }
}
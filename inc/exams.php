<?php
/**
 * Master Exams Module Loader
 * File: inc/exams.php
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

$educore_exams_file_path = plugin_dir_path( __FILE__ ) . 'exams/exams-tabs.php';

if ( file_exists( $educore_exams_file_path ) ) {
    require_once $educore_exams_file_path;
} else {
    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
    error_log( 'EduCore Exams Error: Missing file ' . $educore_exams_file_path );
}
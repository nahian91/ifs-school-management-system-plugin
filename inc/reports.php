<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Master Reports Module Loader
 * File: inc/reports.php
 */

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$educore_reports_dir = plugin_dir_path( __FILE__ ) . 'reports/';

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$educore_reports_files = array(
    'reports-finance.php',     // Financial reports view
    'reports-attendance.php',  // Attendance analytics view
    'reports-tabs.php',        // Sub-navigation router: educore_reports_tab()
);

foreach ( $educore_reports_files as $educore_file ) {
    $educore_file_path = $educore_reports_dir . $educore_file;
    if ( file_exists( $educore_file_path ) ) {
        require_once $educore_file_path;
    } else {
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log( 'EduCore Reports Error: Missing file ' . $educore_file_path );
    }
}
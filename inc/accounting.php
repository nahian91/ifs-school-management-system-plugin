<?php
/**
 * Master Accounting Module Loader
 * File: inc/accounting.php
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Direct access safety buffer
}

$educore_accounting_dir = plugin_dir_path( __FILE__ ) . 'accounting/';

$educore_accounting_files = array(
    'accounting-delete.php',
    'accounting-add-edit.php',
    'accounting-list.php',
    'accounting-view.php',
    'accounting-tab.php',
);

foreach ( $educore_accounting_files as $educore_file ) {
    $educore_file_path = $educore_accounting_dir . $educore_file;
    if ( file_exists( $educore_file_path ) ) {
        require_once $educore_file_path;
    } else {
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log( 'EduCore Accounting Error: Missing file ' . $educore_file_path );
    }
}
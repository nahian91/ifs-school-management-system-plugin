<?php
/**
 * EduCore Fee Management Module Loader
 * File: inc/fees.php
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$educore_fees_dir = plugin_dir_path( __FILE__ ) . 'fees/';

// Core Fee Views & Handlers.
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$educore_fee_files = array(
    'fees-tabs.php',
    'fees-list.php',
    'fees-collect.php',
    'fees-invoice-print.php',
    'fees-view.php',
);

foreach ( $educore_fee_files as $educore_file ) {
    $educore_file_path = $educore_fees_dir . $educore_file;
    if ( file_exists( $educore_file_path ) ) {
        require_once $educore_file_path;
    } else {
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log( 'EduCore Fees Error: Missing file ' . $educore_file_path );
    }
}
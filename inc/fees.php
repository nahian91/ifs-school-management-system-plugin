<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * EduCore Fee Management Module Loader
 */
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$educore_fees_dir = plugin_dir_path( __FILE__ ) . 'fees/';

// Core Fee Views & Handlers
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$educore_fee_files = array(
    'fees-tabs.php', 
    'fees-list.php',
    'fees-collect.php', 
    'fees-invoice-print.php', 
    'fees-settings.php', 
);

foreach ( $educore_fee_files as $educore_file ) {
    $educore_file_path = $educore_fees_dir . $educore_file;
    if ( file_exists( $educore_file_path ) ) {
        require_once $educore_file_path;
    }
}
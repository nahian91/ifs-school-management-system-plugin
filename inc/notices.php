<?php
/**
 * Master Notices & Events Module Loader
 * File: inc/notices.php
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

$educore_notices_dir = plugin_dir_path( __FILE__ ) . 'notices/';

$educore_notice_files = array(
    'notice-tab.php',
    'notice-list.php',
    'notice-add-edit.php',
    'notice-view.php',
    'notice-delete.php',
    'gallery.php',
);

foreach ( $educore_notice_files as $educore_file ) {
    $educore_file_path = $educore_notices_dir . $educore_file;
    if ( file_exists( $educore_file_path ) ) {
        require_once $educore_file_path;
    } else {
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log( 'EduCore Notices Error: Missing file ' . $educore_file_path );
    }
}
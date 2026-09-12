<?php
/**
 * Master Users & Roles Management Module Loader
 * File: inc/users.php
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

$educore_users_dir = plugin_dir_path( __FILE__ ) . 'users/';

$educore_user_files = array(
    'user-tab.php',
    'all-users.php',
    'add-user.php',
    'view-user.php',
);

foreach ( $educore_user_files as $educore_file ) {
    $educore_file_path = $educore_users_dir . $educore_file;
    if ( file_exists( $educore_file_path ) ) {
        require_once $educore_file_path;
    } else {
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log( 'EduCore Users Error: Missing file ' . $educore_file_path );
    }
}
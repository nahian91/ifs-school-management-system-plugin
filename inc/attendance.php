<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

require_once plugin_dir_path(__FILE__) . 'attendance/attendance-ajax.php';
require_once plugin_dir_path(__FILE__) . 'attendance/attendance-daily.php';
require_once plugin_dir_path(__FILE__) . 'attendance/attendance-monthly.php';
require_once plugin_dir_path(__FILE__) . 'attendance/attendance-reports.php';
require_once plugin_dir_path(__FILE__) . 'attendance/attendance-tab.php';
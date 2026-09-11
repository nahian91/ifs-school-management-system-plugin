<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Master Notices & Events Module Loader
 * File: inc/notices.php
 */

$educore_notices_dir = plugin_dir_path( __FILE__ ) . 'notices/';

require_once $educore_notices_dir . 'notice-tab.php';
require_once $educore_notices_dir . 'notice-list.php';
require_once $educore_notices_dir . 'notice-add-edit.php';
require_once $educore_notices_dir . 'notice-view.php';
require_once $educore_notices_dir . 'notice-delete.php';
require_once $educore_notices_dir . 'gallery.php';
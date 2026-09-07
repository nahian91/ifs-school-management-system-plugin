<?php
/**
 * Integrated Navigation Router Matrix for Notice, Events & Gallery
 * File: inc/notice.php (or communications tab root)
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function educore_notice_tab() {
    if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'edit_posts' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to access the communications module.', 'ifsedu-school-management' ) );
    }

    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $current_type = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : 'notice';
    $raw_sub_tab  = isset( $_GET['sub'] ) ? sanitize_key( wp_unslash( $_GET['sub'] ) ) : 'list';
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    // Validate type parameter
    if ( ! in_array( $current_type, array( 'notice', 'events', 'gallery' ), true ) ) {
        $current_type = 'notice';
    }

    $allowed_sub_tabs = array( 'list', 'add', 'edit', 'view', 'delete' );
    $sub_tab          = in_array( $raw_sub_tab, $allowed_sub_tabs, true ) ? $raw_sub_tab : 'list';

    // Dynamic Navigation URLs using add_query_arg()
    $base_admin_url = admin_url( 'admin.php' );
    $notice_url     = add_query_arg( array( 'page' => 'school_management_system', 'tab' => 'notice', 'type' => 'notice', 'sub' => 'list' ), $base_admin_url );
    $events_url     = add_query_arg( array( 'page' => 'school_management_system', 'tab' => 'notice', 'type' => 'events', 'sub' => 'list' ), $base_admin_url );
    $gallery_url    = add_query_arg( array( 'page' => 'school_management_system', 'tab' => 'notice', 'type' => 'gallery', 'sub' => 'list' ), $base_admin_url );
    ?>

    <div class="ifs-educore-communications-root">
        
        <!-- Top Sub-Navigation Menu Bar -->
        <div class="ifs-educore-nav-bento-bar">
            <div class="ifs-educore-nav-tabs-group">
                <!-- Notice Board Button -->
                <a href="<?php echo esc_url( $notice_url ); ?>" 
                   class="ifs-educore-nav-tab-item <?php echo ( 'notice' === $current_type ) ? 'ifs-educore-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-megaphone"></span> <?php esc_html_e( 'Notice Board', 'ifsedu-school-management' ); ?>
                </a>

                <!-- Academic Events Button -->
                <a href="<?php echo esc_url( $events_url ); ?>" 
                   class="ifs-educore-nav-tab-item <?php echo ( 'events' === $current_type ) ? 'ifs-educore-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-calendar-alt"></span> <?php esc_html_e( 'Academic Events', 'ifsedu-school-management' ); ?>
                </a>

                <!-- Photo Gallery Button -->
                <a href="<?php echo esc_url( $gallery_url ); ?>" 
                   class="ifs-educore-nav-tab-item <?php echo ( 'gallery' === $current_type ) ? 'ifs-educore-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-format-gallery"></span> <?php esc_html_e( 'Photo Gallery', 'ifsedu-school-management' ); ?>
                </a>
            </div>
            
            <div>
                <?php if ( 'notice' === $current_type || 'events' === $current_type ) : 
                    $add_item_url = add_query_arg( array( 'page' => 'school_management_system', 'tab' => 'notice', 'type' => $current_type, 'sub' => 'add' ), $base_admin_url );
                ?>
                    <a href="<?php echo esc_url( $add_item_url ); ?>" class="ifs-educore-btn-action-add">
                        + <?php echo ( 'events' === $current_type ) ? esc_html__( 'Add New Event', 'ifsedu-school-management' ) : esc_html__( 'Add New Notice', 'ifsedu-school-management' ); ?>
                    </a>
                <?php elseif ( 'gallery' === $current_type ) : 
                    $add_gallery_url = add_query_arg( array( 'page' => 'school_management_system', 'tab' => 'notice', 'type' => 'gallery', 'sub' => 'add' ), $base_admin_url );
                ?>
                    <a href="<?php echo esc_url( $add_gallery_url ); ?>" class="ifs-educore-btn-action-add">
                        + <?php esc_html_e( 'Create Photo Album', 'ifsedu-school-management' ); ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Dynamic Viewport Engine Container -->
        <div class="ifs-educore-viewport-container">
            <?php
            if ( 'gallery' === $current_type ) {
                if ( function_exists( 'educore_gallery_router' ) ) {
                    educore_gallery_router( $sub_tab );
                } else {
                    echo '<div class="notice notice-error"><p>' . esc_html__( 'Gallery router module is missing.', 'ifsedu-school-management' ) . '</p></div>';
                }
            } else {
                educore_notice_events_router( $current_type, $sub_tab );
            }
            ?>
        </div>
    </div>
    <?php
}

/**
 * Sub-Router for Notices & Academic Events
 */
function educore_notice_events_router( $type, $sub_tab ) {
    switch ( $sub_tab ) {
        case 'add':
        case 'edit':
            if ( function_exists( 'educore_notice_events_add_edit_view' ) ) {
                educore_notice_events_add_edit_view( $type );
            }
            break;

        case 'view':
            if ( function_exists( 'educore_notice_events_single_view' ) ) {
                educore_notice_events_single_view( $type );
            }
            break;

        case 'delete':
            if ( function_exists( 'educore_notice_events_delete_action' ) ) {
                educore_notice_events_delete_action( $type );
            }
            break;

        case 'list':
        default:
            if ( function_exists( 'educore_notice_events_list_view' ) ) {
                educore_notice_events_list_view( $type );
            }
            break;
    }
}

/**
 * Universal Safe JS/PHP Redirection Helper
 */
function educore_safe_redirect( $url ) {
    if ( ! headers_sent() ) {
        wp_safe_redirect( $url );
        exit;
    } else {
        echo '<script type="text/javascript">';
        echo 'window.location.href=' . wp_json_encode( esc_url_raw( $url ) ) . ';';
        echo '</script>';
        echo '<noscript><meta http-equiv="refresh" content="0;url=' . esc_url( $url ) . '" /></noscript>';
        exit;
    }
}
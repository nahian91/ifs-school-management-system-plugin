<?php
/**
 * Notices & Communications Module Router & Navigation Matrix
 * File: inc/notices/notice-tab.php
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Route handler alias for notices tab.
 */
function educore_notices_tab() {
    educore_notice_tab();
}

/**
 * Route handler alias for notices view.
 */
function educore_notices_view() {
    educore_notice_tab();
}

/**
 * Route handler alias for notice view.
 */
function educore_notice_view() {
    educore_notice_tab();
}

/**
 * Universal Safe JS/PHP Redirection Helper
 *
 * @param string $url Target redirection URL.
 */
if ( ! function_exists( 'educore_safe_redirect' ) ) {
    function educore_safe_redirect( $url ) {
        if ( ! headers_sent() ) {
            wp_safe_redirect( $url );
            exit;
        } else {
            echo '<script type="text/javascript">';
            echo 'window.location.href="' . esc_url_raw( $url ) . '";';
            echo '</script>';
            echo '<noscript><meta http-equiv="refresh" content="0;url=' . esc_url( $url ) . '" /></noscript>';
            exit;
        }
    }
}

/**
 * Primary Navigation Router Matrix
 */
function educore_notice_tab() {
    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $current_type = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : 'notice';
    $sub_tab      = isset( $_GET['sub'] ) ? sanitize_key( wp_unslash( $_GET['sub'] ) ) : 'list';
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    if ( ! in_array( $current_type, array( 'notice', 'events', 'gallery' ), true ) ) {
        $current_type = 'notice';
    }

    $notice_url  = admin_url( 'admin.php?page=school_management_system&tab=notices&type=notice&sub=list' );
    $events_url  = admin_url( 'admin.php?page=school_management_system&tab=notices&type=events&sub=list' );
    $gallery_url = admin_url( 'admin.php?page=school_management_system&tab=notices&type=gallery&sub=list' );
    ?>

    <style id="ifs-educore-notice-tab-styles">
        .ifs-educore-communications-root {
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #0f172a;
        }

        .ifs-educore-nav-bento-bar {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 16px 20px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            box-shadow: 0 4px 15px -3px rgba(0, 0, 0, 0.03);
        }

        .ifs-educore-nav-tabs-group {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .ifs-educore-nav-tab-item {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 18px;
            border-radius: 10px;
            font-size: 13.5px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.2s ease-in-out;
            border: 1px solid #e2e8f0;
            color: #64748b;
            background: #f8fafc;
        }

        .ifs-educore-nav-tab-item:hover {
            color: #00523c;
            background: #f0fdf4;
            border-color: #a7f3d0;
        }

        .ifs-educore-nav-tab-item .dashicons {
            font-size: 18px;
            width: 18px;
            height: 18px;
        }

        .ifs-educore-nav-tab-item.ifs-educore-tab-active {
            color: #ffffff !important;
            background: #00523c !important;
            border-color: #00523c !important;
            box-shadow: 0 4px 12px rgba(0, 106, 78, 0.2);
        }

        .ifs-educore-nav-tab-item.ifs-educore-tab-active .dashicons {
            color: #a7f3d0 !important;
        }

        .ifs-educore-btn-action-add {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            height: 38px;
            padding: 0 16px;
            background: #00523c;
            color: #ffffff;
            font-size: 13px;
            font-weight: 800;
            border-radius: 8px;
            text-decoration: none;
            box-shadow: 0 4px 12px rgba(0, 82, 60, 0.2);
            transition: all 0.2s ease;
        }

        .ifs-educore-btn-action-add:hover {
            background: #003e2d;
            color: #ffffff;
        }

        .ifs-educore-viewport-container {
            width: 100%;
        }

        @media print {
            .no-print { display: none !important; }
        }
    </style>

    <div class="ifs-educore-communications-root">
        <div class="ifs-educore-nav-bento-bar no-print">
            <div class="ifs-educore-nav-tabs-group">
                <a href="<?php echo esc_url( $notice_url ); ?>" 
                   class="ifs-educore-nav-tab-item <?php echo ( 'notice' === $current_type ) ? 'ifs-educore-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-megaphone"></span> <?php esc_html_e( 'Notice Board', 'ifsedu-school-management' ); ?>
                </a>

                <a href="<?php echo esc_url( $events_url ); ?>" 
                   class="ifs-educore-nav-tab-item <?php echo ( 'events' === $current_type ) ? 'ifs-educore-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-calendar-alt"></span> <?php esc_html_e( 'Academic Events', 'ifsedu-school-management' ); ?>
                </a>

                <a href="<?php echo esc_url( $gallery_url ); ?>" 
                   class="ifs-educore-nav-tab-item <?php echo ( 'gallery' === $current_type ) ? 'ifs-educore-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-format-gallery"></span> <?php esc_html_e( 'Photo Gallery', 'ifsedu-school-management' ); ?>
                </a>
            </div>

            <div>
                <?php if ( current_user_can( 'manage_options' ) ) : ?>
                    <?php if ( 'notice' === $current_type || 'events' === $current_type ) : ?>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=school_management_system&tab=notices&type=' . $current_type . '&sub=add' ) ); ?>" class="ifs-educore-btn-action-add">
                            + <?php echo ( 'events' === $current_type ) ? esc_html__( 'Add New Event', 'ifsedu-school-management' ) : esc_html__( 'Add New Notice', 'ifsedu-school-management' ); ?>
                        </a>
                    <?php elseif ( 'gallery' === $current_type ) : ?>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=school_management_system&tab=notices&type=' . $current_type . '&sub=add' ) ); ?>" class="ifs-educore-btn-action-add">
                            + <?php esc_html_e( 'Create Photo Album', 'ifsedu-school-management' ); ?>
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="ifs-educore-viewport-container">
            <?php
            if ( 'gallery' === $current_type ) {
                educore_gallery_router( $sub_tab );
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
 *
 * @param string $type    Current content type (notice/events).
 * @param string $sub_tab Current action/sub view.
 */
function educore_notice_events_router( $type, $sub_tab ) {
    switch ( $sub_tab ) {
        case 'add':
        case 'edit':
            educore_notice_events_add_edit_view( $type );
            break;

        case 'view':
            educore_notice_events_single_view( $type );
            break;

        case 'delete':
            educore_notice_events_delete_action( $type );
            break;

        case 'list':
        default:
            educore_notice_events_list_view( $type );
            break;
    }
}
<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Route handler aliases
 */
function educore_notices_tab() {
    educore_notice_tab();
}

function educore_notices_view() {
    educore_notice_tab();
}

function educore_notice_view() {
    educore_notice_tab();
}

/**
 * Universal Safe JS/PHP Redirection Helper
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

    <div class="ifs-educore-communications-root">
        <div class="ifs-educore-nav-bento-bar no-print">
            <div class="ifs-educore-nav-tabs-group">
                <a href="<?php echo esc_url( $notice_url ); ?>" 
                   class="ifs-educore-nav-tab-item <?php echo ( $current_type === 'notice' ) ? 'ifs-educore-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-megaphone"></span> <?php esc_html_e( 'Notice Board', 'ifsedu-school-management' ); ?>
                </a>

                <a href="<?php echo esc_url( $events_url ); ?>" 
                   class="ifs-educore-nav-tab-item <?php echo ( $current_type === 'events' ) ? 'ifs-educore-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-calendar-alt"></span> <?php esc_html_e( 'Academic Events', 'ifsedu-school-management' ); ?>
                </a>

                <a href="<?php echo esc_url( $gallery_url ); ?>" 
                   class="ifs-educore-nav-tab-item <?php echo ( $current_type === 'gallery' ) ? 'ifs-educore-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-format-gallery"></span> <?php esc_html_e( 'Photo Gallery', 'ifsedu-school-management' ); ?>
                </a>
            </div>

            <div>
                <?php if ( current_user_can( 'manage_options' ) ) : ?>
                    <?php if ( $current_type === 'notice' || $current_type === 'events' ) : ?>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=school_management_system&tab=notices&type=' . $current_type . '&sub=add' ) ); ?>" class="ifs-educore-btn-action-add">
                            + <?php echo ( $current_type === 'events' ) ? esc_html__( 'Add New Event', 'ifsedu-school-management' ) : esc_html__( 'Add New Notice', 'ifsedu-school-management' ); ?>
                        </a>
                    <?php elseif ( $current_type === 'gallery' ) : ?>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=school_management_system&tab=notices&type=' . $current_type . '&sub=add' ) ); ?>" class="ifs-educore-btn-action-add">
                            + <?php esc_html_e( 'Create Photo Album', 'ifsedu-school-management' ); ?>
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="ifs-educore-viewport-container">
            <?php
            if ( $current_type === 'gallery' ) {
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
<?php
/**
 * Single Detail View Page for Notices & Events
 * File: inc/notices/notice-events-single.php
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function educore_notice_events_single_view( $type = 'notice' ) {
    global $wpdb;
    $table_notices = $wpdb->prefix . 'sms_notices';

    if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'edit_posts' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to view notices or events.', 'ifsedu-school-management' ) );
    }

    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $id = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0;
    // phpcs:enable WordPress.Security.NonceVerification.Recommended
    
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $item = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table_notices}` WHERE id = %d LIMIT 1", $id ) );
    // phpcs:enable

    $base_admin = admin_url( 'admin.php' );
    $back_url   = add_query_arg(
        array(
            'page' => 'school_management_system',
            'tab'  => 'notices',
            'type' => ( 'events' === $type || 'event' === $type ) ? 'events' : 'notice',
            'sub'  => 'list',
        ),
        $base_admin
    );

    if ( ! $item ) {
        ?>
        <style>
            .ifs-educore-alert-error {
                background: #fef2f2;
                border: 1px solid #fecaca;
                color: #b91c1c;
                padding: 16px 20px;
                border-radius: 12px;
                font-weight: 700;
                font-size: 14px;
                display: flex;
                align-items: center;
                gap: 10px;
                margin-top: 20px;
            }
        </style>
        <div class="ifs-educore-alert-error">
            <span class="dashicons dashicons-dismiss"></span>
            <?php esc_html_e( 'Record not found or has been deleted.', 'ifsedu-school-management' ); ?>
        </div>
        <?php
        return;
    }

    // Dynamic Date Processing
    $raw_date     = ( ! empty( $item->event_date ) && '1970-01-01' !== $item->event_date ) ? $item->event_date : $item->created_at;
    $date_ts      = ! empty( $raw_date ) ? strtotime( $raw_date ) : false;
    $display_date = $date_ts ? date_i18n( 'F j, Y', $date_ts ) : '—';

    // Dynamic Badge Styling Logic
    $priority_class = 'ifs-educore-priority-normal';
    if ( isset( $item->priority ) && 'High' === $item->priority ) {
        $priority_class = 'ifs-educore-priority-high';
    } elseif ( isset( $item->priority ) && 'Urgent' === $item->priority ) {
        $priority_class = 'ifs-educore-priority-urgent';
    }

    $status_class   = ( isset( $item->status ) && 'Published' === $item->status ) ? 'ifs-educore-status-published' : 'ifs-educore-status-draft';
    $featured_image = isset( $item->featured_image ) ? $item->featured_image : '';
    ?>

    <div class="ifs-educore-single-root">
        
        <!-- Navigation Back Action -->
        <div class="ifs-educore-top-action-bar">
            <a href="<?php echo esc_url( $back_url ); ?>" class="ifs-educore-btn-back">
                <span class="dashicons dashicons-arrow-left-alt"></span>
                <?php esc_html_e( 'Back to Directory', 'ifsedu-school-management' ); ?>
            </a>
        </div>

        <!-- Main Detail Bento Card -->
        <div class="ifs-educore-single-bento-card">
            
            <!-- Header Section -->
            <div class="ifs-educore-single-header">
                <div>
                    <h2 class="ifs-educore-single-title"><?php echo esc_html( $item->title ); ?></h2>
                </div>
                <div>
                    <span class="ifs-educore-badge-node <?php echo esc_attr( $status_class ); ?>">
                        <?php echo esc_html( isset( $item->status ) ? $item->status : 'Published' ); ?>
                    </span>
                </div>
            </div>

            <!-- Hero Featured Image Banner Node (if available) -->
            <?php if ( ! empty( $featured_image ) ) : ?>
                <div class="ifs-educore-hero-featured-banner">
                    <a href="<?php echo esc_url( $featured_image ); ?>" target="_blank" title="<?php esc_attr_e( 'View Full Image', 'ifsedu-school-management' ); ?>">
                        <img src="<?php echo esc_url( $featured_image ); ?>" alt="<?php echo esc_attr( $item->title ); ?>">
                    </a>
                </div>
            <?php endif; ?>

            <!-- Metadata Metrics Grid -->
            <div class="ifs-educore-meta-grid">
                <div class="ifs-educore-meta-item">
                    <span class="ifs-educore-meta-label"><?php esc_html_e( 'Category Type', 'ifsedu-school-management' ); ?></span>
                    <span class="ifs-educore-meta-value">
                        <span class="dashicons dashicons-tag" style="color: #64748b; font-size:16px;"></span>
                        <?php echo esc_html( isset( $item->notice_type ) ? $item->notice_type : ( isset( $item->category ) ? $item->category : 'General' ) ); ?>
                    </span>
                </div>

                <div class="ifs-educore-meta-item">
                    <span class="ifs-educore-meta-label"><?php esc_html_e( 'Target Audience', 'ifsedu-school-management' ); ?></span>
                    <span class="ifs-educore-meta-value">
                        <span class="dashicons dashicons-groups" style="color: #64748b; font-size:16px;"></span>
                        <?php echo esc_html( isset( $item->target_audience ) ? $item->target_audience : 'All' ); ?>
                    </span>
                </div>

                <div class="ifs-educore-meta-item">
                    <span class="ifs-educore-meta-label"><?php esc_html_e( 'Priority Level', 'ifsedu-school-management' ); ?></span>
                    <span class="ifs-educore-meta-value">
                        <span class="ifs-educore-badge-node <?php echo esc_attr( $priority_class ); ?>">
                            <?php echo esc_html( isset( $item->priority ) ? $item->priority : 'Normal' ); ?>
                        </span>
                    </span>
                </div>

                <div class="ifs-educore-meta-item">
                    <span class="ifs-educore-meta-label">
                        <?php echo ( 'events' === $type ) ? esc_html__( 'Event Date', 'ifsedu-school-management' ) : esc_html__( 'Published Date', 'ifsedu-school-management' ); ?>
                    </span>
                    <span class="ifs-educore-meta-value">
                        <span class="dashicons dashicons-calendar-alt" style="color: #00523c; font-size:16px;"></span>
                        <?php echo esc_html( $display_date ); ?>
                    </span>
                </div>
            </div>

            <!-- Rich Description Viewport -->
            <div class="ifs-educore-content-body">
                <?php 
                $content_val = isset( $item->description ) ? $item->description : ( isset( $item->content ) ? $item->content : '' );
                echo wp_kses_post( $content_val ); 
                ?>
            </div>

            <!-- Attachment Node (if present) -->
            <?php if ( ! empty( $item->attachment_url ) ) : ?>
                <div class="ifs-educore-attachment-card">
                    <div class="ifs-educore-attachment-info">
                        <span class="dashicons dashicons-paperclip"></span>
                        <?php esc_html_e( 'Official Attached Document / File Available', 'ifsedu-school-management' ); ?>
                    </div>
                    <a href="<?php echo esc_url( $item->attachment_url ); ?>" target="_blank" class="ifs-educore-btn-download">
                        <span class="dashicons dashicons-download"></span>
                        <?php esc_html_e( 'View / Download Attachment', 'ifsedu-school-management' ); ?>
                    </a>
                </div>
            <?php endif; ?>

        </div>

    </div>
    <?php
}
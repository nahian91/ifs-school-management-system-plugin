<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Single Notice / Event Detail View
 */
function educore_notice_events_single_view( $type = 'notice' ) {
    global $wpdb;
    $table_notices = $wpdb->prefix . 'sms_notices';

    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $item = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table_notices}` WHERE id = %d", $id ) );
    // phpcs:enable

    $back_url = admin_url( 'admin.php?page=school_management_system&tab=notices&type=' . $type . '&sub=list' );

    if ( ! $item ) {
        ?>
        <div class="ifs-educore-not-found-box">
            <span class="dashicons dashicons-dismiss"></span>
            <?php esc_html_e( 'Record not found or has been deleted.', 'ifsedu-school-management' ); ?>
        </div>
        <?php
        return;
    }

    $display_date = ( ! empty( $item->event_date ) && $item->event_date !== '1970-01-01' ) 
        ? date_i18n( 'F j, Y', strtotime( $item->event_date ) ) 
        : date_i18n( 'F j, Y', strtotime( ! empty( $item->publish_date ) ? $item->publish_date : $item->created_at ) );

    $priority_class = 'ifs-educore-priority-normal';
    if ( $item->priority === 'High' ) {
        $priority_class = 'ifs-educore-priority-high';
    } elseif ( $item->priority === 'Urgent' ) {
        $priority_class = 'ifs-educore-priority-urgent';
    }

    $status_class   = ( $item->status === 'Published' ) ? 'ifs-educore-status-published' : 'ifs-educore-status-draft';
    $featured_image = ! empty( $item->featured_image ) ? $item->featured_image : '';
    ?>

    <div class="ifs-educore-single-root">
        <div class="ifs-educore-top-action-bar">
            <a href="<?php echo esc_url( $back_url ); ?>" class="ifs-educore-btn-back">
                <span class="dashicons dashicons-arrow-left-alt"></span>
                <?php esc_html_e( 'Back to Directory', 'ifsedu-school-management' ); ?>
            </a>
        </div>

        <div class="ifs-educore-single-bento-card">
            <div class="ifs-educore-single-header">
                <div>
                    <h2 class="ifs-educore-single-title"><?php echo esc_html( $item->title ); ?></h2>
                </div>
                <div>
                    <span class="ifs-educore-badge-node <?php echo esc_attr( $status_class ); ?>">
                        <?php echo esc_html( $item->status ); ?>
                    </span>
                </div>
            </div>

            <?php if ( ! empty( $featured_image ) ) : ?>
                <div class="ifs-educore-hero-featured-banner">
                    <a href="<?php echo esc_url( $featured_image ); ?>" target="_blank" title="<?php esc_attr_e( 'View Full Image', 'ifsedu-school-management' ); ?>">
                        <img src="<?php echo esc_url( $featured_image ); ?>" alt="<?php echo esc_attr( $item->title ); ?>">
                    </a>
                </div>
            <?php endif; ?>

            <div class="ifs-educore-meta-grid">
                <div class="ifs-educore-meta-item">
                    <span class="ifs-educore-meta-label"><?php esc_html_e( 'Category Type', 'ifsedu-school-management' ); ?></span>
                    <span class="ifs-educore-meta-value">
                        <span class="dashicons dashicons-tag ifs-educore-meta-icon"></span>
                        <?php echo esc_html( $item->notice_type ?? $item->category ); ?>
                    </span>
                </div>

                <div class="ifs-educore-meta-item">
                    <span class="ifs-educore-meta-label"><?php esc_html_e( 'Target Audience', 'ifsedu-school-management' ); ?></span>
                    <span class="ifs-educore-meta-value">
                        <span class="dashicons dashicons-groups ifs-educore-meta-icon"></span>
                        <?php echo esc_html( $item->target_audience ); ?>
                    </span>
                </div>

                <div class="ifs-educore-meta-item">
                    <span class="ifs-educore-meta-label"><?php esc_html_e( 'Priority Level', 'ifsedu-school-management' ); ?></span>
                    <span class="ifs-educore-meta-value">
                        <span class="ifs-educore-badge-node <?php echo esc_attr( $priority_class ); ?>">
                            <?php echo esc_html( $item->priority ); ?>
                        </span>
                    </span>
                </div>

                <div class="ifs-educore-meta-item">
                    <span class="ifs-educore-meta-label">
                        <?php echo ( $type === 'events' ) ? esc_html__( 'Event Date', 'ifsedu-school-management' ) : esc_html__( 'Published Date', 'ifsedu-school-management' ); ?>
                    </span>
                    <span class="ifs-educore-meta-value">
                        <span class="dashicons dashicons-calendar-alt ifs-educore-date-icon"></span>
                        <?php echo esc_html( $display_date ); ?>
                    </span>
                </div>
            </div>

            <div class="ifs-educore-content-body">
                <?php echo wp_kses_post( ! empty( $item->description ) ? $item->description : $item->content ); ?>
            </div>

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
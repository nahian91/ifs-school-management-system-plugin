<?php
/**
 * Single Notice / Event Detail View
 * File: inc/notices/notice-view.php
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Render Single Notice or Event Detail View
 *
 * @param string $type Content type ('notice' or 'events').
 */
function educore_notice_events_single_view( $type = 'notice' ) {
    global $wpdb;
    $table_notices = $wpdb->prefix . 'sms_notices';

    $is_admin = current_user_can( 'manage_options' );
    $is_staff = class_exists( 'IFSEdu_School_Management_System' )
        ? IFSEdu_School_Management_System::has_access( array( 'teacher', 'instructor', 'staff' ) )
        : current_user_can( 'edit_posts' );

    if ( ! $is_admin && ! $is_staff ) {
        wp_die( esc_html__( 'You do not have permission to access this module.', 'ifsedu-school-management' ) );
    }

    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $item = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table_notices}` WHERE id = %d", $id ) );
    // phpcs:enable

    $back_url = admin_url( 'admin.php?page=school_management_system&tab=notices&type=' . $type . '&sub=list' );

    if ( ! $item ) {
        ?>
        <style id="ifs-educore-single-view-error-styles">
            .ifs-educore-not-found-box {
                background: #fef2f2;
                border: 1px solid #fecaca;
                color: #dc2626;
                padding: 20px;
                border-radius: 12px;
                font-weight: 700;
                display: flex;
                align-items: center;
                gap: 10px;
                margin-top: 20px;
                font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            }
        </style>
        <div class="ifs-educore-not-found-box">
            <span class="dashicons dashicons-dismiss"></span>
            <?php esc_html_e( 'Record not found or has been deleted.', 'ifsedu-school-management' ); ?>
        </div>
        <?php
        return;
    }

    $display_date = ( ! empty( $item->event_date ) && '1970-01-01' !== $item->event_date && '0000-00-00' !== $item->event_date ) 
        ? date_i18n( 'F j, Y', strtotime( $item->event_date ) ) 
        : date_i18n( 'F j, Y', strtotime( ! empty( $item->publish_date ) ? $item->publish_date : $item->created_at ) );

    $priority_class = 'ifs-educore-priority-normal';
    if ( 'High' === $item->priority ) {
        $priority_class = 'ifs-educore-priority-high';
    } elseif ( 'Urgent' === $item->priority ) {
        $priority_class = 'ifs-educore-priority-urgent';
    }

    $status_class   = ( 'Published' === $item->status ) ? 'ifs-educore-status-published' : 'ifs-educore-status-draft';
    $featured_image = ! empty( $item->featured_image ) ? $item->featured_image : '';
    ?>

    <style id="ifs-educore-single-view-styles">
        .ifs-educore-single-root {
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #0f172a;
        }

        .ifs-educore-top-action-bar {
            margin-bottom: 20px;
        }

        .ifs-educore-btn-back {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            height: 38px;
            padding: 0 16px;
            background: #ffffff;
            color: #475569;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .ifs-educore-btn-back:hover {
            background: #f1f5f9;
            color: #0f172a;
        }

        .ifs-educore-single-bento-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.03);
            margin-bottom: 30px;
        }

        .ifs-educore-single-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 24px;
            border-bottom: 2px solid #f1f5f9;
            padding-bottom: 16px;
            flex-wrap: wrap;
        }

        .ifs-educore-single-title {
            margin: 0;
            font-size: 22px;
            font-weight: 800;
            color: #00523c;
        }

        .ifs-educore-badge-node {
            display: inline-flex;
            align-items: center;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
        }

        .ifs-educore-status-published { background: #ecfdf5; color: #059669; border: 1px solid #bbf7d0; }
        .ifs-educore-status-draft { background: #f1f5f9; color: #64748b; border: 1px solid #cbd5e1; }
        .ifs-educore-priority-normal { background: #f8fafc; color: #475569; border: 1px solid #cbd5e1; }
        .ifs-educore-priority-high { background: #fff7ed; color: #d97706; border: 1px solid #fed7aa; }
        .ifs-educore-priority-urgent { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }

        .ifs-educore-hero-featured-banner {
            width: 100%;
            max-height: 350px;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
            margin-bottom: 24px;
            background: #f8fafc;
        }

        .ifs-educore-hero-featured-banner img {
            width: 100%;
            height: 100%;
            max-height: 350px;
            object-fit: cover;
            display: block;
        }

        .ifs-educore-meta-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 24px;
        }

        .ifs-educore-meta-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .ifs-educore-meta-label {
            font-size: 11.5px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .ifs-educore-meta-value {
            font-size: 13.5px;
            font-weight: 700;
            color: #0f172a;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .ifs-educore-meta-icon,
        .ifs-educore-date-icon {
            font-size: 16px;
            width: 16px;
            height: 16px;
            color: #00523c;
        }

        .ifs-educore-content-body {
            font-size: 14.5px;
            line-height: 1.8;
            color: #334155;
            margin-bottom: 30px;
        }

        .ifs-educore-content-body p {
            margin-top: 0;
            margin-bottom: 1em;
        }

        .ifs-educore-attachment-card {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 12px;
            padding: 16px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }

        .ifs-educore-attachment-info {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 13.5px;
            font-weight: 700;
            color: #065f46;
        }

        .ifs-educore-attachment-info .dashicons {
            font-size: 18px;
            width: 18px;
            height: 18px;
        }

        .ifs-educore-btn-download {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            height: 38px;
            padding: 0 16px;
            background: #00523c;
            color: #ffffff;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            box-shadow: 0 2px 6px rgba(0,82,60,0.15);
            transition: background 0.2s ease;
        }

        .ifs-educore-btn-download:hover {
            background: #047857;
            color: #ffffff;
        }

        @media print {
            .no-print { display: none !important; }
            body, .ifs-educore-single-root { background: #ffffff !important; padding: 0 !important; }
            .ifs-educore-single-bento-card { border: none !important; box-shadow: none !important; padding: 0 !important; }
        }
    </style>

    <div class="ifs-educore-single-root">
        <div class="ifs-educore-top-action-bar no-print">
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
                        <?php echo ( 'events' === $type ) ? esc_html__( 'Event Date', 'ifsedu-school-management' ) : esc_html__( 'Published Date', 'ifsedu-school-management' ); ?>
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
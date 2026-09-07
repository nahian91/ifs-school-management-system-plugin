<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Integrated Notices, Academic Events & Photo Gallery Module
 * File: inc/notices.php
 * Custom Prefixes Applied: dpt-, afdp-
 */

// Route handler alias mappings
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
 * 1. Primary Navigation Router Matrix
 */
function educore_notice_tab() {
    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $current_type = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : 'notice';
    $sub_tab      = isset( $_GET['sub'] ) ? sanitize_key( wp_unslash( $_GET['sub'] ) ) : 'list';
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    if ( ! in_array( $current_type, array( 'notice', 'events', 'gallery' ), true ) ) {
        $current_type = 'notice';
    }

    // Dynamic Navigation URLs matching your menu router
    $notice_url  = admin_url( 'admin.php?page=school_management_system&tab=notices&type=notice&sub=list' );
    $events_url  = admin_url( 'admin.php?page=school_management_system&tab=notices&type=events&sub=list' );
    $gallery_url = admin_url( 'admin.php?page=school_management_system&tab=notices&type=gallery&sub=list' );
    ?>

    <style>
        .dpt-communications-root {
            margin: 20px 20px 24px 0;
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #0f172a;
        }

        .afdp-nav-bento-bar {
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

        .dpt-nav-tabs-group {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .dpt-nav-tab-item {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 18px;
            border-radius: 10px;
            font-size: 13.5px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.2s ease-in-out;
            color: #64748b;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
        }

        .dpt-nav-tab-item .dashicons {
            font-size: 18px;
            width: 18px;
            height: 18px;
            color: #64748b;
        }

        .dpt-nav-tab-item:hover {
            color: #00523c;
            background: #f0fdf4;
            border-color: #a7f3d0;
        }

        .dpt-nav-tab-item:hover .dashicons { color: #00523c; }

        .dpt-nav-tab-item.dpt-tab-active {
            color: #ffffff !important;
            background: #00523c !important;
            border-color: #00523c !important;
            box-shadow: 0 4px 12px rgba(0, 106, 78, 0.2);
        }

        .dpt-nav-tab-item.dpt-tab-active .dashicons {
            color: #a7f3d0 !important;
        }

        .dpt-btn-action-add {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 9px 20px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            color: #00523c;
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            transition: all 0.2s ease-in-out;
        }

        .dpt-btn-action-add:hover {
            color: #ffffff;
            background: #00523c;
            border-color: #00523c;
            box-shadow: 0 4px 12px rgba(0, 106, 78, 0.25);
            transform: translateY(-1px);
        }

        .dpt-viewport-container {
            width: 100%;
        }

        @media print {
            .no-print, .afdp-nav-bento-bar { display: none !important; }
        }
    </style>

    <div class="dpt-communications-root">
        
        <!-- Top Sub-Navigation Menu Bar -->
        <div class="afdp-nav-bento-bar no-print">
            <div class="dpt-nav-tabs-group">
                <a href="<?php echo esc_url( $notice_url ); ?>" 
                   class="dpt-nav-tab-item <?php echo ( $current_type === 'notice' ) ? 'dpt-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-megaphone"></span> <?php esc_html_e( 'Notice Board', 'ifsedu-school-management' ); ?>
                </a>

                <a href="<?php echo esc_url( $events_url ); ?>" 
                   class="dpt-nav-tab-item <?php echo ( $current_type === 'events' ) ? 'dpt-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-calendar-alt"></span> <?php esc_html_e( 'Academic Events', 'ifsedu-school-management' ); ?>
                </a>

                <a href="<?php echo esc_url( $gallery_url ); ?>" 
                   class="dpt-nav-tab-item <?php echo ( $current_type === 'gallery' ) ? 'dpt-tab-active' : ''; ?>">
                    <span class="dashicons dashicons-format-gallery"></span> <?php esc_html_e( 'Photo Gallery', 'ifsedu-school-management' ); ?>
                </a>
            </div>
            
            <div>
                <?php if ( current_user_can( 'manage_options' ) ) : ?>
                    <?php if ( $current_type === 'notice' || $current_type === 'events' ) : ?>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=school_management_system&tab=notices&type=' . $current_type . '&sub=add' ) ); ?>" class="dpt-btn-action-add">
                            + <?php echo ( $current_type === 'events' ) ? esc_html__( 'Add New Event', 'ifsedu-school-management' ) : esc_html__( 'Add New Notice', 'ifsedu-school-management' ); ?>
                        </a>
                    <?php elseif ( $current_type === 'gallery' ) : ?>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=school_management_system&tab=notices&type=' . $current_type . '&sub=add' ) ); ?>" class="dpt-btn-action-add">
                            + <?php esc_html_e( 'Create Photo Album', 'ifsedu-school-management' ); ?>
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Viewport Render Area -->
        <div class="dpt-viewport-container">
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
 * 2. Sub-Router for Notices & Academic Events
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

/**
 * 3. Listing View (Fixed Schema Query Mapping)
 */
function educore_notice_events_list_view( $type = 'notice' ) {
    global $wpdb;
    $table_notices = $wpdb->prefix . 'sms_notices';

    $is_admin = current_user_can( 'manage_options' );
    $is_staff = class_exists( 'IFSEdu_School_Management_System' )
        ? IFSEdu_School_Management_System::has_access( array( 'teacher', 'instructor', 'staff' ) )
        : current_user_can( 'edit_posts' );

    if ( ! $is_admin && ! $is_staff ) {
        wp_die( esc_html__( 'You do not have permission to access this module.', 'ifsedu-school-management' ) );
    }

    $is_event_mode = ( $type === 'events' || $type === 'event' );
    $type_slug     = $is_event_mode ? 'events' : 'notice';

    // Universal SQL query covering notice_type, item_type and type columns
    if ( $is_event_mode ) {
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $records = $wpdb->get_results( 
            $wpdb->prepare(
                "SELECT * FROM `{$table_notices}` 
                 WHERE LOWER(notice_type) IN (%s, %s) 
                    OR LOWER(item_type) IN (%s, %s) 
                 ORDER BY id DESC",
                'event',
                'events',
                'event',
                'events'
            )
        );
        // phpcs:enable
    } else {
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $records = $wpdb->get_results( 
            $wpdb->prepare(
                "SELECT * FROM `{$table_notices}` 
                 WHERE LOWER(notice_type) NOT IN (%s, %s) 
                    OR notice_type IS NULL 
                    OR notice_type = '' 
                 ORDER BY id DESC",
                'event',
                'events'
            )
        );
        // phpcs:enable
    }

    $records = is_array( $records ) ? $records : array();
    ?>

    <style>
        .dpt-list-root {
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #0f172a;
        }

        .dpt-bento-card-table {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.03);
        }

        .afdp-table-header {
            border-bottom: 2px solid #f1f5f9;
            padding-bottom: 16px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }

        .afdp-table-title {
            font-size: 18px;
            font-weight: 800;
            color: #00523c;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
            letter-spacing: -0.3px;
        }

        .afdp-table-title .dashicons {
            font-size: 20px;
            width: 20px;
            height: 20px;
        }

        .dpt-responsive-datatable {
            width: 100%;
            overflow-x: auto;
        }

        .dpt-architecture-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 13.5px;
        }

        .dpt-architecture-table th {
            background: #f8fafc;
            color: #475569;
            font-weight: 700;
            padding: 14px 16px;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
            white-space: nowrap;
            font-size: 11.5px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .dpt-architecture-table td {
            padding: 16px;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
            vertical-align: middle;
        }

        .dpt-architecture-table tbody tr:hover td {
            background-color: #f8fafc;
        }

        .dpt-thumb-container {
            width: 44px;
            height: 44px;
            border-radius: 8px;
            overflow: hidden;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .dpt-thumb-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.2s ease;
        }

        .dpt-thumb-img:hover {
            transform: scale(1.1);
        }

        .dpt-thumb-placeholder {
            color: #94a3b8;
            font-size: 20px;
            width: 20px;
            height: 20px;
        }

        .dpt-badge-node {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11.5px;
            font-weight: 700;
            letter-spacing: 0.2px;
            text-transform: uppercase;
        }

        .dpt-badge-audience { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .dpt-priority-normal { background: #f0f9ff; color: #0369a1; border: 1px solid #bae6fd; }
        .dpt-priority-high   { background: #fff7ed; color: #c2410c; border: 1px solid #fed7aa; }
        .dpt-priority-urgent { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
        .dpt-status-published { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
        .dpt-status-draft     { background: #f1f5f9; color: #64748b; border: 1px solid #cbd5e1; }

        .dpt-actions-flex {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 6px;
        }

        .dpt-square-btn {
            width: 32px;
            height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.2s ease;
            border: 1px solid transparent;
            cursor: pointer;
        }

        .dpt-square-btn .dashicons {
            font-size: 16px;
            width: 16px;
            height: 16px;
        }

        .dpt-btn-view { background: #f0f9ff; color: #0284c7; border-color: #bae6fd; }
        .dpt-btn-view:hover { background: #0284c7; color: #ffffff; }

        .dpt-btn-edit { background: #f0fdf4; color: #16a34a; border-color: #bbf7d0; }
        .dpt-btn-edit:hover { background: #16a34a; color: #ffffff; }

        .dpt-btn-delete { background: #fef2f2; color: #dc2626; border-color: #fecaca; }
        .dpt-btn-delete:hover { background: #dc2626; color: #ffffff; }

        .dpt-attachment-link {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            margin-top: 4px;
            font-size: 11.5px;
            color: #00523c;
            text-decoration: none;
            font-weight: 600;
        }

        .dpt-attachment-link:hover {
            text-decoration: underline;
        }
    </style>

    <div class="dpt-list-root">
        <div class="dpt-bento-card-table">
            
            <div class="afdp-table-header">
                <h3 class="afdp-table-title">
                    <span class="dashicons <?php echo $is_event_mode ? 'dashicons-calendar-alt' : 'dashicons-megaphone'; ?>"></span>
                    <?php echo $is_event_mode ? esc_html__( 'Academic Events Directory', 'ifsedu-school-management' ) : esc_html__( 'Official Notice Board', 'ifsedu-school-management' ); ?>
                </h3>
                <span style="background:#f1f5f9; color:#475569; padding:4px 12px; border-radius:20px; font-size:12px; font-weight:700;">
                    <?php echo esc_html( count( $records ) ); ?> <?php echo $is_event_mode ? esc_html__( 'Events', 'ifsedu-school-management' ) : esc_html__( 'Notices', 'ifsedu-school-management' ); ?>
                </span>
            </div>

            <div class="dpt-responsive-datatable">
                <table class="dpt-architecture-table educore-datatable">
                    <thead>
                        <tr>
                            <th style="width: 50px;">ID</th>
                            <th style="width: 60px;"><?php esc_html_e( 'Banner', 'ifsedu-school-management' ); ?></th>
                            <th><?php esc_html_e( 'Title & Details', 'ifsedu-school-management' ); ?></th>
                            <th><?php esc_html_e( 'Target Audience', 'ifsedu-school-management' ); ?></th>
                            <th><?php echo $is_event_mode ? esc_html__( 'Event Date', 'ifsedu-school-management' ) : esc_html__( 'Publish Date', 'ifsedu-school-management' ); ?></th>
                            <th><?php esc_html_e( 'Category / Priority', 'ifsedu-school-management' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'ifsedu-school-management' ); ?></th>
                            <th style="text-align: right; width: 120px;"><?php esc_html_e( 'Actions', 'ifsedu-school-management' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( ! empty( $records ) ) : ?>
                            <?php foreach ( $records as $row ) : 
                                $id         = absint( $row->id );
                                $view_url   = admin_url( 'admin.php?page=school_management_system&tab=notices&type=' . $type_slug . '&sub=view&id=' . $id );
                                $edit_url   = admin_url( 'admin.php?page=school_management_system&tab=notices&type=' . $type_slug . '&sub=edit&id=' . $id );
                                $delete_url = wp_nonce_url( 
                                    admin_url( 'admin.php?page=school_management_system&tab=notices&type=' . $type_slug . '&sub=delete&id=' . $id ), 
                                    'delete_item_' . $id 
                                );

                                // Resolve display date safely
                                $raw_date = ! empty( $row->event_date ) && $row->event_date !== '1970-01-01' && $row->event_date !== '0000-00-00'
                                    ? $row->event_date 
                                    : ( ! empty( $row->publish_date ) ? $row->publish_date : $row->created_at );
                                $display_date = date_i18n( 'd M Y', strtotime( $raw_date ) );

                                // Priority resolution
                                $priority_label = ! empty( $row->priority ) ? $row->priority : 'Normal';
                                $priority_class = 'dpt-priority-normal';
                                if ( $priority_label === 'High' ) {
                                    $priority_class = 'dpt-priority-high';
                                } elseif ( $priority_label === 'Urgent' ) {
                                    $priority_class = 'dpt-priority-urgent';
                                }

                                $status_val     = ! empty( $row->status ) ? $row->status : 'Published';
                                $status_class   = ( $status_val === 'Published' ) ? 'dpt-status-published' : 'dpt-status-draft';
                                $featured_image = ! empty( $row->featured_image ) ? $row->featured_image : '';
                            ?>
                            <tr>
                                <td style="font-weight: 700; color: #64748b;">#<?php echo esc_html( $id ); ?></td>
                                
                                <td>
                                    <div class="dpt-thumb-container">
                                        <?php if ( ! empty( $featured_image ) ) : ?>
                                            <a href="<?php echo esc_url( $featured_image ); ?>" target="_blank" title="<?php esc_attr_e( 'View Full Image', 'ifsedu-school-management' ); ?>">
                                                <img src="<?php echo esc_url( $featured_image ); ?>" class="dpt-thumb-img" alt="Banner">
                                            </a>
                                        <?php else : ?>
                                            <span class="dashicons dashicons-format-image dpt-thumb-placeholder"></span>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <td>
                                    <strong style="color: #0f172a; display: block; font-size: 14px;"><?php echo esc_html( $row->title ); ?></strong>
                                    <?php if ( ! empty( $row->notice_type ) && $row->notice_type !== 'Notice' && $row->notice_type !== 'Event' ) : ?>
                                        <small style="color: #00523c; font-weight: 700;">[<?php echo esc_html( $row->notice_type ); ?>]</small>
                                    <?php endif; ?>
                                    <?php if ( ! empty( $row->attachment_url ) ) : ?>
                                        <br>
                                        <a href="<?php echo esc_url( $row->attachment_url ); ?>" target="_blank" class="dpt-attachment-link">
                                            <span class="dashicons dashicons-paperclip"></span>
                                            <?php esc_html_e( 'Attachment Available', 'ifsedu-school-management' ); ?>
                                        </a>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <span class="dpt-badge-node dpt-badge-audience">
                                        <?php echo esc_html( ! empty( $row->target_audience ) ? $row->target_audience : 'All' ); ?>
                                    </span>
                                </td>

                                <td style="font-weight: 600; color: #334155;"><?php echo esc_html( $display_date ); ?></td>

                                <td>
                                    <span class="dpt-badge-node <?php echo esc_attr( $priority_class ); ?>">
                                        <?php echo esc_html( $priority_label ); ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="dpt-badge-node <?php echo esc_attr( $status_class ); ?>">
                                        <?php echo esc_html( $status_val ); ?>
                                    </span>
                                </td>

                                <td style="text-align: right;">
                                    <div class="dpt-actions-flex">
                                        <a href="<?php echo esc_url( $view_url ); ?>" class="dpt-square-btn dpt-btn-view" title="<?php esc_attr_e( 'View', 'ifsedu-school-management' ); ?>">
                                            <span class="dashicons dashicons-visibility"></span>
                                        </a>
                                        <?php if ( $is_admin ) : ?>
                                            <a href="<?php echo esc_url( $edit_url ); ?>" class="dpt-square-btn dpt-btn-edit" title="<?php esc_attr_e( 'Edit', 'ifsedu-school-management' ); ?>">
                                                <span class="dashicons dashicons-edit"></span>
                                            </a>
                                            <a href="<?php echo esc_url( $delete_url ); ?>" class="dpt-square-btn dpt-btn-delete" title="<?php esc_attr_e( 'Delete', 'ifsedu-school-management' ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete this record?', 'ifsedu-school-management' ) ); ?>');">
                                                <span class="dashicons dashicons-trash"></span>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 36px; color: #94a3b8;">
                                    <span class="dashicons dashicons-info" style="font-size: 28px; width: 28px; height: 28px; display: block; margin: 0 auto 8px auto;"></span>
                                    <?php esc_html_e( 'No records found in this directory.', 'ifsedu-school-management' ); ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>

    <script type="text/javascript">
    jQuery(document).ready(function($) {
        if ($.fn.DataTable && !$.fn.DataTable.isDataTable('.educore-datatable')) {
            $('.educore-datatable').DataTable({
                "pageLength": 15,
                "ordering": true,
                "responsive": true,
                "language": {
                    "search": "Filter Records:"
                }
            });
        }
    });
    </script>
    <?php
}

/**
 * 4. Add / Edit Notice & Events View (Saves with strict schema alignment)
 */
function educore_notice_events_add_edit_view( $type = 'notice' ) {
    global $wpdb;
    $table_notices = $wpdb->prefix . 'sms_notices';

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Permission denied.', 'ifsedu-school-management' ) );
    }

    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $is_edit = isset( $_GET['sub'] ) && 'edit' === $_GET['sub'];
    $id      = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    $item = null;
    if ( $is_edit && $id > 0 ) {
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $item = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table_notices}` WHERE id = %d", $id ) );
        // phpcs:enable
    }

    $alert_message = '';
    $alert_type    = '';

    // Handle Form Processing
    $post_nonce = isset( $_POST['educore_item_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['educore_item_nonce'] ) ) : '';
    if ( isset( $_POST['educore_save_item'] ) && wp_verify_nonce( $post_nonce, 'save_item_action' ) ) {
        $attachment_url = $item && isset( $item->attachment_url ) ? $item->attachment_url : '';
        $featured_image = $item && isset( $item->featured_image ) ? $item->featured_image : '';

        require_once ABSPATH . 'wp-admin/includes/file.php';

        if ( ! empty( $_FILES['item_file']['name'] ) ) {
            $upload = wp_handle_upload( $_FILES['item_file'], array( 'test_form' => false ) );
            if ( ! isset( $upload['error'] ) ) {
                $attachment_url = $upload['url'];
            }
        }

        if ( ! empty( $_FILES['featured_image_file']['name'] ) ) {
            $image_upload = wp_handle_upload( $_FILES['featured_image_file'], array( 'test_form' => false ) );
            if ( ! isset( $image_upload['error'] ) ) {
                $featured_image = $image_upload['url'];
            }
        }

        $raw_notice_type  = isset( $_POST['notice_type'] ) ? sanitize_text_field( wp_unslash( $_POST['notice_type'] ) ) : 'Notice';
        $form_type        = ( $type === 'events' || $type === 'event' ) ? 'Event' : $raw_notice_type;
        $title            = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
        $priority_val     = isset( $_POST['priority'] ) ? sanitize_text_field( wp_unslash( $_POST['priority'] ) ) : 'Normal';
        $target_audience  = isset( $_POST['target_audience'] ) ? sanitize_text_field( wp_unslash( $_POST['target_audience'] ) ) : 'All';
        $event_date_val   = ! empty( $_POST['event_date'] ) ? sanitize_text_field( wp_unslash( $_POST['event_date'] ) ) : current_time( 'Y-m-d' );
        $description_body = isset( $_POST['description'] ) ? wp_kses_post( wp_unslash( $_POST['description'] ) ) : '';
        $status           = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'Published';

        // Map data directly to all database columns
        $data = array(
            'title'           => $title,
            'notice_type'     => $form_type,
            'priority'        => $priority_val,
            'target_audience' => $target_audience,
            'description'     => $description_body,
            'content'         => $description_body,
            'event_date'      => $event_date_val,
            'publish_date'    => $event_date_val,
            'attachment_url'  => sanitize_url( $attachment_url ),
            'featured_image'  => sanitize_url( $featured_image ),
            'item_type'       => ( $type === 'events' || $type === 'event' ) ? 'event' : 'notice',
            'created_by'      => get_current_user_id(),
            'status'          => $status,
        );

        if ( $is_edit && $id > 0 ) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->update( $table_notices, $data, array( 'id' => $id ) );
            // phpcs:enable
            $alert_message = esc_html__( 'Record updated successfully.', 'ifsedu-school-management' );
            $alert_type    = 'success';
            $item          = (object) array_merge( (array) $item, $data );
        } else {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->insert( $table_notices, $data );
            // phpcs:enable
            $alert_message = esc_html__( 'Published successfully.', 'ifsedu-school-management' );
            $alert_type    = 'success';
            $_POST         = array();
            $item          = null;
        }
    }

    $back_url = admin_url( 'admin.php?page=school_management_system&tab=notices&type=' . ( ( $type === 'events' || $type === 'event' ) ? 'events' : 'notice' ) . '&sub=list' );
    ?>

    <style>
        .dpt-editor-root {
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #0f172a;
        }

        .dpt-top-action-bar {
            margin-bottom: 20px;
        }

        .dpt-btn-back {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            color: #334155;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.2s ease;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .dpt-btn-back:hover {
            background: #f8fafc;
            border-color: #94a3b8;
            color: #0f172a;
        }

        .dpt-form-bento-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.03);
        }

        .afdp-form-header {
            border-bottom: 2px solid #f1f5f9;
            padding-bottom: 16px;
            margin-bottom: 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .afdp-form-title {
            font-size: 20px;
            font-weight: 800;
            color: #00523c;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
            letter-spacing: -0.4px;
        }

        .dpt-grid-row {
            display: grid;
            gap: 20px;
            margin-bottom: 20px;
        }

        .dpt-cols-12   { grid-template-columns: 1fr; }
        .dpt-cols-8-4  { grid-template-columns: 2fr 1fr; }
        .dpt-cols-3    { grid-template-columns: repeat(3, 1fr); }

        @media (max-width: 868px) {
            .dpt-cols-8-4, .dpt-cols-3 {
                grid-template-columns: 1fr;
            }
        }

        .dpt-field-node {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .dpt-field-label {
            font-size: 13px;
            font-weight: 700;
            color: #334155;
            letter-spacing: -0.1px;
        }

        .dpt-field-label span.required {
            color: #dc2626;
        }

        .dpt-input-control,
        .dpt-select-control {
            width: 100%;
            height: 44px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            padding: 0 14px;
            font-size: 13.5px;
            color: #0f172a;
            background-color: #f8fafc;
            box-sizing: border-box;
            transition: all 0.2s ease;
        }

        .dpt-input-file {
            width: 100%;
            padding: 8px 12px;
            border: 1px dashed #cbd5e1;
            border-radius: 10px;
            background: #f8fafc;
            font-size: 13px;
            color: #475569;
            box-sizing: border-box;
        }

        .dpt-input-control:focus,
        .dpt-select-control:focus {
            border-color: #00523c;
            background-color: #ffffff;
            box-shadow: 0 0 0 3px rgba(0, 106, 78, 0.1);
            outline: none;
        }

        .dpt-img-preview-box {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 6px;
            padding: 8px;
            border: 1px dashed #cbd5e1;
            border-radius: 10px;
            background: #f8fafc;
        }

        .dpt-img-preview-box img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }

        .dpt-editor-wrapper {
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            overflow: hidden;
            background: #ffffff;
        }

        .dpt-editor-wrapper .wp-editor-container {
            border: none;
        }

        .dpt-submit-action {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #f1f5f9;
        }

        .dpt-btn-primary {
            height: 46px;
            padding: 0 32px;
            background: #00523c;
            border: none;
            color: #ffffff;
            font-weight: 800;
            font-size: 14px;
            border-radius: 10px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(0, 106, 78, 0.25);
        }

        .dpt-btn-primary:hover {
            background: #00523c;
            transform: translateY(-1px);
        }

        .afdp-alert-node {
            padding: 14px 18px;
            border-radius: 10px;
            font-size: 13.5px;
            font-weight: 600;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .afdp-alert-success {
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            color: #065f46;
        }
    </style>

    <div class="dpt-editor-root">
        
        <div class="dpt-top-action-bar">
            <a href="<?php echo esc_url( $back_url ); ?>" class="dpt-btn-back">
                <span class="dashicons dashicons-arrow-left-alt"></span>
                <?php esc_html_e( 'Back to List', 'ifsedu-school-management' ); ?>
            </a>
        </div>

        <?php if ( ! empty( $alert_message ) ) : ?>
            <div class="afdp-alert-node afdp-alert-<?php echo esc_attr( $alert_type ); ?>">
                <span class="dashicons dashicons-yes-alt"></span>
                <?php echo esc_html( $alert_message ); ?>
            </div>
        <?php endif; ?>

        <div class="dpt-form-bento-card">
            
            <div class="afdp-form-header">
                <h3 class="afdp-form-title">
                    <span class="dashicons <?php echo $is_edit ? 'dashicons-edit' : 'dashicons-plus-alt'; ?>"></span>
                    <?php echo $is_edit ? esc_html__( 'Edit Record Details', 'ifsedu-school-management' ) : esc_html__( 'Add New Announcement / Event', 'ifsedu-school-management' ); ?>
                </h3>
            </div>

            <form method="POST" action="" enctype="multipart/form-data">
                <?php wp_nonce_field( 'save_item_action', 'educore_item_nonce' ); ?>

                <!-- Row 1: Title & Category -->
                <div class="dpt-grid-row <?php echo ( $type !== 'events' ) ? 'dpt-cols-8-4' : 'dpt-cols-12'; ?>">
                    <div class="dpt-field-node">
                        <label class="dpt-field-label">
                            <?php esc_html_e( 'Title', 'ifsedu-school-management' ); ?> <span class="required">*</span>
                        </label>
                        <input type="text" name="title" class="dpt-input-control" value="<?php echo $item ? esc_attr( $item->title ) : ''; ?>" placeholder="Enter notice or event heading..." required>
                    </div>

                    <?php if ( $type !== 'events' ) : ?>
                        <div class="dpt-field-node">
                            <label class="dpt-field-label"><?php esc_html_e( 'Category Type', 'ifsedu-school-management' ); ?></label>
                            <select name="notice_type" class="dpt-select-control">
                                <option value="Notice" <?php selected( $item ? ( $item->notice_type ?? $item->category ) : '', 'Notice' ); ?>>General Notice</option>
                                <option value="Holiday" <?php selected( $item ? ( $item->notice_type ?? $item->category ) : '', 'Holiday' ); ?>>Holiday Notice</option>
                                <option value="Exam" <?php selected( $item ? ( $item->notice_type ?? $item->category ) : '', 'Exam' ); ?>>Exam Notice</option>
                            </select>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Row 2: Target, Priority, Date -->
                <div class="dpt-grid-row dpt-cols-3">
                    <div class="dpt-field-node">
                        <label class="dpt-field-label"><?php esc_html_e( 'Target Audience', 'ifsedu-school-management' ); ?></label>
                        <select name="target_audience" class="dpt-select-control">
                            <option value="All" <?php selected( $item ? $item->target_audience : '', 'All' ); ?>>All Stakeholders</option>
                            <option value="Students" <?php selected( $item ? $item->target_audience : '', 'Students' ); ?>>Students Only</option>
                            <option value="Teachers" <?php selected( $item ? $item->target_audience : '', 'Teachers' ); ?>>Teachers Only</option>
                        </select>
                    </div>

                    <div class="dpt-field-node">
                        <label class="dpt-label dpt-field-label"><?php esc_html_e( 'Priority Level', 'ifsedu-school-management' ); ?></label>
                        <select name="priority" class="dpt-select-control">
                            <option value="Normal" <?php selected( $item ? $item->priority : '', 'Normal' ); ?>>Normal</option>
                            <option value="High" <?php selected( $item ? $item->priority : '', 'High' ); ?>>High</option>
                            <option value="Urgent" <?php selected( $item ? $item->priority : '', 'Urgent' ); ?>>Urgent</option>
                        </select>
                    </div>

                    <div class="dpt-field-node">
                        <label class="dpt-field-label">
                            <?php echo ( $type === 'events' ) ? esc_html__( 'Event Date', 'ifsedu-school-management' ) : esc_html__( 'Effective Date', 'ifsedu-school-management' ); ?>
                        </label>
                        <input type="date" name="event_date" class="dpt-input-control" value="<?php echo $item ? esc_attr( ! empty( $item->event_date ) && $item->event_date !== '1970-01-01' ? $item->event_date : ( ! empty( $item->publish_date ) ? $item->publish_date : '' ) ) : esc_attr( current_time( 'Y-m-d' ) ); ?>">
                    </div>
                </div>

                <!-- Row 3: Rich Description Editor -->
                <div class="dpt-grid-row dpt-cols-12">
                    <div class="dpt-field-node">
                        <label class="dpt-field-label"><?php esc_html_e( 'Description Details', 'ifsedu-school-management' ); ?></label>
                        <div class="dpt-editor-wrapper">
                            <?php 
                            wp_editor( 
                                $item ? ( ! empty( $item->description ) ? $item->description : $item->content ) : '', 
                                'description', 
                                array( 
                                    'textarea_rows' => 8,
                                    'quicktags'     => true,
                                    'tinymce'       => true
                                ) 
                            ); 
                            ?>
                        </div>
                    </div>
                </div>

                <!-- Row 4: Featured Image, File Upload & Status -->
                <div class="dpt-grid-row dpt-cols-3">
                    <div class="dpt-field-node">
                        <label class="dpt-field-label">
                            <?php esc_html_e( 'Featured Image (JPG / PNG)', 'ifsedu-school-management' ); ?>
                        </label>
                        <input type="file" name="featured_image_file" class="dpt-input-file" accept="image/*">
                        <?php if ( $item && ! empty( $item->featured_image ) ) : ?>
                            <div class="dpt-img-preview-box">
                                <img src="<?php echo esc_url( $item->featured_image ); ?>" alt="Featured Image Preview">
                                <span style="font-size: 12px; color: #475569; font-weight:600;">Current Banner</span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="dpt-field-node">
                        <label class="dpt-field-label">
                            <?php esc_html_e( 'Attachment File (PDF / DOC)', 'ifsedu-school-management' ); ?>
                            <?php if ( $item && ! empty( $item->attachment_url ) ) : ?>
                                &mdash; <a href="<?php echo esc_url( $item->attachment_url ); ?>" target="_blank" style="color:#00523c; text-decoration:underline;">View Current</a>
                            <?php endif; ?>
                        </label>
                        <input type="file" name="item_file" class="dpt-input-file">
                    </div>

                    <div class="dpt-field-node">
                        <label class="dpt-field-label"><?php esc_html_e( 'Publication Status', 'ifsedu-school-management' ); ?></label>
                        <select name="status" class="dpt-select-control">
                            <option value="Published" <?php selected( $item ? $item->status : '', 'Published' ); ?>>Published</option>
                            <option value="Draft" <?php selected( $item ? $item->status : '', 'Draft' ); ?>>Draft</option>
                        </select>
                    </div>
                </div>

                <!-- Form Submit Action Bar -->
                <div class="dpt-submit-action">
                    <button type="submit" name="educore_save_item" class="dpt-btn-primary">
                        <span class="dashicons dashicons-saved"></span>
                        <?php esc_html_e( 'Save & Publish Record', 'ifsedu-school-management' ); ?>
                    </button>
                </div>

            </form>
        </div>

    </div>
    <?php
}

/**
 * 5. Single Notice / Event Detail View
 */
function educore_notice_events_single_view( $type = 'notice' ) {
    global $wpdb;
    $table_notices = $wpdb->prefix . 'sms_notices';

    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $id   = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $item = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table_notices}` WHERE id = %d", $id ) );
    // phpcs:enable

    $back_url = admin_url( 'admin.php?page=school_management_system&tab=notices&type=' . $type . '&sub=list' );

    if ( ! $item ) {
        ?>
        <div style="background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; padding: 16px 20px; border-radius: 12px; font-weight: 700; font-size: 14px;">
            <span class="dashicons dashicons-dismiss"></span>
            <?php esc_html_e( 'Record not found or has been deleted.', 'ifsedu-school-management' ); ?>
        </div>
        <?php
        return;
    }

    $display_date = ( ! empty( $item->event_date ) && $item->event_date !== '1970-01-01' ) 
        ? date_i18n( 'F j, Y', strtotime( $item->event_date ) ) 
        : date_i18n( 'F j, Y', strtotime( ! empty( $item->publish_date ) ? $item->publish_date : $item->created_at ) );

    $priority_class = 'dpt-priority-normal';
    if ( $item->priority === 'High' ) {
        $priority_class = 'dpt-priority-high';
    } elseif ( $item->priority === 'Urgent' ) {
        $priority_class = 'dpt-priority-urgent';
    }

    $status_class   = ( $item->status === 'Published' ) ? 'dpt-status-published' : 'dpt-status-draft';
    $featured_image = ! empty( $item->featured_image ) ? $item->featured_image : '';
    ?>

    <style>
        .dpt-single-root {
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #0f172a;
        }

        .dpt-top-action-bar {
            margin-bottom: 20px;
        }

        .dpt-btn-back {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            color: #334155;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.2s ease;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .dpt-btn-back:hover {
            background: #f8fafc;
            border-color: #94a3b8;
            color: #0f172a;
        }

        .dpt-single-bento-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.03);
        }

        .afdp-single-header {
            border-bottom: 2px solid #f1f5f9;
            padding-bottom: 20px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
            flex-wrap: wrap;
        }

        .afdp-single-title {
            font-size: 24px;
            font-weight: 800;
            color: #0f172a;
            margin: 0 0 8px 0;
            letter-spacing: -0.5px;
            line-height: 1.3;
        }

        .dpt-hero-featured-banner {
            width: 100%;
            max-height: 420px;
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 24px;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .dpt-hero-featured-banner img {
            width: 100%;
            height: 100%;
            max-height: 420px;
            object-fit: cover;
            display: block;
        }

        .dpt-meta-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 28px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 16px 20px;
        }

        @media (max-width: 768px) {
            .dpt-meta-grid { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 480px) {
            .dpt-meta-grid { grid-template-columns: 1fr; }
        }

        .dpt-meta-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .dpt-meta-label {
            font-size: 11.5px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .dpt-meta-value {
            font-size: 13.5px;
            font-weight: 700;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .dpt-badge-node {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11.5px;
            font-weight: 700;
            letter-spacing: 0.2px;
            text-transform: uppercase;
        }

        .dpt-priority-normal { background: #f0f9ff; color: #0369a1; border: 1px solid #bae6fd; }
        .dpt-priority-high   { background: #fff7ed; color: #c2410c; border: 1px solid #ffedd5; }
        .dpt-priority-urgent { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }

        .dpt-status-published { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
        .dpt-status-draft     { background: #f1f5f9; color: #64748b; border: 1px solid #cbd5e1; }

        .dpt-content-body {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 24px;
            font-size: 14.5px;
            line-height: 1.7;
            color: #334155;
            margin-bottom: 28px;
            min-height: 120px;
        }

        .dpt-attachment-card {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 12px;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }

        .dpt-attachment-info {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13.5px;
            font-weight: 700;
            color: #065f46;
        }

        .dpt-btn-download {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 18px;
            background: #00523c;
            color: #ffffff;
            font-size: 12.5px;
            font-weight: 700;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.2s ease;
            box-shadow: 0 2px 8px rgba(0, 106, 78, 0.2);
        }

        .dpt-btn-download:hover {
            background: #00523c;
            color: #ffffff;
            transform: translateY(-1px);
        }
    </style>

    <div class="dpt-single-root">
        
        <div class="dpt-top-action-bar">
            <a href="<?php echo esc_url( $back_url ); ?>" class="dpt-btn-back">
                <span class="dashicons dashicons-arrow-left-alt"></span>
                <?php esc_html_e( 'Back to Directory', 'ifsedu-school-management' ); ?>
            </a>
        </div>

        <div class="dpt-single-bento-card">
            
            <div class="afdp-single-header">
                <div>
                    <h2 class="afdp-single-title"><?php echo esc_html( $item->title ); ?></h2>
                </div>
                <div>
                    <span class="dpt-badge-node <?php echo esc_attr( $status_class ); ?>">
                        <?php echo esc_html( $item->status ); ?>
                    </span>
                </div>
            </div>

            <?php if ( ! empty( $featured_image ) ) : ?>
                <div class="dpt-hero-featured-banner">
                    <a href="<?php echo esc_url( $featured_image ); ?>" target="_blank" title="<?php esc_attr_e( 'View Full Image', 'ifsedu-school-management' ); ?>">
                        <img src="<?php echo esc_url( $featured_image ); ?>" alt="<?php echo esc_attr( $item->title ); ?>">
                    </a>
                </div>
            <?php endif; ?>

            <div class="dpt-meta-grid">
                <div class="dpt-meta-item">
                    <span class="dpt-meta-label"><?php esc_html_e( 'Category Type', 'ifsedu-school-management' ); ?></span>
                    <span class="dpt-meta-value">
                        <span class="dashicons dashicons-tag" style="color: #64748b; font-size:16px;"></span>
                        <?php echo esc_html( $item->notice_type ?? $item->category ); ?>
                    </span>
                </div>

                <div class="dpt-meta-item">
                    <span class="dpt-meta-label"><?php esc_html_e( 'Target Audience', 'ifsedu-school-management' ); ?></span>
                    <span class="dpt-meta-value">
                        <span class="dashicons dashicons-groups" style="color: #64748b; font-size:16px;"></span>
                        <?php echo esc_html( $item->target_audience ); ?>
                    </span>
                </div>

                <div class="dpt-meta-item">
                    <span class="dpt-meta-label"><?php esc_html_e( 'Priority Level', 'ifsedu-school-management' ); ?></span>
                    <span class="dpt-meta-value">
                        <span class="dpt-badge-node <?php echo esc_attr( $priority_class ); ?>">
                            <?php echo esc_html( $item->priority ); ?>
                        </span>
                    </span>
                </div>

                <div class="dpt-meta-item">
                    <span class="dpt-meta-label">
                        <?php echo ( $type === 'events' ) ? esc_html__( 'Event Date', 'ifsedu-school-management' ) : esc_html__( 'Published Date', 'ifsedu-school-management' ); ?>
                    </span>
                    <span class="dpt-meta-value">
                        <span class="dashicons dashicons-calendar-alt" style="color: #00523c; font-size:16px;"></span>
                        <?php echo esc_html( $display_date ); ?>
                    </span>
                </div>
            </div>

            <div class="dpt-content-body">
                <?php echo wp_kses_post( ! empty( $item->description ) ? $item->description : $item->content ); ?>
            </div>

            <?php if ( ! empty( $item->attachment_url ) ) : ?>
                <div class="dpt-attachment-card">
                    <div class="dpt-attachment-info">
                        <span class="dashicons dashicons-paperclip"></span>
                        <?php esc_html_e( 'Official Attached Document / File Available', 'ifsedu-school-management' ); ?>
                    </div>
                    <a href="<?php echo esc_url( $item->attachment_url ); ?>" target="_blank" class="dpt-btn-download">
                        <span class="dashicons dashicons-download"></span>
                        <?php esc_html_e( 'View / Download Attachment', 'ifsedu-school-management' ); ?>
                    </a>
                </div>
            <?php endif; ?>

        </div>

    </div>
    <?php
}

/**
 * 6. Delete Action Handler
 */
function educore_notice_events_delete_action( $type = 'notice' ) {
    global $wpdb;
    $table_notices = $wpdb->prefix . 'sms_notices';

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Permission denied.', 'ifsedu-school-management' ) );
    }

    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $id    = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
    $_nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    if ( $id > 0 && wp_verify_nonce( $_nonce, 'delete_item_' . $id ) ) {
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->delete( $table_notices, array( 'id' => $id ), array( '%d' ) );
        // phpcs:enable
    }

    $target_url = admin_url( 'admin.php?page=school_management_system&tab=notices&type=' . ( ( $type === 'events' || $type === 'event' ) ? 'events' : 'notice' ) . '&sub=list' );
    educore_safe_redirect( $target_url );
}

/**
 * 7. Photo Gallery Module & Router
 */
function educore_gallery_router( $sub_tab ) {
    switch ( $sub_tab ) {
        case 'add':
        case 'edit':
            educore_gallery_add_edit_view();
            break;

        case 'view':
            educore_gallery_single_album_view();
            break;

        case 'delete_photo':
            educore_gallery_photo_delete_action();
            break;

        case 'delete':
            educore_gallery_delete_action();
            break;

        case 'list':
        default:
            educore_gallery_list_view();
            break;
    }
}

function educore_gallery_list_view() {
    global $wpdb;
    $table_albums = $wpdb->prefix . 'sms_gallery_albums';
    $table_photos = $wpdb->prefix . 'sms_gallery_photos';

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Permission denied.', 'ifsedu-school-management' ) );
    }

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $albums  = $wpdb->get_results( "SELECT * FROM `{$table_albums}` ORDER BY id DESC" );
    // phpcs:enable
    $albums  = is_array( $albums ) ? $albums : array();
    $add_url = admin_url( 'admin.php?page=school_management_system&tab=notices&type=gallery&sub=add' );
    ?>

    <style>
        .dpt-gallery-root {
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #0f172a;
        }

        .afdp-header-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
            gap: 16px;
            flex-wrap: wrap;
        }

        .afdp-page-title {
            font-size: 20px;
            font-weight: 800;
            color: #00523c;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
            letter-spacing: -0.4px;
        }

        .dpt-btn-primary {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: #00523c;
            color: #ffffff;
            font-size: 13.5px;
            font-weight: 700;
            border-radius: 10px;
            text-decoration: none;
            transition: all 0.2s ease;
            box-shadow: 0 2px 8px rgba(0, 106, 78, 0.2);
            border: none;
            cursor: pointer;
        }

        .dpt-btn-primary:hover {
            background: #00523c;
            color: #ffffff;
            transform: translateY(-1px);
        }

        .dpt-bento-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }

        .dpt-album-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: all 0.25s ease;
            box-shadow: 0 4px 15px -3px rgba(0, 0, 0, 0.03);
        }

        .dpt-album-card:hover {
            border-color: #cbd5e1;
            transform: translateY(-3px);
            box-shadow: 0 12px 25px -5px rgba(0, 0, 0, 0.08);
        }

        .dpt-cover-container {
            height: 180px;
            position: relative;
            background-color: #f1f5f9;
            overflow: hidden;
        }

        .dpt-cover-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.4s ease;
        }

        .dpt-album-card:hover .dpt-cover-img {
            transform: scale(1.04);
        }

        .dpt-category-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            background: rgba(15, 23, 42, 0.75);
            backdrop-filter: blur(4px);
            color: #ffffff;
            font-size: 11px;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 20px;
            letter-spacing: 0.3px;
            text-transform: uppercase;
        }

        .dpt-card-body {
            padding: 16px 20px;
            flex: 1;
        }

        .dpt-album-title {
            font-size: 16px;
            font-weight: 800;
            color: #0f172a;
            margin: 0 0 6px 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .dpt-photo-count {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 12.5px;
            color: #64748b;
            font-weight: 600;
        }

        .dpt-card-footer {
            padding: 12px 20px 16px 20px;
            background: #ffffff;
            border-top: 1px solid #f8fafc;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 6px;
        }

        .dpt-square-btn {
            width: 32px;
            height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.2s ease;
            border: 1px solid transparent;
        }

        .dpt-btn-view { background: #f0f9ff; color: #0284c7; border-color: #bae6fd; }
        .dpt-btn-view:hover { background: #0284c7; color: #ffffff; }

        .dpt-btn-edit { background: #f0fdf4; color: #16a34a; border-color: #bbf7d0; }
        .dpt-btn-edit:hover { background: #16a34a; color: #ffffff; }

        .dpt-btn-delete { background: #fef2f2; color: #dc2626; border-color: #fecaca; }
        .dpt-btn-delete:hover { background: #dc2626; color: #ffffff; }

        .dpt-empty-state {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 60px 20px;
            text-align: center;
            color: #64748b;
        }

        .dpt-empty-state .dashicons {
            font-size: 48px;
            width: 48px;
            height: 48px;
            color: #cbd5e1;
            margin-bottom: 12px;
        }

        .dpt-empty-state h5 {
            margin: 0;
            font-size: 16px;
            font-weight: 700;
            color: #475569;
        }
    </style>

    <div class="dpt-gallery-root">
        
        <div class="afdp-header-bar">
            <h2 class="afdp-page-title">
                <span class="dashicons dashicons-format-gallery"></span> 
                <?php esc_html_e( 'Photo Albums Directory', 'ifsedu-school-management' ); ?>
            </h2>
            <a href="<?php echo esc_url( $add_url ); ?>" class="dpt-btn-primary">
                <span class="dashicons dashicons-plus-alt2" style="font-size:16px; width:16px; height:16px;"></span>
                <?php esc_html_e( 'Create New Album', 'ifsedu-school-management' ); ?>
            </a>
        </div>

        <?php if ( ! empty( $albums ) ) : ?>
            <div class="dpt-bento-grid">
                <?php foreach ( $albums as $album ) : 
                    $album_id    = absint( $album->id );
                    $view_url    = admin_url( 'admin.php?page=school_management_system&tab=notices&type=gallery&sub=view&id=' . $album_id );
                    $edit_url    = admin_url( 'admin.php?page=school_management_system&tab=notices&type=gallery&sub=edit&id=' . $album_id );
                    $delete_url  = wp_nonce_url( admin_url( 'admin.php?page=school_management_system&tab=notices&type=gallery&sub=delete&id=' . $album_id ), 'delete_gallery_' . $album_id );
                    
                    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                    $photo_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM `{$table_photos}` WHERE album_id = %d", $album_id ) );
                    // phpcs:enable

                    $cover_src   = ! empty( $album->cover_image ) ? $album->cover_image : EDUCORE_URL . 'assets/img/logo.png';
                ?>
                <div class="dpt-album-card">
                    <div class="dpt-cover-container">
                        <img src="<?php echo esc_url( $cover_src ); ?>" class="dpt-cover-img" alt="<?php echo esc_attr( $album->title ); ?>">
                        <span class="dpt-category-badge">
                            <?php echo esc_html( $album->category ?? 'General' ); ?>
                        </span>
                    </div>

                    <div class="dpt-card-body">
                        <h3 class="dpt-album-title" title="<?php echo esc_attr( $album->title ); ?>"><?php echo esc_html( $album->title ); ?></h3>
                        <span class="dpt-photo-count">
                            <span class="dashicons dashicons-images-alt2" style="font-size: 14px; width:14px; height:14px;"></span> 
                            <?php echo esc_html( $photo_count ); ?> <?php esc_html_e( 'Photos', 'ifsedu-school-management' ); ?>
                        </span>
                    </div>

                    <div class="dpt-card-footer">
                        <a href="<?php echo esc_url( $view_url ); ?>" class="dpt-square-btn dpt-btn-view" title="<?php esc_attr_e( 'View Album', 'ifsedu-school-management' ); ?>">
                            <span class="dashicons dashicons-visibility"></span>
                        </a>
                        <a href="<?php echo esc_url( $edit_url ); ?>" class="dpt-square-btn dpt-btn-edit" title="<?php esc_attr_e( 'Edit Album', 'ifsedu-school-management' ); ?>">
                            <span class="dashicons dashicons-edit"></span>
                        </a>
                        <a href="<?php echo esc_url( $delete_url ); ?>" class="dpt-square-btn dpt-btn-delete" title="<?php esc_attr_e( 'Delete Album', 'ifsedu-school-management' ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this album and all its images?', 'ifsedu-school-management' ) ); ?>');">
                            <span class="dashicons dashicons-trash"></span>
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else : ?>
            <div class="dpt-empty-state">
                <span class="dashicons dashicons-format-gallery"></span>
                <h5><?php esc_html_e( 'No photo albums created yet.', 'ifsedu-school-management' ); ?></h5>
            </div>
        <?php endif; ?>

    </div>
    <?php
}

function educore_gallery_single_album_view() {
    global $wpdb;
    $table_albums = $wpdb->prefix . 'sms_gallery_albums';
    $table_photos = $wpdb->prefix . 'sms_gallery_photos';

    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $album_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $album    = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table_albums}` WHERE id = %d", $album_id ) );
    // phpcs:enable

    if ( ! $album ) {
        ?>
        <div style="background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; padding: 16px 20px; border-radius: 12px; font-weight: 700; font-size: 14px;">
            <span class="dashicons dashicons-dismiss"></span>
            <?php esc_html_e( 'Album not found or has been deleted.', 'ifsedu-school-management' ); ?>
        </div>
        <?php
        return;
    }

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $photos   = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$table_photos}` WHERE album_id = %d ORDER BY id DESC", $album_id ) );
    // phpcs:enable
    $photos   = is_array( $photos ) ? $photos : array();
    $back_url = admin_url( 'admin.php?page=school_management_system&tab=notices&type=gallery&sub=list' );
    $edit_url = admin_url( 'admin.php?page=school_management_system&tab=notices&type=gallery&sub=edit&id=' . $album_id );
    ?>

    <style>
        .dpt-single-root {
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #0f172a;
        }

        .dpt-top-action-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            gap: 16px;
            flex-wrap: wrap;
        }

        .dpt-btn-back {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            color: #334155;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .dpt-btn-action {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: #2563eb;
            color: #ffffff;
            font-size: 13px;
            font-weight: 700;
            border-radius: 10px;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .dpt-album-detail-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.03);
        }

        .afdp-album-header-title {
            font-size: 22px;
            font-weight: 800;
            color: #00523c;
            margin: 0 0 8px 0;
            letter-spacing: -0.4px;
        }

        .dpt-photo-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 16px;
        }

        .dpt-photo-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
            transition: all 0.2s ease;
        }

        .dpt-photo-card:hover {
            transform: scale(1.02);
            border-color: #cbd5e1;
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
        }

        .dpt-photo-link {
            display: block;
            height: 140px;
            width: 100%;
        }

        .dpt-photo-link img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
    </style>

    <div class="dpt-single-root">
        
        <div class="dpt-top-action-bar">
            <a href="<?php echo esc_url( $back_url ); ?>" class="dpt-btn-back">
                <span class="dashicons dashicons-arrow-left-alt"></span>
                <?php esc_html_e( 'Back to Album Directory', 'ifsedu-school-management' ); ?>
            </a>
            <a href="<?php echo esc_url( $edit_url ); ?>" class="dpt-btn-action">
                <span class="dashicons dashicons-edit"></span>
                <?php esc_html_e( 'Edit / Upload More Photos', 'ifsedu-school-management' ); ?>
            </a>
        </div>

        <div class="dpt-album-detail-card">
            <h3 class="afdp-album-header-title"><?php echo esc_html( $album->title ); ?></h3>
            <div style="display: flex; gap: 16px; font-size: 12.5px; color: #64748b; font-weight: 600; margin-bottom: 12px;">
                <span><strong>Category:</strong> <?php echo esc_html( $album->category ); ?></span>
                <span>•</span>
                <span><strong>Total Photos:</strong> <?php echo esc_html( count( $photos ) ); ?></span>
            </div>
            <?php if ( ! empty( $album->description ) ) : ?>
                <p style="color: #334155; font-size: 14px; line-height: 1.6; margin: 12px 0 0 0; padding-top: 12px; border-top: 1px solid #f1f5f9;"><?php echo esc_html( $album->description ); ?></p>
            <?php endif; ?>
        </div>

        <?php if ( ! empty( $photos ) ) : ?>
            <div class="dpt-photo-grid">
                <?php foreach ( $photos as $photo ) : ?>
                    <div class="dpt-photo-card">
                        <a href="<?php echo esc_url( $photo->image_url ); ?>" target="_blank" class="dpt-photo-link">
                            <img src="<?php echo esc_url( $photo->image_url ); ?>" alt="Gallery Photo">
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else : ?>
            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 40px; text-align: center; color: #64748b; font-weight: 600;">
                <?php esc_html_e( 'This album contains no photos yet.', 'ifsedu-school-management' ); ?>
            </div>
        <?php endif; ?>

    </div>
    <?php
}

function educore_gallery_add_edit_view() {
    global $wpdb;
    $table_albums = $wpdb->prefix . 'sms_gallery_albums';
    $table_photos = $wpdb->prefix . 'sms_gallery_photos';

    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $is_edit  = isset( $_GET['sub'] ) && 'edit' === $_GET['sub'];
    $album_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    $album         = null;
    $photos        = array();
    $saved_message = false;

    if ( $is_edit && $album_id > 0 ) {
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $album  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table_albums}` WHERE id = %d", $album_id ) );
        $photos = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$table_photos}` WHERE album_id = %d ORDER BY id DESC", $album_id ) );
        // phpcs:enable
        $photos = is_array( $photos ) ? $photos : array();
    }

    $gallery_nonce = isset( $_POST['educore_gallery_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['educore_gallery_nonce'] ) ) : '';
    if ( isset( $_POST['educore_save_gallery'] ) && wp_verify_nonce( $gallery_nonce, 'save_gallery_action' ) ) {
        $cover_image = $album ? $album->cover_image : '';

        if ( ! empty( $_FILES['cover_image']['name'] ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            $upload = wp_handle_upload( $_FILES['cover_image'], array( 'test_form' => false ) );
            if ( ! isset( $upload['error'] ) ) {
                $cover_image = $upload['url'];
            }
        }

        $album_title       = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
        $album_category    = isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : 'General';
        $album_description = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';
        $album_status      = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'Published';

        $album_data = array(
            'title'       => $album_title,
            'category'    => $album_category,
            'description' => $album_description,
            'cover_image' => sanitize_url( $cover_image ),
            'status'      => $album_status,
        );

        if ( $is_edit && $album_id > 0 ) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->update( $table_albums, $album_data, array( 'id' => $album_id ) );
            // phpcs:enable
            $current_id = $album_id;
        } else {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
            $wpdb->insert( $table_albums, $album_data );
            // phpcs:enable
            $current_id = (int) $wpdb->insert_id;
        }

        // Multi-File Batch Photo Upload
        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        if ( ! empty( $_FILES['gallery_photos']['name'][0] ) && $current_id > 0 ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            $files = wp_unslash( $_FILES['gallery_photos'] );
            foreach ( $files['name'] as $k => $v ) {
                if ( ! empty( $files['name'][ $k ] ) ) {
                    $file = array(
                        'name'     => sanitize_file_name( $files['name'][ $k ] ),
                        'type'     => sanitize_text_field( $files['type'][ $k ] ),
                        'tmp_name' => sanitize_text_field( $files['tmp_name'][ $k ] ),
                        'error'    => intval( $files['error'][ $k ] ),
                        'size'     => intval( $files['size'][ $k ] ),
                    );
                    $up = wp_handle_upload( $file, array( 'test_form' => false ) );
                    if ( ! isset( $up['error'] ) ) {
                        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
                        $wpdb->insert( $table_photos, array( 'album_id' => $current_id, 'image_url' => sanitize_url( $up['url'] ) ) );
                        // phpcs:enable

                        if ( empty( $cover_image ) ) {
                            $cover_image = $up['url'];
                            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                            $wpdb->update( $table_albums, array( 'cover_image' => $cover_image ), array( 'id' => $current_id ) );
                            // phpcs:enable
                        }
                    }
                }
            }
        }
        // phpcs:enable

        $saved_message = true;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $album  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table_albums}` WHERE id = %d", $current_id ) );
        $photos = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$table_photos}` WHERE album_id = %d ORDER BY id DESC", $current_id ) );
        // phpcs:enable
        $photos = is_array( $photos ) ? $photos : array();
    }

    $back_url = admin_url( 'admin.php?page=school_management_system&tab=notices&type=gallery&sub=list' );
    ?>

    <style>
        .dpt-form-root {
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #0f172a;
        }

        .dpt-top-action-bar {
            margin-bottom: 20px;
        }

        .dpt-btn-back {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            color: #334155;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .dpt-bento-form-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.03);
        }

        .afdp-form-title {
            font-size: 20px;
            font-weight: 800;
            color: #00523c;
            margin: 0 0 24px 0;
            padding-bottom: 16px;
            border-bottom: 2px solid #f1f5f9;
            letter-spacing: -0.4px;
        }

        .dpt-form-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        @media (max-width: 768px) {
            .dpt-form-row { grid-template-columns: 1fr; }
        }

        .dpt-form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 20px;
        }

        .dpt-form-group label {
            font-size: 13px;
            font-weight: 700;
            color: #334155;
        }

        .dpt-field-input,
        .dpt-field-select,
        .dpt-field-textarea {
            width: 100%;
            padding: 10px 14px;
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            font-size: 14px;
            color: #0f172a;
            box-sizing: border-box;
        }

        .dpt-upload-bento-node {
            background: #f0fdf4;
            border: 1px dashed #86efac;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
        }

        .dpt-upload-bento-node label {
            color: #065f46;
            font-size: 14px;
            font-weight: 800;
            margin-bottom: 6px;
            display: block;
        }

        .dpt-manage-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
            gap: 12px;
        }

        .dpt-manage-photo-card {
            position: relative;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 4px;
            background: #ffffff;
            height: 90px;
        }

        .dpt-manage-photo-card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 6px;
        }

        .dpt-btn-photo-del {
            position: absolute;
            top: -6px;
            right: -6px;
            width: 24px;
            height: 24px;
            background: #dc2626;
            color: #ffffff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            font-size: 14px;
            font-weight: 800;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }

        .dpt-btn-submit {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 32px;
            background: #00523c;
            color: #ffffff;
            font-size: 14px;
            font-weight: 800;
            border-radius: 10px;
            border: none;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0, 106, 78, 0.25);
        }
    </style>

    <div class="dpt-form-root">
        
        <div class="dpt-top-action-bar">
            <a href="<?php echo esc_url( $back_url ); ?>" class="dpt-btn-back">
                <span class="dashicons dashicons-arrow-left-alt"></span>
                <?php esc_html_e( 'Back to Gallery', 'ifsedu-school-management' ); ?>
            </a>
        </div>

        <?php if ( $saved_message ) : ?>
            <div style="background: #ecfdf5; border: 1px solid #a7f3d0; color: #047857; padding: 14px 20px; border-radius: 12px; font-weight: 700; font-size: 14px; margin-bottom: 20px;">
                <span class="dashicons dashicons-yes-alt"></span>
                <?php esc_html_e( 'Album saved successfully.', 'ifsedu-school-management' ); ?>
            </div>
        <?php endif; ?>

        <div class="dpt-bento-form-card">
            <h3 class="afdp-form-title">
                <?php echo $is_edit ? esc_html__( 'Edit Album Details', 'ifsedu-school-management' ) : esc_html__( 'Create Photo Album', 'ifsedu-school-management' ); ?>
            </h3>

            <form method="POST" action="" enctype="multipart/form-data">
                <?php wp_nonce_field( 'save_gallery_action', 'educore_gallery_nonce' ); ?>

                <div class="dpt-form-row">
                    <div class="dpt-form-group" style="margin-bottom:0;">
                        <label><?php esc_html_e( 'Album Title', 'ifsedu-school-management' ); ?> *</label>
                        <input type="text" name="title" class="dpt-field-input" value="<?php echo $album ? esc_attr( $album->title ) : ''; ?>" required>
                    </div>
                    <div class="dpt-form-group" style="margin-bottom:0;">
                        <label><?php esc_html_e( 'Category', 'ifsedu-school-management' ); ?></label>
                        <select name="category" class="dpt-field-select">
                            <option value="Academic" <?php selected( $album ? $album->category : '', 'Academic' ); ?>>Academic</option>
                            <option value="Sports" <?php selected( $album ? $album->category : '', 'Sports' ); ?>>Sports</option>
                            <option value="Cultural" <?php selected( $album ? $album->category : '', 'Cultural' ); ?>>Cultural</option>
                            <option value="Campus" <?php selected( $album ? $album->category : '', 'Campus' ); ?>>Campus & Infrastructure</option>
                            <option value="General" <?php selected( $album ? $album->category : '', 'General' ); ?>>General</option>
                        </select>
                    </div>
                    <div class="dpt-form-group" style="margin-bottom:0;">
                        <label><?php esc_html_e( 'Status', 'ifsedu-school-management' ); ?></label>
                        <select name="status" class="dpt-field-select">
                            <option value="Published" <?php selected( $album ? $album->status : '', 'Published' ); ?>>Published</option>
                            <option value="Draft" <?php selected( $album ? $album->status : '', 'Draft' ); ?>>Draft</option>
                        </select>
                    </div>
                </div>

                <div class="dpt-form-group">
                    <label><?php esc_html_e( 'Description', 'ifsedu-school-management' ); ?></label>
                    <textarea name="description" class="dpt-field-textarea" rows="3"><?php echo $album ? esc_textarea( $album->description ) : ''; ?></textarea>
                </div>

                <div class="dpt-form-group">
                    <label><?php esc_html_e( 'Cover Image (Thumbnail)', 'ifsedu-school-management' ); ?></label>
                    <input type="file" name="cover_image" class="dpt-field-input" accept="image/*">
                    <?php if ( $album && ! empty( $album->cover_image ) ) : ?>
                        <div style="margin-top: 10px;">
                            <img src="<?php echo esc_url( $album->cover_image ); ?>" style="width: 80px; height: 60px; object-fit: cover; border-radius: 8px; border: 1px solid #cbd5e1;">
                        </div>
                    <?php endif; ?>
                </div>

                <div class="dpt-upload-bento-node">
                    <label><?php esc_html_e( 'Upload Photos to Album', 'ifsedu-school-management' ); ?></label>
                    <input type="file" name="gallery_photos[]" class="dpt-field-input" accept="image/*" multiple>
                    <p style="margin: 6px 0 0 0; font-size: 12px; color: #047857; font-weight: 600;">
                        <?php esc_html_e( 'Select multiple files to batch upload images into this gallery.', 'ifsedu-school-management' ); ?>
                    </p>
                </div>

                <?php if ( $is_edit && ! empty( $photos ) ) : ?>
                    <div style="margin-bottom: 28px;">
                        <h4 style="font-size: 15px; font-weight: 800; color: #0f172a; padding-bottom: 10px; border-bottom: 1px solid #f1f5f9; margin: 0 0 16px 0;">
                            <?php esc_html_e( 'Manage Existing Album Photos', 'ifsedu-school-management' ); ?> (<?php echo esc_html( count( $photos ) ); ?>)
                        </h4>
                        <div class="dpt-manage-grid">
                            <?php foreach ( $photos as $photo ) : 
                                $photo_del_url = wp_nonce_url( 
                                    admin_url( 'admin.php?page=school_management_system&tab=notices&type=gallery&sub=delete_photo&photo_id=' . $photo->id . '&album_id=' . $album_id ), 
                                    'delete_photo_' . $photo->id 
                                );
                            ?>
                                <div class="dpt-manage-photo-card">
                                    <img src="<?php echo esc_url( $photo->image_url ); ?>" alt="Gallery Image">
                                    <a href="<?php echo esc_url( $photo_del_url ); ?>" class="dpt-btn-photo-del" title="<?php esc_attr_e( 'Delete Photo', 'ifsedu-school-management' ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Remove this photo?', 'ifsedu-school-management' ) ); ?>');">
                                        &times;
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <button type="submit" name="educore_save_gallery" class="dpt-btn-submit">
                    <?php echo $is_edit ? esc_html__( 'Update Album', 'ifsedu-school-management' ) : esc_html__( 'Publish Album', 'ifsedu-school-management' ); ?>
                </button>
            </form>
        </div>

    </div>
    <?php
}

function educore_gallery_photo_delete_action() {
    global $wpdb;
    $table_photos = $wpdb->prefix . 'sms_gallery_photos';

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Permission denied.', 'ifsedu-school-management' ) );
    }

    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $photo_id = isset( $_GET['photo_id'] ) ? absint( $_GET['photo_id'] ) : 0;
    $album_id = isset( $_GET['album_id'] ) ? absint( $_GET['album_id'] ) : 0;
    $del_nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    if ( $photo_id > 0 && wp_verify_nonce( $del_nonce, 'delete_photo_' . $photo_id ) ) {
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->delete( $table_photos, array( 'id' => $photo_id ), array( '%d' ) );
        // phpcs:enable
    }

    $redirect_url = admin_url( 'admin.php?page=school_management_system&tab=notices&type=gallery&sub=edit&id=' . $album_id );
    educore_safe_redirect( $redirect_url );
}

function educore_gallery_delete_action() {
    global $wpdb;
    $table_albums = $wpdb->prefix . 'sms_gallery_albums';
    $table_photos = $wpdb->prefix . 'sms_gallery_photos';

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Permission denied.', 'ifsedu-school-management' ) );
    }

    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $album_id  = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
    $del_nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    if ( $album_id > 0 && wp_verify_nonce( $del_nonce, 'delete_gallery_' . $album_id ) ) {
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->delete( $table_photos, array( 'album_id' => $album_id ), array( '%d' ) );
        $wpdb->delete( $table_albums, array( 'id' => $album_id ), array( '%d' ) );
        // phpcs:enable
    }

    $redirect_url = admin_url( 'admin.php?page=school_management_system&tab=notices&type=gallery&sub=list' );
    educore_safe_redirect( $redirect_url );
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
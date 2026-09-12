<?php
/**
 * Notice & Event Table Listing View
 * File: inc/notices/notice-list.php
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Notice & Event Table Listing View
 *
 * @param string $type Content type ('notice' or 'events').
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

    $is_event_mode = ( 'events' === $type || 'event' === $type );
    $type_slug     = $is_event_mode ? 'events' : 'notice';

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    if ( $is_event_mode ) {
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
    } else {
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
    }
    // phpcs:enable

    $records = is_array( $records ) ? $records : array();
    ?>

    <style id="ifs-educore-notice-list-styles">
        .ifs-educore-list-root {
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #0f172a;
        }

        .ifs-educore-bento-card-table {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 15px -3px rgba(0, 0, 0, 0.03);
            margin-bottom: 24px;
        }

        .ifs-educore-table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #f1f5f9;
            padding-bottom: 12px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .ifs-educore-table-title {
            margin: 0;
            font-size: 16px;
            font-weight: 800;
            color: #00523c;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .ifs-educore-count-badge {
            background: #f0fdf4;
            color: #047857;
            border: 1px solid #bbf7d0;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 800;
        }

        .ifs-educore-responsive-datatable {
            overflow-x: auto;
        }

        .ifs-educore-architecture-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 13.5px;
        }

        .ifs-educore-architecture-table th {
            padding: 12px 16px;
            color: #475569;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            font-size: 11.5px;
            text-transform: capitalize;
            font-weight: 800;
        }

        .ifs-educore-architecture-table td {
            padding: 12px 16px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        .ifs-educore-col-id { width: 6%; }
        .ifs-educore-col-banner { width: 10%; }
        .ifs-educore-col-actions { text-align: right; width: 12%; white-space: nowrap; }

        .ifs-educore-id-cell {
            font-weight: 800;
            color: #64748b;
        }

        .ifs-educore-thumb-container {
            width: 44px;
            height: 44px;
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .ifs-educore-thumb-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .ifs-educore-thumb-placeholder {
            font-size: 20px;
            color: #94a3b8;
        }

        .ifs-educore-title-main {
            color: #0f172a;
            font-size: 14px;
        }

        .ifs-educore-notice-type-tag {
            display: block;
            color: #64748b;
            font-size: 11.5px;
            margin-top: 2px;
        }

        .ifs-educore-attachment-link {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 11.5px;
            font-weight: 700;
            color: #0284c7;
            text-decoration: none;
            margin-top: 4px;
        }

        .ifs-educore-attachment-link:hover {
            text-decoration: underline;
        }

        .ifs-educore-badge-node {
            display: inline-flex;
            align-items: center;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11.5px;
            font-weight: 700;
        }

        .ifs-educore-badge-audience { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }
        .ifs-educore-priority-normal { background: #f8fafc; color: #475569; border: 1px solid #cbd5e1; }
        .ifs-educore-priority-high { background: #fff7ed; color: #d97706; border: 1px solid #fed7aa; }
        .ifs-educore-priority-urgent { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }

        .ifs-educore-status-published { background: #ecfdf5; color: #059669; border: 1px solid #bbf7d0; }
        .ifs-educore-status-draft { background: #f1f5f9; color: #64748b; border: 1px solid #cbd5e1; }

        .ifs-educore-date-cell {
            color: #475569;
            font-size: 12.5px;
            font-weight: 600;
        }

        .ifs-educore-actions-flex {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            justify-content: flex-end;
        }

        .ifs-educore-square-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.2s ease;
            border: 1px solid transparent;
        }

        .ifs-educore-square-btn .dashicons {
            font-size: 16px;
            width: 16px;
            height: 16px;
        }

        .ifs-educore-btn-view { background: #f0f9ff; color: #0284c7; border-color: #bae6fd; }
        .ifs-educore-btn-view:hover { background: #0284c7; color: #ffffff; }

        .ifs-educore-btn-edit { background: #f0fdf4; color: #16a34a; border-color: #bbf7d0; }
        .ifs-educore-btn-edit:hover { background: #16a34a; color: #ffffff; }

        .ifs-educore-btn-delete { background: #fef2f2; color: #dc2626; border-color: #fecaca; }
        .ifs-educore-btn-delete:hover { background: #dc2626; color: #ffffff; }

        .ifs-educore-empty-cell {
            text-align: center;
            color: #94a3b8;
            padding: 40px;
            font-weight: 600;
        }

        .ifs-educore-empty-icon {
            font-size: 24px;
            width: 24px;
            height: 24px;
            vertical-align: middle;
            margin-right: 6px;
        }
    </style>

    <div class="ifs-educore-list-root">
        <div class="ifs-educore-bento-card-table">
            
            <div class="ifs-educore-table-header">
                <h3 class="ifs-educore-table-title">
                    <span class="dashicons <?php echo $is_event_mode ? 'dashicons-calendar-alt' : 'dashicons-megaphone'; ?>"></span>
                    <?php echo $is_event_mode ? esc_html__( 'Academic Events Directory', 'ifsedu-school-management' ) : esc_html__( 'Official Notice Board', 'ifsedu-school-management' ); ?>
                </h3>
                <span class="ifs-educore-count-badge">
                    <?php echo esc_html( count( $records ) ); ?> <?php echo $is_event_mode ? esc_html__( 'Events', 'ifsedu-school-management' ) : esc_html__( 'Notices', 'ifsedu-school-management' ); ?>
                </span>
            </div>

            <div class="ifs-educore-responsive-datatable">
                <table class="ifs-educore-architecture-table ifs-educore-datatable">
                    <thead>
                        <tr>
                            <th class="ifs-educore-col-id">ID</th>
                            <th class="ifs-educore-col-banner"><?php esc_html_e( 'Banner', 'ifsedu-school-management' ); ?></th>
                            <th><?php esc_html_e( 'Title & Details', 'ifsedu-school-management' ); ?></th>
                            <th><?php esc_html_e( 'Target Audience', 'ifsedu-school-management' ); ?></th>
                            <th><?php echo $is_event_mode ? esc_html__( 'Event Date', 'ifsedu-school-management' ) : esc_html__( 'Publish Date', 'ifsedu-school-management' ); ?></th>
                            <th><?php esc_html_e( 'Category / Priority', 'ifsedu-school-management' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'ifsedu-school-management' ); ?></th>
                            <th class="ifs-educore-col-actions"><?php esc_html_e( 'Actions', 'ifsedu-school-management' ); ?></th>
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

                                $raw_date = ! empty( $row->event_date ) && '1970-01-01' !== $row->event_date && '0000-00-00' !== $row->event_date
                                    ? $row->event_date 
                                    : ( ! empty( $row->publish_date ) ? $row->publish_date : $row->created_at );
                                $display_date = date_i18n( 'd M Y', strtotime( $raw_date ) );

                                $priority_label = ! empty( $row->priority ) ? $row->priority : 'Normal';
                                $priority_class = 'ifs-educore-priority-normal';
                                if ( 'High' === $priority_label ) {
                                    $priority_class = 'ifs-educore-priority-high';
                                } elseif ( 'Urgent' === $priority_label ) {
                                    $priority_class = 'ifs-educore-priority-urgent';
                                }

                                $status_val     = ! empty( $row->status ) ? $row->status : 'Published';
                                $status_class   = ( 'Published' === $status_val ) ? 'ifs-educore-status-published' : 'ifs-educore-status-draft';
                                $featured_image = ! empty( $row->featured_image ) ? $row->featured_image : '';
                            ?>
                            <tr>
                                <td class="ifs-educore-id-cell">#<?php echo esc_html( $id ); ?></td>
                                
                                <td>
                                    <div class="ifs-educore-thumb-container">
                                        <?php if ( ! empty( $featured_image ) ) : ?>
                                            <a href="<?php echo esc_url( $featured_image ); ?>" target="_blank" title="<?php esc_attr_e( 'View Full Image', 'ifsedu-school-management' ); ?>">
                                                <img src="<?php echo esc_url( $featured_image ); ?>" class="ifs-educore-thumb-img" alt="Banner">
                                            </a>
                                        <?php else : ?>
                                            <span class="dashicons dashicons-format-image ifs-educore-thumb-placeholder"></span>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <td>
                                    <strong class="ifs-educore-title-main"><?php echo esc_html( $row->title ); ?></strong>
                                    <?php if ( ! empty( $row->notice_type ) && 'Notice' !== $row->notice_type && 'Event' !== $row->notice_type ) : ?>
                                        <small class="ifs-educore-notice-type-tag">[<?php echo esc_html( $row->notice_type ); ?>]</small>
                                    <?php endif; ?>
                                    <?php if ( ! empty( $row->attachment_url ) ) : ?>
                                        <br>
                                        <a href="<?php echo esc_url( $row->attachment_url ); ?>" target="_blank" class="ifs-educore-attachment-link">
                                            <span class="dashicons dashicons-paperclip"></span>
                                            <?php esc_html_e( 'Attachment Available', 'ifsedu-school-management' ); ?>
                                        </a>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <span class="ifs-educore-badge-node ifs-educore-badge-audience">
                                        <?php echo esc_html( ! empty( $row->target_audience ) ? $row->target_audience : 'All' ); ?>
                                    </span>
                                </td>

                                <td class="ifs-educore-date-cell"><?php echo esc_html( $display_date ); ?></td>

                                <td>
                                    <span class="ifs-educore-badge-node <?php echo esc_attr( $priority_class ); ?>">
                                        <?php echo esc_html( $priority_label ); ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="ifs-educore-badge-node <?php echo esc_attr( $status_class ); ?>">
                                        <?php echo esc_html( $status_val ); ?>
                                    </span>
                                </td>

                                <td class="ifs-educore-col-actions">
                                    <div class="ifs-educore-actions-flex">
                                        <a href="<?php echo esc_url( $view_url ); ?>" class="ifs-educore-square-btn ifs-educore-btn-view" title="<?php esc_attr_e( 'View', 'ifsedu-school-management' ); ?>">
                                            <span class="dashicons dashicons-visibility"></span>
                                        </a>
                                        <?php if ( $is_admin ) : ?>
                                            <a href="<?php echo esc_url( $edit_url ); ?>" class="ifs-educore-square-btn ifs-educore-btn-edit" title="<?php esc_attr_e( 'Edit', 'ifsedu-school-management' ); ?>">
                                                <span class="dashicons dashicons-edit"></span>
                                            </a>
                                            <a href="<?php echo esc_url( $delete_url ); ?>" class="ifs-educore-square-btn ifs-educore-btn-delete" title="<?php esc_attr_e( 'Delete', 'ifsedu-school-management' ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete this record?', 'ifsedu-school-management' ) ); ?>');">
                                                <span class="dashicons dashicons-trash"></span>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr>
                                <td colspan="8" class="ifs-educore-empty-cell">
                                    <span class="dashicons dashicons-info ifs-educore-empty-icon"></span>
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
        if ($.fn.DataTable && !$.fn.DataTable.isDataTable('.ifs-educore-datatable')) {
            $('.ifs-educore-datatable').DataTable({
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
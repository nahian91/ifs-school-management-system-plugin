<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Notice & Event Table Listing View
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
                <table class="ifs-educore-architecture-table educore-datatable">
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

                                $raw_date = ! empty( $row->event_date ) && $row->event_date !== '1970-01-01' && $row->event_date !== '0000-00-00'
                                    ? $row->event_date 
                                    : ( ! empty( $row->publish_date ) ? $row->publish_date : $row->created_at );
                                $display_date = date_i18n( 'd M Y', strtotime( $raw_date ) );

                                $priority_label = ! empty( $row->priority ) ? $row->priority : 'Normal';
                                $priority_class = 'ifs-educore-priority-normal';
                                if ( $priority_label === 'High' ) {
                                    $priority_class = 'ifs-educore-priority-high';
                                } elseif ( $priority_label === 'Urgent' ) {
                                    $priority_class = 'ifs-educore-priority-urgent';
                                }

                                $status_val     = ! empty( $row->status ) ? $row->status : 'Published';
                                $status_class   = ( $status_val === 'Published' ) ? 'ifs-educore-status-published' : 'ifs-educore-status-draft';
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
                                    <?php if ( ! empty( $row->notice_type ) && $row->notice_type !== 'Notice' && $row->notice_type !== 'Event' ) : ?>
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

                                <td class="ifs-educore-actions-cell">
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
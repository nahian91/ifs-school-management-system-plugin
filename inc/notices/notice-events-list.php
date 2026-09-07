<?php
/**
 * Common Listing Directory for Notices & Academic Events
 * File: inc/notices/notice-events-list.php
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function educore_notice_events_list_view( $type = 'notice' ) {
    global $wpdb;
    $table_notices = $wpdb->prefix . 'sms_notices';

    $is_admin = current_user_can( 'manage_options' );
    $is_staff = false;

    if ( class_exists( 'IFSEdu_School_Management_System' ) && method_exists( 'IFSEdu_School_Management_System', 'has_access' ) ) {
        $is_staff = IFSEdu_School_Management_System::has_access( array( 'teacher', 'instructor', 'staff' ) );
    } else {
        $is_staff = current_user_can( 'edit_posts' );
    }

    if ( ! $is_admin && ! $is_staff ) {
        wp_die( esc_html__( 'You do not have permission to access this module.', 'ifsedu-school-management' ) );
    }

    $is_event_mode = ( 'events' === $type || 'event' === $type );
    $type_slug     = $is_event_mode ? 'events' : 'notice';

    // Check which type column exists in the database
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$table_notices}`" );
    // phpcs:enable
    
    $columns = is_array( $columns ) ? $columns : array();

    $has_type_col       = in_array( 'type', $columns, true );
    $has_notice_type_col = in_array( 'notice_type', $columns, true );

    // Flexible query matching both lowercase and capitalized types ('notice', 'Notice', 'event', 'Event')
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    if ( $has_type_col ) {
        if ( $is_event_mode ) {
            $records = $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM `{$table_notices}` WHERE LOWER(type) IN (%s, %s) ORDER BY id DESC",
                'event',
                'events'
            ) );
        } else {
            $records = $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM `{$table_notices}` WHERE LOWER(type) = %s OR type = '' OR type IS NULL ORDER BY id DESC",
                'notice'
            ) );
        }
    } elseif ( $has_notice_type_col ) {
        if ( $is_event_mode ) {
            $records = $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM `{$table_notices}` WHERE LOWER(notice_type) IN (%s, %s) ORDER BY id DESC",
                'event',
                'events'
            ) );
        } else {
            $records = $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM `{$table_notices}` WHERE LOWER(notice_type) = %s OR notice_type = '' OR notice_type IS NULL ORDER BY id DESC",
                'notice'
            ) );
        }
    } else {
        $records = $wpdb->get_results( "SELECT * FROM `{$table_notices}` ORDER BY id DESC" );
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
                <span style="background:#f1f5f9; color:#475569; padding:4px 12px; border-radius:20px; font-size:12px; font-weight:700;">
                    <?php echo esc_html( count( $records ) ); ?> <?php echo $is_event_mode ? esc_html__( 'Events', 'ifsedu-school-management' ) : esc_html__( 'Notices', 'ifsedu-school-management' ); ?>
                </span>
            </div>

            <div class="ifs-educore-responsive-datatable">
                <table class="ifs-educore-architecture-table educore-datatable">
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
                                $base_admin = admin_url( 'admin.php' );
                                $view_url   = add_query_arg( array( 'page' => 'school_management_system', 'tab' => 'notices', 'type' => $type_slug, 'sub' => 'view', 'id' => $id ), $base_admin );
                                $edit_url   = add_query_arg( array( 'page' => 'school_management_system', 'tab' => 'notices', 'type' => $type_slug, 'sub' => 'edit', 'id' => $id ), $base_admin );
                                $delete_url = wp_nonce_url( 
                                    add_query_arg( array( 'page' => 'school_management_system', 'tab' => 'notices', 'type' => $type_slug, 'sub' => 'delete', 'id' => $id ), $base_admin ), 
                                    'delete_item_' . $id 
                                );

                                // Resolve date field dynamically
                                $raw_date     = ! empty( $row->publish_date ) ? $row->publish_date : ( ! empty( $row->event_date ) ? $row->event_date : ( ! empty( $row->event_start_date ) ? $row->event_start_date : $row->created_at ) );
                                $date_ts      = ! empty( $raw_date ) ? strtotime( $raw_date ) : false;
                                $display_date = $date_ts ? date_i18n( 'd M Y', $date_ts ) : '—';

                                // Category / Priority Badge
                                $priority_label = ! empty( $row->priority ) ? $row->priority : ( ! empty( $row->category ) ? $row->category : 'General' );
                                $priority_class = 'ifs-educore-priority-normal';
                                if ( in_array( $priority_label, array( 'High', 'Exam' ), true ) ) {
                                    $priority_class = 'ifs-educore-priority-high';
                                } elseif ( in_array( $priority_label, array( 'Urgent', 'Holiday' ), true ) ) {
                                    $priority_class = 'ifs-educore-priority-urgent';
                                }

                                $status_val     = ! empty( $row->status ) ? $row->status : 'Published';
                                $status_class   = ( 'Published' === $status_val ) ? 'ifs-educore-status-published' : 'ifs-educore-status-draft';
                                $featured_image = ! empty( $row->featured_image ) ? $row->featured_image : ( ! empty( $row->attachment_url ) ? $row->attachment_url : '' );
                            ?>
                            <tr>
                                <td style="font-weight: 700; color: #64748b;">#<?php echo absint( $id ); ?></td>
                                
                                <!-- Featured Image Cell -->
                                <td>
                                    <div class="ifs-educore-thumb-container">
                                        <?php if ( ! empty( $featured_image ) && preg_match( '/\.(jpg|jpeg|png|gif|webp)$/i', $featured_image ) ) : ?>
                                            <a href="<?php echo esc_url( $featured_image ); ?>" target="_blank" title="<?php esc_attr_e( 'View Full Image', 'ifsedu-school-management' ); ?>">
                                                <img src="<?php echo esc_url( $featured_image ); ?>" class="ifs-educore-thumb-img" alt="<?php esc_attr_e( 'Banner', 'ifsedu-school-management' ); ?>">
                                            </a>
                                        <?php else : ?>
                                            <span class="dashicons dashicons-format-image ifs-educore-thumb-placeholder"></span>
                                        <?php endif; ?>
                                    </div>
                                </td>

                                <td>
                                    <strong style="color: #0f172a; display: block; font-size: 14px;"><?php echo esc_html( $row->title ); ?></strong>
                                    <?php if ( ! empty( $row->event_location ) ) : ?>
                                        <small style="color: #64748b; font-weight: 600;">
                                            <span class="dashicons dashicons-location" style="font-size: 12px; width: 12px; height: 12px; vertical-align: middle;"></span>
                                            <?php echo esc_html( $row->event_location ); ?>
                                        </small>
                                    <?php endif; ?>
                                    <?php if ( ! empty( $row->attachment_url ) && ! preg_match( '/\.(jpg|jpeg|png|gif|webp)$/i', $row->attachment_url ) ) : ?>
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

                                <td style="font-weight: 600; color: #334155;"><?php echo esc_html( $display_date ); ?></td>

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

                                <td style="text-align: right;">
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
                    "search": "<?php echo esc_js( __( 'Filter Records:', 'ifsedu-school-management' ) ); ?>"
                }
            });
        }
    });
    </script>
    <?php
}

/**
 * Handle Record Deletion
 */
function educore_notice_events_delete_action( $type = 'notice' ) {
    global $wpdb;
    $table_notices = $wpdb->prefix . 'sms_notices';

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Permission denied.', 'ifsedu-school-management' ) );
    }

    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $id    = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0;
    $_nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    if ( $id > 0 && wp_verify_nonce( $_nonce, 'delete_item_' . $id ) ) {
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $wpdb->delete( $table_notices, array( 'id' => $id ), array( '%d' ) );
        // phpcs:enable
        
        if ( function_exists( 'educore_log_activity' ) ) {
            /* translators: %d: Notice/Event ID */
            educore_log_activity( sprintf( __( 'Deleted notice/event ID #%d', 'ifsedu-school-management' ), $id ) );
        }
    }

    $type_slug  = ( 'events' === $type || 'event' === $type ) ? 'events' : 'notice';
    $target_url = add_query_arg(
        array(
            'page' => 'school_management_system',
            'tab'  => 'notices',
            'type' => $type_slug,
            'sub'  => 'list',
        ),
        admin_url( 'admin.php' )
    );

    if ( function_exists( 'educore_safe_redirect' ) ) {
        educore_safe_redirect( $target_url );
    } else {
        if ( ! headers_sent() ) {
            wp_safe_redirect( $target_url );
            exit;
        } else {
            echo '<script type="text/javascript">window.location.href=' . wp_json_encode( esc_url_raw( $target_url ) ) . ';</script>';
            exit;
        }
    }
}
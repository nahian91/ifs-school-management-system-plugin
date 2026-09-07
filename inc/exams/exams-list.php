<?php
/**
 * Academic Examinations Directory List View
 * File: inc/exams/exam-list.php
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

function educore_exam_list_view() {
    global $wpdb;
    
    $table_exams = $wpdb->prefix . 'sms_exams';

    // Strict Capability Check
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to manage examination schemes.', 'ifsedu-school-management' ) );
    }

    // Dynamic Base URL
    $base_url = add_query_arg(
        array(
            'page' => 'school_management_system',
            'tab'  => 'exams',
            'sub'  => 'list',
        ),
        admin_url( 'admin.php' )
    );
    $add_url  = add_query_arg(
        array(
            'page' => 'school_management_system',
            'tab'  => 'exams',
            'sub'  => 'add',
        ),
        admin_url( 'admin.php' )
    );

    // Handle Delete Exam Action
    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $get_action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
    $get_id     = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

    if ( 'delete' === $get_action && $get_id > 0 ) {
        if ( isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'delete_exam_' . $get_id ) ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->delete( $table_exams, array( 'id' => $get_id ), array( '%d' ) );

            if ( function_exists( 'educore_log_activity' ) ) {
                /* translators: %d: Exam ID */
                educore_log_activity( sprintf( __( 'Deleted exam ID: %d', 'ifsedu-school-management' ), $get_id ) );
            }

            $redirect_target = add_query_arg( array( 'status' => 'deleted' ), $base_url );

            if ( ! headers_sent() ) {
                wp_safe_redirect( $redirect_target );
                exit;
            } else {
                echo '<script type="text/javascript">window.location.href=' . wp_json_encode( esc_url_raw( $redirect_target ) ) . ';</script>';
                exit;
            }
        }
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $exams = $wpdb->get_results( "SELECT * FROM `{$table_exams}` ORDER BY id DESC" );
    $status_msg = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
    // phpcs:enable WordPress.Security.NonceVerification.Recommended
    ?>

    <style>
        .ifs-educore-action-btn-svg.view {
            background: #f0fdf4;
            color: #047857;
            border: 1px solid #bbf7d0;
        }
        .ifs-educore-action-btn-svg.view:hover {
            background: #00523c;
            color: #ffffff;
            border-color: #00523c;
        }
    </style>

    <div class="ifs-educore-exams-root">
        
        <!-- Status Alert Notification Bar -->
        <?php if ( ! empty( $status_msg ) ) : ?>
            <div class="ifs-educore-status-banner">
                <span class="dashicons dashicons-yes-alt"></span>
                <?php 
                    if ( 'success' === $status_msg ) {
                        esc_html_e( 'New examination scheme created successfully.', 'ifsedu-school-management' );
                    } elseif ( 'updated' === $status_msg ) {
                        esc_html_e( 'Examination details updated successfully.', 'ifsedu-school-management' );
                    } elseif ( 'deleted' === $status_msg ) {
                        esc_html_e( 'Examination record removed successfully.', 'ifsedu-school-management' );
                    }
                ?>
            </div>
        <?php endif; ?>

        <!-- Full-Width Examination Table Bento Card -->
        <div class="ifs-educore-bento-card">
            <h4 class="ifs-educore-card-title">
                <span><?php esc_html_e( 'All Configured Examination Schemes', 'ifsedu-school-management' ); ?></span>
                <span style="font-size:12px; font-weight:600; color:#64748b; background:#f1f5f9; padding:3px 10px; border-radius:12px;">
                    <?php echo count( $exams ); ?> <?php esc_html_e( 'Exams Found', 'ifsedu-school-management' ); ?>
                </span>
            </h4>
            
            <div class="ifs-educore-table-responsive">
                <table class="ifs-educore-exams-table educore-datatable">
                    <thead>
                        <tr>
                            <th style="width: 28%;"><?php esc_html_e( 'Exam Name', 'ifsedu-school-management' ); ?></th>
                            <th style="width: 24%;"><?php esc_html_e( 'Target Class / Tier', 'ifsedu-school-management' ); ?></th>
                            <th style="width: 20%;"><?php esc_html_e( 'Schedule Period', 'ifsedu-school-management' ); ?></th>
                            <th style="width: 14%;"><?php esc_html_e( 'Status', 'ifsedu-school-management' ); ?></th>
                            <th style="width: 14%; text-align: right;"><?php esc_html_e( 'Actions', 'ifsedu-school-management' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( ! empty( $exams ) ) : foreach ( $exams as $exam ) : 
                            $exam_id  = absint( $exam->id );
                            
                            $view_url = add_query_arg(
                                array(
                                    'page' => 'school_management_system',
                                    'tab'  => 'exams',
                                    'sub'  => 'view',
                                    'id'   => $exam_id,
                                ),
                                admin_url( 'admin.php' )
                            );

                            $edit_url = add_query_arg(
                                array(
                                    'page'   => 'school_management_system',
                                    'tab'    => 'exams',
                                    'sub'    => 'add',
                                    'action' => 'edit',
                                    'id'     => $exam_id,
                                ),
                                admin_url( 'admin.php' )
                            );

                            $del_url  = wp_nonce_url(
                                add_query_arg(
                                    array(
                                        'page'   => 'school_management_system',
                                        'tab'    => 'exams',
                                        'sub'    => 'list',
                                        'action' => 'delete',
                                        'id'     => $exam_id,
                                    ),
                                    admin_url( 'admin.php' )
                                ),
                                'delete_exam_' . $exam_id
                            );

                            $start_timestamp = ! empty( $exam->start_date ) ? strtotime( $exam->start_date ) : false;
                            $end_timestamp   = ! empty( $exam->end_date ) ? strtotime( $exam->end_date ) : false;
                        ?>
                        <tr>
                            <td>
                                <strong style="font-size: 14px; color:#0f172a;"><?php echo esc_html( $exam->exam_name ); ?></strong>
                            </td>
                            <td>
                                <span class="ifs-educore-badge ifs-educore-badge-class"><?php echo esc_html( $exam->class_name ); ?></span>
                            </td>
                            <td>
                                <span style="color: #475569; font-weight: 600; font-size: 12.5px;">
                                    <?php 
                                    echo esc_html( $start_timestamp ? date_i18n( 'd M Y', $start_timestamp ) : '—' ); 
                                    echo ' - ';
                                    echo esc_html( $end_timestamp ? date_i18n( 'd M Y', $end_timestamp ) : '—' ); 
                                    ?>
                                </span>
                            </td>
                            <td>
                                <?php 
                                    $badge_class = 'ifs-educore-badge-upcoming';
                                    if ( 'Completed' === $exam->status ) {
                                        $badge_class = 'ifs-educore-badge-completed';
                                    } elseif ( 'Ongoing' === $exam->status ) {
                                        $badge_class = 'ifs-educore-badge-ongoing';
                                    }
                                ?>
                                <span class="ifs-educore-badge <?php echo esc_attr( $badge_class ); ?>">
                                    <?php echo esc_html( $exam->status ); ?>
                                </span>
                            </td>
                            <td style="text-align: right;">
                                <div style="display: inline-flex; gap: 6px; justify-content: flex-end;">
                                    <!-- View Details Page Button -->
                                    <a href="<?php echo esc_url( $view_url ); ?>" class="ifs-educore-action-btn-svg view" title="<?php esc_attr_e( 'View Exam Details', 'ifsedu-school-management' ); ?>">
                                        <span class="dashicons dashicons-visibility" style="font-size:16px; width:16px; height:16px;"></span>
                                    </a>

                                    <!-- Edit Button -->
                                    <a href="<?php echo esc_url( $edit_url ); ?>" class="ifs-educore-action-btn-svg edit" title="<?php esc_attr_e( 'Edit Exam', 'ifsedu-school-management' ); ?>">
                                        <span class="dashicons dashicons-edit" style="font-size:16px; width:16px; height:16px;"></span>
                                    </a>

                                    <!-- Delete Button -->
                                    <a href="<?php echo esc_url( $del_url ); ?>" class="ifs-educore-action-btn-svg delete" title="<?php esc_attr_e( 'Delete Exam', 'ifsedu-school-management' ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this exam scheme permanently?', 'ifsedu-school-management' ) ); ?>');">
                                        <span class="dashicons dashicons-trash" style="font-size:16px; width:16px; height:16px;"></span>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; else : ?>
                        <tr>
                            <td colspan="5" style="text-align:center; padding: 30px; color:#94a3b8;">
                                <?php esc_html_e( 'No examination schemes created yet.', 'ifsedu-school-management' ); ?>
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
        if ($.fn.DataTable) {
            $('.educore-datatable').DataTable({ 
                "pageLength": 15,
                "ordering": false,
                "responsive": true,
                "language": {
                    "search": "<?php echo esc_js( __( 'Search Schemes:', 'ifsedu-school-management' ) ); ?>",
                    "lengthMenu": "<?php echo esc_js( __( 'Show _MENU_ entries', 'ifsedu-school-management' ) ); ?>"
                }
            });
        }
    });
    </script>
    <?php
}
<?php
/**
 * View User Profile & Associated Records View
 * File: inc/users/user-view.php
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Render Detailed User Profile & Associated Records View
 *
 * @param string $base_url Base URL for users navigation.
 */
function ifs_educore_render_user_view( $base_url ) {
    global $wpdb;

    if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'list_users' ) && ! current_user_can( 'edit_users' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to view system users.', 'ifsedu-school-management' ) );
    }

    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $user_id = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : 0;
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    $target_user = get_userdata( $user_id );
    $back_url    = add_query_arg( array( 'page' => 'school_management_system', 'tab' => 'users', 'sub' => 'list' ), admin_url( 'admin.php' ) );

    if ( ! $target_user ) {
        echo '<div class="ifs-educore-users-root">';
        echo '<div class="ifs-educore-bento-card" style="text-align: center; padding: 40px;">';
        echo '<p style="color: #dc2626; font-weight: 600; font-size: 15px; margin-bottom: 16px;">' . esc_html__( 'Error: The requested user account could not be found.', 'ifsedu-school-management' ) . '</p>';
        echo '<a href="' . esc_url( $back_url ) . '" class="ifs-educore-btn-primary" style="display: inline-block; text-decoration: none;">&larr; ' . esc_html__( 'Back to Users Directory', 'ifsedu-school-management' ) . '</a>';
        echo '</div></div>';
        return;
    }

    $user_roles   = (array) $target_user->roles;
    $primary_role = ! empty( $user_roles ) ? reset( $user_roles ) : 'none';
    
    // Format Role Label nicely.
    $role_labels = array(
        'administrator'  => '👑 Administrator',
        'teacher'        => '👨‍🏫 Teacher',
        'accountant'     => '💼 Accountant',
        'staff'          => '👔 Office Staff / Officer',
        'governing_body' => '🏛️ Governing Body',
    );
    $display_role_label = isset( $role_labels[ $primary_role ] ) ? $role_labels[ $primary_role ] : ucfirst( $primary_role );

    // Fetch linked staff profile if exists.
    $table_staff = $wpdb->prefix . 'sms_staff';
    $table_audit = $wpdb->prefix . 'sms_audit_logs';

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $linked_staff = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table_staff}` WHERE wp_user_id = %d LIMIT 1", $user_id ) );
    
    // Fetch User Work History / Activity Logs.
    $user_audit_logs = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$table_audit}` WHERE user_id = %d ORDER BY timestamp DESC LIMIT 10", $user_id ) );
    // phpcs:enable

    // Retrieve Last Login Meta (falls back if custom tracker isn't active).
    $last_login           = get_user_meta( $user_id, 'educore_last_login', true );
    $last_login_formatted = ! empty( $last_login ) ? date_i18n( 'd M Y, h:i A', strtotime( $last_login ) ) : __( 'Never recorded', 'ifsedu-school-management' );

    $display_name_source = $target_user->display_name ? $target_user->display_name : $target_user->user_login;
    $initial             = function_exists( 'mb_substr' ) ? mb_substr( $display_name_source, 0, 1, 'UTF-8' ) : substr( $display_name_source, 0, 1 );
    $reg_time            = ! empty( $target_user->user_registered ) ? strtotime( $target_user->user_registered ) : false;
    $reg_date            = $reg_time ? date_i18n( 'd M Y, h:i A', $reg_time ) : '—';
    ?>

    <div class="ifs-educore-user-form-root">
        <div class="ifs-educore-bento-card">
            
            <!-- Profile Header Card -->
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 20px; border-bottom: 1px solid #f1f5f9; padding-bottom: 24px; margin-bottom: 30px;">
                <div style="display: flex; align-items: center; gap: 20px;">
                    <div style="width: 70px; height: 70px; border-radius: 50%; background: #f0fdf4; border: 2px solid #00523c; display: flex; align-items: center; justify-content: center; font-size: 28px; font-weight: 800; color: #00523c; overflow: hidden; flex-shrink: 0;">
                        <?php if ( $linked_staff && ! empty( $linked_staff->profile_image ) ) : ?>
                            <img src="<?php echo esc_url( $linked_staff->profile_image ); ?>" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                        <?php else : ?>
                            <?php echo esc_html( strtoupper( $initial ) ); ?>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h3 class="ifs-educore-form-title" style="margin: 0 0 6px 0;">
                            <?php echo esc_html( trim( $target_user->first_name . ' ' . $target_user->last_name ) ?: $target_user->user_login ); ?>
                        </h3>
                        <span style="display: inline-block; background: #f8fafc; border: 1px solid #e2e8f0; color: #475569; font-size: 12.5px; font-weight: 700; padding: 3px 12px; border-radius: 20px;">
                            <?php echo esc_html( $display_role_label ); ?>
                        </span>
                    </div>
                </div>

                <div>
                    <a href="<?php echo esc_url( add_query_arg( array( 'page' => 'school_management_system', 'tab' => 'users', 'sub' => 'edit', 'id' => $user_id ), admin_url( 'admin.php' ) ) ); ?>" class="ifs-educore-btn-primary" style="text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                        <span class="dashicons dashicons-edit" style="font-size: 16px; width: 16px; height: 16px;"></span>
                        <?php esc_html_e( 'Edit Profile', 'ifsedu-school-management' ); ?>
                    </a>
                </div>
            </div>

            <!-- Details Grid Form-like Layout -->
            <div class="ifs-educore-form-grid">
                
                <!-- Username -->
                <div class="ifs-educore-field-group">
                    <label class="ifs-educore-field-label"><?php esc_html_e( 'Username (Login ID)', 'ifsedu-school-management' ); ?></label>
                    <div style="padding: 10px 14px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; font-weight: 600; color: #0f172a;">
                        <?php echo esc_html( $target_user->user_login ); ?>
                    </div>
                </div>

                <!-- Email -->
                <div class="ifs-educore-field-group">
                    <label class="ifs-educore-field-label"><?php esc_html_e( 'Email Address', 'ifsedu-school-management' ); ?></label>
                    <div style="padding: 10px 14px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; font-weight: 600; color: #0f172a;">
                        <a href="mailto:<?php echo esc_attr( $target_user->user_email ); ?>" style="color: #00523c; text-decoration: none;">
                            <?php echo esc_html( $target_user->user_email ); ?>
                        </a>
                    </div>
                </div>

                <!-- First Name -->
                <div class="ifs-educore-field-group">
                    <label class="ifs-educore-field-label"><?php esc_html_e( 'First Name', 'ifsedu-school-management' ); ?></label>
                    <div style="padding: 10px 14px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; font-weight: 600; color: #0f172a;">
                        <?php echo esc_html( $target_user->first_name ?: '—' ); ?>
                    </div>
                </div>

                <!-- Last Name -->
                <div class="ifs-educore-field-group">
                    <label class="ifs-educore-field-label"><?php esc_html_e( 'Last Name', 'ifsedu-school-management' ); ?></label>
                    <div style="padding: 10px 14px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; font-weight: 600; color: #0f172a;">
                        <?php echo esc_html( $target_user->last_name ?: '—' ); ?>
                    </div>
                </div>

                <!-- Last Login -->
                <div class="ifs-educore-field-group">
                    <label class="ifs-educore-field-label"><?php esc_html_e( 'Last System Login', 'ifsedu-school-management' ); ?></label>
                    <div style="padding: 10px 14px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; font-weight: 600; color: #0f172a;">
                        <?php echo esc_html( $last_login_formatted ); ?>
                    </div>
                </div>

                <!-- Registration Date -->
                <div class="ifs-educore-field-group">
                    <label class="ifs-educore-field-label"><?php esc_html_e( 'Account Registration Date', 'ifsedu-school-management' ); ?></label>
                    <div style="padding: 10px 14px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; font-weight: 600; color: #0f172a;">
                        <?php echo esc_html( $reg_date ); ?>
                    </div>
                </div>

            </div>

            <?php if ( $linked_staff ) : ?>
                <div style="margin-top: 30px; border-top: 1px solid #f1f5f9; padding-top: 24px;">
                    <h4 style="margin: 0 0 16px 0; font-size: 15px; font-weight: 700; color: #0f172a;">
                        <?php esc_html_e( 'Linked Staff / HR Profile Details', 'ifsedu-school-management' ); ?>
                    </h4>
                    
                    <div class="ifs-educore-form-grid">
                        <div class="ifs-educore-field-group">
                            <label class="ifs-educore-field-label"><?php esc_html_e( 'Staff ID Code', 'ifsedu-school-management' ); ?></label>
                            <div style="padding: 10px 14px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; font-weight: 600; color: #0f172a;">
                                <?php echo esc_html( $linked_staff->staff_id ); ?>
                            </div>
                        </div>

                        <div class="ifs-educore-field-group">
                            <label class="ifs-educore-field-label"><?php esc_html_e( 'Designation', 'ifsedu-school-management' ); ?></label>
                            <div style="padding: 10px 14px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; font-weight: 600; color: #0f172a;">
                                <?php echo esc_html( $linked_staff->designation ); ?>
                            </div>
                        </div>

                        <div class="ifs-educore-field-group">
                            <label class="ifs-educore-field-label"><?php esc_html_e( 'Phone Number', 'ifsedu-school-management' ); ?></label>
                            <div style="padding: 10px 14px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; font-weight: 600; color: #0f172a;">
                                <?php echo esc_html( $linked_staff->phone ); ?>
                            </div>
                        </div>

                        <div class="ifs-educore-field-group">
                            <label class="ifs-educore-field-label"><?php esc_html_e( 'Employment Status', 'ifsedu-school-management' ); ?></label>
                            <div style="padding: 10px 14px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; font-weight: 600; color: #0f172a;">
                                <?php echo esc_html( $linked_staff->status ); ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Work History & IP Audit Trail Section -->
            <div style="margin-top: 30px; border-top: 1px solid #f1f5f9; padding-top: 24px;">
                <h4 style="margin: 0 0 16px 0; font-size: 15px; font-weight: 700; color: #0f172a;">
                    <?php esc_html_e( 'User Activity Work History & IP Logs', 'ifsedu-school-management' ); ?>
                </h4>

                <div class="ifs-educore-table-responsive" style="border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden;">
                    <table class="ifs-educore-users-table" style="width: 100%; border-collapse: collapse; text-align: left; font-size: 13px;">
                        <thead>
                            <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; color: #475569;">
                                <th style="padding: 12px 16px; font-weight: 700;"><?php esc_html_e( 'Action Performed', 'ifsedu-school-management' ); ?></th>
                                <th style="padding: 12px 16px; font-weight: 700; width: 160px;"><?php esc_html_e( 'IP Address', 'ifsedu-school-management' ); ?></th>
                                <th style="padding: 12px 16px; font-weight: 700; width: 180px;"><?php esc_html_e( 'Timestamp', 'ifsedu-school-management' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ( ! empty( $user_audit_logs ) ) : ?>
                                <?php foreach ( $user_audit_logs as $log ) : ?>
                                    <tr style="border-bottom: 1px solid #f1f5f9;">
                                        <td style="padding: 12px 16px; color: #0f172a; font-weight: 500;">
                                            <?php echo esc_html( $log->action_performed ); ?>
                                        </td>
                                        <td style="padding: 12px 16px; color: #64748b; font-family: monospace;">
                                            <code><?php echo esc_html( $log->ip_address ); ?></code>
                                        </td>
                                        <td style="padding: 12px 16px; color: #64748b; font-size: 12.5px;">
                                            <?php echo esc_html( date_i18n( 'd M Y, h:i A', strtotime( $log->timestamp ) ) ); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <tr>
                                    <td colspan="3" style="padding: 24px; text-align: center; color: #64748b;">
                                        <?php esc_html_e( 'No recorded work history or activity logs found for this user.', 'ifsedu-school-management' ); ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="ifs-educore-form-actions" style="margin-top: 30px; border-top: 1px solid #f1f5f9; padding-top: 20px;">
                <a href="<?php echo esc_url( $back_url ); ?>" class="ifs-educore-btn-primary" style="background: #64748b; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                    <span class="dashicons dashicons-arrow-left-alt" style="font-size: 16px; width: 16px; height: 16px;"></span>
                    <?php esc_html_e( 'Back to Users List', 'ifsedu-school-management' ); ?>
                </a>
            </div>

        </div>
    </div>
    <?php
}
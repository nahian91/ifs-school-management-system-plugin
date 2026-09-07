<?php
/**
 * Users Directory Table View
 * File: inc/users/all-users.php
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function educore_users_list_view() {
    if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'list_users' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to view system users.', 'ifsedu-school-management' ) );
    }

    $all_users = get_users( array(
        'role__in' => array( 'administrator', 'teacher', 'accountant', 'staff', 'governing_body' ),
        'orderby'  => 'registered',
        'order'    => 'DESC',
    ) );

    $base_admin_url  = admin_url( 'admin.php' );
    $current_user_id = get_current_user_id();
    $is_admin        = current_user_can( 'manage_options' );
    $is_governing    = current_user_can( 'governing_body' );
    ?>
    <div class="ifs-educore-bento-card">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; padding-bottom:14px; border-bottom:1px solid #f1f5f9;">
            <h3 style="margin:0; font-size:17px; font-weight:800; color:#0f172a;">
                <?php
                printf(
                    /* translators: %d: Total count of loaded staff, teachers, and officers */
                    esc_html__( 'Loaded Staff, Teachers & Officers (%d Total)', 'ifsedu-school-management' ),
                    count( $all_users )
                );
                ?>
            </h3>
        </div>

        <div class="ifs-educore-table-responsive">
            <table class="ifs-educore-users-table">
                <thead>
                    <tr>
                        <th style="width: 50px;"><?php esc_html_e( 'Avatar', 'ifsedu-school-management' ); ?></th>
                        <th><?php esc_html_e( 'Username', 'ifsedu-school-management' ); ?></th>
                        <th><?php esc_html_e( 'Full Name', 'ifsedu-school-management' ); ?></th>
                        <th><?php esc_html_e( 'Email Address', 'ifsedu-school-management' ); ?></th>
                        <th><?php esc_html_e( 'Assigned Role', 'ifsedu-school-management' ); ?></th>
                        <th><?php esc_html_e( 'Registered Date', 'ifsedu-school-management' ); ?></th>
                        <th style="text-align: right;"><?php esc_html_e( 'Actions', 'ifsedu-school-management' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( ! empty( $all_users ) ) : foreach ( $all_users as $user_obj ) : 
                        $user_roles      = (array) $user_obj->roles;
                        $primary_role    = ! empty( $user_roles ) ? $user_roles[0] : 'none';
                        $is_target_admin = in_array( 'administrator', $user_roles, true );
                        
                        $badge_class = 'ifs-educore-role-default';
                        if ( 'administrator' === $primary_role ) {
                            $badge_class = 'ifs-educore-role-admin';
                        } elseif ( 'teacher' === $primary_role ) {
                            $badge_class = 'ifs-educore-role-teacher';
                        } elseif ( 'accountant' === $primary_role ) {
                            $badge_class = 'ifs-educore-role-accountant';
                        } elseif ( 'staff' === $primary_role ) {
                            $badge_class = 'ifs-educore-role-staff';
                        } elseif ( 'governing_body' === $primary_role ) {
                            $badge_class = 'ifs-educore-role-governing';
                        }

                        $user_edit_url = add_query_arg( array( 'page' => 'school_management_system', 'tab' => 'users', 'sub' => 'edit', 'id' => $user_obj->ID ), $base_admin_url );
                        $user_del_url  = wp_nonce_url( 
                            add_query_arg( array( 'page' => 'school_management_system', 'tab' => 'users', 'sub' => 'delete', 'id' => $user_obj->ID ), $base_admin_url ), 
                            'delete_sms_user_' . $user_obj->ID 
                        );

                        $display_name_source = $user_obj->display_name ? $user_obj->display_name : $user_obj->user_login;
                        $initial = function_exists( 'mb_substr' ) 
                            ? mb_substr( $display_name_source, 0, 1, 'UTF-8' ) 
                            : substr( $display_name_source, 0, 1 );

                        $reg_time = ! empty( $user_obj->user_registered ) ? strtotime( $user_obj->user_registered ) : false;
                        $reg_date_formatted = $reg_time ? date_i18n( 'd M Y', $reg_time ) : '—';
                        $full_name_display = trim( (string) $user_obj->first_name . ' ' . (string) $user_obj->last_name );
                        
                        // Format role label cleanly
                        $role_label = 'governing_body' === $primary_role ? 'Governing Body' : ucfirst( $primary_role );
                    ?>
                        <tr>
                            <td>
                                <div class="ifs-educore-avatar-fallback"><?php echo esc_html( strtoupper( $initial ) ); ?></div>
                            </td>
                            <td>
                                <strong><?php echo esc_html( $user_obj->user_login ); ?></strong>
                            </td>
                            <td>
                                <?php echo esc_html( ! empty( $full_name_display ) ? $full_name_display : '—' ); ?>
                            </td>
                            <td>
                                <a href="mailto:<?php echo esc_attr( $user_obj->user_email ); ?>" style="color:#2563eb; text-decoration:none; font-weight:600;">
                                    <?php echo esc_html( $user_obj->user_email ); ?>
                                </a>
                            </td>
                            <td>
                                <span class="ifs-educore-role-badge <?php echo esc_attr( $badge_class ); ?>">
                                    <?php echo esc_html( $role_label ); ?>
                                </span>
                            </td>
                            <td>
                                <small style="color:#64748b; font-weight:600;">
                                    <?php echo esc_html( $reg_date_formatted ); ?>
                                </small>
                            </td>
                            <td style="text-align: right;">
                                <div class="ifs-educore-action-group">
                                    <?php if ( ! $is_governing ) : ?>
                                        <?php if ( ! $is_target_admin || $is_admin ) : ?>
                                            <a href="<?php echo esc_url( $user_edit_url ); ?>" class="ifs-educore-action-btn-sm edit" title="<?php esc_attr_e( 'Edit User', 'ifsedu-school-management' ); ?>">
                                                <span class="dashicons dashicons-edit"></span>
                                            </a>
                                        <?php endif; ?>

                                        <?php if ( $user_obj->ID !== $current_user_id && ( ! $is_target_admin || $is_admin ) ) : ?>
                                            <a href="<?php echo esc_url( $user_del_url ); ?>" class="ifs-educore-action-btn-sm delete" title="<?php esc_attr_e( 'Delete User', 'ifsedu-school-management' ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete this user?', 'ifsedu-school-management' ) ); ?>');">
                                                <span class="dashicons dashicons-trash"></span>
                                            </a>
                                        <?php endif; ?>
                                    <?php else : ?>
                                        <span style="color:#94a3b8; font-size:12px; font-style:italic;"><?php esc_html_e( 'View Only', 'ifsedu-school-management' ); ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; else : ?>
                        <tr>
                            <td colspan="7" style="text-align:center; padding:30px; color:#94a3b8;">
                                <?php esc_html_e( 'No registered staff, teacher or officer accounts found.', 'ifsedu-school-management' ); ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}
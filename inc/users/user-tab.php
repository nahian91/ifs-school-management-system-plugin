<?php
/**
 * Enterprise User Management & Role Administration Module (Router)
 * File: inc/users/user-tab.php
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Immediate access layer lockdown
}

function educore_users_tab() {
    if ( ! current_user_can( 'create_users' ) && ! current_user_can( 'manage_options' ) && ! current_user_can( 'edit_users' ) && ! current_user_can( 'list_users' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to manage users.', 'ifsedu-school-management' ) );
    }

    global $wpdb;
    $table_staff = $wpdb->prefix . 'sms_staff';

    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $sub_mode = isset( $_GET['sub'] ) ? sanitize_key( wp_unslash( $_GET['sub'] ) ) : 'list';
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    // Construct URLs for top submenu links using add_query_arg()
    $base_admin_url = admin_url( 'admin.php' );
    $all_users_url  = add_query_arg( array( 'page' => 'school_management_system', 'tab' => 'users', 'sub' => 'list' ), $base_admin_url );
    $add_user_url   = add_query_arg( array( 'page' => 'school_management_system', 'tab' => 'users', 'sub' => 'add' ), $base_admin_url );

    // --------------------------------------------------------------------------
    // 1. CREATE USER CUSTOM ROLES ON FIRST LOAD
    // --------------------------------------------------------------------------
    if ( ! get_role( 'teacher' ) ) {
        add_role( 'teacher', __( 'Teacher', 'ifsedu-school-management' ), array(
            'read'         => true,
            'upload_files' => true,
        ) );
    }
    if ( ! get_role( 'accountant' ) ) {
        add_role( 'accountant', __( 'Accountant', 'ifsedu-school-management' ), array(
            'read'         => true,
            'upload_files' => true,
        ) );
    }
    if ( ! get_role( 'staff' ) ) {
        add_role( 'staff', __( 'Staff / Officer', 'ifsedu-school-management' ), array(
            'read'         => true,
            'upload_files' => true,
        ) );
    }

    // --------------------------------------------------------------------------
    // 2. HANDLE DELETE ACTION
    // --------------------------------------------------------------------------
    if ( 'delete' === $sub_mode && isset( $_GET['id'] ) ) {
        $del_user_id = absint( $_GET['id'] );
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        $del_nonce   = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        if ( empty( $del_nonce ) || ! wp_verify_nonce( $del_nonce, 'delete_sms_user_' . $del_user_id ) ) {
            wp_die( esc_html__( 'Security check failed. You do not have permission to delete this user account.', 'ifsedu-school-management' ) );
        }

        $target_user = get_userdata( $del_user_id );

        // Prevent non-administrators from deleting administrator accounts
        if ( $target_user && in_array( 'administrator', (array) $target_user->roles, true ) && ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Security check failed. You cannot delete an administrator account.', 'ifsedu-school-management' ) );
        }

        if ( $del_user_id === get_current_user_id() ) {
            $redirect_url = add_query_arg( array( 'page' => 'school_management_system', 'tab' => 'users', 'msg' => 'self_delete' ), $base_admin_url );
        } else {
            require_once ABSPATH . 'wp-admin/includes/user.php';
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $wpdb->update( $table_staff, array( 'wp_user_id' => null ), array( 'wp_user_id' => $del_user_id ), array( null ), array( '%d' ) );
            // phpcs:enable
            wp_delete_user( $del_user_id );
            
            if ( function_exists( 'educore_log_activity' ) ) {
                /* translators: %d: Deleted WordPress user ID */
                educore_log_activity( sprintf( __( 'Deleted system user ID #%d', 'ifsedu-school-management' ), $del_user_id ) );
            }

            $redirect_url = add_query_arg( array( 'page' => 'school_management_system', 'tab' => 'users', 'msg' => 'deleted' ), $base_admin_url );
        }

        if ( ! headers_sent() ) {
            wp_safe_redirect( $redirect_url );
            exit;
        } else {
            echo '<script type="text/javascript">window.location.href=' . wp_json_encode( esc_url_raw( $redirect_url ) ) . ';</script>';
            exit;
        }
    }

    // --------------------------------------------------------------------------
    // 3. HANDLE ADD / EDIT USER FORM SUBMISSION
    // --------------------------------------------------------------------------
    $form_error = '';
    $req_method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';

    if ( 'POST' === $req_method && isset( $_POST['educore_save_user_btn'] ) ) {
        if ( ! isset( $_POST['ifs_educore_user_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ifs_educore_user_nonce'] ) ), 'save_user_action' ) ) {
            $form_error = __( 'Security verification failed. Please refresh and try again.', 'ifsedu-school-management' );
        } else {
            $username   = isset( $_POST['user_login'] ) ? sanitize_user( wp_unslash( $_POST['user_login'] ), true ) : '';
            $first_name = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
            $last_name  = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';
            $email      = isset( $_POST['user_email'] ) ? sanitize_email( wp_unslash( $_POST['user_email'] ) ) : '';
            
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $pass1      = isset( $_POST['pass1'] ) ? (string) wp_unslash( $_POST['pass1'] ) : '';
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $pass2      = isset( $_POST['pass2'] ) ? (string) wp_unslash( $_POST['pass2'] ) : '';
            $role       = isset( $_POST['role'] ) ? sanitize_key( wp_unslash( $_POST['role'] ) ) : 'teacher';
            $staff_link = isset( $_POST['staff_link_id'] ) ? absint( $_POST['staff_link_id'] ) : 0;
            $edit_id    = isset( $_POST['edit_user_id'] ) ? absint( $_POST['edit_user_id'] ) : 0;

            $allowed_roles = array( 'teacher', 'accountant', 'staff' );
            if ( current_user_can( 'manage_options' ) ) {
                $allowed_roles[] = 'administrator';
            }

            if ( ! in_array( $role, $allowed_roles, true ) ) {
                $role = 'teacher';
            }

            if ( ! is_email( $email ) ) {
                $form_error = __( 'Invalid email address provided.', 'ifsedu-school-management' );
            } elseif ( 0 === $edit_id && empty( $username ) ) {
                $form_error = __( 'Username is required.', 'ifsedu-school-management' );
            } elseif ( 0 === $edit_id && username_exists( $username ) ) {
                $form_error = __( 'This username is already registered. Please choose another.', 'ifsedu-school-management' );
            } elseif ( email_exists( $email ) && ( 0 === $edit_id || intval( email_exists( $email ) ) !== $edit_id ) ) {
                $form_error = __( 'This email address is already assigned to an existing user.', 'ifsedu-school-management' );
            } elseif ( ( 0 === $edit_id || ! empty( $pass1 ) ) && ( $pass1 !== $pass2 ) ) {
                $form_error = __( 'Passwords do not match. Please re-type both password fields.', 'ifsedu-school-management' );
            } elseif ( 0 === $edit_id && strlen( $pass1 ) < 6 ) {
                $form_error = __( 'Password must be at least 6 characters long.', 'ifsedu-school-management' );
            } else {
                $user_args = array(
                    'first_name'   => $first_name,
                    'last_name'    => $last_name,
                    'display_name' => trim( $first_name . ' ' . $last_name ) ?: ( $edit_id ? '' : $username ),
                    'user_email'   => $email,
                    'role'         => $role,
                );

                if ( ! empty( $pass1 ) ) {
                    $user_args['user_pass'] = $pass1;
                }

                if ( $edit_id > 0 ) {
                    $user_args['ID'] = $edit_id;
                    $updated_user_id = wp_update_user( $user_args );

                    if ( ! is_wp_error( $updated_user_id ) ) {
                        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                        $wpdb->update( $table_staff, array( 'wp_user_id' => null ), array( 'wp_user_id' => $edit_id ), array( null ), array( '%d' ) );
                        if ( $staff_link > 0 ) {
                            $wpdb->update( $table_staff, array( 'wp_user_id' => $edit_id ), array( 'id' => $staff_link ), array( '%d' ), array( '%d' ) );
                        }
                        // phpcs:enable

                        if ( function_exists( 'educore_log_activity' ) ) {
                            /* translators: %d: Updated user ID */
                            educore_log_activity( sprintf( __( 'Updated system user account ID #%d', 'ifsedu-school-management' ), $edit_id ) );
                        }

                        $redirect_url = add_query_arg( array( 'page' => 'school_management_system', 'tab' => 'users', 'msg' => 'updated' ), $base_admin_url );
                        if ( ! headers_sent() ) {
                            wp_safe_redirect( $redirect_url );
                            exit;
                        } else {
                            echo '<script type="text/javascript">window.location.href=' . wp_json_encode( esc_url_raw( $redirect_url ) ) . ';</script>';
                            exit;
                        }
                    } else {
                        $form_error = $updated_user_id->get_error_message();
                    }
                } else {
                    $user_args['user_login'] = $username;
                    $new_user_id = wp_insert_user( $user_args );

                    if ( ! is_wp_error( $new_user_id ) ) {
                        if ( $staff_link > 0 ) {
                            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                            $wpdb->update( $table_staff, array( 'wp_user_id' => $new_user_id ), array( 'id' => $staff_link ), array( '%d' ), array( '%d' ) );
                            // phpcs:enable
                        }

                        if ( function_exists( 'educore_log_activity' ) ) {
                            /* translators: 1: Username, 2: Role */
                            educore_log_activity( sprintf( __( 'Created new user: %1$s with role [%2$s]', 'ifsedu-school-management' ), $username, $role ) );
                        }

                        $redirect_url = add_query_arg( array( 'page' => 'school_management_system', 'tab' => 'users', 'msg' => 'created' ), $base_admin_url );
                        if ( ! headers_sent() ) {
                            wp_safe_redirect( $redirect_url );
                            exit;
                        } else {
                            echo '<script type="text/javascript">window.location.href=' . wp_json_encode( esc_url_raw( $redirect_url ) ) . ';</script>';
                            exit;
                        }
                    } else {
                        $form_error = $new_user_id->get_error_message();
                    }
                }
            }
        }
    }

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $all_staff_members = $wpdb->get_results( "SELECT id, full_name, designation, phone, email, wp_user_id FROM `{$table_staff}` WHERE status = 'Active' ORDER BY full_name ASC" );
    // phpcs:enable
    ?>

    <style id="ifs-educore-users-tab-styles">
        .ifs-educore-users-nav-root {
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #0f172a;
        }

        .ifs-educore-top-nav-wrapper {
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

        .ifs-educore-nav-button-group {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .ifs-educore-nav-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 18px;
            border-radius: 10px;
            font-size: 13.5px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.2s ease-in-out;
            border: 1px solid transparent;
        }

        .ifs-educore-nav-link .dashicons {
            font-size: 18px;
            width: 18px;
            height: 18px;
        }

        .ifs-educore-nav-link-inactive {
            color: #64748b;
            background: #f8fafc;
            border-color: #e2e8f0;
        }

        .ifs-educore-nav-link-inactive:hover {
            color: #00523c;
            background: #f0fdf4;
            border-color: #a7f3d0;
        }

        .ifs-educore-nav-link-active {
            color: #ffffff !important;
            background: #00523c !important;
            border-color: #00523c !important;
            box-shadow: 0 4px 12px rgba(0, 106, 78, 0.2);
        }

        .ifs-educore-nav-link-active .dashicons {
            color: #a7f3d0 !important;
        }

        .ifs-educore-context-badge {
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            color: #475569;
            font-size: 12px;
            font-weight: 700;
            padding: 5px 12px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .ifs-educore-context-badge .dashicons {
            font-size: 14px;
            width: 14px;
            height: 14px;
            vertical-align: middle;
        }

        .ifs-educore-feedback-alert {
            padding: 14px 18px;
            border-radius: 10px;
            font-size: 13.5px;
            font-weight: 600;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .ifs-educore-feedback-alert.error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #b91c1c;
        }

        .ifs-educore-feedback-alert.success {
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            color: #047857;
        }

        .ifs-educore-feedback-alert.info {
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            color: #0369a1;
        }

        .ifs-educore-module-viewport-container {
            width: 100%;
        }

        @media print {
            .no-print { display: none !important; }
        }
    </style>

    <div class="ifs-educore-users-nav-root">
        <div class="ifs-educore-top-nav-wrapper no-print">
            <div class="ifs-educore-nav-button-group">
                <a href="<?php echo esc_url( $all_users_url ); ?>" 
                   class="ifs-educore-nav-link <?php echo ( 'list' === $sub_mode || 'edit' === $sub_mode || 'view' === $sub_mode ) ? 'ifs-educore-nav-link-active' : 'ifs-educore-nav-link-inactive'; ?>">
                    <span class="dashicons dashicons-admin-users"></span> <?php esc_html_e( 'All Users', 'ifsedu-school-management' ); ?>
                </a>
                
                <a href="<?php echo esc_url( $add_user_url ); ?>" 
                   class="ifs-educore-nav-link <?php echo ( 'add' === $sub_mode ) ? 'ifs-educore-nav-link-active' : 'ifs-educore-nav-link-inactive'; ?>">
                    <span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e( 'Add New User', 'ifsedu-school-management' ); ?>
                </a>
            </div>

            <?php if ( 'edit' === $sub_mode ) : ?>
                <div>
                    <span class="ifs-educore-context-badge">
                        <span class="dashicons dashicons-edit"></span>
                        <?php esc_html_e( 'Editing User Record', 'ifsedu-school-management' ); ?>
                    </span>
                </div>
            <?php elseif ( 'view' === $sub_mode ) : ?>
                <div>
                    <span class="ifs-educore-context-badge">
                        <span class="dashicons dashicons-visibility"></span>
                        <?php esc_html_e( 'Viewing User Profile', 'ifsedu-school-management' ); ?>
                    </span>
                </div>
            <?php endif; ?>
        </div>

        <?php if ( ! empty( $form_error ) ) : ?>
            <div class="ifs-educore-feedback-alert error">
                <span class="dashicons dashicons-warning"></span>
                <span><?php echo esc_html( $form_error ); ?></span>
            </div>
        <?php endif; ?>

        <?php
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        if ( isset( $_GET['msg'] ) ) : 
            $msg = sanitize_key( wp_unslash( $_GET['msg'] ) );
        ?>
            <?php if ( 'created' === $msg ) : ?>
                <div class="ifs-educore-feedback-alert success">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <span><?php esc_html_e( 'New user account created successfully with assigned role.', 'ifsedu-school-management' ); ?></span>
                </div>
            <?php elseif ( 'updated' === $msg ) : ?>
                <div class="ifs-educore-feedback-alert info">
                    <span class="dashicons dashicons-saved"></span>
                    <span><?php esc_html_e( 'User credentials and roles updated successfully.', 'ifsedu-school-management' ); ?></span>
                </div>
            <?php elseif ( 'deleted' === $msg ) : ?>
                <div class="ifs-educore-feedback-alert error">
                    <span class="dashicons dashicons-trash"></span>
                    <span><?php esc_html_e( 'User account has been deleted permanently.', 'ifsedu-school-management' ); ?></span>
                </div>
            <?php elseif ( 'self_delete' === $msg ) : ?>
                <div class="ifs-educore-feedback-alert error">
                    <span class="dashicons dashicons-warning"></span>
                    <span><?php esc_html_e( 'Security Alert: You cannot delete your own logged-in user account.', 'ifsedu-school-management' ); ?></span>
                </div>
            <?php endif; ?>
        <?php 
        endif;
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        ?>

        <div class="ifs-educore-module-viewport-container">
            <?php
            if ( 'add' === $sub_mode || 'edit' === $sub_mode ) {
                if ( function_exists( 'educore_user_add_edit_view' ) ) {
                    educore_user_add_edit_view( $sub_mode, $all_staff_members, $table_staff );
                }
            } elseif ( 'view' === $sub_mode ) {
                if ( function_exists( 'ifs_educore_render_user_view' ) ) {
                    ifs_educore_render_user_view( 
                        add_query_arg( array( 'page' => 'school_management_system', 'tab' => 'users' ), $base_admin_url ) 
                    );
                }
            } else {
                if ( function_exists( 'educore_users_list_view' ) ) {
                    educore_users_list_view();
                }
            }
            ?>
        </div>
    </div>
    <?php
}
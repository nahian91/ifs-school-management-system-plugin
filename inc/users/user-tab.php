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
    if ( ! current_user_can( 'create_users' ) && ! current_user_can( 'manage_options' ) && ! current_user_can( 'edit_users' ) ) {
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

    <div class="ifs-educore-users-nav-root">
        <div class="ifs-educore-top-nav-wrapper no-print">
            <div class="ifs-educore-nav-button-group">
                <a href="<?php echo esc_url( $all_users_url ); ?>" 
                   class="ifs-educore-nav-link <?php echo ( 'list' === $sub_mode || 'edit' === $sub_mode ) ? 'ifs-educore-nav-link-active' : 'ifs-educore-nav-link-inactive'; ?>">
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
                        <span class="dashicons dashicons-edit" style="font-size:14px; width:14px; height:14px;"></span>
                        <?php esc_html_e( 'Editing User Record', 'ifsedu-school-management' ); ?>
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
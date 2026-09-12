<?php
/**
 * Role-Based Permissions & Module Access Matrix Settings Module
 * File: inc/settings/permissions.php
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Render Role Permissions Matrix View & Handle Form Submission
 *
 * @param string $base_url Base URL for settings subtabs.
 */
function educore_render_settings_permissions_view( $base_url ) {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient administrative permissions to manage role access permissions.', 'ifsedu-school-management' ) );
    }

    $settings_updated = false;

    $system_modules = array(
        'dashboard'  => __( 'Dashboard & Overview', 'ifsedu-school-management' ),
        'students'   => __( 'Student Admission & Profiles', 'ifsedu-school-management' ),
        'academics'  => __( 'Academic Setup', 'ifsedu-school-management' ),
        'attendance' => __( 'Attendance Engine', 'ifsedu-school-management' ),
        'exams'      => __( 'Exams, Grading & Marksheet', 'ifsedu-school-management' ),
        'fees'       => __( 'Fee Collection & Invoicing Ledger', 'ifsedu-school-management' ),
        'accounts'   => __( 'Accounts & Financial Ledger', 'ifsedu-school-management' ),
        'staff'      => __( 'Staff / Teacher Directory', 'ifsedu-school-management' ),
        'notices'    => __( 'Notices & Academic Events', 'ifsedu-school-management' ),
        'reports'    => __( 'Analytics & System Reports', 'ifsedu-school-management' ),
    );

    $configurable_roles = array(
        'teacher'        => __( 'Teacher', 'ifsedu-school-management' ),
        'accountant'     => __( 'Accountant', 'ifsedu-school-management' ),
        'staff'          => __( 'Staff', 'ifsedu-school-management' ),
        'governing_body' => __( 'Director', 'ifsedu-school-management' ),
    );

    $req_method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';

    if ( 'POST' === $req_method && isset( $_POST['educore_save_permissions'] ) ) {
        if ( ! isset( $_POST['educore_permissions_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['educore_permissions_nonce'] ) ), 'save_permissions_action' ) ) {
            wp_die( esc_html__( 'Security check failed.', 'ifsedu-school-management' ) );
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $raw_permissions = ( isset( $_POST['educore_role_permissions'] ) && is_array( $_POST['educore_role_permissions'] ) ) ? wp_unslash( $_POST['educore_role_permissions'] ) : array();

        $sanitized_permissions = array();

        foreach ( $configurable_roles as $role_key => $role_name ) {
            $sanitized_permissions[ $role_key ] = array();
            if ( isset( $raw_permissions[ $role_key ] ) && is_array( $raw_permissions[ $role_key ] ) ) {
                $clean_role_modules = array_map( 'sanitize_key', $raw_permissions[ $role_key ] );
                foreach ( array_keys( $system_modules ) as $mod_key ) {
                    if ( in_array( $mod_key, $clean_role_modules, true ) ) {
                        $sanitized_permissions[ $role_key ][] = $mod_key;
                    }
                }
            }
        }

        update_option( 'educore_role_permissions', $sanitized_permissions );

        if ( function_exists( 'educore_log_activity' ) ) {
            educore_log_activity( __( 'Updated role-based module access control matrix.', 'ifsedu-school-management' ) );
        }

        $settings_updated = true;
    }

    $saved_permissions = get_option( 'educore_role_permissions', array() );
    $saved_permissions = is_array( $saved_permissions ) ? $saved_permissions : array();
    ?>

    <style id="ifs-educore-permissions-styles">
        .ifs-educore-permissions-root {
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #0f172a;
        }

        .ifs-educore-alert {
            background: #ecfdf5;
            border-left: 4px solid #10b981;
            color: #065f46;
            padding: 12px 18px;
            border-radius: 8px;
            font-weight: 700;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .ifs-educore-settings-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.03);
            margin-bottom: 30px;
        }

        .ifs-educore-perm-table-responsive {
            overflow-x: auto;
        }

        .ifs-educore-perm-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 13.5px;
        }

        .ifs-educore-perm-table th,
        .ifs-educore-perm-table td {
            padding: 14px 16px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        .ifs-educore-perm-table th {
            background: #f8fafc;
            color: #475569;
            font-size: 11.5px;
            font-weight: 800;
            text-transform: capitalize;
            border-bottom: 1px solid #e2e8f0;
            text-align: center;
        }

        .ifs-educore-perm-table th.ifs-educore-module-col,
        .ifs-educore-perm-table td.ifs-educore-module-col {
            text-align: left;
            width: 35%;
        }

        .ifs-educore-perm-table td:not(.ifs-educore-module-col) {
            text-align: center;
        }

        .ifs-educore-perm-module-icon {
            color: #00523c;
            font-size: 16px;
            width: 16px;
            height: 16px;
            vertical-align: middle;
            margin-right: 6px;
        }

        /* Custom Toggle Switch styling */
        .ifs-educore-switch {
            position: relative;
            display: inline-block;
            width: 44px;
            height: 24px;
        }

        .ifs-educore-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .ifs-educore-switch-slider {
            position: absolute;
            cursor: pointer;
            inset: 0;
            background-color: #cbd5e1;
            transition: .2s;
            border-radius: 24px;
        }

        .ifs-educore-switch-slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .2s;
            border-radius: 50%;
        }

        .ifs-educore-switch input:checked + .ifs-educore-switch-slider {
            background-color: #00523c;
        }

        .ifs-educore-switch input:checked + .ifs-educore-switch-slider:before {
            transform: translateX(20px);
        }

        .ifs-educore-form-actions {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: flex-end;
        }

        .ifs-educore-btn-submit {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            height: 42px;
            padding: 0 24px;
            background: #00523c;
            color: #ffffff;
            font-size: 13.5px;
            font-weight: 800;
            border-radius: 10px;
            border: none;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0, 82, 60, 0.25);
            transition: all 0.2s ease;
        }

        .ifs-educore-btn-submit:hover {
            background: #003e2d;
        }
    </style>

    <div class="ifs-educore-permissions-root">
        <?php if ( $settings_updated ) : ?>
            <div class="ifs-educore-alert">
                <span class="dashicons dashicons-yes-alt"></span>
                <?php esc_html_e( 'Permissions updated successfully.', 'ifsedu-school-management' ); ?>
            </div>
        <?php endif; ?>

        <div class="ifs-educore-settings-card">
            <form method="POST" action="">
                <?php wp_nonce_field( 'save_permissions_action', 'educore_permissions_nonce' ); ?>

                <div class="ifs-educore-perm-table-responsive">
                    <table class="ifs-educore-perm-table">
                        <thead>
                            <tr>
                                <th class="ifs-educore-module-col"><?php esc_html_e( 'System Module / Feature', 'ifsedu-school-management' ); ?></th>
                                <?php foreach ( $configurable_roles as $role_key => $role_name ) : ?>
                                    <th><?php echo esc_html( $role_name ); ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $system_modules as $mod_key => $mod_name ) : ?>
                                <tr>
                                    <td class="ifs-educore-module-col">
                                        <span class="dashicons dashicons-arrow-right-alt2 ifs-educore-perm-module-icon"></span>
                                        <?php echo esc_html( $mod_name ); ?>
                                    </td>
                                    <?php foreach ( $configurable_roles as $role_key => $role_name ) : 
                                        $is_allowed = isset( $saved_permissions[ $role_key ] ) && is_array( $saved_permissions[ $role_key ] ) && in_array( $mod_key, $saved_permissions[ $role_key ], true );
                                    ?>
                                        <td>
                                            <label class="ifs-educore-switch">
                                                <input type="checkbox" name="educore_role_permissions[<?php echo esc_attr( $role_key ); ?>][]" value="<?php echo esc_attr( $mod_key ); ?>" <?php checked( $is_allowed, true ); ?>>
                                                <span class="ifs-educore-switch-slider"></span>
                                            </label>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="ifs-educore-form-actions">
                    <button type="submit" name="educore_save_permissions" class="ifs-educore-btn-submit">
                        <span class="dashicons dashicons-saved"></span>
                        <?php esc_html_e( 'Save Access Matrix', 'ifsedu-school-management' ); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php
}
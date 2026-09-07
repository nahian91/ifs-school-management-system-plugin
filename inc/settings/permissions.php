<?php
/**
 * Role-Based Permissions & Module Access Matrix Settings Module
 * File: inc/settings/permissions.php
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function educore_render_settings_permissions_view( $base_url ) {
    $settings_updated = false;

    $system_modules = array(
        'dashboard'  => __( 'Dashboard & Overview', 'ifsedu-school-management' ),
        'students'   => __( 'Student Admission & Profiles', 'ifsedu-school-management' ),
        'academics'  => __( 'Academic Setup (Classes, Subjects, Routine)', 'ifsedu-school-management' ),
        'attendance' => __( 'Attendance Engine (Student & Staff)', 'ifsedu-school-management' ),
        'exams'      => __( 'Exams, Grading & Marksheet', 'ifsedu-school-management' ),
        'fees'       => __( 'Fee Collection & Invoicing Ledger', 'ifsedu-school-management' ),
        'accounts'   => __( 'Accounts & Financial Ledger', 'ifsedu-school-management' ),
        'staff'      => __( 'Staff / Teacher Directory', 'ifsedu-school-management' ),
        'notices'    => __( 'Notices & Academic Events', 'ifsedu-school-management' ),
        'reports'    => __( 'Analytics & System Reports', 'ifsedu-school-management' ),
    );

    $configurable_roles = array(
        'teacher'        => __( 'Teacher / Instructor', 'ifsedu-school-management' ),
        'accountant'     => __( 'Accountant / Cashier', 'ifsedu-school-management' ),
        'staff'          => __( 'General Staff', 'ifsedu-school-management' ),
        'governing_body' => __( 'Governing Body', 'ifsedu-school-management' ),
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
                            <th class="module-col"><?php esc_html_e( 'System Module / Feature', 'ifsedu-school-management' ); ?></th>
                            <?php foreach ( $configurable_roles as $role_key => $role_name ) : ?>
                                <th><?php echo esc_html( $role_name ); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $system_modules as $mod_key => $mod_name ) : ?>
                            <tr>
                                <td class="module-col">
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
    <?php
}
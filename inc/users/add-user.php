<?php
/**
 * Add / Edit User Form View
 * File: inc/users/add-user.php
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function educore_user_add_edit_view( $sub_mode, $all_staff_members, $table_staff ) {
    global $wpdb;

    if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'edit_users' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to manage system users.', 'ifsedu-school-management' ) );
    }

    $edit_user       = null;
    $linked_staff_id = 0;
    
    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    if ( 'edit' === $sub_mode && isset( $_GET['id'] ) ) {
        $user_id_to_edit = absint( $_GET['id'] );
        $edit_user       = get_userdata( $user_id_to_edit );

        if ( $edit_user ) {
            // Prevent non-superadmins from editing administrators if lacking capabilities
            if ( in_array( 'administrator', (array) $edit_user->roles, true ) && ! current_user_can( 'manage_options' ) ) {
                wp_die( esc_html__( 'You cannot edit administrator profiles.', 'ifsedu-school-management' ) );
            }

            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $linked_staff_obj = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM `{$table_staff}` WHERE wp_user_id = %d LIMIT 1", $edit_user->ID ) );
            // phpcs:enable
            if ( $linked_staff_obj ) {
                $linked_staff_id = intval( $linked_staff_obj->id );
            }
        }
    }
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    $current_role = ( $edit_user && ! empty( $edit_user->roles ) ) ? $edit_user->roles[0] : 'teacher';

    $staff_map_js = array();
    if ( ! empty( $all_staff_members ) && is_array( $all_staff_members ) ) {
        foreach ( $all_staff_members as $st_item ) {
            $name_parts = explode( ' ', trim( (string) $st_item->full_name ), 2 );
            $f_name     = isset( $name_parts[0] ) ? $name_parts[0] : '';
            $l_name     = isset( $name_parts[1] ) ? $name_parts[1] : '';
            
            $slug_login = '';
            if ( ! empty( $st_item->email ) && is_email( $st_item->email ) ) {
                $email_parts = explode( '@', $st_item->email );
                $slug_login  = sanitize_user( current( $email_parts ), true );
            } else {
                $slug_login  = sanitize_user( strtolower( str_replace( ' ', '_', (string) $st_item->full_name ) ), true );
            }

            $staff_map_js[ $st_item->id ] = array(
                'first_name' => $f_name,
                'last_name'  => $l_name,
                'email'      => is_email( $st_item->email ) ? sanitize_email( $st_item->email ) : '',
                'username'   => $slug_login,
            );
        }
    }
    ?>
    <style>
        .ifs-educore-password-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }
        .ifs-educore-password-wrapper input {
            padding-right: 40px !important;
        }
        .ifs-educore-toggle-password {
            position: absolute;
            right: 10px;
            background: none;
            border: none;
            color: #64748b;
            cursor: pointer;
            padding: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            outline: none;
        }
        .ifs-educore-toggle-password:hover {
            color: #00523c;
        }
    </style>

    <div class="ifs-educore-bento-card">
        <h3 style="margin:0 0 20px 0; font-size:18px; font-weight:800; color:#0f172a; border-bottom:1px solid #f1f5f9; padding-bottom:14px;">
            <?php echo $edit_user ? esc_html__( 'Edit User Profile & Credentials', 'ifsedu-school-management' ) : esc_html__( 'Register New System User', 'ifsedu-school-management' ); ?>
        </h3>

        <form method="POST" action="" id="educoreUserForm">
            <?php wp_nonce_field( 'save_user_action', 'ifs_educore_user_nonce' ); ?>
            <input type="hidden" name="edit_user_id" value="<?php echo $edit_user ? esc_attr( $edit_user->ID ) : '0'; ?>">

            <div class="ifs-educore-form-grid">
                
                <!-- Link Staff Member -->
                <div class="ifs-educore-field-group full-width" style="grid-column: span 2;">
                    <label class="ifs-educore-field-label"><?php esc_html_e( 'Link with Existing Staff / Teacher Profile (Auto-fills Profile Data)', 'ifsedu-school-management' ); ?></label>
                    <select name="staff_link_id" id="educore_staff_linker" class="ifs-educore-select">
                        <option value="0"><?php esc_html_e( '-- Choose Staff Profile to Auto-Fill --', 'ifsedu-school-management' ); ?></option>
                        <?php if ( ! empty( $all_staff_members ) && is_array( $all_staff_members ) ) : ?>
                            <?php foreach ( $all_staff_members as $st ) : ?>
                                <option value="<?php echo intval( $st->id ); ?>" <?php selected( $linked_staff_id, $st->id ); ?>>
                                    <?php echo esc_html( $st->full_name . ' (' . $st->designation . ' - ' . $st->phone . ')' ); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <!-- Username -->
                <div class="ifs-educore-field-group">
                    <label class="ifs-educore-field-label"><?php esc_html_e( 'Username (Login ID)', 'ifsedu-school-management' ); ?> <span style="color:#ef4444;">*</span></label>
                    <input type="text" name="user_login" id="educore_user_login" class="ifs-educore-input" value="<?php echo $edit_user ? esc_attr( $edit_user->user_login ) : ''; ?>" <?php echo $edit_user ? 'readonly style="background:#f1f5f9;"' : 'required'; ?> placeholder="<?php esc_attr_e( 'e.g. teacher_john', 'ifsedu-school-management' ); ?>">
                </div>

                <!-- Email -->
                <div class="ifs-educore-field-group">
                    <label class="ifs-educore-field-label"><?php esc_html_e( 'Email Address', 'ifsedu-school-management' ); ?> <span style="color:#ef4444;">*</span></label>
                    <input type="email" name="user_email" id="educore_user_email" class="ifs-educore-input" value="<?php echo $edit_user ? esc_attr( $edit_user->user_email ) : ''; ?>" placeholder="<?php esc_attr_e( 'e.g. user@school.edu.bd', 'ifsedu-school-management' ); ?>" required>
                </div>

                <!-- First Name -->
                <div class="ifs-educore-field-group">
                    <label class="ifs-educore-field-label"><?php esc_html_e( 'First Name', 'ifsedu-school-management' ); ?></label>
                    <input type="text" name="first_name" id="educore_first_name" class="ifs-educore-input" value="<?php echo $edit_user ? esc_attr( $edit_user->first_name ) : ''; ?>" placeholder="<?php esc_attr_e( 'e.g. Tanvir', 'ifsedu-school-management' ); ?>">
                </div>

                <!-- Last Name -->
                <div class="ifs-educore-field-group">
                    <label class="ifs-educore-field-label"><?php esc_html_e( 'Last Name', 'ifsedu-school-management' ); ?></label>
                    <input type="text" name="last_name" id="educore_last_name" class="ifs-educore-input" value="<?php echo $edit_user ? esc_attr( $edit_user->last_name ) : ''; ?>" placeholder="<?php esc_attr_e( 'e.g. Ahmed', 'ifsedu-school-management' ); ?>">
                </div>

                <!-- Password with Show/Hide Toggle -->
                <div class="ifs-educore-field-group">
                    <label class="ifs-educore-field-label"><?php echo $edit_user ? esc_html__( 'New Password (Leave blank to keep unchanged)', 'ifsedu-school-management' ) : esc_html__( 'Password', 'ifsedu-school-management' ) . ' <span style="color:#ef4444;">*</span>'; ?></label>
                    <div class="ifs-educore-password-wrapper">
                        <input type="password" name="pass1" id="educore_pass1" class="ifs-educore-input" <?php echo $edit_user ? '' : 'required'; ?> autocomplete="new-password">
                        <button type="button" class="ifs-educore-toggle-password" data-target="educore_pass1" title="<?php esc_attr_e( 'Toggle Password Visibility', 'ifsedu-school-management' ); ?>">
                            <span class="dashicons dashicons-visibility"></span>
                        </button>
                    </div>
                    
                    <!-- Password Strength Meter Bar -->
                    <div style="margin-top: 6px;">
                        <div style="height: 6px; width: 100%; background: #e2e8f0; border-radius: 4px; overflow: hidden;">
                            <div id="educorePasswordStrengthBar" style="height: 100%; width: 0%; transition: width 0.3s ease, background-color 0.3s ease;"></div>
                        </div>
                        <small id="educorePasswordStrengthText" style="display: block; margin-top: 4px; font-weight: 600; color: #64748b; font-size: 11.5px;"></small>
                    </div>
                </div>

                <!-- Re-type Password with Show/Hide Toggle -->
                <div class="ifs-educore-field-group">
                    <label class="ifs-educore-field-label"><?php esc_html_e( 'Re-Type Password', 'ifsedu-school-management' ); ?> <?php echo $edit_user ? '' : '<span style="color:#ef4444;">*</span>'; ?></label>
                    <div class="ifs-educore-password-wrapper">
                        <input type="password" name="pass2" id="educore_pass2" class="ifs-educore-input" <?php echo $edit_user ? '' : 'required'; ?> autocomplete="new-password">
                        <button type="button" class="ifs-educore-toggle-password" data-target="educore_pass2" title="<?php esc_attr_e( 'Toggle Password Visibility', 'ifsedu-school-management' ); ?>">
                            <span class="dashicons dashicons-visibility"></span>
                        </button>
                    </div>
                    <small id="educorePasswordMatchText" style="display: block; margin-top: 6px; font-weight: 600; font-size: 11.5px;"></small>
                </div>

                <!-- System Role Dropdown -->
                <div class="ifs-educore-field-group" style="grid-column: span 2;">
                    <label class="ifs-educore-field-label"><?php esc_html_e( 'System Staff Role & Access Level', 'ifsedu-school-management' ); ?> <span style="color:#ef4444;">*</span></label>
                    <select name="role" class="ifs-educore-select" required>
                        <?php if ( current_user_can( 'manage_options' ) ) : ?>
                            <option value="administrator" <?php selected( $current_role, 'administrator' ); ?>>👑 <?php esc_html_e( 'Administrator (Full Access & Settings)', 'ifsedu-school-management' ); ?></option>
                        <?php endif; ?>
                        <option value="teacher" <?php selected( $current_role, 'teacher' ); ?>>👨‍🏫 <?php esc_html_e( 'Teacher (Students, Attendance & Marks Matrix)', 'ifsedu-school-management' ); ?></option>
                        <option value="accountant" <?php selected( $current_role, 'accountant' ); ?>>💼 <?php esc_html_e( 'Accountant (Fees Collection & Accounting Ledger)', 'ifsedu-school-management' ); ?></option>
                        <option value="staff" <?php selected( $current_role, 'staff' ); ?>>👔 <?php esc_html_e( 'Office Staff / Officer (General Portal & Records)', 'ifsedu-school-management' ); ?></option>
                        <option value="governing_body" <?php selected( $current_role, 'governing_body' ); ?>>🏛️ <?php esc_html_e( 'Governing Body (View-Only Access to All Modules)', 'ifsedu-school-management' ); ?></option>
                    </select>
                </div>
            </div>

            <div style="margin-top:24px; text-align:right;">
                <button type="submit" name="educore_save_user_btn" id="educoreSubmitBtn" class="ifs-educore-btn-primary" style="height:44px; padding:0 32px;">
                    <span class="dashicons dashicons-saved"></span>
                    <?php echo $edit_user ? esc_html__( 'Update User Account', 'ifsedu-school-management' ) : esc_html__( 'Create User Account', 'ifsedu-school-management' ); ?>
                </button>
            </div>
        </form>
    </div>

    <!-- Auto-Fill, Password Strength & Toggle JS Engine -->
    <script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function() {
        var staffMap = <?php echo wp_json_encode( ! empty( $staff_map_js ) ? $staff_map_js : array() ); ?>;
        var linkerSelect = document.getElementById('educore_staff_linker');

        if (linkerSelect) {
            linkerSelect.addEventListener('change', function() {
                var staffId = this.value;
                if (staffId && staffMap[staffId]) {
                    var data = staffMap[staffId];

                    var firstNameInput = document.getElementById('educore_first_name');
                    var lastNameInput  = document.getElementById('educore_last_name');
                    var emailInput     = document.getElementById('educore_user_email');
                    var loginInput     = document.getElementById('educore_user_login');

                    if (firstNameInput) firstNameInput.value = data.first_name;
                    if (lastNameInput) lastNameInput.value = data.last_name;
                    if (emailInput) emailInput.value = data.email;
                    if (loginInput && !loginInput.hasAttribute('readonly')) {
                        loginInput.value = data.username;
                    }
                }
            });
        }

        // Show/Hide Password Visibility Toggle
        document.querySelectorAll('.ifs-educore-toggle-password').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var targetId = this.getAttribute('data-target');
                var inputField = document.getElementById(targetId);
                var icon = this.querySelector('.dashicons');

                if (inputField) {
                    if (inputField.type === 'password') {
                        inputField.type = 'text';
                        icon.classList.remove('dashicons-visibility');
                        icon.classList.add('dashicons-hidden');
                    } else {
                        inputField.type = 'password';
                        icon.classList.remove('dashicons-hidden');
                        icon.classList.add('dashicons-visibility');
                    }
                }
            });
        });

        // Password Strength & Match Validation Engine
        var pass1 = document.getElementById('educore_pass1');
        var pass2 = document.getElementById('educore_pass2');
        var strengthBar = document.getElementById('educorePasswordStrengthBar');
        var strengthText = document.getElementById('educorePasswordStrengthText');
        var matchText = document.getElementById('educorePasswordMatchText');
        var form = document.getElementById('educoreUserForm');

        function validatePassword() {
            var p1 = pass1.value;
            var p2 = pass2.value;

            if (!p1 && pass1.hasAttribute('required') === false) {
                strengthBar.style.width = '0%';
                strengthText.textContent = '';
                matchText.textContent = '';
                return true;
            }

            // Strength Calculation
            var score = 0;
            if (p1.length >= 6) score++;
            if (p1.length >= 10) score++;
            if (/[A-Z]/.test(p1)) score++;
            if (/[0-9]/.test(p1)) score++;
            if (/[^A-Za-z0-9]/.test(p1)) score++;

            var width = '0%';
            var color = '#cbd5e1';
            var msg = '';

            if (p1.length > 0) {
                if (score <= 2) {
                    width = '33%';
                    color = '#ef4444';
                    msg = '<?php echo esc_js( __( 'Weak Password', 'ifsedu-school-management' ) ); ?>';
                } else if (score <= 4) {
                    width = '66%';
                    color = '#f59e0b';
                    msg = '<?php echo esc_js( __( 'Medium Strength Password', 'ifsedu-school-management' ) ); ?>';
                } else {
                    width = '100%';
                    color = '#10b981';
                    msg = '<?php echo esc_js( __( 'Strong Password', 'ifsedu-school-management' ) ); ?>';
                }
            }

            strengthBar.style.width = width;
            strengthBar.style.backgroundColor = color;
            strengthText.textContent = msg;
            strengthText.style.color = color;

            // Match Check
            if (p2.length > 0) {
                if (p1 === p2) {
                    matchText.textContent = '<?php echo esc_js( __( '✓ Passwords Match', 'ifsedu-school-management' ) ); ?>';
                    matchText.style.color = '#10b981';
                    return true;
                } else {
                    matchText.textContent = '<?php echo esc_js( __( '✕ Passwords Do Not Match', 'ifsedu-school-management' ) ); ?>';
                    matchText.style.color = '#ef4444';
                    return false;
                }
            } else {
                matchText.textContent = '';
                return p1 === '';
            }
        }

        if (pass1 && pass2) {
            pass1.addEventListener('input', validatePassword);
            pass2.addEventListener('input', validatePassword);
        }

        if (form) {
            form.addEventListener('submit', function(e) {
                var p1 = pass1.value;
                var p2 = pass2.value;
                if (p1 || p2) {
                    if (p1 !== p2) {
                        alert('<?php echo esc_js( __( 'Error: Passwords do not match.', 'ifsedu-school-management' ) ); ?>');
                        e.preventDefault();
                    }
                }
            });
        }
    });
    </script>
    <?php
}
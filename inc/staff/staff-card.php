<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Lockdown direct access
}

/**
 * Enterprise Multi-Step Staff Profile & Management Engine
 * File: inc/staff/staff-id-cards.php
 * Target Table: sms_staff
 */

/**
 * AJAX Handler: Fetch Staff Names by Staff Type
 */
add_action( 'wp_ajax_ifs_educore_get_staff_names_by_type', 'ifs_educore_get_staff_names_by_type_handler' );
function ifs_educore_get_staff_names_by_type_handler() {
    check_ajax_referer( 'ifs_educore_staff_id_nonce', 'security' );

    global $wpdb;
    $table_staff = $wpdb->prefix . 'sms_staff';
    $staff_type  = isset( $_POST['staff_type'] ) ? sanitize_text_field( wp_unslash( $_POST['staff_type'] ) ) : '';

    if ( empty( $staff_type ) ) {
        wp_send_json_success( array() );
    }

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $results = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, full_name, index_no FROM `{$table_staff}` WHERE staff_type = %s ORDER BY full_name ASC",
            $staff_type
        )
    );
    // phpcs:enable

    wp_send_json_success( $results );
}

/**
 * High-End Academic Staff ID Card Printing Engine
 * Schema: {$wpdb->prefix}sms_staff
 * Dimensions: CR80 Standard (85.6mm x 53.98mm)
 */
function educore_staff_id_cards_view() {
    global $wpdb;

    $table_staff = $wpdb->prefix . 'sms_staff';

    // Check Table Existence
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_staff ) ) !== $table_staff ) {
        echo '<div style="margin:20px; padding:16px; border-radius:8px; border-left:4px solid #ef4444; background:#fef2f2; color:#991b1b; font-weight:600;">';
        echo 'Database Error: Table <code>' . esc_html( $table_staff ) . '</code> does not exist.';
        echo '</div>';
        return;
    }

    // Trigger Parameters
    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $is_loaded     = isset( $_GET['load_staff'] ) && $_GET['load_staff'] === '1';
    $selected_type = isset( $_GET['staff_type'] ) ? sanitize_text_field( wp_unslash( $_GET['staff_type'] ) ) : '';
    $selected_id   = isset( $_GET['staff_id'] ) ? absint( wp_unslash( $_GET['staff_id'] ) ) : 0;
    $search_query  = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    // Fetch Staff Types
    $staff_types = $wpdb->get_results( "SELECT DISTINCT staff_type FROM `{$table_staff}` WHERE staff_type != '' ORDER BY staff_type ASC" );

    // Pre-fetch staff list for selected staff type if reloading page
    $type_staff_members = array();
    if ( ! empty( $selected_type ) ) {
        $type_staff_members = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, full_name, index_no FROM `{$table_staff}` WHERE staff_type = %s ORDER BY full_name ASC",
                $selected_type
            )
        );
    }
    // phpcs:enable

    $staff_members = array();

    // Query executed ONLY when requested
    if ( $is_loaded || ! empty( $selected_type ) || ! empty( $search_query ) || $selected_id > 0 ) {
        $where_clauses = array( '1=1' );
        $params        = array();

        if ( ! empty( $selected_type ) ) {
            $where_clauses[] = 'staff_type = %s';
            $params[]        = $selected_type;
        }

        if ( $selected_id > 0 ) {
            $where_clauses[] = 'id = %d';
            $params[]        = $selected_id;
        }

        if ( ! empty( $search_query ) ) {
            $where_clauses[] = '(full_name LIKE %s OR designation LIKE %s OR phone LIKE %s OR index_no LIKE %s OR nid_no LIKE %s)';
            $like_s          = '%' . $wpdb->esc_like( $search_query ) . '%';
            $params[]        = $like_s;
            $params[]        = $like_s;
            $params[]        = $like_s;
            $params[]        = $like_s;
            $params[]        = $like_s;
        }

        $where_sql = implode( ' AND ', $where_clauses );
        $sql       = "SELECT * FROM `{$table_staff}` WHERE {$where_sql} ORDER BY order_number ASC, id DESC";

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        if ( ! empty( $params ) ) {
            $staff_members = $wpdb->get_results( $wpdb->prepare( $sql, ...$params ) );
        } else {
            $staff_members = $wpdb->get_results( $sql );
        }
        // phpcs:enable
    }

    // Pull Dynamic Institutional Settings
    $school_name    = get_option( 'educore_school_name', get_bloginfo( 'name' ) );
    $school_tagline = get_option( 'educore_school_tagline', '' );
    $school_logo    = get_option( 'educore_school_logo', '' );
    $principal_sig  = get_option( 'educore_principal_sig', '' );

    if ( empty( $school_name ) || 'WordPress' === $school_name ) {
        $school_name = get_bloginfo( 'name' );
    }

    $ajax_nonce = wp_create_nonce( 'ifs_educore_staff_id_nonce' );
    ?>

    <!-- Top Navigation & Control Panel -->
    <div class="ifs-educore-id-controls-card no-print">
        <form method="get" class="ifs-educore-filter-form">
            <input type="hidden" name="page" value="school_management_system">
            <input type="hidden" name="tab" value="staff">
            <input type="hidden" name="sub" value="id_card">

            <!-- Primary Filter: Staff Type -->
            <select name="staff_type" id="staff_type_select" onchange="ifsEducoreFetchStaffNames(this.value)">
                <option value="">-- All Staff Types --</option>
                <?php if ( ! empty( $staff_types ) ) : ?>
                    <?php foreach ( $staff_types as $st ) : ?>
                        <option value="<?php echo esc_attr( $st->staff_type ); ?>" <?php selected( $selected_type, $st->staff_type ); ?>>
                            <?php echo esc_html( ucfirst( $st->staff_type ) ); ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>

            <!-- Dependent Dropdown: Staff Names -->
            <select name="staff_id" id="staff_name_select" <?php echo empty( $selected_type ) ? 'disabled' : ''; ?>>
                <option value="">-- All Persons --</option>
                <?php if ( ! empty( $type_staff_members ) ) : ?>
                    <?php foreach ( $type_staff_members as $person ) : 
                        $person_id = absint( $person->id );
                    ?>
                        <option value="<?php echo absint( $person_id ); ?>" <?php selected( $selected_id, $person_id ); ?>>
                            <?php echo esc_html( $person->full_name . ( $person->index_no ? ' (' . $person->index_no . ')' : '' ) ); ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>

            <input type="text" name="s" placeholder="Search phone, index..." value="<?php echo esc_attr( $search_query ); ?>">

            <button type="submit" name="load_staff" value="1" class="ifs-educore-btn-secondary">
                <span class="dashicons dashicons-filter"></span> Filter & Load
            </button>
            
            <?php if ( ! $is_loaded && empty( $selected_type ) && empty( $search_query ) && $selected_id === 0 ) : ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=school_management_system&tab=staff&sub=id_card&load_staff=1' ) ); ?>" class="ifs-educore-btn-secondary ifs-educore-btn-load">
                    <span class="dashicons dashicons-groups"></span> Load All Staff
                </a>
            <?php else : ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=school_management_system&tab=staff&sub=id_card' ) ); ?>" class="ifs-educore-btn-secondary">
                    Clear / Reset
                </a>
            <?php endif; ?>
        </form>

        <?php if ( ! empty( $staff_members ) ) : ?>
            <div style="display: flex; gap: 10px; align-items: center;">
                <button type="button" class="ifs-educore-btn-secondary" onclick="ifsEducoreToggleSelectAll(this)">
                    <span class="dashicons dashicons-checkbox"></span> Toggle Select All
                </button>
                <button type="button" class="ifs-educore-btn-primary" onclick="window.print();">
                    <span class="dashicons dashicons-printer"></span> Print Selected Cards
                </button>
            </div>
        <?php endif; ?>
    </div>

    <!-- Cards Layout Grid -->
    <?php if ( ! empty( $staff_members ) ) : ?>
        <div class="ifs-educore-id-cards-grid" id="ifsEducoreIDCardsGrid">
            <?php foreach ( $staff_members as $staff ) : 
                $staff_internal_id = absint( $staff->id );
                $photo_url    = ! empty( $staff->profile_image ) ? $staff->profile_image : '';
                $staff_code   = ! empty( $staff->index_no ) ? $staff->index_no : 'STF-' . str_pad( (string) $staff_internal_id, 4, '0', STR_PAD_LEFT );
                
                $join_ts      = ( ! empty( $staff->joining_date ) && $staff->joining_date !== '1970-01-01' ) ? strtotime( $staff->joining_date ) : false;
                $joining_date = $join_ts ? date_i18n( 'M Y', $join_ts ) : 'N/A';
                
                $blood_group  = ! empty( $staff->blood_group ) ? $staff->blood_group : 'N/A';
                $full_name    = ! empty( $staff->full_name ) ? $staff->full_name : 'Staff Member';
                $phone        = ! empty( $staff->phone ) ? $staff->phone : 'N/A';
                ?>
                
                <div class="ifs-educore-card-wrapper" id="card-wrap-<?php echo absint( $staff_internal_id ); ?>">
                    <label class="ifs-educore-card-checkbox-label no-print">
                        <input type="checkbox" class="ifs-educore-card-select-cb" checked data-target="card-wrap-<?php echo absint( $staff_internal_id ); ?>" onchange="ifsEducoreSyncCardPrintState(this)">
                        Print Card
                    </label>

                    <div class="ifs-educore-id-card-unit">
                        <!-- Header -->
                        <div class="ifs-educore-card-header">
                            <?php if ( ! empty( $school_logo ) ) : ?>
                                <img src="<?php echo esc_url( $school_logo ); ?>" alt="Logo" class="ifs-educore-staff-logo">
                            <?php endif; ?>
                            <div class="ifs-educore-header-content-box">
                                <div class="ifs-educore-inst-name"><?php echo esc_html( $school_name ); ?></div>
                                <div class="ifs-educore-card-title"><?php echo esc_html( ! empty( $school_tagline ) ? $school_tagline : __( 'Staff Identity Card', 'ifsedu-school-management' ) ); ?></div>
                            </div>
                        </div>

                        <!-- Body -->
                        <div class="ifs-educore-card-body">
                            <div class="ifs-educore-photo-box">
                                <?php if ( $photo_url ) : ?>
                                    <img src="<?php echo esc_url( $photo_url ); ?>" alt="Staff Photo">
                                <?php else : ?>
                                    <span class="dashicons dashicons-admin-users"></span>
                                <?php endif; ?>
                            </div>

                            <div class="ifs-educore-info-box">
                                <div class="ifs-educore-info-name"><?php echo esc_html( $full_name ); ?></div>
                                <div class="ifs-educore-info-designation"><?php echo esc_html( ! empty( $staff->designation ) ? $staff->designation : 'Staff Member' ); ?></div>

                                <table class="ifs-educore-info-table">
                                    <tr>
                                        <td class="lbl">ID / Index:</td>
                                        <td class="val"><?php echo esc_html( $staff_code ); ?></td>
                                    </tr>
                                    <tr>
                                        <td class="lbl">Phone:</td>
                                        <td class="val"><?php echo esc_html( $phone ); ?></td>
                                    </tr>
                                    <tr>
                                        <td class="lbl">Joined:</td>
                                        <td class="val"><?php echo esc_html( $joining_date ); ?></td>
                                    </tr>
                                    <tr>
                                        <td class="lbl">Blood:</td>
                                        <td class="val" style="color:#dc2626; font-weight:800;"><?php echo esc_html( $blood_group ); ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="ifs-educore-card-footer">
                            <div class="ifs-educore-barcode-sim">||| | |||| | |||</div>
                            <div class="ifs-educore-sign-block">
                                <?php if ( ! empty( $principal_sig ) ) : ?>
                                    <img src="<?php echo esc_url( $principal_sig ); ?>" alt="Signature" class="ifs-educore-staff-sig-img">
                                <?php endif; ?>
                                <div class="ifs-educore-sign-line"></div>
                                <div class="ifs-educore-sign-text">Authority Sign</div>
                            </div>
                        </div>
                    </div>
                </div>

            <?php endforeach; ?>
        </div>
    <?php elseif ( $is_loaded || ! empty( $selected_type ) || ! empty( $search_query ) || $selected_id > 0 ) : ?>
        <div style="background: #ffffff; padding: 48px; border-radius: 12px; text-align: center; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <span class="dashicons dashicons-id-alt" style="font-size: 48px; width: 48px; height: 48px; color: #cbd5e1;"></span>
            <h3 style="margin: 12px 0 6px 0; color: #0f172a; font-weight:700;">No Staff Records Found</h3>
            <p style="color: #64748b; margin: 0;">No matching records were found in <code><?php echo esc_html( $table_staff ); ?></code> for your filter criteria.</p>
        </div>
    <?php else : ?>
        <!-- Idle State prior to loading -->
        <div style="background: #ffffff; padding: 50px 20px; border-radius: 12px; text-align: center; border: 1px dashed #cbd5e1;">
            <span class="dashicons dashicons-groups" style="font-size: 52px; width: 52px; height: 52px; color: #00523c; opacity: 0.8;"></span>
            <h3 style="margin: 16px 0 8px 0; color: #0f172a; font-weight:700; font-size:18px;">Staff ID Card Printing Panel</h3>
            <p style="color: #64748b; max-width: 460px; margin: 0 auto 20px auto; font-size:14px; line-height:1.5;">
                Select a staff type (e.g. Office, Teacher) to view specific names, or click <strong>Load All Staff</strong> to preview all ID cards.
            </p>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=school_management_system&tab=staff&sub=id_card&load_staff=1' ) ); ?>" class="ifs-educore-btn-primary" style="display:inline-flex;">
                <span class="dashicons dashicons-download"></span> Load Staff Records
            </a>
        </div>
    <?php endif; ?>

    <script>
        function ifsEducoreFetchStaffNames(staffType) {
            var nameSelect = document.getElementById('staff_name_select');
            
            if (!staffType) {
                nameSelect.innerHTML = '<option value="">-- All Persons --</option>';
                nameSelect.disabled = true;
                return;
            }

            nameSelect.disabled = true;
            nameSelect.innerHTML = '<option value="">Loading...</option>';

            var formData = new FormData();
            formData.append('action', 'ifs_educore_get_staff_names_by_type');
            formData.append('security', '<?php echo esc_js( $ajax_nonce ); ?>');
            formData.append('staff_type', staffType);

            fetch(ajaxurl, {
                method: 'POST',
                body: formData
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    var options = '<option value="">-- All Persons --</option>';
                    data.data.forEach(function(person) {
                        var extra = person.index_no ? ' (' + person.index_no + ')' : '';
                        options += '<option value="' + person.id + '">' + person.full_name + extra + '</option>';
                    });
                    nameSelect.innerHTML = options;
                    nameSelect.disabled = false;
                } else {
                    nameSelect.innerHTML = '<option value="">-- All Persons --</option>';
                }
            })
            .catch(function() {
                nameSelect.innerHTML = '<option value="">-- All Persons --</option>';
            });
        }

        function ifsEducoreSyncCardPrintState(cb) {
            var targetId = cb.getAttribute('data-target');
            var wrapper = document.getElementById(targetId);
            if (wrapper) {
                if (cb.checked) {
                    wrapper.classList.remove('ifs-educore-print-hide');
                } else {
                    wrapper.classList.add('ifs-educore-print-hide');
                }
            }
        }

        function ifsEducoreToggleSelectAll(btn) {
            var checkboxes = document.querySelectorAll('.ifs-educore-card-select-cb');
            var allChecked = true;

            checkboxes.forEach(function(cb) {
                if (!cb.checked) allChecked = false;
            });

            checkboxes.forEach(function(cb) {
                cb.checked = !allChecked;
                ifsEducoreSyncCardPrintState(cb);
            });
        }
    </script>
    <?php
}
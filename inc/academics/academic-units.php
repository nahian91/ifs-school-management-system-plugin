<?php
/**
 * Academic Class & Section Setup Engine
 * File: inc/academics/class-setup.php
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) { 
    exit; // Exit if accessed directly.
}

/**
 * Render Academic Class & Section Setup View & Handle Operations
 */
function educore_render_class_setup_view() {
    global $wpdb;

    // Strict Capability Check.
    if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'edit_posts' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to configure academic units.', 'ifsedu-school-management' ) );
    }

    // Build Base URL safely from current request URI without state query params.
    $base_url = add_query_arg(
        array(
            'page'   => 'school_management_system',
            'tab'    => 'academics',
            'subtab' => 'units',
        ),
        admin_url( 'admin.php' )
    );

    // --------------------------------------------------------------------------
    // 1. STATE SETUP FOR EDIT MODE (Intercept Safely)
    // --------------------------------------------------------------------------
    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $get_action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
    $get_id     = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0;
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    $is_edit  = ( 'edit_unit' === $get_action && 0 < $get_id );
    $edit_row = null;

    if ( $is_edit ) {
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $edit_row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$wpdb->prefix}sms_academic_units` WHERE id = %d LIMIT 1", $get_id ) );
        // phpcs:enable
        if ( ! $edit_row ) {
            $is_edit = false;
        }
    }

    // --------------------------------------------------------------------------
    // 2. HANDLE DELETE ACTION
    // --------------------------------------------------------------------------
    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    if ( 'delete_unit' === $get_action && 0 < $get_id ) {
        $del_nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

        if ( wp_verify_nonce( $del_nonce, 'delete_unit_action_' . $get_id ) ) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $wpdb->delete( $wpdb->prefix . 'sms_academic_units', array( 'id' => $get_id ), array( '%d' ) );
            // phpcs:enable
            
            if ( function_exists( 'educore_log_activity' ) ) {
                /* translators: %d: Academic Unit ID */
                educore_log_activity( sprintf( __( 'Deleted academic unit ID #%d', 'ifsedu-school-management' ), absint( $get_id ) ) );
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
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    // --------------------------------------------------------------------------
    // 3. HANDLE FORM SUBMIT (Add / Update)
    // --------------------------------------------------------------------------
    $req_method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
    if ( 'POST' === $req_method && isset( $_POST['save_class_row'] ) ) {
        if ( isset( $_POST['class_setup_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['class_setup_nonce'] ) ), 'class_setup_action' ) ) {
            $class_name   = isset( $_POST['class_name'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['class_name'] ) ) ) : '';
            $section_name = isset( $_POST['section_name'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['section_name'] ) ) ) : '';
            $sort_order   = isset( $_POST['sort_order'] ) ? absint( wp_unslash( $_POST['sort_order'] ) ) : 0;
            $row_id       = isset( $_POST['row_id'] ) ? absint( wp_unslash( $_POST['row_id'] ) ) : 0;

            if ( ! empty( $class_name ) ) {
                // Check Duplicate for Class + Section Combination using direct prepared queries.
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                if ( 0 < $row_id ) {
                    $is_duplicate = (int) $wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT id FROM `{$wpdb->prefix}sms_academic_units` WHERE class_name = %s AND section_name = %s AND id != %d LIMIT 1",
                            $class_name,
                            $section_name,
                            $row_id
                        )
                    );
                } else {
                    $is_duplicate = (int) $wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT id FROM `{$wpdb->prefix}sms_academic_units` WHERE class_name = %s AND section_name = %s LIMIT 1",
                            $class_name,
                            $section_name
                        )
                    );
                }
                // phpcs:enable

                if ( ! $is_duplicate ) {
                    $data = array(
                        'class_name'   => $class_name,
                        'section_name' => $section_name,
                        'sort_order'   => $sort_order,
                    );
                    $format = array( '%s', '%s', '%d' );

                    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
                    if ( 0 < $row_id ) {
                        $wpdb->update( $wpdb->prefix . 'sms_academic_units', $data, array( 'id' => $row_id ), $format, array( '%d' ) );
                        if ( function_exists( 'educore_log_activity' ) ) {
                            /* translators: 1: Class name, 2: Section name */
                            educore_log_activity( sprintf( __( 'Updated academic unit: Class %1$s (%2$s)', 'ifsedu-school-management' ), $class_name, $section_name ) );
                        }
                        $redirect_target = add_query_arg( array( 'status' => 'updated' ), $base_url );
                    } else {
                        $wpdb->insert( $wpdb->prefix . 'sms_academic_units', $data, $format );
                        if ( function_exists( 'educore_log_activity' ) ) {
                            /* translators: 1: Class name, 2: Section name */
                            educore_log_activity( sprintf( __( 'Created academic unit: Class %1$s (%2$s)', 'ifsedu-school-management' ), $class_name, $section_name ) );
                        }
                        $redirect_target = add_query_arg( array( 'status' => 'success' ), $base_url );
                    }
                    // phpcs:enable

                    if ( ! headers_sent() ) {
                        wp_safe_redirect( $redirect_target );
                        exit;
                    } else {
                        echo '<script type="text/javascript">window.location.href=' . wp_json_encode( esc_url_raw( $redirect_target ) ) . ';</script>';
                        exit;
                    }
                } else {
                    echo '<div class="ifs-educore-alert-node ifs-educore-alert-warning">' . esc_html__( 'This Class and Section combination already exists.', 'ifsedu-school-management' ) . '</div>';
                }
            }
        }
    }

    // --------------------------------------------------------------------------
    // 4. FETCH STRATEGY (Prioritize sort_order, then fallback to Natural Numeric Sorting)
    // --------------------------------------------------------------------------
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $classes = $wpdb->get_results( 
        "SELECT * FROM `{$wpdb->prefix}sms_academic_units` 
         ORDER BY sort_order ASC, CAST(class_name AS UNSIGNED) ASC, class_name ASC, section_name ASC"
    );
    // phpcs:enable

    // Natural Sorting fallback for mixed strings with equal sort_order.
    if ( ! empty( $classes ) && is_array( $classes ) ) {
        usort( $classes, function( $a, $b ) {
            $order_a = isset( $a->sort_order ) ? (int) $a->sort_order : 0;
            $order_b = isset( $b->sort_order ) ? (int) $b->sort_order : 0;

            if ( $order_a !== $order_b ) {
                return $order_a - $order_b;
            }

            $res = strnatcasecmp( (string) $a->class_name, (string) $b->class_name );
            if ( 0 === $res ) {
                return strnatcasecmp( (string) $a->section_name, (string) $b->section_name );
            }
            return $res;
        } );
    }

    // Extract Unique Class Names for the Select Dropdown Filter.
    $unique_class_names = array();
    if ( ! empty( $classes ) && is_array( $classes ) ) {
        foreach ( $classes as $cls_item ) {
            $c_val = trim( (string) $cls_item->class_name );
            if ( ! empty( $c_val ) && ! in_array( $c_val, $unique_class_names, true ) ) {
                $unique_class_names[] = $c_val;
            }
        }
    }
    ?>

    <style id="ifs-educore-class-setup-styles">
        .ifs-educore-class-setup-root {
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #0f172a;
        }

        .ifs-educore-bento-box {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 15px -3px rgba(0, 0, 0, 0.03);
            margin-bottom: 24px;
        }

        .ifs-educore-bento-subheading {
            font-size: 16px;
            font-weight: 800;
            color: #00523c;
            margin-top: 0;
            margin-bottom: 16px;
            border-bottom: 2px solid #f1f5f9;
            padding-bottom: 10px;
        }

        .ifs-educore-form-flex-wrap {
            display: flex;
            gap: 16px;
            align-items: flex-end;
            max-width: 900px;
            flex-wrap: wrap;
        }

        .ifs-educore-field-col-class {
            flex: 2;
            min-width: 200px;
        }

        .ifs-educore-field-col-section {
            flex: 2;
            min-width: 200px;
        }

        .ifs-educore-field-col-order {
            flex: 1;
            min-width: 120px;
        }

        .ifs-educore-form-label {
            display: block;
            font-size: 13px;
            font-weight: 700;
            color: #334155;
            margin-bottom: 6px;
        }

        .ifs-educore-field-input {
            width: 100%;
            padding: 8px 12px;
            border: 1.5px solid #cbd5e1;
            border-radius: 8px;
            height: 40px;
            font-size: 13.5px;
            color: #0f172a;
            background: #f8fafc;
            box-sizing: border-box;
            outline: none;
            transition: all 0.2s ease;
        }

        .ifs-educore-field-input:focus {
            border-color: #00523c;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(0, 82, 60, 0.1);
        }

        .ifs-educore-btn-action-trigger {
            background: #00523c;
            color: #fff;
            border: none;
            padding: 0 20px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 13.5px;
            cursor: pointer;
            height: 40px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 4px 12px rgba(0, 82, 60, 0.2);
            transition: background 0.2s ease;
        }

        .ifs-educore-btn-action-trigger:hover {
            background: #003e2d;
        }

        .ifs-educore-cancel-link {
            display: inline-flex;
            align-items: center;
            padding: 8px 12px;
            font-size: 13px;
            font-weight: 700;
            color: #64748b;
            text-decoration: none;
            margin-left: 8px;
        }

        .ifs-educore-cancel-link:hover {
            color: #0f172a;
        }

        .ifs-educore-header-flex-between {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 20px;
        }

        .ifs-educore-filter-select {
            height: 38px;
            border: 1.5px solid #cbd5e1;
            border-radius: 8px;
            padding: 0 12px;
            font-size: 13px;
            color: #0f172a;
            background: #ffffff;
            outline: none;
        }

        .ifs-educore-count-pill {
            background: #f0fdf4;
            color: #047857;
            border: 1px solid #bbf7d0;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 800;
        }

        .ifs-educore-responsive-datatable {
            overflow-x: auto;
        }

        .ifs-educore-architecture-table {
            width: 100%;
            border-collapse: collapse;
        }

        .ifs-educore-architecture-table th {
            background: #f8fafc;
            text-align: left;
            padding: 12px 16px;
            border-bottom: 2px solid #e2e8f0;
            color: #475569;
            font-size: 11.5px;
            text-transform: capitalize;
            font-weight: 800;
        }

        .ifs-educore-architecture-table td {
            padding: 12px 16px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
            font-size: 13.5px;
        }

        .ifs-educore-order-pill {
            background: #f1f5f9;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 700;
            color: #475569;
        }

        .ifs-educore-section-tag {
            background: #f1f5f9;
            padding: 4px 10px;
            border-radius: 6px;
            font-weight: 700;
            font-size: 12px;
            color: #475569;
        }

        .ifs-educore-na-text {
            color: #94a3b8;
            font-style: italic;
            font-weight: 600;
        }

        .ifs-educore-actions-cell {
            text-align: right;
        }

        .ifs-educore-square-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.2s ease;
            border: 1px solid transparent;
        }

        .ifs-educore-square-btn .dashicons {
            font-size: 16px;
            width: 16px;
            height: 16px;
        }

        .ifs-educore-btn-edit { background: #f0f9ff; color: #0284c7; border-color: #bae6fd; margin-right: 6px; }
        .ifs-educore-btn-edit:hover { background: #0284c7; color: #ffffff; }

        .ifs-educore-btn-delete { background: #fef2f2; color: #dc2626; border-color: #fecaca; }
        .ifs-educore-btn-delete:hover { background: #dc2626; color: #ffffff; }

        .ifs-educore-empty-cell {
            text-align: center;
            padding: 30px;
            color: #94a3b8;
            font-weight: 600;
        }

        .ifs-educore-alert-warning {
            padding: 12px 16px;
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
            border-radius: 8px;
            margin-bottom: 16px;
            font-weight: 700;
            font-size: 13px;
        }
    </style>

    <div class="ifs-educore-class-setup-root">
        <div class="ifs-educore-bento-box">
            <h5 class="ifs-educore-bento-subheading"><?php echo $is_edit ? esc_html__( 'Edit Academic Unit', 'ifsedu-school-management' ) : esc_html__( 'Add Academic Unit (Class & Section)', 'ifsedu-school-management' ); ?></h5>
            <form method="POST" action="<?php echo esc_url( $base_url ); ?>">
                <?php wp_nonce_field( 'class_setup_action', 'class_setup_nonce' ); ?>
                <input type="hidden" name="row_id" value="<?php echo esc_attr( $get_id ); ?>">
                
                <div class="ifs-educore-form-flex-wrap">
                    <div class="ifs-educore-field-col-class">
                        <label class="ifs-educore-form-label"><?php esc_html_e( 'Class Name', 'ifsedu-school-management' ); ?> <span style="color:#dc2626;">*</span></label>
                        <input type="text" name="class_name" class="ifs-educore-field-input" placeholder="<?php esc_attr_e( 'e.g. 1, 2, Class 9', 'ifsedu-school-management' ); ?>" value="<?php echo $edit_row ? esc_attr( $edit_row->class_name ) : ''; ?>" required>
                    </div>

                    <div class="ifs-educore-field-col-section">
                        <label class="ifs-educore-form-label"><?php esc_html_e( 'Section Name', 'ifsedu-school-management' ); ?></label>
                        <input type="text" name="section_name" class="ifs-educore-field-input" placeholder="<?php esc_attr_e( 'e.g. Section A, Science, Rose', 'ifsedu-school-management' ); ?>" value="<?php echo $edit_row ? esc_attr( $edit_row->section_name ) : ''; ?>">
                    </div>

                    <div class="ifs-educore-field-col-order">
                        <label class="ifs-educore-form-label"><?php esc_html_e( 'Order Class', 'ifsedu-school-management' ); ?></label>
                        <input type="number" name="sort_order" class="ifs-educore-field-input" placeholder="<?php esc_attr_e( 'e.g. 1', 'ifsedu-school-management' ); ?>" value="<?php echo ( $edit_row && isset( $edit_row->sort_order ) ) ? esc_attr( $edit_row->sort_order ) : '0'; ?>" min="0" step="1">
                    </div>

                    <div>
                        <button type="submit" name="save_class_row" class="ifs-educore-btn-action-trigger">
                            <span class="dashicons <?php echo $is_edit ? 'dashicons-edit' : 'dashicons-plus-alt2'; ?>"></span> 
                            <?php echo $is_edit ? esc_html__( 'Update Unit', 'ifsedu-school-management' ) : esc_html__( 'Add Unit', 'ifsedu-school-management' ); ?>
                        </button>
                        <?php if ( $is_edit ) : ?>
                            <a href="<?php echo esc_url( $base_url ); ?>" class="ifs-educore-cancel-link"><?php esc_html_e( 'Cancel', 'ifsedu-school-management' ); ?></a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>

        <div class="ifs-educore-bento-box">
            <div class="ifs-educore-header-flex-between">
                <h5 class="ifs-educore-bento-subheading" style="margin:0; border:none; padding:0;"><?php esc_html_e( 'Configured Academic Units', 'ifsedu-school-management' ); ?></h5>
                
                <div style="display:flex; align-items:center; gap:12px;">
                    <select id="ifs_educore_unit_class_filter" class="ifs-educore-filter-select">
                        <option value="all"><?php esc_html_e( 'All Classes Filter', 'ifsedu-school-management' ); ?></option>
                        <?php foreach ( $unique_class_names as $cname ) : ?>
                            <option value="<?php echo esc_attr( $cname ); ?>"><?php echo esc_html( $cname ); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <span class="ifs-educore-count-pill" id="ifs_educore_unit_count_pill">
                        <?php echo absint( count( $classes ) ); ?> <?php esc_html_e( 'Units Configured', 'ifsedu-school-management' ); ?>
                    </span>
                </div>
            </div>

            <div class="ifs-educore-responsive-datatable">
                <table class="ifs-educore-architecture-table" id="ifs_educore_units_table">
                    <thead>
                        <tr>
                            <th style="width: 15%;"><?php esc_html_e( 'Order', 'ifsedu-school-management' ); ?></th>
                            <th style="width: 35%;"><?php esc_html_e( 'Class Name', 'ifsedu-school-management' ); ?></th>
                            <th style="width: 30%;"><?php esc_html_e( 'Section Name', 'ifsedu-school-management' ); ?></th>
                            <th style="width: 20%; text-align: right;"><?php esc_html_e( 'Actions', 'ifsedu-school-management' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( ! empty( $classes ) ) : foreach ( $classes as $cls ) : 
                            $cls_internal_id = absint( $cls->id );
                            $edit_link       = add_query_arg( array( 'action' => 'edit_unit', 'id' => $cls_internal_id ), $base_url );
                            $delete_link     = wp_nonce_url( add_query_arg( array( 'action' => 'delete_unit', 'id' => $cls_internal_id ), $base_url ), 'delete_unit_action_' . $cls_internal_id );
                            $display_order   = isset( $cls->sort_order ) ? (int) $cls->sort_order : 0;
                        ?>
                            <tr data-class-name="<?php echo esc_attr( $cls->class_name ); ?>">
                                <td>
                                    <span class="ifs-educore-order-pill"><?php echo esc_html( $display_order ); ?></span>
                                </td>
                                <td style="font-weight: 700; color: #0f172a;"><?php echo esc_html( $cls->class_name ); ?></td>
                                <td>
                                    <?php if ( ! empty( $cls->section_name ) ) : ?>
                                        <span class="ifs-educore-section-tag"><?php echo esc_html( $cls->section_name ); ?></span>
                                    <?php else : ?>
                                        <span class="ifs-educore-na-text"><?php esc_html_e( 'N/A', 'ifsedu-school-management' ); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="ifs-educore-actions-cell">
                                    <a href="<?php echo esc_url( $edit_link ); ?>" class="ifs-educore-square-btn ifs-educore-btn-edit" title="<?php esc_attr_e( 'Edit Unit', 'ifsedu-school-management' ); ?>"><span class="dashicons dashicons-edit"></span></a>
                                    <a href="<?php echo esc_url( $delete_link ); ?>" class="ifs-educore-square-btn ifs-educore-btn-delete" title="<?php esc_attr_e( 'Delete Unit', 'ifsedu-school-management' ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this academic unit?', 'ifsedu-school-management' ) ); ?>');"><span class="dashicons dashicons-trash"></span></a>
                                </td>
                            </tr>
                        <?php endforeach; else : ?>
                            <tr><td colspan="4" class="ifs-educore-empty-cell"><?php esc_html_e( 'No academic units configured yet.', 'ifsedu-school-management' ); ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function() {
        var filterSelect = document.getElementById('ifs_educore_unit_class_filter');
        var tableBody   = document.querySelector('#ifs_educore_units_table tbody');
        var countPill   = document.getElementById('ifs_educore_unit_count_pill');

        if (filterSelect && tableBody) {
            filterSelect.addEventListener('change', function() {
                var selectedClass = this.value;
                var rows          = tableBody.querySelectorAll('tr[data-class-name]');
                var visibleCount  = 0;

                rows.forEach(function(row) {
                    var className = row.getAttribute('data-class-name');
                    if (selectedClass === 'all' || className === selectedClass) {
                        row.style.display = '';
                        visibleCount++;
                    } else {
                        row.style.display = 'none';
                    }
                });

                if (countPill) {
                    countPill.textContent = visibleCount + ' <?php echo esc_js( __( 'Units Configured', 'ifsedu-school-management' ) ); ?>';
                }
            });
        }
    });
    </script>
    <?php
}

educore_render_class_setup_view();
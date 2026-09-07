<?php
/**
 * Academic Class & Section Setup Engine
 * File: inc/academics/class-setup.php
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) { 
    exit; 
}

function educore_render_class_setup_view() {
    global $wpdb;

    // Strict Capability Check
    if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'edit_posts' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to configure academic units.', 'ifsedu-school-management' ) );
    }

    // Build Base URL safely from current request URI without state query params
    $base_url = add_query_arg(
        array(
            'page'   => 'school_management_system',
            'tab'    => 'academics',
            'subtab' => 'units',
        ),
        admin_url( 'admin.php' )
    );

    // 1. Handle Delete Action (Intercept Before Page Renders)
    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    if ( isset( $_GET['action'] ) && 'delete_unit' === $_GET['action'] && isset( $_GET['id'] ) ) {
        $delete_id = absint( wp_unslash( $_GET['id'] ) );
        $del_nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

        if ( $delete_id > 0 && wp_verify_nonce( $del_nonce, 'delete_unit_action_' . $delete_id ) ) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $wpdb->delete( $wpdb->prefix . 'sms_academic_units', array( 'id' => $delete_id ), array( '%d' ) );
            // phpcs:enable
            
            if ( function_exists( 'educore_log_activity' ) ) {
                /* translators: %d: Academic Unit ID */
                educore_log_activity( sprintf( __( 'Deleted academic unit ID #%d', 'ifsedu-school-management' ), absint( $delete_id ) ) );
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

    // 2. State Setup for Edit Mode
    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $is_edit  = isset( $_GET['action'] ) && 'edit_unit' === $_GET['action'] && isset( $_GET['id'] );
    $edit_id  = $is_edit ? absint( wp_unslash( $_GET['id'] ) ) : 0;
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $edit_row = $is_edit ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$wpdb->prefix}sms_academic_units` WHERE id = %d LIMIT 1", $edit_id ) ) : null;
    // phpcs:enable
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    // 3. Handle Form Submit (Add/Edit)
    $req_method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
    if ( 'POST' === $req_method && isset( $_POST['save_class_row'] ) ) {
        if ( isset( $_POST['class_setup_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['class_setup_nonce'] ) ), 'class_setup_action' ) ) {
            $class_name   = isset( $_POST['class_name'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['class_name'] ) ) ) : '';
            $section_name = isset( $_POST['section_name'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['section_name'] ) ) ) : '';
            $sort_order   = isset( $_POST['sort_order'] ) ? absint( wp_unslash( $_POST['sort_order'] ) ) : 0;
            $row_id       = isset( $_POST['row_id'] ) ? absint( wp_unslash( $_POST['row_id'] ) ) : 0;

            if ( ! empty( $class_name ) ) {
                // Check Duplicate for Class + Section Combination using direct prepared queries
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                if ( $row_id > 0 ) {
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
                    if ( $row_id > 0 ) {
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
                    echo '<div class="ifs-educore-alert-node ifs-educore-alert-warning" style="padding:12px 16px; background:#fef3c7; color:#92400e; border-radius:8px; margin-bottom:16px;">' . esc_html__( 'This Class and Section combination already exists.', 'ifsedu-school-management' ) . '</div>';
                }
            }
        }
    }

    // 4. Fetch Strategy (Prioritize sort_order, then fallback to Natural Numeric Sorting)
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $classes = $wpdb->get_results( 
        "SELECT * FROM `{$wpdb->prefix}sms_academic_units` 
         ORDER BY sort_order ASC, CAST(class_name AS UNSIGNED) ASC, class_name ASC, section_name ASC"
    );
    // phpcs:enable

    // Natural Sorting fallback for mixed strings with equal sort_order
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

    // Extract Unique Class Names for the Select Dropdown Filter
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

    <div class="ifs-educore-bento-box" style="background:#fff; padding:24px; border-radius:12px; border:1px solid #e2e8f0; margin-bottom:24px;">
        <h5 class="ifs-educore-bento-subheading" style="font-size:16px; font-weight:700; color:#0f172a; margin-top:0; margin-bottom:16px;"><?php echo $is_edit ? esc_html__( 'Edit Academic Unit', 'ifsedu-school-management' ) : esc_html__( 'Add Academic Unit (Class & Section)', 'ifsedu-school-management' ); ?></h5>
        <form method="POST" action="<?php echo esc_url( $base_url ); ?>">
            <?php wp_nonce_field( 'class_setup_action', 'class_setup_nonce' ); ?>
            <input type="hidden" name="row_id" value="<?php echo esc_attr( $edit_id ); ?>">
            
            <div style="display:flex; gap:16px; align-items:flex-end; max-width:900px; flex-wrap:wrap;">
                <div style="flex:2; min-width:200px;">
                    <label class="ifs-educore-form-label" style="display:block; font-size:13px; font-weight:600; color:#475569; margin-bottom:6px;"><?php esc_html_e( 'Class Name', 'ifsedu-school-management' ); ?> <span style="color:#dc2626;">*</span></label>
                    <input type="text" name="class_name" class="ifs-educore-field-input" placeholder="<?php esc_attr_e( 'e.g. 1, 2, Class 9', 'ifsedu-school-management' ); ?>" value="<?php echo $edit_row ? esc_attr( $edit_row->class_name ) : ''; ?>" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; height:38px;" required>
                </div>

                <div style="flex:2; min-width:200px;">
                    <label class="ifs-educore-form-label" style="display:block; font-size:13px; font-weight:600; color:#475569; margin-bottom:6px;"><?php esc_html_e( 'Section Name', 'ifsedu-school-management' ); ?></label>
                    <input type="text" name="section_name" class="ifs-educore-field-input" placeholder="<?php esc_attr_e( 'e.g. Section A, Science, Rose', 'ifsedu-school-management' ); ?>" value="<?php echo $edit_row ? esc_attr( $edit_row->section_name ) : ''; ?>" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; height:38px;">
                </div>

                <div style="flex:1; min-width:120px;">
                    <label class="ifs-educore-form-label" style="display:block; font-size:13px; font-weight:600; color:#475569; margin-bottom:6px;"><?php esc_html_e( 'Order Class', 'ifsedu-school-management' ); ?></label>
                    <input type="number" name="sort_order" class="ifs-educore-field-input" placeholder="<?php esc_attr_e( 'e.g. 1', 'ifsedu-school-management' ); ?>" value="<?php echo ( $edit_row && isset( $edit_row->sort_order ) ) ? esc_attr( $edit_row->sort_order ) : '0'; ?>" min="0" step="1" style="width:100%; padding:8px 12px; border:1px solid #cbd5e1; border-radius:6px; height:38px;">
                </div>

                <div>
                    <button type="submit" name="save_class_row" class="ifs-educore-btn-action-trigger" style="background:#00523c; color:#fff; border:none; padding:8px 16px; border-radius:6px; font-weight:600; cursor:pointer; height:38px; display:inline-flex; align-items:center; gap:6px;">
                        <span class="dashicons <?php echo $is_edit ? 'dashicons-edit' : 'dashicons-plus-alt2'; ?>" style="font-size:18px; width:18px; height:18px;"></span> 
                        <?php echo $is_edit ? esc_html__( 'Update Unit', 'ifsedu-school-management' ) : esc_html__( 'Add Unit', 'ifsedu-school-management' ); ?>
                    </button>
                    <?php if ( $is_edit ) : ?>
                        <a href="<?php echo esc_url( $base_url ); ?>" style="display:inline-flex; align-items:center; padding:8px 12px; font-size:13px; color:#64748b; text-decoration:none; margin-left:8px;"><?php esc_html_e( 'Cancel', 'ifsedu-school-management' ); ?></a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <div class="ifs-educore-bento-box" style="background:#fff; padding:24px; border-radius:12px; border:1px solid #e2e8f0;">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:16px;">
            <h5 class="ifs-educore-bento-subheading" style="font-size:16px; font-weight:700; color:#0f172a; margin:0;"><?php esc_html_e( 'Configured Academic Units', 'ifsedu-school-management' ); ?></h5>
            
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
            <table class="ifs-educore-architecture-table" id="ifs_educore_units_table" style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr style="background:#f8fafc; text-align:left;">
                        <th style="width: 15%; padding:12px 16px; border-bottom:2px solid #e2e8f0; color:#475569; font-size:12px; text-transform:uppercase;"><?php esc_html_e( 'Order', 'ifsedu-school-management' ); ?></th>
                        <th style="width: 35%; padding:12px 16px; border-bottom:2px solid #e2e8f0; color:#475569; font-size:12px; text-transform:uppercase;"><?php esc_html_e( 'Class Name', 'ifsedu-school-management' ); ?></th>
                        <th style="width: 30%; padding:12px 16px; border-bottom:2px solid #e2e8f0; color:#475569; font-size:12px; text-transform:uppercase;"><?php esc_html_e( 'Section Name', 'ifsedu-school-management' ); ?></th>
                        <th style="width: 20%; text-align: right; padding:12px 16px; border-bottom:2px solid #e2e8f0; color:#475569; font-size:12px; text-transform:uppercase;"><?php esc_html_e( 'Actions', 'ifsedu-school-management' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( ! empty( $classes ) ) : foreach ( $classes as $cls ) : 
                        $cls_internal_id = absint( $cls->id );
                        $edit_link       = add_query_arg( array( 'action' => 'edit_unit', 'id' => $cls_internal_id ), $base_url );
                        $delete_link     = wp_nonce_url( add_query_arg( array( 'action' => 'delete_unit', 'id' => $cls_internal_id ), $base_url ), 'delete_unit_action_' . $cls_internal_id );
                        $display_order   = isset( $cls->sort_order ) ? (int) $cls->sort_order : 0;
                    ?>
                        <tr style="border-bottom:1px solid #f1f5f9;" data-class-name="<?php echo esc_attr( $cls->class_name ); ?>">
                            <td style="color: #64748b; font-weight: 600; padding:12px 16px;">
                                <span style="background:#f1f5f9; padding:2px 8px; border-radius:4px; font-size:12px;"><?php echo esc_html( $display_order ); ?></span>
                            </td>
                            <td style="font-weight: 700; color: #0f172a; padding:12px 16px;"><?php echo esc_html( $cls->class_name ); ?></td>
                            <td style="color: #334155; padding:12px 16px;">
                                <?php if ( ! empty( $cls->section_name ) ) : ?>
                                    <span style="background:#f1f5f9; padding:4px 10px; border-radius:4px; font-weight:600; font-size:12px; color:#475569;"><?php echo esc_html( $cls->section_name ); ?></span>
                                <?php else : ?>
                                    <span style="color:#94a3b8; font-style:italic;"><?php esc_html_e( 'N/A', 'ifsedu-school-management' ); ?></span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right; padding:12px 16px;">
                                <a href="<?php echo esc_url( $edit_link ); ?>" class="ifs-educore-square-btn ifs-educore-square-btn-edit" style="color:#3b82f6; text-decoration:none; margin-right:8px;" title="<?php esc_attr_e( 'Edit Unit', 'ifsedu-school-management' ); ?>"><span class="dashicons dashicons-edit"></span></a>
                                <a href="<?php echo esc_url( $delete_link ); ?>" class="ifs-educore-square-btn ifs-educore-square-btn-delete" style="color:#ef4444; text-decoration:none;" title="<?php esc_attr_e( 'Delete Unit', 'ifsedu-school-management' ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this academic unit?', 'ifsedu-school-management' ) ); ?>');"><span class="dashicons dashicons-trash"></span></a>
                            </td>
                        </tr>
                    <?php endforeach; else : ?>
                        <tr><td colspan="4" style="text-align:center; padding: 20px; color:#64748b;"><?php esc_html_e( 'No academic units configured yet.', 'ifsedu-school-management' ); ?></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function() {
        var filterSelect = document.getElementById('ifs_educore_unit_class_filter');
        var tableBody    = document.querySelector('#ifs_educore_units_table tbody');
        var countPill    = document.getElementById('ifs_educore_unit_count_pill');

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
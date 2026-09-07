<?php
/**
 * Staff Directory List View
 * File: inc/staff/staff-list.php
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Direct access lockdown
}

function educore_staff_list_view() {
    global $wpdb;
    $table_staff = $wpdb->prefix . 'sms_staff';
    
    // 1. Capability Check
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have permission to view the staff directory.', 'ifsedu-school-management' ) );
    }

    // Active Tab Handler (URL Key)
    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $active_tab = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : 'school_teacher';
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    // Map URL tab keys directly to exact DB `staff_type` values stored in form select
    $tab_to_db_map = array(
        'school_teacher'  => 'Teacher (School)',
        'college_teacher' => 'Teacher (College)',
        'staff'           => 'Staff',
        'officer'         => 'Officer',
    );

    // Fallback to School Teacher if tab is invalid
    if ( ! array_key_exists( $active_tab, $tab_to_db_map ) ) {
        $active_tab = 'school_teacher';
    }

    $db_staff_type = $tab_to_db_map[ $active_tab ];

    // Detect Order Column dynamically in db safely
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $db_columns = $wpdb->get_col( "DESCRIBE `{$table_staff}`", 0 );
    // phpcs:enable
    
    $order_col  = 'id';
    $allowed_order_cols = array( 'sort_order', 'serial_number', 'position', 'order_no', 'serial', 'id' );

    foreach ( $allowed_order_cols as $col ) {
        if ( is_array( $db_columns ) && in_array( $col, $db_columns, true ) ) {
            $order_col = $col;
            break;
        }
    }

    // Fetch DB records ordered strictly by DB order column
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $staff_members = $wpdb->get_results( 
        $wpdb->prepare(
            "SELECT *, `{$order_col}` AS db_order_number 
             FROM `{$table_staff}` 
             WHERE staff_type = %s 
             ORDER BY `{$order_col}` ASC, id DESC",
            $db_staff_type
        )
    );
    // phpcs:enable

    // Tab Base URL Generator
    $base_tab_url = add_query_arg( array( 'page' => 'school_management_system', 'tab' => 'staff' ), admin_url( 'admin.php' ) );
    $school_url   = add_query_arg( 'type', 'school_teacher', $base_tab_url );
    $college_url  = add_query_arg( 'type', 'college_teacher', $base_tab_url );
    $staff_url    = add_query_arg( 'type', 'staff', $base_tab_url );
    $officer_url  = add_query_arg( 'type', 'officer', $base_tab_url );
    $add_url      = add_query_arg( 'sub', 'add', $base_tab_url );
    ?>

    <!-- Header Title & Action CTA -->
    <div class="d-flex justify-content-between align-items-center mb-3" style="margin: 20px 20px 24px 0;">
        <h2>
            <span class="dashicons dashicons-groups text-success me-1"></span> 
            <?php esc_html_e( 'Teachers & Staff Directory', 'ifsedu-school-management' ); ?>
        </h2>
        <a href="<?php echo esc_url( $add_url ); ?>" class="btn btn-success fw-bold px-4 shadow-sm" style="background-color: #00523c; border: none; font-size: 14px; padding: 10px 20px;">
            + <?php esc_html_e( 'Add New Staff Member', 'ifsedu-school-management' ); ?>
        </a>
    </div>

    <!-- Category Tabs Navigation (4 Tabs) -->
    <div class="ifs-educore-tabs-wrapper" style="margin-right: 20px;">
        <a href="<?php echo esc_url( $school_url ); ?>" class="ifs-educore-tab-item <?php echo ( 'school_teacher' === $active_tab ) ? 'active' : ''; ?>">
            <span class="dashicons dashicons-welcome-learn-more"></span>
            <?php esc_html_e( 'School Teacher', 'ifsedu-school-management' ); ?>
        </a>

        <a href="<?php echo esc_url( $college_url ); ?>" class="ifs-educore-tab-item <?php echo ( 'college_teacher' === $active_tab ) ? 'active' : ''; ?>">
            <span class="dashicons dashicons-bank"></span>
            <?php esc_html_e( 'College Teacher', 'ifsedu-school-management' ); ?>
        </a>

        <a href="<?php echo esc_url( $staff_url ); ?>" class="ifs-educore-tab-item <?php echo ( 'staff' === $active_tab ) ? 'active' : ''; ?>">
            <span class="dashicons dashicons-id"></span>
            <?php esc_html_e( 'Staff', 'ifsedu-school-management' ); ?>
        </a>

        <a href="<?php echo esc_url( $officer_url ); ?>" class="ifs-educore-tab-item <?php echo ( 'officer' === $active_tab ) ? 'active' : ''; ?>">
            <span class="dashicons dashicons-businessperson"></span>
            <?php esc_html_e( 'Officers', 'ifsedu-school-management' ); ?>
        </a>
    </div>

    <div class="bg-white p-4 rounded shadow-sm border" style="margin-right: 20px; margin-bottom: 30px;">
        <table class="table table-striped table-hover align-middle educore-datatable w-100">
            <thead class="table-light">
                <tr>
                    <th style="width: 70px; text-align: center;"><?php esc_html_e( 'Order', 'ifsedu-school-management' ); ?></th>
                    <th style="width: 130px;"><?php esc_html_e( 'Staff ID', 'ifsedu-school-management' ); ?></th>
                    <th><?php esc_html_e( 'Name', 'ifsedu-school-management' ); ?></th>
                    <th><?php esc_html_e( 'Designation', 'ifsedu-school-management' ); ?></th>
                    <th style="width: 180px;"><?php esc_html_e( 'Employment Type', 'ifsedu-school-management' ); ?></th>
                    <th style="text-align: right; width: 220px;"><?php esc_html_e( 'Actions', 'ifsedu-school-management' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( ! empty( $staff_members ) ) : ?>
                    <?php foreach ( $staff_members as $staff ) : 
                        $staff_id   = absint( $staff->id );
                        $view_url   = add_query_arg( array( 'page' => 'school_management_system', 'tab' => 'staff', 'sub' => 'view', 'id' => $staff_id ), admin_url( 'admin.php' ) );
                        $edit_url   = add_query_arg( array( 'page' => 'school_management_system', 'tab' => 'staff', 'sub' => 'edit', 'id' => $staff_id ), admin_url( 'admin.php' ) );
                        $delete_url = wp_nonce_url( 
                            add_query_arg( array( 'page' => 'school_management_system', 'tab' => 'staff', 'sub' => 'delete', 'id' => $staff_id ), admin_url( 'admin.php' ) ), 
                            'delete_staff_' . $staff_id 
                        );

                        // Order value direct from DB
                        $order_no = isset( $staff->db_order_number ) ? absint( $staff->db_order_number ) : 0;

                        // Primary Name Resolution
                        $full_name = ! empty( $staff->full_name ) ? $staff->full_name : ( ! empty( $staff->name ) ? $staff->name : '' );

                        // Staff ID fallback
                        $display_staff_id = ! empty( $staff->staff_id ) ? strtoupper( (string) $staff->staff_id ) : '—';

                        // Employment Type display value stored directly in staff_type
                        $emp_type_label = ! empty( $staff->staff_type ) ? $staff->staff_type : $db_staff_type;
                    ?>
                    <tr>
                        <!-- Order Number Column (from DB) -->
                        <td class="text-center">
                            <span class="ifs-educore-order-badge"><?php echo esc_html( $order_no ); ?></span>
                        </td>

                        <!-- Staff ID Column -->
                        <td>
                            <code><?php echo esc_html( $display_staff_id ); ?></code>
                        </td>

                        <!-- Name & WP User Link -->
                        <td>
                            <strong class="text-slate-800 d-block"><?php echo esc_html( $full_name ); ?></strong>
                            <?php if ( ! empty( $staff->wp_user_id ) ) : ?>
                                <small class="text-muted">
                                    <span class="dashicons dashicons-admin-users" style="font-size: 14px; width: 14px; height: 14px;"></span> 
                                    <?php
                                    printf(
                                        /* translators: %d: WordPress User ID */
                                        esc_html__( 'Linked WP User #%d', 'ifsedu-school-management' ),
                                        absint( $staff->wp_user_id )
                                    );
                                    ?>
                                </small>
                            <?php endif; ?>
                        </td>

                        <!-- Designation -->
                        <td>
                            <span class="badge bg-secondary px-2 py-1" style="font-weight: 600; font-size: 12px;">
                                <?php echo esc_html( ! empty( $staff->designation ) ? $staff->designation : __( 'Staff Member', 'ifsedu-school-management' ) ); ?>
                            </span>
                        </td>

                        <!-- Employment Type Column -->
                        <td>
                            <span class="ifs-educore-emp-type-badge">
                                <?php echo esc_html( $emp_type_label ); ?>
                            </span>
                        </td>

                        <!-- Action Buttons with Modern SVG Icons -->
                        <td style="text-align: right;">
                            <div class="ifs-educore-action-group">
                                <!-- View Button -->
                                <a href="<?php echo esc_url( $view_url ); ?>" class="ifs-educore-btn-action ifs-educore-btn-view" title="<?php esc_attr_e( 'View Profile', 'ifsedu-school-management' ); ?>">
                                    <svg viewBox="0 0 24 24"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>
                                    <?php esc_html_e( 'View', 'ifsedu-school-management' ); ?>
                                </a>

                                <!-- Edit Button -->
                                <a href="<?php echo esc_url( $edit_url ); ?>" class="ifs-educore-btn-action ifs-educore-btn-edit" title="<?php esc_attr_e( 'Edit Record', 'ifsedu-school-management' ); ?>">
                                    <svg viewBox="0 0 24 24"><path d="M3 17.25V21h4.75L17.81 9.94l-4.75-4.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 4.75 4.75 1.83-1.83z"/></svg>
                                    <?php esc_html_e( 'Edit', 'ifsedu-school-management' ); ?>
                                </a>

                                <!-- Delete Button -->
                                <a href="<?php echo esc_url( $delete_url ); ?>" 
                                   class="ifs-educore-btn-action ifs-educore-btn-delete" 
                                   title="<?php esc_attr_e( 'Delete Record', 'ifsedu-school-management' ); ?>"
                                   onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete this staff record?', 'ifsedu-school-management' ) ); ?>');">
                                    <svg viewBox="0 0 24 24"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
                                    <?php esc_html_e( 'Delete', 'ifsedu-school-management' ); ?>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">
                            <?php esc_html_e( 'No records found for this category.', 'ifsedu-school-management' ); ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- DataTables Safe Initialization -->
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        if ($.fn.DataTable) {
            if ($.fn.DataTable.isDataTable('.educore-datatable')) {
                $('.educore-datatable').DataTable().destroy();
            }
            $('.educore-datatable').DataTable({
                "pageLength": 15,
                "ordering": true,
                "order": [[0, "asc"]],
                "responsive": true,
                "columnDefs": [
                    { "orderable": false, "targets": [5] }
                ],
                "language": {
                    "emptyTable": "<?php echo esc_js( __( 'No staff records found.', 'ifsedu-school-management' ) ); ?>"
                }
            });
        }
    });
    </script>
    <?php
}
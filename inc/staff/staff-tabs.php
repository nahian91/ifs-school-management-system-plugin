<?php
/**
 * High-End Academic Staff & Teachers Sub-Navigation Engine & Router Matrix
 * File: inc/staff.php
 * Text Domain: ifsedu-school-management
 * Architecture: Bento Layout Viewports with Integrated Hardware Print Lockdown
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Immediate access layer lockdown
}

// Load Modular Dependency Sub-Files if segregated
$educore_staff_dir = defined( 'EDUCORE_PATH' ) ? EDUCORE_PATH . 'inc/staff/' : plugin_dir_path( __FILE__ ) . 'staff/';

if ( file_exists( $educore_staff_dir . 'staff-list.php' ) ) {
    require_once $educore_staff_dir . 'staff-list.php';
}
if ( file_exists( $educore_staff_dir . 'staff-add-edit.php' ) ) {
    require_once $educore_staff_dir . 'staff-add-edit.php';
}
if ( file_exists( $educore_staff_dir . 'staff-delete.php' ) ) {
    require_once $educore_staff_dir . 'staff-delete.php';
}
if ( file_exists( $educore_staff_dir . 'staff-profile.php' ) ) {
    require_once $educore_staff_dir . 'staff-profile.php';
}
if ( file_exists( $educore_staff_dir . 'staff-id-cards.php' ) ) {
    require_once $educore_staff_dir . 'staff-id-cards.php';
}

function educore_staff_tab() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to access the staff module.', 'ifsedu-school-management' ) );
    }

    $allowed_sub_tabs = array( 'list', 'add', 'edit', 'view', 'id_card', 'id_cards', 'delete' );

    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $raw_sub_tab = isset( $_GET['sub'] ) ? sanitize_key( wp_unslash( $_GET['sub'] ) ) : 'list';
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    $sub_tab = in_array( $raw_sub_tab, $allowed_sub_tabs, true ) ? $raw_sub_tab : 'list';

    // Construct URLs for top submenu links using add_query_arg()
    $base_admin_url = admin_url( 'admin.php' );
    $all_staff_url  = add_query_arg( array( 'page' => 'school_management_system', 'tab' => 'staff', 'sub' => 'list' ), $base_admin_url );
    $add_staff_url  = add_query_arg( array( 'page' => 'school_management_system', 'tab' => 'staff', 'sub' => 'add' ), $base_admin_url );
    $id_card_url    = add_query_arg( array( 'page' => 'school_management_system', 'tab' => 'staff', 'sub' => 'id_card' ), $base_admin_url );
    ?>

    <div class="ifs-educore-staff-nav-root">
        
        <!-- Top Sub-Navigation Menu Bar (Bento Frame Layer) -->
        <div class="ifs-educore-top-nav-wrapper no-print">
            <div class="ifs-educore-nav-button-group">
                <a href="<?php echo esc_url( $all_staff_url ); ?>" 
                   class="ifs-educore-nav-link <?php echo ( 'list' === $sub_tab ) ? 'ifs-educore-nav-link-active' : 'ifs-educore-nav-link-inactive'; ?>">
                    <span class="dashicons dashicons-businessman"></span> <?php esc_html_e( 'All Staff & Teachers', 'ifsedu-school-management' ); ?>
                </a>
                
                <a href="<?php echo esc_url( $add_staff_url ); ?>" 
                   class="ifs-educore-nav-link <?php echo ( 'add' === $sub_tab ) ? 'ifs-educore-nav-link-active' : 'ifs-educore-nav-link-inactive'; ?>">
                    <span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e( '+ Add New Staff', 'ifsedu-school-management' ); ?>
                </a>

                <a href="<?php echo esc_url( $id_card_url ); ?>" 
                   class="ifs-educore-nav-link <?php echo ( 'id_card' === $sub_tab || 'id_cards' === $sub_tab ) ? 'ifs-educore-nav-link-active' : 'ifs-educore-nav-link-inactive'; ?>">
                    <span class="dashicons dashicons-id"></span> <?php esc_html_e( 'Print ID Cards', 'ifsedu-school-management' ); ?>
                </a>
            </div>

            <?php if ( 'edit' === $sub_tab || 'view' === $sub_tab ) : ?>
                <div>
                    <span class="ifs-educore-context-badge">
                        <span class="dashicons dashicons-edit" style="font-size:14px; width:14px; height:14px;"></span>
                        <?php
                        printf(
                            /* translators: %s: Action verb (e.g. Editing, Viewing) */
                            esc_html__( '%sing Staff Record', 'ifsedu-school-management' ),
                            esc_html( ucfirst( $sub_tab ) )
                        );
                        ?>
                    </span>
                </div>
            <?php endif; ?>
        </div>

        <!-- System HR/Staff Viewport Execution Core -->
        <div class="ifs-educore-module-viewport-container">
            <?php
            switch ( $sub_tab ) {
                case 'add':
                case 'edit':
                    if ( function_exists( 'educore_staff_add_edit_view' ) ) {
                        educore_staff_add_edit_view();
                    } else {
                        echo '<div class="ifs-educore-notice-card"><span class="dashicons dashicons-info"></span> ' . esc_html__( 'Add/Edit Staff module initializing.', 'ifsedu-school-management' ) . '</div>';
                    }
                    break;

                case 'view':
                    if ( function_exists( 'educore_staff_profile_view' ) ) {
                        educore_staff_profile_view();
                    } else {
                        echo '<div class="ifs-educore-notice-card"><span class="dashicons dashicons-info"></span> ' . esc_html__( 'Profile view module initializing.', 'ifsedu-school-management' ) . '</div>';
                    }
                    break;

                case 'id_card':
                case 'id_cards':
                    if ( function_exists( 'educore_staff_id_cards_view' ) ) {
                        educore_staff_id_cards_view();
                    } else {
                        echo '<div class="ifs-educore-notice-card"><span class="dashicons dashicons-info"></span> ' . 
                            wp_kses(
                                sprintf(
                                    /* translators: %s: Function name */
                                    __( 'Staff ID Cards module initializing. Please load %s template file.', 'ifsedu-school-management' ),
                                    '<code>educore_staff_id_cards_view()</code>'
                                ),
                                array( 'code' => array() )
                            ) . '</div>';
                    }
                    break;

                case 'delete':
                    if ( function_exists( 'educore_staff_delete_action' ) ) {
                        educore_staff_delete_action();
                    }
                    break;

                case 'list':
                default:
                    if ( function_exists( 'educore_staff_list_view' ) ) {
                        educore_staff_list_view();
                    } else {
                        echo '<div class="ifs-educore-notice-card"><span class="dashicons dashicons-info"></span> ' . esc_html__( 'Staff List View module initializing.', 'ifsedu-school-management' ) . '</div>';
                    }
                    break;
            }
            ?>
        </div>
    </div>
    <?php
}
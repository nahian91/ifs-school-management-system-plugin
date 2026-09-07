<?php
/**
 * High-End Academic Students Sub-Navigation Engine & Router Matrix
 * Custom Prefixes Applied: ifs-educore-
 * File: students-tab.php
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

function educore_students_tab() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'ifsedu-school-management' ) );
    }

    $allowed_sub_tabs = array( 'list', 'add', 'edit', 'view', 'id_card', 'admit_card', 'certificate', 'promotion', 'delete' );

    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $raw_sub_tab = isset( $_GET['sub'] ) ? sanitize_key( wp_unslash( $_GET['sub'] ) ) : 'list';
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    $sub_tab = in_array( $raw_sub_tab, $allowed_sub_tabs, true ) ? $raw_sub_tab : 'list';
    
    // Construct URLs for top submenu links using add_query_arg()
    $base_admin_url   = admin_url( 'admin.php' );
    $all_students_url = add_query_arg( array( 'page' => 'school_management_system', 'tab' => 'students', 'sub' => 'list' ), $base_admin_url );
    $add_student_url  = add_query_arg( array( 'page' => 'school_management_system', 'tab' => 'students', 'sub' => 'add' ), $base_admin_url );
    $id_card_url      = add_query_arg( array( 'page' => 'school_management_system', 'tab' => 'students', 'sub' => 'id_card' ), $base_admin_url );
    $admit_card_url   = add_query_arg( array( 'page' => 'school_management_system', 'tab' => 'students', 'sub' => 'admit_card' ), $base_admin_url );
    $certificate_url  = add_query_arg( array( 'page' => 'school_management_system', 'tab' => 'students', 'sub' => 'certificate' ), $base_admin_url );
    $promotion_url    = add_query_arg( array( 'page' => 'school_management_system', 'tab' => 'students', 'sub' => 'promotion' ), $base_admin_url );
    ?>

    <div class="ifs-educore-students-nav-root">
        
        <!-- Top Sub-Navigation Menu Bar (Bento Frame Layer) -->
        <div class="ifs-educore-top-nav-wrapper no-print">
            <div class="ifs-educore-nav-button-group">
                <a href="<?php echo esc_url( $all_students_url ); ?>" 
                   class="ifs-educore-nav-link <?php echo ( 'list' === $sub_tab ) ? 'ifs-educore-nav-link-active' : 'ifs-educore-nav-link-inactive'; ?>">
                    <span class="dashicons dashicons-groups"></span> <?php esc_html_e( 'All Students', 'ifsedu-school-management' ); ?>
                </a>
                
                <a href="<?php echo esc_url( $add_student_url ); ?>" 
                   class="ifs-educore-nav-link <?php echo ( 'add' === $sub_tab ) ? 'ifs-educore-nav-link-active' : 'ifs-educore-nav-link-inactive'; ?>">
                    <span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e( '+ Add New Student', 'ifsedu-school-management' ); ?>
                </a>
                
                <a href="<?php echo esc_url( $id_card_url ); ?>" 
                   class="ifs-educore-nav-link <?php echo ( 'id_card' === $sub_tab ) ? 'ifs-educore-nav-link-active' : 'ifs-educore-nav-link-inactive'; ?>">
                    <span class="dashicons dashicons-id-alt"></span> <?php esc_html_e( 'Student ID Cards', 'ifsedu-school-management' ); ?>
                </a>
                
                <a href="<?php echo esc_url( $admit_card_url ); ?>" 
                   class="ifs-educore-nav-link <?php echo ( 'admit_card' === $sub_tab ) ? 'ifs-educore-nav-link-active' : 'ifs-educore-nav-link-inactive'; ?>">
                    <span class="dashicons dashicons-tickets-alt"></span> <?php esc_html_e( 'Admit Cards', 'ifsedu-school-management' ); ?>
                </a>

                <a href="<?php echo esc_url( $certificate_url ); ?>" 
                   class="ifs-educore-nav-link <?php echo ( 'certificate' === $sub_tab ) ? 'ifs-educore-nav-link-active' : 'ifs-educore-nav-link-inactive'; ?>">
                    <span class="dashicons dashicons-awards"></span> <?php esc_html_e( 'Certificate', 'ifsedu-school-management' ); ?>
                </a>

                <a href="<?php echo esc_url( $promotion_url ); ?>" 
                   class="ifs-educore-nav-link <?php echo ( 'promotion' === $sub_tab ) ? 'ifs-educore-nav-link-active' : 'ifs-educore-nav-link-inactive'; ?>">
                    <span class="dashicons dashicons-randomize"></span> <?php esc_html_e( 'Student Promotion', 'ifsedu-school-management' ); ?>
                </a>
            </div>

            <?php if ( in_array( $sub_tab, array( 'edit', 'view' ), true ) ) : ?>
                <div>
                    <span class="ifs-educore-context-badge">
                        <span class="dashicons dashicons-edit" style="font-size:14px; width:14px; height:14px;"></span>
                        <?php 
                        if ( 'edit' === $sub_tab ) {
                            esc_html_e( 'Editing Student Record', 'ifsedu-school-management' );
                        } else {
                            esc_html_e( 'Viewing Student Record', 'ifsedu-school-management' );
                        }
                        ?>
                    </span>
                </div>
            <?php endif; ?>
        </div>

        <!-- System Routing Execution Core -->
        <div class="ifs-educore-module-viewport-container">
            <?php
            switch ( $sub_tab ) {
                case 'add':
                case 'edit':
                    if ( function_exists( 'educore_student_add_edit_view' ) ) {
                        educore_student_add_edit_view();
                    }
                    break;

                case 'view':
                    if ( function_exists( 'educore_student_profile_view' ) ) {
                        educore_student_profile_view();
                    }
                    break;

                case 'id_card':
                    if ( function_exists( 'educore_student_id_card_view' ) ) {
                        educore_student_id_card_view();
                    } else {
                        echo '<div class="ifs-educore-notice-card"><span class="dashicons dashicons-info" style="vertical-align:middle; margin-right:6px;"></span> ' .
                            wp_kses(
                                sprintf(
                                    /* translators: %s: Function name in code tags */
                                    __( 'Student ID Card Generator module is initializing. Define %s.', 'ifsedu-school-management' ),
                                    '<code>educore_student_id_card_view()</code>'
                                ),
                                array( 'code' => array() )
                            ) . '</div>';
                    }
                    break;

                case 'admit_card':
                    if ( function_exists( 'educore_student_admit_card_view' ) ) {
                        educore_student_admit_card_view();
                    } else {
                        echo '<div class="ifs-educore-notice-card"><span class="dashicons dashicons-info" style="vertical-align:middle; margin-right:6px;"></span> ' .
                            wp_kses(
                                sprintf(
                                    /* translators: %s: Function name in code tags */
                                    __( 'Admit Card Generator module is initializing. Define %s.', 'ifsedu-school-management' ),
                                    '<code>educore_student_admit_card_view()</code>'
                                ),
                                array( 'code' => array() )
                            ) . '</div>';
                    }
                    break;

                case 'certificate':
                    if ( function_exists( 'educore_student_certificate_view' ) ) {
                        educore_student_certificate_view();
                    } else {
                        echo '<div class="ifs-educore-notice-card"><span class="dashicons dashicons-info" style="vertical-align:middle; margin-right:6px;"></span> ' .
                            wp_kses(
                                sprintf(
                                    /* translators: %s: Function name in code tags */
                                    __( 'Certificate Generator module is initializing. Define %s.', 'ifsedu-school-management' ),
                                    '<code>educore_student_certificate_view()</code>'
                                ),
                                array( 'code' => array() )
                            ) . '</div>';
                    }
                    break;

                case 'promotion':
                    if ( function_exists( 'educore_student_promotion_view' ) ) {
                        educore_student_promotion_view();
                    } else {
                        echo '<div class="ifs-educore-notice-card"><span class="dashicons dashicons-info" style="vertical-align:middle; margin-right:6px;"></span> ' .
                            wp_kses(
                                sprintf(
                                    /* translators: %s: Function name in code tags */
                                    __( 'Student Promotion module is initializing. Define %s.', 'ifsedu-school-management' ),
                                    '<code>educore_student_promotion_view()</code>'
                                ),
                                array( 'code' => array() )
                            ) . '</div>';
                    }
                    break;

                case 'delete':
                    if ( function_exists( 'educore_student_delete_action' ) ) {
                        educore_student_delete_action();
                    }
                    break;

                case 'list':
                default:
                    if ( function_exists( 'educore_students_list_view' ) ) {
                        educore_students_list_view();
                    }
                    break;
            }
            ?>
        </div>
    </div>
    <?php
}
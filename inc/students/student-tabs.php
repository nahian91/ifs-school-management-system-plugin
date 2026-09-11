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

    <style id="ifs-educore-students-router-styles">
        .ifs-educore-students-nav-root {
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #0f172a;
        }

        .ifs-educore-top-nav-wrapper {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 16px 20px;
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            box-shadow: 0 4px 15px -3px rgba(0, 0, 0, 0.03);
        }

        .ifs-educore-nav-button-group {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .ifs-educore-nav-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 18px;
            border-radius: 10px;
            font-size: 13.5px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.2s ease-in-out;
            border: 1px solid transparent;
        }

        .ifs-educore-nav-link .dashicons {
            font-size: 18px;
            width: 18px;
            height: 18px;
        }

        .ifs-educore-nav-link-inactive {
            color: #64748b;
            background: #f8fafc;
            border-color: #e2e8f0;
        }

        .ifs-educore-nav-link-inactive:hover {
            color: #00523c;
            background: #f0fdf4;
            border-color: #a7f3d0;
        }

        .ifs-educore-nav-link-active {
            color: #ffffff !important;
            background: #00523c !important;
            border-color: #00523c !important;
            box-shadow: 0 4px 12px rgba(0, 106, 78, 0.2);
        }

        .ifs-educore-nav-link-active .dashicons {
            color: #a7f3d0 !important;
        }

        .ifs-educore-context-badge {
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            color: #475569;
            font-size: 12px;
            font-weight: 700;
            padding: 5px 12px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .ifs-educore-context-badge .dashicons {
            font-size: 14px;
            width: 14px;
            height: 14px;
            vertical-align: middle;
        }

        .ifs-educore-notice-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            color: #475569;
            font-size: 14px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.02);
        }

        .ifs-educore-notice-card .dashicons {
            color: #0284c7;
            font-size: 20px;
            width: 20px;
            height: 20px;
            vertical-align: middle;
            margin-right: 6px;
        }

        .ifs-educore-module-viewport-container {
            width: 100%;
        }

        @media print {
            .no-print { display: none !important; }
        }
    </style>

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
                        <span class="dashicons dashicons-edit"></span>
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
                        echo '<div class="ifs-educore-notice-card"><span class="dashicons dashicons-info"></span> ' .
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
                        echo '<div class="ifs-educore-notice-card"><span class="dashicons dashicons-info"></span> ' .
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
                        echo '<div class="ifs-educore-notice-card"><span class="dashicons dashicons-info"></span> ' .
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
                        echo '<div class="ifs-educore-notice-card"><span class="dashicons dashicons-info"></span> ' .
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
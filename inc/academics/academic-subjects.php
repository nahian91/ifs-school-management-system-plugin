<?php
/**
 * Academic Subjects Management & Mark Distribution Engine with Dynamic Breakdown Repeater
 * File: inc/academics/class-subjects.php
 * Text Domain: ifsedu-school-management
 * Updated: Restricted to Classes 9-12 with Class-Wise Grouped Directory View
 */

if ( ! defined( 'ABSPATH' ) ) { 
    exit; 
}

function educore_render_subjects_view() {
    global $wpdb;
    $table_units    = $wpdb->prefix . 'sms_academic_units';
    $table_subjects = $wpdb->prefix . 'sms_subjects';

    if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'edit_posts' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to manage academic subjects.', 'ifsedu-school-management' ) );
    }

    $base_url = add_query_arg(
        array(
            'page'   => 'school_management_system',
            'tab'    => 'academics',
            'subtab' => 'subjects',
        ),
        admin_url( 'admin.php' )
    );

    // --------------------------------------------------------------------------
    // 1. DIRECT FORM SUBMISSION: SINGLE SUBJECT UPDATE
    // --------------------------------------------------------------------------
    $req_method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';

    if ( 'POST' === $req_method && isset( $_POST['educore_update_single_subject'] ) ) {
        if ( isset( $_POST['edit_subject_nonce_field'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['edit_subject_nonce_field'] ) ), 'ifs_educore_edit_subject_nonce' ) ) {
            $sub_id          = isset( $_POST['subject_id'] ) ? absint( wp_unslash( $_POST['subject_id'] ) ) : 0;
            $unit_keys       = ( isset( $_POST['class_units'] ) && is_array( $_POST['class_units'] ) ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['class_units'] ) ) : array();
            $sub_name        = isset( $_POST['subject_name'] ) ? sanitize_text_field( wp_unslash( $_POST['subject_name'] ) ) : '';
            $sub_code        = isset( $_POST['subject_code'] ) ? sanitize_text_field( wp_unslash( $_POST['subject_code'] ) ) : '';
            $sub_order       = isset( $_POST['subject_order'] ) ? intval( wp_unslash( $_POST['subject_order'] ) ) : 0;
            $tot_m           = isset( $_POST['total_marks'] ) ? floatval( wp_unslash( $_POST['total_marks'] ) ) : 100.00;
            $pass_m          = isset( $_POST['pass_marks'] ) ? floatval( wp_unslash( $_POST['pass_marks'] ) ) : 33.00;
            $cq_m            = isset( $_POST['cq_marks'] ) ? floatval( wp_unslash( $_POST['cq_marks'] ) ) : 0.00;
            $cq_p            = isset( $_POST['cq_pass'] ) ? floatval( wp_unslash( $_POST['cq_pass'] ) ) : 0.00;
            $mcq_m           = isset( $_POST['mcq_marks'] ) ? floatval( wp_unslash( $_POST['mcq_marks'] ) ) : 0.00;
            $mcq_p           = isset( $_POST['mcq_pass'] ) ? floatval( wp_unslash( $_POST['mcq_pass'] ) ) : 0.00;
            $pr_m            = isset( $_POST['practical_marks'] ) ? floatval( wp_unslash( $_POST['practical_marks'] ) ) : 0.00;
            $pr_p            = isset( $_POST['practical_pass'] ) ? floatval( wp_unslash( $_POST['practical_pass'] ) ) : 0.00;
            $preset_type     = isset( $_POST['preset_type'] ) ? sanitize_text_field( wp_unslash( $_POST['preset_type'] ) ) : 'gen_100';

            // Collect Custom Breakdown if preset is 'other'
            $breakdown_json = null;
            if ( 'other' === $preset_type && isset( $_POST['sub_comp_name'] ) && is_array( $_POST['sub_comp_name'] ) ) {
                $components = array();
                $names      = array_map( 'sanitize_text_field', wp_unslash( $_POST['sub_comp_name'] ) );
                $totals     = isset( $_POST['sub_comp_total'] ) && is_array( $_POST['sub_comp_total'] ) ? array_map( 'floatval', wp_unslash( $_POST['sub_comp_total'] ) ) : array();
                $passes     = isset( $_POST['sub_comp_pass'] ) && is_array( $_POST['sub_comp_pass'] ) ? array_map( 'floatval', wp_unslash( $_POST['sub_comp_pass'] ) ) : array();

                foreach ( $names as $idx => $comp_name ) {
                    if ( empty( $comp_name ) ) {
                        continue;
                    }
                    $c_max = isset( $totals[ $idx ] ) ? $totals[ $idx ] : 0;
                    $c_pas = isset( $passes[ $idx ] ) ? $passes[ $idx ] : 0;

                    $components[] = array(
                        'name'  => $comp_name,
                        'total' => $c_max,
                        'pass'  => $c_pas,
                    );
                }

                if ( ! empty( $components ) ) {
                    $breakdown_json = wp_json_encode( $components );
                    $cq_m           = 0.00;
                    $cq_p           = 0.00;
                    $mcq_m          = 0.00;
                    $mcq_p          = 0.00;
                    $pr_m           = 0.00;
                    $pr_p           = 0.00;
                }
            }

            if ( $sub_id > 0 && ! empty( $unit_keys ) && ! empty( $sub_name ) ) {
                $target_unit_ids = array();
                foreach ( $unit_keys as $ukey ) {
                    if ( strpos( $ukey, 'id:' ) === 0 ) {
                        $target_unit_ids[] = absint( str_replace( 'id:', '', $ukey ) );
                    } elseif ( strpos( $ukey, 'class:' ) === 0 ) {
                        $c_name = sanitize_text_field( str_replace( 'class:', '', $ukey ) );
                        $c_ids  = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM `{$table_units}` WHERE class_name = %s ORDER BY sort_order ASC, id ASC", $c_name ) );
                        if ( ! empty( $c_ids ) ) {
                            $target_unit_ids = array_merge( $target_unit_ids, array_map( 'absint', $c_ids ) );
                        }
                    }
                }
                $target_unit_ids = array_values( array_unique( array_filter( $target_unit_ids ) ) );

                if ( ! empty( $target_unit_ids ) ) {
                    $primary_unit_id = $target_unit_ids[0];

                    $update_data = array(
                        'class_id'        => $primary_unit_id,
                        'subject_name'    => $sub_name,
                        'subject_code'    => $sub_code,
                        'subject_order'   => $sub_order,
                        'total_marks'     => $tot_m,
                        'pass_marks'      => $pass_m,
                        'cq_marks'        => $cq_m,
                        'cq_pass'         => $cq_p,
                        'mcq_marks'       => $mcq_m,
                        'mcq_pass'        => $mcq_p,
                        'practical_marks' => $pr_m,
                        'practical_pass'  => $pr_p,
                        'breakdown_data'  => $breakdown_json,
                    );

                    $wpdb->update(
                        $table_subjects,
                        $update_data,
                        array( 'id' => $sub_id ),
                        array( '%d', '%s', '%s', '%d', '%f', '%f', '%f', '%f', '%f', '%f', '%f', '%f', '%s' ),
                        array( '%d' )
                    );

                    for ( $i = 1; $i < count( $target_unit_ids ); $i++ ) {
                        $other_unit_id = $target_unit_ids[ $i ];
                        $exists_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$table_subjects}` WHERE class_id = %d AND subject_name = %s LIMIT 1", $other_unit_id, $sub_name ) );

                        if ( ! $exists_id ) {
                            $insert_data             = $update_data;
                            $insert_data['class_id'] = $other_unit_id;
                            $wpdb->insert(
                                $table_subjects,
                                $insert_data,
                                array( '%d', '%s', '%s', '%d', '%f', '%f', '%f', '%f', '%f', '%f', '%f', '%f', '%s' )
                            );
                        }
                    }
                }

                $redirect_target = add_query_arg( array( 'status' => 'updated' ), $base_url );
                if ( ! headers_sent() ) {
                    wp_safe_redirect( $redirect_target );
                    exit;
                } else {
                    echo '<script type="text/javascript">window.location.href=' . wp_json_encode( esc_url_raw( $redirect_target ) ) . ';</script>';
                    exit;
                }
            }
        }
    }

    // --------------------------------------------------------------------------
    // 2. REPEATER SUBMISSION (BULK ASSIGN WITH CUSTOM BREAKDOWN)
    // --------------------------------------------------------------------------
    if ( 'POST' === $req_method && isset( $_POST['save_subjects_repeater'] ) ) {
        if ( isset( $_POST['subject_setup_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['subject_setup_nonce'] ) ), 'subject_setup_action' ) ) {
            $unit_keys       = ( isset( $_POST['class_units'] ) && is_array( $_POST['class_units'] ) ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['class_units'] ) ) : array();
            $subject_name    = ( isset( $_POST['subject_name'] ) && is_array( $_POST['subject_name'] ) ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['subject_name'] ) ) : array();
            $subject_code    = ( isset( $_POST['subject_code'] ) && is_array( $_POST['subject_code'] ) ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['subject_code'] ) ) : array();
            $subject_order   = ( isset( $_POST['subject_order'] ) && is_array( $_POST['subject_order'] ) ) ? array_map( 'intval', wp_unslash( $_POST['subject_order'] ) ) : array();
            $preset_types    = ( isset( $_POST['distribution_preset'] ) && is_array( $_POST['distribution_preset'] ) ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['distribution_preset'] ) ) : array();
            $total_marks     = ( isset( $_POST['total_marks'] ) && is_array( $_POST['total_marks'] ) ) ? array_map( 'floatval', wp_unslash( $_POST['total_marks'] ) ) : array();
            $pass_marks      = ( isset( $_POST['pass_marks'] ) && is_array( $_POST['pass_marks'] ) ) ? array_map( 'floatval', wp_unslash( $_POST['pass_marks'] ) ) : array();
            $cq_marks        = ( isset( $_POST['cq_marks'] ) && is_array( $_POST['cq_marks'] ) ) ? array_map( 'floatval', wp_unslash( $_POST['cq_marks'] ) ) : array();
            $cq_pass         = ( isset( $_POST['cq_pass'] ) && is_array( $_POST['cq_pass'] ) ) ? array_map( 'floatval', wp_unslash( $_POST['cq_pass'] ) ) : array();
            $mcq_marks       = ( isset( $_POST['mcq_marks'] ) && is_array( $_POST['mcq_marks'] ) ) ? array_map( 'floatval', wp_unslash( $_POST['mcq_marks'] ) ) : array();
            $mcq_pass        = ( isset( $_POST['mcq_pass'] ) && is_array( $_POST['mcq_pass'] ) ) ? array_map( 'floatval', wp_unslash( $_POST['mcq_pass'] ) ) : array();
            $practical_marks = ( isset( $_POST['practical_marks'] ) && is_array( $_POST['practical_marks'] ) ) ? array_map( 'floatval', wp_unslash( $_POST['practical_marks'] ) ) : array();
            $practical_pass  = ( isset( $_POST['practical_pass'] ) && is_array( $_POST['practical_pass'] ) ) ? array_map( 'floatval', wp_unslash( $_POST['practical_pass'] ) ) : array();

            $raw_custom_names  = isset( $_POST['sub_comp_name'] ) && is_array( $_POST['sub_comp_name'] ) ? wp_unslash( $_POST['sub_comp_name'] ) : array();
            $raw_custom_totals = isset( $_POST['sub_comp_total'] ) && is_array( $_POST['sub_comp_total'] ) ? wp_unslash( $_POST['sub_comp_total'] ) : array();
            $raw_custom_passes = isset( $_POST['sub_comp_pass'] ) && is_array( $_POST['sub_comp_pass'] ) ? wp_unslash( $_POST['sub_comp_pass'] ) : array();

            if ( ! empty( $unit_keys ) && ! empty( $subject_name ) ) {
                $target_unit_ids = array();
                foreach ( $unit_keys as $ukey ) {
                    if ( strpos( $ukey, 'id:' ) === 0 ) {
                        $target_unit_ids[] = absint( str_replace( 'id:', '', $ukey ) );
                    } elseif ( strpos( $ukey, 'class:' ) === 0 ) {
                        $c_name = sanitize_text_field( str_replace( 'class:', '', $ukey ) );
                        $c_ids  = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM `{$table_units}` WHERE class_name = %s ORDER BY sort_order ASC, id ASC", $c_name ) );
                        if ( ! empty( $c_ids ) ) {
                            $target_unit_ids = array_merge( $target_unit_ids, array_map( 'absint', $c_ids ) );
                        }
                    }
                }
                $target_unit_ids = array_values( array_unique( array_filter( $target_unit_ids ) ) );

                $inserted_count = 0;
                if ( ! empty( $target_unit_ids ) ) {
                    foreach ( $target_unit_ids as $c_id ) {
                        $c_id_int = absint( $c_id );
                        if ( $c_id_int <= 0 ) {
                            continue;
                        }

                        foreach ( $subject_name as $index => $name ) {
                            $s_name = sanitize_text_field( (string) $name );
                            if ( empty( $s_name ) ) {
                                continue;
                            }

                            $s_code       = isset( $subject_code[ $index ] ) ? sanitize_text_field( (string) $subject_code[ $index ] ) : '';
                            $s_order      = isset( $subject_order[ $index ] ) ? intval( $subject_order[ $index ] ) : ( $index + 1 );
                            $s_preset     = isset( $preset_types[ $index ] ) ? sanitize_text_field( $preset_types[ $index ] ) : 'gen_100';
                            $s_total      = ( isset( $total_marks[ $index ] ) && floatval( $total_marks[ $index ] ) > 0 ) ? floatval( $total_marks[ $index ] ) : 100.00;
                            $s_pass       = isset( $pass_marks[ $index ] ) ? floatval( $pass_marks[ $index ] ) : 33.00;
                            $s_cq         = isset( $cq_marks[ $index ] ) ? floatval( $cq_marks[ $index ] ) : 0.00;
                            $s_cq_p       = isset( $cq_pass[ $index ] ) ? floatval( $cq_pass[ $index ] ) : 0.00;
                            $s_mcq        = isset( $mcq_marks[ $index ] ) ? floatval( $mcq_marks[ $index ] ) : 0.00;
                            $s_mcq_p      = isset( $mcq_pass[ $index ] ) ? floatval( $mcq_pass[ $index ] ) : 0.00;
                            $s_practical  = isset( $practical_marks[ $index ] ) ? floatval( $practical_marks[ $index ] ) : 0.00;
                            $s_pr_p       = isset( $practical_pass[ $index ] ) ? floatval( $practical_pass[ $index ] ) : 0.00;
                            $s_breakdown  = null;

                            if ( 'other' === $s_preset && isset( $raw_custom_names[ $index ] ) && is_array( $raw_custom_names[ $index ] ) ) {
                                $c_list = array();
                                foreach ( $raw_custom_names[ $index ] as $c_idx => $c_n ) {
                                    $c_n_clean = sanitize_text_field( $c_n );
                                    if ( empty( $c_n_clean ) ) {
                                        continue;
                                    }
                                    $c_t = isset( $raw_custom_totals[ $index ][ $c_idx ] ) ? floatval( $raw_custom_totals[ $index ][ $c_idx ] ) : 0;
                                    $c_p = isset( $raw_custom_passes[ $index ][ $c_idx ] ) ? floatval( $raw_custom_passes[ $index ][ $c_idx ] ) : 0;

                                    $c_list[] = array(
                                        'name'  => $c_n_clean,
                                        'total' => $c_t,
                                        'pass'  => $c_p,
                                    );
                                }

                                if ( ! empty( $c_list ) ) {
                                    $s_breakdown = wp_json_encode( $c_list );
                                    $s_cq        = 0.00;
                                    $s_cq_p      = 0.00;
                                    $s_mcq       = 0.00;
                                    $s_mcq_p     = 0.00;
                                    $s_practical = 0.00;
                                    $s_pr_p      = 0.00;
                                }
                            }

                            $wpdb->insert( 
                                $table_subjects, 
                                array( 
                                    'class_id'        => $c_id_int, 
                                    'subject_name'    => $s_name, 
                                    'subject_code'    => $s_code, 
                                    'subject_order'   => $s_order, 
                                    'total_marks'     => $s_total, 
                                    'pass_marks'      => $s_pass, 
                                    'cq_marks'        => $s_cq, 
                                    'cq_pass'         => $s_cq_p, 
                                    'mcq_marks'       => $s_mcq, 
                                    'mcq_pass'        => $s_mcq_p, 
                                    'practical_marks' => $s_practical, 
                                    'practical_pass'  => $s_pr_p, 
                                    'breakdown_data'  => $s_breakdown,
                                ), 
                                array( '%d', '%s', '%s', '%d', '%f', '%f', '%f', '%f', '%f', '%f', '%f', '%f', '%s' ) 
                            );
                            $inserted_count++;
                        }
                    }
                }

                if ( $inserted_count > 0 ) {
                    $redirect_target = add_query_arg( array( 'status' => 'subjects_added', 'count' => $inserted_count ), $base_url );
                    if ( ! headers_sent() ) {
                        wp_safe_redirect( $redirect_target );
                        exit;
                    } else {
                        echo '<script type="text/javascript">window.location.href=' . wp_json_encode( esc_url_raw( $redirect_target ) ) . ';</script>';
                        exit;
                    }
                }
            }
        }
    }

    // --------------------------------------------------------------------------
    // 3. HANDLE DELETE ACTION
    // --------------------------------------------------------------------------
    if ( isset( $_GET['action'] ) && 'delete_subject' === $_GET['action'] && isset( $_GET['id'] ) ) {
        $delete_id = absint( wp_unslash( $_GET['id'] ) );
        $del_nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

        if ( $delete_id > 0 && wp_verify_nonce( $del_nonce, 'delete_subject_action_' . $delete_id ) ) {
            $wpdb->delete( $table_subjects, array( 'id' => $delete_id ), array( '%d' ) );

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

    // --------------------------------------------------------------------------
    // 4. DATA QUERIES (RESTRICTED TO CLASSES 9 - 12 ONLY)
    // --------------------------------------------------------------------------
    $all_raw_units = $wpdb->get_results( 
        "SELECT id, class_name, section_name, sort_order 
         FROM `{$table_units}` 
         WHERE class_name != '' 
         ORDER BY sort_order ASC, CAST(class_name AS UNSIGNED) ASC, class_name ASC, section_name ASC", 
        ARRAY_A 
    );

    $display_units = array();
    $processed_classes = array();

    if ( ! empty( $all_raw_units ) ) {
        foreach ( $all_raw_units as $unit ) {
            $c_name     = trim( (string) $unit['class_name'] );
            $s_name     = trim( (string) $unit['section_name'] );
            $u_id       = absint( $unit['id'] );
            $sort_order = isset( $unit['sort_order'] ) ? (int) $unit['sort_order'] : 0;

            preg_match( '/\d+/', $c_name, $matches );
            $c_num = ! empty( $matches ) ? intval( $matches[0] ) : 0;

            // RESTRICT TO CLASSES 9, 10, 11, 12 ONLY
            if ( in_array( $c_num, array( 9, 10, 11, 12 ), true ) ) {
                $label = $c_name . ( '' !== $s_name ? ' (' . $s_name . ')' : '' );
                $display_units[] = array(
                    'key'          => 'id:' . $u_id,
                    'label'        => $label,
                    'class_name'   => $c_name,
                    'section_name' => $s_name,
                    'sort_order'   => $sort_order,
                );
            }
        }

        usort( $display_units, function( $a, $b ) {
            $order_a = isset( $a['sort_order'] ) ? (int) $a['sort_order'] : 0;
            $order_b = isset( $b['sort_order'] ) ? (int) $b['sort_order'] : 0;

            if ( $order_a !== $order_b ) {
                return $order_a - $order_b;
            }

            $res = strnatcasecmp( $a['class_name'], $b['class_name'] );
            return ( 0 === $res ) ? strnatcasecmp( $a['section_name'], $b['section_name'] ) : $res;
        } );
    }

    $subjects_list = $wpdb->get_results( "
        SELECT s.*, u.class_name, u.section_name, u.sort_order AS class_sort_order 
        FROM `{$table_subjects}` s 
        LEFT JOIN `{$table_units}` u ON s.class_id = u.id 
        WHERE u.class_name REGEXP '9|10|11|12'
        ORDER BY u.sort_order ASC, CAST(u.class_name AS UNSIGNED) ASC, u.class_name ASC, u.section_name ASC, s.subject_order ASC, s.subject_name ASC
    " );

    if ( ! empty( $subjects_list ) && is_array( $subjects_list ) ) {
        usort( $subjects_list, function( $a, $b ) {
            $c_order_a = isset( $a->class_sort_order ) ? (int) $a->class_sort_order : 0;
            $c_order_b = isset( $b->class_sort_order ) ? (int) $b->class_sort_order : 0;

            if ( $c_order_a !== $c_order_b ) {
                return $c_order_a - $c_order_b;
            }

            $class_a = $a->class_name ?: '';
            $class_b = $b->class_name ?: '';
            $res     = strnatcasecmp( (string) $class_a, (string) $class_b );
            if ( 0 === $res ) {
                $sec_a   = $a->section_name ?: '';
                $sec_b   = $b->section_name ?: '';
                $sec_res = strnatcasecmp( (string) $sec_a, (string) $sec_b );
                if ( 0 === $sec_res ) {
                    $order_a = intval( $a->subject_order ?? 0 );
                    $order_b = intval( $b->subject_order ?? 0 );
                    if ( $order_a === $order_b ) {
                        return strnatcasecmp( (string) $a->subject_name, (string) $b->subject_name );
                    }
                    return ( $order_a < $order_b ) ? -1 : 1;
                }
                return $sec_res;
            }
            return $res;
        } );
    }

    // Group subjects class-wise for clean sectional card display
    $grouped_subjects = array();
    if ( ! empty( $subjects_list ) ) {
        foreach ( $subjects_list as $sub_item ) {
            $c_lbl = ! empty( $sub_item->class_name ) ? $sub_item->class_name . ( ! empty( $sub_item->section_name ) ? ' (' . $sub_item->section_name . ')' : '' ) : 'Class 9';
            $grouped_subjects[ $c_lbl ][] = $sub_item;
        }
    }
    ?>

    <style>
        .ifs-educore-subjects-container {
            max-width: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
            box-sizing: border-box !important;
            font-family: inherit;
        }
        .ifs-educore-bento-card {
            background: #ffffff !important;
            border: 1px solid #e2e8f0 !important;
            border-radius: 14px !important;
            padding: 22px !important;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.02) !important;
            margin: 0 0 20px 0 !important;
            box-sizing: border-box !important;
            height: auto !important;
        }
        .ifs-educore-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 8px;
            margin-bottom: 16px;
            flex-wrap: wrap;
            gap: 10px;
        }
        .ifs-educore-card-title {
            font-size: 15px;
            font-weight: 800;
            color: #0f172a;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .ifs-educore-form-label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            color: #334155;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin-bottom: 6px;
        }
        .ifs-educore-field-input,
        .ifs-educore-field-select {
            width: 100%;
            height: 38px;
            padding: 0 10px;
            border: 1.5px solid #cbd5e1;
            border-radius: 8px;
            font-size: 13px;
            color: #0f172a;
            background: #ffffff;
            box-sizing: border-box;
            outline: none;
            transition: border-color 0.2s;
        }
        .ifs-educore-field-input:focus,
        .ifs-educore-field-select:focus {
            border-color: #00523c;
            box-shadow: 0 0 0 3px rgba(0, 82, 60, 0.12);
        }

        /* Class Selector Panel */
        .ifs-class-selector-panel {
            background: #f8fafc !important;
            border: 1.5px solid #e2e8f0 !important;
            border-radius: 10px !important;
            padding: 14px !important;
            box-sizing: border-box !important;
        }
        .ifs-class-panel-toolbar {
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            flex-wrap: wrap !important;
            gap: 8px !important;
            margin-bottom: 10px !important;
            padding-bottom: 10px !important;
            border-bottom: 1px solid #e2e8f0 !important;
        }
        .ifs-class-search-input {
            height: 32px !important;
            padding: 0 10px !important;
            font-size: 12.5px !important;
            border: 1px solid #cbd5e1 !important;
            border-radius: 6px !important;
            background: #ffffff !important;
            max-width: 200px !important;
            outline: none !important;
            box-sizing: border-box !important;
        }
        .ifs-class-toolbar-actions {
            display: flex !important;
            align-items: center !important;
            gap: 8px !important;
        }
        .ifs-class-count-badge {
            font-size: 11.5px !important;
            font-weight: 700 !important;
            background: #e2e8f0 !important;
            color: #475569 !important;
            padding: 3px 8px !important;
            border-radius: 999px !important;
        }
        .ifs-class-count-badge.has-selected {
            background: #dcfce7 !important;
            color: #15803d !important;
        }
        .ifs-class-btn-toggle {
            background: #ffffff !important;
            border: 1px solid #cbd5e1 !important;
            color: #334155 !important;
            padding: 4px 10px !important;
            border-radius: 6px !important;
            font-size: 11.5px !important;
            font-weight: 700 !important;
            cursor: pointer !important;
        }
        .ifs-class-btn-toggle:hover {
            background: #00523c !important;
            color: #ffffff !important;
            border-color: #00523c !important;
        }

        /* Class Cards Grid */
        .ifs-class-grid {
            display: grid !important;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)) !important;
            gap: 8px !important;
            max-height: 150px !important;
            overflow-y: auto !important;
            padding: 2px !important;
            box-sizing: border-box !important;
        }
        .ifs-class-card {
            display: flex !important;
            align-items: center !important;
            gap: 6px !important;
            background: #ffffff !important;
            border: 1px solid #e2e8f0 !important;
            padding: 7px 10px !important;
            border-radius: 6px !important;
            font-size: 13px !important;
            font-weight: 600 !important;
            color: #1e293b !important;
            cursor: pointer !important;
            user-select: none !important;
            box-sizing: border-box !important;
        }
        .ifs-class-card:hover {
            border-color: #94a3b8 !important;
            background: #f8fafc !important;
        }
        .ifs-class-card.is-active {
            border-color: #00523c !important;
            background: #f0fdf4 !important;
            color: #00523c !important;
        }
        .ifs-class-card input[type="checkbox"] {
            margin: 0 !important;
            width: 15px !important;
            height: 15px !important;
            cursor: pointer !important;
            accent-color: #00523c !important;
            flex-shrink: 0 !important;
        }

        /* Repeater Rows */
        .ifs-educore-repeater-row {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 14px;
            margin-bottom: 12px;
        }
        .ifs-educore-repeater-grid-top {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1.6fr 36px;
            gap: 10px;
            align-items: end;
            margin-bottom: 10px;
        }
        .ifs-educore-repeater-grid-marks {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 10px;
            background: #ffffff;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }

        /* Custom Breakdown Sub-Repeater inside Row */
        .ifs-custom-breakdown-subrepeater {
            display: none;
            background: #ffffff;
            border: 1.5px solid #00523c;
            border-radius: 8px;
            padding: 12px;
            margin-top: 10px;
        }
        .ifs-custom-breakdown-subrepeater.is-active {
            display: block;
        }
        .ifs-sub-item-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 32px;
            gap: 8px;
            align-items: center;
            margin-bottom: 6px;
        }
        .ifs-btn-del-subitem {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
            border-radius: 6px;
            height: 32px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            font-weight: bold;
        }

        .ifs-educore-btn-remove-row {
            height: 38px;
            width: 36px;
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .ifs-educore-btn-remove-row:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }
        .ifs-educore-btn-add-repeater {
            background: #f1f5f9;
            color: #00523c;
            border: 1.5px dashed #00523c;
            padding: 9px 16px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-right: 8px;
        }
        .ifs-educore-btn-submit {
            background: #00523c;
            color: #ffffff;
            border: none;
            padding: 10px 22px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 13.5px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 4px 12px rgba(0, 82, 60, 0.15);
        }
        .ifs-educore-btn-submit:hover {
            background: #047857;
        }

        /* Class-Wise Grouped Directory Cards */
        .ifs-class-group-card {
            background: #ffffff;
            border: 1.5px solid #e2e8f0;
            border-radius: 12px;
            margin-bottom: 16px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.01);
        }
        .ifs-class-group-header {
            background: #f8fafc;
            padding: 12px 18px;
            border-bottom: 1.5px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .ifs-class-group-title {
            margin: 0;
            font-size: 14.5px;
            font-weight: 800;
            color: #00523c;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .ifs-educore-architecture-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 13px;
        }
        .ifs-educore-architecture-table th {
            padding: 9px 12px;
            background: #ffffff;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            color: #64748b;
            border-bottom: 1.5px solid #e2e8f0;
            text-align: left;
        }
        .ifs-educore-architecture-table td {
            padding: 9px 12px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }
        .ifs-educore-order-badge {
            background: #f1f5f9;
            color: #475569;
            padding: 2px 7px;
            border-radius: 5px;
            font-weight: 700;
        }
        .ifs-educore-code-tag {
            font-size: 11px;
            background: #f1f5f9;
            padding: 2px 5px;
            border-radius: 4px;
            color: #475569;
            margin-left: 4px;
        }
        .ifs-educore-marks-badge {
            background: #eff6ff;
            color: #1d4ed8;
            padding: 2px 7px;
            border-radius: 5px;
            font-weight: 700;
        }
        .ifs-educore-breakdown-chip {
            font-size: 11.5px;
            color: #334155;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 3px 6px;
            border-radius: 5px;
            display: inline-flex;
            gap: 4px;
        }
        .ifs-educore-square-btn {
            padding: 4px 6px;
            border-radius: 6px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            border: 1px solid transparent;
        }
        .ifs-educore-btn-edit {
            background: #eff6ff;
            color: #2563eb;
            border-color: #bfdbfe;
        }
        .ifs-educore-btn-delete {
            background: #fee2e2;
            color: #dc2626;
            border-color: #fecaca;
        }

        /* Modal */
        .ifs-educore-modal-backdrop {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(15, 23, 42, 0.6);
            z-index: 99999;
            align-items: center;
            justify-content: center;
        }
        .ifs-educore-modal-backdrop.is-visible {
            display: flex;
        }
        .ifs-educore-modal-card {
            background: #ffffff;
            border-radius: 14px;
            padding: 22px;
            width: 100%;
            max-width: 660px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }
    </style>

    <div class="ifs-educore-subjects-container">

        <!-- Status Alerts -->
        <?php if ( isset( $_GET['status'] ) && 'subjects_added' === $_GET['status'] ) : ?>
            <div style="background:#ecfdf5; border-left:4px solid #00523c; color:#065f46; padding:10px 14px; border-radius:6px; font-weight:700; margin-bottom:14px;">
                <span class="dashicons dashicons-yes-alt" style="vertical-align:middle;"></span>
                <?php 
                    $added_count = isset( $_GET['count'] ) ? absint( wp_unslash( $_GET['count'] ) ) : 0;
                    printf( esc_html__( 'Successfully assigned %d subjects across classes (Grades 9-12).', 'ifsedu-school-management' ), absint( $added_count ) );
                ?>
            </div>
        <?php elseif ( isset( $_GET['status'] ) && 'updated' === $_GET['status'] ) : ?>
            <div style="background:#eff6ff; border-left:4px solid #2563eb; color:#1e40af; padding:10px 14px; border-radius:6px; font-weight:700; margin-bottom:14px;">
                <span class="dashicons dashicons-yes-alt" style="vertical-align:middle;"></span>
                <?php esc_html_e( 'Subject evaluation scheme updated successfully.', 'ifsedu-school-management' ); ?>
            </div>
        <?php elseif ( isset( $_GET['status'] ) && 'deleted' === $_GET['status'] ) : ?>
            <div style="background:#ecfdf5; border-left:4px solid #00523c; color:#065f46; padding:10px 14px; border-radius:6px; font-weight:700; margin-bottom:14px;">
                <span class="dashicons dashicons-yes-alt" style="vertical-align:middle;"></span>
                <?php esc_html_e( 'Academic subject deleted successfully.', 'ifsedu-school-management' ); ?>
            </div>
        <?php endif; ?>

        <!-- Assign Subjects Bento Card (Filtered to 9-12) -->
        <div class="ifs-educore-bento-card">
            <form method="POST" action="<?php echo esc_url( $base_url ); ?>" id="ifs-educore-main-subject-form">
                <?php wp_nonce_field( 'subject_setup_action', 'subject_setup_nonce' ); ?>
                
                <div style="margin-bottom: 16px;">
                    <label class="ifs-educore-form-label"><?php esc_html_e( 'Target Classes (Grades 9 - 12 Only)', 'ifsedu-school-management' ); ?> <span style="color:#dc2626;">*</span></label>
                    
                    <div class="ifs-class-selector-panel">
                        <div class="ifs-class-panel-toolbar">
                            <input type="text" id="classSearchInput" class="ifs-class-search-input" placeholder="<?php esc_attr_e( 'Search class 9-12...', 'ifsedu-school-management' ); ?>" autocomplete="off">
                            
                            <div class="ifs-class-toolbar-actions">
                                <span class="ifs-class-count-badge" id="selectedClassCountBadge">0 Selected</span>
                                <button type="button" class="ifs-class-btn-toggle" id="btnToggleAllClasses"><?php esc_html_e( 'Select All', 'ifsedu-school-management' ); ?></button>
                            </div>
                        </div>

                        <div class="ifs-class-grid" id="classGridContainer">
                            <?php if ( ! empty( $display_units ) ) : foreach ( $display_units as $unit ) : ?>
                                <label class="ifs-class-card" data-class-text="<?php echo esc_attr( strtolower( $unit['label'] ) ); ?>">
                                    <input type="checkbox" name="class_units[]" value="<?php echo esc_attr( $unit['key'] ); ?>" class="cb-class">
                                    <span><?php echo esc_html( $unit['label'] ); ?></span>
                                </label>
                            <?php endforeach; else : ?>
                                <div style="font-size:12.5px; color:#ef4444; padding: 10px; font-weight:700; grid-column: 1 / -1; text-align: center;">
                                    <?php esc_html_e( 'No Classes 9-12 configured in Academic Setup.', 'ifsedu-school-management' ); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div id="ifs-educore-subject-repeater-canvas" class="ifs-educore-repeater-canvas">
                    <div class="ifs-educore-repeater-row" data-row-index="0">
                        <div class="ifs-educore-repeater-grid-top">
                            <div>
                                <label class="ifs-educore-form-label"><?php esc_html_e( 'Subject Title', 'ifsedu-school-management' ); ?> <span style="color:#dc2626;">*</span></label>
                                <input type="text" name="subject_name[]" class="ifs-educore-field-input" placeholder="e.g. Physics / Higher Math" required>
                            </div>
                            <div>
                                <label class="ifs-educore-form-label"><?php esc_html_e( 'Code', 'ifsedu-school-management' ); ?></label>
                                <input type="text" name="subject_code[]" class="ifs-educore-field-input" placeholder="e.g. 136">
                            </div>
                            <div>
                                <label class="ifs-educore-form-label"><?php esc_html_e( 'Order', 'ifsedu-school-management' ); ?></label>
                                <input type="number" name="subject_order[]" class="ifs-educore-field-input f-order" value="1" placeholder="1">
                            </div>
                            <div>
                                <label class="ifs-educore-form-label"><?php esc_html_e( 'Distribution Preset', 'ifsedu-school-management' ); ?></label>
                                <select name="distribution_preset[]" class="ifs-educore-field-select preset-selector">
                                    <option value="gen_100">General (70/30)</option>
                                    <option value="sci_100">Science (50/25/25)</option>
                                    <option value="lang_100">Language (100 CQ)</option>
                                    <option value="jun_50">Junior Tier (35/15)</option>
                                    <option value="other"><?php esc_html_e( 'Other (Custom Breakdown)', 'ifsedu-school-management' ); ?></option>
                                </select>
                            </div>
                            <div>
                                <button type="button" class="ifs-educore-btn-remove-row btn-remove-row" disabled>
                                    <span class="dashicons dashicons-no"></span>
                                </button>
                            </div>
                        </div>

                        <div class="ifs-educore-repeater-grid-marks">
                            <div>
                                <label class="ifs-educore-form-label"><?php esc_html_e( 'Total / Pass', 'ifsedu-school-management' ); ?></label>
                                <div style="display:flex; gap:6px;">
                                    <input type="number" step="0.5" name="total_marks[]" class="ifs-educore-field-input f-total" value="100" placeholder="Total" required>
                                    <input type="number" step="0.5" name="pass_marks[]" class="ifs-educore-field-input f-pass" value="33" placeholder="Pass">
                                </div>
                            </div>
                            <div>
                                <label class="ifs-educore-form-label"><?php esc_html_e( 'CQ (Total / Pass)', 'ifsedu-school-management' ); ?></label>
                                <div style="display:flex; gap:6px;">
                                    <input type="number" step="0.5" name="cq_marks[]" class="ifs-educore-field-input f-cq" value="70">
                                    <input type="number" step="0.5" name="cq_pass[]" class="ifs-educore-field-input f-cq-pass" value="23">
                                </div>
                            </div>
                            <div>
                                <label class="ifs-educore-form-label"><?php esc_html_e( 'MCQ (Total / Pass)', 'ifsedu-school-management' ); ?></label>
                                <div style="display:flex; gap:6px;">
                                    <input type="number" step="0.5" name="mcq_marks[]" class="ifs-educore-field-input f-mcq" value="30">
                                    <input type="number" step="0.5" name="mcq_pass[]" class="ifs-educore-field-input f-mcq-pass" value="10">
                                </div>
                            </div>
                            <div>
                                <label class="ifs-educore-form-label"><?php esc_html_e( 'Practical (Tot / Pass)', 'ifsedu-school-management' ); ?></label>
                                <div style="display:flex; gap:6px;">
                                    <input type="number" step="0.5" name="practical_marks[]" class="ifs-educore-field-input f-pr" value="0">
                                    <input type="number" step="0.5" name="practical_pass[]" class="ifs-educore-field-input f-pr-pass" value="0">
                                </div>
                            </div>
                        </div>

                        <div class="ifs-custom-breakdown-subrepeater">
                            <div style="display:grid; grid-template-columns: 1fr 1fr auto; gap:12px; align-items:end; margin-bottom:12px; padding-bottom:10px; border-bottom:1px solid #e2e8f0;">
                                <div>
                                    <label class="ifs-educore-form-label" style="color:#00523c;"><?php esc_html_e( 'Subject Total Marks', 'ifsedu-school-management' ); ?> <span style="color:#dc2626;">*</span></label>
                                    <input type="number" step="0.5" class="ifs-educore-field-input custom-main-total" value="100" placeholder="100" required>
                                </div>
                                <div>
                                    <label class="ifs-educore-form-label" style="color:#00523c;"><?php esc_html_e( 'Subject Overall Pass Marks', 'ifsedu-school-management' ); ?> <span style="color:#dc2626;">*</span></label>
                                    <input type="number" step="0.5" class="ifs-educore-field-input custom-main-pass" value="33" placeholder="33" required>
                                </div>
                                <div>
                                    <button type="button" class="ifs-btn-add-subitem" style="background:#00523c; color:#fff; border:none; padding:8px 12px; border-radius:6px; font-size:12px; font-weight:700; cursor:pointer; height:38px;">+ Add Component</button>
                                </div>
                            </div>
                            <div style="margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
                                <small style="font-size:11.5px; font-weight:700; color:#475569; text-transform:uppercase;"><?php esc_html_e( 'Component Breakdown Items', 'ifsedu-school-management' ); ?></small>
                                <small style="font-size:11.5px; color:#64748b;" class="custom-sum-indicator">(Sum of items: Total 100 | Pass 33)</small>
                            </div>
                            <div class="ifs-subitems-container">
                                <div class="ifs-sub-item-row">
                                    <input type="text" name="sub_comp_name[0][]" class="ifs-educore-field-input sub-comp-name" placeholder="Component Name" style="height:34px; font-size:12.5px;" value="Written Examination">
                                    <input type="number" step="0.5" name="sub_comp_total[0][]" class="ifs-educore-field-input sub-comp-total" placeholder="Max" style="height:34px; font-size:12.5px;" value="60">
                                    <input type="number" step="0.5" name="sub_comp_pass[0][]" class="ifs-educore-field-input sub-comp-pass" placeholder="Pass" style="height:34px; font-size:12.5px;" value="20">
                                    <button type="button" class="ifs-btn-del-subitem" title="Remove">&times;</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div style="margin-top:14px;">
                    <button type="button" id="ifs-educore-btn-add-subject" class="ifs-educore-btn-add-repeater">
                        <span class="dashicons dashicons-plus-alt2"></span>
                        <?php esc_html_e( 'Add Another Subject Row', 'ifsedu-school-management' ); ?>
                    </button>

                    <button type="submit" name="save_subjects_repeater" class="ifs-educore-btn-submit">
                        <span class="dashicons dashicons-saved"></span>
                        <?php esc_html_e( 'Save All Subjects', 'ifsedu-school-management' ); ?>
                    </button>
                </div>
            </form>
        </div>

        <!-- Class-Wise Grouped Academic Subjects Directory Card (9-12) -->
        <div class="ifs-educore-bento-card">
            <div class="ifs-educore-card-header">
                <h5 class="ifs-educore-card-title">
                    <span class="dashicons dashicons-list-view"></span>
                    <?php esc_html_e( 'Academic Subjects Directory (Grades 9 - 12)', 'ifsedu-school-management' ); ?>
                </h5>
                
                <div style="display:flex; align-items:center; gap:10px;">
                    <select id="ifs-educore-class-filter" class="ifs-educore-field-select" style="max-width:200px; height:34px;">
                        <option value="all"><?php esc_html_e( '-- All Classes Filter --', 'ifsedu-school-management' ); ?></option>
                        <?php foreach ( $display_units as $unit ) : ?>
                            <option value="<?php echo esc_attr( strtolower( $unit['label'] ) ); ?>"><?php echo esc_html( $unit['label'] ); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <span style="background:#f1f5f9; padding:5px 10px; border-radius:6px; font-size:12px; font-weight:700; color:#475569;" id="ifs-educore-subject-count-pill">
                        <?php echo count( $subjects_list ); ?> <?php esc_html_e( 'Subjects', 'ifsedu-school-management' ); ?>
                    </span>
                </div>
            </div>

            <!-- Grouped Directory Matrix -->
            <div id="ifs-educore-grouped-directory">
                <?php if ( ! empty( $grouped_subjects ) ) : foreach ( $grouped_subjects as $cls_label => $subs_in_class ) : ?>
                    <div class="ifs-class-group-card" data-group-class="<?php echo esc_attr( strtolower( $cls_label ) ); ?>">
                        <div class="ifs-class-group-header">
                            <h6 class="ifs-class-group-title">
                                <span class="dashicons dashicons-welcome-learn-more" style="color:#00523c;"></span>
                                <?php echo esc_html( $cls_label ); ?>
                            </h6>
                            <span style="font-size:11.5px; font-weight:700; color:#64748b; background:#e2e8f0; padding:2px 8px; border-radius:999px;">
                                <?php echo count( $subs_in_class ); ?> Subjects
                            </span>
                        </div>
                        <div style="overflow-x:auto;">
                            <table class="ifs-educore-architecture-table">
                                <thead>
                                    <tr>
                                        <th style="width: 6%; text-align:center;"><?php esc_html_e( 'Order', 'ifsedu-school-management' ); ?></th>
                                        <th style="width: 32%;"><?php esc_html_e( 'Subject Title & Code', 'ifsedu-school-management' ); ?></th>
                                        <th style="width: 15%;"><?php esc_html_e( 'Full / Pass', 'ifsedu-school-management' ); ?></th>
                                        <th style="width: 35%;"><?php esc_html_e( 'Mark Distribution', 'ifsedu-school-management' ); ?></th>
                                        <th style="width: 12%; text-align:right;"><?php esc_html_e( 'Actions', 'ifsedu-school-management' ); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ( $subs_in_class as $sub_item ) : 
                                        $sub_internal_id = absint( $sub_item->id );
                                        $delete_url = wp_nonce_url( 
                                            add_query_arg( array( 'action' => 'delete_subject', 'id' => $sub_internal_id ), $base_url ), 
                                            'delete_subject_action_' . $sub_internal_id 
                                        );
                                        $custom_breakdowns = ! empty( $sub_item->breakdown_data ) ? json_decode( $sub_item->breakdown_data, true ) : array();
                                    ?>
                                        <tr>
                                            <td style="text-align:center;">
                                                <span class="ifs-educore-order-badge"><?php echo intval( $sub_item->subject_order ?? 0 ); ?></span>
                                            </td>
                                            <td>
                                                <strong style="color: #0f172a;"><?php echo esc_html( $sub_item->subject_name ); ?></strong>
                                                <?php if ( ! empty( $sub_item->subject_code ) ) : ?>
                                                    <span class="ifs-educore-code-tag"><?php echo esc_html( $sub_item->subject_code ); ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="ifs-educore-marks-badge"><?php echo floatval( $sub_item->total_marks ?? 100 ); ?></span>
                                                <small style="color:#64748b; font-weight:700;">(Pass: <?php echo floatval( $sub_item->pass_marks ?? 33 ); ?>)</small>
                                            </td>
                                            <td>
                                                <?php if ( ! empty( $custom_breakdowns ) && is_array( $custom_breakdowns ) ) : ?>
                                                    <div style="display:flex; flex-wrap:wrap; gap:4px;">
                                                        <?php foreach ( $custom_breakdowns as $cbk ) : ?>
                                                            <span class="ifs-educore-breakdown-chip" style="background:#f0fdf4; border-color:#bbf7d0; color:#15803d;">
                                                                <?php echo esc_html( $cbk['name'] ); ?>: <strong><?php echo floatval( $cbk['total'] ); ?></strong> <small>(&ge;<?php echo floatval( $cbk['pass'] ); ?>)</small>
                                                            </span>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php else : ?>
                                                    <div class="ifs-educore-breakdown-chip">
                                                        <span>CQ: <strong><?php echo floatval( $sub_item->cq_marks ?? 0 ); ?></strong> <small>(&ge;<?php echo floatval( $sub_item->cq_pass ?? 0 ); ?>)</small></span> |
                                                        <span>MCQ: <strong><?php echo floatval( $sub_item->mcq_marks ?? 0 ); ?></strong> <small>(&ge;<?php echo floatval( $sub_item->mcq_pass ?? 0 ); ?>)</small></span>
                                                        <?php if ( floatval( $sub_item->practical_marks ?? 0 ) > 0 ) : ?>
                                                            | <span>PR: <strong><?php echo floatval( $sub_item->practical_marks ); ?></strong> <small>(&ge;<?php echo floatval( $sub_item->practical_pass ?? 0 ); ?>)</small></span>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td style="text-align: right;">
                                                <div style="display:inline-flex; gap:6px;">
                                                    <button type="button" 
                                                            class="ifs-educore-square-btn ifs-educore-btn-edit btn-trigger-edit" 
                                                            data-id="<?php echo esc_attr( $sub_internal_id ); ?>"
                                                            data-unit-id="<?php echo esc_attr( $sub_item->class_id ); ?>"
                                                            data-class-name="<?php echo esc_attr( $sub_item->class_name ); ?>"
                                                            data-name="<?php echo esc_attr( $sub_item->subject_name ); ?>"
                                                            data-code="<?php echo esc_attr( $sub_item->subject_code ); ?>"
                                                            data-order="<?php echo esc_attr( $sub_item->subject_order ?? 0 ); ?>"
                                                            data-total="<?php echo esc_attr( $sub_item->total_marks ?? 100 ); ?>"
                                                            data-pass="<?php echo esc_attr( $sub_item->pass_marks ?? 33 ); ?>"
                                                            data-cq="<?php echo esc_attr( $sub_item->cq_marks ?? 0 ); ?>"
                                                            data-cq-pass="<?php echo esc_attr( $sub_item->cq_pass ?? 0 ); ?>"
                                                            data-mcq="<?php echo esc_attr( $sub_item->mcq_marks ?? 0 ); ?>"
                                                            data-mcq-pass="<?php echo esc_attr( $sub_item->mcq_pass ?? 0 ); ?>"
                                                            data-practical="<?php echo esc_attr( $sub_item->practical_marks ?? 0 ); ?>"
                                                            data-practical-pass="<?php echo esc_attr( $sub_item->practical_pass ?? 0 ); ?>"
                                                            data-breakdown="<?php echo esc_attr( ! empty( $sub_item->breakdown_data ) ? $sub_item->breakdown_data : '' ); ?>"
                                                            title="<?php esc_attr_e( 'Edit Subject Scheme', 'ifsedu-school-management' ); ?>">
                                                        <span class="dashicons dashicons-edit"></span>
                                                    </button>

                                                    <a href="<?php echo esc_url( $delete_url ); ?>" 
                                                       class="ifs-educore-square-btn ifs-educore-btn-delete" 
                                                       title="<?php esc_attr_e( 'Delete Subject', 'ifsedu-school-management' ); ?>" 
                                                       onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to remove this subject?', 'ifsedu-school-management' ) ); ?>');">
                                                        <span class="dashicons dashicons-trash"></span>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; else : ?>
                    <div style="text-align:center; padding: 30px; color: #94a3b8; background:#fff; border:1px solid #e2e8f0; border-radius:12px;">
                        <?php esc_html_e( 'No subjects assigned to classes 9-12 yet.', 'ifsedu-school-management' ); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Edit Subject Dynamic Modal -->
    <div class="ifs-educore-modal-backdrop" id="ifs-educore-edit-modal">
        <div class="ifs-educore-modal-card">
            <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #f1f5f9; padding-bottom:14px; margin-bottom:16px;">
                <h4 style="margin:0; font-size:16px; font-weight:800; color:#0f172a;"><?php esc_html_e( 'Edit Subject & Evaluation Scheme', 'ifsedu-school-management' ); ?></h4>
                <button type="button" id="ifs-educore-close-modal" style="background:none; border:none; font-size:22px; cursor:pointer; color:#64748b;">&times;</button>
            </div>

            <form id="ifs-educore-edit-subject-form" method="POST" action="<?php echo esc_url( $base_url ); ?>">
                <input type="hidden" id="edit_subject_id" name="subject_id" value="">
                <input type="hidden" name="educore_update_single_subject" value="1">
                <?php wp_nonce_field( 'ifs_educore_edit_subject_nonce', 'edit_subject_nonce_field' ); ?>

                <div style="display:grid; grid-template-columns: 2fr 1fr; gap:12px; margin-bottom: 12px;">
                    <div>
                        <label class="ifs-educore-form-label"><?php esc_html_e( 'Target Class (Grades 9-12)', 'ifsedu-school-management' ); ?> <span style="color:#dc2626;">*</span></label>
                        <div class="ifs-class-grid" id="edit_class_checkbox_container" style="max-height: 120px;">
                            <?php foreach ( $display_units as $unit ) : ?>
                                <label class="ifs-class-card">
                                    <input type="checkbox" name="class_units[]" class="edit-class-cb" value="<?php echo esc_attr( $unit['key'] ); ?>" data-unit-id="<?php echo esc_attr( str_replace('id:', '', $unit['key']) ); ?>" data-class-name="<?php echo esc_attr( $unit['class_name'] ); ?>">
                                    <span><?php echo esc_html( $unit['label'] ); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div>
                        <label class="ifs-educore-form-label"><?php esc_html_e( 'Preset Distribution', 'ifsedu-school-management' ); ?></label>
                        <select name="preset_type" class="ifs-educore-field-select" id="edit_preset_selector">
                            <option value="gen_100">General (70/30)</option>
                            <option value="sci_100">Science (50/25/25)</option>
                            <option value="lang_100">Language (100 CQ)</option>
                            <option value="jun_50">Junior Tier (35/15)</option>
                            <option value="other"><?php esc_html_e( 'Other (Custom Breakdown)', 'ifsedu-school-management' ); ?></option>
                        </select>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: 2fr 1fr 1fr; gap:12px; margin-bottom: 12px;">
                    <div>
                        <label class="ifs-educore-form-label"><?php esc_html_e( 'Subject Name', 'ifsedu-school-management' ); ?> <span style="color:#dc2626;">*</span></label>
                        <input type="text" id="edit_subject_name" name="subject_name" class="ifs-educore-field-input" required>
                    </div>
                    <div>
                        <label class="ifs-educore-form-label"><?php esc_html_e( 'Code', 'ifsedu-school-management' ); ?></label>
                        <input type="text" id="edit_subject_code" name="subject_code" class="ifs-educore-field-input">
                    </div>
                    <div>
                        <label class="ifs-educore-form-label"><?php esc_html_e( 'Order', 'ifsedu-school-management' ); ?></label>
                        <input type="number" id="edit_subject_order" name="subject_order" class="ifs-educore-field-input" value="0">
                    </div>
                </div>

                <!-- Edit Modal Standard Mark Inputs -->
                <div id="edit_modal_standard_grid">
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom: 12px;">
                        <div>
                            <label class="ifs-educore-form-label"><?php esc_html_e( 'Full Marks', 'ifsedu-school-management' ); ?> <span style="color:#dc2626;">*</span></label>
                            <input type="number" step="0.5" id="edit_total_marks" name="total_marks" class="ifs-educore-field-input" required>
                        </div>
                        <div>
                            <label class="ifs-educore-form-label"><?php esc_html_e( 'Overall Pass Marks', 'ifsedu-school-management' ); ?></label>
                            <input type="number" step="0.5" id="edit_pass_marks" name="pass_marks" class="ifs-educore-field-input">
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap:10px; margin-bottom: 16px; background:#f8fafc; padding:12px; border-radius:10px; border:1px solid #e2e8f0;">
                        <div>
                            <label class="ifs-educore-form-label"><?php esc_html_e( 'CQ (Total / Pass)', 'ifsedu-school-management' ); ?></label>
                            <div style="display:flex; gap:4px;">
                                <input type="number" step="0.5" id="edit_cq_marks" name="cq_marks" class="ifs-educore-field-input" placeholder="CQ">
                                <input type="number" step="0.5" id="edit_cq_pass" name="cq_pass" class="ifs-educore-field-input" placeholder="Pass">
                            </div>
                        </div>
                        <div>
                            <label class="ifs-educore-form-label"><?php esc_html_e( 'MCQ (Total / Pass)', 'ifsedu-school-management' ); ?></label>
                            <div style="display:flex; gap:4px;">
                                <input type="number" step="0.5" id="edit_mcq_marks" name="mcq_marks" class="ifs-educore-field-input" placeholder="MCQ">
                                <input type="number" step="0.5" id="edit_mcq_pass" name="mcq_pass" class="ifs-educore-field-input" placeholder="Pass">
                            </div>
                        </div>
                        <div>
                            <label class="ifs-educore-form-label"><?php esc_html_e( 'Practical (Tot / Pass)', 'ifsedu-school-management' ); ?></label>
                            <div style="display:flex; gap:4px;">
                                <input type="number" step="0.5" id="edit_practical_marks" name="practical_marks" class="ifs-educore-field-input" placeholder="PR">
                                <input type="number" step="0.5" id="edit_practical_pass" name="practical_pass" class="ifs-educore-field-input" placeholder="Pass">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Edit Modal Custom Breakdown Container -->
                <div id="edit_modal_custom_breakdown" style="display:none; background:#ffffff; border:1.5px solid #00523c; border-radius:8px; padding:12px; margin-bottom:16px;">
                    <div style="display:grid; grid-template-columns: 1fr 1fr auto; gap:12px; align-items:end; margin-bottom:12px; padding-bottom:10px; border-bottom:1px solid #e2e8f0;">
                        <div>
                            <label class="ifs-educore-form-label" style="color:#00523c;"><?php esc_html_e( 'Subject Total Marks', 'ifsedu-school-management' ); ?> <span style="color:#dc2626;">*</span></label>
                            <input type="number" step="0.5" id="edit_custom_main_total" class="ifs-educore-field-input" value="100" placeholder="100">
                        </div>
                        <div>
                            <label class="ifs-educore-form-label" style="color:#00523c;"><?php esc_html_e( 'Subject Overall Pass Marks', 'ifsedu-school-management' ); ?> <span style="color:#dc2626;">*</span></label>
                            <input type="number" step="0.5" id="edit_custom_main_pass" class="ifs-educore-field-input" value="33" placeholder="33">
                        </div>
                        <div>
                            <button type="button" id="edit_btn_add_subitem" style="background:#00523c; color:#fff; border:none; padding:8px 12px; border-radius:6px; font-size:12px; font-weight:700; cursor:pointer; height:38px;">+ Add Component</button>
                        </div>
                    </div>
                    <div style="margin-bottom:6px; display:flex; justify-content:space-between; align-items:center;">
                        <small style="font-size:11.5px; font-weight:700; color:#475569; text-transform:uppercase;"><?php esc_html_e( 'Component Breakdown Items', 'ifsedu-school-management' ); ?></small>
                        <small style="font-size:11.5px; color:#64748b;" id="edit_custom_sum_indicator"></small>
                    </div>
                    <div id="edit_subitems_container"></div>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:10px;">
                    <button type="button" class="ifs-educore-square-btn" style="background:#f1f5f9; color:#475569; padding:8px 16px; font-weight:700;" id="ifs-educore-cancel-edit"><?php esc_html_e( 'Cancel', 'ifsedu-school-management' ); ?></button>
                    <button type="submit" class="ifs-educore-btn-submit" id="ifs-educore-save-edit-btn">
                        <span class="dashicons dashicons-saved"></span> <?php esc_html_e( 'Update Scheme', 'ifsedu-school-management' ); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php
}

educore_render_subjects_view();
?>

<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function() {
    var grid = document.getElementById('classGridContainer');
    var toggleBtn = document.getElementById('btnToggleAllClasses');
    var searchInput = document.getElementById('classSearchInput');
    var countBadge = document.getElementById('selectedClassCountBadge');
    var mainForm = document.getElementById('ifs-educore-main-subject-form');

    function updateSelectionState() {
        if (!grid) return;
        var allCheckboxes = grid.querySelectorAll('.cb-class');
        var checkedCheckboxes = grid.querySelectorAll('.cb-class:checked');
        var count = checkedCheckboxes.length;

        if (countBadge) {
            countBadge.textContent = count + ' Selected';
            if (count > 0) {
                countBadge.classList.add('has-selected');
            } else {
                countBadge.classList.remove('has-selected');
            }
        }

        allCheckboxes.forEach(function(cb) {
            var card = cb.closest('.ifs-class-card');
            if (card) {
                if (cb.checked) {
                    card.classList.add('is-active');
                } else {
                    card.classList.remove('is-active');
                }
            }
        });

        if (toggleBtn) {
            var visibleCheckboxes = grid.querySelectorAll('.ifs-class-card:not([style*="display: none"]) .cb-class');
            var visibleChecked = grid.querySelectorAll('.ifs-class-card:not([style*="display: none"]) .cb-class:checked');
            var allVisibleChecked = visibleCheckboxes.length > 0 && visibleCheckboxes.length === visibleChecked.length;
            toggleBtn.textContent = allVisibleChecked ? '<?php echo esc_js( __( 'Deselect All', 'ifsedu-school-management' ) ); ?>' : '<?php echo esc_js( __( 'Select All', 'ifsedu-school-management' ) ); ?>';
        }
    }

    if (searchInput && grid) {
        searchInput.addEventListener('input', function() {
            var q = this.value.toLowerCase().trim();
            var cards = grid.querySelectorAll('.ifs-class-card');
            cards.forEach(function(card) {
                var text = card.getAttribute('data-class-text') || '';
                if (!q || text.indexOf(q) !== -1) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
            updateSelectionState();
        });
    }

    if (grid) {
        grid.addEventListener('change', function(e) {
            if (e.target.classList.contains('cb-class')) {
                updateSelectionState();
            }
        });
    }

    if (toggleBtn && grid) {
        toggleBtn.addEventListener('click', function(e) {
            e.preventDefault();
            var visibleCheckboxes = grid.querySelectorAll('.ifs-class-card:not([style*="display: none"]) .cb-class');
            var visibleChecked = grid.querySelectorAll('.ifs-class-card:not([style*="display: none"]) .cb-class:checked');
            var shouldCheckAll = visibleCheckboxes.length !== visibleChecked.length;

            visibleCheckboxes.forEach(function(cb) {
                cb.checked = shouldCheckAll;
            });
            updateSelectionState();
        });
    }

    function detectMatchingPreset(total, pass, cq, cqPass, mcq, mcqPass, pr, prPass, breakdownData) {
        if (breakdownData && breakdownData.length > 2) {
            return 'other';
        }
        if (total == 100 && pass == 33 && cq == 70 && cqPass == 23 && mcq == 30 && mcqPass == 10 && pr == 0 && prPass == 0) {
            return 'gen_100';
        } else if (total == 100 && pass == 33 && cq == 50 && cqPass == 17 && mcq == 25 && mcqPass == 8 && pr == 25 && prPass == 8) {
            return 'sci_100';
        } else if (total == 100 && pass == 33 && cq == 100 && cqPass == 33 && mcq == 0 && mcqPass == 0 && pr == 0 && prPass == 0) {
            return 'lang_100';
        } else if (total == 50 && pass == 17 && cq == 35 && cqPass == 12 && mcq == 15 && mcqPass == 5 && pr == 0 && prPass == 0) {
            return 'jun_50';
        }
        return 'other';
    }

    function syncCustomBreakdownTotals(row) {
        if (!row) return;
        var subItems = row.querySelectorAll('.ifs-sub-item-row');
        if (subItems.length === 0) return;

        var sumTotal = 0;
        var sumPass = 0;

        subItems.forEach(function(itemRow) {
            var m = parseFloat(itemRow.querySelector('.sub-comp-total').value) || 0;
            var p = parseFloat(itemRow.querySelector('.sub-comp-pass').value) || 0;
            sumTotal += m;
            sumPass += p;
        });

        var mainTotalInp = row.querySelector('.custom-main-total');
        var mainPassInp = row.querySelector('.custom-main-pass');
        var fTotal = row.querySelector('.f-total');
        var fPass = row.querySelector('.f-pass');

        var finalTot = mainTotalInp ? (parseFloat(mainTotalInp.value) || sumTotal) : sumTotal;
        var finalPas = mainPassInp ? (parseFloat(mainPassInp.value) || sumPass) : sumPass;

        if (fTotal) fTotal.value = finalTot;
        if (fPass) fPass.value = finalPas;

        var ind = row.querySelector('.custom-sum-indicator');
        if (ind) ind.textContent = '(Sum of items: Total ' + sumTotal + ' | Pass ' + sumPass + ')';
    }

    function syncModalCustomBreakdownTotals() {
        var modal = document.getElementById('ifs-educore-edit-modal');
        if (!modal) return;
        var subItems = modal.querySelectorAll('#edit_subitems_container .ifs-sub-item-row');
        var sumTotal = 0;
        var sumPass = 0;

        subItems.forEach(function(itemRow) {
            var m = parseFloat(itemRow.querySelector('.sub-comp-total').value) || 0;
            var p = parseFloat(itemRow.querySelector('.sub-comp-pass').value) || 0;
            sumTotal += m;
            sumPass += p;
        });

        var mainTotInp = document.getElementById('edit_custom_main_total');
        var mainPasInp = document.getElementById('edit_custom_main_pass');
        var editTot = document.getElementById('edit_total_marks');
        var editPas = document.getElementById('edit_pass_marks');

        var finalTot = mainTotInp ? (parseFloat(mainTotInp.value) || sumTotal) : sumTotal;
        var finalPas = mainPasInp ? (parseFloat(mainPasInp.value) || sumPass) : sumPass;

        if (editTot) editTot.value = finalTot;
        if (editPas) editPas.value = finalPas;

        var ind = document.getElementById('edit_custom_sum_indicator');
        if (ind) ind.textContent = '(Sum of items: Total ' + sumTotal + ' | Pass ' + sumPass + ')';
    }

    function applyPresetValues(totalInp, passInp, cqInp, cqPass, mcqInp, mcqPass, prInp, prPass, presetKey, rowContext) {
        if (!totalInp) return;
        var standardGrid = rowContext ? rowContext.querySelector('.ifs-educore-repeater-grid-marks') : null;
        var subRepeater = rowContext ? rowContext.querySelector('.ifs-custom-breakdown-subrepeater') : null;

        if (presetKey === 'other') {
            if (standardGrid) standardGrid.style.display = 'none';
            if (subRepeater) {
                subRepeater.classList.add('is-active');
                syncCustomBreakdownTotals(rowContext);
            }
        } else {
            if (standardGrid) standardGrid.style.display = 'grid';
            if (subRepeater) subRepeater.classList.remove('is-active');

            if (presetKey === 'gen_100') {
                totalInp.value = 100; passInp.value = 33;
                cqInp.value = 70; cqPass.value = 23;
                mcqInp.value = 30; mcqPass.value = 10;
                prInp.value = 0; prPass.value = 0;
            } else if (presetKey === 'sci_100') {
                totalInp.value = 100; passInp.value = 33;
                cqInp.value = 50; cqPass.value = 17;
                mcqInp.value = 25; mcqPass.value = 8;
                prInp.value = 25; prPass.value = 8;
            } else if (presetKey === 'lang_100') {
                totalInp.value = 100; passInp.value = 33;
                cqInp.value = 100; cqPass.value = 33;
                mcqInp.value = 0; mcqPass.value = 0;
                prInp.value = 0; prPass.value = 0;
            } else if (presetKey === 'jun_50') {
                totalInp.value = 50; passInp.value = 17;
                cqInp.value = 35; cqPass.value = 12;
                mcqInp.value = 15; mcqPass.value = 5;
                prInp.value = 0; prPass.value = 0;
            }
        }
    }

    if (mainForm) {
        mainForm.addEventListener('submit', function(e) {
            var selectedClasses = mainForm.querySelectorAll('input[name="class_units[]"]:checked');
            if (selectedClasses.length === 0) {
                alert('<?php echo esc_js( __( 'Please select at least one class checkbox before saving.', 'ifsedu-school-management' ) ); ?>');
                e.preventDefault();
            }
        });
    }

    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('preset-selector')) {
            var row = e.target.closest('.ifs-educore-repeater-row');
            if (row) {
                applyPresetValues(
                    row.querySelector('.f-total'), row.querySelector('.f-pass'),
                    row.querySelector('.f-cq'), row.querySelector('.f-cq-pass'),
                    row.querySelector('.f-mcq'), row.querySelector('.f-mcq-pass'),
                    row.querySelector('.f-pr'), row.querySelector('.f-pr-pass'),
                    e.target.value,
                    row
                );
            }
        }
        if (e.target.id === 'edit_preset_selector') {
            var modalStdGrid = document.getElementById('edit_modal_standard_grid');
            var modalCustom = document.getElementById('edit_modal_custom_breakdown');
            if (e.target.value === 'other') {
                if (modalStdGrid) modalStdGrid.style.display = 'none';
                if (modalCustom) modalCustom.style.display = 'block';
                syncModalCustomBreakdownTotals();
            } else {
                if (modalStdGrid) modalStdGrid.style.display = 'block';
                if (modalCustom) modalCustom.style.display = 'none';
            }
            applyPresetValues(
                document.getElementById('edit_total_marks'), document.getElementById('edit_pass_marks'),
                document.getElementById('edit_cq_marks'), document.getElementById('edit_cq_pass'),
                document.getElementById('edit_mcq_marks'), document.getElementById('edit_mcq_pass'),
                document.getElementById('edit_practical_marks'), document.getElementById('edit_practical_pass'),
                e.target.value,
                null
            );
        }
    });

    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('ifs-btn-add-subitem')) {
            var parentRow = e.target.closest('.ifs-educore-repeater-row');
            var rowIndex = parentRow ? parentRow.getAttribute('data-row-index') : 0;
            var subRepeater = e.target.closest('.ifs-custom-breakdown-subrepeater');
            var container = subRepeater.querySelector('.ifs-subitems-container');
            var newSubRow = document.createElement('div');
            newSubRow.className = 'ifs-sub-item-row';
            newSubRow.innerHTML = `
                <input type="text" name="sub_comp_name[${rowIndex}][]" class="ifs-educore-field-input sub-comp-name" placeholder="Component Name" style="height:34px; font-size:12.5px;">
                <input type="number" step="0.5" name="sub_comp_total[${rowIndex}][]" class="ifs-educore-field-input sub-comp-total" placeholder="Max" style="height:34px; font-size:12.5px;" value="20">
                <input type="number" step="0.5" name="sub_comp_pass[${rowIndex}][]" class="ifs-educore-field-input sub-comp-pass" placeholder="Pass" style="height:34px; font-size:12.5px;" value="7">
                <button type="button" class="ifs-btn-del-subitem" title="Remove">&times;</button>
            `;
            container.appendChild(newSubRow);
            syncCustomBreakdownTotals(parentRow);
        }

        if (e.target.id === 'edit_btn_add_subitem') {
            var editContainer = document.getElementById('edit_subitems_container');
            var newEditRow = document.createElement('div');
            newEditRow.className = 'ifs-sub-item-row';
            newEditRow.innerHTML = `
                <input type="text" name="sub_comp_name[]" class="ifs-educore-field-input sub-comp-name" placeholder="Component Name" style="height:34px; font-size:12.5px;">
                <input type="number" step="0.5" name="sub_comp_total[]" class="ifs-educore-field-input sub-comp-total" placeholder="Max" style="height:34px; font-size:12.5px;" value="20">
                <input type="number" step="0.5" name="sub_comp_pass[]" class="ifs-educore-field-input sub-comp-pass" placeholder="Pass" style="height:34px; font-size:12.5px;" value="7">
                <button type="button" class="ifs-btn-del-subitem" title="Remove">&times;</button>
            `;
            editContainer.appendChild(newEditRow);
            syncModalCustomBreakdownTotals();
        }

        if (e.target.classList.contains('ifs-btn-del-subitem')) {
            var rowToDel = e.target.closest('.ifs-sub-item-row');
            var parentRepeaterRow = e.target.closest('.ifs-educore-repeater-row');
            if (rowToDel) {
                rowToDel.remove();
                if (parentRepeaterRow) {
                    syncCustomBreakdownTotals(parentRepeaterRow);
                } else {
                    syncModalCustomBreakdownTotals();
                }
            }
        }
    });

    document.addEventListener('input', function(e) {
        if (e.target.classList.contains('sub-comp-total') || e.target.classList.contains('sub-comp-pass') || e.target.classList.contains('custom-main-total') || e.target.classList.contains('custom-main-pass')) {
            var repeaterRow = e.target.closest('.ifs-educore-repeater-row');
            if (repeaterRow) syncCustomBreakdownTotals(repeaterRow);
        }

        if (e.target.id === 'edit_custom_main_total' || e.target.id === 'edit_custom_main_pass' || (e.target.closest('#edit_subitems_container') && (e.target.classList.contains('sub-comp-total') || e.target.classList.contains('sub-comp-pass')))) {
            syncModalCustomBreakdownTotals();
        }
    });

    var canvas = document.getElementById('ifs-educore-subject-repeater-canvas');
    var addBtn = document.getElementById('ifs-educore-btn-add-subject');

    function updateRemoveButtons() {
        if (!canvas) return;
        var rows = canvas.querySelectorAll('.ifs-educore-repeater-row');
        rows.forEach(function(row, idx) {
            row.setAttribute('data-row-index', idx);
            row.querySelectorAll('.sub-comp-name').forEach(function(i) { i.name = `sub_comp_name[${idx}][]`; });
            row.querySelectorAll('.sub-comp-total').forEach(function(i) { i.name = `sub_comp_total[${idx}][]`; });
            row.querySelectorAll('.sub-comp-pass').forEach(function(i) { i.name = `sub_comp_pass[${idx}][]`; });

            var btn = row.querySelector('.btn-remove-row');
            if (rows.length > 1) {
                btn.removeAttribute('disabled');
            } else {
                btn.setAttribute('disabled', 'disabled');
            }
        });
    }

    if (addBtn && canvas) {
        addBtn.addEventListener('click', function() {
            var rows = canvas.querySelectorAll('.ifs-educore-repeater-row');
            var newRow = rows[0].cloneNode(true);
            var nextIndex = rows.length;

            newRow.setAttribute('data-row-index', nextIndex);
            newRow.querySelectorAll('input[type="text"]').forEach(function(inp) { inp.value = ''; });
            var orderInput = newRow.querySelector('.f-order');
            if (orderInput) {
                orderInput.value = nextIndex + 1;
            }

            var presetDropdown = newRow.querySelector('.preset-selector');
            if (presetDropdown) {
                presetDropdown.value = 'gen_100';
            }

            var subRepeater = newRow.querySelector('.ifs-custom-breakdown-subrepeater');
            if (subRepeater) {
                subRepeater.classList.remove('is-active');
            }
            var stdGrid = newRow.querySelector('.ifs-educore-repeater-grid-marks');
            if (stdGrid) {
                stdGrid.style.display = 'grid';
            }

            applyPresetValues(
                newRow.querySelector('.f-total'), newRow.querySelector('.f-pass'),
                newRow.querySelector('.f-cq'), newRow.querySelector('.f-cq-pass'),
                newRow.querySelector('.f-mcq'), newRow.querySelector('.f-mcq-pass'),
                newRow.querySelector('.f-pr'), newRow.querySelector('.f-pr-pass'),
                'gen_100',
                newRow
            );

            canvas.appendChild(newRow);
            updateRemoveButtons();
        });

        canvas.addEventListener('click', function(e) {
            var btn = e.target.closest('.btn-remove-row');
            if (btn && !btn.hasAttribute('disabled')) {
                var row = btn.closest('.ifs-educore-repeater-row');
                if (row) {
                    row.remove();
                    updateRemoveButtons();
                }
            }
        });
    }

    // Real-Time Table Filter
    var filterSelect = document.getElementById('ifs-educore-class-filter');
    var groupCards = document.querySelectorAll('.ifs-class-group-card');
    var countPill = document.getElementById('ifs-educore-subject-count-pill');

    if (filterSelect) {
        filterSelect.addEventListener('change', function() {
            var selectedFilter = this.value.toLowerCase().trim();
            var visibleCardCount = 0;

            groupCards.forEach(function(card) {
                var groupClass = (card.getAttribute('data-group-class') || '').toLowerCase().trim();
                if (selectedFilter === 'all' || groupClass.indexOf(selectedFilter) !== -1) {
                    card.style.display = '';
                    visibleCardCount++;
                } else {
                    card.style.display = 'none';
                }
            });
        });
    }

    var modal = document.getElementById('ifs-educore-edit-modal');
    var closeModalBtn = document.getElementById('ifs-educore-close-modal');
    var cancelModalBtn = document.getElementById('ifs-educore-cancel-edit');
    var editForm = document.getElementById('ifs-educore-edit-subject-form');

    function hideModal() {
        if (modal) modal.classList.remove('is-visible');
    }

    if (closeModalBtn) closeModalBtn.addEventListener('click', hideModal);
    if (cancelModalBtn) cancelModalBtn.addEventListener('click', hideModal);

    document.addEventListener('click', function(e) {
        var editBtn = e.target.closest('.btn-trigger-edit');
        if (editBtn) {
            var targetUnitId = editBtn.getAttribute('data-unit-id');
            var targetClassName = editBtn.getAttribute('data-class-name');

            var totVal = editBtn.getAttribute('data-total') || 100;
            var passVal = editBtn.getAttribute('data-pass') || 33;
            var cqVal = editBtn.getAttribute('data-cq') || 0;
            var cqPassVal = editBtn.getAttribute('data-cq-pass') || 0;
            var mcqVal = editBtn.getAttribute('data-mcq') || 0;
            var mcqPassVal = editBtn.getAttribute('data-mcq-pass') || 0;
            var prVal = editBtn.getAttribute('data-practical') || 0;
            var prPassVal = editBtn.getAttribute('data-practical-pass') || 0;
            var breakdownRaw = editBtn.getAttribute('data-breakdown') || '';

            document.getElementById('edit_subject_id').value         = editBtn.getAttribute('data-id');
            document.getElementById('edit_subject_name').value       = editBtn.getAttribute('data-name');
            document.getElementById('edit_subject_code').value       = editBtn.getAttribute('data-code');
            document.getElementById('edit_subject_order').value      = editBtn.getAttribute('data-order') || 0;
            document.getElementById('edit_total_marks').value        = totVal;
            document.getElementById('edit_pass_marks').value         = passVal;
            document.getElementById('edit_cq_marks').value           = cqVal;
            document.getElementById('edit_cq_pass').value            = cqPassVal;
            document.getElementById('edit_mcq_marks').value          = mcqVal;
            document.getElementById('edit_mcq_pass').value           = mcqPassVal;
            document.getElementById('edit_practical_marks').value    = prVal;
            document.getElementById('edit_practical_pass').value     = prPassVal;

            var editPresetDropdown = document.getElementById('edit_preset_selector');
            var modalStdGrid = document.getElementById('edit_modal_standard_grid');
            var modalCustom = document.getElementById('edit_modal_custom_breakdown');
            var editSubContainer = document.getElementById('edit_subitems_container');

            var detected = detectMatchingPreset(totVal, passVal, cqVal, cqPassVal, mcqVal, mcqPassVal, prVal, prPassVal, breakdownRaw);
            if (editPresetDropdown) {
                editPresetDropdown.value = detected;
            }

            if (detected === 'other') {
                if (modalStdGrid) modalStdGrid.style.display = 'none';
                if (modalCustom) modalCustom.style.display = 'block';

                document.getElementById('edit_custom_main_total').value = totVal;
                document.getElementById('edit_custom_main_pass').value = passVal;

                if (editSubContainer) {
                    editSubContainer.innerHTML = '';
                    var parsedBreakdown = [];
                    try {
                        parsedBreakdown = JSON.parse(breakdownRaw);
                    } catch (err) {}

                    if (Array.isArray(parsedBreakdown) && parsedBreakdown.length > 0) {
                        parsedBreakdown.forEach(function(item) {
                            var div = document.createElement('div');
                            div.className = 'ifs-sub-item-row';
                            div.innerHTML = `
                                <input type="text" name="sub_comp_name[]" class="ifs-educore-field-input sub-comp-name" placeholder="Component Name" style="height:34px; font-size:12.5px;" value="${item.name}">
                                <input type="number" step="0.5" name="sub_comp_total[]" class="ifs-educore-field-input sub-comp-total" placeholder="Max" style="height:34px; font-size:12.5px;" value="${item.total}">
                                <input type="number" step="0.5" name="sub_comp_pass[]" class="ifs-educore-field-input sub-comp-pass" placeholder="Pass" style="height:34px; font-size:12.5px;" value="${item.pass}">
                                <button type="button" class="ifs-btn-del-subitem" title="Remove">&times;</button>
                            `;
                            editSubContainer.appendChild(div);
                        });
                    } else {
                        editSubContainer.innerHTML = `
                            <div class="ifs-sub-item-row">
                                <input type="text" name="sub_comp_name[]" class="ifs-educore-field-input sub-comp-name" placeholder="Component Name" style="height:34px; font-size:12.5px;" value="Written Exam">
                                <input type="number" step="0.5" name="sub_comp_total[]" class="ifs-educore-field-input sub-comp-total" placeholder="Max" style="height:34px; font-size:12.5px;" value="${totVal}">
                                <input type="number" step="0.5" name="sub_comp_pass[]" class="ifs-educore-field-input sub-comp-pass" placeholder="Pass" style="height:34px; font-size:12.5px;" value="${passVal}">
                                <button type="button" class="ifs-btn-del-subitem" title="Remove">&times;</button>
                            </div>
                        `;
                    }
                    syncModalCustomBreakdownTotals();
                }
            } else {
                if (modalStdGrid) modalStdGrid.style.display = 'block';
                if (modalCustom) modalCustom.style.display = 'none';
            }

            document.querySelectorAll('#edit_class_checkbox_container .edit-class-cb').forEach(function(cb) {
                var cbUnitId = cb.getAttribute('data-unit-id');
                var cbClassName = cb.getAttribute('data-class-name');

                if (cbUnitId === targetUnitId || cbClassName === targetClassName) {
                    cb.checked = true;
                } else {
                    cb.checked = false;
                }
            });

            modal.classList.add('is-visible');
        }
    });

    if (editForm) {
        editForm.addEventListener('submit', function(e) {
            var selectedEditClasses = editForm.querySelectorAll('input[name="class_units[]"]:checked');
            if (selectedEditClasses.length === 0) {
                alert('<?php echo esc_js( __( 'Please select at least one class checkbox.', 'ifsedu-school-management' ) ); ?>');
                e.preventDefault();
            }
        });
    }

    updateSelectionState();
});
</script>
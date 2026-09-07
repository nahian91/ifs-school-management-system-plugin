<?php
/**
 * Standalone Single Examination Scheme Details View
 * File: inc/exams/exam-view.php
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function educore_exam_single_view() {
    global $wpdb;

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to view examination schemes.', 'ifsedu-school-management' ) );
    }

    $table_exams    = $wpdb->prefix . 'sms_exams';
    $table_subjects = $wpdb->prefix . 'sms_subjects';

    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $exam_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    $list_url = add_query_arg(
        array(
            'page' => 'school_management_system',
            'tab'  => 'exams',
            'sub'  => 'list',
        ),
        admin_url( 'admin.php' )
    );

    $edit_url = add_query_arg(
        array(
            'page'   => 'school_management_system',
            'tab'    => 'exams',
            'sub'    => 'add',
            'action' => 'edit',
            'id'     => $exam_id,
        ),
        admin_url( 'admin.php' )
    );

    // Fetch Exam Record
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $exam = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table_exams}` WHERE id = %d LIMIT 1", $exam_id ) );
    // phpcs:enable

    if ( ! $exam ) {
        ?>
        <div class="ifs-educore-exams-root">
            <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:14px; padding:40px 20px; text-align:center; max-width:600px; margin:40px auto; box-shadow:0 4px 15px rgba(0,0,0,0.03);">
                <span class="dashicons dashicons-warning" style="font-size:42px; width:42px; height:42px; color:#dc2626;"></span>
                <h3 style="font-size:18px; font-weight:800; color:#0f172a; margin:14px 0 6px 0;"><?php esc_html_e( 'Examination Scheme Not Found', 'ifsedu-school-management' ); ?></h3>
                <p style="font-weight:600; color:#64748b; font-size:13.5px; margin-bottom:20px;"><?php esc_html_e( 'The requested exam record does not exist or has been removed from the database.', 'ifsedu-school-management' ); ?></p>
                <a href="<?php echo esc_url( $list_url ); ?>" style="display:inline-flex; align-items:center; gap:6px; background:#00523c; color:#fff; text-decoration:none; padding:9px 20px; border-radius:8px; font-weight:700; font-size:13px;">
                    <span class="dashicons dashicons-arrow-left-alt"></span> <?php esc_html_e( 'Back to Examination Directory', 'ifsedu-school-management' ); ?>
                </a>
            </div>
        </div>
        <?php
        return;
    }

    // Parse Classes & Subjects Map
    $classes_array = ! empty( $exam->class_name ) ? array_map( 'trim', explode( ',', $exam->class_name ) ) : array();
    $subject_map   = ! empty( $exam->subject_ids ) ? json_decode( $exam->subject_ids, true ) : array();

    // Fetch Subject Details Map
    $all_subs_raw = $wpdb->get_results( "SELECT id, subject_name, subject_code FROM `{$table_subjects}`" );
    $subject_dict = array();
    if ( ! empty( $all_subs_raw ) ) {
        foreach ( $all_subs_raw as $s_row ) {
            $subject_dict[ $s_row->id ] = array(
                'name' => $s_row->subject_name,
                'code' => $s_row->subject_code,
            );
        }
    }

    $start_ts     = ! empty( $exam->start_date ) ? strtotime( $exam->start_date ) : false;
    $end_ts       = ! empty( $exam->end_date ) ? strtotime( $exam->end_date ) : false;
    $att_start_ts = ! empty( $exam->att_start_date ) ? strtotime( $exam->att_start_date ) : $start_ts;
    $att_end_ts   = ! empty( $exam->att_end_date ) ? strtotime( $exam->att_end_date ) : $end_ts;

    // Calculate Working Days vs Off Days within Examination Window
    $total_working_days = 0;
    $total_off_days     = 0;
    if ( $start_ts && $end_ts && $end_ts >= $start_ts ) {
        $curr = $start_ts;
        while ( $curr <= $end_ts ) {
            $day_of_week = (int) gmdate( 'w', $curr ); // 0 = Sunday, 5 = Friday, 6 = Saturday
            // Assuming Friday (5) and Saturday (6) are weekly off days/weekends in Bangladesh standard
            if ( 5 === $day_of_week || 6 === $day_of_week ) {
                $total_off_days++;
            } else {
                $total_working_days++;
            }
            $curr = strtotime( '+1 day', $curr );
        }
    }

    // Total unique subjects across this exam scheme
    $total_distinct_subjects = 0;
    if ( is_array( $subject_map ) ) {
        $flat_sub_ids = array();
        foreach ( $subject_map as $c_subs ) {
            if ( is_array( $c_subs ) ) {
                $flat_sub_ids = array_merge( $flat_sub_ids, $c_subs );
            }
        }
        $total_distinct_subjects = count( array_unique( $flat_sub_ids ) );
    }

    // Dynamic School Settings Info
    $school_name    = get_option( 'educore_school_name', get_bloginfo( 'name' ) );
    $school_tagline = get_option( 'educore_school_tagline', get_bloginfo( 'description' ) );
    $school_logo    = get_option( 'educore_school_logo', '' );
    ?>

    <style>
        .ifs-exam-view-wrapper {
            max-width: 1040px;
            margin: 15px 0 40px 0;
            font-family: inherit;
        }

        /* Top Action Bar */
        .ifs-exam-view-top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            padding: 14px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.02);
        }
        .ifs-exam-btn-back {
            text-decoration: none;
            color: #475569;
            font-weight: 700;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: color 0.15s ease;
        }
        .ifs-exam-btn-back:hover {
            color: #00523c;
        }
        .ifs-exam-btn-edit {
            background: #00523c;
            color: #ffffff !important;
            text-decoration: none;
            padding: 8px 18px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 2px 8px rgba(0, 82, 60, 0.2);
            transition: all 0.2s ease;
        }
        .ifs-exam-btn-edit:hover {
            background: #047857;
            transform: translateY(-1px);
        }
        .ifs-exam-btn-print {
            background: #f8fafc;
            color: #334155 !important;
            border: 1px solid #cbd5e1;
            text-decoration: none;
            padding: 7px 16px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .ifs-exam-btn-print:hover {
            background: #e2e8f0;
            color: #0f172a !important;
        }

        /* Hero Scheme Banner */
        .ifs-exam-hero-card {
            background: linear-gradient(135deg, #00523c 0%, #064e3b 100%);
            color: #ffffff;
            border-radius: 14px;
            padding: 26px 30px;
            margin-bottom: 22px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 6px 20px rgba(0, 82, 60, 0.18);
        }
        .ifs-exam-hero-title {
            margin: 0;
            font-size: 24px;
            font-weight: 900;
            letter-spacing: -0.3px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .ifs-exam-hero-subtitle {
            margin: 6px 0 0 0;
            color: #a7f3d0;
            font-size: 13.5px;
            font-weight: 600;
        }

        /* Bento Stats Matrix */
        .ifs-exam-bento-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .ifs-exam-bento-stat-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 18px 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.02);
            transition: transform 0.15s ease, border-color 0.15s ease;
        }
        .ifs-exam-bento-stat-card:hover {
            border-color: #cbd5e1;
            transform: translateY(-2px);
        }
        .ifs-exam-stat-label {
            font-size: 11.5px;
            font-weight: 800;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 8px;
        }
        .ifs-exam-stat-value {
            font-size: 16px;
            font-weight: 800;
            color: #0f172a;
            display: block;
        }

        /* Status Badges */
        .ifs-exam-status-tag {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 12.5px;
            font-weight: 800;
            padding: 4px 12px;
            border-radius: 999px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .ifs-exam-status-tag.upcoming {
            background: #fef3c7;
            color: #b45309;
            border: 1px solid #fde68a;
        }
        .ifs-exam-status-tag.ongoing {
            background: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
        }
        .ifs-exam-status-tag.completed {
            background: #ecfdf5;
            color: #047857;
            border: 1px solid #a7f3d0;
        }

        /* Class & Subject Matrix Layout */
        .ifs-exam-matrix-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 24px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.02);
        }
        .ifs-exam-matrix-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1.5px solid #f1f5f9;
            padding-bottom: 14px;
            margin-bottom: 20px;
        }
        .ifs-exam-matrix-title {
            margin: 0;
            font-size: 16px;
            font-weight: 800;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .ifs-class-section-panel {
            background: #f8fafc;
            border: 1.5px solid #e2e8f0;
            border-radius: 12px;
            padding: 18px 20px;
            margin-bottom: 16px;
            transition: all 0.2s ease;
        }
        .ifs-class-section-panel:hover {
            border-color: #cbd5e1;
            background: #ffffff;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.03);
        }
        .ifs-class-panel-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
            border-bottom: 1px dashed #cbd5e1;
            padding-bottom: 10px;
            margin-bottom: 14px;
        }
        .ifs-class-title-badge {
            font-size: 14.5px;
            font-weight: 800;
            color: #00523c;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .ifs-class-subs-count {
            font-size: 12px;
            font-weight: 700;
            color: #475569;
            background: #e2e8f0;
            padding: 3px 10px;
            border-radius: 999px;
        }
        .ifs-subject-chips-list {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .ifs-subject-chip-node {
            background: #ffffff;
            border: 1.5px solid #cbd5e1;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12.5px;
            font-weight: 700;
            color: #1e293b;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
        }
        .ifs-subject-chip-node code {
            font-size: 11px;
            background: #f1f5f9;
            color: #00523c;
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: 800;
        }

        /* Printable Mode */
        @media print {
            #adminmenuwrap, #adminmenuback, #wpadminbar, #wpfooter, .no-print {
                display: none !important;
            }
            body, .ifs-exam-view-wrapper {
                background: transparent !important;
                padding: 0 !important;
                margin: 0 !important;
                width: 100% !important;
            }
            .ifs-exam-hero-card {
                background: #00523c !important;
                color: #ffffff !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                box-shadow: none !important;
            }
            .ifs-exam-bento-stat-card,
            .ifs-class-section-panel,
            .ifs-exam-matrix-card {
                box-shadow: none !important;
                border: 1px solid #000 !important;
            }
        }
    </style>

    <div class="ifs-exam-view-wrapper">

        <!-- Top Navigation Actions Bar -->
        <div class="ifs-exam-view-top-bar no-print">
            <a href="<?php echo esc_url( $list_url ); ?>" class="ifs-exam-btn-back">
                <span class="dashicons dashicons-arrow-left-alt"></span>
                <?php esc_html_e( 'Back to All Schemes', 'ifsedu-school-management' ); ?>
            </a>

            <div style="display: flex; align-items: center; gap: 10px;">
                <button type="button" onclick="window.print();" class="ifs-exam-btn-print">
                    <span class="dashicons dashicons-printer" style="font-size:15px; width:15px; height:15px;"></span>
                    <?php esc_html_e( 'Print Scheme Document', 'ifsedu-school-management' ); ?>
                </button>
                <a href="<?php echo esc_url( $edit_url ); ?>" class="ifs-exam-btn-edit">
                    <span class="dashicons dashicons-edit" style="font-size:15px; width:15px; height:15px;"></span>
                    <?php esc_html_e( 'Edit Scheme Configuration', 'ifsedu-school-management' ); ?>
                </a>
            </div>
        </div>

        <!-- Hero Header -->
        <div class="ifs-exam-hero-card">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:16px;">
                <div>
                    <h1 class="ifs-exam-hero-title">
                        <span class="dashicons dashicons-awards" style="font-size:28px; width:28px; height:28px;"></span>
                        <?php echo esc_html( $exam->exam_name ); ?>
                    </h1>
                    <p class="ifs-exam-hero-subtitle">
                        <?php echo esc_html( ! empty( $school_name ) ? $school_name : get_bloginfo( 'name' ) ); ?> &bull; <?php esc_html_e( 'Official Academic Session Scheme', 'ifsedu-school-management' ); ?>
                    </p>
                </div>
                <div>
                    <?php
                    $status_key = strtolower( (string) $exam->status );
                    ?>
                    <span class="ifs-exam-status-tag <?php echo esc_attr( $status_key ); ?>" style="background:#ffffff; color:#0f172a; border:none; padding:6px 16px; font-size:13px;">
                        <span class="dashicons dashicons-marker" style="font-size:14px; width:14px; height:14px; color:#00523c;"></span>
                        <?php echo esc_html( $exam->status ); ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Bento Grid Meta Summary -->
        <div class="ifs-exam-bento-stats">
            
            <!-- Schedule Window -->
            <div class="ifs-exam-bento-stat-card">
                <span class="ifs-exam-stat-label">
                    <span class="dashicons dashicons-calendar-alt" style="color:#00523c; font-size:16px; width:16px; height:16px;"></span>
                    <?php esc_html_e( 'Examination Window', 'ifsedu-school-management' ); ?>
                </span>
                <span class="ifs-exam-stat-value" style="font-size:14px;">
                    <?php echo esc_html( ( $start_ts ? date_i18n( 'd M, Y', $start_ts ) : '—' ) . ' to ' . ( $end_ts ? date_i18n( 'd M, Y', $end_ts ) : '—' ) ); ?>
                </span>
            </div>

            <!-- Total Working Days -->
            <div class="ifs-exam-bento-stat-card">
                <span class="ifs-exam-stat-label">
                    <span class="dashicons dashicons-hammer" style="color:#0284c7; font-size:16px; width:16px; height:16px;"></span>
                    <?php esc_html_e( 'Total Working Days', 'ifsedu-school-management' ); ?>
                </span>
                <span class="ifs-exam-stat-value" style="color:#0284c7;">
                    <?php echo intval( $total_working_days ); ?> <?php esc_html_e( 'Working Days', 'ifsedu-school-management' ); ?>
                </span>
            </div>

            <!-- Total Off Days -->
            <div class="ifs-exam-bento-stat-card">
                <span class="ifs-exam-stat-label">
                    <span class="dashicons dashicons-calendar" style="color:#d97706; font-size:16px; width:16px; height:16px;"></span>
                    <?php esc_html_e( 'Total Off Days (Weekends)', 'ifsedu-school-management' ); ?>
                </span>
                <span class="ifs-exam-stat-value" style="color:#d97706;">
                    <?php echo intval( $total_off_days ); ?> <?php esc_html_e( 'Off Days', 'ifsedu-school-management' ); ?>
                </span>
            </div>

            <!-- Term Attendance Calculation Window -->
            <div class="ifs-exam-bento-stat-card" style="border-left: 3.5px solid #00523c;">
                <span class="ifs-exam-stat-label">
                    <span class="dashicons dashicons-clock" style="color:#047857; font-size:16px; width:16px; height:16px;"></span>
                    <?php esc_html_e( 'Attendance Scope Range', 'ifsedu-school-management' ); ?>
                </span>
                <span class="ifs-exam-stat-value" style="font-size:14px; color:#047857;">
                    <?php echo esc_html( ( $att_start_ts ? date_i18n( 'd M, Y', $att_start_ts ) : '—' ) . ' to ' . ( $att_end_ts ? date_i18n( 'd M, Y', $att_end_ts ) : '—' ) ); ?>
                </span>
            </div>

            <!-- Classes Enrolled -->
            <div class="ifs-exam-bento-stat-card">
                <span class="ifs-exam-stat-label">
                    <span class="dashicons dashicons-category" style="color:#2563eb; font-size:16px; width:16px; height:16px;"></span>
                    <?php esc_html_e( 'Applicable Classes', 'ifsedu-school-management' ); ?>
                </span>
                <span class="ifs-exam-stat-value">
                    <?php echo count( $classes_array ); ?> <?php esc_html_e( 'Academic Classes', 'ifsedu-school-management' ); ?>
                </span>
            </div>

            <!-- Subject Evaluations -->
            <div class="ifs-exam-bento-stat-card">
                <span class="ifs-exam-stat-label">
                    <span class="dashicons dashicons-book" style="color:#7c3aed; font-size:16px; width:16px; height:16px;"></span>
                    <?php esc_html_e( 'Subject Coverage', 'ifsedu-school-management' ); ?>
                </span>
                <span class="ifs-exam-stat-value">
                    <?php echo intval( $total_distinct_subjects ); ?> <?php esc_html_e( 'Subjects Evaluated', 'ifsedu-school-management' ); ?>
                </span>
            </div>

        </div>

        <!-- Class & Subject Evaluation Breakdown Matrix -->
        <div class="ifs-exam-matrix-card">
            <div class="ifs-exam-matrix-header">
                <h3 class="ifs-exam-matrix-title">
                    <span class="dashicons dashicons-networking" style="color:#00523c;"></span>
                    <?php esc_html_e( 'Class-Wise Included Examination Subjects', 'ifsedu-school-management' ); ?>
                </h3>
                <span style="font-size:12px; font-weight:700; color:#64748b;">
                    <?php esc_html_e( 'Evaluation Matrix', 'ifsedu-school-management' ); ?>
                </span>
            </div>

            <?php if ( ! empty( $classes_array ) ) : foreach ( $classes_array as $cls ) : 
                $sub_ids = isset( $subject_map[ $cls ] ) && is_array( $subject_map[ $cls ] ) ? $subject_map[ $cls ] : array();
                $count_cls_subs = count( $sub_ids );
            ?>
                <div class="ifs-class-section-panel">
                    <div class="ifs-class-panel-top">
                        <div class="ifs-class-title-badge">
                            <span class="dashicons dashicons-welcome-learn-more" style="font-size:18px; width:18px; height:18px;"></span>
                            <?php printf( esc_html__( 'Class: %s', 'ifsedu-school-management' ), esc_html( $cls ) ); ?>
                        </div>
                        <span class="ifs-class-subs-count">
                            <?php echo $count_cls_subs > 0 ? sprintf( esc_html__( '%d Subjects Selected', 'ifsedu-school-management' ), $count_cls_subs ) : esc_html__( 'All Enrolled Subjects', 'ifsedu-school-management' ); ?>
                        </span>
                    </div>

                    <div class="ifs-subject-chips-list">
                        <?php if ( ! empty( $sub_ids ) ) : foreach ( $sub_ids as $sid ) : 
                            $s_info = isset( $subject_dict[ $sid ] ) ? $subject_dict[ $sid ] : array( 'name' => '#' . $sid, 'code' => '' );
                        ?>
                            <div class="ifs-subject-chip-node">
                                <span><?php echo esc_html( $s_info['name'] ); ?></span>
                                <?php if ( ! empty( $s_info['code'] ) ) : ?>
                                    <code><?php echo esc_html( $s_info['code'] ); ?></code>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; else : ?>
                            <span style="font-size:13px; color:#64748b; font-style:italic;">
                                <span class="dashicons dashicons-info" style="font-size:15px; width:15px; height:15px; vertical-align:middle;"></span>
                                <?php esc_html_e( 'All enrolled academic subjects for this class are included under this examination scheme.', 'ifsedu-school-management' ); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; else : ?>
                <div style="padding:30px; background:#f8fafc; border:1px dashed #cbd5e1; border-radius:10px; color:#94a3b8; text-align:center;">
                    <span class="dashicons dashicons-warning" style="font-size:24px; width:24px; height:24px; display:block; margin:0 auto 6px auto;"></span>
                    <?php esc_html_e( 'No academic classes have been associated with this examination scheme.', 'ifsedu-school-management' ); ?>
                </div>
            <?php endif; ?>

        </div>

    </div>
    <?php
}
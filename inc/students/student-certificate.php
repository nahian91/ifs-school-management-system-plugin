<?php
/**
 * Enterprise Academic Certificate, Testimonial & TC Compiler Engine
 * File: student-certificate-view.php
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Immediate access layer lockdown
}

function educore_student_certificate_view() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient administrative permissions to access this page.', 'ifsedu-school-management' ) );
    }

    global $wpdb;
    $table_students = $wpdb->prefix . 'sms_students';
    $table_units    = $wpdb->prefix . 'sms_academic_units';
    
    // Request routing
    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $action     = isset( $_GET['cert_action'] ) ? sanitize_key( wp_unslash( $_GET['cert_action'] ) ) : '';
    $student_id = isset( $_GET['student_id'] ) ? absint( $_GET['student_id'] ) : 0;
    $doc_type   = isset( $_GET['doc_type'] ) ? sanitize_key( wp_unslash( $_GET['doc_type'] ) ) : 'certificate';
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    // Pull Dynamic Institutional Settings
    $school_name    = get_option( 'educore_school_name', get_bloginfo( 'name' ) );
    $school_tagline = get_option( 'educore_school_tagline', '' );
    $school_logo    = get_option( 'educore_school_logo', '' );
    $principal_sig  = get_option( 'educore_principal_sig', '' );

    if ( empty( $school_name ) || 'WordPress' === $school_name ) {
        $school_name = get_bloginfo( 'name' );
    }

    // =========================================================================
    // 1. PRINT & PREVIEW VIEW
    // =========================================================================
    if ( 'print' === $action && $student_id > 0 ) {
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $student = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, student_id, full_name, class_name, section_name, roll_no, father_name, mother_name, guardian_name 
                 FROM `{$table_students}` 
                 WHERE id = %d LIMIT 1",
                $student_id
            )
        );
        // phpcs:enable
        
        if ( ! $student ) {
            echo '<div class="notice notice-error" style="padding:15px; margin:20px 20px 20px 0; font-weight:700;">' . esc_html__( 'Student record not found in database.', 'ifsedu-school-management' ) . '</div>';
            return;
        }

        $back_url = add_query_arg(
            array(
                'page' => 'school_management_system',
                'tab'  => 'students',
                'sub'  => 'certificate',
            ),
            admin_url( 'admin.php' )
        );
        
        // Define Document Titles
        $doc_title = __( 'CERTIFICATE OF ACHIEVEMENT', 'ifsedu-school-management' );
        if ( 'testimonial' === $doc_type ) {
            $doc_title = __( 'ACADEMIC TESTIMONIAL', 'ifsedu-school-management' );
        } elseif ( 'transfer_certificate' === $doc_type ) {
            $doc_title = __( 'TRANSFER CERTIFICATE', 'ifsedu-school-management' );
        }

        $guardian_display = ! empty( $student->father_name ) ? $student->father_name : ( ! empty( $student->guardian_name ) ? $student->guardian_name : '—' );
        $mother_display   = ! empty( $student->mother_name ) ? $student->mother_name : '—';
        $student_uid      = strtoupper( (string) $student->student_id );
        ?>

        <style>
        .cert-print-container {
            padding: 20px 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            font-family: 'Cinzel', 'Georgia', serif;
        }

        .cert-action-bar {
            width: 100%;
            max-width: 280mm;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-bottom: 20px;
        }

        .cert-btn {
            border: none;
            border-radius: 6px;
            padding: 8px 18px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
            transition: background 0.2s ease;
        }

        .cert-btn-primary {
            background: #00523c;
            color: #ffffff;
        }

        .cert-btn-primary:hover {
            background: #065f46;
        }

        .cert-btn-secondary {
            background: #f1f5f9;
            color: #334155;
            border: 1px solid #cbd5e1;
        }

        .cert-btn-secondary:hover {
            background: #e2e8f0;
        }

        /* Certificate A4 Landscape Document Box */
        .cert-print-wrapper {
            width: 280mm;
            min-height: 195mm;
            background: #ffffff;
            border: 12px double #00523c;
            padding: 30px 45px;
            box-sizing: border-box;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .cert-header {
            text-align: center;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 14px;
        }

        .cert-school-brand-row {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }

        .cert-logo-img {
            width: 50px;
            height: 50px;
            object-fit: contain;
        }

        .cert-school-name {
            margin: 0;
            font-size: 28px;
            font-weight: 800;
            color: #00523c;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .cert-school-sub {
            margin: 4px 0 0 0;
            font-size: 13px;
            color: #64748b;
            font-family: 'Inter', sans-serif;
        }

        .cert-title-badge {
            display: inline-block;
            background: #00523c;
            color: #ffffff;
            font-size: 14px;
            font-weight: 800;
            padding: 6px 24px;
            border-radius: 24px;
            margin-top: 14px;
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        .cert-body {
            padding: 30px 20px;
            font-size: 16px;
            line-height: 2.2;
            color: #1e293b;
            text-align: justify;
            font-family: 'Georgia', serif;
        }

        .cert-body .highlight {
            font-weight: bold;
            color: #00523c;
            border-bottom: 1px dashed #00523c;
            padding: 0 4px;
        }

        .cert-seal-box {
            position: absolute;
            bottom: 40mm;
            left: 50%;
            transform: translateX(-50%);
            width: 75px;
            height: 75px;
            border: 2px dashed #cbd5e1;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            font-size: 10px;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 1px;
            line-height: 1.2;
        }

        .cert-footer {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            padding-top: 20px;
            font-family: 'Inter', sans-serif;
        }

        .cert-sign-col {
            text-align: center;
            width: 220px;
        }

        .cert-sign-line {
            border-top: 1px solid #0f172a;
            padding-top: 6px;
            font-size: 12px;
            font-weight: 700;
            color: #334155;
        }

        .cert-sig-img {
            max-height: 35px;
            object-fit: contain;
            display: block;
            margin: 0 auto 6px auto;
        }

        .cert-date {
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
        }

        @media print {
            @page {
                size: A4 landscape;
                margin: 0;
            }

            #adminmenuwrap, #adminmenuback, #wpadminbar, #wpfooter, .no-print, .cert-action-bar, .educore-sidebar-container, .educore-dashboard-footer {
                display: none !important;
            }

            html, body, #wpcontent, #wpbody, #wpbody-content, #educore-wrapper, .educore-right-box, .cert-print-container {
                background: #ffffff !important;
                padding: 0 !important;
                margin: 0 !important;
                width: 100% !important;
            }

            .cert-print-wrapper {
                box-shadow: none !important;
                border: 10px double #00523c !important;
                page-break-inside: avoid !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                margin: 8mm auto !important;
            }
        }
        </style>

        <div class="cert-print-container">
            <div class="cert-action-bar no-print">
                <button type="button" onclick="window.print();" class="cert-btn cert-btn-primary">
                    <span class="dashicons dashicons-printer"></span> <?php esc_html_e( 'Print Document', 'ifsedu-school-management' ); ?>
                </button>
                <a href="<?php echo esc_url( $back_url ); ?>" class="cert-btn cert-btn-secondary">
                    <span class="dashicons dashicons-arrow-left-alt"></span> <?php esc_html_e( 'Back to Generator', 'ifsedu-school-management' ); ?>
                </a>
            </div>

            <div class="cert-print-wrapper">
                <div class="cert-header">
                    <div class="cert-school-brand-row">
                        <?php if ( ! empty( $school_logo ) ) : ?>
                            <img src="<?php echo esc_url( $school_logo ); ?>" alt="<?php esc_attr_e( 'Logo', 'ifsedu-school-management' ); ?>" class="cert-logo-img">
                        <?php endif; ?>
                        <h1 class="cert-school-name"><?php echo esc_html( $school_name ); ?></h1>
                    </div>
                    <?php if ( ! empty( $school_tagline ) ) : ?>
                        <p class="cert-school-sub"><?php echo esc_html( $school_tagline ); ?></p>
                    <?php endif; ?>
                    <div class="cert-title-badge"><?php echo esc_html( $doc_title ); ?></div>
                </div>

                <div class="cert-body">
                    <div>
                        <?php if ( 'testimonial' === $doc_type ) : ?>
                            <?php
                            printf(
                                /* translators: 1: Student Name, 2: Father/Guardian Name, 3: Mother Name, 4: Student ID, 5: Class Name, 6: Section Name, 7: Roll Number */
                                esc_html__( 'This is to certify that %1$s, son/daughter of %2$s and %3$s, bearing Student ID %4$s, is/was a bonafide student of this institution in Class %5$s (Section: %6$s, Roll No: %7$s). To the best of my knowledge, he/she bears a good moral character and took an active interest in co-curricular activities. I wish him/her every success and a bright future in all academic and personal pursuits.', 'ifsedu-school-management' ),
                                '<span class="highlight">' . esc_html( $student->full_name ) . '</span>',
                                '<span class="highlight">' . esc_html( $guardian_display ) . '</span>',
                                '<span class="highlight">' . esc_html( $mother_display ) . '</span>',
                                '<span class="highlight">' . esc_html( $student_uid ) . '</span>',
                                '<span class="highlight">' . esc_html( $student->class_name ) . '</span>',
                                '<span class="highlight">' . esc_html( ! empty( $student->section_name ) ? $student->section_name : __( 'N/A', 'ifsedu-school-management' ) ) . '</span>',
                                '<span class="highlight">#' . esc_html( $student->roll_no ) . '</span>'
                            );
                            ?>
                        <?php elseif ( 'transfer_certificate' === $doc_type ) : ?>
                            <?php
                            printf(
                                /* translators: 1: Student Name, 2: Father/Guardian Name, 3: Class Name, 4: Student ID, 5: Month and Year */
                                esc_html__( 'This is to certify that %1$s, son/daughter of %2$s, was a registered regular student of this institution in Class %3$s under Student ID %4$s. He/She has paid all institutional dues up to the month of %5$s. He/She is granted this Transfer Certificate on personal grounds/guardian\'s formal request. His/Her conduct and character during the academic stay were satisfactory.', 'ifsedu-school-management' ),
                                '<span class="highlight">' . esc_html( $student->full_name ) . '</span>',
                                '<span class="highlight">' . esc_html( $guardian_display ) . '</span>',
                                '<span class="highlight">' . esc_html( $student->class_name ) . '</span>',
                                '<span class="highlight">' . esc_html( $student_uid ) . '</span>',
                                '<span class="highlight">' . esc_html( date_i18n( 'F Y' ) ) . '</span>'
                            );
                            ?>
                        <?php else : ?>
                            <?php
                            printf(
                                /* translators: 1: Student Name, 2: Student ID, 3: Roll Number, 4: Class Name */
                                esc_html__( 'This Certificate of Academic Excellence is proudly presented to %1$s (Student ID: %2$s, Roll No: %3$s) in recognition of exemplary discipline, dedication, and commendable performance in Class %4$s during the academic session. We appreciate the outstanding efforts and wish for continued academic distinction.', 'ifsedu-school-management' ),
                                '<span class="highlight">' . esc_html( $student->full_name ) . '</span>',
                                '<span class="highlight">' . esc_html( $student_uid ) . '</span>',
                                '<span class="highlight">#' . esc_html( $student->roll_no ) . '</span>',
                                '<span class="highlight">' . esc_html( $student->class_name ) . '</span>'
                            );
                            ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="cert-seal-box"><?php echo esc_html( "Official\nSeal" ); ?></div>

                <div class="cert-footer">
                    <div class="cert-sign-col">
                        <div class="cert-sign-line"><?php esc_html_e( 'Class Teacher', 'ifsedu-school-management' ); ?></div>
                    </div>
                    <?php /* translators: %s: Formatted date of issue */ ?>
                    <div class="cert-date"><?php echo esc_html( sprintf( __( 'Date of Issue: %s', 'ifsedu-school-management' ), date_i18n( 'd F, Y' ) ) ); ?></div>
                    <div class="cert-sign-col">
                        <?php if ( ! empty( $principal_sig ) ) : ?>
                            <img src="<?php echo esc_url( $principal_sig ); ?>" alt="<?php esc_attr_e( 'Signature', 'ifsedu-school-management' ); ?>" class="cert-sig-img">
                        <?php endif; ?>
                        <div class="cert-sign-line"><?php esc_html_e( 'Principal / Headmaster', 'ifsedu-school-management' ); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <?php
        return; // End print view
    }

    // =========================================================================
    // 2. FORM FILTER VIEW (DEFAULT)
    // =========================================================================
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $raw_units = $wpdb->get_results( 
        "SELECT class_name, section_name, dept_name, sort_order 
         FROM `{$table_units}` 
         WHERE class_name != '' 
         ORDER BY sort_order ASC, CAST(class_name AS UNSIGNED) ASC, class_name ASC, section_name ASC" 
    );
    // phpcs:enable
    
    $unique_classes    = array();
    $class_order_map   = array();
    $class_section_map = array();
    
    if ( ! empty( $raw_units ) ) {
        foreach ( $raw_units as $unit ) {
            $c     = trim( (string) $unit->class_name );
            $s_ord = isset( $unit->sort_order ) ? (int) $unit->sort_order : 0;

            if ( ! isset( $class_order_map[ $c ] ) || $s_ord < $class_order_map[ $c ] ) {
                $class_order_map[ $c ] = $s_ord;
            }

            if ( ! isset( $class_section_map[ $c ] ) ) {
                $class_section_map[ $c ] = array();
                $unique_classes[] = $c;
            }
            if ( ! empty( $unit->section_name ) ) {
                $class_section_map[ $c ][] = trim( (string) $unit->section_name );
            }
            if ( ! empty( $unit->dept_name ) ) {
                $class_section_map[ $c ][] = trim( (string) $unit->dept_name );
            }
        }

        foreach ( $class_section_map as $c => $secs ) {
            $class_section_map[ $c ] = array_values( array_unique( array_filter( $secs ) ) );
            usort( $class_section_map[ $c ], 'strnatcasecmp' );
        }

        $unique_classes = array_values( array_unique( $unique_classes ) );
        usort( $unique_classes, function( $a, $b ) use ( $class_order_map ) {
            $order_a = isset( $class_order_map[ $a ] ) ? $class_order_map[ $a ] : 0;
            $order_b = isset( $class_order_map[ $b ] ) ? $class_order_map[ $b ] : 0;
            if ( $order_a !== $order_b ) {
                return $order_a - $order_b;
            }
            return strnatcasecmp( $a, $b );
        } );
    }

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $students = $wpdb->get_results( "SELECT id, full_name, student_id, class_name, section_name, roll_no FROM `{$table_students}` WHERE status='Active' ORDER BY CAST(roll_no AS UNSIGNED) ASC, roll_no ASC" );
    // phpcs:enable
    ?>

    <div class="cert-form-box">
        <div class="cert-form-header">
            <span class="dashicons dashicons-awards" style="font-size: 44px; width: 44px; height: 44px; color: #00523c;"></span>
            <h2><?php esc_html_e( 'Academic Document & Certificate Compiler', 'ifsedu-school-management' ); ?></h2>
            <p><?php esc_html_e( 'Select the document type and student credentials to compile an official certificate.', 'ifsedu-school-management' ); ?></p>
        </div>
        
        <form method="GET" action="">
            <input type="hidden" name="page" value="school_management_system">
            <input type="hidden" name="tab" value="students">
            <input type="hidden" name="sub" value="certificate">
            <input type="hidden" name="cert_action" value="print">
            <input type="hidden" name="action" value="print">
            
            <div class="cert-grid-2">
                <!-- 1. Document Type -->
                <div class="cert-field-group">
                    <label class="cert-field-label"><?php esc_html_e( '1. Select Document Type', 'ifsedu-school-management' ); ?> <span style="color:#ef4444;">*</span></label>
                    <select name="doc_type" class="cert-select-input" required>
                        <option value="certificate">🎓 <?php esc_html_e( 'Certificate of Achievement', 'ifsedu-school-management' ); ?></option>
                        <option value="testimonial">📜 <?php esc_html_e( 'Academic Testimonial / Character Certificate', 'ifsedu-school-management' ); ?></option>
                        <option value="transfer_certificate">📄 <?php esc_html_e( 'Transfer Certificate (TC)', 'ifsedu-school-management' ); ?></option>
                    </select>
                </div>

                <!-- 2. Class -->
                <div class="cert-field-group">
                    <label class="cert-field-label"><?php esc_html_e( '2. Filter By Class', 'ifsedu-school-management' ); ?></label>
                    <select id="cert_class" class="cert-select-input">
                        <option value=""><?php esc_html_e( '-- All Classes --', 'ifsedu-school-management' ); ?></option>
                        <?php foreach ( $unique_classes as $cls ) : ?>
                            <option value="<?php echo esc_attr( $cls ); ?>"><?php echo esc_html( $cls ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- 3. Section -->
                <div class="cert-field-group">
                    <label class="cert-field-label"><?php esc_html_e( '3. Filter By Section', 'ifsedu-school-management' ); ?></label>
                    <select id="cert_section" class="cert-select-input" disabled>
                        <option value=""><?php esc_html_e( 'Select Class First', 'ifsedu-school-management' ); ?></option>
                    </select>
                </div>

                <!-- 4. Target Student Selector -->
                <div class="cert-field-group">
                    <label class="cert-field-label"><?php esc_html_e( '4. Select Target Student', 'ifsedu-school-management' ); ?> <span style="color:#ef4444;">*</span></label>
                    <select id="cert_student" name="student_id" class="cert-select-input" required>
                        <option value=""><?php esc_html_e( '-- Choose Student --', 'ifsedu-school-management' ); ?></option>
                        <?php foreach ( $students as $s ) : ?>
                            <option value="<?php echo esc_attr( $s->id ); ?>" 
                                    data-class="<?php echo esc_attr( $s->class_name ); ?>" 
                                    data-section="<?php echo esc_attr( $s->section_name ); ?>">
                                <?php echo esc_html( sprintf( '[Roll: %1$s] %2$s (%3$s - %4$s)', $s->roll_no, $s->full_name, strtoupper( (string) $s->student_id ), $s->class_name ) ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <button type="submit" class="cert-submit-btn">
                <span class="dashicons dashicons-visibility"></span> <?php esc_html_e( 'Compile & Preview Document', 'ifsedu-school-management' ); ?>
            </button>
        </form>
    </div>

    <!-- Client-Side Filter Chaining Engine -->
    <script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function() {
        var classSelect   = document.getElementById('cert_class');
        var sectionSelect = document.getElementById('cert_section');
        var studentSelect = document.getElementById('cert_student');
        
        if (!classSelect || !sectionSelect || !studentSelect) {
            return;
        }

        var allStudents     = Array.from(studentSelect.options).slice(1);
        var classSectionMap = <?php echo wp_json_encode( $class_section_map ); ?>;

        function updateSections() {
            var selectedClass = classSelect.value;
            sectionSelect.innerHTML = '';
            
            if (selectedClass && classSectionMap[selectedClass] && classSectionMap[selectedClass].length > 0) {
                sectionSelect.innerHTML = '<option value=""><?php echo esc_js( __( '-- All Sections --', 'ifsedu-school-management' ) ); ?></option>';
                classSectionMap[selectedClass].forEach(function(sec) {
                    var opt = document.createElement('option');
                    opt.value = sec;
                    opt.textContent = sec;
                    sectionSelect.appendChild(opt);
                });
                sectionSelect.disabled = false;
            } else if (selectedClass) {
                sectionSelect.innerHTML = '<option value=""><?php echo esc_js( __( 'No Sections Available', 'ifsedu-school-management' ) ); ?></option>';
                sectionSelect.disabled = true;
            } else {
                sectionSelect.innerHTML = '<option value=""><?php echo esc_js( __( 'Select Class First', 'ifsedu-school-management' ) ); ?></option>';
                sectionSelect.disabled = true;
            }
            
            filterStudents();
        }

        function filterStudents() {
            var selectedClass   = classSelect.value;
            var selectedSection = sectionSelect.value;

            studentSelect.innerHTML = '<option value=""><?php echo esc_js( __( '-- Choose Student --', 'ifsedu-school-management' ) ); ?></option>';

            allStudents.forEach(function(option) {
                var sClass   = option.getAttribute('data-class');
                var sSection = option.getAttribute('data-section');

                var matchClass   = (selectedClass === "" || sClass === selectedClass);
                var matchSection = (selectedSection === "" || sSection === selectedSection);

                if (matchClass && matchSection) {
                    studentSelect.appendChild(option.cloneNode(true));
                }
            });
        }

        classSelect.addEventListener('change', updateSections);
        sectionSelect.addEventListener('change', filterStudents);
    });
    </script>
    <?php
}
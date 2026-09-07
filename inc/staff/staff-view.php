<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Direct access lockdown
}

/**
 * High-End Staff Profile Bento Visualizer
 * Shows 100% full staff record details
 * File: inc/staff/staff-profile-view.php
 */
function educore_staff_profile_view() {
    global $wpdb;
    $table_staff = $wpdb->prefix . 'sms_staff';

    // 1. Security & Permission Check
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have permission to view staff profiles.', 'ifsedu-school-management' ) );
    }

    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $staff_id = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0;
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $staff = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table_staff}` WHERE id = %d LIMIT 1", $staff_id ) );
    // phpcs:enable

    if ( ! $staff ) {
        echo '<div class="alert alert-danger my-4 border-0 shadow-sm">' . esc_html__( 'Staff record not found.', 'ifsedu-school-management' ) . '</div>';
        return;
    }

    // Processing variables
    $back_url = admin_url( 'admin.php?page=school_management_system&tab=staff&sub=list' );
    $edit_url = admin_url( 'admin.php?page=school_management_system&tab=staff&sub=edit&id=' . absint( $staff->id ) );
    
    $is_active = strtolower( trim( (string) ( $staff->status ?? '' ) ) ) === 'active';

    // Date Format Handling
    $dob_ts       = ( ! empty( $staff->dob ) && '1970-01-01' !== $staff->dob && '0000-00-00' !== $staff->dob ) ? strtotime( $staff->dob ) : false;
    $dob          = $dob_ts ? date_i18n( 'd F, Y', $dob_ts ) : '—';

    $joining_ts   = ( ! empty( $staff->joining_date ) && '1970-01-01' !== $staff->joining_date && '0000-00-00' !== $staff->joining_date ) ? strtotime( $staff->joining_date ) : false;
    $joining_date = $joining_ts ? date_i18n( 'd F, Y', $joining_ts ) : '—';

    $salary       = number_format( (float) ( $staff->salary ?? 0 ), 2 );
    $staff_id_num = ! empty( $staff->staff_id ) ? strtoupper( (string) $staff->staff_id ) : '—';
    ?>

    <div class="ifs-educore-profile-container my-3">
        
        <!-- Navigation Controls Bar -->
        <div class="d-flex justify-content-between align-items: center mb-4 no-print">
            <a href="<?php echo esc_url( $back_url ); ?>" class="btn btn-outline-secondary btn-sm fw-bold px-3 py-2" style="border-radius: 8px;">
                &larr; <?php esc_html_e( 'Back to Directory', 'ifsedu-school-management' ); ?>
            </a>
            <div class="d-flex gap-2">
                <button onclick="window.print();" class="btn btn-light btn-sm border fw-bold px-3 py-2" style="border-radius: 8px;">
                    <span class="dashicons dashicons-printer me-1" style="vertical-align:middle;"></span> <?php esc_html_e( 'Print Profile', 'ifsedu-school-management' ); ?>
                </button>
                <a href="<?php echo esc_url( $edit_url ); ?>" class="btn btn-primary btn-sm fw-bold px-4 py-2" style="background-color: #00523c; border: none; border-radius: 8px; box-shadow: 0 4px 12px rgba(0, 106, 78, 0.25);">
                    <span class="dashicons dashicons-edit me-1" style="vertical-align:middle;"></span> <?php esc_html_e( 'Edit Profile', 'ifsedu-school-management' ); ?>
                </a>
            </div>
        </div>

        <!-- Hero Header Card -->
        <div class="ifs-educore-bento-header-card mb-4">
            <svg class="ifs-educore-bento-header-bg-pattern" width="200" height="200" viewBox="0 0 24 24"><path fill="#ffffff" d="M12 2l-7 7v11c0 1.1.9 2 2 2h10c1.1 0 2-.9 2-2V9l-7-7zm0 2.84L17.16 9H6.84L12 4.84zM7 19v-8h10v8H7z"/></svg>

            <div class="row align-items-center">
                <div class="col-md-auto text-center text-md-start mb-3 mb-md-0">
                    <div class="ifs-educore-profile-avatar-wrapper">
                        <?php if ( ! empty( $staff->profile_image ) ) : ?>
                            <img src="<?php echo esc_url( $staff->profile_image ); ?>" alt="<?php echo esc_attr( $staff->full_name ); ?>" class="ifs-educore-profile-avatar-img">
                        <?php else : 
                            $first_letter = mb_substr( (string) ( $staff->full_name ?? 'S' ), 0, 1, 'UTF-8' );
                        ?>
                            <div class="ifs-educore-profile-avatar-placeholder mx-auto">
                                <?php echo esc_html( mb_strtoupper( $first_letter, 'UTF-8' ) ); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-md text-center text-md-start">
                    <div class="d-flex flex-wrap align-items: center justify-content: center justify-content-md-start gap-2 mb-2">
                        <h2 class="m-0 fw-bold text-white" style="letter-spacing: -0.5px;"><?php echo esc_html( $staff->full_name ); ?></h2>
                        <span class="badge rounded-pill bg-white text-dark px-3 py-1 fs-6 shadow-sm">
                            <span class="ifs-educore-status-indicator-dot <?php echo $is_active ? 'ifs-educore-status-dot-active' : 'ifs-educore-status-dot-inactive'; ?>"></span>
                            <?php echo esc_html( ucfirst( (string) $staff->status ) ); ?>
                        </span>
                    </div>

                    <div class="d-flex flex-wrap align-items: center justify-content: center justify-content-md-start gap-2">
                        <div class="ifs-educore-glass-id-badge">
                            <span class="dashicons dashicons-id text-white"></span>
                            <span>ID: <strong><?php echo esc_html( $staff_id_num ); ?></strong></span>
                        </div>
                        <div class="ifs-educore-glass-id-badge">
                            <span class="dashicons dashicons-businessperson text-white"></span>
                            <span class="fw-bold text-white"><?php echo esc_html( $staff->designation ); ?></span>
                        </div>
                        <?php if ( ! empty( $staff->staff_type ) ) : ?>
                            <div class="ifs-educore-glass-id-badge">
                                <span class="dashicons dashicons-category text-white"></span>
                                <span><?php echo esc_html( $staff->staff_type ); ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if ( ! empty( $staff->index_no ) ) : ?>
                            <div class="ifs-educore-glass-id-badge">
                                <span class="dashicons dashicons-awards text-white"></span>
                                <span>Index: <strong><?php echo esc_html( $staff->index_no ); ?></strong></span>
                            </div>
                        <?php endif; ?>
                        <?php if ( isset( $staff->order_number ) ) : ?>
                            <div class="ifs-educore-glass-id-badge">
                                <span class="dashicons dashicons-sort text-white"></span>
                                <span>Serial: #<strong><?php echo absint( $staff->order_number ); ?></strong></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bento Grid Main Content Area -->
        <div class="row g-4">
            
            <!-- Left Column: Primary Details -->
            <div class="col-lg-8">
                <div class="row g-4">
                    
                    <!-- General Personal Information -->
                    <div class="col-12">
                        <div class="ifs-educore-bento-card">
                            <div class="ifs-educore-bento-section-title">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                                <?php esc_html_e( 'Personal Details & Identity', 'ifsedu-school-management' ); ?>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="ifs-educore-info-label"><?php esc_html_e( 'National ID (NID)', 'ifsedu-school-management' ); ?></div>
                                    <div class="ifs-educore-info-value font-monospace"><?php echo esc_html( $staff->nid_no ?: '—' ); ?></div>
                                </div>
                                <div class="col-md-3">
                                    <div class="ifs-educore-info-label"><?php esc_html_e( 'Date of Birth', 'ifsedu-school-management' ); ?></div>
                                    <div class="ifs-educore-info-value"><?php echo esc_html( $dob ); ?></div>
                                </div>
                                <div class="col-md-3">
                                    <div class="ifs-educore-info-label"><?php esc_html_e( 'Gender', 'ifsedu-school-management' ); ?></div>
                                    <div class="ifs-educore-info-value"><?php echo esc_html( $staff->gender ?: 'Male' ); ?></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="ifs-educore-info-label"><?php esc_html_e( "Father's Name", 'ifsedu-school-management' ); ?></div>
                                    <div class="ifs-educore-info-value"><?php echo esc_html( $staff->father_name ?: '—' ); ?></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="ifs-educore-info-label"><?php esc_html_e( "Mother's Name", 'ifsedu-school-management' ); ?></div>
                                    <div class="ifs-educore-info-value"><?php echo esc_html( $staff->mother_name ?: '—' ); ?></div>
                                </div>
                                <div class="col-md-4">
                                    <div class="ifs-educore-info-label"><?php esc_html_e( 'Blood Group', 'ifsedu-school-management' ); ?></div>
                                    <div class="ifs-educore-info-value text-danger fw-bold">
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-3">
                                            <?php echo esc_html( $staff->blood_group ?: 'N/A' ); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="ifs-educore-info-label"><?php esc_html_e( 'Quota Category', 'ifsedu-school-management' ); ?></div>
                                    <div class="ifs-educore-info-value"><?php echo esc_html( $staff->quota_type ?: 'General' ); ?></div>
                                </div>
                                <div class="col-md-4">
                                    <div class="ifs-educore-info-label"><?php esc_html_e( 'Linked WP User', 'ifsedu-school-management' ); ?></div>
                                    <div class="ifs-educore-info-value"><?php echo ( isset( $staff->wp_user_id ) && $staff->wp_user_id ) ? '<span class="badge bg-light text-dark border">User #' . absint( $staff->wp_user_id ) . '</span>' : 'Unlinked'; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Employment & Academic Qualifications -->
                    <div class="col-12">
                        <div class="ifs-educore-bento-card">
                            <div class="ifs-educore-bento-section-title">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 10v6M2 10l10-5 10 5-10 5z"></path><path d="M6 12v5c3 3 9 3 12 0v-5"></path></svg>
                                <?php esc_html_e( 'Academic & Service Portfolio', 'ifsedu-school-management' ); ?>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="ifs-educore-info-label"><?php esc_html_e( 'Subject Expertise', 'ifsedu-school-management' ); ?></div>
                                    <div class="ifs-educore-info-value text-success fw-bold"><?php echo esc_html( $staff->subject_expert ?: '—' ); ?></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="ifs-educore-info-label"><?php esc_html_e( 'Highest Qualification', 'ifsedu-school-management' ); ?></div>
                                    <div class="ifs-educore-info-value"><?php echo esc_html( $staff->highest_degree ?: '—' ); ?></div>
                                </div>
                                <div class="col-md-4">
                                    <div class="ifs-educore-info-label"><?php esc_html_e( 'Joining Date', 'ifsedu-school-management' ); ?></div>
                                    <div class="ifs-educore-info-value"><?php echo esc_html( $joining_date ); ?></div>
                                </div>
                                <div class="col-md-4">
                                    <div class="ifs-educore-info-label"><?php esc_html_e( 'Pay Scale Grade', 'ifsedu-school-management' ); ?></div>
                                    <div class="ifs-educore-info-value"><?php echo esc_html( $staff->pay_grade ?: '—' ); ?></div>
                                </div>
                                <div class="col-md-4">
                                    <div class="ifs-educore-info-label"><?php esc_html_e( 'Gross Monthly Salary', 'ifsedu-school-management' ); ?></div>
                                    <div class="ifs-educore-info-value text-success fw-bold fs-5">৳<?php echo esc_html( $salary ); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Addresses -->
                    <div class="col-12">
                        <div class="ifs-educore-bento-card">
                            <div class="ifs-educore-bento-section-title">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                                <?php esc_html_e( 'Residential Address Records', 'ifsedu-school-management' ); ?>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="ifs-educore-info-label"><?php esc_html_e( 'Present Address', 'ifsedu-school-management' ); ?></div>
                                    <div class="ifs-educore-info-value text-secondary bg-light p-3 rounded-3 border" style="min-height: 80px;"><?php echo nl2br( esc_html( $staff->address ?: '—' ) ); ?></div>
                                </div>
                                <div class="col-md-6">
                                    <div class="ifs-educore-info-label"><?php esc_html_e( 'Permanent Address', 'ifsedu-school-management' ); ?></div>
                                    <div class="ifs-educore-info-value text-secondary bg-light p-3 rounded-3 border" style="min-height: 80px;"><?php echo nl2br( esc_html( $staff->permanent_address ?: '—' ) ); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Right Column: Contact, Banking & Emergency -->
            <div class="col-lg-4">
                <div class="row g-4">
                    
                    <!-- Direct Contacts -->
                    <div class="col-12">
                        <div class="ifs-educore-bento-card">
                            <div class="ifs-educore-bento-section-title">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.7 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                                <?php esc_html_e( 'Direct Contact Channels', 'ifsedu-school-management' ); ?>
                            </div>
                            <div class="mb-3">
                                <div class="ifs-educore-info-label"><?php esc_html_e( 'Mobile Phone', 'ifsedu-school-management' ); ?></div>
                                <div class="ifs-educore-info-value">
                                    <a href="tel:<?php echo esc_attr( $staff->phone ); ?>" class="text-decoration-none text-dark fw-bold">
                                        <?php echo esc_html( $staff->phone ); ?>
                                    </a>
                                </div>
                            </div>
                            <?php if ( ! empty( $staff->whatsapp_no ) ) : ?>
                                <div class="mb-3">
                                    <div class="ifs-educore-info-label"><?php esc_html_e( 'WhatsApp Number', 'ifsedu-school-management' ); ?></div>
                                    <div class="ifs-educore-info-value">
                                        <a href="https://wa.me/<?php echo esc_attr( preg_replace( '/[^0-9]/', '', (string) $staff->whatsapp_no ) ); ?>" target="_blank" class="text-decoration-none text-success fw-bold d-inline-flex align-items-center gap-1">
                                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981z"/></svg>
                                            <?php echo esc_html( $staff->whatsapp_no ); ?>
                                        </a>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <div class="mb-2">
                                <div class="ifs-educore-info-label"><?php esc_html_e( 'Email Address', 'ifsedu-school-management' ); ?></div>
                                <div class="ifs-educore-info-value">
                                    <?php if ( ! empty( $staff->email ) ) : ?>
                                        <a href="mailto:<?php echo esc_attr( $staff->email ); ?>" class="text-decoration-none text-primary">
                                            <?php echo esc_html( $staff->email ); ?>
                                        </a>
                                    <?php else : ?>
                                        —
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Banking Details -->
                    <div class="col-12">
                        <div class="ifs-educore-bento-card">
                            <div class="ifs-educore-bento-section-title">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="2" y="5" width="20" height="14" rx="2"></rect><line x1="2" y1="10" x2="22" y2="10"></line></svg>
                                <?php esc_html_e( 'EFT / Payroll Banking', 'ifsedu-school-management' ); ?>
                            </div>
                            <div class="mb-3">
                                <div class="ifs-educore-info-label"><?php esc_html_e( 'Bank Name', 'ifsedu-school-management' ); ?></div>
                                <div class="ifs-educore-info-value"><?php echo esc_html( $staff->bank_name ?: '—' ); ?></div>
                            </div>
                            <div class="mb-3">
                                <div class="ifs-educore-info-label"><?php esc_html_e( 'Account Number', 'ifsedu-school-management' ); ?></div>
                                <div class="ifs-educore-info-value font-monospace bg-light p-2 rounded text-dark border"><?php echo esc_html( $staff->bank_acc_no ?: '—' ); ?></div>
                            </div>
                            <div>
                                <div class="ifs-educore-info-label"><?php esc_html_e( 'Routing Number', 'ifsedu-school-management' ); ?></div>
                                <div class="ifs-educore-info-value font-monospace"><?php echo esc_html( $staff->bank_routing ?: '—' ); ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Emergency Contact -->
                    <div class="col-12">
                        <div class="ifs-educore-bento-card border-danger-subtle" style="background: #fff8f8;">
                            <div class="ifs-educore-bento-section-title text-danger" style="border-bottom-color: #fee2e2;">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                                <?php esc_html_e( 'Emergency Contact', 'ifsedu-school-management' ); ?>
                            </div>
                            <div class="mb-2">
                                <div class="ifs-educore-info-label"><?php esc_html_e( 'Contact Person', 'ifsedu-school-management' ); ?></div>
                                <div class="ifs-educore-info-value fw-bold"><?php echo esc_html( $staff->emergency_name ?: '—' ); ?></div>
                            </div>
                            <div class="mb-2">
                                <div class="ifs-educore-info-label"><?php esc_html_e( 'Relationship', 'ifsedu-school-management' ); ?></div>
                                <div class="ifs-educore-info-value"><?php echo esc_html( $staff->emergency_relation ?: '—' ); ?></div>
                            </div>
                            <div>
                                <div class="ifs-educore-info-label"><?php esc_html_e( 'Phone Number', 'ifsedu-school-management' ); ?></div>
                                <div class="ifs-educore-info-value">
                                    <?php if ( ! empty( $staff->emergency_phone ) ) : ?>
                                        <a href="tel:<?php echo esc_attr( $staff->emergency_phone ); ?>" class="text-decoration-none text-danger fw-bold fs-6">
                                            <?php echo esc_html( $staff->emergency_phone ); ?>
                                        </a>
                                    <?php else : ?>
                                        —
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Social Networks -->
                    <?php if ( ! empty( $staff->linkedin_url ) || ! empty( $staff->facebook_url ) || ! empty( $staff->website_url ) ) : ?>
                        <div class="col-12 no-print">
                            <div class="ifs-educore-bento-card">
                                <div class="ifs-educore-bento-section-title">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="18" cy="5" r="3"></circle><circle cx="6" cy="12" r="3"></circle><circle cx="18" cy="19" r="3"></circle><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line></svg>
                                    <?php esc_html_e( 'Digital Presence', 'ifsedu-school-management' ); ?>
                                </div>
                                <div class="d-flex flex-wrap gap-2">
                                    <?php if ( ! empty( $staff->linkedin_url ) ) : ?>
                                        <a href="<?php echo esc_url( $staff->linkedin_url ); ?>" target="_blank" class="ifs-educore-social-pill">
                                            <svg viewBox="0 0 24 24"><path d="M19 3a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h14m-.5 15.5v-5.3a3.26 3.26 0 0 0-3.26-3.26c-.85 0-1.84.52-2.32 1.3v-1.11h-2.79v8.37h2.79v-4.93c0-.77.62-1.4 1.39-1.4a1.4 1.4 0 0 1 1.4 1.4v4.93h2.79M6.88 8.56a1.68 1.68 0 0 0 1.68-1.68c0-.93-.75-1.69-1.68-1.69a1.69 1.69 0 0 0-1.69 1.69c0 .93.76 1.68 1.69 1.68m1.39 9.94v-8.37H5.5v8.37h2.77z"/></svg>
                                            LinkedIn
                                        </a>
                                    <?php endif; ?>
                                    <?php if ( ! empty( $staff->facebook_url ) ) : ?>
                                        <a href="<?php echo esc_url( $staff->facebook_url ); ?>" target="_blank" class="ifs-educore-social-pill">
                                            <svg viewBox="0 0 24 24"><path d="M22 12c0-5.52-4.48-10-10-10S2 6.48 2 12c0 4.84 3.44 8.87 8 9.8V15H8v-3h2V9.5C10 7.57 11.57 6 13.5 6H16v3h-2c-.55 0-1 .45-1 1v2h3v3h-3v6.95c5.05-.5 9-4.76 9-9.95z"/></svg>
                                            Facebook
                                        </a>
                                    <?php endif; ?>
                                    <?php if ( ! empty( $staff->website_url ) ) : ?>
                                        <a href="<?php echo esc_url( $staff->website_url ); ?>" target="_blank" class="ifs-educore-social-pill">
                                            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg>
                                            Portfolio
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                </div>
            </div>

        </div>
    </div>
    <?php
}
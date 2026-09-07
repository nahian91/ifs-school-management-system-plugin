<?php
/**
 * Shared Form View for Adding and Editing Notices & Events
 * File: inc/notices/notice-events-add.php
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function educore_notice_events_add_edit_view( $type = 'notice' ) {
    global $wpdb;

    if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'edit_posts' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to manage notices or events.', 'ifsedu-school-management' ) );
    }

    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $is_edit = isset( $_GET['sub'] ) && 'edit' === sanitize_key( wp_unslash( $_GET['sub'] ) );
    $id      = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0;
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    $alert_message = '';
    $alert_type    = '';

    // Handle Form Processing
    $req_method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
    if ( 'POST' === $req_method && isset( $_POST['educore_save_item'] ) ) {
        if ( ! isset( $_POST['ifs_educore_item_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ifs_educore_item_nonce'] ) ), 'save_item_action' ) ) {
            $alert_message = esc_html__( 'Security check failed. Please refresh and try again.', 'ifsedu-school-management' );
            $alert_type    = 'error';
        } else {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $existing_item = ( $id > 0 ) ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$wpdb->prefix}sms_notices` WHERE id = %d LIMIT 1", $id ) ) : null;
            // phpcs:enable

            $attachment_url = ( $existing_item && ! empty( $existing_item->attachment_url ) ) ? $existing_item->attachment_url : '';
            $featured_image = ( $existing_item && ! empty( $existing_item->featured_image ) ) ? $existing_item->featured_image : '';

            // 1. Handle Attachment Document with Strict MIME Checks
            if ( ! empty( $_FILES['item_file']['name'] ) && isset( $_FILES['item_file']['error'] ) && UPLOAD_ERR_OK === $_FILES['item_file']['error'] ) {
                $allowed_doc_mimes = array(
                    'pdf'  => 'application/pdf',
                    'doc'  => 'application/msword',
                    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'zip'  => 'application/zip',
                );

                // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                $file_info = wp_check_filetype( sanitize_file_name( $_FILES['item_file']['name'] ), $allowed_doc_mimes );
                if ( in_array( $file_info['type'], $allowed_doc_mimes, true ) ) {
                    require_once ABSPATH . 'wp-admin/includes/file.php';
                    $upload = wp_handle_upload( $_FILES['item_file'], array( 'test_form' => false, 'mimes' => $allowed_doc_mimes ) );
                    if ( isset( $upload['url'] ) && empty( $upload['error'] ) ) {
                        $attachment_url = esc_url_raw( $upload['url'] );
                    }
                }
                // phpcs:enable
            }

            // 2. Handle Featured Banner Image with Strict MIME Checks
            if ( ! empty( $_FILES['featured_image_file']['name'] ) && isset( $_FILES['featured_image_file']['error'] ) && UPLOAD_ERR_OK === $_FILES['featured_image_file']['error'] ) {
                $allowed_img_mimes = array(
                    'jpg|jpeg|jpe' => 'image/jpeg',
                    'png'          => 'image/png',
                    'webp'         => 'image/webp',
                );

                // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                $img_info = wp_check_filetype( sanitize_file_name( $_FILES['featured_image_file']['name'] ), $allowed_img_mimes );
                if ( in_array( $img_info['type'], $allowed_img_mimes, true ) ) {
                    require_once ABSPATH . 'wp-admin/includes/file.php';
                    $image_upload = wp_handle_upload( $_FILES['featured_image_file'], array( 'test_form' => false, 'mimes' => $allowed_img_mimes ) );
                    if ( isset( $image_upload['url'] ) && empty( $image_upload['error'] ) ) {
                        $featured_image = esc_url_raw( $image_upload['url'] );
                    }
                }
                // phpcs:enable
            }

            $form_type        = ( 'events' === $type || 'event' === $type ) ? 'event' : 'notice';
            $title            = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
            $category         = isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : 'General';
            $target_audience  = isset( $_POST['target_audience'] ) ? sanitize_text_field( wp_unslash( $_POST['target_audience'] ) ) : 'All';
            $publish_date_val = ! empty( $_POST['publish_date'] ) ? sanitize_text_field( wp_unslash( $_POST['publish_date'] ) ) : current_time( 'Y-m-d' );
            $content_body     = isset( $_POST['content'] ) ? wp_kses_post( wp_unslash( $_POST['content'] ) ) : '';
            $status           = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'Published';
            $created_by       = get_current_user_id();
            $notice_type_val  = ( 'event' === $form_type ) ? 'Event' : $category;

            // Mapped strictly to existing database columns
            $data_payload = array(
                'title'           => $title,
                'notice_type'     => $notice_type_val,
                'priority'        => 'Normal',
                'target_audience' => $target_audience,
                'description'     => wp_strip_all_tags( $content_body ),
                'event_date'      => $publish_date_val,
                'attachment_url'  => $attachment_url,
                'created_by'      => $created_by,
                'status'          => $status,
                'featured_image'  => $featured_image,
                'item_type'       => $form_type,
                'content'         => $content_body,
            );

            $data_formats = array(
                '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s'
            );

            if ( $is_edit && $id > 0 ) {
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $res = $wpdb->update( $wpdb->prefix . 'sms_notices', $data_payload, array( 'id' => $id ), $data_formats, array( '%d' ) );
                // phpcs:enable

                if ( false !== $res ) {
                    if ( function_exists( 'educore_log_activity' ) ) {
                        /* translators: %s: Notice/Event title */
                        educore_log_activity( sprintf( __( 'Updated record: %s', 'ifsedu-school-management' ), $title ) );
                    }
                    $alert_message = esc_html__( 'Record updated successfully.', 'ifsedu-school-management' );
                    $alert_type    = 'success';
                } else {
                    $db_error      = $wpdb->last_error ? $wpdb->last_error : esc_html__( 'No rows updated or criteria mismatch.', 'ifsedu-school-management' );
                    /* translators: %s: Database error message */
                    $alert_message = sprintf( esc_html__( 'Database Update Error: %s', 'ifsedu-school-management' ), $db_error );
                    $alert_type    = 'error';
                }
            } else {
                $data_payload['created_at'] = current_time( 'mysql' );
                $data_formats[]             = '%s';

                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $res = $wpdb->insert( $wpdb->prefix . 'sms_notices', $data_payload, $data_formats );
                // phpcs:enable

                if ( false !== $res && $wpdb->insert_id > 0 ) {
                    if ( function_exists( 'educore_log_activity' ) ) {
                        /* translators: %s: Notice/Event title */
                        educore_log_activity( sprintf( __( 'Published new record: %s', 'ifsedu-school-management' ), $title ) );
                    }
                    /* translators: %d: Insert ID */
                    $alert_message = sprintf( esc_html__( 'Published successfully to database (ID: %d).', 'ifsedu-school-management' ), absint( $wpdb->insert_id ) );
                    $alert_type    = 'success';
                } else {
                    $db_error      = $wpdb->last_error ? $wpdb->last_error : esc_html__( 'Unknown SQL execution failure or table missing.', 'ifsedu-school-management' );
                    /* translators: %s: Database error message */
                    $alert_message = sprintf( esc_html__( 'Database Insert Error: %s', 'ifsedu-school-management' ), $db_error );
                    $alert_type    = 'error';
                }
            }
        }
    }

    // Refresh Item if in Edit Mode
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $item = ( $id > 0 ) ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$wpdb->prefix}sms_notices` WHERE id = %d LIMIT 1", $id ) ) : null;
    // phpcs:enable

    $base_admin_url = admin_url( 'admin.php' );
    $back_url       = add_query_arg(
        array(
            'page' => 'school_management_system',
            'tab'  => 'notices',
            'type' => ( 'events' === $type || 'event' === $type ) ? 'events' : 'notice',
            'sub'  => 'list',
        ),
        $base_admin_url
    );

    $current_title    = $item ? $item->title : '';
    $current_category = $item ? ( isset( $item->notice_type ) ? $item->notice_type : 'General' ) : 'General';
    $current_audience = $item ? $item->target_audience : 'All';
    $current_date     = $item ? ( ! empty( $item->event_date ) ? $item->event_date : current_time( 'Y-m-d' ) ) : current_time( 'Y-m-d' );
    $current_content  = $item ? ( ! empty( $item->content ) ? $item->content : $item->description ) : '';
    $current_status   = $item ? $item->status : 'Published';
    $current_feat_img = $item ? $item->featured_image : '';
    $current_attach   = $item ? $item->attachment_url : '';
    ?>

    <div class="ifs-educore-editor-root">
        
        <div class="ifs-educore-top-action-bar">
            <a href="<?php echo esc_url( $back_url ); ?>" class="ifs-educore-btn-back">
                <span class="dashicons dashicons-arrow-left-alt"></span>
                <?php esc_html_e( 'Back to List', 'ifsedu-school-management' ); ?>
            </a>
        </div>

        <?php if ( ! empty( $alert_message ) ) : ?>
            <div class="ifs-educore-alert-node ifs-educore-alert-<?php echo esc_attr( $alert_type ); ?>">
                <span class="dashicons <?php echo 'success' === $alert_type ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>"></span>
                <?php echo esc_html( $alert_message ); ?>
            </div>
        <?php endif; ?>

        <div class="ifs-educore-form-bento-card">
            
            <div class="ifs-educore-form-header">
                <h3 class="ifs-educore-form-title">
                    <span class="dashicons <?php echo $is_edit ? 'dashicons-edit' : 'dashicons-plus-alt'; ?>"></span>
                    <?php 
                    if ( $is_edit ) {
                        printf(
                            /* translators: %s: Record type name */
                            esc_html__( 'Edit %s Record', 'ifsedu-school-management' ),
                            esc_html( 'events' === $type ? 'Event' : 'Notice' )
                        );
                    } else {
                        printf(
                            /* translators: %s: Item type name */
                            esc_html__( 'Publish New %s', 'ifsedu-school-management' ),
                            esc_html( 'events' === $type ? 'Academic Event' : 'Notice / Announcement' )
                        );
                    }
                    ?>
                </h3>
            </div>

            <?php
            // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $safe_request_uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
            // phpcs:enable
            ?>
            <form method="POST" action="<?php echo esc_url( $safe_request_uri ); ?>" enctype="multipart/form-data">
                <?php wp_nonce_field( 'save_item_action', 'ifs_educore_item_nonce' ); ?>

                <!-- Row 1: Title & Date -->
                <div class="ifs-educore-grid-row ifs-educore-cols-8-4">
                    <div class="ifs-educore-field-node">
                        <label class="ifs-educore-field-label">
                            <?php esc_html_e( 'Title', 'ifsedu-school-management' ); ?> <span class="required">*</span>
                        </label>
                        <input type="text" name="title" class="ifs-educore-input-control" value="<?php echo esc_attr( $current_title ); ?>" placeholder="<?php esc_attr_e( 'Enter heading...', 'ifsedu-school-management' ); ?>" required>
                    </div>

                    <div class="ifs-educore-field-node">
                        <label class="ifs-educore-field-label">
                            <?php echo ( 'events' === $type ) ? esc_html__( 'Event Date', 'ifsedu-school-management' ) : esc_html__( 'Publish Date', 'ifsedu-school-management' ); ?> <span class="required">*</span>
                        </label>
                        <input type="date" name="publish_date" class="ifs-educore-input-control" value="<?php echo esc_attr( $current_date ); ?>" required>
                    </div>
                </div>

                <!-- Row 2: Category, Audience, Status -->
                <div class="ifs-educore-grid-row ifs-educore-cols-3">
                    <div class="ifs-educore-field-node">
                        <label class="ifs-educore-field-label"><?php esc_html_e( 'Category', 'ifsedu-school-management' ); ?></label>
                        <select name="category" class="ifs-educore-select-control">
                            <option value="General" <?php selected( $current_category, 'General' ); ?>><?php esc_html_e( 'General', 'ifsedu-school-management' ); ?></option>
                            <option value="Academic" <?php selected( $current_category, 'Academic' ); ?>><?php esc_html_e( 'Academic', 'ifsedu-school-management' ); ?></option>
                            <option value="Exam" <?php selected( $current_category, 'Exam' ); ?>><?php esc_html_e( 'Exam', 'ifsedu-school-management' ); ?></option>
                            <option value="Holiday" <?php selected( $current_category, 'Holiday' ); ?>><?php esc_html_e( 'Holiday', 'ifsedu-school-management' ); ?></option>
                        </select>
                    </div>

                    <div class="ifs-educore-field-node">
                        <label class="ifs-educore-field-label"><?php esc_html_e( 'Target Audience', 'ifsedu-school-management' ); ?></label>
                        <select name="target_audience" class="ifs-educore-select-control">
                            <option value="All" <?php selected( $current_audience, 'All' ); ?>><?php esc_html_e( 'All Users', 'ifsedu-school-management' ); ?></option>
                            <option value="Students" <?php selected( $current_audience, 'Students' ); ?>><?php esc_html_e( 'Students Only', 'ifsedu-school-management' ); ?></option>
                            <option value="Teachers" <?php selected( $current_audience, 'Teachers' ); ?>><?php esc_html_e( 'Teachers Only', 'ifsedu-school-management' ); ?></option>
                        </select>
                    </div>

                    <div class="ifs-educore-field-node">
                        <label class="ifs-educore-field-label"><?php esc_html_e( 'Publication Status', 'ifsedu-school-management' ); ?></label>
                        <select name="status" class="ifs-educore-select-control">
                            <option value="Published" <?php selected( $current_status, 'Published' ); ?>><?php esc_html_e( 'Published', 'ifsedu-school-management' ); ?></option>
                            <option value="Draft" <?php selected( $current_status, 'Draft' ); ?>><?php esc_html_e( 'Draft', 'ifsedu-school-management' ); ?></option>
                        </select>
                    </div>
                </div>

                <!-- Row 3: Description Textarea Field -->
                <div class="ifs-educore-grid-row ifs-educore-cols-12">
                    <div class="ifs-educore-field-node">
                        <label class="ifs-educore-field-label"><?php esc_html_e( 'Description & Detailed Content', 'ifsedu-school-management' ); ?></label>
                        <textarea name="content" class="ifs-educore-textarea-control" placeholder="<?php esc_attr_e( 'Write detailed notice or event description here...', 'ifsedu-school-management' ); ?>"><?php echo esc_textarea( $current_content ); ?></textarea>
                    </div>
                </div>

                <!-- Row 4: Banner Image & File Attachment -->
                <div class="ifs-educore-grid-row ifs-educore-cols-2">
                    <div class="ifs-educore-field-node">
                        <label class="ifs-educore-field-label"><?php esc_html_e( 'Banner Image (JPG / PNG / WEBP)', 'ifsedu-school-management' ); ?></label>
                        <input type="file" name="featured_image_file" class="ifs-educore-input-file" accept="image/jpeg,image/png,image/webp">
                        <?php if ( ! empty( $current_feat_img ) ) : ?>
                            <div class="ifs-educore-img-preview-box">
                                <img src="<?php echo esc_url( $current_feat_img ); ?>" alt="<?php esc_attr_e( 'Banner Preview', 'ifsedu-school-management' ); ?>">
                                <span style="font-size: 12px; color: #475569; font-weight:600;"><?php esc_html_e( 'Current Banner Active', 'ifsedu-school-management' ); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="ifs-educore-field-node">
                        <label class="ifs-educore-field-label">
                            <?php esc_html_e( 'Attachment Document (PDF / DOC / ZIP)', 'ifsedu-school-management' ); ?>
                            <?php if ( ! empty( $current_attach ) ) : ?>
                                &mdash; <a href="<?php echo esc_url( $current_attach ); ?>" target="_blank" style="color:#00523c; text-decoration:underline; font-weight:600;"><?php esc_html_e( 'View Current', 'ifsedu-school-management' ); ?></a>
                            <?php endif; ?>
                        </label>
                        <input type="file" name="item_file" class="ifs-educore-input-file" accept="application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/zip">
                    </div>
                </div>

                <!-- Submit Action Bar -->
                <div class="ifs-educore-submit-action">
                    <button type="submit" name="educore_save_item" class="ifs-educore-btn-primary">
                        <span class="dashicons dashicons-saved"></span>
                        <?php echo $is_edit ? esc_html__( 'Update Record', 'ifsedu-school-management' ) : esc_html__( 'Save & Publish', 'ifsedu-school-management' ); ?>
                    </button>
                </div>

            </form>
        </div>

    </div>
    <?php
}
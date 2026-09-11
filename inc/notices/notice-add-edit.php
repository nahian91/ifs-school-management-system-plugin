<?php
/**
 * Institutional Notice & Event Add/Edit Command Console (WP Media Uploader Enabled)
 * File: inc/notices/add-edit.php
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function educore_notice_events_add_edit_view( $type = 'notice' ) {
    global $wpdb;
    $table_notices = $wpdb->prefix . 'sms_notices';

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Unauthorized access privilege level.', 'ifsedu-school-management' ) );
    }

    // Enqueue WordPress Media Uploader Scripts & Styles
    wp_enqueue_media();

    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $is_edit = isset( $_GET['sub'] ) && 'edit' === $_GET['sub'];
    $id      = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    $item = null;
    if ( $is_edit && $id > 0 ) {
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $item = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table_notices}` WHERE id = %d", $id ) );
        // phpcs:enable
    }

    $alert_message = '';
    $alert_type    = '';

    $post_nonce = isset( $_POST['educore_item_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['educore_item_nonce'] ) ) : '';
    if ( isset( $_POST['educore_save_item'] ) && wp_verify_nonce( $post_nonce, 'save_item_action' ) ) {
        $attachment_url = isset( $_POST['attachment_url'] ) ? esc_url_raw( wp_unslash( $_POST['attachment_url'] ) ) : ( $item->attachment_url ?? '' );
        $featured_image = isset( $_POST['featured_image'] ) ? esc_url_raw( wp_unslash( $_POST['featured_image'] ) ) : ( $item->featured_image ?? '' );

        $raw_notice_type  = isset( $_POST['notice_type'] ) ? sanitize_text_field( wp_unslash( $_POST['notice_type'] ) ) : 'Notice';
        $form_type        = ( $type === 'events' || $type === 'event' ) ? 'Event' : $raw_notice_type;
        $title            = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
        $priority_val     = isset( $_POST['priority'] ) ? sanitize_text_field( wp_unslash( $_POST['priority'] ) ) : 'Normal';
        $target_audience  = isset( $_POST['target_audience'] ) ? sanitize_text_field( wp_unslash( $_POST['target_audience'] ) ) : 'All';
        $event_date_val   = ! empty( $_POST['event_date'] ) ? sanitize_text_field( wp_unslash( $_POST['event_date'] ) ) : current_time( 'Y-m-d' );
        $description_body = isset( $_POST['description'] ) ? wp_kses_post( wp_unslash( $_POST['description'] ) ) : '';
        $status           = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'Published';

        $data = array(
            'title'           => $title,
            'notice_type'     => $form_type,
            'priority'        => $priority_val,
            'target_audience' => $target_audience,
            'description'     => $description_body,
            'content'         => $description_body,
            'event_date'      => $event_date_val,
            'publish_date'    => $event_date_val,
            'attachment_url'  => $attachment_url,
            'featured_image'  => $featured_image,
            'item_type'       => ( $type === 'events' || $type === 'event' ) ? 'event' : 'notice',
            'created_by'      => get_current_user_id(),
            'status'          => $status,
        );

        if ( $is_edit && $id > 0 ) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->update( $table_notices, $data, array( 'id' => $id ) );
            // phpcs:enable
            
            if ( function_exists( 'educore_log_activity' ) ) {
                /* translators: %d: Item ID */
                educore_log_activity( sprintf( __( 'Updated notice/event record ID #%d', 'ifsedu-school-management' ), $id ) );
            }

            $alert_message = esc_html__( 'Record updated successfully.', 'ifsedu-school-management' );
            $alert_type    = 'success';
            $item          = (object) array_merge( (array) $item, $data );
        } else {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->insert( $table_notices, $data );
            // phpcs:enable
            
            if ( function_exists( 'educore_log_activity' ) ) {
                /* translators: %s: Title */
                educore_log_activity( sprintf( __( 'Published new announcement/event: %s', 'ifsedu-school-management' ), $title ) );
            }

            $alert_message = esc_html__( 'Published successfully.', 'ifsedu-school-management' );
            $alert_type    = 'success';
            $_POST         = array();
            $item          = null;
        }
    }

    $back_url = admin_url( 'admin.php?page=school_management_system&tab=notices&type=' . ( ( $type === 'events' || $type === 'event' ) ? 'events' : 'notice' ) . '&sub=list' );
    ?>

    <style id="ifs-educore-editor-pro-styles">
        .ifs-educore-editor-root {
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #0f172a;
        }
        .ifs-educore-top-action-bar {
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .ifs-educore-btn-back {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            color: #475569;
            text-decoration: none;
            border-radius: 8px;
            font-size: 13.5px;
            font-weight: 700;
            transition: all 0.2s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }
        .ifs-educore-btn-back:hover {
            background: #f8fafc;
            color: #00523c;
            border-color: #cbd5e1;
        }
        .ifs-educore-alert-node {
            padding: 14px 18px;
            border-radius: 10px;
            font-size: 13.5px;
            font-weight: 600;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.03);
        }
        .ifs-educore-alert-success {
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            color: #047857;
        }
        .ifs-educore-form-bento-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.04), 0 8px 10px -6px rgba(0, 0, 0, 0.04);
        }
        .ifs-educore-form-header {
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 16px;
            margin-bottom: 24px;
        }
        .ifs-educore-form-title {
            margin: 0;
            font-size: 17px;
            font-weight: 800;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .ifs-educore-form-title .dashicons {
            color: #00523c;
            font-size: 20px;
            width: 20px;
            height: 20px;
        }
        .ifs-educore-grid-row {
            display: grid;
            gap: 20px;
            margin-bottom: 20px;
        }
        .ifs-educore-cols-8-4 {
            grid-template-columns: 2fr 1fr;
        }
        .ifs-educore-cols-3 {
            grid-template-columns: repeat(3, 1fr);
        }
        .ifs-educore-cols-12 {
            grid-template-columns: 1fr;
        }
        .ifs-educore-field-node {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .ifs-educore-field-label {
            font-size: 13px;
            font-weight: 700;
            color: #334155;
        }
        .ifs-educore-required {
            color: #dc2626;
        }
        .ifs-educore-input-control, 
        .ifs-educore-select-control {
            width: 100%;
            padding: 10px 14px;
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            font-size: 14px;
            color: #0f172a;
            box-sizing: border-box;
            transition: all 0.2s ease;
        }
        .ifs-educore-input-control:focus, 
        .ifs-educore-select-control:focus {
            outline: none;
            border-color: #00523c;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(0, 82, 60, 0.12);
        }
        .ifs-educore-uploader-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .ifs-educore-media-preview-wrap {
            width: 100%;
            height: 120px;
            background: #f8fafc;
            border: 2px dashed #cbd5e1;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }
        .ifs-educore-media-preview-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .ifs-educore-media-placeholder {
            color: #94a3b8;
            font-size: 28px;
        }
        .ifs-educore-media-btn-row {
            display: flex;
            gap: 8px;
        }
        .ifs-educore-btn-media, .ifs-educore-btn-media-remove {
            padding: 7px 12px;
            border-radius: 8px;
            font-size: 12.5px;
            font-weight: 700;
            cursor: pointer;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.2s ease;
        }
        .ifs-educore-btn-media {
            background: #f1f5f9;
            color: #334155;
            border: 1px solid #cbd5e1;
        }
        .ifs-educore-btn-media:hover {
            background: #e2e8f0;
            color: #0f172a;
        }
        .ifs-educore-btn-media-remove {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }
        .ifs-educore-btn-media-remove:hover {
            background: #fee2e2;
        }
        .ifs-educore-editor-wrapper {
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            overflow: hidden;
            background: #f8fafc;
        }
        .ifs-educore-submit-action {
            margin-top: 30px;
            border-top: 1px solid #f1f5f9;
            padding-top: 20px;
            display: flex;
            justify-content: flex-end;
        }
        .ifs-educore-btn-primary {
            background: #00523c;
            color: #ffffff;
            border: none;
            padding: 12px 24px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(0, 82, 60, 0.25);
            transition: background 0.2s ease, transform 0.1s ease;
        }
        .ifs-educore-btn-primary:hover {
            background: #004030;
            transform: translateY(-1px);
        }
        @media screen and (max-width: 900px) {
            .ifs-educore-cols-8-4, .ifs-educore-cols-3 {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="ifs-educore-editor-root">
        <div class="ifs-educore-top-action-bar">
            <a href="<?php echo esc_url( $back_url ); ?>" class="ifs-educore-btn-back">
                <span class="dashicons dashicons-arrow-left-alt"></span>
                <?php esc_html_e( 'Back to List', 'ifsedu-school-management' ); ?>
            </a>
        </div>

        <?php if ( ! empty( $alert_message ) ) : ?>
            <div class="ifs-educore-alert-node ifs-educore-alert-<?php echo esc_attr( $alert_type ); ?>">
                <span class="dashicons dashicons-yes-alt"></span>
                <?php echo esc_html( $alert_message ); ?>
            </div>
        <?php endif; ?>

        <div class="ifs-educore-form-bento-card">
            <div class="ifs-educore-form-header">
                <h3 class="ifs-educore-form-title">
                    <span class="dashicons <?php echo $is_edit ? 'dashicons-edit' : 'dashicons-plus-alt'; ?>"></span>
                    <?php echo $is_edit ? esc_html__( 'Edit Record Details', 'ifsedu-school-management' ) : esc_html__( 'Add New Announcement / Event', 'ifsedu-school-management' ); ?>
                </h3>
            </div>

            <form method="POST" action="">
                <?php wp_nonce_field( 'save_item_action', 'educore_item_nonce' ); ?>

                <div class="ifs-educore-grid-row <?php echo ( $type !== 'events' ) ? 'ifs-educore-cols-8-4' : 'ifs-educore-cols-12'; ?>">
                    <div class="ifs-educore-field-node">
                        <label class="ifs-educore-field-label">
                            <?php esc_html_e( 'Title', 'ifsedu-school-management' ); ?> <span class="ifs-educore-required">*</span>
                        </label>
                        <input type="text" name="title" class="ifs-educore-input-control" value="<?php echo $item ? esc_attr( $item->title ) : ''; ?>" placeholder="Enter notice or event heading..." required>
                    </div>

                    <?php if ( $type !== 'events' ) : ?>
                        <div class="ifs-educore-field-node">
                            <label class="ifs-educore-field-label"><?php esc_html_e( 'Category Type', 'ifsedu-school-management' ); ?></label>
                            <select name="notice_type" class="ifs-educore-select-control">
                                <option value="Notice" <?php selected( $item ? ( $item->notice_type ?? $item->category ) : '', 'Notice' ); ?>>General Notice</option>
                                <option value="Holiday" <?php selected( $item ? ( $item->notice_type ?? $item->category ) : '', 'Holiday' ); ?>>Holiday Notice</option>
                                <option value="Exam" <?php selected( $item ? ( $item->notice_type ?? $item->category ) : '', 'Exam' ); ?>>Exam Notice</option>
                            </select>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="ifs-educore-grid-row ifs-educore-cols-3">
                    <div class="ifs-educore-field-node">
                        <label class="ifs-educore-field-label"><?php esc_html_e( 'Target Audience', 'ifsedu-school-management' ); ?></label>
                        <select name="target_audience" class="ifs-educore-select-control">
                            <option value="All" <?php selected( $item ? $item->target_audience : '', 'All' ); ?>>All Stakeholders</option>
                            <option value="Students" <?php selected( $item ? $item->target_audience : '', 'Students' ); ?>>Students Only</option>
                            <option value="Teachers" <?php selected( $item ? $item->target_audience : '', 'Teachers' ); ?>>Teachers Only</option>
                        </select>
                    </div>

                    <div class="ifs-educore-field-node">
                        <label class="ifs-educore-field-label"><?php esc_html_e( 'Priority Level', 'ifsedu-school-management' ); ?></label>
                        <select name="priority" class="ifs-educore-select-control">
                            <option value="Normal" <?php selected( $item ? $item->priority : '', 'Normal' ); ?>>Normal</option>
                            <option value="High" <?php selected( $item ? $item->priority : '', 'High' ); ?>>High</option>
                            <option value="Urgent" <?php selected( $item ? $item->priority : '', 'Urgent' ); ?>>Urgent</option>
                        </select>
                    </div>

                    <div class="ifs-educore-field-node">
                        <label class="ifs-educore-field-label">
                            <?php echo ( $type === 'events' ) ? esc_html__( 'Event Date', 'ifsedu-school-management' ) : esc_html__( 'Effective Date', 'ifsedu-school-management' ); ?>
                        </label>
                        <input type="date" name="event_date" class="ifs-educore-input-control" value="<?php echo $item ? esc_attr( ! empty( $item->event_date ) && $item->event_date !== '1970-01-01' ? $item->event_date : ( ! empty( $item->publish_date ) ? $item->publish_date : '' ) ) : esc_attr( current_time( 'Y-m-d' ) ); ?>">
                    </div>
                </div>

                <div class="ifs-educore-grid-row ifs-educore-cols-12">
                    <div class="ifs-educore-field-node">
                        <label class="ifs-educore-field-label"><?php esc_html_e( 'Description Details', 'ifsedu-school-management' ); ?></label>
                        <div class="ifs-educore-editor-wrapper">
                            <?php 
                            wp_editor( 
                                $item ? ( ! empty( $item->description ) ? $item->description : $item->content ) : '', 
                                'description', 
                                array( 
                                    'textarea_rows' => 8,
                                    'quicktags'     => true,
                                    'tinymce'       => true
                                ) 
                            ); 
                            ?>
                        </div>
                    </div>
                </div>

                <div class="ifs-educore-grid-row ifs-educore-cols-3">
                    <!-- Featured Image Uploader -->
                    <div class="ifs-educore-field-node">
                        <label class="ifs-educore-field-label"><?php esc_html_e( 'Featured Banner Image', 'ifsedu-school-management' ); ?></label>
                        <div class="ifs-educore-uploader-group">
                            <div class="ifs-educore-media-preview-wrap" id="featured_image_preview_container">
                                <?php if ( $item && ! empty( $item->featured_image ) ) : ?>
                                    <img src="<?php echo esc_url( $item->featured_image ); ?>" alt="Preview">
                                <?php else : ?>
                                    <span class="dashicons dashicons-format-image ifs-educore-media-placeholder"></span>
                                <?php endif; ?>
                            </div>
                            <input type="hidden" name="featured_image" id="featured_image_input" value="<?php echo $item ? esc_attr( $item->featured_image ) : ''; ?>">
                            <div class="ifs-educore-media-btn-row">
                                <button type="button" class="ifs-educore-btn-media" id="upload_featured_image_btn">
                                    <span class="dashicons dashicons-upload"></span> <?php esc_html_e( 'Select Image', 'ifsedu-school-management' ); ?>
                                </button>
                                <button type="button" class="ifs-educore-btn-media-remove" id="remove_featured_image_btn" style="<?php echo ( $item && ! empty( $item->featured_image ) ) ? '' : 'display:none;'; ?>">
                                    <span class="dashicons dashicons-trash"></span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Attachment Document/File Uploader -->
                    <div class="ifs-educore-field-node">
                        <label class="ifs-educore-field-label"><?php esc_html_e( 'Attachment Document (PDF / File)', 'ifsedu-school-management' ); ?></label>
                        <div class="ifs-educore-uploader-group">
                            <div class="ifs-educore-media-preview-wrap" id="attachment_url_preview_container" style="background: #f1f5f9;">
                                <?php if ( $item && ! empty( $item->attachment_url ) ) : ?>
                                    <div style="padding: 10px; text-align: center; word-break: break-all; font-size: 12px; font-weight: 600; color: #00523c;">
                                        📄 <?php echo esc_html( basename( $item->attachment_url ) ); ?>
                                    </div>
                                <?php else : ?>
                                    <span class="dashicons dashicons-media-document ifs-educore-media-placeholder"></span>
                                <?php endif; ?>
                            </div>
                            <input type="hidden" name="attachment_url" id="attachment_url_input" value="<?php echo $item ? esc_attr( $item->attachment_url ) : ''; ?>">
                            <div class="ifs-educore-media-btn-row">
                                <button type="button" class="ifs-educore-btn-media" id="upload_attachment_url_btn">
                                    <span class="dashicons dashicons-upload"></span> <?php esc_html_e( 'Select File', 'ifsedu-school-management' ); ?>
                                </button>
                                <button type="button" class="ifs-educore-btn-media-remove" id="remove_attachment_url_btn" style="<?php echo ( $item && ! empty( $item->attachment_url ) ) ? '' : 'display:none;'; ?>">
                                    <span class="dashicons dashicons-trash"></span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="ifs-educore-field-node">
                        <label class="ifs-educore-field-label"><?php esc_html_e( 'Publication Status', 'ifsedu-school-management' ); ?></label>
                        <select name="status" class="ifs-educore-select-control">
                            <option value="Published" <?php selected( $item ? $item->status : '', 'Published' ); ?>>Published</option>
                            <option value="Draft" <?php selected( $item ? $item->status : '', 'Draft' ); ?>>Draft</option>
                        </select>
                    </div>
                </div>

                <div class="ifs-educore-submit-action">
                    <button type="submit" name="educore_save_item" class="ifs-educore-btn-primary">
                        <span class="dashicons dashicons-saved"></span>
                        <?php esc_html_e( 'Save & Publish Record', 'ifsedu-school-management' ); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- WordPress Media Uploader JavaScript Integration -->
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        function ifsSetupMediaUploader(buttonId, inputId, containerId, removeBtnId, isImage) {
            var mediaFrame;
            $('#' + buttonId).on('click', function(e) {
                e.preventDefault();
                if (mediaFrame) {
                    mediaFrame.open();
                    return;
                }
                mediaFrame = wp.media({
                    title: '<?php echo esc_js( __( 'Select or Upload Media File', 'ifsedu-school-management' ) ); ?>',
                    button: { text: '<?php echo esc_js( __( 'Use this file', 'ifsedu-school-management' ) ); ?>' },
                    multiple: false
                });

                mediaFrame.on('select', function() {
                    var attachment = mediaFrame.state().get('selection').first().toJSON();
                    $('#' + inputId).val(attachment.url);
                    
                    if (isImage) {
                        $('#' + containerId).html('<img src="' + attachment.url + '" alt="Preview">');
                    } else {
                        var fileName = attachment.filename || attachment.url.split('/').pop();
                        $('#' + containerId).html('<div style="padding: 10px; text-align: center; word-break: break-all; font-size: 12px; font-weight: 600; color: #00523c;">📄 ' + fileName + '</div>');
                    }
                    $('#' + removeBtnId).show();
                });

                mediaFrame.open();
            });

            $('#' + removeBtnId).on('click', function(e) {
                e.preventDefault();
                $('#' + inputId).val('');
                if (isImage) {
                    $('#' + containerId).html('<span class="dashicons dashicons-format-image ifs-educore-media-placeholder"></span>');
                } else {
                    $('#' + containerId).html('<span class="dashicons dashicons-media-document ifs-educore-media-placeholder"></span>');
                }
                $(this).hide();
            });
        }

        ifsSetupMediaUploader('upload_featured_image_btn', 'featured_image_input', 'featured_image_preview_container', 'remove_featured_image_btn', true);
        ifsSetupMediaUploader('upload_attachment_url_btn', 'attachment_url_input', 'attachment_url_preview_container', 'remove_attachment_url_btn', false);
    });
    </script>
    <?php
}
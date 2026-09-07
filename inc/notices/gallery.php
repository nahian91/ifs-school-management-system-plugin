<?php
/**
 * Gallery Module Internal Sub-Router & View Controller
 * File: inc/notices/gallery.php
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Gallery Module Internal Sub-Router
 *
 * @param string $sub_tab The active sub-tab slug.
 */
function educore_gallery_router( $sub_tab ) {
    if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'edit_posts' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to access the gallery module.', 'ifsedu-school-management' ) );
    }

    $allowed_sub_tabs = array( 'list', 'add', 'edit', 'view', 'delete_photo', 'delete' );
    $sub_tab          = in_array( $sub_tab, $allowed_sub_tabs, true ) ? $sub_tab : 'list';

    switch ( $sub_tab ) {
        case 'add':
        case 'edit':
            educore_gallery_add_edit_view();
            break;

        case 'view':
            educore_gallery_single_album_view();
            break;

        case 'delete_photo':
            educore_gallery_photo_delete_action();
            break;

        case 'delete':
            educore_gallery_delete_action();
            break;

        case 'list':
        default:
            educore_gallery_list_view();
            break;
    }
}

/**
 * Photo Albums Grid Directory View
 * Theme Aesthetic: Neo-Bento Card Grid Layout
 */
function educore_gallery_list_view() {
    global $wpdb;
    $table_albums = $wpdb->prefix . 'sms_gallery_albums';
    $table_photos = $wpdb->prefix . 'sms_gallery_photos';

    if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'edit_posts' ) ) {
        wp_die( esc_html__( 'Permission denied.', 'ifsedu-school-management' ) );
    }

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $albums = $wpdb->get_results( "SELECT * FROM `{$table_albums}` ORDER BY id DESC" );
    // phpcs:enable
    $albums = is_array( $albums ) ? $albums : array();
    
    $add_url = add_query_arg(
        array(
            'page' => 'school_management_system',
            'tab'  => 'notice',
            'type' => 'gallery',
            'sub'  => 'add',
        ),
        admin_url( 'admin.php' )
    );
    ?>

    <div class="dpt-gallery-root">
        
        <div class="afdp-header-bar">
            <h2 class="afdp-page-title">
                <span class="dashicons dashicons-format-gallery"></span> 
                <?php esc_html_e( 'Photo Albums Directory', 'ifsedu-school-management' ); ?>
            </h2>
            <a href="<?php echo esc_url( $add_url ); ?>" class="dpt-btn-primary">
                <span class="dashicons dashicons-plus-alt2" style="font-size:16px; width:16px; height:16px;"></span>
                <?php esc_html_e( 'Create New Album', 'ifsedu-school-management' ); ?>
            </a>
        </div>

        <?php if ( ! empty( $albums ) ) : ?>
            <div class="dpt-bento-grid">
                <?php foreach ( $albums as $album ) : 
                    $album_id       = absint( $album->id );
                    $base_admin_url = admin_url( 'admin.php' );
                    $view_url       = add_query_arg( array( 'page' => 'school_management_system', 'tab' => 'notice', 'type' => 'gallery', 'sub' => 'view', 'id' => $album_id ), $base_admin_url );
                    $edit_url       = add_query_arg( array( 'page' => 'school_management_system', 'tab' => 'notice', 'type' => 'gallery', 'sub' => 'edit', 'id' => $album_id ), $base_admin_url );
                    $delete_url     = wp_nonce_url( 
                        add_query_arg( array( 'page' => 'school_management_system', 'tab' => 'notice', 'type' => 'gallery', 'sub' => 'delete', 'id' => $album_id ), $base_admin_url ), 
                        'delete_gallery_' . $album_id 
                    );
                    
                    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                    $photo_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM `{$table_photos}` WHERE album_id = %d", $album_id ) );
                    // phpcs:enable

                    $cover_src = ! empty( $album->cover_image ) ? $album->cover_image : ( defined( 'EDUCORE_URL' ) ? EDUCORE_URL . 'assets/img/logo.png' : '' );
                ?>
                <div class="dpt-album-card">
                    <div class="dpt-cover-container">
                        <img src="<?php echo esc_url( $cover_src ); ?>" class="dpt-cover-img" alt="<?php echo esc_attr( $album->title ); ?>">
                        <span class="dpt-category-badge">
                            <?php echo esc_html( ! empty( $album->category ) ? $album->category : 'General' ); ?>
                        </span>
                    </div>

                    <div class="dpt-card-body">
                        <h3 class="dpt-album-title" title="<?php echo esc_attr( $album->title ); ?>"><?php echo esc_html( $album->title ); ?></h3>
                        <span class="dpt-photo-count">
                            <span class="dashicons dashicons-images-alt2" style="font-size: 14px; width:14px; height:14px;"></span> 
                            <?php echo esc_html( $photo_count ); ?> <?php esc_html_e( 'Photos', 'ifsedu-school-management' ); ?>
                        </span>
                    </div>

                    <div class="dpt-card-footer">
                        <a href="<?php echo esc_url( $view_url ); ?>" class="dpt-square-btn dpt-btn-view" title="<?php esc_attr_e( 'View Album', 'ifsedu-school-management' ); ?>">
                            <span class="dashicons dashicons-visibility"></span>
                        </a>
                        <a href="<?php echo esc_url( $edit_url ); ?>" class="dpt-square-btn dpt-btn-edit" title="<?php esc_attr_e( 'Edit Album', 'ifsedu-school-management' ); ?>">
                            <span class="dashicons dashicons-edit"></span>
                        </a>
                        <a href="<?php echo esc_url( $delete_url ); ?>" class="dpt-square-btn dpt-btn-delete" title="<?php esc_attr_e( 'Delete Album', 'ifsedu-school-management' ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this album and all its images?', 'ifsedu-school-management' ) ); ?>');">
                            <span class="dashicons dashicons-trash"></span>
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else : ?>
            <div class="dpt-empty-state">
                <span class="dashicons dashicons-format-gallery"></span>
                <h5><?php esc_html_e( 'No photo albums created yet.', 'ifsedu-school-management' ); ?></h5>
            </div>
        <?php endif; ?>

    </div>
    <?php
}

/**
 * Single Album Gallery Photo View
 */
function educore_gallery_single_album_view() {
    global $wpdb;
    $table_albums = $wpdb->prefix . 'sms_gallery_albums';
    $table_photos = $wpdb->prefix . 'sms_gallery_photos';

    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $album_id = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0;
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $album = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table_albums}` WHERE id = %d LIMIT 1", $album_id ) );

    if ( ! $album ) {
        ?>
        <style>
            .afdp-alert-error {
                background: #fef2f2;
                border: 1px solid #fecaca;
                color: #b91c1c;
                padding: 16px 20px;
                border-radius: 12px;
                font-weight: 700;
                font-size: 14px;
                display: flex;
                align-items: center;
                gap: 10px;
                margin-top: 20px;
            }
        </style>
        <div class="afdp-alert-error">
            <span class="dashicons dashicons-dismiss"></span>
            <?php esc_html_e( 'Album not found or has been deleted.', 'ifsedu-school-management' ); ?>
        </div>
        <?php
        return;
    }

    $photos = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$table_photos}` WHERE album_id = %d ORDER BY id DESC", $album_id ) );
    // phpcs:enable
    $photos = is_array( $photos ) ? $photos : array();
    
    $base_admin_url = admin_url( 'admin.php' );
    $back_url       = add_query_arg( array( 'page' => 'school_management_system', 'tab' => 'notice', 'type' => 'gallery', 'sub' => 'list' ), $base_admin_url );
    $edit_url       = add_query_arg( array( 'page' => 'school_management_system', 'tab' => 'notice', 'type' => 'gallery', 'sub' => 'edit', 'id' => $album_id ), $base_admin_url );
    ?>

    <style>
        /* ==========================================================================
           SINGLE ALBUM VIEW - NEO-BENTO SYSTEM
           ========================================================================== */
        .dpt-single-root {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            color: #0f172a;
        }

        .dpt-top-action-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            gap: 16px;
            flex-wrap: wrap;
        }

        .dpt-btn-back {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            color: #334155;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.2s ease;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .dpt-btn-back:hover {
            background: #f8fafc;
            border-color: #94a3b8;
            color: #0f172a;
        }

        .dpt-btn-action {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: #2563eb;
            color: #ffffff;
            font-size: 13px;
            font-weight: 700;
            border-radius: 10px;
            text-decoration: none;
            transition: all 0.2s ease;
            box-shadow: 0 2px 8px rgba(37, 99, 235, 0.2);
        }

        .dpt-btn-action:hover {
            background: #1d4ed8;
            color: #ffffff;
        }

        /* Detail Card */
        .dpt-album-detail-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.03);
        }

        .afdp-album-header-title {
            font-size: 22px;
            font-weight: 800;
            color: #00523c;
            margin: 0 0 8px 0;
            letter-spacing: -0.4px;
        }

        .dpt-meta-strip {
            display: flex;
            align-items: center;
            gap: 16px;
            font-size: 12.5px;
            color: #64748b;
            font-weight: 600;
            margin-bottom: 12px;
        }

        .dpt-album-desc {
            color: #334155;
            font-size: 14px;
            line-height: 1.6;
            margin: 12px 0 0 0;
            padding-top: 12px;
            border-top: 1px solid #f1f5f9;
        }

        /* Photo Gallery Responsive Grid */
        .dpt-photo-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
            gap: 16px;
        }

        .dpt-photo-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
            transition: all 0.2s ease;
        }

        .dpt-photo-card:hover {
            transform: scale(1.02);
            border-color: #cbd5e1;
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
        }

        .dpt-photo-link {
            display: block;
            height: 140px;
            width: 100%;
        }

        .dpt-photo-link img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .dpt-empty-box {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 40px;
            text-align: center;
            color: #64748b;
            font-weight: 600;
        }
    </style>

    <div class="dpt-single-root">
        
        <div class="dpt-top-action-bar">
            <a href="<?php echo esc_url( $back_url ); ?>" class="dpt-btn-back">
                <span class="dashicons dashicons-arrow-left-alt"></span>
                <?php esc_html_e( 'Back to Album Directory', 'ifsedu-school-management' ); ?>
            </a>
            <a href="<?php echo esc_url( $edit_url ); ?>" class="dpt-btn-action">
                <span class="dashicons dashicons-edit"></span>
                <?php esc_html_e( 'Edit / Upload More Photos', 'ifsedu-school-management' ); ?>
            </a>
        </div>

        <div class="dpt-album-detail-card">
            <h3 class="afdp-album-header-title"><?php echo esc_html( $album->title ); ?></h3>
            <div class="dpt-meta-strip">
                <span><strong><?php esc_html_e( 'Category:', 'ifsedu-school-management' ); ?></strong> <?php echo esc_html( $album->category ); ?></span>
                <span>•</span>
                <span><strong><?php esc_html_e( 'Total Photos:', 'ifsedu-school-management' ); ?></strong> <?php echo esc_html( count( $photos ) ); ?></span>
            </div>
            <?php if ( ! empty( $album->description ) ) : ?>
                <p class="dpt-album-desc"><?php echo esc_html( $album->description ); ?></p>
            <?php endif; ?>
        </div>

        <?php if ( ! empty( $photos ) ) : ?>
            <div class="dpt-photo-grid">
                <?php foreach ( $photos as $photo ) : ?>
                    <div class="dpt-photo-card">
                        <a href="<?php echo esc_url( $photo->image_url ); ?>" target="_blank" class="dpt-photo-link">
                            <img src="<?php echo esc_url( $photo->image_url ); ?>" alt="<?php esc_attr_e( 'Gallery Photo', 'ifsedu-school-management' ); ?>">
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else : ?>
            <div class="dpt-empty-box">
                <?php esc_html_e( 'This album contains no photos yet.', 'ifsedu-school-management' ); ?>
            </div>
        <?php endif; ?>

    </div>
    <?php
}

/**
 * Add / Edit Gallery Album Form
 */
function educore_gallery_add_edit_view() {
    global $wpdb;
    $table_albums = $wpdb->prefix . 'sms_gallery_albums';
    $table_photos = $wpdb->prefix . 'sms_gallery_photos';

    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $is_edit  = isset( $_GET['sub'] ) && 'edit' === sanitize_key( wp_unslash( $_GET['sub'] ) );
    $album_id = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0;
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    $album         = null;
    $photos        = array();
    $saved_message = false;

    if ( $is_edit && $album_id > 0 ) {
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $album  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table_albums}` WHERE id = %d LIMIT 1", $album_id ) );
        $photos = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$table_photos}` WHERE album_id = %d ORDER BY id DESC", $album_id ) );
        // phpcs:enable
        $photos = is_array( $photos ) ? $photos : array();
    }

    $req_method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
    if ( 'POST' === $req_method && isset( $_POST['educore_save_gallery'] ) ) {
        $gallery_nonce = isset( $_POST['educore_gallery_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['educore_gallery_nonce'] ) ) : '';
        if ( wp_verify_nonce( $gallery_nonce, 'save_gallery_action' ) ) {
            $cover_image = $album ? $album->cover_image : '';

            // Handle Cover Image Upload with Strict MIME Check
            if ( ! empty( $_FILES['cover_image']['name'] ) && isset( $_FILES['cover_image']['error'] ) && UPLOAD_ERR_OK === $_FILES['cover_image']['error'] ) {
                $allowed_mimes = array(
                    'jpg|jpeg|jpe' => 'image/jpeg',
                    'png'          => 'image/png',
                    'webp'         => 'image/webp',
                );

                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                $file_info = wp_check_filetype( sanitize_file_name( $_FILES['cover_image']['name'] ), $allowed_mimes );

                if ( in_array( $file_info['type'], $allowed_mimes, true ) ) {
                    require_once ABSPATH . 'wp-admin/includes/file.php';
                    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                    $upload = wp_handle_upload( $_FILES['cover_image'], array( 'test_form' => false, 'mimes' => $allowed_mimes ) );
                    if ( ! isset( $upload['error'] ) && isset( $upload['url'] ) ) {
                        $cover_image = esc_url_raw( $upload['url'] );
                    }
                }
            }

            $album_title       = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
            $album_category    = isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : 'General';
            $album_description = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';
            $album_status      = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'Published';

            $album_data = array(
                'title'       => $album_title,
                'category'    => $album_category,
                'description' => $album_description,
                'cover_image' => sanitize_url( $cover_image ),
                'status'      => $album_status,
            );

            $album_formats = array( '%s', '%s', '%s', '%s', '%s' );

            $current_id = 0;
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
            if ( $is_edit && $album_id > 0 ) {
                $wpdb->update( $table_albums, $album_data, array( 'id' => $album_id ), $album_formats, array( '%d' ) );
                $current_id = $album_id;
            } else {
                $wpdb->insert( $table_albums, $album_data, $album_formats );
                $current_id = (int) $wpdb->insert_id;
            }
            // phpcs:enable

            // Multi-File Upload Processing with MIME Check
            if ( ! empty( $_FILES['gallery_photos']['name'][0] ) && $current_id > 0 ) {
                $allowed_mimes = array(
                    'jpg|jpeg|jpe' => 'image/jpeg',
                    'png'          => 'image/png',
                    'webp'         => 'image/webp',
                );

                require_once ABSPATH . 'wp-admin/includes/file.php';
                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                $files = $_FILES['gallery_photos'];
                
                foreach ( $files['name'] as $k => $v ) {
                    if ( ! empty( $files['name'][ $k ] ) && UPLOAD_ERR_OK === $files['error'][ $k ] ) {
                        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                        $file_info = wp_check_filetype( sanitize_file_name( $files['name'][ $k ] ), $allowed_mimes );
                        if ( ! in_array( $file_info['type'], $allowed_mimes, true ) ) {
                            continue;
                        }

                        $file = array(
                            'name'     => $files['name'][ $k ],
                            'type'     => $files['type'][ $k ],
                            'tmp_name' => $files['tmp_name'][ $k ],
                            'error'    => $files['error'][ $k ],
                            'size'     => $files['size'][ $k ],
                        );
                        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                        $up = wp_handle_upload( $file, array( 'test_form' => false, 'mimes' => $allowed_mimes ) );
                        if ( ! isset( $up['error'] ) && isset( $up['url'] ) ) {
                            $sanitized_url = esc_url_raw( $up['url'] );
                            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
                            $wpdb->insert( $table_photos, array( 'album_id' => $current_id, 'image_url' => $sanitized_url ), array( '%d', '%s' ) );

                            if ( empty( $cover_image ) ) {
                                $cover_image = $sanitized_url;
                                $wpdb->update( $table_albums, array( 'cover_image' => $cover_image ), array( 'id' => $current_id ), array( '%s' ), array( '%d' ) );
                            }
                            // phpcs:enable
                        }
                    }
                }
            }

            $saved_message = true;

            // Reload data
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $album  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table_albums}` WHERE id = %d LIMIT 1", $current_id ) );
            $photos = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$table_photos}` WHERE album_id = %d ORDER BY id DESC", $current_id ) );
            // phpcs:enable
            $photos = is_array( $photos ) ? $photos : array();
        }
    }

    $base_admin_url = admin_url( 'admin.php' );
    $back_url       = add_query_arg( array( 'page' => 'school_management_system', 'tab' => 'notice', 'type' => 'gallery', 'sub' => 'list' ), $base_admin_url );
    ?>

    <style>
        /* ==========================================================================
           ADD/EDIT FORM - NEO-BENTO ARCHITECTURE
           ========================================================================== */
        .dpt-form-root {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            color: #0f172a;
        }

        .dpt-top-action-bar {
            margin-bottom: 20px;
        }

        .dpt-btn-back {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            color: #334155;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.2s ease;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .dpt-btn-back:hover {
            background: #f8fafc;
            border-color: #94a3b8;
            color: #0f172a;
        }

        .afdp-alert-success {
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            color: #047857;
            padding: 14px 20px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }

        .dpt-bento-form-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.03);
        }

        .afdp-form-title {
            font-size: 20px;
            font-weight: 800;
            color: #00523c;
            margin: 0 0 24px 0;
            padding-bottom: 16px;
            border-bottom: 2px solid #f1f5f9;
            letter-spacing: -0.4px;
        }

        /* Form Grid Mechanics */
        .dpt-form-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        @media (max-width: 768px) {
            .dpt-form-row {
                grid-template-columns: 1fr;
            }
        }

        .dpt-form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 20px;
        }

        .dpt-form-group label {
            font-size: 13px;
            font-weight: 700;
            color: #334155;
        }

        .dpt-field-input,
        .dpt-field-select,
        .dpt-field-textarea {
            width: 100%;
            padding: 10px 14px;
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            font-size: 14px;
            color: #0f172a;
            transition: all 0.2s ease;
            box-sizing: border-box;
        }

        .dpt-field-input:focus,
        .dpt-field-select:focus,
        .dpt-field-textarea:focus {
            outline: none;
            border-color: #00523c;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(0, 106, 78, 0.1);
        }

        /* Upload Area Accent Node */
        .dpt-upload-bento-node {
            background: #f0fdf4;
            border: 1px dashed #86efac;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
        }

        .dpt-upload-bento-node label {
            color: #065f46;
            font-size: 14px;
            font-weight: 800;
            margin-bottom: 6px;
            display: block;
        }

        /* Current Photos Management Grid */
        .dpt-photos-manager {
            margin-bottom: 28px;
        }

        .dpt-photos-manager-title {
            font-size: 15px;
            font-weight: 800;
            color: #0f172a;
            padding-bottom: 10px;
            border-bottom: 1px solid #f1f5f9;
            margin: 0 0 16px 0;
        }

        .dpt-manage-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
            gap: 12px;
        }

        .dpt-manage-photo-card {
            position: relative;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 4px;
            background: #ffffff;
            height: 90px;
        }

        .dpt-manage-photo-card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 6px;
        }

        .dpt-btn-photo-del {
            position: absolute;
            top: -6px;
            right: -6px;
            width: 24px;
            height: 24px;
            background: #dc2626;
            color: #ffffff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            font-size: 14px;
            font-weight: 800;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            transition: transform 0.2s ease;
        }

        .dpt-btn-photo-del:hover {
            transform: scale(1.15);
            color: #ffffff;
        }

        .dpt-btn-submit {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 32px;
            background: #00523c;
            color: #ffffff;
            font-size: 14px;
            font-weight: 800;
            border-radius: 10px;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(0, 106, 78, 0.25);
        }

        .dpt-btn-submit:hover {
            background: #004080;
            transform: translateY(-1px);
        }
    </style>

    <div class="dpt-form-root">
        
        <div class="dpt-top-action-bar">
            <a href="<?php echo esc_url( $back_url ); ?>" class="dpt-btn-back">
                <span class="dashicons dashicons-arrow-left-alt"></span>
                <?php esc_html_e( 'Back to Gallery', 'ifsedu-school-management' ); ?>
            </a>
        </div>

        <?php if ( $saved_message ) : ?>
            <div class="afdp-alert-success">
                <span class="dashicons dashicons-yes-alt"></span>
                <?php esc_html_e( 'Album saved successfully.', 'ifsedu-school-management' ); ?>
            </div>
        <?php endif; ?>

        <div class="dpt-bento-form-card">
            <h3 class="afdp-form-title">
                <?php echo $is_edit ? esc_html__( 'Edit Album Details', 'ifsedu-school-management' ) : esc_html__( 'Create Photo Album', 'ifsedu-school-management' ); ?>
            </h3>

            <form method="POST" action="" enctype="multipart/form-data">
                <?php wp_nonce_field( 'save_gallery_action', 'educore_gallery_nonce' ); ?>

                <div class="dpt-form-row">
                    <div class="dpt-form-group" style="margin-bottom:0;">
                        <label><?php esc_html_e( 'Album Title', 'ifsedu-school-management' ); ?></label>
                        <input type="text" name="title" class="dpt-field-input" value="<?php echo $album ? esc_attr( $album->title ) : ''; ?>" required>
                    </div>
                    <div class="dpt-form-group" style="margin-bottom:0;">
                        <label><?php esc_html_e( 'Category', 'ifsedu-school-management' ); ?></label>
                        <select name="category" class="dpt-field-select">
                            <option value="Academic" <?php selected( $album ? $album->category : '', 'Academic' ); ?>><?php esc_html_e( 'Academic', 'ifsedu-school-management' ); ?></option>
                            <option value="Sports" <?php selected( $album ? $album->category : '', 'Sports' ); ?>><?php esc_html_e( 'Sports', 'ifsedu-school-management' ); ?></option>
                            <option value="Cultural" <?php selected( $album ? $album->category : '', 'Cultural' ); ?>><?php esc_html_e( 'Cultural', 'ifsedu-school-management' ); ?></option>
                            <option value="Campus" <?php selected( $album ? $album->category : '', 'Campus' ); ?>><?php esc_html_e( 'Campus & Infrastructure', 'ifsedu-school-management' ); ?></option>
                            <option value="General" <?php selected( $album ? $album->category : '', 'General' ); ?>><?php esc_html_e( 'General', 'ifsedu-school-management' ); ?></option>
                        </select>
                    </div>
                    <div class="dpt-form-group" style="margin-bottom:0;">
                        <label><?php esc_html_e( 'Status', 'ifsedu-school-management' ); ?></label>
                        <select name="status" class="dpt-field-select">
                            <option value="Published" <?php selected( $album ? $album->status : '', 'Published' ); ?>><?php esc_html_e( 'Published', 'ifsedu-school-management' ); ?></option>
                            <option value="Draft" <?php selected( $album ? $album->status : '', 'Draft' ); ?>><?php esc_html_e( 'Draft', 'ifsedu-school-management' ); ?></option>
                        </select>
                    </div>
                </div>

                <div class="dpt-form-group">
                    <label><?php esc_html_e( 'Description', 'ifsedu-school-management' ); ?></label>
                    <textarea name="description" class="dpt-field-textarea" rows="3"><?php echo $album ? esc_textarea( $album->description ) : ''; ?></textarea>
                </div>

                <div class="dpt-form-group">
                    <label><?php esc_html_e( 'Cover Image (Thumbnail)', 'ifsedu-school-management' ); ?></label>
                    <input type="file" name="cover_image" class="dpt-field-input" accept="image/jpeg,image/png,image/webp">
                    <?php if ( $album && ! empty( $album->cover_image ) ) : ?>
                        <div style="margin-top: 10px;">
                            <img src="<?php echo esc_url( $album->cover_image ); ?>" alt="<?php esc_attr_e( 'Cover Thumbnail', 'ifsedu-school-management' ); ?>" style="width: 80px; height: 60px; object-fit: cover; border-radius: 8px; border: 1px solid #cbd5e1;">
                        </div>
                    <?php endif; ?>
                </div>

                <div class="dpt-upload-bento-node">
                    <label><?php esc_html_e( 'Upload Photos to Album', 'ifsedu-school-management' ); ?></label>
                    <input type="file" name="gallery_photos[]" class="dpt-field-input" accept="image/jpeg,image/png,image/webp" multiple>
                    <p style="margin: 6px 0 0 0; font-size: 12px; color: #047857; font-weight: 600;">
                        <?php esc_html_e( 'Select multiple files to batch upload images into this gallery.', 'ifsedu-school-management' ); ?>
                    </p>
                </div>

                <?php if ( $is_edit && ! empty( $photos ) ) : ?>
                    <div class="dpt-photos-manager">
                        <h4 class="dpt-photos-manager-title">
                            <?php esc_html_e( 'Manage Existing Album Photos', 'ifsedu-school-management' ); ?> (<?php echo count( $photos ); ?>)
                        </h4>
                        <div class="dpt-manage-grid">
                            <?php foreach ( $photos as $photo ) : 
                                $photo_internal_id = absint( $photo->id );
                                $base_admin_url    = admin_url( 'admin.php' );
                                $photo_del_url     = wp_nonce_url( 
                                    add_query_arg( array( 'page' => 'school_management_system', 'tab' => 'notice', 'type' => 'gallery', 'sub' => 'delete_photo', 'photo_id' => $photo_internal_id, 'album_id' => $album_id ), $base_admin_url ), 
                                    'delete_photo_' . $photo_internal_id 
                                );
                            ?>
                                <div class="dpt-manage-photo-card">
                                    <img src="<?php echo esc_url( $photo->image_url ); ?>" alt="<?php esc_attr_e( 'Gallery Image', 'ifsedu-school-management' ); ?>">
                                    <a href="<?php echo esc_url( $photo_del_url ); ?>" class="dpt-btn-photo-del" title="<?php esc_attr_e( 'Delete Photo', 'ifsedu-school-management' ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Remove this photo?', 'ifsedu-school-management' ) ); ?>');">
                                        &times;
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <button type="submit" name="educore_save_gallery" class="dpt-btn-submit">
                    <?php echo $is_edit ? esc_html__( 'Update Album', 'ifsedu-school-management' ) : esc_html__( 'Publish Album', 'ifsedu-school-management' ); ?>
                </button>
            </form>
        </div>

    </div>
    <?php
}

/**
 * Action Handler: Delete Individual Gallery Photo
 */
function educore_gallery_photo_delete_action() {
    global $wpdb;
    $table_photos = $wpdb->prefix . 'sms_gallery_photos';

    if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'edit_posts' ) ) {
        wp_die( esc_html__( 'Permission denied.', 'ifsedu-school-management' ) );
    }

    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $photo_id  = isset( $_GET['photo_id'] ) ? absint( wp_unslash( $_GET['photo_id'] ) ) : 0;
    $album_id  = isset( $_GET['album_id'] ) ? absint( wp_unslash( $_GET['album_id'] ) ) : 0;
    $del_nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    if ( $photo_id > 0 && wp_verify_nonce( $del_nonce, 'delete_photo_' . $photo_id ) ) {
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $wpdb->delete( $table_photos, array( 'id' => $photo_id ), array( '%d' ) );
        // phpcs:enable
        if ( function_exists( 'educore_log_activity' ) ) {
            /* translators: %d: Photo ID */
            educore_log_activity( sprintf( __( 'Deleted gallery photo ID #%d', 'ifsedu-school-management' ), $photo_id ) );
        }
    }

    $redirect_url = add_query_arg(
        array(
            'page' => 'school_management_system',
            'tab'  => 'notice',
            'type' => 'gallery',
            'sub'  => 'edit',
            'id'   => $album_id,
        ),
        admin_url( 'admin.php' )
    );

    if ( ! headers_sent() ) {
        wp_safe_redirect( $redirect_url );
        exit;
    } else {
        echo '<script type="text/javascript">window.location.href=' . wp_json_encode( esc_url_raw( $redirect_url ) ) . ';</script>';
        exit;
    }
}

/**
 * Action Handler: Delete Complete Photo Album & Associated Photos
 */
function educore_gallery_delete_action() {
    global $wpdb;
    $table_albums = $wpdb->prefix . 'sms_gallery_albums';
    $table_photos = $wpdb->prefix . 'sms_gallery_photos';

    if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'edit_posts' ) ) {
        wp_die( esc_html__( 'Permission denied.', 'ifsedu-school-management' ) );
    }

    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $album_id  = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0;
    $del_nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    if ( $album_id > 0 && wp_verify_nonce( $del_nonce, 'delete_gallery_' . $album_id ) ) {
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $album = $wpdb->get_row( $wpdb->prepare( "SELECT title FROM `{$table_albums}` WHERE id = %d LIMIT 1", $album_id ) );
        
        $wpdb->delete( $table_photos, array( 'album_id' => $album_id ), array( '%d' ) );
        $wpdb->delete( $table_albums, array( 'id' => $album_id ), array( '%d' ) );
        // phpcs:enable

        if ( $album && function_exists( 'educore_log_activity' ) ) {
            /* translators: %s: Album title */
            educore_log_activity( sprintf( __( 'Deleted photo album: %s', 'ifsedu-school-management' ), $album->title ) );
        }
    }

    $redirect_url = add_query_arg(
        array(
            'page' => 'school_management_system',
            'tab'  => 'notice',
            'type' => 'gallery',
            'sub'  => 'list',
        ),
        admin_url( 'admin.php' )
    );

    if ( ! headers_sent() ) {
        wp_safe_redirect( $redirect_url );
        exit;
    } else {
        echo '<script type="text/javascript">window.location.href=' . wp_json_encode( esc_url_raw( $redirect_url ) ) . ';</script>';
        exit;
    }
}
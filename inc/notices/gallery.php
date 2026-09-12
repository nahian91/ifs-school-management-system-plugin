<?php
/**
 * Institutional Photo Gallery & Album Management Suite
 * File: inc/notices/gallery.php
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Gallery Sub-Router
 *
 * @param string $sub_tab Current gallery sub-view action.
 */
function educore_gallery_router( $sub_tab ) {
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
 * Gallery Albums Directory Listing
 */
function educore_gallery_list_view() {
    global $wpdb;
    $table_albums = $wpdb->prefix . 'sms_gallery_albums';
    $table_photos = $wpdb->prefix . 'sms_gallery_photos';

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Permission denied.', 'ifsedu-school-management' ) );
    }

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $albums = $wpdb->get_results( "SELECT * FROM `{$table_albums}` ORDER BY id DESC" );
    // phpcs:enable
    $albums  = is_array( $albums ) ? $albums : array();
    $add_url = admin_url( 'admin.php?page=school_management_system&tab=notices&type=gallery&sub=add' );
    ?>

    <style id="ifs-educore-gallery-ui-styles">
        .ifs-educore-gallery-root {
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #0f172a;
        }

        .ifs-educore-header-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            padding: 20px 24px;
            border-radius: 16px;
            box-shadow: 0 4px 15px -3px rgba(0, 0, 0, 0.03);
            flex-wrap: wrap;
            gap: 16px;
        }

        .ifs-educore-page-title {
            margin: 0;
            font-size: 18px;
            font-weight: 800;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .ifs-educore-page-title .dashicons {
            color: #00523c;
            font-size: 22px;
            width: 22px;
            height: 22px;
        }

        .ifs-educore-btn-primary {
            background: #00523c;
            color: #ffffff;
            border: none;
            padding: 10px 20px;
            border-radius: 10px;
            font-size: 13.5px;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(0, 82, 60, 0.2);
            transition: all 0.2s ease;
        }

        .ifs-educore-btn-primary:hover {
            background: #004030;
            color: #ffffff;
            transform: translateY(-1px);
        }

        .ifs-educore-bento-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 24px;
        }

        .ifs-educore-album-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02);
        }

        .ifs-educore-album-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 25px -5px rgba(0, 82, 60, 0.08);
            border-color: #cbd5e1;
        }

        .ifs-educore-cover-container {
            position: relative;
            height: 180px;
            background: #f1f5f9;
            overflow: hidden;
        }

        .ifs-educore-cover-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.4s ease;
        }

        .ifs-educore-album-card:hover .ifs-educore-cover-img {
            transform: scale(1.05);
        }

        .ifs-educore-category-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            background: rgba(15, 23, 42, 0.75);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            color: #ffffff;
            font-size: 11px;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 20px;
            letter-spacing: 0.02em;
        }

        .ifs-educore-card-body {
            padding: 18px 20px 12px 20px;
            flex: 1;
        }

        .ifs-educore-album-title {
            margin: 0 0 6px 0;
            font-size: 15.5px;
            font-weight: 800;
            color: #0f172a;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .ifs-educore-photo-count {
            font-size: 12.5px;
            color: #64748b;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .ifs-educore-photo-count-icon {
            font-size: 15px;
            width: 15px;
            height: 15px;
            color: #00523c;
        }

        .ifs-educore-card-footer {
            padding: 14px 20px;
            border-top: 1px solid #f1f5f9;
            background: #f8fafc;
            display: flex;
            justify-content: flex-end;
            gap: 8px;
        }

        .ifs-educore-square-btn {
            width: 34px;
            height: 34px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: all 0.2s ease;
            border: 1px solid transparent;
        }

        .ifs-educore-square-btn .dashicons {
            font-size: 16px;
            width: 16px;
            height: 16px;
        }

        .ifs-educore-btn-view {
            background: #f0fdf4;
            color: #059669;
            border-color: #bbf7d0;
        }

        .ifs-educore-btn-view:hover {
            background: #059669;
            color: #fff;
        }

        .ifs-educore-btn-edit {
            background: #f0f9ff;
            color: #0284c7;
            border-color: #bae6fd;
        }

        .ifs-educore-btn-edit:hover {
            background: #0284c7;
            color: #fff;
        }

        .ifs-educore-btn-delete {
            background: #fef2f2;
            color: #dc2626;
            border-color: #fecaca;
        }

        .ifs-educore-btn-delete:hover {
            background: #dc2626;
            color: #fff;
        }

        .ifs-educore-empty-state {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 60px 20px;
            text-align: center;
            color: #64748b;
        }

        .ifs-educore-empty-state .dashicons {
            font-size: 48px;
            width: 48px;
            height: 48px;
            color: #cbd5e1;
            margin-bottom: 12px;
        }

        .ifs-educore-empty-state h5 {
            margin: 0;
            font-size: 15px;
            font-weight: 700;
            color: #334155;
        }
    </style>

    <div class="ifs-educore-gallery-root">
        <div class="ifs-educore-header-bar">
            <h2 class="ifs-educore-page-title">
                <span class="dashicons dashicons-format-gallery"></span> 
                <?php esc_html_e( 'Photo Albums Directory', 'ifsedu-school-management' ); ?>
            </h2>
            <a href="<?php echo esc_url( $add_url ); ?>" class="ifs-educore-btn-primary">
                <span class="dashicons dashicons-plus-alt2"></span>
                <?php esc_html_e( 'Create New Album', 'ifsedu-school-management' ); ?>
            </a>
        </div>

        <?php if ( ! empty( $albums ) ) : ?>
            <div class="ifs-educore-bento-grid">
                <?php foreach ( $albums as $album ) : 
                    $album_id   = absint( $album->id );
                    $view_url   = admin_url( 'admin.php?page=school_management_system&tab=notices&type=gallery&sub=view&id=' . $album_id );
                    $edit_url   = admin_url( 'admin.php?page=school_management_system&tab=notices&type=gallery&sub=edit&id=' . $album_id );
                    $delete_url = wp_nonce_url( admin_url( 'admin.php?page=school_management_system&tab=notices&type=gallery&sub=delete&id=' . $album_id ), 'delete_gallery_' . $album_id );
                    
                    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                    $photo_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM `{$table_photos}` WHERE album_id = %d", $album_id ) );
                    // phpcs:enable

                    $cover_src = ! empty( $album->cover_image ) ? $album->cover_image : EDUCORE_URL . 'assets/img/logo.png';
                ?>
                <div class="ifs-educore-album-card">
                    <div class="ifs-educore-cover-container">
                        <img src="<?php echo esc_url( $cover_src ); ?>" class="ifs-educore-cover-img" alt="<?php echo esc_attr( $album->title ); ?>">
                        <span class="ifs-educore-category-badge">
                            <?php echo esc_html( ! empty( $album->category ) ? $album->category : 'General' ); ?>
                        </span>
                    </div>

                    <div class="ifs-educore-card-body">
                        <h3 class="ifs-educore-album-title" title="<?php echo esc_attr( $album->title ); ?>"><?php echo esc_html( $album->title ); ?></h3>
                        <span class="ifs-educore-photo-count">
                            <span class="dashicons dashicons-images-alt2 ifs-educore-photo-count-icon"></span> 
                            <?php echo esc_html( $photo_count ); ?> <?php esc_html_e( 'Photos', 'ifsedu-school-management' ); ?>
                        </span>
                    </div>

                    <div class="ifs-educore-card-footer">
                        <a href="<?php echo esc_url( $view_url ); ?>" class="ifs-educore-square-btn ifs-educore-btn-view" title="<?php esc_attr_e( 'View Album', 'ifsedu-school-management' ); ?>">
                            <span class="dashicons dashicons-visibility"></span>
                        </a>
                        <a href="<?php echo esc_url( $edit_url ); ?>" class="ifs-educore-square-btn ifs-educore-btn-edit" title="<?php esc_attr_e( 'Edit Album', 'ifsedu-school-management' ); ?>">
                            <span class="dashicons dashicons-edit"></span>
                        </a>
                        <a href="<?php echo esc_url( $delete_url ); ?>" class="ifs-educore-square-btn ifs-educore-btn-delete" title="<?php esc_attr_e( 'Delete Album', 'ifsedu-school-management' ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this album and all its images?', 'ifsedu-school-management' ) ); ?>');">
                            <span class="dashicons dashicons-trash"></span>
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else : ?>
            <div class="ifs-educore-empty-state">
                <span class="dashicons dashicons-format-gallery"></span>
                <h5><?php esc_html_e( 'No photo albums created yet.', 'ifsedu-school-management' ); ?></h5>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Single Album Photo Grid View
 */
function educore_gallery_single_album_view() {
    global $wpdb;
    $table_albums = $wpdb->prefix . 'sms_gallery_albums';
    $table_photos = $wpdb->prefix . 'sms_gallery_photos';

    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $album_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $album = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table_albums}` WHERE id = %d", $album_id ) );
    // phpcs:enable

    if ( ! $album ) {
        ?>
        <style id="ifs-educore-single-gallery-error-styles">
            .ifs-educore-error-box {
                background: #fef2f2;
                border: 1px solid #fecaca;
                color: #dc2626;
                padding: 20px;
                border-radius: 12px;
                font-weight: 600;
                display: flex;
                align-items: center;
                gap: 10px;
                font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            }
        </style>
        <div class="ifs-educore-error-box">
            <span class="dashicons dashicons-dismiss"></span>
            <?php esc_html_e( 'Album not found or has been deleted.', 'ifsedu-school-management' ); ?>
        </div>
        <?php
        return;
    }

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $photos = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$table_photos}` WHERE album_id = %d ORDER BY id DESC", $album_id ) );
    // phpcs:enable
    $photos   = is_array( $photos ) ? $photos : array();
    $back_url = admin_url( 'admin.php?page=school_management_system&tab=notices&type=gallery&sub=list' );
    $edit_url = admin_url( 'admin.php?page=school_management_system&tab=notices&type=gallery&sub=edit&id=' . $album_id );
    ?>

    <style id="ifs-educore-single-gallery-styles">
        .ifs-educore-single-root {
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #0f172a;
        }

        .ifs-educore-top-action-bar {
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }

        .ifs-educore-btn-back, 
        .ifs-educore-btn-action {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
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

        .ifs-educore-btn-back:hover, 
        .ifs-educore-btn-action:hover {
            background: #f8fafc;
            color: #00523c;
            border-color: #cbd5e1;
        }

        .ifs-educore-album-detail-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 24px 28px;
            margin-bottom: 28px;
            box-shadow: 0 4px 15px -3px rgba(0,0,0,0.03);
        }

        .ifs-educore-album-header-title {
            margin: 0 0 8px 0;
            font-size: 20px;
            font-weight: 800;
            color: #0f172a;
        }

        .ifs-educore-album-meta-row {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            color: #64748b;
            margin-bottom: 12px;
        }

        .ifs-educore-album-desc-text {
            margin: 0;
            font-size: 14px;
            color: #334155;
            line-height: 1.5;
        }

        .ifs-educore-photo-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
        }

        .ifs-educore-photo-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            overflow: hidden;
            height: 160px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.02);
            transition: all 0.2s ease;
        }

        .ifs-educore-photo-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.06);
            border-color: #00523c;
        }

        .ifs-educore-photo-link img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .ifs-educore-photo-card:hover img {
            transform: scale(1.04);
        }

        .ifs-educore-empty-photos-box {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 50px;
            text-align: center;
            color: #64748b;
            font-weight: 600;
        }
    </style>

    <div class="ifs-educore-single-root">
        <div class="ifs-educore-top-action-bar">
            <a href="<?php echo esc_url( $back_url ); ?>" class="ifs-educore-btn-back">
                <span class="dashicons dashicons-arrow-left-alt"></span>
                <?php esc_html_e( 'Back to Album Directory', 'ifsedu-school-management' ); ?>
            </a>
            <a href="<?php echo esc_url( $edit_url ); ?>" class="ifs-educore-btn-action">
                <span class="dashicons dashicons-edit"></span>
                <?php esc_html_e( 'Edit / Upload More Photos', 'ifsedu-school-management' ); ?>
            </a>
        </div>

        <div class="ifs-educore-album-detail-card">
            <h3 class="ifs-educore-album-header-title"><?php echo esc_html( $album->title ); ?></h3>
            <div class="ifs-educore-album-meta-row">
                <span><strong>Category:</strong> <?php echo esc_html( $album->category ); ?></span>
                <span>•</span>
                <span><strong>Total Photos:</strong> <?php echo esc_html( count( $photos ) ); ?></span>
            </div>
            <?php if ( ! empty( $album->description ) ) : ?>
                <p class="ifs-educore-album-desc-text"><?php echo esc_html( $album->description ); ?></p>
            <?php endif; ?>
        </div>

        <?php if ( ! empty( $photos ) ) : ?>
            <div class="ifs-educore-photo-grid">
                <?php foreach ( $photos as $photo ) : ?>
                    <div class="ifs-educore-photo-card">
                        <a href="<?php echo esc_url( $photo->image_url ); ?>" target="_blank" class="ifs-educore-photo-link">
                            <img src="<?php echo esc_url( $photo->image_url ); ?>" alt="Gallery Photo">
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else : ?>
            <div class="ifs-educore-empty-photos-box">
                <?php esc_html_e( 'This album contains no photos yet.', 'ifsedu-school-management' ); ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Album Create & Edit Form View (WordPress Media Library Enabled)
 */
function educore_gallery_add_edit_view() {
    global $wpdb;
    $table_albums = $wpdb->prefix . 'sms_gallery_albums';
    $table_photos = $wpdb->prefix . 'sms_gallery_photos';

    // Enqueue WordPress Media Library uploader.
    wp_enqueue_media();

    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $is_edit  = isset( $_GET['sub'] ) && 'edit' === $_GET['sub'];
    $album_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    $album         = null;
    $photos        = array();
    $saved_message = false;

    if ( $is_edit && 0 < $album_id ) {
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $album  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table_albums}` WHERE id = %d", $album_id ) );
        $photos = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$table_photos}` WHERE album_id = %d ORDER BY id DESC", $album_id ) );
        // phpcs:enable
        $photos = is_array( $photos ) ? $photos : array();
    }

    $gallery_nonce = isset( $_POST['educore_gallery_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['educore_gallery_nonce'] ) ) : '';
    if ( isset( $_POST['educore_save_gallery'] ) && wp_verify_nonce( $gallery_nonce, 'save_gallery_action' ) ) {
        $cover_image       = isset( $_POST['cover_image'] ) ? esc_url_raw( wp_unslash( $_POST['cover_image'] ) ) : ( $album->cover_image ?? '' );
        $album_title       = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
        $album_category    = isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : 'General';
        $album_description = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';
        $album_status      = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'Published';

        $album_data = array(
            'title'       => $album_title,
            'category'    => $album_category,
            'description' => $album_description,
            'cover_image' => $cover_image,
            'status'      => $album_status,
        );

        if ( $is_edit && 0 < $album_id ) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->update( $table_albums, $album_data, array( 'id' => $album_id ) );
            // phpcs:enable
            $current_id = $album_id;
        } else {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
            $wpdb->insert( $table_albums, $album_data );
            // phpcs:enable
            $current_id = (int) $wpdb->insert_id;
        }

        // Handle JSON array of selected photo URLs from WP Media Library.
        if ( ! empty( $_POST['gallery_photo_urls'] ) && 0 < $current_id ) {
            $raw_urls   = sanitize_text_field( wp_unslash( $_POST['gallery_photo_urls'] ) );
            $photo_urls = json_decode( stripslashes( $raw_urls ), true );

            if ( is_array( $photo_urls ) && ! empty( $photo_urls ) ) {
                foreach ( $photo_urls as $url ) {
                    $clean_url = esc_url_raw( trim( $url ) );
                    if ( ! empty( $clean_url ) ) {
                        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
                        $wpdb->insert( $table_photos, array( 'album_id' => $current_id, 'image_url' => $clean_url ) );
                        // phpcs:enable

                        if ( empty( $cover_image ) ) {
                            $cover_image = $clean_url;
                            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                            $wpdb->update( $table_albums, array( 'cover_image' => $cover_image ), array( 'id' => $current_id ) );
                            // phpcs:enable
                        }
                    }
                }
            }
        }

        $saved_message = true;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $album  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table_albums}` WHERE id = %d", $current_id ) );
        $photos = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$table_photos}` WHERE album_id = %d ORDER BY id DESC", $current_id ) );
        // phpcs:enable
        $photos = is_array( $photos ) ? $photos : array();
    }

    $back_url = admin_url( 'admin.php?page=school_management_system&tab=notices&type=gallery&sub=list' );
    ?>

    <style id="ifs-educore-form-gallery-styles">
        .ifs-educore-form-root {
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #0f172a;
        }

        .ifs-educore-top-action-bar {
            margin-bottom: 24px;
        }

        .ifs-educore-btn-back {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
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

        .ifs-educore-alert-success-box {
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            color: #047857;
            padding: 14px 18px;
            border-radius: 10px;
            font-size: 13.5px;
            font-weight: 600;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .ifs-educore-bento-form-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.04), 0 8px 10px -6px rgba(0, 0, 0, 0.04);
        }

        .ifs-educore-form-title {
            margin: 0 0 24px 0;
            font-size: 17px;
            font-weight: 800;
            color: #0f172a;
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 16px;
        }

        .ifs-educore-form-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }

        .ifs-educore-form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-bottom: 20px;
        }

        .ifs-educore-form-group label {
            font-size: 13px;
            font-weight: 700;
            color: #334155;
        }

        .ifs-educore-field-input, 
        .ifs-educore-field-select,
        .ifs-educore-field-textarea {
            width: 100%;
            padding: 10px 14px;
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            font-size: 14px;
            color: #0f172a;
            box-sizing: border-box;
            outline: none;
            transition: all 0.2s ease;
        }

        .ifs-educore-field-input:focus, 
        .ifs-educore-field-select:focus,
        .ifs-educore-field-textarea:focus {
            border-color: #00523c;
            background: #ffffff;
            box-shadow: 0 0 0 3px rgba(0, 82, 60, 0.12);
        }

        .ifs-educore-btn-submit {
            background: #00523c;
            color: #ffffff;
            border: none;
            padding: 12px 24px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0, 82, 60, 0.25);
            transition: background 0.2s ease;
        }

        .ifs-educore-btn-submit:hover {
            background: #004030;
        }

        .ifs-educore-cover-flex {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-top: 6px;
        }

        .ifs-educore-cover-preview-wrap {
            margin-top: 10px;
        }

        .ifs-educore-cover-preview-img {
            max-height: 100px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
        }

        .ifs-educore-upload-bento-node {
            background: #f8fafc;
            border: 2px dashed #cbd5e1;
            padding: 24px;
            border-radius: 12px;
            text-align: center;
            margin-top: 24px;
        }

        .ifs-educore-upload-bento-label {
            font-weight: 700;
            color: #0f172a;
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .ifs-educore-upload-hint {
            margin-top: 8px;
            font-size: 12.5px;
            color: #64748b;
        }

        .ifs-educore-selected-previews {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 16px;
            justify-content: center;
        }

        .ifs-educore-preview-chip {
            width: 70px;
            height: 70px;
            border-radius: 8px;
            overflow: hidden;
            border: 2px solid #00523c;
            position: relative;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
        }

        .ifs-educore-preview-chip img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .ifs-educore-photos-manage-section {
            margin-top: 28px;
        }

        .ifs-educore-manage-title {
            font-size: 14.5px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 12px;
        }

        .ifs-educore-manage-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
            gap: 12px;
        }

        .ifs-educore-manage-photo-card {
            position: relative;
            height: 110px;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #cbd5e1;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }

        .ifs-educore-manage-photo-card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .ifs-educore-btn-photo-del {
            position: absolute;
            top: 6px;
            right: 6px;
            background: rgba(220,38,38,0.9);
            color: #fff;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            font-weight: bold;
            font-size: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        .ifs-educore-submit-row {
            margin-top: 32px;
            border-top: 1px solid #f1f5f9;
            padding-top: 24px;
        }

        @media screen and (max-width: 900px) {
            .ifs-educore-form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="ifs-educore-form-root">
        <div class="ifs-educore-top-action-bar">
            <a href="<?php echo esc_url( $back_url ); ?>" class="ifs-educore-btn-back">
                <span class="dashicons dashicons-arrow-left-alt"></span>
                <?php esc_html_e( 'Back to Gallery', 'ifsedu-school-management' ); ?>
            </a>
        </div>

        <?php if ( $saved_message ) : ?>
            <div class="ifs-educore-alert-success-box">
                <span class="dashicons dashicons-yes-alt"></span>
                <?php esc_html_e( 'Album saved successfully.', 'ifsedu-school-management' ); ?>
            </div>
        <?php endif; ?>

        <div class="ifs-educore-bento-form-card">
            <h3 class="ifs-educore-form-title">
                <?php echo $is_edit ? esc_html__( 'Edit Album Details', 'ifsedu-school-management' ) : esc_html__( 'Create Photo Album', 'ifsedu-school-management' ); ?>
            </h3>

            <form method="POST" action="">
                <?php wp_nonce_field( 'save_gallery_action', 'educore_gallery_nonce' ); ?>

                <div class="ifs-educore-form-row">
                    <div class="ifs-educore-form-group" style="margin-bottom:0;">
                        <label><?php esc_html_e( 'Album Title', 'ifsedu-school-management' ); ?> *</label>
                        <input type="text" name="title" class="ifs-educore-field-input" value="<?php echo $album ? esc_attr( $album->title ) : ''; ?>" required>
                    </div>
                    <div class="ifs-educore-form-group" style="margin-bottom:0;">
                        <label><?php esc_html_e( 'Category', 'ifsedu-school-management' ); ?></label>
                        <select name="category" class="ifs-educore-field-select">
                            <option value="Academic" <?php selected( $album ? $album->category : '', 'Academic' ); ?>>Academic</option>
                            <option value="Sports" <?php selected( $album ? $album->category : '', 'Sports' ); ?>>Sports</option>
                            <option value="Cultural" <?php selected( $album ? $album->category : '', 'Cultural' ); ?>>Cultural</option>
                            <option value="Campus" <?php selected( $album ? $album->category : '', 'Campus' ); ?>>Campus & Infrastructure</option>
                            <option value="General" <?php selected( $album ? $album->category : '', 'General' ); ?>>General</option>
                        </select>
                    </div>
                    <div class="ifs-educore-form-group" style="margin-bottom:0;">
                        <label><?php esc_html_e( 'Status', 'ifsedu-school-management' ); ?></label>
                        <select name="status" class="ifs-educore-field-select">
                            <option value="Published" <?php selected( $album ? $album->status : '', 'Published' ); ?>>Published</option>
                            <option value="Draft" <?php selected( $album ? $album->status : '', 'Draft' ); ?>>Draft</option>
                        </select>
                    </div>
                </div>

                <div class="ifs-educore-form-group">
                    <label><?php esc_html_e( 'Description', 'ifsedu-school-management' ); ?></label>
                    <textarea name="description" class="ifs-educore-field-textarea" rows="3"><?php echo $album ? esc_textarea( $album->description ) : ''; ?></textarea>
                </div>

                <!-- Cover Image Media Uploader -->
                <div class="ifs-educore-form-group">
                    <label><?php esc_html_e( 'Cover Image (Thumbnail)', 'ifsedu-school-management' ); ?></label>
                    <div class="ifs-educore-cover-flex">
                        <input type="text" name="cover_image" id="ifs_cover_image_input" class="ifs-educore-field-input" value="<?php echo $album ? esc_attr( $album->cover_image ) : ''; ?>" readonly style="flex: 1;">
                        <button type="button" class="ifs-educore-btn-primary" id="ifs_upload_cover_btn" style="padding: 10px 16px; font-size: 13px; box-shadow: none;"><?php esc_html_e( 'Choose Image', 'ifsedu-school-management' ); ?></button>
                    </div>
                    <?php if ( $album && ! empty( $album->cover_image ) ) : ?>
                        <div class="ifs-educore-cover-preview-wrap">
                            <img src="<?php echo esc_url( $album->cover_image ); ?>" class="ifs-educore-cover-preview-img" alt="Cover Preview">
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Multi-Photo WP Media Uploader Node -->
                <div class="ifs-educore-upload-bento-node">
                    <label class="ifs-educore-upload-bento-label"><?php esc_html_e( 'Upload Multiple Photos to Album', 'ifsedu-school-management' ); ?></label>
                    <input type="hidden" name="gallery_photo_urls" id="ifs_gallery_photo_urls_input" value="">
                    <button type="button" class="ifs-educore-btn-primary" id="ifs_upload_multi_photos_btn" style="box-shadow: none;">
                        <span class="dashicons dashicons-images-alt2" style="vertical-align: middle; margin-top: 3px;"></span> 
                        <?php esc_html_e( 'Select Multiple Photos from Media Library', 'ifsedu-school-management' ); ?>
                    </button>
                    <p class="ifs-educore-upload-hint">
                        <?php esc_html_e( 'Click to open WordPress Media Library. Hold Ctrl/Cmd to select multiple images simultaneously.', 'ifsedu-school-management' ); ?>
                    </p>
                    <div id="ifs_selected_previews_container" class="ifs-educore-selected-previews"></div>
                </div>

                <?php if ( $is_edit && ! empty( $photos ) ) : ?>
                    <div class="ifs-educore-photos-manage-section">
                        <h4 class="ifs-educore-manage-title">
                            <?php esc_html_e( 'Manage Existing Album Photos', 'ifsedu-school-management' ); ?> (<?php echo esc_html( count( $photos ) ); ?>)
                        </h4>
                        <div class="ifs-educore-manage-grid">
                            <?php foreach ( $photos as $photo ) : 
                                $photo_del_url = wp_nonce_url( 
                                    admin_url( 'admin.php?page=school_management_system&tab=notices&type=gallery&sub=delete_photo&photo_id=' . $photo->id . '&album_id=' . $album_id ), 
                                    'delete_photo_' . $photo->id 
                                );
                            ?>
                                <div class="ifs-educore-manage-photo-card">
                                    <img src="<?php echo esc_url( $photo->image_url ); ?>" alt="Gallery Image">
                                    <a href="<?php echo esc_url( $photo_del_url ); ?>" class="ifs-educore-btn-photo-del" title="<?php esc_attr_e( 'Delete Photo', 'ifsedu-school-management' ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Remove this photo?', 'ifsedu-school-management' ) ); ?>');">
                                        &times;
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="ifs-educore-submit-row">
                    <button type="submit" name="educore_save_gallery" class="ifs-educore-btn-submit">
                        <span class="dashicons dashicons-saved" style="vertical-align: middle; margin-top: 3px;"></span>
                        <?php echo $is_edit ? esc_html__( 'Update Album', 'ifsedu-school-management' ) : esc_html__( 'Publish Album', 'ifsedu-school-management' ); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- WordPress Media Library Integration Script -->
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        // Cover Image Uploader.
        var coverFrame;
        $('#ifs_upload_cover_btn').on('click', function(e) {
            e.preventDefault();
            if (coverFrame) {
                coverFrame.open();
                return;
            }
            coverFrame = wp.media({
                title: '<?php echo esc_js( __( 'Select Cover Image', 'ifsedu-school-management' ) ); ?>',
                button: { text: '<?php echo esc_js( __( 'Use as Cover', 'ifsedu-school-management' ) ); ?>' },
                multiple: false
            });
            coverFrame.on('select', function() {
                var attachment = coverFrame.state().get('selection').first().toJSON();
                $('#ifs_cover_image_input').val(attachment.url);
            });
            coverFrame.open();
        });

        // Multi-Photo Selector Uploader.
        var multiFrame;
        var selectedUrls = [];
        $('#ifs_upload_multi_photos_btn').on('click', function(e) {
            e.preventDefault();
            if (multiFrame) {
                multiFrame.open();
                return;
            }
            multiFrame = wp.media({
                title: '<?php echo esc_js( __( 'Select Gallery Photos', 'ifsedu-school-management' ) ); ?>',
                button: { text: '<?php echo esc_js( __( 'Add Selected Photos', 'ifsedu-school-management' ) ); ?>' },
                multiple: true
            });

            multiFrame.on('select', function() {
                var selection = multiFrame.state().get('selection');
                selection.each(function(attachment) {
                    var url = attachment.toJSON().url;
                    if (selectedUrls.indexOf(url) === -1) {
                        selectedUrls.push(url);
                        $('#ifs_selected_previews_container').append(
                            '<div class="ifs-educore-preview-chip">' +
                            '<img src="' + url + '">' +
                            '</div>'
                        );
                    }
                });
                $('#ifs_gallery_photo_urls_input').val(JSON.stringify(selectedUrls));
            });
            multiFrame.open();
        });
    });
    </script>
    <?php
}

/**
 * Gallery Photo Single Deletion
 */
function educore_gallery_photo_delete_action() {
    global $wpdb;
    $table_photos = $wpdb->prefix . 'sms_gallery_photos';

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Permission denied.', 'ifsedu-school-management' ) );
    }

    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $photo_id  = isset( $_GET['photo_id'] ) ? absint( $_GET['photo_id'] ) : 0;
    $album_id  = isset( $_GET['album_id'] ) ? absint( $_GET['album_id'] ) : 0;
    $del_nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    if ( 0 < $photo_id && wp_verify_nonce( $del_nonce, 'delete_photo_' . $photo_id ) ) {
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->delete( $table_photos, array( 'id' => $photo_id ), array( '%d' ) );
        // phpcs:enable
    }

    $redirect_url = admin_url( 'admin.php?page=school_management_system&tab=notices&type=gallery&sub=edit&id=' . $album_id );
    educore_safe_redirect( $redirect_url );
}

/**
 * Gallery Album Complete Deletion
 */
function educore_gallery_delete_action() {
    global $wpdb;
    $table_albums = $wpdb->prefix . 'sms_gallery_albums';
    $table_photos = $wpdb->prefix . 'sms_gallery_photos';

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Permission denied.', 'ifsedu-school-management' ) );
    }

    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $album_id  = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
    $del_nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    if ( 0 < $album_id && wp_verify_nonce( $del_nonce, 'delete_gallery_' . $album_id ) ) {
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->delete( $table_photos, array( 'album_id' => $album_id ), array( '%d' ) );
        $wpdb->delete( $table_albums, array( 'id' => $album_id ), array( '%d' ) );
        // phpcs:enable
    }

    $redirect_url = admin_url( 'admin.php?page=school_management_system&tab=notices&type=gallery&sub=list' );
    educore_safe_redirect( $redirect_url );
}
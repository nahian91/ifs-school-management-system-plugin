<?php
/**
 * Institutional Photo Gallery & Album Management Suite
 * File: inc/notices/gallery.php
 * Text Domain: ifsedu-school-management
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

if ( ! function_exists( 'educore_gallery_get_table' ) ) {
    /**
     * Helper: Defensive table name resolver for gallery module
     */
    function educore_gallery_get_table( string $key ): string {
        global $wpdb;
        if ( function_exists( 'educore_get_table_name' ) ) {
            $tbl = educore_get_table_name( $key );
            if ( ! empty( $tbl ) ) {
                return $tbl;
            }
        }
        return $wpdb->prefix . 'sms_' . $key;
    }
}

/**
 * Gallery Sub-Router
 *
 * @param string $sub_tab Current gallery sub-view action.
 */
function educore_gallery_router( $sub_tab ): void {
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
 * Gallery Albums Directory Listing with Quick-Create Modal
 */
function educore_gallery_list_view(): void {
    global $wpdb;
    $table_albums = educore_gallery_get_table( 'gallery_albums' );
    $table_photos = educore_gallery_get_table( 'gallery_photos' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Permission denied.', 'ifsedu-school-management' ), 403 );
    }

    if ( function_exists( 'wp_enqueue_media' ) ) {
        wp_enqueue_media();
    }

    // Handle Quick Album Creation Modal POST
    $saved_message = false;
    $gallery_nonce = isset( $_POST['educore_gallery_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['educore_gallery_nonce'] ) ) : '';
    if ( isset( $_POST['educore_quick_create_album'] ) && wp_verify_nonce( $gallery_nonce, 'quick_create_album_action' ) ) {
        $cover_image       = isset( $_POST['cover_image'] ) ? esc_url_raw( wp_unslash( $_POST['cover_image'] ) ) : '';
        $album_title       = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
        $album_category    = isset( $_POST['category'] ) ? sanitize_text_field( wp_unslash( $_POST['category'] ) ) : 'General';
        $album_description = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';
        $album_status      = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'Published';

        if ( ! empty( $album_title ) ) {
            $wpdb->insert(
                $table_albums,
                array(
                    'title'       => $album_title,
                    'category'    => $album_category,
                    'description' => $album_description,
                    'cover_image' => $cover_image,
                    'status'      => $album_status,
                )
            );
            $saved_message = true;
        }
    }

    $albums = $wpdb->get_results(
        $wpdb->prepare(
            'SELECT * FROM %i ORDER BY id DESC',
            $table_albums
        )
    );
    $albums = is_array( $albums ) ? $albums : array();
    ?>

    <style id="ifs-educore-gallery-ui-styles">
        .ifs-educore-gallery-root {
            width: 100%;
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #0f172a;
            box-sizing: border-box;
        }

        .ifs-educore-header-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            padding: 18px 24px;
            border-radius: 14px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.02);
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
            border-radius: 9px;
            font-size: 13.5px;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(0, 82, 60, 0.18);
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .ifs-educore-btn-primary:hover {
            background: #047857;
            color: #ffffff;
            transform: translateY(-1px);
        }

        .ifs-educore-btn-secondary {
            background: #f1f5f9;
            color: #334155;
            border: 1.5px solid #cbd5e1;
            padding: 9px 18px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .ifs-educore-btn-secondary:hover {
            background: #e2e8f0;
            color: #0f172a;
        }

        .ifs-educore-alert-success {
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            color: #047857;
            padding: 12px 18px;
            border-radius: 9px;
            font-size: 13.5px;
            font-weight: 700;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .ifs-educore-bento-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(290px, 1fr));
            gap: 20px;
        }

        .ifs-educore-album-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 2px 6px rgba(0,0,0,0.02);
        }

        .ifs-educore-album-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 25px -5px rgba(0, 0, 0, 0.08);
            border-color: #cbd5e1;
        }

        .ifs-educore-cover-container {
            position: relative;
            height: 185px;
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
            padding: 3px 10px;
            border-radius: 20px;
            letter-spacing: 0.02em;
        }

        .ifs-educore-card-body {
            padding: 16px 18px 12px 18px;
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
            font-size: 12px;
            color: #64748b;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .ifs-educore-photo-count-icon {
            font-size: 14px;
            width: 14px;
            height: 14px;
            color: #00523c;
        }

        .ifs-educore-card-footer {
            padding: 12px 18px;
            border-top: 1px solid #f1f5f9;
            background: #f8fafc;
            display: flex;
            justify-content: flex-end;
            gap: 8px;
        }

        .ifs-educore-square-btn {
            width: 32px;
            height: 32px;
            border-radius: 7px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: all 0.2s ease;
            border: 1px solid transparent;
        }

        .ifs-educore-square-btn .dashicons {
            font-size: 15px;
            width: 15px;
            height: 15px;
        }

        .ifs-educore-btn-view {
            background: #ecfdf5;
            color: #047857;
            border-color: #a7f3d0;
        }

        .ifs-educore-btn-view:hover {
            background: #047857;
            color: #ffffff;
        }

        .ifs-educore-btn-edit {
            background: #eff6ff;
            color: #1d4ed8;
            border-color: #bfdbfe;
        }

        .ifs-educore-btn-edit:hover {
            background: #1d4ed8;
            color: #ffffff;
        }

        .ifs-educore-btn-delete {
            background: #fef2f2;
            color: #dc2626;
            border-color: #fecaca;
        }

        .ifs-educore-btn-delete:hover {
            background: #dc2626;
            color: #ffffff;
        }

        .ifs-educore-empty-state {
            background: #ffffff;
            border: 1px dashed #cbd5e1;
            border-radius: 14px;
            padding: 60px 20px;
            text-align: center;
            color: #64748b;
        }

        .ifs-educore-empty-state .dashicons {
            font-size: 48px;
            width: 48px;
            height: 48px;
            color: #94a3b8;
            margin-bottom: 12px;
        }

        .ifs-educore-empty-state h5 {
            margin: 0;
            font-size: 15px;
            font-weight: 700;
            color: #334155;
        }

        /* -------------------------------------------------------------
           NEO-BENTO MODAL DIALOG STYLES
           ------------------------------------------------------------- */
        .ifs-educore-modal-dialog {
            border: none;
            border-radius: 16px;
            padding: 0;
            margin: auto;
            background: transparent;
            max-width: 540px;
            width: 92%;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            opacity: 0;
            transform: scale(0.95);
            transition: opacity 0.2s ease, transform 0.2s ease, overlay 0.2s allow-discrete, display 0.2s allow-discrete;
        }

        .ifs-educore-modal-dialog[open] {
            opacity: 1;
            transform: scale(1);
        }

        @starting-style {
            .ifs-educore-modal-dialog[open] {
                opacity: 0;
                transform: scale(0.95);
            }
        }

        .ifs-educore-modal-dialog::backdrop {
            background: rgba(15, 23, 42, 0.5);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            opacity: 0;
            transition: opacity 0.2s ease, overlay 0.2s allow-discrete, display 0.2s allow-discrete;
        }

        .ifs-educore-modal-dialog[open]::backdrop {
            opacity: 1;
        }

        @starting-style {
            .ifs-educore-modal-dialog[open]::backdrop {
                opacity: 0;
            }
        }

        .ifs-educore-modal-content {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            display: flex;
            flex-direction: column;
            color: #0f172a;
            font-family: inherit;
        }

        .ifs-educore-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 20px;
            border-bottom: 1px solid #f1f5f9;
        }

        .ifs-educore-modal-title {
            margin: 0;
            font-size: 16px;
            font-weight: 800;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .ifs-educore-modal-title .dashicons {
            color: #00523c;
            font-size: 18px;
            width: 18px;
            height: 18px;
        }

        .ifs-educore-modal-close {
            background: transparent;
            border: none;
            font-size: 22px;
            line-height: 1;
            color: #64748b;
            cursor: pointer;
            border-radius: 6px;
            padding: 2px 6px;
            transition: color 0.15s ease, background 0.15s ease;
        }

        .ifs-educore-modal-close:hover {
            color: #0f172a;
            background: #f1f5f9;
        }

        .ifs-educore-modal-body {
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .ifs-educore-modal-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .ifs-educore-modal-group label {
            font-size: 11.5px;
            font-weight: 700;
            color: #475569;
            text-transform: capitalize;
            letter-spacing: 0.3px;
        }

        .ifs-educore-modal-input,
        .ifs-educore-modal-select,
        .ifs-educore-modal-textarea {
            width: 100%;
            height: 40px;
            padding: 0 12px;
            background: #ffffff;
            border: 1.5px solid #cbd5e1;
            border-radius: 8px;
            font-size: 13.5px;
            font-weight: 600;
            color: #0f172a;
            box-sizing: border-box;
            outline: none;
            transition: all 0.2s ease;
        }

        .ifs-educore-modal-textarea {
            height: auto;
            padding: 8px 12px;
            line-height: 1.5;
        }

        .ifs-educore-modal-input:focus,
        .ifs-educore-modal-select:focus,
        .ifs-educore-modal-textarea:focus {
            border-color: #00523c;
            box-shadow: 0 0 0 3px rgba(0, 82, 60, 0.12);
        }

        .ifs-educore-modal-footer {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 10px;
            padding: 14px 20px;
            background: #f8fafc;
            border-top: 1px solid #f1f5f9;
        }
    </style>

    <div class="ifs-educore-gallery-root">
        <?php if ( $saved_message ) : ?>
            <div class="ifs-educore-alert-success">
                <span class="dashicons dashicons-yes-alt"></span>
                <?php esc_html_e( 'New album created successfully.', 'ifsedu-school-management' ); ?>
            </div>
        <?php endif; ?>

        <div class="ifs-educore-header-bar">
            <h2 class="ifs-educore-page-title">
                <span class="dashicons dashicons-format-gallery"></span> 
                <?php esc_html_e( 'Photo Albums Directory', 'ifsedu-school-management' ); ?>
            </h2>
            <div style="display:flex; gap:10px; align-items:center;">
                <button type="button" class="ifs-educore-btn-primary" id="ifsOpenCreateAlbumModalBtn">
                    <span class="dashicons dashicons-plus-alt2"></span>
                    <?php esc_html_e( 'Create New Album', 'ifsedu-school-management' ); ?>
                </button>
            </div>
        </div>

        <?php if ( ! empty( $albums ) ) : ?>
            <div class="ifs-educore-bento-grid">
                <?php foreach ( $albums as $album ) : 
                    $album_id   = absint( $album->id );
                    $view_url   = admin_url( 'admin.php?page=school_management_system&tab=notices&type=gallery&sub=view&id=' . $album_id );
                    $edit_url   = admin_url( 'admin.php?page=school_management_system&tab=notices&type=gallery&sub=edit&id=' . $album_id );
                    $delete_url = wp_nonce_url( admin_url( 'admin.php?page=school_management_system&tab=notices&type=gallery&sub=delete&id=' . $album_id ), 'delete_gallery_' . $album_id );
                    
                    $photo_count = (int) $wpdb->get_var(
                        $wpdb->prepare(
                            'SELECT COUNT(id) FROM %i WHERE album_id = %d',
                            $table_photos,
                            $album_id
                        )
                    );

                    $cover_src = ! empty( $album->cover_image ) ? (string) $album->cover_image : ( defined( 'EDUCORE_URL' ) ? EDUCORE_URL . 'assets/img/logo.png' : '' );
                ?>
                <div class="ifs-educore-album-card">
                    <div class="ifs-educore-cover-container">
                        <?php if ( ! empty( $cover_src ) ) : ?>
                            <img src="<?php echo esc_url( $cover_src ); ?>" class="ifs-educore-cover-img" alt="<?php echo esc_attr( (string) $album->title ); ?>">
                        <?php endif; ?>
                        <span class="ifs-educore-category-badge">
                            <?php echo esc_html( ! empty( $album->category ) ? (string) $album->category : 'General' ); ?>
                        </span>
                    </div>

                    <div class="ifs-educore-card-body">
                        <h3 class="ifs-educore-album-title" title="<?php echo esc_attr( (string) $album->title ); ?>"><?php echo esc_html( (string) $album->title ); ?></h3>
                        <span class="ifs-educore-photo-count">
                            <span class="dashicons dashicons-images-alt2 ifs-educore-photo-count-icon"></span> 
                            <?php echo esc_html( (string) $photo_count ); ?> <?php esc_html_e( 'Photos', 'ifsedu-school-management' ); ?>
                        </span>
                    </div>

                    <div class="ifs-educore-card-footer">
                        <a href="<?php echo esc_url( $view_url ); ?>" class="ifs-educore-square-btn ifs-educore-btn-view" title="<?php esc_attr_e( 'View Album', 'ifsedu-school-management' ); ?>">
                            <span class="dashicons dashicons-visibility"></span>
                        </a>
                        <a href="<?php echo esc_url( $edit_url ); ?>" class="ifs-educore-square-btn ifs-educore-btn-edit" title="<?php esc_attr_e( 'Edit Album & Photos', 'ifsedu-school-management' ); ?>">
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

        <!-- Quick Create Album Modal -->
        <dialog id="ifsCreateAlbumModal" class="ifs-educore-modal-dialog">
            <div class="ifs-educore-modal-content">
                <div class="ifs-educore-modal-header">
                    <h3 class="ifs-educore-modal-title">
                        <span class="dashicons dashicons-format-gallery"></span>
                        <?php esc_html_e( 'Quick Create Album', 'ifsedu-school-management' ); ?>
                    </h3>
                    <button type="button" class="ifs-educore-modal-close" id="ifsCloseCreateModalBtn">&times;</button>
                </div>

                <form method="POST" action="">
                    <?php wp_nonce_field( 'quick_create_album_action', 'educore_gallery_nonce' ); ?>
                    
                    <div class="ifs-educore-modal-body">
                        <div class="ifs-educore-modal-group">
                            <label for="modal_album_title"><?php esc_html_e( 'Album Title', 'ifsedu-school-management' ); ?> <span style="color:#dc2626;">*</span></label>
                            <input type="text" name="title" id="modal_album_title" class="ifs-educore-modal-input" placeholder="e.g. Annual Sports Meet 2026" required>
                        </div>

                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px;">
                            <div class="ifs-educore-modal-group">
                                <label for="modal_album_category"><?php esc_html_e( 'Category', 'ifsedu-school-management' ); ?></label>
                                <select name="category" id="modal_album_category" class="ifs-educore-modal-select">
                                    <option value="Academic">Academic</option>
                                    <option value="Sports">Sports</option>
                                    <option value="Cultural">Cultural</option>
                                    <option value="Campus">Campus & Infrastructure</option>
                                    <option value="General" selected>General</option>
                                </select>
                            </div>
                            <div class="ifs-educore-modal-group">
                                <label for="modal_album_status"><?php esc_html_e( 'Status', 'ifsedu-school-management' ); ?></label>
                                <select name="status" id="modal_album_status" class="ifs-educore-modal-select">
                                    <option value="Published" selected>Published</option>
                                    <option value="Draft">Draft</option>
                                </select>
                            </div>
                        </div>

                        <div class="ifs-educore-modal-group">
                            <label for="modal_album_desc"><?php esc_html_e( 'Description (Optional)', 'ifsedu-school-management' ); ?></label>
                            <textarea name="description" id="modal_album_desc" class="ifs-educore-modal-textarea" rows="2" placeholder="Brief notes about the event..."></textarea>
                        </div>

                        <div class="ifs-educore-modal-group">
                            <label><?php esc_html_e( 'Cover Image', 'ifsedu-school-management' ); ?></label>
                            <div style="display:flex; gap:8px; align-items:center;">
                                <input type="text" name="cover_image" id="modal_cover_image_input" class="ifs-educore-modal-input" placeholder="https://..." readonly style="flex:1;">
                                <button type="button" class="ifs-educore-btn-secondary" id="modal_upload_cover_btn">
                                    <?php esc_html_e( 'Choose', 'ifsedu-school-management' ); ?>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="ifs-educore-modal-footer">
                        <button type="button" class="ifs-educore-btn-secondary" id="ifsCancelCreateModalBtn"><?php esc_html_e( 'Cancel', 'ifsedu-school-management' ); ?></button>
                        <button type="submit" name="educore_quick_create_album" class="ifs-educore-btn-primary"><?php esc_html_e( 'Create Album', 'ifsedu-school-management' ); ?></button>
                    </div>
                </form>
            </div>
        </dialog>
    </div>

    <script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function() {
        var modal = document.getElementById('ifsCreateAlbumModal');
        var openBtn = document.getElementById('ifsOpenCreateAlbumModalBtn');
        var closeBtn = document.getElementById('ifsCloseCreateModalBtn');
        var cancelBtn = document.getElementById('ifsCancelCreateModalBtn');

        if (modal && openBtn) {
            openBtn.addEventListener('click', function() {
                modal.showModal();
            });

            var closeModal = function() { modal.close(); };
            if (closeBtn) closeBtn.addEventListener('click', closeModal);
            if (cancelBtn) cancelBtn.addEventListener('click', closeModal);

            modal.addEventListener('click', function(e) {
                var rect = modal.getBoundingClientRect();
                var isInDialog = (
                    rect.top <= e.clientY &&
                    e.clientY <= rect.top + rect.height &&
                    rect.left <= e.clientX &&
                    e.clientX <= rect.left + rect.width
                );
                if (!isInDialog) {
                    modal.close();
                }
            });
        }

        // Modal Cover Image WordPress Media Library Picker
        var coverFrame;
        var uploadBtn = document.getElementById('modal_upload_cover_btn');
        if (uploadBtn) {
            uploadBtn.addEventListener('click', function(e) {
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
                    document.getElementById('modal_cover_image_input').value = attachment.url;
                });
                coverFrame.open();
            });
        }
    });
    </script>
    <?php
}

/**
 * Single Album Photo Grid View with Image Lightbox Modal
 */
function educore_gallery_single_album_view(): void {
    global $wpdb;
    $table_albums = educore_gallery_get_table( 'gallery_albums' );
    $table_photos = educore_gallery_get_table( 'gallery_photos' );

    $album_id = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0;

    $album = $wpdb->get_row(
        $wpdb->prepare(
            'SELECT * FROM %i WHERE id = %d',
            $table_albums,
            $album_id
        )
    );

    if ( ! $album ) {
        ?>
        <div style="background:#fef2f2; border:1px solid #fecaca; color:#dc2626; padding:18px; border-radius:12px; font-weight:700; display:flex; align-items:center; gap:8px;">
            <span class="dashicons dashicons-dismiss"></span>
            <?php esc_html_e( 'Album not found or has been deleted.', 'ifsedu-school-management' ); ?>
        </div>
        <?php
        return;
    }

    $photos = $wpdb->get_results(
        $wpdb->prepare(
            'SELECT * FROM %i WHERE album_id = %d ORDER BY id DESC',
            $table_photos,
            $album_id
        )
    );
    $photos   = is_array( $photos ) ? $photos : array();
    $back_url = admin_url( 'admin.php?page=school_management_system&tab=notices&type=gallery&sub=list' );
    $edit_url = admin_url( 'admin.php?page=school_management_system&tab=notices&type=gallery&sub=edit&id=' . $album_id );
    ?>

    <style id="ifs-educore-single-gallery-styles">
        .ifs-educore-single-root {
            width: 100%;
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
            border: 1.5px solid #cbd5e1;
            color: #334155;
            text-decoration: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            transition: all 0.2s ease;
        }

        .ifs-educore-btn-back:hover, 
        .ifs-educore-btn-action:hover {
            background: #f8fafc;
            color: #00523c;
            border-color: #00523c;
        }

        .ifs-educore-album-detail-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.02);
        }

        .ifs-educore-album-header-title {
            margin: 0 0 8px 0;
            font-size: 20px;
            font-weight: 900;
            color: #0f172a;
        }

        .ifs-educore-album-meta-row {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            color: #64748b;
            margin-bottom: 12px;
            font-weight: 600;
        }

        .ifs-educore-album-desc-text {
            margin: 0;
            font-size: 13.5px;
            color: #475569;
            line-height: 1.6;
        }

        .ifs-educore-photo-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
            gap: 18px;
        }

        .ifs-educore-photo-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            overflow: hidden;
            height: 170px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.02);
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .ifs-educore-photo-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.06);
            border-color: #00523c;
        }

        .ifs-educore-photo-card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
            display: block;
        }

        .ifs-educore-photo-card:hover img {
            transform: scale(1.04);
        }

        .ifs-educore-empty-photos-box {
            background: #ffffff;
            border: 1px dashed #cbd5e1;
            border-radius: 14px;
            padding: 50px;
            text-align: center;
            color: #64748b;
            font-weight: 700;
        }

        /* -------------------------------------------------------------
           IMAGE LIGHTBOX MODAL STYLES
           ------------------------------------------------------------- */
        .ifs-lightbox-dialog {
            border: none;
            border-radius: 14px;
            padding: 0;
            margin: auto;
            background: #0f172a;
            max-width: 90vw;
            max-height: 90vh;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            overflow: hidden;
            opacity: 0;
            transform: scale(0.95);
            transition: opacity 0.2s ease, transform 0.2s ease, overlay 0.2s allow-discrete, display 0.2s allow-discrete;
        }

        .ifs-lightbox-dialog[open] {
            opacity: 1;
            transform: scale(1);
        }

        @starting-style {
            .ifs-lightbox-dialog[open] {
                opacity: 0;
                transform: scale(0.95);
            }
        }

        .ifs-lightbox-dialog::backdrop {
            background: rgba(15, 23, 42, 0.75);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
        }

        .ifs-lightbox-content {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            max-width: 90vw;
            max-height: 90vh;
        }

        .ifs-lightbox-content img {
            max-width: 90vw;
            max-height: 85vh;
            object-fit: contain;
            display: block;
        }

        .ifs-lightbox-close {
            position: absolute;
            top: 12px;
            right: 12px;
            background: rgba(0, 0, 0, 0.6);
            color: #ffffff;
            border: none;
            font-size: 24px;
            line-height: 1;
            border-radius: 50%;
            width: 36px;
            height: 36px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s;
            z-index: 10;
        }

        .ifs-lightbox-close:hover {
            background: rgba(220, 38, 38, 0.9);
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
            <h3 class="ifs-educore-album-header-title"><?php echo esc_html( (string) $album->title ); ?></h3>
            <div class="ifs-educore-album-meta-row">
                <span><strong><?php esc_html_e( 'Category:', 'ifsedu-school-management' ); ?></strong> <?php echo esc_html( (string) $album->category ); ?></span>
                <span>•</span>
                <span><strong><?php esc_html_e( 'Total Photos:', 'ifsedu-school-management' ); ?></strong> <?php echo (int) count( $photos ); ?></span>
            </div>
            <?php if ( ! empty( $album->description ) ) : ?>
                <p class="ifs-educore-album-desc-text"><?php echo esc_html( (string) $album->description ); ?></p>
            <?php endif; ?>
        </div>

        <?php if ( ! empty( $photos ) ) : ?>
            <div class="ifs-educore-photo-grid">
                <?php foreach ( $photos as $photo ) : ?>
                    <div class="ifs-educore-photo-card ifs-trigger-lightbox" data-img="<?php echo esc_url( (string) $photo->image_url ); ?>">
                        <img src="<?php echo esc_url( (string) $photo->image_url ); ?>" alt="<?php esc_attr_e( 'Gallery Photo', 'ifsedu-school-management' ); ?>">
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else : ?>
            <div class="ifs-educore-empty-photos-box">
                <?php esc_html_e( 'This album contains no photos yet.', 'ifsedu-school-management' ); ?>
            </div>
        <?php endif; ?>

        <!-- Lightbox Image Modal -->
        <dialog id="ifsLightboxModal" class="ifs-lightbox-dialog">
            <div class="ifs-lightbox-content">
                <button type="button" class="ifs-lightbox-close" id="ifsCloseLightboxBtn">&times;</button>
                <img src="" id="ifsLightboxImg" alt="Enlarged Photo">
            </div>
        </dialog>
    </div>

    <script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function() {
        var modal = document.getElementById('ifsLightboxModal');
        var modalImg = document.getElementById('ifsLightboxImg');
        var closeBtn = document.getElementById('ifsCloseLightboxBtn');

        if (modal) {
            document.querySelectorAll('.ifs-trigger-lightbox').forEach(function(card) {
                card.addEventListener('click', function() {
                    var fullUrl = this.getAttribute('data-img');
                    if (fullUrl && modalImg) {
                        modalImg.src = fullUrl;
                        modal.showModal();
                    }
                });
            });

            var closeModal = function() { 
                modal.close(); 
                if (modalImg) modalImg.src = '';
            };

            if (closeBtn) closeBtn.addEventListener('click', closeModal);

            modal.addEventListener('click', function(e) {
                var rect = modal.getBoundingClientRect();
                var isInDialog = (
                    rect.top <= e.clientY &&
                    e.clientY <= rect.top + rect.height &&
                    rect.left <= e.clientX &&
                    e.clientX <= rect.left + rect.width
                );
                if (!isInDialog) {
                    closeModal();
                }
            });
        }
    });
    </script>
    <?php
}

/**
 * Album Create & Edit Form View (WordPress Media Library Enabled)
 */
function educore_gallery_add_edit_view(): void {
    global $wpdb;
    $table_albums = educore_gallery_get_table( 'gallery_albums' );
    $table_photos = educore_gallery_get_table( 'gallery_photos' );

    wp_enqueue_media();

    $is_edit  = isset( $_GET['sub'] ) && 'edit' === $_GET['sub'];
    $album_id = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0;

    $album         = null;
    $photos        = array();
    $saved_message = false;

    if ( $is_edit && 0 < $album_id ) {
        $album  = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $table_albums, $album_id ) );
        $photos = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE album_id = %d ORDER BY id DESC', $table_photos, $album_id ) );
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
            $wpdb->update( $table_albums, $album_data, array( 'id' => $album_id ) );
            $current_id = $album_id;
        } else {
            $wpdb->insert( $table_albums, $album_data );
            $current_id = (int) $wpdb->insert_id;
        }

        if ( ! empty( $_POST['gallery_photo_urls'] ) && 0 < $current_id ) {
            $raw_urls   = sanitize_text_field( wp_unslash( $_POST['gallery_photo_urls'] ) );
            $photo_urls = json_decode( stripslashes( $raw_urls ), true );

            if ( is_array( $photo_urls ) && ! empty( $photo_urls ) ) {
                foreach ( $photo_urls as $url ) {
                    $clean_url = esc_url_raw( trim( (string) $url ) );
                    if ( ! empty( $clean_url ) ) {
                        $wpdb->insert( $table_photos, array( 'album_id' => $current_id, 'image_url' => $clean_url ) );

                        if ( empty( $cover_image ) ) {
                            $cover_image = $clean_url;
                            $wpdb->update( $table_albums, array( 'cover_image' => $cover_image ), array( 'id' => $current_id ) );
                        }
                    }
                }
            }
        }

        $saved_message = true;
        $album  = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $table_albums, $current_id ) );
        $photos = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE album_id = %d ORDER BY id DESC', $table_photos, $current_id ) );
        $photos = is_array( $photos ) ? $photos : array();
    }

    $back_url = admin_url( 'admin.php?page=school_management_system&tab=notices&type=gallery&sub=list' );
    ?>

    <style id="ifs-educore-form-gallery-styles">
        .ifs-educore-form-root {
            width: 100%;
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #0f172a;
        }

        .ifs-educore-top-action-bar {
            margin-bottom: 20px;
        }

        .ifs-educore-btn-back {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            background: #ffffff;
            border: 1.5px solid #cbd5e1;
            color: #334155;
            text-decoration: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            transition: all 0.2s ease;
        }

        .ifs-educore-btn-back:hover {
            background: #f8fafc;
            color: #00523c;
            border-color: #00523c;
        }

        .ifs-educore-alert-success-box {
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            color: #047857;
            padding: 12px 18px;
            border-radius: 9px;
            font-size: 13.5px;
            font-weight: 700;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .ifs-educore-bento-form-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 24px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.02);
        }

        .ifs-educore-form-title {
            margin: 0 0 20px 0;
            font-size: 16.5px;
            font-weight: 800;
            color: #0f172a;
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 14px;
        }

        .ifs-educore-form-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr;
            gap: 16px;
            margin-bottom: 18px;
        }

        .ifs-educore-form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-bottom: 18px;
        }

        .ifs-educore-form-group label {
            font-size: 11.5px;
            font-weight: 700;
            color: #475569;
            text-transform: capitalize;
            letter-spacing: 0.3px;
        }

        .ifs-educore-field-input, 
        .ifs-educore-field-select,
        .ifs-educore-field-textarea {
            width: 100%;
            height: 42px;
            padding: 0 14px;
            background: #ffffff;
            border: 1.5px solid #cbd5e1;
            border-radius: 9px;
            font-size: 13.5px;
            color: #0f172a;
            box-sizing: border-box;
            outline: none;
            transition: all 0.2s ease;
            font-weight: 600;
        }

        .ifs-educore-field-textarea {
            height: auto;
            padding: 10px 14px;
            line-height: 1.5;
        }

        .ifs-educore-field-input:focus, 
        .ifs-educore-field-select:focus,
        .ifs-educore-field-textarea:focus {
            border-color: #00523c;
            box-shadow: 0 0 0 3px rgba(0, 82, 60, 0.12);
        }

        .ifs-educore-btn-submit {
            background: #00523c;
            color: #ffffff;
            border: none;
            padding: 10px 24px;
            border-radius: 9px;
            font-size: 13.5px;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0, 82, 60, 0.18);
            transition: background 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .ifs-educore-btn-submit:hover {
            background: #047857;
        }

        .ifs-educore-cover-flex {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .ifs-educore-upload-bento-node {
            background: #f8fafc;
            border: 2px dashed #cbd5e1;
            padding: 24px;
            border-radius: 12px;
            text-align: center;
            margin-top: 20px;
        }

        .ifs-educore-upload-bento-label {
            font-weight: 800;
            color: #0f172a;
            display: block;
            margin-bottom: 6px;
            font-size: 14px;
        }

        .ifs-educore-upload-hint {
            margin-top: 8px;
            font-size: 12px;
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
            width: 72px;
            height: 72px;
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
            display: block;
        }

        .ifs-educore-photos-manage-section {
            margin-top: 28px;
        }

        .ifs-educore-manage-title {
            font-size: 14px;
            font-weight: 800;
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
            height: 100px;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #cbd5e1;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }

        .ifs-educore-manage-photo-card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .ifs-educore-btn-photo-del {
            position: absolute;
            top: 5px;
            right: 5px;
            background: rgba(220, 38, 38, 0.9);
            color: #ffffff;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            font-weight: bold;
            font-size: 14px;
        }

        @media (max-width: 900px) {
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
                        <input type="text" name="title" class="ifs-educore-field-input" value="<?php echo $album ? esc_attr( (string) $album->title ) : ''; ?>" required>
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
                    <textarea name="description" class="ifs-educore-field-textarea" rows="3"><?php echo $album ? esc_textarea( (string) $album->description ) : ''; ?></textarea>
                </div>

                <!-- Cover Image Media Uploader -->
                <div class="ifs-educore-form-group">
                    <label><?php esc_html_e( 'Cover Image (Thumbnail)', 'ifsedu-school-management' ); ?></label>
                    <div class="ifs-educore-cover-flex">
                        <input type="text" name="cover_image" id="ifs_cover_image_input" class="ifs-educore-field-input" value="<?php echo $album ? esc_attr( (string) $album->cover_image ) : ''; ?>" readonly style="flex:1;">
                        <button type="button" class="ifs-educore-btn-primary" id="ifs_upload_cover_btn" style="box-shadow:none; height:42px; border-radius:9px;">
                            <?php esc_html_e( 'Choose Image', 'ifsedu-school-management' ); ?>
                        </button>
                    </div>
                </div>

                <!-- Multi-Photo WP Media Uploader Node -->
                <div class="ifs-educore-upload-bento-node">
                    <label class="ifs-educore-upload-bento-label"><?php esc_html_e( 'Upload Photos to Album', 'ifsedu-school-management' ); ?></label>
                    <input type="hidden" name="gallery_photo_urls" id="ifs_gallery_photo_urls_input" value="">
                    <button type="button" class="ifs-educore-btn-primary" id="ifs_upload_multi_photos_btn" style="box-shadow:none; border-radius:9px;">
                        <span class="dashicons dashicons-images-alt2" style="font-size:16px; width:16px; height:16px;"></span> 
                        <?php esc_html_e( 'Select Multiple Photos from Media Library', 'ifsedu-school-management' ); ?>
                    </button>
                    <p class="ifs-educore-upload-hint">
                        <?php esc_html_e( 'Hold Ctrl/Cmd to select multiple images simultaneously.', 'ifsedu-school-management' ); ?>
                    </p>
                    <div id="ifs_selected_previews_container" class="ifs-educore-selected-previews"></div>
                </div>

                <?php if ( $is_edit && ! empty( $photos ) ) : ?>
                    <div class="ifs-educore-photos-manage-section">
                        <h4 class="ifs-educore-manage-title">
                            <?php esc_html_e( 'Manage Existing Photos', 'ifsedu-school-management' ); ?> (<?php echo (int) count( $photos ); ?>)
                        </h4>
                        <div class="ifs-educore-manage-grid">
                            <?php foreach ( $photos as $photo ) : 
                                $photo_del_url = wp_nonce_url( 
                                    admin_url( 'admin.php?page=school_management_system&tab=notices&type=gallery&sub=delete_photo&photo_id=' . $photo->id . '&album_id=' . $album_id ), 
                                    'delete_photo_' . $photo->id 
                                );
                            ?>
                                <div class="ifs-educore-manage-photo-card">
                                    <img src="<?php echo esc_url( (string) $photo->image_url ); ?>" alt="<?php esc_attr_e( 'Gallery Image', 'ifsedu-school-management' ); ?>">
                                    <a href="<?php echo esc_url( $photo_del_url ); ?>" class="ifs-educore-btn-photo-del" title="<?php esc_attr_e( 'Delete Photo', 'ifsedu-school-management' ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Remove this photo?', 'ifsedu-school-management' ) ); ?>');">
                                        &times;
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div style="margin-top:24px; border-top:1px solid #f1f5f9; padding-top:18px;">
                    <button type="submit" name="educore_save_gallery" class="ifs-educore-btn-submit">
                        <span class="dashicons dashicons-saved"></span>
                        <?php echo $is_edit ? esc_html__( 'Update Album', 'ifsedu-school-management' ) : esc_html__( 'Publish Album', 'ifsedu-school-management' ); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- WordPress Media Library Integration Script -->
    <script type="text/javascript">
    jQuery(document).ready(function($) {
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
function educore_gallery_photo_delete_action(): void {
    global $wpdb;
    $table_photos = educore_gallery_get_table( 'gallery_photos' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Permission denied.', 'ifsedu-school-management' ), 403 );
    }

    $photo_id  = isset( $_GET['photo_id'] ) ? absint( wp_unslash( $_GET['photo_id'] ) ) : 0;
    $album_id  = isset( $_GET['album_id'] ) ? absint( wp_unslash( $_GET['album_id'] ) ) : 0;
    $del_nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

    if ( 0 < $photo_id && wp_verify_nonce( $del_nonce, 'delete_photo_' . $photo_id ) ) {
        $wpdb->delete( $table_photos, array( 'id' => $photo_id ), array( '%d' ) );
    }

    $redirect_url = admin_url( 'admin.php?page=school_management_system&tab=notices&type=gallery&sub=edit&id=' . $album_id );
    if ( function_exists( 'educore_safe_redirect' ) ) {
        educore_safe_redirect( $redirect_url );
    } else {
        wp_safe_redirect( $redirect_url );
        exit;
    }
}

/**
 * Gallery Album Complete Deletion
 */
function educore_gallery_delete_action(): void {
    global $wpdb;
    $table_albums = educore_gallery_get_table( 'gallery_albums' );
    $table_photos = educore_gallery_get_table( 'gallery_photos' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Permission denied.', 'ifsedu-school-management' ), 403 );
    }

    $album_id  = isset( $_GET['id'] ) ? absint( wp_unslash( $_GET['id'] ) ) : 0;
    $del_nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

    if ( 0 < $album_id && wp_verify_nonce( $del_nonce, 'delete_gallery_' . $album_id ) ) {
        $wpdb->delete( $table_photos, array( 'album_id' => $album_id ), array( '%d' ) );
        $wpdb->delete( $table_albums, array( 'id' => $album_id ), array( '%d' ) );
    }

    $redirect_url = admin_url( 'admin.php?page=school_management_system&tab=notices&type=gallery&sub=list' );
    if ( function_exists( 'educore_safe_redirect' ) ) {
        educore_safe_redirect( $redirect_url );
    } else {
        wp_safe_redirect( $redirect_url );
        exit;
    }
}
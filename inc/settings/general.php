<?php
/**
 * Institutional General Profile & Identity Settings Module
 * File: inc/settings/general.php
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function educore_render_settings_general_view( $base_url ) {
    $settings_updated = false;
    $req_method       = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';

    if ( 'POST' === $req_method && isset( $_POST['educore_save_general_settings'] ) ) {
        if ( ! isset( $_POST['educore_settings_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['educore_settings_nonce'] ) ), 'save_settings_action' ) ) {
            wp_die( esc_html__( 'Security check failed.', 'ifsedu-school-management' ) );
        }

        $school_name    = isset( $_POST['school_name'] ) ? sanitize_text_field( wp_unslash( $_POST['school_name'] ) ) : '';
        $school_tagline = isset( $_POST['school_tagline'] ) ? sanitize_text_field( wp_unslash( $_POST['school_tagline'] ) ) : '';
        $school_logo    = isset( $_POST['school_logo'] ) ? esc_url_raw( wp_unslash( $_POST['school_logo'] ) ) : '';
        $principal_sig  = isset( $_POST['principal_sig'] ) ? esc_url_raw( wp_unslash( $_POST['principal_sig'] ) ) : '';

        update_option( 'educore_school_name', $school_name );
        update_option( 'educore_school_tagline', $school_tagline );
        update_option( 'educore_school_logo', $school_logo );
        update_option( 'educore_principal_sig', $principal_sig );

        if ( function_exists( 'educore_log_activity' ) ) {
            educore_log_activity( __( 'Updated general institutional settings profile.', 'ifsedu-school-management' ) );
        }

        $settings_updated = true;
    }

    $school_name    = get_option( 'educore_school_name', get_bloginfo( 'name' ) );
    $school_tagline = get_option( 'educore_school_tagline', '' );
    $school_logo    = get_option( 'educore_school_logo', '' );
    $principal_sig  = get_option( 'educore_principal_sig', '' );
    ?>

    <?php if ( $settings_updated ) : ?>
        <div class="ifs-educore-alert">
            <span class="dashicons dashicons-yes-alt"></span>
            <?php esc_html_e( 'General settings updated successfully.', 'ifsedu-school-management' ); ?>
        </div>
    <?php endif; ?>

    <div class="ifs-educore-settings-card">
        <form method="POST" action="">
            <?php wp_nonce_field( 'save_settings_action', 'educore_settings_nonce' ); ?>

            <!-- Row 1: Name & Motto -->
            <div class="ifs-educore-grid-row">
                <div class="ifs-educore-field-node">
                    <label class="ifs-educore-label">
                        <?php esc_html_e( 'Official Institution Name', 'ifsedu-school-management' ); ?> <span class="required">*</span>
                    </label>
                    <input type="text" name="school_name" class="ifs-educore-input" value="<?php echo esc_attr( $school_name ); ?>" required>
                </div>

                <div class="ifs-educore-field-node">
                    <label class="ifs-educore-label">
                        <?php esc_html_e( 'Motto / Tagline', 'ifsedu-school-management' ); ?>
                    </label>
                    <input type="text" name="school_tagline" class="ifs-educore-input" value="<?php echo esc_attr( $school_tagline ); ?>" placeholder="<?php esc_attr_e( 'e.g. Education for Enlightenment', 'ifsedu-school-management' ); ?>">
                </div>
            </div>

            <!-- Row 2: Institutional Logo & Principal Signature -->
            <div class="ifs-educore-grid-row">
                <!-- Logo Upload -->
                <div class="ifs-educore-field-node">
                    <label class="ifs-educore-label"><?php esc_html_e( 'Institutional Logo', 'ifsedu-school-management' ); ?></label>
                    <div class="ifs-educore-uploader-card">
                        <div class="ifs-educore-preview-box" id="ifs_logo_preview">
                            <?php if ( ! empty( $school_logo ) ) : ?>
                                <img src="<?php echo esc_url( $school_logo ); ?>" alt="<?php esc_attr_e( 'School Logo', 'ifsedu-school-management' ); ?>">
                            <?php else : ?>
                                <span class="dashicons dashicons-format-image" style="font-size:32px; width:32px; height:32px; color:#94a3b8;"></span>
                            <?php endif; ?>
                        </div>
                        <input type="hidden" name="school_logo" id="ifs_school_logo_input" value="<?php echo esc_url( $school_logo ); ?>">
                        <div class="ifs-educore-uploader-actions">
                            <button type="button" class="ifs-educore-btn-upload" id="ifs_upload_logo_btn">
                                <span class="dashicons dashicons-upload"></span> <?php esc_html_e( 'Select Logo', 'ifsedu-school-management' ); ?>
                            </button>
                            <?php $logo_remove_style = empty( $school_logo ) ? 'display:none;' : ''; ?>
                            <button type="button" class="ifs-educore-btn-remove" id="ifs_remove_logo_btn" style="<?php echo esc_attr( $logo_remove_style ); ?>">
                                <?php esc_html_e( 'Remove', 'ifsedu-school-management' ); ?>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Principal Signature Upload -->
                <div class="ifs-educore-field-node">
                    <label class="ifs-educore-label"><?php esc_html_e( 'Principal / Authority Signature', 'ifsedu-school-management' ); ?></label>
                    <div class="ifs-educore-uploader-card">
                        <div class="ifs-educore-preview-box" id="ifs_sig_preview">
                            <?php if ( ! empty( $principal_sig ) ) : ?>
                                <img src="<?php echo esc_url( $principal_sig ); ?>" alt="<?php esc_attr_e( 'Principal Signature', 'ifsedu-school-management' ); ?>">
                            <?php else : ?>
                                <span class="dashicons dashicons-edit" style="font-size:32px; width:32px; height:32px; color:#94a3b8;"></span>
                            <?php endif; ?>
                        </div>
                        <input type="hidden" name="principal_sig" id="ifs_principal_sig_input" value="<?php echo esc_url( $principal_sig ); ?>">
                        <div class="ifs-educore-uploader-actions">
                            <button type="button" class="ifs-educore-btn-upload" id="ifs_upload_sig_btn">
                                <span class="dashicons dashicons-upload"></span> <?php esc_html_e( 'Select Signature', 'ifsedu-school-management' ); ?>
                            </button>
                            <?php $sig_remove_style = empty( $principal_sig ) ? 'display:none;' : ''; ?>
                            <button type="button" class="ifs-educore-btn-remove" id="ifs_remove_sig_btn" style="<?php echo esc_attr( $sig_remove_style ); ?>">
                                <?php esc_html_e( 'Remove', 'ifsedu-school-management' ); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <div style="margin-top: 10px; padding-top: 20px; border-top: 1px solid #f1f5f9;">
                <button type="submit" name="educore_save_general_settings" class="ifs-educore-btn-submit">
                    <span class="dashicons dashicons-saved"></span>
                    <?php esc_html_e( 'Save General Settings', 'ifsedu-school-management' ); ?>
                </button>
            </div>
        </form>
    </div>

    <!-- Media Uploader Script -->
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        function setupMediaUploader(btnSelector, inputSelector, previewSelector, removeBtnSelector, modalTitle) {
            var mediaFrame;
            $(btnSelector).on('click', function(e) {
                e.preventDefault();
                if (mediaFrame) {
                    mediaFrame.open();
                    return;
                }
                mediaFrame = wp.media({
                    title: modalTitle,
                    button: { text: '<?php echo esc_js( __( 'Use Selected Image', 'ifsedu-school-management' ) ); ?>' },
                    multiple: false
                });
                mediaFrame.on('select', function() {
                    var attachment = mediaFrame.state().get('selection').first().toJSON();
                    $(inputSelector).val(attachment.url);
                    $(previewSelector).html('<img src="' + attachment.url + '" alt="Preview">');
                    $(removeBtnSelector).show();
                });
                mediaFrame.open();
            });

            $(removeBtnSelector).on('click', function(e) {
                e.preventDefault();
                $(inputSelector).val('');
                $(previewSelector).html('<span class="dashicons dashicons-format-image" style="font-size:32px; width:32px; height:32px; color:#94a3b8;"></span>');
                $(this).hide();
            });
        }

        setupMediaUploader('#ifs_upload_logo_btn', '#ifs_school_logo_input', '#ifs_logo_preview', '#ifs_remove_logo_btn', '<?php echo esc_js( __( 'Select Institutional Logo', 'ifsedu-school-management' ) ); ?>');
        setupMediaUploader('#ifs_upload_sig_btn', '#ifs_principal_sig_input', '#ifs_sig_preview', '#ifs_remove_sig_btn', '<?php echo esc_js( __( 'Select Principal Signature Image', 'ifsedu-school-management' ) ); ?>');
    });
    </script>
    <?php
}
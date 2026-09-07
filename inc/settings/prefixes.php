<?php
/**
 * Institutional ID & Voucher Prefix Settings Module
 * File: inc/settings/prefixes.php
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function educore_render_settings_prefixes_view( $base_url ) {
    $settings_updated = false;
    $req_method       = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';

    if ( 'POST' === $req_method && isset( $_POST['educore_save_prefixes'] ) ) {
        if ( ! isset( $_POST['educore_prefixes_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['educore_prefixes_nonce'] ) ), 'save_prefixes_action' ) ) {
            wp_die( esc_html__( 'Security check failed.', 'ifsedu-school-management' ) );
        }

        $prefix_student = isset( $_POST['prefix_student'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['prefix_student'] ) ) ) : 'STU-';
        $prefix_teacher = isset( $_POST['prefix_teacher'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['prefix_teacher'] ) ) ) : 'TCH-';
        $prefix_staff   = isset( $_POST['prefix_staff'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['prefix_staff'] ) ) ) : 'STF-';
        $prefix_officer = isset( $_POST['prefix_officer'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['prefix_officer'] ) ) ) : 'OFC-';
        $prefix_fee     = isset( $_POST['prefix_fee'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['prefix_fee'] ) ) ) : 'INV-';
        $prefix_acc     = isset( $_POST['prefix_acc'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_POST['prefix_acc'] ) ) ) : 'VCH-';

        update_option( 'educore_prefix_student', $prefix_student );
        update_option( 'educore_prefix_teacher', $prefix_teacher );
        update_option( 'educore_prefix_staff', $prefix_staff );
        update_option( 'educore_prefix_officer', $prefix_officer );
        update_option( 'educore_prefix_fee', $prefix_fee );
        update_option( 'educore_prefix_acc', $prefix_acc );

        if ( function_exists( 'educore_log_activity' ) ) {
            educore_log_activity( __( 'Updated system ID and voucher prefix codes.', 'ifsedu-school-management' ) );
        }

        $settings_updated = true;
    }

    $prefix_student = get_option( 'educore_prefix_student', 'STU-' );
    $prefix_teacher = get_option( 'educore_prefix_teacher', 'TCH-' );
    $prefix_staff   = get_option( 'educore_prefix_staff', 'STF-' );
    $prefix_officer = get_option( 'educore_prefix_officer', 'OFC-' );
    $prefix_fee     = get_option( 'educore_prefix_fee', 'INV-' );
    $prefix_acc     = get_option( 'educore_prefix_acc', 'VCH-' );
    ?>

    <?php if ( $settings_updated ) : ?>
        <div class="ifs-educore-alert">
            <span class="dashicons dashicons-yes-alt"></span>
            <?php esc_html_e( 'Prefix settings updated successfully.', 'ifsedu-school-management' ); ?>
        </div>
    <?php endif; ?>

    <div class="ifs-educore-settings-card">
        <form method="POST" action="">
            <?php wp_nonce_field( 'save_prefixes_action', 'educore_prefixes_nonce' ); ?>

            <!-- Row 1: Student & Teacher -->
            <div class="ifs-educore-grid-row">
                <div class="ifs-educore-field-node">
                    <label class="ifs-educore-label">
                        <?php esc_html_e( 'Student ID Prefix', 'ifsedu-school-management' ); ?> <span class="required">*</span>
                    </label>
                    <input type="text" name="prefix_student" class="ifs-educore-input" value="<?php echo esc_attr( $prefix_student ); ?>" required placeholder="<?php esc_attr_e( 'e.g. STU-', 'ifsedu-school-management' ); ?>">
                    <span class="ifs-educore-help-text"><?php esc_html_e( 'Example output: STU-2026-0001', 'ifsedu-school-management' ); ?></span>
                </div>

                <div class="ifs-educore-field-node">
                    <label class="ifs-educore-label">
                        <?php esc_html_e( 'Teacher / Instructor ID Prefix', 'ifsedu-school-management' ); ?> <span class="required">*</span>
                    </label>
                    <input type="text" name="prefix_teacher" class="ifs-educore-input" value="<?php echo esc_attr( $prefix_teacher ); ?>" required placeholder="<?php esc_attr_e( 'e.g. TCH-', 'ifsedu-school-management' ); ?>">
                    <span class="ifs-educore-help-text"><?php esc_html_e( 'Example output: TCH-0101', 'ifsedu-school-management' ); ?></span>
                </div>
            </div>

            <!-- Row 2: General Staff & Officer -->
            <div class="ifs-educore-grid-row">
                <div class="ifs-educore-field-node">
                    <label class="ifs-educore-label">
                        <?php esc_html_e( 'General Staff ID Prefix', 'ifsedu-school-management' ); ?> <span class="required">*</span>
                    </label>
                    <input type="text" name="prefix_staff" class="ifs-educore-input" value="<?php echo esc_attr( $prefix_staff ); ?>" required placeholder="<?php esc_attr_e( 'e.g. STF-', 'ifsedu-school-management' ); ?>">
                    <span class="ifs-educore-help-text"><?php esc_html_e( 'Example output: STF-0012', 'ifsedu-school-management' ); ?></span>
                </div>

                <div class="ifs-educore-field-node">
                    <label class="ifs-educore-label">
                        <?php esc_html_e( 'Officer / Admin Staff Prefix', 'ifsedu-school-management' ); ?> <span class="required">*</span>
                    </label>
                    <input type="text" name="prefix_officer" class="ifs-educore-input" value="<?php echo esc_attr( $prefix_officer ); ?>" required placeholder="<?php esc_attr_e( 'e.g. OFC-', 'ifsedu-school-management' ); ?>">
                    <span class="ifs-educore-help-text"><?php esc_html_e( 'Example output: OFC-0005', 'ifsedu-school-management' ); ?></span>
                </div>
            </div>

            <!-- Row 3: Fee Invoices & Accounting Vouchers -->
            <div class="ifs-educore-grid-row">
                <div class="ifs-educore-field-node">
                    <label class="ifs-educore-label">
                        <?php esc_html_e( 'Fee Invoice Prefix', 'ifsedu-school-management' ); ?> <span class="required">*</span>
                    </label>
                    <input type="text" name="prefix_fee" class="ifs-educore-input" value="<?php echo esc_attr( $prefix_fee ); ?>" required placeholder="<?php esc_attr_e( 'e.g. INV-', 'ifsedu-school-management' ); ?>">
                    <span class="ifs-educore-help-text"><?php esc_html_e( 'Example output: INV-2026-0045', 'ifsedu-school-management' ); ?></span>
                </div>

                <div class="ifs-educore-field-node">
                    <label class="ifs-educore-label">
                        <?php esc_html_e( 'Accounting Voucher Prefix', 'ifsedu-school-management' ); ?> <span class="required">*</span>
                    </label>
                    <input type="text" name="prefix_acc" class="ifs-educore-input" value="<?php echo esc_attr( $prefix_acc ); ?>" required placeholder="<?php esc_attr_e( 'e.g. VCH-', 'ifsedu-school-management' ); ?>">
                    <span class="ifs-educore-help-text"><?php esc_html_e( 'Example output: VCH-2026-0102', 'ifsedu-school-management' ); ?></span>
                </div>
            </div>

            <!-- Submit Button -->
            <div style="margin-top: 10px; padding-top: 20px; border-top: 1px solid #f1f5f9;">
                <button type="submit" name="educore_save_prefixes" class="ifs-educore-btn-submit">
                    <span class="dashicons dashicons-saved"></span>
                    <?php esc_html_e( 'Save ID Prefixes', 'ifsedu-school-management' ); ?>
                </button>
            </div>
        </form>
    </div>
    <?php
}
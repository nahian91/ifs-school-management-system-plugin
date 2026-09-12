<?php
/**
 * Institutional ID & Voucher Prefix Settings Module
 * File: inc/settings/prefixes.php
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Render ID & Voucher Prefixes Settings View & Handle Form Submission
 *
 * @param string $base_url Base URL for settings subtabs.
 */
function educore_render_settings_prefixes_view( $base_url ) {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient administrative permissions to access this page.', 'ifsedu-school-management' ) );
    }

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

    <style id="ifs-educore-prefixes-styles">
        .ifs-educore-prefixes-root {
            font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #0f172a;
        }

        .ifs-educore-alert {
            background: #ecfdf5;
            border-left: 4px solid #10b981;
            color: #065f46;
            padding: 12px 18px;
            border-radius: 8px;
            font-weight: 700;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .ifs-educore-settings-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.03);
            margin-bottom: 30px;
        }

        .ifs-educore-grid-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }

        @media (max-width: 768px) {
            .ifs-educore-grid-row {
                grid-template-columns: 1fr;
            }
        }

        .ifs-educore-field-node {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .ifs-educore-label {
            font-size: 12.5px;
            font-weight: 700;
            color: #334155;
            letter-spacing: -0.1px;
        }

        .ifs-educore-required {
            color: #ef4444;
        }

        .ifs-educore-input {
            width: 100%;
            height: 42px;
            border: 1.5px solid #cbd5e1;
            border-radius: 10px;
            padding: 0 14px;
            font-size: 13.5px;
            color: #0f172a;
            background-color: #f8fafc;
            box-sizing: border-box;
            outline: none;
            transition: all 0.2s ease;
        }

        .ifs-educore-input:focus {
            border-color: #00523c;
            background-color: #ffffff;
            box-shadow: 0 0 0 3px rgba(0, 82, 60, 0.1);
        }

        .ifs-educore-help-text {
            font-size: 11.5px;
            color: #64748b;
        }

        .ifs-educore-prefixes-submit-wrap {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: flex-end;
        }

        .ifs-educore-btn-submit {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            height: 42px;
            padding: 0 24px;
            background: #00523c;
            color: #ffffff;
            font-size: 13.5px;
            font-weight: 800;
            border-radius: 10px;
            border: none;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0, 82, 60, 0.25);
            transition: all 0.2s ease;
        }

        .ifs-educore-btn-submit:hover {
            background: #003e2d;
        }
    </style>

    <div class="ifs-educore-prefixes-root">
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
                            <?php esc_html_e( 'Student ID Prefix', 'ifsedu-school-management' ); ?> <span class="ifs-educore-required">*</span>
                        </label>
                        <input type="text" name="prefix_student" class="ifs-educore-input" value="<?php echo esc_attr( $prefix_student ); ?>" required placeholder="<?php esc_attr_e( 'e.g. STU-', 'ifsedu-school-management' ); ?>">
                        <span class="ifs-educore-help-text"><?php esc_html_e( 'Example output: STU-2026-0001', 'ifsedu-school-management' ); ?></span>
                    </div>

                    <div class="ifs-educore-field-node">
                        <label class="ifs-educore-label">
                            <?php esc_html_e( 'Teacher / Instructor ID Prefix', 'ifsedu-school-management' ); ?> <span class="ifs-educore-required">*</span>
                        </label>
                        <input type="text" name="prefix_teacher" class="ifs-educore-input" value="<?php echo esc_attr( $prefix_teacher ); ?>" required placeholder="<?php esc_attr_e( 'e.g. TCH-', 'ifsedu-school-management' ); ?>">
                        <span class="ifs-educore-help-text"><?php esc_html_e( 'Example output: TCH-0101', 'ifsedu-school-management' ); ?></span>
                    </div>
                </div>

                <!-- Row 2: General Staff & Officer -->
                <div class="ifs-educore-grid-row">
                    <div class="ifs-educore-field-node">
                        <label class="ifs-educore-label">
                            <?php esc_html_e( 'General Staff ID Prefix', 'ifsedu-school-management' ); ?> <span class="ifs-educore-required">*</span>
                        </label>
                        <input type="text" name="prefix_staff" class="ifs-educore-input" value="<?php echo esc_attr( $prefix_staff ); ?>" required placeholder="<?php esc_attr_e( 'e.g. STF-', 'ifsedu-school-management' ); ?>">
                        <span class="ifs-educore-help-text"><?php esc_html_e( 'Example output: STF-0012', 'ifsedu-school-management' ); ?></span>
                    </div>

                    <div class="ifs-educore-field-node">
                        <label class="ifs-educore-label">
                            <?php esc_html_e( 'Officer / Admin Staff Prefix', 'ifsedu-school-management' ); ?> <span class="ifs-educore-required">*</span>
                        </label>
                        <input type="text" name="prefix_officer" class="ifs-educore-input" value="<?php echo esc_attr( $prefix_officer ); ?>" required placeholder="<?php esc_attr_e( 'e.g. OFC-', 'ifsedu-school-management' ); ?>">
                        <span class="ifs-educore-help-text"><?php esc_html_e( 'Example output: OFC-0005', 'ifsedu-school-management' ); ?></span>
                    </div>
                </div>

                <!-- Row 3: Fee Invoices & Accounting Vouchers -->
                <div class="ifs-educore-grid-row">
                    <div class="ifs-educore-field-node">
                        <label class="ifs-educore-label">
                            <?php esc_html_e( 'Fee Invoice Prefix', 'ifsedu-school-management' ); ?> <span class="ifs-educore-required">*</span>
                        </label>
                        <input type="text" name="prefix_fee" class="ifs-educore-input" value="<?php echo esc_attr( $prefix_fee ); ?>" required placeholder="<?php esc_attr_e( 'e.g. INV-', 'ifsedu-school-management' ); ?>">
                        <span class="ifs-educore-help-text"><?php esc_html_e( 'Example output: INV-2026-0045', 'ifsedu-school-management' ); ?></span>
                    </div>

                    <div class="ifs-educore-field-node">
                        <label class="ifs-educore-label">
                            <?php esc_html_e( 'Accounting Voucher Prefix', 'ifsedu-school-management' ); ?> <span class="ifs-educore-required">*</span>
                        </label>
                        <input type="text" name="prefix_acc" class="ifs-educore-input" value="<?php echo esc_attr( $prefix_acc ); ?>" required placeholder="<?php esc_attr_e( 'e.g. VCH-', 'ifsedu-school-management' ); ?>">
                        <span class="ifs-educore-help-text"><?php esc_html_e( 'Example output: VCH-2026-0102', 'ifsedu-school-management' ); ?></span>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="ifs-educore-prefixes-submit-wrap">
                    <button type="submit" name="educore_save_prefixes" class="ifs-educore-btn-submit">
                        <span class="dashicons dashicons-saved"></span>
                        <?php esc_html_e( 'Save ID Prefixes', 'ifsedu-school-management' ); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php
}
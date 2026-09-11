<?php
/**
 * Institutional Fees, Class Fee Structure & Late Fine Automation Settings
 * File: inc/settings/fees.php
 * Text Domain: ifsedu-school-management
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function educore_render_settings_fees_view( $base_url ) {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient administrative permissions to access this page.', 'ifsedu-school-management' ) );
    }

    global $wpdb;
    $table_fee_types = $wpdb->prefix . 'sms_fee_types';
    $table_units     = $wpdb->prefix . 'sms_academic_units';
    $table_late_cfg  = $wpdb->prefix . 'sms_late_fee_config';

    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    $active_section = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : 'structure';
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    $subtab_base_url = add_query_arg( 'subtab', 'fees', $base_url );
    $notice_message  = '';

    // --------------------------------------------------------------------------
    // 1. FORM SUBMISSIONS & ACTIONS
    // --------------------------------------------------------------------------
    // Save Class-wise Fee Structure
    if ( isset( $_POST['save_class_fee_structure'] ) && check_admin_referer( 'educore_save_fees_settings_action', 'educore_fees_settings_nonce' ) ) {
        $target_class = isset( $_POST['target_class'] ) ? sanitize_text_field( wp_unslash( $_POST['target_class'] ) ) : '';
        $fee_titles   = ( isset( $_POST['fee_title'] ) && is_array( $_POST['fee_title'] ) ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['fee_title'] ) ) : array();
        $fee_amounts  = ( isset( $_POST['amount'] ) && is_array( $_POST['amount'] ) ) ? array_map( 'floatval', wp_unslash( $_POST['amount'] ) ) : array();
        $period_types = ( isset( $_POST['period_type'] ) && is_array( $_POST['period_type'] ) ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['period_type'] ) ) : array();

        if ( ! empty( $target_class ) && ! empty( $fee_titles ) ) {
            $saved_count = 0;
            foreach ( $fee_titles as $index => $title ) {
                $trimmed_title = trim( (string) $title );
                $amount        = isset( $fee_amounts[ $index ] ) ? floatval( $fee_amounts[ $index ] ) : 0.00;
                $period        = isset( $period_types[ $index ] ) ? sanitize_text_field( (string) $period_types[ $index ] ) : 'Monthly';

                if ( ! empty( $trimmed_title ) ) {
                    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                    $existing_id = (int) $wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT id FROM `{$table_fee_types}` WHERE class_name = %s AND fee_title = %s LIMIT 1",
                            $target_class,
                            $trimmed_title
                        )
                    );

                    if ( $existing_id > 0 ) {
                        $wpdb->update(
                            $table_fee_types,
                            array( 'amount' => $amount, 'period_type' => $period ),
                            array( 'id' => $existing_id ),
                            array( '%f', '%s' ),
                            array( '%d' )
                        );
                    } else {
                        $wpdb->insert(
                            $table_fee_types,
                            array( 'class_name' => $target_class, 'fee_title' => $trimmed_title, 'amount' => $amount, 'period_type' => $period ),
                            array( '%s', '%s', '%f', '%s' )
                        );
                    }
                    // phpcs:enable
                    $saved_count++;
                }
            }

            if ( function_exists( 'educore_log_activity' ) ) {
                /* translators: 1: Saved count, 2: Class Name */
                educore_log_activity( sprintf( __( 'Configured %1$d fee items for %2$s', 'ifsedu-school-management' ), $saved_count, $target_class ) );
            }

            $notice_message = sprintf(
                /* translators: 1: Saved count, 2: Class Name */
                esc_html__( 'Successfully updated %1$d fee item(s) for Class: %2$s', 'ifsedu-school-management' ),
                intval( $saved_count ),
                esc_html( $target_class )
            );
        }
    }

    // Save Late Fee Configuration
    if ( isset( $_POST['save_late_fine_config'] ) && check_admin_referer( 'educore_save_late_fine_action', 'educore_late_fine_nonce' ) ) {
        $fine_type       = isset( $_POST['fine_type'] ) ? sanitize_text_field( wp_unslash( $_POST['fine_type'] ) ) : 'Fixed';
        $fine_amount     = isset( $_POST['fine_amount'] ) ? floatval( wp_unslash( $_POST['fine_amount'] ) ) : 0.00;
        $grace_days      = isset( $_POST['grace_days'] ) ? absint( wp_unslash( $_POST['grace_days'] ) ) : 0;
        $fine_start_date = isset( $_POST['fine_start_date'] ) ? absint( wp_unslash( $_POST['fine_start_date'] ) ) : 12;
        $max_cap         = isset( $_POST['max_fine_cap'] ) ? floatval( wp_unslash( $_POST['max_fine_cap'] ) ) : 0.00;
        $status          = isset( $_POST['fine_status'] ) ? sanitize_text_field( wp_unslash( $_POST['fine_status'] ) ) : 'Active';

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $existing_cfg_id = (int) $wpdb->get_var( "SELECT id FROM `{$table_late_cfg}` LIMIT 1" );

        $config_data = array(
            'fine_type'       => $fine_type,
            'fine_amount'     => $fine_amount,
            'grace_days'      => $grace_days,
            'fine_start_date' => $fine_start_date,
            'max_fine_cap'    => $max_cap,
            'status'          => $status,
        );

        $config_formats = array( '%s', '%f', '%d', '%d', '%f', '%s' );

        if ( $existing_cfg_id > 0 ) {
            $wpdb->update( $table_late_cfg, $config_data, array( 'id' => $existing_cfg_id ), $config_formats, array( '%d' ) );
        } else {
            $wpdb->insert( $table_late_cfg, $config_data, $config_formats );
        }
        // phpcs:enable

        if ( function_exists( 'educore_log_activity' ) ) {
            educore_log_activity( __( 'Updated late fee fine automation rules.', 'ifsedu-school-management' ) );
        }

        $notice_message = esc_html__( 'Late fee fine automation rules saved successfully.', 'ifsedu-school-management' );
    }

    // Delete Single Fee Item
    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    if ( isset( $_GET['action'] ) && 'delete_fee_type' === $_GET['action'] && isset( $_GET['id'] ) ) {
        $del_id    = absint( $_GET['id'] );
        $del_nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
        if ( $del_id > 0 && wp_verify_nonce( $del_nonce, 'delete_fee_type_' . $del_id ) ) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->delete( $table_fee_types, array( 'id' => $del_id ), array( '%d' ) );
            // phpcs:enable

            if ( function_exists( 'educore_log_activity' ) ) {
                /* translators: %d: Item ID */
                educore_log_activity( sprintf( __( 'Deleted fee type ID #%d', 'ifsedu-school-management' ), $del_id ) );
            }

            $notice_message = esc_html__( 'Fee item deleted successfully.', 'ifsedu-school-management' );
        }
    }
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    // --------------------------------------------------------------------------
    // 2. DATA QUERIES
    // --------------------------------------------------------------------------
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $raw_classes = $wpdb->get_col( "SELECT DISTINCT class_name FROM `{$table_units}` WHERE class_name != '' ORDER BY CAST(class_name AS UNSIGNED) ASC, class_name ASC" );
    // phpcs:enable

    $academic_classes = array();
    if ( ! empty( $raw_classes ) && is_array( $raw_classes ) ) {
        $academic_classes = array_values( array_unique( $raw_classes ) );
        usort( $academic_classes, 'strnatcasecmp' );
    }

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    $all_fee_types = $wpdb->get_results( "SELECT * FROM `{$table_fee_types}` ORDER BY CAST(class_name AS UNSIGNED) ASC, class_name ASC, fee_title ASC" );
    $late_config   = $wpdb->get_row( "SELECT * FROM `{$table_late_cfg}` LIMIT 1" );
    // phpcs:enable
    ?>

    <div class="ifs-fees-settings-container">

        <?php if ( ! empty( $notice_message ) ) : ?>
            <div class="ifs-fees-alert-box">
                <span class="dashicons dashicons-yes-alt ifs-fees-alert-icon"></span>
                <?php echo esc_html( $notice_message ); ?>
            </div>
        <?php endif; ?>
        
        <!-- Section Toggle Navigation Pills -->
        <div class="ifs-fees-nav-pills">
            <a href="<?php echo esc_url( add_query_arg( 'section', 'structure', $subtab_base_url ) ); ?>" class="ifs-fees-nav-pill <?php echo 'structure' === $active_section ? 'is-active' : ''; ?>">
                <span class="dashicons dashicons-money-alt"></span>
                <?php esc_html_e( 'Class Fee Structure', 'ifsedu-school-management' ); ?>
            </a>
            <a href="<?php echo esc_url( add_query_arg( 'section', 'late_fine', $subtab_base_url ) ); ?>" class="ifs-fees-nav-pill <?php echo 'late_fine' === $active_section ? 'is-active' : ''; ?>">
                <span class="dashicons dashicons-clock"></span>
                <?php esc_html_e( 'Late Fine Rules', 'ifsedu-school-management' ); ?>
            </a>
        </div>

        <?php if ( 'late_fine' === $active_section ) : ?>
            <!-- SECTION 2: LATE FINE AUTOMATION -->
            <div class="ifs-fees-card">
                <h4 class="ifs-fees-card-title">
                    <span class="dashicons dashicons-clock ifs-fees-card-title-icon"></span>
                    <?php esc_html_e( 'Automated Late Fine Rules & Surcharge Criteria', 'ifsedu-school-management' ); ?>
                </h4>

                <form method="POST" action="">
                    <?php wp_nonce_field( 'educore_save_late_fine_action', 'educore_late_fine_nonce' ); ?>

                    <div class="ifs-fees-grid-layout">
                        <div>
                            <label class="ifs-fees-label"><?php esc_html_e( 'Fine Calculation Type', 'ifsedu-school-management' ); ?></label>
                            <select name="fine_type" class="ifs-fees-select">
                                <option value="Fixed" <?php selected( $late_config->fine_type ?? 'Fixed', 'Fixed' ); ?>><?php esc_html_e( 'Fixed Fine (Per Overdue Bill)', 'ifsedu-school-management' ); ?></option>
                                <option value="Daily" <?php selected( $late_config->fine_type ?? 'Fixed', 'Daily' ); ?>><?php esc_html_e( 'Daily Accruing Fine (Per Day)', 'ifsedu-school-management' ); ?></option>
                                <option value="Percentage" <?php selected( $late_config->fine_type ?? 'Fixed', 'Percentage' ); ?>><?php esc_html_e( 'Percentage of Due (%)', 'ifsedu-school-management' ); ?></option>
                            </select>
                        </div>

                        <div>
                            <label class="ifs-fees-label"><?php esc_html_e( 'Fine Rate (৳ or %)', 'ifsedu-school-management' ); ?></label>
                            <input type="number" step="0.01" min="0" name="fine_amount" class="ifs-fees-input" value="<?php echo esc_attr( $late_config->fine_amount ?? '50.00' ); ?>">
                        </div>

                        <div>
                            <label class="ifs-fees-label"><?php esc_html_e( 'Billing Cut-off Date (Day of Month)', 'ifsedu-school-management' ); ?></label>
                            <select name="fine_start_date" class="ifs-fees-select">
                                <?php 
                                $selected_day = isset( $late_config->fine_start_date ) ? absint( $late_config->fine_start_date ) : 12;
                                for ( $d = 1; $d <= 31; $d++ ) {
                                    echo '<option value="' . esc_attr( $d ) . '" ' . selected( $selected_day, $d, false ) . '>' .
                                        esc_html( sprintf( __( 'Every Month %dth', 'ifsedu-school-management' ), $d ) ) .
                                        '</option>';
                                }
                                ?>
                            </select>
                        </div>

                        <div>
                            <label class="ifs-fees-label"><?php esc_html_e( 'Grace Period (Days Allowed)', 'ifsedu-school-management' ); ?></label>
                            <input type="number" min="0" name="grace_days" class="ifs-fees-input" value="<?php echo esc_attr( $late_config->grace_days ?? '5' ); ?>">
                        </div>

                        <div>
                            <label class="ifs-fees-label"><?php esc_html_e( 'Maximum Fine Cap (৳ 0 = Unlimited)', 'ifsedu-school-management' ); ?></label>
                            <input type="number" step="0.01" min="0" name="max_fine_cap" class="ifs-fees-input" value="<?php echo esc_attr( $late_config->max_fine_cap ?? '500.00' ); ?>">
                        </div>

                        <div>
                            <label class="ifs-fees-label"><?php esc_html_e( 'Rule Status', 'ifsedu-school-management' ); ?></label>
                            <select name="fine_status" class="ifs-fees-select">
                                <option value="Active" <?php selected( $late_config->status ?? 'Active', 'Active' ); ?>><?php esc_html_e( 'Active (Apply Penalty)', 'ifsedu-school-management' ); ?></option>
                                <option value="Inactive" <?php selected( $late_config->status ?? 'Active', 'Inactive' ); ?>><?php esc_html_e( 'Inactive (Disabled)', 'ifsedu-school-management' ); ?></option>
                            </select>
                        </div>
                    </div>

                    <button type="submit" name="save_late_fine_config" class="ifs-fees-btn-save">
                        <span class="dashicons dashicons-saved"></span>
                        <?php esc_html_e( 'Save Late Fine Rules', 'ifsedu-school-management' ); ?>
                    </button>
                </form>
            </div>

        <?php else : ?>
            <!-- SECTION 1: FEE STRUCTURE SETUP & DIRECTORY -->
            <div class="ifs-fees-card">
                <h4 class="ifs-fees-card-title">
                    <span class="dashicons dashicons-money-alt ifs-fees-card-title-icon"></span>
                    <?php esc_html_e( 'Class-wise Standard Fee Structure Configuration', 'ifsedu-school-management' ); ?>
                </h4>

                <form method="POST" action="">
                    <?php wp_nonce_field( 'educore_save_fees_settings_action', 'educore_fees_settings_nonce' ); ?>

                    <div class="ifs-fees-select-class-wrap">
                        <label class="ifs-fees-label"><?php esc_html_e( 'Select Target Class', 'ifsedu-school-management' ); ?> <span class="ifs-fees-required-star">*</span></label>
                        <select name="target_class" id="ifs_target_class_select" class="ifs-fees-select" required>
                            <option value=""><?php esc_html_e( '-- Choose Class --', 'ifsedu-school-management' ); ?></option>
                            <?php foreach ( $academic_classes as $cls_name ) : ?>
                                <option value="<?php echo esc_attr( $cls_name ); ?>"><?php echo esc_html( $cls_name ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div id="ifs_fee_repeater_canvas">
                        <div class="ifs-fees-repeater-row">
                            <div>
                                <label class="ifs-fees-label"><?php esc_html_e( 'Fee Title / Particulars', 'ifsedu-school-management' ); ?> <span class="ifs-fees-required-star">*</span></label>
                                <input type="text" name="fee_title[]" class="ifs-fees-input" placeholder="<?php esc_attr_e( 'e.g. Monthly Tuition Fee / Exam Fee', 'ifsedu-school-management' ); ?>" required>
                            </div>
                            <div>
                                <label class="ifs-fees-label"><?php esc_html_e( 'Amount (৳)', 'ifsedu-school-management' ); ?> <span class="ifs-fees-required-star">*</span></label>
                                <input type="number" step="0.01" min="0" name="amount[]" class="ifs-fees-input" placeholder="0.00" required>
                            </div>
                            <div>
                                <label class="ifs-fees-label"><?php esc_html_e( 'Billing Cycle', 'ifsedu-school-management' ); ?></label>
                                <select name="period_type[]" class="ifs-fees-select">
                                    <option value="Monthly"><?php esc_html_e( 'Monthly', 'ifsedu-school-management' ); ?></option>
                                    <option value="Term/Exam"><?php esc_html_e( 'Term / Exam-wise', 'ifsedu-school-management' ); ?></option>
                                    <option value="Annual/Admission"><?php esc_html_e( 'Annual / Admission', 'ifsedu-school-management' ); ?></option>
                                    <option value="One-Time"><?php esc_html_e( 'One-Time / Miscellaneous', 'ifsedu-school-management' ); ?></option>
                                </select>
                            </div>
                            <div>
                                <button type="button" class="ifs-fees-btn-remove btn-remove-row" title="<?php esc_attr_e( 'Remove', 'ifsedu-school-management' ); ?>">
                                    <span class="dashicons dashicons-no-alt"></span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="ifs-fees-actions-bar">
                        <button type="button" id="ifs_add_fee_row_btn" class="ifs-fees-btn-add">
                            <span class="dashicons dashicons-plus-alt2"></span>
                            <?php esc_html_e( 'Add Another Item', 'ifsedu-school-management' ); ?>
                        </button>

                        <button type="submit" name="save_class_fee_structure" class="ifs-fees-btn-save">
                            <span class="dashicons dashicons-saved"></span>
                            <?php esc_html_e( 'Save Fee Structure', 'ifsedu-school-management' ); ?>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Directory Table Card -->
            <div class="ifs-fees-card">
                <div class="ifs-fees-directory-header">
                    <div class="ifs-fees-directory-count">
                        <?php echo count( $all_fee_types ); ?> <?php esc_html_e( 'Fee Items Configured', 'ifsedu-school-management' ); ?>
                    </div>
                    <input type="text" id="ifs_fee_filter_input" class="ifs-fees-input ifs-fees-search-input" placeholder="<?php esc_attr_e( 'Search class or fee item...', 'ifsedu-school-management' ); ?>">
                </div>

                <div class="ifs-fees-table-container">
                    <table class="ifs-fees-table" id="ifs_fees_directory_table">
                        <thead>
                            <tr>
                                <th style="width: 22%;"><?php esc_html_e( 'Class', 'ifsedu-school-management' ); ?></th>
                                <th style="width: 38%;"><?php esc_html_e( 'Fee Particulars', 'ifsedu-school-management' ); ?></th>
                                <th style="width: 18%;"><?php esc_html_e( 'Amount', 'ifsedu-school-management' ); ?></th>
                                <th style="width: 14%;"><?php esc_html_e( 'Cycle', 'ifsedu-school-management' ); ?></th>
                                <th style="width: 8%; text-align: right;"><?php esc_html_e( 'Action', 'ifsedu-school-management' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ( ! empty( $all_fee_types ) ) : foreach ( $all_fee_types as $item ) : 
                                $del_id = absint( $item->id );
                                $del_link = wp_nonce_url(
                                    add_query_arg(
                                        array(
                                            'action' => 'delete_fee_type',
                                            'id'     => $del_id,
                                        ),
                                        $subtab_base_url
                                    ),
                                    'delete_fee_type_' . $del_id
                                );
                            ?>
                                <tr class="fee-row" data-search="<?php echo esc_attr( strtolower( (string) ( $item->class_name . ' ' . $item->fee_title ) ) ); ?>">
                                    <td><strong class="ifs-fees-class-strong"><?php echo esc_html( $item->class_name ); ?></strong></td>
                                    <td><strong class="ifs-fees-title-strong"><?php echo esc_html( $item->fee_title ); ?></strong></td>
                                    <td><span class="ifs-fees-amount-badge">৳<?php echo esc_html( number_format( floatval( $item->amount ), 2 ) ); ?></span></td>
                                    <td><span class="ifs-fees-cycle-badge"><?php echo esc_html( $item->period_type ); ?></span></td>
                                    <td class="ifs-fees-action-cell">
                                        <a href="<?php echo esc_url( $del_link ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Remove this fee item?', 'ifsedu-school-management' ) ); ?>');" class="ifs-fees-delete-action" title="<?php esc_attr_e( 'Delete Fee Item', 'ifsedu-school-management' ); ?>">
                                            <span class="dashicons dashicons-trash ifs-fees-delete-icon"></span>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; else : ?>
                                <tr>
                                    <td colspan="5" class="ifs-fees-empty-cell">
                                        <?php esc_html_e( 'No fee structure configured yet.', 'ifsedu-school-management' ); ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

    </div>

    <!-- Client-Side Script -->
    <script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function() {
        var canvas = document.getElementById('ifs_fee_repeater_canvas');
        var addBtn = document.getElementById('ifs_add_fee_row_btn');
        var filterInput = document.getElementById('ifs_fee_filter_input');

        if (addBtn && canvas) {
            addBtn.addEventListener('click', function() {
                var rows = canvas.querySelectorAll('.ifs-fees-repeater-row');
                if (rows.length > 0) {
                    var newRow = rows[0].cloneNode(true);
                    newRow.querySelectorAll('input').forEach(function(inp) {
                        inp.value = '';
                    });
                    var selectEl = newRow.querySelector('select');
                    if (selectEl) {
                        selectEl.selectedIndex = 0;
                    }
                    canvas.appendChild(newRow);
                }
            });

            canvas.addEventListener('click', function(e) {
                var removeBtn = e.target.closest('.btn-remove-row');
                if (removeBtn) {
                    var rows = canvas.querySelectorAll('.ifs-fees-repeater-row');
                    if (rows.length > 1) {
                        removeBtn.closest('.ifs-fees-repeater-row').remove();
                    } else {
                        alert('<?php echo esc_js( __( 'At least one fee row is required.', 'ifsedu-school-management' ) ); ?>');
                    }
                }
            });
        }

        if (filterInput) {
            filterInput.addEventListener('input', function() {
                var q = this.value.toLowerCase().trim();
                document.querySelectorAll('.fee-row').forEach(function(r) {
                    var text = r.getAttribute('data-search') || '';
                    r.style.display = (!q || text.indexOf(q) !== -1) ? '' : 'none';
                });
            });
        }
    });
    </script>
    <?php
}
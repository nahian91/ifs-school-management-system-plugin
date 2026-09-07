<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Direct access safety buffer
}

/**
 * 1. MAIN RENDER FUNCTION FOR THE FINANCE TAB
 */
function educore_arms_finance_tab() {
    global $wpdb;
    $table_expenses = $wpdb->prefix . 'arms_expenses';
    $security_nonce = wp_create_nonce( 'arms_finance_secure_nonce' );

    // Fetch live entries from database logs
    $expenses_log = array();
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_expenses ) ) === $table_expenses ) {
        $expenses_log = $wpdb->get_results( "SELECT * FROM `{$table_expenses}` ORDER BY id DESC", ARRAY_A );
    }
    // phpcs:enable

    $fixed_lease_total       = 0;
    $utility_matrix_total    = 0;
    $pending_outflow_total   = 0;

    if ( ! empty( $expenses_log ) ) {
        foreach ( $expenses_log as $row ) {
            $amt = floatval( $row['total_amount'] );
            if ( $row['expense_category'] === 'operational' && $row['expense_type'] === 'rent' ) {
                $fixed_lease_total += $amt;
            } elseif ( $row['expense_category'] === 'utility' ) {
                $utility_matrix_total += $amt;
            } else {
                $pending_outflow_total += $amt;
            }
        }
    }
    ?>

    <div class="arms-fin-wrapper">

        <div class="arms-fin-nav">
            <button type="button" class="arms-fin-btn active" id="btn-fin-expenses" onclick="armsSwitchFinTab('fin-expenses')">Expenses</button>
        </div>

        <div id="fin-expenses" class="arms-fin-panel active">
            <div class="arms-sub-nav-tabs">
                <button type="button" class="arms-sub-tab-btn active" id="btn-sub-exp-list" onclick="armsSwitchSubExpenseTab('sub-exp-list')">📋 Expense Ledger Log</button>
                <button type="button" class="arms-sub-tab-btn" id="btn-sub-exp-add" onclick="armsSwitchSubExpenseTab('sub-exp-add')">➕ Add Expense Allocation</button>
            </div>

            <div id="sub-exp-list" class="arms-form-matrix-block active">
                <div class="arms-stat-grid">
                    <div class="arms-stat-card"><span class="arms-stat-label">Rent & Lease</span><span class="arms-stat-val" id="kpi-fixed-lease">৳<?php echo esc_html( number_format( $fixed_lease_total, 2 ) ); ?></span></div>
                    <div class="arms-stat-card"><span class="arms-stat-label">Utilities</span><span class="arms-stat-val" id="kpi-utility-matrix">৳<?php echo esc_html( number_format( $utility_matrix_total, 2 ) ); ?></span></div>
                    <div class="arms-stat-card danger"><span class="arms-stat-label">Other Expenses</span><span class="arms-stat-val" id="kpi-pending-outflow">৳<?php echo esc_html( number_format( $pending_outflow_total, 2 ) ); ?></span></div>
                </div>

                <div class="arms-table-toolbar">
                    <div class="arms-filter-group">
                        <label>Category:</label>
                        <select class="arms-select-field arms-expense-filter-cat" onchange="armsFilterExpenseTable()">
                            <option value="">All Categories</option>
                            <option value="salary">Salary Matrix</option>
                            <option value="utility">Utility Matrix</option>
                            <option value="operational">Operational Overhead</option>
                        </select>
                    </div>
                    <div class="arms-filter-group">
                        <label>From Date:</label>
                        <input type="date" class="arms-input-field arms-expense-filter-start" onchange="armsFilterExpenseTable()" />
                    </div>
                    <div class="arms-filter-group">
                        <label>To Date:</label>
                        <input type="date" class="arms-input-field arms-expense-filter-end" onchange="armsFilterExpenseTable()" />
                    </div>
                </div>

                <div class="arms-table-wrapper">
                    <table class="arms-data-table" id="arms-expenses-log-table">
                        <thead>
                            <tr>
                                <th>Expense Type</th>
                                <th>System Code</th>
                                <th>Category</th>
                                <th>Total Amount</th>
                                <th>Date</th>
                                <th style="text-align: center; width: 140px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ( ! empty( $expenses_log ) ) : ?>
                                <?php foreach ( $expenses_log as $row ) : ?>
                                    <tr data-id="<?php echo intval( $row['id'] ); ?>" 
                                        data-category="<?php echo esc_attr( $row['expense_category'] ); ?>" 
                                        data-type="<?php echo esc_attr( $row['expense_type'] ); ?>"
                                        data-month="<?php echo esc_attr( $row['target_month'] ); ?>"
                                        data-year="<?php echo esc_attr( $row['target_year'] ); ?>"
                                        data-base="<?php echo esc_attr( $row['base_amount'] ); ?>"
                                        data-adjustment="<?php echo esc_attr( $row['adjustment_amount'] ); ?>"
                                        data-auth="<?php echo esc_attr( $row['authorized_by'] ); ?>"
                                        data-date="<?php echo esc_attr( $row['transaction_date'] ); ?>">
                                        <td><b><?php echo esc_html( ucfirst( $row['expense_type'] ) ); ?></b></td>
                                        <td><code>EXP-<?php echo esc_html( strtoupper( substr( $row['expense_category'], 0, 3 ) ) ); ?></code></td>
                                        <td><?php echo esc_html( ucfirst( $row['expense_category'] ) ); ?> Matrix</td>
                                        <td class="row-total-amount">৳<?php echo esc_html( number_format( floatval( $row['total_amount'] ), 2 ) ); ?></td>
                                        <td><?php echo esc_html( $row['transaction_date'] ); ?></td>
                                        <td>
                                            <div class="arms-actions-cell">
                                                <button type="button" class="arms-action-btn edit" onclick="armsEditExpenseRow(this)">Edit</button>
                                                <button type="button" class="arms-action-btn delete" onclick="armsRowAction('delete', <?php echo intval( $row['id'] ); ?>)">Delete</button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <tr class="no-records-row"><td colspan="6" style="text-align:center; color:#94a3b8;">No records saved yet. Add fields through the form array matrix.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="sub-exp-add" class="arms-form-matrix-block">
                <div style="margin-bottom: 16px;">
                    <label class="arms-label-inline" style="display:block; margin-bottom:6px;">Select Core Expense Matrix Category</label>
                    <select class="arms-select-field" id="arms-main-expense-category" style="width:100%; max-width:320px;" onchange="armsRenderExpenseFormFields(this.value)">
                        <option value="salary">Salary Matrix</option>
                        <option value="utility">Utility Matrix</option>
                        <option value="operational">Operational Overhead Matrix</option>
                    </select>
                </div>

                <form method="post" action="" id="arms-expense-form">
                    <input type="hidden" id="arms-expense-row-id" value="0" />
                    
                    <div id="ctx-fields-salary" class="arms-form-fields-context">
                        <div class="arms-form-grid-layout">
                            <div class="arms-form-element-group">
                                <label>Staff Category Type</label>
                                <select class="arms-data-type arms-select-field" id="arms-staff-role-selector">
                                    <option value="doctor">Doctor</option>
                                    <option value="physio">Physio</option>
                                    <option value="nurse">Nurse</option>
                                    <option value="staff">Staff</option>
                                </select>
                            </div>
                            <div class="arms-form-element-group" id="arms-staff-profile-wrapper">
                                <label>Select Employee Profile</label>
                                <select class="arms-select-field" id="arms-staff-profile-dropdown" name="authorized_by_staff_sync">
                                    <option value="">-- Select Personnel Profile --</option>
                                </select>
                            </div>
                            <div class="arms-form-element-group">
                                <label>Target Month</label>
                                <select class="arms-data-month arms-select-field">
                                    <option value="January">January</option><option value="February">February</option><option value="March">March</option>
                                    <option value="April">April</option><option value="May">May</option><option value="June" selected>June</option>
                                    <option value="July">July</option><option value="August">August</option><option value="September">September</option>
                                    <option value="October">October</option><option value="November">November</option><option value="December">December</option>
                                </select>
                            </div>
                            <div class="arms-form-element-group">
                                <label>Target Accounting Fiscal Year</label>
                                <select class="arms-data-year arms-select-field">
                                    <option value="2026" selected>2026</option>
                                    <option value="2027">2027</option>
                                </select>
                            </div>
                            <div class="arms-form-element-group">
                                <label>Base Line Net Amount (৳)</label>
                                <input type="number" step="0.01" placeholder="0.00" class="arms-data-base arms-input-field" id="arms-salary-base-input" required />
                            </div>
                            <div class="arms-form-element-group">
                                <label>Bonus Adjustments (৳)</label>
                                <input type="number" step="0.01" placeholder="0.00" class="arms-data-adjustment arms-input-field" />
                            </div>
                            <div class="arms-form-element-group" style="flex-direction:row; gap:8px;">
                                <button type="submit" class="arms-submit-btn" id="arms-salary-submit-text">Post Salary Ledger</button>
                                <button type="button" class="arms-cancel-btn" id="arms-salary-cancel-btn" style="display:none;" onclick="armsResetExpenseForm()">Cancel</button>
                            </div>
                        </div>
                    </div>

                    <div id="ctx-fields-utility" class="arms-form-fields-context" style="display:none;">
                        <div class="arms-form-grid-layout">
                            <div class="arms-form-element-group">
                                <label>Infrastructure Utility Type</label>
                                <select class="arms-data-type arms-select-field">
                                    <option value="electricity">Electricity</option>
                                    <option value="internet">Internet</option>
                                    <option value="water">Water</option>
                                </select>
                            </div>
                            <div class="arms-form-element-group">
                                <label>Billing Period Month</label>
                                <select class="arms-data-month arms-select-field">
                                    <option value="January">January</option><option value="February">February</option><option value="March">March</option>
                                    <option value="April">April</option><option value="May">May</option><option value="June" selected>June</option>
                                    <option value="July">July</option><option value="August">August</option><option value="September">September</option>
                                    <option value="October">October</option><option value="November">November</option><option value="December">December</option>
                                </select>
                            </div>
                            <div class="arms-form-element-group">
                                <label>Aggregated Meter Amount (৳)</label>
                                <input type="number" step="0.01" placeholder="0.00" class="arms-data-base arms-input-field" />
                            </div>
                            <div class="arms-form-element-group">
                                <label>Posting Transaction Date</label>
                                <input type="date" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" class="arms-data-date arms-input-field" />
                            </div>
                            <div class="arms-form-element-group" style="flex-direction:row; gap:8px;">
                                <button type="submit" class="arms-submit-btn" id="arms-utility-submit-text">Post Utility Ledger</button>
                                <button type="button" class="arms-cancel-btn" id="arms-utility-cancel-btn" style="display:none;" onclick="armsResetExpenseForm()">Cancel</button>
                            </div>
                        </div>
                    </div>

                    <div id="ctx-fields-operational" class="arms-form-fields-context" style="display:none;">
                        <div class="arms-form-grid-layout">
                            <div class="arms-form-element-group">
                                <label>Operational Cost Allocation Line</label>
                                <select class="arms-data-type arms-select-field">
                                    <option value="rent">Rent</option>
                                    <option value="marketing">Marketing</option>
                                    <option value="equipment">Equipment purchase</option>
                                    <option value="consumables">Consumables</option>
                                </select>
                            </div>
                            <div class="arms-form-element-group">
                                <label>Authorized Initiated By</label>
                                <input type="text" placeholder="Procurement Officer" class="arms-data-auth arms-input-field" />
                            </div>
                            <div class="arms-form-element-group">
                                <label>Gross Allocation Amount (৳)</label>
                                <input type="number" step="0.01" placeholder="0.00" class="arms-data-base arms-input-field" />
                            </div>
                            <div class="arms-form-element-group">
                                <label>Invoice Transaction Date</label>
                                <input type="date" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" class="arms-data-date arms-input-field" />
                            </div>
                            <div class="arms-form-element-group" style="flex-direction:row; gap:8px;">
                                <button type="submit" class="arms-submit-btn" id="arms-operational-submit-text">Post Operational Ledger</button>
                                <button type="button" class="arms-cancel-btn" id="arms-operational-cancel-btn" style="display:none;" onclick="armsResetExpenseForm()">Cancel</button>
                            </div>
                        </div>
                    </div>

                </form>
            </div>
        </div>

    </div>

    <script type="text/javascript">
        var arms_fin_meta = { 
            nonce: '<?php echo esc_js( $security_nonce ); ?>',
            ajaxurl: '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>',
            current_date: '<?php echo esc_js( current_time( 'Y-m-d' ) ); ?>'
        };

        window.armsSwitchFinTab = function(panelId) {
            document.querySelectorAll('.arms-fin-panel').forEach(function(panel) {
                panel.classList.remove('active');
            });
            document.querySelectorAll('.arms-fin-btn').forEach(function(btn) {
                btn.classList.remove('active');
            });

            var selectedPanel = document.getElementById(panelId);
            var selectedBtn = document.getElementById('btn-' + panelId);

            if (selectedPanel && selectedBtn) {
                selectedPanel.classList.add('active');
                selectedBtn.classList.add('active');
            }
        };

        window.armsSwitchSubExpenseTab = function(subPanelId) {
            document.querySelectorAll('#fin-expenses .arms-form-matrix-block').forEach(function(block) {
                block.classList.remove('active');
            });
            document.querySelectorAll('#fin-expenses .arms-sub-tab-btn').forEach(function(btn) {
                btn.classList.remove('active');
            });

            var selectedSubPanel = document.getElementById(subPanelId);
            var selectedSubBtn = document.getElementById('btn-' + subPanelId);

            if (selectedSubPanel && selectedSubBtn) {
                selectedSubPanel.classList.add('active');
                selectedSubBtn.classList.add('active');
            }
        };

        // Helper promise function to reload staff rows safely asynchronously
        window.armsFetchStaffOptionsPromise = function(chosenRole) {
            return jQuery.ajax({
                url: arms_fin_meta.ajaxurl,
                type: 'POST',
                data: {
                    action: 'educore_arms_load_staff_by_role',
                    nonce: arms_fin_meta.nonce,
                    role_category: chosenRole
                }
            });
        };

        window.armsRenderExpenseFormFields = function(targetCategory) {
            document.querySelectorAll('.arms-form-fields-context').forEach(function(ctxBlock) {
                ctxBlock.style.display = 'none';
                jQuery(ctxBlock).find('.arms-input-field, .arms-select-field').removeAttr('required');
            });
            
            var targetedContextBlock = document.getElementById('ctx-fields-' + targetCategory);
            if (targetedContextBlock) {
                targetedContextBlock.style.display = 'block';
                jQuery(targetedContextBlock).find('.arms-data-base').attr('required', 'required');
                
                if (targetCategory === 'salary' && jQuery('#arms-expense-row-id').val() == '0') {
                    jQuery('#arms-staff-role-selector').trigger('change');
                }
            }
        };

        window.armsFilterExpenseTable = function() {
            var category = jQuery('.arms-expense-filter-cat').val();
            var start = jQuery('.arms-expense-filter-start').val();
            var end = jQuery('.arms-expense-filter-end').val();

            jQuery('#arms-expenses-log-table tbody tr').each(function() {
                var $row = jQuery(this);
                if($row.hasClass('no-records-row')) return;
                
                var rowCat = $row.attr('data-category');
                var rowDate = $row.attr('data-date');
                var match = true;

                if (category && rowCat !== category) match = false;
                if (start && rowDate < start) match = false;
                if (end && rowDate > end) match = false;

                if (match) $row.show(); else $row.hide();
            });
        };

        window.armsEditExpenseRow = function(btn) {
            var $row = jQuery(btn).closest('tr');
            var rowId = $row.attr('data-id');
            var category = $row.attr('data-category');
            var staffType = $row.attr('data-type');
            var storedName = $row.attr('data-auth');
            
            jQuery('#arms-expense-row-id').val(rowId);
            jQuery('#arms-main-expense-category').val(category).prop('disabled', true);
            
            // Show fields
            armsRenderExpenseFormFields(category);
            var $ctx = jQuery('#ctx-fields-' + category);
            
            if (category === 'salary') {
                $ctx.find('#arms-staff-role-selector').val(staffType);
                
                // Fetch dynamic elements synchronously via promise before choosing employee name text
                window.armsFetchStaffOptionsPromise(staffType).done(function(response) {
                    var dropdown = jQuery('#arms-staff-profile-dropdown');
                    if (response.success && response.data.length > 0) {
                        var dropdownOptions = '<option value="">-- Select Personnel Profile --</option>';
                        jQuery.each(response.data, function(idx, staff) {
                            dropdownOptions += '<option value="' + staff.id + '" data-salary="' + staff.salary + '"' + (staff.display_name === storedName ? ' selected' : '') + '>' + staff.display_name + '</option>';
                        });
                        dropdown.html(dropdownOptions);
                    } else {
                        dropdown.html('<option value="">No matching personnel found</option>');
                    }
                });
            }

            $ctx.find('.arms-data-month').val($row.attr('data-month'));
            $ctx.find('.arms-data-year').val($row.attr('data-year'));
            $ctx.find('.arms-data-base').val($row.attr('data-base'));
            $ctx.find('.arms-data-adjustment').val($row.attr('data-adjustment'));
            $ctx.find('.arms-data-auth').val(storedName);
            $ctx.find('.arms-data-date').val($row.attr('data-date'));
            
            jQuery('#arms-' + category + '-submit-text').text('Update Ledger Record');
            jQuery('#arms-' + category + '-cancel-btn').show();
            
            jQuery('#btn-sub-exp-add').text('📝 Edit Expense Record');
            armsSwitchSubExpenseTab('sub-exp-add');
        };

        window.armsResetExpenseForm = function() {
            jQuery('#arms-expense-form')[0].reset();
            jQuery('#arms-expense-row-id').val('0');
            jQuery('#arms-main-expense-category').prop('disabled', false);
            jQuery('#arms-staff-profile-dropdown').html('<option value="">-- Select Personnel Profile --</option>');
            
            document.querySelectorAll('.arms-data-date').forEach(function(el) {
                el.value = arms_fin_meta.current_date;
            });
            
            jQuery('.arms-submit-btn').each(function() {
                var cat = jQuery(this).attr('id').split('-')[1];
                jQuery(this).text('Post ' + cat.charAt(0).toUpperCase() + cat.slice(1) + ' Ledger');
            });
            jQuery('.arms-cancel-btn').hide();
            jQuery('#btn-sub-exp-add').text('➕ Add Expense Allocation');
            
            armsRenderExpenseFormFields(jQuery('#arms-main-expense-category').val());
        };

        window.armsRowAction = function(actionType, rowId) {
            if (actionType === 'delete') {
                if (confirm('Are you absolutely sure you want to delete row log entry ID: ' + rowId + '?')) {
                    var $row = jQuery('#arms-expenses-log-table tbody tr[data-id="' + rowId + '"]');
                    var category = $row.attr('data-category');
                    var type = $row.attr('data-type');
                    var amt = parseFloat($row.find('.row-total-amount').text().replace(/[^0-9.-]+/g,"")) || 0;

                    if (category === 'operational' && type === 'rent') {
                        var currentFixed = parseFloat(jQuery('#kpi-fixed-lease').text().replace(/[^0-9.-]+/g,"")) || 0;
                        jQuery('#kpi-fixed-lease').text('৳' + Math.max(0, currentFixed - amt).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
                    } else if (category === 'utility') {
                        var currentUtil = parseFloat(jQuery('#kpi-utility-matrix').text().replace(/[^0-9.-]+/g,"")) || 0;
                        jQuery('#kpi-utility-matrix').text('৳' + Math.max(0, currentUtil - amt).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
                    } else {
                        var currentOther = parseFloat(jQuery('#kpi-pending-outflow').text().replace(/[^0-9.-]+/g,"")) || 0;
                        jQuery('#kpi-pending-outflow').text('৳' + Math.max(0, currentOther - amt).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
                    }

                    $row.fadeOut('slow', function() {
                        jQuery(this).remove();
                        if (jQuery('#arms-expenses-log-table tbody tr').length === 0) {
                            jQuery('#arms-expenses-log-table tbody').append('<tr class="no-records-row"><td colspan="6" style="text-align:center; color:#94a3b8;">No records saved yet. Add fields through the form array matrix.</td></tr>');
                        }
                    });
                }
            }
        };

        jQuery(document).ready(function($) {
            armsRenderExpenseFormFields($('#arms-main-expense-category').val());

            // Dynamic employee loading on clean manual category switch
            $('#arms-staff-role-selector').on('change', function() {
                var chosenRole = $(this).val();
                var dropdown = $('#arms-staff-profile-dropdown');
                dropdown.html('<option value="">-- Loading Employee Profiles --</option>');
                
                window.armsFetchStaffOptionsPromise(chosenRole).done(function(response) {
                    if (response.success && response.data.length > 0) {
                        var dropdownOptions = '<option value="">-- Select Personnel Profile --</option>';
                        $.each(response.data, function(idx, staff) {
                            dropdownOptions += '<option value="' + staff.id + '" data-salary="' + staff.salary + '">' + staff.display_name + '</option>';
                        });
                        dropdown.html(dropdownOptions);
                    } else {
                        dropdown.html('<option value="">No registered employees for this role</option>');
                    }
                }).fail(function(){
                    dropdown.html('<option value="">Database communication error</option>');
                });
            });

            // Sync contractual salary input field box on selection
            $('#arms-staff-profile-dropdown').on('change', function() {
                var chosenProfile = $(this).find('option:selected');
                var salaryBaseline = chosenProfile.data('salary');
                if (salaryBaseline) {
                    $('#arms-salary-base-input').val(parseFloat(salaryBaseline).toFixed(2));
                } else {
                    $('#arms-salary-base-input').val('');
                }
            });

            $('#arms-expense-form').on('submit', function(e) {
                e.preventDefault();
                
                var $form = $(this);
                var activeCategory = $('#arms-main-expense-category').val();
                var $ctx = $('#ctx-fields-' + activeCategory);
                
                // Get display name string if Category is Salary, otherwise pull raw text field
                var authorizedValue = $ctx.find('.arms-data-auth').val() || '';
                if (activeCategory === 'salary') {
                    authorizedValue = $('#arms-staff-profile-dropdown option:selected').text();
                }

                var payload = {
                    action: 'educore_arms_save_finance_expense',
                    nonce: arms_fin_meta.nonce,
                    id: $('#arms-expense-row-id').val(),
                    expense_category: activeCategory,
                    expense_type: $ctx.find('.arms-data-type').val(),
                    target_month: $ctx.find('.arms-data-month').val() || '',
                    target_year: $ctx.find('.arms-data-year').val() || '',
                    base_amount: $ctx.find('.arms-data-base').val() || 0,
                    adjustment_amount: $ctx.find('.arms-data-adjustment').val() || 0,
                    authorized_by: authorizedValue,
                    transaction_date: $ctx.find('.arms-data-date').val() || arms_fin_meta.current_date
                };

                $form.find('.arms-submit-btn').prop('disabled', true);

                $.post(arms_fin_meta.ajaxurl, payload, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data || 'An error occurred while handling processing configurations.');
                        $form.find('.arms-submit-btn').prop('disabled', false);
                    }
                }).fail(function() {
                    alert('Server communication configuration failure.');
                    $form.find('.arms-submit-btn').prop('disabled', false);
                });
            });
        });
    </script>
    <?php
}

/**
 * 2. BACKEND AJAX HANDLER: Robust, case-insensitive mapping against wp_arms_staff
 */
add_action( 'wp_ajax_educore_arms_load_staff_by_role', 'educore_arms_load_staff_by_role_handler' );
function educore_arms_load_staff_by_role_handler() {
    check_ajax_referer( 'arms_finance_secure_nonce', 'nonce' );
    global $wpdb;

    $role_category = isset( $_POST['role_category'] ) ? sanitize_text_field( wp_unslash( $_POST['role_category'] ) ) : '';
    $table_staff   = $wpdb->prefix . 'arms_staff';

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
    if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_staff ) ) !== $table_staff ) {
        wp_send_json_error( 'Staff registry database table does not exist.' );
    }

    // LOWER() matching handles both 'doctor' and 'Doctor' entries cleanly
    $results = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, first_name, last_name, salary FROM `{$table_staff}` WHERE LOWER(role_category) = LOWER(%s) AND status = 'active' ORDER BY first_name ASC",
            $role_category
        ),
        ARRAY_A
    );
    // phpcs:enable

    $formatted_staff_profiles = array();
    if ( ! empty( $results ) ) {
        foreach ( $results as $row ) {
            $formatted_staff_profiles[] = array(
                'id'           => intval( $row['id'] ),
                'display_name' => esc_html( trim( $row['first_name'] . ' ' . $row['last_name'] ) ),
                'salary'       => floatval( $row['salary'] ),
            );
        }
    }

    wp_send_json_success( $formatted_staff_profiles );
}

/**
 * 3. BACKEND AJAX HANDLER FOR PROCESSING UPDATES AND INSERTS
 */
add_action( 'wp_ajax_educore_arms_save_finance_expense', 'educore_arms_save_finance_expense_handler' );
function educore_arms_save_finance_expense_handler() {
    check_ajax_referer( 'arms_finance_secure_nonce', 'nonce' );

    global $wpdb;
    $table_expenses = $wpdb->prefix . 'arms_expenses';

    $id                = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
    $expense_category  = isset( $_POST['expense_category'] ) ? sanitize_text_field( wp_unslash( $_POST['expense_category'] ) ) : '';
    $expense_type      = isset( $_POST['expense_type'] ) ? sanitize_text_field( wp_unslash( $_POST['expense_type'] ) ) : '';
    $target_month      = isset( $_POST['target_month'] ) ? sanitize_text_field( wp_unslash( $_POST['target_month'] ) ) : '';
    $target_year       = isset( $_POST['target_year'] ) ? sanitize_text_field( wp_unslash( $_POST['target_year'] ) ) : '';
    $base_amount       = isset( $_POST['base_amount'] ) ? floatval( $_POST['base_amount'] ) : 0.0;
    $adjustment_amount = isset( $_POST['adjustment_amount'] ) ? floatval( $_POST['adjustment_amount'] ) : 0.0;
    $authorized_by     = isset( $_POST['authorized_by'] ) ? sanitize_text_field( wp_unslash( $_POST['authorized_by'] ) ) : '';
    $transaction_date  = isset( $_POST['transaction_date'] ) ? sanitize_text_field( wp_unslash( $_POST['transaction_date'] ) ) : current_time( 'Y-m-d' );

    $total_amount = $base_amount + $adjustment_amount;

    $data = array(
        'expense_category'  => $expense_category,
        'expense_type'      => $expense_type,
        'target_month'      => $target_month,
        'target_year'       => $target_year,
        'base_amount'       => $base_amount,
        'adjustment_amount' => $adjustment_amount,
        'total_amount'      => $total_amount,
        'authorized_by'     => $authorized_by,
        'transaction_date'  => $transaction_date,
    );

    $format = array( '%s', '%s', '%s', '%s', '%f', '%f', '%f', '%s', '%s' );

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
    if ( $id > 0 ) {
        $updated = $wpdb->update( $table_expenses, $data, array( 'id' => $id ), $format, array( '%d' ) );
        if ( $updated !== false ) {
            wp_send_json_success( 'Ledger record updated successfully.' );
        } else {
            wp_send_json_error( 'Failed to update ledger record database entry.' );
        }
    } else {
        $inserted = $wpdb->insert( $table_expenses, $data, $format );
        if ( $inserted !== false ) {
            wp_send_json_success( 'Ledger record posted successfully.' );
        } else {
            wp_send_json_error( 'Failed to save ledger record entry.' );
        }
    }
    // phpcs:enable
}
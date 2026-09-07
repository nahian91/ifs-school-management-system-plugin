(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        initSidebarToggle();
        initDashboardClock();
        initAcademicHolidayCalendar();
    });

    /**
     * 1. Sidebar Collapse & Persistence
     */
    function initSidebarToggle() {
        var sidebar   = document.getElementById('educoreSidebar');
        var toggleBtn = document.getElementById('educoreToggleSidebar');

        if (!sidebar || !toggleBtn) return;

        try {
            if (localStorage.getItem('educore_sidebar_collapsed') === 'true') {
                sidebar.classList.add('collapsed');
            }
        } catch (e) {
            // Silently handle private browsing / disabled storage restrictions
        }

        toggleBtn.addEventListener('click', function() {
            var isCollapsed = sidebar.classList.toggle('collapsed');
            try {
                localStorage.setItem('educore_sidebar_collapsed', isCollapsed ? 'true' : 'false');
            } catch (e) {}
        });
    }

    /**
     * 2. Live Dashboard Clock
     */
    function initDashboardClock() {
        var clockElem = document.getElementById('educoreLiveDashboardClock');
        if (!clockElem) return;

        function updateClock() {
            var now     = new Date();
            var hours   = String(now.getHours()).padStart(2, '0');
            var minutes = String(now.getMinutes()).padStart(2, '0');
            var seconds = String(now.getSeconds()).padStart(2, '0');
            clockElem.textContent = hours + ':' + minutes + ':' + seconds;
        }

        updateClock();
        setInterval(updateClock, 1000);
    }

    /**
     * 3. Academic Holiday Calendar, Modal Engine & Segmented Switcher
     */
    function initAcademicHolidayCalendar() {
        var hiddenInput       = document.getElementById('ifs_academic_off_dates_json');
        var counterPill       = document.getElementById('ifs_total_off_days_pill');
        var dayCells          = document.querySelectorAll('.ifs-cal-day-cell:not(.is-empty)');

        var modal             = document.getElementById('ifs_holiday_modal');
        var closeModalBtn     = document.getElementById('ifs_close_holiday_modal');
        var cancelModalBtn    = document.getElementById('ifs_cancel_holiday_btn');
        var saveModalBtn      = document.getElementById('ifs_save_holiday_btn');

        var modalDateLabel    = document.getElementById('modal_display_date');
        var modalWeekdayLabel = document.getElementById('modal_display_weekday');
        var modalBadge        = document.getElementById('modal_display_badge');
        var modalStatusSelect = document.getElementById('modal_day_status');
        var modalReasonWrap   = document.getElementById('modal_reason_wrap');
        var modalReasonInput  = document.getElementById('modal_holiday_reason');

        var switchOpen        = document.getElementById('ifs_switch_open');
        var switchOff         = document.getElementById('ifs_switch_off');

        if (!hiddenInput || !modal) return;

        var activeTargetCell = null;
        var offDaysDataMap   = {};

        var i18n = window.educoreCalendarData || {
            totalOffDays:   'Total Off Days',
            defaultHoliday: 'Holiday',
            weeklyHoliday:  'Weekly Holiday',
            blockedText:    'Holiday / Blocked',
            openText:       'Open Academic Day'
        };

        try {
            offDaysDataMap = JSON.parse(hiddenInput.value || '{}');
        } catch (e) {
            offDaysDataMap = {};
        }

        function updateStorageState() {
            hiddenInput.value = JSON.stringify(offDaysDataMap);
            if (counterPill) {
                counterPill.textContent = Object.keys(offDaysDataMap).length + ' ' + i18n.totalOffDays;
            }
        }

        function syncSegmentedSwitch(status) {
            if (status === 'off') {
                if (switchOff) switchOff.classList.add('is-active-off');
                if (switchOpen) switchOpen.classList.remove('is-active-open');
                if (modalReasonWrap) modalReasonWrap.style.display = 'block';
                if (modalBadge) {
                    modalBadge.textContent       = i18n.blockedText;
                    modalBadge.style.color       = '#dc2626';
                    modalBadge.style.borderColor = '#fca5a5';
                }
            } else {
                if (switchOpen) switchOpen.classList.add('is-active-open');
                if (switchOff) switchOff.classList.remove('is-active-off');
                if (modalReasonWrap) modalReasonWrap.style.display = 'none';
                if (modalBadge) {
                    modalBadge.textContent       = i18n.openText;
                    modalBadge.style.color       = '#047857';
                    modalBadge.style.borderColor = '#a7f3d0';
                }
            }
            if (modalStatusSelect) {
                modalStatusSelect.value = status;
            }
        }

        if (switchOpen) {
            switchOpen.addEventListener('click', function() {
                syncSegmentedSwitch('open');
            });
        }

        if (switchOff) {
            switchOff.addEventListener('click', function() {
                syncSegmentedSwitch('off');
                if (modalReasonInput && !modalReasonInput.value.trim()) {
                    modalReasonInput.value = i18n.defaultHoliday;
                }
            });
        }

        function hideModal() {
            modal.classList.remove('is-visible');
            activeTargetCell = null;
        }

        if (closeModalBtn) closeModalBtn.addEventListener('click', hideModal);
        if (cancelModalBtn) cancelModalBtn.addEventListener('click', hideModal);

        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                hideModal();
            }
        });

        document.querySelectorAll('.ifs-reason-preset-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                if (modalReasonInput) {
                    modalReasonInput.value = this.textContent.trim();
                }
            });
        });

        dayCells.forEach(function(cell) {
            cell.addEventListener('click', function() {
                activeTargetCell = this;
                var dateStr = this.getAttribute('data-date');
                if (!dateStr) return;

                var isOff  = this.classList.contains('is-off-day');
                var reason = offDaysDataMap[dateStr] || this.getAttribute('data-reason') || '';

                var dateParts = dateStr.split('-');
                var dObj      = new Date(parseInt(dateParts[0], 10), parseInt(dateParts[1], 10) - 1, parseInt(dateParts[2], 10));
                var weekdays  = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                var dayName   = weekdays[dObj.getDay()] || '';

                if (modalDateLabel) modalDateLabel.textContent = dateStr;
                if (modalWeekdayLabel) modalWeekdayLabel.textContent = dayName;

                var defaultHolidayText = (dayName === 'Friday' || dayName === 'Saturday') ? i18n.weeklyHoliday : i18n.defaultHoliday;

                if (isOff) {
                    syncSegmentedSwitch('off');
                    if (modalReasonInput) {
                        modalReasonInput.value = reason ? reason : defaultHolidayText;
                    }
                } else {
                    syncSegmentedSwitch('open');
                    if (modalReasonInput) {
                        modalReasonInput.value = defaultHolidayText;
                    }
                }

                modal.classList.add('is-visible');
            });
        });

        if (saveModalBtn) {
            saveModalBtn.addEventListener('click', function() {
                if (!activeTargetCell) return;

                var dateStr = activeTargetCell.getAttribute('data-date');
                var status  = modalStatusSelect ? modalStatusSelect.value : 'open';
                var reason  = modalReasonInput ? modalReasonInput.value.trim() : '';

                if (status === 'off') {
                    var appliedReason = reason ? reason : i18n.defaultHoliday;
                    activeTargetCell.classList.add('is-off-day');
                    offDaysDataMap[dateStr] = appliedReason;
                    activeTargetCell.setAttribute('data-reason', appliedReason);
                    activeTargetCell.setAttribute('title', dateStr + ' (' + appliedReason + ')');
                } else {
                    activeTargetCell.classList.remove('is-off-day');
                    delete offDaysDataMap[dateStr];
                    activeTargetCell.removeAttribute('data-reason');
                    activeTargetCell.setAttribute('title', dateStr);
                }

                updateStorageState();
                hideModal();
            });
        }
    }
})();
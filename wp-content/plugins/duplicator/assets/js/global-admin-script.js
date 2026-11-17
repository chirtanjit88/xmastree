/*! dup admin script */
jQuery(document).ready(function ($) {
    $(document).on('click', '.dupli-admin-notice[data-to-dismiss] .notice-dismiss', function (event) {
        event.preventDefault();
        event.stopImmediatePropagation();
        var notice = $(this).closest('.dupli-admin-notice[data-to-dismiss]');
        $.post(ajaxurl, {
            action: 'duplicator_admin_notice_to_dismiss',
            notice: notice.data('to-dismiss'),
            fixKeys: notice.data('fix-keys') || '',
            nonce: dupli_global_data.nonce_admin_notice_to_dismiss
        });
    });

    $(document).on('click', '.dupli-dismissable-dismiss', function (event) {
        event.preventDefault();

        var $root = $(this).closest('.dupli-dismissable');
        if ($root.length === 0) {
            return;
        }

        var action = $root.data('dismiss-action');
        var nonce = $root.data('dismiss-nonce');
        if (!action || !nonce) {
            return;
        }

        $.post(dupli_global_data.ajaxurl, {
            action: action,
            nonce: nonce
        });

        var $row = $root.closest('tr');
        if ($row.length > 0) {
            $row.remove();
        } else {
            $root.hide().remove();
        }
    });

    $(document).on('click', '.dupli-quick-fix-notice .dupli-quick-fix', function () {
        var $button = $(this),
            $notice = $button.closest('.dupli-quick-fix-notice'),
            resetButton = function () {
                $button.prop('disabled', false).removeClass('disabled');
            },
            showError = function (text) {
                var $error = $('<span/>', {
                    class: 'dupli-quick-fix-error color-alert'
                });
                $error.append(
                    $('<i/>', {
                        class: 'fa fa-exclamation-triangle'
                    }),
                    document.createTextNode(' ' + text)
                );
                $notice.find('.dupli-quick-fix-error').remove();
                $button.after(' ', $error);
            };

        $notice.find('.dupli-quick-fix-error').remove();
        $button.prop('disabled', true).addClass('disabled');

        $.post(ajaxurl, {
            action: 'duplicator_quick_fix',
            nonce: dupli_global_data.nonce_quick_fix,
            fixKeys: $button.data('fix-key') || ''
        }, null, 'json').done(function (result) {
            var funcData = (result && result.data && result.data.funcData) || {};
            var appliedKeys = funcData.applied_keys || [];

            $notice.find('[data-fix-key]').filter(function () {
                return appliedKeys.indexOf($(this).attr('data-fix-key')) !== -1;
            }).remove();

            if (!result.success || !funcData.success) {
                resetButton();
                showError(funcData.message || dupli_global_data.quick_fix_error_msg);
                return;
            }

            if ($notice.find('.dupli-fix-error-item').length === 0) {
                $notice.remove();
            } else {
                $button.remove();
            }
        }).fail(function () {
            resetButton();
            showError(dupli_global_data.quick_fix_error_msg);
        });
    });

    // Dashboard widget refresh: paused while the tab is hidden, stopped after repeated failures
    var dupDashboardInterval = 30000;
    var dupDashboardMaxFailures = 3;
    var dupDashboardFailures = 0;
    var dupDashboardTimer = null;
    var dupDashboardInFlight = false;
    var dupDashboardDueAt = 0;

    function dupDashboardSchedule(delay) {
        clearTimeout(dupDashboardTimer);
        dupDashboardTimer = null;
        dupDashboardDueAt = Date.now() + delay;
        // A hidden tab schedules nothing: the visibility handler resumes the refresh
        if (document.hidden) {
            return;
        }
        dupDashboardTimer = setTimeout(dupDashboardUpdate, delay);
    }

    function dupDashboardFailure() {
        dupDashboardFailures++;
        // An expired session or nonce keeps failing: stop instead of polling forever
        if (dupDashboardFailures >= dupDashboardMaxFailures) {
            return;
        }
        dupDashboardSchedule(dupDashboardInterval * Math.pow(2, dupDashboardFailures));
    }

    function dupDashboardUpdate() {
        dupDashboardTimer = null;
        dupDashboardInFlight = true;
        jQuery.ajax({
            type: "POST",
            url: dupli_global_data.ajaxurl,
            dataType: "json",
            data: {
                action: 'duplicator_dashboad_widget_info',
                nonce: dupli_global_data.nonce_dashboard_widged_info
            },
            success: function (result) {
                if (!result || !result.success) {
                    dupDashboardFailure();
                    return;
                }

                dupDashboardFailures = 0;
                $('#duplicator_dashboard_widget .dup-last-backup-info').html(result.data.funcData.lastBackupInfo);

                if (result.data.funcData.isBackupCreationBlocked) {
                    $('#duplicator_dashboard_widget #dupli-create-new').addClass('disabled');
                } else {
                    $('#duplicator_dashboard_widget #dupli-create-new').removeClass('disabled');
                }
                dupDashboardSchedule(dupDashboardInterval);
            },
            error: function () {
                dupDashboardFailure();
            },
            complete: function () {
                dupDashboardInFlight = false;
            }
        });
    }

    if ($('#duplicator_dashboard_widget').length) {
        $(document).on('visibilitychange', function () {
            if (document.hidden) {
                clearTimeout(dupDashboardTimer);
                dupDashboardTimer = null;
            } else if (
                !dupDashboardInFlight &&
                dupDashboardTimer === null &&
                dupDashboardFailures < dupDashboardMaxFailures
            ) {
                // Keep the delay that was due, the interval or a failure backoff, instead of refreshing at once
                dupDashboardSchedule(Math.max(0, dupDashboardDueAt - Date.now()));
            }
        });
        // The server rendered the current state with the page: the first refresh waits one interval
        dupDashboardSchedule(dupDashboardInterval);
    }
});

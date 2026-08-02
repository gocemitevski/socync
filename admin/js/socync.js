jQuery(document).ready(function($) {
    // --- Tabbed UI ---
    var $tabs = $('.socync-tab');

    function activateTab(tabId) {
        $tabs.removeClass('active').attr('aria-selected', 'false').attr('tabindex', '-1');
        $('.socync-tab-content').removeClass('active');
        $tabs.filter('[data-tab="' + tabId + '"]').addClass('active').attr('aria-selected', 'true').attr('tabindex', '0');
        $('#' + tabId).addClass('active');
    }

    $tabs.on('click keydown', function(e) {
        if (e.type === 'keydown' && e.which !== 13 && e.which !== 32) return;
        var tabId = $(this).data('tab');
        activateTab(tabId);
        if (window.history.replaceState) {
            var url = new URL(window.location);
            url.searchParams.set('tab', tabId);
            window.history.replaceState({}, '', url);
        }
    });

    // --- Confirmation dialogs for scheduled post actions ---
    $(document).on('click', '.socync-confirm-delete', function(e) {
        if (!confirm(SocyncAdmin.confirm_delete || 'Delete this scheduled post?')) {
            e.preventDefault();
        }
    });
    $(document).on('click', '.socync-confirm-cancel', function(e) {
        if (!confirm(SocyncAdmin.confirm_cancel || 'Cancel this scheduled post?')) {
            e.preventDefault();
        }
    });
    $(document).on('click', '.socync-confirm-clear', function(e) {
        if (!confirm(SocyncAdmin.confirm_clear_log || 'Clear all log entries? This cannot be undone.')) {
            e.preventDefault();
        }
    });

    // --- Select-on-click for read-only OAuth redirect URL inputs ---
    $(document).on('click', '.socync-select-on-click', function() {
        this.select();
    });

    // --- Dev log detail toggle ---
    $(document).on('click', '.socync-dev-toggle', function(e) {
        e.preventDefault();
        var $target = $('#' + $(this).data('target'));
        if (!$target.length) return;
        if ($target.is(':visible')) {
            $target.hide();
            $(this).text(SocyncAdmin.show_details || 'Show details');
        } else {
            $target.show();
            $(this).text(SocyncAdmin.hide_details || 'Hide details');
        }
    });

    // --- Pagination page input: navigate on Enter ---
    $(document).on('keydown', '#socync-current-page-selector', function(e) {
        if (13 !== e.keyCode) return;
        e.preventDefault();
        var page = parseInt(this.value, 10);
        var totalPages = parseInt($(this).data('total-pages'), 10) || 0;
        if (page > 0 && page <= totalPages) {
            window.location.href = $(this).data('page-url').replace('__PAGE__', page);
        }
    });
});

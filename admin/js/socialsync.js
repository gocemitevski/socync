jQuery(document).ready(function($) {
    // --- Tabbed UI ---
    var $tabs = $('.socialsync-tab');

    function activateTab(tabId) {
        $tabs.removeClass('active').attr('aria-selected', 'false').attr('tabindex', '-1');
        $('.socialsync-tab-content').removeClass('active');
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
    $(document).on('click', '.socialsync-confirm-delete', function(e) {
        if (!confirm(SocialSyncAdmin.confirm_delete || 'Delete this scheduled post?')) {
            e.preventDefault();
        }
    });
    $(document).on('click', '.socialsync-confirm-cancel', function(e) {
        if (!confirm(SocialSyncAdmin.confirm_cancel || 'Cancel this scheduled post?')) {
            e.preventDefault();
        }
    });
});

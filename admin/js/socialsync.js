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
        location.hash = tabId;
    });

    var hash = location.hash.replace('#', '');
    if (hash && $tabs.filter('[data-tab="' + hash + '"]').length) {
        activateTab(hash);
    }

    var $scheduleSelect = $('select[name="_socialsync_schedule_type"]');
    var $scheduleDateInput = $('input[name="_socialsync_schedule_date"]');

    function toggleScheduleDate() {
        if ($scheduleSelect.length && $scheduleSelect.val() === 'scheduled') {
            $scheduleDateInput.prop('disabled', false);
        } else if ($scheduleDateInput.length) {
            $scheduleDateInput.prop('disabled', true);
        }
    }

    $scheduleSelect.on('change', toggleScheduleDate);
    toggleScheduleDate();

    // --- Social Post Preview ---
    var $titleInput = $('#title');
    var $excerptInput = $('#excerpt');
    var $customX = $('textarea[name="_socialsync_x_content"]');
    var $customLinkedin = $('textarea[name="_socialsync_linkedin_content"]');
    var $customFacebook = $('textarea[name="_socialsync_facebook_content"]');
    var $previewArea = $('#socialsync-preview-area');

    function getPermalink() {
        var $slug = $('#sample-permalink');
        if ($slug.length) {
            return $.trim($slug.text());
        }
        var $permalink = $('#edit-slug-box .permalink');
        if ($permalink.length) {
            return $.trim($permalink.text());
        }
        return '[post-url]';
    }

    function getFeaturedImage() {
        // Try the PHP-rendered data attribute first
        var src = $previewArea.data('featured-image') || '';

        // If empty, try reading from the DOM
        if (!src) {
            var $img = $('#postimagediv .inside img');
            if ($img.length) {
                src = $img.attr('src') || '';
            }
        }

        return src;
    }

    function composeDefault() {
        var title = $titleInput.val() || '(no title)';
        var excerpt = $excerptInput.val() || '';
        var url = getPermalink();
        return title + '\n\n' + excerpt + '\n\n' + url;
    }

    function updatePreviews() {
        var $previews = $('.socialsync-platform-preview');
        if (!$previews.length) return;

        var defaultText = composeDefault();
        var imageUrl = getFeaturedImage();

        $previews.each(function() {
            var $pre = $(this).find('.socialsync-preview-text');
            var $img = $(this).find('.socialsync-preview-image');
            var platform = $(this).data('platform');
            var custom = '';

            if (platform === 'x') custom = $customX.val();
            else if (platform === 'linkedin') custom = $customLinkedin.val();
            else if (platform === 'facebook') custom = $customFacebook.val();

            $pre.text(custom || defaultText);

            if (imageUrl) {
                $img.attr('src', imageUrl);
            } else {
                $img.removeAttr('src');
            }
        });
    }

    $titleInput.on('input', updatePreviews);
    $excerptInput.on('input', updatePreviews);
    $customX.on('input', updatePreviews);
    $customLinkedin.on('input', updatePreviews);
    $customFacebook.on('input', updatePreviews);

    // Watch for featured image changes using MutationObserver
    var $postImageDiv = document.getElementById('postimagediv');
    if ($postImageDiv) {
        var observer = new MutationObserver(function() {
            // Invalidate the cached data attribute so DOM takes over
            if ($previewArea.data('featured-image')) {
                $previewArea.data('featured-image', '');
            }
            updatePreviews();
        });
        observer.observe($postImageDiv, { childList: true, subtree: true, attributes: true, attributeFilter: ['class', 'src'] });
    }

    updatePreviews();

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

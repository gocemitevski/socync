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
    var $customBluesky = $('textarea[name="_socialsync_bluesky_content"]');
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
        var url = getPermalink();
        return title + '\n\n' + url;
    }

    function updatePreviews() {
        var $previews = $('.socialsync-platform-preview');
        if (!$previews.length) return;

        var defaultText = composeDefault();
        var imageUrl = getFeaturedImage();

        $previews.each(function() {
            var $container = $(this).find('.socialsync-preview-text');
            var platform = $(this).data('platform');
            var custom = '';

            if (platform === 'x') custom = $customX.val();
            else if (platform === 'linkedin') custom = $customLinkedin.val();
            else if (platform === 'facebook') custom = $customFacebook.val();
            else if (platform === 'bluesky') custom = $customBluesky.val();

            var text = custom || defaultText;
            var prefix = $(this).data('prefix');
            var hashtags = $(this).data('hashtags');
            if (prefix) text = prefix + ': ' + text;
            if (hashtags) text = text + '\n\n' + hashtags;
            $container.html('<img class="socialsync-preview-image" src="' + (imageUrl || '') + '" alt="" />\n' + text);
        });
    }

    $titleInput.on('input', updatePreviews);
    $excerptInput.on('input', updatePreviews);
    $customX.on('input', updatePreviews);
    $customLinkedin.on('input', updatePreviews);
    $customFacebook.on('input', updatePreviews);
    $customBluesky.on('input', updatePreviews);

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

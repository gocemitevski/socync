=== Socync - Automatic social media posting and scheduling ===
Contributors: gocemitevski
Tags: twitter, linkedin, facebook, bluesky, auto-post
Requires at least: 5.0
Tested up to: 7.0
Stable tag: 0.7.3
Requires PHP: 7.4
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A lightweight, stable, and maintainable WordPress plugin that automatically pushes published posts to X (Twitter), LinkedIn, Facebook and Bluesky.

== Description ==

Socync connects your WordPress site to four major social platforms and automatically publishes your content when you publish a new post. It also supports standalone scheduled posts for full control over timing and content.

= Features =

* **Auto-Post on Publish**: Automatically push new WordPress posts to all connected platforms after a configurable delay (default 2 minutes)
* **Per-Platform Autoposting Toggle**: Choose which connected platforms receive auto-posts on the Settings page
* **Multi-Platform Support**: Post to X (Twitter), LinkedIn, Facebook, and Bluesky
* **Link Previews with Thumbnails**: LinkedIn and Bluesky posts include OG thumbnail images
* **Standalone Scheduled Posts**: Create posts directly from the Schedule page — no WP Post required
* **Flexible Scheduling**: Schedule standalone posts at a specific date and time
* **Custom Content**: Full control over post content for each platform (prefix, hashtags per platform)
* **Dry Run Mode**: Preview what would be posted without actually publishing
* **Clean Admin Interface**: Tabbed settings page with unified Log view
* **Comprehensive Logging**: Track all posting attempts, errors, and developer-level API details
* **Multiple Auth Methods**: OAuth 2.0 (X, LinkedIn, Facebook), OAuth 1.0a legacy (X fallback), App Password (Bluesky)

== Installation ==

1. Upload the `socync` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to Socync in the admin menu to configure your connections

== Configuration ==

= X (Twitter) — OAuth 2.0 PKCE =

1. Go to Socync > Connections
2. Click "Connect" for X
3. Enter your Client ID and Client Secret (Confidential Client with Read+Write + `offline.access` scopes)
4. Click "Authorize" and log in with your X account

**Legacy OAuth 1.0a fallback**: If you previously configured OAuth 1.0a credentials (API Key/Secret + Access Token/Secret), they continue to work. The provider auto-detects which auth mode to use based on stored credentials.

= LinkedIn — OAuth 2.0 =

1. Go to Socync > Connections
2. Click "Connect" for LinkedIn
3. Enter your Client ID and Client Secret
4. Authorize the application via the OAuth redirect (redirects back to `admin-post.php?action=socync_oauth_callback_linkedin`)

= Facebook — OAuth 2.0 =

1. Go to Socync > Connections
2. Click "Connect" for Facebook
3. Enter your Client ID and Client Secret
4. Authorize the application via the OAuth redirect, then select a Facebook Page to post as

= Bluesky — App Password =

1. Go to Socync > Connections
2. Click "Connect" for Bluesky
3. Enter your Bluesky handle/email and an App Password (generate one at Settings > App Passwords in Bluesky)
4. Save credentials (no OAuth redirect)

== External services ==

Socync sends data to the following third-party services so it can publish content to your connected accounts. Data is only transmitted when you connect an account or publish a post (automatically when a WordPress post is published, or from the Schedule page). The plugin never shares this data with anyone else.

= X (Twitter) =

Socync publishes your post text (title, permalink, and any configured prefix/hashtags) to your X account using the X API v2. During connection it performs an OAuth 2.0 authorization flow and stores/refreshes the access token.

* Data sent: your X OAuth credentials (client ID, client secret, access/refresh tokens) and the post text you publish.
* When: when you connect your X account and when you publish a WordPress post or a scheduled post to X.
* X Terms of Service: https://x.com/en/tos
* X Privacy Policy: https://x.com/en/privacy

= LinkedIn =

Socync publishes your post text (and optionally a thumbnail image) to your LinkedIn profile or Page using the LinkedIn Posts API and Images API. During connection it performs an OAuth 2.0 authorization flow and stores/refreshes the access token.

* Data sent: your LinkedIn OAuth credentials, the post text you publish, and any thumbnail image you attach.
* When: when you connect your LinkedIn account and when you publish a WordPress post or a scheduled post to LinkedIn.
* LinkedIn User Agreement: https://www.linkedin.com/legal/user-agreement
* LinkedIn Privacy Policy: https://www.linkedin.com/legal/privacy-policy

= Facebook (Meta) =

Socync publishes your post text (and optionally a thumbnail image) to your selected Facebook Page or personal timeline using the Facebook Graph API. During connection it performs an OAuth 2.0 authorization flow and stores/refreshes the access and Page tokens.

* Data sent: your Facebook app/Page credentials (app secret, access and Page tokens), the post text you publish, and any thumbnail image you attach.
* When: when you connect your Facebook account and when you publish a WordPress post or a scheduled post to Facebook.
* Meta Platform Terms: https://developers.facebook.com/terms/dfc_platform_terms/
* Meta Developer Policy: https://developers.facebook.com/devpolicy/
* Meta Privacy Policy: https://www.facebook.com/privacy/policy/

= Bluesky =

Socync authenticates using your Bluesky handle/email and an App Password (via the AT Protocol session API) and publishes your post text (and optionally a thumbnail image) to your Bluesky account.

* Data sent: your Bluesky identifier (handle or email), your App Password (for authentication only), the post text you publish, and any thumbnail image you attach.
* When: when you connect your Bluesky account and when you publish a WordPress post or a scheduled post to Bluesky.
* Bluesky Terms of Service: https://bsky.social/about/support/tos
* Bluesky Privacy Policy: https://bsky.social/about/support/privacy-policy

== Usage ==

= Auto-Posting on Publish =

When you publish a new WordPress post, Socync automatically enqueues it for delivery to all platforms enabled in **Socync > Settings > Autoposting**. The post is sent after a configurable delay (default 2 minutes, set on the Settings page).

Content format: `{prefix}: {title} {permalink} {hashtags}` (all on one line).

Configure per-platform prefix and hashtags on the Connections page. Toggle autoposting per platform on the Settings page.

= Creating a Scheduled Post =

1. Go to Socync > Schedule
2. Click "Add New Scheduled Post"
3. Enter the post content and select the platforms to post to
4. Set the desired date and time
5. Save — the post will be published automatically when the time arrives

= Viewing Logs =

1. Go to Socync > Log
2. View all posting attempts, their status, and any errors

== Screenshots ==

1. Socync Connections page with OAuth setup for X (Twitter), LinkedIn, Facebook, and Bluesky
2. Scheduled posts management interface with status indicators
3. Log viewer with platform filter and detailed error reporting
4. Socync Settings page

== Frequently Asked Questions ==

= Which platforms are supported? =

X (Twitter), LinkedIn, Facebook, and Bluesky.

= What authentication methods are used? =

X uses OAuth 2.0 Authorization Code with PKCE (with OAuth 1.0a fallback). LinkedIn and Facebook use OAuth 2.0. Bluesky uses App Password authentication.

= Can I schedule posts in advance? =

Yes. Use the Socync > Schedule page to create standalone scheduled posts at any future date and time.

= Is there a dry run mode? =

Yes. Enable Developer Mode on the Settings page and then use Dry Run mode to preview what would be posted without actually sending anything.

= What happens to my data when I delete the plugin? =

Your Socync settings, connection data, and scheduled posts are preserved so they are restored if you re-install the plugin. To remove all plugin data on deletion, enable the "Delete data on uninstall" option on the Settings page.

== Changelog ==

= 0.7.3 =
* Added option to preserve plugin data on uninstall (data is kept by default; enable deletion in Settings > Uninstall)
* Fixed Facebook token refresh to use the stored access token
* Fixed timezone-dependent future-date check for scheduled posts
* Added plugin icon assets for the WordPress Plugin Directory
* Gated scheduled posts table creation behind a versioned option

= 0.7.2 =
* Documented external services (what data is sent, when, and links to each provider's terms/privacy policy)
* Removed register_uninstall_hook in favor of uninstall.php for reliable cleanup
* Sanitized OAuth callback query parameters (security hardening)
* Moved inline admin JavaScript and CSS into enqueued assets
* Fixed page-number navigation (Enter key) on the Log page

= 0.7.1 =
* Updated admin screenshots

= 0.7.0 =
* Renamed plugin from SocialSync to Socync (all internal prefixes, classes, options, hooks, and the text domain now use the socync prefix)
* Existing SocialSync settings are not migrated automatically — reconnect your accounts after updating
* Fixed hardcoded admin version reference so asset caching uses the current plugin version

= 0.6.0 =
* Fixed PHP 7.4 compatibility: removed union return types (array|WP_Error) which require PHP 8.0+

= 0.5.9 =
* Fixed LinkedIn image thumbnail upload returning 426 Upgrade Required (LinkedIn-Version header mismatch between Posts API and Images API)

= 0.5.8 =
* Added missing plugin headers (Requires at least, Requires PHP, Tested up to, Domain Path)
* Added load_plugin_textdomain() call and /languages/ directory
* Created WordPress-format readme.txt for Plugin Directory
* Drop custom scheduled_posts table on uninstall
* Wrapped all static WP_Error messages in __() for i18n consistency

= 0.5.7 =
* Added SSRF protection for OG metadata and thumbnail fetches
* Fixed Bluesky JWT storage (removed sanitize_text_field that could corrupt tokens)
* Fixed Bluesky handle URL encoding (rawurlencode for RFC 3986 compliance)
* Disabled autoloading for socync_logs option to reduce database overhead

= 0.5.6 =
* Added Settings page integration tests
* Updated admin page dashicons
* Improved plugin headers and metadata

= 0.5.5 =
* Security audit fixes: sanitization hardening, unified logging for Facebook, OAuth URL redaction, WP_Error redirect fixes

= 0.5.4 =
* Security audit fixes: token storage sanitization removal, body redaction fallback, dev logger HTML stripping

== Upgrade Notice ==

= 0.7.3 =
Uninstall is now non-destructive by default: plugin data is preserved unless the "Delete data on uninstall" option is enabled on the Settings page.

= 0.7.2 =
Security hardening and coding-standards fixes. Recommended upgrade.

= 0.7.1 =
Updated admin screenshots.

= 0.7.0 =
Plugin renamed to Socync. Reconnect your accounts after updating — settings are not migrated automatically.

= 0.6.0 =
PHP 7.4 compatibility fix. Recommended upgrade for all users.

= 0.5.9 =
Fixed LinkedIn image upload. Recommended upgrade for LinkedIn users.

= 0.5.8 =
Plugin directory compliance improvements. Recommended upgrade.

= 0.5.7 =
Security improvements including SSRF protection. Recommended upgrade for all users.

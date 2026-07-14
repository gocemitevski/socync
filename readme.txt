=== SocialSync - Automatic social media posting and scheduling ===
Contributors: gocemitevski
Tags: social media, twitter, x, linkedin, facebook, bluesky, auto-post, scheduling
Requires at least: 5.0
Tested up to: 7.0
Stable tag: 0.5.8
Requires PHP: 7.4
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A lightweight, stable, and maintainable WordPress plugin that automatically pushes published posts to X (Twitter), LinkedIn, Facebook and Bluesky.

== Description ==

SocialSync connects your WordPress site to four major social platforms and automatically publishes your content when you publish a new post. It also supports standalone scheduled posts for full control over timing and content.

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

1. Upload the `socialsync` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to SocialSync in the admin menu to configure your connections

== Configuration ==

= X (Twitter) — OAuth 2.0 PKCE =

1. Go to SocialSync > Connections
2. Click "Connect" for X
3. Enter your Client ID and Client Secret (Confidential Client with Read+Write + `offline.access` scopes)
4. Click "Authorize" and log in with your X account

**Legacy OAuth 1.0a fallback**: If you previously configured OAuth 1.0a credentials (API Key/Secret + Access Token/Secret), they continue to work. The provider auto-detects which auth mode to use based on stored credentials.

= LinkedIn — OAuth 2.0 =

1. Go to SocialSync > Connections
2. Click "Connect" for LinkedIn
3. Enter your Client ID and Client Secret
4. Authorize the application via the OAuth redirect (redirects back to `admin-post.php?action=socialsync_oauth_callback_linkedin`)

= Facebook — OAuth 2.0 =

1. Go to SocialSync > Connections
2. Click "Connect" for Facebook
3. Enter your Client ID and Client Secret
4. Authorize the application via the OAuth redirect, then select a Facebook Page to post as

= Bluesky — App Password =

1. Go to SocialSync > Connections
2. Click "Connect" for Bluesky
3. Enter your Bluesky handle/email and an App Password (generate one at Settings > App Passwords in Bluesky)
4. Save credentials (no OAuth redirect)

== Usage ==

= Auto-Posting on Publish =

When you publish a new WordPress post, SocialSync automatically enqueues it for delivery to all platforms enabled in **SocialSync > Settings > Autoposting**. The post is sent after a configurable delay (default 2 minutes, set on the Settings page).

Content format: `{prefix}: {title} {permalink} {hashtags}` (all on one line).

Configure per-platform prefix and hashtags on the Connections page. Toggle autoposting per platform on the Settings page.

= Creating a Scheduled Post =

1. Go to SocialSync > Schedule
2. Click "Add New Scheduled Post"
3. Enter the post content and select the platforms to post to
4. Set the desired date and time
5. Save — the post will be published automatically when the time arrives

= Viewing Logs =

1. Go to SocialSync > Log
2. View all posting attempts, their status, and any errors

== Screenshots ==

1. SocialSync Connections page with OAuth setup for X (Twitter), LinkedIn, Facebook, and Bluesky
2. Scheduled posts management interface with status indicators
3. Log viewer with platform filter and detailed error reporting
4. SocialSync Settings page

== Frequently Asked Questions ==

= Which platforms are supported? =

X (Twitter), LinkedIn, Facebook, and Bluesky.

= What authentication methods are used? =

X uses OAuth 2.0 Authorization Code with PKCE (with OAuth 1.0a fallback). LinkedIn and Facebook use OAuth 2.0. Bluesky uses App Password authentication.

= Can I schedule posts in advance? =

Yes. Use the SocialSync > Schedule page to create standalone scheduled posts at any future date and time.

= Is there a dry run mode? =

Yes. Enable Developer Mode on the Settings page and then use Dry Run mode to preview what would be posted without actually sending anything.

== Changelog ==

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
* Disabled autoloading for socialsync_logs option to reduce database overhead

= 0.5.6 =
* Added Settings page integration tests
* Updated admin page dashicons
* Improved plugin headers and metadata

= 0.5.5 =
* Security audit fixes: sanitization hardening, unified logging for Facebook, OAuth URL redaction, WP_Error redirect fixes

= 0.5.4 =
* Security audit fixes: token storage sanitization removal, body redaction fallback, dev logger HTML stripping

== Upgrade Notice ==

= 0.5.8 =
Plugin directory compliance improvements. Recommended upgrade.

= 0.5.7 =
Security improvements including SSRF protection. Recommended upgrade for all users.

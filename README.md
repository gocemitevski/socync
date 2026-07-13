# SocialSync - Automatic social media posting and scheduling

Requires at least: 5.0
Tested up to: 7.0
Stable tag: 0.5.5
License: GPL v2 or later

A lightweight, stable, and maintainable WordPress plugin that automatically pushes published posts to X (Twitter), LinkedIn, Facebook and Bluesky.

## Features

- **Auto-Post on Publish**: Automatically push new WordPress posts to all connected platforms after a configurable delay (default 2 minutes)
- **Per-Platform Autoposting Toggle**: Choose which connected platforms receive auto-posts on the Settings page
- **Multi-Platform Support**: Post to X (Twitter), LinkedIn, Facebook, and Bluesky
- **Link Previews with Thumbnails**: LinkedIn and Bluesky posts include OG thumbnail images
- **Standalone Scheduled Posts**: Create posts directly from the Schedule page — no WP Post required
- **Flexible Scheduling**: Schedule standalone posts at a specific date and time
- **Custom Content**: Full control over post content for each platform (prefix, hashtags per platform)
- **Dry Run Mode**: Preview what would be posted without actually publishing
- **Clean Admin Interface**: Tabbed settings page with unified Log view
- **Comprehensive Logging**: Track all posting attempts, errors, and developer-level API details
- **Multiple Auth Methods**: OAuth 2.0 (X, LinkedIn, Facebook), OAuth 1.0a legacy (X fallback), App Password (Bluesky)

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- API credentials for X (OAuth 2.0 PKCE or legacy OAuth 1.0a), LinkedIn (OAuth 2.0), Facebook (OAuth 2.0), Bluesky (App Password)

## Installation

1. Upload the `socialsync` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to SocialSync in the admin menu to configure your connections

## Configuration

### X (Twitter) — OAuth 2.0 PKCE
1. Go to SocialSync > Connections
2. Click "Connect" for X
3. Enter your Client ID and Client Secret (Confidential Client with Read+Write + `offline.access` scopes)
4. Click "Authorize" and log in with your X account

**Legacy OAuth 1.0a fallback**: If you previously configured OAuth 1.0a credentials (API Key/Secret + Access Token/Secret), they continue to work. The provider auto-detects which auth mode to use based on stored credentials.

### LinkedIn — OAuth 2.0
1. Go to SocialSync > Connections
2. Click "Connect" for LinkedIn
3. Enter your Client ID and Client Secret
4. Authorize the application via the OAuth redirect (redirects back to `admin-post.php?action=socialsync_oauth_callback_linkedin`)

### Facebook — OAuth 2.0
1. Go to SocialSync > Connections
2. Click "Connect" for Facebook
3. Enter your Client ID and Client Secret
4. Authorize the application via the OAuth redirect, then select a Facebook Page to post as

### Bluesky — App Password
1. Go to SocialSync > Connections
2. Click "Connect" for Bluesky
3. Enter your Bluesky handle/email and an App Password (generate one at Settings > App Passwords in Bluesky)
4. Save credentials (no OAuth redirect)

## Usage

### Auto-Posting on Publish

When you publish a new WordPress post, SocialSync automatically enqueues it for delivery to all platforms enabled in **SocialSync > Settings > Autoposting**. The post is sent after a configurable delay (default 2 minutes, set on the Settings page).

Content format: `{prefix}: {title} {permalink} {hashtags}` (all on one line).

Configure per-platform prefix and hashtags on the Connections page. Toggle autoposting per platform on the Settings page.

### Creating a Scheduled Post

1. Go to SocialSync > Schedule
2. Click "Add New Scheduled Post"
3. Enter the post content and select the platforms to post to
4. Set the desired date and time
5. Save — the post will be published automatically when the time arrives

### Viewing Logs

1. Go to SocialSync > Log
2. View all posting attempts, their status, and any errors

## File Structure

```
socialsync/
├── admin/
│   ├── css/                # Admin styles
│   ├── js/                 # JavaScript files
│   ├── class-admin.php     # Admin functionality (menu, metabox, OAuth handlers)
│   └── views/              # Settings page views (connections-page, settings-page, scheduled-posts-page, log-page)
├── includes/
│   ├── class-activator.php     # Activation logic (table creation, cron setup)
│   ├── class-deactivator.php   # Deactivation cleanup
│   ├── class-api-handler.php   # Abstract base class for API requests
│   ├── class-dev-logger.php    # Developer event logging (500-entry ring buffer)
│   ├── class-scheduled-post.php # Custom table model (CRUD)
│   └── class-scheduler.php     # WP-Cron queue management
├── providers/
│   ├── class-x-provider.php         # X/Twitter OAuth 1.0a API
│   ├── class-linkedin-provider.php  # LinkedIn OAuth 2.0 API
│   ├── class-facebook-provider.php  # Facebook Graph API
│   └── class-bluesky-provider.php   # Bluesky AT Protocol API
├── socialsync.php          # Main plugin file (entry point, class loader)
└── uninstall.php           # Cleanup on deletion

## Development

### Manual Testing

No test infrastructure or CI is configured yet. Test manually in a WordPress context:

```bash
wp plugin activate socialsync
wp cron event run socialsync_run_delayed_posts
```

## License

GPL v2 or later

## Contributing

Contributions are welcome! Please follow the WordPress Coding Standards and submit pull requests.

## Support

For issues and questions, please visit the [GitHub repository](https://github.com/yourusername/socialsync).

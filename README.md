# SocialSync for WordPress

A lightweight, stable, and maintainable WordPress plugin that automatically pushes published posts to X (Twitter), LinkedIn, Facebook and Bluesky.

## Features

- **Multi-Platform Support**: Automatically post to X (Twitter), LinkedIn, Facebook, and Bluesky
- **Link Previews with Thumbnails**: LinkedIn and Bluesky posts include OG thumbnail images
- **Immediate Posting**: Posts are published immediately when you publish a WordPress post
- **Scheduled Posting**: Schedule posts to be published at a specific date and time
- **Custom Content**: Override post content, prefix, and hashtags for each platform
- **Standalone Scheduled Posts**: Create posts directly from the Schedule page without a WP Post
- **Dry Run Mode**: Preview what would be posted without actually publishing
- **Clean Admin Interface**: Tabbed settings page with unified Log view
- **Comprehensive Logging**: Track all posting attempts, errors, and developer-level API details
- **Multiple Auth Methods**: OAuth 2.0 (LinkedIn, Facebook), OAuth 1.0a (X), App Password (Bluesky)

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- API credentials for X (OAuth 1.0a), LinkedIn (OAuth 2.0), Facebook (OAuth 2.0), Bluesky (App Password)

## Installation

1. Upload the `socialsync` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to SocialSync in the admin menu to configure your connections

## Configuration

### X (Twitter) — OAuth 1.0a
1. Go to SocialSync > Connections
2. Click "Connect" for X
3. Enter your API Key, API Key Secret, Access Token, and Access Token Secret
4. Save credentials (no OAuth redirect — credentials must be generated in X Developer Portal with Read+Write permission)

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

### Posting to Social Media

1. Create or edit a post in WordPress
2. In the "SocialSync" meta box, check the platforms you want to post to
3. Optionally customize the post content for each platform
4. Choose "Immediate" or "Scheduled" for posting
5. If scheduled, select the date and time
6. Publish the post

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

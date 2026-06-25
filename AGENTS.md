# SocialSync – Agent Guide

## Project

WordPress plugin that auto-publishes posts to X (Twitter), LinkedIn, Facebook, and Bluesky.

- Entry point: `socialsync.php` (plugin header + class loader)
- Text domain: `social-sync`
- PHP 7.4+, no build step, no JS bundler, no task runner
- No `composer.json` or test infrastructure exists yet (README mentions them but they're not implemented)

## Structure

```
socialsync.php          — main plugin file
includes/               — core logic (activator, deactivator, scheduler, API base, dev logger, scheduled post model)
admin/                  — admin UI (class-admin.php, views/, css/, js/)
providers/              — one class per platform: X, LinkedIn, Facebook, Bluesky
```

All classes follow `SocialSync_{Name}` naming convention.

## Storage

- **`wp_options`**: tokens, API keys, settings, logs (`socialsync_*` prefixed)
- **Custom table**: `{$wpdb->prefix}socialsync_scheduled_posts` (created on activation via `SocialSync_Scheduled_Post::create_table()`)
- **`wp_postmeta`** (legacy): `_socialsync_*` keys from the removed metabox — no longer actively used

## Key Flows

- **Scheduler**: WP-Cron fires `socialsync_run_delayed_posts` every minute to process standalone scheduled posts. Each per-post run is wrapped in try/catch so exceptions don't stall the queue. Content is passed through `html_entity_decode()` before being sent to providers. Each `$provider->publish()` call is wrapped in try/catch — exceptions are caught and returned as `WP_Error` so cron continues to the next platform.
- **OAuth**: LinkedIn and Facebook use OAuth 2.0 with state transients (5-min TTL). Callbacks go through `admin-post.php?action=socialsync_oauth_callback_{platform}`.
- **X (Twitter)**: OAuth 1.0a with HMAC-SHA1 — no OAuth callback flow. Credentials entered directly on settings page.
- **Bluesky**: App password auth via `com.atproto.server.createSession`. No OAuth2.
- **Dev Logger**: Captures API request/response details, publish steps, and cron events to a 500-entry ring buffer in `socialsync_dev_logs`. Toggled via Dev Mode on Settings page. Sensitive fields (`access_token`, `refresh_token`, `client_secret`, `app_password`, `password`) are redacted before logging.
- **Admin assets**: `socialsync-admin` stylesheet depends on WP core `list-tables` to ensure proper pagination and table styling.

## Auth Quirks

- X uses **OAuth 1.0a User Context** (4 fields: API key, API secret, access token, access token secret). Must have OAuth 1.0a enabled in X Developer Portal with Read+Write permission.
- Facebook requires selecting a Page after OAuth (stores `facebook_page_id` + `facebook_page_token` separately).
- LinkedIn uses the **Posts API** (`/rest/posts`) with `LinkedIn-Version: 202506` — requires the "Posts API" product in the LinkedIn Developer App (not just "Share on LinkedIn"). Thumbnail uploads go through the Images API.
- LinkedIn can post as a person or organization (selectable after OAuth).

## Admin

- Menu: "SocialSync" top-level at position 92, subpages: Connections, Schedule, Log
- All admin handlers check `manage_options` capability + nonce
- Assets enqueued only on `social-sync*` hooks
- Log page uses WP core list table pagination (`.pagination-links`, `.paging-input`, Screen Options for per-page)
- Dev Mode toggle on Settings page enables the Developer source filter on the Log page

## Encryption (M-3)

Credentials are encrypted at rest via AES-256-CBC using option filters (`pre_update_option_` / `option_`) registered on `init`.

- **Encryption key**: Derived from `AUTH_KEY` (or `wp_salt('auth')` as fallback). If `AUTH_KEY` changes (server migration, security rotation), stored credentials become unreadable and the admin must re-enter them.
- **Coverage**: 11 sensitive options (`*_secret`, `*_token`, `*_app_password`, `*_refresh_jwt`, plus token arrays where `access_token`/`refresh_token` fields are encrypted).
- **Not encrypted**: Client/app IDs (`x_api_key`, `linkedin_client_id`, `facebook_app_id`), identifiers (`bluesky_identifier`), DIDs, page/org/person IDs — these are not secrets.
- **Backward compat**: `socialsync_decrypt()` returns the original value on failure, so unencrypted legacy data passes through unchanged. On the next `update_option` call, the value is encrypted.

## Commands (none exist yet)

No commands, test runners, linters, or CI are configured. To run manually in a WordPress context:

- Activate plugin → `wp plugin activate socialsync`
- Trigger cron → `wp cron event run socialsync_run_delayed_posts`

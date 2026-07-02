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
  - Columns: `id`, `post_id` (WP post ID for auto-posts, 0 for standalone), `title`, `content`, `platforms`, `scheduled_date`, `status`, `error_message`, `created_at`, `updated_at`
- **`wp_postmeta`** (legacy): `_socialsync_*` keys from the removed metabox — no longer actively used

## Key Flows

- **Auto-Post on Publish**: `transition_post_status` hook fires on initial publish (not re-publish). `enqueue_post()` checks `socialsync_autopost_platforms` setting, filters to currently-connected platforms, and inserts a row into `socialsync_scheduled_posts` with `post_id` set and `scheduled_date = now + 2 min` (local timezone). Content is built dynamically at cron time using per-platform prefix/hashtags from settings.
- **Scheduler**: WP-Cron fires `socialsync_run_delayed_posts` every minute to process both standalone and WP-post-sourced scheduled posts. Each per-post run is wrapped in try/catch so exceptions don't stall the queue. Content is passed through `html_entity_decode()` before being sent to providers. Each `$provider->publish()` call is wrapped in try/catch — exceptions are caught and returned as `WP_Error` so cron continues to the next platform. For WP-post rows, `publish_to_platform()` builds content dynamically from the post title/permalink and per-platform prefix/hashtags.
- **Log Resolution (log_version)**: Log entries store a `log_version` field. v1 entries stored the scheduled_post row ID in `post_id`; v2+ store the resolved WP post ID. The Log page uses this field to resolve old entries correctly — falling back through `SocialSync_Scheduled_Post::get()` for v1, direct `get_post()` for v2+.
- **Schedule Page Defaults**: New standalone scheduled posts pre-check platforms from the `socialsync_autopost_platforms` option. Editing existing posts preserves the stored platforms JSON column.
- **OAuth Redirect URL Display**: LinkedIn and Facebook callback URLs shown as copy-friendly `<code>` blocks with `user-select:all` above their Connect forms on the Connections page.
- **OAuth**: LinkedIn and Facebook use OAuth 2.0 with state transients (5-min TTL). Callbacks go through `admin-post.php?action=socialsync_oauth_callback_{platform}`.
- **X (Twitter)**: OAuth 2.0 Authorization Code with PKCE (confidential client with Basic auth for token exchange). Token refresh via `offline.access` scope. OAuth 1.0a HMAC-SHA1 fallback retained — `auth_mode` auto-detected from stored credentials.
- **Bluesky**: App password auth via `com.atproto.server.createSession`. No OAuth2.
- **Dev Logger**: Captures API request/response details, publish steps, and cron events to a 500-entry ring buffer in `socialsync_dev_logs`. Toggled via Dev Mode on Settings page. Sensitive fields (`access_token`, `refresh_token`, `client_secret`, `app_password`, `password`) are redacted before logging.
- **Admin assets**: `socialsync-admin` stylesheet depends on WP core `list-tables` to ensure proper pagination and table styling.

## Auth Quirks

- X uses **OAuth 2.0 Authorization Code with PKCE** (Client ID + Client Secret, confidential client). Must have OAuth 2.0 enabled in X Developer Portal with Read+Write + `offline.access` scopes. **OAuth 1.0a fallback** — if only legacy credentials (API key/secret + access token/secret) are stored, the provider auto-detects and uses OAuth 1.0a.
- Facebook requires selecting a Page after OAuth (stores `facebook_page_id` + `facebook_page_token` separately).
- LinkedIn uses the **Posts API** (`/rest/posts`) with `LinkedIn-Version: 202506` — requires the "Posts API" product in the LinkedIn Developer App (not just "Share on LinkedIn"). Thumbnail uploads go through the Images API.
- LinkedIn can post as a person or organization (selectable after OAuth).

## Admin

- Menu: "SocialSync" top-level at position 92, subpages: Connections, Schedule, Log
- All admin handlers check `manage_options` capability + nonce
- Assets enqueued only on `social-sync*` hooks
- Log page uses WP core list table pagination (`.pagination-links`, `.paging-input`, Screen Options for per-page)
- Dev Mode toggle on Settings page enables the Developer source filter on the Log page
- Autoposting section on Settings page shows all 4 platforms; disconnected platforms show a disabled checkbox with a link to Connections

## Encryption (M-3)

Credentials are encrypted at rest via AES-256-CBC using option filters (`pre_update_option_` / `option_`) registered on `init`.

- **Encryption key**: Derived from `AUTH_KEY` (or `wp_salt('auth')` as fallback). If `AUTH_KEY` changes (server migration, security rotation), stored credentials become unreadable and the admin must re-enter them.
- **Coverage**: 13 sensitive options (`*_secret`, `*_token`, `*_app_password`, `*_refresh_jwt`, plus token arrays where `access_token`/`refresh_token` fields are encrypted).
- **Not encrypted**: Client/app IDs (`x_api_key`, `linkedin_client_id`, `facebook_app_id`), identifiers (`bluesky_identifier`), DIDs, page/org/person IDs — these are not secrets.
- **Backward compat**: `socialsync_decrypt()` returns the original value on failure, so unencrypted legacy data passes through unchanged. On the next `update_option` call, the value is encrypted.

## Commands (none exist yet)

No commands, test runners, linters, or CI are configured. To run manually in a WordPress context:

- Activate plugin → `wp plugin activate socialsync`
- Trigger cron → `wp cron event run socialsync_run_delayed_posts`

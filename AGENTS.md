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
includes/               — core logic (activator, deactivator, scheduler, API base, scheduled post model)
admin/                  — admin UI (class-admin.php, views/, css/, js/)
providers/              — one class per platform: X, LinkedIn, Facebook, Bluesky
```

All classes follow `SocialSync_{Name}` naming convention.

## Storage

- **`wp_options`**: tokens, API keys, settings, logs (`socialsync_*` prefixed)
- **`wp_postmeta`**: per-post platform selections, custom content, schedule (`_socialsync_*` prefixed)
- **Custom table**: `{$wpdb->prefix}socialsync_scheduled_posts` (created on activation via `SocialSync_Scheduled_Post::create_table()`)

## Key Flows

- **Scheduler**: WP-Cron fires `socialsync_run_delayed_posts` every minute. Processes both WP post meta and standalone scheduled posts.
- **Posting trigger**: `publish_post` hook → `SocialSync_Admin::save_post_data()` → `SocialSync_Scheduler::enqueue_post()`
- **OAuth**: LinkedIn and Facebook use OAuth 2.0 with state transients (5-min TTL). Callbacks go through `admin-post.php?action=socialsync_oauth_callback_{platform}`.
- **X (Twitter)**: OAuth 1.0a with HMAC-SHA1 — no OAuth callback flow. Credentials entered directly on settings page.
- **Bluesky**: App password auth via `com.atproto.server.createSession`. No OAuth2.

## Auth Quirks

- X uses **OAuth 1.0a User Context** (4 fields: API key, API secret, access token, access token secret). Must have OAuth 1.0a enabled in X Developer Portal with Read+Write permission.
- Facebook requires selecting a Page after OAuth (stores `facebook_page_id` + `facebook_page_token` separately).
- LinkedIn can post as a person or organization (selectable after OAuth).

## Admin

- Menu: "SocialSync" top-level at position 92, subpages: Connections, Schedule, Log
- All admin handlers check `manage_options` capability + nonce
- Metabox appears only on `post` post type (Classic Editor)
- Assets enqueued only on `social-sync*` or `post.php`/`post-new.php` hooks

## Commands (none exist yet)

No commands, test runners, linters, or CI are configured. To run manually in a WordPress context:

- Activate plugin → `wp plugin activate socialsync`
- Trigger cron → `wp cron event run socialsync_run_delayed_posts`

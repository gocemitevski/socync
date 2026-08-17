# Connecting your social networks

Socync supports four social platforms. Every platform is connected from a single place — the **Socync → Connections** page — but each one needs its own developer app (and for Bluesky, an app password) before you can connect.

Use the guide for the platform you want to connect:

- [Connecting X (Twitter)](connecting-x.md)
- [Connecting LinkedIn](connecting-linkedin.md)
- [Connecting Facebook](connecting-facebook.md)
- [Connecting Bluesky](connecting-bluesky.md)

## What you need before you start

- WordPress 5.0 or newer, PHP 7.4 or newer.
- A logged-in WordPress **administrator** account (only admins can manage connections).
- A browser session where you are also logged in to the social network you want to connect (X, LinkedIn, or Facebook), because the connection flow redirects you to that platform to authorize the app.
- A stable internet connection — the OAuth authorization step must be completed within **5 minutes** of clicking **Connect**. If you are slower than that, or you refresh the page mid-flow, the connection will fail with an "Invalid OAuth state" error and you simply click **Connect** again.

## How the connections page works

1. Go to **Socync → Connections** in the WordPress admin menu.
2. Pick the tab for the platform: **X (Twitter)**, **LinkedIn**, **Facebook**, or **Bluesky**.
3. Each tab shows:
   - A **Setup Guide** (a short checklist for the developer portal side) and a read-only **OAuth Redirect URL** to paste into your app — except the **Bluesky** tab, which uses an App Password and has no developer portal or redirect URL,
   - the **Connect** form where you paste your app credentials,
   - per-platform **Prefix Text** and **Hashtags** fields (used for auto-posts; shown whether connected or not),
   - and, once connected, a **Disconnect** button.

### Authentication types at a glance

| Platform | Authentication | What you need from the platform |
|---|---|---|
| X (Twitter) | OAuth 2.0 with PKCE | Client ID + Client Secret (or legacy OAuth 1.0a keys, see below) |
| LinkedIn | OAuth 2.0 | Client ID + Client Secret |
| Facebook | OAuth 2.0 | App ID + App Secret |
| Bluesky | App Password (no OAuth) | An app password from your Bluesky settings |

## The OAuth Redirect URL

X, LinkedIn, and Facebook require a **redirect (callback) URL** registered on the app. It is shown on each tab and always has the same shape, one per platform:

```
https://your-site-url/wp-admin/admin-post.php?action=socync_oauth_callback_x
https://your-site-url/wp-admin/admin-post.php?action=socync_oauth_callback_linkedin
https://your-site-url/wp-admin/admin-post.php?action=socync_oauth_callback_facebook
```

Rules that apply to all three:

- It must be **HTTPS** (a public, SSL-enabled site). Local/LAN setups without HTTPS cannot use OAuth.
- It must be pasted **exactly** as shown — no trailing slash, no extra characters. Providers compare it character-for-character.
- If you change your site URL later, update the redirect URL in the developer portal.

## After you connect

Connecting the account is only the first half. To actually publish:

1. **Per-platform content (optional):** set **Prefix Text** and **Hashtags** on the Connections tab. These are prepended/appended to auto-posts sent to that platform.
2. **Enable autoposting:** go to **Socync → Settings → Autoposting** and tick each connected platform you want posts delivered to. Disconnected platforms show a disabled checkbox with a link to the Connections page.
3. **Check the Log:** go to **Socync → Log** after publishing to confirm the post was delivered and to see any error details.

Some platforms have an extra step after connecting — pick the correct **Page** (Facebook) or enter a **Page ID** (LinkedIn). Those are documented in the platform guides.

## Stored credentials & security

- Credentials are stored in WordPress options and **encrypted at rest** (AES-256-CBC). Client/app IDs, identifiers, and DIDs are not secrets and are stored in plain text.
- The encryption key is derived from your WordPress `AUTH_KEY`. **If the server is migrated or `AUTH_KEY` is rotated, stored credentials become unreadable and you must reconnect each platform.**
- The **Disconnect** button on a Connections tab deletes that platform's stored credentials and tokens.
- Socync only transmits what is needed to publish: your post text (title, permalink, prefix, hashtags), the Open Graph thumbnail of the linked article, and your credentials during authentication. It never shares data with anyone else.

## Common troubleshooting

| Symptom | Cause / fix |
|---|---|
| "Invalid OAuth state parameter" | The 5-minute authorization window lapsed or the page was refreshed. Click **Connect** again and complete the flow without interruption. |
| "Connection failed: access_denied" | You (or the app's reviewer) declined the permission prompt. Re-run the flow and accept all requested permissions. |
| Connection fails after a server move or password/key rotation | The stored credentials can no longer be decrypted. Reconnect the platform. |
| Publish fails with 401 / "token expired" | The access token expired and could not be refreshed. Open the platform tab and reconnect. |
| A platform does not appear in Autoposting | It is not connected, or it was not ticked in **Socync → Settings → Autoposting**. |

If something still fails, enable **Developer Mode** on the Settings page and check **Socync → Log** for detailed API request/response information (secrets are redacted).
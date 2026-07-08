# SocialSync User Guide

## Getting Started

### Installation

1. Upload the `socialsync` folder to `/wp-content/plugins/`
2. Go to **Plugins** in your WordPress admin and activate **SocialSync**
3. A new **SocialSync** menu appears at the bottom of the admin sidebar

### Navigating the Menu

- **Connections** — Connect and manage your social network accounts
- **Schedule** — Create and manage standalone scheduled posts
- **Log** — View publishing history and developer-level API details
- **Settings** — Configure autoposting, delay, and developer mode

---

## Connecting Social Networks

Each network requires credentials from its developer portal. The Connections page provides a setup guide for each platform with the OAuth redirect URL pre-filled.

### X (Twitter) — OAuth 2.0 PKCE

1. Go to [developer.x.com](https://developer.x.com) → **Projects & Apps** → **Create App** (Web App)
2. Under **User Authentication Settings**, enable OAuth 2.0, set App Type to **Web App**
3. Paste the OAuth Redirect URL shown on the Connections page into the **Redirect URI** field
4. Under **Permissions**, check **Read and Write** + **Offline access**
5. Save, then copy the **Client ID** and **Client Secret**
6. Enter them on the Connections page and click **Connect**

If you previously used OAuth 1.0a credentials (API Key/Secret + Access Token/Secret), they continue to work — SocialSync detects and uses whichever credentials are stored.

### LinkedIn — OAuth 2.0

1. Go to [developer.linkedin.com](https://developer.linkedin.com) → **My Apps** → **Create App**
2. In the **Products** tab, add **Share on LinkedIn**. Also request **Posts API** if available
3. In the **Auth** tab, add the OAuth Redirect URL to **Authorized redirect URLs for your app**
4. From the **Auth** tab, copy the **Client ID** and **Client Secret**
5. Enter them on the Connections page and click **Connect**
6. After authorizing, you can post as your personal profile or enter a LinkedIn Page ID to post as an organization

### Facebook — OAuth 2.0

1. Go to [developers.facebook.com](https://developers.facebook.com) → **My Apps** → **Create App** → **Business**
2. Add the **Pages API** permission to your app
3. From **Settings → Basic**, copy the **App ID** and **App Secret**
4. Under **Facebook Login → Settings**, add the OAuth Redirect URL to **Valid OAuth Redirect URIs**
5. Enter the App ID and App Secret on the Connections page and click **Connect**
6. After authorizing, select which Facebook Page to post to from the dropdown

### Bluesky — App Password

1. Sign in to [bsky.app](https://bsky.app) → **Settings** → **App Passwords**
2. Click **Add App Password**, name it `SocialSync`, and copy the generated password
3. Enter your Bluesky handle (or email) and the app password on the Connections page
4. Click **Connect** — no OAuth redirect needed

---

## Configuring Settings

### Autoposting

When you publish a new WordPress post, SocialSync can automatically send it to selected platforms.

1. Go to **SocialSync → Settings**
2. Under **Autoposting**, check the platforms you want to auto-post to
3. Set the **Delay** in minutes — the post will be sent this long after publishing (default 2, set to 0 for instant)
4. Disconnected platforms are shown disabled with a link to the Connections page

### Developer Mode and Dry Run

- **Developer Mode** — enables verbose logging of all plugin events, API requests, and responses. View these on the Log page by selecting the **Developer** source filter
- **Dry Run** — logs everything but skips actual API calls. Useful for testing content format before real posting. Requires Developer Mode to be enabled

### Per-Platform Prefix and Hashtags

Each connected platform has its own prefix text and hashtags settings on the Connections page. These are applied to auto-posts at publish time in the format:

```
{prefix}: {title} {permalink} {hashtags}
```

All on one line, no line breaks. Leave prefix or hashtags empty to omit them.

---

## Auto-Publishing WordPress Posts

SocialSync hooks into WordPress's `transition_post_status` to detect when a post is first published. Updates and re-publishes are ignored.

1. Go to **SocialSync → Settings** and enable autoposting for the platforms you want
2. The **Delay** setting controls how long after publishing the post is sent
3. When you publish a WordPress post, SocialSync creates a scheduled entry
4. WP-Cron processes the entry after the configured delay

### Content Format

Auto-posts use the WP post title and permalink, combined with per-platform prefix and hashtags:

```
New post: My Article Title https://yoursite.com/my-article #tech #news
```

Configure prefix and hashtags on each platform's tab on the Connections page.

### Disabling Auto-Posting

Uncheck a platform under **SocialSync → Settings → Autoposting**. Existing scheduled entries are not affected — only future publishes.

---

## Scheduling Standalone Posts

You can create posts directly from the Schedule page without creating a WordPress post first.

1. Go to **SocialSync → Schedule**
2. Enter the post **Content**
3. Select which **Platforms** to post to (new posts default to your autoposting selection)
4. Set a **Date and Time**, then click **Schedule**
5. The post is sent when the scheduled time arrives

### Post Now vs Schedule

- **Schedule** — saves the post for delivery at the specified date and time
- **Post Now** — sends the post immediately, regardless of the date field

The **Post Now** button is found below the date picker, next to the Schedule button.

### Editing and Cancelling

- **Edit** — click the post title on the Log page or go to **SocialSync → Schedule** with the `?edit=` parameter
- **Cancel** — click the Cancel link next to a scheduled post on the Log page
- **Delete** — use the Delete action on the Log page for individual entries

---

## Viewing Logs

### Main Log

1. Go to **SocialSync → Log**
2. See all publishing attempts, their status, and error messages
3. Use the source filter to show **Posts** (WP post + standalone) or **Developer** entries
4. Use the status links (Published, Failed, Scheduled, Dry Run, Cancelled) to filter by result

### Developer Log

With **Developer Mode** enabled on the Settings page, the Log page collects detailed entries including:

- API request URLs, headers, and bodies
- API response status codes and bodies
- Post content as built per platform
- OAuth callback details
- Cron run summaries

Click **Show details** on any developer entry to expand the full request/response data.

### Pagination

Use the page input at the top-right to jump to a specific page. The bottom bar shows the current page range. Change the per-page count via **Screen Options**.

### Retention

Set auto-clear after a number of days on the Log page. Terminal-status posts (published, failed, cancelled) older than this threshold are purged daily. Set to 0 to keep entries indefinitely.

---

## Posting as an Organization

### LinkedIn Page

1. Connect your LinkedIn account
2. Under **Organization Settings**, enter your LinkedIn Page numeric ID
3. When set, posts are published as the Page instead of your personal profile
4. Leave empty to post as yourself

To find your Page ID, visit your LinkedIn Page and look at the URL — the numeric ID appears after `/company/`. Or use the LinkedIn Help Center link on the Connections page.

### Facebook Page

1. Connect your Facebook account
2. A dropdown lists all Facebook Pages you administer
3. Select a Page to post as, or choose **Post as User (Timeline)** for personal posts
4. The page-specific access token is stored automatically

---

## Frequently Asked Questions

**Why is my post not appearing on X/LinkedIn/Facebook/Bluesky?**

Check the Log page for the status and error message. Common causes: the OAuth token expired (reconnect the platform), the credentials were disconnected, or the platform's API returned an error. Each per-platform publish is wrapped in a try/catch — an error on one platform does not affect the others.

**How do I reconnect after changing my credentials?**

Go to the Connections page, click **Disconnect** for the platform, then go through the connection flow again. Disconnecting clears all stored credentials for that platform.

**Why did my credentials stop working suddenly?**

If your server's `AUTH_KEY` or `AUTH_SALT` constants changed (server migration, security rotation), encrypted credentials become unreadable. You must re-enter them on the Connections page. This is a security feature — your secrets are encrypted at rest and the encryption key is derived from `AUTH_KEY`.

**What is Dry Run mode?**

Dry Run mode logs everything SocialSync would do — including the exact API request bodies — without actually sending them to any platform. Enable it under **SocialSync → Settings → Developer Mode**, then toggle Dry Run. Check the Developer log to verify your content format before real posting.

**Can I post immediately without waiting for the delay?**

Yes — for WordPress auto-posts, set the delay to **0** on the Settings page. For standalone posts, use the **Post Now** button on the Schedule page.

**How do I post as a Page rather than my personal profile?**

- **Facebook** — select a Page from the dropdown that appears after connecting. The dropdown lists all Pages you administer
- **LinkedIn** — enter your LinkedIn Page numeric ID under Organization Settings on the Connections page. Leave empty to post as your personal profile

**Where can I find my LinkedIn Page ID?**

Visit your LinkedIn Page in a browser. The Page ID is the numeric string in the URL after `/company/`. For example, `https://www.linkedin.com/company/1234567/` — the Page ID is `1234567`.

**Why does the LinkedIn Organization dropdown show nothing?**

The `organizationalEntityAcls` endpoint requires the **Community Management API** product, which must be approved by LinkedIn/Microsoft. This product can only be requested for new developer applications (not ones with existing products). Without it, SocialSync cannot list your organizations — you must enter the Page ID manually.

**How long does the OAuth token last?**

- **X** — access tokens last 2 hours. With the `offline.access` scope, a refresh token is provided for automatic renewal
- **LinkedIn** — access tokens last approximately 1 year. A refresh token may also be provided depending on the app configuration
- **Facebook** — user tokens last about 60 days. Page tokens may last longer. Tokens are refreshed when expired
- **Bluesky** — session tokens last 24 hours. A refresh JWT is stored for automatic re-authentication

**Can I schedule a post in advance?**

Yes. Go to **SocialSync → Schedule**, set the content, platforms, and a future date/time. The post is processed by WP-Cron when the scheduled time arrives.

---

## Troubleshooting

### OAuth Errors

- **"State mismatch"** — The OAuth state parameter expired (5-minute TTL) or was manipulated. Click Connect again to start a fresh flow
- **"Access denied" / 403** — The app may not have the required permissions. Verify the scopes in your developer portal match those listed in the setup guide
- **"Invalid redirect URI"** — Ensure the OAuth Redirect URL on the Connections page is exactly listed in your app's authorized redirect URIs

### LinkedIn Community Management API

The `/organizationalEntityAcls` endpoint returns 403 unless your LinkedIn app has the Community Management API product approved. Without this:

- You cannot list your organizations in a dropdown
- You can still post as an organization by entering the Page ID manually
- You can still post as your personal profile without any additional approvals

### cURL Timeout Errors

If you see timeout errors in the log, the social platform may be slow to respond (Facebook can take 20-40 seconds). The API handler uses a 60-second timeout. If you still see timeouts, check your server's outbound connectivity and firewall settings.

### Encryption Key Migration

If your `wp-config.php` `AUTH_KEY` value changes, all encrypted credentials become unreadable. This is intentional and by design. After a key change:

1. Go to **SocialSync → Connections**
2. Disconnect and reconnect each platform
3. This re-encrypts your credentials with the new key

No data is lost — credentials simply need to be re-entered.

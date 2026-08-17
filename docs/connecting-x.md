# Connecting X (Twitter)

Socync connects to X using **OAuth 2.0 Authorization Code with PKCE** (the recommended, current method) and keeps a **legacy OAuth 1.0a** path for older setups. New connections should always use OAuth 2.0.

> **Redirect URL for this platform:** copy this value from the X tab in **Socync → Connections** (it is also shown below) and paste it into your X app:
>
> ```
> https://your-site-url/wp-admin/admin-post.php?action=socync_oauth_callback_x
> ```

## Part 1 — Create the app in the X Developer Portal

1. Go to [developer.x.com](https://developer.x.com) and sign in (log in as the account that owns the X handle you want to post to).
2. Open **Projects & Apps** and click **Create Project**, then **Create App** inside the project. Choose **Web App** as the app type.
3. Open **User Authentication Settings** for the app:
   - Enable **OAuth 2.0**.
   - Set **App Type** to **Web App** (this is a confidential client — required so Socync can securely exchange and refresh tokens).
   - Under **Redirect URL**, paste the Socync redirect URL:
     ```
     https://your-site-url/wp-admin/admin-post.php?action=socync_oauth_callback_x
     ```
     Make sure it matches the value shown on the Connections page **exactly** (HTTPS, no trailing slash).
4. Under **Permissions** (scopes), check:
   - **Read and Write** (so Socync can create posts), and
   - **Offline access** (so Socync can refresh the token without you re-authorizing).
   These map to the scopes `tweet.read`, `tweet.write`, `users.read`, and `offline.access`.
5. Save the authentication settings.
6. Open the **Keys and Tokens** tab and copy two values:
   - **Client ID**
   - **Client Secret**

> The authorization flow must complete within 5 minutes of clicking Connect. If you are interrupted, just click **Connect** again.

## Part 2 — Connect in Socync

1. Go to **Socync → Connections → X (Twitter)**.
2. If you are not already connected, scroll to the **Connect Account** section.
3. Paste the **Client ID** and **Client Secret** from your X app.
4. Click **Connect**. You are redirected to `x.com/i/oauth2/authorize`, where you sign in to X and approve the requested permissions (Read, Write, and Offline access).
5. You are redirected back automatically, and the X tab shows **Connected**.

That is it. Socync stores the access token and refreshes it automatically using the `offline.access` refresh token (client secret is sent via HTTP Basic auth to `https://api.x.com/2/oauth2/token`). If the refresh ever fails, you will be asked to reconnect.

## Part 3 — Verify

1. Publish a WordPress post (or create a scheduled post) with X enabled in **Socync → Settings → Autoposting**.
2. Check **Socync → Log** — a successful post returns "Posted to X (Tweet ID: …)".

## X troubleshooting

| Symptom | Cause / fix |
|---|---|
| Connection fails with "access_denied" or a scope error | The app does not have the required scopes. In the X Developer Portal, enable **Read and Write** + **Offline access** in User Authentication Settings, then reconnect. |
| Publish returns 401 | The app's OAuth configuration is wrong — check App Type (**Web App**), the exact Redirect URL, and that scopes are enabled. Reconnect after fixing. |
| "Client is not permitted to perform this action" / write fails | The app only has Read permission. Enable **Read and Write** and reconnect. |
| "Invalid redirect URI" when authorizing | The redirect URL in the X app does not match the one on the Connections page. Copy it again, character for character (HTTPS, no trailing slash). |
| "Invalid OAuth state parameter" | The 5-minute window lapsed. Click **Connect** again. |

---

# Legacy: OAuth 1.0a (older setups only)

Socync still supports X's older **OAuth 1.0a** method for installations that were configured before OAuth 2.0 was introduced. It is detected automatically: if the plugin finds the four legacy credential options (`socync_x_api_key`, `socync_x_api_key_secret`, `socync_x_access_token`, `socync_x_access_token_secret`) and no OAuth 2.0 token, it signs API requests with OAuth 1.0a HMAC-SHA1.

> **Important:** the current Socync Connections page only offers the OAuth 2.0 form. There is no legacy form in the UI. If you are setting up a brand-new connection, use [Part 1 / Part 2](#part-1--create-the-app-in-the-x-developer-portal) (OAuth 2.0). The steps below are for applying OAuth 1.0a credentials to an existing/advanced setup that needs them.

## 1. Get the OAuth 1.0a credentials

1. Go to [developer.x.com](https://developer.x.com) → your project's app → **Keys and Tokens**.
2. Under **API Key and Secret**, copy:
   - **API Key** (also called Consumer Key)
   - **API Key Secret** (also called Consumer Secret)
3. Under **Access Token and Secret**, copy:
   - **Access Token**
   - **Access Token Secret**
   If they are not shown, regenerate them. The access token must have **Read and Write** permission (check the token's permission level in the portal).

## 2. Store the credentials in Socync

Socync reads these four credential options, plus one "connected" flag:

| Option | Value |
|---|---|
| `socync_x_api_key` | API Key |
| `socync_x_api_key_secret` | API Key Secret |
| `socync_x_access_token` | Access Token |
| `socync_x_access_token_secret` | Access Token Secret |
| `socync_x_connected` | `1` — marks X as connected so the UI shows **Connected** and autoposting is enabled |

There is no admin form for these, so set them from the command line (WP-CLI) on your server:

```bash
wp option update socync_x_api_key "YOUR_API_KEY"
wp option update socync_x_api_key_secret "YOUR_API_KEY_SECRET"
wp option update socync_x_access_token "YOUR_ACCESS_TOKEN"
wp option update socync_x_access_token_secret "YOUR_ACCESS_TOKEN_SECRET"
wp option update socync_x_connected 1
```

The four credential options must all be present and non-empty — the provider only enters legacy mode when the complete set exists and no OAuth 2.0 token is stored.

> **Why `socync_x_connected` is required:** the provider itself signs requests as soon as the four credential options exist, but the admin UI, the Autoposting checkbox, and auto-post-on-publish all check the separate `socync_x_connected` flag (the OAuth 2.0 flow sets it automatically). Without it, the X tab still shows the Connect form and auto-posting stays disabled even though the provider can technically publish.

## 3. Verify

With the five options set (the four credentials plus `socync_x_connected = 1`), the X tab in **Socync → Connections** shows **Connected**, the X checkbox in **Socync → Settings → Autoposting** is enabled, and auto-posts are delivered. Publish a WordPress post (or create a scheduled post) with X enabled and confirm it appears on **Socync → Log**.

## Legacy troubleshooting

- **Posts fail with 401 Unauthorized:** the access token lacks Write permission (regenerate it with Read and Write), or the API Key/Secret were rotated — update the stored options.
- **Switching to OAuth 2.0:** remove the four legacy options (or click **Disconnect**, which deletes both credential sets), then run the OAuth 2.0 flow in Parts 1–2 above.
- X recommends OAuth 2.0 for all new integrations; treat OAuth 1.0a as a compatibility path.
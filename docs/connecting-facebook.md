# Connecting Facebook

Socync connects to Facebook with **OAuth 2.0** using the **Facebook Graph API** (v25). You create a **Business** app, add the Page permissions (`pages_manage_posts`, `pages_read_engagement`), and then choose whether posts go to a **Facebook Page** or your personal **Timeline**.

> **Redirect URL for this platform:** copy this value from the Facebook tab in **Socync → Connections** (it is also shown below) and paste it into your Facebook app:
>
> ```
> https://your-site-url/wp-admin/admin-post.php?action=socync_oauth_callback_facebook
> ```

## Part 1 — Create the app in the Meta Developer Portal

1. Go to [developers.facebook.com](https://developers.facebook.com) and sign in as the account that administers the Facebook Page (or profile) you want to post to.
2. Open **My Apps** and click **Create App**.
3. Choose **Business** as the app type and complete the setup wizard.
4. Add the **`pages_manage_posts`** and **`pages_read_engagement`** permissions to the app — Socync requests exactly these two. The product through which you add them varies over time (currently **Facebook Login for Business**; older guides may refer to a **Pages API** product). For real (non-tester) users you may also need **Advanced Access** for these permissions.
5. Go to **Settings → Basic** and copy:
   - **App ID** (Socync labels this field **Client ID**)
   - **App Secret** (Socync labels this field **Client Secret**)
6. Go to **Facebook Login → Settings**:
   - Under **Valid OAuth Redirect URIs**, add the Socync redirect URL:
     ```
     https://your-site-url/wp-admin/admin-post.php?action=socync_oauth_callback_facebook
     ```
     Exact match required (HTTPS, no trailing slash).

> The authorization flow must complete within 5 minutes of clicking Connect.

## Part 2 — Connect in Socync

1. Go to **Socync → Connections → Facebook**.
2. Scroll to the **Connect Account** section.
3. Paste the **Client ID** (= App ID) and **Client Secret** (= App Secret).
4. Click **Connect**. You are redirected to Facebook's login dialog (`https://www.facebook.com/v25.0/dialog/oauth`), where you approve the app and its permissions.
5. You are redirected back automatically, the Facebook tab shows **Connected**, and Socync automatically fetches and caches the list of Pages you administer.

Socync keeps your access token refreshed automatically (it extends it with the `fb_exchange_token` grant against the Graph API). The stored token and any selected Page token are used for publishing via `POST https://graph.facebook.com/v25.0/{page_id}/feed` (or `/me/feed` for your timeline).

## Part 3 — Choose where to post

1. In the Facebook tab, find the **Page Settings** section (it appears once connected and pages are cached).
2. Choose a destination:
   - **A Page** — pick it from the **Post to Page** dropdown and click **Save Page Selection**. Socync stores that Page's access token separately and posts as the Page.
   - **Post as User (Timeline)** — select the empty option at the top of the list. Posts go to the connecting profile's timeline.

If the list is empty, your account administers no Pages (or none are visible to the app). In that case the Page Settings dropdown is not shown and posts go to the connecting profile's timeline by default. To post to a Page instead, connect with an account that manages one.

## Part 4 — Verify

1. Publish a WordPress post (or create a scheduled post) with Facebook enabled in **Socync → Settings → Autoposting**.
2. Check **Socync → Log** — a successful post returns "Facebook Post #… created successfully."

## Facebook troubleshooting

| Symptom | Cause / fix |
|---|---|
| "App is in development mode" when authorizing or publishing | Apps in Development mode only work for people with a role on the app. Either add the connecting profile as an app tester/role, or switch the app to **Live** (Settings → Basic → App Mode → Live). |
| "Invalid redirect URI" | The valid OAuth redirect URI in **Facebook Login → Settings** does not match the Connections page value exactly. Re-copy it (HTTPS, no trailing slash). |
| Pages list is empty after connecting | The connected account does not administer a Page visible to the app, or the app lacks the Page permissions (`pages_manage_posts` / `pages_read_engagement`). Verify the Page exists and your role on it, then reconnect. |
| Publishing to a Page fails with permission errors | The Page token is missing or outdated — re-select the Page in **Page Settings**, or disconnect and reconnect. |
| "Selected Facebook page not found" when saving | The cached pages list is stale or the Page token is missing. Disconnect, reconnect, then re-select the Page. |
| "Invalid OAuth state parameter" | The 5-minute window lapsed. Click **Connect** again. |
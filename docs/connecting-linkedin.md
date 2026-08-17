# Connecting LinkedIn

Socync connects to LinkedIn with **OAuth 2.0** and publishes using the **LinkedIn Posts API** (`/rest/posts`). You must add the **Posts API** product to your app — without it, publishing fails even though connecting succeeds (the **Share on LinkedIn** product alone does not grant access to it).

> **Redirect URL for this platform:** copy this value from the LinkedIn tab in **Socync → Connections** (it is also shown below) and paste it into your LinkedIn app:
>
> ```
> https://your-site-url/wp-admin/admin-post.php?action=socync_oauth_callback_linkedin
> ```

## Part 1 — Create the app in the LinkedIn Developer Portal

1. Go to [developer.linkedin.com](https://developer.linkedin.com) and sign in (as the profile that owns the LinkedIn account/Page you want to post to).
2. Open **My Apps** and click **Create App**.
   - Give the app a name (e.g. "Socync"), select the **LinkedIn Page** your company/organization operates (required to create the app), and confirm the app logo.
3. In the **Products** tab, add the **Posts API** product — this is what Socync actually publishes with — and the **Share on LinkedIn** product for the posting permissions.
4. In the **Auth** tab:
   - Under **Authorized redirect URLs for your app**, add the Socync redirect URL:
     ```
     https://your-site-url/wp-admin/admin-post.php?action=socync_oauth_callback_linkedin
     ```
     Exact match required (HTTPS, no trailing slash).
   - Copy the **Client ID** and **Client Secret** from the Auth tab. These are the credentials Socync needs.
5. If the Posts API product needs access approval from LinkedIn, request it and wait for it to be granted before connecting.

> The authorization flow must complete within 5 minutes of clicking Connect.

## Part 2 — Connect in Socync

1. Go to **Socync → Connections → LinkedIn**.
2. Scroll to the **Connect Account** section.
3. Paste the **Client ID** and **Client Secret** from your LinkedIn app.
4. Click **Connect**. You are redirected to LinkedIn's authorization page (`https://www.linkedin.com/oauth/v2/authorization`), where you sign in and approve the requested permissions.
5. You are redirected back automatically and the LinkedIn tab shows **Connected**.

During connection Socync requests these scopes:

```
w_member_social w_organization_social r_organization_social r_liteprofile r_emailaddress
```

It automatically fetches and stores your LinkedIn **person ID** so it can post to your personal profile by default. Access tokens are refreshed automatically **if LinkedIn issues a refresh token to your app** (eligibility depends on your app's approval level and can change over time); otherwise the access token lasts about 60 days, and when it expires you will be asked to reconnect and authorize again.

## Part 3 — Choose who to post as

By default Socync posts to your **personal profile**. To post as a LinkedIn **Page (organization)** instead:

1. In the LinkedIn tab, go to the **Organization Settings** section.
2. Enter your Page's numeric **LinkedIn Page ID** and click **Save Page ID**.
3. Leave the field empty to post as yourself again.

Finding your Page ID: LinkedIn documents it in the [help article "Find your Page ID"](https://www.linkedin.com/help/linkedin/answer/a521928) — the numeric ID is also visible in the "About this Page" section of your Page. Socync supports one author at a time (either the profile or one Page ID); it cannot post to multiple organizations in a single connection.

Posts are published as `urn:li:person:<id>` or `urn:li:organization:<id>` via `POST https://api.linkedin.com/rest/posts`. When you include a link, Socync reads the page's Open Graph metadata and attaches the article title, description, and thumbnail (uploaded through the LinkedIn Images API, max 5 MB).

## Part 4 — Verify

1. Publish a WordPress post (or create a scheduled post) with LinkedIn enabled in **Socync → Settings → Autoposting**.
2. Check **Socync → Log** — a successful post returns "Posted to LinkedIn (Post ID: …)".

## LinkedIn troubleshooting

| Symptom | Cause / fix |
|---|---|
| Connection works but publishing fails with 401/403 | The **Posts API** product is not added to your app (only "Share on LinkedIn" is). Add **Posts API** in the Products tab, request access, and reconnect. |
| "ACCESS_DENIED" / "This app is not allowed to create posts" | The app or the Posts API product has not been approved/activated, or the connected profile lacks permission. Request access in the developer portal and reconnect. |
| "Invalid redirect URI" | The authorized redirect URL in the LinkedIn app does not match the Connections page value exactly. Re-copy it (HTTPS, no trailing slash). |
| Posts appear from the wrong author | Check the **Organization Settings** Page ID on the LinkedIn tab — remove it to post as your personal profile. |
| "No LinkedIn profile or organization selected" when publishing | Neither a person ID nor a Page ID is stored. Disconnect and reconnect, then re-save the Page ID if needed. |
| "Invalid OAuth state parameter" | The 5-minute window lapsed. Click **Connect** again. |
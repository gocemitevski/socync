# Connecting Bluesky

Bluesky does **not** use OAuth. Instead, Socync authenticates with your Bluesky **identifier** (handle or email) and a generated **App Password** — never your main account password.

> Bluesky has no redirect URL: there is no developer app to create and nothing to paste into a callback field.

## Part 1 — Create an App Password

1. Sign in to [bsky.app](https://bsky.app) with the account you want to post from.
2. Go to **Settings → App Passwords** (under Privacy & Security).
3. Click **Add App Password**.
4. Give it a descriptive name — for example **`Socync`**.
5. Copy the generated password. It looks like a random token with dashes, e.g. `abcd-efgh-ijkl-mnop`.
6. Keep it somewhere safe. You cannot see it again after this screen — if you lose it, delete the app password and generate a new one.

> Use the app password, **not** your Bluesky account password. The account password will not work and sharing it is unsafe.

## Part 2 — Connect in Socync

1. Go to **Socync → Connections → Bluesky**.
2. Scroll to the **Connect Account** section.
3. Enter your **Identifier**:
   - your Bluesky handle, e.g. `you.bsky.social`, **or**
   - your Bluesky sign-in email address.
4. Paste the **App Password** you generated.
5. Click **Connect**.

Socync immediately validates the credentials against Bluesky's session API (`https://bsky.social/xrpc/com.atproto.server.createSession`). On success the Bluesky tab shows **Connected**. If validation fails, Socync clears the entered credentials and shows an error — nothing is stored.

Once connected, Socync stores the session and refreshes it automatically (using the refresh JWT, falling back to a fresh sign-in) so posts keep flowing. Reconnection is only needed if the app password is revoked.

## Part 3 — Verify

1. Publish a WordPress post (or create a scheduled post) with Bluesky enabled in **Socync → Settings → Autoposting**.
2. Check **Socync → Log** — a successful post returns "Posted to Bluesky (URI: …)".

When you post a link, Bluesky gets a link-card embed: Socync reads the page's Open Graph metadata and uploads the thumbnail (JPEG/PNG/GIF/WebP, max 5 MB) to `com.atproto.repo.uploadBlob`.

## Bluesky troubleshooting

| Symptom | Cause / fix |
|---|---|
| "Failed to authenticate with Bluesky" | The identifier or app password is wrong. Generate a fresh app password at **Settings → App Passwords** and try again. |
| "Invalid app password" in the log | You entered your main password instead of an app password, or the app password was revoked. Create a new one and reconnect. |
| Posts fail after the app password was revoked | Reconnection is required. In the Bluesky tab, click **Disconnect**, then connect again with a new app password. |
| Thumbnail missing on link posts | The linked page has no `og:image`, or the image is larger than 5 MB / blocked by the SSRF guard. The text post still publishes. |
# Remotes Web Push

Remotes can send silent, generic notifications to opted-in browser and PWA subscriptions even when the page is closed. These are standard Web Push requests, with no payload, message text, sender, or conversation identifier sent to the browser push provider. Notifications open the Remotes inbox. Normal in-page sound and tab alerts remain independent.

## Server setup

Generate a stable VAPID key pair using `Minishlink\WebPush\VAPID::createVapidKeys()` in a private administrative environment. Set `WEB_PUSH_PUBLIC_KEY`, `WEB_PUSH_PRIVATE_KEY`, and `WEB_PUSH_SUBJECT` in the server environment. The subject must be a public HTTPS URL or `mailto:` contact. Keep the private key outside source control, logs, and application data. Keep the pair stable: changing it requires browsers to disable and re-enable notifications.

Run the migration and keep the application's queue worker running. Without the key pair, the application keeps its existing page alerts and does not queue Web Push delivery. Nothing in this change configures a production environment.

## Browser setup

Choose **Allow notifications** in Settings → Profile → Remotes. Each browser must opt in. On iPhone and iPad, install the site on the Home Screen and opt in from that app. Browser and OS permissions, Focus settings, and background delivery affect arrival; fully quitting a browser may prevent delivery until it runs again. Custom message sound plays only while the site is open.

## Delivery and retention

Delivery waits ten seconds, then rechecks unread state, conversation membership and history, request status, mutes, blocks, and whether Remotes is enabled. Messages older than five minutes are not sent. Requests expire at the provider after five minutes; a shared topic and notification tag replace bursts. Temporary network failures, rate limits, and server errors retry with a thirty-second delay, up to three attempts. The same generic notification may be replaced during retries.

The database stores only an encrypted endpoint, its SHA-256 lookup hash, the owning user, and a row id for model identity and pagination. No browser encryption keys, authentication secrets, timestamps, device names, message payloads, or delivery history are stored. Only the server uses endpoints; exports include subscription ids and exclude delivery endpoints. Subscriptions are deleted when notifications are disabled in that browser, that browser signs out, Remotes is disabled, the account is deleted, or the provider returns 404/410. Reusing the browser under another account associates its subscription only with the current account.

Supported endpoint hosts are Google's FCM, Mozilla's push service, Apple's web push service, and Microsoft's `*.notify.windows.com` service. Registration rejects other destinations and custom ports; delivery never follows redirects.

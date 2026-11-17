# /dev/null lite

The iOS app whose web view loads dev-null.sh, in a Capacitor project. A site deploy updates it without an App Store release. Only its native parts need a release: the tab bar, and whatever plugins join it.

The name "lite" is on the App Store, in the site's code (`isLiteMobileApp`) and in the user agent token (`DevNullLite/`). The home screen and the app itself say `/dev/null`, and the bundle ID stays neutral (`sh.devnull.app`) because it can't change once a build is uploaded. If the app gets renamed, the site can accept both tokens until old installs are gone. It is not the NativePHP app, which the site calls the mobile app.

## Run it

From this directory, against a local copy of the site (`composer dev` at the repository's root):

```bash
bun install
CAP_SERVER_URL=http://localhost:8000 bun run sync
bun run ios
```

Run the `App` scheme on a simulator from Xcode. Without `CAP_SERVER_URL`, the app loads the production site. The build has to be signed, even ad hoc (`CODE_SIGN_IDENTITY=-`), or the Keychain refuses the device key. Locally, the sign-in sheet shows the site's local sign-in rather than GitHub. On a real iPhone, sign with your own team and a temporary bundle ID, never `sh.devnull.app`, which can only ever belong to one team.

## How it fits together

- `capacitor.config.ts`: the site's URL, and the user agent token that tells the site it's in the app. The token is for the UI only: anything can send it.
- `ios/App/App/MainViewController.swift`: a native tab bar around the one web view, which moves to the selected tab.
- `ios/App/App/TabsPlugin.swift`: the bridge the site drives the tab bar with, from `resources/js/lib/lite-mobile-app.ts`. Everything crossing it is untrusted, because a script injected in the site reaches it too.
- `ios/App/App/AppCoordinator.swift`: what the app shows, and signing in. See below.
- `ios/App/App/BridgeFilters.swift`: only the main frame reaches plugins, and only the ones the site uses. Capacitor always registers the others, and `CapacitorHttp` would hand a page script the session cookie.
- `ios/App/App/RailPageView.swift`: the native screens, drawn like the site's standalone pages, with colors copied from its tokens.
- `www/offline.html`: shown when the site can't load. A copy of the site's `public/offline.html` with absolute links, its fonts and a retry through the app, to keep in sync by hand.
- `www/fonts/`: the site's fonts, for that page and the native screens, which find them through `bun run sync`.
- `assets/`: the app icon's sources, light and dark, for `bun run assets`. The launch screen's logo is `Lockup` in the asset catalog: Geist's outlines, not text.

## Signing in

The app is a device, like the other apps: a key in the Keychain, approved through `/mobile/authorize` in the system's sign-in sheet, which shares Safari's sessions, except right after signing out, for another account. A key in the Keychain means signed in, and it never crosses the bridge. Without one, the app shows its welcome screen, never the signed-out site.

The web view's session comes from that key: a signed `POST /api/v1/me/web-session` answers with cookies, which the app copies to the web view before it loads. When the session ends, the site asks the app for a new one, and the page never sees the answer. The site ending a session for good means deleting the device: the next renewal fails, and the app forgets the key and everything in the web view.

Reinstalling keeps the Keychain, so a shared phone signs back in as the previous account. Sign out first.

## Not there yet

Sign in with Apple, push notifications, and deep links. Signing in needs a security review before it reaches production.

## Releasing

`DEVELOPMENT_TEAM=… bun run testflight` archives against production and uploads to App Store Connect, from a regular terminal: `xcodebuild` reads Xcode's account from the login keychain, which a sandboxed shell can't. The script hasn't run against this project yet, because the publishing team isn't set up. Signing keys never go in this repository, which is public.

The bundle ID can't change once a build is uploaded.

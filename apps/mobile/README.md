# /dev/null lite

The iOS app whose web view loads dev-null.sh, in a Capacitor project. A site deploy updates it without an App Store release. Only its native parts need a release: the tab bar, and whatever plugins join it.

The name "lite" only exists on the App Store: the home screen and the app itself say `/dev/null`, and the code says "web view" (`sh.devnull.app`, `DevNullWebView/`), so that the store name can change alone. It is not the NativePHP app, which the site calls the mobile app.

## Run it

From this directory, against a local copy of the site (`composer dev` at the repository's root):

```bash
bun install
CAP_SERVER_URL=http://localhost:8000 bun run sync
bun run ios
```

Run the `App` scheme on a simulator from Xcode. Without `CAP_SERVER_URL`, the app loads the production site.

## How it fits together

- `capacitor.config.ts`: the site's URL, and the user agent token that tells the site it's in the app. The token is for the UI only: anything can send it.
- `ios/App/App/MainViewController.swift`: a native tab bar around the one web view, which moves to the selected tab.
- `ios/App/App/TabsPlugin.swift`: the bridge the site drives the tab bar with, from `resources/js/lib/webview-app.ts`. Everything crossing it is untrusted, because a script injected in the site reaches it too.
- `www/offline.html`: shown when the site can't load. A copy of the site's `public/offline.html` with absolute links, to keep in sync by hand.
- `assets/`: icon and splash sources, for `bun run assets`.

## Not there yet

Signing in on production (GitHub leaves the web view for Safari, so nothing comes back), push notifications, and deep links. Signing in needs a security review before it reaches production.

## Releasing

`DEVELOPMENT_TEAM=… bun run testflight` archives against production and uploads to App Store Connect, from a regular terminal: `xcodebuild` reads Xcode's account from the login keychain, which a sandboxed shell can't. The script hasn't run against this project yet, because the publishing team isn't set up. Signing keys never go in this repository, which is public.

The bundle ID can't change once a build is uploaded.

import AuthenticationServices
import CryptoKit
import UIKit
import WebKit

/// What the app shows, depending on whether it's signed in. A key in the Keychain
/// means signed in, and the web view gets its session from it: the key is the one
/// lasting credential, and the session can always be made again.
@MainActor
final class AppCoordinator: NSObject, ASWebAuthenticationPresentationContextProviding {
    private(set) static var shared: AppCoordinator?

    private static let cookieNames = "session-cookie-names"
    private static let signedOut = "signed-out"

    private let window: UIWindow
    private var main: MainViewController?
    private var authentication: ASWebAuthenticationSession?
    private var isSigningIn = false

    private var isRenewing = false
    private var failedRenewals = 0
    private var nextRenewal = Date.distantPast
    private var lastRenewal = Date.distantPast

    init(window: UIWindow) {
        self.window = window
    }

    func start() {
        Self.shared = self

        guard let key = DeviceKey.load() else {
            showWelcome()
            return
        }

        // The site's first load has to find the cookies, so the web view waits for them
        window.rootViewController = LaunchViewController()

        Task {
            if await hasLiveSession() {
                showMain()
            } else {
                await openSession(key: key)
            }
        }
    }

    // MARK: Signing in

    func signIn() {
        guard !isSigningIn else { return }

        isSigningIn = true

        let key = DeviceKey.generate()
        let verifier = Data.random(count: 32).base64URLEncoded
        let state = UUID().uuidString

        var components = URLComponents(url: Site.url(path: "/mobile/authorize"), resolvingAgainstBaseURL: false)!
        components.queryItems = [
            URLQueryItem(name: "fingerprint", value: key.fingerprint),
            URLQueryItem(name: "device_name", value: String(UIDevice.current.name.prefix(100))),
            URLQueryItem(name: "app_id", value: "ios:sh.devnull.app"),
            URLQueryItem(name: "app_name", value: "/dev/null lite"),
            URLQueryItem(name: "code_challenge", value: Data(SHA256.hash(data: Data(verifier.utf8))).base64URLEncoded),
            URLQueryItem(name: "code_challenge_method", value: "S256"),
            URLQueryItem(name: "state", value: state),
            URLQueryItem(name: "redirect_uri", value: "devnull://lite/auth"),
        ]
        // URLComponents leaves "+" as is, which the site reads back as a space
        components.percentEncodedQuery = components.percentEncodedQuery?.replacingOccurrences(of: "+", with: "%2B")

        // The scheme isn't declared in Info.plist, as the other apps own it: the
        // session gets its callback itself, whichever apps are installed
        let session = ASWebAuthenticationSession(url: components.url!, callbackURLScheme: "devnull") { url, _ in
            Task { @MainActor in
                self.authentication = nil

                if let url {
                    await self.finishSignIn(callback: url, key: key, verifier: verifier, state: state)
                }

                self.isSigningIn = false
            }
        }

        session.presentationContextProvider = self
        // After signing out, for another account: reusing Safari's would sign the same one back in
        session.prefersEphemeralWebBrowserSession = UserDefaults.standard.bool(forKey: Self.signedOut)
        authentication = session
        session.start()
    }

    func presentationAnchor(for session: ASWebAuthenticationSession) -> ASPresentationAnchor {
        window
    }

    private func finishSignIn(callback: URL, key: DeviceKey, verifier: String, state: String) async {
        let items = URLComponents(url: callback, resolvingAgainstBaseURL: false)?.queryItems ?? []
        let value = { (name: String) in items.first { $0.name == name }?.value }

        guard callback.scheme == "devnull", callback.host == "lite", callback.path == "/auth", value("state") == state else {
            return
        }

        // Without a code, the person declined on the approval page
        guard let code = value("code") else {
            return
        }

        do {
            try await SiteAPI(key: key).redeem(code: code, verifier: verifier)
            try key.save()
        } catch {
            (window.rootViewController as? WelcomeViewController)?.showError("Couldn't sign in. Please try again.")
            return
        }

        UserDefaults.standard.removeObject(forKey: Self.signedOut)

        await openSession(key: key)
    }

    // MARK: The web view's session

    private func hasLiveSession() async -> Bool {
        let names = UserDefaults.standard.stringArray(forKey: Self.cookieNames) ?? []
        let cookies = await WKWebsiteDataStore.default().httpCookieStore.allCookies()

        return cookies.contains { cookie in
            names.contains(cookie.name) && (cookie.expiresDate ?? .distantPast) > Date(timeIntervalSinceNow: 60)
        }
    }

    /// Writes the cookies for the web view's first load, or its next one.
    private func install(_ cookies: [HTTPCookie]) async {
        let store = WKWebsiteDataStore.default().httpCookieStore

        for cookie in cookies {
            await store.setCookie(cookie)
        }

        UserDefaults.standard.set(cookies.map(\.name), forKey: Self.cookieNames)
    }

    /// On launch or after signing in, before there's a web view.
    private func openSession(key: DeviceKey) async {
        do {
            let session = try await SiteAPI(key: key).webSession()
            await install(session.cookies)
            showMain(path: session.isOnboarded ? nil : "/onboarding")
        } catch SiteAPIError.notApproved {
            await forgetSignIn()
        } catch SiteAPIError.banned {
            showBanned()
        } catch SiteAPIError.unreachable {
            // The web view shows the offline page, whose retry comes back through `renewSession`
            showMain()
        } catch {
            window.rootViewController = MessageViewController(
                message: "Couldn't reach /dev/null. Please try again.",
                action: "Try again"
            ) { [weak self] in
                self?.start()
            }
        }
    }

    /// Whenever the site reports its session ended. It may report it many times in a
    /// row, so there's one renewal at a time, and failures wait longer each time.
    /// Someone waiting for an answer doesn't wait for the next try.
    func renewSession(immediately: Bool = false) {
        guard let main, !isRenewing, immediately || main.presentedViewController == nil else { return }

        // The offline page's retry is someone tapping, and the session may be fine
        if !main.isShowingSite {
            nextRenewal = .distantPast
            lastRenewal = .distantPast
        } else if immediately {
            nextRenewal = .distantPast
        }

        guard Date() >= nextRenewal, let key = DeviceKey.load() else { return }

        // A session that just renewed and already ended won't work by trying again
        guard Date().timeIntervalSince(lastRenewal) > 30 else {
            nextRenewal = Date(timeIntervalSinceNow: 60)
            alertRenewalFailed()
            return
        }

        isRenewing = true

        Task {
            defer { isRenewing = false }

            do {
                let session = try await SiteAPI(key: key).webSession()
                await install(session.cookies)
                failedRenewals = 0
                lastRenewal = Date()
                // Requests the page sent before this renewal may still come back refused
                nextRenewal = Date(timeIntervalSinceNow: 3)
                main.reloadSite(path: session.isOnboarded ? nil : "/onboarding")
            } catch SiteAPIError.notApproved {
                await forgetSignIn()
            } catch SiteAPIError.banned {
                showBanned()
            } catch SiteAPIError.unreachable {
                waitBeforeNextRenewal()
            } catch SiteAPIError.failed(status: 503) where main.isShowingSite {
                // The site is in maintenance, which the page already says
                waitBeforeNextRenewal()
            } catch {
                waitBeforeNextRenewal()
                alertRenewalFailed()
            }
        }
    }

    private func waitBeforeNextRenewal() {
        failedRenewals += 1
        nextRenewal = Date(timeIntervalSinceNow: min(60, pow(2, Double(failedRenewals))))
    }

    private func alertRenewalFailed() {
        guard let main, main.presentedViewController == nil else { return }

        let alert = UIAlertController(title: nil, message: "Couldn't reconnect to /dev/null.", preferredStyle: .alert)
        alert.addAction(UIAlertAction(title: "Not now", style: .cancel) { [weak self] _ in
            self?.nextRenewal = Date(timeIntervalSinceNow: 60)
        })
        alert.addAction(UIAlertAction(title: "Try again", style: .default) { [weak self] _ in
            self?.lastRenewal = .distantPast
            self?.renewSession(immediately: true)
        })
        main.present(alert, animated: true)
    }

    // MARK: Signing out

    /// The site deleted the device, because the person signed out or revoked it:
    /// nothing of this account stays. Capacitor keeps a copy of the web view's cookies
    /// in the shared storage, which would otherwise come back.
    private func forgetSignIn() async {
        DeviceKey.delete()
        main = nil
        failedRenewals = 0
        nextRenewal = .distantPast
        lastRenewal = .distantPast

        await WKWebsiteDataStore.default().removeData(
            ofTypes: WKWebsiteDataStore.allWebsiteDataTypes(),
            modifiedSince: .distantPast
        )
        HTTPCookieStorage.shared.removeCookies(since: .distantPast)
        UserDefaults.standard.removeObject(forKey: Self.cookieNames)
        UserDefaults.standard.set(true, forKey: Self.signedOut)

        showWelcome()
    }

    // MARK: Screens

    private func showWelcome() {
        main = nil
        window.rootViewController = WelcomeViewController(
            showsPromptHint: !UserDefaults.standard.bool(forKey: Self.signedOut)
        ) { [weak self] in
            self?.signIn()
        }
    }

    private func showMain(path: String? = nil) {
        let main = MainViewController()
        self.main = main
        window.rootViewController = main

        // Capacitor starts loading the site with the view, so this comes right after
        if let path {
            main.reloadSite(path: path)
        }
    }

    private func showBanned() {
        main = nil
        window.rootViewController = MessageViewController(
            message: "This account has been banned from /dev/null.",
            action: "Sign out"
        ) { [weak self] in
            Task { await self?.forgetSignIn() }
        }
    }
}

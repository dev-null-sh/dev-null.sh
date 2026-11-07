import Capacitor
import WebKit

/// How the site says the web view's session has ended (see `renewExpiredSessions`
/// in resources/js/lib/lite-mobile-app.ts on the site). It takes nothing and answers
/// nothing: a script injected in the site can trigger a renewal, and no more.
@objc(SessionPlugin)
public class SessionPlugin: CAPPlugin, CAPBridgedPlugin {
    public let identifier = "SessionPlugin"
    public let jsName = "Session"
    public let pluginMethods: [CAPPluginMethod] = [
        CAPPluginMethod(name: "renew", returnType: CAPPluginReturnPromise),
    ]

    @objc func renew(_ call: CAPPluginCall) {
        call.resolve()

        Task { @MainActor in
            AppCoordinator.shared?.renewSession()
        }
    }

    /// The site's sign-in page leads to GitHub, which belongs in the sign-in sheet:
    /// a signed-in app only needs a new session.
    override public func shouldOverrideLoad(_ navigationAction: WKNavigationAction) -> NSNumber? {
        guard navigationAction.targetFrame?.isMainFrame == true,
              let url = navigationAction.request.url,
              Site.contains(url),
              url.path == "/login" else {
            return nil
        }

        Task { @MainActor in
            AppCoordinator.shared?.renewSession(immediately: true)
        }

        return true
    }
}

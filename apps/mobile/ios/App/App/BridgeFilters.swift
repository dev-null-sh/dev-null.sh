import Capacitor
import WebKit

/// Page scripts reach every native plugin through Capacitor's bridge, and they may
/// not all be ours: an injected script, or a third party's embed, since the message
/// handler is there in every frame. `CapacitorHttp` would make requests with the web
/// view's cookies and hand back the session cookie, and `WebView` would change what
/// the app loads. No API removes them, and any plugin class can be loaded by naming
/// it, so only the main frame gets through, and only to the plugins the site uses.
final class BridgeMessageFilter: NSObject, WKScriptMessageHandler {
    private static let plugins: Set<String> = ["Tabs", "Session", "Console"]

    private let handler: WKScriptMessageHandler

    init(handler: WKScriptMessageHandler) {
        self.handler = handler
    }

    func userContentController(_ userContentController: WKUserContentController, didReceive message: WKScriptMessage) {
        guard message.frameInfo.isMainFrame,
              let body = message.body as? [String: Any],
              body["type"] as? String == "message",
              let plugin = body["pluginId"] as? String,
              Self.plugins.contains(plugin) else {
            return
        }

        handler.userContentController(userContentController, didReceive: message)
    }
}

/// Capacitor also answers a few synchronous calls through `prompt()`, and would show
/// a native prompt for anything else: nothing from another frame gets there, the call
/// that sets cookies goes nowhere, and the rest goes to its handler untouched.
final class BridgePromptFilter: NSObject, WKUIDelegate {
    private let handler: WKUIDelegate

    init(handler: WKUIDelegate) {
        self.handler = handler
    }

    override func responds(to selector: Selector!) -> Bool {
        super.responds(to: selector) || handler.responds(to: selector)
    }

    override func forwardingTarget(for selector: Selector!) -> Any? {
        handler
    }

    func webView(
        _ webView: WKWebView,
        runJavaScriptTextInputPanelWithPrompt prompt: String,
        defaultText: String?,
        initiatedByFrame frame: WKFrameInfo,
        completionHandler: @escaping (String?) -> Void
    ) {
        guard frame.isMainFrame else {
            completionHandler(nil)
            return
        }

        let payload = (try? JSONSerialization.jsonObject(with: Data(prompt.utf8), options: .fragmentsAllowed)) as? [String: Any]

        if payload?["type"] as? String == "CapacitorCookies.set" {
            completionHandler("")
            return
        }

        handler.webView?(
            webView,
            runJavaScriptTextInputPanelWithPrompt: prompt,
            defaultText: defaultText,
            initiatedByFrame: frame,
            completionHandler: completionHandler
        )
    }
}

import UIKit
import Capacitor

/// What the site asks the tab bar to show, see `Tabs.set()` in
/// resources/js/lib/webview-app.ts on the site.
struct TabsState {
    struct Tab: Equatable {
        let id: String
        let title: String
        let icon: String
        let url: String
    }

    var tabs: [Tab] = []
    var badges: [String: String] = [:]
    var selected: String?
    var isVisible = false
    var isDark = true
    var tint: UIColor?
}

/// The bridge between the site and the native tab bar.
///
/// Everything crossing it is untrusted: a script injected in the site reaches
/// this plugin as easily as the site does.
@objc(TabsPlugin)
public class TabsPlugin: CAPPlugin, CAPBridgedPlugin {
    public let identifier = "TabsPlugin"
    public let jsName = "Tabs"
    public let pluginMethods: [CAPPluginMethod] = [
        CAPPluginMethod(name: "set", returnType: CAPPluginReturnPromise),
        CAPPluginMethod(name: "setTopColor", returnType: CAPPluginReturnPromise),
    ]

    @objc func set(_ call: CAPPluginCall) {
        var state = TabsState()

        // More than five would make iOS add its own "More" tab
        state.tabs = objects(call.getArray("tabs")).prefix(5).compactMap { object in
            guard let id = object["id"] as? String,
                  let title = object["title"] as? String,
                  let icon = object["icon"] as? String,
                  let url = object["url"] as? String,
                  siteURL(url) != nil else { return nil }

            if let badge = object["badge"] as? Int, badge > 0 {
                state.badges[id] = badge > 99 ? "99+" : String(badge)
            }

            return TabsState.Tab(id: id, title: title, icon: icon, url: url)
        }
        state.selected = call.getString("selected")
        state.isVisible = call.getBool("visible") ?? true
        state.isDark = call.getString("appearance") != "light"
        state.tint = call.getString("tint").flatMap(UIColor.init(hex:))

        DispatchQueue.main.async { [weak self] in
            guard let self, let main = self.main else { return }

            main.tabsPlugin = self
            main.apply(state)
        }

        call.resolve()
    }

    @objc func setTopColor(_ call: CAPPluginCall) {
        guard let color = call.getString("color").flatMap(UIColor.init(hex:)) else {
            call.reject("A color is needed, as #rrggbb")
            return
        }

        DispatchQueue.main.async { [weak self] in
            self?.main?.setTopColor(color)
        }

        call.resolve()
    }

    /// Reading down a page collapses the tab bar: the site hides its compose
    /// button meanwhile.
    func collapsed(_ collapsed: Bool) {
        notifyListeners("collapse", data: ["collapsed": collapsed])
    }

    /// A tap on a tab: the site visits it, or scrolls back to the top when it's
    /// the tab already shown.
    func select(_ tab: TabsState.Tab, reselected: Bool) {
        if hasListeners("select") {
            notifyListeners("select", data: ["id": tab.id, "url": tab.url, "reselected": reselected])
        } else {
            load(tab.url)
        }
    }

    /// Without the site's listeners (a page that failed to load, mostly), the web
    /// view loads the page itself.
    private func load(_ url: String) {
        guard let absolute = siteURL(url) else { return }

        bridge?.webView?.load(URLRequest(url: absolute))
    }

    /// The page of the site that `url` points to, or nil if it may leave the site.
    private func siteURL(_ url: String) -> URL? {
        guard isSitePath(url),
              let server = bridge?.config.serverURL,
              let absolute = URL(string: url, relativeTo: server)?.absoluteURL,
              absolute.scheme == server.scheme,
              absolute.host == server.host,
              absolute.port == server.port else { return nil }

        return absolute
    }

    private var main: MainViewController? {
        bridge?.viewController?.tabBarController as? MainViewController
    }

    private func objects(_ array: JSArray?) -> [JSObject] {
        (array ?? []).compactMap { $0 as? JSObject }
    }
}

/// The icons of the tabs: SF Symbols, and the commit of the site's Log.
enum TabIcon {
    static func image(named name: String, selected: Bool = false) -> UIImage? {
        if name == "commit" {
            return commit
        }

        if selected, let filled = UIImage(systemName: "\(name).fill") {
            return filled
        }

        return UIImage(systemName: name)
    }

    /// Lucide's git-commit-vertical, as the site draws its Log.
    private static let commit: UIImage = {
        let size = CGSize(width: 24, height: 24)
        let image = UIGraphicsImageRenderer(size: size).image { _ in
            let path = UIBezierPath()
            path.move(to: CGPoint(x: 12, y: 2.5))
            path.addLine(to: CGPoint(x: 12, y: 8.5))
            path.move(to: CGPoint(x: 12, y: 15.5))
            path.addLine(to: CGPoint(x: 12, y: 21.5))
            path.append(UIBezierPath(arcCenter: CGPoint(x: 12, y: 12), radius: 3.5, startAngle: 0, endAngle: .pi * 2, clockwise: true))
            path.lineWidth = 2
            path.lineCapStyle = .round
            UIColor.black.setStroke()
            path.stroke()
        }

        return image.withRenderingMode(.alwaysTemplate)
    }()
}

/// Whether `url` is a plain path of the site, with nothing another URL parser
/// could read as another site. Browsers read `//host` and `/\\host` as other
/// sites, and dot segments can collapse into them: `/.//host`, `/%2e//host`.
func isSitePath(_ url: String) -> Bool {
    guard url.hasPrefix("/"), !url.contains("\\"),
          url.rangeOfCharacter(from: .whitespacesAndNewlines.union(.controlCharacters)) == nil else { return false }

    let path = url.prefix { $0 != "?" && $0 != "#" }
    let segments = path.split(separator: "/", omittingEmptySubsequences: false).dropFirst()

    return segments.enumerated().allSatisfy { index, segment in
        guard let decoded = segment.removingPercentEncoding else { return false }

        // Only the last segment may be empty: a trailing slash
        return (!segment.isEmpty || index == segments.count - 1)
            && decoded != "." && decoded != ".."
            && !decoded.contains("/") && !decoded.contains("\\")
    }
}

import UIKit
import WebKit
import Capacitor

/// The native tab bar, around the one web view of the site.
///
/// The site tells the tabs (see `TabsPlugin`): until it does, and on pages where
/// it hides them, the tab bar is hidden. Every tab shows the same web view,
/// which moves to the selected tab, and a tap on a tab is a visit in the site.
final class MainViewController: UITabBarController, UITabBarControllerDelegate {
    let web = WebViewController()
    weak var tabsPlugin: TabsPlugin?

    private var state = TabsState()

    override func viewDidLoad() {
        super.viewDidLoad()

        delegate = self
        view.backgroundColor = .siteBackground

        if #available(iOS 26, *) {
            // The bar shrinks while reading down a log, as in Apple's apps
            tabBarMinimizeBehavior = .onScrollDown
        }

        let first = WebTabViewController(tab: nil)
        setViewControllers([first], animated: false)
        setTabBarVisible(false, animated: false)
        adoptWebView(in: first)

        // Once adopted: the web view doesn't exist before its controller's view loads
        if #available(iOS 26, *) {
            followScrolling()
        }
    }

    /// The status bar sits on the band that takes the color of the page. Until the
    /// site tells its theme, it follows the system, as the launch screen does.
    override var preferredStatusBarStyle: UIStatusBarStyle {
        guard !state.tabs.isEmpty else { return .default }

        return state.isDark ? .lightContent : .darkContent
    }

    override var childForStatusBarStyle: UIViewController? {
        nil
    }

    // MARK: Scrolling

    private var scrollObservation: NSKeyValueObservation?
    private var lastOffset: CGFloat = 0
    /// How far the reader scrolled in the current direction: down is positive.
    private var travel: CGFloat = 0
    private var isCollapsed = false

    /// Reading down a page collapses the tab bar (iOS shrinks it) and the
    /// site's compose button (the site hides it); scrolling back up a little
    /// brings both back, as in Apple's apps, not only at the top of the page.
    @available(iOS 26, *)
    private func followScrolling() {
        scrollObservation = web.webView?.scrollView.observe(\.contentOffset, options: [.new]) { [weak self] scrollView, _ in
            MainActor.assumeIsolated {
                self?.scrolled(scrollView)
            }
        }
    }

    @available(iOS 26, *)
    private func scrolled(_ scrollView: UIScrollView) {
        let offset = scrollView.contentOffset.y + scrollView.adjustedContentInset.top
        let delta = offset - lastOffset
        lastOffset = offset

        if offset <= 0 {
            travel = 0
            setCollapsed(false)
            return
        }

        // Only the reader's scrolling, not a page that grows or loads
        guard scrollView.isTracking || scrollView.isDecelerating else { return }

        travel = (delta > 0) == (travel > 0) ? travel + delta : delta

        if travel > 40 {
            setCollapsed(true)
        } else if travel < -40 {
            setCollapsed(false)
        }
    }

    @available(iOS 26, *)
    private func setCollapsed(_ collapsed: Bool) {
        guard collapsed != isCollapsed else { return }

        isCollapsed = collapsed

        if !collapsed {
            // iOS only expands a bar minimized on scroll at the top of the
            // page: switching its behavior off and on expands it right away
            tabBarMinimizeBehavior = .never
            DispatchQueue.main.async {
                self.tabBarMinimizeBehavior = .onScrollDown
            }
        }

        tabsPlugin?.collapsed(collapsed)
    }

    /// Shows what the site asked for, rebuilding the tabs only when they change,
    /// so the web view doesn't move for a badge.
    func apply(_ newState: TabsState) {
        let previous = state
        state = newState

        // Only the native parts follow the site's theme: overriding the web view too
        // would hide the system's appearance from a site that follows it
        tabBar.overrideUserInterfaceStyle = newState.isDark ? .dark : .light
        setNeedsStatusBarAppearanceUpdate()
        tabBar.tintColor = newState.tint

        // Without tabs there would be no page left to show the web view in
        if newState.tabs != previous.tabs, !newState.tabs.isEmpty {
            setViewControllers(newState.tabs.map { WebTabViewController(tab: $0) }, animated: false)
        }

        for case let controller as WebTabViewController in viewControllers ?? [] {
            controller.tabBarItem.badgeValue = newState.badges[controller.webTab?.id ?? ""]
        }

        if let selected = newState.selected,
           let index = newState.tabs.firstIndex(where: { $0.id == selected }),
           selectedIndex != index {
            selectedIndex = index
        }

        if let controller = selectedViewController as? WebTabViewController {
            adoptWebView(in: controller)
        }

        setTabBarVisible(newState.isVisible && !newState.tabs.isEmpty, animated: previous.isVisible != newState.isVisible)
    }

    /// Whether the site is in the web view, rather than the offline page.
    var isShowingSite: Bool {
        Site.contains(web.webView?.url)
    }

    /// Loads the page again with a new session, or the given one instead.
    func reloadSite(path: String? = nil) {
        if let path, web.webView?.url?.path != path {
            web.webView?.load(URLRequest(url: Site.url(path: path)))
        } else if isShowingSite {
            web.webView?.reload()
        } else {
            web.webView?.load(URLRequest(url: Site.url))
        }
    }

    func adoptWebView(in tab: WebTabViewController) {
        guard web.parent !== tab else { return }

        web.willMove(toParent: nil)
        web.view.removeFromSuperview()
        web.removeFromParent()

        tab.addChild(web)
        tab.view.addSubview(web.view)
        // Below the status bar, as in the installed web app: the site's sticky
        // headers stick under it, and the band under the status bar takes the
        // color at the top of the page (see `setTopColor`), so they read as
        // one surface. The bottom goes under the tab bar, whose height the site reads from
        // env(safe-area-inset-bottom).
        web.view.translatesAutoresizingMaskIntoConstraints = false
        NSLayoutConstraint.activate([
            web.view.topAnchor.constraint(equalTo: tab.view.safeAreaLayoutGuide.topAnchor),
            web.view.bottomAnchor.constraint(equalTo: tab.view.bottomAnchor),
            web.view.leadingAnchor.constraint(equalTo: tab.view.leadingAnchor),
            web.view.trailingAnchor.constraint(equalTo: tab.view.trailingAnchor),
        ])
        web.didMove(toParent: tab)
        tab.view.backgroundColor = topColor

        // The tab bar follows the web view's scrolling, to shrink and to blur
        tab.setContentScrollView(web.webView?.scrollView, for: .bottom)
    }

    /// The color at the top of the page, under the status bar.
    private var topColor: UIColor = .siteBackground

    /// The site measures the color at the top of the page, and tells it as it
    /// changes: another page, the theme, or a dialog dimming the page, which
    /// fades in and out, so the band does too.
    func setTopColor(_ color: UIColor) {
        topColor = color

        UIView.animate(withDuration: 0.2) {
            self.view.backgroundColor = color
            self.web.parent?.view.backgroundColor = color
        }
    }

    private func setTabBarVisible(_ visible: Bool, animated: Bool) {
        if #available(iOS 18, *) {
            setTabBarHidden(!visible, animated: animated)
        } else {
            tabBar.isHidden = !visible
        }
    }

    // MARK: UITabBarControllerDelegate

    func tabBarController(_ tabBarController: UITabBarController, shouldSelect viewController: UIViewController) -> Bool {
        if let tab = (viewController as? WebTabViewController)?.webTab {
            tabsPlugin?.select(tab, reselected: viewController === selectedViewController)
        }

        return true
    }

    func tabBarController(_ tabBarController: UITabBarController, didSelect viewController: UIViewController) {
        if let controller = viewController as? WebTabViewController {
            adoptWebView(in: controller)
        }
    }
}

/// A tab: an empty page that the web view moves into when it's selected.
final class WebTabViewController: UIViewController {
    let webTab: TabsState.Tab?

    init(tab: TabsState.Tab?) {
        self.webTab = tab
        super.init(nibName: nil, bundle: nil)

        if let tab {
            tabBarItem = UITabBarItem(
                title: tab.title,
                image: TabIcon.image(named: tab.icon),
                selectedImage: TabIcon.image(named: tab.icon, selected: true)
            )
        }
    }

    required init?(coder: NSCoder) {
        fatalError("init(coder:) has not been implemented")
    }

    override func viewWillAppear(_ animated: Bool) {
        super.viewWillAppear(animated)
        (tabBarController as? MainViewController)?.adoptWebView(in: self)
    }
}

/// The web view of the site.
final class WebViewController: CAPBridgeViewController {
    private var navigationFilter: CancelledNavigationFilter?
    private var promptFilter: BridgePromptFilter?

    override func capacitorDidLoad() {
        if let handler = (bridge as? CapacitorBridge)?.webViewDelegationHandler {
            handler.contentController.removeScriptMessageHandler(forName: "bridge")
            handler.contentController.add(BridgeMessageFilter(handler: handler), name: "bridge")
        }

        if let handler = webView?.uiDelegate {
            promptFilter = BridgePromptFilter(handler: handler)
            webView?.uiDelegate = promptFilter
        }

        if let handler = webView?.navigationDelegate {
            navigationFilter = CancelledNavigationFilter(handler: handler)
            webView?.navigationDelegate = navigationFilter
        }

        // The edge swipes of Safari go back and forward: the site has no back
        // button on phones
        webView?.allowsBackForwardNavigationGestures = true
        // The site's background shows through until its first paint, in the
        // system's appearance, as the launch screen
        webView?.isOpaque = false
        webView?.backgroundColor = .clear

        bridge?.registerPluginInstance(TabsPlugin())
        bridge?.registerPluginInstance(SessionPlugin())
    }
}

/// Capacitor shows its error page for any navigation that fails, even one that
/// was only cancelled: a link or a redirect leaving for Safari cancels its own
/// navigation, which would tell people they're offline. Everything else goes to
/// Capacitor's handler untouched.
final class CancelledNavigationFilter: NSObject, WKNavigationDelegate {
    private let handler: WKNavigationDelegate

    init(handler: WKNavigationDelegate) {
        self.handler = handler
    }

    override func responds(to selector: Selector!) -> Bool {
        super.responds(to: selector) || handler.responds(to: selector)
    }

    override func forwardingTarget(for selector: Selector!) -> Any? {
        handler
    }

    func webView(_ webView: WKWebView, didFail navigation: WKNavigation!, withError error: Error) {
        if !isCancelled(error) {
            handler.webView?(webView, didFail: navigation, withError: error)
        }
    }

    func webView(_ webView: WKWebView, didFailProvisionalNavigation navigation: WKNavigation!, withError error: Error) {
        if !isCancelled(error) {
            handler.webView?(webView, didFailProvisionalNavigation: navigation, withError: error)
        }
    }

    private func isCancelled(_ error: Error) -> Bool {
        let error = error as NSError

        return (error.domain == NSURLErrorDomain && error.code == NSURLErrorCancelled)
            // "Frame load interrupted by policy change", which no iOS API names
            || (error.domain == "WebKitErrorDomain" && error.code == 102)
    }
}

extension UIColor {
    /// The site's background, light or dark, until it tells its theme-color.
    static let siteBackground = UIColor { traits in
        traits.userInterfaceStyle == .dark ? UIColor(hex: "#111112")! : UIColor(hex: "#f6f7f8")!
    }

    convenience init?(hex: String) {
        let digits = hex.trimmingCharacters(in: CharacterSet(charactersIn: "#"))

        guard digits.count == 6, let value = UInt32(digits, radix: 16) else { return nil }

        self.init(
            red: CGFloat((value >> 16) & 0xFF) / 255,
            green: CGFloat((value >> 8) & 0xFF) / 255,
            blue: CGFloat(value & 0xFF) / 255,
            alpha: 1
        )
    }
}

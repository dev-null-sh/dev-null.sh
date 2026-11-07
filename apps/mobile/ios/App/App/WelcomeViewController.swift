import UIKit

/// The first thing someone sees: they installed the app to sign in, so it's that, and
/// not the signed-out site. It needs no network to show.
final class WelcomeViewController: UIViewController {
    private static let command = "git init"
    private static let headline = "A social network\nfor developers."
    private static let message = "Your profile comes from GitHub.\nNothing to fill in."

    private let showsPromptHint: Bool
    private let onSignIn: () -> Void
    private var playsEntrance: Bool
    private let hint = UILabel()
    private let page = RailPageView(
        command: WelcomeViewController.command,
        headline: WelcomeViewController.headline,
        message: WelcomeViewController.message
    )
    private lazy var button = UIButton.sitePrimary(
        title: "Sign in with GitHub",
        image: UIImage(named: "GitHubMark")
    ) { [weak self] in
        self?.signIn()
    }

    init(showsPromptHint: Bool, comesFromLaunch: Bool, onSignIn: @escaping () -> Void) {
        self.showsPromptHint = showsPromptHint
        playsEntrance = comesFromLaunch && !UIAccessibility.isReduceMotionEnabled
        self.onSignIn = onSignIn
        super.init(nibName: nil, bundle: nil)
    }

    required init?(coder: NSCoder) {
        fatalError("init(coder:) has not been implemented")
    }

    override func loadView() {
        view = page
    }

    override func viewDidLoad() {
        super.viewDidLoad()

        page.actions.addArrangedSubview(button)

        if showsPromptHint {
            hint.numberOfLines = 0
            renderHint()
            page.actions.addArrangedSubview(hint)
            registerForTraitChanges([UITraitPreferredContentSizeCategory.self]) { (self: Self, _: UITraitCollection) in
                self.renderHint()
            }
        }
    }

    private func renderHint() {
        hint.attributedText = NSAttributedString(
            string: "iOS will ask to use dev-null.sh to sign in.",
            attributes: .site(.regular(13, .footnote), color: .siteMuted, lineHeight: 1.4, alignment: .center)
        )
    }

    // Not any earlier: the logo has to match the launch screen's, with the safe area known
    override func viewIsAppearing(_ animated: Bool) {
        super.viewIsAppearing(animated)

        if playsEntrance {
            page.prepareEntrance()
        }
    }

    override func viewDidAppear(_ animated: Bool) {
        super.viewDidAppear(animated)

        if playsEntrance {
            page.playEntrance()
            playsEntrance = false
        }
    }

    private func signIn() {
        page.command = Self.command
        page.headline = Self.headline
        page.message = Self.message
        onSignIn()
    }

    /// From the tap until the site is showing, or until it comes back here.
    func setBusy(_ isBusy: Bool) {
        // Not disabled, which iOS would dim on top of the spinner
        button.isUserInteractionEnabled = !isBusy
        button.accessibilityTraits = isBusy ? [.button, .notEnabled] : .button
        button.configuration?.showsActivityIndicator = isBusy
        button.configuration?.title = isBusy ? "Signing in…" : "Sign in with GitHub"
    }

    /// In place of what the page was saying, above the same button.
    func showSignInFailed() {
        page.command = "fatal: could not sign in"
        page.headline = "Couldn't sign in."
        page.message = "Something went wrong on the way back. Please try again."
        UINotificationFeedbackGenerator().notificationOccurred(.error)
        UIAccessibility.post(notification: .announcement, argument: page.message)
    }
}

/// While the web view waits for its session and its first page: the launch screen's
/// picture, so there's nothing to notice until it takes long enough to say so.
final class LaunchView: UIView {
    private static let patience: TimeInterval = 1.2

    private let waitingSince: Date
    private let status = UILabel()

    /// A launch goes through two of these, before and after the session, and keeps counting.
    init(waitingSince: Date) {
        self.waitingSince = waitingSince
        super.init(frame: .zero)

        backgroundColor = .siteBackground

        let lockup = UIImageView.lockup()
        status.attributedText = NSAttributedString(
            string: "git fetch origin",
            attributes: .site(.mono(13, .footnote), color: .siteMuted, lineHeight: 1.4)
        )
        status.alpha = 0

        [lockup, status].forEach {
            $0.translatesAutoresizingMaskIntoConstraints = false
            addSubview($0)
        }

        NSLayoutConstraint.activate([
            lockup.centerXAnchor.constraint(equalTo: centerXAnchor),
            lockup.centerYAnchor.constraint(equalTo: centerYAnchor),
            status.centerXAnchor.constraint(equalTo: centerXAnchor),
            status.topAnchor.constraint(equalTo: lockup.bottomAnchor, constant: 16),
        ])
    }

    required init?(coder: NSCoder) {
        fatalError("init(coder:) has not been implemented")
    }

    override func didMoveToWindow() {
        super.didMoveToWindow()

        guard window != nil else { return }

        // Out of the crossfade that may have brought it here, which turns off animations and their delays
        DispatchQueue.main.async {
            self.startWaiting()
        }
    }

    private func startWaiting() {
        let wait = Self.patience + waitingSince.timeIntervalSinceNow

        // It was already saying so in the previous one
        if wait <= 0 {
            status.alpha = 1
        }

        // A pulse like the site's loading rows, or just there with reduced motion
        UIView.animate(withDuration: wait > 0 ? 0.3 : 0, delay: max(0, wait)) {
            self.status.alpha = 1
        } completion: { finished in
            guard finished, !UIAccessibility.isReduceMotionEnabled else { return }

            UIView.animate(withDuration: 1, delay: 0, options: [.repeat, .autoreverse, .curveEaseInOut, .allowUserInteraction]) {
                self.status.alpha = 0.4
            }
        }
    }
}

final class LaunchViewController: UIViewController {
    private let waitingSince: Date

    init(waitingSince: Date) {
        self.waitingSince = waitingSince
        super.init(nibName: nil, bundle: nil)
    }

    required init?(coder: NSCoder) {
        fatalError("init(coder:) has not been implemented")
    }

    override func loadView() {
        view = LaunchView(waitingSince: waitingSince)
    }
}

/// When the web view can't be shown, because the account or the site won't let it:
/// what happened, and one way forward.
final class MessageViewController: UIViewController {
    private let page: RailPageView
    private let action: String
    private let onAction: () -> Void

    init(command: String, headline: String, message: String, action: String, onAction: @escaping () -> Void) {
        page = RailPageView(command: command, headline: headline, message: message)
        self.action = action
        self.onAction = onAction
        super.init(nibName: nil, bundle: nil)
    }

    required init?(coder: NSCoder) {
        fatalError("init(coder:) has not been implemented")
    }

    override func loadView() {
        view = page
    }

    override func viewDidLoad() {
        super.viewDidLoad()

        page.actions.addArrangedSubview(UIButton.sitePrimary(title: action) { [weak self] in
            self?.onAction()
        })
    }
}

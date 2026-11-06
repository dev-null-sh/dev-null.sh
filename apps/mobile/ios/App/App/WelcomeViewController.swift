import UIKit

/// The first thing someone sees: they installed the app to sign in, so it's that, and
/// not the signed-out site. It needs no network to show.
final class WelcomeViewController: UIViewController {
    private let showsPromptHint: Bool
    private let onSignIn: () -> Void
    private let errorLabel = UILabel()

    init(showsPromptHint: Bool, onSignIn: @escaping () -> Void) {
        self.showsPromptHint = showsPromptHint
        self.onSignIn = onSignIn
        super.init(nibName: nil, bundle: nil)
    }

    required init?(coder: NSCoder) {
        fatalError("init(coder:) has not been implemented")
    }

    override func viewDidLoad() {
        super.viewDidLoad()
        view.backgroundColor = .siteBackground

        let name = UILabel.wordmark()

        let button = UIButton(configuration: .prominent(title: "Sign in with GitHub"), primaryAction: UIAction { [weak self] _ in
            self?.errorLabel.text = nil
            self?.onSignIn()
        })

        let hint = UILabel()
        hint.text = "iOS will ask to use dev-null.sh to sign in."
        hint.font = .preferredFont(forTextStyle: .footnote)
        hint.textColor = .secondaryLabel
        hint.textAlignment = .center
        hint.numberOfLines = 0
        hint.isHidden = !showsPromptHint

        errorLabel.font = .preferredFont(forTextStyle: .footnote)
        errorLabel.textColor = .systemRed
        errorLabel.textAlignment = .center
        errorLabel.numberOfLines = 0

        let actions = UIStackView(arrangedSubviews: [errorLabel, button, hint])
        actions.axis = .vertical
        actions.spacing = 12

        [name, actions].forEach {
            $0.translatesAutoresizingMaskIntoConstraints = false
            view.addSubview($0)
        }

        NSLayoutConstraint.activate([
            name.centerXAnchor.constraint(equalTo: view.centerXAnchor),
            name.centerYAnchor.constraint(equalTo: view.centerYAnchor),
            actions.leadingAnchor.constraint(equalTo: view.layoutMarginsGuide.leadingAnchor),
            actions.trailingAnchor.constraint(equalTo: view.layoutMarginsGuide.trailingAnchor),
            actions.bottomAnchor.constraint(equalTo: view.safeAreaLayoutGuide.bottomAnchor, constant: -16),
        ])
    }

    func showError(_ message: String) {
        errorLabel.text = message
    }
}

/// While the web view waits for its session: the same background as the launch screen.
final class LaunchViewController: UIViewController {
    override func viewDidLoad() {
        super.viewDidLoad()
        view.backgroundColor = .siteBackground
    }
}

/// A message with one way forward, for when the web view can't be shown.
final class MessageViewController: UIViewController {
    private let message: String
    private let action: String
    private let onAction: () -> Void

    init(message: String, action: String, onAction: @escaping () -> Void) {
        self.message = message
        self.action = action
        self.onAction = onAction
        super.init(nibName: nil, bundle: nil)
    }

    required init?(coder: NSCoder) {
        fatalError("init(coder:) has not been implemented")
    }

    override func viewDidLoad() {
        super.viewDidLoad()
        view.backgroundColor = .siteBackground

        let label = UILabel()
        label.text = message
        label.textAlignment = .center
        label.numberOfLines = 0

        let button = UIButton(configuration: .prominent(title: action), primaryAction: UIAction { [weak self] _ in
            self?.onAction()
        })

        let stack = UIStackView(arrangedSubviews: [label, button])
        stack.axis = .vertical
        stack.spacing = 16
        stack.translatesAutoresizingMaskIntoConstraints = false
        view.addSubview(stack)

        NSLayoutConstraint.activate([
            stack.leadingAnchor.constraint(equalTo: view.layoutMarginsGuide.leadingAnchor),
            stack.trailingAnchor.constraint(equalTo: view.layoutMarginsGuide.trailingAnchor),
            stack.centerYAnchor.constraint(equalTo: view.centerYAnchor),
        ])
    }
}

extension UILabel {
    fileprivate static func wordmark() -> UILabel {
        let label = UILabel()
        label.text = "/dev/null"
        label.font = .monospacedSystemFont(ofSize: 34, weight: .semibold)

        return label
    }
}

extension UIButton.Configuration {
    fileprivate static func prominent(title: String) -> UIButton.Configuration {
        var configuration = UIButton.Configuration.filled()
        configuration.title = title
        configuration.buttonSize = .large

        return configuration
    }
}

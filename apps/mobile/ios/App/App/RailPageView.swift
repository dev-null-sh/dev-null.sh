import UIKit

/// A native screen drawn like the site's standalone pages (the error pages, the
/// approval page in the sign-in sheet, the offline page), so going from one to the
/// other doesn't change worlds: the logo on a node of the rail, then a hollow node
/// with what a machine would print, what it means in words, and the actions.
final class RailPageView: UIView {
    let actions = UIStackView()

    var command: String {
        didSet { render() }
    }

    var message: String {
        didSet { render() }
    }

    var headline: String {
        didSet { render() }
    }

    private let scrollView = UIScrollView()
    private let column = UIView()
    private let lockup = UIImageView.lockup()
    private let commandLabel = UILabel()
    private let headlineLabel = UILabel()
    private let messageLabel = UILabel()

    private let headNode = RailNode(style: .filled)
    private let segment = UIView()
    private let segmentFade = CAGradientLayer()
    private let hollowNode = RailNode(style: .hollow)

    init(command: String, headline: String, message: String) {
        self.command = command
        self.headline = headline
        self.message = message
        super.init(frame: .zero)

        backgroundColor = .siteBackground

        for label in [commandLabel, headlineLabel, messageLabel] {
            label.numberOfLines = 0
            label.translatesAutoresizingMaskIntoConstraints = false
            column.addSubview(label)
        }

        headlineLabel.accessibilityTraits = .header
        lockup.translatesAutoresizingMaskIntoConstraints = false
        column.addSubview(lockup)

        segment.backgroundColor = .siteRail
        segmentFade.colors = [UIColor.black.cgColor, UIColor.clear.cgColor]
        segmentFade.locations = [0.4, 1]
        segment.layer.mask = segmentFade
        // The segment is drawn from its top, as on the site
        segment.layer.anchorPoint = CGPoint(x: 0.5, y: 0)

        for rail in [segment, headNode, hollowNode] {
            rail.isAccessibilityElement = false
            column.addSubview(rail)
        }

        column.translatesAutoresizingMaskIntoConstraints = false
        scrollView.translatesAutoresizingMaskIntoConstraints = false
        scrollView.alwaysBounceVertical = false
        scrollView.addSubview(column)
        addSubview(scrollView)

        actions.axis = .vertical
        actions.spacing = 12
        actions.translatesAutoresizingMaskIntoConstraints = false
        addSubview(actions)

        // As short as it can be, no taller than the screen unless the text needs it
        let contentFitsScreen = scrollView.contentLayoutGuide.heightAnchor.constraint(equalTo: scrollView.frameLayoutGuide.heightAnchor)
        contentFitsScreen.priority = .defaultLow

        NSLayoutConstraint.activate([
            scrollView.topAnchor.constraint(equalTo: safeAreaLayoutGuide.topAnchor),
            scrollView.leadingAnchor.constraint(equalTo: leadingAnchor),
            scrollView.trailingAnchor.constraint(equalTo: trailingAnchor),
            scrollView.bottomAnchor.constraint(equalTo: actions.topAnchor, constant: -16),

            actions.leadingAnchor.constraint(equalTo: leadingAnchor, constant: Self.gutter),
            actions.trailingAnchor.constraint(equalTo: trailingAnchor, constant: -Self.gutter),
            actions.bottomAnchor.constraint(equalTo: safeAreaLayoutGuide.bottomAnchor, constant: -16),

            // Centered in what's above the actions, and scrolling at the largest text sizes
            column.leadingAnchor.constraint(equalTo: scrollView.contentLayoutGuide.leadingAnchor),
            column.trailingAnchor.constraint(equalTo: scrollView.contentLayoutGuide.trailingAnchor),
            column.widthAnchor.constraint(equalTo: scrollView.frameLayoutGuide.widthAnchor),
            column.centerYAnchor.constraint(equalTo: scrollView.contentLayoutGuide.centerYAnchor),
            column.topAnchor.constraint(greaterThanOrEqualTo: scrollView.contentLayoutGuide.topAnchor, constant: 24),
            scrollView.contentLayoutGuide.heightAnchor.constraint(greaterThanOrEqualTo: scrollView.frameLayoutGuide.heightAnchor),
            contentFitsScreen,

            lockup.topAnchor.constraint(equalTo: column.topAnchor),
            lockup.leadingAnchor.constraint(equalTo: column.leadingAnchor, constant: Self.contentInset),

            commandLabel.topAnchor.constraint(equalTo: lockup.bottomAnchor, constant: 56),
            headlineLabel.topAnchor.constraint(equalTo: commandLabel.bottomAnchor, constant: 16),
            messageLabel.topAnchor.constraint(equalTo: headlineLabel.bottomAnchor, constant: 12),
            messageLabel.bottomAnchor.constraint(equalTo: column.bottomAnchor),
        ] + [commandLabel, headlineLabel, messageLabel].flatMap { label in
            [
                label.leadingAnchor.constraint(equalTo: column.leadingAnchor, constant: Self.contentInset),
                label.trailingAnchor.constraint(equalTo: column.trailingAnchor, constant: -Self.gutter),
            ]
        })

        // The logo first, whatever order the views were added in
        column.accessibilityElements = [lockup, commandLabel, headlineLabel, messageLabel]

        render()
        registerForTraitChanges([UITraitPreferredContentSizeCategory.self]) { (self: Self, _: UITraitCollection) in
            self.render()
        }
    }

    required init?(coder: NSCoder) {
        fatalError("init(coder:) has not been implemented")
    }

    private static let gutter: CGFloat = 16
    private static let railWidth: CGFloat = 40
    private static var contentInset: CGFloat { gutter + railWidth }

    private func render() {
        commandLabel.attributedText = NSAttributedString(
            string: command,
            attributes: .site(.mono(13, .footnote), color: .siteMuted, lineHeight: 1.4)
        )
        headlineLabel.attributedText = NSAttributedString(
            string: headline,
            attributes: .site(.semibold(30, .title1), color: .siteInk, lineHeight: 1.2, tracking: -0.025)
        )
        messageLabel.attributedText = NSAttributedString(
            string: message,
            attributes: .site(.regular(17, .body), color: .siteMuted, lineHeight: 1.5)
        )
        setNeedsLayout()
    }

    override func layoutSubviews() {
        super.layoutSubviews()
        scrollView.layoutIfNeeded()
        column.layoutIfNeeded()

        let x = Self.gutter + Self.railWidth / 2
        let headY = lockup.center.y
        // In the middle of the command's first line (see `render`), whichever way it wraps
        let hollowY = commandLabel.frame.minY + UIFont.mono(13, .footnote).pointSize * 1.4 / 2

        headNode.center = CGPoint(x: x, y: headY)
        hollowNode.center = CGPoint(x: x, y: hollowY)

        let segmentHeight = max(0, hollowY - 24 - headY)
        segment.bounds = CGRect(x: 0, y: 0, width: 2, height: segmentHeight)
        segment.center = CGPoint(x: x, y: headY)
        segmentFade.frame = segment.bounds
    }

    // MARK: Coming from the launch screen

    /// Starts from the launch screen's picture: only the logo, in its middle.
    func prepareEntrance() {
        layoutIfNeeded()

        let center = convert(lockup.center, from: column)
        lockup.transform = CGAffineTransform(translationX: bounds.midX - center.x, y: bounds.midY - center.y)
        segment.transform = CGAffineTransform(scaleX: 1, y: 0.001)
        hollowNode.transform = CGAffineTransform(scaleX: 0.001, y: 0.001)

        for fading in [headNode, commandLabel, headlineLabel, messageLabel, actions] as [UIView] {
            fading.alpha = 0
        }
    }

    /// The logo goes to its place, then the rail draws down to the next node, as it
    /// does on the site, and what it says comes in.
    func playEntrance() {
        // iOS is still fading out its launch screen, whose logo would stay behind
        let launchFade = 0.5
        let curve = UICubicTimingParameters(controlPoint1: CGPoint(x: 0.16, y: 1), controlPoint2: CGPoint(x: 0.3, y: 1))

        func animate(duration: TimeInterval, delay: TimeInterval, _ animations: @escaping () -> Void) {
            let animator = UIViewPropertyAnimator(duration: duration, timingParameters: curve)
            animator.addAnimations(animations)
            animator.startAnimation(afterDelay: launchFade + delay)
        }

        animate(duration: 0.56, delay: 0) { self.lockup.transform = .identity }
        animate(duration: 0.24, delay: 0.18) { self.headNode.alpha = 1 }
        animate(duration: 0.36, delay: 0.24) { self.segment.transform = .identity }

        let pop = UISpringTimingParameters(dampingRatio: 0.55, initialVelocity: .zero)
        let hollow = UIViewPropertyAnimator(duration: 0.5, timingParameters: pop)
        hollow.addAnimations { self.hollowNode.transform = .identity }
        hollow.startAnimation(afterDelay: launchFade + 0.42)

        for (index, fading) in ([commandLabel, headlineLabel, messageLabel, actions] as [UIView]).enumerated() {
            let delay = 0.42 + 0.04 * Double(index)
            fading.transform = CGAffineTransform(translationX: 0, y: 8)
            animate(duration: 0.44, delay: delay) {
                fading.alpha = 1
                fading.transform = .identity
            }
        }
    }
}

/// A node of the rail: 14pt with a ring, filled for what happened, hollow for what's next.
private final class RailNode: UIView {
    enum Style {
        case filled
        case hollow
    }

    private let style: Style
    private let dot = UIView()

    init(style: Style) {
        self.style = style
        super.init(frame: CGRect(x: 0, y: 0, width: style == .filled ? 18 : 14, height: style == .filled ? 18 : 14))

        backgroundColor = .siteBackground
        layer.cornerRadius = bounds.width / 2
        layer.borderWidth = 2

        if style == .filled {
            dot.frame = bounds.insetBy(dx: 5, dy: 5)
            dot.layer.cornerRadius = dot.bounds.width / 2
            dot.backgroundColor = .siteRail
            addSubview(dot)
        }

        updateBorder()
        registerForTraitChanges([UITraitUserInterfaceStyle.self]) { (self: Self, _: UITraitCollection) in
            self.updateBorder()
        }
    }

    required init?(coder: NSCoder) {
        fatalError("init(coder:) has not been implemented")
    }

    private func updateBorder() {
        let color: UIColor = style == .filled ? .siteRail : .siteMuted
        layer.borderColor = color.resolvedColor(with: traitCollection).cgColor
    }
}

extension UIImageView {
    /// The logo and the wordmark, the same picture as on the launch screen.
    static func lockup() -> UIImageView {
        let view = UIImageView(image: UIImage(named: "Lockup"))
        view.isAccessibilityElement = true
        view.accessibilityLabel = "/dev/null"
        view.setContentHuggingPriority(.required, for: .horizontal)
        view.setContentHuggingPriority(.required, for: .vertical)

        return view
    }
}

extension UIButton {
    /// The site's primary button, at the size of a phone's main action.
    static func sitePrimary(title: String, image: UIImage? = nil, action: @escaping () -> Void) -> UIButton {
        var configuration = UIButton.Configuration.filled()
        configuration.title = title
        configuration.image = image
        configuration.imagePadding = 8
        configuration.buttonSize = .large
        configuration.cornerStyle = .fixed
        configuration.background.cornerRadius = 6
        configuration.titleTextAttributesTransformer = UIConfigurationTextAttributesTransformer { attributes in
            var attributes = attributes
            attributes.font = .semibold(16, .body)

            return attributes
        }

        let button = UIButton(configuration: configuration, primaryAction: UIAction { _ in action() })
        // The pressed color, and the same fill while it's busy rather than the system's gray
        button.configurationUpdateHandler = { button in
            button.configuration?.background.backgroundColor = button.isHighlighted ? .sitePrimaryPressed : .sitePrimary
            button.configuration?.baseForegroundColor = .sitePrimaryText
        }
        button.heightAnchor.constraint(greaterThanOrEqualToConstant: 50).isActive = true

        return button
    }
}

extension Dictionary where Key == NSAttributedString.Key, Value == Any {
    /// How the site sets its text: `lineHeight` as a multiple of the size, `tracking` in em.
    static func site(_ font: UIFont, color: UIColor, lineHeight: CGFloat, tracking: CGFloat = 0, alignment: NSTextAlignment = .natural) -> Self {
        let paragraph = NSMutableParagraphStyle()
        paragraph.minimumLineHeight = font.pointSize * lineHeight
        paragraph.maximumLineHeight = font.pointSize * lineHeight
        paragraph.alignment = alignment

        return [
            .font: font,
            .foregroundColor: color,
            .paragraphStyle: paragraph,
            .kern: font.pointSize * tracking,
            // Centers the text in its line, as a browser does
            .baselineOffset: (font.pointSize * lineHeight - font.lineHeight) / 4,
        ]
    }
}

extension UIFont {
    static func regular(_ size: CGFloat, _ style: TextStyle) -> UIFont {
        site("Geist-Regular", size, style)
    }

    static func semibold(_ size: CGFloat, _ style: TextStyle) -> UIFont {
        site("Geist-SemiBold", size, style)
    }

    static func mono(_ size: CGFloat, _ style: TextStyle) -> UIFont {
        site("GeistMono-Regular", size, style)
    }

    /// One of the site's fonts in `www/fonts`, following the reader's text size.
    private static func site(_ name: String, _ size: CGFloat, _ style: TextStyle) -> UIFont {
        UIFontMetrics(forTextStyle: style).scaledFont(for: UIFont(name: name, size: size) ?? .systemFont(ofSize: size))
    }
}

extension UIColor {
    static let siteInk = site(light: "#121417", dark: "#ececec")
    static let siteMuted = site(light: "#5b616b", dark: "#a1a1a6")
    static let siteRail = site(light: "#d3d7dd", dark: "#36363a")
    static let sitePrimary = site(light: "#3b6fe0", dark: "#7aa2f7")
    static let sitePrimaryPressed = site(light: "#2e5ccb", dark: "#94b5f9")
    static let sitePrimaryText = site(light: "#ffffff", dark: "#111112")

    private static func site(light: String, dark: String) -> UIColor {
        UIColor { traits in
            UIColor(hex: traits.userInterfaceStyle == .dark ? dark : light)!
        }
    }
}

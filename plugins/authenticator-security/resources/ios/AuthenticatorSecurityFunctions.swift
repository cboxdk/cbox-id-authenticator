import UIKit
import LocalAuthentication

#if !CBOX_AUTHENTICATOR_SOURCE_CHECK
#error("Authenticator iOS preparation did not complete. Run authenticator:prepare-ios before building.")
#endif

// Generated app and plugin logging is redirected here by the preparation hook.
// In particular, the bridge must never log Keychain writes or OAuth callback URLs.
func cboxDiscardLog(_ items: Any..., separator: String = " ", terminator: String = "\n") {}

// A notification can select an enrolled installation, never start OAuth or supply a host.
func cboxNotificationURL(_ value: String) -> URL? {
    guard let url = URL(string: value), url.scheme == "com.cboxid.authenticator",
          url.user == nil, url.password == nil, url.port == nil,
          url.query == nil, url.fragment == nil else { return nil }
    let parts = url.path.split(separator: "/").map(String.init)
    let validIDs = parts.allSatisfy { $0.range(of: "^[0-9A-HJKMNP-TV-Z]{26}$", options: [.regularExpression, .caseInsensitive]) != nil }
    guard validIDs else { return nil }
    switch url.host {
    case "request": return parts.count == 2 ? url : nil
    case "security": return parts.count == 1 ? url : nil
    case "approvals": return parts.count <= 1 ? url : nil
    default: return nil
    }
}

func cboxLocalized(_ key: String) -> String {
    NSLocalizedString(key, bundle: .main, value: key, comment: "Cbox Authenticator")
}

// Lives on the main queue. PHP reads the generation before and after a decision
// prompt, so an event queued before backgrounding can never approve on return.
final class CboxSecurityGate {
    static let shared = CboxSecurityGate()
    private var lifecycle = CboxAuthenticationLifecycle(enabled: UserDefaults.standard.bool(forKey: "cbox.security.enabled"))
    private var context: LAContext?
    private var window: UIWindow?
    private var observers: [NSObjectProtocol] = []
    private var message: UILabel?

    func start() {
        let center = NotificationCenter.default
        observers.append(center.addObserver(forName: UIApplication.willResignActiveNotification, object: nil, queue: .main) { _ in
            self.lifecycle.resignActive()
            self.updatePresentation()
        })
        observers.append(center.addObserver(forName: UIApplication.didEnterBackgroundNotification, object: nil, queue: .main) { _ in
            self.lifecycle.enterBackground()
            let old = self.context
            self.context = nil
            old?.invalidate()
            self.updatePresentation()
        })
        observers.append(center.addObserver(forName: UIApplication.didBecomeActiveNotification, object: nil, queue: .main) { _ in
            self.lifecycle.becomeActive()
            self.updatePresentation()
        })
        observers.append(center.addObserver(forName: UIWindow.didBecomeVisibleNotification, object: nil, queue: .main) { event in
            if let shown = event.object as? UIWindow, shown !== self.window, self.lifecycle.needsCover {
                self.cover()
            }
        })
        if UIApplication.shared.applicationState == .active { lifecycle.becomeActive() }
        updatePresentation()
    }

    func configure(_ enabled: Bool, afterSignIn: Bool = false) {
        lifecycle.configure(enabled, afterSignIn: afterSignIn)
        UserDefaults.standard.set(enabled, forKey: "cbox.security.enabled")
        if !enabled {
            let old = context
            context = nil
            old?.invalidate()
        }
        updatePresentation()
    }

    var state: [String: Any] {
        ["unlocked": lifecycle.canAccessSecrets, "generation": lifecycle.generation,
         "foreground": lifecycle.foreground,
         "pushAvailable": FirebaseManager.shared.isConfigured,
         "locale": Bundle.main.preferredLocalizations.first ?? "en"]
    }

    func beginBrowserAuthentication() -> Bool {
        guard lifecycle.beginBrowser() else { return false }
        updatePresentation()
        return true
    }

    func endBrowserAuthentication(completed: Bool = false) {
        lifecycle.endBrowser(completed: completed)
        updatePresentation()
    }

    private func updatePresentation() {
        if lifecycle.needsCover { cover() } else { window?.isHidden = true }
        if lifecycle.shouldAutomaticallyUnlock { unlock(manual: false) }
    }

    private func cover() {
        if window == nil {
            guard let scene = UIApplication.shared.connectedScenes.compactMap({ $0 as? UIWindowScene }).first else { return }
            let shield = UIWindow(windowScene: scene)
            shield.windowLevel = .alert + 1
            let controller = UIViewController()
            controller.view.backgroundColor = UIColor { traits in
                traits.userInterfaceStyle == .dark
                    ? UIColor(red: 23/255, green: 25/255, blue: 28/255, alpha: 1)
                    : UIColor(red: 250/255, green: 249/255, blue: 246/255, alpha: 1)
            }
            let mark = UIImageView(image: UIImage(systemName: "lock.shield.fill"))
            mark.preferredSymbolConfiguration = UIImage.SymbolConfiguration(pointSize: 56, weight: .medium)
            mark.tintColor = UIColor(red: 8/255, green: 80/255, blue: 171/255, alpha: 1)
            mark.contentMode = .scaleAspectFit
            mark.isAccessibilityElement = false
            mark.heightAnchor.constraint(equalToConstant: 80).isActive = true
            let title = UILabel()
            title.text = "Cbox ID"
            title.font = .preferredFont(forTextStyle: .largeTitle)
            title.adjustsFontForContentSizeCategory = true
            title.textAlignment = .center
            let text = UILabel()
            text.text = cboxLocalized("Unlock to view your codes, accounts and requests.")
            text.font = .preferredFont(forTextStyle: .body)
            text.adjustsFontForContentSizeCategory = true
            text.numberOfLines = 0
            text.textAlignment = .center
            let button = UIButton(type: .system)
            var configuration = UIButton.Configuration.filled()
            configuration.baseBackgroundColor = UIColor(red: 8/255, green: 80/255, blue: 171/255, alpha: 1)
            configuration.cornerStyle = .capsule
            configuration.contentInsets = NSDirectionalEdgeInsets(top: 16, leading: 24, bottom: 16, trailing: 24)
            button.configuration = configuration
            button.titleLabel?.adjustsFontForContentSizeCategory = true
            button.heightAnchor.constraint(greaterThanOrEqualToConstant: 48).isActive = true
            controller.view.accessibilityViewIsModal = true
            button.setTitle(cboxLocalized("Unlock"), for: .normal)
            button.addAction(UIAction { _ in self.unlock(manual: true) }, for: .touchUpInside)
            let stack = UIStackView(arrangedSubviews: [mark, title, text, button])
            stack.axis = .vertical
            stack.spacing = 24
            stack.translatesAutoresizingMaskIntoConstraints = false
            controller.view.addSubview(stack)
            NSLayoutConstraint.activate([
                stack.centerYAnchor.constraint(equalTo: controller.view.centerYAnchor),
                stack.leadingAnchor.constraint(equalTo: controller.view.safeAreaLayoutGuide.leadingAnchor, constant: 32),
                stack.trailingAnchor.constraint(equalTo: controller.view.safeAreaLayoutGuide.trailingAnchor, constant: -32)
            ])
            shield.rootViewController = controller
            message = text
            window = shield
        }
        window?.isHidden = false
    }

    private func unlock(manual: Bool) {
        guard context == nil, let epoch = lifecycle.beginUnlock(manual: manual) else { return }
        let attempt = LAContext()
        var error: NSError?
        guard attempt.canEvaluatePolicy(.deviceOwnerAuthentication, error: &error) else {
            lifecycle.finishUnlock(success: false, generation: epoch)
            message?.text = cboxLocalized("Set a device passcode in iPhone Settings to unlock Cbox ID.")
            return
        }
        context = attempt
        attempt.evaluatePolicy(.deviceOwnerAuthentication, localizedReason: cboxLocalized("Unlock your codes and Cbox ID accounts")) { success, _ in
            DispatchQueue.main.async {
                guard self.context === attempt else { return }
                self.context = nil
                self.lifecycle.finishUnlock(success: success, generation: epoch)
                self.updatePresentation()
            }
        }
    }
}

func CboxAuthenticatorSecurityInit() {
    DispatchQueue.main.async { CboxSecurityGate.shared.start() }
}

enum AuthenticatorSecurityFunctions {
    private static func onMain<T>(_ body: () -> T) -> T {
        Thread.isMainThread ? body() : DispatchQueue.main.sync(execute: body)
    }
    class Configure: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            AuthenticatorSecurityFunctions.onMain {
                CboxSecurityGate.shared.configure(parameters["enabled"] as? Bool ?? true,
                    afterSignIn: parameters["afterSignIn"] as? Bool ?? false)
                return CboxSecurityGate.shared.state
            }
        }
    }
    class State: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            AuthenticatorSecurityFunctions.onMain { CboxSecurityGate.shared.state }
        }
    }
}

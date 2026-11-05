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

// Lives on the main queue. PHP reads the generation before and after a decision
// prompt, so an event queued before backgrounding can never approve on return.
final class CboxSecurityGate {
    static let shared = CboxSecurityGate()
    private var enabled = UserDefaults.standard.bool(forKey: "cbox.security.enabled")
    private var locked = true
    private var generation = 0
    private var context: LAContext?
    private var window: UIWindow?
    private var observers: [NSObjectProtocol] = []
    private var message: UILabel?

    func start() {
        let center = NotificationCenter.default
        observers.append(center.addObserver(forName: UIApplication.willResignActiveNotification, object: nil, queue: .main) { _ in
            if self.enabled { self.cover() }
        })
        observers.append(center.addObserver(forName: UIApplication.didEnterBackgroundNotification, object: nil, queue: .main) { _ in
            self.generation += 1
            self.locked = true
            self.context?.invalidate()
            self.context = nil
            if self.enabled { self.cover() }
        })
        observers.append(center.addObserver(forName: UIApplication.didBecomeActiveNotification, object: nil, queue: .main) { _ in self.resume() })
        observers.append(center.addObserver(forName: UIWindow.didBecomeVisibleNotification, object: nil, queue: .main) { event in
            if let shown = event.object as? UIWindow, shown !== self.window, self.enabled && self.locked {
                self.cover()
            }
        })
        resume()
    }

    func configure(_ enabled: Bool) {
        self.enabled = enabled
        UserDefaults.standard.set(enabled, forKey: "cbox.security.enabled")
        if enabled { resume() } else {
            context?.invalidate()
            context = nil
            window?.isHidden = true
        }
    }

    var state: [String: Any] {
        ["unlocked": !enabled || !locked, "generation": generation,
         "foreground": UIApplication.shared.applicationState == .active,
         "locale": Locale.preferredLanguages.first ?? "en"]
    }

    private func resume() {
        guard enabled else { return }
        guard UIApplication.shared.applicationState == .active else { cover(); return }
        if locked { cover(); unlock() } else { window?.isHidden = true }
    }

    private func localized(_ english: String, _ danish: String) -> String {
        (Locale.preferredLanguages.first ?? "en").lowercased().hasPrefix("da") ? danish : english
    }

    private func cover() {
        if window == nil {
            guard let scene = UIApplication.shared.connectedScenes.compactMap({ $0 as? UIWindowScene }).first else { return }
            let shield = UIWindow(windowScene: scene)
            shield.windowLevel = .alert + 1
            let controller = UIViewController()
            controller.view.backgroundColor = .systemBackground
            let title = UILabel()
            title.text = "Cbox ID"
            title.font = .preferredFont(forTextStyle: .largeTitle)
            title.adjustsFontForContentSizeCategory = true
            title.textAlignment = .center
            let text = UILabel()
            text.text = self.localized("Unlock to view your accounts and requests.", "Lås op for at se dine konti og anmodninger.")
            text.font = .preferredFont(forTextStyle: .body)
            text.adjustsFontForContentSizeCategory = true
            text.numberOfLines = 0
            text.textAlignment = .center
            let button = UIButton(type: .system)
            button.configuration = .filled()
            button.titleLabel?.adjustsFontForContentSizeCategory = true
            button.heightAnchor.constraint(greaterThanOrEqualToConstant: 48).isActive = true
            controller.view.accessibilityViewIsModal = true
            button.setTitle(self.localized("Unlock", "Lås op"), for: .normal)
            button.addAction(UIAction { _ in self.unlock() }, for: .touchUpInside)
            let stack = UIStackView(arrangedSubviews: [title, text, button])
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

    private func unlock() {
        guard enabled, locked, context == nil, UIApplication.shared.applicationState == .active else { return }
        let attempt = LAContext()
        var error: NSError?
        guard attempt.canEvaluatePolicy(.deviceOwnerAuthentication, error: &error) else {
            message?.text = self.localized("Set a device passcode in iPhone Settings to unlock Cbox ID.", "Indstil en adgangskode i iPhone-indstillinger for at låse Cbox ID op.")
            return
        }
        context = attempt
        let epoch = generation
        attempt.evaluatePolicy(.deviceOwnerAuthentication, localizedReason: self.localized("Unlock your Cbox ID accounts", "Lås dine Cbox ID-konti op")) { success, _ in
            DispatchQueue.main.async {
                guard self.context === attempt else { return }
                self.context = nil
                guard success, self.generation == epoch else { return }
                self.locked = false
                self.window?.isHidden = true
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
                CboxSecurityGate.shared.configure(parameters["enabled"] as? Bool ?? true)
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

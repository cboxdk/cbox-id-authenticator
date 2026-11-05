import AuthenticationServices
import UIKit

/// Owns one system authentication session and its lock lifecycle together.
final class CboxAuthenticationBrowser: NSObject, ASWebAuthenticationPresentationContextProviding {
    static let shared = CboxAuthenticationBrowser()
    private var session: ASWebAuthenticationSession?
    private var anchor: UIWindow?

    func open(url: URL, id: String) -> Bool {
        guard session == nil,
              let scene = UIApplication.shared.connectedScenes.compactMap({ $0 as? UIWindowScene })
                .first(where: { $0.activationState == .foregroundActive }),
              let appWindow = scene.windows.first(where: { $0.isKeyWindow && $0.windowLevel == .normal }),
              CboxSecurityGate.shared.beginBrowserAuthentication() else { return false }
        anchor = appWindow
        let auth = ASWebAuthenticationSession(url: url, callbackURLScheme: "com.cboxid.authenticator") { [weak self] callback, error in
            DispatchQueue.main.async {
                guard let self = self, self.session != nil else { return }
                self.session = nil
                self.anchor = nil
                if let callback = callback, error == nil,
                   callback.scheme == "com.cboxid.authenticator", callback.host == nil,
                   callback.path == "/oauth/callback", callback.fragment == nil {
                    CboxSecurityGate.shared.endBrowserAuthentication(completed: true)
                    self.emit(id: id, result: "completed")
                    DeepLinkRouter.shared.handle(url: callback)
                } else {
                    CboxSecurityGate.shared.endBrowserAuthentication()
                    let cancelled = (error as? ASWebAuthenticationSessionError)?.code == .canceledLogin
                    self.emit(id: id, result: cancelled ? "cancelled" : "failed")
                }
            }
        }
        auth.presentationContextProvider = self
        auth.prefersEphemeralWebBrowserSession = false
        session = auth
        guard auth.start() else {
            session = nil
            anchor = nil
            CboxSecurityGate.shared.endBrowserAuthentication()
            return false
        }
        return true
    }

    func presentationAnchor(for session: ASWebAuthenticationSession) -> ASPresentationAnchor {
        anchor ?? ASPresentationAnchor()
    }

    private func emit(id: String, result: String) {
        LaravelBridge.shared.send?("App\\Events\\AuthenticationFinished", ["id": id, "result": result])
    }
}

enum AuthenticatorBrowserFunctions {
    class Open: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            guard let raw = parameters["url"] as? String, let url = URL(string: raw),
                  url.scheme == "https", url.host != nil, url.user == nil, url.password == nil,
                  url.fragment == nil, let id = parameters["id"] as? String,
                  id.range(of: "^[a-f0-9]{32}$", options: .regularExpression) != nil else {
                return ["success": false]
            }
            let open = { CboxAuthenticationBrowser.shared.open(url: url, id: id) }
            return ["success": Thread.isMainThread ? open() : DispatchQueue.main.sync(execute: open)]
        }
    }
}

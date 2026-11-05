import UIKit
import UniformTypeIdentifiers

// Only encrypted, bounded backup files cross this bridge. No setup keys or passwords.
enum AuthenticatorBackupFunctions {
    static let maxBytes = 2_097_152
    private static func onMain<T>(_ body: () -> T) -> T {
        Thread.isMainThread ? body() : DispatchQueue.main.sync(execute: body)
    }
    private static func authorized(_ parameters: [String: Any]) -> Bool {
        let state = CboxSecurityGate.shared.state
        return state["unlocked"] as? Bool == true && state["foreground"] as? Bool == true
            && parameters["generation"] as? Int == state["generation"] as? Int
    }
    class CopyCode: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            AuthenticatorBackupFunctions.onMain {
                guard AuthenticatorBackupFunctions.authorized(parameters),
                      let code = parameters["code"] as? String,
                      code.range(of: "^([0-9]{6}|[0-9]{8})$", options: .regularExpression) != nil,
                      let expires = parameters["expires"] as? Double else { return ["success": false] }
                let deadline = Date(timeIntervalSince1970: expires)
                guard deadline > Date(), deadline.timeIntervalSinceNow <= 300 else { return ["success": false] }
                UIPasteboard.general.setItems([[UTType.utf8PlainText.identifier: code]],
                    options: [.localOnly: true, .expirationDate: deadline])
                return ["success": true]
            }
        }
    }
    class PickBackup: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            AuthenticatorBackupFunctions.onMain {
                guard AuthenticatorBackupFunctions.authorized(parameters),
                      let id = parameters["id"] as? String else { return ["success": false] }
                return ["success": CboxBackupPicker.begin(id: id, contents: nil)]
            }
        }
    }
    class SaveBackup: BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            AuthenticatorBackupFunctions.onMain {
                guard AuthenticatorBackupFunctions.authorized(parameters),
                      let id = parameters["id"] as? String,
                      let contents = parameters["contents"] as? String,
                      contents.hasPrefix("CBOX-TOTP-1\n"),
                      contents.utf8.count <= AuthenticatorBackupFunctions.maxBytes else { return ["success": false] }
                return ["success": CboxBackupPicker.begin(id: id, contents: contents)]
            }
        }
    }
}

private final class CboxBackupPicker: NSObject, UIDocumentPickerDelegate {
    private static var active: CboxBackupPicker?
    private let id: String
    private let exporting: Bool
    private var temporaryDirectory: URL?

    private init(id: String, exporting: Bool) {
        self.id = id
        self.exporting = exporting
    }

    static func begin(id: String, contents: String?) -> Bool {
        guard active == nil, id.range(of: "^[a-f0-9]{32}$", options: .regularExpression) != nil,
              let scene = UIApplication.shared.connectedScenes.compactMap({ $0 as? UIWindowScene })
                  .first(where: { $0.activationState == .foregroundActive }),
              var root = scene.windows.first(where: { $0.isKeyWindow && !$0.isHidden })?.rootViewController else { return false }
        while let presented = root.presentedViewController { root = presented }
        let delegate = CboxBackupPicker(id: id, exporting: contents != nil)
        let picker: UIDocumentPickerViewController
        if let contents = contents {
            do {
                let directory = FileManager.default.temporaryDirectory.appendingPathComponent("cbox-backup-" + UUID().uuidString)
                try FileManager.default.createDirectory(at: directory, withIntermediateDirectories: true)
                delegate.temporaryDirectory = directory
                let formatter = ISO8601DateFormatter()
                formatter.formatOptions = [.withFullDate]
                let file = directory.appendingPathComponent("Cbox-codes-" + formatter.string(from: Date()) + ".cboxbackup")
                try Data(contents.utf8).write(to: file, options: [.atomic, .completeFileProtection])
                var excluded = file
                var values = URLResourceValues()
                values.isExcludedFromBackup = true
                try excluded.setResourceValues(values)
                picker = UIDocumentPickerViewController(forExporting: [file], asCopy: true)
            } catch {
                delegate.cleanup()
                return false
            }
        } else {
            picker = UIDocumentPickerViewController(forOpeningContentTypes: [.data, .content], asCopy: false)
            picker.allowsMultipleSelection = false
        }
        active = delegate
        picker.delegate = delegate
        picker.modalPresentationStyle = .formSheet
        root.present(picker, animated: true)
        return true
    }

    func documentPickerWasCancelled(_ controller: UIDocumentPickerViewController) {
        finish(result: "cancelled")
    }

    func documentPicker(_ controller: UIDocumentPickerViewController, didPickDocumentsAt urls: [URL]) {
        if exporting { finish(result: "saved"); return }
        guard urls.count == 1, let url = urls.first else { finish(result: "failed"); return }
        let access = url.startAccessingSecurityScopedResource()
        defer { if access { url.stopAccessingSecurityScopedResource() } }
        var coordinateError: NSError?
        var selected: String?
        NSFileCoordinator().coordinate(readingItemAt: url, options: [], error: &coordinateError) { readable in
            do {
                let values = try readable.resourceValues(forKeys: [.isRegularFileKey, .fileSizeKey])
                guard values.isRegularFile == true,
                      let size = values.fileSize, size <= AuthenticatorBackupFunctions.maxBytes else { return }
                let handle = try FileHandle(forReadingFrom: readable)
                defer { try? handle.close() }
                guard let bytes = try handle.read(upToCount: AuthenticatorBackupFunctions.maxBytes + 1),
                      bytes.count <= AuthenticatorBackupFunctions.maxBytes,
                      let text = String(data: bytes, encoding: .utf8),
                      text.hasPrefix("CBOX-TOTP-1\n") else { return }
                selected = text
            } catch { /* The result is a fixed error, never a path or file contents. */ }
        }
        if coordinateError == nil, let selected = selected {
            finish(result: "picked", contents: selected)
        } else {
            finish(result: "failed")
        }
    }

    private func finish(result: String, contents: String = "") {
        let payload: [String: Any] = ["id": id, "result": result, "contents": contents]
        cleanup()
        CboxBackupPicker.active = nil
        DispatchQueue.main.async {
            LaravelBridge.shared.send?("App\\Events\\BackupFileResult", payload)
        }
    }
    private func cleanup() {
        if let directory = temporaryDirectory {
            try? FileManager.default.removeItem(at: directory)
            temporaryDirectory = nil
        }
    }
    deinit { cleanup() }
}

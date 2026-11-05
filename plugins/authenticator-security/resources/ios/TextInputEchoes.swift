import Foundation

/// Acknowledges older PHP echoes without replacing newer characters in the field.
struct CboxTextInputEchoes {
    private var pending: [String] = []

    mutating func sent(_ value: String) {
        pending.append(value)
        if pending.count > 256 { pending.removeFirst(pending.count - 256) }
    }

    mutating func acknowledge(_ value: String) -> Bool {
        guard let index = pending.firstIndex(of: value) else { return false }
        pending.removeFirst(index + 1)
        return true
    }

    mutating func reset() { pending.removeAll() }
}

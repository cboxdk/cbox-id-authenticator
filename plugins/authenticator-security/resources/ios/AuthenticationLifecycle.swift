import Foundation

/// Main-queue state, independent of UIKit so the actual lifecycle rules can be tested.
struct CboxAuthenticationLifecycle {
    private(set) var enabled: Bool
    private(set) var locked: Bool
    private(set) var generation = 0
    private(set) var foreground = false
    private(set) var backgrounded = false
    private(set) var browserActive = false
    private(set) var authenticationGeneration: Int?
    private var attemptedUnlock = false
    private var automaticUnlockAllowed = true
    private var mayFinishFirstConnection = false

    init(enabled: Bool) {
        self.enabled = enabled
        self.locked = enabled
    }

    var canAccessSecrets: Bool {
        foreground && !browserActive && (!enabled || !locked)
    }

    var needsCover: Bool {
        enabled && (backgrounded || (!browserActive && (!foreground || locked)))
    }

    var shouldAutomaticallyUnlock: Bool {
        enabled && locked && foreground && !browserActive
            && authenticationGeneration == nil && !attemptedUnlock && automaticUnlockAllowed
    }

    mutating func configure(_ value: Bool, afterSignIn: Bool = false) {
        if value && !enabled {
            // A first successful web sign-in has just authenticated the account.
            // Start protecting it now, without asking for a second authentication
            // before the user has even seen it. Existing locked vaults never use this.
            locked = !(afterSignIn && mayFinishFirstConnection)
            attemptedUnlock = false
        }
        mayFinishFirstConnection = false
        enabled = value
        if !value {
            locked = false
            authenticationGeneration = nil
        }
    }

    mutating func resignActive() { foreground = false }

    mutating func enterBackground() {
        mayFinishFirstConnection = false
        foreground = false
        backgrounded = true
        generation += 1
        locked = enabled
        authenticationGeneration = nil
        attemptedUnlock = false
        automaticUnlockAllowed = !browserActive
    }

    mutating func becomeActive() {
        foreground = true
        backgrounded = false
    }

    mutating func beginBrowser() -> Bool {
        guard canAccessSecrets, authenticationGeneration == nil else { return false }
        mayFinishFirstConnection = false
        browserActive = true
        automaticUnlockAllowed = false
        generation += 1
        return true
    }

    mutating func endBrowser(completed: Bool = false) {
        mayFinishFirstConnection = browserActive && completed && !enabled && !backgrounded
        browserActive = false
        // A deliberate unlock after returning prevents Face ID from competing with
        // the authentication sheet's dismissal or a password-manager handoff.
        automaticUnlockAllowed = false
    }

    mutating func beginUnlock(manual: Bool) -> Int? {
        guard enabled, locked, foreground, !browserActive, authenticationGeneration == nil,
              manual || shouldAutomaticallyUnlock else { return nil }
        attemptedUnlock = true
        authenticationGeneration = generation
        return generation
    }

    mutating func finishUnlock(success: Bool, generation epoch: Int) {
        guard authenticationGeneration == epoch else { return }
        authenticationGeneration = nil
        if success && generation == epoch { locked = false }
    }
}

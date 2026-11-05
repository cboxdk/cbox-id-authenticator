import Foundation

private func check(_ condition: Bool, file: StaticString = #file, line: UInt = #line) {
    precondition(condition, "Authentication lifecycle regression", file: file, line: line)
}

final class AuthenticationLifecycleTests {
    func unlocked(at now: ContinuousClock.Instant = .now) -> CboxAuthenticationLifecycle {
        var state = CboxAuthenticationLifecycle(enabled: true)
        state.becomeActive(at: now)
        let epoch = state.beginUnlock(manual: false)!
        state.finishUnlock(success: true, generation: epoch, at: now)
        return state
    }

    func testBrowserAndPasswordManagerDoNotTriggerFaceID() {
        var state = unlocked()
        check(state.beginBrowser())
        check(!(state.canAccessSecrets))
        check(!(state.beginBrowser()))
        for _ in 0..<5 {
            state.resignActive()
            check(!(state.needsCover))
            state.becomeActive()
            check(!(state.shouldAutomaticallyUnlock))
            check(!(state.needsCover))
            check(state.beginUnlock(manual: true) == nil)
        }
        state.endBrowser()
        check(state.canAccessSecrets)
    }

    func testBackgroundDuringBrowserProtectsSnapshotAndRequiresOneDeliberateUnlock() {
        let now = ContinuousClock.now
        var state = unlocked()
        check(state.beginBrowser())
        let before = state.generation
        state.resignActive()
        state.enterBackground(at: now)
        check(state.needsCover)
        check(state.generation == before + 1)
        state.becomeActive(at: now.advanced(by: .seconds(120)))
        check(!(state.needsCover))
        check(!(state.shouldAutomaticallyUnlock))
        state.endBrowser()
        check(state.needsCover)
        check(!(state.shouldAutomaticallyUnlock))
        check(!(state.canAccessSecrets))
        let epoch = state.beginUnlock(manual: true)!
        check(state.beginUnlock(manual: true) == nil)
        state.resignActive()
        state.finishUnlock(success: true, generation: epoch)
        check(!(state.canAccessSecrets))
        state.becomeActive()
        check(state.canAccessSecrets)
        check(!(state.needsCover))
    }

    func testCancelledFaceIDDoesNotLoopOnActivation() {
        var state = CboxAuthenticationLifecycle(enabled: true)
        state.becomeActive()
        let epoch = state.beginUnlock(manual: false)!
        state.resignActive()
        state.finishUnlock(success: false, generation: epoch)
        for _ in 0..<3 {
            state.becomeActive()
            check(!(state.shouldAutomaticallyUnlock))
            check(state.beginUnlock(manual: false) == nil)
        }
        check(state.beginUnlock(manual: true) != nil)
    }

    func testStaleFaceIDCannotUnlockAfterBackground() {
        var state = CboxAuthenticationLifecycle(enabled: true)
        state.becomeActive()
        let epoch = state.beginUnlock(manual: false)!
        state.enterBackground()
        state.finishUnlock(success: true, generation: epoch)
        state.becomeActive()
        check(!(state.canAccessSecrets))
        check(state.shouldAutomaticallyUnlock)
    }

    func testFirstConnectionProtectsNewAccountWithoutInterruptingBrowser() {
        var state = CboxAuthenticationLifecycle(enabled: false)
        state.becomeActive()
        check(state.beginBrowser())
        state.configure(true)
        check(state.beginUnlock(manual: false) == nil)
        check(!(state.needsCover))
        state.endBrowser()
        check(state.needsCover)
        check(!(state.shouldAutomaticallyUnlock))
        let epoch = state.beginUnlock(manual: true)!
        state.finishUnlock(success: true, generation: epoch)
        check(state.canAccessSecrets)
    }

    func testFailedBrowserStartAndNormalResume() {
        let now = ContinuousClock.now
        var state = unlocked()
        check(state.beginBrowser())
        state.endBrowser()
        check(state.canAccessSecrets)
        check(state.beginBrowser())
        state.endBrowser()
        state.enterBackground(at: now)
        state.becomeActive(at: now.advanced(by: .seconds(120)))
        check(state.shouldAutomaticallyUnlock)
        check(!(state.beginBrowser()))
    }

    func testDisableAndReenableRequiresAuthentication() {
        var state = unlocked()
        state.configure(false)
        check(state.canAccessSecrets)
        state.configure(true)
        check(!(state.canAccessSecrets))
        check(state.shouldAutomaticallyUnlock)
    }

    func testFirstSuccessfulConnectionDoesNotAskForAnotherLogin() {
        let now = ContinuousClock.now
        var state = CboxAuthenticationLifecycle(enabled: false)
        state.becomeActive()
        check(state.beginBrowser())
        state.resignActive()
        state.enterBackground()
        state.becomeActive()
        state.endBrowser(completed: true)
        state.configure(true, afterSignIn: true)
        check(state.canAccessSecrets)
        check(!state.needsCover)
        state.enterBackground(at: now)
        state.becomeActive(at: now.advanced(by: .seconds(120)))
        check(!state.canAccessSecrets)
        check(state.shouldAutomaticallyUnlock)
    }

    func testBrowserSuccessNeverUnlocksAnExistingVault() {
        var state = unlocked()
        check(state.beginBrowser())
        state.enterBackground()
        state.protectedDataLocked()
        state.becomeActive()
        state.endBrowser(completed: true)
        state.configure(true, afterSignIn: true)
        check(!state.canAccessSecrets)
        check(state.needsCover)
    }

    func testCancelledOrBackgroundedSetupStillRequiresAuthentication() {
        for completed in [false, true] {
            var state = CboxAuthenticationLifecycle(enabled: false)
            state.becomeActive()
            check(state.beginBrowser())
            state.endBrowser(completed: completed)
            if completed {
                state.enterBackground()
                state.becomeActive()
            }
            state.configure(true, afterSignIn: true)
            check(!state.canAccessSecrets)
            check(state.needsCover)
        }
    }

    func testBrowserReturnDoesNotBypassCodeVaultSetup() {
        var state = CboxAuthenticationLifecycle(enabled: false)
        state.becomeActive()
        check(state.beginBrowser())
        state.endBrowser(completed: true)
        state.configure(true)
        check(!state.canAccessSecrets)
    }
    func testScreenshotsAndTemporaryInactivityDoNotRelock() {
        var state = unlocked()
        let epoch = state.generation
        for _ in 0..<5 {
            state.resignActive()
            check(!state.canAccessSecrets)
            state.becomeActive()
            check(state.canAccessSecrets)
            check(state.generation == epoch)
            check(!state.shouldAutomaticallyUnlock)
        }
    }

    func testShortAppSwitchKeepsUnlockButInvalidatesPendingActions() {
        let now = ContinuousClock.now
        var state = unlocked(at: now)
        let epoch = state.generation
        state.enterBackground(at: now)
        check(state.needsCover)
        check(!state.canAccessSecrets)
        check(state.generation != epoch)
        state.becomeActive(at: now.advanced(by: .seconds(119)))
        check(state.canAccessSecrets)
        check(!state.needsCover)
        check(!state.shouldAutomaticallyUnlock)
        check(state.hasRecentAuthentication(at: now.advanced(by: .seconds(119))))
    }

    func testBackgroundGraceExpiresAndCannotBeExtendedByDuplicateNotifications() {
        let now = ContinuousClock.now
        var state = unlocked(at: now)
        state.enterBackground(at: now)
        state.enterBackground(at: now.advanced(by: .seconds(100)))
        state.becomeActive(at: now.advanced(by: .seconds(120)))
        check(!state.canAccessSecrets)
        check(!state.hasRecentAuthentication(at: now.advanced(by: .seconds(120))))
        check(state.shouldAutomaticallyUnlock)
    }

    func testDeviceLockImmediatelyCancelsGraceAndRecentAuthentication() {
        let now = ContinuousClock.now
        var state = unlocked(at: now)
        state.enterBackground(at: now)
        state.protectedDataLocked()
        state.becomeActive(at: now.advanced(by: .seconds(1)))
        check(!state.canAccessSecrets)
        check(!state.hasRecentAuthentication(at: now.advanced(by: .seconds(1))))
        check(state.shouldAutomaticallyUnlock)
    }

    func testRecentAuthenticationHasAFixedFiveMinuteWindow() {
        let now = ContinuousClock.now
        let state = unlocked(at: now)
        check(state.hasRecentAuthentication(at: now.advanced(by: .seconds(299))))
        check(!state.hasRecentAuthentication(at: now.advanced(by: .seconds(300))))
        check(!state.hasRecentAuthentication(at: now.advanced(by: .seconds(-1))))
    }

    func testFreshDecisionAuthenticationIsRememberedOnlyForTheSameSession() {
        let now = ContinuousClock.now
        var state = unlocked(at: now)
        let epoch = state.generation
        let later = now.advanced(by: .seconds(301))
        check(!state.hasRecentAuthentication(at: later))
        check(!state.finishDecisionAuthentication(success: false, generation: epoch, at: later))
        check(!state.hasRecentAuthentication(at: later))
        state.resignActive()
        check(state.finishDecisionAuthentication(success: true, generation: epoch, at: later))
        check(!state.hasRecentAuthentication(at: later))
        state.becomeActive(at: later)
        check(state.hasRecentAuthentication(at: later))
        state.enterBackground(at: later)
        check(!state.finishDecisionAuthentication(success: true, generation: epoch, at: later))
        state.becomeActive(at: later.advanced(by: .seconds(1)))
        check(!state.finishDecisionAuthentication(success: true, generation: epoch, at: later))
        check(!state.hasRecentAuthentication(at: later.advanced(by: .seconds(300))))
    }

    func testShortBrowserHandoffDoesNotAskForAnotherUnlock() {
        let now = ContinuousClock.now
        var state = unlocked(at: now)
        check(state.beginBrowser())
        state.enterBackground(at: now)
        state.becomeActive(at: now.advanced(by: .seconds(15)))
        check(!state.canAccessSecrets)
        check(!state.shouldAutomaticallyUnlock)
        state.endBrowser(completed: true)
        check(state.canAccessSecrets)
        check(!state.shouldAutomaticallyUnlock)
    }
}

@main
struct AuthenticationLifecycleTestRunner {
    static func main() {
        let tests = AuthenticationLifecycleTests()
        let cases: [(String, () -> Void)] = [
            ("browser and password manager", tests.testBrowserAndPasswordManagerDoNotTriggerFaceID),
            ("background during browser", tests.testBackgroundDuringBrowserProtectsSnapshotAndRequiresOneDeliberateUnlock),
            ("cancelled biometrics", tests.testCancelledFaceIDDoesNotLoopOnActivation),
            ("stale biometrics", tests.testStaleFaceIDCannotUnlockAfterBackground),
            ("first connection", tests.testFirstConnectionProtectsNewAccountWithoutInterruptingBrowser),
            ("failed browser and resume", tests.testFailedBrowserStartAndNormalResume),
            ("reenabling lock", tests.testDisableAndReenableRequiresAuthentication),
            ("first completed sign-in", tests.testFirstSuccessfulConnectionDoesNotAskForAnotherLogin),
            ("existing vault stays locked", tests.testBrowserSuccessNeverUnlocksAnExistingVault),
            ("cancelled or backgrounded setup", tests.testCancelledOrBackgroundedSetupStillRequiresAuthentication),
            ("code setup still authenticates", tests.testBrowserReturnDoesNotBypassCodeVaultSetup),
            ("screenshots and temporary inactivity", tests.testScreenshotsAndTemporaryInactivityDoNotRelock),
            ("short app switch", tests.testShortAppSwitchKeepsUnlockButInvalidatesPendingActions),
            ("background grace boundary", tests.testBackgroundGraceExpiresAndCannotBeExtendedByDuplicateNotifications),
            ("device lock bypasses grace", tests.testDeviceLockImmediatelyCancelsGraceAndRecentAuthentication),
            ("recent authentication expires", tests.testRecentAuthenticationHasAFixedFiveMinuteWindow),
            ("fresh decision authentication", tests.testFreshDecisionAuthenticationIsRememberedOnlyForTheSameSession),
            ("short browser handoff", tests.testShortBrowserHandoffDoesNotAskForAnotherUnlock),
        ]
        for (name, run) in cases {
            run()
            print("PASS: \(name)")
        }
    }
}

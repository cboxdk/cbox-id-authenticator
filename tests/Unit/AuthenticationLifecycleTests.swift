import Foundation

private func check(_ condition: Bool, file: StaticString = #file, line: UInt = #line) {
    precondition(condition, "Authentication lifecycle regression", file: file, line: line)
}

final class AuthenticationLifecycleTests {
    func unlocked() -> CboxAuthenticationLifecycle {
        var state = CboxAuthenticationLifecycle(enabled: true)
        state.becomeActive()
        let epoch = state.beginUnlock(manual: false)!
        state.finishUnlock(success: true, generation: epoch)
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
        var state = unlocked()
        check(state.beginBrowser())
        let before = state.generation
        state.resignActive()
        state.enterBackground()
        check(state.needsCover)
        check(state.generation == before + 1)
        state.becomeActive()
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
        var state = unlocked()
        check(state.beginBrowser())
        state.endBrowser()
        check(state.canAccessSecrets)
        check(state.beginBrowser())
        state.endBrowser()
        state.enterBackground()
        state.becomeActive()
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
        ]
        for (name, run) in cases {
            run()
            print("PASS: \(name)")
        }
    }
}

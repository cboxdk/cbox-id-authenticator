# Authenticator security

Private iOS plugin for Cbox ID Authenticator. Registers a UIKit privacy shield above
all app screens, requests device-owner authentication on launch and after backgrounding,
and exposes a monotonic background generation to PHP. Approval prompts must capture and
recheck that generation so a queued biometric result cannot approve after the app locks.

`AuthenticatorSecurity.OpenAuthentication` owns one `ASWebAuthenticationSession`,
coordinates with the lock, and sends the fixed, correlated
`App\Events\AuthenticationFinished` event. While that browser is active, app Face ID
never starts. Backgrounding still increments the generation and protects snapshots.
Any required unlock on browser return is deliberate; cancelled Face ID also waits
for an explicit retry. The platform-independent lifecycle is tested in
`tests/Unit/AuthenticationLifecycleTests.swift`.

`AuthenticatorSecurity.Configure` enables the lock when an account is connected.
`AuthenticatorSecurity.State` reports `unlocked`, `foreground` and `generation`.
The lock uses `LAContext.deviceOwnerAuthentication`, including the iPhone passcode
fallback. It never receives biometric data. No Android implementation is shipped yet;
the PHP approval path refuses when the native gate cannot be read.

The manifest copies the privacy declaration into the Xcode resources group on every
build. Changes belong here, never only in the ignored generated Xcode project.

The post-compile hook prepares generated Swift sources before Xcode compilation.
It fixes Firebase event delivery, restricts notification URLs, redirects app/plugin
logging to a no-op, and disables Bifrost OTA checks. The plugin's compile guard is
removed only after successful preparation. Never bypass it on an app target.
The `CBOX_AUTHENTICATOR_SOURCE_CHECK` flag is exclusively for isolated source checks.
The PHP packaging test parses the entire transformed Swift tree on macOS.

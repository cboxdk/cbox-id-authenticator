# Changelog

## Unreleased

- Redesign nine native iOS screens with a blue brand treatment, consistent account
  and action rows, persistent field labels, clear primary actions and dark-mode surfaces.
- Shorten connection onboarding, show connected accounts directly in the inbox, and
  add per-code countdown bars and inline copy feedback to the TOTP vault.

- Fix clipped account-removal controls with an inset destructive row and correlated
  native confirmation; cancellation preserves the account, removal returns to Settings.
- Remove the extra post-login acknowledgment and skip the redundant unlock only after
  the first verified browser sign-in. Existing accounts and code vaults remain protected.
- Fix delayed native input echoes overwriting newly typed characters; dismiss the
  keyboard on Done and clear pending secure input on backgrounding.
- Align form fields and action buttons with native list insets; keep backup and account
  connection opened from Settings within that tab and its back stack.
- Coordinate system browser login with the native app lock; prevent competing Face ID
  prompts during Google/password-manager handoffs and automatic retry after cancellation.
- Finish scanner dismissal before opening login; clean up cancelled OAuth reservations.
- Start new users on Cbox ID, show connected account context, and clarify the onboarding
  steps with consistent native spacing. Code-only users still start in their vault.

- Add offline TOTP (SHA1/SHA256/SHA512, six/eight digits) with native code lists,
  QR/manual setup, search, rename, individual deletion and expiring local clipboard.
- Store up to 500 codes atomically in device-only Keychain, independently of Cbox accounts.
- Add password-encrypted Argon2id/XChaCha20-Poly1305 file backups, native Files
  import/export, preview and duplicate-safe merge; no automatic synchronization.
- Keep three native tabs available without sign-in; protect code and backup state
  across app lock/background events.
- Align CocoaPods deployment targets with iOS 18.2 for Xcode 27 and update the
  embedded PHP runtime to 8.4.27. Signed development builds installed over USB.

- Finish the iOS SuperNative screens, account details, same-phone onboarding, Danish
  and English text, privacy/help and third-party notices.
- Wait for correlated device-authentication completion before approving; invalidate
  pending decisions on cancellation, navigation, backgrounding and expiry.
- Add the native app lock and app-switcher privacy shield.
- Verify account identity, safely replace reconnected accounts, preserve Keychain
  state on errors and store refreshed credentials atomically.
- Enrol without push permission; retry changed push tokens and route notifications
  through locally registered installations.
- Fetch pending requests across accounts with bounded concurrency and pagination;
  distinguish outages from missing or completed requests.
- Add typed API outcomes, unexpected-request reporting, source checks, dependency
  checks and reproducible iOS hardening, including credential-log and OTA removal.
- Require the matching Cbox ID API v2 changes for device revocation, DPoP authorization,
  verified identity, reports, nullable push tokens and account-specific push routing.

Native device and TestFlight acceptance remain required before a release is tagged.

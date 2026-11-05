# Changelog

## Unreleased

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

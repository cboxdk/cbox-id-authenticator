# Cbox ID

Native iPhone companion for Cbox ID: approve or deny sign-ins and agent actions,
report unexpected requests, manage several connected accounts, and keep offline TOTP
codes for other services with encrypted backup/import/export.

Built with **NativePHP 4 / SuperNative**, EDGE and native SwiftUI controls. The app's
screens contain no webviews. Sign-in uses the system authentication browser. A local
Swift plugin provides the app lock, privacy shield and notification routing policy.
Android is not release-ready: the security plugin currently supports iOS only.

## Current status

Version **1.0.0 (5)** is available in TestFlight and selected in the App Store draft.
Bifrost build **13898** uses `main` revision `ac0466a6acf26fa7bc809e3e62e21bdad7685a7a`.
Firebase/APNs are configured. On 6 November 2026 the owner received test push
**PUSH 7926**, opened it and approved it; the server recorded approval at 14:01:02 UTC.
The temporary test client was removed after verification.

The release revision passed 261 PHP tests / 10,318 assertions, PHPStan, Pint and
plugin/dependency checks in GitHub CI. The signed IPA was inspected for its name
(**Cbox ID**), bundle ID, build number, push configuration and the owner-approved
authentication-only encryption exemption.

The App Store draft has ten localized listings and six polished screenshots per
language. Review contact, age rating, free pricing, build selection and manual
release are configured. Privacy publication awaits the owner's confirmation;
a separate reviewer account and repeatable approval test flow still need to be
provisioned. The app has **not** been submitted for review or publicly released.

- [Release status and remaining gates](IOS-RELEASE-PLAN.md).
- [Store metadata, artwork and reviewer steps](STORE-LISTING.md).
- [Physical device test guide](IPHONE-TEST.md).

## Development and checks

Use PHP **8.4** for this project, matching the minor version in `nativephp.lock`.
The existing server project uses PHP 8.5. Do not change its runtime to match the app.
Marketplace credentials belong in global Composer authentication or CI secrets.

```sh
composer install
php artisan key:generate
composer validate --strict
composer audit --locked
composer qa
php artisan authenticator:check
```

`composer qa` runs the PHP suite, Larastan, plugin validation and the locked production
dependency/license inventory check. After an intentional dependency update, regenerate
that inventory with `php artisan authenticator:dependencies` and review the diff.
The `reviewed-nativephp-runtime` Composer package repository freezes both the
NativePHP source archive and its dependency metadata. Bifrost may resolve dependencies
again when it encounters the local plugin repository. Update this package definition
and the lock file together when qualifying a new runtime. Native preparation verifies
installed production versions and commit references against the reviewed inventory.
The app binds NativePHP's packaging command to preserve the explicit, owner-approved
authentication-only encryption exemption after archiving. Export stops if that
declaration is missing or changed. The exemption covers login proofs, authentication
secrets and their encrypted TOTP backups; reassess it before adding general file/data
encryption. Approved on 2026-11-06 against the [BIS authentication and key-management
guidance](https://www.bis.gov/learn-support/encryption-controls/cryptography-for-data-confidentiality)
and [Apple's encryption declaration guidance](https://developer.apple.com/documentation/security/complying-with-encryption-export-regulations).
The native app exposes 97 Composer and 9 resolved iOS dependency notices under
Privacy and help → Licenses. After an iOS dependency update, run
`php artisan authenticator:ios-notices`, review the licenses, then verify them with
`php artisan authenticator:ios-notices --check`. The command reads CocoaPods notices
and the pinned ZIPFoundation checkout. Check the final archive against this inventory.

The iOS preparation hook runs on every native project generation. It restricts push
links to existing local installations, fixes foreground refresh delivery, disables
native credential logging and Bifrost OTA updates. It keeps native tabs visible and
waits for scanner dismissal before delivering a scanned code, and acknowledges queued
input echoes without replacing newer text. It generates ten Localizable.strings files,
localizes scanner and authentication dialogs, and exposes action-row labels and hints
to VoiceOver. A Swift compile guard prevents a
build if preparation did not complete. Source-only CI defines
`CBOX_AUTHENTICATOR_SOURCE_CHECK`; never set this flag on an app build.

For native installation and building, follow [IPHONE-TEST.md](IPHONE-TEST.md).
Do not hand-edit `vendor/` or the generated `nativephp/ios/` directory to fix product code.

## Security boundary

The system sign-in browser and app lock share a native lifecycle. Google or password
manager handoffs never automatically start the app's own Face ID prompt. Real
backgrounding still locks secrets and covers app snapshots; after a browser handoff,
any required unlock is deliberate. Cancelled biometrics never retry in a loop.
Browser cancellation removes the unused OAuth reservation and its private key.
Only a verified first browser connection can enable protection without a second unlock;
existing protected data still requires device authentication. The pure Swift lifecycle
has eleven regression scenarios, with separate delayed-input-echo checks in source CI.

Approvals require a matching asynchronous device-authentication result. Cancellation,
leaving the screen, backgrounding, expiry and duplicate events cannot send an approval.
The server separately requires an enrolled, non-revoked device, the provisioned public
client and valid DPoP. Device removal immediately refuses its old access token and
revokes the matching refresh grants.

DPoP keys are generated by OpenSSL and stored in the iPhone Keychain. They are **not
Secure Enclave keys** and this is not a WebAuthn/passkey authenticator. Push contains
routing hints; request descriptions and decisions come from the authenticated API.
TOTP works without a Cbox account. SHA1/SHA256/SHA512, six/eight digits and periods
from 1–300 seconds are supported. Setup uses standard otpauth://totp QR codes or
manual Base32 keys. HOTP and other authenticators’ bulk migration formats are not
supported. Up to 500 codes are stored atomically in a separate, device-only Keychain
item; signing out of Cbox does not remove them. Codes use the phone clock, never a
Cbox server clock offset. Copied codes are local to the device and expire.

Backups use the CBOX-TOTP-1 format: a magic line followed by a JSON envelope with
version 1, Argon2id13 (2 operations, 64 MiB), a random 16-byte salt, a random 24-byte
nonce and XChaCha20-Poly1305 ciphertext. The canonical header is authenticated as
additional data. Passwords require at least 12 characters; files are bounded at 2 MiB
before derivation. KDF costs are fixed, never trusted from arbitrary file input.
Only encrypted files cross the native Files bridge. Import validates all entries,
previews new/duplicate counts and atomically merges without replacing existing codes
or labels. Passwords and decrypted previews are cleared on lock/background generation
changes. No automatic synchronization or Cbox credential backup is provided.

## License

This private app and its local security plugin are marked `proprietary`: no additional
distribution rights are granted. Third-party licenses remain with their respective
packages. Marketplace entitlements and native SDK terms must be checked for the actual
App Store build; the dependency check does not replace that review.

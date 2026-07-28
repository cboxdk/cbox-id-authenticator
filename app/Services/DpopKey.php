<?php

declare(strict_types=1);

namespace App\Services;

use Native\Mobile\Facades\SecureStorage;
use RuntimeException;

/**
 * The device's DPoP signing key.
 *
 * WHAT THIS IS AND — IMPORTANTLY — WHAT IT IS NOT
 * ----------------------------------------------
 * An ES256 keypair generated on this handset, held in SecureStorage (iOS Keychain with
 * `WhenUnlockedThisDeviceOnly`, Android EncryptedSharedPreferences over the Keystore).
 * It sender-constrains every token this app holds: a stolen access or refresh token is
 * useless without it, which is genuinely better than the bearer tokens Authy and Duo
 * Push carry.
 *
 * It is NOT hardware-backed. SecureStorage stores STRINGS; it exposes no key generation
 * and no signing primitive, so the key is produced by ext-openssl, kept as a PEM, and
 * loaded back into PHP userspace to sign each proof. The Keystore protects it at rest —
 * it does not make it non-exportable, and it is in process memory every time a proof is
 * minted. An attacker with code execution on a rooted handset can lift it.
 *
 * That ceiling is why this app is positioned as a decoupled approval surface rather
 * than as stronger 2FA: a passkey registered through the platform's own WebAuthn flow
 * is cryptographically stronger than anything reachable from here. Raising it means a
 * custom NativePHP plugin exposing SecKeyCreateRandomKey with
 * kSecAttrTokenIDSecureEnclave on iOS and KeyGenParameterSpec with StrongBox on
 * Android, returning a SIGNATURE rather than a key. That is real Swift/Kotlin work and
 * a separate epic.
 *
 * The key never leaves the device, and `WhenUnlockedThisDeviceOnly` means it is not in
 * iCloud backups and does not restore onto a replacement handset. A restored phone
 * re-enrols from scratch, which is correct — do not try to work around it.
 */
final class DpopKey
{
    private const STORAGE_KEY = 'cboxid.dpop.privkey';

    private ?string $pem = null;

    /**
     * The private key PEM, generating and persisting one on first use.
     */
    public function pem(): string
    {
        if (is_string($this->pem)) {
            return $this->pem;
        }

        $stored = SecureStorage::get(self::STORAGE_KEY);

        if (is_string($stored) && $stored !== '') {
            return $this->pem = $stored;
        }

        return $this->pem = $this->generate();
    }

    /**
     * The public JWK, as it must appear in a DPoP proof header.
     *
     * Public parameters only — `crv`, `kty`, `x`, `y`. The server rejects a proof whose
     * header JWK carries private material, and rightly so.
     *
     * @return array{crv: string, kty: string, x: string, y: string}
     */
    public function publicJwk(): array
    {
        $details = openssl_pkey_get_details($this->resource());

        if ($details === false || ! isset($details['ec']['x'], $details['ec']['y'])) {
            throw new RuntimeException('Could not read the DPoP public key.');
        }

        return [
            // Ordered lexicographically because the thumbprint below hashes this
            // canonically, and RFC 7638 requires exactly that ordering.
            'crv' => 'P-256',
            'kty' => 'EC',
            'x' => self::base64Url(str_pad($details['ec']['x'], 32, "\0", STR_PAD_LEFT)),
            'y' => self::base64Url(str_pad($details['ec']['y'], 32, "\0", STR_PAD_LEFT)),
        ];
    }

    /**
     * The RFC 7638 JWK thumbprint — the `jkt` the server binds tokens to.
     */
    public function thumbprint(): string
    {
        return self::base64Url(hash('sha256', json_encode($this->publicJwk(), JSON_THROW_ON_ERROR), true));
    }

    /**
     * @return \OpenSSLAsymmetricKey
     */
    public function resource(): \OpenSSLAsymmetricKey
    {
        $key = openssl_pkey_get_private($this->pem());

        if ($key === false) {
            throw new RuntimeException('The stored DPoP key is unreadable.');
        }

        return $key;
    }

    /**
     * Forget the key. Used on sign-out, alongside revoking the refresh token and
     * deregistering the device — after this the handset cannot mint a valid proof, so
     * any token that survived elsewhere is inert.
     */
    public function forget(): void
    {
        SecureStorage::delete(self::STORAGE_KEY);
        $this->pem = null;
    }

    private function generate(): string
    {
        $key = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'prime256v1',
        ]);

        if ($key === false || ! openssl_pkey_export($key, $pem)) {
            throw new RuntimeException('Could not generate a DPoP key on this device.');
        }

        if (! SecureStorage::set(self::STORAGE_KEY, $pem)) {
            // Refusing here is deliberate. Carrying on with an in-memory-only key would
            // produce an enrolment that silently stops working on the next app launch,
            // and the user would have no way to tell why their prompts stopped.
            throw new RuntimeException('Could not store the DPoP key securely on this device.');
        }

        return $pem;
    }

    public static function base64Url(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }
}

<?php

declare(strict_types=1);

namespace App\Totp;

use RuntimeException;
use Throwable;

/** Portable, password-encrypted v1 backup. KDF costs are fixed before any allocation. */
final class EncryptedBackup
{
    public const int MAX_BYTES = 2097152;

    private const string PREFIX = "CBOX-TOTP-1\n";

    private const int OPS = 2;

    private const int MEMORY = 67108864;

    /** @param list<TotpEntry> $entries */
    public function encrypt(#[\SensitiveParameter] array $entries, #[\SensitiveParameter] string $password): string
    {
        if (mb_strlen($password) < 12 || strlen($password) > 1024) {
            throw new RuntimeException('Use a backup password of at least 12 characters and at most 1024 bytes.');
        }
        if (count($entries) > TotpStore::MAX_ENTRIES) {
            throw new RuntimeException('Too many codes in this backup.');
        }
        $salt = random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES);
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $header = $this->header($salt, $nonce);
        $plain = json_encode(['version' => 1, 'created_at' => gmdate('c'),
            'entries' => array_map(fn (TotpEntry $e): array => $e->toArray(), $entries)], JSON_THROW_ON_ERROR);
        $key = $this->key($password, $salt);
        try {
            $ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($plain, $this->aad($header), $nonce, $key);
            $result = self::PREFIX.json_encode($header + ['ciphertext' => base64_encode($ciphertext)], JSON_THROW_ON_ERROR);
            if (strlen($result) > self::MAX_BYTES) {
                throw new RuntimeException('This backup is too large.');
            }

            return $result;
        } finally {
            sodium_memzero($key);
            sodium_memzero($plain);
        }
    }

    /** @return list<TotpEntry> */
    public function decrypt(#[\SensitiveParameter] string $file, #[\SensitiveParameter] string $password): array
    {
        $plain = false;
        $key = null;
        try {
            if (strlen($file) > self::MAX_BYTES || ! str_starts_with($file, self::PREFIX) || $password === '' || strlen($password) > 1024) {
                throw new RuntimeException;
            }
            $data = json_decode(substr($file, strlen(self::PREFIX)), true, 8, JSON_THROW_ON_ERROR);
            if (! is_array($data) || count($data) !== 8 || ($data['version'] ?? null) !== 1
                || ($data['format'] ?? null) !== 'cbox-totp' || ($data['kdf'] ?? null) !== 'argon2id13'
                || ($data['opslimit'] ?? null) !== self::OPS || ($data['memlimit'] ?? null) !== self::MEMORY) {
                throw new RuntimeException;
            }
            $salt = $this->decode($data['salt'] ?? null, SODIUM_CRYPTO_PWHASH_SALTBYTES);
            $nonce = $this->decode($data['nonce'] ?? null, SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
            $ciphertext = $this->decode($data['ciphertext'] ?? null);
            if (strlen($ciphertext) < SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_ABYTES) {
                throw new RuntimeException;
            }
            $key = $this->key($password, $salt);
            $plain = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt($ciphertext, $this->aad($this->header($salt, $nonce)), $nonce, $key);
            if ($plain === false) {
                throw new RuntimeException;
            }
            $payload = json_decode($plain, true, 16, JSON_THROW_ON_ERROR);
            if (! is_array($payload) || ($payload['version'] ?? null) !== 1
                || ! is_array($payload['entries'] ?? null) || ! array_is_list($payload['entries'])
                || count($payload['entries']) > TotpStore::MAX_ENTRIES) {
                throw new RuntimeException;
            }

            return array_map(fn (array $row): TotpEntry => TotpEntry::fromArray($row), $payload['entries']);
        } catch (Throwable) {
            throw new RuntimeException('Could not open this backup. Check its password and use a Cbox encrypted backup file.');
        } finally {
            if (is_string($key)) {
                sodium_memzero($key);
            }
            if (is_string($plain)) {
                sodium_memzero($plain);
            }
        }
    }

    /** @return array<string, string|int> */
    private function header(string $salt, string $nonce): array
    {
        return ['format' => 'cbox-totp', 'version' => 1, 'kdf' => 'argon2id13',
            'opslimit' => self::OPS, 'memlimit' => self::MEMORY, 'salt' => base64_encode($salt), 'nonce' => base64_encode($nonce)];
    }

    /** @param array<string, string|int> $header */
    private function aad(array $header): string
    {
        return self::PREFIX.json_encode($header, JSON_THROW_ON_ERROR);
    }

    private function key(#[\SensitiveParameter] string $password, string $salt): string
    {
        return sodium_crypto_pwhash(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES, $password, $salt,
            self::OPS, self::MEMORY, SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13);
    }

    private function decode(mixed $value, ?int $length = null): string
    {
        if (! is_string($value)) {
            throw new RuntimeException;
        }
        $bytes = base64_decode($value, true);
        if ($bytes === false || base64_encode($bytes) !== $value || ($length !== null && strlen($bytes) !== $length)) {
            throw new RuntimeException;
        }

        return $bytes;
    }
}

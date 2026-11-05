<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\SecureVault as SecureStorage;
use RuntimeException;

/** One atomic Keychain record per account, including refresh-token rotation. */
final class TokenStore
{
    private const RECORD = 'credentials.v1';

    private const LEGACY = ['token.access', 'token.refresh', 'token.expires'];

    public function __construct(private readonly string $prefix) {}

    public function accessToken(): ?string
    {
        return $this->record()['access'] ?? null;
    }

    public function refreshToken(): ?string
    {
        return $this->record()['refresh'] ?? null;
    }

    public function needsRefresh(): bool
    {
        $record = $this->record();

        return ($record['access'] ?? null) === null || time() >= $record['expires'] - 60;
    }

    public function isSignedIn(): bool
    {
        return $this->refreshToken() !== null;
    }

    public function put(string $accessToken, ?string $refreshToken, int $expiresIn): void
    {
        $json = json_encode([
            'access' => $accessToken,
            'refresh' => $refreshToken !== null && $refreshToken !== '' ? $refreshToken : $this->refreshToken(),
            'expires' => time() + max(0, $expiresIn),
        ], JSON_THROW_ON_ERROR);
        if (! SecureStorage::set($this->prefix.self::RECORD, $json)) {
            throw new RuntimeException('Could not save your session securely. Connect this account again.');
        }
        $this->deleteLegacy();
    }

    /** Serialize refresh across foreground/background PHP workers for this account. */
    public function synchronized(callable $callback): mixed
    {
        $path = storage_path('framework/cache/refresh-'.hash('sha256', $this->prefix).'.lock');
        $handle = fopen($path, 'c');
        if ($handle === false) {
            throw new RuntimeException('Could not lock this session. Try again.');
        }
        try {
            $deadline = microtime(true) + 25;
            while (! flock($handle, LOCK_EX | LOCK_NB)) {
                if (microtime(true) >= $deadline) {
                    throw new RuntimeException('A sign-in refresh is still in progress. Try again.');
                }
                usleep(50000);
            }

            return $callback();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    public function forget(): void
    {
        // Clear legacy first: failing after removal of the new record must not resurrect it.
        $this->deleteLegacy();
        if (! SecureStorage::delete($this->prefix.self::RECORD)) {
            throw new RuntimeException('Could not remove the saved session. Try again.');
        }
    }

    /** @return array{access: ?string, refresh: ?string, expires: int} */
    private function record(): array
    {
        $raw = SecureStorage::get($this->prefix.self::RECORD);
        if (is_string($raw) && $raw !== '') {
            $data = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
            if (! is_array($data) || ! is_int($data['expires'] ?? null)) {
                throw new RuntimeException('The saved session could not be read. Connect this account again.');
            }

            return [
                'access' => is_string($data['access'] ?? null) ? $data['access'] : null,
                'refresh' => is_string($data['refresh'] ?? null) ? $data['refresh'] : null,
                'expires' => $data['expires'],
            ];
        }
        $read = function (string $key): ?string {
            $value = SecureStorage::get($this->prefix.$key);

            return is_string($value) && $value !== '' ? $value : null;
        };
        $record = ['access' => $read('token.access'), 'refresh' => $read('token.refresh'), 'expires' => (int) $read('token.expires')];
        if ($record['access'] !== null || $record['refresh'] !== null) {
            if (! SecureStorage::set($this->prefix.self::RECORD, json_encode($record, JSON_THROW_ON_ERROR))) {
                throw new RuntimeException('Could not upgrade the saved session securely. Try again.');
            }
            $this->deleteLegacy();
        }

        return $record;
    }

    private function deleteLegacy(): void
    {
        foreach (self::LEGACY as $key) {
            if (! SecureStorage::delete($this->prefix.$key)) {
                throw new RuntimeException('Could not clear an old session. Try again.');
            }
        }
    }
}

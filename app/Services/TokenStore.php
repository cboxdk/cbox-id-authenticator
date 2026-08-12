<?php

declare(strict_types=1);

namespace App\Services;

use Native\Mobile\Facades\SecureStorage;

/**
 * The access and refresh tokens for ONE account, in secure storage.
 *
 * Access tokens are short-lived by design for this client — a lifted DPoP key gives an
 * attacker a window, and the shorter it is the better. The refresh token is bound to
 * the same key, so rotation must reuse it: presenting a different `jkt` gets the whole
 * chain refused.
 *
 * Every key is namespaced by the account that owns it. The keys were fixed constants
 * once, which meant connecting a second tenant silently overwrote the first's refresh
 * token — no error, no warning, just a signed-out account.
 */
final class TokenStore
{
    private const ACCESS = 'token.access';

    private const REFRESH = 'token.refresh';

    private const EXPIRES = 'token.expires';

    public function __construct(private readonly string $prefix) {}

    public function accessToken(): ?string
    {
        return $this->read(self::ACCESS);
    }

    public function refreshToken(): ?string
    {
        return $this->read(self::REFRESH);
    }

    /**
     * Whether the access token is close enough to expiry to be worth refreshing before
     * use. A 60-second margin keeps a token from expiring between the check and the
     * call it was checked for.
     */
    public function needsRefresh(): bool
    {
        $expires = $this->read(self::EXPIRES);

        if ($expires === null) {
            return $this->accessToken() === null;
        }

        return (int) $expires - 60 <= time();
    }

    public function isSignedIn(): bool
    {
        return $this->refreshToken() !== null;
    }

    public function put(string $accessToken, ?string $refreshToken, int $expiresIn): void
    {
        SecureStorage::set($this->prefix.self::ACCESS, $accessToken);
        SecureStorage::set($this->prefix.self::EXPIRES, (string) (time() + $expiresIn));

        // A rotation that returns no new refresh token leaves the existing one in
        // place; clearing it here would sign the user out on every refresh.
        if ($refreshToken !== null && $refreshToken !== '') {
            SecureStorage::set($this->prefix.self::REFRESH, $refreshToken);
        }
    }

    public function forget(): void
    {
        foreach ([self::ACCESS, self::REFRESH, self::EXPIRES] as $key) {
            SecureStorage::delete($this->prefix.$key);
        }
    }

    private function read(string $key): ?string
    {
        $value = SecureStorage::get($this->prefix.$key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}

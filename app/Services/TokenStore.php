<?php

declare(strict_types=1);

namespace App\Services;

use Native\Mobile\Facades\SecureStorage;

/**
 * The access and refresh tokens, in secure storage.
 *
 * Access tokens are short-lived by design for this client — a lifted DPoP key gives an
 * attacker a window, and the shorter it is the better. The refresh token is bound to
 * the same key, so rotation must reuse it: presenting a different `jkt` gets the whole
 * chain refused.
 */
final class TokenStore
{
    private const ACCESS = 'cboxid.token.access';

    private const REFRESH = 'cboxid.token.refresh';

    private const EXPIRES = 'cboxid.token.expires';

    private const ISSUER = 'cboxid.issuer';

    public function accessToken(): ?string
    {
        return self::read(self::ACCESS);
    }

    public function refreshToken(): ?string
    {
        return self::read(self::REFRESH);
    }

    public function issuer(): ?string
    {
        return self::read(self::ISSUER);
    }

    public function rememberIssuer(string $issuer): void
    {
        SecureStorage::set(self::ISSUER, $issuer);
    }

    /**
     * Whether the access token is close enough to expiry to be worth refreshing before
     * use. A 60-second margin keeps a token from expiring between the check and the
     * call it was checked for.
     */
    public function needsRefresh(): bool
    {
        $expires = self::read(self::EXPIRES);

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
        SecureStorage::set(self::ACCESS, $accessToken);
        SecureStorage::set(self::EXPIRES, (string) (time() + $expiresIn));

        // A rotation that returns no new refresh token leaves the existing one in
        // place; clearing it here would sign the user out on every refresh.
        if ($refreshToken !== null && $refreshToken !== '') {
            SecureStorage::set(self::REFRESH, $refreshToken);
        }
    }

    public function forget(): void
    {
        foreach ([self::ACCESS, self::REFRESH, self::EXPIRES, self::ISSUER] as $key) {
            SecureStorage::delete($key);
        }
    }

    private static function read(string $key): ?string
    {
        $value = SecureStorage::get($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}

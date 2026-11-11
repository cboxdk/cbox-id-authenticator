<?php

declare(strict_types=1);

namespace App\Services;

/**
 * The `api_base` a host advertises in `/.well-known/cbox-authenticator`.
 *
 * Cbox ID v3 moved the management API to its own host (`https://api.cboxid.com/v1`). An
 * account connected before that, or to a host that advertises nothing, keeps calling
 * `{issuer}/api/v1` exactly as before: null here means "the issuer's own `/api/v1`".
 *
 * The value decides where a DPoP-bound access token is sent, so it is held to the same
 * allowlist as the host that was scanned. Anything unexpected is ignored rather than
 * refused: the issuer's own `/api/v1` still answers, so a malformed value costs nothing.
 */
final class ApiBase
{
    /** The only API version this build speaks on the API host. */
    public const string V1_PATH = '/v1';

    public static function fromAdvertised(mixed $value, string $issuer): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $base = rtrim(trim($value), '/');

        // Today's base, advertised outright: nothing changes.
        if ($base === self::legacy($issuer)) {
            return null;
        }

        $parts = parse_url($base);

        if (! is_array($parts) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            return null;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        $authority = $host.(isset($parts['port']) ? ':'.$parts['port'] : '');

        if ($host === '' || ($parts['path'] ?? '') !== self::V1_PATH || ! SupportedHost::allows($authority)) {
            return null;
        }

        $loopback = preg_match('/^(localhost|127\.0\.0\.1|\[::1\])$/', $host) === 1;

        if ($scheme !== 'https' && ! ($scheme === 'http' && $loopback)) {
            return null;
        }

        return $scheme.'://'.$authority.self::V1_PATH;
    }

    public static function legacy(string $issuer): string
    {
        return rtrim($issuer, '/').'/api/v1';
    }
}

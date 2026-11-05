<?php

declare(strict_types=1);

namespace App\Services;

/**
 * What a scanned QR code, or a tapped enrolment link, actually says.
 *
 * A QR code is attacker-supplied input. Everything here refuses rather than coerces:
 * "make it work somehow" is how an app ends up asking a hostile server for its
 * authenticator client.
 *
 * Two shapes are accepted, and they are the same shape:
 *
 *   com.cboxid.authenticator://connect?host=acme.cboxid.com&t=<jws>
 *   https://acme.cboxid.com                (bare host or URL — no token, first-run only)
 *
 * The token is the short-lived proof that the code came from a live console session.
 * It is carried, not trusted, and not verified here either: the server checks it at
 * `POST /devices`, which is the only place that can. {@see EnrolmentToken} does nothing
 * more than glance at its expiry so a stale code fails early.
 */
final readonly class EnrolmentCode
{
    public function __construct(
        public string $host,
        public ?string $token = null,
    ) {}

    public static function parse(string $raw): ?self
    {
        $raw = trim($raw);

        if ($raw === '') {
            return null;
        }

        $token = null;

        if (str_contains($raw, '://')) {
            $parts = parse_url($raw);

            if (! is_array($parts)) {
                return null;
            }

            if (! in_array($parts['scheme'] ?? '', ['https', 'com.cboxid.authenticator'], true) || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
                return null;
            }
            if ($parts['scheme'] === 'com.cboxid.authenticator' && ($parts['host'] ?? '') !== 'connect') {
                return null;
            }

            $query = [];
            parse_str($parts['query'] ?? '', $query);

            $token = is_string($query['t'] ?? null) && $query['t'] !== '' ? $query['t'] : null;

            // Our own scheme carries the host as a query parameter; an https URL carries
            // it as the authority. Anything else is neither.
            $raw = $parts['scheme'] === 'https'
                ? ($parts['host'] ?? '')
                : (is_string($query['host'] ?? null) ? $query['host'] : '');
        }

        $host = self::hostFrom($raw);

        return $host === null ? null : new self($host, $token);
    }

    /**
     * A bare hostname, and nothing that could smuggle a path, port or credentials past
     * the check.
     */
    private static function hostFrom(string $raw): ?string
    {
        $raw = strtolower(trim($raw));

        if ($raw === '') {
            return null;
        }

        // A loopback host, optionally with a port, so the app can be pointed at a local
        // `php artisan serve`. Narrow on purpose: only these names, and a port is the
        // only extra thing allowed. Without it the first end-to-end sign-in test would
        // be stuck behind a deployment.
        if (preg_match('/^(localhost|127\.0\.0\.1|\[::1\])(:\d{1,5})?$/', $raw) === 1) {
            return $raw;
        }

        return preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/', $raw) === 1 ? $raw : null;
    }
}

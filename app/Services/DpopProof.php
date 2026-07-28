<?php

declare(strict_types=1);

namespace App\Services;

use Firebase\JWT\JWT;
use Illuminate\Support\Str;

/**
 * Mints one DPoP proof per request (RFC 9449).
 *
 * The server's validator requires `typ=dpop+jwt`, an ES256 signature, a public-only
 * `jwk` in the header, `htm`/`htu` matching the request, a single-use `jti`, an `iat`
 * within 60 seconds, and — at a resource endpoint — an `ath` equal to the SHA-256 of
 * the access token being presented.
 *
 * THE CLOCK PROBLEM, AND WHY THE OFFSET EXISTS
 * --------------------------------------------
 * That 60-second `iat` window has no recovery path on this platform: laravel-id
 * implements no `DPoP-Nonce` challenge, which is the mechanism RFC 9449 §8 provides
 * precisely so a server can hand a drifting client a fresh reference point. Without it,
 * a handset whose clock is off by more than a minute gets a hard `invalid_dpop_proof`
 * on every single call and the app is simply, inexplicably dead.
 *
 * So the client corrects itself: every response carries a `Date` header, and
 * {@see syncTo()} records the difference between it and local time. Proofs are stamped
 * with the corrected time. This is a mitigation, not a fix — the real fix is
 * server-side nonce support, and it is worth adding.
 */
final class DpopProof
{
    /** Seconds to add to local time to match the server. */
    private int $offset = 0;

    public function __construct(private readonly DpopKey $key) {}

    /**
     * Learn the server's clock from a response `Date` header.
     *
     * Cheap and worth doing on every response: drift is not constant, and the bootstrap
     * call alone would only correct once per launch.
     */
    public function syncTo(?string $serverDate): void
    {
        if ($serverDate === null || $serverDate === '') {
            return;
        }

        $parsed = strtotime($serverDate);

        if ($parsed === false) {
            return;
        }

        $this->offset = $parsed - time();
    }

    public function offset(): int
    {
        return $this->offset;
    }

    /**
     * A proof for one request.
     *
     * `$accessToken` is supplied at resource endpoints and omitted at the token
     * endpoint, where there is no token to bind to yet.
     */
    public function mint(string $method, string $url, ?string $accessToken = null): string
    {
        $claims = [
            // Single-use. The server persists (jkt, jti) under a unique constraint, so a
            // replayed proof is refused outright.
            'jti' => (string) Str::ulid(),
            'htm' => strtoupper($method),
            // Origin and path only — the server normalises away query and fragment, and
            // sending them would simply fail to match.
            'htu' => self::normaliseUrl($url),
            'iat' => time() + $this->offset,
        ];

        if ($accessToken !== null && $accessToken !== '') {
            $claims['ath'] = DpopKey::base64Url(hash('sha256', $accessToken, true));
        }

        return JWT::encode($claims, $this->key->resource(), 'ES256', null, [
            'typ' => 'dpop+jwt',
            'jwk' => $this->key->publicJwk(),
        ]);
    }

    /**
     * The headers a DPoP-bound request carries.
     *
     * Note the `DPoP` auth scheme rather than `Bearer`: once a token carries a
     * `cnf.jkt`, the server's resource guard REQUIRES it be presented this way and
     * refuses a plain bearer presentation of the same token.
     *
     * @return array<string, string>
     */
    public function headers(string $method, string $url, ?string $accessToken = null): array
    {
        $headers = ['DPoP' => $this->mint($method, $url, $accessToken)];

        if ($accessToken !== null && $accessToken !== '') {
            $headers['Authorization'] = 'DPoP '.$accessToken;
        }

        return $headers;
    }

    private static function normaliseUrl(string $url): string
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return $url;
        }

        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return $parts['scheme'].'://'.$parts['host'].$port.($parts['path'] ?? '');
    }
}

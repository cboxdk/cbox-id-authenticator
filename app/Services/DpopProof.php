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
 * The key is the ACCOUNT's, the clock is the DEVICE's. That split is why the two are
 * separate collaborators: proofs for two different accounts must be signed by two
 * different keys, but both benefit from the same drift correction. See
 * {@see ServerClock} for why the 60-second window needs correcting at all.
 */
final class DpopProof
{
    public function __construct(
        private readonly DpopKey $key,
        private readonly ServerClock $clock,
    ) {}

    /**
     * Learn the server's clock from a response `Date` header.
     *
     * Cheap and worth doing on every response: drift is not constant, and the bootstrap
     * call alone would only correct once per launch.
     */
    public function syncTo(?string $serverDate): void
    {
        $this->clock->syncTo($serverDate);
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
            'iat' => $this->clock->now(),
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

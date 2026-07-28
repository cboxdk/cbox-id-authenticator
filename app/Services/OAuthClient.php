<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Native\Mobile\Facades\SecureStorage;
use RuntimeException;

/**
 * Authorization Code + PKCE (S256), as a public client, DPoP-bound.
 *
 * The app holds no client secret — it cannot, since the bundle ships as readable PHP —
 * and does not need one. Possession of the `client_id` grants nothing: PKCE binds the
 * code to a verifier this install generated, and DPoP binds the resulting tokens to a
 * key that never leaves the handset.
 *
 * The `client_id` itself is DISCOVERED rather than compiled in, because the platform's
 * `oauth_clients.client_id` carries a global unique index — each environment mints its
 * own, so no single value could serve every tenant from one App Store binary.
 */
final class OAuthClient
{
    private const VERIFIER = 'cboxid.pkce.verifier';

    private const STATE = 'cboxid.pkce.state';

    private const CLIENT_ID = 'cboxid.client_id';

    public function __construct(
        private readonly TokenStore $tokens,
        private readonly DpopProof $proof,
    ) {}

    /**
     * Ask a host what its authenticator client looks like.
     *
     * @return array<string, mixed>
     */
    public function discover(string $host): array
    {
        $base = 'https://'.preg_replace('#^https?://#', '', rtrim($host, '/'));

        $response = Http::acceptJson()->timeout(15)->get($base.'/.well-known/cbox-authenticator');

        // Correct the clock from the very first call, before any proof is minted —
        // otherwise a drifting handset fails its token exchange with nothing to learn from.
        $this->proof->syncTo($response->header('Date'));

        if (! $response->successful()) {
            throw new RuntimeException('That host does not offer the Cbox ID authenticator.');
        }

        $body = $response->json();

        if (! is_array($body) || ! is_string($body['issuer'] ?? null) || ! is_string($body['client_id'] ?? null)) {
            throw new RuntimeException('That host returned an unusable authenticator document.');
        }

        $this->tokens->rememberIssuer($body['issuer']);
        SecureStorage::set(self::CLIENT_ID, $body['client_id']);

        return $body;
    }

    /**
     * The URL to hand to the system browser, and the state to verify on return.
     */
    public function authorizeUrl(string $redirectUri): string
    {
        $verifier = DpopKey::base64Url(random_bytes(32));
        $state = DpopKey::base64Url(random_bytes(16));

        SecureStorage::set(self::VERIFIER, $verifier);
        SecureStorage::set(self::STATE, $state);

        $query = http_build_query([
            'response_type' => 'code',
            'client_id' => $this->clientId(),
            'redirect_uri' => $redirectUri,
            'scope' => 'openid offline_access devices.manage approvals.read approvals.write',
            'state' => $state,
            'nonce' => (string) Str::ulid(),
            'code_challenge' => DpopKey::base64Url(hash('sha256', $verifier, true)),
            'code_challenge_method' => 'S256',
            // RFC 8707. Pins the token's audience to this issuer so it cannot be
            // replayed against another resource server.
            'resource' => $this->issuer(),
        ]);

        return $this->issuer().'/oauth/authorize?'.$query;
    }

    /**
     * Exchange the code the callback deep link carried.
     *
     * `$redirectUri` must be BYTE-IDENTICAL to the one sent to /authorize — the server
     * compares them exactly, and a trailing slash is enough to fail the exchange.
     */
    public function exchange(string $code, string $state, string $redirectUri): void
    {
        $expected = SecureStorage::get(self::STATE);

        if (! is_string($expected) || ! hash_equals($expected, $state)) {
            throw new RuntimeException('The sign-in response did not match this request.');
        }

        $verifier = SecureStorage::get(self::VERIFIER);

        if (! is_string($verifier) || $verifier === '') {
            throw new RuntimeException('This sign-in attempt has expired. Try again.');
        }

        $this->token([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'code_verifier' => $verifier,
            'client_id' => $this->clientId(),
            'resource' => $this->issuer(),
        ]);

        SecureStorage::delete(self::VERIFIER);
        SecureStorage::delete(self::STATE);
    }

    /**
     * A usable access token, refreshing first if it is at or near expiry.
     */
    public function freshAccessToken(): string
    {
        if (! $this->tokens->needsRefresh()) {
            $token = $this->tokens->accessToken();

            if ($token !== null) {
                return $token;
            }
        }

        return $this->refresh();
    }

    /**
     * Rotate. The proof must be signed with the SAME key the refresh token was bound
     * to — the server refuses a different `jkt` outright, which is exactly what makes a
     * stolen refresh token worthless.
     */
    public function refresh(): string
    {
        $refresh = $this->tokens->refreshToken();

        if ($refresh === null) {
            throw new RuntimeException('This device is signed out.');
        }

        return $this->token([
            'grant_type' => 'refresh_token',
            'refresh_token' => $refresh,
            'client_id' => $this->clientId(),
            'resource' => $this->issuer(),
        ]);
    }

    /**
     * Revoke the refresh token server-side on sign-out.
     */
    public function revoke(): void
    {
        $refresh = $this->tokens->refreshToken();

        if ($refresh === null) {
            return;
        }

        $url = $this->issuer().'/oauth/revoke';

        Http::asForm()->timeout(10)->post($url, [
            'token' => $refresh,
            'token_type_hint' => 'refresh_token',
            'client_id' => $this->clientId(),
        ]);
    }

    /**
     * @param  array<string, string>  $payload
     */
    private function token(array $payload): string
    {
        $url = $this->issuer().'/oauth/token';

        $response = Http::asForm()
            ->withHeaders($this->proof->headers('POST', $url))
            ->timeout(20)
            ->post($url, $payload);

        $this->proof->syncTo($response->header('Date'));

        if (! $response->successful()) {
            throw new RuntimeException('Sign-in failed: '.(string) $response->json('error', $response->status()));
        }

        $body = $response->json();

        if (! is_array($body) || ! is_string($body['access_token'] ?? null)) {
            throw new RuntimeException('The token endpoint returned no access token.');
        }

        $refresh = $body['refresh_token'] ?? null;
        $expiresIn = is_numeric($body['expires_in'] ?? null) ? (int) $body['expires_in'] : 900;

        $this->tokens->put(
            $body['access_token'],
            is_string($refresh) ? $refresh : null,
            $expiresIn,
        );

        return $body['access_token'];
    }

    public function clientId(): string
    {
        $id = SecureStorage::get(self::CLIENT_ID);

        if (! is_string($id) || $id === '') {
            throw new RuntimeException('This device has not been connected to a Cbox ID host.');
        }

        return $id;
    }

    private function issuer(): string
    {
        $issuer = $this->tokens->issuer();

        if ($issuer === null) {
            throw new RuntimeException('This device has not been connected to a Cbox ID host.');
        }

        return rtrim($issuer, '/');
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Accounts\Account;
use App\Accounts\PendingSignIns;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Native\Mobile\Facades\SecureStorage;
use RuntimeException;

/**
 * Authorization Code + PKCE (S256), as a public client, DPoP-bound — for ONE account.
 *
 * The app holds no client secret — it cannot, since the bundle ships as readable PHP —
 * and does not need one. Possession of the `client_id` grants nothing: PKCE binds the
 * code to a verifier this install generated, and DPoP binds the resulting tokens to a
 * key that never leaves the handset.
 *
 * The `client_id` is DISCOVERED per host by {@see HostDiscovery} and stored under this
 * account's namespace. It was once kept under a single fixed key, which is why
 * connecting a second tenant broke the first: the same slot held both.
 *
 * WHAT THIS CLASS NO LONGER DOES
 * ------------------------------
 * It does not hold the PKCE verifier or the `state`. Those belong to one attempt, not
 * to an account, and keeping them here in fixed slots meant two overlapping sign-ins
 * clobbered each other. They live in {@see PendingSignIns}, keyed by
 * `state`, and are passed in.
 */
final class OAuthClient
{
    private const CLIENT_ID = 'client_id';

    public function __construct(
        private readonly Account $account,
        private readonly TokenStore $tokens,
        private readonly DpopProof $proof,
    ) {}

    public function rememberClientId(string $clientId): void
    {
        SecureStorage::set($this->account->keyPrefix().self::CLIENT_ID, $clientId);
    }

    public function clientId(): string
    {
        $id = SecureStorage::get($this->account->keyPrefix().self::CLIENT_ID);

        if (! is_string($id) || $id === '') {
            throw new RuntimeException('This account is not connected to a Cbox ID host.');
        }

        return $id;
    }

    /**
     * A fresh PKCE verifier and `state` for one sign-in attempt.
     *
     * Returned rather than stored, so the caller decides where an in-flight attempt
     * lives and two attempts cannot share a slot.
     *
     * @return array{state: string, verifier: string}
     */
    public static function newAttempt(): array
    {
        return [
            'state' => DpopKey::base64Url(random_bytes(16)),
            'verifier' => DpopKey::base64Url(random_bytes(32)),
        ];
    }

    /**
     * The URL to hand to the system browser.
     *
     * Pure — it reads the account's client id and issuer and computes a string. Nothing
     * is written, so an abandoned attempt leaves no trace to clean up.
     */
    public function authorizeUrl(string $redirectUri, string $state, string $verifier): string
    {
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
     *
     * The `state` is checked by the caller, which resolves the attempt by it: an unknown
     * `state` finds no pending record and never reaches this method.
     */
    public function exchange(string $code, string $verifier, string $redirectUri): void
    {
        $this->token([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'code_verifier' => $verifier,
            'client_id' => $this->clientId(),
            'resource' => $this->issuer(),
        ]);
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
            throw new RuntimeException('This account is signed out.');
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

        Http::asForm()->timeout(10)->post($this->issuer().'/oauth/revoke', [
            'token' => $refresh,
            'token_type_hint' => 'refresh_token',
            'client_id' => $this->clientId(),
        ]);
    }

    public function forget(): void
    {
        SecureStorage::delete($this->account->keyPrefix().self::CLIENT_ID);
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

    private function issuer(): string
    {
        return rtrim($this->account->issuer, '/');
    }
}

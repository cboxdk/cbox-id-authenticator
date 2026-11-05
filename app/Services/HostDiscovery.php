<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Asks a host what its authenticator client looks like.
 *
 * Account-independent on purpose: this runs BEFORE an account exists, and its answer is
 * what makes one possible. It reads and validates; it stores nothing. Storing was the
 * old shape, and it is how a second tenant came to overwrite the first's `client_id`.
 *
 * WHY THE CLIENT ID IS DISCOVERED AND NOT COMPILED IN
 * --------------------------------------------------
 * `oauth_clients.client_id` carries a global unique index on the platform, so every
 * environment mints its own and no single value could serve every tenant from one App
 * Store binary. Possession of it grants nothing — PKCE binds the code to a verifier
 * this install generated, and DPoP binds the resulting tokens to a key that never
 * leaves the handset.
 */
final class HostDiscovery
{
    public function __construct(private readonly ServerClock $clock) {}

    public function fetch(string $host): DiscoveredHost
    {
        $base = self::baseUrlFor($host);

        $response = Http::acceptJson()->withoutRedirecting()->connectTimeout(5)->timeout(15)->get($base.'/.well-known/cbox-authenticator');

        // Correct the clock from the very first call, before any proof is minted —
        // otherwise a drifting handset fails its token exchange with nothing to learn from.
        $this->clock->syncTo($response->header('Date'));

        if (! $response->successful()) {
            throw new EnrolmentFailed('That host does not offer the Cbox ID authenticator.');
        }

        $body = $response->json();

        if (! is_array($body) || ! is_string($body['issuer'] ?? null) || ! is_string($body['client_id'] ?? null)) {
            throw new EnrolmentFailed('That host returned an unusable authenticator document.');
        }

        if (rtrim($body['issuer'], '/') !== $base || trim($body['client_id']) === '') {
            throw new EnrolmentFailed('The host returned an unexpected issuer. Scan a fresh code from Trusted devices.');
        }

        if (($body['api_version'] ?? 0) < 2) {
            throw new EnrolmentFailed('Your Cbox ID host needs an update before this app can connect. Contact your administrator.');
        }

        return new DiscoveredHost(
            rtrim($body['issuer'], '/'),
            $body['client_id'],
        );
    }

    /**
     * HTTPS everywhere, except a loopback host during development.
     *
     * Forcing https unconditionally means the app can never be pointed at a local
     * `php artisan serve`, so the first end-to-end test of the sign-in flow would have
     * to wait for a deployed environment. The exception is deliberately narrow —
     * literal localhost or a loopback address, nothing else — and mirrors what the
     * platform's own redirect-URI validator already permits for the same reason.
     *
     * Anything with a real hostname is https, so a tenant cannot be talked onto
     * plaintext by a QR code claiming `http://`.
     */
    public static function baseUrlFor(string $host): string
    {
        $bare = strtolower(preg_replace('#^https?://#', '', rtrim($host, '/')) ?? $host);
        if (! SupportedHost::allows($bare)) {
            throw new EnrolmentFailed(SupportedHost::refusal());
        }
        $loopback = preg_match('/^(localhost|127\.0\.0\.1|\[::1\])(:\d{1,5})?$/', $bare) === 1;

        return ($loopback ? 'http://' : 'https://').$bare;
    }
}

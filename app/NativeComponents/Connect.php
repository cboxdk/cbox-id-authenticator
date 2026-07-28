<?php

declare(strict_types=1);

namespace App\NativeComponents;

use App\Services\OAuthClient;
use App\Services\TokenStore;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Facades\Browser;
use Throwable;

/**
 * Point the app at a Cbox ID host, then sign in.
 *
 * The host is asked what its authenticator client is rather than the app assuming one:
 * `oauth_clients.client_id` is globally unique on the platform, so every environment
 * mints its own and no single value could be compiled into one App Store binary.
 */
class Connect extends NativeComponent
{
    public string $host = '';

    public ?string $error = null;

    public bool $working = false;

    public function mount(): void
    {
        if (app(TokenStore::class)->isSignedIn()) {
            $this->replace('/approvals');
        }
    }

    public function connect(): void
    {
        $host = trim($this->host);

        if ($host === '') {
            $this->error = 'Enter the address your organisation gave you.';

            return;
        }

        $this->working = true;
        $this->error = null;

        try {
            $document = app(OAuthClient::class)->discover($host);

            // Hand off to the system browser rather than an in-app web view. The user
            // must be able to see the real address bar and the real TLS padlock while
            // typing their password — an app-controlled view can render anything it
            // likes, which is exactly the phishing surface a sign-in must not have.
            Browser::auth(app(OAuthClient::class)->authorizeUrl($this->redirectUri()));
        } catch (Throwable $e) {
            $this->error = $e->getMessage();
        } finally {
            $this->working = false;
        }
    }

    /**
     * Must be byte-identical to the value sent to the token endpoint later — the server
     * compares them exactly.
     *
     * Which of the two registered URIs is used depends on Spike A: whether NativePHP
     * accepts a dotted private-use scheme. The HTTPS App Link is the preferred form
     * (RFC 8252 §7.2 — a private-use scheme can be squatted by another app on the same
     * handset), but it constrains a build to one host.
     */
    private function redirectUri(): string
    {
        $scheme = (string) config('nativephp.deeplink_scheme', 'dk.cbox.id.authenticator');

        return $scheme.':/oauth/callback';
    }

    public function render(): View
    {
        return view('native.connect');
    }
}

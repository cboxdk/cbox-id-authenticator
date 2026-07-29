<?php

declare(strict_types=1);

namespace App\NativeComponents;

use App\Concerns\NavigatesScreens;
use App\Services\OAuthClient;
use App\Services\TokenStore;
use Illuminate\View\View;
use Native\Mobile\Attributes\On;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Events\Scanner\CodeScanned;
use Native\Mobile\Facades\Browser;
use Native\Mobile\Facades\Scanner;
use Throwable;

/**
 * Point the app at a Cbox ID host, then sign in.
 *
 * WHY A QR CODE AND NOT A TEXT FIELD
 * ----------------------------------
 * Partly forced, partly better. Forced because NativePHP 4.0.0-rc.1 registers no
 * `text_input` EDGE element — the class exists but nothing registers it, so there is no
 * way to render one. Better because typing `acme.cboxid.com` on a phone keyboard is
 * error-prone, and a mistyped host is not a harmless typo: it is the app asking a
 * stranger's server for an authenticator client. The console renders the code; the
 * handset reads it.
 *
 * A deep link carrying `?host=` does the same job, so an emailed enrolment link works
 * without a camera at all.
 */
class Connect extends NativeComponent
{
    use NavigatesScreens;

    public ?string $error = null;

    public bool $working = false;

    public function mount(): void
    {
        if (app(TokenStore::class)->isSignedIn()) {
            $this->replace('/approvals');

            return;
        }

        // An enrolment deep link may carry the host directly.
        $host = (string) $this->data('host', '');

        if ($host !== '') {
            $this->begin($host);
        }
    }

    public function scan(): void
    {
        Scanner::scan()->id('connect-host')->scan();
    }

    #[On(CodeScanned::class)]
    public function scanned(string $data): void
    {
        $this->begin($data);
    }

    private function begin(string $raw): void
    {
        $host = $this->hostFrom($raw);

        if ($host === null) {
            $this->error = "That code isn't a Cbox ID address.";

            return;
        }

        $this->working = true;
        $this->error = null;

        try {
            app(OAuthClient::class)->discover($host);

            // The SYSTEM browser, not an in-app web view. The user has to be able to see
            // a real address bar and a real padlock while typing their password — an
            // app-controlled view can draw whatever it likes, which is precisely the
            // phishing surface a sign-in must not have.
            Browser::auth(app(OAuthClient::class)->authorizeUrl($this->redirectUri()));
        } catch (Throwable $e) {
            $this->error = $e->getMessage();
        } finally {
            $this->working = false;
        }
    }

    /**
     * Accept a bare hostname, an https URL, or the app's own enrolment scheme.
     *
     * Anything else is refused rather than coerced: a QR code is attacker-supplied
     * input, and "make it work somehow" is how an app ends up asking a hostile server
     * for its authenticator client.
     */
    private function hostFrom(string $raw): ?string
    {
        $raw = trim($raw);

        if ($raw === '') {
            return null;
        }

        if (str_contains($raw, '://')) {
            $parts = parse_url($raw);

            if (! is_array($parts)) {
                return null;
            }

            $scheme = $parts['scheme'] ?? '';

            // Our own scheme carries the host as a query parameter.
            if ($scheme !== 'https') {
                $query = [];
                parse_str($parts['query'] ?? '', $query);
                $raw = is_string($query['host'] ?? null) ? $query['host'] : '';
            } else {
                $raw = $parts['host'] ?? '';
            }
        }

        // A hostname, and nothing that could smuggle a path, port or credentials.
        return preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/i', $raw) === 1 ? strtolower($raw) : null;
    }

    /**
     * Must be byte-identical to the value sent to the token endpoint later.
     *
     * Which of the two registered URIs a build uses depends on Spike A: whether
     * NativePHP accepts a dotted private-use scheme. The HTTPS App Link is the preferred
     * form (RFC 8252 §7.2 — a private-use scheme can be squatted by another app on the
     * same handset), but it ties a build to one host.
     */
    private function redirectUri(): string
    {
        $scheme = (string) config('nativephp.deeplink_scheme', 'com.cboxid.authenticator');

        return $scheme.':/oauth/callback';
    }

    public function render(): View
    {
        return view('native.connect');
    }
}

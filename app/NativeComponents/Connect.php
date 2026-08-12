<?php

declare(strict_types=1);

namespace App\NativeComponents;

use App\Accounts\AccountContext;
use App\Concerns\NavigatesScreens;
use App\Services\EnrolmentCode;
use App\Services\EnrolmentToken;
use App\Services\HostDiscovery;
use App\Support\RedirectUri;
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
 * This screen ADDS an account. It never assumes it is the first — reaching it with two
 * accounts already connected is ordinary, and the sign-in that follows reserves its own
 * namespace rather than writing over anything.
 */
class Connect extends NativeComponent
{
    use NavigatesScreens;

    public ?string $error = null;

    public bool $working = false;

    public function mount(): void
    {
        // An enrolment deep link may carry the code directly, so an emailed link works
        // on a handset whose camera is unavailable.
        $host = (string) $this->data('host', '');

        if ($host !== '') {
            $token = (string) $this->data('t', '');

            $this->begin(new EnrolmentCode($host, $token !== '' ? $token : null));
        }
    }

    public function scan(): void
    {
        Scanner::scan()->id('connect-host')->scan();
    }

    #[On(CodeScanned::class)]
    public function scanned(string $data): void
    {
        $code = EnrolmentCode::parse($data);

        if ($code === null) {
            $this->error = "That code isn't a Cbox ID address.";

            return;
        }

        $this->begin($code);
    }

    private function begin(EnrolmentCode $code): void
    {
        if ($this->working) {
            return;
        }

        $this->working = true;
        $this->error = null;

        try {
            $host = app(HostDiscovery::class)->fetch($code->host);

            // A courtesy glance, not a check — see EnrolmentToken. Catching an obviously
            // stale code here saves the user a full sign-in that the server would refuse
            // at the end; the server remains the only thing that actually verifies it.
            if ($code->token !== null) {
                app(EnrolmentToken::class)->precheck($code->token);
            }

            $pending = app(AccountContext::class)
                ->beginSignIn($host->issuer, $host->clientId, $code->token);

            // The SYSTEM browser, not an in-app web view. The user has to be able to see
            // a real address bar and a real padlock while typing their password — an
            // app-controlled view can draw whatever it likes, which is precisely the
            // phishing surface a sign-in must not have.
            //
            // The return value is not decoration. `Browser::auth()` reports failure by
            // returning false — including when the native bridge is absent entirely,
            // because the build shipped without the browser plugin. Ignoring it once cost
            // an afternoon: the scanner closed, the screen sat there, and nothing said why.
            $opened = Browser::auth(
                app(AccountContext::class)
                    ->reserved($pending)
                    ->oauth
                    ->authorizeUrl(RedirectUri::current(), $pending->state, $pending->verifier)
            );

            if (! $opened) {
                $this->error = "This device can't open a browser for sign-in.";
            }
        } catch (Throwable $e) {
            $this->error = $e->getMessage();
        } finally {
            $this->working = false;
        }
    }

    public function render(): View
    {
        return view('native.connect');
    }
}

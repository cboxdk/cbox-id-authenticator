<?php

declare(strict_types=1);

namespace App\NativeComponents;

use App\Accounts\AccountContext;
use App\Accounts\AccountStore;
use App\Concerns\NavigatesScreens;
use App\Events\AuthenticationFinished;
use App\Services\AppSecurity;
use App\Services\AuthenticationBrowser;
use App\Services\EnrolmentCode;
use App\Services\EnrolmentCodeRejected;
use App\Services\EnrolmentFailed;
use App\Services\EnrolmentToken;
use App\Services\HostDiscovery;
use App\Services\SupportedHost;
use App\Support\DeepLinkQuery;
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
 * Scan a connection code or open Trusted devices in the system browser.
 * The latter also supports setup using only this phone.
 *
 * This screen ADDS an account. It never assumes it is the first — reaching it with two
 * accounts already connected is ordinary, and the sign-in that follows reserves its own
 * namespace rather than writing over anything.
 */
class Connect extends NativeComponent
{
    use NavigatesScreens;

    public ?string $error = null;

    public string $host = '';

    public ?string $reconnecting = null;

    public bool $working = false;

    public bool $showManual = false;

    public ?string $notice = null;

    public string $signInHost = '';

    private ?string $browserId = null;

    private ?string $pendingState = null;

    public function toggleManual(): void
    {
        $this->showManual = ! $this->showManual;
    }

    /**
     * Whether there is anywhere to go back to.
     *
     * False on first run — Launch reaches this screen with replace(), so there is no
     * entry beneath it and a Back button would be a dead control. True whenever an
     * account already exists, because then the only way here is a deliberate push from
     * the approvals list or Settings, and without a way out the screen is a trap: the
     * scanner is the sole thing on it, and cancelling a scan leaves you standing here.
     */
    public bool $canGoBack = false;

    public function mount(): void
    {
        $this->canGoBack = app(AppSecurity::class)->hasSecrets();

        // An enrolment link carries the code directly — the console renders one beside
        // the QR so a handset whose camera is unavailable, or a user who would rather
        // tap than scan, still gets here.
        //
        // Read off the URI, not from navigation data. The router puts a deep link's
        // query nowhere: it does not parse it into data, and `#^/connect$#` cannot even
        // match a URI carrying one — see App\Support\DeepLinkQuery and the /connect{tail}
        // route. data() is consulted first so an in-app navigation still works.
        $query = DeepLinkQuery::parse($this->nativeRouter?->currentUri());

        $this->reconnecting = is_string($this->data('reconnect')) ? $this->data('reconnect') : null;
        $host = (string) ($this->data('host') ?? $query['host'] ?? '');
        $token = (string) ($this->data('t') ?? $query['t'] ?? '');

        if ($host !== '') {
            $this->begin(new EnrolmentCode($host, $token !== '' ? $token : null));
        }
    }

    public function about(): void
    {
        $this->navigate('/about');
    }

    public function codes(): void
    {
        $this->replace('/codes');
    }

    public function openConsole(): void
    {
        try {
            $url = HostDiscovery::baseUrlFor(trim($this->host)).'/account/devices';
            if (! Browser::open($url)) {
                $this->error = 'Could not open your browser. Open My account → Trusted devices on your Cbox ID host.';
            }
        } catch (Throwable) {
            $this->error = 'Enter a supported Cbox ID address, such as your-company.cboxid.com.';
        }
    }

    public function scan(): void
    {
        if ($this->working) {
            return;
        }
        Scanner::scan()->id('connect-host')->scan();
    }

    #[On(AuthenticationFinished::class)]
    public function authenticationFinished(string $id, string $result): void
    {
        if ($this->browserId === null || ! hash_equals($this->browserId, $id)) {
            return;
        }
        $this->browserId = null;
        $this->working = false;
        if ($result === 'completed') {
            $this->pendingState = null;

            return;
        }
        try {
            $this->cancelPending();
        } catch (Throwable) {
            $this->error = 'Could not finish cleanup. Reopen the app and scan a new code.';

            return;
        }
        $this->notice = $result === 'cancelled' ? 'Sign-in cancelled. Scan the code when you are ready.' : null;
        $this->error = $result === 'cancelled' ? null : 'Sign-in could not finish. Scan a fresh code to try again.';
    }

    private function cancelPending(): void
    {
        if ($this->pendingState !== null) {
            app(AccountContext::class)->cancelSignIn($this->pendingState);
            $this->pendingState = null;
        }
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

        // Refused BEFORE discovery, so an unsupported host is never contacted at all.
        // Asking it for its authenticator document first would tell that server a Cbox ID
        // app had scanned it, and would report the wrong reason when it answered.
        if (! SupportedHost::allows($code->host)) {
            $this->error = SupportedHost::refusal();

            return;
        }

        $existing = $this->reconnecting === null ? null : app(AccountStore::class)->find($this->reconnecting);
        if ($existing !== null && $existing->issuer !== HostDiscovery::baseUrlFor($code->host)) {
            $this->error = 'Scan a fresh code for the account you are reconnecting.';

            return;
        }
        $this->working = true;
        $this->error = null;
        $this->notice = null;
        $this->signInHost = $code->host;

        try {
            $host = app(HostDiscovery::class)->fetch($code->host);

            // A courtesy glance, not a check — see EnrolmentToken. Catching an obviously
            // stale code here saves the user a full sign-in that the server would refuse
            // at the end; the server remains the only thing that actually verifies it.
            if ($code->token !== null) {
                app(EnrolmentToken::class)->precheck($code->token);
            }

            $pending = app(AccountContext::class)
                ->beginSignIn($host->issuer, $host->clientId, $code->token, $existing?->subject);
            $this->pendingState = $pending->state;
            $this->browserId = bin2hex(random_bytes(16));

            // The system authentication session coordinates with the native app lock.
            $opened = app(AuthenticationBrowser::class)->open(
                app(AccountContext::class)
                    ->reserved($pending)
                    ->oauth
                    ->authorizeUrl(RedirectUri::current(), $pending->state, $pending->verifier),
                $this->browserId,
            );

            if (! $opened) {
                $this->cancelPending();
                $this->browserId = null;
                $this->working = false;
                $this->error = "This device can't open a browser for sign-in.";
            }
        } catch (Throwable $e) {
            $this->working = false;
            $this->browserId = null;
            try {
                $this->cancelPending();
            } catch (Throwable) {
                // The indexed reservation remains available for the next cleanup.
            }
            $this->error = ($e instanceof EnrolmentFailed || $e instanceof EnrolmentCodeRejected) ? $e->getMessage()
                : 'Could not connect this account. Open Trusted devices and scan a fresh code.';
        }
    }

    public function render(): View
    {
        return view('native.connect');
    }
}

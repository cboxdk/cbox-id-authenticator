<?php

declare(strict_types=1);

namespace App\NativeComponents;

use App\Accounts\Account;
use App\Accounts\AccountContext;
use App\Accounts\PendingSignIns;
use App\Concerns\NavigatesScreens;
use App\Services\ApiFailure;
use App\Services\DeviceSecurity;
use App\Support\DeepLinkQuery;
use App\Support\RedirectUri;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;
use Throwable;

/**
 * Where the browser hands control back after sign-in.
 *
 * Reached by deep link, so the query string arrives as route data.
 *
 * THE STATE CHECK IS THE LOOKUP
 * -----------------------------
 * The `state` is not compared against a stored value — it is the KEY the attempt is
 * stored under, and {@see PendingSignIns::take()} removes it as it reads.
 * An unknown `state` therefore finds nothing and goes no further, and a callback
 * delivered twice — which the OS's deep-link handling can do — finds nothing the second
 * time. That is a stronger property than the single-slot comparison it replaces, where
 * a second sign-in overwrote the first attempt's verifier.
 */
class OAuthCallback extends NativeComponent
{
    use NavigatesScreens;

    public ?string $error = null;

    /**
     * The callback's query parameters.
     *
     * Read from the URI rather than from navigation data, because the router never puts
     * a deep link's query there — see {@see DeepLinkQuery}. `data()` is still consulted
     * first so an in-app navigation, and any future router that does populate it, keep
     * working.
     *
     * @return array<string, string>
     */
    private function query(): array
    {
        $fromUri = DeepLinkQuery::parse($this->nativeRouter?->currentUri());

        return array_filter([
            'code' => (string) $this->data('code', ''),
            'state' => (string) $this->data('state', ''),
            'error' => (string) $this->data('error', ''),
        ]) + $fromUri;
    }

    public function mount(): void
    {
        $query = $this->query();

        $code = $query['code'] ?? '';
        $state = $query['state'] ?? '';

        // The authorization server reports its own failures here too — a denied consent,
        // an invalid request — and those arrive with no code at all.
        if (($query['error'] ?? '') !== '') {
            if ($state !== '') {
                try {
                    app(AccountContext::class)->cancelSignIn($state);
                } catch (Throwable) {
                    $this->error = 'Could not finish cleanup. Reopen the app and scan a new code.';

                    return;
                }
            }
            $this->error = 'Sign-in was not completed.';

            return;
        }

        if ($code === '' || $state === '') {
            // Name what was missing. "Incomplete" sent us reading the platform's redirect
            // builder, the browser plugin and the OAuth response in turn, when the answer
            // was that the router had dropped the query string before this screen ran.
            $this->error = 'That sign-in link was incomplete (no '
                .($code === '' ? 'code' : 'state').').';

            return;
        }

        $context = app(AccountContext::class);
        $pending = $context->pending()->take($state);

        if ($pending === null) {
            $this->error = 'This sign-in attempt has expired. Scan the code again.';

            return;
        }

        $services = $context->reserved($pending);

        try {
            if (isset($query['iss']) && rtrim($query['iss'], '/') !== rtrim($pending->issuer, '/')) {
                throw new \RuntimeException('The sign-in response came from an unexpected host.');
            }
            $services->oauth->exchange($code, $pending->verifier, RedirectUri::current());
            // Read identity from the authenticated API, never from an unverified ID token.
            $identity = $services->api->identity();
            if ($pending->expectedSubject !== null && $pending->expectedSubject !== $identity['subject']) {
                throw new \RuntimeException('Use the same account when reconnecting.');
            }
            $duplicates = array_filter($context->store()->forIssuer($pending->issuer),
                fn (Account $account): bool => $account->subject === $identity['subject']);
            $services->enrolment->enrol($pending->enrolmentToken);
            $label = $identity['label'];
            $context->store()->put(new Account(
                $pending->accountId, $pending->issuer, Account::labelFor($pending->issuer, $label), $identity['subject'],
            ));
            foreach ($duplicates as $old) {
                $previous = $context->for($old);
                try {
                    if (($device = $previous->enrolment->deviceId()) !== null) {
                        $previous->api->deregisterDevice($device);
                    }
                } catch (Throwable) {
                    // Local key removal still makes this old installation unusable.
                }
                $context->remove($old);
            }
            app(DeviceSecurity::class)->configure(true);

            $this->replace('/approvals', ['connected' => Account::labelFor($pending->issuer, $label)]);
        } catch (Throwable $e) {
            // Nothing was added to the index, so the reserved namespace is unreachable
            // through it. Wipe it rather than leave a DPoP key and a half-set of tokens
            // behind on every failed attempt.
            if ($context->store()->find($pending->accountId) === null) {
                try {
                    if (($device = $services->enrolment->deviceId()) !== null) {
                        $services->api->deregisterDevice($device);
                    }
                    $services->forget();
                } catch (Throwable) {
                    $this->error = 'Could not finish cleanup. Reopen the app and scan a new code.';

                    return;
                }
            }
            $this->error = $e instanceof ApiFailure ? $e->getMessage()
                : 'Could not connect this account. Open Trusted devices and scan a fresh code.';
        }
    }

    public function render(): View
    {
        return view('native.oauth-callback');
    }

    public function retry(): void
    {
        $this->replace('/connect');
    }
}

<?php

declare(strict_types=1);

namespace App\NativeComponents;

use App\Accounts\Account;
use App\Accounts\AccountContext;
use App\Accounts\PendingSignIns;
use App\Concerns\NavigatesScreens;
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
            $services->oauth->exchange($code, $pending->verifier, RedirectUri::current());

            // Enrol BEFORE the account joins the index, so a REFUSED enrolment code is
            // reported here rather than leaving an account that silently never receives
            // prompts. It throws only for that — a refusal by the host.
            //
            // A handset with no push token returns false instead, and the sign-in
            // carries on. That covers someone who tapped "Don't Allow", and it covers a
            // self-hosted installation that cannot push at all. Neither is a broken
            // account: the approvals list is fetched with the OAuth token and refreshes
            // on its own. Settings says plainly which accounts can reach this phone.
            $services->enrolment->enrol($pending->enrolmentToken);

            $context->store()->put(new Account(
                $pending->accountId,
                $pending->issuer,
                Account::labelFor($pending->issuer),
            ));

            $this->replace('/approvals');
        } catch (Throwable $e) {
            // Nothing was added to the index, so the reserved namespace is unreachable
            // through it. Wipe it rather than leave a DPoP key and a half-set of tokens
            // behind on every failed attempt.
            $services->forget();

            $this->error = $e->getMessage();
        }
    }

    public function render(): View
    {
        return view('native.oauth-callback');
    }
}

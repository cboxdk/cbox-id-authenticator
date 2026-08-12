<?php

declare(strict_types=1);

namespace App\NativeComponents;

use App\Accounts\AccountContext;
use App\Accounts\AccountServices;
use App\Concerns\NavigatesScreens;
use Illuminate\View\View;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Attributes\Poll;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Facades\Biometrics;
use Throwable;

/**
 * One approval request, and the decision.
 *
 * THIS SCREEN IS THE SECURITY CONTROL, AND THE BIOMETRIC IS NOT
 * ------------------------------------------------------------
 * `Biometrics::prompt()` returns a bool over the bridge. There is no challenge in, no
 * signature out, no attestation — the plugin exposes exactly one function. So the
 * server receives an HTTP request saying "approved" and has no evidence any biometric
 * check happened, and on a rooted handset the check is a text edit away from being
 * removed. It stops a shoulder-surfer holding an unlocked phone, and nothing more.
 *
 * The actual control is the BINDING MESSAGE: the transaction description the user
 * compares against what the requesting app showed them. That comparison is what defeats
 * approve-the-prompt fatigue, so it is rendered above the fold, before the buttons, and
 * fetched over TLS rather than pushed — a lock screen is readable by anyone holding the
 * handset.
 *
 * The account is in the ROUTE, not inferred. An approval id is unique only within its
 * own host, so asking "which approval is 01J…" without saying whose is a question with
 * more than one answer — and answering it against the wrong account would send a
 * DPoP-bound token to a tenant it was never minted for.
 */
class ApprovalDetail extends NativeComponent
{
    use NavigatesScreens;

    /** @var array<string, mixed>|null */
    public ?array $approval = null;

    public bool $missing = false;

    public bool $working = false;

    public ?string $outcome = null;

    /** Which account this request belongs to, for the header. */
    public ?string $accountLabel = null;

    public function mount(): void
    {
        $this->load();
    }

    /**
     * The account named in the route, or null if it is no longer connected.
     *
     * Signing out on another screen, or a deep link that outlived the account it points
     * at, both land here — and both must read as "this request is gone" rather than
     * throwing.
     */
    private function services(): ?AccountServices
    {
        return app(AccountContext::class)->forId((string) $this->param('account'));
    }

    /**
     * The countdown has to move, and the request can be answered elsewhere — on the web
     * console, or on another enrolled handset — so this also catches it disappearing.
     */
    #[Poll(1000)]
    public function tick(): void
    {
        if ($this->outcome === null && $this->secondsLeft <= 0) {
            $this->outcome = 'expired';
        }
    }

    /**
     * Seconds until the CIBA request expires. Shown so the user knows whether it is
     * still worth answering, rather than tapping Approve into an error.
     */
    #[Computed]
    public function secondsLeft(): int
    {
        $expires = $this->approval['expires_at'] ?? null;

        if (! is_string($expires)) {
            return 0;
        }

        return max(0, strtotime($expires) - time());
    }

    public function approve(): void
    {
        $this->decide(true);
    }

    public function deny(): void
    {
        $this->decide(false);
    }

    /**
     * Deny, and say so loudly.
     *
     * Separate from an ordinary deny because the intent is different: this is the user
     * telling us the request was not theirs. The server can treat that as a risk signal
     * rather than a routine decline — a distinction lost if both send the same thing.
     */
    public function wasNotMe(): void
    {
        $this->decide(false, 'not_me');
    }

    private function decide(bool $approve, ?string $reason = null): void
    {
        if ($this->working || $this->outcome !== null) {
            return;
        }

        // A denial does NOT wait on biometrics. Someone reacting to a prompt they did
        // not expect should be able to shut it down instantly; making them authenticate
        // first only delays the safe action.
        if ($approve && ! Biometrics::prompt()->id('approve-request')->prompt()) {
            return;
        }

        $services = $this->services();

        if ($services === null) {
            $this->missing = true;

            return;
        }

        $this->working = true;

        $id = (string) $this->param('id');

        try {
            $settled = $approve
                ? $services->api->approve($id)
                : $services->api->deny($id);

            // The server collapses expired, already-answered and not-yours into one
            // response on purpose, so a stolen handset cannot probe which ids are real.
            // The app must not try to tell them apart either.
            $this->outcome = $settled
                ? ($approve ? 'approved' : ($reason === 'not_me' ? 'reported' : 'denied'))
                : 'stale';
        } catch (Throwable) {
            $this->outcome = 'failed';
        } finally {
            $this->working = false;
        }
    }

    public function load(): void
    {
        $services = $this->services();

        if ($services === null) {
            $this->missing = true;

            return;
        }

        $this->accountLabel = $services->account->label;

        try {
            $this->approval = $services->api->approval((string) $this->param('id'));
            $this->missing = $this->approval === null;
        } catch (Throwable) {
            $this->missing = true;
        }
    }

    public function render(): View
    {
        return view('native.approval-detail');
    }
}

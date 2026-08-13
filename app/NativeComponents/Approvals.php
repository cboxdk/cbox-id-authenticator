<?php

declare(strict_types=1);

namespace App\NativeComponents;

use App\Accounts\AccountContext;
use App\Concerns\NavigatesScreens;
use Illuminate\View\View;
use Native\Mobile\Attributes\On;
use Native\Mobile\Attributes\Poll;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Events\PushNotification\PushNotificationReceived;
use Throwable;

/**
 * Pending approval requests, across every connected account.
 *
 * Polls as well as listening for pushes. That is not redundancy for its own sake: a
 * push can be dropped, delayed, or silently disabled by the OS, and a CIBA request
 * lives 300 seconds — far too short a window to depend on a delivery channel nobody
 * controls. The poll is what makes the app correct; the push is what makes it prompt.
 *
 * ONE LIST, NOT A SWITCHER
 * ------------------------
 * Every account is fetched on each pass and the results are merged. An account picker
 * would be less code, but a prompt arriving on whichever account is not currently
 * selected would sit unseen until the user happened to switch — and 300 seconds is not
 * long enough for "happened to".
 *
 * EACH ACCOUNT FAILS ALONE
 * ------------------------
 * A host that is unreachable marks itself degraded and the others still render. Letting
 * one failure empty the list would tell someone waiting on an approval that nothing is
 * pending, which is the single worst thing this screen can say.
 */
class Approvals extends NativeComponent
{
    use NavigatesScreens;

    /** @var list<array<string, mixed>> */
    public array $approvals = [];

    /** @var list<string> Labels of accounts whose host could not be reached. */
    public array $degraded = [];

    public function mount(): void
    {
        if (app(AccountContext::class)->store()->isEmpty()) {
            $this->replace('/connect');

            return;
        }

        $this->load();
    }

    /** Returning after answering a prompt must not still show the answered one. */
    public function onResume(): void
    {
        $this->load();
    }

    #[Poll(15000)]
    public function refresh(): void
    {
        $this->load();
    }

    /**
     * A push arrived while this screen was open. Reload rather than trusting the
     * payload: it carries routing only, and everything shown here comes from the API.
     */
    #[On(PushNotificationReceived::class)]
    public function pushed(): void
    {
        $this->load();
    }

    public function open(string $key): void
    {
        // The row key carries both halves because an approval id is only unique within
        // its own host — two tenants can and will mint the same id.
        [$accountId, $id] = array_pad(explode(':', $key, 2), 2, '');

        if ($accountId !== '' && $id !== '') {
            $this->navigate('/approvals/'.$accountId.'/'.$id);
        }
    }

    public function load(): void
    {
        $rows = [];
        $degraded = [];

        foreach (app(AccountContext::class)->all() as $services) {
            try {
                foreach ($services->api->pendingApprovals() as $approval) {
                    $id = $approval['id'] ?? null;

                    if (! is_string($id) || $id === '') {
                        continue;
                    }

                    $rows[] = $approval + [
                        'account_id' => $services->account->id,
                        'account_label' => $services->account->label,
                        'row_key' => $services->account->id.':'.$id,
                    ];
                }
            } catch (Throwable) {
                // Offline, or this one host is unreachable. Say which, and carry on with
                // the rest.
                $degraded[] = $services->account->label;
            }
        }

        $this->degraded = $degraded;

        // Every account unreachable — almost always the handset being offline rather
        // than several hosts failing at once. Keep what is already on screen: replacing
        // it with an empty list reads as "nothing pending", and that is the one wrong
        // thing to tell someone waiting on an approval.
        if ($rows === [] && $degraded !== []) {
            return;
        }

        // Soonest to expire first. Across hosts the only shared ordering that means
        // anything is "how long have I got", and it is what the user is deciding on.
        usort($rows, fn (array $a, array $b): int => $this->expiryOf($a) <=> $this->expiryOf($b));

        $this->approvals = $rows;
    }

    /**
     * @param  array<string, mixed>  $approval
     */
    private function expiryOf(array $approval): int
    {
        $expires = $approval['expires_at'] ?? null;

        // An unparseable or absent expiry sorts last rather than first: it is the row we
        // know least about, and putting it at the top would push a genuinely urgent
        // prompt below the fold.
        return is_string($expires) ? (strtotime($expires) ?: PHP_INT_MAX) : PHP_INT_MAX;
    }

    public function render(): View
    {
        return view('native.approvals');
    }
}

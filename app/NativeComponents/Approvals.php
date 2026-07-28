<?php

declare(strict_types=1);

namespace App\NativeComponents;

use App\Services\CboxApi;
use App\Services\TokenStore;
use Illuminate\View\View;
use Native\Mobile\Attributes\On;
use Native\Mobile\Attributes\Poll;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Events\PushNotification\PushNotificationReceived;
use Throwable;

/**
 * Pending approval requests.
 *
 * Polls as well as listening for pushes. That is not redundancy for its own sake: a
 * push can be dropped, delayed, or silently disabled by the OS, and a CIBA request
 * lives 300 seconds — far too short a window to depend on a delivery channel nobody
 * controls. The poll is what makes the app correct; the push is what makes it prompt.
 */
class Approvals extends NativeComponent
{
    /** @var list<array<string, mixed>> */
    public array $approvals = [];

    public bool $failed = false;

    public function mount(): void
    {
        if (! app(TokenStore::class)->isSignedIn()) {
            $this->replace('/');

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

    public function open(string $id): void
    {
        $this->navigate('/approvals/'.$id);
    }

    public function load(): void
    {
        try {
            $this->approvals = app(CboxApi::class)->pendingApprovals();
            $this->failed = false;
        } catch (Throwable) {
            // Offline, or the host is unreachable. Keep whatever is already on screen —
            // blanking the list would read as "nothing pending", which is the one wrong
            // thing to tell someone waiting on an approval.
            $this->failed = true;
        }
    }

    public function render(): View
    {
        return view('native.approvals');
    }
}

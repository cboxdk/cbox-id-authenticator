<?php

declare(strict_types=1);

namespace App\NativeComponents;

use App\Accounts\AccountContext;
use App\Concerns\NavigatesScreens;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Facades\PushNotifications;
use Throwable;

class Settings extends NativeComponent
{
    use NavigatesScreens;

    public ?string $permission = null;

    public bool $working = false;

    public ?string $notice = null;

    /** @var list<array{id: string, label: string, issuer: string}> */
    public array $accounts = [];

    public function mount(): void
    {
        $this->permission = PushNotifications::checkPermission();
        $this->loadAccounts();
    }

    public function goAddAccount(): void
    {
        $this->navigate('/connect');
    }

    /**
     * Re-register EVERY account with a freshly read token.
     *
     * The recovery path when notifications were refused and later enabled in system
     * settings — nothing else would prompt the app to notice, since no rotation event
     * fires for a permission change. It has to cover every account: the token is the
     * handset's, but the device row is per host, and re-syncing only one would leave the
     * others pushing to a token the OS no longer honours.
     */
    public function resync(): void
    {
        $this->working = true;

        $token = PushNotifications::getToken();
        $failed = [];

        foreach (app(AccountContext::class)->all() as $services) {
            if (! $services->enrolment->register($token)) {
                $failed[] = $services->account->label;
            }
        }

        $this->permission = PushNotifications::checkPermission();
        $this->notice = $failed === []
            ? 'Notifications re-synced.'
            : 'Could not re-sync: '.implode(', ', $failed);
        $this->working = false;
    }

    /**
     * Sign out of one account, leaving the others untouched.
     *
     * Deregister first so no further pushes are sent, then revoke the refresh token,
     * then wipe local state. The reverse order leaves an unreachable device row behind
     * if the app is killed part-way — the server would keep pushing to a handset that
     * has already forgotten how to answer.
     */
    public function signOut(string $accountId): void
    {
        if ($this->working) {
            return;
        }

        $this->working = true;

        $context = app(AccountContext::class);
        $account = $context->store()->find($accountId);

        if ($account === null) {
            $this->working = false;

            return;
        }

        try {
            $services = $context->for($account);
            $device = $services->enrolment->deviceId();

            if ($device !== null) {
                $services->api->deregisterDevice($device);
            }
        } catch (Throwable) {
            // Best effort, and the revoke inside remove() is too. Wiping the DPoP key
            // makes every surviving token unusable regardless, since each request must
            // carry a proof signed by it.
        }

        $context->remove($account);

        $this->loadAccounts();
        $this->working = false;

        if ($this->accounts === []) {
            $this->replace('/connect');
        }
    }

    private function loadAccounts(): void
    {
        $this->accounts = array_map(
            fn ($account): array => [
                'id' => $account->id,
                'label' => $account->label,
                'issuer' => $account->issuer,
            ],
            app(AccountContext::class)->store()->all(),
        );
    }

    public function render(): View
    {
        return view('native.settings');
    }
}

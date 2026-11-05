<?php

declare(strict_types=1);

namespace App\NativeComponents;

use App\Accounts\AccountContext;
use App\Concerns\NavigatesScreens;
use App\Services\AppSecurity;
use App\Services\DevicePush;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Facades\Browser;
use Native\Mobile\Facades\System;
use Throwable;

class Settings extends NativeComponent
{
    use NavigatesScreens;

    public ?string $permission = null;

    public bool $working = false;

    public ?string $notice = null;

    public ?string $removing = null;

    /** @var list<array{id: string, label: string, issuer: string}> */
    public array $accounts = [];

    public function mount(): void
    {
        $this->permission = app(DevicePush::class)->permission();
        $this->loadAccounts();
    }

    public function onResume(): void
    {
        $this->mount();
    }

    public function account(string $id): void
    {
        $this->navigate('/account/'.$id);
    }

    public function notificationSettings(): void
    {
        System::appSettings();
    }

    public function backup(): void
    {
        $this->navigate('/codes/backup');
    }

    public function about(): void
    {
        $this->navigate('/about');
    }

    public function manage(string $id): void
    {
        $account = app(AccountContext::class)->store()->find($id);
        if ($account !== null && ! Browser::open($account->issuer.'/account')) {
            $this->notice = 'Could not open your browser. Open your Cbox ID host to manage your account.';
        }
    }

    public function requestRemoval(string $id): void
    {
        $this->removing = $id;
    }

    public function cancelRemoval(): void
    {
        $this->removing = null;
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

        if (app(DevicePush::class)->permission() === 'not_determined') {
            app(DevicePush::class)->requestPermission();
        }
        $token = app(DevicePush::class)->token();
        $failed = [];

        foreach (app(AccountContext::class)->all() as $services) {
            if (! $services->enrolment->register($token)) {
                $failed[] = $services->account->label;
            }
        }

        $this->permission = app(DevicePush::class)->permission();
        $this->notice = $failed === []
            ? 'Notifications re-synced.'
            : __('Could not re-sync: :accounts', ['accounts' => implode(', ', $failed)]);
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

        try {
            $context->remove($account);
        } catch (Throwable) {
            $this->working = false;
            $this->notice = 'Could not finish removing the saved account. Unlock your phone and try again.';

            return;
        }
        $this->removing = null;
        $this->loadAccounts();
        $this->working = false;

        app(AppSecurity::class)->refresh();
        if (! app(AppSecurity::class)->hasSecrets()) {
            $this->replace('/codes');
        }
    }

    private function loadAccounts(): void
    {
        $context = app(AccountContext::class);

        $this->accounts = array_map(
            fn ($account): array => [
                'id' => $account->id,
                'label' => $account->label,
                'issuer' => $account->issuer,
                // Whether this host ever registered the handset. A device id exists only
                // after a successful POST /devices, so its absence is the honest answer
                // to "will I be alerted for this account" — and the answer is no,
                // whether because notifications were declined or because the host has no
                // push transport at all. Saying "On" for every account when the switch
                // is per host is how someone waits for a prompt that is never coming.
                'notifies' => $this->permission === 'granted' && $context->for($account)->enrolment->hasPushToken(),
            ],
            $context->store()->all(),
        );
    }

    public function render(): View
    {
        return view('native.settings');
    }
}

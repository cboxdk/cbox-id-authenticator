<?php

declare(strict_types=1);

namespace App\NativeComponents;

use App\Services\DeviceEnrolment;
use App\Services\DpopKey;
use App\Services\OAuthClient;
use App\Services\TokenStore;
use App\Support\InstallId;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Facades\PushNotifications;
use Throwable;

class Settings extends NativeComponent
{
    public ?string $permission = null;

    public bool $working = false;

    public ?string $notice = null;

    public function mount(): void
    {
        $this->permission = PushNotifications::checkPermission();
    }

    /**
     * Re-register with a freshly read token. The recovery path when notifications were
     * refused and later enabled in system settings — nothing else would prompt the app
     * to notice, since no rotation event fires for a permission change.
     */
    public function resync(): void
    {
        $this->working = true;

        $ok = app(DeviceEnrolment::class)->enrol();

        $this->permission = PushNotifications::checkPermission();
        $this->notice = $ok ? 'Notifications re-synced.' : 'Could not re-sync. Check your connection.';
        $this->working = false;
    }

    /**
     * Sign out, in an order that matters.
     *
     * Deregister first so no further pushes are sent, then revoke the refresh token,
     * then wipe local state. The reverse order leaves an unreachable device row behind
     * if the app is killed part-way — the server would keep pushing to a handset that
     * has already forgotten how to answer.
     */
    public function signOut(): void
    {
        $this->working = true;

        try {
            app(OAuthClient::class)->revoke();
        } catch (Throwable) {
            // Best effort. Wiping the key below makes the token unusable anyway, since
            // every request must carry a proof signed by it.
        }

        app(TokenStore::class)->forget();
        app(DpopKey::class)->forget();
        InstallId::forget();

        $this->working = false;
        $this->replace('/');
    }

    public function render(): View
    {
        return view('native.settings');
    }
}

<?php

declare(strict_types=1);

namespace App\Providers;

use App\Accounts\AccountContext;
use App\Services\DeviceSecurity;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;
use Native\Mobile\Events\PushNotification\TokenGenerated;
use Throwable;

/**
 * Keeps the server's copy of the push token current.
 *
 * Registered as an ordinary Laravel listener rather than an `#[On]` attribute on a
 * screen, and that distinction matters: `#[On]` listeners only fire while their screen
 * is on the stack, so a rotation arriving on any other screen — or during a cold start
 * before a screen mounts — would be silently missed. A missed rotation means the server
 * pushes to a dead token, the device trips its circuit breaker, and the user simply
 * stops getting approval prompts with nothing on screen to explain it.
 */
class PushTokenServiceProvider extends ServiceProvider
{
    public function boot(Dispatcher $events): void
    {
        $events->listen(TokenGenerated::class, function (TokenGenerated $event): void {
            if (! $this->app->make(DeviceSecurity::class)->pushAvailable()) {
                return;
            }
            // Every account, not just one. The token belongs to the handset, but the
            // device row belongs to a host — re-registering with only one of them would
            // leave every other account pushing to a token the OS no longer honours,
            // and the user would simply stop getting prompts from those tenants with
            // nothing on screen to explain it.
            try {
                $accounts = $this->app->make(AccountContext::class)->all();
            } catch (Throwable) {
                // Keychain may be locked during delivery. Foreground re-sync retries.
                return;
            }
            foreach ($accounts as $services) {
                // Nothing to register against until this account has signed in; the
                // callback screen enrols explicitly once it has.
                try {
                    if ($services->isSignedIn()) {
                        $services->enrolment->register($event->token);
                    }
                } catch (Throwable) {
                    // A single unavailable account must not prevent other rotations.
                }
            }
        });
    }
}

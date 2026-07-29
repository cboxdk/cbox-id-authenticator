<?php

declare(strict_types=1);

namespace App\NativeComponents;

use App\Concerns\NavigatesScreens;
use App\Services\DeviceEnrolment;
use App\Services\OAuthClient;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;
use Throwable;

/**
 * Where the browser hands control back after sign-in.
 *
 * Reached by deep link, so the query string arrives as route data. The `state` check
 * happens inside {@see OAuthClient::exchange()} and is not optional: without it, any
 * app or page able to open this scheme could feed the app an authorization code of its
 * choosing.
 */
class OAuthCallback extends NativeComponent
{
    use NavigatesScreens;

    public ?string $error = null;

    public function mount(): void
    {
        $code = (string) $this->data('code', '');
        $state = (string) $this->data('state', '');

        // The authorization server reports its own failures here too — a denied consent,
        // an invalid request — and those arrive with no code at all.
        $serverError = (string) $this->data('error', '');

        if ($serverError !== '') {
            $this->error = 'Sign-in was not completed.';

            return;
        }

        if ($code === '' || $state === '') {
            $this->error = 'That sign-in link was incomplete.';

            return;
        }

        try {
            $scheme = (string) config('nativephp.deeplink_scheme', 'com.cboxid.authenticator');

            app(OAuthClient::class)->exchange($code, $state, $scheme.':/oauth/callback');

            // Enrol immediately. A signed-in app that never registered its push token is
            // indistinguishable, from the user's side, from one that simply never gets
            // prompts — and they would have no way to tell why.
            app(DeviceEnrolment::class)->enrol();

            $this->replace('/approvals');
        } catch (Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    public function render(): View
    {
        return view('native.oauth-callback');
    }
}

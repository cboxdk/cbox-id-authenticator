<?php

declare(strict_types=1);

namespace App\NativeComponents;

use App\Accounts\AccountStore;
use App\Concerns\NavigatesScreens;
use App\Services\AppSecurity;
use App\Totp\TotpStore;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

/**
 * Decides where a cold start lands, and nothing else.
 *
 * The connect screen used to do this, redirecting to the approvals list whenever a
 * refresh token existed. That is what made adding a second account impossible: the only
 * route to the scanner bounced away before it could render. Separating "where do I go"
 * from "add an account" is what unblocks it.
 *
 * Renders a bare background rather than a spinner. The decision is a keychain read —
 * fast enough that a spinner would flash rather than reassure.
 */
class Launch extends NativeComponent
{
    use NavigatesScreens;

    public ?string $error = null;

    public function mount(): void
    {
        try {
            $empty = app(AccountStore::class)->isEmpty();
            app(AppSecurity::class)->refresh();
            $this->replace($empty && ! app(TotpStore::class)->isEmpty() ? '/codes' : '/approvals');
        } catch (\Throwable) {
            $this->error = 'Your saved accounts could not be read. Unlock your phone and try again. Nothing has been removed.';
        }
    }

    public function render(): View
    {
        return view('native.launch');
    }
}

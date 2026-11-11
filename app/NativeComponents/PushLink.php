<?php

declare(strict_types=1);

namespace App\NativeComponents;

use App\Accounts\AccountContext;
use App\Concerns\NavigatesScreens;
use App\Support\DeepLinkQuery;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

/** Push data selects an existing local account; it can never supply a network host. */
class PushLink extends NativeComponent
{
    use NavigatesScreens;

    public function mount(): void
    {
        $install = (string) $this->param('install');
        $request = DeepLinkQuery::cleanParam((string) $this->param('id'));
        $security = $this->param('id') === null;
        if (preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/i', $install) === 1 && ($security || preg_match('/^(?:[a-z]{1,16}_)?[0-9A-HJKMNP-TV-Z]{26}$/i', $request) === 1)) {
            foreach (app(AccountContext::class)->all() as $services) {
                if (hash_equals($services->installId->get(), $install)) {
                    $this->replace($security ? '/account/'.$services->account->id : '/approvals/'.$services->account->id.'/'.$request);

                    return;
                }
            }
        }
        $this->replace('/approvals');
    }

    public function render(): View
    {
        return view('native.launch');
    }
}

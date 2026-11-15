<?php

declare(strict_types=1);

namespace App\NativeComponents;

use App\Accounts\AccountContext;
use App\Concerns\NavigatesScreens;
use App\Support\DeepLinkQuery;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;
use Throwable;

/**
 * `https://login.cboxid.com/authenticator/approve/{id}` — the Universal Link behind the
 * sign-in page's "Open Cbox ID app" button — and the same path by the app's own scheme.
 *
 * The link names a request and no account: the page that offers it does not know which
 * of this phone's accounts the request belongs to, and an https link is answered by
 * whichever host it points at, not by the one that minted the request. So each account
 * is asked, over its own DPoP-bound session, whether the request is its. A host answers
 * only for its own subject, so a request id from somebody else's sign-in finds nothing
 * here. Nothing in the link is trusted beyond its shape.
 */
class ApprovalLink extends NativeComponent
{
    use NavigatesScreens;

    public bool $notFound = false;

    public bool $unreachable = false;

    public function mount(): void
    {
        $this->notFound = false;
        $this->unreachable = false;
        // A bare ULID, or a v3 public id (`apr_01J…`) carrying one, as push links accept.
        $id = DeepLinkQuery::cleanParam((string) $this->param('id'));
        if (preg_match('/^((?:[a-z]{1,16}_)?)([0-9A-HJKMNP-TV-Z]{26})$/i', $id, $parts) !== 1) {
            $this->notFound = true;

            return;
        }
        $id = strtolower($parts[1]).strtoupper($parts[2]);

        $accounts = app(AccountContext::class)->all();
        if ($accounts === []) {
            $this->replace('/');

            return;
        }

        foreach ($accounts as $services) {
            try {
                if ($services->api->approval($id) !== null) {
                    // `/link` tells the detail screen to say "go back to your browser"
                    // when it is done, instead of only "Approved".
                    $this->replace('/approvals/'.$services->account->id.'/'.$id.'/link');

                    return;
                }
            } catch (Throwable) {
                $this->unreachable = true;
            }
        }

        $this->notFound = true;
    }

    public function render(): View
    {
        return view('native.approval-link');
    }
}

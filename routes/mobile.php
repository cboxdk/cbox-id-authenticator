<?php

declare(strict_types=1);

use App\Layouts\AppLayout;
use App\NativeComponents\About;
use App\NativeComponents\AccountDetail;
use App\NativeComponents\AddCode;
use App\NativeComponents\ApprovalDetail;
use App\NativeComponents\Approvals;
use App\NativeComponents\CodeBackup;
use App\NativeComponents\CodeDetail;
use App\NativeComponents\Codes;
use App\NativeComponents\Connect;
use App\NativeComponents\Launch;
use App\NativeComponents\Licenses;
use App\NativeComponents\OAuthCallback;
use App\NativeComponents\PushLink;
use App\NativeComponents\Settings;
use Illuminate\Support\Facades\Route;

/*
 * Native screens. These routes are also the DEEP-LINK surface: with
 * NATIVEPHP_DEEPLINK_SCHEME set, `com.cboxid.authenticator://approvals/…` opens the app
 * straight at that screen, including from a cold start. That is how a notification tap
 * reaches the right approval.
 *
 * HOW A DEEP LINK BECOMES A ROUTE. For a private-use scheme the OS gives no authority,
 * so the native shell turns the URL's HOST into the first path segment
 * (DeepLinkRouter.swift): `scheme://connect?host=x` resolves to `/connect?host=x`, while
 * `scheme:/oauth/callback?code=…` — one slash, no authority — resolves to its path
 * unchanged. `/connect` therefore has to exist as a route, or the emailed enrolment link
 * the connect screen advertises lands nowhere. It previously did not exist.
 *
 * The push payload is untrusted routing data — the detail screen re-fetches the
 * approval over TLS and renders only what the server returns.
 */

// Where a cold start lands. Decides between first-run and the approvals list; see
// Launch for why that decision cannot live on the connect screen.
Route::native('/', Launch::class);

// Add an account. Reached from Settings, from the approvals list, or by an enrolment
// deep link — never as an implicit redirect, so it still renders with accounts present.
Route::native('/connect', Connect::class)->layout(AppLayout::class);

/*
 * The same screen with a query, for the same reason /oauth/{tail} exists below: the
 * router matches a URI verbatim and `#^/connect$#` cannot survive a `?`.
 *
 * The enrolment link from the console is `…://connect?host=…&t=…`, which is the whole
 * point of a link rather than a scan — so this is not an edge case either. `{tail}`
 * widens to `[^/]+` and there is no separator, giving `#^/connect(?P<tail>[^/]+)$#`:
 * it matches `/connect?host=…` and nothing else. A bare /connect still takes the exact
 * route above, and /approvals is untouched.
 */
Route::native('/connect{tail}', Connect::class)->layout(AppLayout::class);

// The OAuth redirect target. Registered on the client as both this scheme and an
// HTTPS App Link; multi-account settles the choice on the scheme, since an App Link is
// bound to a single host.
Route::native('/oauth/callback', OAuthCallback::class);

/*
 * The same screen, again, and it is not a duplicate.
 *
 * NativeRouter::resolve() matches a URI verbatim: exact key first, then `#^pattern$#`
 * with `{param}` widened to `[^/]+`. Nothing splits off a query string. So the callback
 * this app actually receives —
 *
 *     /oauth/callback?code=…&state=…&iss=…
 *
 * — matches neither form, resolves to null, and the router exits to the web view instead
 * of reaching the screen. The OAuth callback is the one URI in this app guaranteed to
 * carry a query, so that is not an edge case; it is the flow.
 *
 * `{tail}` matches `callback?code=…` in one greedy segment, which gets the screen
 * mounted. OAuthCallback then reads the query off the router's own URI — see
 * App\Support\DeepLinkQuery. Registered second so a bare /oauth/callback still takes the
 * exact route above.
 *
 * Remove this the day the router learns about query strings, and not before.
 */
Route::native('/oauth/{tail}', OAuthCallback::class);

/*
 * The signed-in screens, under shared chrome.
 *
 * AppLayout gives them the tab bar and the nav bar. It also takes over the safe areas —
 * the framework frees whichever edges the bars do not own — so none of these three
 * carries a `safe-area` class of its own. Adding one back insets the content twice.
 *
 * Launch and OAuthCallback are transient screens without chrome.
 */
Route::nativeGroup(AppLayout::class, function (): void {
    Route::native('/approvals', Approvals::class);

    // The account is in the path because an approval id is unique only within its own host.
    Route::native('/approvals/{account}/{id}', ApprovalDetail::class);

    Route::native('/settings', Settings::class);
    // Shared flows stay in the tab that opened them, including its back stack.
    Route::native('/settings/backup', CodeBackup::class);
    Route::native('/settings/connect', Connect::class);
});

// Version 2 push route: installation maps locally to exactly one account.
Route::native('/request/{install}/{id}', PushLink::class);
Route::native('/security/{install}', PushLink::class);
// Old queued notifications safely open the inbox.
Route::native('/approvals/{legacy}', Approvals::class)->layout(AppLayout::class);
Route::native('/about', About::class)->layout(AppLayout::class);

Route::native('/account/{id}', AccountDetail::class)->layout(AppLayout::class);

Route::native('/licenses', Licenses::class)->layout(AppLayout::class);

Route::native('/codes', Codes::class)->layout(AppLayout::class);
Route::native('/codes/add', AddCode::class)->layout(AppLayout::class);
Route::native('/codes/backup', CodeBackup::class)->layout(AppLayout::class);
Route::native('/codes/detail/{id}', CodeDetail::class)->layout(AppLayout::class);

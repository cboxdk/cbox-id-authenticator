<?php

declare(strict_types=1);

use App\NativeComponents\ApprovalDetail;
use App\NativeComponents\Approvals;
use App\NativeComponents\Connect;
use App\NativeComponents\Launch;
use App\NativeComponents\OAuthCallback;
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
Route::native('/connect', Connect::class);

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

Route::native('/approvals', Approvals::class);

// The account is in the path because an approval id is unique only within its own host.
Route::native('/approvals/{account}/{id}', ApprovalDetail::class);

Route::native('/settings', Settings::class);

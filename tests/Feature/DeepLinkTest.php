<?php

declare(strict_types=1);

use App\NativeComponents\Approvals;
use App\NativeComponents\Connect;
use App\NativeComponents\OAuthCallback;
use App\NativeComponents\Settings;
use App\Support\DeepLinkQuery;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Edge\NativeRouter;

/**
 * The router has no notion of a query string, and deep links are this app's entire
 * re-entry surface — the OAuth callback and every notification tap. These pin both
 * halves of that gap and the workarounds standing in it.
 */
beforeEach(function (): void {
    require base_path('routes/mobile.php');
});

it('reaches the callback screen whether or not the uri carries a query', function (): void {
    // resolve() matches `#^/oauth/callback$#`, and the `$` cannot survive a query
    // string. Without the second registration the callback screen is never reached and
    // the router exits to the web view instead.
    expect(NativeRouter::resolve('/oauth/callback')['class'] ?? null)->toBe(OAuthCallback::class)
        ->and(NativeRouter::resolve('/oauth/callback?code=abc&state=xyz')['class'] ?? null)
        ->toBe(OAuthCallback::class);
});

it('recovers the callback query from the uri the router recorded', function (): void {
    $query = DeepLinkQuery::parse('/oauth/callback?code=abc&state=xyz&iss=https%3A%2F%2Fcbox.cboxid.com');

    expect($query['code'])->toBe('abc')
        ->and($query['state'])->toBe('xyz')
        ->and($query['iss'])->toBe('https://cbox.cboxid.com');
});

it('treats a uri with no query as having none', function (string $uri): void {
    expect(DeepLinkQuery::parse($uri))->toBe([]);
})->with(['/oauth/callback', '/', '']);

it('survives the router handing it nothing at all', function (): void {
    expect(DeepLinkQuery::parse(null))->toBe([]);
});

it('strips the query a greedy route parameter swallowed', function (): void {
    // `{id}` widens to `[^/]+`, which does not stop at a `?`. Asking the server for
    // `01J…?iss=…` matches nothing, so a live request would render as gone.
    expect(DeepLinkQuery::cleanParam('01JABC?iss=https://cbox.cboxid.com'))->toBe('01JABC')
        ->and(DeepLinkQuery::cleanParam('01JABC'))->toBe('01JABC');
});

it('still routes an approval deep link to the right screen and account', function (): void {
    $resolved = NativeRouter::resolve('/approvals/01ACCOUNT/01APPROVAL?iss=x');

    expect($resolved['params']['account'] ?? null)->toBe('01ACCOUNT')
        // The id arrives polluted — proof the cleaning in ApprovalDetail is load-bearing
        // rather than defensive.
        ->and($resolved['params']['id'] ?? null)->toBe('01APPROVAL?iss=x');
});

it('names what was missing when a callback arrives without its code', function (): void {
    fakeSecureStorage();

    Native\Mobile\Testing\Native::test(OAuthCallback::class)
        ->assertSet('error', 'That sign-in link was incomplete (no code).');
});

it('reaches the connect screen from an enrolment link carrying a query', function (): void {
    // `…://connect?host=…&t=…` is the console's tap-to-enrol link, so a query here is
    // the norm rather than an edge. Without /connect{tail} it resolves to nothing and
    // the app simply opens on whatever screen it was already showing.
    expect(NativeRouter::resolve('/connect')['class'] ?? null)
        ->toBe(Connect::class)
        ->and(NativeRouter::resolve('/connect?host=cboxid.com&t=abc')['class'] ?? null)
        ->toBe(Connect::class);
});

it('does not let the connect pattern swallow other screens', function (): void {
    // `#^/connect(?P<tail>[^/]+)$#` — no separator, so it matches /connect plus
    // something and nothing else.
    expect(NativeRouter::resolve('/approvals')['class'] ?? null)
        ->toBe(Approvals::class)
        ->and(NativeRouter::resolve('/settings')['class'] ?? null)
        ->toBe(Settings::class);
});

it('starts enrolment from the link rather than waiting for a scan', function (): void {
    fakeSecureStorage();
    Http::fake();

    // Reached the way the OS delivers it: the query lives on the URI, not in data.
    Native\Mobile\Testing\Native::visit('/connect?host=id.acme.dk&t=abc')
        // Refused for the host, which proves the link was READ — an unparsed link does
        // nothing at all and leaves the screen blank, which is the reported symptom.
        ->assertSee('connects to cboxid.com accounts only');
});

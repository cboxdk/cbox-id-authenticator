<?php

declare(strict_types=1);

use App\Accounts\Account;
use App\Accounts\AccountContext;
use App\Accounts\AccountStore;
use App\NativeComponents\ApprovalLink;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Edge\NativeRouter;
use Native\Mobile\Testing\Native;

/**
 * "Open Cbox ID app" on a phone's sign-in page is a Universal Link
 * (`https://login.cboxid.com/authenticator/approve/{id}`). It names a request and no
 * account, and the host it points at need not be the one that minted the request.
 */
const LINKED_REQUEST = '01JD2Z7Q8M3K4N5P6R7S8T9V0W';

beforeEach(function (): void {
    require base_path('routes/mobile.php');
});

it('routes the universal link and the scheme fallback to the link screen', function (): void {
    expect(NativeRouter::resolve('/authenticator/approve/'.LINKED_REQUEST)['class'] ?? null)->toBe(ApprovalLink::class)
        ->and(NativeRouter::resolve('/approvals/01ACC/'.LINKED_REQUEST.'/link')['params']['via'] ?? null)->toBe('link');
});

it('opens the request on the account it belongs to', function (): void {
    fakeSecureStorage();
    anAccount();
    $other = new Account('01OTHER', 'https://acme.cboxid.com', 'bob');
    app(AccountStore::class)->put($other);
    $services = app(AccountContext::class)->for($other);
    $services->oauth->rememberClientId('cid_b');
    $services->tokens->put('access-b', 'refresh-b', 900);
    Http::fake([
        'cbox.cboxid.com/*' => Http::response(['error' => 'not_found'], 404),
        'acme.cboxid.com/*' => Http::response(['data' => ['id' => LINKED_REQUEST]]),
    ]);

    Native::test(ApprovalLink::class, ['id' => strtolower(LINKED_REQUEST)])
        ->assertReplacedWith('/approvals/01OTHER/'.LINKED_REQUEST.'/link');
});

it('says so when no account on this phone has the request', function (): void {
    fakeSecureStorage();
    anAccount();
    Http::fake(['*' => Http::response(['error' => 'not_found'], 404)]);

    Native::test(ApprovalLink::class, ['id' => LINKED_REQUEST])
        ->assertSet('notFound', true)
        ->assertSee('No request for this phone')
        ->assertNoNavigation();
});

it('offers a retry when a host could not be reached', function (): void {
    fakeSecureStorage();
    anAccount();
    Http::fake(['*' => Http::response('', 503)]);

    Native::test(ApprovalLink::class, ['id' => LINKED_REQUEST])
        ->assertSet('unreachable', true)
        ->assertSee('Try again');
});

it('ignores a link that is not a request id and asks no host', function (): void {
    fakeSecureStorage();
    anAccount();
    Http::fake();

    Native::test(ApprovalLink::class, ['id' => '../settings'])->assertSet('notFound', true);
    Http::assertNothingSent();
});

it('sends a phone with no accounts to first run', function (): void {
    fakeSecureStorage();

    Native::test(ApprovalLink::class, ['id' => LINKED_REQUEST])->assertReplacedWith('/');
});

it('accepts a v3 public request id', function (): void {
    fakeSecureStorage();
    anAccount();
    Http::fake(['*' => Http::response(['data' => ['id' => 'apr_'.LINKED_REQUEST]])]);

    Native::test(ApprovalLink::class, ['id' => 'apr_'.LINKED_REQUEST])
        ->assertReplacedWith('/approvals/01ACC/apr_'.LINKED_REQUEST.'/link');
});

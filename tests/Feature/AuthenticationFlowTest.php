<?php

declare(strict_types=1);

use App\Accounts\AccountContext;
use App\Accounts\AccountStore;
use App\Events\AuthenticationFinished;
use App\NativeComponents\Connect;
use App\NativeComponents\Launch;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;

function fakeAuthenticationHost(): void
{
    Http::fake(['*/.well-known/cbox-authenticator' => Http::response([
        'issuer' => 'https://cbox.cboxid.com', 'client_id' => 'cid_a', 'api_version' => 2,
    ])]);
}

function authenticationSessionId(): string
{
    $calls = array_values(array_filter(Native::fakeBridge()->calls, fn (array $c): bool => $c['method'] === 'AuthenticatorSecurity.OpenAuthentication'));

    return $calls[0]['params']['id'];
}

it('keeps a single browser flow busy until its matching completion and cleans up cancellation', function (): void {
    $storage = fakeSecureStorage();
    $existing = anAccount();
    $before = $storage->getArrayCopy();
    fakeAuthenticationHost();
    Native::fakeBridge()->respondTo('AuthenticatorSecurity.OpenAuthentication', ['success' => true]);
    $screen = Native::test(Connect::class)->call('scanned', 'cbox.cboxid.com')
        ->assertSet('working', true)->assertSee('Continue signing in');
    $id = authenticationSessionId();
    $screen->call('scanned', 'cbox.cboxid.com');
    Http::assertSentCount(1);
    $screen->emitNative(AuthenticationFinished::class, ['id' => 'wrong', 'result' => 'cancelled'])
        ->assertSet('working', true);
    $screen->emitNative(AuthenticationFinished::class, ['id' => $id, 'result' => 'cancelled'])
        ->assertSet('working', false)->assertSet('error', null)->assertSee('Scan the code');
    expect($storage->getArrayCopy())->toBe($before);
    expect(app(AccountStore::class)->find($existing->id))->not->toBeNull();
    $screen->call('scanned', 'cbox.cboxid.com')->assertSet('working', true);
    Http::assertSentCount(2);
});

it('removes reserved secrets when the native session cannot start', function (): void {
    $storage = fakeSecureStorage();
    fakeAuthenticationHost();
    Native::fakeBridge()->respondTo('AuthenticatorSecurity.OpenAuthentication', ['success' => false]);
    Native::test(Connect::class)->call('scanned', 'cbox.cboxid.com')
        ->assertSet('working', false)->assertSet('error', "This device can't open a browser for sign-in.");
    expect($storage->getArrayCopy())->toBe([]);
});

it('preserves the OAuth attempt for the callback on browser success', function (): void {
    $storage = fakeSecureStorage();
    fakeAuthenticationHost();
    Native::fakeBridge()->respondTo('AuthenticatorSecurity.OpenAuthentication', ['success' => true]);
    $screen = Native::test(Connect::class)->call('scanned', 'cbox.cboxid.com');
    $state = array_key_first(json_decode($storage['cboxid.pending'], true));
    $screen->emitNative(AuthenticationFinished::class, ['id' => authenticationSessionId(), 'result' => 'completed'])
        ->assertSet('working', false)->assertSet('error', null);
    expect(app(AccountContext::class)->pending()->take($state))->not->toBeNull();
});

it('cleans up denied consent and provides a direct retry route', function (): void {
    $storage = fakeSecureStorage();
    $pending = app(AccountContext::class)->beginSignIn('https://cbox.cboxid.com', 'cid_a');
    Native::visit('/oauth/callback?error=access_denied&state='.$pending->state)
        ->assertSee('Sign-in was not completed.')->call('retry')->assertReplacedWith('/connect');
    expect($storage->getArrayCopy())->toBe([]);
});

it('opens Cbox requests for connected users and offers both workflows for new users', function (): void {
    fakeSecureStorage();
    Native::visit('/approvals')->assertSee('Start with Cbox ID')->assertSee('Also need verification codes?')
        ->call('addCode')->assertNavigatedTo('/codes/add');
    anAccount();
    Native::test(Launch::class)->assertReplacedWith('/approvals');
});

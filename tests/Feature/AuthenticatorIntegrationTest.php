<?php

declare(strict_types=1);

use App\Accounts\Account;
use App\Accounts\AccountContext;
use App\Accounts\AccountStore;
use App\NativeComponents\About;
use App\NativeComponents\Connect;
use App\NativeComponents\Licenses;
use App\NativeComponents\OAuthCallback;
use App\NativeComponents\Settings;
use App\Services\ApiFailure;
use App\Services\DeviceSecurity;
use App\Services\EnrolmentFailed;
use App\Services\HostDiscovery;
use App\Services\SecureVault;
use App\Services\SupportedHost;
use App\Services\TokenStore;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Native\Mobile\Testing\Native;

beforeEach(fn () => Http::preventStrayRequests());

it('enrols without Firebase and never passes an APNs fallback token to the FCM host', function (): void {
    fakeSecureStorage();
    Native::fakeBridge()
        ->respondTo('AuthenticatorSecurity.State', ['unlocked' => true, 'generation' => 0, 'foreground' => true, 'pushAvailable' => false])
        ->respondTo('PushNotification.GetToken', ['token' => 'old-apns-token'])
        ->respondTo('PushNotification.CheckPermission', ['status' => 'granted']);
    $context = app(AccountContext::class);
    $pending = $context->beginSignIn('https://cbox.cboxid.com', 'cid', 'qr-code');
    Http::fake([
        '*/oauth/token' => Http::response(['access_token' => 'access', 'refresh_token' => 'refresh', 'expires_in' => 900]),
        '*/authenticator/me' => Http::response(['data' => ['subject' => 'alice', 'label' => 'alice@cbox.dk']]),
        '*/devices' => Http::response(['data' => ['id' => 'device-1']], 201),
    ]);
    Native::test(OAuthCallback::class, data: ['state' => $pending->state, 'code' => 'code'])->assertReplacedWith('/approvals');
    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/devices') && $request['push_token'] === null);
    Native::fakeBridge()->assertNotCalled('PushNotification.GetToken');
    Native::fakeBridge()->assertNotCalled('PushNotification.Enroll');
    Native::test(Settings::class)->assertSet('permission', 'unavailable')
        ->assertSee('Notifications are not configured in this build. Open the app to check requests.')
        ->assertDontSee('Re-sync notifications');
});

it('completes sign-in with verified identity before a push token exists', function (): void {
    fakeSecureStorage();
    $context = app(AccountContext::class);
    $pending = $context->beginSignIn('https://cbox.cboxid.com', 'cid', 'qr-code');
    Http::fake([
        '*/oauth/token' => Http::response(['access_token' => 'access', 'refresh_token' => 'refresh', 'expires_in' => 900]),
        '*/authenticator/me' => Http::response(['data' => ['subject' => 'alice', 'label' => 'alice@cbox.dk']]),
        '*/devices' => Http::response(['data' => ['id' => 'device-1']], 201),
    ]);
    Native::test(OAuthCallback::class, data: ['state' => $pending->state, 'code' => 'code'])
        ->assertReplacedWith('/approvals');
    $account = $context->store()->find($pending->accountId);
    expect($account?->subject)->toBe('alice')->and($account?->label)->toContain('alice@cbox.dk');
    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/devices') && $request['enrolment_token'] === 'qr-code' && $request['push_token'] === null);
    expect($context->forId($pending->accountId)?->enrolment->deviceId())->toBe('device-1');
});

it('refuses an issuer mix-up before exchanging a code', function (): void {
    fakeSecureStorage();
    $pending = app(AccountContext::class)->beginSignIn('https://cbox.cboxid.com', 'cid', 'qr');
    Native::visit('/oauth/callback?'.http_build_query(['state' => $pending->state, 'code' => 'code', 'iss' => 'https://other.cboxid.com']))
        ->assertNoNavigation();
    Http::assertNothingSent();
    expect(app(AccountStore::class)->isEmpty())->toBeTrue();
});

it('replaces a reconnected identity without duplicating it', function (): void {
    fakeSecureStorage();
    $context = app(AccountContext::class);
    $old = new Account('old-account', 'https://cbox.cboxid.com', 'Alice', 'alice');
    $context->store()->put($old);
    $context->for($old)->tokens->put('old-access', 'old-refresh', 900);
    $context->for($old)->oauth->rememberClientId('cid');
    $pending = $context->beginSignIn($old->issuer, 'cid', 'qr');
    Http::fake([
        '*/oauth/token' => Http::response(['access_token' => 'new-access', 'refresh_token' => 'new-refresh']),
        '*/authenticator/me' => Http::response(['data' => ['subject' => 'alice', 'label' => 'Alice']]),
        '*/devices' => Http::response(['data' => ['id' => 'new-device']], 201),
        '*/oauth/revoke' => Http::response([]),
    ]);
    Native::test(OAuthCallback::class, data: ['state' => $pending->state, 'code' => 'code'])->assertReplacedWith('/approvals');
    expect($context->store()->all())->toHaveCount(1)
        ->and($context->store()->find($pending->accountId)?->subject)->toBe('alice')
        ->and($context->for($old)->tokens->refreshToken())->toBeNull();
});

it('routes a notification by installation even with two identities on one host', function (): void {
    $storage = fakeSecureStorage();
    $context = app(AccountContext::class);
    foreach (['alice', 'bob'] as $id) {
        $account = new Account($id, 'https://cbox.cboxid.com', $id, $id);
        $context->store()->put($account);
        $storage[$account->keyPrefix().'install_id'] = (string) Str::ulid();
    }
    $request = (string) Str::ulid();
    Native::visit('/request/'.$storage['cboxid.acct.bob.install_id'].'/'.$request)
        ->assertReplacedWith('/approvals/bob/'.$request);
    Native::visit('/request/'.Str::ulid().'/'.$request)->assertReplacedWith('/approvals');
    Http::assertNothingSent();
});

it('rejects an untrusted discovery issuer or a redirect', function (int $status, array $body): void {
    Http::fake(['*' => Http::response($body, $status, ['Location' => 'https://evil.example'])]);
    expect(fn () => app(HostDiscovery::class)->fetch('cbox.cboxid.com'))->toThrow(RuntimeException::class);
    Http::assertSentCount(1);
})->with([
    [200, ['issuer' => 'https://evil.example', 'client_id' => 'cid']],
    [302, []],
]);

it('does not allow loopback in a production binary', function (): void {
    $this->app->instance('env', 'production');
    expect(SupportedHost::allows('localhost:8000'))->toBeFalse();
});

it('never replaces unreadable keychain data with a new account', function (): void {
    fakeSecureStorage();
    Native::fakeBridge()->respondTo('SecureStorage.Get', ['status' => 'error', 'code' => 'PROTECTED_DATA_UNAVAILABLE']);
    expect(fn () => SecureVault::get('cboxid.accounts'))->toThrow(RuntimeException::class);
    Native::fakeBridge()->assertNotCalled('SecureStorage.Set');
});

it('keeps the previous credential pair intact when an atomic write fails', function (): void {
    fakeSecureStorage();
    $store = new TokenStore('test.');
    $store->put('old-access', 'old-refresh', 900);
    Native::fakeBridge()->respondTo('SecureStorage.Set', ['success' => false]);
    expect(fn () => $store->put('new-access', 'new-refresh', 900))->toThrow(RuntimeException::class);
    expect($store->accessToken())->toBe('old-access')->and($store->refreshToken())->toBe('old-refresh');
});

it('migrates legacy credentials without dropping the refresh token', function (): void {
    $storage = fakeSecureStorage(['test.token.access' => 'access', 'test.token.refresh' => 'refresh', 'test.token.expires' => (string) (time() + 900)]);
    $store = new TokenStore('test.');
    expect($store->refreshToken())->toBe('refresh')->and($storage)->toHaveKey('test.credentials.v1')->not->toHaveKey('test.token.refresh');
    $store->put('new-access', null, 900);
    expect($store->refreshToken())->toBe('refresh');
});

it('renders privacy and account removal as native screens', function (): void {
    fakeSecureStorage();
    Native::test(About::class)->assertSee('Your accounts and privacy')->assertSee('Contact Cbox');
    app(AccountStore::class)->put(new Account('alice', 'https://cbox.cboxid.com', 'Alice'));
    Native::test(Settings::class)->call('requestRemoval', 'alice')->assertSee('Keep account')->assertSee('Remove account');
});

it('renders a usable native host field and Danish account screens', function (): void {
    fakeSecureStorage();
    app()->setLocale('da');
    Native::test(Connect::class)
        ->assertElement('outlined_text_input', fn ($node) => ($node['props']['keyboard'] ?? null) === 'url')
        ->assertSee('Scan koden')->assertAccessible();
    Native::test(About::class)->assertSee('Dine konti og dit privatliv')->assertAccessible();
    Native::fakeBridge()->respondTo('AuthenticatorSecurity.State', ['locale' => 'da-DK']);
    expect(app(DeviceSecurity::class)->locale())->toBe('da');
});

it('routes security alerts to the locally matched account without contacting a host', function (): void {
    $storage = fakeSecureStorage();
    $account = anAccount();
    $install = (string) Str::ulid();
    $storage[$account->keyPrefix().'install_id'] = $install;
    Native::visit('/security/'.$install)->assertReplacedWith('/account/'.$account->id);
    Native::visit('/security/'.Str::ulid())->assertReplacedWith('/approvals');
    Http::assertNothingSent();
});

it('retrieves every pending page and signs the cursor request without its query', function (): void {
    fakeSecureStorage();
    $account = anAccount();
    $cursor = (string) Str::ulid();
    Http::fake(['*/approvals*' => Http::sequence()
        ->push(['data' => [['id' => 'first']], 'meta' => ['has_more' => true, 'next_cursor' => $cursor]])
        ->push(['data' => [['id' => 'second']], 'meta' => ['has_more' => false]])]);
    $pending = app(AccountContext::class)->for($account)->api->pendingApprovals();
    expect(array_column($pending, 'id'))->toBe(['first', 'second']);
    Http::assertSentCount(2);
    Http::assertSent(function ($request) use ($cursor): bool {
        if (! str_ends_with($request->url(), '?cursor='.$cursor)) {
            return false;
        }
        $proof = explode('.', $request->header('DPoP')[0]);
        $claims = json_decode(base64_decode(strtr($proof[1], '-_', '+/')), true);

        return $claims['htu'] === 'https://cbox.cboxid.com/api/v1/approvals';
    });
});

it('refuses a cursor loop instead of hiding a truncated inbox', function (): void {
    fakeSecureStorage();
    $account = anAccount();
    Http::fake(['*' => Http::response(['data' => [], 'meta' => ['has_more' => true, 'next_cursor' => (string) Str::ulid()]])]);
    expect(fn () => app(AccountContext::class)->for($account)->api->pendingApprovals())->toThrow(ApiFailure::class);
    Http::assertSentCount(2);
});

it('explains that an old host needs updating before attempting sign-in', function (): void {
    Http::fake(['*' => Http::response(['issuer' => 'https://cbox.cboxid.com', 'client_id' => 'cid'])]);
    expect(fn () => app(HostDiscovery::class)->fetch('cbox.cboxid.com'))->toThrow(EnrolmentFailed::class, 'needs an update');
});

it('shows the bundled third-party notices in a native screen', function (): void {
    fakeSecureStorage();
    $screen = Native::test(Licenses::class)->assertSee('laravel/framework')->assertAccessible();
    $packages = $screen->get('packages');
    $index = array_search('laravel/framework', array_column($packages, 'name'), true);
    $screen->call('select', $index)->assertSee('MIT')->assertSee('Permission is hereby granted');
});

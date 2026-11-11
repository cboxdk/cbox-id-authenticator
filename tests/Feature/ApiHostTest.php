<?php

declare(strict_types=1);

use App\Accounts\Account;
use App\Accounts\AccountContext;
use App\Accounts\AccountStore;
use App\Accounts\PendingSignIn;
use App\NativeComponents\Approvals;
use App\NativeComponents\Connect;
use App\Services\ApiBase;
use App\Services\HostDiscovery;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Native\Mobile\Testing\Native;

/*
| Cbox ID v3 advertises `api_base` (https://api.cboxid.com/v1) in
| `/.well-known/cbox-authenticator`. An account connected with it calls the API host; every
| other account keeps calling `{issuer}/api/v1` exactly as before.
*/

function apiHostAccount(): Account
{
    $account = new Account('01API', 'https://cbox.cboxid.com', 'alice', 'usr_alice', 'https://api.cboxid.com/v1');
    app(AccountStore::class)->put($account);

    $services = app(AccountContext::class)->for($account);
    $services->oauth->rememberClientId('cid_a');
    $services->tokens->put('access', 'refresh', 900);

    return $account;
}

/** @return array<string, mixed> */
function dpopClaims(Request $request): array
{
    $proof = explode('.', $request->header('DPoP')[0]);

    return json_decode(base64_decode(strtr($proof[1], '-_', '+/')), true);
}

it('reads api_base from discovery', function (): void {
    Http::fake(['*' => Http::response([
        'issuer' => 'https://cbox.cboxid.com', 'client_id' => 'cid', 'api_version' => 2,
        'api_base' => 'https://api.cboxid.com/v1',
    ])]);

    $host = app(HostDiscovery::class)->fetch('cbox.cboxid.com');

    expect($host->advertisedApiBase)->toBe('https://api.cboxid.com/v1')
        ->and($host->apiBase())->toBe('https://api.cboxid.com/v1');
});

it('falls back to the issuer\'s own /api/v1 when api_base is absent', function (): void {
    Http::fake(['*' => Http::response(['issuer' => 'https://cbox.cboxid.com', 'client_id' => 'cid', 'api_version' => 2])]);

    $host = app(HostDiscovery::class)->fetch('cbox.cboxid.com');

    expect($host->advertisedApiBase)->toBeNull()
        ->and($host->apiBase())->toBe('https://cbox.cboxid.com/api/v1');
});

it('ignores an api_base it cannot trust with a token', function (mixed $advertised): void {
    expect(ApiBase::fromAdvertised($advertised, 'https://cbox.cboxid.com'))->toBeNull();
})->with([
    'the legacy base itself' => 'https://cbox.cboxid.com/api/v1',
    'another domain' => 'https://api.evil.example/v1',
    'a lookalike suffix' => 'https://api.notcboxid.com/v1',
    'plain http' => 'http://api.cboxid.com/v1',
    'an unknown version' => 'https://api.cboxid.com/v2',
    'credentials' => 'https://user:pw@api.cboxid.com/v1',
    'a query' => 'https://api.cboxid.com/v1?x=1',
    'not a string' => 42,
    'empty' => '',
]);

it('accepts the API host with or without a trailing slash', function (): void {
    expect(ApiBase::fromAdvertised('https://api.cboxid.com/v1/', 'https://cbox.cboxid.com'))->toBe('https://api.cboxid.com/v1')
        ->and(ApiBase::fromAdvertised('https://API.cboxid.com/v1', 'https://cbox.cboxid.com'))->toBe('https://api.cboxid.com/v1');
});

it('stores an existing account exactly as before', function (): void {
    $account = new Account('01A', 'https://cbox.cboxid.com', 'alice', 'usr_alice');

    expect($account->toArray())->toBe(['id' => '01A', 'issuer' => 'https://cbox.cboxid.com', 'label' => 'alice', 'subject' => 'usr_alice'])
        ->and(Account::fromArray($account->toArray())?->apiBase)->toBeNull();
});

it('keeps an account\'s api_base across a restart and re-checks it on read', function (): void {
    $row = (new Account('01A', 'https://cbox.cboxid.com', 'alice', null, 'https://api.cboxid.com/v1'))->toArray();

    expect(Account::fromArray($row)?->apiBase)->toBe('https://api.cboxid.com/v1')
        ->and(Account::fromArray([...$row, 'api_base' => 'https://evil.example/v1'])?->apiBase)->toBeNull();
});

it('carries api_base from the scan through the pending sign-in', function (): void {
    $keychain = fakeSecureStorage();
    Native::fakeBridge()->respondTo('AuthenticatorSecurity.OpenAuthentication', ['success' => true]);
    Http::fake(['*/.well-known/cbox-authenticator' => Http::response([
        'issuer' => 'https://a.cboxid.com', 'client_id' => 'cid_a', 'api_version' => 2,
        'api_base' => 'https://api.cboxid.com/v1',
    ])]);

    Native::test(Connect::class)->call('scanned', 'a.cboxid.com');

    $row = array_values(json_decode($keychain['cboxid.pending'], true))[0];
    $pending = PendingSignIn::fromArray($row);

    expect($pending?->apiBase)->toBe('https://api.cboxid.com/v1')
        ->and(app(AccountContext::class)->reserved($pending)->account->apiBase)->toBe('https://api.cboxid.com/v1');
});

it('lists approvals from /v1/me/approvals, prefixed ids and all, across pages', function (): void {
    fakeSecureStorage();
    apiHostAccount();
    $cursor = (string) Str::ulid();

    Http::fake(['https://api.cboxid.com/v1/me/approvals*' => Http::sequence()
        ->push(['object' => 'list', 'data' => [['id' => 'apr_'.Str::ulid(), 'client_name' => 'Payroll']], 'has_more' => true, 'next_cursor' => 'apr_'.$cursor])
        ->push(['object' => 'list', 'data' => [['id' => 'apr_'.Str::ulid(), 'client_name' => 'Console']], 'has_more' => false, 'next_cursor' => null]),
    ]);

    $rows = Native::test(Approvals::class)->get('approvals');

    expect(array_column($rows, 'client_name'))->toContain('Payroll', 'Console')
        ->and($rows[0]['id'])->toStartWith('apr_');
    Http::assertSent(fn (Request $r): bool => $r->url() === 'https://api.cboxid.com/v1/me/approvals'
        && dpopClaims($r)['htu'] === 'https://api.cboxid.com/v1/me/approvals');
    Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), '/v1/me/approvals?after=apr_'.$cursor.'&cursor='.$cursor));
    Http::assertNotSent(fn (Request $r): bool => str_contains($r->url(), '/api/v1/'));
});

it('reads one approval and decides it on /v1/me/approvals', function (): void {
    fakeSecureStorage();
    $api = app(AccountContext::class)->for(apiHostAccount())->api;
    $id = 'apr_'.Str::ulid();

    Http::fake([
        "https://api.cboxid.com/v1/me/approvals/{$id}" => Http::response(['object' => 'approval', 'id' => $id, 'binding_message' => 'Pay 100']),
        "https://api.cboxid.com/v1/me/approvals/{$id}/approve" => Http::response(['object' => 'approval', 'id' => $id, 'status' => 'approved']),
        "https://api.cboxid.com/v1/me/approvals/{$id}/deny" => Http::response(['type' => 'https://api.cboxid.com/problems/conflict', 'error' => 'conflict'], 409, ['Content-Type' => 'application/problem+json']),
    ]);

    expect($api->approval($id)['binding_message'] ?? null)->toBe('Pay 100')
        ->and($api->approve($id))->toBeTrue()
        ->and($api->deny($id))->toBeFalse();
});

it('reads identity and enrols the device on the API host', function (): void {
    fakeSecureStorage();
    $services = app(AccountContext::class)->for(apiHostAccount());

    Http::fake([
        'https://api.cboxid.com/v1/me/authenticator' => Http::response(['object' => 'authenticator', 'subject' => 'usr_alice', 'label' => 'alice@example.com']),
        'https://api.cboxid.com/v1/devices' => Http::response(['object' => 'device', 'id' => 'dvc_'.Str::ulid()], 201),
    ]);

    expect($services->api->identity())->toBe(['subject' => 'usr_alice', 'label' => 'alice@example.com'])
        ->and($services->enrolment->enrol('a-code'))->toBeTrue()
        ->and($services->enrolment->deviceId())->toStartWith('dvc_');
});

it('keeps an account without api_base on {issuer}/api/v1', function (): void {
    fakeSecureStorage();
    $api = app(AccountContext::class)->for(anAccount())->api;

    Http::fake(['https://cbox.cboxid.com/api/v1/approvals' => Http::response(['data' => [['id' => 'ap1']]])]);

    expect(array_column($api->pendingApprovals(), 'id'))->toBe(['ap1']);
    Http::assertSent(fn (Request $r): bool => $r->url() === 'https://cbox.cboxid.com/api/v1/approvals');
});

it('routes a notification carrying a prefixed approval id', function (): void {
    $storage = fakeSecureStorage();
    $account = new Account('bob', 'https://cbox.cboxid.com', 'bob', 'bob');
    app(AccountContext::class)->store()->put($account);
    $storage[$account->keyPrefix().'install_id'] = $install = (string) Str::ulid();
    $request = 'apr_'.Str::ulid();

    Native::visit('/request/'.$install.'/'.$request)->assertReplacedWith('/approvals/bob/'.$request);
    Native::visit('/request/'.$install.'/apr_not-an-id')->assertReplacedWith('/approvals');
});

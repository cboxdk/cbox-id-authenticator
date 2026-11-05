<?php

declare(strict_types=1);

use App\Accounts\Account;
use App\Accounts\AccountContext;
use App\Accounts\AccountStorageFailed;
use App\Accounts\AccountStore;
use App\Accounts\PendingSignIns;
use App\Services\ServerClock;

it('keeps two accounts on the same host completely separate', function (): void {
    // The case the old fixed keys collapsed: same issuer, two people. Nothing about one
    // may be reachable through the other.
    $keychain = fakeSecureStorage();
    $store = new AccountStore;

    $alice = new Account('01ALICE', 'https://cbox.cboxid.com', 'alice@cbox.dk');
    $bob = new Account('01BOB', 'https://cbox.cboxid.com', 'bob@cbox.dk');

    $store->put($alice);
    $store->put($bob);

    $context = new AccountContext($store, new PendingSignIns, new ServerClock);

    $context->for($alice)->tokens->put('alice-access', 'alice-refresh', 900);
    $context->for($bob)->tokens->put('bob-access', 'bob-refresh', 900);

    expect($context->for($alice)->tokens->refreshToken())->toBe('alice-refresh')
        ->and($context->for($bob)->tokens->refreshToken())->toBe('bob-refresh');

    // And prove it at the storage layer, not just through the accessor.
    expect($keychain)->toHaveKey('cboxid.acct.01ALICE.credentials.v1')
        ->and($keychain)->toHaveKey('cboxid.acct.01BOB.credentials.v1');
});

it('does not let one account prefix match another', function (): void {
    // Without the trailing dot in keyPrefix(), wiping 01AB would take 01ABC's keys too.
    expect((new Account('01AB', 'https://a.test', 'a'))->keyPrefix())->toBe('cboxid.acct.01AB.')
        ->and(str_starts_with('cboxid.acct.01ABC.token.access', 'cboxid.acct.01AB.'))->toBeFalse();
});

it('wipes every secret when an account is removed and leaves the other untouched', function (): void {
    $keychain = fakeSecureStorage();
    $store = new AccountStore;

    $alice = new Account('01ALICE', 'https://a.cboxid.com', 'alice');
    $bob = new Account('01BOB', 'https://b.cboxid.com', 'bob');

    $store->put($alice);
    $store->put($bob);

    $context = new AccountContext($store, new PendingSignIns, new ServerClock);

    foreach ([$alice, $bob] as $account) {
        $services = $context->for($account);
        $services->tokens->put('access', 'refresh', 900);
        $services->oauth->rememberClientId('cid_'.$account->id);
        $services->installId->get();
        $services->key->pem();
    }

    $context->remove($alice);

    $remaining = array_keys($keychain->getArrayCopy());

    expect(array_filter($remaining, fn (string $k): bool => str_contains($k, '01ALICE')))->toBe([])
        ->and(array_filter($remaining, fn (string $k): bool => str_contains($k, '01BOB')))->not->toBe([]);

    expect($store->all())->toHaveCount(1)
        ->and($store->all()[0]->id)->toBe('01BOB');
});

it('finds the accounts on one issuer so a push can be routed home', function (): void {
    fakeSecureStorage();
    $store = new AccountStore;

    $store->put(new Account('01A', 'https://cbox.cboxid.com', 'a'));
    $store->put(new Account('01B', 'https://cbox.cboxid.com/', 'b'));
    $store->put(new Account('01C', 'https://other.cboxid.com', 'c'));

    // Trailing slash must not split one issuer into two.
    expect($store->forIssuer('https://cbox.cboxid.com'))->toHaveCount(2)
        ->and($store->forIssuer('https://other.cboxid.com'))->toHaveCount(1)
        ->and($store->forIssuer('https://nobody.cboxid.com'))->toBe([]);
});

it('refuses to overwrite a corrupt account index', function (): void {
    $raw = '{not json';
    $keychain = fakeSecureStorage(['cboxid.accounts' => $raw]);
    expect(fn () => (new AccountStore)->put(new Account('new', 'https://cbox.cboxid.com', 'new')))
        ->toThrow(AccountStorageFailed::class);
    expect($keychain['cboxid.accounts'])->toBe($raw);
});

it('drops index rows that are missing an issuer instead of guessing one', function (): void {
    fakeSecureStorage([
        'cboxid.accounts' => json_encode([
            ['id' => '01GOOD', 'issuer' => 'https://good.cboxid.com', 'label' => 'good'],
            ['id' => '01BAD'],
        ]),
    ]);

    expect(fn () => (new AccountStore)->all())->toThrow(AccountStorageFailed::class);
});

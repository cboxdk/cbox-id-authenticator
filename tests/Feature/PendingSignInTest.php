<?php

declare(strict_types=1);

use App\Accounts\AccountContext;
use App\Accounts\AccountStore;
use App\Accounts\PendingSignIn;
use App\Accounts\PendingSignIns;
use App\Services\ServerClock;

it('keeps two overlapping sign-ins independent', function (): void {
    // The old shape kept the verifier and state under two fixed keys, so starting a
    // second attempt destroyed the first's verifier and whichever callback arrived
    // first failed the exchange. This is that bug, pinned.
    fakeSecureStorage();
    $pending = new PendingSignIns;
    $context = new AccountContext(new AccountStore, $pending, new ServerClock);

    $first = $context->beginSignIn('https://a.cboxid.com', 'cid_a');
    $second = $context->beginSignIn('https://b.cboxid.com', 'cid_b');

    expect($first->state)->not->toBe($second->state)
        ->and($first->verifier)->not->toBe($second->verifier)
        ->and($first->accountId)->not->toBe($second->accountId);

    // Both still resolvable, in either order.
    expect($pending->take($second->state)?->issuer)->toBe('https://b.cboxid.com')
        ->and($pending->take($first->state)?->issuer)->toBe('https://a.cboxid.com');
});

it('answers a state exactly once', function (): void {
    // A callback URL passes through the OS's deep-link handling and can be delivered
    // twice. The second delivery must not be exchangeable.
    fakeSecureStorage();
    $pending = new PendingSignIns;

    $pending->put(new PendingSignIn('st', '01ACC', 'https://a.test', 'cid', 'verifier', time()));

    expect($pending->take('st'))->not->toBeNull()
        ->and($pending->take('st'))->toBeNull();
});

it('refuses a state it never issued', function (): void {
    fakeSecureStorage();

    expect((new PendingSignIns)->take('never-issued'))->toBeNull();
});

it('refuses an attempt that has gone stale', function (): void {
    fakeSecureStorage();
    $pending = new PendingSignIns;

    $pending->put(new PendingSignIn('st', '01ACC', 'https://a.test', 'cid', 'v', time() - 601));

    expect($pending->take('st'))->toBeNull();
});

it('wipes the keys an abandoned attempt reserved', function (): void {
    // Tapping "Scan the code" and walking away writes a DPoP key. Without the sweep it
    // would sit in the keychain forever, and again on every attempt.
    $keychain = fakeSecureStorage();
    $pending = new PendingSignIns;
    $context = new AccountContext(new AccountStore, $pending, new ServerClock);

    $abandoned = $context->beginSignIn('https://a.cboxid.com', 'cid_a');

    expect($keychain)->toHaveKey('cboxid.acct.'.$abandoned->accountId.'.dpop.privkey');

    // Age it past the TTL, then start a fresh attempt — which sweeps.
    $records = json_decode($keychain['cboxid.pending'], true);
    $records[$abandoned->state]['created_at'] = time() - 601;
    $keychain['cboxid.pending'] = json_encode($records);

    $context->beginSignIn('https://b.cboxid.com', 'cid_b');

    $left = array_filter(
        array_keys($keychain->getArrayCopy()),
        fn (string $k): bool => str_contains($k, $abandoned->accountId),
    );

    expect($left)->toBe([]);
});

it('never sweeps an account that completed sign-in', function (): void {
    // The sweep wipes secrets. A stale pending record whose account did complete must
    // be dropped WITHOUT touching that account's keys.
    $keychain = fakeSecureStorage();
    $pending = new PendingSignIns;
    $store = new AccountStore;
    $context = new AccountContext($store, $pending, new ServerClock);

    $attempt = $context->beginSignIn('https://a.cboxid.com', 'cid_a');
    $services = $context->reserved($attempt);
    $services->tokens->put('access', 'refresh', 900);
    $store->put($services->account);

    // A record left behind, aged out.
    $records = json_decode($keychain['cboxid.pending'], true);
    $records[$attempt->state]['created_at'] = time() - 601;
    $keychain['cboxid.pending'] = json_encode($records);

    $context->beginSignIn('https://b.cboxid.com', 'cid_b');

    expect($keychain)->toHaveKey('cboxid.acct.'.$attempt->accountId.'.credentials.v1');
});

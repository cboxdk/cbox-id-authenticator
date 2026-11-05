<?php

declare(strict_types=1);

use App\Accounts\AccountStore;
use App\Accounts\LegacyAccountMigration;

/** The exact keychain a pre-multi-account install left behind. */
function legacyInstall(): array
{
    return [
        'cboxid.issuer' => 'https://cbox.cboxid.com',
        'cboxid.token.access' => 'old-access',
        'cboxid.token.refresh' => 'old-refresh',
        'cboxid.token.expires' => (string) (time() + 900),
        'cboxid.client_id' => 'cid_old',
        'cboxid.dpop.privkey' => '-----BEGIN EC PRIVATE KEY-----old-----END EC PRIVATE KEY-----',
        'cboxid.install_id' => '01OLDINSTALL',
        'cboxid.pkce.verifier' => 'abandoned',
    ];
}

it('carries a signed-in install across without signing anyone out', function (): void {
    $keychain = fakeSecureStorage(legacyInstall());
    $store = new AccountStore;

    (new LegacyAccountMigration($store))->run();

    expect($store->all())->toHaveCount(1);

    $account = $store->all()[0];

    expect($account->issuer)->toBe('https://cbox.cboxid.com')
        ->and($account->label)->toBe('cbox.cboxid.com');

    $prefix = $account->keyPrefix();

    expect($keychain[$prefix.'token.refresh'])->toBe('old-refresh')
        ->and($keychain[$prefix.'client_id'])->toBe('cid_old')
        ->and($keychain[$prefix.'install_id'])->toBe('01OLDINSTALL')
        ->and($keychain[$prefix.'dpop.privkey'])->toContain('old');
});

it('leaves none of the old fixed keys behind', function (): void {
    $keychain = fakeSecureStorage(legacyInstall());

    (new LegacyAccountMigration(new AccountStore))->run();

    foreach (array_keys(legacyInstall()) as $old) {
        expect($keychain)->not->toHaveKey($old);
    }
});

it('is safe to run twice', function (): void {
    // The device runs this on every launch. A second pass must not mint a second
    // account from keys the first one already moved.
    fakeSecureStorage(legacyInstall());
    $store = new AccountStore;

    (new LegacyAccountMigration($store))->run();
    (new LegacyAccountMigration($store))->run();

    expect($store->all())->toHaveCount(1);
});

it('does nothing on a fresh install', function (): void {
    fakeSecureStorage();
    $store = new AccountStore;

    (new LegacyAccountMigration($store))->run();

    expect($store->all())->toBe([]);
});

it('does not migrate an install that never finished signing in', function (): void {
    // Tokens with no issuer cannot be aimed at anything, so there is nothing to carry —
    // and inventing an issuer would point this handset's secrets at the wrong host.
    $keychain = fakeSecureStorage([
        'cboxid.token.refresh' => 'orphan',
        'cboxid.pkce.verifier' => 'abandoned',
    ]);
    $store = new AccountStore;

    (new LegacyAccountMigration($store))->run();

    expect($store->all())->toBe([])
        ->and($keychain)->not->toHaveKey('cboxid.pkce.verifier');
});

it('does not touch an install that already has accounts', function (): void {
    // Stale legacy keys alongside a migrated install must not mint a duplicate.
    $keychain = fakeSecureStorage(legacyInstall() + [
        'cboxid.accounts' => json_encode([
            ['id' => '01EXISTING', 'issuer' => 'https://cbox.cboxid.com', 'label' => 'cbox.cboxid.com'],
        ]),
    ]);

    $store = new AccountStore;

    (new LegacyAccountMigration($store))->run();

    expect($store->all())->toHaveCount(1)
        ->and($store->all()[0]->id)->toBe('01EXISTING')
        ->and($keychain['cboxid.token.refresh'])->toBe('old-refresh');
});

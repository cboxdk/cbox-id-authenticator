<?php

declare(strict_types=1);
use App\Accounts\Account;
use App\Accounts\AccountContext;
use App\Accounts\AccountStore;
use App\Totp\Base32;
use App\Totp\TotpEntry;
use Native\Mobile\Testing\Native;
use Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature');

/**
 * An in-memory stand-in for the iOS Keychain / Android Keystore.
 *
 * SecureStorage is a native bridge call, so in-process it silently no-ops: `set()`
 * returns false and `get()` returns null. Every account secret is built on it, so
 * without this helper none of that could be tested at all — which is a fair part of how
 * the single-account key collision survived as long as it did.
 *
 * An ArrayObject rather than a plain array so the caller keeps a live handle: a test can
 * assert on exactly which keys were written, and prove two accounts never shared one.
 *
 * @param  array<string, string>  $seed
 * @return ArrayObject<string, string>
 */
function fakeSecureStorage(array $seed = []): ArrayObject
{
    /** @var ArrayObject<string, string> $store */
    $store = new ArrayObject($seed);

    Native::fakeBridge()
        ->respondTo('AuthenticatorSecurity.State', ['unlocked' => true, 'generation' => 0, 'foreground' => true, 'pushAvailable' => true])
        ->respondTo('SecureStorage.Set', function (array $params) use ($store): array {
            $store[$params['key']] = $params['value'];

            return ['success' => true];
        })
        ->respondTo('SecureStorage.Get', fn (array $params): array => [
            'value' => $store[$params['key']] ?? null,
        ])
        ->respondTo('SecureStorage.Delete', function (array $params) use ($store): array {
            unset($store[$params['key']]);

            return ['success' => true];
        });

    return $store;
}

function anAccount(): Account
{
    $account = new Account('01ACC', 'https://cbox.cboxid.com', 'alice');

    app(AccountStore::class)->put($account);

    $services = app(AccountContext::class)->for($account);
    $services->oauth->rememberClientId('cid_a');
    $services->tokens->put('access', 'refresh', 900);

    return $account;
}

function testTotpEntry(string $secret = '12345678901234567890'): TotpEntry
{
    return new TotpEntry(str_repeat('a', 32), 'Example', 'alice@example.test', Base32::encode($secret));
}

<?php

declare(strict_types=1);
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

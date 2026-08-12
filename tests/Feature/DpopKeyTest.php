<?php

declare(strict_types=1);

use App\Services\DpopKey;

it('ships the OpenSSL configuration key generation needs', function (): void {
    // ext-openssl will not create a key without a configuration to read, and the iOS
    // runtime ships none — its php.ini sets only the CA bundle. A desktop finds the
    // system copy, which is exactly why losing this file would pass every test here and
    // fail on every handset.
    expect(resource_path('openssl.cnf'))->toBeFile();
});

it('generates a P-256 key on a runtime with no system OpenSSL config', function (): void {
    fakeSecureStorage();

    $key = new DpopKey('cboxid.acct.01TEST.');
    $jwk = $key->publicJwk();

    expect($key->pem())->toContain('PRIVATE KEY')
        // ES256 is what the server's DPoP validator requires; any other curve is
        // rejected outright.
        ->and($jwk['crv'])->toBe('P-256')
        ->and($jwk['kty'])->toBe('EC');
});

it('orders the public JWK the way RFC 7638 requires', function (): void {
    // The thumbprint hashes this JSON canonically, so the key order IS the value. Get it
    // wrong and every `jkt` the server binds tokens to is a different one.
    fakeSecureStorage();

    expect(array_keys((new DpopKey('cboxid.acct.01TEST.'))->publicJwk()))
        ->toBe(['crv', 'kty', 'x', 'y']);
});

it('reuses the stored key rather than minting a new one per proof', function (): void {
    // A fresh key on each call would rebind every token and the server would refuse the
    // next request with a `jkt` it had never seen.
    fakeSecureStorage();

    $first = (new DpopKey('cboxid.acct.01TEST.'))->pem();
    $second = (new DpopKey('cboxid.acct.01TEST.'))->pem();

    expect($second)->toBe($first);
});

it('gives two accounts two different keys', function (): void {
    fakeSecureStorage();

    expect((new DpopKey('cboxid.acct.01A.'))->thumbprint())
        ->not->toBe((new DpopKey('cboxid.acct.01B.'))->thumbprint());
});

it('refuses rather than carrying on with a key it could not store', function (): void {
    // An in-memory-only key produces an enrolment that stops working on the next launch,
    // and the user has no way to tell why their prompts stopped.
    Native\Mobile\Testing\Native::fakeBridge()
        ->respondTo('SecureStorage.Get', ['value' => null])
        ->respondTo('SecureStorage.Set', ['success' => false]);

    expect(fn () => (new DpopKey('cboxid.acct.01TEST.'))->pem())
        ->toThrow(RuntimeException::class, 'securely');
});

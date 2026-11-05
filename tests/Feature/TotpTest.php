<?php

declare(strict_types=1);
use App\Totp\Base32;
use App\Totp\TotpEntry;
use App\Totp\TotpStore;
use App\Totp\TotpUri;
use Native\Mobile\Testing\Native;

it('matches every RFC 6238 SHA1 SHA256 and SHA512 test vector', function (int $time, array $expected): void {
    foreach (['SHA1' => 20, 'SHA256' => 32, 'SHA512' => 64] as $algorithm => $length) {
        $key = substr(str_repeat('1234567890', 7), 0, $length);
        $entry = new TotpEntry(str_repeat('a', 32), 'RFC', 'alice', Base32::encode($key), $algorithm, 8);
        expect($entry->code($time))->toBe($expected[$algorithm]);
    }
})->with([
    [59, ['SHA1' => '94287082', 'SHA256' => '46119246', 'SHA512' => '90693936']],
    [1111111109, ['SHA1' => '07081804', 'SHA256' => '68084774', 'SHA512' => '25091201']],
    [1111111111, ['SHA1' => '14050471', 'SHA256' => '67062674', 'SHA512' => '99943326']],
    [1234567890, ['SHA1' => '89005924', 'SHA256' => '91819424', 'SHA512' => '93441116']],
    [2000000000, ['SHA1' => '69279037', 'SHA256' => '90698825', 'SHA512' => '38618901']],
    [20000000000, ['SHA1' => '65353130', 'SHA256' => '77737706', 'SHA512' => '47863826']],
]);

it('changes only at the period boundary and retains leading zeroes', function (): void {
    $entry = testTotpEntry();
    expect($entry->code(0))->toBe('755224')->and($entry->code(29))->toBe('755224')
        ->and($entry->code(30))->toBe('287082')->and($entry->code(1111111109))->toBe('081804');
});

it('normalizes manual keys without losing bits', function (): void {
    $bytes = '123456789012345678901';
    $key = Base32::encode($bytes);
    expect(Base32::normalize(strtolower(chunk_split($key, 4, ' '))))->toBe($key)
        ->and(Base32::decode($key))->toBe($bytes);
});

it('rejects malformed or short setup keys', function (string $key): void {
    expect(fn () => Base32::normalize($key))->toThrow(InvalidArgumentException::class);
})->with(['1234', 'A', 'AAAAAAAAAAAAAAAAA', 'JBSWY3DPEHPK3PXP0', 'JBSWY3DPEHPK3PXP=', str_repeat('A', 520)]);

it('parses an escaped standard URI and honors all settings', function (): void {
    $entry = TotpUri::parse('otpauth://totp/ACME%20Co:alice%2Btest%40example.test?secret=GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ&issuer=ACME%20Co&algorithm=SHA256&digits=8&period=60');
    expect($entry->issuer)->toBe('ACME Co')->and($entry->account)->toBe('alice+test@example.test')
        ->and($entry->algorithm)->toBe('SHA256')->and($entry->digits)->toBe(8)->and($entry->period)->toBe(60);
});

it('rejects ambiguous or unsupported setup codes', function (string $uri): void {
    expect(fn () => TotpUri::parse($uri))->toThrow(InvalidArgumentException::class);
})->with([
    'otpauth://hotp/alice?secret=JBSWY3DPEHPK3PXP&counter=1',
    'otpauth-migration://offline?data=anything',
    'https://example.test/alice?secret=JBSWY3DPEHPK3PXP',
    'otpauth://totp/A:alice?secret=JBSWY3DPEHPK3PXP&issuer=B',
    'otpauth://totp/alice?secret=JBSWY3DPEHPK3PXP&secret=AAAAAAAAAAAAAAAA',
    'otpauth://totp/alice?secret=JBSWY3DPEHPK3PXP&digits=7',
    'otpauth://totp/alice?secret=JBSWY3DPEHPK3PXP&period=0',
    'otpauth://totp/alice?secret=JBSWY3DPEHPK3PXP&algorithm=MD5',
    'otpauth://totp/alice%xx?secret=JBSWY3DPEHPK3PXP',
]);

it('merges atomically and keeps existing names for duplicate secrets', function (): void {
    $storage = fakeSecureStorage();
    $store = app(TotpStore::class);
    expect($store->merge([testTotpEntry()]))->toBe(['added' => 1, 'duplicates' => 0]);
    $original = $store->all()[0];
    $renamed = new TotpEntry(str_repeat('b', 32), 'Different', 'bob', $original->secret);
    expect($store->merge([$renamed]))->toBe(['added' => 0, 'duplicates' => 1])
        ->and($store->all()[0]->account)->toBe('alice@example.test');
    $before = $storage[TotpStore::KEY];
    Native::fakeBridge()->respondTo('SecureStorage.Set', ['success' => false]);
    expect(fn () => $store->merge([testTotpEntry('a different test key!')]))->toThrow(RuntimeException::class);
    expect($storage[TotpStore::KEY])->toBe($before);
});

it('never overwrites corrupt or unavailable storage', function (): void {
    $storage = fakeSecureStorage([TotpStore::KEY => '{"broken":true}']);
    expect(fn () => app(TotpStore::class)->merge([testTotpEntry()]))->toThrow(RuntimeException::class);
    Native::fakeBridge()->assertNotCalled('SecureStorage.Set');
    Native::fakeBridge()->respondTo('SecureStorage.Get', ['status' => 'error', 'code' => 'PROTECTED_DATA_UNAVAILABLE']);
    expect(fn () => app(TotpStore::class)->merge([testTotpEntry()]))->toThrow(RuntimeException::class);
    expect($storage[TotpStore::KEY])->toBe('{"broken":true}');
});

it('refuses access while the native app gate is locked', function (): void {
    fakeSecureStorage();
    app(TotpStore::class)->merge([testTotpEntry()]);
    Native::fakeBridge()->respondTo('AuthenticatorSecurity.State', ['unlocked' => false, 'foreground' => true, 'generation' => 1]);
    expect(fn () => app(TotpStore::class)->all())->toThrow(RuntimeException::class);
    expect(fn () => app(TotpStore::class)->remove(str_repeat('a', 32)))->toThrow(RuntimeException::class);
});

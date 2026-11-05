<?php

declare(strict_types=1);
use App\Totp\EncryptedBackup;
use App\Totp\TotpStore;

it('creates randomized portable authenticated backups without plaintext secrets', function (): void {
    $backup = app(EncryptedBackup::class);
    $entry = testTotpEntry();
    $first = $backup->encrypt([$entry], 'a good backup password');
    $second = $backup->encrypt([$entry], 'a good backup password');
    expect($first)->not->toBe($second)->not->toContain($entry->secret)->not->toContain($entry->account);
    expect($backup->decrypt($first, 'a good backup password')[0]->toArray())->toBe($entry->toArray());
});

it('rejects a wrong password without changing the vault', function (): void {
    $storage = fakeSecureStorage();
    $store = app(TotpStore::class);
    $store->merge([testTotpEntry()]);
    $before = $storage[TotpStore::KEY];
    $backup = app(EncryptedBackup::class);
    $file = $backup->encrypt($store->all(), 'a good backup password');
    expect(fn () => $store->merge($backup->decrypt($file, 'incorrect password')))->toThrow(RuntimeException::class);
    expect($storage[TotpStore::KEY])->toBe($before);
});

it('rejects tampering and hostile KDF costs before importing', function (string $field, mixed $value): void {
    $backup = app(EncryptedBackup::class);
    $file = $backup->encrypt([testTotpEntry()], 'a good backup password');
    [$prefix, $json] = explode("\n", $file, 2);
    $data = json_decode($json, true);
    $data[$field] = $value;
    expect(fn () => $backup->decrypt($prefix."\n".json_encode($data), 'a good backup password'))->toThrow(RuntimeException::class);
})->with([
    ['version', 2], ['opslimit', 9999999], ['memlimit', PHP_INT_MAX], ['kdf', 'pbkdf2'],
    ['salt', base64_encode(str_repeat('x', 16))], ['nonce', base64_encode(str_repeat('x', 24))],
    ['ciphertext', base64_encode(str_repeat('x', 120))],
]);

it('bounds files and requires a strong export password', function (): void {
    $backup = app(EncryptedBackup::class);
    expect(fn () => $backup->encrypt([testTotpEntry()], 'short'))->toThrow(RuntimeException::class);
    expect(fn () => $backup->decrypt(str_repeat('x', EncryptedBackup::MAX_BYTES + 1), 'password'))->toThrow(RuntimeException::class);
    expect(fn () => $backup->decrypt('otpauth://totp/alice?secret=JBSWY3DPEHPK3PXP', 'password'))->toThrow(RuntimeException::class);
});

it('previews and restores without replacing existing records', function (): void {
    fakeSecureStorage();
    $store = app(TotpStore::class);
    $store->merge([testTotpEntry()]);
    $backup = app(EncryptedBackup::class);
    $entries = $backup->decrypt($backup->encrypt([testTotpEntry(), testTotpEntry('another valid key!')], 'a good backup password'), 'a good backup password');
    expect($store->merge($entries, preview: true))->toBe(['added' => 1, 'duplicates' => 1]);
    expect($store->all())->toHaveCount(1);
    expect($store->merge($entries))->toBe(['added' => 1, 'duplicates' => 1]);
    expect($store->all())->toHaveCount(2);
    expect($store->merge($entries))->toBe(['added' => 0, 'duplicates' => 2]);
});

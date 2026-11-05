<?php

declare(strict_types=1);

namespace App\Services;

use Native\Mobile\Facades\SecureStorage;
use RuntimeException;

/** Missing and temporarily unreadable Keychain items must never mean the same thing. */
final class SecureVault
{
    public static function get(string $key): ?string
    {
        $result = SecureStorage::read($key);
        if ($result->failed() || $result->unavailable()) {
            throw new RuntimeException('Secure storage is unavailable. Unlock your phone and try again.');
        }

        return $result->value;
    }

    public static function set(string $key, ?string $value): bool
    {
        return SecureStorage::set($key, $value);
    }

    public static function delete(string $key): bool
    {
        return SecureStorage::delete($key);
    }
}

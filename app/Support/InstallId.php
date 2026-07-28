<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;
use Native\Mobile\Facades\SecureStorage;

/**
 * A stable id for THIS installation.
 *
 * Deliberately generated here rather than taken from a vendor device identifier.
 * `identifierForVendor` on iOS and `ANDROID_ID` on Android both reset when the app is
 * reinstalled and are shared across one vendor's apps — so keying enrolment on them
 * would either orphan the device on every reinstall or collide two of our own apps.
 *
 * The server treats this as the enrolment key: posting again with the same install id
 * updates the existing device rather than creating a second one, which is what makes
 * push-token rotation a plain re-POST with no separate endpoint to get wrong.
 */
final class InstallId
{
    private const KEY = 'cboxid.install_id';

    public static function get(): string
    {
        $existing = SecureStorage::get(self::KEY);

        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        $id = (string) Str::ulid();

        SecureStorage::set(self::KEY, $id);

        return $id;
    }

    public static function forget(): void
    {
        SecureStorage::delete(self::KEY);
    }
}

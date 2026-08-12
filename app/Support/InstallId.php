<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;
use Native\Mobile\Facades\SecureStorage;

/**
 * A stable id for this installation, AS SEEN BY ONE ACCOUNT.
 *
 * Deliberately generated here rather than taken from a vendor device identifier.
 * `identifierForVendor` on iOS and `ANDROID_ID` on Android both reset when the app is
 * reinstalled and are shared across one vendor's apps — so keying enrolment on them
 * would either orphan the device on every reinstall or collide two of our own apps.
 *
 * The server treats this as the enrolment key: posting again with the same install id
 * updates the existing device rather than creating a second one, which is what makes
 * push-token rotation a plain re-POST with no separate endpoint to get wrong.
 *
 * PER ACCOUNT, NOT PER DEVICE
 * ---------------------------
 * One id shared across accounts would hand every connected tenant the same identifier
 * for this handset. Two tenants comparing device lists could then tell they were
 * looking at the same phone — and by extension the same person. The id is cheap; the
 * correlation is not. Same reasoning as the per-account DPoP key.
 */
final class InstallId
{
    private const KEY = 'install_id';

    public function __construct(private readonly string $prefix) {}

    public function get(): string
    {
        $existing = SecureStorage::get($this->prefix.self::KEY);

        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        $id = (string) Str::ulid();

        SecureStorage::set($this->prefix.self::KEY, $id);

        return $id;
    }

    public function forget(): void
    {
        SecureStorage::delete($this->prefix.self::KEY);
    }
}

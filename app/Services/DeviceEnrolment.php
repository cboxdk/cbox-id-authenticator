<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\InstallId;
use Native\Mobile\Facades\Device;
use Native\Mobile\Facades\PushNotifications;
use Native\Mobile\Facades\SecureStorage;
use Native\Mobile\Facades\System;
use Throwable;

/**
 * Registers this handset with the user's Cbox ID host.
 *
 * Called after sign-in and again whenever FCM rotates the token. The server endpoint is
 * idempotent on the install id, so both paths are the same POST — there is no separate
 * rotation call to get wrong, and no ordering hazard between rotating and re-reading a
 * device id.
 */
final class DeviceEnrolment
{
    private const DEVICE_ID = 'device_id';

    public function __construct(
        private readonly CboxApi $api,
        private readonly InstallId $installId,
        private readonly string $prefix,
    ) {}

    /**
     * Ask for notification permission if it has not been decided, then register.
     *
     * Enrolment proceeds even when permission is refused. A device that cannot be pushed
     * to can still be polled, and the approvals screen refreshes on its own — losing the
     * whole app because someone tapped "Don't Allow" would be a far worse outcome than
     * losing the banner.
     *
     * The permission prompt is asked once for the handset, not once per account, so a
     * second account added later inherits the answer rather than nagging.
     */
    /**
     * @return bool whether a device record now exists; false means this handset has no
     *              push token, which is NOT a failed sign-in
     *
     * @throws EnrolmentFailed when the host refused the registration
     */
    public function enrol(?string $enrolmentToken = null): bool
    {
        if (PushNotifications::checkPermission() === 'not_determined') {
            PushNotifications::enroll()->enroll();
        }

        $pushToken = PushNotifications::getToken();

        // No token, nothing to register — and that must not fail the sign-in. Someone
        // who tapped "Don't Allow" still has a working account: the approvals list is
        // fetched with the OAuth token and refreshes on its own. Losing the account over
        // a lost banner is far the worse outcome, and treating the two as one failure is
        // exactly what this method used to do.
        if (! is_string($pushToken) || $pushToken === '') {
            return false;
        }

        $this->post($pushToken, $enrolmentToken);

        return true;
    }

    /**
     * Re-register after the push token rotates.
     *
     * Swallows failure, unlike {@see enrol()}. A rotation happens with no user watching
     * and no screen to report to, and the retry is the next rotation or the next launch;
     * throwing would surface as an unexplained crash on a background event.
     */
    public function register(?string $pushToken): bool
    {
        if (! is_string($pushToken) || $pushToken === '') {
            return false;
        }

        try {
            $this->post($pushToken);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @throws EnrolmentFailed when the host refused the registration — a spent or
     *                         expired enrolment code, a claimed install id, an
     *                         unreachable host
     */
    private function post(string $pushToken, ?string $enrolmentToken = null): void
    {
        try {
            $response = $this->api->enrolDevice(
                $this->installId->get(),
                $pushToken,
                $this->deviceName(),
                System::isIos() ? 'ios' : 'android',
                $enrolmentToken,
            );
        } catch (Throwable $e) {
            throw new EnrolmentFailed($e->getMessage(), previous: $e);
        }

        // Kept because `DELETE /devices/{id}` matches on the server's primary key, not
        // on the install id we chose. Without this, signing out could revoke the tokens
        // but never remove the device row, and the server would go on pushing to a
        // handset that has forgotten how to answer.
        $id = $response['data']['id'] ?? null;

        if (is_string($id) && $id !== '') {
            SecureStorage::set($this->prefix.self::DEVICE_ID, $id);
        }
    }

    /**
     * The server's id for this handset, once enrolled.
     */
    public function deviceId(): ?string
    {
        $id = SecureStorage::get($this->prefix.self::DEVICE_ID);

        return is_string($id) && $id !== '' ? $id : null;
    }

    public function forget(): void
    {
        SecureStorage::delete($this->prefix.self::DEVICE_ID);
    }

    /**
     * A name the user will recognise in the console's device list.
     */
    private function deviceName(): string
    {
        try {
            $info = Device::getInfo();

            $name = is_array($info) ? ($info['name'] ?? $info['model'] ?? null) : null;

            if (is_string($name) && trim($name) !== '') {
                return mb_substr(trim($name), 0, 100);
            }
        } catch (Throwable) {
            // Fall through to the generic name below.
        }

        return System::isIos() ? 'iPhone' : 'Android device';
    }
}

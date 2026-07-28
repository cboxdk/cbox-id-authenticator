<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\InstallId;
use Native\Mobile\Facades\Device;
use Native\Mobile\Facades\PushNotifications;
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
    public function __construct(private readonly CboxApi $api) {}

    /**
     * Ask for notification permission if it has not been decided, then register.
     *
     * Enrolment proceeds even when permission is refused. A device that cannot be pushed
     * to can still be polled, and the approvals screen refreshes on its own — losing the
     * whole app because someone tapped "Don't Allow" would be a far worse outcome than
     * losing the banner.
     */
    public function enrol(): bool
    {
        if (PushNotifications::checkPermission() === 'not_determined') {
            PushNotifications::enroll()->enroll();
        }

        return $this->register(PushNotifications::getToken());
    }

    /**
     * Register, or re-register, with whatever push token we currently hold.
     */
    public function register(?string $pushToken): bool
    {
        if (! is_string($pushToken) || $pushToken === '') {
            return false;
        }

        try {
            $this->api->enrolDevice(
                InstallId::get(),
                $pushToken,
                $this->deviceName(),
                System::isIos() ? 'ios' : 'android',
            );

            return true;
        } catch (Throwable) {
            // The retry is the next launch or the next token rotation. Failing loudly
            // here would strand the user on a dead-end screen mid sign-in.
            return false;
        }
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

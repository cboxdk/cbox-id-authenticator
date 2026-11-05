<?php

declare(strict_types=1);

namespace App\Services;

use Native\Mobile\Facades\PushNotifications;

/** The server consumes FCM tokens, never the SDK's raw APNs fallback. */
final readonly class DevicePush
{
    public function __construct(private DeviceSecurity $security) {}

    public function token(): ?string
    {
        return $this->security->pushAvailable() ? PushNotifications::getToken() : null;
    }

    public function permission(): string
    {
        return $this->security->pushAvailable() ? (PushNotifications::checkPermission() ?? 'unknown') : 'unavailable';
    }

    public function requestPermission(): void
    {
        if ($this->security->pushAvailable()) {
            PushNotifications::enroll()->enroll();
        }
    }
}

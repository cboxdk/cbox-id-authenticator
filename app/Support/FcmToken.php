<?php

declare(strict_types=1);

namespace App\Support;

final class FcmToken
{
    /** Reject the iOS SDK's raw APNs fallback without assuming a fixed FCM format. */
    public static function accept(?string $token): ?string
    {
        return $token === null || $token === '' || preg_match('/\A[0-9a-f]{64}\z/i', $token) === 1
            ? null : $token;
    }
}

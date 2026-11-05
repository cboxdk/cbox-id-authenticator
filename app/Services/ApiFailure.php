<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class ApiFailure extends RuntimeException
{
    public function __construct(public readonly int $status, public readonly ?string $reason = null)
    {
        parent::__construct(match (true) {
            $reason === 'device_revoked' => 'This phone is no longer trusted. Remove this account and scan a new code from Trusted devices.',
            $status === 401 => 'Your session has expired. Connect this account again.',
            $status === 403 => 'This account cannot perform this action.',
            $status === 429 => 'Too many requests. Wait a moment and try again.',
            default => 'Could not reach your Cbox ID host. Check your connection and try again.',
        });
    }
}

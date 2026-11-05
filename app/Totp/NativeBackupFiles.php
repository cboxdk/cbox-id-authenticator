<?php

declare(strict_types=1);

namespace App\Totp;

use App\Services\DeviceSecurity;

final class NativeBackupFiles
{
    public function pick(string $id): bool
    {
        return $this->call('PickBackup', ['id' => $id]);
    }

    public function save(string $id, string $encrypted): bool
    {
        return $this->call('SaveBackup', ['id' => $id, 'contents' => $encrypted]);
    }

    public function copyCode(string $code, int $expires): bool
    {
        return $this->call('CopyCode', ['code' => $code, 'expires' => $expires]);
    }

    /** @param array<string, mixed> $params */
    private function call(string $method, array $params): bool
    {
        $generation = app(DeviceSecurity::class)->generation();
        if ($generation === null || ! function_exists('nativephp_call')) {
            return false;
        }
        $raw = nativephp_call('AuthenticatorSecurity.'.$method, json_encode($params + ['generation' => $generation], JSON_THROW_ON_ERROR));
        $result = is_string($raw) ? json_decode($raw, true) : null;

        return is_array($result) && (($result['data']['success'] ?? $result['success'] ?? false) === true);
    }
}

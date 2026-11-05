<?php

declare(strict_types=1);

namespace App\Services;

final class AuthenticationBrowser
{
    public function open(string $url, string $id): bool
    {
        if (! function_exists('nativephp_call')) {
            return false;
        }

        $raw = nativephp_call('AuthenticatorSecurity.OpenAuthentication', json_encode([
            'url' => $url, 'id' => $id,
        ], JSON_THROW_ON_ERROR));
        $result = is_string($raw) ? json_decode($raw, true) : null;

        return is_array($result) && (($result['data']['success'] ?? $result['success'] ?? false) === true);
    }
}

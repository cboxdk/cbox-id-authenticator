<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\AppLanguages;

/** The native gate fails closed if its plugin is unavailable. */
final class DeviceSecurity
{
    public function configure(bool $enabled, bool $afterSignIn = false): void
    {
        if (function_exists('nativephp_call')) {
            nativephp_call('AuthenticatorSecurity.Configure', json_encode(['enabled' => $enabled, 'afterSignIn' => $afterSignIn], JSON_THROW_ON_ERROR));
        }
    }

    public function authenticate(string $id): bool
    {
        if (! function_exists('nativephp_call')) {
            return false;
        }
        $raw = nativephp_call('AuthenticatorSecurity.Authenticate', json_encode([
            'id' => $id,
        ], JSON_THROW_ON_ERROR));
        $response = is_string($raw) ? json_decode($raw, true) : null;
        $result = is_array($response) ? ($response['data'] ?? $response) : null;

        // Dispatch is not authentication. Only the native completion event can
        // confirm the fresh check; a refused or unavailable bridge cannot start it.
        return is_array($result) && ($result['started'] ?? false) === true;
    }

    public function generation(): ?int
    {
        $state = $this->state();

        return ($state['unlocked'] ?? false) === true && ($state['foreground'] ?? false) === true && is_int($state['generation'] ?? null)
            ? $state['generation'] : null;
    }

    public function canReuseAuthentication(int $generation): bool
    {
        $state = $this->state();

        return ($state['unlocked'] ?? false) === true && ($state['foreground'] ?? false) === true
            && ($state['generation'] ?? null) === $generation && ($state['recentAuthentication'] ?? false) === true;
    }

    public function locale(): string
    {
        return AppLanguages::resolve($this->state()['locale'] ?? 'en');
    }

    public function pushAvailable(): bool
    {
        return ($this->state()['pushAvailable'] ?? false) === true;
    }

    /** @return array<string, mixed> */
    private function state(): array
    {
        if (! function_exists('nativephp_call')) {
            return [];
        }
        $raw = nativephp_call('AuthenticatorSecurity.State', '{}');
        $response = is_string($raw) ? json_decode($raw, true) : null;
        $state = is_array($response) ? ($response['data'] ?? $response) : [];

        return is_array($state) ? $state : [];
    }
}

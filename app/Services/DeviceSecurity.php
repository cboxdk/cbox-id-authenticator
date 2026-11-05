<?php

declare(strict_types=1);

namespace App\Services;

use Native\Mobile\Events\Biometric\Completed;

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
        $raw = nativephp_call('Biometric.Prompt', json_encode([
            'id' => $id, 'event' => Completed::class,
        ], JSON_THROW_ON_ERROR));
        $response = is_string($raw) ? json_decode($raw, true) : null;

        // Current iOS returns an empty object; earlier bridges wrap that in success.
        // Neither represents authentication. Only Completed can authorize a decision.
        return is_array($response) && ($response['status'] ?? null) !== 'error';
    }

    public function generation(): ?int
    {
        $state = $this->state();

        return ($state['unlocked'] ?? false) === true && ($state['foreground'] ?? false) === true && is_int($state['generation'] ?? null)
            ? $state['generation'] : null;
    }

    public function locale(): string
    {
        $locale = $this->state()['locale'] ?? 'en';

        return is_string($locale) && str_starts_with(strtolower($locale), 'da') ? 'da' : 'en';
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

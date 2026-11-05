<?php

declare(strict_types=1);

namespace App\Services;

/** Verified discovery document; issuer must match the scanned HTTPS origin. */
final readonly class DiscoveredHost
{
    public function __construct(
        public string $issuer,
        public string $clientId,
    ) {}

    public function apiBase(): string
    {
        return rtrim($this->issuer, '/').'/api/v1';
    }
}

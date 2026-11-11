<?php

declare(strict_types=1);

namespace App\Services;

/** Verified discovery document; issuer must match the scanned HTTPS origin. */
final readonly class DiscoveredHost
{
    public function __construct(
        public string $issuer,
        public string $clientId,
        /** The advertised API host base, or null for the issuer's own `/api/v1`. */
        public ?string $advertisedApiBase = null,
    ) {}

    public function apiBase(): string
    {
        return $this->advertisedApiBase ?? ApiBase::legacy($this->issuer);
    }
}

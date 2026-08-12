<?php

declare(strict_types=1);

namespace App\Services;

/**
 * What `/.well-known/cbox-authenticator` said.
 *
 * The issuer, not the scanned hostname, is what identifies the account from here on:
 * a host may answer for an issuer that differs from the name typed into the QR code,
 * and every later comparison — the RFC 9207 `iss` on the authorization response, the
 * RFC 8707 `resource` on the token request — is made against this value.
 */
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

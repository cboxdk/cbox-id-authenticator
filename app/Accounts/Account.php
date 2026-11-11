<?php

declare(strict_types=1);

namespace App\Accounts;

use App\Services\ApiBase;

/**
 * One Cbox ID account this handset is connected to.
 *
 * WHY A LOCAL ID RATHER THAN THE ISSUER
 * -------------------------------------
 * The obvious key is the issuer — one host, one account — and it is wrong. Two people
 * share a phone; one person holds a personal and an admin account on the same tenant.
 * Keying on the issuer would silently collapse those into one, and the second sign-in
 * would overwrite the first's refresh token with no error anywhere. A locally minted
 * ULID lets the same host appear twice; `subject` is what distinguishes them.
 *
 * The id is also the SecureStorage namespace for everything secret this account owns —
 * see {@see keyPrefix()}. Nothing about an account may live under a fixed key, which is
 * exactly the defect this type exists to remove.
 */
final readonly class Account
{
    public function __construct(
        public string $id,
        public string $issuer,
        public string $label,
        public ?string $subject = null,
        /**
         * Where this account's devices and approvals API lives when the host advertised
         * one (`api_base`). Null — every account connected before Cbox ID v3 — keeps
         * calling `{issuer}/api/v1` exactly as before.
         */
        public ?string $apiBase = null,
    ) {}

    /**
     * The SecureStorage prefix every secret belonging to this account hangs off.
     *
     * The trailing dot is part of the prefix on purpose: without it `acct.01AB` would
     * also match `acct.01ABC…`, and a wipe of one account would take another's keys
     * with it.
     */
    public function keyPrefix(): string
    {
        return 'cboxid.acct.'.$this->id.'.';
    }

    /**
     * What the user sees in the account list.
     *
     * The host is the honest default — it is what they scanned, and it is what tells
     * two tenants apart at a glance. A subject only sharpens it once the id_token has
     * been read.
     */
    public static function labelFor(string $issuer, ?string $subject = null): string
    {
        $host = parse_url($issuer, PHP_URL_HOST);
        $host = is_string($host) && $host !== '' ? $host : $issuer;

        return $subject !== null && $subject !== '' ? $subject.' · '.$host : $host;
    }

    /**
     * @return array{id: string, issuer: string, label: string, subject: string|null, api_base?: string}
     */
    public function toArray(): array
    {
        $row = [
            'id' => $this->id,
            'issuer' => $this->issuer,
            'label' => $this->label,
            'subject' => $this->subject,
        ];

        // Written only when there is one, so an existing account's stored row is unchanged.
        if ($this->apiBase !== null) {
            $row['api_base'] = $this->apiBase;
        }

        return $row;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function fromArray(array $row): ?self
    {
        $id = $row['id'] ?? null;
        $issuer = $row['issuer'] ?? null;

        // A row missing either is unusable, and guessing a replacement would attach
        // this device's secrets to the wrong host. Drop it instead.
        if (! is_string($id) || $id === '' || ! is_string($issuer) || $issuer === '') {
            return null;
        }

        $subject = is_string($row['subject'] ?? null) ? $row['subject'] : null;
        $label = is_string($row['label'] ?? null) && $row['label'] !== ''
            ? $row['label']
            : self::labelFor($issuer, $subject);

        // Re-checked on read: the value decides where a token is sent.
        $apiBase = ApiBase::fromAdvertised($row['api_base'] ?? null, $issuer);

        return new self($id, $issuer, $label, $subject, $apiBase);
    }
}

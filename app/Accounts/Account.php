<?php

declare(strict_types=1);

namespace App\Accounts;

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
     * @return array{id: string, issuer: string, label: string, subject: string|null}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'issuer' => $this->issuer,
            'label' => $this->label,
            'subject' => $this->subject,
        ];
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

        return new self($id, $issuer, $label, $subject);
    }
}
